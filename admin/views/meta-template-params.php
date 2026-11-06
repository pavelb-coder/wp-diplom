<?php
/**
 * Метабокс: параметры бланка.
 *
 * Сюда переехали настройки, которые раньше жили на отдельной странице:
 * организация, нумерация, проверка подлинности, преподаватель в предпросмотре
 * и отметка «бланк по умолчанию». Значения хранятся в самом бланке, поэтому
 * у каждого документа может быть своя серия номеров и своя организация.
 *
 * @package WP_Diplom
 *
 * @var array     $params   Действующие параметры бланка.
 * @var array     $saved    Сохранённые значения бланка.
 * @var array     $pages    Страницы сайта.
 * @var bool      $is_default Бланк выбран по умолчанию.
 * @var int       $post_id  id бланка.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<p class="description">
	<?php esc_html_e( 'Параметры действуют для документов, напечатанных по этому бланку. Пустое поле — значение по умолчанию для сайта.', 'wp-diplom' ); ?>
</p>

<table class="form-table wpdc-form-table" role="presentation">
	<tbody>
		<tr>
			<th scope="row">
				<label for="wpdc_param_organization"><?php esc_html_e( 'Организация', 'wp-diplom' ); ?></label>
			</th>
			<td>
				<input type="text" class="regular-text" id="wpdc_param_organization"
					name="wpdc_params[organization]"
					value="<?php echo esc_attr( $saved['organization'] ?? '' ); ?>"
					placeholder="<?php echo esc_attr( $params['organization'] ); ?>">
				<p class="description"><?php esc_html_e( 'Подставляется вместо {organization} и указывается как автор PDF.', 'wp-diplom' ); ?></p>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="wpdc_param_number_prefix"><?php esc_html_e( 'Нумерация', 'wp-diplom' ); ?></label>
			</th>
			<td>
				<input type="text" class="small-text" id="wpdc_param_number_prefix"
					name="wpdc_params[number_prefix]"
					value="<?php echo esc_attr( $saved['number_prefix'] ?? '' ); ?>"
					placeholder="<?php echo esc_attr( $params['number_prefix'] ); ?>">

				<span class="wpdc-inline">
					<label for="wpdc_param_number_padding"><?php esc_html_e( 'разрядность', 'wp-diplom' ); ?></label>
					<input type="number" min="1" max="10" class="small-text" id="wpdc_param_number_padding"
						name="wpdc_params[number_padding]"
						value="<?php echo esc_attr( $saved['number_padding'] ?? '' ); ?>"
						placeholder="<?php echo esc_attr( $params['number_padding'] ); ?>">
				</span>

				<p class="description">
					<?php
					printf(
						/* translators: %s — пример номера. */
						esc_html__( 'Номер выглядит так: %s. Счётчик общий для сайта и сбрасывается каждый год.', 'wp-diplom' ),
						'<code>' . esc_html( $params['number_prefix'] . '-' . current_time( 'Y' ) . '-' . str_pad( '1', (int) $params['number_padding'], '0', STR_PAD_LEFT ) ) . '</code>'
					);
					?>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'Проверка подлинности', 'wp-diplom' ); ?></th>
			<td>
				<input type="hidden" name="wpdc_params[enable_verify]" value="0">
				<label for="wpdc_param_enable_verify">
					<input type="checkbox" id="wpdc_param_enable_verify" name="wpdc_params[enable_verify]" value="1"
						<?php checked( ! empty( $params['enable_verify'] ) ); ?>>
					<?php esc_html_e( 'Разрешить проверку по коду', 'wp-diplom' ); ?>
				</label>

				<p>
					<label for="wpdc_param_verify_page"><?php esc_html_e( 'Страница с шорткодом [wpdc_verify]', 'wp-diplom' ); ?></label>
					<select name="wpdc_params[verify_page]" id="wpdc_param_verify_page">
						<option value="0"><?php esc_html_e( '— не выбрана —', 'wp-diplom' ); ?></option>
						<?php foreach ( $pages as $wpdc_page ) : ?>
							<option value="<?php echo esc_attr( $wpdc_page->ID ); ?>" <?php selected( (int) $params['verify_page'], $wpdc_page->ID ); ?>>
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
				<label for="wpdc_param_sample_teacher"><?php esc_html_e( 'Преподаватель в предпросмотре', 'wp-diplom' ); ?></label>
			</th>
			<td>
				<input type="text" class="regular-text" id="wpdc_param_sample_teacher"
					name="wpdc_params[sample_teacher]"
					value="<?php echo esc_attr( $saved['sample_teacher'] ?? '' ); ?>"
					placeholder="<?php echo esc_attr( $params['sample_teacher'] ? $params['sample_teacher'] : __( 'Иванов И. И.', 'wp-diplom' ) ); ?>">
				<p class="description"><?php esc_html_e( 'Подставляется вместо {teacher}, когда бланк открывают в предпросмотре.', 'wp-diplom' ); ?></p>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'По умолчанию', 'wp-diplom' ); ?></th>
			<td>
				<input type="hidden" name="wpdc_is_default" value="0">
				<label for="wpdc_is_default">
					<input type="checkbox" id="wpdc_is_default" name="wpdc_is_default" value="1" <?php checked( $is_default ); ?>>
					<?php esc_html_e( 'Печатать по этому бланку, если бланк не указан', 'wp-diplom' ); ?>
				</label>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'Шорткоды', 'wp-diplom' ); ?></th>
			<td>
				<p class="description">
					<code>[wpdc_verify]</code> — <?php esc_html_e( 'форма проверки по коду', 'wp-diplom' ); ?><br>
					<code>[wpdc_certificate id="123"]</code> — <?php esc_html_e( 'карточка сертификата', 'wp-diplom' ); ?>
				</p>
			</td>
		</tr>
	</tbody>
</table>
