# One-time reconciliation of the final five SPIE volumes

`reconcile_spie_remaining.py` is a manual, offline maintenance utility for
Citation Bot. Use it **after** the DBLP streaming generator and the
SPIE KBART publisher-title lookup. It is not run by Citation Bot itself.

The supplied `data/spie_reviewed_reconciliations.json` is a deliberately small
allowlist of *exact* DBLP titles, ordered editors, SPIE publisher titles and
print years. It records the decisions reviewed in November 2026:

| DBLP volume:year | Action | Result |
| --- | --- | --- |
| `3695:1999` | Publisher omits a trailing `I` from the title | Include canonical SPIE title |
| `7254:2009` | Publisher omits a trailing `I` from the title | Include canonical SPIE title |
| `10572:2017` | SPIE calls the event a *Conference*, not a *Symposium* | Include canonical SPIE title |
| `5609:2002` | DBLP incorrectly gives 2002; SPIE and publication records give 2004 | Include as `5609:2004` |
| `12039:2023` | DBLP incorrectly reuses volume 12039 for the 2023 conference (real 2023 volume is 12471) | Exclude; retain valid `12039:2022` |

These are **not general equivalence rules**. The tool refuses any changed
bibliographic title, incomplete or reordered editor roster, changed publisher
metadata, invalid publisher URL, unexpected rejection reason, or collision with
existing records. It keeps the `12039:2023` anomaly in the residual audit instead
of creating a false record. In particular, `12039:2022` must already be present
with all three correct editors.

## Run

From the repository root, after regenerating the 407-candidate extraction:

```sh
python3 -m unittest discover -s tests/python -v

python3 tools/reconcile_spie_remaining.py \
  --candidates ../dblp-data/spie_volume_editors.candidates.json \
  --review ../dblp-data/spie_volume_editors.review.jsonl \
  --publisher-audit ../dblp-data/spie-publisher-title-audit.jsonl \
  --output ../dblp-data/spie_volume_editors.curated.json \
  --remaining ../dblp-data/spie_volume_editors.remaining.jsonl
```

Expected for the reported 407-new-record extraction: four more unique records,
**412 entries total** (407 new + one existing + four reconciled), and one
remaining audit entry documenting the incorrect DBLP `12039:2023` volume.
`12039:2023` should not be imported. The script doesn't modify the input
candidate JSON, audit files or live runtime database.

Inspect the four additions and run the PHP integration tests **before** copying
`spie_volume_editors.curated.json` into
`src/includes/data/spie_volume_editors.json`. The PHP bounded loader must support
at least the final output size (currently configured for up to 512 KiB).

## Source evidence

- Publisher KBART volume URLs are checked from the lookup audit, not invented.
- [SPIE volume 10572 article citation](https://nanolithography.spiedigitallibrary.org/conference-proceedings-of-spie/10572/1057204/A-sparse-representation-of-the-pathologists-interaction-with-whole-slide/10.1117/12.2285906.full) explicitly calls the volume a *Conference*.
- [SPIE volume 5609 catalog evidence](https://cir.nii.ac.jp/crid/1970023484892635451/holdings) dates it to October 2004.
- [SPIE 2022 volume 12039](https://www.proceedings.com/spie12039.html) and [SPIE 2023 volume 12471](https://www.proceedings.com/spie12471.html) establish the 2023 DBLP volume error.

A corresponding DBLP metadata correction should be reported upstream so this
allowlist can eventually be retired.
