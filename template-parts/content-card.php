<?php
/**
 * Карточка записи в сетке.
 *
 * @package WP_Diplom
 */
?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'card' ); ?>>
	<?php if ( has_post_thumbnail() ) : ?>
		<a class="card__thumb" href="<?php the_permalink(); ?>" aria-hidden="true" tabindex="-1">
			<?php the_post_thumbnail( 'wp-diplom-card', array( 'loading' => 'lazy' ) ); ?>
		</a>
	<?php endif; ?>

	<div class="card__body">
		<?php
		$post_type_object = get_post_type_object( get_post_type() );
		if ( $post_type_object ) :
			?>
			<span class="card__type"><?php echo esc_html( $post_type_object->labels->singular_name ); ?></span>
		<?php endif; ?>

		<h2 class="card__title">
			<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
		</h2>

		<div class="card__excerpt">
			<?php echo esc_html( wp_trim_words( get_the_excerpt(), 24, '…' ) ); ?>
		</div>

		<footer class="card__footer">
			<time class="card__date" datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>">
				<?php echo esc_html( get_the_date() ); ?>
			</time>
			<a class="card__more" href="<?php the_permalink(); ?>">
				<?php esc_html_e( 'Подробнее →', 'wp-diplom' ); ?>
			</a>
		</footer>
	</div>
</article>
