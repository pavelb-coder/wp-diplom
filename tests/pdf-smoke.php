<?php
/**
 * Автономный тест PDF-движка (без установки WordPress).
 *
 * Запуск: php tests/pdf-smoke.php [/путь/для/вывода.pdf]
 *
 * Проверяет: кириллицу, перенос строк, выравнивание, векторные элементы,
 * встраивание PNG и JPEG, многостраничность.
 *
 * @package WP_Diplom
 */

define( 'WPD_PDF_STANDALONE', true );

$root = dirname( __DIR__ );

require_once $root . '/includes/pdf/class-wpd-pdf-writer.php';

$output = $argv[1] ?? sys_get_temp_dir() . '/wpd-smoke.pdf';
$fonts  = array(
	'sans'  => array(
		'regular' => $root . '/assets/fonts/DejaVuSans.ttf',
		'bold'    => $root . '/assets/fonts/DejaVuSans-Bold.ttf',
	),
	'serif' => array(
		'regular' => $root . '/assets/fonts/DejaVuSerif.ttf',
		'bold'    => $root . '/assets/fonts/DejaVuSerif.ttf',
	),
);

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

// --- Проверка парсера шрифта ---------------------------------------------
$font = WPD_PDF_Font::load( $fonts['sans']['regular'] );

check( null !== $font, 'шрифт DejaVuSans загружен' );
check( $font && $font->units_per_em === 2048, 'unitsPerEm = 2048' );
check( $font && $font->glyph( 0x41 ) > 0, 'латинская «A» имеет глиф' );
check( $font && $font->glyph( 0x410 ) > 0, 'кириллическая «А» (U+0410) имеет глиф' );
check( $font && $font->glyph( 0x451 ) > 0, 'кириллическая «ё» (U+0451) имеет глиф' );
check( $font && $font->glyph( 0x1F600 ) > 0, 'эмодзи U+1F600 есть в DejaVu (gid > 0)' );
check( $font && $font->glyph( 0x10FFFD ) === 0, 'отсутствующий символ U+10FFFD → gid 0' );

// Сверка с эталоном fontTools: A=36, А=933, ё=998, ₽=2961.
check(
	$font && 36 === $font->glyph( 0x41 ) && 933 === $font->glyph( 0x410 )
		&& 998 === $font->glyph( 0x451 ) && 2961 === $font->glyph( 0x20BD ),
	'глифы совпадают с эталоном fontTools (A/А/ё/₽)'
);

$width_a = $font ? $font->text_width( 'A', 10 ) : 0;
$width_w = $font ? $font->text_width( 'WWW', 10 ) : 0;

check( $width_a > 3 && $width_a < 12, sprintf( 'ширина «A» при 10pt = %.2f (разумно)', $width_a ) );
check( $width_w > $width_a * 2, 'ширина «WWW» больше ширины «A»' );

$russian = 'Сертификат соответствия';
$latin   = 'Certificate of completion';

check(
	$font && $font->text_width( $russian, 12 ) > 0,
	sprintf( 'ширина русской строки = %.1f pt', $font ? $font->text_width( $russian, 12 ) : 0 )
);

$map = array();
$hex = $font ? $font->encode( 'АБ', $map ) : '';

check( 8 === strlen( $hex ), 'кодирование 2 символов даёт 8 hex-символов (2 байта на глиф)' );
check( 2 === count( $map ), 'ToUnicode-карта собрала 2 глифа' );

// --- Тестовое изображение (PNG собирается вручную, без GD) ---------------
/**
 * Собирает PNG вручную (8 бит, без интерлейса).
 *
 * @param int   $width   Ширина.
 * @param int   $height  Высота.
 * @param array $pixel   Функция (x, y) → [r, g, b] или [r, g, b, a].
 * @param bool  $alpha   Писать RGBA (color type 6) вместо RGB.
 * @return string
 */
function wpd_make_png( $width, $height, $pixel, $alpha = false ) {
	$raw = '';

	for ( $y = 0; $y < $height; $y++ ) {
		$raw .= "\x00"; // Фильтр None.

		for ( $x = 0; $x < $width; $x++ ) {
			$rgba  = $pixel( $x, $y );
			$raw  .= chr( $rgba[0] ) . chr( $rgba[1] ) . chr( $rgba[2] );

			if ( $alpha ) {
				$raw .= chr( $rgba[3] ?? 255 );
			}
		}
	}

	$chunk = static function ( $type, $data ) {
		return pack( 'N', strlen( $data ) ) . $type . $data . pack( 'N', crc32( $type . $data ) );
	};

	$ihdr = pack( 'N', $width ) . pack( 'N', $height )
		. chr( 8 )                      // Бит на канал.
		. chr( $alpha ? 6 : 2 )         // Тип цвета.
		. "\x00\x00\x00";             // Сжатие, фильтр, интерлейс.

	return "\x89PNG\r\n\x1a\n"
		. $chunk( 'IHDR', $ihdr )
		. $chunk( 'IDAT', gzcompress( $raw, 6 ) )
		. $chunk( 'IEND', '' );
}

$png_path = sys_get_temp_dir() . '/wpd-test-bg.png';
$png_data = wpd_make_png(
	800,
	600,
	static function ( $x, $y ) {
		// Градиент + рамка: так видно, что пиксели не перепутаны.
		$border = ( $x < 4 || $y < 4 || $x > 795 || $y > 595 );

		return $border ? array( 47, 111, 237 ) : array( 246, (int) ( 200 + $y / 10 ), 251 );
	}
);

file_put_contents( $png_path, $png_data );

check( file_exists( $png_path ), 'тестовый PNG создан (' . strlen( $png_data ) . ' байт)' );

$image_info = WPD_PDF_Writer::read_image( $png_path );

check( is_array( $image_info ) && 800 === $image_info['width'] && 600 === $image_info['height'], 'PNG декодирован: 800x600' );
check( is_array( $image_info ) && 'raw' === $image_info['kind'], 'PNG отдан как сырые RGB-данные (без GD)' );
check( is_array( $image_info ) && strlen( $image_info['data'] ) === 800 * 600 * 3, 'объём RGB-данных = 800*600*3' );
check( is_array( $image_info ) && null === $image_info['smask'], 'в RGB-PNG нет альфа-канала' );

// Проверка первого пикселя (рамка синяя, #2f6fed).
if ( is_array( $image_info ) ) {
	$first = unpack( 'C3', substr( $image_info['data'], 0, 3 ) );
	check(
		array( 47, 111, 237 ) === array( $first[1], $first[2], $first[3] ),
		sprintf( 'первый пиксель = #%02x%02x%02x (ожидалось #2f6fed)', $first[1], $first[2], $first[3] )
	);
}

// PNG с прозрачностью → должен появиться /SMask.
$rgba_path = sys_get_temp_dir() . '/wpd-test-alpha.png';

file_put_contents(
	$rgba_path,
	wpd_make_png(
		40,
		40,
		static function ( $x ) {
			return array( 47, 111, 237, $x < 20 ? 255 : 0 );
		},
		true
	)
);

$rgba_info = WPD_PDF_Writer::read_image( $rgba_path );

check(
	is_array( $rgba_info ) && is_string( $rgba_info['smask'] ) && strlen( $rgba_info['smask'] ) === 1600,
	'RGBA-PNG: альфа-канал извлечён (40*40 байт)'
);

// --- Построение документа ------------------------------------------------
$mm = WPD_PDF_Writer::MM_TO_PT;

$pdf = new WPD_PDF_Writer( $fonts );

check( $pdf->has_fonts(), 'шрифты зарегистрированы в документе' );

$pdf->set_meta(
	array(
		'Title'   => 'Тестовый сертификат',
		'Author'  => 'WP Diplom',
		'Creator' => 'WPD PDF Writer',
	)
);

// Страница 1 — A4 landscape (297 x 210 мм).
$pdf->add_page( 297 * $mm, 210 * $mm );

$pdf->set_fill_color( '#ffffff' );
$pdf->rect( 0, 0, 297 * $mm, 210 * $mm, 'F' );

$pdf->image( $png_path, 10 * $mm, 10 * $mm, 277 * $mm, 190 * $mm, 'cover' );

$pdf->set_stroke_color( '#2f6fed' );
$pdf->set_line_width( 2 );
$pdf->rect( 15 * $mm, 15 * $mm, 267 * $mm, 180 * $mm, 'S' );

$pdf->text(
	'ПОЧЁТНАЯ ГРАМОТА',
	0,
	40 * $mm,
	297 * $mm,
	0,
	array(
		'family' => 'serif',
		'style'  => 'bold',
		'size'   => 40,
		'align'  => 'center',
		'color'  => '#141a2b',
	)
);

$pdf->text(
	"Награждается\nИванов Иван Иванович",
	40 * $mm,
	80 * $mm,
	217 * $mm,
	30 * $mm,
	array(
		'family' => 'sans',
		'style'  => 'bold',
		'size'   => 26,
		'align'  => 'center',
		'color'  => '#2f6fed',
	)
);

$long = 'За успешное освоение программы «Веб-разработка на WordPress», '
	. 'проявленное усердие и высокое качество итогового проекта. '
	. 'Данный текст проверяет автоматический перенос длинной строки по словам.';

$pdf->text(
	$long,
	40 * $mm,
	125 * $mm,
	217 * $mm,
	30 * $mm,
	array(
		'size'  => 13,
		'align' => 'center',
		'color' => '#5b6478',
	)
);

$pdf->text(
	'Certificate of completion · Test #1',
	40 * $mm,
	170 * $mm,
	217 * $mm,
	0,
	array(
		'size'  => 11,
		'align' => 'center',
		'color' => '#8a93a6',
	)
);

// Страница 2 — A5 portrait.
$pdf->add_page( 148 * $mm, 210 * $mm );

$pdf->set_fill_color( '#f6f7fb' );
$pdf->rect( 0, 0, 148 * $mm, 210 * $mm, 'F' );

$pdf->set_stroke_color( '#c9d2e8' );
$pdf->set_line_width( 0.5 );
$pdf->line( 20 * $mm, 100 * $mm, 128 * $mm, 100 * $mm );

$pdf->text(
	'Страница 2: проверка многостраничности',
	20 * $mm,
	40 * $mm,
	108 * $mm,
	0,
	array(
		'size'   => 14,
		'style'  => 'bold',
		'align'  => 'center',
		'color'  => '#141a2b',
	)
);

// Прозрачное изображение проверяем на второй странице.
$used_alpha = $pdf->image( $rgba_path, 20 * $mm, 120 * $mm, 30 * $mm, 30 * $mm, 'contain' );

check( $used_alpha, 'прозрачное PNG вставлено на страницу' );
check( 2 === $pdf->page_count(), 'документ содержит 2 страницы' );

$binary = $pdf->output();

check( 0 === strpos( $binary, '%PDF-1.4' ), 'заголовок PDF корректный' );
check( false !== strpos( $binary, '%%EOF' ), 'файл завершён %%EOF' );
check( false !== strpos( $binary, '/Subtype /CIDFontType2' ), 'шрифт встроен как CIDFontType2' );
check( false !== strpos( $binary, '/Encoding /Identity-H' ), 'используется кодировка Identity-H' );
check( false !== strpos( $binary, '/ToUnicode' ), 'есть карта ToUnicode' );
check( false !== strpos( $binary, '/XObject' ), 'изображение зарегистрировано как XObject' );
check( false !== strpos( $binary, '/SMask' ), 'прозрачность вынесена в /SMask' );
check( false !== strpos( $binary, '/FlateDecode' ), 'данные изображений сжаты Flate' );
check( false !== strpos( $binary, '/Type /Pages' ), 'есть дерево страниц' );
check( strlen( $binary ) > 500000, sprintf( 'размер файла %d байт (шрифт встроен целиком)', strlen( $binary ) ) );

// Корректность xref.
if ( preg_match( '/startxref\s+(\d+)/', $binary, $m ) ) {
	$offset = (int) $m[1];
	check( 'xref' === substr( $binary, $offset, 4 ), 'startxref указывает на таблицу xref' );
}

file_put_contents( $output, $binary );

check( file_exists( $output ), 'файл записан: ' . $output );

echo "\n";
if ( $failures > 0 ) {
	echo "Провалено проверок: {$failures}\n";
	exit( 1 );
}

echo "Все проверки пройдены.\n";
