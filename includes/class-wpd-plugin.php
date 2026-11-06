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
final class WPD_Plugin {

	/**
	 * Экземпляр (синглтон).
	 *
	 * @var WPD_Plugin|null
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
	 * @return WPD_Plugin
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
		require_once WPD_DIR . 'includes/class-wpd-post-types.php';
		require_once WPD_DIR . 'includes/class-wpd-renderer.php';
		require_once WPD_DIR . 'includes/class-wpd-certificate.php';
		require_once WPD_DIR . 'includes/class-wpd-email.php';
		require_once WPD_DIR . 'includes/class-wpd-admin.php';
		require_once WPD_DIR . 'includes/class-wpd-frontend.php';
	}

	/**
	 * Инициализация хуков.
	 */
	public function init() {
		load_plugin_textdomain( 'wp-diplom', false, dirname( WPD_BASENAME ) . '/languages' );

		$this->components['post_types'] = new WPD_Post_Types();
		$this->components['email']      = new WPD_Email();
		$this->components['admin']      = new WPD_Admin();
		$this->components['frontend']   = new WPD_Frontend();

		foreach ( $this->components as $component ) {
			if ( method_exists( $component, 'hooks' ) ) {
				$component->hooks();
			}
		}

		add_action( 'init', array( $this, 'register_rewrite' ) );
		add_filter( 'plugin_action_links_' . WPD_BASENAME, array( $this, 'action_links' ) );
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
	 * Правила перезаписи для публичной ссылки на сертификат.
	 */
	public function register_rewrite() {
		add_rewrite_rule(
			'^certificate/([A-Za-z0-9]{4,32})/?$',
			'index.php?wpd_code=$matches[1]',
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
		$url = admin_url( 'edit.php?post_type=wpd_template&page=wpd-settings' );

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

		$saved = get_option( 'wpd_settings', array() );
		$saved = is_array( $saved ) ? $saved : array();

		$settings = array_merge( $defaults, $saved );

		if ( null === $key ) {
			return $settings;
		}

		return $settings[ $key ] ?? null;
	}

	/**
	 * Активация: создаём бланк по умолчанию и правила перезаписи.
	 */
	public static function activate() {
		require_once WPD_DIR . 'includes/class-wpd-post-types.php';
		require_once WPD_DIR . 'includes/class-wpd-renderer.php';

		$post_types = new WPD_Post_Types();
		$post_types->register_post_types();

		$existing = get_posts(
			array(
				'post_type'      => 'wpd_template',
				'posts_per_page' => 1,
				'post_status'    => 'any',
				'fields'         => 'ids',
			)
		);

		if ( empty( $existing ) ) {
			$template_id = wp_insert_post(
				array(
					'post_type'   => 'wpd_template',
					'post_title'  => __( 'Сертификат (по умолчанию)', 'wp-diplom' ),
					'post_status' => 'publish',
				),
				true
			);

			if ( ! is_wp_error( $template_id ) ) {
				update_post_meta( $template_id, '_wpd_page', 'a4_landscape' );
				update_post_meta( $template_id, '_wpd_background_color', '#ffffff' );
				update_post_meta( $template_id, '_wpd_background_fit', 'cover' );
				update_post_meta( $template_id, '_wpd_document_title', __( 'Сертификат', 'wp-diplom' ) );
				update_post_meta(
					$template_id,
					'_wpd_border',
					array(
						'enabled' => 1,
						'color'   => '#2f6fed',
						'width'   => 0.8,
						'inset_x' => 12,
						'inset_y' => 12,
					)
				);
				update_post_meta( $template_id, '_wpd_fields', WPD_Renderer::default_fields() );

				$settings                     = (array) get_option( 'wpd_settings', array() );
				$settings['default_template'] = $template_id;
				update_option( 'wpd_settings', $settings );
			}
		}

		if ( ! wp_next_scheduled( 'wpd_cleanup_temp' ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'wpd_cleanup_temp' );
		}

		flush_rewrite_rules();
	}

	/**
	 * Деактивация.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'wpd_cleanup_temp' );
		flush_rewrite_rules();
	}

	/**
	 * Каталог для PDF-файлов (внутри uploads).
	 *
	 * @return string Путь с завершающим слэшем.
	 */
	public static function storage_dir() {
		$uploads = wp_upload_dir( null, false );

		$dir = trailingslashit( $uploads['basedir'] ) . 'wpd-certificates';

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
