#!/usr/bin/env bash
set -euo pipefail
site=/home/litdwlek/public_html/sandbox-redesign
test "$(realpath "$site")" = "$site"
test -f "$site/wp-config.php"
test -d "$site/wp-content/plugins/litdecals-sandbox-connector"
url=$(wp --path="$site" --skip-plugins --skip-themes option get siteurl)
test "${url%/}" = "https://litdecals.com/sandbox-redesign"
temp=$(mktemp)
stage=""
trap 'rm -f "$temp"; if [ -n "$stage" ]; then rm -f "$stage"; fi' EXIT
curl --fail --silent --show-error --proto '=https' --tlsv1.2 \
  https://raw.githubusercontent.com/beyonditconsulting-glitc/litdecals/fdcc545ea88f52b8702310d3e0c971400590b993/connection/litdecals-sandbox-auth.php \
  -o "$temp"
php -l "$temp"
destination="$site/wp-content/mu-plugins"
mkdir -p "$destination"
test ! -L "$destination"
test ! -L "$destination/litdecals-sandbox-auth.php"
if [ -f "$destination/litdecals-sandbox-auth.php" ]; then
  backup=$(mktemp "$HOME/litdecals-sandbox-auth.backup.XXXXXX")
  cp "$destination/litdecals-sandbox-auth.php" "$backup"
fi
stage=$(mktemp "$destination/.litdecals-auth.XXXXXX")
cp "$temp" "$stage"
chmod 644 "$stage"
mv "$stage" "$destination/litdecals-sandbox-auth.php"
stage=""
echo "Sandbox connector authentication fix installed."
