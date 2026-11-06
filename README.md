# WP Diplom

Дипломный проект — классическая тема WordPress с кастомными типами записей, виджетами и поддержкой редактора блоков.

- **Тема:** WP Diplom `v1.0.0`
- **Требования:** WordPress 6.0+, PHP 7.4+
- **Лицензия:** GPL v2 или новее
- **Репозиторий:** https://github.com/pavelb-coder/wp-diplom

## Установка

1. Скачайте репозиторий в `wp-content/themes/wp-diplom`:

   ```bash
   cd wp-content/themes
   git clone https://github.com/pavelb-coder/wp-diplom.git
   ```

   Либо соберите архив и загрузите его через **Внешний вид → Темы → Добавить новую → Загрузить тему**.

2. Активируйте тему в админке: **Внешний вид → Темы**.
3. Настройте меню: **Внешний вид → Меню** — области «Главное меню» и «Меню в подвале».
4. Заполните виджеты: **Внешний вид → Виджеты** — «Боковая колонка» и «Подвал».
5. Настройки главного блока и акцентного цвета: **Внешний вид → Настроить**.
6. Для главной страницы: **Настройки → Чтение** → «Статическая страница» → выберите страницу (используется `front-page.php`).

## Структура

```
wp-diplom/
├── .github/workflows/ci.yml # PHP lint, проверка версии, сборка zip по тегу
├── assets/
│   ├── css/main.css        # стили темы
│   └── js/main.js          # мобильное меню, анимация карточек
├── bin/
│   ├── bump-version.sh     # подъём версии x.y.z
│   └── check-version.sh    # проверка формата и согласованности версии
├── tests/
│   └── version-resolution.php # тест чтения версии из style.css
├── inc/
│   ├── theme-setup.php     # возможности темы, меню, сайдбары
│   ├── enqueue.php         # подключение CSS/JS
│   ├── template-tags.php   # хелперы: мета, пагинация, крошки
│   ├── post-types.php      # CPT «Проект» + таксономия «Технологии»
│   ├── customizer.php      # настройки темы
│   ├── widgets.php         # виджет «Последние проекты»
│   └── security.php        # чистка head, отключение XML-RPC
├── template-parts/
│   ├── content-card.php    # карточка записи
│   └── content-none.php    # «ничего не найдено»
├── front-page.php          # главная: hero + проекты + блог
├── header.php / footer.php
├── index.php / archive.php / search.php / 404.php
├── single.php / page.php / sidebar.php / comments.php
├── style.css               # заголовок темы (Version: 1.0.0 — источник истины)
├── screenshot.png          # превью темы (1200×900)
├── CHANGELOG.md            # история изменений
└── README.md               # эта документация
```

## Возможности

- Адаптивная вёрстка (breakpoints 980 / 780 / 520 px)
- `title-tag`, `post-thumbnails`, `custom-logo`, HTML5-разметка
- Кастомный тип записи **Проект** (архив `/projects/`) и таксономия **Технологии**
- Виджет «Последние проекты» с настройкой количества
- Customizer: hero-заголовок и подзаголовок, текст подвала, акцентный цвет
- Доступность: skip-link, ARIA-атрибуты, `prefers-reduced-motion`
- Безопасность: экранирование вывода, `abspath`-guard во всех PHP-файлах, отключённый XML-RPC
- Интернационализация: text domain `wp-diplom`

## Разработка

```bash
# проверка синтаксиса всех PHP-файлов
find . -name "*.php" -not -path "./.git/*" -exec php -l {} \;

# проверка версии (формат + согласованность во всех файлах)
./bin/check-version.sh

# тест резолвинга версии из style.css (без установки WordPress)
php tests/version-resolution.php
```

## Версионирование

Версия темы — строго в формате **`x.y.z`** ([SemVer](https://semver.org/lang/ru/) → `MAJOR.MINOR.PATCH`):

| Часть | Когда повышать | Пример |
|---|---|---|
| `MAJOR` (x) | несовместимые изменения: переименование шаблонов, удаление хуков и функций темы | `1.0.0` → `2.0.0` |
| `MINOR` (y) | новая функциональность без поломок: новый шаблон, CPT, виджет | `1.0.0` → `1.1.0` |
| `PATCH` (z) | исправления, стили, тексты | `1.0.0` → `1.0.1` |

**Единственный источник истины — заголовок `Version:` в `style.css`.** WordPress читает его в любом случае (список тем, обновления), поэтому дублировать версию в PHP не нужно: константа `WP_DIPLOM_VERSION` в `functions.php` читает значение из `style.css` через `wp_get_theme()` и передаётся в `wp_enqueue_style()` / `wp_enqueue_script()` как параметр версии — кэш браузера сбрасывается автоматически при подъёме версии.

```bash
# поднять версию: обновит style.css, README.md и добавит секцию в CHANGELOG.md
./bin/bump-version.sh 1.1.0

# посмотреть, что изменится, без записи
./bin/bump-version.sh 1.1.0 --dry-run

# проверить согласованность
./bin/check-version.sh
```

Проверки автоматизированы в CI (`.github/workflows/ci.yml`): синтаксис PHP на 7.4 / 8.1 / 8.3, формат `x.y.z`, совпадение git-тега `vX.Y.Z` с `style.css` и сборка `wp-diplom-X.Y.Z.zip` при пуше тега.

Релиз:

```bash
./bin/bump-version.sh 1.1.0
# впишите изменения в CHANGELOG.md
git commit -am "chore(release): v1.1.0"
git tag -a v1.1.0 -m "v1.1.0"
git push --follow-tags
```

## Changelog

Полная история — в [CHANGELOG.md](CHANGELOG.md). Текущая версия: **1.0.0**.

