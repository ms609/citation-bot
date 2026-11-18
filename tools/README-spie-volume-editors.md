# Offline DBLP → SPIE volume-editor candidates

`build_spie_volume_editors.py` produces *candidate* entries matching the shape of
`src/includes/data/spie_volume_editors.json` in PR #6487. It reads the full DBLP
XML dump in bounded memory, without network access or loading the whole dump into
memory. It is a manual maintainer tool, **not** an automated source of verified
metadata and not a runtime Citation Bot dependency.

Requirements: Python 3.10+ and `lxml`. DBLP requires its matching local DTD to
resolve named character entities. Download the XML and DTD from
<https://dblp.org/xml/> (ideally use a fixed monthly snapshot from
<https://dblp.org/xml/release/> for reproducibility).

```sh
python3 -m pip install lxml
mkdir -p /tmp/dblp
curl -fL https://dblp.org/xml/dblp.dtd -o /tmp/dblp/dblp.dtd
curl -fL https://dblp.org/xml/dblp.xml.gz -o /tmp/dblp/dblp.xml.gz

python3 tools/build_spie_volume_editors.py \
  --xml /tmp/dblp/dblp.xml.gz \
  --dtd /tmp/dblp/dblp.dtd \
  --existing src/includes/data/spie_volume_editors.json \
  --output /tmp/spie_volume_editors.candidates.json \
  --review /tmp/spie_volume_editors.review.jsonl
```

`--existing` is optional; existing curated records take precedence and are never
modified. After supplying reviewed titles, accepted new records are keyed as
`volume:year` and have `title`, ordered
`editors` (`given`/`family`) and `sources` (DBLP persistent record URLs).

Records are selected **only** from DBLP `<proceedings>` records whose book series
is `SPIE Proceedings` (or `Proceedings of SPIE`), with numeric volumes, valid
years and explicit `<editor>` elements. Paper `<author>` records are not used.
**Every new record requires an explicitly reviewed canonical title** supplied
via `--titles`. By default, the JSON output therefore contains only previously
verified `--existing` records; all new entries appear in the review report.
This is intentional: many DBLP titles append venue/date information, including
cases that a simple regular expression will miss. Records with ambiguous
editor-name splitting, conflicting existing rosters, duplicate volume/year
keys or incomplete metadata also remain in review regardless of title override.
If an editor could not be parsed, the review candidate's `editors` array is
empty, rather than a dangerously incomplete roster. The full ordered original
names remain available in its `dblp_editors` array.

DBLP titles often append a meeting location and date. Do **not** strip text
blindly: the Citation Bot importer currently matches the full title exactly.
After checking a proceedings title against a publisher or other independent
record, provide the canonical title in a separate JSON mapping:

```json
{
  "6505:2007": "Security, Steganography, and Watermarking of Multimedia Contents IX"
}
```

Pass that file as `--titles /tmp/spie-reviewed-titles.json`. It overrides just
the candidate title; it does not certify any editor names. Verify each new
editor roster and the given/family splits against the cited source(s) before
copying entries into `src/includes/data/spie_volume_editors.json`. In
particular, suffixes, particles, compound family names, Eastern name order and
DBLP homonym disambiguators need attention. A record may be missing from DBLP;
this generator does not invent editorial metadata.

**Size limit:** PR #6487 currently rejects runtime JSON over 128 KiB. The tool
warns when output exceeds this limit. Review and adjust the bounded PHP loader
size separately before committing a larger output. Do not copy its full output
without reviewing the report and ensuring the runtime loader accepts it.

Security: XML parsing resolves **only the local `dblp.dtd`** and prohibits
network XML entities. Compressed input is decoded as a stream. Output files
cannot replace the XML, DTD, existing JSON, or title overrides accidentally.

Run the offline tests without downloading DBLP:

```sh
python3 -m unittest discover -s tests/python -v
```
