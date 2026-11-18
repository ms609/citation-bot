#!/usr/bin/env python3
"""Match DBLP SPIE volume candidates to publisher-supplied SPIE KBART titles.

This is an offline maintenance utility. Obtain the publisher's proceedings
volume title list (Excel) from the SPIE Digital Library Librarian Resources
page. It never scrapes protected proceedings pages and never writes runtime
metadata without an explicit subsequent review/copy step.
"""

from __future__ import annotations

import argparse
import csv
import json
import re
import sys
import unicodedata
from collections import defaultdict
from pathlib import Path
from typing import Any
from urllib.parse import urlparse

VOLUME = re.compile(r"[1-9][0-9]{2,5}\Z")
KEY = re.compile(r"([1-9][0-9]{2,5}):([12][0-9]{3})\Z")
VOLUME_URL = re.compile(r"/conference-proceedings-of-spie/([1-9][0-9]{2,5})(?:\.toc)?(?:/|\Z)", re.I)
GENERIC_TITLES = {"proceedings of spie", "spie proceedings", "conference proceedings"}
MAX_KBART_BYTES = 64 * 1024 * 1024
# Meeting dates, not just the conference year in a title, must follow any
# relaxed title variant. The publication title itself is never truncated.
EVENT_MONTH = re.compile(
    r"\b(?:jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|jun(?:e)?|"
    r"jul(?:y)?|aug(?:ust)?|sep(?:tember)?|oct(?:ober)?|nov(?:ember)?|"
    r"dec(?:ember)?)\b", re.I,
)
EVENT_YEAR = re.compile(r"\b(?:19|20)\d{2}\b")
EVENT_DATE = re.compile(EVENT_MONTH.pattern + r".{0,40}\b((?:19|20)\d{2})\b", re.I)
ACRONYM_STOPWORDS = {"a", "an", "and", "at", "by", "for", "in", "of", "on", "the", "to", "with"}


def normalized(value: str) -> str:
    value = unicodedata.normalize("NFKC", value).casefold()
    value = value.replace("’", "'").replace("‘", "'").replace("‐", "-").replace("–", "-")
    return " ".join(value.split())


def title_agrees(publisher: str, dblp: str) -> bool:
    """Accept exact/prefix titles and narrowly scoped DBLP venue-date variants.

    The publisher title is always retained verbatim. DBLP may add a fixed
    proceedings prefix, a conference-year suffix, or an acronym in commas
    instead of publisher parentheses. These variants must end with a meeting
    month and year, and never tolerate word substitution or missing editions.
    """
    p = normalized(publisher).rstrip(".,;:")
    d = normalized(dblp)
    if not p:
        return False
    if d == p or any(d.startswith(p + suffix) for suffix in
                     (",", ":", ";", ".", " - ", " (")):
        return True

    def matches_venue_prefix(prefix: str, year: str | None = None) -> bool:
        if not d.startswith(prefix + ", "):
            return False
        tail = d[len(prefix) + 2:]
        years = EVENT_DATE.findall(tail)
        return bool(years and (year is None or year in years))

    # DBLP sometimes adds these literal bibliographic prefixes.
    variants = [p, "proceedings of the conference on " + p,
                "electronic imaging: " + p]
    if any(matches_venue_prefix(variant) for variant in variants):
        return True
    # Some DBLP titles append the conference year, which the publisher
    # omits from the proceedings title. Verify that the meeting date agrees.
    for year in set(EVENT_YEAR.findall(d)):
        if matches_venue_prefix(p + " " + year, year) or matches_venue_prefix(
            "proceedings of the conference on " + p + " " + year, year
        ):
            return True

    # SPIE: "Conference (ICMV 2017)"; DBLP: "Conference, ICMV 2017, Rome...".
    acronym = re.fullmatch(r"(.+?) \(([a-z][a-z0-9-]+) ((?:19|20)\d{2})\)", p)
    if acronym:
        base, short, year = acronym.groups()
        return matches_venue_prefix(f"{base}, {short} {year}", year)

    # SPIE: "Visualization and Data Analysis 2009";
    # DBLP: "Visualization and Data Analysis, VDA 2009, San Jose...".
    dated = re.fullmatch(r"(.+?) ((?:19|20)\d{2})", p)
    if dated:
        base, year = dated.groups()
        alias = re.match(rf"{re.escape(base)}, ([a-z]{{3,12}}) {year}(?=, )", d)
        if alias:
            initials = "".join(word[0] for word in re.findall(r"[a-z]+", base)
                               if word not in ACRONYM_STOPWORDS)
            short = alias.group(1)
            if initials.endswith(short) and matches_venue_prefix(
                f"{base}, {short} {year}", year
            ):
                return True
    return False


def publisher_url(url: str, volume: str) -> bool:
    """Validate a source URL rather than trusting arbitrary links in a workbook."""
    try:
        parsed = urlparse(url)
        host = (parsed.hostname or "").lower()
        path = parsed.path
        match = VOLUME_URL.search(path)
        return (parsed.scheme == "https" and not parsed.username and
                (host == "spiedigitallibrary.org" or host.endswith(".spiedigitallibrary.org")) and
                match is not None and match.group(1) == volume)
    except ValueError:
        return False


def volume_from_row(row: dict[str, str]) -> tuple[str | None, str | None]:
    """Require volume identifiers in publisher fields to agree."""
    candidates: set[str] = set()
    for column in ("monograph_volume", "num_first_vol_online"):
        text = row.get(column, "").strip()
        if text:
            # Excel may serialize an integer cell as 6505.0.
            if re.fullmatch(r"[1-9][0-9]{2,5}(?:\.0)?", text):
                candidates.add(text.removesuffix(".0"))
            else:
                return None, "invalid-publisher-volume"
    link = row.get("title_url", "").strip()
    if link:
        match = VOLUME_URL.search(urlparse(link).path)
        if match:
            candidates.add(match.group(1))
    if not candidates:
        return None, "missing-publisher-volume"
    if len(candidates) != 1:
        return None, "conflicting-publisher-volume"
    return next(iter(candidates)), None


def publisher_years(row: dict[str, str]) -> tuple[int | None, int | None]:
    """Return distinct KBART print and online years, never conflating them.

    An older printed volume may be digitized or first hosted years later.
    Its online publication date is not a conflicting print publication date.
    """
    def year(field: str) -> int | None:
        text = row.get(field, "").strip()
        match = re.match(r"^([12][0-9]{3})(?:\b|-|/|\Z)", text)
        return int(match.group(1)) if match else None

    return (year("date_monograph_published_print"),
            year("date_monograph_published_online"))


def canonical_row(row: dict[str, str]) -> dict[str, Any] | None:
    volume, _ = volume_from_row(row)
    if volume is None:
        return None
    title = " ".join(row.get("publication_title", "").split())
    if len(title) < 10 or len(title) > 1000 or normalized(title) in GENERIC_TITLES:
        return None
    publisher = normalized(row.get("publisher_name", ""))
    if publisher and "spie" not in publisher:
        return None
    kind = normalized(row.get("publication_type", ""))
    if kind and kind not in ("monograph", "book", "conference proceedings", "proceedings"):
        return None
    link = row.get("title_url", "").strip()
    if link and not publisher_url(link, volume):
        return None
    if not link:
        link = f"https://www.spiedigitallibrary.org/conference-proceedings-of-spie/{volume}"
    print_year, online_year = publisher_years(row)
    return {"volume": volume, "title": title, "source": link,
            "year": print_year, "online_year": online_year}


def kbart_records(path: Path) -> tuple[dict[str, dict[str, Any]], set[str]]:
    try:
        from openpyxl import load_workbook
    except ImportError as exc:
        raise ValueError("Install openpyxl: python3 -m pip install openpyxl") from exc
    if not path.is_file() or path.stat().st_size > MAX_KBART_BYTES:
        raise ValueError("SPIE title list is missing or exceeds the 64 MiB limit")
    if path.suffix.lower() != ".xlsx":
        raise ValueError("Expected the SPIE publisher KBART title list in .xlsx format")
    workbook = load_workbook(path, read_only=True, data_only=True)
    found: dict[str, dict[str, Any]] = {}
    conflicts: set[str] = set()
    try:
        sheet_count = 0
        for sheet in workbook:
            header = None
            for position, cells in enumerate(sheet.iter_rows(values_only=True)):
                values = [str(v).strip() if v is not None else "" for v in cells]
                if header is None:
                    possible = [re.sub(r"\W+", "_", v.lower()).strip("_") for v in values]
                    if ("publication_title" in possible and
                        any(field in possible for field in ("monograph_volume", "num_first_vol_online", "title_url"))):
                        header = possible
                        sheet_count += 1
                    elif position >= 20:
                        break
                    continue
                record = dict(zip(header, values))
                entry = canonical_row(record)
                if entry is None:
                    continue
                volume = entry["volume"]
                if volume in found:
                    # Online dates describe electronic availability, not a
                    # different volume/edition. Do not manufacture conflicts
                    # when otherwise identical rows have different online dates.
                    current = found[volume]
                    if any(current[field] != entry[field]
                           for field in ("title", "source", "year")):
                        conflicts.add(volume)
                else:
                    found[volume] = entry
        if sheet_count == 0:
            raise ValueError("No KBART sheet with publication_title and volume columns found")
    finally:
        workbook.close()
    return found, conflicts


def load_candidates(path: Path) -> list[dict[str, str]]:
    result = []
    if path.suffix.lower() == ".csv":
        with path.open(encoding="utf-8-sig", newline="") as stream:
            for row in csv.DictReader(stream):
                result.append({"key": row.get("key", ""), "title": row.get("dblp_title", "")})
    elif path.suffix.lower() == ".jsonl":
        for raw in path.read_text(encoding="utf-8").splitlines():
            if raw.strip():
                entry = json.loads(raw)
                result.append({"key": entry.get("key") or "", "title": entry.get("data", {}).get("title", "")})
    else:
        raise ValueError("Expected candidate review file with .csv or .jsonl suffix")
    return result


def lookup(
    candidates: list[dict[str, str]], publisher: dict[str, dict[str, Any]],
    publisher_conflicts: set[str],
) -> tuple[dict[str, dict[str, str]], list[dict[str, Any]], dict[str, int]]:
    by_key: dict[str, set[str]] = defaultdict(set)
    for candidate in candidates:
        by_key[candidate["key"]].add(candidate["title"])
    titles = {}
    audit = []
    stats = {"matched": 0, "review": 0}
    for key, dblp_titles in sorted(by_key.items()):
        matched = KEY.fullmatch(key)
        if matched is None:
            audit.append({"key": key, "issues": ["invalid-dblp-key"]})
            continue
        volume, year = matched.groups()
        issues = []
        if len(dblp_titles) != 1:
            issues.append("duplicate-dblp-volume-year")
        if volume in publisher_conflicts:
            issues.append("conflicting-publisher-records")
        entry = publisher.get(volume)
        if entry is None:
            issues.append("missing-publisher-record")
        else:
            if entry["year"] is not None:
                # Compare DBLP's proceedings year with SPIE's *print* year.
                # Online availability may occur much later (retrospective
                # digitization), so disagreement with it is not a conflict.
                if abs(entry["year"] - int(year)) > 1:
                    issues.append("publisher-year-mismatch")
            elif (entry["online_year"] is not None and
                  abs(entry["online_year"] - int(year)) > 1):
                # No print date corroborates DBLP here. An online year far
                # from DBLP's year is not evidence of a wrong print date;
                # leave the record for review rather than making one up.
                issues.append("publisher-print-year-unavailable")
            if len(dblp_titles) == 1 and not title_agrees(entry["title"], next(iter(dblp_titles))):
                issues.append("publisher-dblp-title-mismatch")
        if issues:
            audit.append({"key": key, "issues": issues, "dblp_titles": sorted(dblp_titles),
                          "publisher": entry})
            continue
        assert entry is not None
        titles[key] = {"title": entry["title"], "source": entry["source"]}
        stats["matched"] += 1
    stats["review"] = len(audit)
    return titles, audit, stats


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--candidates", required=True, type=Path,
                        help="DBLP review CSV or JSONL (the generator --review output)")
    parser.add_argument("--spie-kbart", required=True, type=Path,
                        help="SPIE publisher proceedings volume title list workbook (.xlsx)")
    parser.add_argument("--titles", required=True, type=Path,
                        help="Output JSON mapping for build_spie_volume_editors.py --titles")
    parser.add_argument("--audit", required=True, type=Path,
                        help="Unmatched/ambiguous records (JSON Lines)")
    args = parser.parse_args(argv)
    inputs = {args.candidates.resolve(), args.spie_kbart.resolve()}
    outputs = {args.titles.resolve(), args.audit.resolve()}
    if len(outputs) != 2 or inputs & outputs:
        parser.error("Output paths must differ and must not overwrite input files")
    try:
        publisher, conflicts = kbart_records(args.spie_kbart)
        titles, audit, stats = lookup(load_candidates(args.candidates), publisher, conflicts)
        from build_spie_volume_editors import write_atomic
        write_atomic(args.titles, json.dumps(titles, indent=2, ensure_ascii=False) + "\n")
        write_atomic(args.audit, "".join(json.dumps(row, ensure_ascii=False) + "\n" for row in audit))
    except (ValueError, OSError, json.JSONDecodeError) as exc:
        print(f"Error: {exc}", file=sys.stderr)
        return 1
    print(f"Publisher title matches: {stats['matched']}; need review: {stats['review']}; "
          f"publisher title list volumes: {len(publisher)}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
