#!/usr/bin/env bash
#
# Regenerates .github/cffconvert-requirements.txt: the hash-pinned dependency
# closure of cffconvert 2.0.0 used by the "Validate CITATION.cff" GitHub
# Actions workflow (pip install --require-hashes).
#
# The lockfile pins the newest versions that satisfy every constraint of
# cffconvert 2.0.0, together with sha256 digests of the exact distributions
# pip selects on the workflow's target. Run this script on that target so the
# hashes match what CI installs.
#
# Usage:
#   bash tools/update_cffconvert_lock.sh
#
# Environment:
#   PYTHON   Python 3 interpreter (default: python3)
#
# Requirements: Linux with CPython 3.12 (x86_64) and network access to PyPI.
set -euo pipefail

LOCKFILE=".github/cffconvert-requirements.txt"
PYTHON="${PYTHON:-python3}"
CFFCONVERT_VERSION="2.0.0"

cd "$(git rev-parse --show-toplevel 2>/dev/null || echo "$PWD")"

# ---------------------------------------------------------------------------
# Platform check: digests must match the wheels pip selects in CI.
# ---------------------------------------------------------------------------
if [[ "$(uname -s)" != "Linux" ]]; then
    echo "ERROR: run this on Linux (the workflow's target) so the hashes match CI." >&2
    exit 1
fi

python_major="$("$PYTHON" -c 'import sys; print(sys.version_info.major)')"
python_minor="$("$PYTHON" -c 'import sys; print(sys.version_info.minor)')"
if [[ "$python_major" != "3" || "$python_minor" != "12" ]]; then
    echo "WARNING: expected CPython 3.12 (the workflow target); got $python_major.$python_minor." >&2
fi

if [[ ! -f "$LOCKFILE" ]]; then
    echo "ERROR: $LOCKFILE not found." >&2
    exit 1
fi

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

# ---------------------------------------------------------------------------
# Resolve the closure from a fresh venv so we get the newest compatible
# versions. Environment tooling (pip, wheel) is excluded; setuptools is a real
# dependency (jsonschema requires it) and must stay.
# ---------------------------------------------------------------------------
"$PYTHON" -m venv "$TMP/venv"
VENV_PIP="$TMP/venv/bin/pip"
"$VENV_PIP" install --quiet --upgrade pip
"$VENV_PIP" install --quiet "cffconvert==$CFFCONVERT_VERSION"
"$VENV_PIP" freeze | grep -v -E '^(pip|wheel)==' > "$TMP/freeze.txt"

# ---------------------------------------------------------------------------
# Download each pinned distribution (--no-deps) to capture the exact files
# pip selects on this platform.
# ---------------------------------------------------------------------------
mkdir -p "$TMP/dl"
while IFS= read -r pkg; do
    (cd "$TMP/dl" && "$VENV_PIP" download --quiet --no-deps "$pkg")
done < "$TMP/freeze.txt"

# ---------------------------------------------------------------------------
# Emit the new pinned body from the downloaded files.
# ---------------------------------------------------------------------------
"$PYTHON" - "$TMP/freeze.txt" "$TMP/dl" <<'PYEOF' > "$TMP/new_body.txt"
import hashlib
import os
import re
import sys

freeze, dl = sys.argv[1], sys.argv[2]

def norm(name):
    return re.sub(r'[-_.]+', '_', name)

specs = []
for line in open(freeze, encoding="utf-8"):
    line = line.strip()
    if not line or line.startswith("#"):
        continue
    name, _, ver = line.partition("==")
    if name.lower() in ("pip", "wheel"):
        continue
    specs.append((name, ver))

files = sorted(os.listdir(dl))
by_file = {}
for f in files:
    for name, ver in specs:
        if f.startswith(f"{norm(name)}-{ver}-") or f == f"{name}-{ver}.tar.gz":
            digest = hashlib.sha256(open(os.path.join(dl, f), "rb").read()).hexdigest()
            by_file[f] = (name, ver, digest)
            break

unmatched = [f for f in files if f not in by_file]
if unmatched:
    print("ERROR: could not match downloaded files: " + ", ".join(unmatched), file=sys.stderr)
    sys.exit(1)

for name, ver in specs:
    entries = sorted((f, h) for f, (n, v, h) in by_file.items() if n == name and v == ver)
    if not entries:
        print(f"ERROR: no distribution for {name}=={ver}", file=sys.stderr)
        sys.exit(1)
    print(f"{name}=={ver} \\")
    for i, (f, h) in enumerate(entries):
        cont = " \\" if i < len(entries) - 1 else ""
        print(f"    --hash=sha256:{h}{cont}")
PYEOF

# ---------------------------------------------------------------------------
# Keep the existing header comment block and replace the pinned body.
# ---------------------------------------------------------------------------
awk '!/^[[:space:]]*#/ && NF { exit } { print }' "$LOCKFILE" > "$TMP/new_lock.txt"
cat "$TMP/new_body.txt" >> "$TMP/new_lock.txt"
mv "$TMP/new_lock.txt" "$LOCKFILE"

echo "Regenerated $LOCKFILE"
echo "Next: re-run pip-audit and the workflow's validation before committing."