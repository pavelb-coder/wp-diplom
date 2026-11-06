<?php
/**
 * Основной файл темы.
 *
 * @package WP_Diplom
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WP_DIPLOM_VERSION', '1.0.0' );
define( 'WP_DIPLOM_DIR', get_template_directory() );
define( 'WP_DIPLOM_URI', get_template_directory_uri() );

require_once WP_DIPLOM_DIR . '/inc/theme-setup.php';
require_once WP_DIPLOM_DIR . '/inc/enqueue.php';
require_once WP_DIPLOM_DIR . '/inc/template-tags.php';
require_once WP_DIPLOM_DIR . '/inc/post-types.php';
require_once WP_DIPLOM_DIR . '/inc/customizer.php';
require_once WP_DIPLOM_DIR . '/inc/widgets.php';
require_once WP_DIPLOM_DIR . '/inc/security.php';
