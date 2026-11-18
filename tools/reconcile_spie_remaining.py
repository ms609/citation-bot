#!/usr/bin/env python3
"""Apply explicitly reviewed SPIE metadata corrections to offline DBLP candidates.

This is a one-time, auditable maintenance step, not part of Citation Bot runtime.
Requires the streaming generator's candidate JSON, review JSONL and the SPIE
KBART lookup audit JSONL. A fixed manifest pins each original DBLP title,
ordered editor names, publisher title, publisher print year and intended action.
No fuzzy matching, HTTP requests, or implicit year corrections are performed.
"""

from __future__ import annotations

import argparse
import copy
import json
import re
import sys
from pathlib import Path
from typing import Any
from urllib.parse import urlparse

ROOT = Path(__file__).resolve().parent
DEFAULT_MANIFEST = ROOT / "data" / "spie_reviewed_reconciliations.json"
KEY = re.compile(r"([1-9][0-9]{2,5}):([12][0-9]{3})\Z")
MAX_CANDIDATES_BYTES = 512 * 1024
MAX_AUDIT_BYTES = 8 * 1024 * 1024


def read_json(path: Path, max_bytes: int) -> Any:
    if not path.is_file() or path.stat().st_size > max_bytes:
        raise ValueError(f"Missing or oversized input: {path}")
    return json.loads(path.read_text(encoding="utf-8"))


def read_jsonl(path: Path) -> dict[str, dict[str, Any]]:
    if not path.is_file() or path.stat().st_size > MAX_AUDIT_BYTES:
        raise ValueError(f"Missing or oversized input: {path}")
    result: dict[str, dict[str, Any]] = {}
    with path.open(encoding="utf-8") as stream:
        for line in stream:
            if not line.strip():
                continue
            value = json.loads(line)
            key = value.get("key") if isinstance(value, dict) else None
            if not isinstance(key, str) or not KEY.fullmatch(key) or key in result:
                raise ValueError(f"Invalid or repeated review key in {path}: {key!r}")
            result[key] = value
    return result


def is_spie_volume_url(url: str, volume: str) -> bool:
    if not isinstance(url, str):
        return False
    parsed = urlparse(url)
    return (
        parsed.scheme == "https"
        and (parsed.hostname or "").lower() in ("spiedigitallibrary.org", "www.spiedigitallibrary.org")
        and parsed.username is None
        and parsed.password is None
        and parsed.port in (None, 443)
        and not parsed.query
        and not parsed.fragment
        and parsed.path.rstrip("/") == f"/conference-proceedings-of-spie/{volume}"
    )


def reconcile(
    candidates: dict[str, Any],
    review: dict[str, dict[str, Any]],
    publisher_audit: dict[str, dict[str, Any]],
    manifest: dict[str, dict[str, Any]],
) -> tuple[dict[str, Any], list[dict[str, Any]], list[str]]:
    """Return final records, residual review rows and keys added this run."""
    if not isinstance(candidates, dict) or not isinstance(manifest, dict):
        raise ValueError("Expected candidate and manifest JSON objects")
    output = copy.deepcopy(candidates)
    unresolved = copy.deepcopy(review)
    added: list[str] = []
    for key, rule in manifest.items():
        match = KEY.fullmatch(key)
        if match is None or not isinstance(rule, dict):
            raise ValueError(f"Invalid correction rule: {key}")
        volume, original_year = match.groups()
        target = rule.get("target_key")
        target_match = KEY.fullmatch(target) if isinstance(target, str) else None
        action = rule.get("action")
        if target_match is None or target_match.group(1) != volume or action not in ("add", "correct-year", "exclude"):
            raise ValueError(f"Invalid target/action for {key}")
        if action == "add" and target != key:
            raise ValueError(f"Unexpected key change for {key}")
        if action == "correct-year" and target == key:
            raise ValueError(f"Missing corrected year for {key}")
        if target_match.group(2) != str(rule.get("publisher_year")):
            raise ValueError(f"Target does not match publisher print year for {key}")
        source_record = review.get(key)
        audit_record = publisher_audit.get(key)
        if source_record is None or audit_record is None:
            raise ValueError(f"Missing DBLP or publisher audit evidence for {key}")
        if set(source_record.get("issues", [])) != {"title-includes-venue-date", "canonical-title-not-reviewed"}:
            raise ValueError(f"Other unresolved issues for {key}")
        raw_names = source_record.get("dblp_editors")
        data = source_record.get("data")
        expected = rule.get("dblp_editors")
        if (
            not isinstance(data, dict)
            or raw_names != expected
            or not isinstance(expected, list)
            or not expected
            or not isinstance(data.get("editors"), list)
            or len(data["editors"]) != len(expected)
        ):
            raise ValueError(f"Incomplete/changed editor roster for {key}")
        if data.get("title") != rule.get("dblp_title") or audit_record.get("dblp_titles") != [rule.get("dblp_title")]:
            raise ValueError(f"Changed DBLP title for {key}")
        db_url = f"https://dblp.org/rec/{source_record.get('dblp_key', '')}"
        if data.get("sources") != [db_url] or not db_url.startswith("https://dblp.org/rec/conf/"):
            raise ValueError(f"Invalid DBLP provenance for {key}")
        publisher = audit_record.get("publisher")
        if not isinstance(publisher, dict):
            raise ValueError(f"Missing SPIE publisher metadata for {key}")
        if (
            publisher.get("volume") != volume
            or publisher.get("title") != rule.get("publisher_title")
            or publisher.get("year") != rule.get("publisher_year")
            or not is_spie_volume_url(publisher.get("source"), volume)
        ):
            raise ValueError(f"Changed or invalid publisher record for {key}")
        if action == "exclude":
            # DBLP incorrectly repeats volume 12039 as both 2022 and 2023.
            # This is NOT a valid new record and must never overwrite 2022.
            if audit_record.get("issues") != ["publisher-dblp-title-mismatch"]:
                raise ValueError(f"Unexpected publisher audit reason for {key}")
            existing = output.get(target)
            expected_existing_editors = [
                {"given": "John E.", "family": "Tomaszewski"},
                {"given": "Aaron D.", "family": "Ward"},
                {"given": "Richard M.", "family": "Levenson"},
            ]
            if (
                not isinstance(existing, dict)
                or existing.get("title") != rule.get("publisher_title")
                or existing.get("editors") != expected_existing_editors
                or publisher["source"] not in existing.get("sources", [])
            ):
                raise ValueError(f"Expected distinct correct volume missing: {target}")
            if key in output:
                raise ValueError(f"Incorrect DBLP record already included: {key}")
            unresolved[key]["issues"] = ["incorrect-dblp-volume"]
            unresolved[key]["correct_volume_key"] = target
            continue
        if action == "add" and audit_record.get("issues") != ["publisher-dblp-title-mismatch"]:
            raise ValueError(f"Unexpected publisher audit reason for {key}")
        if action == "correct-year" and audit_record.get("issues") != ["publisher-year-mismatch"]:
            raise ValueError(f"Unexpected publisher-year audit reason for {key}")
        editors = data["editors"]
        if not all(
            isinstance(e, dict)
            and isinstance(e.get("given"), str)
            and e["given"].strip()
            and isinstance(e.get("family"), str)
            and e["family"].strip()
            for e in editors
        ):
            raise ValueError(f"Malformed editor in {key}")
        corrected = {"title": publisher["title"], "editors": editors, "sources": [db_url, publisher["source"]]}
        if target in output:
            if output[target] != corrected:
                raise ValueError(f"Conflicting existing record for {target}")
        else:
            output[target] = corrected
            added.append(target)
        del unresolved[key]
    if any(not KEY.fullmatch(key) for key in output):
        raise ValueError("Invalid candidate volume/year key")
    output = dict(sorted(output.items(), key=lambda item: tuple(map(int, item[0].split(":")))))
    return output, [unresolved[key] for key in sorted(unresolved)], added


def write_atomic(path: Path, value: str) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    temporary = path.with_name(path.name + ".tmp")
    try:
        temporary.write_text(value, encoding="utf-8")
        temporary.replace(path)
    finally:
        temporary.unlink(missing_ok=True)


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--candidates", required=True, type=Path)
    parser.add_argument("--review", required=True, type=Path)
    parser.add_argument("--publisher-audit", required=True, type=Path)
    parser.add_argument("--manifest", type=Path, default=DEFAULT_MANIFEST)
    parser.add_argument("--output", required=True, type=Path)
    parser.add_argument("--remaining", required=True, type=Path)
    args = parser.parse_args(argv)
    inputs = {p.resolve() for p in (args.candidates, args.review, args.publisher_audit, args.manifest)}
    outputs = {args.output.resolve(), args.remaining.resolve()}
    if len(outputs) != 2 or inputs & outputs:
        parser.error("Output paths must differ and must not overwrite inputs")
    try:
        candidates = read_json(args.candidates, MAX_CANDIDATES_BYTES)
        review = read_jsonl(args.review)
        audit = read_jsonl(args.publisher_audit)
        manifest = read_json(args.manifest, 64 * 1024)
        output, remaining, added = reconcile(candidates, review, audit, manifest)
        encoded = json.dumps(output, indent=2, ensure_ascii=False) + "\n"
        if len(encoded.encode("utf-8")) > MAX_CANDIDATES_BYTES:
            raise ValueError("Output exceeds the runtime JSON loader 512 KiB limit")
        write_atomic(args.output, encoded)
        write_atomic(args.remaining, "".join(json.dumps(row, ensure_ascii=False) + "\n" for row in remaining))
    except (OSError, ValueError, json.JSONDecodeError) as exc:
        print(f"Error: {exc}", file=sys.stderr)
        return 1
    print(
        f"Reconciled {len(added)} records ({', '.join(added)}); "
        f"final volume records: {len(output)}; remaining audit items: {len(remaining)}"
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
