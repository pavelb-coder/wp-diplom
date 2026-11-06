<?php
/**
 * Метабокс: действия с документом.
 *
 * @package WP_Diplom
 *
 * @var WP_Post $post      Запись.
 * @var bool    $is_public Опубликован ли сертификат.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wpd_send_url = wp_nonce_url(
	add_query_arg(
		array(
			'action' => 'wpd_send',
			'id'     => $post->ID,
		),
		admin_url( 'admin-post.php' )
	),
	'wpd_send_' . $post->ID
);

$wpd_sent_at     = WPD_Certificate::sent_at( $post->ID );
$wpd_generated   = (string) get_post_meta( $post->ID, '_wpd_pdf_generated', true );
$wpd_email       = (string) get_post_meta( $post->ID, '_wpd_email', true );
$wpd_notice      = isset( $_GET['wpd_notice'] ) ? sanitize_key( wp_unslash( $_GET['wpd_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>
<?php if ( 'wpd-sent' === $wpd_notice ) : ?>
	<div class="notice notice-success inline"><p><?php esc_html_e( 'Письмо отправлено.', 'wp-diplom' ); ?></p></div>
<?php elseif ( 'wpd-error' === $wpd_notice ) : ?>
	<div class="notice notice-error inline"><p><?php esc_html_e( 'Не удалось отправить письмо. Проверьте e-mail и настройки SMTP.', 'wp-diplom' ); ?></p></div>
<?php endif; ?>

<div class="wpd-actions">
	<p>
		<a class="button button-primary widefat" href="<?php echo esc_url( WPD_Certificate::download_url( $post->ID ) ); ?>">
			<span class="dashicons dashicons-pdf"></span>
			<?php esc_html_e( 'Скачать PDF', 'wp-diplom' ); ?>
		</a>
	</p>

	<p>
		<?php if ( is_email( $wpd_email ) ) : ?>
			<a class="button widefat" href="<?php echo esc_url( $wpd_send_url ); ?>">
				<span class="dashicons dashicons-email"></span>
				<?php echo $wpd_sent_at ? esc_html__( 'Отправить повторно', 'wp-diplom' ) : esc_html__( 'Отправить на e-mail', 'wp-diplom' ); ?>
			</a>
		<?php else : ?>
			<button type="button" class="button widefat" disabled>
				<?php esc_html_e( 'Нет e-mail получателя', 'wp-diplom' ); ?>
			</button>
		<?php endif; ?>
	</p>
</div>

<ul class="wpd-meta-list">
	<li>
		<span><?php esc_html_e( 'PDF собран', 'wp-diplom' ); ?>:</span>
		<?php echo $wpd_generated ? esc_html( $wpd_generated ) : esc_html__( 'ещё нет', 'wp-diplom' ); ?>
	</li>
	<li>
		<span><?php esc_html_e( 'Письмо отправлено', 'wp-diplom' ); ?>:</span>
		<?php echo $wpd_sent_at ? esc_html( $wpd_sent_at ) : esc_html__( 'нет', 'wp-diplom' ); ?>
	</li>
	<li>
		<span><?php esc_html_e( 'Статус записи', 'wp-diplom' ); ?>:</span>
		<?php echo $is_public ? esc_html__( 'опубликован', 'wp-diplom' ) : esc_html__( 'черновик', 'wp-diplom' ); ?>
	</li>
</ul>

<p class="description">
	<?php esc_html_e( 'PDF пересобирается автоматически при изменении данных. Файлы хранятся в wp-content/uploads/wpd-certificates.', 'wp-diplom' ); ?>
</p>
