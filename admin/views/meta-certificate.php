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

$wpd_recipient = (string) get_post_meta( $post->ID, '_wpd_recipient', true );
$wpd_email     = (string) get_post_meta( $post->ID, '_wpd_email', true );
$wpd_course    = (string) get_post_meta( $post->ID, '_wpd_course', true );
$wpd_hours     = (string) get_post_meta( $post->ID, '_wpd_hours', true );
$wpd_teacher   = (string) get_post_meta( $post->ID, '_wpd_teacher', true );
$wpd_date      = (string) get_post_meta( $post->ID, '_wpd_date', true );
$wpd_number    = (string) get_post_meta( $post->ID, '_wpd_number', true );
$wpd_template  = absint( get_post_meta( $post->ID, '_wpd_template', true ) );
$wpd_revoked   = (bool) get_post_meta( $post->ID, '_wpd_revoked', true );
$wpd_custom    = get_post_meta( $post->ID, '_wpd_custom', true );
$wpd_code      = (string) get_post_meta( $post->ID, '_wpd_code', true );
$wpd_custom    = is_array( $wpd_custom ) ? $wpd_custom : array();

$wpd_default_template = $wpd_template ? $wpd_template : absint( $settings['default_template'] );
?>
<table class="form-table wpd-form-table" role="presentation">
	<tbody>
		<tr>
			<th scope="row">
				<label for="wpd_template"><?php esc_html_e( 'Бланк', 'wp-diplom' ); ?></label>
			</th>
			<td>
				<select name="wpd_template" id="wpd_template">
					<option value="0"><?php esc_html_e( '— не выбран —', 'wp-diplom' ); ?></option>
					<?php foreach ( $templates as $wpd_item ) : ?>
						<option value="<?php echo esc_attr( $wpd_item->ID ); ?>" <?php selected( $wpd_default_template, $wpd_item->ID ); ?>>
							<?php echo esc_html( $wpd_item->post_title ); ?>
						</option>
					<?php endforeach; ?>
				</select>

				<?php if ( ! $templates ) : ?>
					<p class="description wpd-warning">
						<?php
						printf(
							/* translators: %s — ссылка на создание бланка. */
							esc_html__( 'Бланков нет. %s, иначе будет использован встроенный дизайн.', 'wp-diplom' ),
							'<a href="' . esc_url( admin_url( 'post-new.php?post_type=wpd_template' ) ) . '">' . esc_html__( 'Создайте бланк', 'wp-diplom' ) . '</a>'
						);
						?>
					</p>
				<?php endif; ?>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="wpd_recipient"><?php esc_html_e( 'Получатель', 'wp-diplom' ); ?></label>
			</th>
			<td>
				<input type="text" class="regular-text" name="wpd_recipient" id="wpd_recipient"
					value="<?php echo esc_attr( $wpd_recipient ); ?>" required>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="wpd_email"><?php esc_html_e( 'E-mail', 'wp-diplom' ); ?></label>
			</th>
			<td>
				<input type="email" class="regular-text" name="wpd_email" id="wpd_email"
					value="<?php echo esc_attr( $wpd_email ); ?>">
				<p class="description"><?php esc_html_e( 'Нужен для отправки сертификата письмом.', 'wp-diplom' ); ?></p>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="wpd_course"><?php esc_html_e( 'Программа', 'wp-diplom' ); ?></label>
			</th>
			<td>
				<input type="text" class="regular-text" name="wpd_course" id="wpd_course"
					value="<?php echo esc_attr( $wpd_course ); ?>">
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="wpd_hours"><?php esc_html_e( 'Часов', 'wp-diplom' ); ?></label>
			</th>
			<td>
				<input type="number" min="0" step="1" class="small-text" name="wpd_hours" id="wpd_hours"
					value="<?php echo esc_attr( $wpd_hours ); ?>">
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="wpd_teacher"><?php esc_html_e( 'Преподаватель', 'wp-diplom' ); ?></label>
			</th>
			<td>
				<input type="text" class="regular-text" name="wpd_teacher" id="wpd_teacher"
					value="<?php echo esc_attr( $wpd_teacher ); ?>">
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="wpd_date"><?php esc_html_e( 'Дата выдачи', 'wp-diplom' ); ?></label>
			</th>
			<td>
				<input type="date" name="wpd_date" id="wpd_date" value="<?php echo esc_attr( $wpd_date ); ?>">
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="wpd_number"><?php esc_html_e( 'Номер', 'wp-diplom' ); ?></label>
			</th>
			<td>
				<input type="text" class="regular-text" name="wpd_number" id="wpd_number"
					value="<?php echo esc_attr( $wpd_number ); ?>">
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
				<?php if ( $wpd_code ) : ?>
					<code><?php echo esc_html( $wpd_code ); ?></code>
					<br>
					<a href="<?php echo esc_url( WPD_Certificate::verify_url( $wpd_code ) ); ?>" target="_blank" rel="noopener">
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
				<div id="wpd-custom-fields" class="wpd-custom-fields">
					<?php foreach ( $wpd_custom as $wpd_key => $wpd_value ) : ?>
						<p class="wpd-custom-row">
							<input type="text" name="wpd_custom_key[]" value="<?php echo esc_attr( $wpd_key ); ?>"
								placeholder="group" class="wpd-custom-key">
							<input type="text" name="wpd_custom_value[]" value="<?php echo esc_attr( $wpd_value ); ?>"
								placeholder="<?php esc_attr_e( 'значение', 'wp-diplom' ); ?>" class="regular-text">
							<button type="button" class="button-link wpd-remove-custom">×</button>
						</p>
					<?php endforeach; ?>
				</div>

				<button type="button" class="button" id="wpd-add-custom">
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
				<label class="wpd-inline">
					<input type="checkbox" name="wpd_revoked" value="1" <?php checked( $wpd_revoked ); ?>>
					<?php esc_html_e( 'Сертификат отозван (не проходит проверку подлинности)', 'wp-diplom' ); ?>
				</label>
			</td>
		</tr>
	</tbody>
</table>
