#!/usr/bin/env bash
# Поднять версию темы. Формат версии — строго x.y.z (SemVer).
#
# Использование:
#   ./bin/bump-version.sh 1.1.0            # поднять версию, добавить запись в CHANGELOG
#   ./bin/bump-version.sh 1.1.0 --dry-run  # только показать, что изменится
#
# Что обновляется:
#   style.css      — заголовок `Version:` (единственный источник истины для WP)
#   README.md      — строка «**Тема:** WP Diplom `vX.Y.Z`»
#   CHANGELOG.md   — новая секция сверху
#
# functions.php трогать не нужно: константа WP_DIPLOM_VERSION читает style.css.
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

CURRENT="$(sed -n 's/^Version: *//p' style.css | head -1 | tr -d '[:space:]')"
echo "Текущая версия: $CURRENT"
echo "Новая версия:   $NEW_VERSION"

if [[ "$CURRENT" == "$NEW_VERSION" ]]; then
	echo "Версия не изменилась — выходить некуда." >&2
	exit 1
fi

# Проверка, что новая версия действительно выше текущей.
if [[ "$(printf '%s\n%s\n' "$CURRENT" "$NEW_VERSION" | sort -V | tail -1)" != "$NEW_VERSION" ]]; then
	echo "Ошибка: новая версия ниже текущей ($CURRENT → $NEW_VERSION)." >&2
	exit 1
fi

if [[ "$DRY_RUN" == "--dry-run" ]]; then
	echo "[dry-run] style.css: Version: $CURRENT → $NEW_VERSION"
	echo "[dry-run] README.md: v$CURRENT → v$NEW_VERSION"
	echo "[dry-run] CHANGELOG.md: добавить секцию [$NEW_VERSION]"
	exit 0
fi

# 1. style.css — единственный источник истины.
sed -i.bak -E "s/^Version: *${CURRENT//./\\.}$/Version: ${NEW_VERSION}/" style.css
rm -f style.css.bak

# 2. README.md — строка с версией темы.
sed -i.bak -E "s/(\*\*Тема:\*\* WP Diplom \`)v[0-9]+\.[0-9]+\.[0-9]+/\1v${NEW_VERSION}/" README.md
rm -f README.md.bak

# 3. CHANGELOG.md — новая секция сверху, под заголовком «## История изменений».
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
echo "Не забудьте: вписать изменения в CHANGELOG.md, закоммитить и запушить тег."
echo "  git commit -am 'chore(release): v$NEW_VERSION'"
echo "  git tag -a v$NEW_VERSION -m 'v$NEW_VERSION' && git push --follow-tags"
