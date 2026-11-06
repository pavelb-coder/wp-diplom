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
 * @var string $title      Тип документа.
 * @var array  $border     Настройки рамки.
 * @var int    $pdf_id     id вложения с PDF-шаблоном.
 * @var string $pdf_name   Имя файла шаблона.
 * @var int    $pdf_page   Номер страницы PDF (с нуля).
 * @var float  $pdf_w      Ширина страницы PDF в мм.
 * @var float  $pdf_h      Высота страницы PDF в мм.
 * @var string $pdf_err    Текст последней ошибки загрузки.
 * @var string $pdf_url    Ссылка на файл шаблона.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<table class="form-table wpdc-form-table" role="presentation">
	<tbody>
		<tr>
			<th scope="row">
				<label for="wpdc_page"><?php esc_html_e( 'Формат страницы', 'wp-diplom' ); ?></label>
			</th>
			<td>
				<select name="wpdc_page" id="wpdc_page">
					<?php foreach ( WPDC_Renderer::page_size_options() as $key => $label ) : ?>
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
			<th scope="row">
				<label for="wpdc_pdf"><?php esc_html_e( 'PDF-шаблон', 'wp-diplom' ); ?></label>
			</th>
			<td>
				<?php if ( $pdf_err ) : ?>
					<div class="notice notice-error inline">
						<p><?php echo esc_html( $pdf_err ); ?></p>
					</div>
				<?php endif; ?>

				<?php if ( $pdf_id ) : ?>
					<p class="wpdc-pdf-current">
						<span class="dashicons dashicons-pdf"></span>
						<a href="<?php echo esc_url( $pdf_url ); ?>" target="_blank" rel="noopener">
							<?php echo esc_html( $pdf_name ); ?>
						</a>
						<?php if ( $pdf_w > 0 ) : ?>
							<span class="description">
								<?php
								printf(
									/* translators: 1: ширина в мм, 2: высота в мм. */
									esc_html__( '— страница %1$s×%2$s мм', 'wp-diplom' ),
									esc_html( number_format_i18n( $pdf_w, 1 ) ),
									esc_html( number_format_i18n( $pdf_h, 1 ) )
								);
								?>
							</span>
						<?php endif; ?>
					</p>

					<p>
						<label for="wpdc_pdf_page">
							<?php esc_html_e( 'Страница PDF:', 'wp-diplom' ); ?>
						</label>
						<input type="number" min="1" step="1" class="small-text" name="wpdc_pdf_page"
							id="wpdc_pdf_page" value="<?php echo esc_attr( $pdf_page + 1 ); ?>">
						<label class="wpdc-inline">
							<input type="checkbox" name="wpdc_pdf_remove" value="1">
							<?php esc_html_e( 'удалить шаблон', 'wp-diplom' ); ?>
						</label>
					</p>
				<?php endif; ?>

				<input type="hidden" name="wpdc_pdf_id" id="wpdc_pdf_id" value="0">

				<p>
					<button type="button" class="button" id="wpdc-pdf-select">
						<?php esc_html_e( 'Выбрать PDF из медиатеки', 'wp-diplom' ); ?>
					</button>
					<span class="description" id="wpdc-pdf-chosen" style="display:none"></span>
				</p>

				<p>
					<input type="file" name="wpdc_pdf" id="wpdc_pdf" accept="application/pdf,.pdf">
					<span class="description">
						<?php esc_html_e( 'или загрузите файл напрямую (не более ', 'wp-diplom' ); ?><?php echo esc_html( size_format( wp_max_upload_size() ) ); ?>)
					</span>
				</p>

				<p class="description">
					<?php esc_html_e( 'Файл должен быть без пароля. Многостраничный PDF: номера полей ниже относятся к выбранной странице.', 'wp-diplom' ); ?>
				</p>

				<p class="description">
					<?php esc_html_e( 'Загрузите готовый сертификат в PDF — плагин напечатает данные получателя прямо поверх него. Векторная графика и текст останутся векторными, качество не теряется. При загрузке формат страницы переключается на «По размеру PDF-шаблона».', 'wp-diplom' ); ?>
				</p>

				<p class="description">
					<?php esc_html_e( 'Координаты полей ниже задаются в миллиметрах от верхнего левого угла страницы PDF — так же, как вы видите их в редакторе PDF.', 'wp-diplom' ); ?>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'Фон', 'wp-diplom' ); ?></th>
			<td>
				<div class="wpdc-media">
					<input type="hidden" name="wpdc_background_id" id="wpdc_background_id"
						value="<?php echo esc_attr( $background ); ?>">

					<div class="wpdc-media__preview" id="wpdc-background-preview">
						<?php
						if ( $background ) {
							echo wp_get_attachment_image( $background, 'medium', false, array( 'alt' => '' ) );
						}
						?>
					</div>

					<p>
						<button type="button" class="button" id="wpdc-background-select">
							<?php esc_html_e( 'Выбрать изображение', 'wp-diplom' ); ?>
						</button>
						<button type="button" class="button-link button-link-delete" id="wpdc-background-remove">
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
				<label for="wpdc_background_fit"><?php esc_html_e( 'Вписывание фона', 'wp-diplom' ); ?></label>
			</th>
			<td>
				<select name="wpdc_background_fit" id="wpdc_background_fit">
					<option value="cover" <?php selected( $fit, 'cover' ); ?>><?php esc_html_e( 'Заполнить (обрезать края)', 'wp-diplom' ); ?></option>
					<option value="contain" <?php selected( $fit, 'contain' ); ?>><?php esc_html_e( 'Вписать целиком', 'wp-diplom' ); ?></option>
					<option value="stretch" <?php selected( $fit, 'stretch' ); ?>><?php esc_html_e( 'Растянуть', 'wp-diplom' ); ?></option>
				</select>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="wpdc_background_color"><?php esc_html_e( 'Цвет подложки', 'wp-diplom' ); ?></label>
			</th>
			<td>
				<input type="color" name="wpdc_background_color" id="wpdc_background_color"
					value="<?php echo esc_attr( $color ? $color : '#ffffff' ); ?>">
				<p class="description">
					<?php esc_html_e( 'Виден там, где фон не покрывает страницу (или если фон не выбран).', 'wp-diplom' ); ?>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="wpdc_document_title"><?php esc_html_e( 'Тип документа', 'wp-diplom' ); ?></label>
			</th>
			<td>
				<input type="text" class="regular-text" name="wpdc_document_title" id="wpdc_document_title"
					value="<?php echo esc_attr( $title ); ?>" placeholder="<?php esc_attr_e( 'Сертификат', 'wp-diplom' ); ?>">
				<p class="description">
					<?php esc_html_e( 'Используется в свойствах PDF и метаданных документа.', 'wp-diplom' ); ?>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'Рамка', 'wp-diplom' ); ?></th>
			<td>
				<label class="wpdc-inline">
					<input type="checkbox" name="wpdc_border_enabled" value="1" <?php checked( ! empty( $border['enabled'] ) ); ?>>
					<?php esc_html_e( 'Рисовать рамку', 'wp-diplom' ); ?>
				</label>

				<div class="wpdc-grid wpdc-grid--4">
					<label>
						<?php esc_html_e( 'Цвет', 'wp-diplom' ); ?>
						<input type="color" name="wpdc_border_color" value="<?php echo esc_attr( $border['color'] ); ?>">
					</label>

					<label>
						<?php esc_html_e( 'Толщина, мм', 'wp-diplom' ); ?>
						<input type="number" step="0.1" min="0" max="10" name="wpdc_border_width"
							value="<?php echo esc_attr( $border['width'] ); ?>">
					</label>

					<label>
						<?php esc_html_e( 'Отступ X, мм', 'wp-diplom' ); ?>
						<input type="number" step="0.5" min="0" max="100" name="wpdc_border_inset_x"
							value="<?php echo esc_attr( $border['inset_x'] ); ?>">
					</label>

					<label>
						<?php esc_html_e( 'Отступ Y, мм', 'wp-diplom' ); ?>
						<input type="number" step="0.5" min="0" max="100" name="wpdc_border_inset_y"
							value="<?php echo esc_attr( $border['inset_y'] ); ?>">
					</label>
				</div>
			</td>
		</tr>
	</tbody>
</table>
