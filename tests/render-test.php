<?php
/**
 * Тест рендерера сертификата (без WordPress).
 *
 * Запуск: php tests/render-test.php [/путь/сертификат.pdf]
 *
 * @package WP_Diplom
 */

define( 'WPD_PDF_STANDALONE', true );

$root = dirname( __DIR__ );

require_once $root . '/includes/class-wpd-renderer.php';

$output   = $argv[1] ?? sys_get_temp_dir() . '/wpd-certificate.pdf';
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

// --- Подстановка плейсхолдеров -------------------------------------------
$data = WPD_Renderer::normalize_data(
	array(
		'recipient_name' => 'Петрова Анна Сергеевна',
		'course_title'   => 'Веб-разработка на WordPress',
		'course_hours'   => '72',
		'teacher'        => 'Иванов И. И.',
		'date'           => '06.11.2026',
		'number'         => 'WPD-2026-0001',
		'organization'   => 'Красноярский техникум',
		'code'           => 'a1b2c3d4',
		'custom'         => array( 'group' => 'ВР-21' ),
	)
);

check(
	'Петрова Анна Сергеевна' === WPD_Renderer::replace_placeholders( '{recipient_name}', $data ),
	'плейсхолдер {recipient_name} подставлен'
);

check(
	'Веб-разработка на WordPress (72 ч.)' === WPD_Renderer::replace_placeholders( '{course_title} ({course_hours} ч.)', $data ),
	'несколько плейсхолдеров в одной строке'
);

check(
	'Группа: ВР-21' === WPD_Renderer::replace_placeholders( 'Группа: {custom_group}', $data ),
	'дополнительное поле из CSV ({custom_group})'
);

check(
	'Пусто: ' === WPD_Renderer::replace_placeholders( 'Пусто: {unknown_key}', $data ),
	'неизвестный плейсхолдер заменён пустой строкой'
);

// --- Нормализация конфигурации -------------------------------------------
$template = WPD_Renderer::normalize_template(
	array(
		'page'         => 'a4_landscape',
		'organization' => 'Красноярский техникум',
		'fields'       => WPD_Renderer::default_fields(),
	)
);

check( 9 === count( $template['fields'] ), 'бланк по умолчанию содержит 9 полей' );
check( true === $template['border']['enabled'], 'рамка включена по умолчанию' );
check( '#ffffff' === $template['background_color'], 'фон по умолчанию белый' );

$fallback = WPD_Renderer::normalize_template( array( 'page' => 'нечто-неизвестное' ) );
check( 'a4_landscape' === $fallback['page'], 'неизвестный размер страницы → A4 landscape' );

list( $w, $h ) = WPD_Renderer::page_size( 'a4_landscape' );
check( 297.0 === $w && 210.0 === $h, 'размер A4 landscape = 297×210 мм' );

// --- Цвета ---------------------------------------------------------------
check( '#2f6fed' === WPD_Renderer::sanitize_color( '#2f6fed' ), 'валидный HEX-цвет сохранён' );
check( '#000000' === WPD_Renderer::sanitize_color( 'javascript:alert(1)' ), 'невалидный цвет → чёрный' );
check( '#abc' === WPD_Renderer::sanitize_color( 'abc' ), 'короткая запись цвета принята' );

// --- Рендер PDF ----------------------------------------------------------
$renderer = new WPD_Renderer();

$pdf_binary = $renderer->render( $template, $data );

check( 0 === strpos( $pdf_binary, '%PDF-1.4' ), 'PDF сгенерирован из бланка по умолчанию' );
check( strlen( $pdf_binary ) > 500000, sprintf( 'размер PDF = %d байт', strlen( $pdf_binary ) ) );

file_put_contents( $output, $pdf_binary );
check( file_exists( $output ), 'файл записан: ' . $output );

// Титул из метаданных содержит имя получателя.
check(
	false !== strpos( $pdf_binary, '/Title' ),
	'метаданные документа записаны'
);

// Потоки сжаты, поэтому наличие текста проверяет внешний парсер
// (tests/validate-pdf.py), а здесь — только выбранная кодировка шрифта.
check(
	false !== strpos( $pdf_binary, '/Encoding /Identity-H' ),
	'текст кодируется через Identity-H (двухбайтовые глифы)'
);
check(
	false !== strpos( $pdf_binary, '/Filter /FlateDecode' ),
	'потоки сжаты FlateDecode'
);

// --- Кастомный бланк без рамки и с нестандартной страницей ----------------
$custom = WPD_Renderer::normalize_template(
	array(
		'page'             => 'a5_portrait',
		'background_color' => '#fdfaf3',
		'border'           => array( 'enabled' => false ),
		'fields'           => array(
			array(
				'text' => '{recipient_name}',
				'x'    => 14,
				'y'    => 60,
				'w'    => 120,
				'h'    => 20,
				'size' => 20,
				'font' => 'serif',
				'align' => 'center',
			),
		),
	)
);

$custom_binary = $renderer->render( $custom, $data );

check( 0 === strpos( $custom_binary, '%PDF-1.4' ), 'кастомный бланк A5 отрендерен' );

// A5 portrait = 148×210 мм → 419.5×595.3 pt.
check(
	false !== strpos( $custom_binary, '/MediaBox [0 0 419.528 595.276]' )
	|| (bool) preg_match( '/MediaBox \[0 0 419\.5\S* 595\.2\S*\]/', $custom_binary ),
	'размер страницы A5 portrait в PDF'
);

// --- Ошибка при отсутствии шрифтов ---------------------------------------
$broken = new WPD_Renderer( '/nonexistent-fonts-dir' );
$thrown = false;

try {
	$broken->render( $template, $data );
} catch ( RuntimeException $e ) {
	$thrown = true;
}

check( $thrown, 'без шрифтов рендер бросает RuntimeException' );

echo "\n";
if ( $failures > 0 ) {
	echo "Провалено проверок: {$failures}\n";
	exit( 1 );
}

echo "Все проверки пройдены.\n";
