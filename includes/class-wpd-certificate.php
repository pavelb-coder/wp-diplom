<?php
/**
 * Модель сертификата: создание, нумерация, данные, PDF.
 *
 * @package WP_Diplom
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Работа с записями типа wpd_certificate.
 */
class WPD_Certificate {

	/**
	 * Конфигурация бланка для рендерера.
	 *
	 * @param int $template_id id бланка.
	 * @return array<string, mixed>
	 */
	public static function template_config( $template_id ) {
		$template_id = absint( $template_id );

		$background_id = $template_id ? absint( get_post_meta( $template_id, '_wpd_background_id', true ) ) : 0;
		$background    = '';

		if ( $background_id ) {
			$path = get_attached_file( $background_id );

			if ( $path && is_readable( $path ) ) {
				$background = $path;
			}
		}

		$border = $template_id ? get_post_meta( $template_id, '_wpd_border', true ) : array();

		if ( ! is_array( $border ) || empty( $border ) ) {
			$border = array(
				'enabled' => true,
				'color'   => '#2f6fed',
				'width'   => 0.8,
				'inset_x' => 12,
				'inset_y' => 12,
			);
		} else {
			$border['enabled'] = ! empty( $border['enabled'] );

			foreach ( array( 'width', 'inset_x', 'inset_y' ) as $key ) {
				$border[ $key ] = isset( $border[ $key ] ) ? (float) $border[ $key ] : 0;
			}
		}

		$fields = $template_id ? get_post_meta( $template_id, '_wpd_fields', true ) : array();

		if ( ! is_array( $fields ) || empty( $fields ) ) {
			$fields = WPD_Renderer::default_fields();
		}

		$settings = WPD_Plugin::settings();

		return array(
			'page'             => (string) ( $template_id ? get_post_meta( $template_id, '_wpd_page', true ) : 'a4_landscape' ),
			'background'       => $background,
			'background_color' => (string) ( $template_id ? get_post_meta( $template_id, '_wpd_background_color', true ) : '#ffffff' ),
			'background_fit'   => (string) ( $template_id ? get_post_meta( $template_id, '_wpd_background_fit', true ) : 'cover' ),
			'organization'     => (string) ( $template_id ? get_post_meta( $template_id, '_wpd_organization', true ) : '' )
				?: (string) $settings['organization'],
			'document_title'   => (string) ( $template_id ? get_post_meta( $template_id, '_wpd_document_title', true ) : '' )
				?: __( 'Сертификат', 'wp-diplom' ),
			'border'           => $border,
			'fields'           => $fields,
		);
	}

	/**
	 * Данные сертификата для подстановки в бланк.
	 *
	 * @param int $post_id id сертификата.
	 * @return array<string, mixed>
	 */
	public static function data( $post_id ) {
		$post_id = absint( $post_id );
		$code    = (string) get_post_meta( $post_id, '_wpd_code', true );
		$date    = (string) get_post_meta( $post_id, '_wpd_date', true );

		$custom = get_post_meta( $post_id, '_wpd_custom', true );

		return array(
			'recipient_name'  => (string) get_post_meta( $post_id, '_wpd_recipient', true ),
			'recipient_email' => (string) get_post_meta( $post_id, '_wpd_email', true ),
			'course_title'    => (string) get_post_meta( $post_id, '_wpd_course', true ),
			'course_hours'    => (string) get_post_meta( $post_id, '_wpd_hours', true ),
			'teacher'         => (string) get_post_meta( $post_id, '_wpd_teacher', true ),
			'date'            => self::format_date( $date ),
			'number'          => (string) get_post_meta( $post_id, '_wpd_number', true ),
			'organization'    => (string) WPD_Plugin::settings( 'organization' ),
			'site_name'       => (string) get_bloginfo( 'name' ),
			'code'            => $code,
			'custom'          => is_array( $custom ) ? $custom : array(),
		);
	}

	/**
	 * Форматирует дату для документа.
	 *
	 * @param string $date Дата в формате Y-m-d.
	 * @return string
	 */
	public static function format_date( $date ) {
		if ( '' === $date ) {
			return '';
		}

		$timestamp = strtotime( $date );

		return $timestamp ? date_i18n( get_option( 'date_format' ), $timestamp ) : $date;
	}

	/**
	 * Следующий номер сертификата: PREFIX-YEAR-0001.
	 *
	 * @return string
	 */
	public static function next_number() {
		$settings = WPD_Plugin::settings();
		$prefix   = sanitize_text_field( (string) $settings['number_prefix'] );
		$padding  = max( 1, min( 10, (int) $settings['number_padding'] ) );
		$year     = (int) current_time( 'Y' );
		$option   = 'wpd_number_counter_' . $year;

		$counter = (int) get_option( $option, 0 ) + 1;

		update_option( $option, $counter, false );

		return sprintf( '%s-%d-%0' . $padding . 'd', $prefix, $year, $counter );
	}

	/**
	 * Код публичной проверки.
	 *
	 * @return string
	 */
	public static function generate_code() {
		try {
			return strtolower( bin2hex( random_bytes( 6 ) ) );
		} catch ( Exception $e ) {
			return substr( md5( uniqid( '', true ) ), 0, 12 );
		}
	}

	/**
	 * Создаёт сертификат.
	 *
	 * @param array<string, mixed> $args Данные: recipient, email, course, hours, teacher, date, template_id, custom, number.
	 * @return int|WP_Error
	 */
	public static function create( array $args ) {
		$recipient = trim( (string) ( $args['recipient'] ?? '' ) );

		if ( '' === $recipient ) {
			return new WP_Error( 'wpd_no_recipient', __( 'Не указано имя получателя.', 'wp-diplom' ) );
		}

		$settings    = WPD_Plugin::settings();
		$template_id = absint( $args['template_id'] ?? $settings['default_template'] );

		if ( ! $template_id ) {
			$template_id = self::first_template_id();
		}

		$number = trim( (string) ( $args['number'] ?? '' ) );
		$date   = (string) ( $args['date'] ?? '' );

		if ( '' === $date ) {
			$date = current_time( 'Y-m-d' );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => 'wpd_certificate',
				'post_status' => 'publish',
				'post_title'  => $recipient,
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		if ( '' === $number ) {
			$number = self::next_number();
		}

		$custom = array();

		if ( ! empty( $args['custom'] ) && is_array( $args['custom'] ) ) {
			foreach ( $args['custom'] as $key => $value ) {
				$custom[ sanitize_key( $key ) ] = sanitize_text_field( (string) $value );
			}
		}

		update_post_meta( $post_id, '_wpd_recipient', $recipient );
		update_post_meta( $post_id, '_wpd_email', sanitize_email( (string) ( $args['email'] ?? '' ) ) );
		update_post_meta( $post_id, '_wpd_course', sanitize_text_field( (string) ( $args['course'] ?? '' ) ) );
		update_post_meta( $post_id, '_wpd_hours', sanitize_text_field( (string) ( $args['hours'] ?? '' ) ) );
		update_post_meta( $post_id, '_wpd_teacher', sanitize_text_field( (string) ( $args['teacher'] ?? '' ) ) );
		update_post_meta( $post_id, '_wpd_date', sanitize_text_field( $date ) );
		update_post_meta( $post_id, '_wpd_number', sanitize_text_field( $number ) );
		update_post_meta( $post_id, '_wpd_template', $template_id );
		update_post_meta( $post_id, '_wpd_code', self::generate_code() );
		update_post_meta( $post_id, '_wpd_custom', $custom );
		update_post_meta( $post_id, '_wpd_revoked', 0 );

		return $post_id;
	}

	/**
	 * Первый доступный бланк.
	 *
	 * @return int
	 */
	public static function first_template_id() {
		$ids = get_posts(
			array(
				'post_type'      => 'wpd_template',
				'posts_per_page' => 1,
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);

		return $ids ? (int) $ids[0] : 0;
	}

	/**
	 * PDF-файл сертификата (генерируется при необходимости).
	 *
	 * @param int  $post_id id сертификата.
	 * @param bool $force   Перегенерировать.
	 * @return string|WP_Error Путь к файлу.
	 */
	public static function pdf( $post_id, $force = false ) {
		$post_id = absint( $post_id );
		$post    = get_post( $post_id );

		if ( ! $post || 'wpd_certificate' !== $post->post_type ) {
			return new WP_Error( 'wpd_not_found', __( 'Сертификат не найден.', 'wp-diplom' ) );
		}

		$code = (string) get_post_meta( $post_id, '_wpd_code', true );

		if ( '' === $code ) {
			$code = self::generate_code();
			update_post_meta( $post_id, '_wpd_code', $code );
		}

		$path = WPD_Plugin::storage_dir() . $code . '.pdf';

		if ( ! $force && file_exists( $path ) && filesize( $path ) > 0 ) {
			return $path;
		}

		$template_id = absint( get_post_meta( $post_id, '_wpd_template', true ) );
		$template    = self::template_config( $template_id );
		$data        = self::data( $post_id );

		try {
			$renderer = new WPD_Renderer();
			$binary   = $renderer->render( $template, $data );
		} catch ( Exception $e ) {
			return new WP_Error( 'wpd_render_failed', $e->getMessage() );
		} catch ( Error $e ) {
			return new WP_Error( 'wpd_render_failed', $e->getMessage() );
		}

		if ( false === file_put_contents( $path, $binary ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			return new WP_Error( 'wpd_write_failed', __( 'Не удалось записать PDF. Проверьте права на каталог uploads.', 'wp-diplom' ) );
		}

		update_post_meta( $post_id, '_wpd_pdf_generated', current_time( 'mysql' ) );

		return $path;
	}

	/**
	 * Удаляет закэшированный PDF.
	 *
	 * @param int $post_id id сертификата.
	 */
	public static function delete_pdf( $post_id ) {
		$code = (string) get_post_meta( absint( $post_id ), '_wpd_code', true );

		if ( '' === $code ) {
			return;
		}

		$path = WPD_Plugin::storage_dir() . $code . '.pdf';

		if ( file_exists( $path ) ) {
			wp_delete_file( $path );
		}
	}

	/**
	 * Сертификат по коду проверки.
	 *
	 * @param string $code Код.
	 * @return WP_Post|null
	 */
	public static function by_code( $code ) {
		$code = preg_replace( '/[^a-z0-9]/i', '', (string) $code );

		if ( '' === $code ) {
			return null;
		}

		$posts = get_posts(
			array(
				'post_type'      => 'wpd_certificate',
				'posts_per_page' => 1,
				'post_status'    => 'publish',
				'meta_key'       => '_wpd_code', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => strtolower( $code ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		return $posts ? $posts[0] : null;
	}

	/**
	 * Публичная ссылка проверки.
	 *
	 * @param string $code Код.
	 * @return string
	 */
	public static function verify_url( $code ) {
		$settings = WPD_Plugin::settings();
		$page_id  = absint( $settings['verify_page'] ?? 0 );

		if ( $page_id ) {
			return add_query_arg( 'wpd_code', $code, get_permalink( $page_id ) );
		}

		return home_url( '/certificate/' . $code . '/' );
	}

	/**
	 * Ссылка на скачивание PDF (для админки).
	 *
	 * @param int $post_id id сертификата.
	 * @return string
	 */
	public static function download_url( $post_id ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'wpd_download',
					'id'     => absint( $post_id ),
				),
				admin_url( 'admin-post.php' )
			),
			'wpd_download_' . absint( $post_id )
			
		);
	}

	/**
	 * Отправлен ли сертификат получателю.
	 *
	 * @param int $post_id id.
	 * @return string Дата отправки или пустая строка.
	 */
	public static function sent_at( $post_id ) {
		return (string) get_post_meta( absint( $post_id ), '_wpd_sent_at', true );
	}
}
