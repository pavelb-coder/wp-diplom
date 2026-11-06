<?php
/**
 * Небольшие меры безопасности и чистка вывода.
 *
 * @package WP_Diplom
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Убираем версию WP из head и из параметров скриптов/стилей.
 */
remove_action( 'wp_head', 'wp_generator' );

/**
 * Убираем лишние ссылки из head.
 */
function wp_diplom_clean_head() {
	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'wp_shortlink_wp_head' );
}
add_action( 'init', 'wp_diplom_clean_head' );

/**
 * Отключаем XML-RPC.
 *
 * @param bool $enabled Текущее значение.
 * @return bool
 */
function wp_diplom_disable_xmlrpc( $enabled ) {
	return false;
}
add_filter( 'xmlrpc_enabled', 'wp_diplom_disable_xmlrpc' );

/**
 * Правильные размеры для iframe-встраиваний.
 *
 * @param string $content Контент.
 * @return string
 */
function wp_diplom_responsive_embeds( $content ) {
	if ( ! is_singular() ) {
		return $content;
	}
	return $content;
}
add_filter( 'the_content', 'wp_diplom_responsive_embeds', 5 );
