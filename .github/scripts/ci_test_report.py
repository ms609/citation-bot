#!/usr/bin/env python3
"""Build safe test artifacts from ParaTest reports using the Python standard library.

Trusted CI runs contain API credentials. Do not publish raw DebugLog.txt,
PHPUnit failure text, system-out/system-err, or arbitrary JUnit attributes: they
may contain HTTP requests, tokens, or responses. Only allowlisted test identifiers,
counts, durations, and coverage metrics are exported.
"""

import argparse
import math
import re
from pathlib import Path
from xml.etree import ElementTree as ET

SAFE_CLASS = re.compile(r"^[A-Za-z_][A-Za-z0-9_\\]*$")
SAFE_METHOD = re.compile(r"^([A-Za-z_][A-Za-z0-9_]*)(?:$|\b)")
MAX_FAILURES = 20
MAX_SLOW = 20


def safe_identifier(raw: str, *, method: bool = False) -> str:
    """Reject unstructured, potentially sensitive dataset labels."""
    if len(raw) > 160:
        return "redacted"
    if method:
        match = SAFE_METHOD.match(raw)
        return match.group(1) if match else "redacted"
    return raw if SAFE_CLASS.fullmatch(raw) else "redacted"


def read_cases(source: Path) -> tuple[list[tuple[str, str, float, str]], str]:
    if not source.is_file():
        return [], "JUnit report not found (test setup may have failed)."
    try:
        root = ET.parse(source).getroot()
    except (ET.ParseError, OSError, ValueError):
        return [], "JUnit report could not be parsed."

    cases = []
    for case in root.iter("testcase"):
        classname = safe_identifier(case.get("class", case.get("classname", "")))
        name = safe_identifier(case.get("name", ""), method=True)
        try:
            seconds = float(case.get("time", "0"))
        except (ValueError, TypeError):
            seconds = 0.0
        if not math.isfinite(seconds) or seconds < 0:
            seconds = 0.0
        if case.find("error") is not None:
            status = "error"
        elif case.find("failure") is not None:
            status = "failure"
        elif case.find("skipped") is not None:
            status = "skipped"
        else:
            status = "passed"
        cases.append((classname, name, seconds, status))
    return cases, ""


def coverage_metrics(source: Path) -> list[str]:
    if not source.is_file():
        return ["Coverage XML: not produced."]
    summary = [f"Coverage XML: present ({source.stat().st_size} bytes)."]
    try:
        # Clover project-level metrics normally appear before file-level metrics.
        with source.open("rb") as stream:
            for _, element in ET.iterparse(stream, events=("start",)):
                if element.tag != "metrics" or "files" not in element.attrib:
                    continue
                for quantity, covered in (
                    ("statements", "coveredstatements"),
                    ("methods", "coveredmethods"),
                    ("elements", "coveredelements"),
                ):
                    if quantity not in element.attrib or covered not in element.attrib:
                        continue
                    total = int(element.attrib[quantity])
                    passed = int(element.attrib[covered])
                    if total >= 0 and 0 <= passed <= total:
                        pct = (100.0 * passed / total) if total else 100.0
                        summary.append(f"{quantity}: {passed}/{total} ({pct:.1f}%).")
                break
    except (ET.ParseError, OSError, ValueError):
        summary.append("Coverage metrics could not be parsed.")
    return summary


def debug_metadata(source: Path) -> str:
    # Never read or copy actual log lines into artifact files or job summaries.
    if not source.is_file():
        return "DebugLog.txt: absent. Raw debug content is never uploaded."
    try:
        return f"DebugLog.txt: present ({source.stat().st_size} bytes). Raw debug content is never uploaded."
    except OSError:
        return "DebugLog.txt: metadata unavailable. Raw debug content is never uploaded."


def write_report(
    junit: Path, coverage: Path, debug: Path, destination: Path, version: str
) -> None:
    destination.mkdir(parents=True, exist_ok=True)
    cases, warning = read_cases(junit)
    failures = [c for c in cases if c[3] in ("failure", "error")]
    skipped = sum(c[3] == "skipped" for c in cases)
    errors = sum(c[3] == "error" for c in cases)
    failed = sum(c[3] == "failure" for c in cases)
    total_time = sum(c[2] for c in cases)

    lines = [
        f"### PHP {version} test diagnostics",
        "",
        f"Tests: **{len(cases)}** | Failed: **{failed}** | Errors: **{errors}** | Skipped: **{skipped}**",
        "",
    ]
    if warning:
        lines.extend([f"**{warning}**", ""])
    if failures:
        lines.extend(["**Failing tests (names only; no assertion output):**", ""])
        for classname, name, _, status in failures[:MAX_FAILURES]:
            lines.append(f"- `{classname}::{name}` ({status})")
        if len(failures) > MAX_FAILURES:
            lines.append(f"- …and {len(failures) - MAX_FAILURES} additional failures")
        lines.append("")

    lines.extend([
        "**Slowest test cases:**",
        "",
        "| Test | Case time |",
        "| --- | ---: |",
    ])
    for classname, name, seconds, _ in sorted(cases, key=lambda c: c[2], reverse=True)[:MAX_SLOW]:
        lines.append(f"| `{classname}::{name}` | {seconds:.3f}s |")
    if not cases:
        lines.append("| No case timings available | — |")

    lines.extend([
        "",
        f"Summed case time: {total_time:.3f}s (not wall-clock duration).",
        "",
        "**Coverage:** " + " ".join(coverage_metrics(coverage)),
        "",
        debug_metadata(debug),
        "",
        "Raw JUnit assertion text and debug logs are deliberately excluded to protect credentials.",
    ])
    (destination / "summary.md").write_text("\n".join(lines) + "\n", encoding="utf-8")

    sorted_cases = sorted(cases, key=lambda c: c[2], reverse=True)
    timing_lines = [
        f"{seconds:9.3f}s {classname}::{name} [{status}]"
        for classname, name, seconds, status in sorted_cases
    ]
    (destination / "timings.txt").write_text(
        "\n".join(timing_lines) + ("\n" if timing_lines else ""), encoding="utf-8"
    )

    # Re-create the JUnit report from safe fields; never copy failure bodies or
    # arbitrary names/attributes from original JUnit output.
    if not warning:
        root = ET.Element("testsuites")
        suite = ET.SubElement(root, "testsuite", {
            "name": f"PHP {version}", "tests": str(len(cases)),
            "failures": str(failed), "errors": str(errors),
            "skipped": str(skipped), "time": f"{total_time:.3f}",
        })
        for classname, name, seconds, status in cases:
            case = ET.SubElement(suite, "testcase", {
                "classname": classname, "name": name, "time": f"{seconds:.3f}",
            })
            if status != "passed":
                ET.SubElement(case, status, {"message": "Details omitted; see CI test step."})
        ET.ElementTree(root).write(
            destination / "junit.xml", encoding="utf-8", xml_declaration=True
        )


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--php-version", required=True, choices=("8.4", "8.5"))
    parser.add_argument("--junit", type=Path, default=Path("junit.xml"))
    parser.add_argument("--coverage", type=Path, default=Path("coverage.xml"))
    parser.add_argument("--debug-log", type=Path, default=Path("DebugLog.txt"))
    parser.add_argument("--output-dir", type=Path, default=Path("test-diagnostics"))
    args = parser.parse_args()
    write_report(args.junit, args.coverage, args.debug_log, args.output_dir, args.php_version)


if __name__ == "__main__":
    main()
