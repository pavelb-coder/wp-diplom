<?php
/**
 * Интеграционный тест логики плагина на заглушках WordPress.
 *
 * Проверяет: нумерацию, создание сертификата, метаданные, коды проверки,
 * кэширование PDF и конфигурацию бланка.
 *
 * Запуск: php tests/integration-test.php
 *
 * @package WP_Diplom
 */

if ( ! defined( 'WPDC_DIR' ) ) {
	define( 'WPDC_DIR', dirname( __DIR__ ) . '/' );
}

require_once __DIR__ . '/wp-stubs.php';

require_once WPDC_DIR . 'includes/class-wpdc-renderer.php';
require_once WPDC_DIR . 'includes/class-wpdc-certificate.php';
require_once WPDC_DIR . 'includes/class-wpdc-admin.php';
require_once WPDC_DIR . 'includes/class-wpdc-email.php';

/**
 * Заглушка главного класса плагина: только настройки и каталог хранения.
 */
class WPDC_Plugin {

	/**
	 * Настройки.
	 *
	 * @param string|null $key Ключ.
	 * @return mixed
	 */
	public static function settings( $key = null ) {
		$defaults = array(
			'organization'     => 'Тестовый сайт',
			'default_template' => 0,
			'number_prefix'    => 'WPD',
			'number_padding'   => 4,
			'email_subject'    => 'Ваш сертификат — {course_title}',
			'email_body'       => 'Здравствуйте, {recipient_name}!',
			'sender_name'      => 'Тестовый сайт',
			'sender_email'     => 'noreply@example.test',
			'verify_page'      => 0,
			'enable_verify'    => 1,
		);

		$saved = get_option( 'wpdc_settings', array() );
		$saved = is_array( $saved ) ? $saved : array();
		$all   = array_merge( $defaults, $saved );

		return null === $key ? $all : ( $all[ $key ] ?? null );
	}

	/**
	 * Каталог PDF.
	 *
	 * @return string
	 */
	public static function storage_dir() {
		$dir = trailingslashit( $GLOBALS['wpdc_test']['uploads'] ) . 'wpdc-certificates';

		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		return trailingslashit( $dir );
	}
}

$failures = 0;

/**
 * Проверка утверждения.
 *
 * @param bool   $condition Условие.
 * @param string $label     Описание.
 */
function check( $condition, $label ) {
	global $failures;

	echo ( $condition ? '✓ ' : '✗ ' ) . $label . "\n";

	if ( ! $condition ) {
		$failures++;
	}
}

// --- Бланк по умолчанию -------------------------------------------------
$template_id = wp_insert_post(
	array(
		'post_type'   => 'wpdc_template',
		'post_status' => 'publish',
		'post_title'  => 'Основной бланк',
	)
);

update_post_meta( $template_id, '_wpdc_page', 'a4_landscape' );
update_post_meta( $template_id, '_wpdc_border', array( 'enabled' => 1, 'color' => '#2f6fed', 'width' => 0.8, 'inset_x' => 12, 'inset_y' => 12 ) );
update_post_meta( $template_id, '_wpdc_fields', WPDC_Renderer::default_fields() );

$config = WPDC_Certificate::template_config( $template_id );

check( 'a4_landscape' === $config['page'], 'template_config вернул формат страницы' );
check( 9 === count( $config['fields'] ), 'template_config вернул 9 полей' );
check( true === $config['border']['enabled'], 'рамка включена' );
check( 'Тестовый сайт' === $config['organization'], 'организация подставлена из настроек' );

$fallback = WPDC_Certificate::template_config( 0 );
check( 9 === count( $fallback['fields'] ), 'без бланка используются поля по умолчанию' );

// --- Нумерация ----------------------------------------------------------
$year = current_time( 'Y' );

$first  = WPDC_Certificate::next_number();
$second = WPDC_Certificate::next_number();

check( 'WPD-' . $year . '-0001' === $first, 'первый номер: ' . $first );
check( 'WPD-' . $year . '-0002' === $second, 'номер инкрементируется: ' . $second );

// --- Создание сертификата ----------------------------------------------
$created = WPDC_Certificate::create(
	array(
		'recipient'   => 'Иванов Иван Иванович',
		'email'       => 'ivan@example.com',
		'course'      => 'Веб-разработка на WordPress',
		'hours'       => '72',
		'teacher'     => 'Петров П. П.',
		'date'        => '2026-11-06',
		'template_id' => $template_id,
		'custom'      => array( 'group' => 'ВР-21' ),
	)
);

check( is_int( $created ) && $created > 0, 'сертификат создан, id = ' . var_export( $created, true ) );

$data = WPDC_Certificate::data( $created );

check( 'Иванов Иван Иванович' === $data['recipient_name'], 'имя получателя сохранено' );
check( 'ivan@example.com' === $data['recipient_email'], 'e-mail сохранён' );
check( '06.11.2026' === $data['date'], 'дата отформатирована по date_format: ' . $data['date'] );
check( 'ВР-21' === $data['custom']['group'], 'дополнительное поле сохранено' );
check( 'WPD-' . $year . '-0003' === $data['number'], 'номер присвоен автоматически: ' . $data['number'] );
check( 12 === strlen( $data['code'] ) && ctype_xdigit( $data['code'] ), 'код проверки — 12 hex-символов' );

$empty = WPDC_Certificate::create( array( 'recipient' => '   ' ) );

check( is_wp_error( $empty ), 'пустое имя получателя → WP_Error' );

// --- Поиск по коду -----------------------------------------------------
$found = WPDC_Certificate::by_code( strtoupper( $data['code'] ) );

check( $found && (int) $found->ID === $created, 'поиск по коду работает (регистр не важен)' );

$missing = WPDC_Certificate::by_code( 'deadbeefcafe' );

check( null === $missing, 'несуществующий код → null' );

// --- Генерация PDF и кэш ----------------------------------------------
$pdf_one = WPDC_Certificate::pdf( $created );

check( ! is_wp_error( $pdf_one ), 'PDF сгенерирован без ошибок' );
check( is_string( $pdf_one ) && file_exists( $pdf_one ), 'файл PDF существует: ' . basename( (string) $pdf_one ) );

if ( is_string( $pdf_one ) && file_exists( $pdf_one ) ) {
	$binary = (string) file_get_contents( $pdf_one );

	check( 0 === strpos( $binary, '%PDF-1.4' ), 'файл начинается с %PDF-1.4' );
	check( strlen( $binary ) > 300000, sprintf( 'размер PDF: %.1f КБ', strlen( $binary ) / 1024 ) );
	check( false !== strpos( $binary, '/ToUnicode' ), 'PDF содержит ToUnicode (кириллица извлекается)' );

	$first_mtime = filemtime( $pdf_one );
	sleep( 1 );

	$pdf_two = WPDC_Certificate::pdf( $created );

	check( filemtime( $pdf_two ) === $first_mtime, 'повторный вызов берёт PDF из кэша' );

	WPDC_Certificate::delete_pdf( $created );

	check( ! file_exists( $pdf_one ), 'delete_pdf удалил файл' );

	$pdf_three = WPDC_Certificate::pdf( $created );

	check( ! is_wp_error( $pdf_three ) && file_exists( $pdf_three ), 'после сброса PDF пересобран' );
}

// --- Массовая генерация удалена ----------------------------------------
$admin = new WPDC_Admin();

$bulk_methods = array( 'handle_bulk', 'handle_bulk_zip', 'render_bulk_page', 'parse_csv' );
$leftovers    = array();

foreach ( $bulk_methods as $method ) {
	if ( method_exists( $admin, $method ) ) {
		$leftovers[] = $method;
	}
}

check( empty( $leftovers ), 'обработчиков массовой генерации нет' . ( $leftovers ? ': ' . implode( ', ', $leftovers ) : '' ) );

$admin_source = (string) file_get_contents( WPDC_DIR . 'includes/class-wpdc-admin.php' );

check( false === strpos( $admin_source, 'wpdc-bulk' ), 'подменю «Массовая генерация» не регистрируется' );
check( false === strpos( $admin_source, 'wpdc_bulk' ), 'хуки admin_post_wpdc_bulk_* удалены' );
check( false === strpos( $admin_source, 'ZipArchive' ), 'работа с ZIP удалена' );
check( ! file_exists( WPDC_DIR . 'admin/views/page-bulk.php' ), 'страница массовой генерации удалена' );

// --- Письмо: настраивается только встроенными значениями -----------------
$settings_view = (string) file_get_contents( WPDC_DIR . 'admin/views/page-settings.php' );

foreach ( array( 'wpdc_email_subject', 'wpdc_email_body', 'wpdc_sender_name', 'wpdc_sender_email' ) as $gone ) {
	check( false === strpos( $settings_view, $gone ), 'в настройках нет поля ' . $gone );
}

check( false === strpos( $settings_view, 'Письмо с сертификатом' ), 'раздел «Письмо с сертификатом» удалён' );
check( false !== strpos( $settings_view, 'wpdc_sample_teacher' ), 'поле «Преподаватель в предпросмотре» сохранено' );

$defaults = WPDC_Plugin::settings();

check( '' !== trim( (string) $defaults['email_subject'] ), 'тема письма по умолчанию не пуста' );
check( '' !== trim( (string) $defaults['email_body'] ), 'текст письма по умолчанию не пуст' );

// --- Письмо ------------------------------------------------------------
$message = WPDC_Email::compose( $created );

check( false !== strpos( $message['subject'], 'Веб-разработка на WordPress' ), 'тема письма подставила {course_title}' );
check( false !== strpos( $message['body'], 'Иванов Иван Иванович' ), 'текст письма подставил {recipient_name}' );

$no_email = WPDC_Certificate::create( array( 'recipient' => 'Без Почты' ) );
$send     = WPDC_Email::send( $no_email );

check( is_wp_error( $send ) && 'wpdc_no_email' === $send->get_error_code(), 'без e-mail отправка отклонена' );

echo "\n";
if ( $failures > 0 ) {
	echo "Провалено проверок: {$failures}\n";
	exit( 1 );
}

echo "Все проверки пройдены.\n";
