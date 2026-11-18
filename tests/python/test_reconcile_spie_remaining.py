"""Offline tests for audited, exact SPIE volume reconciliation."""

import copy
import json
import sys
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / "tools"))
from reconcile_spie_remaining import (  # noqa: E402
    DEFAULT_MANIFEST,
    main,
    reconcile,
)


class ReconcileSpieRemainingTest(unittest.TestCase):
    def setUp(self):
        self.rules = json.loads(DEFAULT_MANIFEST.read_text(encoding="utf-8"))
        self.candidates = {
            "12039:2022": {
                "title": "Medical Imaging 2022: Digital and Computational Pathology",
                "editors": [
                    {"given": "John E.", "family": "Tomaszewski"},
                    {"given": "Aaron D.", "family": "Ward"},
                    {"given": "Richard M.", "family": "Levenson"},
                ],
                "sources": [
                    "https://dblp.org/rec/conf/midp/2022",
                    "https://www.spiedigitallibrary.org/conference-proceedings-of-spie/12039",
                ],
            }
        }
        self.review = {}
        self.audit = {}
        for key, rule in self.rules.items():
            volume, original_year = key.split(":")
            names = rule["dblp_editors"]
            # Representative complete editor roster; real parsed values come
            # from the generator. Only the order/validity matters here.
            editors = [{"given": f"Given{index}", "family": f"Last{index}"} for index, _ in enumerate(names)]
            self.review[key] = {
                "key": key,
                "issues": ["title-includes-venue-date", "canonical-title-not-reviewed"],
                "data": {
                    "title": rule["dblp_title"],
                    "editors": editors,
                    "sources": [f"https://dblp.org/rec/conf/test/{original_year}"],
                },
                "dblp_editors": names,
                "dblp_key": f"conf/test/{original_year}",
            }
            self.audit[key] = {
                "key": key,
                "dblp_titles": [rule["dblp_title"]],
                "issues": (["publisher-year-mismatch"] if key == "5609:2002" else ["publisher-dblp-title-mismatch"]),
                "publisher": {
                    "volume": volume,
                    "title": rule["publisher_title"],
                    "year": rule["publisher_year"],
                    "online_year": rule["publisher_year"],
                    "source": f"https://www.spiedigitallibrary.org/conference-proceedings-of-spie/{volume}",
                },
            }

    def invoke(self):
        return reconcile(self.candidates, self.review, self.audit, self.rules)

    def test_adds_four_corrections_and_excludes_wrong_2023_volume(self):
        output, remaining, added = self.invoke()
        self.assertEqual({"3695:1999", "5609:2004", "7254:2009", "10572:2017"}, set(added))
        self.assertEqual(5, len(output))
        self.assertNotIn("5609:2002", output)
        self.assertNotIn("12039:2023", output)
        self.assertEqual("Mobile Robots XVII", output["5609:2004"]["title"])
        self.assertEqual(
            "13th International Conference on Medical Information Processing and Analysis",
            output["10572:2017"]["title"],
        )
        self.assertEqual("Given0", output["10572:2017"]["editors"][0]["given"])
        self.assertEqual(2, len(output["7254:2009"]["sources"]))
        self.assertEqual(1, len(remaining))
        self.assertEqual("12039:2023", remaining[0]["key"])
        self.assertEqual(["incorrect-dblp-volume"], remaining[0]["issues"])
        # An offline curation must never mutate the raw files.
        self.assertEqual(5, len(self.review))
        self.assertEqual(1, len(self.candidates))

    def test_rejects_changed_publisher_title_or_year(self):
        for key, field, value in [
            ("3695:1999", "title", "Data Mining and Knowledge Discovery I"),
            ("5609:2002", "year", 2002),
            ("7254:2009", "volume", "7255"),
            ("10572:2017", "source", "https://example.org/10572"),
        ]:
            with self.subTest(key=key, field=field):
                audit = copy.deepcopy(self.audit)
                audit[key]["publisher"][field] = value
                with self.assertRaisesRegex(ValueError, "publisher record"):
                    reconcile(self.candidates, self.review, audit, self.rules)

    def test_rejects_incomplete_or_changed_editor_roster(self):
        for key in self.rules:
            with self.subTest(key=key):
                review = copy.deepcopy(self.review)
                review[key]["data"]["editors"].pop()
                with self.assertRaisesRegex(ValueError, "roster"):
                    reconcile(self.candidates, review, self.audit, self.rules)
        review = copy.deepcopy(self.review)
        review["10572:2017"]["dblp_editors"].reverse()
        with self.assertRaisesRegex(ValueError, "roster"):
            reconcile(self.candidates, review, self.audit, self.rules)

    def test_rejects_changed_dblp_titles_or_issues(self):
        review = copy.deepcopy(self.review)
        review["3695:1999"]["data"]["title"] += " Another Venue"
        with self.assertRaisesRegex(ValueError, "Changed DBLP title"):
            reconcile(self.candidates, review, self.audit, self.rules)
        review = copy.deepcopy(self.review)
        review["3695:1999"]["issues"].append("editor-name-needs-review")
        with self.assertRaisesRegex(ValueError, "Other unresolved"):
            reconcile(self.candidates, review, self.audit, self.rules)

    def test_does_not_overwrite_conflicting_existing_record(self):
        candidates = copy.deepcopy(self.candidates)
        candidates["5609:2004"] = {"title": "Wrong", "editors": [], "sources": []}
        with self.assertRaisesRegex(ValueError, "Conflicting existing"):
            reconcile(candidates, self.review, self.audit, self.rules)

    def test_wrong_year_and_duplicate_2023_never_enter_output(self):
        output, _, _ = self.invoke()
        self.assertNotIn("5609:2002", output)
        self.assertNotIn("12039:2023", output)
        missing = copy.deepcopy(self.candidates)
        missing.pop("12039:2022")
        with self.assertRaisesRegex(ValueError, "distinct correct volume missing"):
            reconcile(missing, self.review, self.audit, self.rules)
        stale = copy.deepcopy(self.candidates)
        stale["12039:2023"] = {"title": "Bad", "editors": [], "sources": []}
        with self.assertRaisesRegex(ValueError, "already included"):
            reconcile(stale, self.review, self.audit, self.rules)

    def test_repeat_reconciliation_is_idempotent(self):
        output, _, _ = self.invoke()
        final, remaining, added = reconcile(output, self.review, self.audit, self.rules)
        self.assertEqual(output, final)
        self.assertEqual([], added)
        self.assertEqual(1, len(remaining))

    def test_rejects_unexpected_publisher_reason(self):
        audit = copy.deepcopy(self.audit)
        audit["5609:2002"]["issues"] = ["publisher-dblp-title-mismatch"]
        with self.assertRaisesRegex(ValueError, "publisher-year audit"):
            reconcile(self.candidates, self.review, audit, self.rules)

    def test_cli_writes_final_json_and_audit_without_overwriting_inputs(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory)
            candidates = path / "candidate.json"
            review = path / "review.jsonl"
            publisher = path / "publisher.jsonl"
            output = path / "output.json"
            remaining = path / "remaining.jsonl"
            candidates.write_text(json.dumps(self.candidates), encoding="utf-8")
            review.write_text("".join(json.dumps(row) + "\n" for row in self.review.values()), encoding="utf-8")
            publisher.write_text("".join(json.dumps(row) + "\n" for row in self.audit.values()), encoding="utf-8")
            arguments = [
                "--candidates",
                str(candidates),
                "--review",
                str(review),
                "--publisher-audit",
                str(publisher),
                "--output",
                str(output),
                "--remaining",
                str(remaining),
            ]
            self.assertEqual(0, main(arguments))
            self.assertEqual(5, len(json.loads(output.read_text())))
            self.assertEqual("12039:2023", json.loads(remaining.read_text())["key"])
            with self.assertRaises(SystemExit):
                invalid_args = arguments.copy()
                invalid_args[invalid_args.index("--output") + 1] = str(candidates)
                main(invalid_args)


if __name__ == "__main__":
    unittest.main()
