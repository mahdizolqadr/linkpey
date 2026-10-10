#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
REPO="$ROOT/repo"
VERSION="$(grep -i '^Stable tag:' "$REPO/readme.txt" | awk '{print $3}')"
OUT="$ROOT/linkpey-${VERSION}.zip"
TMP="$(mktemp -d)"
mkdir -p "$TMP/linkpey"
rsync -a --exclude='.git' "$REPO/" "$TMP/linkpey/"
(cd "$TMP" && zip -rq "$OUT" linkpey)
rm -rf "$TMP"
echo "Wrote $OUT"
