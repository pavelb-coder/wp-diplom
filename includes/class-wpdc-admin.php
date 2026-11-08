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
		add_action( 'admin_notices', array( $this, 'activation_notice' ) );
		add_action( 'admin_notices', array( $this, 'creation_hint' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'add_meta_boxes', array( $this, 'meta_boxes' ) );
		add_action( 'save_post_wpdc_template', array( $this, 'save_template' ), 10, 2 );
		add_action( 'save_post_wpdc_certificate', array( $this, 'save_certificate' ), 10, 2 );
		add_action( 'before_delete_post', array( $this, 'delete_files' ) );
		add_filter( 'upload_mimes', array( $this, 'allow_font_mimes' ) );

		add_filter( 'manage_wpdc_certificate_posts_columns', array( $this, 'certificate_columns' ) );
		add_action( 'manage_wpdc_certificate_posts_custom_column', array( $this, 'certificate_column_content' ), 10, 2 );
		add_filter( 'manage_wpdc_template_posts_columns', array( $this, 'template_columns' ) );
		add_action( 'manage_wpdc_template_posts_custom_column', array( $this, 'template_column_content' ), 10, 2 );

		add_action( 'admin_post_wpdc_download', array( $this, 'handle_download' ) );
		add_action( 'admin_post_wpdc_preview', array( $this, 'handle_preview' ) );
		add_action( 'admin_post_wpdc_send', array( $this, 'handle_send' ) );
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
	 * Подсказка на списке сертификатов: записи создаются программно.
	 *
	 * Массовой генерации в плагине нет, поэтому администратору нужно сказать
	 * прямо, откуда берутся сертификаты.
	 */
	public function creation_hint() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || 'edit-wpdc_certificate' !== $screen->id || ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$snippet = sprintf(
			"WPDC_Certificate::create( array( 'recipient' => 'Иванов Иван', 'course' => 'Название программы', 'hours' => 72, 'template_id' => %d ) );",
			(int) ( WPDC_Plugin::settings()['default_template'] ?? 0 )
		);

		printf(
			'<div class="notice notice-info inline wpdc-creation-hint"><p>%s</p><p><code>%s</code></p></div>',
			esc_html__( 'Сертификаты создаются программно — вызовом WPDC_Certificate::create() по событию на сайте (окончание курса, оплата, вебинар). Номер, код проверки и PDF формируются автоматически.', 'wp-diplom' ),
			esc_html( $snippet )
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
			'wpdcAdmin',
			array(
				'chooseBackground' => __( 'Выбрать фон бланка', 'wp-diplom' ),
				'useBackground'    => __( 'Использовать это изображение', 'wp-diplom' ),
				'confirmDelete'    => __( 'Удалить поле?', 'wp-diplom' ),
				'choosePdf'        => __( 'Выбрать PDF-шаблон', 'wp-diplom' ),
				'chooseFont'       => __( 'Выбрать шрифт .ttf', 'wp-diplom' ),
				'useFont'          => __( 'Использовать этот шрифт', 'wp-diplom' ),
				'usePdf'           => __( 'Использовать этот PDF', 'wp-diplom' ),
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
			'wpdc_template_fonts',
			__( 'Шрифты бланка', 'wp-diplom' ),
			array( $this, 'render_template_fonts_box' ),
			'wpdc_template',
			'normal',
			'default'
		);

		add_meta_box(
			'wpdc_template_params',
			__( 'Параметры бланка', 'wp-diplom' ),
			array( $this, 'render_template_params_box' ),
			'wpdc_template',
			'normal',
			'default'
		);

		add_meta_box(
			'wpdc_template_texts',
			__( 'Тексты бланка', 'wp-diplom' ),
			array( $this, 'render_template_texts_box' ),
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

		$pdf_id   = absint( get_post_meta( $post->ID, '_wpdc_pdf_id', true ) );
		$pdf_file = $pdf_id ? (string) get_attached_file( $pdf_id ) : '';
		$pdf_name = $pdf_file ? basename( $pdf_file ) : '';
		$pdf_page = absint( get_post_meta( $post->ID, '_wpdc_pdf_page', true ) );
		$pdf_w    = (float) get_post_meta( $post->ID, '_wpdc_pdf_w_mm', true );
		$pdf_h    = (float) get_post_meta( $post->ID, '_wpdc_pdf_h_mm', true );
		$pdf_err  = (string) get_post_meta( $post->ID, '_wpdc_pdf_error', true );
		$pdf_url  = $pdf_id ? (string) wp_get_attachment_url( $pdf_id ) : '';

		include WPDC_DIR . 'admin/views/meta-template-design.php';
	}

	/**
	 * Метабокс «Поля бланка».
	 *
	 * @param WP_Post $post Запись.
	 */
	/**
	 * Метабокс «Шрифты бланка».
	 *
	 * @param WP_Post $post Запись.
	 */
	public function render_template_fonts_box( $post ) {
		wp_nonce_field( 'wpdc_save_template', 'wpdc_template_nonce_fonts' );

		$fonts   = self::sanitize_fonts( get_post_meta( $post->ID, '_wpdc_fonts', true ) );
		$builtin = array( 'Sans (DejaVu)', 'Serif (DejaVu)' );

		include WPDC_DIR . 'admin/views/meta-template-fonts.php';
	}

	/**
	 * Разрешает загружать .ttf в медиатеку.
	 *
	 * @param array<string, string> $mimes Разрешённые типы.
	 * @return array<string, string>
	 */
	public function allow_font_mimes( $mimes ) {
		$mimes['ttf'] = 'font/ttf';

		return $mimes;
	}

	/**
	 * Гарнитуры для списка в полях бланка.
	 *
	 * @param int $template_id id бланка.
	 * @return array<string, string>
	 */
	public static function font_families( $template_id = 0 ) {
		$families = array(
			'sans'  => __( 'Sans (DejaVu)', 'wp-diplom' ),
			'serif' => __( 'Serif (DejaVu)', 'wp-diplom' ),
		);

		$rows = $template_id ? get_post_meta( $template_id, '_wpdc_fonts', true ) : array();

		foreach ( self::sanitize_fonts( $rows ) as $font ) {
			$families[ $font['family'] ] = '' !== $font['label'] ? $font['label'] : $font['family'];
		}

		return $families;
	}

	/**
	 * Очистка набора подключённых шрифтов.
	 *
	 * Строки без файлов отбрасываются: подключать нечего.
	 *
	 * @param mixed $raw Сырые данные.
	 * @return array<int, array<string, mixed>>
	 */
	public static function sanitize_fonts( $raw ) {
		$clean = array();

		if ( ! is_array( $raw ) ) {
			return $clean;
		}

		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$regular = absint( $row['regular'] ?? 0 );
			$bold    = absint( $row['bold'] ?? 0 );

			if ( ! $regular && ! $bold ) {
				continue;
			}

			$family = sanitize_key( (string) ( $row['family'] ?? '' ) );

			// Ключ не задан или был не латиницей — берём имя файла шрифта.
			if ( '' === $family ) {
				$file   = get_attached_file( $regular ? $regular : $bold );
				$family = sanitize_key( pathinfo( basename( (string) $file ), PATHINFO_FILENAME ) );
			}

			if ( '' === $family ) {
				$family = 'font-' . ( count( $clean ) + 1 );
			}

			$clean[] = array(
				'family'  => $family,
				'label'   => sanitize_text_field( wp_unslash( (string) ( $row['label'] ?? '' ) ) ),
				'regular' => $regular,
				'bold'    => $bold,
			);
		}

		return $clean;
	}

	public function render_template_params_box( $post ) {
		wp_nonce_field( 'wpdc_save_template', 'wpdc_template_nonce_params' );

		$stored = get_post_meta( $post->ID, '_wpdc_params', true );
		$saved  = is_array( $stored ) ? $stored : array();
		$params = WPDC_Plugin::params( $post->ID );
		$pages  = get_pages();
		$is_default = (int) WPDC_Plugin::settings( 'default_template' ) === (int) $post->ID;
		$post_id    = $post->ID;

		include WPDC_DIR . 'admin/views/meta-template-params.php';
	}

	public function render_template_texts_box( $post ) {
		wp_nonce_field( 'wpdc_save_template', 'wpdc_template_nonce_texts' );

		$stored    = get_post_meta( $post->ID, '_wpdc_texts', true );
		$overrides = is_array( $stored ) ? $stored : array();
		$global    = WPDC_Texts::global_texts();
		$defaults  = WPDC_Texts::defaults();

		// Запасное значение — текст соответствующего поля бланка.
		$fields = (array) get_post_meta( $post->ID, '_wpdc_fields', true );
		$by_id  = array();

		foreach ( $fields as $field ) {
			if ( is_array( $field ) && isset( $field['id'] ) ) {
				$by_id[ (string) $field['id'] ] = isset( $field['text'] ) ? (string) $field['text'] : '';
			}
		}

		$slots = WPDC_Texts::SLOTS;
		$values = array();

		foreach ( array_keys( $slots ) as $slot ) {
			$fallback        = $by_id[ $slot ] ?? $defaults[ $slot ];
			$values[ $slot ] = WPDC_Texts::effective( $slot, $overrides, $fallback );
		}

		$presets      = WPDC_Texts::presets();
		$placeholders = WPDC_Renderer::placeholders();

		include WPDC_DIR . 'admin/views/meta-template-texts.php';
	}

	public function render_template_fields_box( $post ) {
		$fields = get_post_meta( $post->ID, '_wpdc_fields', true );

		if ( ! is_array( $fields ) || empty( $fields ) ) {
			$fields = WPDC_Renderer::default_fields();
		}

		$placeholders = WPDC_Renderer::placeholders();
		$families     = self::font_families( $post->ID );
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
	 * Сохраняет PDF-шаблон бланка.
	 *
	 * Три пути: выбор файла из медиатеки (основной), загрузка через файловое
	 * поле (если включён enctype) и удаление. Вложение обязательно проверяется
	 * парсером: битый или зашифрованный PDF не должен попасть в бланк.
	 *
	 * @param int $post_id id бланка.
	 * @return int id вложения с PDF (0, если шаблона нет).
	 */
	private function save_pdf_template( $post_id ) {
		require_once WPDC_DIR . 'includes/pdf/class-wpdc-pdf-source.php';

		$current = absint( get_post_meta( $post_id, '_wpdc_pdf_id', true ) );

		delete_post_meta( $post_id, '_wpdc_pdf_error' );

		// Удаление шаблона.
		if ( ! empty( $_POST['wpdc_pdf_remove'] ) ) {
			$current = 0;

			foreach ( array( '_wpdc_pdf_id', '_wpdc_pdf_page', '_wpdc_pdf_w_mm', '_wpdc_pdf_h_mm' ) as $key ) {
				delete_post_meta( $post_id, $key );
			}

			return 0;
		}

		// Запасной путь: файловое поле (работает, если форме включили enctype).
		$uploaded = isset( $_FILES['wpdc_pdf'] ) && is_array( $_FILES['wpdc_pdf'] )
			&& isset( $_FILES['wpdc_pdf']['error'] )
			&& UPLOAD_ERR_NO_FILE !== (int) $_FILES['wpdc_pdf']['error'];

		if ( $uploaded ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';

			$attachment_id = media_handle_upload(
				'wpdc_pdf',
				$post_id,
				array(),
				array(
					'test_form' => false,
					'mimes'     => array( 'pdf' => 'application/pdf' ),
				)
			);

			$chosen = is_wp_error( $attachment_id ) ? 0 : (int) $attachment_id;

			if ( is_wp_error( $attachment_id ) ) {
				update_post_meta( $post_id, '_wpdc_pdf_error', $attachment_id->get_error_message() );
			}
		} else {
			$chosen = absint( $_POST['wpdc_pdf_id'] ?? 0 );
		}

		if ( $chosen && $chosen !== $current ) {
			$path = (string) get_attached_file( $chosen );
			$mime = (string) get_post_mime_type( $chosen );

			try {
				if ( 'application/pdf' !== $mime ) {
					throw new RuntimeException( 'Выбранный файл — не PDF.' );
				}

				$source = new WPDC_PDF_Source( $path );
				$pages  = $source->page_count();
				$page   = 0;
				$size   = $source->page_size( $page );

				$current = $chosen;

				update_post_meta( $post_id, '_wpdc_pdf_id', $current );
				update_post_meta( $post_id, '_wpdc_pdf_w_mm', round( $size['width'] * 25.4 / 72, 2 ) );
				update_post_meta( $post_id, '_wpdc_pdf_h_mm', round( $size['height'] * 25.4 / 72, 2 ) );
				update_post_meta( $post_id, '_wpdc_pdf_page', $page );
				update_post_meta( $current, '_wpdc_pdf_template', $post_id );
			} catch ( Throwable $e ) {
				update_post_meta( $post_id, '_wpdc_pdf_error', $e->getMessage() );

				if ( $uploaded && $chosen ) {
					wp_delete_attachment( $chosen, true );
				}
			}
		}

		// Номер страницы PDF (для многостраничных файлов).
		if ( $current && isset( $_POST['wpdc_pdf_page'] ) ) {
			$path = (string) get_attached_file( $current );

			try {
				$source = new WPDC_PDF_Source( $path );
				$pages  = max( 0, $source->page_count() - 1 );
				$page   = max( 0, min( $pages, (int) $_POST['wpdc_pdf_page'] - 1 ) );
				$size   = $source->page_size( $page );

				update_post_meta( $post_id, '_wpdc_pdf_page', $page );
				update_post_meta( $post_id, '_wpdc_pdf_w_mm', round( $size['width'] * 25.4 / 72, 2 ) );
				update_post_meta( $post_id, '_wpdc_pdf_h_mm', round( $size['height'] * 25.4 / 72, 2 ) );
			} catch ( Throwable $e ) {
				update_post_meta( $post_id, '_wpdc_pdf_error', $e->getMessage() );
			}
		}

		return $current;
	}

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

		// Дизайн: сначала PDF-шаблон (он влияет на формат страницы).
		$pdf_id     = $this->save_pdf_template( $post_id );
		$page       = sanitize_key( wp_unslash( $_POST['wpdc_page'] ?? 'a4_landscape' ) );
		$new_upload = ( isset( $_POST['wpdc_pdf_id'] ) && absint( $_POST['wpdc_pdf_id'] ) )
			|| ( isset( $_FILES['wpdc_pdf']['name'] ) && '' !== (string) $_FILES['wpdc_pdf']['name'] );

		if ( $pdf_id && $new_upload ) {
			// Загрузили новый PDF — печатаем в его размере.
			$page = 'pdf';
		}

		if ( ! $pdf_id && 'pdf' === $page ) {
			$page = 'a4_landscape';
		}

		if ( 'pdf' !== $page && ! isset( WPDC_Renderer::PAGE_SIZES[ $page ] ) ) {
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

		// Шрифты бланка.
		$fonts = self::sanitize_fonts( wp_unslash( $_POST['wpdc_fonts'] ?? array() ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( $fonts ) {
			update_post_meta( $post_id, '_wpdc_fonts', $fonts );
		} else {
			delete_post_meta( $post_id, '_wpdc_fonts' );
		}

		// Параметры бланка.
		if ( isset( $_POST['wpdc_params'] ) && is_array( $_POST['wpdc_params'] ) ) {
			$params = WPDC_Plugin::sanitize_params( wp_unslash( $_POST['wpdc_params'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			update_post_meta( $post_id, '_wpdc_params', $params );
			// Организацию дублируем в старый ключ: его читает шапка бланка.
			update_post_meta( $post_id, '_wpdc_organization', $params['organization'] );
		}

		if ( isset( $_POST['wpdc_is_default'] ) ) {
			WPDC_Plugin::set_default_template( empty( $_POST['wpdc_is_default'] ) ? 0 : $post_id );
		}

		// Тексты бланка: пустое значение = «как в настройках».
		if ( isset( $_POST['wpdc_text'] ) ) {
			$texts = WPDC_Texts::sanitize( wp_unslash( $_POST['wpdc_text'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

			if ( $texts ) {
				update_post_meta( $post_id, '_wpdc_texts', $texts );
			} else {
				delete_post_meta( $post_id, '_wpdc_texts' );
			}
		}

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

		$params   = WPDC_Plugin::params( $template_id );
		$template = WPDC_Certificate::template_config( $template_id );

		$sample = array(
			'recipient_name'  => __( 'Петрова Анна Сергеевна', 'wp-diplom' ),
			'recipient_email' => 'anna@example.com',
			'course_title'    => __( 'Веб-разработка на WordPress', 'wp-diplom' ),
			'course_hours'    => '72',
			'date'            => WPDC_Certificate::format_date( current_time( 'Y-m-d' ) ),
			'number'          => 'WPD-' . current_time( 'Y' ) . '-0001',
			'organization'    => $params['organization'],
			'site_name'       => get_bloginfo( 'name' ),
			'code'            => 'demo123456',
			'custom'          => array( 'group' => 'ВР-21' ),
		);

		// Если название курса задано в бланке — показываем его в предпросмотре.
		$texts = WPDC_Texts::resolve( isset( $template['texts'] ) ? (array) $template['texts'] : array() );

		if ( ! empty( $texts['course_title'] ) ) {
			$sample['course_title'] = $texts['course_title'];
		}

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

	/* --------------------------------------------------------------------- */
	/* Утилиты                                                                */
	/* --------------------------------------------------------------------- */

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
