<?php
/**
 * Страница настроек плагина.
 *
 * @package WP_Diplom
 *
 * @var array     $settings  Настройки.
 * @var WP_Post[] $templates Бланки.
 * @var array     $pages     Страницы сайта.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wpd_notice = isset( $_GET['wpd_notice'] ) ? sanitize_key( wp_unslash( $_GET['wpd_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>
<div class="wrap wpd-wrap">
	<h1><?php esc_html_e( 'Настройки сертификатов', 'wp-diplom' ); ?></h1>

	<?php if ( 'wpd-settings-saved' === $wpd_notice ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Настройки сохранены.', 'wp-diplom' ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="wpd_save_settings">
		<?php wp_nonce_field( 'wpd_save_settings' ); ?>

		<h2 class="title"><?php esc_html_e( 'Основное', 'wp-diplom' ); ?></h2>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="wpd_organization"><?php esc_html_e( 'Организация', 'wp-diplom' ); ?></label>
					</th>
					<td>
						<input type="text" class="regular-text" name="wpd_organization" id="wpd_organization"
							value="<?php echo esc_attr( $settings['organization'] ); ?>">
						<p class="description"><?php esc_html_e( 'Подставляется в {organization} на бланке и в письме.', 'wp-diplom' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="wpd_default_template"><?php esc_html_e( 'Бланк по умолчанию', 'wp-diplom' ); ?></label>
					</th>
					<td>
						<select name="wpd_default_template" id="wpd_default_template">
							<option value="0"><?php esc_html_e( '— первый доступный —', 'wp-diplom' ); ?></option>
							<?php foreach ( $templates as $wpd_template ) : ?>
								<option value="<?php echo esc_attr( $wpd_template->ID ); ?>" <?php selected( (int) $settings['default_template'], $wpd_template->ID ); ?>>
									<?php echo esc_html( $wpd_template->post_title ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Нумерация', 'wp-diplom' ); ?></th>
					<td>
						<label class="wpd-inline">
							<?php esc_html_e( 'Префикс', 'wp-diplom' ); ?>
							<input type="text" class="small-text" name="wpd_number_prefix"
								value="<?php echo esc_attr( $settings['number_prefix'] ); ?>">
						</label>

						<label class="wpd-inline">
							<?php esc_html_e( 'Знаков в счётчике', 'wp-diplom' ); ?>
							<input type="number" min="1" max="10" class="small-text" name="wpd_number_padding"
								value="<?php echo esc_attr( $settings['number_padding'] ); ?>">
						</label>

						<p class="description">
							<?php
							printf(
								/* translators: %s — пример номера. */
								esc_html__( 'Итоговый вид: %s (счётчик сбрасывается каждый год).', 'wp-diplom' ),
								'<code>' . esc_html( $settings['number_prefix'] . '-' . current_time( 'Y' ) . '-' . str_pad( '1', (int) $settings['number_padding'], '0', STR_PAD_LEFT ) ) . '</code>'
							);
							?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Проверка подлинности', 'wp-diplom' ); ?></th>
					<td>
						<label class="wpd-inline">
							<input type="checkbox" name="wpd_enable_verify" value="1" <?php checked( ! empty( $settings['enable_verify'] ) ); ?>>
							<?php esc_html_e( 'Разрешить публичную проверку по коду', 'wp-diplom' ); ?>
						</label>

						<p>
							<label for="wpd_verify_page"><?php esc_html_e( 'Страница с шорткодом [wpd_verify]', 'wp-diplom' ); ?></label>
							<select name="wpd_verify_page" id="wpd_verify_page">
								<option value="0"><?php esc_html_e( '— не выбрана —', 'wp-diplom' ); ?></option>
								<?php foreach ( $pages as $wpd_page ) : ?>
									<option value="<?php echo esc_attr( $wpd_page->ID ); ?>" <?php selected( (int) ( $settings['verify_page'] ?? 0 ), $wpd_page->ID ); ?>>
										<?php echo esc_html( $wpd_page->post_title ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</p>

						<p class="description">
							<?php
							printf(
								/* translators: %s — пример URL. */
								esc_html__( 'Без выбранной страницы проверка работает по адресу %s', 'wp-diplom' ),
								'<code>' . esc_html( home_url( '/certificate/КОД/' ) ) . '</code>'
							);
							?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>

		<h2 class="title"><?php esc_html_e( 'Письмо с сертификатом', 'wp-diplom' ); ?></h2>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="wpd_sender_name"><?php esc_html_e( 'Имя отправителя', 'wp-diplom' ); ?></label>
					</th>
					<td>
						<input type="text" class="regular-text" name="wpd_sender_name" id="wpd_sender_name"
							value="<?php echo esc_attr( $settings['sender_name'] ); ?>">
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="wpd_sender_email"><?php esc_html_e( 'E-mail отправителя', 'wp-diplom' ); ?></label>
					</th>
					<td>
						<input type="email" class="regular-text" name="wpd_sender_email" id="wpd_sender_email"
							value="<?php echo esc_attr( $settings['sender_email'] ); ?>">
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="wpd_email_subject"><?php esc_html_e( 'Тема письма', 'wp-diplom' ); ?></label>
					</th>
					<td>
						<input type="text" class="large-text" name="wpd_email_subject" id="wpd_email_subject"
							value="<?php echo esc_attr( $settings['email_subject'] ); ?>">
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="wpd_email_body"><?php esc_html_e( 'Текст письма', 'wp-diplom' ); ?></label>
					</th>
					<td>
						<textarea name="wpd_email_body" id="wpd_email_body" rows="10" class="large-text code"><?php echo esc_textarea( $settings['email_body'] ); ?></textarea>
						<p class="description">
							<?php esc_html_e( 'Доступны плейсхолдеры бланка, а также {verify_url} и {site_url}. PDF прикрепляется автоматически.', 'wp-diplom' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="wpd_sample_teacher"><?php esc_html_e( 'Преподаватель в предпросмотре', 'wp-diplom' ); ?></label>
					</th>
					<td>
						<input type="text" class="regular-text" name="wpd_sample_teacher" id="wpd_sample_teacher"
							value="<?php echo esc_attr( $settings['sample_teacher'] ); ?>"
							placeholder="<?php esc_attr_e( 'Иванов И. И.', 'wp-diplom' ); ?>">
					</td>
				</tr>
			</tbody>
		</table>

		<?php submit_button( __( 'Сохранить настройки', 'wp-diplom' ) ); ?>
	</form>

	<div class="wpd-card">
		<h2><?php esc_html_e( 'Шорткоды', 'wp-diplom' ); ?></h2>
		<ul class="wpd-list">
			<li><code>[wpd_verify]</code> — форма проверки подлинности по коду</li>
			<li><code>[wpd_certificate id="123"]</code> — карточка сертификата со ссылкой на PDF</li>
		</ul>
	</div>
</div>
