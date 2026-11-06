<?php
/**
 * Тест загрузки плагина: заголовок, версия, регистрация компонентов.
 *
 * Проверяет, что плагин стартует без ошибок и что WPDC_VERSION читается
 * из заголовка `Version:` (формат x.y.z).
 *
 * Запуск: php tests/bootstrap-test.php
 *
 * @package WP_Diplom
 */

require_once __DIR__ . '/wp-stubs.php';

if ( ! defined( 'WPDC_TEST_ROOT' ) ) {
	define( 'WPDC_TEST_ROOT', dirname( __DIR__ ) . '/' );
}

/**
 * Заглушка get_file_data(): читает заголовки из файла.
 *
 * @param string $file   Путь.
 * @param array  $headers Заголовки.
 * @return array
 */
function get_file_data( $file, $headers = array(), $context = '' ) {
	$data    = array();
	$content = is_readable( $file ) ? (string) file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	$content = substr( $content, 0, 8192 );

	foreach ( $headers as $field => $regex ) {
		if ( preg_match( '/^[ \t\/*#@]*' . preg_quote( $regex, '/' ) . ':(.*)$/mi', $content, $match ) && $match[1] ) {
			$data[ $field ] = trim( preg_replace( '/\s*(?:\*\/|\?>).*/', '', $match[1] ) );
		} else {
			$data[ $field ] = '';
		}
	}

	return $data;
}

function register_rewrite_rule( ...$args ) {}
function add_rewrite_rule( ...$args ) {}
function add_rewrite_tag( ...$args ) {}
function flush_rewrite_rules( ...$args ) {}
function wp_next_scheduled( ...$args ) {
	return false;
}
function wp_schedule_event( ...$args ) {
	return true;
}
function wp_clear_scheduled_hook( ...$args ) {}
function get_current_screen() {
	return null;
}

$failures = 0;

/**
 * Проверка утверждения.
 *
 * @param bool   $condition Условие.
 * @param string $label     Описание.
 */
function check( $condition, $label ) {
	global $failures;

	echo ( $condition ? '✓ ' : '✗ ' ) . $label . "\n";

	if ( ! $condition ) {
		$failures++;
	}
}

// --- Загрузка плагина ---------------------------------------------------
require_once WPDC_TEST_ROOT . 'wp-diplom.php';

check( defined( 'WPDC_VERSION' ), 'константа WPDC_VERSION определена' );
$header_version = '';

if ( preg_match( '/^[ \t\/*#@]*Version:(.*)$/mi', (string) file_get_contents( WPDC_TEST_ROOT . 'wp-diplom.php' ), $m ) ) {
	$header_version = trim( $m[1] );
}

check(
	defined( 'WPDC_VERSION' ) && $header_version === WPDC_VERSION,
	'версия совпадает с заголовком (' . ( $header_version ? $header_version : '—' ) . '): ' . ( defined( 'WPDC_VERSION' ) ? WPDC_VERSION : '—' )
);
check( (bool) preg_match( '/^\d+\.\d+\.\d+$/', WPDC_VERSION ), 'версия в формате x.y.z' );

// Классы подключаются конструктором плагина.
$plugin = wpdc();

check( class_exists( 'WPDC_Plugin' ), 'класс WPDC_Plugin загружен' );
check( class_exists( 'WPDC_Post_Types' ), 'класс WPDC_Post_Types загружен' );
check( class_exists( 'WPDC_Certificate' ), 'класс WPDC_Certificate загружен' );
check( class_exists( 'WPDC_Renderer' ), 'класс WPDC_Renderer загружен' );
check( class_exists( 'WPDC_Admin' ), 'класс WPDC_Admin загружен' );
check( class_exists( 'WPDC_Frontend' ), 'класс WPDC_Frontend загружен' );
check( class_exists( 'WPDC_Email' ), 'класс WPDC_Email загружен' );
check( class_exists( 'WPDC_PDF_Writer' ), 'класс WPDC_PDF_Writer загружен' );
check( class_exists( 'WPDC_PDF_Font' ), 'класс WPDC_PDF_Font загружен' );

// --- Инициализация ------------------------------------------------------

check( $plugin instanceof WPDC_Plugin, 'wpdc() возвращает экземпляр плагина' );
check( $plugin === wpdc(), 'wpdc() — синглтон' );

$thrown = false;

try {
	$plugin->init();
} catch ( Throwable $e ) {
	$thrown = $e->getMessage();
}

check( false === $thrown, 'init() выполняется без исключений' . ( $thrown ? ': ' . $thrown : '' ) );

check( null !== $plugin->component( 'admin' ), 'компонент admin зарегистрирован' );
check( null !== $plugin->component( 'frontend' ), 'компонент frontend зарегистрирован' );
check( null !== $plugin->component( 'post_types' ), 'компонент post_types зарегистрирован' );
check( null !== $plugin->component( 'email' ), 'компонент email зарегистрирован' );

// --- Шрифты на месте ----------------------------------------------------
$fonts = array( 'DejaVuSans.ttf', 'DejaVuSans-Bold.ttf', 'DejaVuSerif.ttf' );

foreach ( $fonts as $font ) {
	check(
		is_readable( WPDC_TEST_ROOT . 'assets/fonts/' . $font ),
		'шрифт на месте: ' . $font . ' (' . round( filesize( WPDC_TEST_ROOT . 'assets/fonts/' . $font ) / 1024 ) . ' КБ)'
	);
}

// --- Обязательные файлы -------------------------------------------------
$required = array(
	'uninstall.php',
	'readme.txt',
	'README.md',
	'CHANGELOG.md',
	'includes/class-wpdc-plugin.php',
	'includes/pdf/class-wpdc-pdf-writer.php',
);

foreach ( $required as $file ) {
	check( file_exists( WPDC_TEST_ROOT . $file ), 'файл на месте: ' . $file );
}

echo "\n";
if ( $failures > 0 ) {
	echo "Провалено проверок: {$failures}\n";
	exit( 1 );
}

echo "Все проверки пройдены.\n";
