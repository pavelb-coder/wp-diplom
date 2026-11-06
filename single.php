<?php
/**
 * Шаблон отдельной записи.
 *
 * @package WP_Diplom
 */

get_header();
?>

<div class="container layout">
	<div class="layout__main">
		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<article id="post-<?php the_ID(); ?>" <?php post_class( 'entry entry--single' ); ?>>
				<header class="entry-header">
					<h1 class="entry-title"><?php the_title(); ?></h1>
					<?php wp_diplom_entry_meta(); ?>
				</header>

				<?php if ( has_post_thumbnail() ) : ?>
					<figure class="entry-thumbnail">
						<?php the_post_thumbnail( 'wp-diplom-hero' ); ?>
					</figure>
				<?php endif; ?>

				<div class="entry-content">
					<?php
					the_content();

					wp_link_pages( array(
						'before' => '<div class="page-links">' . esc_html__( 'Страницы:', 'wp-diplom' ),
						'after'  => '</div>',
					) );
					?>
				</div>

				<footer class="entry-footer">
					<?php the_tags( '<ul class="tags"><li>', '</li><li>', '</li></ul>' ); ?>
				</footer>
			</article>

			<?php
			the_post_navigation( array(
				'prev_text' => '<span class="nav-label">' . esc_html__( 'Предыдущая', 'wp-diplom' ) . '</span><span class="nav-title">%title</span>',
				'next_text' => '<span class="nav-label">' . esc_html__( 'Следующая', 'wp-diplom' ) . '</span><span class="nav-title">%title</span>',
			) );

			if ( comments_open() || get_comments_number() ) {
				comments_template();
			}
		endwhile;
		?>
	</div>

	<?php get_sidebar(); ?>
</div>

<?php
get_footer();
