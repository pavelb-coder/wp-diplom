#!/usr/bin/env bash
# Проверка, что версия темы везде в формате x.y.z и синхронизирована.
# Используется локально и в CI (.github/workflows/ci.yml).
set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

errors=0

## 1. style.css: заголовок Version обязателен и в формате x.y.z
VERSION="$(sed -n 's/^Version: *//p' style.css | head -1 | tr -d '[:space:]')"

if [[ -z "$VERSION" ]]; then
	echo "✗ style.css: не найден заголовок «Version:»"
	errors=$((errors + 1))
elif [[ ! "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
	echo "✗ style.css: версия «$VERSION» не в формате x.y.z"
	errors=$((errors + 1))
else
	echo "✓ style.css: Version: $VERSION (x.y.z)"
fi

## 2. functions.php не должен хардкодить версию — только читать из style.css
DEFINE_LINE="$(grep -E "define\(\s*'WP_DIPLOM_VERSION'" functions.php || true)"

if [[ -z "$DEFINE_LINE" ]]; then
	echo "✗ functions.php: константа WP_DIPLOM_VERSION не найдена"
	errors=$((errors + 1))
elif [[ "$DEFINE_LINE" != *"wp_diplom_theme_version()"* ]]; then
	echo "✗ functions.php: версия захардкожена → $DEFINE_LINE"
	echo "    ожидается: define( 'WP_DIPLOM_VERSION', wp_diplom_theme_version() );"
	errors=$((errors + 1))
else
	echo "✓ functions.php: версия берётся из style.css"
fi

## 3. README.md: строка с версией должна совпадать
README_VERSION="$(sed -n 's/.*\*\*Тема:\*\* WP Diplom `v\([0-9.]*\)`.*/\1/p' README.md | head -1)"
if [[ -n "$README_VERSION" && "$README_VERSION" != "$VERSION" ]]; then
	echo "✗ README.md: v$README_VERSION не совпадает с style.css ($VERSION)"
	errors=$((errors + 1))
else
	echo "✓ README.md: v${README_VERSION:-—} совпадает"
fi

## 4. CHANGELOG.md: текущая версия должна иметь запись
if [[ -f CHANGELOG.md ]] && ! grep -qE "^### ${VERSION//./\\.}" CHANGELOG.md; then
	echo "✗ CHANGELOG.md: нет секции для версии $VERSION"
	errors=$((errors + 1))
else
	echo "✓ CHANGELOG.md: секция $VERSION присутствует"
fi

## 5. Прочие файлы не должны содержать захардкоженных версий
# Исключаем: bin/ и tests/ (там версии по делу), fallback в функции версии
# и технические заголовки style.css (Requires at least / Tested up to / Requires PHP).
STRAY="$(grep -rnE "[0-9]+\.[0-9]+\.[0-9]+" --include="*.php" --include="*.js" . \
	| grep -vE "^\./(bin|tests|\.git)/" \
	| grep -vE "wp_diplom_theme_version|Requires at least|Tested up to|Requires PHP|Version:|\? \\\$raw" || true)"
if [[ -n "$STRAY" ]]; then
	echo "⚠ Найдены числа формата x.y.z в коде — проверьте, не версия ли это:"
	echo "$STRAY" | sed 's/^/    /'
fi

echo
if [[ "$errors" -gt 0 ]]; then
	echo "Проверка не пройдена: ошибок — $errors"
	exit 1
fi

echo "Проверка пройдена: версия $VERSION согласована во всех файлах."
