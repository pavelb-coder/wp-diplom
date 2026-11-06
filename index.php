<?php
/**
 * Главный шаблон.
 *
 * @package WP_Diplom
 */

get_header();
?>

<div class="container layout">
	<div class="layout__main">
		<?php if ( have_posts() ) : ?>

			<?php if ( is_home() && ! is_front_page() ) : ?>
				<h1 class="page-title screen-reader-text"><?php single_post_title(); ?></h1>
			<?php endif; ?>

			<div class="posts-grid">
				<?php
				while ( have_posts() ) :
					the_post();
					get_template_part( 'template-parts/content', 'card' );
				endwhile;
				?>
			</div>

			<?php wp_diplom_pagination(); ?>

		<?php else : ?>
			<?php get_template_part( 'template-parts/content', 'none' ); ?>
		<?php endif; ?>
	</div>

	<?php get_sidebar(); ?>
</div>

<?php
get_footer();
