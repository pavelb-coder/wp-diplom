<?php
/**
 * Страница массовой генерации из CSV.
 *
 * @package WP_Diplom
 *
 * @var array|false $result Результат последнего запуска из transient.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wpd_templates = get_posts(
	array(
		'post_type'      => 'wpd_template',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'orderby'        => 'title',
		'order'          => 'ASC',
	)
);

$wpd_notice = isset( $_GET['wpd_notice'] ) ? sanitize_key( wp_unslash( $_GET['wpd_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>
<div class="wrap wpd-wrap">
	<h1><?php esc_html_e( 'Массовая генерация сертификатов', 'wp-diplom' ); ?></h1>

	<?php if ( 'wpd-bulk-empty' === $wpd_notice ) : ?>
		<div class="notice notice-warning is-dismissible">
			<p><?php esc_html_e( 'Не удалось прочитать ни одной строки. Проверьте формат CSV: первая строка — заголовки колонок.', 'wp-diplom' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( 'wpd-bulk-done' === $wpd_notice && is_array( $result ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php
				printf(
					/* translators: 1: создано, 2: ошибок, 3: отправлено писем. */
					esc_html__( 'Готово. Создано: %1$d, ошибок: %2$d, писем отправлено: %3$d.', 'wp-diplom' ),
					(int) $result['created'],
					(int) $result['failed'],
					(int) $result['mailed']
				);
				?>
			</p>

			<?php if ( ! empty( $result['ids'] ) ) : ?>
				<p>
					<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=wpd_certificate' ) ); ?>">
						<?php esc_html_e( 'Открыть список сертификатов', 'wp-diplom' ); ?>
					</a>

					<?php if ( class_exists( 'ZipArchive' ) ) : ?>
						<a class="button button-primary"
							href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'wpd_bulk_zip' ), admin_url( 'admin-post.php' ) ), 'wpd_bulk_zip' ) ); ?>">
							<?php esc_html_e( 'Скачать все PDF (ZIP)', 'wp-diplom' ); ?>
						</a>
					<?php endif; ?>
				</p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<div class="wpd-columns">
		<div class="wpd-columns__main">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<input type="hidden" name="action" value="wpd_bulk_generate">
				<?php wp_nonce_field( 'wpd_bulk_generate' ); ?>

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="wpd_bulk_template"><?php esc_html_e( 'Бланк', 'wp-diplom' ); ?></label>
							</th>
							<td>
								<select name="wpd_bulk_template" id="wpd_bulk_template">
									<option value="0"><?php esc_html_e( 'По умолчанию из настроек', 'wp-diplom' ); ?></option>
									<?php foreach ( $wpd_templates as $wpd_template ) : ?>
										<option value="<?php echo esc_attr( $wpd_template->ID ); ?>">
											<?php echo esc_html( $wpd_template->post_title ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="wpd_bulk_csv"><?php esc_html_e( 'CSV-данные', 'wp-diplom' ); ?></label>
							</th>
							<td>
								<textarea name="wpd_bulk_csv" id="wpd_bulk_csv" rows="12" class="large-text code"
									placeholder="name;email;course;hours;teacher&#10;Иванов Иван;ivan@example.com;Веб-разработка;72;Петров П. П."></textarea>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="wpd_bulk_file"><?php esc_html_e( '…или файл CSV', 'wp-diplom' ); ?></label>
							</th>
							<td>
								<input type="file" name="wpd_bulk_file" id="wpd_bulk_file" accept=".csv,text/csv">
								<p class="description"><?php esc_html_e( 'Файл имеет приоритет над текстом выше.', 'wp-diplom' ); ?></p>
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'E-mail', 'wp-diplom' ); ?></th>
							<td>
								<label class="wpd-inline">
									<input type="checkbox" name="wpd_bulk_email" value="1">
									<?php esc_html_e( 'Сразу отправить сертификаты получателям', 'wp-diplom' ); ?>
								</label>
								<p class="description">
									<?php esc_html_e( 'Отправка выполняется через wp_mail(): при большом списке используйте SMTP-плагин и учитывайте лимиты хостинга.', 'wp-diplom' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Сгенерировать сертификаты', 'wp-diplom' ); ?>
					</button>
				</p>
			</form>
		</div>

		<aside class="wpd-columns__side">
			<div class="wpd-card">
				<h2><?php esc_html_e( 'Формат CSV', 'wp-diplom' ); ?></h2>

				<p><?php esc_html_e( 'Первая строка — заголовки. Разделитель «;» или «,» определяется автоматически.', 'wp-diplom' ); ?></p>

				<pre class="wpd-code">name;email;course;hours;teacher;group
Иванов Иван;ivan@example.com;Веб-разработка;72;Петров П. П.;ВР-21
Петрова Анна;anna@example.com;UI/UX-дизайн;48;Сидорова А. А.;ДЗ-12</pre>

				<p><strong><?php esc_html_e( 'Распознаваемые колонки', 'wp-diplom' ); ?></strong></p>
				<ul class="wpd-list">
					<li><code>name</code>, <code>фио</code>, <code>имя</code> — получатель</li>
					<li><code>email</code>, <code>почта</code> — адрес</li>
					<li><code>course</code>, <code>курс</code>, <code>программа</code></li>
					<li><code>hours</code>, <code>часы</code></li>
					<li><code>teacher</code>, <code>преподаватель</code></li>
					<li><code>date</code>, <code>дата</code> — формат ГГГГ-ММ-ДД</li>
				</ul>

				<p class="description">
					<?php
					printf(
						/* translators: %s — пример плейсхолдера. */
						esc_html__( 'Все остальные колонки доступны в бланке как %s', 'wp-diplom' ),
						'<code>{custom_группа}</code>'
					);
					?>
				</p>
			</div>
		</aside>
	</div>
</div>
