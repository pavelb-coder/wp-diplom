<?php
/**
 * Настройки темы в Customizer.
 *
 * @package WP_Diplom
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Регистрация настроек.
 *
 * @param WP_Customize_Manager $wp_customize Менеджер кастомайзера.
 */
function wp_diplom_customize_register( $wp_customize ) {
	$wp_customize->add_section( 'wp_diplom_hero', array(
		'title'       => esc_html__( 'Главный блок', 'wp-diplom' ),
		'priority'    => 30,
		'description' => esc_html__( 'Заголовок и подзаголовок на главной странице.', 'wp-diplom' ),
	) );

	$wp_customize->add_setting( 'hero_title', array(
		'default'           => esc_html__( 'Дипломный проект на WordPress', 'wp-diplom' ),
		'sanitize_callback' => 'sanitize_text_field',
		'transport'         => 'refresh',
	) );
	$wp_customize->add_control( 'hero_title', array(
		'label'   => esc_html__( 'Заголовок', 'wp-diplom' ),
		'section' => 'wp_diplom_hero',
		'type'    => 'text',
	) );

	$wp_customize->add_setting( 'hero_subtitle', array(
		'default'           => esc_html__( 'Адаптивная тема с кастомными типами записей и поддержкой редактора блоков.', 'wp-diplom' ),
		'sanitize_callback' => 'sanitize_textarea_field',
	) );
	$wp_customize->add_control( 'hero_subtitle', array(
		'label'   => esc_html__( 'Подзаголовок', 'wp-diplom' ),
		'section' => 'wp_diplom_hero',
		'type'    => 'textarea',
	) );

	$wp_customize->add_setting( 'footer_text', array(
		'default'           => '',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'footer_text', array(
		'label'   => esc_html__( 'Текст в подвале', 'wp-diplom' ),
		'section' => 'title_tagline',
		'type'    => 'text',
	) );

	$wp_customize->add_setting( 'accent_color', array(
		'default'           => '#2f6fed',
		'sanitize_callback' => 'sanitize_hex_color',
	) );
	$wp_customize->add_control(
		new WP_Customize_Color_Control(
			$wp_customize,
			'accent_color',
			array(
				'label'   => esc_html__( 'Акцентный цвет', 'wp-diplom' ),
				'section' => 'colors',
			)
		)
	);
}
add_action( 'customize_register', 'wp_diplom_customize_register' );

/**
 * Вывод акцентного цвета в CSS-переменную.
 */
function wp_diplom_accent_css() {
	$color = get_theme_mod( 'accent_color', '#2f6fed' );
	if ( ! $color ) {
		return;
	}
	printf(
		'<style id="wp-diplom-accent">:root{--accent:%1$s;}</style>' . "\n",
		esc_attr( $color )
	);
}
add_action( 'wp_head', 'wp_diplom_accent_css', 20 );
