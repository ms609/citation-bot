#!/usr/bin/env python3
"""Summarize missed source lines from Clover without uploading source or secrets."""

import argparse
import re
from pathlib import Path
from xml.etree import ElementTree as ET

SAFE_SOURCE = re.compile(r"^src/[A-Za-z0-9_./-]+\.php$")


def coverage_gaps(source: Path, root: Path, limit: int = 25) -> str:
    """Return a concise, pathname-validated report of missed statement lines."""
    if not source.is_file():
        return "Clover coverage file was not generated.\n"
    try:
        tree = ET.parse(source)
    except (ET.ParseError, OSError, ValueError):
        return "Clover coverage file could not be parsed.\n"

    records = []
    for file_node in tree.iter("file"):
        path = file_node.get("name", "")
        try:
            relative = Path(path).resolve().relative_to(root.resolve()).as_posix()
        except (ValueError, OSError, RuntimeError):
            continue
        if not SAFE_SOURCE.fullmatch(relative) or ".." in Path(relative).parts:
            continue
        statements = []
        for line in file_node.iter("line"):
            if line.get("type") != "stmt":
                continue
            try:
                number = int(line.get("num", "0"))
                count = int(line.get("count", "-1"))
            except (TypeError, ValueError):
                continue
            if number > 0 and count >= 0:
                statements.append((number, count))
        if not statements:
            continue
        missed = sorted(number for number, count in statements if count == 0)
        if missed:
            records.append((len(missed), relative, len(statements), missed))

    records.sort(key=lambda record: (-record[0], record[1]))
    lines = [
        "# Offline statement coverage gaps",
        "",
        "Only safe relative PHP paths and line numbers are included; no source or log content.",
        "The list is a diagnostic aid, not an alternative coverage threshold.",
        "",
        "| Source | Uncovered statements | Measured statements | Missed line numbers (first 80) |",
        "| --- | ---: | ---: | --- |",
    ]
    for missed_count, path, total, missing in records[:limit]:
        display = ", ".join(str(n) for n in missing[:80])
        lines.append(f"| `{path}` | {missed_count} | {total} | {display} |")
    if not records:
        lines.append("| No missed PHP source statements were found in the Clover input | 0 | — | — |")
    return "\n".join(lines) + "\n"


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("coverage", type=Path)
    parser.add_argument("destination", type=Path)
    args = parser.parse_args()
    root = Path(__file__).resolve().parents[2]
    args.destination.parent.mkdir(parents=True, exist_ok=True)
    args.destination.write_text(coverage_gaps(args.coverage, root), encoding="utf-8")


if __name__ == "__main__":
    main()
