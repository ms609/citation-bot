"""Offline regression tests: python3 -m unittest discover -s tests/python -v."""

import gzip
import json
import sys
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / "tools"))
from build_spie_volume_editors import build, main, records, split_editor  # noqa: E402

DTD = """<!ELEMENT dblp ANY>
<!ELEMENT proceedings ANY>
 <!ENTITY eacute "&#233;">
"""


def entry(
    volume="6505",
    year="2007",
    title="A Verified SPIE Volume",
    editors=None,
    series="SPIE Proceedings",
    key="conf/test/2007",
):
    editors = ["Edward J. Delp III", "Ping Wah Wong"] if editors is None else editors
    return (
        f'<proceedings key="{key}">'
        + "".join(f"<editor>{name}</editor>" for name in editors)
        + f"<title>{title}</title><year>{year}</year>"
        + f"<series>{series}</series><volume>{volume}</volume>"
        + "</proceedings>"
    )


class BuildSpieEditorDataTest(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        self.directory = Path(self.tmp.name)
        self.dtd = self.directory / "dblp.dtd"
        self.dtd.write_text(DTD, encoding="utf-8")

    def write_xml(self, fragments, gz=True):
        file = self.directory / ("dblp.xml.gz" if gz else "dblp.xml")
        raw = (
            '<?xml version="1.0" encoding="UTF-8"?>\n'
            '<!DOCTYPE dblp SYSTEM "dblp.dtd">\n<dblp>' + "".join(fragments) + "</dblp>"
        ).encode("utf-8")
        if gz:
            with gzip.open(file, "wb") as output:
                output.write(raw)
        else:
            file.write_bytes(raw)
        return file

    def test_streaming_gzip_dtd_entity_and_ordered_editors(self):
        xml = self.write_xml(
            [
                "<inproceedings><title>Ignored</title></inproceedings>",
                entry(title="S&eacute;curity of Multimedia"),
                entry(volume="6506", series="Other Publisher"),
            ]
        )
        output, review, stats = build(xml, self.dtd, titles={"6505:2007": "Sécurity of Multimedia"})
        self.assertEqual(stats["proceedings"], 2)
        self.assertEqual(stats["spie"], 1)
        self.assertEqual([], review)
        self.assertEqual(["6505:2007"], list(output))
        record = output["6505:2007"]
        self.assertEqual("Sécurity of Multimedia", record["title"])
        self.assertEqual(
            [
                {"given": "Edward J.", "family": "Delp III"},
                {"given": "Ping Wah", "family": "Wong"},
            ],
            record["editors"],
        )
        self.assertEqual(["https://dblp.org/rec/conf/test/2007"], record["sources"])

    def test_plain_xml_and_venue_date_are_for_review(self):
        xml = self.write_xml(
            [entry(title="Security, Steganography, and Watermarking IX, San Jose, USA, January 28, 2007")], gz=False
        )
        out, review, stats = build(xml, self.dtd)
        self.assertEqual({}, out)
        self.assertEqual("title-includes-venue-date", review[0]["issues"][0])
        self.assertEqual(1, stats["review"])
        reviewed_title = "Security, Steganography, and Watermarking IX"
        out, review, _ = build(xml, self.dtd, titles={"6505:2007": reviewed_title})
        self.assertEqual([], review)
        self.assertEqual(reviewed_title, out["6505:2007"]["title"])

    def test_rejects_ambiguous_names_and_accepts_dblp_ids(self):
        for name in (
            "",
            "Madonna",
            "Jean van der Berg",
            "A {Bad} Name",
            "John 0002",
            "John Smith 0002 Extra",
            "John Smith 2020",
        ):
            with self.subTest(name=name):
                self.assertIsNone(split_editor(name))
        self.assertEqual({"given": "Jane", "family": "Doe Jr."}, split_editor("Jane Doe Jr."))
        self.assertEqual({"given": "Peng", "family": "Li"}, split_editor("Peng Li 0047"))
        self.assertEqual({"given": "James J.", "family": "Butler"}, split_editor("James J. Butler 0001"))
        xml = self.write_xml([entry(editors=["Jean van der Berg"])])
        out, review, _ = build(xml, self.dtd)
        self.assertEqual({}, out)
        self.assertIn("editor-name-needs-review", review[0]["issues"])
        self.assertEqual([], review[0]["data"]["editors"])

    def test_single_particle_surnames_and_ambiguous_particles(self):
        cases = {
            "Harmen R. van As": {"given": "Harmen R.", "family": "van As"},
            "Huib de Ridder": {"given": "Huib", "family": "de Ridder"},
            "Marten J. de Vries": {"given": "Marten J.", "family": "de Vries"},
            "Bram van Ginneken": {"given": "Bram", "family": "van Ginneken"},
            "Harmen R. van As Jr.": {"given": "Harmen R.", "family": "van As Jr."},
            "Harmen R. van As 0001": {"given": "Harmen R.", "family": "van As"},
        }
        for name, expected in cases.items():
            with self.subTest(name=name):
                self.assertEqual(expected, split_editor(name))
        for name in ("van As", "de Ridder", "Jean van der Berg", "Maria de la Cruz"):
            with self.subTest(rejected=name):
                self.assertIsNone(split_editor(name))

    def test_eight_reported_spie_editor_rosters(self):
        # Real names and volume/year keys from the DBLP review report.
        # The source titles in this test simulate reviewed publisher titles;
        # volume 3408 deliberately has no publisher title and stays in review.
        cases = [
            (
                "3408",
                "1998",
                "Broadband European Networks and Multimedia Services 1998, Zurich, Switzerland, May 19-20, 1998.",
                [
                    "Stephan Fischer 0001",
                    "Ralf Steinmetz",
                    "Heinrich J. Stüttgen",
                    "Harmen R. van As",
                    "Roberto Vercelli",
                ],
                ["Fischer", "Steinmetz", "Stüttgen", "van As", "Vercelli"],
            ),
            (
                "8651",
                "2013",
                "Human Vision and Electronic Imaging XVIII",
                ["Bernice E. Rogowitz", "Thrasyvoulos N. Pappas", "Huib de Ridder"],
                ["Rogowitz", "Pappas", "de Ridder"],
            ),
            (
                "9014",
                "2014",
                "Human Vision and Electronic Imaging XIX",
                ["Bernice E. Rogowitz", "Thrasyvoulos N. Pappas", "Huib de Ridder"],
                ["Rogowitz", "Pappas", "de Ridder"],
            ),
            (
                "9394",
                "2015",
                "Human Vision and Electronic Imaging XX",
                ["Bernice E. Rogowitz", "Thrasyvoulos N. Pappas", "Huib de Ridder"],
                ["Rogowitz", "Pappas", "de Ridder"],
            ),
            (
                "8291",
                "2012",
                "Human Vision and Electronic Imaging XVII",
                ["Bernice E. Rogowitz", "Thrasyvoulos N. Pappas", "Huib de Ridder"],
                ["Rogowitz", "Pappas", "de Ridder"],
            ),
            (
                "3525",
                "1998",
                "Mobile Robots XIII and Intelligent Transportation Systems",
                [
                    "Howie Choset",
                    "Douglas W. Gage",  # codespell:ignore Gage
                    "Pushkin Kachroo",
                    "Mikhail Kourjanski",
                    "Marten J. de Vries",
                ],
                ["Choset", "Gage", "Kachroo", "Kourjanski", "de Vries"],  # codespell:ignore Gage
            ),
            (
                "7963",
                "2011",
                "Medical Imaging 2011: Computer-Aided Diagnosis",
                ["Ronald M. Summers", "Bram van Ginneken"],
                ["Summers", "van Ginneken"],
            ),
            (
                "8315",
                "2012",
                "Medical Imaging 2012: Computer-Aided Diagnosis",
                ["Bram van Ginneken", "Carol L. Novak"],
                ["van Ginneken", "Novak"],
            ),
        ]
        xml = self.write_xml(
            [
                entry(volume=volume, year=year, title=title, editors=names, key=f"conf/particle/{volume}")
                for volume, year, title, names, _ in cases
            ]
        )
        titles = {f"{volume}:{year}": title for volume, year, title, _, _ in cases if volume != "3408"}
        output, review, stats = build(xml, self.dtd, titles=titles)
        self.assertEqual(8, stats["spie"])
        self.assertEqual(7, stats["new"])
        self.assertEqual(1, len(review))
        self.assertEqual("3408:1998", review[0]["key"])
        self.assertNotIn("editor-name-needs-review", review[0]["issues"])
        self.assertIn("canonical-title-not-reviewed", review[0]["issues"])
        for volume, year, title, names, surnames in cases:
            key = f"{volume}:{year}"
            if key == "3408:1998":
                self.assertNotIn(key, output)
                continue
            with self.subTest(key=key):
                self.assertEqual(len(names), len(output[key]["editors"]))
                self.assertEqual(surnames, [person["family"] for person in output[key]["editors"]])
        # A separate verified canonical title is required before accepting 3408.
        titles["3408:1998"] = "Publisher-reviewed canonical title"
        output, review, _ = build(xml, self.dtd, titles=titles)
        self.assertEqual([], review)
        self.assertEqual(8, len(output))
        self.assertEqual(
            ["Fischer", "Steinmetz", "Stüttgen", "van As", "Vercelli"],
            [person["family"] for person in output["3408:1998"]["editors"]],
        )

    def test_venue_date_detection_catches_reverse_month_day_order(self):
        xml = self.write_xml(
            [
                entry(
                    title="Medical Imaging 2019: Image Processing, San Diego, CA, USA, 16-21 February 2019",
                )
            ]
        )
        out, review, _ = build(xml, self.dtd)
        self.assertEqual({}, out)
        self.assertIn("title-includes-venue-date", review[0]["issues"])
        self.assertIn("canonical-title-not-reviewed", review[0]["issues"])

    def test_canonical_title_required_even_without_venue_date(self):
        xml = self.write_xml([entry(title="Proceedings with No Location")])
        out, review, _ = build(xml, self.dtd)
        self.assertEqual({}, out)
        self.assertEqual(["canonical-title-not-reviewed"], review[0]["issues"])
        out, review, _ = build(xml, self.dtd, titles={"6505:2007": "Proceedings with No Location"})
        self.assertEqual([], review)
        self.assertEqual("Proceedings with No Location", out["6505:2007"]["title"])

    def test_disambiguated_editor_keeps_complete_roster(self):
        xml = self.write_xml([entry(editors=["Edward J. Delp III", "Peng Li 0047", "Ping Wah Wong"])])
        out, review, _ = build(xml, self.dtd, titles={"6505:2007": "Volume"})
        self.assertEqual([], review)
        self.assertEqual(
            [
                {"given": "Edward J.", "family": "Delp III"},
                {"given": "Peng", "family": "Li"},
                {"given": "Ping Wah", "family": "Wong"},
            ],
            out["6505:2007"]["editors"],
        )

    def test_homonym_suffix_does_not_hide_another_ambiguous_editor(self):
        xml = self.write_xml([entry(editors=["Peng Li 0047", "Jean van der Berg"])])
        out, review, _ = build(xml, self.dtd, titles={"6505:2007": "Volume"})
        self.assertEqual({}, out)
        self.assertIn("editor-name-needs-review", review[0]["issues"])
        self.assertEqual([], review[0]["data"]["editors"])
        self.assertEqual(["Peng Li 0047", "Jean van der Berg"], review[0]["dblp_editors"])

    def test_duplicate_volume_year_is_excluded(self):
        xml = self.write_xml(
            [
                entry(key="conf/test/2007a"),
                entry(key="conf/test/2007b", editors=["Other Person"]),
            ]
        )
        out, review, stats = build(xml, self.dtd, titles={"6505:2007": "Verified Title"})
        self.assertEqual({}, out)
        self.assertEqual(0, stats["new"])
        self.assertEqual(2, len(review))
        self.assertEqual({"conf/test/2007a", "conf/test/2007b"}, {item["dblp_key"] for item in review})
        self.assertTrue(all("duplicate-volume-year" in item["issues"] for item in review))

    def test_duplicate_does_not_repeat_first_record_already_in_review(self):
        xml = self.write_xml(
            [
                entry(key="conf/test/2007a"),
                entry(key="conf/test/2007b", editors=["Other Person"]),
            ]
        )
        # Without a reviewed title, the first record is already in the report.
        out, review, _ = build(xml, self.dtd)
        self.assertEqual({}, out)
        self.assertEqual(2, len(review))
        self.assertEqual(["conf/test/2007a", "conf/test/2007b"], [item["dblp_key"] for item in review])
        self.assertTrue(all("canonical-title-not-reviewed" in item["issues"] for item in review))
        self.assertTrue(all("duplicate-volume-year" in item["issues"] for item in review))

    def test_preserves_existing_record_and_reports_editor_mismatch(self):
        xml = self.write_xml([entry(editors=["An Unknown Editor"])])
        existing = {
            "6505:2007": {
                "title": "Verified Title",
                "editors": [{"given": "Edward J.", "family": "Delp III"}],
                "sources": ["https://example.org/verified"],
            }
        }
        out, review, _ = build(xml, self.dtd, existing=existing)
        self.assertEqual(existing, out)
        self.assertIn("conflicts-with-existing", review[0]["issues"])

    def test_rejects_other_external_entity_system_urls(self):
        xml = self.directory / "dblp.xml"
        xml.write_text('<!DOCTYPE dblp SYSTEM "https://evil.example/dblp.dtd"><dblp></dblp>', encoding="utf-8")
        with self.assertRaises(ValueError):
            list(records(xml, self.dtd))

    def test_cli_writes_json_and_review_and_rejects_overwrite(self):
        xml = self.write_xml([entry()])
        output = self.directory / "candidate.json"
        review = self.directory / "review.jsonl"
        status = main(["--xml", str(xml), "--dtd", str(self.dtd), "--output", str(output), "--review", str(review)])
        self.assertEqual(0, status)
        self.assertEqual({}, json.loads(output.read_text()))
        self.assertIn("canonical-title-not-reviewed", review.read_text())
        with self.assertRaises(SystemExit):
            main(["--xml", str(xml), "--dtd", str(self.dtd), "--output", str(xml), "--review", str(review)])


if __name__ == "__main__":
    unittest.main()
