<?php
/**
 * Парсер TrueType-шрифта для встраивания в PDF (CIDFontType2, Identity-H).
 *
 * Класс не зависит от WordPress и работает с файловой системой напрямую:
 * читает таблицы head/hhea/maxp/hmtx/cmap и кодирует UTF-8 в двухбайтовые
 * идентификаторы глифов. Шрифт встраивается целиком, без сабсеттинга —
 * это проще и надёжнее, ценой размера файла.
 *
 * @package WP_Diplom
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPD_PDF_STANDALONE' ) ) {
	exit;
}

/**
 * Один TrueType-шрифт.
 */
class WPD_PDF_Font {

	/**
	 * Имя шрифта в PDF (PostScript-подобное).
	 *
	 * @var string
	 */
	public $name = 'EmbeddedFont';

	/**
	 * unitsPerEm из head.
	 *
	 * @var int
	 */
	public $units_per_em = 1000;

	/**
	 * Количество глифов.
	 *
	 * @var int
	 */
	public $num_glyphs = 0;

	/**
	 * Ascender в единицах шрифта.
	 *
	 * @var int
	 */
	public $ascender = 800;

	/**
	 * Descender в единицах шрифта (отрицательный).
	 *
	 * @var int
	 */
	public $descender = -200;

	/**
	 * BBox шрифта [xMin, yMin, xMax, yMax].
	 *
	 * @var int[]
	 */
	public $bbox = array( -1000, -400, 2000, 1100 );

	/**
	 * Сырые данные TTF.
	 *
	 * @var string
	 */
	private $data = '';

	/**
	 * Смещения таблиц: имя => [offset, length].
	 *
	 * @var array<string, int[]>
	 */
	private $tables = array();

	/**
	 * Смещение начала hmtx.
	 *
	 * @var int
	 */
	private $hmtx_offset = 0;

	/**
	 * numberOfHMetrics.
	 *
	 * @var int
	 */
	private $num_h_metrics = 0;

	/**
	 * indexToLocFormat (0 = short, 1 = long).
	 *
	 * @var int
	 */
	private $index_to_loc_format = 0;

	/**
	 * Разобранные cmap-субтаблицы.
	 *
	 * @var array<int, array>
	 */
	private $cmap = array();

	/**
	 * Кэш ширин по глифам.
	 *
	 * @var array<int, float>
	 */
	private $width_cache = array();

	/**
	 * Загружает шрифт с диска.
	 *
	 * @param string      $path Путь к .ttf.
	 * @param string|null $name Имя для PDF.
	 * @return self|null
	 */
	public static function load( $path, $name = null ) {
		if ( ! is_readable( $path ) ) {
			return null;
		}

		$data = file_get_contents( $path );
		if ( false === $data || strlen( $data ) < 12 ) {
			return null;
		}

		$font         = new self();
		$font->data   = $data;
		$font->name   = $name ? $name : self::name_from_path( $path );
		$font->tables = $font->read_table_directory();

		$font->read_head();
		$font->read_hhea();
		$font->read_maxp();
		$font->read_cmap();

		return $font->is_usable() ? $font : null;
	}

	/**
	 * Имя шрифта из имени файла.
	 *
	 * @param string $path Путь.
	 * @return string
	 */
	private static function name_from_path( $path ) {
		$base = basename( $path, '.ttf' );

		return 'WPD_' . preg_replace( '/[^A-Za-z0-9]/', '', $base );
	}

	/**
	 * Читает директорию таблиц.
	 *
	 * @return array<string, int[]>
	 */
	private function read_table_directory() {
		$tables = array();

		$num = $this->u16( 4 );

		for ( $i = 0; $i < $num; $i++ ) {
			$record = 12 + $i * 16;
			$tag    = substr( $this->data, $record, 4 );
			$offset = $this->u32( $record + 8 );
			$length = $this->u32( $record + 12 );

			if ( $offset + $length > strlen( $this->data ) ) {
				continue;
			}

			$tables[ $tag ] = array( $offset, $length );
		}

		return $tables;
	}

	/**
	 * head.
	 */
	private function read_head() {
		if ( ! isset( $this->tables['head'] ) ) {
			return;
		}

		$offset = $this->tables['head'][0];

		$this->units_per_em         = $this->u16( $offset + 18 );
		$this->bbox                 = array(
			$this->s16( $offset + 36 ),
			$this->s16( $offset + 38 ),
			$this->s16( $offset + 40 ),
			$this->s16( $offset + 42 ),
		);
		$this->index_to_loc_format  = $this->s16( $offset + 50 );
	}

	/**
	 * hhea + hmtx.
	 */
	private function read_hhea() {
		if ( ! isset( $this->tables['hhea'], $this->tables['hmtx'] ) ) {
			return;
		}

		$offset = $this->tables['hhea'][0];

		$this->ascender        = $this->s16( $offset + 4 );
		$this->descender       = $this->s16( $offset + 6 );
		$this->num_h_metrics   = $this->u16( $offset + 34 );
		$this->hmtx_offset     = $this->tables['hmtx'][0];
	}

	/**
	 * maxp.
	 */
	private function read_maxp() {
		if ( ! isset( $this->tables['maxp'] ) ) {
			return;
		}

		$this->num_glyphs = $this->u16( $this->tables['maxp'][0] + 4 );
	}

	/**
	 * cmap: собирает подходящие подтаблицы (формат 4 и 12).
	 */
	private function read_cmap() {
		if ( ! isset( $this->tables['cmap'] ) ) {
			return;
		}

		$base  = $this->tables['cmap'][0];
		$count = $this->u16( $base + 2 );

		for ( $i = 0; $i < $count; $i++ ) {
			$record    = $base + 4 + $i * 8;
			$platform  = $this->u16( $record );
			$encoding  = $this->u16( $record + 2 );
			$offset    = $base + $this->u32( $record + 4 );
			$format    = $this->u16( $offset );

			if ( 4 !== $format && 12 !== $format ) {
				continue;
			}

			// Приоритет: Windows Unicode BMP (3,1) / UCS-4 (3,10) / Unicode (0,x).
			$priority = 9;
			if ( 3 === $platform && 10 === $encoding && 12 === $format ) {
				$priority = 0;
			} elseif ( 3 === $platform && 1 === $encoding && 4 === $format ) {
				$priority = 1;
			} elseif ( 0 === $platform ) {
				$priority = 2;
			}

			$this->cmap[ $priority ] = array(
				'format' => $format,
				'offset' => $offset,
			);
		}

		ksort( $this->cmap );
	}

	/**
	 * Хватает ли данных для встраивания.
	 *
	 * @return bool
	 */
	public function is_usable() {
		return '' !== $this->data
			&& $this->num_glyphs > 0
			&& $this->units_per_em > 0
			&& ! empty( $this->cmap )
			&& isset( $this->tables['glyf'], $this->tables['loca'] );
	}

	/**
	 * Сырые данные шрифта.
	 *
	 * @return string
	 */
	public function raw() {
		return $this->data;
	}

	/**
	 * Код глифа для символа (Unicode codepoint).
	 *
	 * @param int $cp Код символа.
	 * @return int
	 */
	public function glyph( $cp ) {
		foreach ( $this->cmap as $sub ) {
			$gid = 12 === $sub['format']
				? $this->glyph_format_12( $sub['offset'], $cp )
				: $this->glyph_format_4( $sub['offset'], $cp );

			if ( $gid > 0 ) {
				return $gid;
			}
		}

		return 0;
	}

	/**
	 * Поиск в cmap формата 4.
	 *
	 * @param int $offset Смещение субтаблицы.
	 * @param int $cp     Код символа.
	 * @return int
	 */
	private function glyph_format_4( $offset, $cp ) {
		if ( $cp > 0xFFFF ) {
			return 0;
		}

		$seg_count_x2 = $this->u16( $offset + 6 );
		$seg_count    = (int) ( $seg_count_x2 / 2 );

		$end_offset   = $offset + 14;
		$start_offset = $end_offset + $seg_count_x2 + 2;
		$delta_offset = $start_offset + $seg_count_x2;
		$range_offset = $delta_offset + $seg_count_x2;

		for ( $i = 0; $i < $seg_count; $i++ ) {
			$end = $this->u16( $end_offset + $i * 2 );

			if ( $cp > $end ) {
				continue;
			}

			$start = $this->u16( $start_offset + $i * 2 );

			if ( $cp < $start ) {
				return 0;
			}

			$delta = $this->s16( $delta_offset + $i * 2 );
			$ro    = $this->u16( $range_offset + $i * 2 );

			if ( 0 === $ro ) {
				return ( $cp + $delta ) & 0xFFFF;
			}

			$index = $range_offset + $i * 2 + $ro + ( $cp - $start ) * 2;
			$gid   = $this->u16( $index );

			return 0 === $gid ? 0 : ( $gid + $delta ) & 0xFFFF;
		}

		return 0;
	}

	/**
	 * Поиск в cmap формата 12.
	 *
	 * @param int $offset Смещение субтаблицы.
	 * @param int $cp     Код символа.
	 * @return int
	 */
	private function glyph_format_12( $offset, $cp ) {
		$groups = $this->u32( $offset + 12 );
		$low    = 0;
		$high   = $groups - 1;

		// Бинарный поиск по группам.
		while ( $low <= $high ) {
			$mid       = (int) ( ( $low + $high ) / 2 );
			$group     = $offset + 16 + $mid * 12;
			$start     = $this->u32( $group );
			$end       = $this->u32( $group + 4 );

			if ( $cp < $start ) {
				$high = $mid - 1;
			} elseif ( $cp > $end ) {
				$low = $mid + 1;
			} else {
				return $this->u32( $group + 8 ) + ( $cp - $start );
			}
		}

		return 0;
	}

	/**
	 * Ширина глифа в 1/1000 em (единицы PDF).
	 *
	 * @param int $gid Код глифа.
	 * @return float
	 */
	public function width( $gid ) {
		if ( isset( $this->width_cache[ $gid ] ) ) {
			return $this->width_cache[ $gid ];
		}

		$advance = 0;

		if ( $this->num_h_metrics > 0 ) {
			$index = min( $gid, $this->num_h_metrics - 1 );
			$pos   = $this->hmtx_offset + $index * 4;

			if ( $pos + 2 <= strlen( $this->data ) ) {
				$advance = $this->u16( $pos );
			}
		}

		$width = $advance * 1000 / max( 1, $this->units_per_em );

		$this->width_cache[ $gid ] = $width;

		return $width;
	}

	/**
	 * Ширина строки в пунктах при заданном размере.
	 *
	 * @param string $text UTF-8 текст.
	 * @param float  $size Размер в pt.
	 * @return float
	 */
	public function text_width( $text, $size ) {
		$width = 0.0;

		foreach ( self::codepoints( $text ) as $cp ) {
			$width += $this->width( $this->glyph( $cp ) );
		}

		return $width * $size / 1000;
	}

	/**
	 * Кодирует UTF-8 в hex-строку глифов для Identity-H.
	 *
	 * @param string     $text UTF-8 текст.
	 * @param array|null $map  Сюда собирается gid => codepoint для ToUnicode.
	 * @return string
	 */
	public function encode( $text, &$map = null ) {
		$hex = '';

		foreach ( self::codepoints( $text ) as $cp ) {
			$gid = $this->glyph( $cp );

			if ( null !== $map && ! isset( $map[ $gid ] ) ) {
				$map[ $gid ] = $cp;
			}

			$hex .= sprintf( '%04X', $gid );
		}

		return $hex;
	}

	/**
	 * Разбивает UTF-8 на codepoint'ы.
	 *
	 * @param string $text Текст.
	 * @return int[]
	 */
	public static function codepoints( $text ) {
		$out    = array();
		$length = strlen( $text );

		for ( $i = 0; $i < $length; ) {
			$byte = ord( $text[ $i ] );

			if ( $byte < 0x80 ) {
				$out[] = $byte;
				$i    += 1;
			} elseif ( ( $byte & 0xE0 ) === 0xC0 ) {
				$out[] = ( ( $byte & 0x1F ) << 6 ) | ( ord( $text[ $i + 1 ] ?? "\0" ) & 0x3F );
				$i    += 2;
			} elseif ( ( $byte & 0xF0 ) === 0xE0 ) {
				$out[] = ( ( $byte & 0x0F ) << 12 )
					| ( ( ord( $text[ $i + 1 ] ?? "\0" ) & 0x3F ) << 6 )
					| ( ord( $text[ $i + 2 ] ?? "\0" ) & 0x3F );
				$i += 3;
			} else {
				$out[] = ( ( $byte & 0x07 ) << 18 )
					| ( ( ord( $text[ $i + 1 ] ?? "\0" ) & 0x3F ) << 12 )
					| ( ( ord( $text[ $i + 2 ] ?? "\0" ) & 0x3F ) << 6 )
					| ( ord( $text[ $i + 3 ] ?? "\0" ) & 0x3F );
				$i += 4;
			}
		}

		return $out;
	}

	/**
	 * CMap ToUnicode для использованных глифов.
	 *
	 * @param array<int, int> $map gid => codepoint.
	 * @return string
	 */
	public function to_unicode_cmap( array $map ) {
		$lines = array(
			'/CIDInit /ProcSet findresource begin',
			'12 dict begin',
			'begincmap',
			'/CIDSystemInfo << /Registry (Adobe) /Ordering (UCS) /Supplement 0 >> def',
			'/CMapName /Adobe-Identity-UCS def',
			'/CMapType 2 def',
			'1 begincodespacerange',
			'<0000> <FFFF>',
			'endcodespacerange',
		);

		$entries = array();

		foreach ( $map as $gid => $cp ) {
			$entries[] = sprintf( '<%04X> <%04X>', $gid, $cp );
		}

		// Не больше 100 записей на блок (ограничение спецификации).
		foreach ( array_chunk( $entries, 100 ) as $chunk ) {
			$lines[] = count( $chunk ) . ' beginbfchar';

			foreach ( $chunk as $entry ) {
				$lines[] = $entry;
			}

			$lines[] = 'endbfchar';
		}

		$lines[] = 'endcmap';
		$lines[] = 'CMapName currentdict /CMap defineresource pop';
		$lines[] = 'end';
		$lines[] = 'end';

		return implode( "\n", $lines );
	}

	/**
	 * Массив /W для всех глифов.
	 *
	 * @return string
	 */
	public function widths_array() {
		$widths = array();

		for ( $gid = 0; $gid < $this->num_glyphs; $gid++ ) {
			$widths[] = self::num( $this->width( $gid ) );
		}

		return '0 [' . implode( ' ', $widths ) . ']';
	}

	/**
	 * Форматирует число для PDF.
	 *
	 * @param float $value Значение.
	 * @return string
	 */
	public static function num( $value ) {
		$value = round( (float) $value, 3 );

		$out = rtrim( rtrim( sprintf( '%.3F', $value ), '0' ), '.' );

		return '' === $out || '-0' === $out ? '0' : $out;
	}

	/**
	 * uint16 из данных.
	 *
	 * @param int $offset Смещение.
	 * @return int
	 */
	private function u16( $offset ) {
		if ( ! isset( $this->data[ $offset + 1 ] ) ) {
			return 0;
		}

		return unpack( 'n', substr( $this->data, $offset, 2 ) )[1];
	}

	/**
	 * int16 из данных.
	 *
	 * @param int $offset Смещение.
	 * @return int
	 */
	private function s16( $offset ) {
		$value = $this->u16( $offset );

		return $value > 0x7FFF ? $value - 0x10000 : $value;
	}

	/**
	 * uint32 из данных.
	 *
	 * @param int $offset Смещение.
	 * @return int
	 */
	private function u32( $offset ) {
		if ( ! isset( $this->data[ $offset + 3 ] ) ) {
			return 0;
		}

		return unpack( 'N', substr( $this->data, $offset, 4 ) )[1];
	}
}
