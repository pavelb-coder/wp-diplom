# Changelog

Все заметные изменения темы **WP Diplom**.
Формат версий — `x.y.z` ([SemVer](https://semver.org/lang/ru/)): `MAJOR.MINOR.PATCH`.

## История изменений

### 1.0.0 — 2026-11-06

- Первый релиз темы.
- Каркас классической темы: `header.php`, `footer.php`, `index.php`, `front-page.php`, `single.php`, `page.php`, `archive.php`, `search.php`, `404.php`, `comments.php`, `sidebar.php`.
- `inc/theme-setup.php`: `title-tag`, `post-thumbnails`, `custom-logo`, HTML5-разметка, меню, области виджетов.
- `inc/post-types.php`: кастомный тип записи «Проект» (архив `/projects/`) и таксономия «Технологии».
- `inc/customizer.php`: заголовок/подзаголовок главного блока, текст подвала, акцентный цвет.
- `inc/widgets.php`: виджет «Последние проекты».
- `inc/security.php`: чистка `wp_head`, отключение XML-RPC.
- `assets/css/main.css`: адаптивная вёрстка (980 / 780 / 520 px), тёмный подвал, сетка карточек.
- `assets/js/main.js`: мобильное меню, `IntersectionObserver` для карточек.
- Версия вынесена в единый источник истины — заголовок `Version:` в `style.css`; константа `WP_DIPLOM_VERSION` читает её оттуда.
- `bin/bump-version.sh` и `bin/check-version.sh` — подъём и проверка версии.
- CI: GitHub Actions проверяет синтаксис PHP и согласованность версии.
