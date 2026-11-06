<?php
/**
 * Подвал сайта.
 *
 * @package WP_Diplom
 */
?>
	</main><!-- .site-main -->

	<footer class="site-footer">
		<div class="container">
			<?php if ( is_active_sidebar( 'footer-1' ) ) : ?>
				<div class="footer-widgets">
					<?php dynamic_sidebar( 'footer-1' ); ?>
				</div>
			<?php endif; ?>

			<?php if ( has_nav_menu( 'footer' ) ) : ?>
				<nav class="footer-nav" aria-label="<?php esc_attr_e( 'Меню в подвале', 'wp-diplom' ); ?>">
					<?php
					wp_nav_menu( array(
						'theme_location' => 'footer',
						'menu_class'     => 'menu menu--horizontal',
						'container'      => false,
						'depth'          => 1,
					) );
					?>
				</nav>
			<?php endif; ?>

			<div class="site-info">
				<?php
				$custom_footer = get_theme_mod( 'footer_text' );
				if ( $custom_footer ) {
					echo '<p>' . esc_html( $custom_footer ) . '</p>';
				}
				?>
				<p>
					<?php
					printf(
						/* translators: %1$s — год, %2$s — название сайта. */
						esc_html__( '© %1$s %2$s. Все права защищены.', 'wp-diplom' ),
						esc_html( wp_date( 'Y' ) ),
						esc_html( get_bloginfo( 'name' ) )
					);
					?>
				</p>
				<p class="site-info__theme">
					<?php esc_html_e( 'Тема: WP Diplom — дипломный проект.', 'wp-diplom' ); ?>
				</p>
			</div>
		</div>
	</footer>
</div><!-- .site -->

<?php wp_footer(); ?>
</body>
</html>
