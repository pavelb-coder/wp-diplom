<?php
/**
 * Основной файл темы.
 *
 * @package WP_Diplom
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Версия темы в формате x.y.z (SemVer).
 *
 * Единственный источник истины — заголовок `Version:` в style.css,
 * который WordPress и так обязан читать. Константа нужна для кэш-бастинга
 * ассетов (inc/enqueue.php), поэтому берём значение оттуда, а не дублируем.
 *
 * @return string Версия вида x.y.z.
 */
function wp_diplom_theme_version() {
	$theme = wp_get_theme( get_template() );
	$raw   = (string) $theme->get( 'Version' );

	return preg_match( '/^\d+\.\d+\.\d+$/', $raw ) ? $raw : '1.0.0';
}

define( 'WP_DIPLOM_VERSION', wp_diplom_theme_version() );
define( 'WP_DIPLOM_DIR', get_template_directory() );
define( 'WP_DIPLOM_URI', get_template_directory_uri() );

require_once WP_DIPLOM_DIR . '/inc/theme-setup.php';
require_once WP_DIPLOM_DIR . '/inc/enqueue.php';
require_once WP_DIPLOM_DIR . '/inc/template-tags.php';
require_once WP_DIPLOM_DIR . '/inc/post-types.php';
require_once WP_DIPLOM_DIR . '/inc/customizer.php';
require_once WP_DIPLOM_DIR . '/inc/widgets.php';
require_once WP_DIPLOM_DIR . '/inc/security.php';
