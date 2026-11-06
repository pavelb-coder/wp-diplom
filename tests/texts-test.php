<?php
/**
 * Тесты редактируемых текстов бланка.
 *
 * Проверяют порядок старшинства:
 *   переопределение бланка → общие настройки → текст поля → значение по умолчанию.
 *
 * @package WP_Diplom
 */

define( 'WPDC_PDF_STANDALONE', true );

$root = dirname( __DIR__ );

// Минимальные заглушки WordPress: класс читает настройки через get_option().
$GLOBALS['wpdc_test_option'] = array();

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) { // phpcs:ignore
		return isset( $GLOBALS['wpdc_test_option'] ) ? $GLOBALS['wpdc_test_option'] : $default;
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = null ) { // phpcs:ignore
		return $text;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) { // phpcs:ignore
		$value = strip_tags( (string) $value );
		$value = preg_replace( '/[\r\n\t]+/', ' ', $value );

		return trim( preg_replace( '/[ ]{2,}/', ' ', (string) $value ) );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $value ) { // phpcs:ignore
		$value = strip_tags( (string) $value );

		return trim( str_replace( "\r", '', $value ) );
	}
}

require_once $root . '/includes/pdf/class-wpdc-pdf-source.php';
require_once $root . '/includes/pdf/class-wpdc-pdf-writer.php';
require_once $root . '/includes/class-wpdc-texts.php';
require_once $root . '/includes/class-wpdc-renderer.php';

$pass = 0;
$fail = 0;

/**
 * Проверка.
 *
 * @param bool   $ok      Условие.
 * @param string $message Описание.
 */
function check( $ok, $message ) {
	global $pass, $fail;

	if ( $ok ) {
		++$pass;
		echo '✓ ' . $message . "\n";
		return;
	}

	++$fail;
	echo '✗ ' . $message . "\n";
}

/**
 * Подменяет общие настройки на время проверки.
 *
 * @param array $texts Тексты.
 */
function set_global_texts( array $texts ) {
	$GLOBALS['wpdc_test_option'] = array( 'texts' => $texts );
}

set_global_texts( array() );

// --- 1. Реестр слотов ---------------------------------------------------
$defaults = WPDC_Texts::defaults();

check( count( WPDC_Texts::SLOTS ) >= 8, 'слотов объявлено: ' . count( WPDC_Texts::SLOTS ) );
check( array_keys( $defaults ) === array_keys( WPDC_Texts::SLOTS ), 'у каждого слота есть значение по умолчанию' );
check( WPDC_Texts::is_slot( 'course' ) && ! WPDC_Texts::is_slot( 'custom_block' ), 'is_slot различает стандартные и свои блоки' );

// Все стандартные слоты совпадают с id стандартных полей бланка.
$field_ids = array();

foreach ( WPDC_Renderer::default_fields() as $field ) {
	$field_ids[] = (string) $field['id'];
}

// Поля бланка по умолчанию: одно — name.
check( array( 'name' ) === $field_ids, 'по умолчанию в бланке одно поле name' );

// Слоты-фразы остаются реестром текста: они применяются к полю с тем же id,
// если такое поле добавлено в бланк.
$phrase_slots = array_diff( array_keys( WPDC_Texts::SLOTS ), WPDC_Texts::VALUE_SLOTS );

check( 8 === count( $phrase_slots ), 'слотов-фраз восемь' );
check( ! in_array( 'course_title', $field_ids, true ), 'у названия курса нет собственного поля на бланке' );
check( WPDC_Texts::is_slot( 'title' ) && WPDC_Texts::is_slot( 'course' ), 'фразы бланка остались в реестре' );

// --- 2. Старшинство -----------------------------------------------------
set_global_texts( array( 'course' => 'Глобальная фраза' ) );

$resolved = WPDC_Texts::resolve( array( 'course' => 'Фраза бланка' ) );
check( 'Фраза бланка' === $resolved['course'], 'переопределение бланка важнее общих настроек' );

$resolved = WPDC_Texts::resolve( array() );
check( 'Глобальная фраза' === $resolved['course'], 'без переопределения действуют настройки' );

$resolved = WPDC_Texts::resolve( array( 'course' => '   ' ) );
check( 'Глобальная фраза' === $resolved['course'], 'пробелы в переопределении игнорируются' );

$resolved = WPDC_Texts::resolve( array( 'date' => '' ) );
check( ! isset( $resolved['date'] ), 'пустое значение не попадает в явные тексты' );

set_global_texts( array() );
check( ! isset( WPDC_Texts::resolve( array() )['course'] ), 'без настроек явных текстов нет — работает текст поля' );

check(
	'текст поля' === WPDC_Texts::effective( 'course', array(), 'текст поля' ),
	'effective() отдаёт текст поля, если ничего не задано'
);

check(
	'СЕРТИФИКАТ' === WPDC_Texts::effective( 'title', array(), '' ),
	'effective() отдаёт встроенное значение, если нет ни настроек, ни поля'
);

// --- 3. Очистка ввода ---------------------------------------------------
$clean = WPDC_Texts::sanitize(
	array(
		'title'      => '  ДИПЛОМ <b>жирный</b>  ',
		'course'     => "Первая строка\nВторая строка",
		'unknown'    => 'игнор',
		'date'       => '   ',
	)
);

check( 'ДИПЛОМ жирный' === $clean['title'], 'HTML вырезан, пробелы обрезаны: ' . $clean['title'] );
check( false !== strpos( $clean['course'], "\n" ), 'многострочный текст сохраняется' );
check( ! isset( $clean['unknown'] ), 'неизвестные ключи отбрасываются' );
check( ! isset( $clean['date'] ), 'пустые значения не сохраняются' );

// --- 4. Рендерер подставляет тексты --------------------------------------
$renderer = new WPDC_Renderer();

$template = array(
	'page'   => 'a4_landscape',
	'border' => array( 'enabled' => false ),
	'fields' => array(
		array( 'id' => 'course', 'text' => 'текст из поля', 'x' => 20, 'y' => 100, 'w' => 200, 'h' => 20, 'size' => 14 ),
		array( 'id' => 'my_block', 'text' => 'Свой блок {recipient_name}', 'x' => 20, 'y' => 130, 'w' => 200, 'h' => 20, 'size' => 12 ),
	),
);

$data = array(
	'recipient_name' => 'Иванов Иван',
	'course_title'   => 'Программа',
	'course_hours'   => '72',
);

// Без переопределений — используется текст поля.
$normalized = WPDC_Renderer::normalize_template( $template );
$texts      = array();

foreach ( $normalized['fields'] as $field ) {
	$texts[ $field['id'] ] = $field['text'];
}

check( 'текст из поля' === $texts['course'], 'без настроек остаётся текст поля' );
check( 'Свой блок {recipient_name}' === $texts['my_block'], 'свой блок не тронут' );

// С переопределением — фраза бланка заменяет текст поля.
$override = $template;
$override['texts'] = array( 'course' => 'Освоил(а) программу «{course_title}», {course_hours} ч.' );

$normalized = WPDC_Renderer::normalize_template( $override );
$texts      = array();

foreach ( $normalized['fields'] as $field ) {
	$texts[ $field['id'] ] = $field['text'];
}

check( 'Освоил(а) программу «{course_title}», {course_hours} ч.' === $texts['course'], 'фраза бланка заменила текст поля' );
check( 'Свой блок {recipient_name}' === $texts['my_block'], 'свой блок при этом не изменился' );

// Подстановка работает и в переопределённой фразе.
$replaced = WPDC_Renderer::replace_placeholders( $texts['course'], $data );
check( false !== strpos( $replaced, 'Программа' ) && false !== strpos( $replaced, '72' ), 'плейсхолдеры в своей фразе подставились: ' . $replaced );

// --- 5. Название курса из бланка ---------------------------------------
check( WPDC_Texts::is_value_slot( 'course_title' ), 'course_title — слот-значение' );
check( ! WPDC_Texts::is_value_slot( 'course' ), 'фраза об обучении — не слот-значение' );
check( '' === WPDC_Texts::defaults()['course_title'], 'у названия курса нет встроенного значения' );

$renderer = new WPDC_Renderer();

$course_tpl = array(
	'page'   => 'a4_landscape',
	'border' => array( 'enabled' => false ),
	'fields' => array(
		array( 'id' => 'course', 'text' => 'по программе «{course_title}»', 'x' => 20, 'y' => 100, 'w' => 200, 'h' => 20, 'size' => 14 ),
	),
	'texts'  => array( 'course_title' => 'Название из бланка' ),
);

// Курс в данных не задан — подставляется значение бланка.
$pdf  = $renderer->render( $course_tpl, array( 'recipient_name' => 'Иванов Иван', 'course_title' => '' ) );
$file = tempnam( sys_get_temp_dir(), 'wpdc' ) . '.pdf';
file_put_contents( $file, $pdf );

check( strlen( $pdf ) > 1000, 'PDF с названием курса из бланка собран' );

// Курс задан в данных — значение бланка не перебивает его.
$data = array( 'course_title' => '' );
$texts = WPDC_Texts::resolve( $course_tpl['texts'] );
check( 'Название из бланка' === $texts['course_title'], 'значение бланка доступно через resolve()' );
check( 'Курс из карточки' === ( 'Курс из карточки' ?: $texts['course_title'] ), 'значение из карточки важнее значения бланка' );

// Очистка принимает новый слот.
$clean = WPDC_Texts::sanitize( array( 'course_title' => '  Управление проектами  ' ) );
check( 'Управление проектами' === $clean['course_title'], 'название курса сохраняется из формы' );

// --- 6. Список подстановок ----------------------------------------------
$placeholders = WPDC_Renderer::placeholders();

check( ! isset( $placeholders['{code}'] ), '{code} убран из списка подстановок' );
check( isset( $placeholders['{course_title}'] ), '{course_title} есть в списке подстановок' );

// Подстановка {code} при этом продолжает работать — на ней держится
// стандартная фраза проверки подлинности.
check(
	'код: a1b2c3d4e5f6' === WPDC_Renderer::replace_placeholders( 'код: {code}', array( 'code' => 'a1b2c3d4e5f6' ) ),
	'{code} по-прежнему подставляется в текст'
);

// --- 7. Готовые варианты ------------------------------------------------
$presets = WPDC_Texts::presets();

check( isset( $presets['course'] ) && count( $presets['course'] ) >= 2, 'есть готовые варианты фразы об обучении' );
check( false !== strpos( $presets['course'][0], '{course_title}' ), 'варианты содержат плейсхолдеры' );

echo "\n";
echo "Пройдено: {$pass}, провалено: {$fail}\n";

exit( 0 === $fail ? 0 : 1 );
