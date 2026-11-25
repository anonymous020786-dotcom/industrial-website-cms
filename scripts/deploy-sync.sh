#!/usr/bin/env bash
# Upload the project to the server with rsync (used by .github/workflows/deploy.yml and tested in CI).
#
#   scripts/deploy-sync.sh DEST [WEB_DIR] [RSH]
#     DEST     user@host:/home/u123/domains/example.com   (or a local folder, for testing)
#     WEB_DIR  empty: the whole project goes into DEST (document root = DEST/public)
#              public_html: code goes into DEST, outside the web root; public/* goes into DEST/public_html
#     RSH      remote shell for rsync -e, e.g. "ssh -p 65002 -i key"
#
# Never touched on the server: app/config.php, app/storage/ (backups, logs), uploads, .well-known (SSL).
set -euo pipefail
DEST="${1:?destination required}"
WEB_DIR="${2:-}"
RSH="${3:-}"
cd "$(dirname "$0")/.."
E=()
[ -n "$RSH" ] && E=(-e "$RSH")

if [ -n "$WEB_DIR" ]; then
  case "$WEB_DIR" in *[!A-Za-z0-9_.-]*|.*) echo "WEB_DIR must be a plain folder name like public_html" >&2; exit 1;; esac
  # 1) Code outside the web root. Excluded paths are never deleted, so the web root survives --delete.
  rsync -az --delete --exclude-from=.deployignore --exclude=/public/ --exclude="/$WEB_DIR/" --exclude=/.htaccess "${E[@]}" ./ "$DEST/"
  # 2) Web root: only the contents of public/ (uploads, SSL validation files and the dev router are left alone).
  rsync -az --delete --exclude=/uploads/ --exclude=/.well-known/ --exclude=/router.php "${E[@]}" ./public/ "$DEST/$WEB_DIR/"
else
  rsync -az --delete --exclude-from=.deployignore "${E[@]}" ./ "$DEST/"
fi
