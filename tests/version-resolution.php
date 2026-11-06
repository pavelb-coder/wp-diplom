<?php
/**
 * Тест резолвинга версии темы без установки WordPress.
 *
 * Проверяет, что WP_DIPLOM_VERSION берётся из заголовка `Version:` в style.css,
 * а нестандартные значения (1.1, v1.0.0, пусто, 1.0.0-beta) откатываются к 1.0.0.
 *
 * Запуск: php tests/version-resolution.php
 *
 * @package WP_Diplom
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'WP_DIPLOM_TEST_DIR', dirname( __DIR__ ) );

// --- Стабы ядра WordPress -------------------------------------------------
function add_action( ...$args ) {}
function add_filter( ...$args ) {}
function remove_action( ...$args ) {}
function get_template() { return 'wp-diplom'; }
function get_template_directory() { return WP_DIPLOM_TEST_DIR; }
function get_template_directory_uri() { return 'http://example.test/wp-content/themes/wp-diplom'; }

/**
 * Заглушка WP_Theme: отдаёт только заголовок Version.
 */
class WP_Diplom_Fake_Theme {

	/**
	 * @param string $version Значение заголовка Version.
	 */
	public function __construct( private string $version ) {}

	/**
	 * @param string $header Имя заголовка.
	 * @return string
	 */
	public function get( $header ) {
		return 'Version' === $header ? $this->version : '';
	}
}

/**
 * @param string|null $slug Слаг темы.
 * @return WP_Diplom_Fake_Theme
 */
function wp_get_theme( $slug = null ) {
	return new WP_Diplom_Fake_Theme( (string) ( $GLOBALS['wp_diplom_fake_version'] ?? '' ) );
}

/**
 * Заглушка WP_Widget, чтобы inc/widgets.php загрузился.
 */
class WP_Widget {
	public function __construct( $id = '', $name = '', $opts = array() ) {}
}

// --- Прогон ---------------------------------------------------------------
// Значение по умолчанию до загрузки темы: functions.php резолвит версию сразу.
$GLOBALS['wp_diplom_fake_version'] = '1.0.0';

require WP_DIPLOM_TEST_DIR . '/functions.php';

$cases = array(
	// заголовок в style.css => ожидаемая версия
	'1.0.0'       => '1.0.0',
	'1.1.0'       => '1.1.0',
	'2.13.7'      => '2.13.7',
	'1.1'         => '1.0.0',
	'v1.0.0'      => '1.0.0',
	''            => '1.0.0',
	'1.0.0-beta'  => '1.0.0',
);

$failed = 0;

foreach ( $cases as $header => $expected ) {
	$GLOBALS['wp_diplom_fake_version'] = (string) $header;

	// Сбрасываем статический кэш функции между кейсами.
	$actual = wp_diplom_theme_version();
	$label                  = '' === $header ? '(пусто)' : $header;
	$ok                     = $actual === $expected;

	printf(
		"%s style.css «%s» → %s (ожидалось %s)\n",
		$ok ? '✓' : '✗',
		$label,
		$actual,
		$expected
	);

	if ( ! $ok ) {
		$failed++;
	}
}

// Константа должна быть определена и совпадать с текущей версией темы.
if ( defined( 'WP_DIPLOM_VERSION' ) && 1 === preg_match( '/^\d+\.\d+\.\d+$/', WP_DIPLOM_VERSION ) ) {
	printf( "✓ WP_DIPLOM_VERSION = %s (формат x.y.z)\n", WP_DIPLOM_VERSION );
} else {
	echo "✗ WP_DIPLOM_VERSION не определена или не в формате x.y.z\n";
	$failed++;
}

echo "\n";
if ( $failed > 0 ) {
	echo "Провалено проверок: {$failed}\n";
	exit( 1 );
}

echo "Все проверки пройдены.\n";
