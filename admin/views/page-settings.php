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

$wpdc_notice = isset( $_GET['wpdc_notice'] ) ? sanitize_key( wp_unslash( $_GET['wpdc_notice'] ) ) : '';

?>
<div class="wrap wpdc-wrap">
	<h1><?php esc_html_e( 'Настройки сертификатов', 'wp-diplom' ); ?></h1>

	<?php if ( 'wpdc-settings-saved' === $wpdc_notice ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Настройки сохранены.', 'wp-diplom' ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="wpdc_save_settings">
		<?php wp_nonce_field( 'wpdc_save_settings' ); ?>

		<h2 class="title"><?php esc_html_e( 'Основное', 'wp-diplom' ); ?></h2>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="wpdc_organization"><?php esc_html_e( 'Организация', 'wp-diplom' ); ?></label>
					</th>
					<td>
						<input type="text" class="regular-text" name="wpdc_organization" id="wpdc_organization"
							value="<?php echo esc_attr( $settings['organization'] ); ?>">
						<p class="description"><?php esc_html_e( 'Подставляется в {organization} на бланке и в письме.', 'wp-diplom' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="wpdc_default_template"><?php esc_html_e( 'Бланк по умолчанию', 'wp-diplom' ); ?></label>
					</th>
					<td>
						<select name="wpdc_default_template" id="wpdc_default_template">
							<option value="0"><?php esc_html_e( '— первый доступный —', 'wp-diplom' ); ?></option>
							<?php foreach ( $templates as $wpdc_template ) : ?>
								<option value="<?php echo esc_attr( $wpdc_template->ID ); ?>" <?php selected( (int) $settings['default_template'], $wpdc_template->ID ); ?>>
									<?php echo esc_html( $wpdc_template->post_title ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Нумерация', 'wp-diplom' ); ?></th>
					<td>
						<label class="wpdc-inline">
							<?php esc_html_e( 'Префикс', 'wp-diplom' ); ?>
							<input type="text" class="small-text" name="wpdc_number_prefix"
								value="<?php echo esc_attr( $settings['number_prefix'] ); ?>">
						</label>

						<label class="wpdc-inline">
							<?php esc_html_e( 'Знаков в счётчике', 'wp-diplom' ); ?>
							<input type="number" min="1" max="10" class="small-text" name="wpdc_number_padding"
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
						<label class="wpdc-inline">
							<input type="checkbox" name="wpdc_enable_verify" value="1" <?php checked( ! empty( $settings['enable_verify'] ) ); ?>>
							<?php esc_html_e( 'Разрешить публичную проверку по коду', 'wp-diplom' ); ?>
						</label>

						<p>
							<label for="wpdc_verify_page"><?php esc_html_e( 'Страница с шорткодом [wpdc_verify]', 'wp-diplom' ); ?></label>
							<select name="wpdc_verify_page" id="wpdc_verify_page">
								<option value="0"><?php esc_html_e( '— не выбрана —', 'wp-diplom' ); ?></option>
								<?php foreach ( $pages as $wpdc_page ) : ?>
									<option value="<?php echo esc_attr( $wpdc_page->ID ); ?>" <?php selected( (int) ( $settings['verify_page'] ?? 0 ), $wpdc_page->ID ); ?>>
										<?php echo esc_html( $wpdc_page->post_title ); ?>
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

				<tr>
					<th scope="row">
						<label for="wpdc_sample_teacher"><?php esc_html_e( 'Преподаватель в предпросмотре', 'wp-diplom' ); ?></label>
					</th>
					<td>
						<input type="text" class="regular-text" name="wpdc_sample_teacher" id="wpdc_sample_teacher"
							value="<?php echo esc_attr( $settings['sample_teacher'] ); ?>"
							placeholder="<?php esc_attr_e( 'Иванов И. И.', 'wp-diplom' ); ?>">
						<p class="description"><?php esc_html_e( 'Используется в предпросмотре бланка.', 'wp-diplom' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>

		<?php submit_button( __( 'Сохранить настройки', 'wp-diplom' ) ); ?>
	</form>

	<div class="wpdc-card">
		<h2><?php esc_html_e( 'Шорткоды', 'wp-diplom' ); ?></h2>
		<ul class="wpdc-list">
			<li><code>[wpdc_verify]</code> — форма проверки подлинности по коду</li>
			<li><code>[wpdc_certificate id="123"]</code> — карточка сертификата со ссылкой на PDF</li>
		</ul>
	</div>
</div>
