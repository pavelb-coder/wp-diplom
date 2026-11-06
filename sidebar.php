<?php
/**
 * Боковая колонка.
 *
 * @package WP_Diplom
 */

if ( ! is_active_sidebar( 'sidebar-1' ) ) {
	return;
}
?>
<aside class="layout__sidebar" aria-label="<?php esc_attr_e( 'Боковая колонка', 'wp-diplom' ); ?>">
	<?php dynamic_sidebar( 'sidebar-1' ); ?>
</aside>
