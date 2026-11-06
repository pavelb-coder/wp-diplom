<?php
/**
 * Главная страница.
 *
 * @package WP_Diplom
 */

get_header();

$hero_title    = get_theme_mod( 'hero_title', esc_html__( 'Дипломный проект на WordPress', 'wp-diplom' ) );
$hero_subtitle = get_theme_mod( 'hero_subtitle', esc_html__( 'Адаптивная тема с кастомными типами записей и поддержкой редактора блоков.', 'wp-diplom' ) );
?>

<section class="hero">
	<div class="container hero__inner">
		<h1 class="hero__title"><?php echo esc_html( $hero_title ); ?></h1>
		<?php if ( $hero_subtitle ) : ?>
			<p class="hero__subtitle"><?php echo esc_html( $hero_subtitle ); ?></p>
		<?php endif; ?>

		<form role="search" method="get" class="hero__search" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<label class="screen-reader-text" for="hero-search"><?php esc_html_e( 'Поиск по сайту', 'wp-diplom' ); ?></label>
			<input id="hero-search" type="search" name="s" class="hero__search-input"
				placeholder="<?php esc_attr_e( 'Что искать?', 'wp-diplom' ); ?>"
				value="<?php echo esc_attr( get_search_query() ); ?>">
			<button type="submit" class="button"><?php esc_html_e( 'Найти', 'wp-diplom' ); ?></button>
		</form>
	</div>
</section>

<div class="container">
	<?php
	$projects = new WP_Query( array(
		'post_type'      => 'project',
		'posts_per_page' => 6,
		'no_found_rows'  => true,
	) );

	if ( $projects->have_posts() ) :
		?>
		<section class="section">
			<header class="section__header">
				<h2 class="section__title"><?php esc_html_e( 'Проекты', 'wp-diplom' ); ?></h2>
				<a class="section__link" href="<?php echo esc_url( get_post_type_archive_link( 'project' ) ); ?>">
					<?php esc_html_e( 'Все проекты →', 'wp-diplom' ); ?>
				</a>
			</header>

			<div class="posts-grid">
				<?php
				while ( $projects->have_posts() ) :
					$projects->the_post();
					get_template_part( 'template-parts/content', 'card' );
				endwhile;
				wp_reset_postdata();
				?>
			</div>
		</section>
	<?php endif; ?>

	<?php
	$news = new WP_Query( array(
		'post_type'      => 'post',
		'posts_per_page' => 6,
		'no_found_rows'  => true,
	) );

	if ( $news->have_posts() ) :
		?>
		<section class="section">
			<header class="section__header">
				<h2 class="section__title"><?php esc_html_e( 'Записи блога', 'wp-diplom' ); ?></h2>
			</header>

			<div class="posts-grid">
				<?php
				while ( $news->have_posts() ) :
					$news->the_post();
					get_template_part( 'template-parts/content', 'card' );
				endwhile;
				wp_reset_postdata();
				?>
			</div>
		</section>
	<?php else : ?>
		<section class="section">
			<h2 class="section__title"><?php esc_html_e( 'Записи блога', 'wp-diplom' ); ?></h2>
			<p><?php esc_html_e( 'Опубликуйте первую запись — она появится здесь.', 'wp-diplom' ); ?></p>
		</section>
	<?php endif; ?>
</div>

<?php
get_footer();
