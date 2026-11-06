<?php
/**
 * Plugin Name:       WP Diplom — генератор сертификатов
 * Plugin URI:        https://github.com/pavelb-coder/wp-diplom
 * Description:       Автоматическая генерация сертификатов и дипломов в PDF: визуальные бланки, массовая выгрузка из CSV, отправка на e-mail и проверка подлинности по коду.
 * Version:           1.0.0
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

define( 'WPD_FILE', __FILE__ );
define( 'WPD_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPD_URL', plugin_dir_url( __FILE__ ) );
define( 'WPD_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Версия плагина в формате x.y.z.
 *
 * Единственный источник истины — заголовок `Version:` выше. Значение читается
 * из него, поэтому дублировать версию в коде не нужно (см. bin/check-version.sh).
 *
 * @return string
 */
function wpd_version() {
	static $version = null;

	if ( null !== $version ) {
		return $version;
	}

	$data    = get_file_data(
		WPD_FILE,
		array(
			'Version' => 'Version',
		)
	);
	$raw     = isset( $data['Version'] ) ? (string) $data['Version'] : '';
	$version = preg_match( '/^\d+\.\d+\.\d+$/', $raw ) ? $raw : '1.0.0';

	return $version;
}

define( 'WPD_VERSION', wpd_version() );

require_once WPD_DIR . 'includes/class-wpd-plugin.php';

/**
 * Возвращает экземпляр плагина.
 *
 * @return WPD_Plugin
 */
function wpd() {
	return WPD_Plugin::instance();
}

/**
 * Точка входа.
 */
function wpd_bootstrap() {
	$plugin = wpd();
	$plugin->init();
}
add_action( 'plugins_loaded', 'wpd_bootstrap' );

register_activation_hook( __FILE__, array( 'WPD_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WPD_Plugin', 'deactivate' ) );
