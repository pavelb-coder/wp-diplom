<?php
/**
 * Минимальные заглушки WordPress для автономных тестов.
 *
 * Реализуют только те функции, которые нужны логике плагина: работа с
 * записями, метаданными, опциями, датами и путями. Хранилище — массивы
 * в памяти, файлы — во временном каталоге.
 *
 * @package WP_Diplom
 */

// Константы плагина (WPD_DIR, WPD_URL, WPD_VERSION, WPD_BASENAME) определяет
// сам плагин либо тест-скрипт — здесь их дублировать нельзя.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['wpd_test'] = array(
	'posts'   => array(),
	'meta'    => array(),
	'options' => array(),
	'next_id' => 100,
	'uploads' => sys_get_temp_dir() . '/wpd-test-uploads-' . getmypid(),
);

/**
 * Заглушка WP_Error.
 */
class WP_Error {

	/**
	 * Код ошибки.
	 *
	 * @var string
	 */
	private $code;

	/**
	 * Сообщение.
	 *
	 * @var string
	 */
	private $message;

	/**
	 * Конструктор.
	 *
	 * @param string $code    Код.
	 * @param string $message Сообщение.
	 */
	public function __construct( $code = '', $message = '' ) {
		$this->code    = $code;
		$this->message = $message;
	}

	/**
	 * Сообщение.
	 *
	 * @return string
	 */
	public function get_error_message() {
		return $this->message;
	}

	/**
	 * Код.
	 *
	 * @return string
	 */
	public function get_error_code() {
		return $this->code;
	}
}

/**
 * Проверка на WP_Error.
 *
 * @param mixed $thing Значение.
 * @return bool
 */
function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

/* --- Хуки ------------------------------------------------------------- */
function add_action( ...$args ) {}
function add_filter( ...$args ) {}
function do_action( ...$args ) {}
function apply_filters( $tag, $value ) {
	return $value;
}
function add_shortcode( ...$args ) {}
function register_activation_hook( ...$args ) {}
function register_deactivation_hook( ...$args ) {}
function load_plugin_textdomain( ...$args ) {}

/* --- Записи ----------------------------------------------------------- */
function wp_insert_post( $args, $wp_error = false ) {
	$id = $GLOBALS['wpd_test']['next_id']++;

	$GLOBALS['wpd_test']['posts'][ $id ] = array(
		'ID'          => $id,
		'post_type'   => $args['post_type'] ?? 'post',
		'post_status' => $args['post_status'] ?? 'publish',
		'post_title'  => $args['post_title'] ?? '',
	);

	return $id;
}

function get_post( $post = null ) {
	$id = is_object( $post ) ? (int) $post->ID : (int) $post;

	if ( ! isset( $GLOBALS['wpd_test']['posts'][ $id ] ) ) {
		return null;
	}

	return (object) $GLOBALS['wpd_test']['posts'][ $id ];
}

function get_post_type( $post = null ) {
	$post = get_post( $post );

	return $post ? $post->post_type : false;
}

function get_posts( $args = array() ) {
	$types    = (array) ( $args['post_type'] ?? 'post' );
	$fields   = $args['fields'] ?? '';
	$limit    = isset( $args['posts_per_page'] ) && -1 !== (int) $args['posts_per_page'] ? (int) $args['posts_per_page'] : PHP_INT_MAX;
	$meta_key = $args['meta_key'] ?? '';
	$meta_val = $args['meta_value'] ?? '';
	$out      = array();

	foreach ( $GLOBALS['wpd_test']['posts'] as $post ) {
		if ( ! in_array( $post['post_type'], $types, true ) ) {
			continue;
		}

		if ( ! empty( $args['post_status'] ) && 'any' !== $args['post_status'] ) {
			$statuses = (array) $args['post_status'];

			if ( ! in_array( $post['post_status'], $statuses, true ) ) {
				continue;
			}
		}

		if ( $meta_key ) {
			$value = get_post_meta( $post['ID'], $meta_key, true );

			if ( (string) $value !== (string) $meta_val ) {
				continue;
			}
		}

		$out[] = $post;

		if ( count( $out ) >= $limit ) {
			break;
		}
	}

	if ( 'ids' === $fields ) {
		return array_map(
			static function ( $post ) {
				return $post['ID'];
			},
			$out
		);
	}

	// Возвращаем объекты записей (как WP_Post), а не вызов get_post() —
	// array_map передал бы в него массив, и id потерялся бы.
	return array_map(
		static function ( $post ) {
			return (object) $post;
		},
		$out
	);
}

function wp_delete_post( $id, $force = false ) {
	unset( $GLOBALS['wpd_test']['posts'][ (int) $id ] );

	return true;
}

/* --- Метаданные ------------------------------------------------------- */
function update_post_meta( $post_id, $key, $value ) {
	$GLOBALS['wpd_test']['meta'][ (int) $post_id ][ $key ] = $value;

	return true;
}

function get_post_meta( $post_id, $key = '', $single = false ) {
	$post_id = (int) $post_id;

	if ( '' === $key ) {
		return $GLOBALS['wpd_test']['meta'][ $post_id ] ?? array();
	}

	$exists = isset( $GLOBALS['wpd_test']['meta'][ $post_id ][ $key ] );

	if ( $single ) {
		return $exists ? $GLOBALS['wpd_test']['meta'][ $post_id ][ $key ] : '';
	}

	return $exists ? array( $GLOBALS['wpd_test']['meta'][ $post_id ][ $key ] ) : array();
}

/* --- Опции ------------------------------------------------------------ */
function get_option( $key, $default = false ) {
	if ( 'date_format' === $key ) {
		return $GLOBALS['wpd_test']['options'][ $key ] ?? 'd.m.Y';
	}

	return array_key_exists( $key, $GLOBALS['wpd_test']['options'] )
		? $GLOBALS['wpd_test']['options'][ $key ]
		: $default;
}

function update_option( $key, $value, $autoload = null ) {
	$GLOBALS['wpd_test']['options'][ $key ] = $value;

	return true;
}

function delete_option( $key ) {
	unset( $GLOBALS['wpd_test']['options'][ $key ] );

	return true;
}

function get_transient( $key ) {
	return $GLOBALS['wpd_test']['options'][ '_transient_' . $key ] ?? false;
}

function set_transient( $key, $value, $expiration = 0 ) {
	$GLOBALS['wpd_test']['options'][ '_transient_' . $key ] = $value;

	return true;
}

/* --- Даты и строки ---------------------------------------------------- */
function current_time( $type = 'mysql' ) {
	if ( 'Y' === $type ) {
		return gmdate( 'Y' );
	}

	return 'mysql' === $type ? gmdate( 'Y-m-d H:i:s' ) : gmdate( (string) $type );
}

function date_i18n( $format, $timestamp = null ) {
	return gmdate( $format, null === $timestamp ? time() : $timestamp );
}

function __( $text, $domain = null ) {
	return $text;
}

function esc_html__( $text, $domain = null ) {
	return $text;
}

function esc_html_e( $text, $domain = null ) {
	echo htmlspecialchars( (string) $text, ENT_QUOTES ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}

function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}

function esc_url( $url ) {
	return (string) $url;
}

function esc_textarea( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}

function wp_kses_post( $text ) {
	return (string) $text;
}

function sanitize_text_field( $value ) {
	return trim( preg_replace( '/[\r\n\t]+/', ' ', strip_tags( (string) $value ) ) );
}

function sanitize_textarea_field( $value ) {
	return trim( strip_tags( (string) $value ) );
}

function sanitize_email( $value ) {
	return filter_var( (string) $value, FILTER_VALIDATE_EMAIL ) ? (string) $value : '';
}

function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
}

function sanitize_file_name( $value ) {
	return preg_replace( '/[^A-Za-z0-9._-]/', '', (string) $value );
}

function sanitize_title( $value ) {
	return sanitize_key( $value );
}

function is_email( $value ) {
	return (bool) filter_var( (string) $value, FILTER_VALIDATE_EMAIL );
}

function absint( $value ) {
	return abs( (int) $value );
}

function wp_unslash( $value ) {
	return $value;
}

/* --- Пути и URL ------------------------------------------------------- */
function get_bloginfo( $show = 'name' ) {
	return 'name' === $show ? 'Тестовый сайт' : '';
}

function wp_upload_dir( $dir = null, $create = true ) {
	return array(
		'basedir' => $GLOBALS['wpd_test']['uploads'],
		'baseurl' => 'http://example.test/uploads',
	);
}

function wp_mkdir_p( $dir ) {
	return is_dir( $dir ) || mkdir( $dir, 0777, true );
}

function trailingslashit( $value ) {
	return rtrim( (string) $value, '/\\' ) . '/';
}

function untrailingslashit( $value ) {
	return rtrim( (string) $value, '/\\' );
}

function plugin_dir_path( $file ) {
	return trailingslashit( dirname( $file ) );
}

function plugin_dir_url( $file ) {
	return 'http://example.test/wp-content/plugins/wp-diplom/';
}

function plugin_basename( $file ) {
	return 'wp-diplom/wp-diplom.php';
}

function get_attached_file( $attachment_id ) {
	return '';
}

function home_url( $path = '' ) {
	return 'http://example.test' . $path;
}

function site_url( $path = '' ) {
	return home_url( $path );
}

function admin_url( $path = '' ) {
	return 'http://example.test/wp-admin/' . $path;
}

function get_permalink( $post = 0 ) {
	$id = is_object( $post ) ? $post->ID : (int) $post;

	return 'http://example.test/?p=' . $id;
}

function add_query_arg( ...$args ) {
	$url   = '';
	$query = array();

	if ( is_array( $args[0] ) ) {
		$query = $args[0];
		$url   = $args[1] ?? '';
	} else {
		$query[ $args[0] ] = $args[1];
		$url               = $args[2] ?? '';
	}

	$sep = false === strpos( $url, '?' ) ? '?' : '&';

	return $url . $sep . http_build_query( $query );
}

function wp_nonce_url( $url, $action = -1 ) {
	return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . '_wpnonce=testnonce';
}

function wp_delete_file( $file ) {
	if ( is_file( $file ) ) {
		unlink( $file );
	}
}

function wp_tempnam( $filename = '' ) {
	return tempnam( sys_get_temp_dir(), 'wpd' );
}

function wp_die( $message = '' ) {
	throw new RuntimeException( is_string( $message ) ? $message : 'wp_die' );
}
