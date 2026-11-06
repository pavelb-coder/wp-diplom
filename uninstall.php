<?php
/**
 * Удаление данных плагина при деинсталляции.
 *
 * По умолчанию удаляются только настройки и служебные данные. Записи
 * сертификатов и бланков сохраняются, чтобы не потерять выданные документы.
 * Чтобы удалить всё, включая записи и PDF, добавьте в wp-config.php:
 *
 *     define( 'WPDC_REMOVE_ALL_DATA', true );
 *
 * @package WP_Diplom
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Очистка данных одного сайта.
 */
function wpdc_uninstall_site() {
	global $wpdb;

	// Настройки и счётчики номеров.
	delete_option( 'wpdc_settings' );
	delete_option( 'wpdc_db_version' );

	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE 'wpdc_number_counter_%'"
	);

	// Транзиенты массовой генерации.
	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wpd_bulk_result_%'"
		. " OR option_name LIKE '_transient_timeout_wpd_bulk_result_%'"
	);

	if ( ! defined( 'WPDC_REMOVE_ALL_DATA' ) || ! WPDC_REMOVE_ALL_DATA ) {
		return;
	}

	// Записи и метаданные.
	$post_ids = get_posts(
		array(
			'post_type'      => array( 'wpdc_certificate', 'wpdc_template' ),
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);

	foreach ( $post_ids as $post_id ) {
		wp_delete_post( $post_id, true );
	}

	// PDF-файлы.
	$uploads = wp_upload_dir( null, false );
	$dir     = trailingslashit( $uploads['basedir'] ) . 'wpdc-certificates';

	if ( is_dir( $dir ) ) {
		$files = glob( $dir . '/*' );

		foreach ( (array) $files as $file ) {
			if ( is_file( $file ) ) {
				wp_delete_file( $file );
			}
		}

		@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}

	// Термины таксономии.
	$terms = get_terms(
		array(
			'taxonomy'   => 'wpdc_program',
			'hide_empty' => false,
			'fields'     => 'ids',
		)
	);

	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term_id ) {
			wp_delete_term( $term_id, 'wpdc_program' );
		}
	}
}

if ( is_multisite() ) {
	$sites = get_sites( array( 'fields' => 'ids' ) );

	foreach ( $sites as $site_id ) {
		switch_to_blog( $site_id );
		wpdc_uninstall_site();
		restore_current_blog();
	}
} else {
	wpdc_uninstall_site();
}
