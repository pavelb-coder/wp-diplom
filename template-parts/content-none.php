<?php
/**
 * Заглушка «ничего не найдено».
 *
 * @package WP_Diplom
 */
?>
<section class="no-results">
	<h2 class="page-title"><?php esc_html_e( 'Ничего не найдено', 'wp-diplom' ); ?></h2>

	<?php if ( is_search() ) : ?>
		<p><?php esc_html_e( 'По вашему запросу ничего не нашлось. Попробуйте другие ключевые слова.', 'wp-diplom' ); ?></p>
		<?php get_search_form(); ?>
	<?php elseif ( is_home() && current_user_can( 'publish_posts' ) ) : ?>
		<p>
			<?php
			printf(
				wp_kses(
					/* translators: %s — ссылка на создание записи. */
					__( 'Готовы опубликовать первую запись? <a href="%s">Начните здесь</a>.', 'wp-diplom' ),
					array( 'a' => array( 'href' => array() ) )
				),
				esc_url( admin_url( 'post-new.php' ) )
			);
			?>
		</p>
	<?php else : ?>
		<p><?php esc_html_e( 'Ничего не найдено. Вернитесь на главную или воспользуйтесь поиском.', 'wp-diplom' ); ?></p>
		<?php get_search_form(); ?>
	<?php endif; ?>
</section>
