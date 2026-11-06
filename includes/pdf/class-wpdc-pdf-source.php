<?php
/**
 * Чтение исходного PDF для использования в качестве шаблона бланка.
 *
 * Задача: достать из чужого PDF первую (или указанную) страницу так, чтобы её
 * можно было встроить в наш документ как форму (Form XObject) — без
 * растеризации, без внешних библиотек. Текст в шаблоне остаётся текстом,
 * векторная графика остаётся векторной.
 *
 * Поддерживается:
 * - классическая таблица xref и xref-потоки (PDF 1.5+);
 * - сжатые объектные потоки /ObjStm (так сохраняют Word, Canva, Illustrator);
 * - фильтр FlateDecode с предикторами PNG (нужен для xref-потоков);
 * - наследование /Resources и /MediaBox от родительских узлов;
 * - /Rotate.
 *
 * Не поддерживается намеренно: шифрование (сообщаем понятной ошибкой) и
 * экзотические фильтры (DCTDecode и т. п. внутри страницы не декодируются —
 * они и не нужно, их байты переносятся как есть).
 *
 * @package WP_Diplom
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPDC_PDF_STANDALONE' ) ) {
	exit;
}

/**
 * Разбор исходного PDF-файла.
 */
class WPDC_PDF_Source {

	/**
	 * Содержимое файла.
	 *
	 * @var string
	 */
	private $data = '';

	/**
	 * Разобранные объекты: номер => ['dict' => string, 'stream' => string|null].
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private $objects = array();

	/**
	 * Номера объектов, которые ещё не разобраны (смещение в файле).
	 *
	 * @var array<int, int>
	 */
	private $offsets = array();

	/**
	 * Объекты внутри сжатых потоков: номер => [номер потока, индекс].
	 *
	 * @var array<int, int[]>
	 */
	private $compressed = array();

	/**
	 * Трейлер (словарь).
	 *
	 * @var string
	 */
	private $trailer = '';

	/**
	 * Номер объекта каталога (/Root).
	 *
	 * @var int
	 */
	private $root = 0;

	/**
	 * Кэш страниц: список номеров объектов-страниц.
	 *
	 * @var int[]
	 */
	private $pages = array();

	/**
	 * Конструктор.
	 *
	 * @param string $path Путь к PDF.
	 * @throws RuntimeException Если файл не читается или зашифрован.
	 */
	public function __construct( $path ) {
		if ( ! is_readable( $path ) ) {
			throw new RuntimeException( 'Файл шаблона недоступен для чтения.' );
		}

		$data = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if ( false === $data || '' === $data ) {
			throw new RuntimeException( 'Не удалось прочитать файл шаблона.' );
		}

		if ( 0 !== strpos( ltrim( $data ), '%PDF' ) ) {
			throw new RuntimeException( 'Файл не является PDF.' );
		}

		$this->data = $data;

		$this->parse_xref();
		$this->scan_objects();

		if ( $this->is_encrypted() ) {
			throw new RuntimeException( 'PDF защищён паролем. Загрузите незашифрованный файл.' );
		}
	}

	/* --------------------------------------------------------------------- */
	/* Публичный интерфейс                                                    */
	/* --------------------------------------------------------------------- */

	/**
	 * Количество страниц.
	 *
	 * @return int
	 */
	public function page_count() {
		return count( $this->page_numbers() );
	}

	/**
	 * Размер страницы в точках (1/72 дюйма).
	 *
	 * @param int $index Номер страницы с нуля.
	 * @return array{width: float, height: float}
	 */
	public function page_size( $index = 0 ) {
		$page = $this->page_dict( $index );
		$box  = $this->inherited( $page, 'MediaBox' );
		$rect = self::parse_box( $box );

		$rotate = (int) round( (float) trim( $this->inherited( $page, 'Rotate' ) ) );

		if ( 90 === $rotate || 270 === $rotate ) {
			return array(
				'width'  => (float) ( $rect[3] - $rect[1] ),
				'height' => (float) ( $rect[2] - $rect[0] ),
			);
		}

		return array(
			'width'  => (float) ( $rect[2] - $rect[0] ),
			'height' => (float) ( $rect[3] - $rect[1] ),
		);
	}

	/**
	 * Готовит страницу к встраиванию.
	 *
	 * Возвращает содержимое страницы и её ресурсы (со ссылками вида «N 0 R» в
	 * исходной нумерации — перенумерацией занимается писатель PDF).
	 *
	 * @param int $index Номер страницы с нуля.
	 * @return array{content: string, resources: string, box: float[], rotate: int}
	 * @throws RuntimeException Если страница не найдена.
	 */
	public function export_page( $index = 0 ) {
		$page = $this->page_dict( $index );

		$content = $this->page_content( $page );

		if ( '' === trim( $content ) ) {
			$content = '';
		}

		$resources = $this->inherited( $page, 'Resources' );
		$resources = '' !== trim( $resources ) ? trim( $resources ) : '<< >>';

		// Ресурсы могут быть ссылкой на отдельный объект — разворачиваем.
		if ( preg_match( '/^(\d+)\s+\d+\s+R$/', $resources, $m ) ) {
			$object    = $this->object( (int) $m[1] );
			$resources = null === $object ? '<< >>' : trim( $object['dict'] );
		}

		return array(
			'content'   => $content,
			'resources' => $resources,
			'box'       => self::parse_box( $this->inherited( $page, 'MediaBox' ) ),
			'rotate'    => (int) round( (float) trim( $this->inherited( $page, 'Rotate' ) ) ),
		);
	}

	/**
	 * Все ссылки на объекты внутри словаря.
	 *
	 * @param string $dict Словарь.
	 * @return int[]
	 */
	public static function references( $dict ) {
		$numbers = array();

		if ( preg_match_all( '/(\d+)\s+(\d+)\s+R\b/', $dict, $matches ) ) {
			foreach ( $matches[1] as $number ) {
				$numbers[] = (int) $number;
			}
		}

		return array_values( array_unique( $numbers ) );
	}

	/**
	 * Тело объекта в исходной нумерации.
	 *
	 * @param int $number Номер объекта.
	 * @return array{dict: string, stream: string|null}|null
	 */
	public function object( $number ) {
		$number = (int) $number;

		if ( array_key_exists( $number, $this->objects ) ) {
			return $this->objects[ $number ];
		}

		if ( ! isset( $this->offsets[ $number ] ) ) {
			return null;
		}

		$object = $this->read_object_at( $this->offsets[ $number ] );

		if ( null !== $object ) {
			$this->objects[ $number ] = $object;
		}

		return $object;
	}

	/* --------------------------------------------------------------------- */
	/* Оглавление: xref                                                       */
	/* --------------------------------------------------------------------- */

	/**
	 * Разбирает таблицу xref (классическую или в виде потока).
	 */
	private function parse_xref() {
		if ( ! preg_match_all( '/startxref\s+(\d+)/', $this->data, $matches ) ) {
			return;
		}

		$offset  = (int) end( $matches[1] );
		$visited = array();
		$guard   = 0;

		while ( $offset > 0 && $offset < strlen( $this->data ) && ! isset( $visited[ $offset ] ) && $guard < 64 ) {
			$visited[ $offset ] = true;
			++$guard;

			$offset = $this->parse_xref_section( $offset );
		}
	}

	/**
	 * Разбирает одну секцию xref и возвращает смещение предыдущей.
	 *
	 * @param int $offset Смещение.
	 * @return int 0, если продолжения нет.
	 */
	private function parse_xref_section( $offset ) {
		$head = ltrim( substr( $this->data, $offset, 32 ) );

		if ( 0 === strpos( $head, 'xref' ) ) {
			return $this->parse_xref_table( $offset + 4 );
		}

		return $this->parse_xref_stream( $offset );
	}

	/**
	 * Классическая таблица.
	 *
	 * @param int $offset Смещение сразу после слова xref.
	 * @return int
	 */
	private function parse_xref_table( $offset ) {
		$data = $this->data;
		$len  = strlen( $data );
		$pos  = $offset;

		while ( $pos < $len ) {
			// Пропускаем пробелы до строки «начало количество».
			while ( $pos < $len && false !== strpos( " \r\n\t", $data[ $pos ] ) ) {
				++$pos;
			}

			if ( 0 === substr_compare( $data, 'trailer', $pos, 7 ) ) {
				$dict          = $this->read_dict_at( $pos + 7 );
				$this->trailer = $dict;

				return $this->prev_offset( $dict );
			}

			if ( ! preg_match( '/\G(\d+)\s+(\d+)[ \t]*\r?\n?/', $data, $m, 0, $pos ) ) {
				break;
			}

			$first = (int) $m[1];
			$count = (int) $m[2];
			$pos  += strlen( $m[0] );

			for ( $i = 0; $i < $count; $i++ ) {
				$entry = substr( $data, $pos, 20 );

				if ( strlen( $entry ) < 18 ) {
					$pos = $len;
					break;
				}

				$type   = substr( $entry, 17, 1 );
				$number = $first + $i;

				if ( 'n' === $type && ! isset( $this->offsets[ $number ] ) ) {
					$this->offsets[ $number ] = (int) trim( substr( $entry, 0, 10 ) );
				}

				$pos += 20;
			}
		}

		return 0;
	}

	/**
	 * Поток xref (PDF 1.5+).
	 *
	 * @param int $offset Смещение объекта.
	 * @return int
	 */
	private function parse_xref_stream( $offset ) {
		$object = $this->read_object_at( $offset );

		if ( null === $object || null === $object['stream'] ) {
			return 0;
		}

		$dict = $object['dict'];

		if ( ! preg_match( '/\/W\s*\[([^\]]*)\]/', $dict, $w ) ) {
			return 0;
		}

		$widths = array_map( 'intval', preg_split( '/\s+/', trim( $w[1] ) ) );
		$size   = (int) $this->resolve_number( $dict, 'Size' );

		if ( preg_match( '/\/Index\s*\[([^\]]*)\]/', $dict, $index ) ) {
			$pairs = array_map( 'intval', preg_split( '/\s+/', trim( $index[1] ) ) );
		} else {
			$pairs = array( 0, $size );
		}

		$raw = $this->decode_stream( $dict, $object['stream'] );

		if ( null === $raw ) {
			return 0;
		}

		$row  = array_sum( $widths );
		$line = 0;

		for ( $p = 0; $p + 1 < count( $pairs ); $p += 2 ) {
			$first = $pairs[ $p ];
			$count = $pairs[ $p + 1 ];

			for ( $i = 0; $i < $count; $i++ ) {
				$chunk = substr( $raw, $line * $row, $row );
				++$line;

				if ( strlen( $chunk ) < $row ) {
					continue;
				}

				$fields = array();
				$pos    = 0;

				foreach ( $widths as $width ) {
					$value = 0;

					for ( $b = 0; $b < $width; $b++ ) {
						$value = ( $value << 8 ) | ord( $chunk[ $pos + $b ] );
					}

					$fields[] = $value;
					$pos     += $width;
				}

				$type   = isset( $fields[0] ) ? $fields[0] : 1;
				$number = $first + $i;

				if ( isset( $this->offsets[ $number ] ) || 0 === $type ) {
					continue;
				}

				if ( 1 === $type && isset( $fields[1] ) ) {
					$this->offsets[ $number ] = (int) $fields[1];
				} elseif ( 2 === $type && isset( $fields[1], $fields[2] ) ) {
					$this->compressed[ $number ] = array( (int) $fields[1], (int) $fields[2] );
				}
			}
		}

		if ( '' === $this->trailer ) {
			$this->trailer = preg_replace( '/\s*\/Type\s*\/XRef/', '', $dict );
		}

		return $this->prev_offset( $dict );
	}

	/**
	 * Смещение предыдущей секции xref.
	 *
	 * @param string $dict Словарь.
	 * @return int
	 */
	private function prev_offset( $dict ) {
		if ( preg_match( '/\/Prev\s+(\d+)/', $dict, $m ) ) {
			return (int) $m[1];
		}

		return 0;
	}

	/* --------------------------------------------------------------------- */
	/* Объекты                                                                */
	/* --------------------------------------------------------------------- */

	/**
	 * Ищет объекты простым сканированием — запасной путь, если xref битый.
	 */
	private function scan_objects() {
		// Объектные потоки: разворачиваем их содержимое в общий список.
		$streams = array();

		foreach ( $this->offsets as $number => $offset ) {
			$object = $this->object( $number );

			if ( null !== $object && preg_match( '/\/Type\s*\/ObjStm/', $object['dict'] ) ) {
				$streams[ $number ] = $object;
			}
		}

		foreach ( $this->compressed as $entry ) {
			$number = $entry[0];

			if ( isset( $streams[ $number ] ) ) {
				continue;
			}

			$object = $this->object( $number );

			if ( null !== $object && null !== $object['stream'] ) {
				$streams[ $number ] = $object;
			}
		}

		foreach ( $streams as $object ) {
			$this->expand_object_stream( $object );
		}

		if ( $this->offsets ) {
			return;
		}

		if ( preg_match_all( '/(\d+)\s+(\d+)\s+obj\b/', $this->data, $matches, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $matches[1] as $i => $number ) {
				$this->offsets[ (int) $number[0] ] = (int) $matches[0][ $i ][1];
			}
		}
	}

	/**
	 * Разворачивает сжатый объектный поток.
	 *
	 * @param array<string, mixed> $object Объект /ObjStm.
	 */
	private function expand_object_stream( $object ) {
		if ( ! preg_match( '/\/N\s+(\d+)/', $object['dict'], $n ) || ! preg_match( '/\/First\s+(\d+)/', $object['dict'], $f ) ) {
			return;
		}

		$count = (int) $n[1];
		$first = (int) $f[1];
		$raw   = $this->decode_stream( $object['dict'], $object['stream'] );

		if ( null === $raw ) {
			return;
		}

		$header = substr( $raw, 0, $first );
		$body   = substr( $raw, $first );

		if ( ! preg_match_all( '/(\d+)\s+(\d+)/', $header, $pairs, PREG_SET_ORDER ) ) {
			return;
		}

		$pairs = array_slice( $pairs, 0, $count );

		foreach ( $pairs as $i => $pair ) {
			$number = (int) $pair[1];
			$start  = (int) $pair[2];
			$end    = isset( $pairs[ $i + 1 ] ) ? (int) $pairs[ $i + 1 ][2] : strlen( $body );

			if ( isset( $this->objects[ $number ] ) ) {
				continue;
			}

			$this->objects[ $number ] = array(
				'dict'   => trim( substr( $body, $start, $end - $start ) ),
				'stream' => null,
			);
		}
	}

	/**
	 * Читает объект по смещению.
	 *
	 * @param int $offset Смещение.
	 * @return array{dict: string, stream: string|null}|null
	 */
	private function read_object_at( $offset ) {
		if ( $offset < 0 || $offset >= strlen( $this->data ) ) {
			return null;
		}

		$head = substr( $this->data, $offset, 40 );

		if ( ! preg_match( '/^\s*(\d+)\s+(\d+)\s+obj\b/', $head, $m ) ) {
			return null;
		}

		$start = $offset + strlen( $m[0] );
		$end   = null;
		$dict  = $this->read_dict_at( $start, $end );
		$pos   = null === $end ? $start + strlen( $dict ) : $end;

		$tail = substr( $this->data, $pos, 32 );

		if ( ! preg_match( '/^\s*stream\r?\n/', $tail, $s ) ) {
			return array(
				'dict'   => trim( $dict ),
				'stream' => null,
			);
		}

		$data_start = $pos + strlen( $s[0] );
		$length     = $this->resolve_number( $dict, 'Length', true );
		$stream     = null;

		if ( null !== $length && $length >= 0 ) {
			$stream = substr( $this->data, $data_start, $length );

			// Проверяем, что сразу за данными идёт endstream.
			$after = ltrim( substr( $this->data, $data_start + $length, 20 ) );

			if ( 0 !== strpos( $after, 'endstream' ) ) {
				$stream = null;
			}
		}

		if ( null === $stream ) {
			$end = strpos( $this->data, 'endstream', $data_start );

			if ( false === $end ) {
				$stream = substr( $this->data, $data_start );
			} else {
				$stream = substr( $this->data, $data_start, $end - $data_start );
				$stream = preg_replace( '/\r?\n$/', '', $stream );
			}
		}

		return array(
			'dict'   => trim( $dict ),
			'stream' => $stream,
		);
	}

	/**
	 * Возвращает сырой словарь, начиная со смещения.
	 *
	 * @param int $offset Смещение.
	 * @return string
	 */
	private function find_dict_raw( $offset ) {
		$pos = $offset;

		while ( $pos < strlen( $this->data ) && false !== strpos( " \r\n\t", $this->data[ $pos ] ) ) {
			++$pos;
		}

		if ( $pos >= strlen( $this->data ) ) {
			return '';
		}

		if ( '[' !== $this->data[ $pos ] ) {
			return $this->read_dict_at( $pos );
		}

		// Массив (например, /Contents [ 1 0 R 2 0 R ]).
		$depth = 0;

		for ( $i = $pos; $i < strlen( $this->data ); $i++ ) {
			$char = $this->data[ $i ];

			if ( '[' === $char ) {
				++$depth;
			} elseif ( ']' === $char ) {
				--$depth;

				if ( 0 === $depth ) {
					return substr( $this->data, $pos, $i - $pos + 1 );
				}
			}
		}

		return substr( $this->data, $pos );
	}

	/**
	 * Читает словарь с учётом вложенности.
	 *
	 * @param int      $offset Смещение.
	 * @param int|null $end    Позиция сразу после словаря.
	 * @return string
	 */
	private function read_dict_at( $offset, &$end = null ) {
		$data = $this->data;
		$len  = strlen( $data );
		$pos  = $offset;

		while ( $pos < $len && false !== strpos( " \r\n\t", $data[ $pos ] ) ) {
			++$pos;
		}

		if ( $pos + 1 >= $len || '<<' !== substr( $data, $pos, 2 ) ) {
			// Не словарь — читаем до конца строки (число, имя, ссылка).
			$line_end = strpos( $data, "\n", $pos );
			$end      = false === $line_end ? $pos : $line_end;

			return false === $line_end ? substr( $data, $pos, 200 ) : substr( $data, $pos, $line_end - $pos );
		}

		$depth = 0;

		for ( $i = $pos; $i < $len - 1; $i++ ) {
			$pair = substr( $data, $i, 2 );

			if ( '<<' === $pair ) {
				++$depth;
				++$i;
			} elseif ( '>>' === $pair ) {
				--$depth;
				++$i;

				if ( 0 === $depth ) {
					$end = $i + 1;

					return substr( $data, $pos, $end - $pos );
				}
			}
		}

		return substr( $data, $pos );
	}

	/**
	 * Числовое значение ключа словаря.
	 *
	 * @param string $dict   Словарь.
	 * @param string $key    Ключ.
	 * @param bool   $strict Вернуть null, если значения нет.
	 * @return int|null
	 */
	private function resolve_number( $dict, $key, $strict = false ) {
		if ( ! preg_match( '/\/' . preg_quote( $key, '/' ) . '\s+(\d+)(?:\s+\d+\s+R)?/', $dict, $m ) ) {
			return $strict ? null : 0;
		}

		$value = (int) $m[1];

		// Косвенная ссылка: значение лежит в отдельном объекте.
		if ( preg_match( '/\/' . preg_quote( $key, '/' ) . '\s+\d+\s+\d+\s+R/', $dict ) ) {
			$object = $this->object( $value );

			if ( null === $object ) {
				return $strict ? null : 0;
			}

			return (int) trim( $object['dict'] );
		}

		return $value;
	}

	/* --------------------------------------------------------------------- */
	/* Страницы                                                               */
	/* --------------------------------------------------------------------- */

	/**
	 * Номера объектов-страниц по порядку.
	 *
	 * @return int[]
	 */
	private function page_numbers() {
		if ( $this->pages ) {
			return $this->pages;
		}

		$root = $this->catalog_number();

		if ( $root ) {
			$catalog = $this->object( $root );

			if ( null !== $catalog && preg_match( '/\/Pages\s+(\d+)\s+\d+\s+R/', $catalog['dict'], $m ) ) {
				$this->walk_pages( (int) $m[1], 0 );
			}
		}

		// Запасной путь: ищем объекты /Type /Page напрямую.
		if ( ! $this->pages ) {
			foreach ( $this->offsets as $number => $offset ) {
				$object = $this->object( $number );

				if ( null !== $object && preg_match( '/\/Type\s*\/Page\b(?!s)/', $object['dict'] ) ) {
					$this->pages[] = $number;
				}
			}

			sort( $this->pages );
		}

		return $this->pages;
	}

	/**
	 * Рекурсивный обход дерева страниц.
	 *
	 * @param int $number Номер узла.
	 * @param int $depth  Глубина (защита от циклов).
	 */
	private function walk_pages( $number, $depth ) {
		if ( $depth > 32 || in_array( $number, $this->pages, true ) ) {
			return;
		}

		$node = $this->object( $number );

		if ( null === $node ) {
			return;
		}

		if ( preg_match( '/\/Type\s*\/Page\b(?!s)/', $node['dict'] ) ) {
			$this->pages[] = $number;
			return;
		}

		if ( ! preg_match( '/\/Kids\s*\[([^\]]*)\]/s', $node['dict'], $m ) ) {
			return;
		}

		foreach ( self::references( $m[1] ) as $kid ) {
			$this->walk_pages( $kid, $depth + 1 );
		}
	}

	/**
	 * Словарь страницы.
	 *
	 * @param int $index Номер страницы.
	 * @return string
	 * @throws RuntimeException Если страницы нет.
	 */
	private function page_dict( $index ) {
		$pages = $this->page_numbers();
		$index = (int) $index;

		if ( ! isset( $pages[ $index ] ) ) {
			throw new RuntimeException( 'В PDF нет страницы с таким номером.' );
		}

		$page = $this->object( $pages[ $index ] );

		if ( null === $page ) {
			throw new RuntimeException( 'Не удалось прочитать страницу PDF.' );
		}

		return $page['dict'];
	}

	/**
	 * Номер объекта каталога.
	 *
	 * @return int
	 */
	private function catalog_number() {
		if ( $this->root ) {
			return $this->root;
		}

		if ( preg_match( '/\/Root\s+(\d+)\s+\d+\s+R/', $this->trailer, $m ) ) {
			$this->root = (int) $m[1];
			return $this->root;
		}

		// Каталог мог попасть в объектный поток — ищем по типу.
		foreach ( $this->objects as $number => $object ) {
			if ( preg_match( '/\/Type\s*\/Catalog/', $object['dict'] ) ) {
				$this->root = (int) $number;
				return $this->root;
			}
		}

		foreach ( array_keys( $this->offsets ) as $number ) {
			$object = $this->object( $number );

			if ( null !== $object && preg_match( '/\/Type\s*\/Catalog/', $object['dict'] ) ) {
				$this->root = (int) $number;
				return $this->root;
			}
		}

		return 0;
	}

	/**
	 * Наследуемое значение страницы (/Resources, /MediaBox, /Rotate).
	 *
	 * @param string $page_dict Словарь страницы.
	 * @param string $key       Ключ.
	 * @return string
	 */
	private function inherited( $page_dict, $key ) {
		if ( preg_match( '/\/' . $key . '\b\s*(\[\s*[^\]]*\]|\d+\s+\d+\s+R|-?\d+(?:\.\d+)?|<<)/', $page_dict, $m ) ) {
			$value = $m[1];

			if ( '<<' === $value ) {
				$start = strpos( $page_dict, $m[0] );
				return $this->read_nested_dict( $page_dict, $start );
			}

			return $value;
		}

		// Поиск в родителях.
		$current = $page_dict;
		$guard   = 0;

		while ( $guard < 32 ) {
			++$guard;

			if ( ! preg_match( '/\/Parent\s+(\d+)\s+\d+\s+R/', $current, $m ) ) {
				break;
			}

			$parent = $this->object( (int) $m[1] );

			if ( null === $parent ) {
				break;
			}

			$current = $parent['dict'];

			if ( preg_match( '/\/' . $key . '\b\s*(\[\s*[^\]]*\]|\d+\s+\d+\s+R|-?\d+(?:\.\d+)?|<<)/', $current, $found ) ) {
				if ( '<<' === $found[1] ) {
					$start = strpos( $current, $found[0] );

					return $this->read_nested_dict( $current, $start );
				}

				return $found[1];
			}
		}

		if ( '/MediaBox' === $key ) {
			return '[ 0 0 595.28 841.89 ]';
		}

		return '';
	}

	/**
	 * Достаёт вложенный словарь из строки.
	 *
	 * @param string $haystack Строка.
	 * @param int    $offset   Позиция «/Key <<».
	 * @return string
	 */
	private function read_nested_dict( $haystack, $offset ) {
		$pos = strpos( $haystack, '<<', $offset );

		if ( false === $pos ) {
			return '<< >>';
		}

		$depth = 0;
		$len   = strlen( $haystack );

		for ( $i = $pos; $i < $len - 1; $i++ ) {
			$pair = substr( $haystack, $i, 2 );

			if ( '<<' === $pair ) {
				++$depth;
				++$i;
			} elseif ( '>>' === $pair ) {
				--$depth;
				++$i;

				if ( 0 === $depth ) {
					return substr( $haystack, $pos, $i - $pos + 1 );
				}
			}
		}

		return substr( $haystack, $pos );
	}

	/**
	 * Содержимое страницы (склеенное, если массив потоков).
	 *
	 * @param string $page_dict Словарь страницы.
	 * @return string
	 */
	private function page_content( $page_dict ) {
		if ( ! preg_match( '/\/Contents\s*(\[[^\]]*\]|\d+\s+\d+\s+R)/s', $page_dict, $m ) ) {
			return '';
		}

		$raw   = trim( $m[1] );
		$parts = array();

		$numbers = preg_match( '/^\[/', $raw )
			? self::references( $raw )
			: ( preg_match( '/^(\d+)/', $raw, $one ) ? array( (int) $one[1] ) : array() );

		foreach ( $numbers as $number ) {
			$object = $this->object( $number );

			if ( null === $object || null === $object['stream'] ) {
				continue;
			}

			$decoded = $this->decode_stream( $object['dict'], $object['stream'] );

			$parts[] = null === $decoded ? $object['stream'] : $decoded;
		}

		return implode( "\n", $parts );
	}

	/* --------------------------------------------------------------------- */
	/* Фильтры                                                                */
	/* --------------------------------------------------------------------- */

	/**
	 * Раскрывает FlateDecode (в том числе с предиктором PNG).
	 *
	 * @param string $dict   Словарь объекта.
	 * @param string $stream Данные.
	 * @return string|null
	 */
	private function decode_stream( $dict, $stream ) {
		if ( null === $stream ) {
			return null;
		}

		$filtered = preg_match( '/\/Filter\s*(\[[^\]]*\]|\/\w+)/', $dict, $m );

		if ( ! $filtered ) {
			return $stream;
		}

		if ( false === stripos( $m[1], 'FlateDecode' ) && false === stripos( $m[1], 'Fl ' ) ) {
			return null;
		}

		$raw = $this->inflate( $stream );

		if ( null === $raw ) {
			return null;
		}

		if ( preg_match( '/\/Predictor\s+(\d+)/', $dict, $p ) && (int) $p[1] >= 10 ) {
			$columns = preg_match( '/\/Columns\s+(\d+)/', $dict, $c ) ? (int) $c[1] : 1;
			$colors  = preg_match( '/\/Colors\s+(\d+)/', $dict, $cc ) ? (int) $cc[1] : 1;
			$bpc     = preg_match( '/\/BitsPerComponent\s+(\d+)/', $dict, $b ) ? (int) $b[1] : 8;

			$raw = self::undo_png_predictor( $raw, $colors * $columns * $bpc / 8, $colors * $bpc / 8 );
		}

		return $raw;
	}

	/**
	 * Распаковка zlib.
	 *
	 * @param string $data Данные.
	 * @return string|null
	 */
	private function inflate( $data ) {
		if ( function_exists( 'gzuncompress' ) ) {
			$out = @gzuncompress( $data ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

			if ( false !== $out ) {
				return $out;
			}
		}

		if ( function_exists( 'gzinflate' ) ) {
			$trimmed = substr( $data, 2 );
			$out     = @gzinflate( $trimmed ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

			if ( false !== $out ) {
				return $out;
			}
		}

		return null;
	}

	/**
	 * Снимает PNG-предиктор (тип 12 / 15).
	 *
	 * @param string $data    Данные.
	 * @param int    $row     Длина строки.
	 * @param int    $bpp     Байт на пиксель.
	 * @return string
	 */
	private static function undo_png_predictor( $data, $row, $bpp ) {
		$row = max( 1, (int) $row );
		$bpp = max( 1, (int) $bpp );

		$out    = '';
		$length = strlen( $data );
		$prev   = str_repeat( "\0", $row );
		$pos    = 0;

		while ( $pos + $row + 1 <= $length ) {
			$type    = ord( $data[ $pos ] );
			$current = substr( $data, $pos + 1, $row );
			$pos    += $row + 1;

			for ( $i = 0; $i < $row; $i++ ) {
				$left  = $i >= $bpp ? ord( $current[ $i - $bpp ] ) : 0;
				$up    = ord( $prev[ $i ] );
				$value = ord( $current[ $i ] );

				switch ( $type ) {
					case 1:
						$value = ( $value + $left ) & 0xFF;
						break;
					case 2:
						$value = ( $value + $up ) & 0xFF;
						break;
					case 3:
						$value = ( $value + (int) floor( ( $left + $up ) / 2 ) ) & 0xFF;
						break;
					case 4:
						$up_left = $i >= $bpp ? ord( $prev[ $i - $bpp ] ) : 0;
						$p       = $left + $up - $up_left;
						$pa      = abs( $p - $left );
						$pb      = abs( $p - $up );
						$pc      = abs( $p - $up_left );
						$paeth   = ( $pa <= $pb && $pa <= $pc ) ? $left : ( $pb <= $pc ? $up : $up_left );

						$value = ( $value + $paeth ) & 0xFF;
						break;
				}

				$current[ $i ] = chr( $value );
			}

			$out .= $current;
			$prev = $current;
		}

		return $out;
	}

	/* --------------------------------------------------------------------- */
	/* Прочее                                                                 */
	/* --------------------------------------------------------------------- */

	/**
	 * Зашифрован ли документ.
	 *
	 * @return bool
	 */
	private function is_encrypted() {
		if ( preg_match( '/\/Encrypt\s+\d+\s+\d+\s+R/', $this->trailer ) ) {
			return true;
		}

		// Трейлер мог не разобраться (битый xref) — смотрим хвост файла.
		$tail = substr( $this->data, -4096 );

		return (bool) preg_match( '/\/Encrypt\s*<<|\/Encrypt\s+\d+\s+\d+\s+R/', $tail );
	}

	/**
	 * Разбирает /MediaBox.
	 *
	 * @param string $raw Значение.
	 * @return float[]
	 */
	private static function parse_box( $raw ) {
		$raw = trim( $raw );

		if ( preg_match( '/^(\d+)\s+\d+\s+R$/', $raw, $m ) ) {
			return array( 0.0, 0.0, 595.28, 841.89 );
		}

		$numbers = array();

		if ( preg_match_all( '/-?\d+(?:\.\d+)?/', $raw, $m ) ) {
			foreach ( $m[0] as $value ) {
				$numbers[] = (float) $value;
			}
		}

		if ( count( $numbers ) < 4 ) {
			return array( 0.0, 0.0, 595.28, 841.89 );
		}

		return array( $numbers[0], $numbers[1], $numbers[2], $numbers[3] );
	}
}
