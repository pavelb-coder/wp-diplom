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
require_once WPDC_DIR . 'includes/class-wpdc-texts.php';
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
	 * Параметры бланка: значение из бланка, иначе общие настройки.
	 *
	 * @param int $template_id id бланка.
	 * @return array<string, mixed>
	 */
	public static function params( $template_id = 0 ) {
		$settings = self::settings();
		$saved    = $template_id ? get_post_meta( $template_id, '_wpdc_params', true ) : array();
		$saved    = is_array( $saved ) ? $saved : array();
		$params   = array();

		foreach ( array( 'organization', 'number_prefix', 'number_padding', 'enable_verify', 'verify_page' ) as $key ) {
			$value          = array_key_exists( $key, $saved ) ? $saved[ $key ] : null;
			$params[ $key ] = ( null !== $value && '' !== trim( (string) $value ) ) ? $value : ( $settings[ $key ] ?? '' );
		}

		return $params;
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
check( 1 === count( $config['fields'] ), 'template_config вернул поля бланка (по умолчанию одно — name)' );
check( true === $config['border']['enabled'], 'рамка включена' );
check( 'Тестовый сайт' === $config['organization'], 'организация подставлена из настроек' );

$fallback = WPDC_Certificate::template_config( 0 );
check( 1 === count( $fallback['fields'] ), 'без бланка используются поля по умолчанию' );

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
	check( strlen( $binary ) > 100000, sprintf( 'размер PDF: %.1f КБ', strlen( $binary ) / 1024 ) );
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

// --- Страница настроек удалена, параметры переехали в бланк ---------------
check( ! file_exists( WPDC_DIR . 'admin/views/page-settings.php' ), 'страница настроек удалена' );
check( false === strpos( $admin_source, 'wpdc-settings' ), 'пункта меню «Настройки» больше нет' );
check( false === strpos( $admin_source, 'admin_post_wpdc_save_settings' ), 'обработчик сохранения настроек удалён' );

foreach ( array( 'handle_settings', 'render_settings_page' ) as $method ) {
	check( ! method_exists( $admin, $method ), 'метода ' . $method . '() больше нет' );
}

$params_view = (string) file_get_contents( WPDC_DIR . 'admin/views/meta-template-params.php' );

foreach ( array( 'wpdc_params[organization]', 'wpdc_params[number_prefix]', 'wpdc_params[number_padding]', 'wpdc_params[enable_verify]', 'wpdc_params[verify_page]', 'wpdc_is_default' ) as $field ) {
	check( false !== strpos( $params_view, $field ), 'в параметрах бланка есть поле ' . $field );
}

// --- Поле бланка по умолчанию --------------------------------------------
$default_fields = WPDC_Renderer::default_fields();

check( 1 === count( $default_fields ), 'по умолчанию в бланке одно поле' );
check( 'name' === $default_fields[0]['id'], 'это поле name' );
check( '{recipient_name}' === $default_fields[0]['text'], 'поле печатает имя получателя' );

// --- Параметры бланка перекрывают общие ----------------------------------
update_post_meta( 99, '_wpdc_params', array( 'number_prefix' => 'ДИП', 'number_padding' => 3 ) );

$params = WPDC_Plugin::params( 99 );

check( 'ДИП' === $params['number_prefix'], 'префикс номера берётся из бланка' );
check( 3 === (int) $params['number_padding'], 'разрядность берётся из бланка' );
check( 'Тестовый сайт' === $params['organization'], 'пустой параметр берётся из общих настроек' );

// --- Преподаватель убран из плагина --------------------------------------
$cert_view = (string) file_get_contents( WPDC_DIR . 'admin/views/meta-certificate.php' );

check( false === strpos( $admin_source, 'wpdc_teacher' ), 'в админке нет поля wpdc_teacher' );
check( false === strpos( $admin_source, '_wpdc_teacher' ), 'мета _wpdc_teacher больше не пишется' );
check( false === strpos( $cert_view, "wpdc_teacher" ), 'в карточке сертификата нет поля «Преподаватель»' );
check( false === strpos( $params_view, 'sample_teacher' ), 'в параметрах бланка нет «Преподаватель в предпросмотре»' );
check( false === strpos( (string) file_get_contents( WPDC_DIR . 'includes/class-wpdc-plugin.php' ), 'sample_teacher' ), 'sample_teacher убран из PARAM_KEYS' );
check( false === strpos( (string) file_get_contents( WPDC_DIR . 'includes/class-wpdc-frontend.php' ), "'teacher'" ), 'на странице проверки нет строки «Преподаватель»' );

$placeholders = WPDC_Renderer::placeholders();

check( ! isset( $placeholders['{teacher}'] ), 'плейсхолдера {teacher} больше нет' );
check( '' === WPDC_Renderer::replace_placeholders( '{teacher}', WPDC_Renderer::normalize_data( array() ) ), 'старый текст с {teacher} печатается без имени' );

$cert_data = WPDC_Certificate::data( $created );

check( ! array_key_exists( 'teacher', $cert_data ), 'в данных сертификата нет teacher' );

$texts_defaults = WPDC_Texts::defaults();
$texts_presets  = WPDC_Texts::presets();

check( false === strpos( $texts_defaults['signature'], '{teacher}' ), 'слот «Подпись» не ссылается на преподавателя' );
check( false === strpos( implode( ' ', $texts_presets['signature'] ), '{teacher}' ), 'в готовых вариантах подписи нет {teacher}' );

// --- Сторонние шрифты бланка --------------------------------------------
$GLOBALS['wpdc_stub_attachments'] = array(
	501 => WPDC_DIR . 'assets/fonts/DejaVuSerif.ttf',
	502 => WPDC_DIR . 'assets/fonts/DejaVuSans-Bold.ttf',
);

check( true === isset( $admin->allow_font_mimes( array() )['ttf'] ), 'в медиатеку разрешено загружать .ttf' );

$raw_fonts = array(
	array( 'family' => 'gothic', 'label' => 'Готика', 'regular' => 501, 'bold' => 0 ),
	array( 'family' => '', 'label' => '', 'regular' => 0, 'bold' => 0 ),
	array( 'family' => 'My Font!', 'label' => 'Мой шрифт', 'regular' => 502, 'bold' => 501 ),
	'мусор',
);

$clean_fonts = WPDC_Admin::sanitize_fonts( $raw_fonts );

check( 2 === count( $clean_fonts ), 'пустые строки шрифтов отбрасываются' );
check( 'gothic' === $clean_fonts[0]['family'], 'ключ семейства сохраняется' );
check( 'myfont' === $clean_fonts[1]['family'], 'ключ семейства санитизируется (myfont)' );
check( 501 === $clean_fonts[1]['bold'], 'жирный файл сохраняется' );

$auto_fonts = WPDC_Admin::sanitize_fonts( array( array( 'family' => '', 'label' => '', 'regular' => 501 ) ) );

check( 'dejavuserif' === ( $auto_fonts[0]['family'] ?? '' ), 'пустой ключ берётся из имени файла (dejavuserif)' );

update_post_meta( $template_id, '_wpdc_fonts', $clean_fonts );

$families = WPDC_Admin::font_families( $template_id );

check( 'sans' === array_key_first( $families ), 'встроенная гарнитура sans остаётся первой' );
check( isset( $families['serif'] ), 'встроенная гарнитура serif на месте' );
check( 'Готика' === ( $families['gothic'] ?? '' ), 'подключённый шрифт попал в список гарнитур' );
check( 'Мой шрифт' === ( $families['myfont'] ?? '' ), 'название шрифта выводится как подпись' );

$font_config = WPDC_Certificate::template_config( $template_id );

check( isset( $font_config['fonts']['gothic'] ), 'шрифты бланка попали в конфигурацию' );
check( WPDC_DIR . 'assets/fonts/DejaVuSerif.ttf' === ( $font_config['fonts']['gothic']['regular'] ?? '' ), 'путь к обычному файлу разрешён' );

$font_fields = array(
	array( 'id' => 'name', 'text' => '{recipient_name}', 'x' => 20, 'y' => 20, 'w' => 200, 'h' => 20, 'size' => 24, 'font' => 'gothic' ),
);

$font_normalized = WPDC_Renderer::normalize_template( array( 'fields' => $font_fields, 'fonts' => $font_config['fonts'] ) );

check( 'gothic' === $font_normalized['fields'][0]['font'], 'поле бланка принимает подключённую гарнитуру' );

$fonts_view = (string) file_get_contents( WPDC_DIR . 'admin/views/meta-template-fonts.php' );
$fields_view = (string) file_get_contents( WPDC_DIR . 'admin/views/meta-template-fields.php' );
$admin_js    = (string) file_get_contents( WPDC_DIR . 'assets/js/admin.js' );

check( false !== strpos( $fonts_view, 'wpdc_fonts[' ), 'метабокс шрифтов отправляет wpdc_fonts[...]' );
check( false !== strpos( $fonts_view, 'wpdc-font-row' ), 'в метабоксе шрифтов есть строка шрифта' );
check( false !== strpos( $admin_source, 'wpdc_template_nonce_fonts' ), 'в метабоксе шрифтов есть nonce' );
check( false !== strpos( $fields_view, '$families' ), 'список гарнитур у поля берётся из бланка' );
check( false !== strpos( $admin_js, '.wpdc-font-pick' ), 'в админ-скрипте есть выбор файла шрифта' );
check( false !== strpos( $admin_js, '#wpdc-add-font' ), 'в админ-скрипте есть добавление шрифта' );

unset( $GLOBALS['wpdc_stub_attachments'] );

// --- Тексты сертификата: в настройках раздела больше нет ------------------
$views_source = '';

foreach ( (array) glob( WPDC_DIR . 'admin/views/*.php' ) as $view_file ) {
	$views_source .= (string) file_get_contents( $view_file );
}

check( false === strpos( $views_source, 'Тексты сертификата' ), 'экрана «Тексты сертификата» в админке нет' );
check( false === strpos( $views_source, 'id=\"wpdc-texts\"' ), 'якорь #wpdc-texts больше не используется' );

$texts_box = (string) file_get_contents( WPDC_DIR . 'admin/views/meta-template-texts.php' );

check( false !== strpos( $texts_box, 'wpdc_text[' ), 'метабокс «Тексты бланка» остался в редакторе бланка' );
check( false === strpos( $texts_box, 'settings_url' ), 'ссылки на удалённый раздел настроек нет' );

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
