#!/usr/bin/env bash
# CI check for scripts/deploy-sync.sh: deploys twice into a fake server folder and verifies that
# server-only files survive (config, backups, uploads, SSL validation) and nothing private is published.
set -euo pipefail
cd "$(dirname "$0")/.."
fail=0
check() { if eval "$2"; then echo "ok    $1"; else echo "FAIL  $1"; fail=1; fi; }

for layout in public_html ""; do
  R=$(mktemp -d)
  W=${layout:-public}
  mkdir -p "$R/app/storage/backups" "$R/$W/uploads" "$R/$W/.well-known"
  echo keep > "$R/app/config.php"
  echo keep > "$R/app/storage/backups/backup.sql.gz"
  echo keep > "$R/$W/uploads/photo.webp"
  echo keep > "$R/$W/.well-known/acme-challenge"
  echo stale > "$R/app/lib/old.php" 2>/dev/null || { mkdir -p "$R/app/lib"; echo stale > "$R/app/lib/old.php"; }
  [ -n "$layout" ] && echo placeholder > "$R/$W/default.php"

  echo "== layout: ${layout:-whole project}"
  scripts/deploy-sync.sh "$R" "$layout"
  scripts/deploy-sync.sh "$R" "$layout"   # second run must be safe too

  check "config kept"            "[ -f '$R/app/config.php' ]"
  check "backups kept"           "[ -f '$R/app/storage/backups/backup.sql.gz' ]"
  check "uploads kept"           "[ -f '$R/$W/uploads/photo.webp' ]"
  check "SSL validation kept"    "[ -f '$R/$W/.well-known/acme-challenge' ]"
  check "front controller"       "[ -f '$R/$W/index.php' ]"
  check "assets"                 "[ -f '$R/$W/assets/css/admin.css' ]"
  check "app code"               "[ -f '$R/app/bootstrap.php' ] && [ -f '$R/app/cli.php' ]"
  check "removed files deleted"  "[ ! -e '$R/app/lib/old.php' ]"
  check "no tests/tooling"       "[ ! -e '$R/tests' ] && [ ! -e '$R/node_modules' ] && [ ! -e '$R/scripts' ] && [ ! -e '$R/.git' ]"
  if [ -n "$layout" ]; then
    check "code outside web root" "[ ! -e '$R/$W/app' ] && [ ! -e '$R/public' ]"
    check "no dev router online"  "[ ! -e '$R/$W/router.php' ]"
    check "host placeholder gone" "[ ! -e '$R/$W/default.php' ]"
  fi
  rm -rf -- "$R"
done
exit $fail
