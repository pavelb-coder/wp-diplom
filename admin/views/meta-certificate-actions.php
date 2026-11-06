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

$wpdc_send_url = wp_nonce_url(
	add_query_arg(
		array(
			'action' => 'wpdc_send',
			'id'     => $post->ID,
		),
		admin_url( 'admin-post.php' )
	),
	'wpdc_send_' . $post->ID
);

$wpdc_sent_at     = WPDC_Certificate::sent_at( $post->ID );
$wpdc_generated   = (string) get_post_meta( $post->ID, '_wpdc_pdf_generated', true );
$wpdc_email       = (string) get_post_meta( $post->ID, '_wpdc_email', true );
$wpdc_notice      = isset( $_GET['wpdc_notice'] ) ? sanitize_key( wp_unslash( $_GET['wpdc_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>
<?php if ( 'wpdc-sent' === $wpdc_notice ) : ?>
	<div class="notice notice-success inline"><p><?php esc_html_e( 'Письмо отправлено.', 'wp-diplom' ); ?></p></div>
<?php elseif ( 'wpdc-error' === $wpdc_notice ) : ?>
	<div class="notice notice-error inline"><p><?php esc_html_e( 'Не удалось отправить письмо. Проверьте e-mail и настройки SMTP.', 'wp-diplom' ); ?></p></div>
<?php endif; ?>

<div class="wpdc-actions">
	<p>
		<a class="button button-primary widefat" href="<?php echo esc_url( WPDC_Certificate::download_url( $post->ID ) ); ?>">
			<span class="dashicons dashicons-pdf"></span>
			<?php esc_html_e( 'Скачать PDF', 'wp-diplom' ); ?>
		</a>
	</p>

	<p>
		<?php if ( is_email( $wpdc_email ) ) : ?>
			<a class="button widefat" href="<?php echo esc_url( $wpdc_send_url ); ?>">
				<span class="dashicons dashicons-email"></span>
				<?php echo $wpdc_sent_at ? esc_html__( 'Отправить повторно', 'wp-diplom' ) : esc_html__( 'Отправить на e-mail', 'wp-diplom' ); ?>
			</a>
		<?php else : ?>
			<button type="button" class="button widefat" disabled>
				<?php esc_html_e( 'Нет e-mail получателя', 'wp-diplom' ); ?>
			</button>
		<?php endif; ?>
	</p>
</div>

<ul class="wpdc-meta-list">
	<li>
		<span><?php esc_html_e( 'PDF собран', 'wp-diplom' ); ?>:</span>
		<?php echo $wpdc_generated ? esc_html( $wpdc_generated ) : esc_html__( 'ещё нет', 'wp-diplom' ); ?>
	</li>
	<li>
		<span><?php esc_html_e( 'Письмо отправлено', 'wp-diplom' ); ?>:</span>
		<?php echo $wpdc_sent_at ? esc_html( $wpdc_sent_at ) : esc_html__( 'нет', 'wp-diplom' ); ?>
	</li>
	<li>
		<span><?php esc_html_e( 'Статус записи', 'wp-diplom' ); ?>:</span>
		<?php echo $is_public ? esc_html__( 'опубликован', 'wp-diplom' ) : esc_html__( 'черновик', 'wp-diplom' ); ?>
	</li>
</ul>

<p class="description">
	<?php esc_html_e( 'PDF пересобирается автоматически при изменении данных. Файлы хранятся в wp-content/uploads/wpdc-certificates.', 'wp-diplom' ); ?>
</p>
