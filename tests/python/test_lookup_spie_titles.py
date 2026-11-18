"""Tests for publisher KBART title matching; all data is local and synthetic."""

import json
import sys
import tempfile
import unittest
from pathlib import Path

from openpyxl import Workbook

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / "tools"))
from lookup_spie_titles import (  # noqa: E402
    kbart_records, lookup, main, publisher_url, title_agrees,
)
from build_spie_volume_editors import build  # noqa: E402
from test_build_spie_volume_editors import DTD, entry  # noqa: E402


class PublisherTitleLookupTest(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        self.directory = Path(self.tmp.name)

    def write_book(self, rows, filename="publisher.xlsx"):
        wb = Workbook()
        sheet = wb.active
        sheet.title = "Proceedings Volumes"
        sheet.append(["publication_title", "monograph_volume", "title_url",
                      "publisher_name", "publication_type", "date_monograph_published_print"])
        for row in rows:
            sheet.append(row)
        path = self.directory / filename
        wb.save(path)
        return path

    def test_publisher_title_prefix_matching_is_conservative(self):
        self.assertTrue(title_agrees("Medical Imaging 2007: Image Processing",
                                     "Medical Imaging 2007: Image Processing, San Diego, 17 February 2007"))
        self.assertTrue(title_agrees("Security, Steganography IX",
                                     "Security, Steganography IX, San Jose, 2007"))
        self.assertFalse(title_agrees("Medical Imaging 2007: Computer-Aided Diagnosis",
                                      "Medical Imaging 2007: Image Processing, San Diego, 2007"))
        self.assertFalse(title_agrees("Security", "Security in Practice 2007"))

    def test_accepts_equivalent_publisher_and_dblp_title_formats(self):
        examples = [
            ("Three-Dimensional Image Capture and Applications VI",
             "Proceedings of the Conference on Three-Dimensional Image Capture and "
             "Applications VI, San Jose, CA, USA, January 18, 2004"),
            ("Broadband European Networks and Multimedia Services",
             "Broadband European Networks and Multimedia Services 1998, "
             "Zurich, Switzerland, May 19-20, 1998."),
            ("Image and Video Communications and Processing 2005",
             "Electronic Imaging: Image and Video Communications and Processing 2005, "
             "San Jose, California, USA, 16-20 January 2005"),
            ("Video Surveillance and Transportation Imaging Applications",
             "Video Surveillance and Transportation Imaging Applications 2013, "
             "Burlingame, California, USA, February 3-7, 2013"),
            ("Parallel Processing for Imaging Applications",
             "Proceedings of the Conference on Parallel Processing for Imaging "
             "Applications 2011, San Francisco, CA, USA, January 24-25, 2011"),
            ("14th International Workshop on Breast Imaging (IWBI 2018)",
             "14th International Workshop on Breast Imaging, IWBI 2018, "
             "Atlanta, Georgia, USA, 8-11 July 2018"),
            ("Ninth International Conference on Machine Vision (ICMV 2016)",
             "Ninth International Conference on Machine Vision, ICMV 2016, "
             "Nice, France, 18-20 November 2016"),
            ("Fifteenth International Conference on Machine Vision (ICMV 2022)",
             "Fifteenth International Conference on Machine Vision, ICMV 2022, "
             "Rome, Italy, November 18-20, 2022"),
            ("Third International Conference on Digital Image Processing (ICDIP 2011)",
             "Third International Conference on Digital Image Processing, ICDIP 2011, "
             "Chengdu, China, April 15-17, 2011"),
            ("Visualization and Data Analysis 2009",
             "Visualization and Data Analysis, VDA 2009, San Jose, CA, USA, "
             "January 19-20, 2009"),
            ("Thirteenth International Conference on Quality Control by Artificial Vision 2017",
             "Thirteenth International Conference on Quality Control by Artificial Vision, "
             "QCAV 2017, Tokyo, Japan, May 14, 2017"),
        ]
        for publisher, dblp in examples:
            with self.subTest(publisher=publisher):
                self.assertTrue(title_agrees(publisher, dblp))

    def test_substantive_title_disagreements_remain_for_review(self):
        examples = [
            ("Medical Imaging 2022: Digital and Computational Pathology",
             "Medical Imaging 2023: Digital and Computational Pathology, "
             "San Diego, CA, USA, February 19-23, 2023"),
            ("13th International Conference on Medical Information Processing and Analysis",
             "13th International Symposium on Medical Information Processing and Analysis, "
             "SIPAIM 2017, San Andres Island, 5-7 October 2017"),
            ("Data Mining and Knowledge Discovery: Theory, Tools, and Technology",
             "Data Mining and Knowledge Discovery: Theory, Tools, and Technology I, "
             "Orlando, FL, USA, April 5, 1999"),
            ("Media Forensics and Security",
             "Media Forensics and Security I, part of the IS&T-SPIE Electronic Imaging "
             "Symposium, San Jose, CA, USA, January 19-21, 2009, Proceedings"),
            ("Ninth International Conference on Machine Vision (ICMV 2016)",
             "Ninth International Conference on Machine Vision, ICMV 2017, "
             "Nice, France, November 18-20, 2017"),
            ("Visualization and Data Analysis 2009",
             "Visualization and Data Analysis, XYZ 2009, San Jose, "
             "January 19-20, 2009"),
            ("Three-Dimensional Image Capture and Applications VI",
             "Proceedings of the Conference on Three-Dimensional Image Capture and "
             "Applications VII, San Jose, January 18, 2004"),
            ("Conference on Imaging (COI 2011)",
             "Conference on Imaging, COI 2011, venue unspecified"),
            ("Multimedia Content and Mobile Devices",
             "Multimedia Content and Mobile Devices 2013, San Jose, January 2014"),
        ]
        for publisher, dblp in examples:
            with self.subTest(publisher=publisher, dblp=dblp):
                self.assertFalse(title_agrees(publisher, dblp))

    def test_different_publisher_print_year_still_blocks_title_match(self):
        publisher = {
            "5609": {"volume": "5609", "title": "Mobile Robots XVII",
                     "source": "https://www.spiedigitallibrary.org/conference-proceedings-of-spie/5609",
                     "year": 2004, "online_year": 2004},
        }
        titles, audit, _ = lookup([{"key": "5609:2002", "title":
                                   "Mobile Robots XVII, Philadelphia, PA, USA, October 25, 2004"}],
                                  publisher, set())
        self.assertEqual({}, titles)
        self.assertEqual(["publisher-year-mismatch"], audit[0]["issues"])

    def test_new_variant_publisher_title_flows_into_generated_json(self):
        title = "Three-Dimensional Image Capture and Applications VI"
        dblp = ("Proceedings of the Conference on Three-Dimensional Image "
                "Capture and Applications VI, San Jose, CA, USA, January 18, 2004")
        publisher = {
            "5302": {"volume": "5302", "title": title,
                     "source": "https://www.spiedigitallibrary.org/conference-proceedings-of-spie/5302",
                     "year": 2004, "online_year": 2004},
        }
        titles, audit, _ = lookup([{"key": "5302:2004", "title": dblp}], publisher, set())
        self.assertEqual([], audit)
        dtd = self.directory / "dblp.dtd"
        dtd.write_text(DTD, encoding="utf-8")
        xml = self.directory / "dblp.xml"
        xml.write_text('<?xml version="1.0"?><!DOCTYPE dblp SYSTEM "dblp.dtd"><dblp>' +
                       entry(volume="5302", year="2004", title=dblp,
                             editors=["Brian D. Corner", "Peng Li 0047", "Roy P. Pargas"]) +
                       '</dblp>', encoding="utf-8")
        generated, review, _ = build(xml, dtd, titles=titles)
        self.assertEqual([], review)
        self.assertEqual(title, generated["5302:2004"]["title"])
        self.assertEqual([{"given": "Brian D.", "family": "Corner"},
                          {"given": "Peng", "family": "Li"},
                          {"given": "Roy P.", "family": "Pargas"}],
                         generated["5302:2004"]["editors"])
        self.assertEqual(2, len(generated["5302:2004"]["sources"]))

    def test_accepts_correct_publisher_volume_and_source(self):
        url = "https://www.spiedigitallibrary.org/conference-proceedings-of-spie/6505"
        workbook = self.write_book([("Security, Steganography IX", 6505, url,
                                     "SPIE", "monograph", "2007-03-01")])
        publisher, conflicts = kbart_records(workbook)
        self.assertEqual(set(), conflicts)
        titles, audit, stats = lookup([{"key": "6505:2007", "title":
                                       "Security, Steganography IX, San Jose, USA, 2007"}],
                                      publisher, conflicts)
        self.assertEqual([], audit)
        self.assertEqual(1, stats["matched"])
        self.assertEqual({"title": "Security, Steganography IX", "source": url},
                         titles["6505:2007"])
        self.assertTrue(publisher_url(url, "6505"))

    def test_distinct_volume_and_wrong_publisher_are_not_trusted(self):
        book = self.write_book([
            ("Correct title", 6505,
             "https://www.spiedigitallibrary.org/conference-proceedings-of-spie/6506",
             "SPIE", "monograph", "2007"),
            ("Wrong publisher", 6506, "", "Another press", "monograph", "2007"),
            ("Not a proceedings volume", 6507, "", "SPIE", "serial", "2007"),
        ])
        publisher, conflicts = kbart_records(book)
        self.assertEqual({}, publisher)
        self.assertEqual(set(), conflicts)
        self.assertFalse(publisher_url("https://spiedigitallibrary.org.attacker.net/conference-proceedings-of-spie/6505", "6505"))
        self.assertFalse(publisher_url("http://www.spiedigitallibrary.org/conference-proceedings-of-spie/6505", "6505"))

    def test_conflicting_publisher_records_are_rejected(self):
        book = self.write_book([
            ("First title", 6505, "", "SPIE", "monograph", "2007"),
            ("Different title", 6505, "", "SPIE", "monograph", "2007"),
        ])
        publisher, conflicts = kbart_records(book)
        titles, audit, _ = lookup([{"key": "6505:2007", "title": "First title"}],
                                  publisher, conflicts)
        self.assertEqual({}, titles)
        self.assertIn("conflicting-publisher-records", audit[0]["issues"])

    def test_year_mismatch_and_title_mismatch_are_flagged(self):
        book = self.write_book([
            ("Medical Imaging", 6505, "", "SPIE", "monograph", "1999"),
            ("Color Imaging 2007", 6506, "", "SPIE", "monograph", "2007"),
        ])
        publisher, conflicts = kbart_records(book)
        titles, audit, _ = lookup([
            {"key": "6505:2007", "title": "Medical Imaging, USA, 2007"},
            {"key": "6506:2007", "title": "Unrelated Meeting, 2007"},
        ], publisher, conflicts)
        self.assertEqual({}, titles)
        self.assertIn("publisher-year-mismatch", audit[0]["issues"])
        self.assertIn("publisher-dblp-title-mismatch", audit[1]["issues"])

    def test_later_online_publication_does_not_conflict_with_print_year(self):
        wb = Workbook()
        sheet = wb.active
        sheet.append(["publication_title", "monograph_volume", "publisher_name",
                      "publication_type", "date_monograph_published_print",
                      "date_monograph_published_online"])
        sheet.append(["Security, Steganography IX", 6505, "SPIE", "monograph",
                      "2007-01-01", "2014-01-01"])
        book = self.directory / "publisher.xlsx"
        wb.save(book)
        publisher, conflicts = kbart_records(book)
        self.assertEqual(2007, publisher["6505"]["year"])
        self.assertEqual(2014, publisher["6505"]["online_year"])
        titles, audit, _ = lookup([{"key": "6505:2007", "title":
                                   "Security, Steganography IX, USA 2007"}],
                                  publisher, conflicts)
        self.assertEqual([], audit)
        self.assertEqual("Security, Steganography IX", titles["6505:2007"]["title"])

    def test_print_year_remains_authoritative_when_online_year_matches_dblp(self):
        wb = Workbook()
        sheet = wb.active
        sheet.append(["publication_title", "monograph_volume", "publisher_name",
                      "publication_type", "date_monograph_published_print",
                      "date_monograph_published_online"])
        sheet.append(["Conference IX", 6505, "SPIE", "monograph", "2001", "2007"])
        book = self.directory / "publisher.xlsx"
        wb.save(book)
        publisher, conflicts = kbart_records(book)
        titles, audit, _ = lookup([{"key": "6505:2007", "title": "Conference IX"}],
                                  publisher, conflicts)
        self.assertEqual({}, titles)
        self.assertIn("publisher-year-mismatch", audit[0]["issues"])

    def test_online_only_later_year_needs_review(self):
        wb = Workbook()
        sheet = wb.active
        sheet.append(["publication_title", "monograph_volume", "publisher_name",
                      "publication_type", "date_monograph_published_print",
                      "date_monograph_published_online"])
        sheet.append(["Conference IX", 6505, "SPIE", "monograph", "", "2015"])
        book = self.directory / "publisher.xlsx"
        wb.save(book)
        publisher, conflicts = kbart_records(book)
        titles, audit, _ = lookup([{"key": "6505:2007", "title": "Conference IX"}],
                                  publisher, conflicts)
        self.assertEqual({}, titles)
        self.assertIn("publisher-print-year-unavailable", audit[0]["issues"])
        self.assertNotIn("publisher-year-mismatch", audit[0]["issues"])

    def test_identical_rows_with_different_online_years_are_not_conflicts(self):
        wb = Workbook()
        sheet = wb.active
        sheet.append(["publication_title", "monograph_volume", "publisher_name",
                      "publication_type", "date_monograph_published_print",
                      "date_monograph_published_online"])
        sheet.append(["Conference IX", 6505, "SPIE", "monograph", "2007", "2014"])
        sheet.append(["Conference IX", 6505, "SPIE", "monograph", "2007", "2015"])
        book = self.directory / "publisher.xlsx"
        wb.save(book)
        publisher, conflicts = kbart_records(book)
        self.assertEqual(set(), conflicts)
        titles, audit, _ = lookup([{"key": "6505:2007", "title": "Conference IX"}],
                                  publisher, conflicts)
        self.assertEqual([], audit)
        self.assertIn("6505:2007", titles)

    def test_duplicate_dblp_key_with_different_titles_is_not_accepted(self):
        book = self.write_book([("Conference IX", 6505, "", "SPIE", "monograph", "2007")])
        publisher, conflicts = kbart_records(book)
        titles, audit, _ = lookup([
            {"key": "6505:2007", "title": "Conference IX"},
            {"key": "6505:2007", "title": "Conference IX, San Jose, 2007"},
        ], publisher, conflicts)
        self.assertEqual({}, titles)
        self.assertIn("duplicate-dblp-volume-year", audit[0]["issues"])

    def test_generated_publisher_sources_are_added_to_json(self):
        book = self.write_book([("Medical Imaging 2007: Image Processing", 6505, "",
                                 "SPIE", "monograph", "2007")])
        publisher, conflicts = kbart_records(book)
        titles, audit, _ = lookup([{"key": "6505:2007", "title":
                                   "Medical Imaging 2007: Image Processing, San Diego, Feb 2007"}],
                                  publisher, conflicts)
        self.assertEqual([], audit)
        dtd = self.directory / "dblp.dtd"
        dtd.write_text(DTD, encoding="utf-8")
        xml = self.directory / "dblp.xml"
        xml.write_text('<?xml version="1.0"?><!DOCTYPE dblp SYSTEM "dblp.dtd"><dblp>' +
                       entry(title="Medical Imaging 2007: Image Processing, San Diego, Feb 2007") +
                       '</dblp>', encoding="utf-8")
        generated, review, _ = build(xml, dtd, titles=titles)
        self.assertEqual([], review)
        self.assertEqual("Medical Imaging 2007: Image Processing", generated["6505:2007"]["title"])
        self.assertEqual(2, len(generated["6505:2007"]["sources"]))
        self.assertTrue(generated["6505:2007"]["sources"][1].startswith("https://www.spiedigitallibrary.org/"))

    def test_untrusted_publisher_source_never_passes_to_runtime_json(self):
        dtd = self.directory / "dblp.dtd"
        dtd.write_text(DTD, encoding="utf-8")
        xml = self.directory / "dblp.xml"
        xml.write_text('<?xml version="1.0"?><!DOCTYPE dblp SYSTEM "dblp.dtd"><dblp>' +
                       entry() + '</dblp>', encoding="utf-8")
        for source in ("https://evil.example/6505",
                       "https://www.spiedigitallibrary.org/conference-proceedings-of-spie/6506"):
            output, review, _ = build(xml, dtd, titles={"6505:2007":
                {"title": "A Verified SPIE Volume", "source": source}})
            self.assertEqual({}, output)
            self.assertIn("invalid-publisher-source", review[0]["issues"])

    def test_cli_works_with_review_csv(self):
        book = self.write_book([("Security, Steganography IX", 6505, "", "SPIE", "monograph", "2007")])
        csv = self.directory / "review.csv"
        csv.write_text('key,dblp_title\n6505:2007,"Security, Steganography IX, San Jose, USA, 2007"\n',
                       encoding="utf-8")
        output = self.directory / "titles.json"
        audit = self.directory / "audit.jsonl"
        self.assertEqual(0, main(["--spie-kbart", str(book), "--candidates", str(csv),
                                  "--titles", str(output), "--audit", str(audit)]))
        self.assertEqual("Security, Steganography IX", json.loads(output.read_text())["6505:2007"]["title"])
        self.assertEqual("", audit.read_text())


if __name__ == "__main__":
    unittest.main()
