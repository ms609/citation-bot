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

DTD = '''<!ELEMENT dblp ANY>
<!ELEMENT proceedings ANY>
 <!ENTITY eacute "&#233;">
'''


def entry(volume="6505", year="2007", title="A Verified SPIE Volume", editors=None,
          series="SPIE Proceedings", key="conf/test/2007"):
    editors = ["Edward J. Delp III", "Ping Wah Wong"] if editors is None else editors
    return (f'<proceedings key="{key}">'
            + "".join(f"<editor>{name}</editor>" for name in editors)
            + f"<title>{title}</title><year>{year}</year>"
            + f"<series>{series}</series><volume>{volume}</volume>"
            + "</proceedings>")


class BuildSpieEditorDataTest(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        self.directory = Path(self.tmp.name)
        self.dtd = self.directory / "dblp.dtd"
        self.dtd.write_text(DTD, encoding="utf-8")

    def write_xml(self, fragments, gz=True):
        file = self.directory / ("dblp.xml.gz" if gz else "dblp.xml")
        raw = ('<?xml version="1.0" encoding="UTF-8"?>\n'
               '<!DOCTYPE dblp SYSTEM "dblp.dtd">\n<dblp>'
               + "".join(fragments) + "</dblp>").encode("utf-8")
        if gz:
            with gzip.open(file, "wb") as output:
                output.write(raw)
        else:
            file.write_bytes(raw)
        return file

    def test_streaming_gzip_dtd_entity_and_ordered_editors(self):
        xml = self.write_xml([
            '<inproceedings><title>Ignored</title></inproceedings>',
            entry(title="S&eacute;curity of Multimedia"),
            entry(volume="6506", series="Other Publisher"),
        ])
        output, review, stats = build(xml, self.dtd)
        self.assertEqual(stats["proceedings"], 2)
        self.assertEqual(stats["spie"], 1)
        self.assertEqual([], review)
        self.assertEqual(["6505:2007"], list(output))
        record = output["6505:2007"]
        self.assertEqual("Sécurity of Multimedia", record["title"])
        self.assertEqual([
            {"given": "Edward J.", "family": "Delp III"},
            {"given": "Ping Wah", "family": "Wong"},
        ], record["editors"])
        self.assertEqual(["https://dblp.org/rec/conf/test/2007"], record["sources"])

    def test_plain_xml_and_venue_date_are_for_review(self):
        xml = self.write_xml([entry(title="Security, Steganography, and Watermarking IX,"
                                   " San Jose, USA, January 28, 2007")], gz=False)
        out, review, stats = build(xml, self.dtd)
        self.assertEqual({}, out)
        self.assertEqual("title-includes-venue-date", review[0]["issues"][0])
        self.assertEqual(1, stats["review"])
        reviewed_title = "Security, Steganography, and Watermarking IX"
        out, review, _ = build(xml, self.dtd, titles={"6505:2007": reviewed_title})
        self.assertEqual([], review)
        self.assertEqual(reviewed_title, out["6505:2007"]["title"])

    def test_rejects_ambiguous_or_disambiguated_names(self):
        for name in ("", "Madonna", "Jean van der Berg", "John Smith 0002", "A {Bad} Name"):
            with self.subTest(name=name):
                self.assertIsNone(split_editor(name))
        self.assertEqual({"given": "Jane", "family": "Doe Jr."}, split_editor("Jane Doe Jr."))
        xml = self.write_xml([entry(editors=["Jean van der Berg"])])
        out, review, _ = build(xml, self.dtd)
        self.assertEqual({}, out)
        self.assertIn("editor-name-needs-review", review[0]["issues"])

    def test_duplicate_volume_year_is_excluded(self):
        xml = self.write_xml([
            entry(key="conf/test/2007a"),
            entry(key="conf/test/2007b", editors=["Other Person"]),
        ])
        out, review, stats = build(xml, self.dtd)
        self.assertEqual({}, out)
        self.assertEqual(0, stats["new"])
        self.assertIn("duplicate-volume-year", review[0]["issues"])

    def test_preserves_existing_record_and_reports_editor_mismatch(self):
        xml = self.write_xml([entry(editors=["An Unknown Editor"])])
        existing = {"6505:2007": {
            "title": "Verified Title",
            "editors": [{"given": "Edward J.", "family": "Delp III"}],
            "sources": ["https://example.org/verified"],
        }}
        out, review, _ = build(xml, self.dtd, existing=existing)
        self.assertEqual(existing, out)
        self.assertIn("conflicts-with-existing", review[0]["issues"])

    def test_rejects_other_external_entity_system_urls(self):
        xml = self.directory / "dblp.xml"
        xml.write_text('<!DOCTYPE dblp SYSTEM "https://evil.example/dblp.dtd">'
                       '<dblp></dblp>', encoding="utf-8")
        with self.assertRaises(ValueError):
            list(records(xml, self.dtd))

    def test_cli_writes_json_and_review_and_rejects_overwrite(self):
        xml = self.write_xml([entry()])
        output = self.directory / "candidate.json"
        review = self.directory / "review.jsonl"
        status = main(["--xml", str(xml), "--dtd", str(self.dtd),
                       "--output", str(output), "--review", str(review)])
        self.assertEqual(0, status)
        self.assertEqual("A Verified SPIE Volume", json.loads(output.read_text())["6505:2007"]["title"])
        self.assertEqual("", review.read_text())
        with self.assertRaises(SystemExit):
            main(["--xml", str(xml), "--dtd", str(self.dtd),
                  "--output", str(xml), "--review", str(review)])


if __name__ == "__main__":
    unittest.main()
