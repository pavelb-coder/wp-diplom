<?php
/**
 * Регистрация возможностей темы, меню, областей виджетов.
 *
 * @package WP_Diplom
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Базовая настройка темы.
 */
function wp_diplom_setup() {
	load_theme_textdomain( 'wp-diplom', WP_DIPLOM_DIR . '/languages' );

	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'custom-logo', array(
		'height'      => 64,
		'width'       => 220,
		'flex-height' => true,
		'flex-width'  => true,
	) );
	add_theme_support( 'html5', array(
		'search-form',
		'comment-form',
		'comment-list',
		'gallery',
		'caption',
		'style',
		'script',
	) );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'wp-block-styles' );

	add_editor_style( 'assets/css/main.css' );

	add_image_size( 'wp-diplom-card', 720, 420, true );
	add_image_size( 'wp-diplom-hero', 1600, 720, true );

	register_nav_menus( array(
		'primary' => esc_html__( 'Главное меню', 'wp-diplom' ),
		'footer'  => esc_html__( 'Меню в подвале', 'wp-diplom' ),
	) );

	add_theme_support( 'custom-background', array(
		'default-color' => 'f6f7fb',
	) );
}
add_action( 'after_setup_theme', 'wp_diplom_setup' );

/**
 * Контентная ширина.
 */
function wp_diplom_content_width() {
	$GLOBALS['content_width'] = apply_filters( 'wp_diplom_content_width', 800 );
}
add_action( 'after_setup_theme', 'wp_diplom_content_width', 0 );

/**
 * Области виджетов.
 */
function wp_diplom_widgets_init() {
	register_sidebar( array(
		'name'          => esc_html__( 'Боковая колонка', 'wp-diplom' ),
		'id'            => 'sidebar-1',
		'description'   => esc_html__( 'Отображается на страницах блога и архивов.', 'wp-diplom' ),
		'before_widget' => '<section id="%1$s" class="widget %2$s">',
		'after_widget'  => '</section>',
		'before_title'  => '<h2 class="widget__title">',
		'after_title'   => '</h2>',
	) );

	register_sidebar( array(
		'name'          => esc_html__( 'Подвал (3 колонки)', 'wp-diplom' ),
		'id'            => 'footer-1',
		'description'   => esc_html__( 'Виджеты в подвале сайта.', 'wp-diplom' ),
		'before_widget' => '<section id="%1$s" class="widget %2$s">',
		'after_widget'  => '</section>',
		'before_title'  => '<h3 class="widget__title">',
		'after_title'   => '</h3>',
	) );
}
add_action( 'widgets_init', 'wp_diplom_widgets_init' );
