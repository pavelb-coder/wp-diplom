<?php
/**
 * Кастомный тип записи «Проект» и таксономия «Технологии».
 *
 * @package WP_Diplom
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Регистрация CPT.
 */
function wp_diplom_register_post_types() {
	$labels = array(
		'name'               => esc_html__( 'Проекты', 'wp-diplom' ),
		'singular_name'      => esc_html__( 'Проект', 'wp-diplom' ),
		'add_new'            => esc_html__( 'Добавить проект', 'wp-diplom' ),
		'add_new_item'       => esc_html__( 'Новый проект', 'wp-diplom' ),
		'edit_item'          => esc_html__( 'Редактировать проект', 'wp-diplom' ),
		'view_item'          => esc_html__( 'Смотреть проект', 'wp-diplom' ),
		'search_items'       => esc_html__( 'Искать проекты', 'wp-diplom' ),
		'not_found'          => esc_html__( 'Проекты не найдены', 'wp-diplom' ),
		'not_found_in_trash' => esc_html__( 'В корзине проектов нет', 'wp-diplom' ),
		'all_items'          => esc_html__( 'Все проекты', 'wp-diplom' ),
		'menu_name'          => esc_html__( 'Проекты', 'wp-diplom' ),
	);

	register_post_type( 'project', array(
		'labels'        => $labels,
		'public'        => true,
		'has_archive'   => true,
		'menu_icon'     => 'dashicons-portfolio',
		'menu_position' => 20,
		'rewrite'       => array( 'slug' => 'projects' ),
		'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'revisions' ),
		'show_in_rest'  => true,
		'taxonomies'    => array( 'technology' ),
	) );

	register_taxonomy( 'technology', array( 'project', 'post' ), array(
		'labels'            => array(
			'name'          => esc_html__( 'Технологии', 'wp-diplom' ),
			'singular_name' => esc_html__( 'Технология', 'wp-diplom' ),
			'add_new_item'  => esc_html__( 'Добавить технологию', 'wp-diplom' ),
			'edit_item'     => esc_html__( 'Редактировать технологию', 'wp-diplom' ),
			'search_items'  => esc_html__( 'Искать технологии', 'wp-diplom' ),
		),
		'public'            => true,
		'hierarchical'      => false,
		'show_admin_column' => true,
		'show_in_rest'      => true,
		'rewrite'           => array( 'slug' => 'technology' ),
	) );
}
add_action( 'init', 'wp_diplom_register_post_types' );

/**
 * Сброс правил перезаписи при активации темы.
 */
function wp_diplom_flush_rewrites() {
	wp_diplom_register_post_types();
	flush_rewrite_rules();
}
add_action( 'after_switch_theme', 'wp_diplom_flush_rewrites' );
