#!/usr/bin/env bash
# Поднять версию плагина. Формат — строго x.y.z (SemVer).
#
# Использование:
#   ./bin/bump-version.sh 1.1.0            # поднять версию
#   ./bin/bump-version.sh 1.1.0 --dry-run  # показать план изменений
#
# Обновляет: заголовок Version в wp-diplom.php (источник истины), Stable tag
# в readme.txt, версию в README.md, добавляет секцию в CHANGELOG.md.
set -euo pipefail

NEW_VERSION="${1:-}"
DRY_RUN="${2:-}"

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if [[ -z "$NEW_VERSION" ]]; then
	echo "Ошибка: не указана версия." >&2
	echo "Использование: ./bin/bump-version.sh 1.1.0 [--dry-run]" >&2
	exit 1
fi

if [[ ! "$NEW_VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
	echo "Ошибка: версия «$NEW_VERSION» не в формате x.y.z (например 1.1.0)." >&2
	exit 1
fi

CURRENT="$(sed -n 's/^ \* Version: *//p' wp-diplom.php | head -1 | tr -d '[:space:]')"
echo "Текущая версия: $CURRENT"
echo "Новая версия:   $NEW_VERSION"

if [[ "$CURRENT" == "$NEW_VERSION" ]]; then
	echo "Версия не изменилась." >&2
	exit 1
fi

if [[ "$(printf '%s\n%s\n' "$CURRENT" "$NEW_VERSION" | sort -V | tail -1)" != "$NEW_VERSION" ]]; then
	echo "Ошибка: новая версия ниже текущей ($CURRENT → $NEW_VERSION)." >&2
	exit 1
fi

if [[ "$DRY_RUN" == "--dry-run" ]]; then
	echo "[dry-run] wp-diplom.php: Version: $CURRENT → $NEW_VERSION"
	echo "[dry-run] readme.txt: Stable tag $CURRENT → $NEW_VERSION"
	echo "[dry-run] README.md: Версия $CURRENT → $NEW_VERSION"
	echo "[dry-run] CHANGELOG.md: добавить секцию [$NEW_VERSION]"
	exit 0
fi

# 1. Заголовок плагина — источник истины.
sed -i.bak -E "s/^( \* Version: *)${CURRENT//./\\.}$/\1${NEW_VERSION}/" wp-diplom.php
rm -f wp-diplom.php.bak

# 2. readme.txt
sed -i.bak -E "s/^Stable tag: .*/Stable tag: ${NEW_VERSION}/" readme.txt
rm -f readme.txt.bak

# 3. README.md
sed -i.bak -E "s/(\*\*Версия:\*\*) [0-9]+\.[0-9]+\.[0-9]+/\1 ${NEW_VERSION}/" README.md
rm -f README.md.bak

# 4. CHANGELOG.md
DATE="$(date +%Y-%m-%d)"

if [[ -f CHANGELOG.md ]]; then
	awk -v ver="$NEW_VERSION" -v date="$DATE" '
		{ print }
		/^## История изменений$/ && !done {
			print ""
			print "### " ver " — " date
			print ""
			print "- _(опишите изменения)_"
			done = 1
		}
	' CHANGELOG.md > CHANGELOG.md.tmp && mv CHANGELOG.md.tmp CHANGELOG.md
else
	printf '# Changelog\n\n## История изменений\n\n### %s — %s\n\n- _(опишите изменения)_\n' \
		"$NEW_VERSION" "$DATE" > CHANGELOG.md
fi

echo "Готово: $CURRENT → $NEW_VERSION"
./bin/check-version.sh
echo
echo "Дальше:"
echo "  git commit -am 'chore(release): v$NEW_VERSION'"
echo "  git tag -a v$NEW_VERSION -m 'v$NEW_VERSION' && git push --follow-tags"
