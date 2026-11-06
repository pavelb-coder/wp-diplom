<?php
/**
 * Метабокс: шрифты бланка.
 *
 * Позволяет подключить свои .ttf-шрифты (например, брендовые) и печатать ими
 * текст бланка. Файлы выбираются из медиатеки: форма редактирования записи по
 * умолчанию без enctype, поэтому поле загрузки в ней не сработало бы.
 *
 * @package WP_Diplom
 *
 * @var array<int, array<string, mixed>> $fonts      Подключённые шрифты.
 * @var array<string, string>            $builtin    Встроенные семейства.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Отрисовка строки шрифта.
 *
 * @param array<string, mixed> $font  Шрифт.
 * @param int|string           $index Индекс.
 */
$render_row = static function ( $font, $index ) {
	$font = array_merge(
		array(
			'family'  => '',
			'label'   => '',
			'regular' => 0,
			'bold'    => 0,
		),
		$font
	);

	$name     = 'wpdc_fonts[' . $index . ']';
	$regular  = absint( $font['regular'] );
	$bold     = absint( $font['bold'] );
	$r_name   = $regular ? basename( (string) get_attached_file( $regular ) ) : '';
	$b_name   = $bold ? basename( (string) get_attached_file( $bold ) ) : '';
	?>
	<tr class="wpdc-font-row">
		<td>
			<input type="text" class="wpdc-font-family" name="<?php echo esc_attr( $name ); ?>[family]"
				value="<?php echo esc_attr( $font['family'] ); ?>" placeholder="montserrat">
		</td>

		<td>
			<input type="text" class="regular-text" name="<?php echo esc_attr( $name ); ?>[label]"
				value="<?php echo esc_attr( $font['label'] ); ?>"
				placeholder="<?php esc_attr_e( 'Название в списке', 'wp-diplom' ); ?>">
		</td>

		<td>
			<input type="hidden" class="wpdc-font-id" name="<?php echo esc_attr( $name ); ?>[regular]"
				value="<?php echo esc_attr( $regular ? $regular : '' ); ?>">
			<button type="button" class="button wpdc-font-pick">
				<?php esc_html_e( 'Выбрать .ttf', 'wp-diplom' ); ?>
			</button>
			<span class="wpdc-font-name"><?php echo esc_html( $r_name ); ?></span>
		</td>

		<td>
			<input type="hidden" class="wpdc-font-id" name="<?php echo esc_attr( $name ); ?>[bold]"
				value="<?php echo esc_attr( $bold ? $bold : '' ); ?>">
			<button type="button" class="button wpdc-font-pick">
				<?php esc_html_e( 'Выбрать .ttf', 'wp-diplom' ); ?>
			</button>
			<span class="wpdc-font-name"><?php echo esc_html( $b_name ); ?></span>
		</td>

		<td>
			<button type="button" class="button-link wpdc-remove-font" title="<?php esc_attr_e( 'Убрать шрифт', 'wp-diplom' ); ?>">
				<span class="dashicons dashicons-trash"></span>
			</button>
		</td>
	</tr>
	<?php
};
?>
<div class="wpdc-fonts">
	<p class="description">
		<?php esc_html_e( 'Ключ — латиницей (a–z, 0–9): он связывает шрифт с полями бланка. Если оставить поле пустым, ключ возьмётся из имени файла.', 'wp-diplom' ); ?>
	</p>

	<p class="description">
		<?php esc_html_e( 'Подключите свои шрифты в формате .ttf — они появятся в списке гарнитур у каждого поля бланка. Файл выбирается из медиатеки: загрузите его в разделе «Медиафайлы» или кнопкой ниже. Кириллица обязательна — шрифт без неё напечатает пустые прямоугольники.', 'wp-diplom' ); ?>
	</p>

	<table class="widefat striped wpdc-fonts-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Ключ', 'wp-diplom' ); ?></th>
				<th><?php esc_html_e( 'Название', 'wp-diplom' ); ?></th>
				<th><?php esc_html_e( 'Обычный', 'wp-diplom' ); ?></th>
				<th><?php esc_html_e( 'Жирный', 'wp-diplom' ); ?></th>
				<th></th>
			</tr>
		</thead>

		<tbody id="wpdc-fonts-body">
			<?php foreach ( $fonts as $index => $font ) : ?>
				<?php $render_row( $font, $index ); ?>
			<?php endforeach; ?>
		</tbody>

		<tfoot>
			<tr>
				<td colspan="5">
					<button type="button" class="button" id="wpdc-add-font">
						<?php esc_html_e( '+ Добавить шрифт', 'wp-diplom' ); ?>
					</button>
					<span class="description">
						<?php
						printf(
							/* translators: %s — список встроенных семейств. */
							esc_html__( 'Встроенные гарнитуры доступны всегда: %s.', 'wp-diplom' ),
							esc_html( implode( ', ', $builtin ) )
						);
						?>
					</span>
				</td>
			</tr>
		</tfoot>
	</table>
</div>

<script type="text/html" id="tmpl-wpdc-font-row">
	<?php $render_row( array(), '__index__' ); ?>
</script>
