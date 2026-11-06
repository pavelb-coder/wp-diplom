<?php
/**
 * Интеграционный тест логики плагина на заглушках WordPress.
 *
 * Проверяет: нумерацию, создание сертификата, метаданные, коды проверки,
 * кэширование PDF, разбор CSV и конфигурацию бланка.
 *
 * Запуск: php tests/integration-test.php
 *
 * @package WP_Diplom
 */

if ( ! defined( 'WPD_DIR' ) ) {
	define( 'WPD_DIR', dirname( __DIR__ ) . '/' );
}

require_once __DIR__ . '/wp-stubs.php';

require_once WPD_DIR . 'includes/class-wpd-renderer.php';
require_once WPD_DIR . 'includes/class-wpd-certificate.php';
require_once WPD_DIR . 'includes/class-wpd-admin.php';
require_once WPD_DIR . 'includes/class-wpd-email.php';

/**
 * Заглушка главного класса плагина: только настройки и каталог хранения.
 */
class WPD_Plugin {

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

		$saved = get_option( 'wpd_settings', array() );
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
		$dir = trailingslashit( $GLOBALS['wpd_test']['uploads'] ) . 'wpd-certificates';

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
		'post_type'   => 'wpd_template',
		'post_status' => 'publish',
		'post_title'  => 'Основной бланк',
	)
);

update_post_meta( $template_id, '_wpd_page', 'a4_landscape' );
update_post_meta( $template_id, '_wpd_border', array( 'enabled' => 1, 'color' => '#2f6fed', 'width' => 0.8, 'inset_x' => 12, 'inset_y' => 12 ) );
update_post_meta( $template_id, '_wpd_fields', WPD_Renderer::default_fields() );

$config = WPD_Certificate::template_config( $template_id );

check( 'a4_landscape' === $config['page'], 'template_config вернул формат страницы' );
check( 9 === count( $config['fields'] ), 'template_config вернул 9 полей' );
check( true === $config['border']['enabled'], 'рамка включена' );
check( 'Тестовый сайт' === $config['organization'], 'организация подставлена из настроек' );

$fallback = WPD_Certificate::template_config( 0 );
check( 9 === count( $fallback['fields'] ), 'без бланка используются поля по умолчанию' );

// --- Нумерация ----------------------------------------------------------
$year = current_time( 'Y' );

$first  = WPD_Certificate::next_number();
$second = WPD_Certificate::next_number();

check( 'WPD-' . $year . '-0001' === $first, 'первый номер: ' . $first );
check( 'WPD-' . $year . '-0002' === $second, 'номер инкрементируется: ' . $second );

// --- Создание сертификата ----------------------------------------------
$created = WPD_Certificate::create(
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

$data = WPD_Certificate::data( $created );

check( 'Иванов Иван Иванович' === $data['recipient_name'], 'имя получателя сохранено' );
check( 'ivan@example.com' === $data['recipient_email'], 'e-mail сохранён' );
check( '06.11.2026' === $data['date'], 'дата отформатирована по date_format: ' . $data['date'] );
check( 'ВР-21' === $data['custom']['group'], 'дополнительное поле сохранено' );
check( 'WPD-' . $year . '-0003' === $data['number'], 'номер присвоен автоматически: ' . $data['number'] );
check( 12 === strlen( $data['code'] ) && ctype_xdigit( $data['code'] ), 'код проверки — 12 hex-символов' );

$empty = WPD_Certificate::create( array( 'recipient' => '   ' ) );

check( is_wp_error( $empty ), 'пустое имя получателя → WP_Error' );

// --- Поиск по коду -----------------------------------------------------
$found = WPD_Certificate::by_code( strtoupper( $data['code'] ) );

check( $found && (int) $found->ID === $created, 'поиск по коду работает (регистр не важен)' );

$missing = WPD_Certificate::by_code( 'deadbeefcafe' );

check( null === $missing, 'несуществующий код → null' );

// --- Генерация PDF и кэш ----------------------------------------------
$pdf_one = WPD_Certificate::pdf( $created );

check( ! is_wp_error( $pdf_one ), 'PDF сгенерирован без ошибок' );
check( is_string( $pdf_one ) && file_exists( $pdf_one ), 'файл PDF существует: ' . basename( (string) $pdf_one ) );

if ( is_string( $pdf_one ) && file_exists( $pdf_one ) ) {
	$binary = (string) file_get_contents( $pdf_one );

	check( 0 === strpos( $binary, '%PDF-1.4' ), 'файл начинается с %PDF-1.4' );
	check( strlen( $binary ) > 300000, sprintf( 'размер PDF: %.1f КБ', strlen( $binary ) / 1024 ) );
	check( false !== strpos( $binary, '/ToUnicode' ), 'PDF содержит ToUnicode (кириллица извлекается)' );

	$first_mtime = filemtime( $pdf_one );
	sleep( 1 );

	$pdf_two = WPD_Certificate::pdf( $created );

	check( filemtime( $pdf_two ) === $first_mtime, 'повторный вызов берёт PDF из кэша' );

	WPD_Certificate::delete_pdf( $created );

	check( ! file_exists( $pdf_one ), 'delete_pdf удалил файл' );

	$pdf_three = WPD_Certificate::pdf( $created );

	check( ! is_wp_error( $pdf_three ) && file_exists( $pdf_three ), 'после сброса PDF пересобран' );
}

// --- CSV-парсер --------------------------------------------------------
$admin = new WPD_Admin();

$csv_semicolon = "name;email;course;hours;group\n"
	. "Иванов Иван;ivan@example.com;Веб-разработка;72;ВР-21\n"
	. "Петрова Анна;anna@example.com;UI/UX;48;ДЗ-12\n";

$rows = $admin->parse_csv( $csv_semicolon );

check( 2 === count( $rows ), 'CSV с «;» разобран: 2 строки' );
check( 'Иванов Иван' === $rows[0]['recipient'], 'колонка name → recipient' );
check( 'ivan@example.com' === $rows[0]['email'], 'колонка email распознана' );
check( 'ВР-21' === $rows[0]['custom']['group'], 'неизвестная колонка ушла в custom' );

$csv_comma = "фио,почта,программа,часы\nСидоров Пётр,sidorov@example.com,Python,36\n";

$rows_comma = $admin->parse_csv( $csv_comma );

check( 1 === count( $rows_comma ), 'CSV с «,» и русскими заголовками разобран' );
check( 'Сидоров Пётр' === $rows_comma[0]['recipient'], 'русский заголовок «фио» → recipient' );
check( '36' === $rows_comma[0]['hours'], 'колонка «часы» → hours' );

$csv_broken = "name;email\n;empty@example.com\nБез почты;\n";

$rows_broken = $admin->parse_csv( $csv_broken );

check( 1 === count( $rows_broken ), 'строки без имени отброшены' );
check( 'Без почты' === $rows_broken[0]['recipient'], 'строка без e-mail сохранена' );

check( array() === $admin->parse_csv( '' ), 'пустой CSV → пустой массив' );

// --- Письмо ------------------------------------------------------------
$message = WPD_Email::compose( $created );

check( false !== strpos( $message['subject'], 'Веб-разработка на WordPress' ), 'тема письма подставила {course_title}' );
check( false !== strpos( $message['body'], 'Иванов Иван Иванович' ), 'текст письма подставил {recipient_name}' );

$no_email = WPD_Certificate::create( array( 'recipient' => 'Без Почты' ) );
$send     = WPD_Email::send( $no_email );

check( is_wp_error( $send ) && 'wpd_no_email' === $send->get_error_code(), 'без e-mail отправка отклонена' );

echo "\n";
if ( $failures > 0 ) {
	echo "Провалено проверок: {$failures}\n";
	exit( 1 );
}

echo "Все проверки пройдены.\n";
