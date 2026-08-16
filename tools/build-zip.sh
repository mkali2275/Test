#!/usr/bin/env bash
#
# Package the plugin as an installable WordPress zip.
#
# Produces dist/solar-power-calculator.zip, ready for
# Plugins -> Add New -> Upload Plugin.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN="solar-power-calculator"
DIST="$ROOT/dist"
ZIP="$DIST/$PLUGIN.zip"

cd "$ROOT"

if [ ! -f "$PLUGIN/$PLUGIN.php" ]; then
	echo "Cannot find $PLUGIN/$PLUGIN.php - run this from the repository." >&2
	exit 1
fi

# Refuse to ship code that will not parse on the server.
if command -v php >/dev/null 2>&1; then
	while IFS= read -r file; do
		php -l "$file" >/dev/null
	done < <(find "$PLUGIN" -name '*.php')
	echo "PHP syntax OK"
fi

mkdir -p "$DIST"
rm -f "$ZIP"

# The zip must contain the plugin folder, not its loose contents.
zip -r -q "$ZIP" "$PLUGIN" \
	-x '*.DS_Store' '*/node_modules/*' '*.map'

echo "Built $ZIP"
unzip -l "$ZIP" | tail -n +4 | head -n -2
