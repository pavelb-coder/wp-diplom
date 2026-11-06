<?php
/**
 * Шапка сайта.
 *
 * @package WP_Diplom
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="skip-link screen-reader-text" href="#content"><?php esc_html_e( 'Перейти к содержимому', 'wp-diplom' ); ?></a>

<div class="site">
	<header class="site-header">
		<div class="container site-header__inner">
			<div class="site-branding">
				<?php if ( has_custom_logo() ) : ?>
					<?php the_custom_logo(); ?>
				<?php else : ?>
					<a class="site-title" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
						<?php bloginfo( 'name' ); ?>
					</a>
				<?php endif; ?>

				<?php
				$description = get_bloginfo( 'description', 'display' );
				if ( $description || is_customize_preview() ) :
					?>
					<p class="site-description"><?php echo esc_html( $description ); ?></p>
				<?php endif; ?>
			</div>

			<button class="menu-toggle" aria-controls="primary-menu" aria-expanded="false">
				<span class="menu-toggle__bar"></span>
				<span class="screen-reader-text"><?php esc_html_e( 'Меню', 'wp-diplom' ); ?></span>
			</button>

			<?php if ( has_nav_menu( 'primary' ) ) : ?>
				<nav class="site-nav" aria-label="<?php esc_attr_e( 'Главное меню', 'wp-diplom' ); ?>">
					<?php
					wp_nav_menu( array(
						'theme_location' => 'primary',
						'menu_id'        => 'primary-menu',
						'menu_class'     => 'menu',
						'container'      => false,
						'depth'          => 2,
					) );
					?>
				</nav>
			<?php endif; ?>
		</div>
	</header>

	<main id="content" class="site-main">
		<?php wp_diplom_breadcrumbs(); ?>
