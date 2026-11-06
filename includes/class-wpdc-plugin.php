<?php
/**
 * Ядро плагина: загрузка компонентов, активация, настройки.
 *
 * @package WP_Diplom
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Главный класс плагина.
 */
final class WPDC_Plugin {

	/**
	 * Экземпляр (синглтон).
	 *
	 * @var WPDC_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Экземпляры компонентов.
	 *
	 * @var array<string, object>
	 */
	private $components = array();

	/**
	 * Экземпляр плагина.
	 *
	 * @return WPDC_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Конструктор.
	 */
	private function __construct() {
		$this->load_files();
	}

	/**
	 * Подключение файлов.
	 */
	private function load_files() {
		require_once WPDC_DIR . 'includes/class-wpdc-texts.php';
		require_once WPDC_DIR . 'includes/class-wpdc-post-types.php';
		require_once WPDC_DIR . 'includes/class-wpdc-renderer.php';
		require_once WPDC_DIR . 'includes/class-wpdc-certificate.php';
		require_once WPDC_DIR . 'includes/class-wpdc-email.php';
		require_once WPDC_DIR . 'includes/class-wpdc-admin.php';
		require_once WPDC_DIR . 'includes/class-wpdc-frontend.php';
	}

	/**
	 * Инициализация хуков.
	 */
	public function init() {
		load_plugin_textdomain( 'wp-diplom', false, dirname( WPDC_BASENAME ) . '/languages' );

		$this->components['post_types'] = new WPDC_Post_Types();
		$this->components['email']      = new WPDC_Email();
		$this->components['admin']      = new WPDC_Admin();
		$this->components['frontend']   = new WPDC_Frontend();

		foreach ( $this->components as $component ) {
			if ( method_exists( $component, 'hooks' ) ) {
				$component->hooks();
			}
		}

		add_action( 'init', array( $this, 'register_rewrite' ) );
		add_action( 'init', array( __CLASS__, 'migrate_field_texts' ), 20 );
		add_filter( 'plugin_action_links_' . WPDC_BASENAME, array( $this, 'action_links' ) );
	}

	/**
	 * Компонент по имени.
	 *
	 * @param string $name Имя.
	 * @return object|null
	 */
	public function component( $name ) {
		return $this->components[ $name ] ?? null;
	}

	/**
	 * Однократный перенос текстов полей в переопределения бланка.
	 *
	 * До 1.3.0 фраза хранилась прямо в поле бланка. Теперь стандартные фразы
	 * живут в «Текстах», а поле хранит только положение. Чтобы настроенные
	 * вручную бланки не потеряли свой текст, отличающиеся значения
	 * переносятся в `_wpdc_texts` — как явное переопределение.
	 */
	public static function migrate_field_texts() {
		if ( get_option( 'wpdc_texts_migrated' ) ) {
			return;
		}

		if ( ! class_exists( 'WPDC_Texts' ) ) {
			require_once WPDC_DIR . 'includes/class-wpdc-texts.php';
		}

		$defaults  = WPDC_Texts::defaults();
		$templates = get_posts(
			array(
				'post_type'      => 'wpdc_template',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( $templates as $template_id ) {
			$fields = get_post_meta( $template_id, '_wpdc_fields', true );

			if ( ! is_array( $fields ) ) {
				continue;
			}

			$raw_texts = get_post_meta( $template_id, '_wpdc_texts', true );
			$overrides = is_array( $raw_texts ) ? $raw_texts : array();

			foreach ( $fields as $field ) {
				$slot = isset( $field['id'] ) ? (string) $field['id'] : '';

				if ( ! WPDC_Texts::is_slot( $slot ) || isset( $overrides[ $slot ] ) ) {
					continue;
				}

				$text = isset( $field['text'] ) ? (string) $field['text'] : '';

				if ( '' !== trim( $text ) && $text !== $defaults[ $slot ] ) {
					$overrides[ $slot ] = $text;
				}
			}

			if ( $overrides ) {
				update_post_meta( $template_id, '_wpdc_texts', $overrides );
			}
		}

		update_option( 'wpdc_texts_migrated', 1, false );
	}

	/**
	 * Правила перезаписи для публичной ссылки на сертификат.
	 */
	public function register_rewrite() {
		add_rewrite_rule(
			'^certificate/([A-Za-z0-9]{4,32})/?$',
			'index.php?wpdc_code=$matches[1]',
			'top'
		);
	}

	/**
	 * Ссылки на странице плагинов.
	 *
	 * @param string[] $links Ссылки.
	 * @return string[]
	 */
	public function action_links( $links ) {
		$url = admin_url( 'edit.php?post_type=wpdc_template&page=wpdc-settings' );

		array_unshift(
			$links,
			sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html__( 'Настройки', 'wp-diplom' ) )
		);

		return $links;
	}

	/**
	 * Настройки плагина со значениями по умолчанию.
	 *
	 * @param string|null $key Ключ (null — весь массив).
	 * @return mixed
	 */
	public static function settings( $key = null ) {
		$defaults = array(
			'organization'    => get_bloginfo( 'name' ),
			'default_template' => 0,
			'email_subject'   => 'Ваш сертификат — {course_title}',
			'email_body'      => "Здравствуйте, {recipient_name}!\n\nНаправляем вам сертификат за курс «{course_title}».\nНомер: {number}\nДата выдачи: {date}\n\nПроверить подлинность: {verify_url}\n\nС уважением,\n{organization}",
			'sender_name'     => get_bloginfo( 'name' ),
			'sender_email'    => get_option( 'admin_email' ),
			'enable_verify'   => 1,
			'number_prefix'   => 'WPD',
			'number_padding'  => 4,
			'sample_date'     => '',
			'sample_teacher'  => '',
		);

		$saved = get_option( 'wpdc_settings', array() );
		$saved = is_array( $saved ) ? $saved : array();

		$settings = array_merge( $defaults, $saved );

		if ( null === $key ) {
			return $settings;
		}

		return $settings[ $key ] ?? null;
	}

	/**
	 * Активация.
	 *
	 * Работа вынесена в отдельный метод и обёрнута в try/catch: если на сайте
	 * что-то пойдёт не так (нехватка памяти, отсутствующее расширение,
	 * нестандартный хостинг), WordPress не покажет «фатальную ошибку» и не
	 * откажется включать плагин — вместо этого текст ошибки попадёт в админку.
	 */
	public static function activate() {
		delete_option( 'wpdc_activation_error' );

		try {
			self::do_activate();
		} catch ( Throwable $e ) {
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

	/**
	 * Непосредственно активация: бланк по умолчанию и правила перезаписи.
	 */
	private static function do_activate() {
		require_once WPDC_DIR . 'includes/class-wpdc-texts.php';
		require_once WPDC_DIR . 'includes/class-wpdc-post-types.php';
		require_once WPDC_DIR . 'includes/class-wpdc-renderer.php';

		$post_types = new WPDC_Post_Types();
		$post_types->register_post_types();

		$existing = get_posts(
			array(
				'post_type'      => 'wpdc_template',
				'posts_per_page' => 1,
				'post_status'    => 'any',
				'fields'         => 'ids',
			)
		);

		if ( empty( $existing ) ) {
			$template_id = wp_insert_post(
				array(
					'post_type'   => 'wpdc_template',
					'post_title'  => __( 'Сертификат (по умолчанию)', 'wp-diplom' ),
					'post_status' => 'publish',
				),
				true
			);

			if ( ! is_wp_error( $template_id ) ) {
				update_post_meta( $template_id, '_wpdc_page', 'a4_landscape' );
				update_post_meta( $template_id, '_wpdc_background_color', '#ffffff' );
				update_post_meta( $template_id, '_wpdc_background_fit', 'cover' );
				update_post_meta( $template_id, '_wpdc_document_title', __( 'Сертификат', 'wp-diplom' ) );
				update_post_meta(
					$template_id,
					'_wpdc_border',
					array(
						'enabled' => 1,
						'color'   => '#2f6fed',
						'width'   => 0.8,
						'inset_x' => 12,
						'inset_y' => 12,
					)
				);
				update_post_meta( $template_id, '_wpdc_fields', WPDC_Renderer::default_fields() );

				$settings                     = (array) get_option( 'wpdc_settings', array() );
				$settings['default_template'] = $template_id;
				update_option( 'wpdc_settings', $settings );
			}
		}

		if ( ! wp_next_scheduled( 'wpdc_cleanup_temp' ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'wpdc_cleanup_temp' );
		}

		flush_rewrite_rules();
	}

	/**
	 * Деактивация.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'wpdc_cleanup_temp' );
		flush_rewrite_rules();
	}

	/**
	 * Каталог для PDF-файлов (внутри uploads).
	 *
	 * @return string Путь с завершающим слэшем.
	 */
	public static function storage_dir() {
		$uploads = wp_upload_dir( null, false );

		$dir = trailingslashit( $uploads['basedir'] ) . 'wpdc-certificates';

		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );

			// Защита от прямого листинга.
			if ( ! file_exists( $dir . '/index.php' ) ) {
				file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			}

			if ( ! file_exists( $dir . '/.htaccess' ) ) {
				file_put_contents( $dir . '/.htaccess', "Options -Indexes\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			}
		}

		return trailingslashit( $dir );
	}
}
