<?php
/**
 * Plugin Name:       WP Diplom — генератор сертификатов
 * Plugin URI:        https://github.com/pavelb-coder/wp-diplom
 * Description:       Автоматическая генерация сертификатов и дипломов в PDF: визуальные бланки, массовая выгрузка из CSV, отправка на e-mail и проверка подлинности по коду.
 * Version:           1.2.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            pavelb-coder
 * Author URI:        https://github.com/pavelb-coder
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-diplom
 * Domain Path:       /languages
 *
 * @package WP_Diplom
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Вторая копия плагина (например, распакованная в каталог с другим именем):
 * не объявляем те же классы и функции повторно, иначе сайт упадёт с
 * «Cannot declare class …».
 */
if ( defined( 'WPDC_VERSION' ) || class_exists( 'WPDC_Plugin' ) ) {
	return;
}

/*
 * Имена в этом плагине начинаются с WPDC и wpdc намеренно.
 *
 * Префикс WPD/wpd занят другим плагином того же автора — WP Olympiad Design
 * (github.com/pavelb-coder/wp-design). Совпадение давало фатальную ошибку
 * «Cannot declare class ..., because the name is already in use» и подмену
 * путей плагина. Не сокращайте префикс: тест tests/conflict-test.php следит
 * за этим.
 *
 * define() вызывается только если константа ещё не определена: плагин не
 * должен ни падать, ни затирать чужие значения при повторной загрузке.
 */
if ( ! defined( 'WPDC_FILE' ) ) {
	define( 'WPDC_FILE', __FILE__ );
}

if ( ! defined( 'WPDC_DIR' ) ) {
	define( 'WPDC_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'WPDC_URL' ) ) {
	define( 'WPDC_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'WPDC_BASENAME' ) ) {
	define( 'WPDC_BASENAME', plugin_basename( __FILE__ ) );
}

/**
 * Версия плагина в формате x.y.z.
 *
 * Единственный источник истины — заголовок `Version:` выше. Значение читается
 * из него, поэтому дублировать версию в коде не нужно (см. bin/check-version.sh).
 *
 * @return string
 */
if ( ! function_exists( 'wpdc_version' ) ) {
/**
 * Версия плагина (см. комментарий ниже).
 */
function wpdc_version() {
	static $version = null;

	if ( null !== $version ) {
		return $version;
	}

	$data    = get_file_data(
		WPDC_FILE,
		array(
			'Version' => 'Version',
		)
	);
	$raw     = isset( $data['Version'] ) ? (string) $data['Version'] : '';
	$version = preg_match( '/^\d+\.\d+\.\d+$/', $raw ) ? $raw : '1.0.0';

	return $version;
}
}

if ( ! defined( 'WPDC_VERSION' ) ) {
	define( 'WPDC_VERSION', wpdc_version() );
}

require_once WPDC_DIR . 'includes/class-wpdc-plugin.php';

/**
 * Возвращает экземпляр плагина.
 *
 * @return WPDC_Plugin
 */
function wpdc() {
	return WPDC_Plugin::instance();
}

/**
 * Точка входа.
 */
function wpdc_bootstrap() {
	try {
		$plugin = wpdc();
		$plugin->init();
	} catch ( Throwable $e ) {
		// Ничего не роняем: сайт должен открываться даже при внутренней ошибке.
		update_option(
			'wpdc_activation_error',
			array(
				'message' => $e->getMessage(),
				'file'    => $e->getFile(),
				'line'    => $e->getLine(),
				'php'     => PHP_VERSION,
				'time'    => time(),
			),
			false
		);
	}
}
add_action( 'plugins_loaded', 'wpdc_bootstrap' );

register_activation_hook( __FILE__, array( 'WPDC_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WPDC_Plugin', 'deactivate' ) );
