#!/usr/bin/env bash
# Creează build/cupons-theme.zip și build/cupons-core.zip pentru upload în WordPress
# (Aspect → Teme → Adaugă → Încarcă / Module → Adaugă → Încarcă).
#
# Folosește `git archive`, deci arhivele conțin exact ce e COMIS (fără fișiere locale,
# fără secrete). Fă commit înainte de build. Funcționează în Git Bash pe Windows.
#
#   bash scripts/build-zip.sh          # din HEAD
#   bash scripts/build-zip.sh v1.0.0   # dintr-un tag/commit anume

set -euo pipefail

cd "$(dirname "$0")/.."

REF=${1:-HEAD}
OUT=build
mkdir -p "$OUT"

if [ -n "$(git status --porcelain -- wp-content)" ]; then
  echo "Atenție: ai modificări necomise în wp-content/; nu vor fi incluse în zip." >&2
fi

build() {
  local name=$1 path=$2
  if ! git cat-file -e "$REF:$path" 2>/dev/null; then
    echo "Sar peste $name: $path nu există în $REF." >&2
    return
  fi
  git archive --format=zip --prefix="$name/" -o "$OUT/$name.zip" "$REF:$path"
  echo "OK  $OUT/$name.zip"
}

build cupons-core  wp-content/plugins/cupons-core
build cupons-theme wp-content/themes/cupons-theme
