<?php
/**
 * Типы записей: бланки и сертификаты.
 *
 * @package WP_Diplom
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Регистрация типов записей и таксономии.
 */
class WPD_Post_Types {

	/**
	 * Хуки.
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'register_post_types' ) );
		add_action( 'init', array( $this, 'register_taxonomies' ) );
		add_filter( 'post_updated_messages', array( $this, 'updated_messages' ) );
	}

	/**
	 * Типы записей.
	 */
	public function register_post_types() {
		$labels_template = array(
			'name'               => __( 'Бланки', 'wp-diplom' ),
			'singular_name'      => __( 'Бланк', 'wp-diplom' ),
			'add_new'            => __( 'Добавить бланк', 'wp-diplom' ),
			'add_new_item'       => __( 'Новый бланк', 'wp-diplom' ),
			'edit_item'          => __( 'Редактировать бланк', 'wp-diplom' ),
			'new_item'           => __( 'Новый бланк', 'wp-diplom' ),
			'view_item'          => __( 'Посмотреть бланк', 'wp-diplom' ),
			'search_items'       => __( 'Искать бланки', 'wp-diplom' ),
			'not_found'          => __( 'Бланки не найдены', 'wp-diplom' ),
			'not_found_in_trash' => __( 'В корзине бланков нет', 'wp-diplom' ),
			'all_items'          => __( 'Бланки', 'wp-diplom' ),
			'menu_name'          => __( 'Бланки', 'wp-diplom' ),
		);

		register_post_type(
			'wpd_template',
			array(
				'labels'              => $labels_template,
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => 'edit.php?post_type=wpd_certificate',
				'show_in_rest'        => false,
				'menu_icon'           => 'dashicons-media-default',
				'capability_type'     => 'post',
				'supports'            => array( 'title' ),
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
			)
		);

		$labels_certificate = array(
			'name'               => __( 'Сертификаты', 'wp-diplom' ),
			'singular_name'      => __( 'Сертификат', 'wp-diplom' ),
			'add_new'            => __( 'Добавить сертификат', 'wp-diplom' ),
			'add_new_item'       => __( 'Новый сертификат', 'wp-diplom' ),
			'edit_item'          => __( 'Редактировать сертификат', 'wp-diplom' ),
			'new_item'           => __( 'Новый сертификат', 'wp-diplom' ),
			'view_item'          => __( 'Открыть сертификат', 'wp-diplom' ),
			'search_items'       => __( 'Искать сертификаты', 'wp-diplom' ),
			'not_found'          => __( 'Сертификатов пока нет', 'wp-diplom' ),
			'not_found_in_trash' => __( 'В корзине сертификатов нет', 'wp-diplom' ),
			'all_items'          => __( 'Все сертификаты', 'wp-diplom' ),
			'menu_name'          => __( 'Сертификаты', 'wp-diplom' ),
		);

		register_post_type(
			'wpd_certificate',
			array(
				'labels'              => $labels_certificate,
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'menu_icon'           => 'dashicons-awards',
				'menu_position'       => 26,
				'capability_type'     => 'post',
				'capabilities'        => array(
					'create_posts' => 'edit_posts',
				),
				'map_meta_cap'        => true,
				'supports'            => array( 'title' ),
				'has_archive'         => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
			)
		);
	}

	/**
	 * Таксономия «Программы обучения».
	 */
	public function register_taxonomies() {
		register_taxonomy(
			'wpd_program',
			array( 'wpd_certificate' ),
			array(
				'labels'            => array(
					'name'          => __( 'Программы', 'wp-diplom' ),
					'singular_name' => __( 'Программа', 'wp-diplom' ),
					'add_new_item'  => __( 'Добавить программу', 'wp-diplom' ),
					'edit_item'     => __( 'Редактировать программу', 'wp-diplom' ),
					'search_items'  => __( 'Искать программы', 'wp-diplom' ),
				),
				'public'            => false,
				'show_ui'           => true,
				'show_admin_column' => true,
				'hierarchical'      => true,
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Сообщения об обновлении записей.
	 *
	 * @param array $messages Сообщения.
	 * @return array
	 */
	public function updated_messages( $messages ) {
		$messages['wpd_certificate'] = array(
			0  => '',
			1  => __( 'Сертификат обновлён.', 'wp-diplom' ),
			6  => __( 'Сертификат создан.', 'wp-diplom' ),
			7  => __( 'Сертификат сохранён.', 'wp-diplom' ),
			8  => __( 'Сертификат отправлен в черновик.', 'wp-diplom' ),
			10 => __( 'Черновик сертификата обновлён.', 'wp-diplom' ),
		);

		return $messages;
	}
}
