<?php
/**
 * Шаблон результатов поиска.
 *
 * @package WP_Diplom
 */

get_header();
?>

<div class="container layout">
	<div class="layout__main">
		<header class="page-header">
			<h1 class="page-title">
				<?php
				printf(
					/* translators: %s — поисковый запрос. */
					esc_html__( 'Результаты поиска: «%s»', 'wp-diplom' ),
					esc_html( get_search_query() )
				);
				?>
			</h1>
			<p class="page-description">
				<?php
				printf(
					esc_html__( 'Найдено записей: %d', 'wp-diplom' ),
					(int) $GLOBALS['wp_query']->found_posts
				);
				?>
			</p>
		</header>

		<?php if ( have_posts() ) : ?>
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
