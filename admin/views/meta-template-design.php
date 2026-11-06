<?php
/**
 * Метабокс: дизайн бланка.
 *
 * @package WP_Diplom
 *
 * @var string $page       Размер страницы.
 * @var int    $background id изображения фона.
 * @var string $color      Цвет фона.
 * @var string $fit        Режим вписывания фона.
 * @var string $org        Название организации.
 * @var string $title      Тип документа.
 * @var array  $border     Настройки рамки.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<table class="form-table wpd-form-table" role="presentation">
	<tbody>
		<tr>
			<th scope="row">
				<label for="wpd_page"><?php esc_html_e( 'Формат страницы', 'wp-diplom' ); ?></label>
			</th>
			<td>
				<select name="wpd_page" id="wpd_page">
					<?php foreach ( WPD_Renderer::page_size_options() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $page, $key ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description">
					<?php esc_html_e( 'Поля бланка задаются в миллиметрах от верхнего левого угла.', 'wp-diplom' ); ?>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'Фон', 'wp-diplom' ); ?></th>
			<td>
				<div class="wpd-media">
					<input type="hidden" name="wpd_background_id" id="wpd_background_id"
						value="<?php echo esc_attr( $background ); ?>">

					<div class="wpd-media__preview" id="wpd-background-preview">
						<?php
						if ( $background ) {
							echo wp_get_attachment_image( $background, 'medium', false, array( 'alt' => '' ) );
						}
						?>
					</div>

					<p>
						<button type="button" class="button" id="wpd-background-select">
							<?php esc_html_e( 'Выбрать изображение', 'wp-diplom' ); ?>
						</button>
						<button type="button" class="button-link button-link-delete" id="wpd-background-remove">
							<?php esc_html_e( 'Убрать', 'wp-diplom' ); ?>
						</button>
					</p>

					<p class="description">
						<?php esc_html_e( 'Поддерживаются JPEG и PNG (в том числе с прозрачностью). Рекомендуемый размер для A4 — 3508×2480 px.', 'wp-diplom' ); ?>
					</p>
				</div>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="wpd_background_fit"><?php esc_html_e( 'Вписывание фона', 'wp-diplom' ); ?></label>
			</th>
			<td>
				<select name="wpd_background_fit" id="wpd_background_fit">
					<option value="cover" <?php selected( $fit, 'cover' ); ?>><?php esc_html_e( 'Заполнить (обрезать края)', 'wp-diplom' ); ?></option>
					<option value="contain" <?php selected( $fit, 'contain' ); ?>><?php esc_html_e( 'Вписать целиком', 'wp-diplom' ); ?></option>
					<option value="stretch" <?php selected( $fit, 'stretch' ); ?>><?php esc_html_e( 'Растянуть', 'wp-diplom' ); ?></option>
				</select>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="wpd_background_color"><?php esc_html_e( 'Цвет подложки', 'wp-diplom' ); ?></label>
			</th>
			<td>
				<input type="color" name="wpd_background_color" id="wpd_background_color"
					value="<?php echo esc_attr( $color ? $color : '#ffffff' ); ?>">
				<p class="description">
					<?php esc_html_e( 'Виден там, где фон не покрывает страницу (или если фон не выбран).', 'wp-diplom' ); ?>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="wpd_organization"><?php esc_html_e( 'Организация', 'wp-diplom' ); ?></label>
			</th>
			<td>
				<input type="text" class="regular-text" name="wpd_organization" id="wpd_organization"
					value="<?php echo esc_attr( $org ); ?>"
					placeholder="<?php echo esc_attr( WPD_Plugin::settings( 'organization' ) ); ?>">
				<p class="description">
					<?php esc_html_e( 'Подставляется в плейсхолдер {organization}. Пустое значение — берётся из настроек плагина.', 'wp-diplom' ); ?>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="wpd_document_title"><?php esc_html_e( 'Тип документа', 'wp-diplom' ); ?></label>
			</th>
			<td>
				<input type="text" class="regular-text" name="wpd_document_title" id="wpd_document_title"
					value="<?php echo esc_attr( $title ); ?>" placeholder="<?php esc_attr_e( 'Сертификат', 'wp-diplom' ); ?>">
				<p class="description">
					<?php esc_html_e( 'Используется в свойствах PDF и метаданных документа.', 'wp-diplom' ); ?>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'Рамка', 'wp-diplom' ); ?></th>
			<td>
				<label class="wpd-inline">
					<input type="checkbox" name="wpd_border_enabled" value="1" <?php checked( ! empty( $border['enabled'] ) ); ?>>
					<?php esc_html_e( 'Рисовать рамку', 'wp-diplom' ); ?>
				</label>

				<div class="wpd-grid wpd-grid--4">
					<label>
						<?php esc_html_e( 'Цвет', 'wp-diplom' ); ?>
						<input type="color" name="wpd_border_color" value="<?php echo esc_attr( $border['color'] ); ?>">
					</label>

					<label>
						<?php esc_html_e( 'Толщина, мм', 'wp-diplom' ); ?>
						<input type="number" step="0.1" min="0" max="10" name="wpd_border_width"
							value="<?php echo esc_attr( $border['width'] ); ?>">
					</label>

					<label>
						<?php esc_html_e( 'Отступ X, мм', 'wp-diplom' ); ?>
						<input type="number" step="0.5" min="0" max="100" name="wpd_border_inset_x"
							value="<?php echo esc_attr( $border['inset_x'] ); ?>">
					</label>

					<label>
						<?php esc_html_e( 'Отступ Y, мм', 'wp-diplom' ); ?>
						<input type="number" step="0.5" min="0" max="100" name="wpd_border_inset_y"
							value="<?php echo esc_attr( $border['inset_y'] ); ?>">
					</label>
				</div>
			</td>
		</tr>
	</tbody>
</table>
