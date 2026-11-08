<?php
/**
 * Метабокс: данные сертификата.
 *
 * @package WP_Diplom
 *
 * @var WP_Post    $post      Запись.
 * @var WP_Post[]  $templates Бланки.
 * @var array      $settings  Настройки плагина.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wpdc_recipient = (string) get_post_meta( $post->ID, '_wpdc_recipient', true );
$wpdc_email     = (string) get_post_meta( $post->ID, '_wpdc_email', true );
$wpdc_course    = (string) get_post_meta( $post->ID, '_wpdc_course', true );
$wpdc_hours     = (string) get_post_meta( $post->ID, '_wpdc_hours', true );
$wpdc_date      = (string) get_post_meta( $post->ID, '_wpdc_date', true );
$wpdc_number    = (string) get_post_meta( $post->ID, '_wpdc_number', true );
$wpdc_template  = absint( get_post_meta( $post->ID, '_wpdc_template', true ) );
$wpdc_revoked   = (bool) get_post_meta( $post->ID, '_wpdc_revoked', true );
$wpdc_custom    = get_post_meta( $post->ID, '_wpdc_custom', true );
$wpdc_code      = (string) get_post_meta( $post->ID, '_wpdc_code', true );
$wpdc_custom    = is_array( $wpdc_custom ) ? $wpdc_custom : array();

$wpdc_default_template = $wpdc_template ? $wpdc_template : absint( $settings['default_template'] );
?>
<table class="form-table wpdc-form-table" role="presentation">
	<tbody>
		<tr>
			<th scope="row">
				<label for="wpdc_template"><?php esc_html_e( 'Бланк', 'wp-diplom' ); ?></label>
			</th>
			<td>
				<select name="wpdc_template" id="wpdc_template">
					<option value="0"><?php esc_html_e( '— не выбран —', 'wp-diplom' ); ?></option>
					<?php foreach ( $templates as $wpdc_item ) : ?>
						<option value="<?php echo esc_attr( $wpdc_item->ID ); ?>" <?php selected( $wpdc_default_template, $wpdc_item->ID ); ?>>
							<?php echo esc_html( $wpdc_item->post_title ); ?>
						</option>
					<?php endforeach; ?>
				</select>

				<?php if ( ! $templates ) : ?>
					<p class="description wpdc-warning">
						<?php
						printf(
							/* translators: %s — ссылка на создание бланка. */
							esc_html__( 'Бланков нет. %s, иначе будет использован встроенный дизайн.', 'wp-diplom' ),
							'<a href="' . esc_url( admin_url( 'post-new.php?post_type=wpdc_template' ) ) . '">' . esc_html__( 'Создайте бланк', 'wp-diplom' ) . '</a>'
						);
						?>
					</p>
				<?php endif; ?>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="wpdc_recipient"><?php esc_html_e( 'Получатель', 'wp-diplom' ); ?></label>
			</th>
			<td>
				<input type="text" class="regular-text" name="wpdc_recipient" id="wpdc_recipient"
					value="<?php echo esc_attr( $wpdc_recipient ); ?>" required>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="wpdc_email"><?php esc_html_e( 'E-mail', 'wp-diplom' ); ?></label>
			</th>
			<td>
				<input type="email" class="regular-text" name="wpdc_email" id="wpdc_email"
					value="<?php echo esc_attr( $wpdc_email ); ?>">
				<p class="description"><?php esc_html_e( 'Нужен для отправки сертификата письмом.', 'wp-diplom' ); ?></p>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="wpdc_course"><?php esc_html_e( 'Программа', 'wp-diplom' ); ?></label>
			</th>
			<td>
				<input type="text" class="regular-text" name="wpdc_course" id="wpdc_course"
					value="<?php echo esc_attr( $wpdc_course ); ?>">
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="wpdc_hours"><?php esc_html_e( 'Часов', 'wp-diplom' ); ?></label>
			</th>
			<td>
				<input type="number" min="0" step="1" class="small-text" name="wpdc_hours" id="wpdc_hours"
					value="<?php echo esc_attr( $wpdc_hours ); ?>">
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="wpdc_date"><?php esc_html_e( 'Дата выдачи', 'wp-diplom' ); ?></label>
			</th>
			<td>
				<input type="date" name="wpdc_date" id="wpdc_date" value="<?php echo esc_attr( $wpdc_date ); ?>">
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="wpdc_number"><?php esc_html_e( 'Номер', 'wp-diplom' ); ?></label>
			</th>
			<td>
				<input type="text" class="regular-text" name="wpdc_number" id="wpdc_number"
					value="<?php echo esc_attr( $wpdc_number ); ?>">
				<p class="description">
					<?php
					printf(
						/* translators: %s — пример номера. */
						esc_html__( 'Оставьте пустым — номер присвоится автоматически при сохранении. Пример: %s', 'wp-diplom' ),
						'<code>' . esc_html( $settings['number_prefix'] . '-' . current_time( 'Y' ) . '-' . str_pad( '1', (int) $settings['number_padding'], '0', STR_PAD_LEFT ) ) . '</code>'
					);
					?>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'Код проверки', 'wp-diplom' ); ?></th>
			<td>
				<?php if ( $wpdc_code ) : ?>
					<code><?php echo esc_html( $wpdc_code ); ?></code>
					<br>
					<a href="<?php echo esc_url( WPDC_Certificate::verify_url( $wpdc_code, $wpdc_template ) ); ?>" target="_blank" rel="noopener">
						<?php esc_html_e( 'Открыть страницу проверки', 'wp-diplom' ); ?>
					</a>
				<?php else : ?>
					<span class="description">
						<?php esc_html_e( 'Присвоится автоматически при сохранении.', 'wp-diplom' ); ?>
					</span>
				<?php endif; ?>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'Дополнительные поля', 'wp-diplom' ); ?></th>
			<td>
				<div id="wpdc-custom-fields" class="wpdc-custom-fields">
					<?php foreach ( $wpdc_custom as $wpdc_key => $wpdc_value ) : ?>
						<p class="wpdc-custom-row">
							<input type="text" name="wpdc_custom_key[]" value="<?php echo esc_attr( $wpdc_key ); ?>"
								placeholder="group" class="wpdc-custom-key">
							<input type="text" name="wpdc_custom_value[]" value="<?php echo esc_attr( $wpdc_value ); ?>"
								placeholder="<?php esc_attr_e( 'значение', 'wp-diplom' ); ?>" class="regular-text">
							<button type="button" class="button-link wpdc-remove-custom">×</button>
						</p>
					<?php endforeach; ?>
				</div>

				<button type="button" class="button" id="wpdc-add-custom">
					<?php esc_html_e( '+ Поле', 'wp-diplom' ); ?>
				</button>

				<p class="description">
					<?php
					printf(
						/* translators: %s — пример плейсхолдера. */
						esc_html__( 'В бланке доступны как %s', 'wp-diplom' ),
						'<code>{custom_КЛЮЧ}</code>'
					);
					?>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'Статус', 'wp-diplom' ); ?></th>
			<td>
				<label class="wpdc-inline">
					<input type="checkbox" name="wpdc_revoked" value="1" <?php checked( $wpdc_revoked ); ?>>
					<?php esc_html_e( 'Сертификат отозван (не проходит проверку подлинности)', 'wp-diplom' ); ?>
				</label>
			</td>
		</tr>
	</tbody>
</table>
