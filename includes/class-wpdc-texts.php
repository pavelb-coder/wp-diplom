<?php
/**
 * Тексты сертификата: стандартные фразы, которые подставляются в бланк.
 *
 * Одна и та же фраза нужна в трёх местах: в настройках (общая для всех
 * бланков), в конкретном бланке (переопределение) и как запасной вариант в
 * самом поле бланка (так было до 1.3.0). Здесь описан порядок старшинства,
 * чтобы поведение было предсказуемым:
 *
 *   1. переопределение бланка  (_wpdc_texts)
 *   2. общие настройки         (wpdc_settings['texts'])
 *   3. текст самого поля       (_wpdc_fields[..]['text'])
 *   4. встроенное значение по умолчанию
 *
 * @package WP_Diplom
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPDC_PDF_STANDALONE' ) ) {
	exit;
}

/**
 * Реестр стандартных текстов.
 */
class WPDC_Texts {

	/**
	 * Слоты: ключ совпадает с id стандартного поля бланка.
	 *
	 * @var array<string, string>
	 */
	const SLOTS = array(
		'org'       => 'Строка организации',
		'title'     => 'Заголовок документа',
		'subtitle'  => 'Вводная фраза',
		'course'    => 'Фраза об обучении',
		'date'      => 'Дата выдачи',
		'number'    => 'Номер документа',
		'signature' => 'Подпись',
		'verify'    => 'Проверка подлинности',
		'course_title' => 'Название курса/программы',
	);

	/**
	 * Слоты-значения: не рисуются как отдельная фраза, а подставляются в
	 * одноимённый плейсхолдер, когда в карточке сертификата поле пустое.
	 *
	 * @var string[]
	 */
	const VALUE_SLOTS = array( 'course_title' );

	/**
	 * Слот — это значение, а не фраза бланка.
	 *
	 * @param string $slot Слот.
	 * @return bool
	 */
	public static function is_value_slot( $slot ) {
		return in_array( (string) $slot, self::VALUE_SLOTS, true );
	}

	/**
	 * Встроенные значения по умолчанию.
	 *
	 * @return array<string, string>
	 */
	public static function defaults() {
		return array(
			'org'       => '{organization}',
			'title'     => 'СЕРТИФИКАТ',
			'subtitle'  => 'настоящим подтверждается, что',
			'course'    => 'успешно завершил(а) обучение по программе «{course_title}» объёмом {course_hours} академических часов',
			'date'      => 'Дата выдачи: {date}',
			'number'    => '№ {number}',
			'signature' => 'Подпись: ________________',
			'verify'    => 'Проверить подлинность: {code}',
			'course_title' => '',
		);
	}

	/**
	 * Готовые варианты формулировок для подсказки в интерфейсе.
	 *
	 * @return array<string, string[]>
	 */
	public static function presets() {
		return array(
			'title'     => array(
				'СЕРТИФИКАТ',
				'ДИПЛОМ',
				'СВИДЕТЕЛЬСТВО',
				'СЕРТИФИКАТ УЧАСТНИКА',
			),
			'subtitle'  => array(
				'настоящим подтверждается, что',
				'выдан участнику',
				'настоящий документ удостоверяет, что',
			),
			'course'    => array(
				'успешно завершил(а) обучение по программе «{course_title}» объёмом {course_hours} академических часов',
				'прошёл(ла) обучение по программе «{course_title}» в объёме {course_hours} часов',
				'освоил(а) программу «{course_title}», {course_hours} ак. часов',
				'принял(а) участие в программе «{course_title}» ({course_hours} часов)',
			),
			'date'      => array(
				'Дата выдачи: {date}',
				'{date}',
				'Выдан {date}',
			),
			'number'    => array(
				'№ {number}',
				'Регистрационный номер: {number}',
			),
			'signature' => array(
				'Подпись: ________________',
				'Директор ________________',
				'Руководитель программы ________________',
			),
			'verify'    => array(
				'Проверить подлинность: {code}',
				'Код проверки: {code}',
				'Проверка: {verify_url}',
			),
		);
	}

	/**
	 * Тексты, явно заданные в общих настройках.
	 *
	 * Незаполненные слоты сюда не попадают: тогда действует текст самого поля
	 * бланка — так шаблоны, настроенные до 1.3.0, продолжают работать как есть.
	 *
	 * @return array<string, string>
	 */
	public static function global_texts() {
		$settings = function_exists( 'get_option' ) ? get_option( 'wpdc_settings', array() ) : array();
		$stored   = is_array( $settings ) && isset( $settings['texts'] ) && is_array( $settings['texts'] )
			? $settings['texts']
			: array();

		$texts = array();

		foreach ( array_keys( self::SLOTS ) as $slot ) {
			$value = isset( $stored[ $slot ] ) ? (string) $stored[ $slot ] : '';

			if ( '' !== trim( $value ) ) {
				$texts[ $slot ] = $value;
			}
		}

		return $texts;
	}

	/**
	 * Итоговые явные тексты: общие настройки, поверх них — бланк.
	 *
	 * @param array<string, string> $override Переопределения бланка.
	 * @return array<string, string>
	 */
	public static function resolve( array $override = array() ) {
		$texts = self::global_texts();

		foreach ( array_keys( self::SLOTS ) as $slot ) {
			if ( isset( $override[ $slot ] ) && '' !== trim( (string) $override[ $slot ] ) ) {
				$texts[ $slot ] = (string) $override[ $slot ];
			}
		}

		return $texts;
	}

	/**
	 * Действующий текст слота — то, что видно в интерфейсе.
	 *
	 * @param string                $slot     Слот.
	 * @param array<string, string> $override Переопределения бланка.
	 * @param string                $fallback Запасное значение (текст поля).
	 * @return string
	 */
	public static function effective( $slot, array $override = array(), $fallback = '' ) {
		$texts = self::resolve( $override );

		if ( isset( $texts[ $slot ] ) ) {
			return $texts[ $slot ];
		}

		if ( '' !== trim( (string) $fallback ) ) {
			return (string) $fallback;
		}

		$defaults = self::defaults();

		return isset( $defaults[ $slot ] ) ? $defaults[ $slot ] : '';
	}

	/**
	 * Есть ли слот для этого идентификатора поля.
	 *
	 * @param string $id Идентификатор поля.
	 * @return bool
	 */
	public static function is_slot( $id ) {
		return isset( self::SLOTS[ $id ] );
	}

	/**
	 * Очистка набора текстов (форма приходит из админки).
	 *
	 * @param mixed $raw Сырые данные.
	 * @return array<string, string>
	 */
	public static function sanitize( $raw ) {
		$clean = array();

		if ( ! is_array( $raw ) ) {
			return $clean;
		}

		foreach ( array_keys( self::SLOTS ) as $slot ) {
			if ( ! isset( $raw[ $slot ] ) ) {
				continue;
			}

			// Текст многострочный, но без HTML: бланк рисует шрифт, а не разметку.
			$value = sanitize_textarea_field( (string) $raw[ $slot ] );

			if ( '' !== trim( $value ) ) {
				$clean[ $slot ] = $value;
			}
		}

		return $clean;
	}

	/**
	 * Демонстрационные значения для предпросмотра.
	 *
	 * @return array<string, string>
	 */
	public static function sample_values() {
		return array(
			'recipient_name' => 'Иванов Иван Иванович',
			'course_title'   => 'Информационные технологии в образовании',
			'course_hours'   => '72',
			'date'           => date_i18n( 'd.m.Y' ),
			'number'         => 'WPD-' . gmdate( 'Y' ) . '-0001',
			'organization'   => get_bloginfo( 'name' ),
			'code'           => 'a1b2c3d4e5f6',
			'verify_url'     => home_url( '/certificate/a1b2c3d4e5f6/' ),
			'site_name'      => get_bloginfo( 'name' ),
		);
	}
}
