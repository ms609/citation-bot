#!/usr/bin/env python3
"""Create reviewable SPIE editor JSON from the DBLP XML dump.

This is an offline maintenance utility, not part of Citation Bot's live runtime.
Requires Python 3.10+ and lxml (pip install lxml).
"""

from __future__ import annotations

import argparse
import gzip
import json
import re
import sys
from pathlib import Path
from typing import Any, Iterator
from urllib.parse import quote, urlparse

try:
    from lxml import etree
except ImportError as exc:
    raise SystemExit("Install lxml first: python3 -m pip install lxml") from exc

SERIES = {"spie proceedings", "proceedings of spie"}
VOLUME = re.compile(r"[1-9][0-9]{2,5}\Z")
YEAR = re.compile(r"[12][0-9]{3}\Z")
SUFFIXES = {"jr", "sr", "ii", "iii", "iv", "v", "vi"}
PARTICLES = {
    "al", "ap", "bin", "da", "de", "del", "della", "der", "di", "dos",
    "du", "el", "ibn", "la", "le", "van", "von", "ten", "ter", "st",
}
UNSAFE = re.compile(r"[{}<>|\[\]\x00-\x1f]")
# Venue/date suffixes appear in several orders: e.g. "San Jose, CA, USA,
# January 28, 2007" and "San Diego, CA, USA, 16-21 February 2019".
# This is a review hint only: new records also require an explicit
# maintainer-reviewed canonical title before inclusion in the runtime JSON.
MONTH = re.compile(
    r"\b(?:Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|June?|"
    r"July?|Aug(?:ust)?|Sep(?:tember)?|Oct(?:ober)?|Nov(?:ember)?|Dec(?:ember)?)\b",
    re.IGNORECASE,
)
FOUR_DIGIT_YEAR = re.compile(r"\b(?:19|20)\d{2}\b")


def title_contains_venue_date(title: str) -> bool:
    """Detect likely meeting-date suffixes; never strip titles automatically."""
    if "," not in title:
        return False
    # DBLP titles may end with a year followed by ', Proceedings' or similar.
    # Look at the bibliographic tail to avoid interpreting a year in the
    # principal title (e.g. 'Medical Imaging 2019: Image Processing') as a date.
    tail = title[title.find(",") + 1:]
    return bool(MONTH.search(tail) and FOUR_DIGIT_YEAR.search(tail))


def normalize(value: str) -> str:
    return " ".join(value.split()).casefold()


def text_of(element: Any) -> str:
    return " ".join("".join(element.itertext()).split())


def one_field(record: Any, tag: str) -> str | None:
    fields = record.findall(tag)
    if len(fields) != 1:
        return None
    return text_of(fields[0])


def split_editor(full_name: str) -> dict[str, str] | None:
    """Handle simple Western-order names; reject ambiguous or tagged names."""
    name = " ".join(full_name.split())
    if not name or len(name) > 160 or UNSAFE.search(name):
        return None
    pieces = name.split(" ")
    # DBLP appends four-digit numeric IDs for homonyms (e.g. "Peng Li 0047").
    # The trailing code is not part of the editor's printed name. Strip only
    # a zero-prefixed code in the final position; do not strip other digits or
    # silently drop an editor from a proceedings roster.
    if len(pieces) >= 3 and re.fullmatch(r"0\d{3}", pieces[-1]):
        pieces.pop()
    if len(pieces) < 2 or any(re.fullmatch(r"\d{4}", p) for p in pieces):
        return None
    suffix = ""
    if pieces[-1].strip(".,").casefold() in SUFFIXES:
        suffix = " " + pieces.pop()
    if len(pieces) < 2:
        return None
    # 'van der ...' and other multiword surnames require explicit review.
    if any(p.strip(".,").casefold() in PARTICLES for p in pieces[1:-1]):
        return None
    given, family = " ".join(pieces[:-1]), pieces[-1] + suffix
    if not given or not family or given.endswith(",") or "," in family:
        return None
    return {"given": given, "family": family}


class LocalDtdResolver(etree.Resolver):
    """Resolve only the named DBLP DTD; never fetch remote entities."""

    def __init__(self, dtd: Path) -> None:
        super().__init__()
        self.dtd = dtd

    def resolve(self, system_url: str, public_id: str, context: Any) -> Any:
        if system_url != "dblp.dtd":
            raise ValueError(f"Unexpected external XML resource: {system_url!r}")
        return self.resolve_filename(str(self.dtd), context)


def records(xml_path: Path, dtd_path: Path) -> Iterator[Any]:
    """Read gzipped or plain XML with bounded, per-record tree retention."""
    parser = etree.XMLPullParser(
        events=("start", "end"), load_dtd=True, resolve_entities=True,
        no_network=True, recover=False, huge_tree=False,
    )
    parser.resolvers.add(LocalDtdResolver(dtd_path.resolve()))
    opener = gzip.open if xml_path.suffix == ".gz" else open
    root_seen = False
    try:
        with opener(xml_path, "rb") as stream:
            while chunk := stream.read(128 * 1024):
                parser.feed(chunk)
                for event, node in parser.read_events():
                    if event == "start":
                        if not root_seen:
                            if node.tag != "dblp":
                                raise ValueError("Expected a DBLP <dblp> document root")
                            root_seen = True
                        continue
                    parent = node.getparent()
                    if parent is None or parent.tag != "dblp":
                        continue
                    if node.tag == "proceedings":
                        yield node
                    node.clear()
                    # Also clear earlier completed siblings of non-proceedings records.
                    while node.getprevious() is not None:
                        del parent[0]
        parser.close()
    except (OSError, etree.XMLSyntaxError) as exc:
        raise ValueError(f"Unable to read DBLP XML: {exc}") from exc
    if not root_seen:
        raise ValueError("Empty DBLP document")


def extract(record: Any) -> tuple[str | None, dict[str, Any], list[str]] | None:
    series = one_field(record, "series")
    if series is None or normalize(series) not in SERIES:
        return None
    key = record.get("key", "")
    volume, year, title = (one_field(record, name) for name in ("volume", "year", "title"))
    editors = [text_of(element) for element in record.findall("editor")]
    source = "https://dblp.org/rec/" + quote(key, safe="/-._~") if key.startswith("conf/") else ""
    record_id = f"{volume}:{year}" if volume and year else None
    issues: list[str] = []
    if not (volume and VOLUME.fullmatch(volume) and year and YEAR.fullmatch(year)):
        issues.append("invalid-or-missing-volume-or-year")
    if not title or len(title) > 2000:
        issues.append("invalid-or-missing-title")
    elif title_contains_venue_date(title):
        issues.append("title-includes-venue-date")
    if not source:
        issues.append("invalid-or-missing-dblp-key")
    if not 1 <= len(editors) <= 30:
        issues.append("invalid-editor-count")
    parsed = [split_editor(name) for name in editors]
    if any(name is None for name in parsed):
        issues.append("editor-name-needs-review")
    candidate = {
        "title": title or "",
        # Never expose a shortened roster as a valid-looking candidate.
        "editors": [] if any(name is None for name in parsed) else parsed,
        "sources": [source] if source else [],
    }
    return record_id, {"data": candidate, "dblp_editors": editors, "dblp_key": key}, issues




def valid_spie_source(url: str, volume: str) -> bool:
    """Allow only a SPIE proceedings volume URL for the matched volume."""
    try:
        parsed = urlparse(url)
        host = (parsed.hostname or "").lower()
        return (parsed.scheme == "https" and parsed.username is None and
                (host == "spiedigitallibrary.org" or host.endswith(".spiedigitallibrary.org")) and
                re.fullmatch(r"/conference-proceedings-of-spie/" + re.escape(volume) +
                             r"(?:\.toc)?/?", parsed.path, re.IGNORECASE) is not None)
    except ValueError:
        return False


def load_json_object(path: Path | None) -> dict[str, Any]:
    if path is None:
        return {}
    value = json.loads(path.read_text(encoding="utf-8"))
    if not isinstance(value, dict):
        raise ValueError(f"Expected a JSON object in {path}")
    return value


def build(
    xml_path: Path, dtd_path: Path, existing: dict[str, Any] | None = None,
    titles: dict[str, Any] | None = None,
) -> tuple[dict[str, Any], list[dict[str, Any]], dict[str, int]]:
    """Build candidates, preserving previously reviewed records when supplied."""
    output: dict[str, Any] = dict(existing or {})
    review: list[dict[str, Any]] = []
    seen: dict[str, dict[str, Any]] = {}
    conflicted: set[str] = set()
    stats = {"proceedings": 0, "spie": 0, "new": 0, "review": 0}
    for record in records(xml_path, dtd_path):
        stats["proceedings"] += 1
        result = extract(record)
        if result is None:
            continue
        stats["spie"] += 1
        key, details, issues = result
        candidate = details["data"]
        # DBLP often includes a venue/date in the bibliographic title.
        # Accept either a maintainer-reviewed title string or an SPIE KBART
        # mapping with independently sourced title and publisher URL.
        if key is not None and titles and key in titles:
            override = titles[key]
            publisher_source = None
            if isinstance(override, dict):
                publisher_source = override.get("source")
                override = override.get("title")
            if not isinstance(override, str) or not override.strip() or len(override) > 2000:
                issues.append("invalid-title-override")
            elif publisher_source is not None and (
                not isinstance(publisher_source, str) or
                not valid_spie_source(publisher_source, key.split(":")[0])
            ):
                issues.append("invalid-publisher-source")
            else:
                candidate["title"] = " ".join(override.split())
                if publisher_source:
                    candidate["sources"].append(publisher_source)
                issues = [issue for issue in issues if issue != "title-includes-venue-date"]
        elif key is not None and key not in (existing or {}):
            issues.append("canonical-title-not-reviewed")
        if key is None:
            review.append({"key": None, "issues": issues, **details})
            continue
        if key in conflicted:
            review.append({"key": key, "issues": ["duplicate-volume-year"], **details})
            continue
        if key in seen:
            if candidate != seen[key]:
                conflicted.add(key)
                if key not in (existing or {}):
                    output.pop(key, None)
                review.append({"key": key, "issues": ["duplicate-volume-year"], **details})
            continue
        seen[key] = candidate
        if key in (existing or {}):
            # Never overwrite verified data; differing editors need review.
            old = (existing or {})[key]
            if not isinstance(old, dict) or old.get("editors") != candidate["editors"]:
                review.append({"key": key, "issues": ["conflicts-with-existing"], **details})
            continue
        if issues:
            review.append({"key": key, "issues": issues, **details})
            continue
        output[key] = candidate
        stats["new"] += 1
    for key in conflicted:
        if key not in (existing or {}):
            output.pop(key, None)
    stats["new"] = len(output) - len(existing or {})
    stats["review"] = len(review)
    return dict(sorted(output.items(), key=lambda item: tuple(map(int, item[0].split(":"))))), review, stats


def write_atomic(path: Path, content: str) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    staging = path.with_name(path.name + ".tmp")
    try:
        with staging.open("w", encoding="utf-8", newline="\n") as stream:
            stream.write(content)
        staging.replace(path)
    finally:
        staging.unlink(missing_ok=True)


def main(argv: list[str] | None = None) -> int:
    argument_parser = argparse.ArgumentParser(description=__doc__)
    argument_parser.add_argument("--xml", type=Path, required=True, help="DBLP dblp.xml.gz or dblp.xml")
    argument_parser.add_argument("--dtd", type=Path, required=True, help="Local dblp.dtd")
    argument_parser.add_argument("--output", type=Path, required=True, help="New reviewable JSON output")
    argument_parser.add_argument("--review", type=Path, required=True, help="Records requiring human review (JSONL)")
    argument_parser.add_argument("--existing", type=Path, help="Existing verified JSON to preserve")
    argument_parser.add_argument("--titles", type=Path, help="JSON map from volume:year to canonical title or SPIE publisher title/source")
    args = argument_parser.parse_args(argv)
    if not args.xml.is_file() or not args.dtd.is_file():
        argument_parser.error("--xml and --dtd must point to existing files")
    destinations = [args.output.resolve(), args.review.resolve()]
    if destinations[0] == destinations[1]:
        argument_parser.error("--output and --review must be different files")
    inputs = [p.resolve() for p in (args.xml, args.dtd, args.existing, args.titles) if p]
    if any(dest in inputs for dest in destinations):
        argument_parser.error("Outputs may not overwrite an input; review and copy deliberately")
    try:
        existing = load_json_object(args.existing)
        titles = load_json_object(args.titles)
        output, review, stats = build(args.xml, args.dtd, existing, titles)
    except (ValueError, OSError, json.JSONDecodeError) as exc:
        print(f"Error: {exc}", file=sys.stderr)
        return 1
    encoded = json.dumps(output, indent=2, ensure_ascii=False) + "\n"
    audit = "".join(json.dumps(item, ensure_ascii=False) + "\n" for item in review)
    write_atomic(args.output, encoded)
    write_atomic(args.review, audit)
    print(f"Processed {stats['proceedings']} proceedings, {stats['spie']} SPIE; "
          f"generated {stats['new']} new entries, {stats['review']} for review.")
    print(f"Output: {args.output} ({len(encoded.encode('utf-8'))} bytes); review: {args.review}")
    if len(encoded.encode("utf-8")) > 512 * 1024:
        print("WARNING: PR #6487's runtime loader rejects JSON over 512 KiB; "
              "raise its bounded limit or curate a smaller output before deployment.",
              file=sys.stderr)
    return 0


if __name__ == "__main__":
    sys.exit(main())
