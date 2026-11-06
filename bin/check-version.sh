#!/usr/bin/env bash
# Проверка версии плагина: формат x.y.z и согласованность во всех файлах.
set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

MAIN="wp-diplom.php"
errors=0

## 1. Заголовок Version в основном файле
VERSION="$(sed -n 's/^ \* Version: *//p' "$MAIN" | head -1 | tr -d '[:space:]')"

if [[ -z "$VERSION" ]]; then
	echo "✗ $MAIN: не найден заголовок «Version:»"
	errors=$((errors + 1))
elif [[ ! "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
	echo "✗ $MAIN: версия «$VERSION» не в формате x.y.z"
	errors=$((errors + 1))
else
	echo "✓ $MAIN: Version: $VERSION (x.y.z)"
fi

## 2. Версия не должна хардкодиться в коде — только читаться из заголовка
DEFINE_LINE="$(grep -E "define\(\s*'WPDC_VERSION'" "$MAIN" || true)"

if [[ -z "$DEFINE_LINE" ]]; then
	echo "✗ $MAIN: константа WPDC_VERSION не найдена"
	errors=$((errors + 1))
elif [[ "$DEFINE_LINE" != *"wpdc_version()"* ]]; then
	echo "✗ $MAIN: версия захардкожена → $DEFINE_LINE"
	errors=$((errors + 1))
else
	echo "✓ $MAIN: WPDC_VERSION читается из заголовка"
fi

## 3. readme.txt: Stable tag совпадает
STABLE="$(sed -n 's/^Stable tag: *//p' readme.txt | head -1 | tr -d '[:space:]')"

if [[ -n "$STABLE" && "$STABLE" != "$VERSION" ]]; then
	echo "✗ readme.txt: Stable tag $STABLE не совпадает с $VERSION"
	errors=$((errors + 1))
else
	echo "✓ readme.txt: Stable tag ${STABLE:-—} совпадает"
fi

## 4. CHANGELOG.md содержит секцию текущей версии
if [[ -f CHANGELOG.md ]] && ! grep -qE "^### ${VERSION//./\\.}" CHANGELOG.md; then
	echo "✗ CHANGELOG.md: нет секции для версии $VERSION"
	errors=$((errors + 1))
else
	echo "✓ CHANGELOG.md: секция $VERSION присутствует"
fi

## 5. README.md указывает ту же версию
README_VERSION="$(sed -n 's/.*\*\*Версия:\*\* *\([0-9.]*\).*/\1/p' README.md | head -1)"

if [[ -n "$README_VERSION" && "$README_VERSION" != "$VERSION" ]]; then
	echo "✗ README.md: версия $README_VERSION не совпадает с $VERSION"
	errors=$((errors + 1))
else
	echo "✓ README.md: версия ${README_VERSION:-—} совпадает"
fi

## 6. Предупреждение о числах вида x.y.z в PHP (кроме тестов и шрифтов)
STRAY="$(grep -rnE "[0-9]+\.[0-9]+\.[0-9]+" --include="*.php" . \
	| grep -vE "^\./(bin|tests|\.git)/" \
	| grep -vE "Requires (at least|PHP)|Tested up to|Version:|\\\$version|@since" || true)"

if [[ -n "$STRAY" ]]; then
	echo "⚠ Найдены числа формата x.y.z — проверьте, не версия ли это:"
	echo "$STRAY" | sed 's/^/    /'
fi

echo
if [[ "$errors" -gt 0 ]]; then
	echo "Проверка не пройдена: ошибок — $errors"
	exit 1
fi

echo "Проверка пройдена: версия $VERSION согласована во всех файлах."
