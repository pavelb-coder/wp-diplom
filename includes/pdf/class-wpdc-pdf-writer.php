<?php
/**
 * Минималистичный генератор PDF без внешних зависимостей.
 *
 * Поддерживает: несколько страниц, встроенные TrueType-шрифты (кириллица),
 * векторные прямоугольники и линии, JPEG/PNG-изображения, перенос и
 * выравнивание текста. Координаты — в пунктах (pt), начало отсчёта в
 * верхнем левом углу страницы.
 *
 * @package WP_Diplom
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPDC_PDF_STANDALONE' ) ) {
	exit;
}

require_once __DIR__ . '/class-wpdc-pdf-font.php';
require_once __DIR__ . '/class-wpdc-pdf-source.php';

/**
 * Документ PDF.
 */
class WPDC_PDF_Writer {

	/**
	 * Соотношение миллиметров и пунктов.
	 */
	const MM_TO_PT = 72 / 25.4;

	/**
	 * Страницы: ['width' => float, 'height' => float, 'content' => string].
	 *
	 * @var array<int, array>
	 */
	private $pages = array();

	/**
	 * Объекты документа (индекс 0 = объект 1).
	 *
	 * @var string[]
	 */
	private $objects = array();

	/**
	 * Шрифты: 'family-style' => WPDC_PDF_Font.
	 *
	 * @var array<string, WPDC_PDF_Font>
	 */
	private $fonts = array();

	/**
	 * id объекта Type0: 'family-style' => int.
	 *
	 * @var array<string, int>
	 */
	private $font_refs = array();

	/**
	 * Имя ресурса шрифта: 'family-style' => 'F1'.
	 *
	 * @var array<string, string>
	 */
	private $font_names = array();

	/**
	 * Карты ToUnicode: id Type0 => [gid => codepoint].
	 *
	 * @var array<int, array<int, int>>
	 */
	private $font_maps = array();

	/**
	 * Куда писать ToUnicode: id Type0 => id объекта потока.
	 *
	 * @var array<int, int>
	 */
	private $font_tounicode = array();

	/**
	 * Изображения: ключ => ['id' => int, 'name' => 'Im1', 'width' => px, 'height' => px].
	 *
	 * @var array<string, array>
	 */
	private $images = array();

	/**
	 * Встроенные страницы PDF: ключ => ['id' => int, 'name' => 'Fm1'].
	 *
	 * @var array<string, array>
	 */
	private $forms = array();

	/**
	 * Текущий цвет заливки (RGB 0..1).
	 *
	 * @var float[]
	 */
	private $fill = array( 0.0, 0.0, 0.0 );

	/**
	 * Текущий цвет обводки.
	 *
	 * @var float[]
	 */
	private $stroke = array( 0.0, 0.0, 0.0 );

	/**
	 * Толщина линии в pt.
	 *
	 * @var float
	 */
	private $line_width = 1.0;

	/**
	 * Индекс текущей страницы.
	 *
	 * @var int
	 */
	private $current = -1;

	/**
	 * Метаданные.
	 *
	 * @var array<string, string>
	 */
	private $meta = array();

	/**
	 * Конструктор.
	 *
	 * @param array $fonts Семейства: ['sans' => ['regular' => '/path', 'bold' => '/path']].
	 */
	public function __construct( array $fonts = array() ) {
		foreach ( $fonts as $family => $styles ) {
			foreach ( (array) $styles as $style => $path ) {
				$font = WPDC_PDF_Font::load( $path, $family . '-' . $style );

				if ( $font ) {
					$this->fonts[ $family . '-' . $style ] = $font;
				}
			}
		}
	}

	/**
	 * Метаданные документа.
	 *
	 * @param array<string, string> $meta Title/Author/Subject/Creator.
	 */
	public function set_meta( array $meta ) {
		$this->meta = $meta;
	}

	/**
	 * Есть ли загруженные шрифты.
	 *
	 * @return bool
	 */
	public function has_fonts() {
		return ! empty( $this->fonts );
	}

	/**
	 * Добавляет страницу.
	 *
	 * @param float $width  Ширина в pt.
	 * @param float $height Высота в pt.
	 */
	public function add_page( $width, $height ) {
		$this->pages[] = array(
			'width'   => (float) $width,
			'height'  => (float) $height,
			'content' => '',
		);

		$this->current = count( $this->pages ) - 1;
	}

	/**
	 * Количество страниц.
	 *
	 * @return int
	 */
	public function page_count() {
		return count( $this->pages );
	}

	/**
	 * Размеры текущей страницы.
	 *
	 * @return array{width: float, height: float}
	 */
	public function page_size() {
		if ( $this->current < 0 ) {
			return array(
				'width'  => 0.0,
				'height' => 0.0,
			);
		}

		return array(
			'width'  => $this->pages[ $this->current ]['width'],
			'height' => $this->pages[ $this->current ]['height'],
		);
	}

	/**
	 * Цвет заливки.
	 *
	 * @param string $hex Цвет #rrggbb.
	 */
	public function set_fill_color( $hex ) {
		$this->fill = self::hex_to_rgb( $hex );
	}

	/**
	 * Цвет обводки.
	 *
	 * @param string $hex Цвет #rrggbb.
	 */
	public function set_stroke_color( $hex ) {
		$this->stroke = self::hex_to_rgb( $hex );
	}

	/**
	 * Толщина линии.
	 *
	 * @param float $width Толщина в pt.
	 */
	public function set_line_width( $width ) {
		$this->line_width = max( 0.1, (float) $width );
	}

	/**
	 * Прямоугольник.
	 *
	 * @param float  $x     X от левого края (pt).
	 * @param float  $y     Y от верхнего края (pt).
	 * @param float  $w     Ширина.
	 * @param float  $h     Высота.
	 * @param string $style 'F', 'S' или 'FD'.
	 */
	public function rect( $x, $y, $w, $h, $style = 'F' ) {
		if ( $this->current < 0 ) {
			return;
		}

		$page_height = $this->pages[ $this->current ]['height'];

		$paint = 'f';

		if ( false !== strpos( $style, 'S' ) ) {
			$paint = false !== strpos( $style, 'F' ) ? 'B' : 'S';
		}

		$ops = $this->paint_ops( $style ) . sprintf(
			"\n%s %s %s %s re %s\n",
			self::num( $x ),
			self::num( $page_height - $y - $h ),
			self::num( $w ),
			self::num( $h ),
			$paint
		);

		$this->append( $ops );
	}

	/**
	 * Линия.
	 *
	 * @param float $x1 Начало X.
	 * @param float $y1 Начало Y.
	 * @param float $x2 Конец X.
	 * @param float $y2 Конец Y.
	 */
	public function line( $x1, $y1, $x2, $y2 ) {
		if ( $this->current < 0 ) {
			return;
		}

		$page_height = $this->pages[ $this->current ]['height'];

		$this->append(
			sprintf(
				"%s\n%s w\n%s %s m %s %s l S\n",
				implode( ' ', array_map( array( __CLASS__, 'num' ), $this->stroke ) ) . ' RG',
				self::num( $this->line_width ),
				self::num( $x1 ),
				self::num( $page_height - $y1 ),
				self::num( $x2 ),
				self::num( $page_height - $y2 )
			)
		);
	}

	/**
	 * Выводит текст в прямоугольную область.
	 *
	 * @param string $text   Текст (UTF-8, поддерживает переводы строк).
	 * @param float  $x      X области.
	 * @param float  $y      Y области от верхнего края.
	 * @param float  $width  Ширина области (для переноса и выравнивания).
	 * @param float  $height Высота области (0 — без вертикального выравнивания).
	 * @param array  $opts   family, style, size, color, align, line_height, wrap, valign.
	 * @return float Высота выведенного текста.
	 */
	public function text( $text, $x, $y, $width, $height = 0, array $opts = array() ) {
		$text = (string) $text;

		if ( $this->current < 0 || '' === trim( $text ) ) {
			return 0.0;
		}

		$opts = array_merge(
			array(
				'family'      => 'sans',
				'style'       => 'regular',
				'size'        => 12,
				'color'       => '#000000',
				'align'       => 'left',
				'line_height' => 1.25,
				'wrap'        => true,
				'valign'      => 'middle',
			),
			$opts
		);

		$font = $this->font( $opts['family'], $opts['style'] );

		if ( ! $font ) {
			return 0.0;
		}

		$resource = $this->font_resource( $opts['family'], $opts['style'] );
		$size     = (float) $opts['size'];

		if ( $size <= 0 ) {
			return 0.0;
		}

		$line_height = $size * (float) $opts['line_height'];
		$lines       = $opts['wrap']
			? $this->wrap_text( $text, $font, $size, (float) $width )
			: explode( "\n", $text );

		$total = count( $lines ) * $line_height;
		$top   = (float) $y;

		if ( $height > 0 ) {
			if ( 'middle' === $opts['valign'] ) {
				$top = $y + ( $height - $total ) / 2;
			} elseif ( 'bottom' === $opts['valign'] ) {
				$top = $y + $height - $total;
			}
		}

		$page_height = $this->pages[ $this->current ]['height'];
		$color       = implode( ' ', array_map( array( __CLASS__, 'num' ), self::hex_to_rgb( $opts['color'] ) ) ) . ' rg';
		$chunk       = '';

		foreach ( $lines as $index => $line ) {
			$line_width = $font->text_width( $line, $size );
			$line_x     = (float) $x;

			if ( 'center' === $opts['align'] ) {
				$line_x = $x + ( $width - $line_width ) / 2;
			} elseif ( 'right' === $opts['align'] ) {
				$line_x = $x + $width - $line_width;
			}

			// Базовая линия: 0.8 от кегля даёт оптический центр для DejaVu.
			$baseline = $top + $size * 0.8 + $index * $line_height;
			$hex      = $font->encode( $line, $this->font_maps[ $resource['id'] ] );

			$chunk .= sprintf(
				"BT\n%s\n/%s %s Tf\n1 0 0 1 %s %s Tm\n<%s> Tj\nET\n",
				$color,
				$resource['name'],
				self::num( $size ),
				self::num( $line_x ),
				self::num( $page_height - $baseline ),
				$hex
			);
		}

		$this->append( $chunk );

		return $total;
	}

	/**
	 * Выводит изображение с обрезкой по области.
	 *
	 * @param string $path Путь к JPEG/PNG.
	 * @param float  $x    X области.
	 * @param float  $y    Y области.
	 * @param float  $w    Ширина области.
	 * @param float  $h    Высота области.
	 * @param string $fit  'stretch', 'contain' или 'cover'.
	 * @return bool
	 */
	public function image( $path, $x, $y, $w, $h, $fit = 'cover' ) {
		if ( $this->current < 0 ) {
			return false;
		}

		$image = $this->image_resource( $path );

		if ( ! $image ) {
			return false;
		}

		$box         = self::fit_box( $image['width'], $image['height'], $w, $h, $fit );
		$page_height = $this->pages[ $this->current ]['height'];

		$this->append(
			sprintf(
				"q\n%s %s %s %s re W n\n%s 0 0 %s %s %s cm\n/%s Do\nQ\n",
				self::num( $x ),
				self::num( $page_height - $y - $h ),
				self::num( $w ),
				self::num( $h ),
				self::num( $box['width'] ),
				self::num( $box['height'] ),
				self::num( $x + $box['x'] ),
				self::num( $page_height - $y - $box['y'] - $box['height'] ),
				$image['name']
			)
		);

		return true;
	}

	/**
	 * Кладёт страницу другого PDF как подложку (Form XObject).
	 *
	 * Страница переносится в наш документ как форма: содержимое и все ресурсы
	 * (шрифты, изображения, вложенные формы) переезжают вместе с ней, ссылки
	 * перенумеровываются. Растеризации нет — вектор остаётся вектором.
	 *
	 * @param string $path  Путь к исходному PDF.
	 * @param int    $page  Номер страницы с нуля.
	 * @param float  $x     Левый край области в pt (от левого края страницы).
	 * @param float  $y     Верхний край области в pt (от верхнего края).
	 * @param float  $w     Ширина области в pt.
	 * @param float  $h     Высота области в pt.
	 * @param string $fit   cover|contain|stretch.
	 * @return bool true, если подложка вставлена.
	 * @throws RuntimeException Если PDF не читается.
	 */
	public function pdf_page( $path, $page, $x, $y, $w, $h, $fit = 'cover' ) {
		if ( $this->current < 0 ) {
			return false;
		}

		$form = $this->form_resource( $path, (int) $page );

		if ( ! $form ) {
			return false;
		}

		$box = $form['box'];
		$key = array( 0.0, 0.0, 1.0, 1.0 );

		// Матрица поворота /Rotate.
		$rotate = ( (int) $form['rotate'] % 360 + 360 ) % 360;

		switch ( $rotate ) {
			case 90:
				$key = array( 0.0, -1.0, 1.0, 0.0 );
				break;
			case 180:
				$key = array( -1.0, 0.0, 0.0, -1.0 );
				break;
			case 270:
				$key = array( 0.0, 1.0, -1.0, 0.0 );
				break;
			default:
				$key = array( 1.0, 0.0, 0.0, 1.0 );
		}

		// Габариты содержимого после поворота.
		$corners = array(
			array( $box[0], $box[1] ),
			array( $box[2], $box[1] ),
			array( $box[2], $box[3] ),
			array( $box[0], $box[3] ),
		);

		$min_x = INF;
		$min_y = INF;
		$max_x = -INF;
		$max_y = -INF;

		foreach ( $corners as $corner ) {
			$tx = $key[0] * $corner[0] + $key[2] * $corner[1];
			$ty = $key[1] * $corner[0] + $key[3] * $corner[1];

			$min_x = min( $min_x, $tx );
			$max_x = max( $max_x, $tx );
			$min_y = min( $min_y, $ty );
			$max_y = max( $max_y, $ty );
		}

		$source_w = max( 0.0001, $max_x - $min_x );
		$source_h = max( 0.0001, $max_y - $min_y );

		if ( 'stretch' === $fit ) {
			$scale_x = $w / $source_w;
			$scale_y = $h / $source_h;
		} else {
			$scale   = 'contain' === $fit ? min( $w / $source_w, $h / $source_h ) : max( $w / $source_w, $h / $source_h );
			$scale_x = $scale;
			$scale_y = $scale;
		}

		$drawn_w = $source_w * $scale_x;
		$drawn_h = $source_h * $scale_y;

		// Центрируем внутри области (для cover масштаб может выйти за края — обрежем).
		$offset_x = $x + ( $w - $drawn_w ) / 2;
		$offset_y = $y + ( $h - $drawn_h ) / 2;

		$page_height = $this->pages[ $this->current ]['height'];

		$dest_x = $offset_x - $scale_x * $min_x;
		$dest_y = ( $page_height - $offset_y - $drawn_h ) - $scale_y * $min_y;

		$a = $scale_x * $key[0];
		$b = $scale_y * $key[1];
		$c = $scale_x * $key[2];
		$d = $scale_y * $key[3];

		// Форма наследует текущее графическое состояние вызывающей страницы.
		// Без сброса цвета текст шаблона оказался бы закрашен цветом фона
		// (например, белым на белом) — возвращаем значения по умолчанию.
		$this->append(
			sprintf(
				"q\n0 0 0 rg\n0 0 0 RG\n1 w\n%s %s %s %s %s %s cm\n/%s Do\nQ\n",
				self::num( $a ),
				self::num( $b ),
				self::num( $c ),
				self::num( $d ),
				self::num( $dest_x ),
				self::num( $dest_y ),
				$form['name']
			)
		);

		return true;
	}

	/**
	 * Регистрирует страницу PDF как форму.
	 *
	 * @param string $path Путь к PDF.
	 * @param int    $page Номер страницы.
	 * @return array|null
	 */
	private function form_resource( $path, $page ) {
		if ( ! is_readable( $path ) ) {
			return null;
		}

		$key = md5( $path . '|' . (string) @filemtime( $path ) . '|' . $page ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if ( isset( $this->forms[ $key ] ) ) {
			return $this->forms[ $key ];
		}

		$source = new WPDC_PDF_Source( $path );
		$export = $source->export_page( $page );

		$objects = $this->import_objects( $source, $export['resources'] );
		$content = $export['content'];

		$box = $export['box'];

		$dict = sprintf(
			'<< /Type /XObject /Subtype /Form /FormType 1 /BBox [%s %s %s %s] /Resources %s /Length %d >>',
			self::num( $box[0] ),
			self::num( $box[1] ),
			self::num( $box[2] ),
			self::num( $box[3] ),
			$objects['resources'],
			strlen( $content )
		);

		$id = $this->add_object( $dict . "\nstream\n" . $content . "\nendstream" );

		$this->forms[ $key ] = array(
			'id'    => $id,
			'name'  => 'Fm' . ( count( $this->forms ) + 1 ),
			'box'   => $box,
			'rotate' => $export['rotate'],
		);

		return $this->forms[ $key ];
	}

	/**
	 * Переносит граф объектов исходного PDF в наш документ.
	 *
	 * @param WPDC_PDF_Source $source    Исходный документ.
	 * @param string          $resources Словарь ресурсов страницы.
	 * @return array{resources: string, map: array<int, int>}
	 */
	private function import_objects( $source, $resources ) {
		$queue   = WPDC_PDF_Source::references( $resources );
		$ordered = array();
		$seen    = array();

		while ( $queue ) {
			$number = (int) array_shift( $queue );

			if ( isset( $seen[ $number ] ) ) {
				continue;
			}

			$seen[ $number ] = true;

			$object = $source->object( $number );

			if ( null === $object ) {
				continue;
			}

			$ordered[ $number ] = $object;

			foreach ( WPDC_PDF_Source::references( $object['dict'] ) as $child ) {
				if ( ! isset( $seen[ $child ] ) ) {
					$queue[] = $child;
				}
			}
		}

		// Перенумерация: старый номер → новый id в нашем файле.
		$map   = array();
		$start = count( $this->objects ) + 1;

		foreach ( array_keys( $ordered ) as $index => $number ) {
			$map[ $number ] = $start + $index;
		}

		foreach ( $ordered as $number => $object ) {
			$dict = preg_replace_callback(
				'/(\d+)\s+\d+\s+R\b/',
				static function ( $match ) use ( $map ) {
					$old = (int) $match[1];

					return isset( $map[ $old ] ) ? $map[ $old ] . ' 0 R' : $match[0];
				},
				$object['dict']
			);

			if ( null === $object['stream'] ) {
				$this->add_object( $dict );
				continue;
			}

			$data = $object['stream'];

			// /Length пересчитываем: длина уже распакованного потока могла измениться.
			$dict = preg_replace( '/\/Length\s+(\d+)(\s+\d+\s+R)?/', '/Length ' . strlen( $data ), $dict );

			$this->add_object( $dict . "\nstream\n" . $data . "\nendstream" );
		}

		$resources = preg_replace_callback(
			'/(\d+)\s+\d+\s+R\b/',
			static function ( $match ) use ( $map ) {
				$old = (int) $match[1];

				return isset( $map[ $old ] ) ? $map[ $old ] . ' 0 R' : $match[0];
			},
			$resources
		);

		return array(
			'resources' => $resources,
			'map'       => $map,
		);
	}

	/**
	 * Вписывание изображения в область.
	 *
	 * @param float  $iw  Ширина изображения.
	 * @param float  $ih  Высота изображения.
	 * @param float  $w   Ширина области.
	 * @param float  $h   Высота области.
	 * @param string $fit Режим.
	 * @return array{x: float, y: float, width: float, height: float}
	 */
	public static function fit_box( $iw, $ih, $w, $h, $fit = 'cover' ) {
		if ( $iw <= 0 || $ih <= 0 ) {
			return array(
				'x'      => 0,
				'y'      => 0,
				'width'  => $w,
				'height' => $h,
			);
		}

		if ( 'stretch' === $fit ) {
			return array(
				'x'      => 0,
				'y'      => 0,
				'width'  => $w,
				'height' => $h,
			);
		}

		$scale = 'contain' === $fit
			? min( $w / $iw, $h / $ih )
			: max( $w / $iw, $h / $ih );

		$draw_w = $iw * $scale;
		$draw_h = $ih * $scale;

		return array(
			'x'      => ( $w - $draw_w ) / 2,
			'y'      => ( $h - $draw_h ) / 2,
			'width'  => $draw_w,
			'height' => $draw_h,
		);
	}

	/**
	 * Готовый PDF.
	 *
	 * @return string
	 */
	public function output() {
		if ( empty( $this->pages ) ) {
			$this->add_page( 595.28, 841.89 );
		}

		$content_ids = array();

		foreach ( $this->pages as $index => $page ) {
			$content_ids[ $index ] = $this->add_object( $this->stream( $page['content'] ) );
		}

		$resources = $this->resources_dict();
		$page_ids  = array();

		foreach ( $this->pages as $index => $page ) {
			$page_ids[] = $this->add_object(
				sprintf(
					'<< /Type /Page /Parent %d 0 R /MediaBox [0 0 %s %s] /Resources %s /Contents %d 0 R >>',
					0, // Заполняется ниже.
					self::num( $page['width'] ),
					self::num( $page['height'] ),
					$resources,
					$content_ids[ $index ]
				)
			);
		}

		$pages_id = $this->add_object(
			sprintf(
				'<< /Type /Pages /Count %d /Kids [%s] >>',
				count( $page_ids ),
				implode( ' ', array_map( static function ( $id ) {
					return $id . ' 0 R';
				}, $page_ids ) )
			)
		);

		foreach ( $page_ids as $id ) {
			$this->objects[ $id - 1 ] = str_replace( '/Parent 0 0 R', '/Parent ' . $pages_id . ' 0 R', $this->objects[ $id - 1 ] );
		}

		// Актуальные ToUnicode (карты наполняются при выводе текста).
		foreach ( $this->font_tounicode as $type0_id => $stream_id ) {
			$map = $this->font_maps[ $type0_id ] ?? array();

			$this->objects[ $stream_id - 1 ] = $this->stream(
				$this->fonts_by_id[ $type0_id ]->to_unicode_cmap( $map )
			);
		}

		$catalog_id = $this->add_object( sprintf( '<< /Type /Catalog /Pages %d 0 R >>', $pages_id ) );
		$info_id    = 0;

		if ( $this->meta ) {
			$parts = array();

			foreach ( array( 'Title', 'Author', 'Subject', 'Creator' ) as $key ) {
				if ( ! empty( $this->meta[ $key ] ) ) {
					$parts[] = '/' . $key . ' ' . $this->text_string( $this->meta[ $key ] );
				}
			}

			if ( $parts ) {
				$info_id = $this->add_object( '<< ' . implode( ' ', $parts ) . ' >>' );
			}
		}

		return $this->build_file( $catalog_id, $info_id );
	}

	/**
	 * Сохраняет PDF в файл.
	 *
	 * @param string $path Путь.
	 * @return bool
	 */
	public function save( $path ) {
		return false !== file_put_contents( $path, $this->output() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}

	/**
	 * Шрифт по семейству и стилю с откатом на regular.
	 *
	 * @param string $family Семейство.
	 * @param string $style  Стиль.
	 * @return WPDC_PDF_Font|null
	 */
	private function font( $family, $style ) {
		$key = $family . '-' . $style;

		if ( isset( $this->fonts[ $key ] ) ) {
			return $this->fonts[ $key ];
		}

		if ( isset( $this->fonts[ $family . '-regular' ] ) ) {
			return $this->fonts[ $family . '-regular' ];
		}

		foreach ( $this->fonts as $font ) {
			return $font;
		}

		return null;
	}

	/**
	 * Регистрирует шрифт и возвращает имя ресурса.
	 *
	 * @param string $family Семейство.
	 * @param string $style  Стиль.
	 * @return array{name: string, id: int}
	 */
	private function font_resource( $family, $style ) {
		$key = $family . '-' . $style;

		if ( isset( $this->font_refs[ $key ] ) ) {
			return array(
				'name' => $this->font_names[ $key ],
				'id'   => $this->font_refs[ $key ],
			);
		}

		$font = $this->font( $family, $style );
		$id   = $this->register_font( $font );

		$this->font_refs[ $key ]  = $id;
		$this->font_names[ $key ] = 'F' . count( $this->font_refs );
		$this->font_maps[ $id ]   = array();

		return array(
			'name' => $this->font_names[ $key ],
			'id'   => $id,
		);
	}

	/**
	 * Шрифты по id (для записи ToUnicode).
	 *
	 * @var array<int, WPDC_PDF_Font>
	 */
	private $fonts_by_id = array();

	/**
	 * Создаёт объекты встроенного шрифта.
	 *
	 * @param WPDC_PDF_Font $font Шрифт.
	 * @return int id объекта Type0.
	 */
	private function register_font( $font ) {
		$scale = 1000 / $font->units_per_em;

		$file_id = $this->add_object(
			$this->stream( $font->raw(), '/Length1 ' . strlen( $font->raw() ) )
		);

		$descriptor_id = $this->add_object(
			sprintf(
				'<< /Type /FontDescriptor /FontName /%s /Flags 4 /FontBBox [%s %s %s %s] /ItalicAngle 0 /Ascent %s /Descent %s /CapHeight %s /StemV 80 /FontFile2 %d 0 R >>',
				$font->name,
				self::num( $font->bbox[0] * $scale ),
				self::num( $font->bbox[1] * $scale ),
				self::num( $font->bbox[2] * $scale ),
				self::num( $font->bbox[3] * $scale ),
				self::num( $font->ascender * $scale ),
				self::num( $font->descender * $scale ),
				self::num( $font->ascender * 0.7 * $scale ),
				$file_id
			)
		);

		$descendant_id = $this->add_object(
			sprintf(
				'<< /Type /Font /Subtype /CIDFontType2 /BaseFont /%s /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /FontDescriptor %d 0 R /CIDToGIDMap /Identity /DW 1000 /W [%s] >>',
				$font->name,
				$descriptor_id,
				$font->widths_array()
			)
		);

		$tounicode_id = $this->add_object( $this->stream( ' ' ) );

		$type0_id = $this->add_object(
			sprintf(
				'<< /Type /Font /Subtype /Type0 /BaseFont /%s /Encoding /Identity-H /DescendantFonts [%d 0 R] /ToUnicode %d 0 R >>',
				$font->name,
				$descendant_id,
				$tounicode_id
			)
		);

		$this->font_tounicode[ $type0_id ] = $tounicode_id;
		$this->fonts_by_id[ $type0_id ]    = $font;

		return $type0_id;
	}

	/**
	 * Словарь ресурсов.
	 *
	 * @return string
	 */
	private function resources_dict() {
		$fonts = array();

		foreach ( $this->font_names as $key => $name ) {
			$fonts[] = '/' . $name . ' ' . $this->font_refs[ $key ] . ' 0 R';
		}

		$xobjects = array();

		foreach ( $this->images as $image ) {
			$xobjects[] = '/' . $image['name'] . ' ' . $image['id'] . ' 0 R';
		}

		foreach ( $this->forms as $form ) {
			$xobjects[] = '/' . $form['name'] . ' ' . $form['id'] . ' 0 R';
		}

		$dict = '<<';

		if ( $fonts ) {
			$dict .= ' /Font << ' . implode( ' ', $fonts ) . ' >>';
		}

		if ( $xobjects ) {
			$dict .= ' /XObject << ' . implode( ' ', $xobjects ) . ' >>';
		}

		return $dict . ' /ProcSet [/PDF /Text /ImageB /ImageC] >>';
	}

	/**
	 * Регистрирует изображение как XObject.
	 *
	 * @param string $path Путь к файлу.
	 * @return array|null
	 */
	private function image_resource( $path ) {
		$key = md5( $path . '|' . (string) @filemtime( $path ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if ( isset( $this->images[ $key ] ) ) {
			return $this->images[ $key ];
		}

		$info = self::read_image( $path );

		if ( ! $info ) {
			return null;
		}

		$smask = '';

		if ( ! empty( $info['smask'] ) ) {
			// Альфа-канал выносим в отдельный XObject /SMask.
			$smask_id = $this->add_object(
				$this->stream(
					$info['smask'],
					sprintf(
						'/Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceGray /BitsPerComponent 8',
						$info['width'],
						$info['height']
					)
				)
			);

			$smask = ' /SMask ' . $smask_id . ' 0 R';
		}

		if ( 'jpeg' === $info['kind'] ) {
			$id = $this->add_object(
				sprintf(
					"<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /%s /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>\nstream\n%s\nendstream",
					$info['width'],
					$info['height'],
					$info['colorspace'],
					strlen( $info['data'] ),
					$info['data']
				)
			);
		} else {
			$dict = sprintf(
				'/Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8%s',
				$info['width'],
				$info['height'],
				$smask
			);

			$id = $this->add_object( $this->stream( $info['data'], $dict ) );
		}

		$this->images[ $key ] = array(
			'id'     => $id,
			'name'   => 'Im' . ( count( $this->images ) + 1 ),
			'width'  => $info['width'],
			'height' => $info['height'],
		);

		return $this->images[ $key ];
	}

	/**
	 * Читает изображение и приводит к формату, пригодному для PDF.
	 *
	 * JPEG встраивается как есть (DCTDecode). PNG декодируется собственный
	 * декодером: пиксели пакуются в FlateDecode, прозрачность уходит в /SMask.
	 * Зависимости от расширения GD нет.
	 *
	 * @param string $path Путь.
	 * @return array|null
	 */
	public static function read_image( $path ) {
		if ( ! is_readable( $path ) ) {
			return null;
		}

		$data = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if ( false === $data || '' === $data ) {
			return null;
		}

		if ( "\xFF\xD8" === substr( $data, 0, 2 ) ) {
			$size = self::jpeg_size( $data );

			if ( ! $size ) {
				return null;
			}

			return array(
				'kind'       => 'jpeg',
				'data'       => $data,
				'width'      => $size['width'],
				'height'     => $size['height'],
				'colorspace' => 1 === $size['components'] ? 'DeviceGray' : ( 4 === $size['components'] ? 'DeviceCMYK' : 'DeviceRGB' ),
			);
		}

		if ( "\x89PNG\r\n\x1a\n" === substr( $data, 0, 8 ) ) {
			return self::png_decode( $data );
		}

		// Прочие форматы (GIF, WebP, BMP) — через GD, если он есть.
		if ( function_exists( 'imagecreatefromstring' ) ) {
			return self::gd_to_jpeg( $data );
		}

		return null;
	}

	/**
	 * Декодирует PNG в сырые RGB-данные.
	 *
	 * Поддержка: 8 бит на канал, без интерлейса, типы 0/2/3/4/6.
	 *
	 * @param string $data Содержимое PNG.
	 * @return array|null
	 */
	private static function png_decode( $data ) {
		$length = strlen( $data );
		$pos    = 8;

		$width   = 0;
		$height  = 0;
		$depth   = 0;
		$color   = 0;
		$palette = '';
		$trns    = '';
		$idat    = '';
		$seen_idat = false;

		while ( $pos + 8 <= $length ) {
			$chunk_length = unpack( 'N', substr( $data, $pos, 4 ) )[1];
			$chunk_type   = substr( $data, $pos + 4, 4 );
			$chunk        = substr( $data, $pos + 8, $chunk_length );
			$pos         += 12 + $chunk_length;

			if ( 'IHDR' === $chunk_type ) {
				$width  = unpack( 'N', substr( $chunk, 0, 4 ) )[1];
				$height = unpack( 'N', substr( $chunk, 4, 4 ) )[1];
				$depth  = ord( $chunk[8] );
				$color  = ord( $chunk[9] );

				if ( 0 !== ord( $chunk[12] ) ) {
					return null; // Интерлейс не поддерживаем.
				}
			} elseif ( 'PLTE' === $chunk_type ) {
				$palette = $chunk;
			} elseif ( 'tRNS' === $chunk_type ) {
				$trns = $chunk;
			} elseif ( 'IDAT' === $chunk_type ) {
				$idat     .= $chunk;
				$seen_idat = true;
			} elseif ( 'IEND' === $chunk_type ) {
				break;
			}
		}

		if ( ! $width || ! $height || 8 !== $depth || ! $seen_idat ) {
			return null;
		}

		$channels = array(
			0 => 1, // Grayscale.
			2 => 3, // RGB.
			3 => 1, // Palette.
			4 => 2, // Gray + alpha.
			6 => 4, // RGBA.
		);

		if ( ! isset( $channels[ $color ] ) ) {
			return null;
		}

		if ( 3 === $color && '' === $palette ) {
			return null;
		}

		$bpp = $channels[ $color ];

		if ( ! function_exists( 'gzuncompress' ) ) {
			return null;
		}

		$raw = gzuncompress( $idat );

		if ( false === $raw ) {
			return null;
		}

		$stride = $width * $bpp;

		if ( strlen( $raw ) < $height * ( $stride + 1 ) ) {
			return null;
		}

		$prev = str_repeat( "\0", $stride );
		$rows = array();

		for ( $y = 0; $y < $height; $y++ ) {
			$offset = $y * ( $stride + 1 );
			$filter = ord( $raw[ $offset ] );
			$line   = substr( $raw, $offset + 1, $stride );

			switch ( $filter ) {
				case 0:
					break;

				case 1:
					for ( $i = $bpp; $i < $stride; $i++ ) {
						$line[ $i ] = chr( ( ord( $line[ $i ] ) + ord( $line[ $i - $bpp ] ) ) & 0xFF );
					}
					break;

				case 2:
					for ( $i = 0; $i < $stride; $i++ ) {
						$line[ $i ] = chr( ( ord( $line[ $i ] ) + ord( $prev[ $i ] ) ) & 0xFF );
					}
					break;

				case 3:
					for ( $i = 0; $i < $stride; $i++ ) {
						$a = $i >= $bpp ? ord( $line[ $i - $bpp ] ) : 0;
						$b = ord( $prev[ $i ] );
						$line[ $i ] = chr( ( ord( $line[ $i ] ) + ( ( $a + $b ) >> 1 ) ) & 0xFF );
					}
					break;

				case 4:
					for ( $i = 0; $i < $stride; $i++ ) {
						$a = $i >= $bpp ? ord( $line[ $i - $bpp ] ) : 0;
						$b = ord( $prev[ $i ] );
						$c = $i >= $bpp ? ord( $prev[ $i - $bpp ] ) : 0;
						$p = $a + $b - $c;
						$pa = abs( $p - $a );
						$pb = abs( $p - $b );
						$pc = abs( $p - $c );

						if ( $pa <= $pb && $pa <= $pc ) {
							$predictor = $a;
						} elseif ( $pb <= $pc ) {
							$predictor = $b;
						} else {
							$predictor = $c;
						}

						$line[ $i ] = chr( ( ord( $line[ $i ] ) + $predictor ) & 0xFF );
					}
					break;

				default:
					return null;
			}

			$prev   = $line;
			$rows[] = $line;
		}

		$rgb      = '';
		$alpha    = '';
		$has_alpha = false;

		foreach ( $rows as $line ) {
			for ( $x = 0; $x < $width; $x++ ) {
				$offset = $x * $bpp;

				if ( 2 === $color ) {
					$rgb .= substr( $line, $offset, 3 );
				} elseif ( 6 === $color ) {
					$rgb .= substr( $line, $offset, 3 );
					$value = $line[ $offset + 3 ];
					$alpha .= $value;

					if ( "\xFF" !== $value ) {
						$has_alpha = true;
					}
				} elseif ( 0 === $color ) {
					$gray = $line[ $offset ];
					$rgb .= $gray . $gray . $gray;
				} elseif ( 4 === $color ) {
					$gray = $line[ $offset ];
					$rgb .= $gray . $gray . $gray;
					$value = $line[ $offset + 1 ];
					$alpha .= $value;

					if ( "\xFF" !== $value ) {
						$has_alpha = true;
					}
				} else {
					$index  = ord( $line[ $offset ] );
					$rgb   .= substr( $palette, $index * 3, 3 );
					$value  = isset( $trns[ $index ] ) ? $trns[ $index ] : "\xFF";
					$alpha .= $value;

					if ( "\xFF" !== $value ) {
						$has_alpha = true;
					}
				}
			}
		}

		return array(
			'kind'       => 'raw',
			'data'       => $rgb,
			'smask'      => $has_alpha ? $alpha : null,
			'width'      => $width,
			'height'     => $height,
			'colorspace' => 'DeviceRGB',
		);
	}

	/**
	 * Конвертация через GD (GIF/WebP/BMP или сложные PNG).
	 *
	 * @param string $data Данные файла.
	 * @return array|null
	 */
	private static function gd_to_jpeg( $data ) {
		$image = @imagecreatefromstring( $data ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if ( ! $image ) {
			return null;
		}

		$width  = imagesx( $image );
		$height = imagesy( $image );

		$flat  = imagecreatetruecolor( $width, $height );
		$white = imagecolorallocate( $flat, 255, 255, 255 );
		imagefilledrectangle( $flat, 0, 0, $width, $height, $white );
		imagecopy( $flat, $image, 0, 0, 0, 0, $width, $height );

		ob_start();
		imagejpeg( $flat, null, 92 );
		$jpeg = ob_get_clean();

		imagedestroy( $image );
		imagedestroy( $flat );

		if ( ! $jpeg ) {
			return null;
		}

		return array(
			'kind'       => 'jpeg',
			'data'       => $jpeg,
			'width'      => $width,
			'height'     => $height,
			'colorspace' => 'DeviceRGB',
		);
	}

	/**
	 * Перенос текста по словам.
	 *
	 * @param string       $text  Текст.
	 * @param WPDC_PDF_Font $font  Шрифт.
	 * @param float        $size  Кегль.
	 * @param float        $width Максимальная ширина.
	 * @return string[]
	 */
	private function wrap_text( $text, $font, $size, $width ) {
		$lines = array();

		foreach ( explode( "\n", str_replace( "\r\n", "\n", $text ) ) as $paragraph ) {
			$paragraph = trim( $paragraph );

			if ( '' === $paragraph ) {
				$lines[] = '';
				continue;
			}

			if ( $width <= 0 ) {
				$lines[] = $paragraph;
				continue;
			}

			$current = '';

			foreach ( preg_split( '/\s+/u', $paragraph ) as $word ) {
				$candidate = '' === $current ? $word : $current . ' ' . $word;

				if ( '' !== $current && $font->text_width( $candidate, $size ) > $width ) {
					$lines[] = $current;
					$current = $word;
				} else {
					$current = $candidate;
				}
			}

			$lines[] = $current;
		}

		return $lines;
	}

	/**
	 * Операторы цвета для прямоугольника.
	 *
	 * @param string $style Стиль заливки.
	 * @return string
	 */
	private function paint_ops( $style ) {
		$ops = array();

		if ( false !== strpos( $style, 'F' ) ) {
			$ops[] = implode( ' ', array_map( array( __CLASS__, 'num' ), $this->fill ) ) . ' rg';
		}

		if ( false !== strpos( $style, 'S' ) ) {
			$ops[] = implode( ' ', array_map( array( __CLASS__, 'num' ), $this->stroke ) ) . ' RG';
			$ops[] = self::num( $this->line_width ) . ' w';
		}

		return implode( "\n", $ops );
	}

	/**
	 * Дописывает операторы в текущую страницу.
	 *
	 * @param string $ops Операторы.
	 */
	private function append( $ops ) {
		if ( $this->current >= 0 ) {
			$this->pages[ $this->current ]['content'] .= $ops;
		}
	}

	/**
	 * HEX → RGB 0..1.
	 *
	 * @param string $hex Цвет.
	 * @return float[]
	 */
	public static function hex_to_rgb( $hex ) {
		$hex = ltrim( (string) $hex, '#' );

		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
			return array( 0.0, 0.0, 0.0 );
		}

		return array(
			hexdec( substr( $hex, 0, 2 ) ) / 255,
			hexdec( substr( $hex, 2, 2 ) ) / 255,
			hexdec( substr( $hex, 4, 2 ) ) / 255,
		);
	}

	/**
	 * Форматирует число для PDF (без учёта локали).
	 *
	 * @param float $value Значение.
	 * @return string
	 */
	public static function num( $value ) {
		$value = round( (float) $value, 3 );
		$out   = rtrim( rtrim( sprintf( '%.3F', $value ), '0' ), '.' );

		return ( '' === $out || '-0' === $out ) ? '0' : $out;
	}

	/**
	 * Обёртка данных в поток PDF.
	 *
	 * @param string $data  Данные.
	 * @param string $extra Дополнительные ключи словаря.
	 * @return string
	 */
	private function stream( $data, $extra = '' ) {
		$filter = '';

		if ( function_exists( 'gzcompress' ) && strlen( $data ) > 64 ) {
			$compressed = gzcompress( $data, 6 );

			if ( false !== $compressed ) {
				$data   = $compressed;
				$filter = '/Filter /FlateDecode';
			}
		}

		$dict = trim( trim( $extra ) . ' ' . $filter );

		return sprintf(
			"<< %s/Length %d >>\nstream\n%s\nendstream",
			'' === $dict ? '' : $dict . ' ',
			strlen( $data ),
			$data
		);
	}

	/**
	 * Строка PDF в UTF-16BE.
	 *
	 * @param string $value Значение.
	 * @return string
	 */
	private function text_string( $value ) {
		$encoded = '';

		foreach ( WPDC_PDF_Font::codepoints( $value ) as $cp ) {
			$encoded .= sprintf( '%04X', $cp );
		}

		return '<FEFF' . $encoded . '>';
	}

	/**
	 * Добавляет объект.
	 *
	 * @param string $body Тело.
	 * @return int
	 */
	private function add_object( $body ) {
		$this->objects[] = $body;

		return count( $this->objects );
	}

	/**
	 * Собирает файл.
	 *
	 * @param int $catalog_id Каталог.
	 * @param int $info_id    Info.
	 * @return string
	 */
	private function build_file( $catalog_id, $info_id ) {
		$pdf     = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
		$offsets = array();

		foreach ( $this->objects as $index => $body ) {
			$id             = $index + 1;
			$offsets[ $id ] = strlen( $pdf );
			$pdf           .= sprintf( "%d 0 obj\n%s\nendobj\n", $id, $body );
		}

		$xref_offset = strlen( $pdf );
		$count       = count( $this->objects ) + 1;

		$pdf .= sprintf( "xref\n0 %d\n", $count );
		$pdf .= "0000000000 65535 f \n";

		for ( $id = 1; $id < $count; $id++ ) {
			$pdf .= sprintf( "%010d 00000 n \n", $offsets[ $id ] );
		}

		$pdf .= sprintf(
			"trailer\n<< /Size %d /Root %d 0 R%s >>\nstartxref\n%d\n%%%%EOF\n",
			$count,
			$catalog_id,
			$info_id ? ' /Info ' . $info_id . ' 0 R' : '',
			$xref_offset
		);

		return $pdf;
	}
}
