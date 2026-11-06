<?php
/**
 * Тесты PDF-шаблонов: разбор чужого PDF и печать полей поверх него.
 *
 * @package WP_Diplom
 */

define( 'WPDC_PDF_STANDALONE', true );

$root = dirname( __DIR__ );

require_once $root . '/includes/pdf/class-wpdc-pdf-source.php';
require_once $root . '/includes/pdf/class-wpdc-pdf-writer.php';

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

$tmp = sys_get_temp_dir() . '/wpdc-pdf-template';
@mkdir( $tmp, 0777, true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

/**
 * Собирает минимальный PDF с заданными страницами.
 *
 * @param array $pages Список: [ширина, высота, текст].
 * @param bool  $objstm Упаковать объекты в /ObjStm + xref-поток.
 * @param int   $rotate Поворот страницы.
 * @return string Путь к файлу.
 */
function build_pdf( array $pages, $objstm = false, $rotate = 0 ) {
	global $tmp;

	$objects = array();
	$kids    = array();
	$next    = 3;

	foreach ( $pages as $index => $page ) {
		$content_id  = $next++;
		$contents    = array();
		$contents[]  = "BT /F1 20 Tf 40 100 Td (Page " . ( $index + 1 ) . " " . $page[2] . ') Tj ET';
		$objects[ $content_id ] = array(
			'dict'   => '<</Length ' . strlen( $contents[0] ) . '>>',
			'stream' => $contents[0],
		);
		$page_id = $next++;
		$objects[ $page_id ]    = array(
			'dict'   => '<</Type/Page/Parent 2 0 R/MediaBox[0 0 ' . $page[0] . ' ' . $page[1] . ']'
				. ( $rotate ? '/Rotate ' . $rotate : '' )
				. '/Resources<</Font<</F1 9 0 R>>>>/Contents ' . $content_id . ' 0 R>>',
			'stream' => null,
		);
		$kids[] = $page_id;
	}

	$objects[1] = array( 'dict' => '<</Type/Catalog/Pages 2 0 R>>', 'stream' => null );
	$objects[2] = array( 'dict' => '<</Type/Pages/Count ' . count( $kids ) . '/Kids[' . implode( ' ', array_map( static function ( $id ) {
		return $id . ' 0 R';
	}, $kids ) ) . ']>>', 'stream' => null );
	$objects[9] = array( 'dict' => '<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>', 'stream' => null );

	ksort( $objects );

	$pdf = "%PDF-1.5\n%\xE2\xE3\xCF\xD3\n";

	foreach ( $objects as $number => $object ) {
		$pdf .= $number . " 0 obj\n" . $object['dict'] . "\n";

		if ( null !== $object['stream'] ) {
			$pdf .= "stream\n" . $object['stream'] . "\nendstream\n";
		}

		$pdf .= "endobj\n";
	}

	$path = $tmp . '/tpl-' . md5( serialize( $pages ) . $objstm . $rotate ) . '.pdf';
	file_put_contents( $path, $pdf );

	return $path;
}

// --- 1. Разбор PDF ------------------------------------------------------
$a4 = build_pdf( array( array( 842, 595, 'landscape' ) ) );
$src = new WPDC_PDF_Source( $a4 );

check( 1 === $src->page_count(), 'одна страница найдена' );

$size = $src->page_size( 0 );
check( abs( 842 - $size['width'] ) < 0.5 && abs( 595 - $size['height'] ) < 0.5, 'размер страницы 842×595 pt' );

$page = $src->export_page( 0 );
check( false !== strpos( $page['content'], 'Page 1' ), 'содержимое страницы прочитано' );
check( false !== strpos( $page['resources'], '/Font' ), 'ресурсы страницы прочитаны' );

// --- 2. Многостраничный PDF --------------------------------------------
$multi = build_pdf(
	array(
		array( 842, 595, 'first' ),
		array( 595, 842, 'second' ),
		array( 500, 300, 'third' ),
	)
);

$src = new WPDC_PDF_Source( $multi );
check( 3 === $src->page_count(), 'многостраничный PDF: 3 страницы' );

$third = $src->page_size( 2 );
check( abs( 500 - $third['width'] ) < 0.5, 'размер третьей страницы 500 pt' );
check( false !== strpos( $src->export_page( 1 )['content'], 'Page 2' ), 'вторая страница отличается от первой' );

// --- 3. Поворот страницы ------------------------------------------------
$rotated = build_pdf( array( array( 842, 595, 'rot' ) ), false, 90 );
$src     = new WPDC_PDF_Source( $rotated );
$size    = $src->page_size( 0 );

check( abs( 595 - $size['width'] ) < 0.5 && abs( 842 - $size['height'] ) < 0.5, '/Rotate 90 меняет местами ширину и высоту' );
check( 90 === $src->export_page( 0 )['rotate'], 'поворот передан в рендерер' );

// --- 4. Встраивание в наш PDF ------------------------------------------
$writer = new WPDC_PDF_Writer(
	array(
		'sans' => array(
			'regular' => $root . '/assets/fonts/DejaVuSans.ttf',
			'bold'    => $root . '/assets/fonts/DejaVuSans-Bold.ttf',
		),
	)
);

$writer->add_page( 842, 595 );
$inserted = $writer->pdf_page( $a4, 0, 0, 0, 842, 595, 'stretch' );

check( true === $inserted, 'страница PDF вставлена как форма' );

$writer->text(
	'Петров Пётр',
	40,
	300,
	400,
	20,
	array( 'family' => 'sans', 'style' => 'bold', 'size' => 18, 'color' => '#000000' )
);

$binary = $writer->output();

check( 0 === strpos( $binary, '%PDF' ), 'результат — корректный PDF' );
check( false !== strpos( $binary, '/Subtype /Form' ) || false !== strpos( $binary, '/Subtype/Form' ), 'форма присутствует в файле' );
check( false !== strpos( $binary, 'Page 1' ), 'содержимое шаблона попало в результат' );
check( false !== strpos( $binary, 'ToUnicode' ), 'свой текст поверх шаблона с кириллицей' );

// Графическое состояние изолируется: иначе фон страницы (белый) наследуется
// формой и текст шаблона рисуется белым по белому — так и было поймано.
$state_test = new WPDC_PDF_Writer();
$state_test->add_page( 842, 595 );
$state_test->set_fill_color( '#ffffff' );
$state_test->rect( 0, 0, 842, 595, 'F' );
$state_test->pdf_page( $a4, 0, 0, 0, 842, 595, 'stretch' );
// Поток страницы сжат, поэтому смотрим его через отражение, а не в бинарнике.
$reflection = new ReflectionClass( 'WPDC_PDF_Writer' );
$property   = $reflection->getProperty( 'pages' );
$property->setAccessible( true );
$pages = $property->getValue( $state_test );
$page_ops = isset( $pages[0]['content'] ) ? $pages[0]['content'] : '';

check(
	(bool) preg_match( '/q\n0 0 0 rg\n0 0 0 RG\n1 w\n/', $page_ops ),
	'перед выводом формы цвет сбрасывается на чёрный'
);

// Повторная вставка того же файла переиспользует форму, а не дублирует её.
$writer2 = new WPDC_PDF_Writer();
$writer2->add_page( 842, 595 );
$writer2->pdf_page( $a4, 0, 0, 0, 842, 595 );
$writer2->pdf_page( $a4, 0, 0, 0, 842, 595 );
$twice = $writer2->output();

check( 1 === substr_count( $twice, '/FormType 1' ), 'форма переиспользуется при повторной вставке' );

// --- 5. Ошибки ----------------------------------------------------------
$missing = $tmp . '/nope.pdf';

try {
	new WPDC_PDF_Source( $missing );
	check( false, 'отсутствующий файл отклонён' );
} catch ( RuntimeException $e ) {
	check( true, 'отсутствующий файл отклонён: ' . $e->getMessage() );
}

$txt = $tmp . '/fake.pdf';
file_put_contents( $txt, 'это не PDF' );

try {
	new WPDC_PDF_Source( $txt );
	check( false, 'не-PDF отклонён' );
} catch ( RuntimeException $e ) {
	check( true, 'не-PDF отклонён: ' . $e->getMessage() );
}

$encrypted = $tmp . '/enc.pdf';
file_put_contents(
	$encrypted,
	"%PDF-1.5\n1 0 obj\n<</Type/Catalog/Pages 2 0 R>>\nendobj\n2 0 obj\n<</Type/Pages/Count 0/Kids[]>>\nendobj\n"
	. "trailer\n<</Root 1 0 R/Encrypt 3 0 R>>\n%%EOF\n"
);

try {
	new WPDC_PDF_Source( $encrypted );
	check( false, 'зашифрованный PDF отклонён' );
} catch ( RuntimeException $e ) {
	check( true, 'зашифрованный PDF отклонён: ' . $e->getMessage() );
}

// --- 6. Новый формат страницы в рендерере -------------------------------
require_once $root . '/includes/class-wpdc-renderer.php';

$options = WPDC_Renderer::page_size_options();

check( isset( $options['pdf'] ), 'в списке форматов есть «по размеру PDF-шаблона»' );

$dims = WPDC_Renderer::page_size( 'pdf', array( 'pdf_width_mm' => 176.5, 'pdf_height_mm' => 105.8 ) );
check( abs( 176.5 - $dims[0] ) < 0.01 && abs( 105.8 - $dims[1] ) < 0.01, 'формат «pdf» берёт размеры из шаблона' );

$fallback = WPDC_Renderer::page_size( 'pdf', array() );
check( 297.0 === $fallback[0], 'без размеров шаблона — откат на A4' );

echo "\n";
echo "Пройдено: {$pass}, провалено: {$fail}\n";

exit( 0 === $fail ? 0 : 1 );
