<?php
/**
 * Шаблонные теги темы.
 *
 * @package WP_Diplom
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Вывод даты записи.
 */
function wp_diplom_posted_on() {
	printf(
		'<time class="entry-date" datetime="%1$s">%2$s</time>',
		esc_attr( get_the_date( DATE_W3C ) ),
		esc_html( get_the_date() )
	);
}

/**
 * Вывод метаданных записи: дата, автор, рубрики.
 */
function wp_diplom_entry_meta() {
	echo '<div class="entry-meta">';

	wp_diplom_posted_on();

	printf(
		'<span class="entry-author">%1$s <a href="%2$s">%3$s</a></span>',
		esc_html__( 'Автор:', 'wp-diplom' ),
		esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ),
		esc_html( get_the_author() )
	);

	$categories = get_the_category_list( ', ' );
	if ( $categories ) {
		printf(
			'<span class="entry-cats">%1$s %2$s</span>',
			esc_html__( 'Рубрики:', 'wp-diplom' ),
			wp_kses_post( $categories )
		);
	}

	if ( ! comments_open() && ! get_comments_number() ) {
		echo '<span class="entry-comments">' . esc_html__( 'Комментарии закрыты', 'wp-diplom' ) . '</span>';
	} else {
		printf(
			'<span class="entry-comments"><a href="%1$s">%2$s</a></span>',
			esc_url( get_comments_link() ),
			esc_html( sprintf( '%s: %d', __( 'Комментариев', 'wp-diplom' ), (int) get_comments_number() ) )
		);
	}

	echo '</div>';
}

/**
 * Пагинация.
 */
function wp_diplom_pagination() {
	the_posts_pagination( array(
		'mid_size'  => 2,
		'prev_text' => esc_html__( 'Назад', 'wp-diplom' ),
		'next_text' => esc_html__( 'Вперёд', 'wp-diplom' ),
		'class'     => 'pagination',
	) );
}

/**
 * Хлебные крошки (упрощённые).
 */
function wp_diplom_breadcrumbs() {
	if ( is_front_page() ) {
		return;
	}

	echo '<nav class="breadcrumbs" aria-label="' . esc_attr__( 'Хлебные крошки', 'wp-diplom' ) . '">';
	printf( '<a href="%s">%s</a>', esc_url( home_url( '/' ) ), esc_html__( 'Главная', 'wp-diplom' ) );

	if ( is_singular() ) {
		echo '<span class="breadcrumbs__sep">/</span><span aria-current="page">' . esc_html( get_the_title() ) . '</span>';
	} elseif ( is_category() || is_tag() || is_tax() ) {
		echo '<span class="breadcrumbs__sep">/</span><span aria-current="page">' . esc_html( single_term_title( '', false ) ) . '</span>';
	} elseif ( is_search() ) {
		echo '<span class="breadcrumbs__sep">/</span><span aria-current="page">' . esc_html__( 'Поиск', 'wp-diplom' ) . '</span>';
	} elseif ( is_archive() ) {
		echo '<span class="breadcrumbs__sep">/</span><span aria-current="page">' . esc_html( get_the_archive_title() ) . '</span>';
	}

	echo '</nav>';
}
