<?php
/**
 * Админка: метабоксы, колонки, страницы массовой генерации и настроек.
 *
 * @package WP_Diplom
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Административный интерфейс.
 */
class WPDC_Admin {

	/**
	 * Хуки.
	 */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_notices', array( $this, 'activation_notice' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'add_meta_boxes', array( $this, 'meta_boxes' ) );
		add_action( 'save_post_wpd_template', array( $this, 'save_template' ), 10, 2 );
		add_action( 'save_post_wpd_certificate', array( $this, 'save_certificate' ), 10, 2 );
		add_action( 'before_delete_post', array( $this, 'delete_files' ) );

		add_filter( 'manage_wpd_certificate_posts_columns', array( $this, 'certificate_columns' ) );
		add_action( 'manage_wpd_certificate_posts_custom_column', array( $this, 'certificate_column_content' ), 10, 2 );
		add_filter( 'manage_wpd_template_posts_columns', array( $this, 'template_columns' ) );
		add_action( 'manage_wpd_template_posts_custom_column', array( $this, 'template_column_content' ), 10, 2 );

		add_action( 'admin_post_wpd_download', array( $this, 'handle_download' ) );
		add_action( 'admin_post_wpd_preview', array( $this, 'handle_preview' ) );
		add_action( 'admin_post_wpd_send', array( $this, 'handle_send' ) );
		add_action( 'admin_post_wpd_bulk_generate', array( $this, 'handle_bulk' ) );
		add_action( 'admin_post_wpd_bulk_zip', array( $this, 'handle_bulk_zip' ) );
		add_action( 'admin_post_wpd_save_settings', array( $this, 'handle_settings' ) );
	}

	/* --------------------------------------------------------------------- */
	/* Меню и материалы                                                       */
	/* --------------------------------------------------------------------- */

	/**
	 * Сообщение о неполадке при активации.
	 *
	 * Плагин перехватывает ошибку активации и складывает её в опцию — так сайт
	 * не падает, а администратор видит точное место проблемы.
	 */
	public function activation_notice() {
		$error = get_option( 'wpdc_activation_error' );

		if ( ! is_array( $error ) || empty( $error['message'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$file = isset( $error['file'] ) ? str_replace( ABSPATH, '', (string) $error['file'] ) : '';

		echo '<div class="notice notice-error"><p><strong>';
		echo esc_html__( 'WP Diplom: при активации произошла ошибка, плагин включён, но часть настроек могла не создаться.', 'wp-diplom' );
		echo '</strong></p><p><code>';
		echo esc_html( $error['message'] );
		echo '</code></p>';

		if ( '' !== $file ) {
			echo '<p>';
			echo esc_html__( 'Файл:', 'wp-diplom' ) . ' <code>' . esc_html( $file );
			echo isset( $error['line'] ) ? ':' . (int) $error['line'] : '';
			echo '</code> — PHP <code>' . esc_html( isset( $error['php'] ) ? (string) $error['php'] : PHP_VERSION ) . '</code></p>';
		}

		echo '<p>' . esc_html__( 'Пришлите этот текст разработчику — по нему видно точную причину.', 'wp-diplom' ) . '</p></div>';
	}

	/**
	 * Подменю.
	 */
	public function menu() {
		add_submenu_page(
			'edit.php?post_type=wpdc_certificate',
			__( 'Массовая генерация', 'wp-diplom' ),
			__( 'Массовая генерация', 'wp-diplom' ),
			'edit_posts',
			'wpdc-bulk',
			array( $this, 'render_bulk_page' )
		);

		add_submenu_page(
			'edit.php?post_type=wpdc_certificate',
			__( 'Настройки сертификатов', 'wp-diplom' ),
			__( 'Настройки', 'wp-diplom' ),
			'manage_options',
			'wpdc-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Стили и скрипты админки.
	 *
	 * @param string $hook Текущий экран.
	 */
	public function assets( $hook ) {
		$screen = get_current_screen();

		$is_plugin_screen = $screen && in_array(
			$screen->post_type ?? '',
			array( 'wpdc_certificate', 'wpdc_template' ),
			true
		);

		$is_plugin_page = false !== strpos( (string) $hook, 'wpdc-' );

		if ( ! $is_plugin_screen && ! $is_plugin_page ) {
			return;
		}

		wp_enqueue_style( 'wpdc-admin', WPDC_URL . 'assets/css/admin.css', array(), WPDC_VERSION );
		wp_enqueue_media();

		wp_enqueue_script(
			'wpdc-admin',
			WPDC_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			WPDC_VERSION,
			true
		);

		wp_localize_script(
			'wpdc-admin',
			'wpdAdmin',
			array(
				'chooseBackground' => __( 'Выбрать фон бланка', 'wp-diplom' ),
				'useBackground'    => __( 'Использовать это изображение', 'wp-diplom' ),
				'confirmDelete'    => __( 'Удалить поле?', 'wp-diplom' ),
			)
		);
	}

	/* --------------------------------------------------------------------- */
	/* Метабоксы                                                              */
	/* --------------------------------------------------------------------- */

	/**
	 * Регистрация метабоксов.
	 */
	public function meta_boxes() {
		add_meta_box(
			'wpdc_template_design',
			__( 'Дизайн бланка', 'wp-diplom' ),
			array( $this, 'render_template_design_box' ),
			'wpdc_template',
			'normal',
			'high'
		);

		add_meta_box(
			'wpdc_template_fields',
			__( 'Поля бланка', 'wp-diplom' ),
			array( $this, 'render_template_fields_box' ),
			'wpdc_template',
			'normal',
			'high'
		);

		add_meta_box(
			'wpdc_certificate_data',
			__( 'Данные сертификата', 'wp-diplom' ),
			array( $this, 'render_certificate_box' ),
			'wpdc_certificate',
			'normal',
			'high'
		);

		add_meta_box(
			'wpdc_certificate_actions',
			__( 'Документ', 'wp-diplom' ),
			array( $this, 'render_certificate_actions_box' ),
			'wpdc_certificate',
			'side',
			'default'
		);
	}

	/**
	 * Метабокс «Дизайн бланка».
	 *
	 * @param WP_Post $post Запись.
	 */
	public function render_template_design_box( $post ) {
		wp_nonce_field( 'wpdc_save_template', 'wpdc_template_nonce' );

		$page       = (string) get_post_meta( $post->ID, '_wpdc_page', true );
		$page       = $page ? $page : 'a4_landscape';
		$background = absint( get_post_meta( $post->ID, '_wpdc_background_id', true ) );
		$color      = (string) get_post_meta( $post->ID, '_wpdc_background_color', true );
		$fit        = (string) get_post_meta( $post->ID, '_wpdc_background_fit', true );
		$org        = (string) get_post_meta( $post->ID, '_wpdc_organization', true );
		$title      = (string) get_post_meta( $post->ID, '_wpdc_document_title', true );
		$border     = get_post_meta( $post->ID, '_wpdc_border', true );

		$border = is_array( $border ) && $border ? $border : array(
			'enabled' => 1,
			'color'   => '#2f6fed',
			'width'   => 0.8,
			'inset_x' => 12,
			'inset_y' => 12,
		);

		include WPDC_DIR . 'admin/views/meta-template-design.php';
	}

	/**
	 * Метабокс «Поля бланка».
	 *
	 * @param WP_Post $post Запись.
	 */
	public function render_template_fields_box( $post ) {
		$fields = get_post_meta( $post->ID, '_wpdc_fields', true );

		if ( ! is_array( $fields ) || empty( $fields ) ) {
			$fields = WPDC_Renderer::default_fields();
		}

		$placeholders = WPDC_Renderer::placeholders();
		$preview_url  = wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'wpdc_preview',
					'id'     => $post->ID,
				),
				admin_url( 'admin-post.php' )
			),
			'wpdc_preview_' . $post->ID
		);

		include WPDC_DIR . 'admin/views/meta-template-fields.php';
	}

	/**
	 * Метабокс «Данные сертификата».
	 *
	 * @param WP_Post $post Запись.
	 */
	public function render_certificate_box( $post ) {
		wp_nonce_field( 'wpdc_save_certificate', 'wpdc_certificate_nonce' );

		$settings = WPDC_Plugin::settings();

		$templates = get_posts(
			array(
				'post_type'      => 'wpdc_template',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		include WPDC_DIR . 'admin/views/meta-certificate.php';
	}

	/**
	 * Метабокс действий с документом.
	 *
	 * @param WP_Post $post Запись.
	 */
	public function render_certificate_actions_box( $post ) {
		$is_public = 'publish' === $post->post_status;

		include WPDC_DIR . 'admin/views/meta-certificate-actions.php';
	}

	/* --------------------------------------------------------------------- */
	/* Сохранение                                                             */
	/* --------------------------------------------------------------------- */

	/**
	 * Сохранение бланка.
	 *
	 * @param int     $post_id id.
	 * @param WP_Post $post    Запись.
	 */
	public function save_template( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! isset( $_POST['wpdc_template_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpdc_template_nonce'] ) ), 'wpdc_save_template' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Дизайн.
		$page = sanitize_key( wp_unslash( $_POST['wpdc_page'] ?? 'a4_landscape' ) );

		if ( ! isset( WPDC_Renderer::PAGE_SIZES[ $page ] ) ) {
			$page = 'a4_landscape';
		}

		update_post_meta( $post_id, '_wpdc_page', $page );
		update_post_meta( $post_id, '_wpdc_background_id', absint( $_POST['wpdc_background_id'] ?? 0 ) );

		update_post_meta(
			$post_id,
			'_wpdc_background_color',
			WPDC_Renderer::sanitize_color( wp_unslash( $_POST['wpdc_background_color'] ?? '#ffffff' ) )
		);

		$fit = sanitize_key( wp_unslash( $_POST['wpdc_background_fit'] ?? 'cover' ) );

		update_post_meta( $post_id, '_wpdc_background_fit', in_array( $fit, array( 'cover', 'contain', 'stretch' ), true ) ? $fit : 'cover' );

		update_post_meta( $post_id, '_wpdc_organization', sanitize_text_field( wp_unslash( $_POST['wpdc_organization'] ?? '' ) ) );
		update_post_meta( $post_id, '_wpdc_document_title', sanitize_text_field( wp_unslash( $_POST['wpdc_document_title'] ?? '' ) ) );

		update_post_meta(
			$post_id,
			'_wpdc_border',
			array(
				'enabled' => empty( $_POST['wpdc_border_enabled'] ) ? 0 : 1,
				'color'   => WPDC_Renderer::sanitize_color( wp_unslash( $_POST['wpdc_border_color'] ?? '#2f6fed' ) ),
				'width'   => max( 0, min( 10, (float) ( $_POST['wpdc_border_width'] ?? 0.8 ) ) ),
				'inset_x' => max( 0, min( 100, (float) ( $_POST['wpdc_border_inset_x'] ?? 12 ) ) ),
				'inset_y' => max( 0, min( 100, (float) ( $_POST['wpdc_border_inset_y'] ?? 12 ) ) ),
			)
		);

		// Поля.
		$fields = array();

		if ( isset( $_POST['wpdc_field'] ) && is_array( $_POST['wpdc_field'] ) ) {
			$raw = wp_unslash( $_POST['wpdc_field'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			foreach ( $raw as $field ) {
				if ( ! is_array( $field ) ) {
					continue;
				}

				$fields[] = array(
					'id'          => $field['id'] ?? '',
					'text'        => $field['text'] ?? '',
					'x'           => $field['x'] ?? 0,
					'y'           => $field['y'] ?? 0,
					'w'           => $field['w'] ?? 100,
					'h'           => $field['h'] ?? 10,
					'size'        => $field['size'] ?? 12,
					'font'        => $field['font'] ?? 'sans',
					'style'       => $field['style'] ?? 'regular',
					'color'       => $field['color'] ?? '#000000',
					'align'       => $field['align'] ?? 'center',
					'line_height' => $field['line_height'] ?? 1.25,
					'valign'      => $field['valign'] ?? 'middle',
					'uppercase'   => ! empty( $field['uppercase'] ),
					'keep_empty'  => ! empty( $field['keep_empty'] ),
				);
			}
		}

		if ( $fields ) {
			update_post_meta( $post_id, '_wpdc_fields', WPDC_Renderer::sanitize_fields( $fields ) );
		}
	}

	/**
	 * Сохранение сертификата.
	 *
	 * @param int     $post_id id.
	 * @param WP_Post $post    Запись.
	 */
	public function save_certificate( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! isset( $_POST['wpdc_certificate_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpdc_certificate_nonce'] ) ), 'wpdc_save_certificate' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$recipient = sanitize_text_field( wp_unslash( $_POST['wpdc_recipient'] ?? '' ) );

		update_post_meta( $post_id, '_wpdc_recipient', $recipient );
		update_post_meta( $post_id, '_wpdc_email', sanitize_email( wp_unslash( $_POST['wpdc_email'] ?? '' ) ) );
		update_post_meta( $post_id, '_wpdc_course', sanitize_text_field( wp_unslash( $_POST['wpdc_course'] ?? '' ) ) );
		update_post_meta( $post_id, '_wpdc_hours', sanitize_text_field( wp_unslash( $_POST['wpdc_hours'] ?? '' ) ) );
		update_post_meta( $post_id, '_wpdc_teacher', sanitize_text_field( wp_unslash( $_POST['wpdc_teacher'] ?? '' ) ) );

		$date = sanitize_text_field( wp_unslash( $_POST['wpdc_date'] ?? '' ) );
		update_post_meta( $post_id, '_wpdc_date', preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : current_time( 'Y-m-d' ) );

		$number = sanitize_text_field( wp_unslash( $_POST['wpdc_number'] ?? '' ) );

		if ( '' !== $number ) {
			update_post_meta( $post_id, '_wpdc_number', $number );
		} elseif ( '' === (string) get_post_meta( $post_id, '_wpdc_number', true ) ) {
			update_post_meta( $post_id, '_wpdc_number', WPDC_Certificate::next_number() );
		}

		update_post_meta( $post_id, '_wpdc_template', absint( $_POST['wpdc_template'] ?? 0 ) );
		update_post_meta( $post_id, '_wpdc_revoked', empty( $_POST['wpdc_revoked'] ) ? 0 : 1 );

		$custom = array();

		if ( isset( $_POST['wpdc_custom_key'] ) && is_array( $_POST['wpdc_custom_key'] ) ) {
			$keys   = wp_unslash( $_POST['wpdc_custom_key'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$values = wp_unslash( $_POST['wpdc_custom_value'] ?? array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			foreach ( $keys as $index => $key ) {
				$key = sanitize_key( $key );

				if ( '' !== $key ) {
					$custom[ $key ] = sanitize_text_field( (string) ( $values[ $index ] ?? '' ) );
				}
			}
		}

		update_post_meta( $post_id, '_wpdc_custom', $custom );

		// Сбрасываем кэш PDF, чтобы документ пересобрался с новыми данными.
		WPDC_Certificate::delete_pdf( $post_id );
	}

	/**
	 * Удаление файлов при удалении записи.
	 *
	 * @param int $post_id id.
	 */
	public function delete_files( $post_id ) {
		if ( 'wpdc_certificate' === get_post_type( $post_id ) ) {
			WPDC_Certificate::delete_pdf( $post_id );
		}
	}

	/* --------------------------------------------------------------------- */
	/* Колонки                                                               */
	/* --------------------------------------------------------------------- */

	/**
	 * Колонки списка сертификатов.
	 *
	 * @param array $columns Колонки.
	 * @return array
	 */
	public function certificate_columns( $columns ) {
		$new = array();

		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;

			if ( 'title' === $key ) {
				$new['wpdc_course']   = __( 'Программа', 'wp-diplom' );
				$new['wpdc_number']   = __( 'Номер', 'wp-diplom' );
				$new['wpdc_date']     = __( 'Выдан', 'wp-diplom' );
				$new['wpdc_status']   = __( 'Статус', 'wp-diplom' );
				$new['wpdc_actions']  = __( 'PDF', 'wp-diplom' );
			}
		}

		return $new;
	}

	/**
	 * Содержимое колонок сертификата.
	 *
	 * @param string $column  Колонка.
	 * @param int    $post_id id.
	 */
	public function certificate_column_content( $column, $post_id ) {
		switch ( $column ) {
			case 'wpdc_course':
				echo esc_html( (string) get_post_meta( $post_id, '_wpdc_course', true ) );
				break;

			case 'wpdc_number':
				echo '<code>' . esc_html( (string) get_post_meta( $post_id, '_wpdc_number', true ) ) . '</code>';
				break;

			case 'wpdc_date':
				echo esc_html( WPDC_Certificate::format_date( (string) get_post_meta( $post_id, '_wpdc_date', true ) ) );
				break;

			case 'wpdc_status':
				if ( get_post_meta( $post_id, '_wpdc_revoked', true ) ) {
					echo '<span class="wpdc-badge wpdc-badge--revoked">' . esc_html__( 'Отозван', 'wp-diplom' ) . '</span>';
				} elseif ( '' !== WPDC_Certificate::sent_at( $post_id ) ) {
					echo '<span class="wpdc-badge wpdc-badge--sent">' . esc_html__( 'Отправлен', 'wp-diplom' ) . '</span>';
				} else {
					echo '<span class="wpdc-badge">' . esc_html__( 'Не отправлен', 'wp-diplom' ) . '</span>';
				}
				break;

			case 'wpdc_actions':
				printf(
					'<a class="button button-small" href="%s">%s</a>',
					esc_url( WPDC_Certificate::download_url( $post_id ) ),
					esc_html__( 'Скачать', 'wp-diplom' )
				);
				break;
		}
	}

	/**
	 * Колонки списка бланков.
	 *
	 * @param array $columns Колонки.
	 * @return array
	 */
	public function template_columns( $columns ) {
		$columns['wpdc_page']    = __( 'Формат', 'wp-diplom' );
		$columns['wpdc_fields']  = __( 'Полей', 'wp-diplom' );
		$columns['wpdc_preview'] = __( 'Предпросмотр', 'wp-diplom' );

		return $columns;
	}

	/**
	 * Содержимое колонок бланка.
	 *
	 * @param string $column  Колонка.
	 * @param int    $post_id id.
	 */
	public function template_column_content( $column, $post_id ) {
		if ( 'wpdc_page' === $column ) {
			$options = WPDC_Renderer::page_size_options();
			$page    = (string) get_post_meta( $post_id, '_wpdc_page', true );

			echo esc_html( $options[ $page ] ?? $options['a4_landscape'] );
		}

		if ( 'wpdc_fields' === $column ) {
			$fields = get_post_meta( $post_id, '_wpdc_fields', true );

			echo (int) ( is_array( $fields ) ? count( $fields ) : 0 );
		}

		if ( 'wpdc_preview' === $column ) {
			$url = wp_nonce_url(
				add_query_arg(
					array(
						'action' => 'wpdc_preview',
						'id'     => $post_id,
					),
					admin_url( 'admin-post.php' )
				),
				'wpdc_preview_' . $post_id
			);

			printf(
				'<a href="%s" target="_blank" rel="noopener">%s</a>',
				esc_url( $url ),
				esc_html__( 'PDF с примером', 'wp-diplom' )
			);
		}
	}

	/* --------------------------------------------------------------------- */
	/* Обработчики admin-post                                                 */
	/* --------------------------------------------------------------------- */

	/**
	 * Скачивание PDF сертификата.
	 */
	public function handle_download() {
		$post_id = absint( $_GET['id'] ?? 0 );

		check_admin_referer( 'wpdc_download_' . $post_id );

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'wp-diplom' ), 403 );
		}

		$file = WPDC_Certificate::pdf( $post_id );

		if ( is_wp_error( $file ) ) {
			wp_die( esc_html( $file->get_error_message() ) );
		}

		$this->stream_file( $file, 'certificate-' . $post_id . '.pdf', 'attachment' );
	}

	/**
	 * Предпросмотр бланка с демонстрационными данными.
	 */
	public function handle_preview() {
		$template_id = absint( $_GET['id'] ?? 0 );

		check_admin_referer( 'wpdc_preview_' . $template_id );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'wp-diplom' ), 403 );
		}

		$settings = WPDC_Plugin::settings();

		$template = WPDC_Certificate::template_config( $template_id );

		$sample = array(
			'recipient_name'  => __( 'Петрова Анна Сергеевна', 'wp-diplom' ),
			'recipient_email' => 'anna@example.com',
			'course_title'    => __( 'Веб-разработка на WordPress', 'wp-diplom' ),
			'course_hours'    => '72',
			'teacher'         => $settings['sample_teacher'] ? $settings['sample_teacher'] : __( 'Иванов И. И.', 'wp-diplom' ),
			'date'            => WPDC_Certificate::format_date( current_time( 'Y-m-d' ) ),
			'number'          => 'WPD-' . current_time( 'Y' ) . '-0001',
			'organization'    => $settings['organization'],
			'site_name'       => get_bloginfo( 'name' ),
			'code'            => 'demo123456',
			'custom'          => array( 'group' => 'ВР-21' ),
		);

		try {
			$renderer = new WPDC_Renderer();
			$binary   = $renderer->render( $template, $sample );
		} catch ( Exception $e ) {
			wp_die( esc_html( $e->getMessage() ) );
		}

		$this->stream_binary( $binary, 'template-preview.pdf' );
	}

	/**
	 * Отправка сертификата на e-mail.
	 */
	public function handle_send() {
		$post_id = absint( $_GET['id'] ?? 0 );

		check_admin_referer( 'wpdc_send_' . $post_id );

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'wp-diplom' ), 403 );
		}

		$result = WPDC_Email::send( $post_id, true );

		$notice = is_wp_error( $result ) ? 'wpdc-error' : 'wpdc-sent';

		wp_safe_redirect(
			add_query_arg(
				array(
					'post'       => $post_id,
					'action'     => 'edit',
					'wpdc_notice' => $notice,
				),
				admin_url( 'post.php' )
			)
		);
		exit;
	}

	/**
	 * Массовая генерация из CSV.
	 */
	public function handle_bulk() {
		check_admin_referer( 'wpdc_bulk_generate' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'wp-diplom' ), 403 );
		}

		$template_id = absint( $_POST['wpdc_bulk_template'] ?? 0 );
		$send_email  = ! empty( $_POST['wpdc_bulk_email'] );
		$csv         = '';

		if ( ! empty( $_FILES['wpdc_bulk_file']['tmp_name'] ) && is_uploaded_file( $_FILES['wpdc_bulk_file']['tmp_name'] ) ) {
			$csv = (string) file_get_contents( $_FILES['wpdc_bulk_file']['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.Security.ValidatedSanitizedInput
		} elseif ( isset( $_POST['wpdc_bulk_csv'] ) ) {
			$csv = (string) wp_unslash( $_POST['wpdc_bulk_csv'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}

		$rows = $this->parse_csv( $csv );

		if ( empty( $rows ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'       => 'wpdc-bulk',
						'wpdc_notice' => 'wpdc-bulk-empty',
					),
					admin_url( 'edit.php?post_type=wpdc_certificate' )
				)
			);
			exit;
		}

		$created = 0;
		$failed  = 0;
		$mailed  = 0;
		$ids     = array();

		foreach ( $rows as $row ) {
			$result = WPDC_Certificate::create(
				array(
					'recipient'   => $row['recipient'] ?? '',
					'email'       => $row['email'] ?? '',
					'course'      => $row['course'] ?? '',
					'hours'       => $row['hours'] ?? '',
					'teacher'     => $row['teacher'] ?? '',
					'date'        => $row['date'] ?? '',
					'template_id' => $template_id,
					'custom'      => $row['custom'] ?? array(),
				)
			);

			if ( is_wp_error( $result ) ) {
				$failed++;
				continue;
			}

			$created++;
			$ids[] = $result;

			if ( $send_email ) {
				$sent = WPDC_Email::send( $result );

				if ( ! is_wp_error( $sent ) ) {
					$mailed++;
				}
			}
		}

		set_transient(
			'wpdc_bulk_result_' . get_current_user_id(),
			array(
				'created' => $created,
				'failed'  => $failed,
				'mailed'  => $mailed,
				'ids'     => $ids,
			),
			5 * MINUTE_IN_SECONDS
		);

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => 'wpdc-bulk',
					'wpdc_notice' => 'wpdc-bulk-done',
				),
				admin_url( 'edit.php?post_type=wpdc_certificate' )
			)
		);
		exit;
	}

	/**
	 * ZIP со всеми сгенерированными PDF из последней массовой генерации.
	 */
	public function handle_bulk_zip() {
		check_admin_referer( 'wpdc_bulk_zip' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'wp-diplom' ), 403 );
		}

		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_die( esc_html__( 'Расширение ZIP не установлено на сервере.', 'wp-diplom' ) );
		}

		$result = get_transient( 'wpdc_bulk_result_' . get_current_user_id() );
		$ids    = is_array( $result ) && ! empty( $result['ids'] ) ? array_map( 'absint', $result['ids'] ) : array();

		if ( empty( $ids ) ) {
			wp_die( esc_html__( 'Нет сертификатов для архива.', 'wp-diplom' ) );
		}

		$tmp = wp_tempnam( 'wpdc-bulk.zip' );
		$zip = new ZipArchive();

		if ( true !== $zip->open( $tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			wp_die( esc_html__( 'Не удалось создать архив.', 'wp-diplom' ) );
		}

		foreach ( $ids as $id ) {
			$file = WPDC_Certificate::pdf( $id );

			if ( is_wp_error( $file ) ) {
				continue;
			}

			$name = (string) get_post_meta( $id, '_wpdc_number', true );
			$zip->addFile( $file, ( $name ? $name : 'certificate-' . $id ) . '.pdf' );
		}

		$zip->close();

		$this->stream_file( $tmp, 'certificates.zip', 'attachment', true );
	}

	/**
	 * Сохранение настроек.
	 */
	public function handle_settings() {
		check_admin_referer( 'wpdc_save_settings' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'wp-diplom' ), 403 );
		}

		$settings = array(
			'organization'     => sanitize_text_field( wp_unslash( $_POST['wpdc_organization'] ?? '' ) ),
			'default_template' => absint( $_POST['wpdc_default_template'] ?? 0 ),
			'number_prefix'    => sanitize_text_field( wp_unslash( $_POST['wpdc_number_prefix'] ?? 'WPD' ) ),
			'number_padding'   => max( 1, min( 10, absint( $_POST['wpdc_number_padding'] ?? 4 ) ) ),
			'email_subject'    => sanitize_text_field( wp_unslash( $_POST['wpdc_email_subject'] ?? '' ) ),
			'email_body'       => sanitize_textarea_field( wp_unslash( $_POST['wpdc_email_body'] ?? '' ) ),
			'sender_name'      => sanitize_text_field( wp_unslash( $_POST['wpdc_sender_name'] ?? '' ) ),
			'sender_email'     => sanitize_email( wp_unslash( $_POST['wpdc_sender_email'] ?? '' ) ),
			'verify_page'      => absint( $_POST['wpdc_verify_page'] ?? 0 ),
			'enable_verify'    => empty( $_POST['wpdc_enable_verify'] ) ? 0 : 1,
			'sample_teacher'   => sanitize_text_field( wp_unslash( $_POST['wpdc_sample_teacher'] ?? '' ) ),
		);

		update_option( 'wpdc_settings', $settings );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => 'wpdc-settings',
					'wpdc_notice' => 'wpdc-settings-saved',
				),
				admin_url( 'edit.php?post_type=wpdc_certificate' )
			)
		);
		exit;
	}

	/* --------------------------------------------------------------------- */
	/* Страницы                                                               */
	/* --------------------------------------------------------------------- */

	/**
	 * Страница массовой генерации.
	 */
	public function render_bulk_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'wp-diplom' ), 403 );
		}

		$result = get_transient( 'wpdc_bulk_result_' . get_current_user_id() );

		include WPDC_DIR . 'admin/views/page-bulk.php';
	}

	/**
	 * Страница настроек.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'wp-diplom' ), 403 );
		}

		$settings  = WPDC_Plugin::settings();
		$templates = get_posts(
			array(
				'post_type'      => 'wpdc_template',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
			)
		);

		$pages = get_pages();

		include WPDC_DIR . 'admin/views/page-settings.php';
	}

	/* --------------------------------------------------------------------- */
	/* Утилиты                                                                */
	/* --------------------------------------------------------------------- */

	/**
	 * Разбирает CSV в массив строк.
	 *
	 * Поддерживает разделители «;» и «,», заголовочную строку и русские
	 * названия колонок. Неизвестные колонки уходят в custom_*.
	 *
	 * @param string $csv Содержимое.
	 * @return array<int, array<string, mixed>>
	 */
	public function parse_csv( $csv ) {
		$csv = trim( str_replace( array( "\r\n", "\r" ), "\n", (string) $csv ) );

		if ( '' === $csv ) {
			return array();
		}

		$lines = explode( "\n", $csv );
		$lines = array_values( array_filter( array_map( 'trim', $lines ) ) );

		if ( count( $lines ) < 1 ) {
			return array();
		}

		$delimiter = substr_count( $lines[0], ';' ) >= substr_count( $lines[0], ',' ) ? ';' : ',';

		$header = str_getcsv( array_shift( $lines ), $delimiter );
		$header = array_map(
			static function ( $value ) {
				$value = strtolower( trim( (string) $value ) );
				$value = str_replace( "\xEF\xBB\xBF", '', $value );

				return trim( $value );
			},
			$header
		);

		$aliases = array(
			'recipient' => array( 'recipient', 'name', 'имя', 'фио', 'получатель', 'recipient_name' ),
			'email'     => array( 'email', 'e-mail', 'почта', 'recipient_email' ),
			'course'    => array( 'course', 'курс', 'программа', 'course_title' ),
			'hours'     => array( 'hours', 'часы', 'часов', 'course_hours' ),
			'teacher'   => array( 'teacher', 'преподаватель', 'teacher_name' ),
			'date'      => array( 'date', 'дата', 'дата выдачи' ),
		);

		$map = array();

		foreach ( $header as $index => $column ) {
			foreach ( $aliases as $field => $names ) {
				if ( in_array( $column, $names, true ) ) {
					$map[ $index ] = $field;
					continue 2;
				}
			}

			$map[ $index ] = 'custom:' . $column;
		}

		$rows = array();

		foreach ( $lines as $line ) {
			$values = str_getcsv( $line, $delimiter );

			if ( count( array_filter( $values ) ) === 0 ) {
				continue;
			}

			$row = array( 'custom' => array() );

			foreach ( $values as $index => $value ) {
				$value = trim( (string) $value );
				$field = $map[ $index ] ?? '';

				if ( 0 === strpos( $field, 'custom:' ) ) {
					$key = sanitize_key( substr( $field, 7 ) );

					if ( '' !== $key && '' !== $value ) {
						$row['custom'][ $key ] = $value;
					}

					continue;
				}

				if ( '' !== $field ) {
					$row[ $field ] = $value;
				}
			}

			if ( empty( $row['recipient'] ) ) {
				continue;
			}

			$rows[] = $row;
		}

		return $rows;
	}

	/**
	 * Отдаёт файл в браузер.
	 *
	 * @param string $path     Путь.
	 * @param string $filename Имя файла.
	 * @param string $disposition 'attachment' или 'inline'.
	 * @param bool   $cleanup  Удалить файл после отправки.
	 */
	private function stream_file( $path, $filename, $disposition = 'attachment', $cleanup = false ) {
		if ( ! is_readable( $path ) ) {
			wp_die( esc_html__( 'Файл не найден.', 'wp-diplom' ) );
		}

		$contents = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if ( $cleanup ) {
			wp_delete_file( $path );
		}

		$this->stream_binary( $contents, $filename, $disposition );
	}

	/**
	 * Отдаёт бинарные данные с нужными заголовками.
	 *
	 * @param string $binary      Данные.
	 * @param string $filename    Имя файла.
	 * @param string $disposition 'attachment' или 'inline'.
	 */
	private function stream_binary( $binary, $filename, $disposition = 'inline' ) {
		nocache_headers();

		header( 'Content-Type: application/pdf' );
		header( 'Content-Length: ' . strlen( $binary ) );
		header(
			sprintf(
				'Content-Disposition: %s; filename="%s"',
				$disposition,
				preg_replace( '/[^A-Za-z0-9._-]/', '', $filename )
			)
		);

		echo $binary; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Бинарный PDF.
		exit;
	}
}
