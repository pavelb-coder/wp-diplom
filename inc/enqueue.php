<?php
/**
 * Подключение стилей и скриптов.
 *
 * @package WP_Diplom
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Фронтенд-ассеты.
 */
function wp_diplom_enqueue_assets() {
	wp_enqueue_style(
		'wp-diplom-main',
		WP_DIPLOM_URI . '/assets/css/main.css',
		array(),
		WP_DIPLOM_VERSION
	);

	wp_enqueue_script(
		'wp-diplom-main',
		WP_DIPLOM_URI . '/assets/js/main.js',
		array(),
		WP_DIPLOM_VERSION,
		true
	);

	wp_localize_script( 'wp-diplom-main', 'wpDiplomData', array(
		'menuLabel' => esc_html__( 'Меню', 'wp-diplom' ),
	) );

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'wp_diplom_enqueue_assets' );
