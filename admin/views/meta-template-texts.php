<?php
/**
 * Метабокс: тексты бланка.
 *
 * Показывает стандартные фразы, которые подставляются в документ. Значение
 * берётся по цепочке: переопределение бланка → общие настройки → текст поля.
 * Пустое поле означает «как в настройках».
 *
 * @package WP_Diplom
 *
 * @var array<string, string> $slots        Названия слотов.
 * @var array<string, string> $values       Действующие значения.
 * @var array<string, string> $overrides    Переопределения бланка.
 * @var array<string, string> $global       Значения из настроек.
 * @var array<string, array>  $presets      Готовые варианты.
 * @var array<string, string> $placeholders Плейсхолдеры.
 * @var string                $settings_url Ссылка на общие настройки.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<p class="description wpdc-texts-intro">
	<?php esc_html_e( 'Эти фразы подставляются автоматически — и при предпросмотре, и при массовой генерации. Правьте здесь, если бланк должен отличаться от общих настроек. Пустое поле — «как в настройках».', 'wp-diplom' ); ?>
	<a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Общие тексты', 'wp-diplom' ); ?></a>
</p>

<table class="form-table wpdc-form-table wpdc-texts-table" role="presentation">
	<tbody>
		<?php foreach ( $slots as $slot => $label ) : ?>
			<?php
			$value      = isset( $values[ $slot ] ) ? $values[ $slot ] : '';
			$override   = isset( $overrides[ $slot ] ) ? $overrides[ $slot ] : '';
			$from_glob  = isset( $global[ $slot ] ) ? $global[ $slot ] : '';
			$variants   = isset( $presets[ $slot ] ) ? $presets[ $slot ] : array();
			$field_name = 'wpdc_text[' . $slot . ']';
			?>
			<tr>
				<th scope="row">
					<label for="wpdc_text_<?php echo esc_attr( $slot ); ?>">
						<?php echo esc_html( $label ); ?>
					</label>
				</th>
				<td>
					<textarea
						class="large-text wpdc-text-area"
						rows="2"
						id="wpdc_text_<?php echo esc_attr( $slot ); ?>"
						name="<?php echo esc_attr( $field_name ); ?>"
						data-slot="<?php echo esc_attr( $slot ); ?>"
						placeholder="<?php echo esc_attr( $value ); ?>"><?php echo esc_textarea( $override ); ?></textarea>

					<p class="wpdc-texts-hint description">
						<?php if ( '' === $override ) : ?>
							<span class="wpdc-texts-inherited">
								<?php esc_html_e( 'Сейчас действует:', 'wp-diplom' ); ?>
								<code><?php echo esc_html( $value ); ?></code>
								<?php if ( '' !== $from_glob ) : ?>
									(<?php esc_html_e( 'из настроек', 'wp-diplom' ); ?>)
								<?php else : ?>
									(<?php esc_html_e( 'по умолчанию', 'wp-diplom' ); ?>)
								<?php endif; ?>
							</span>
						<?php else : ?>
							<span class="wpdc-texts-own">
								<?php esc_html_e( 'Своё значение для этого бланка.', 'wp-diplom' ); ?>
							</span>
						<?php endif; ?>
					</p>

					<?php if ( $variants ) : ?>
						<p class="wpdc-texts-presets">
							<label class="wpdc-inline">
								<?php esc_html_e( 'Готовый вариант:', 'wp-diplom' ); ?>
								<select class="wpdc-preset-select" data-target="wpdc_text_<?php echo esc_attr( $slot ); ?>">
									<option value=""><?php esc_html_e( '— выбрать —', 'wp-diplom' ); ?></option>
									<?php foreach ( $variants as $variant ) : ?>
										<option value="<?php echo esc_attr( $variant ); ?>"><?php echo esc_html( $variant ); ?></option>
									<?php endforeach; ?>
								</select>
							</label>
						</p>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>

<p class="description">
	<?php esc_html_e( 'Доступные подстановки:', 'wp-diplom' ); ?>
	<?php foreach ( $placeholders as $code => $hint ) : ?>
		<code><?php echo esc_html( $code ); ?></code>
	<?php endforeach; ?>
</p>

<p class="description">
	<?php esc_html_e( 'Тексты рисуются в тех же прямоугольниках, что и поля бланка: положение, кегль и цвет настраиваются в блоке «Поля бланка». Произвольный блок текста добавляется там же кнопкой «Добавить свой блок».', 'wp-diplom' ); ?>
</p>
