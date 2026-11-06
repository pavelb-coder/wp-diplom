<?php
/**
 * Шаблон комментариев.
 *
 * @package WP_Diplom
 */

if ( post_password_required() ) {
	return;
}
?>
<section id="comments" class="comments-area">
	<?php if ( have_comments() ) : ?>
		<h2 class="comments-title">
			<?php
			$comment_count = get_comments_number();
			printf(
				esc_html( _n( 'Комментарий (%s)', 'Комментарии (%s)', $comment_count, 'wp-diplom' ) ),
				esc_html( number_format_i18n( $comment_count ) )
			);
			?>
		</h2>

		<ol class="comment-list">
			<?php
			wp_list_comments( array(
				'style'       => 'ol',
				'short_ping'  => true,
				'avatar_size' => 56,
			) );
			?>
		</ol>

		<?php
		the_comments_pagination( array(
			'prev_text' => esc_html__( 'Назад', 'wp-diplom' ),
			'next_text' => esc_html__( 'Вперёд', 'wp-diplom' ),
		) );
		?>
	<?php endif; ?>

	<?php if ( ! comments_open() && get_comments_number() ) : ?>
		<p class="no-comments"><?php esc_html_e( 'Комментарии закрыты.', 'wp-diplom' ); ?></p>
	<?php endif; ?>

	<?php
	comment_form( array(
		'title_reply'        => esc_html__( 'Оставить комментарий', 'wp-diplom' ),
		'title_reply_before' => '<h2 id="reply-title" class="comment-reply-title">',
		'title_reply_after'  => '</h2>',
		'class_submit'       => 'button',
		'label_submit'       => esc_html__( 'Отправить', 'wp-diplom' ),
	) );
	?>
</section>
