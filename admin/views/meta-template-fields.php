<?php
/**
 * Метабокс: поля бланка.
 *
 * @package WP_Diplom
 *
 * @var array<int, array<string, mixed>> $fields       Поля.
 * @var array<string, string>            $placeholders Плейсхолдеры.
 * @var string                           $preview_url  Ссылка предпросмотра.
 * @var array<string, string>            $families     Гарнитуры: ключ => название.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Отрисовка строки поля.
 *
 * @param array<string, mixed> $field Поле.
 * @param int                  $index Индекс.
 */
$render_row = static function ( $field, $index ) use ( $families ) {
	$field = array_merge(
		array(
			'id'          => '',
			'text'        => '',
			'x'           => 20,
			'y'           => 20,
			'w'           => 100,
			'h'           => 12,
			'size'        => 14,
			'font'        => 'sans',
			'style'       => 'regular',
			'color'       => '#141a2b',
			'align'       => 'center',
			'line_height' => 1.25,
			'valign'      => 'middle',
			'uppercase'   => false,
			'keep_empty'  => false,
		),
		$field
	);

	$name = 'wpdc_field[' . (int) $index . ']';
	?>
	<tr class="wpdc-field-row">
		<td class="wpdc-field-row__handle" title="<?php esc_attr_e( 'Перетащите, чтобы изменить порядок', 'wp-diplom' ); ?>">
			<span class="dashicons dashicons-menu"></span>
		</td>

		<td>
			<input type="text" class="widefat wpdc-field-id" name="<?php echo esc_attr( $name ); ?>[id]"
				value="<?php echo esc_attr( $field['id'] ); ?>" placeholder="title">
		</td>

		<td>
			<?php if ( class_exists( 'WPDC_Texts' ) && WPDC_Texts::is_slot( $field['id'] ) ) : ?>
				<?php // Стандартная фраза: текст правится в блоке «Тексты бланка», здесь — только положение. ?>
				<input type="hidden" name="<?php echo esc_attr( $name ); ?>[text]"
					value="<?php echo esc_attr( $field['text'] ); ?>">
				<span class="wpdc-field-text-preview"><?php echo esc_html( $field['text'] ); ?></span>
				<p class="description">
					<?php esc_html_e( 'Текст задаётся в блоке «Тексты бланка».', 'wp-diplom' ); ?>
				</p>
			<?php else : ?>
				<textarea class="widefat" rows="2" name="<?php echo esc_attr( $name ); ?>[text]"
					placeholder="<?php esc_attr_e( 'Текст или {recipient_name}', 'wp-diplom' ); ?>"><?php echo esc_textarea( $field['text'] ); ?></textarea>
			<?php endif; ?>
		</td>

		<td><input type="number" step="0.5" class="wpdc-num" name="<?php echo esc_attr( $name ); ?>[x]" value="<?php echo esc_attr( $field['x'] ); ?>"></td>
		<td><input type="number" step="0.5" class="wpdc-num" name="<?php echo esc_attr( $name ); ?>[y]" value="<?php echo esc_attr( $field['y'] ); ?>"></td>
		<td><input type="number" step="0.5" class="wpdc-num" name="<?php echo esc_attr( $name ); ?>[w]" value="<?php echo esc_attr( $field['w'] ); ?>"></td>
		<td><input type="number" step="0.5" class="wpdc-num" name="<?php echo esc_attr( $name ); ?>[h]" value="<?php echo esc_attr( $field['h'] ); ?>"></td>
		<td><input type="number" step="0.5" class="wpdc-num" name="<?php echo esc_attr( $name ); ?>[size]" value="<?php echo esc_attr( $field['size'] ); ?>"></td>

		<td>
			<select name="<?php echo esc_attr( $name ); ?>[font]">
				<?php foreach ( $families as $wpdc_family => $wpdc_label ) : ?>
					<option value="<?php echo esc_attr( $wpdc_family ); ?>" <?php selected( $field['font'], $wpdc_family ); ?>>
						<?php echo esc_html( $wpdc_label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</td>

		<td>
			<select name="<?php echo esc_attr( $name ); ?>[style]">
				<option value="regular" <?php selected( $field['style'], 'regular' ); ?>><?php esc_html_e( 'обычный', 'wp-diplom' ); ?></option>
				<option value="bold" <?php selected( $field['style'], 'bold' ); ?>><?php esc_html_e( 'жирный', 'wp-diplom' ); ?></option>
			</select>
		</td>

		<td>
			<select name="<?php echo esc_attr( $name ); ?>[align]">
				<option value="left" <?php selected( $field['align'], 'left' ); ?>><?php esc_html_e( 'слева', 'wp-diplom' ); ?></option>
				<option value="center" <?php selected( $field['align'], 'center' ); ?>><?php esc_html_e( 'центр', 'wp-diplom' ); ?></option>
				<option value="right" <?php selected( $field['align'], 'right' ); ?>><?php esc_html_e( 'справа', 'wp-diplom' ); ?></option>
			</select>
		</td>

		<td><input type="color" name="<?php echo esc_attr( $name ); ?>[color]" value="<?php echo esc_attr( $field['color'] ); ?>"></td>

		<td class="wpdc-field-row__flags">
			<label title="<?php esc_attr_e( 'Верхний регистр', 'wp-diplom' ); ?>">
				<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[uppercase]" value="1" <?php checked( ! empty( $field['uppercase'] ) ); ?>>
				AA
			</label>
			<label title="<?php esc_attr_e( 'Выводить, даже если значение пустое', 'wp-diplom' ); ?>">
				<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[keep_empty]" value="1" <?php checked( ! empty( $field['keep_empty'] ) ); ?>>
				∅
			</label>
		</td>

		<td>
			<button type="button" class="button-link wpdc-remove-field" title="<?php esc_attr_e( 'Удалить поле', 'wp-diplom' ); ?>">
				<span class="dashicons dashicons-trash"></span>
			</button>
		</td>
	</tr>
	<?php
};
?>
<div class="wpdc-fields">
	<p class="description">
		<?php esc_html_e( 'Каждое поле — прямоугольник на бланке. Координаты и размеры в миллиметрах, кегль в пунктах (pt). Текст центрируется по вертикали внутри своей области.', 'wp-diplom' ); ?>
	</p>

	<div class="wpdc-fields__scroll">
		<table class="widefat wpdc-fields-table">
			<thead>
				<tr>
					<th></th>
					<th><?php esc_html_e( 'ID', 'wp-diplom' ); ?></th>
					<th><?php esc_html_e( 'Текст', 'wp-diplom' ); ?></th>
					<th>X</th>
					<th>Y</th>
					<th><?php esc_html_e( 'Ш', 'wp-diplom' ); ?></th>
					<th><?php esc_html_e( 'В', 'wp-diplom' ); ?></th>
					<th><?php esc_html_e( 'Pt', 'wp-diplom' ); ?></th>
					<th><?php esc_html_e( 'Шрифт', 'wp-diplom' ); ?></th>
					<th><?php esc_html_e( 'Начертание', 'wp-diplom' ); ?></th>
					<th><?php esc_html_e( 'Выравнивание', 'wp-diplom' ); ?></th>
					<th><?php esc_html_e( 'Цвет', 'wp-diplom' ); ?></th>
					<th></th>
					<th></th>
				</tr>
			</thead>

			<tbody id="wpdc-fields-body">
				<?php foreach ( $fields as $index => $field ) : ?>
					<?php $render_row( $field, $index ); ?>
				<?php endforeach; ?>
			</tbody>

			<tfoot>
				<tr>
					<td colspan="14">
						<button type="button" class="button" id="wpdc-add-field">
							<?php esc_html_e( '+ Добавить свой блок', 'wp-diplom' ); ?>
						</button>
					</td>
				</tr>
			</tfoot>
		</table>
	</div>

	<div class="wpdc-fields__help">
		<p><strong><?php esc_html_e( 'Плейсхолдеры', 'wp-diplom' ); ?></strong></p>
		<ul class="wpdc-placeholders">
			<?php foreach ( $placeholders as $code => $label ) : ?>
				<li><code><?php echo esc_html( $code ); ?></code> — <?php echo esc_html( $label ); ?></li>
			<?php endforeach; ?>
		</ul>
	</div>

	<p>
		<a href="<?php echo esc_url( $preview_url ); ?>" target="_blank" rel="noopener" class="button button-primary">
			<?php esc_html_e( 'Открыть предпросмотр PDF', 'wp-diplom' ); ?>
		</a>
		<span class="description">
			<?php esc_html_e( 'Предпросмотр использует сохранённые значения — сначала обновите бланк.', 'wp-diplom' ); ?>
		</span>
	</p>
</div>

<script type="text/html" id="tmpl-wpdc-field-row">
	<?php $render_row( array(), '__index__' ); ?>
</script>
