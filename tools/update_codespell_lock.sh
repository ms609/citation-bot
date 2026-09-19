#!/usr/bin/env bash
# Regenerates .github/codespell-requirements.txt (codespell 2.4.3, pip --require-hashes).
# Pure-Python closure, so hashes match everywhere; run on Linux once codespell gains
# platform-specific dependencies. Usage: bash tools/update_codespell_lock.sh
# (PYTHON env selects the interpreter, default python3).
set -euo pipefail

LOCKFILE=".github/codespell-requirements.txt"
PYTHON="${PYTHON:-python3}"
CODESPELL_VERSION="2.4.3"

cd "$(git rev-parse --show-toplevel 2>/dev/null || echo "$PWD")"

# Warn off Linux: hashes only match CI while the closure stays pure-Python.
if [[ "$(uname -s)" != "Linux" ]]; then
    echo "WARNING: not on Linux (the workflow's target); hashes match CI only while the closure stays pure-Python." >&2
fi

if [[ ! -f "$LOCKFILE" ]]; then
    echo "ERROR: $LOCKFILE not found." >&2
    exit 1
fi

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

# Resolve the closure in a fresh venv; exclude pip/wheel tooling.
"$PYTHON" -m venv "$TMP/venv"
VENV_PIP="$TMP/venv/bin/pip"
if [[ ! -x "$VENV_PIP" ]]; then
    VENV_PIP="$TMP/venv/Scripts/pip"
fi
"$VENV_PIP" install --quiet --upgrade pip
"$VENV_PIP" install --quiet "codespell==$CODESPELL_VERSION"
"$VENV_PIP" freeze | grep -v -E '^(pip|wheel)==' > "$TMP/freeze.txt"

# Download each pinned dist to hash the exact files pip selects.
mkdir -p "$TMP/dl"
while IFS= read -r pkg; do
    (cd "$TMP/dl" && "$VENV_PIP" download --quiet --no-deps "$pkg")
done < "$TMP/freeze.txt"

# Emit the pinned body from the downloaded files.
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

# Keep the header comment, replace the pinned body.
awk '!/^[[:space:]]*#/ && NF { exit } { print }' "$LOCKFILE" > "$TMP/new_lock.txt"
cat "$TMP/new_body.txt" >> "$TMP/new_lock.txt"
mv "$TMP/new_lock.txt" "$LOCKFILE"

echo "Regenerated $LOCKFILE"
echo "Next: re-run pip-audit and the workflow's codespell run before committing."
