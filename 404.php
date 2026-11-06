<?php
/**
 * Страница 404.
 *
 * @package WP_Diplom
 */

get_header();
?>

<div class="container error-404">
	<p class="error-404__code">404</p>
	<h1 class="page-title"><?php esc_html_e( 'Страница не найдена', 'wp-diplom' ); ?></h1>
	<p><?php esc_html_e( 'Возможно, страница была удалена или вы ошиблись в адресе.', 'wp-diplom' ); ?></p>

	<form role="search" method="get" class="error-404__search" action="<?php echo esc_url( home_url( '/' ) ); ?>">
		<label class="screen-reader-text" for="error-search"><?php esc_html_e( 'Поиск по сайту', 'wp-diplom' ); ?></label>
		<input id="error-search" type="search" name="s" value="<?php echo esc_attr( get_search_query() ); ?>">
		<button type="submit" class="button"><?php esc_html_e( 'Найти', 'wp-diplom' ); ?></button>
	</form>

	<p>
		<a class="button button--ghost" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<?php esc_html_e( 'На главную', 'wp-diplom' ); ?>
		</a>
	</p>
</div>

<?php
get_footer();
