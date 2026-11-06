<?php
/**
 * Рендерер сертификата: конфигурация бланка + данные → PDF.
 *
 * Класс намеренно не обращается к WordPress: на вход приходят обычные массивы,
 * на выходе — бинарный PDF. Благодаря этому рендер тестируется автономно
 * (см. tests/render-test.php) и переиспользуется в CLI, cron и REST.
 *
 * @package WP_Diplom
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPDC_PDF_STANDALONE' ) ) {
	exit;
}

require_once __DIR__ . '/pdf/class-wpdc-pdf-writer.php';

/**
 * Сборка PDF сертификата.
 */
class WPDC_Renderer {

	/**
	 * Размеры страниц в миллиметрах.
	 *
	 * @var array<string, float[]>
	 */
	const PAGE_SIZES = array(
		'a4_landscape'     => array( 297.0, 210.0 ),
		'a4_portrait'      => array( 210.0, 297.0 ),
		'a5_landscape'     => array( 210.0, 148.0 ),
		'a5_portrait'      => array( 148.0, 210.0 ),
		'letter_landscape' => array( 279.4, 215.9 ),
		'letter_portrait'  => array( 215.9, 279.4 ),
	);

	/**
	 * Путь к каталогу со шрифтами.
	 *
	 * @var string
	 */
	private $fonts_dir;

	/**
	 * Конструктор.
	 *
	 * @param string|null $fonts_dir Каталог шрифтов.
	 */
	public function __construct( $fonts_dir = null ) {
		$this->fonts_dir = $fonts_dir ? $fonts_dir : dirname( __DIR__ ) . '/assets/fonts';
	}

	/**
	 * Доступные размеры страниц.
	 *
	 * @return array<string, string>
	 */
	public static function page_size_options() {
		return array(
			'a4_landscape'     => self::t( 'A4, альбомная (297×210 мм)' ),
			'a4_portrait'      => self::t( 'A4, книжная (210×297 мм)' ),
			'a5_landscape'     => self::t( 'A5, альбомная (210×148 мм)' ),
			'a5_portrait'      => self::t( 'A5, книжная (148×210 мм)' ),
			'letter_landscape' => self::t( 'Letter, альбомная (279×216 мм)' ),
			'letter_portrait'  => self::t( 'Letter, книжная (216×279 мм)' ),
			'pdf'              => self::t( 'По размеру PDF-шаблона' ),
		);
	}

	/**
	 * Размеры страницы в миллиметрах.
	 *
	 * @param string $key Ключ размера.
	 * @return float[]
	 */
	public static function page_size( $key, array $template = array() ) {
		if ( 'pdf' === $key ) {
			$width  = isset( $template['pdf_width_mm'] ) ? (float) $template['pdf_width_mm'] : 0.0;
			$height = isset( $template['pdf_height_mm'] ) ? (float) $template['pdf_height_mm'] : 0.0;

			if ( $width > 0 && $height > 0 ) {
				return array( $width, $height );
			}

			return self::PAGE_SIZES['a4_landscape'];
		}

		return self::PAGE_SIZES[ $key ] ?? self::PAGE_SIZES['a4_landscape'];
	}

	/**
	 * Рендерит PDF.
	 *
	 * @param array<string, mixed> $template Конфигурация бланка.
	 * @param array<string, mixed> $data     Данные сертификата.
	 * @return string Бинарный PDF.
	 */
	public function render( array $template, array $data ) {
		$template = self::normalize_template( $template );
		$data     = self::normalize_data( $data );

		// Название курса можно задать в бланке — тогда оно подставляется в
		// {course_title}, если в карточке сертификата поле не заполнено.
		if ( '' === trim( (string) $data['course_title'] ) && class_exists( 'WPDC_Texts' ) ) {
			$texts = WPDC_Texts::resolve( $template['texts'] );

			if ( ! empty( $texts['course_title'] ) ) {
				$data['course_title'] = $texts['course_title'];
			}
		}

		list( $width_mm, $height_mm ) = self::page_size( $template['page'], $template );

		$pdf = new WPDC_PDF_Writer( $this->fonts() );

		if ( ! $pdf->has_fonts() ) {
			throw new RuntimeException( 'Не найдены файлы шрифтов в ' . $this->fonts_dir );
		}

		$mm = WPDC_PDF_Writer::MM_TO_PT;

		$pdf->set_meta(
			array(
				// translators: 1: тип документа, 2: имя получателя.
				'Title'   => sprintf( '%1$s — %2$s', $template['document_title'], $data['recipient_name'] ),
				'Author'  => $template['organization'],
				'Subject' => $data['course_title'],
				'Creator' => 'WP Diplom',
			)
		);

		$pdf->add_page( $width_mm * $mm, $height_mm * $mm );

		// Фон: заливка + подложка.
		$pdf->set_fill_color( $template['background_color'] );
		$pdf->rect( 0, 0, $width_mm * $mm, $height_mm * $mm, 'F' );

		$has_pdf = $template['pdf'] && is_readable( $template['pdf'] );

		if ( $has_pdf ) {
			$pdf->pdf_page(
				$template['pdf'],
				$template['pdf_page'],
				0,
				0,
				$width_mm * $mm,
				$height_mm * $mm,
				'stretch' === $template['background_fit'] ? 'stretch' : 'contain'
			);
		} elseif ( $template['background'] && is_readable( $template['background'] ) ) {
			$pdf->image(
				$template['background'],
				0,
				0,
				$width_mm * $mm,
				$height_mm * $mm,
				$template['background_fit']
			);
		}

		// Рамка.
		if ( $template['border']['enabled'] ) {
			$border = $template['border'];

			$pdf->set_stroke_color( $border['color'] );
			$pdf->set_line_width( $border['width'] * $mm );

			$pdf->rect(
				$border['inset_x'] * $mm,
				$border['inset_y'] * $mm,
				( $width_mm - 2 * $border['inset_x'] ) * $mm,
				( $height_mm - 2 * $border['inset_y'] ) * $mm,
				'S'
			);
		}

		// Поля.
		foreach ( $template['fields'] as $field ) {
			$text = self::replace_placeholders( $field['text'], $data );

			if ( $field['uppercase'] ) {
				$text = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $text, 'UTF-8' ) : strtoupper( $text );
			}

			if ( '' === trim( $text ) && ! $field['keep_empty'] ) {
				continue;
			}

			$pdf->text(
				$text,
				$field['x'] * $mm,
				$field['y'] * $mm,
				$field['w'] * $mm,
				$field['h'] * $mm,
				array(
					'family'      => $field['font'],
					'style'       => $field['style'],
					'size'        => $field['size'],
					'color'       => $field['color'],
					'align'       => $field['align'],
					'line_height' => $field['line_height'],
					'valign'      => $field['valign'],
					'wrap'        => true,
				)
			);
		}

		return $pdf->output();
	}

	/**
	 * Шрифтовые семейства.
	 *
	 * @return array<string, array<string, string>>
	 */
	private function fonts() {
		return array(
			'sans'  => array(
				'regular' => $this->fonts_dir . '/DejaVuSans.ttf',
				'bold'    => $this->fonts_dir . '/DejaVuSans-Bold.ttf',
			),
			'serif' => array(
				'regular' => $this->fonts_dir . '/DejaVuSerif.ttf',
				'bold'    => $this->fonts_dir . '/DejaVuSerif.ttf',
			),
		);
	}

	/**
	 * Перевод с безопасным откатом, когда WordPress не загружен.
	 *
	 * @param string $text Текст.
	 * @return string
	 */
	private static function t( $text ) {
		return function_exists( '__' ) ? __( $text, 'wp-diplom' ) : $text;
	}

	/**
	 * Приводит конфигурацию бланка к полному виду.
	 *
	 * @param array<string, mixed> $template Конфигурация.
	 * @return array<string, mixed>
	 */
	public static function normalize_template( array $template ) {
		$template = array_merge(
			array(
				'page'              => 'a4_landscape',
				'pdf'               => '',
				'pdf_page'          => 0,
				'pdf_width_mm'      => 0.0,
				'pdf_height_mm'     => 0.0,
				'background'        => '',
				'background_color'  => '#ffffff',
				'background_fit'    => 'cover',
				'organization'      => '',
				'document_title'    => 'Сертификат',
				'border'            => array(),
				'fields'            => array(),
				'texts'             => array(),
			),
			$template
		);

		$template['border'] = array_merge(
			array(
				'enabled' => true,
				'color'   => '#2f6fed',
				'width'   => 0.8,
				'inset_x' => 12,
				'inset_y' => 12,
			),
			(array) $template['border']
		);

		if ( 'pdf' !== $template['page'] && ! isset( self::PAGE_SIZES[ $template['page'] ] ) ) {
			$template['page'] = 'a4_landscape';
		}

		$template['pdf_page'] = max( 0, (int) $template['pdf_page'] );

		if ( ! in_array( $template['background_fit'], array( 'cover', 'contain', 'stretch' ), true ) ) {
			$template['background_fit'] = 'cover';
		}

		// Явно заданные тексты (настройки или переопределение бланка) заменяют
		// текст стандартного поля. Поля с нестандартным id не трогаем.
		$explicit = array();

		if ( class_exists( 'WPDC_Texts' ) ) {
			$override = isset( $template['texts'] ) && is_array( $template['texts'] ) ? $template['texts'] : array();
			$explicit = WPDC_Texts::resolve( $override );
		}

		$fields = array();

		foreach ( (array) $template['fields'] as $field ) {
			$merged = array_merge(
				array(
					'id'          => '',
					'text'        => '',
					'x'           => 20,
					'y'           => 20,
					'w'           => 100,
					'h'           => 20,
					'size'        => 14,
					'font'        => 'sans',
					'style'       => 'regular',
					'color'       => '#141a2b',
					'align'       => 'center',
					'line_height' => 1.25,
					'valign'      => 'middle',
					'uppercase'   => false,
					'keep_empty'  => false,
				),
				(array) $field
			);

			$slot = (string) $merged['id'];

			if ( '' !== $slot && isset( $explicit[ $slot ] ) ) {
				$merged['text'] = $explicit[ $slot ];
			}

			$fields[] = $merged;
		}

		$template['fields'] = $fields;

		return $template;
	}

	/**
	 * Приводит данные к полному виду (все плейсхолдеры определены).
	 *
	 * @param array<string, mixed> $data Данные.
	 * @return array<string, mixed>
	 */
	public static function normalize_data( array $data ) {
		$defaults = array(
			'recipient_name'  => '',
			'recipient_email' => '',
			'course_title'    => '',
			'course_hours'    => '',
			'teacher'         => '',
			'date'            => '',
			'number'          => '',
			'organization'    => '',
			'site_name'       => '',
			'code'            => '',
		);

		$data = array_merge( $defaults, $data );

		// Дополнительные поля сертификата: custom_ключ.
		$custom = isset( $data['custom'] ) && is_array( $data['custom'] ) ? $data['custom'] : array();

		foreach ( $custom as $key => $value ) {
			$data[ 'custom_' . $key ] = $value;
		}

		return $data;
	}

	/**
	 * Подставляет значения в текст поля.
	 *
	 * @param string               $text Текст с плейсхолдерами вида {recipient_name}.
	 * @param array<string, mixed> $data Данные.
	 * @return string
	 */
	public static function replace_placeholders( $text, array $data ) {
		return preg_replace_callback(
			'/\{([a-z0-9_]+)\}/i',
			static function ( $matches ) use ( $data ) {
				$key = strtolower( $matches[1] );

				return isset( $data[ $key ] ) ? (string) $data[ $key ] : '';
			},
			(string) $text
		);
	}

	/**
	 * Список поддерживаемых плейсхолдеров с описанием.
	 *
	 * @return array<string, string>
	 */
	public static function placeholders() {
		return array(
			'{recipient_name}'  => __( 'Имя получателя', 'wp-diplom' ),
			'{recipient_email}' => __( 'E-mail получателя', 'wp-diplom' ),
			'{course_title}'    => __( 'Название курса/программы', 'wp-diplom' ),
			'{course_hours}'    => __( 'Количество часов', 'wp-diplom' ),
			'{teacher}'         => __( 'Преподаватель', 'wp-diplom' ),
			'{date}'            => __( 'Дата выдачи', 'wp-diplom' ),
			'{number}'          => __( 'Номер сертификата', 'wp-diplom' ),
			'{organization}'    => __( 'Название организации', 'wp-diplom' ),
			'{site_name}'       => __( 'Название сайта', 'wp-diplom' ),
			'{custom_КЛЮЧ}'     => __( 'Дополнительное поле сертификата', 'wp-diplom' ),
		);
	}

	/**
	 * Поля бланка по умолчанию (для A4 landscape).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function default_fields() {
		// По умолчанию в бланке одно поле — имя получателя. Остальные
		// добавляются кнопкой «+ Добавить свой блок» в редакторе бланка.
		return array(
			array(
				'id'        => 'name',
				'text'      => '{recipient_name}',
				'x'         => 20,
				'y'         => 90,
				'w'         => 257,
				'h'         => 20,
				'size'      => 30,
				'font'      => 'serif',
				'style'     => 'bold',
				'color'     => '#141a2b',
				'align'     => 'center',
				'uppercase' => false,
			),
		);
	}

	/**
	 * Валидирует и нормализует набор полей из админки/импорта.
	 *
	 * @param array<int, array<string, mixed>> $fields Поля.
	 * @return array<int, array<string, mixed>>
	 */
	public static function sanitize_fields( $fields ) {
		$clean = array();

		foreach ( (array) $fields as $index => $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$clean[] = array(
				'id'          => sanitize_key( $field['id'] ?? 'field_' . $index ),
				'text'        => wp_kses_post( (string) ( $field['text'] ?? '' ) ),
				'x'           => self::clamp( $field['x'] ?? 20, 0, 2000 ),
				'y'           => self::clamp( $field['y'] ?? 20, 0, 2000 ),
				'w'           => self::clamp( $field['w'] ?? 100, 1, 2000 ),
				'h'           => self::clamp( $field['h'] ?? 20, 0, 2000 ),
				'size'        => self::clamp( $field['size'] ?? 14, 4, 200 ),
				'font'        => in_array( $field['font'] ?? 'sans', array( 'sans', 'serif' ), true ) ? $field['font'] : 'sans',
				'style'       => in_array( $field['style'] ?? 'regular', array( 'regular', 'bold' ), true ) ? $field['style'] : 'regular',
				'color'       => self::sanitize_color( $field['color'] ?? '#000000' ),
				'align'       => in_array( $field['align'] ?? 'center', array( 'left', 'center', 'right' ), true ) ? $field['align'] : 'center',
				'line_height' => self::clamp( $field['line_height'] ?? 1.25, 0.8, 3 ),
				'valign'      => in_array( $field['valign'] ?? 'middle', array( 'top', 'middle', 'bottom' ), true ) ? $field['valign'] : 'middle',
				'uppercase'   => ! empty( $field['uppercase'] ),
				'keep_empty'  => ! empty( $field['keep_empty'] ),
			);
		}

		return $clean;
	}

	/**
	 * Приводит число к диапазону.
	 *
	 * @param mixed $value Значение.
	 * @param float $min   Минимум.
	 * @param float $max   Максимум.
	 * @return float
	 */
	private static function clamp( $value, $min, $max ) {
		$value = (float) $value;

		return max( $min, min( $max, $value ) );
	}

	/**
	 * Проверяет HEX-цвет.
	 *
	 * @param string $color Цвет.
	 * @return string
	 */
	public static function sanitize_color( $color ) {
		$color = ltrim( (string) $color, '#' );

		if ( 3 === strlen( $color ) && ctype_xdigit( $color ) ) {
			return '#' . $color;
		}

		return ( 6 === strlen( $color ) && ctype_xdigit( $color ) ) ? '#' . $color : '#000000';
	}
}
