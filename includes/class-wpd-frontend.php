<?php
/**
 * Публичная часть: шорткоды проверки и карточки, выдача PDF по коду.
 *
 * @package WP_Diplom
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Фронтенд плагина.
 */
class WPD_Frontend {

	/**
	 * Хуки.
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'add_query_var' ) );
		add_action( 'template_redirect', array( $this, 'maybe_serve_pdf' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );

		add_shortcode( 'wpd_verify', array( $this, 'shortcode_verify' ) );
		add_shortcode( 'wpd_certificate', array( $this, 'shortcode_certificate' ) );
	}

	/**
	 * Переменная запроса для кода.
	 */
	public function add_query_var() {
		add_rewrite_tag( '%wpd_code%', '([A-Za-z0-9]{4,32})' );
	}

	/**
	 * Стили.
	 */
	public function assets() {
		wp_enqueue_style( 'wpd-frontend', WPD_URL . 'assets/css/frontend.css', array(), WPD_VERSION );
	}

	/**
	 * Отдаёт PDF по адресу /certificate/{код}/.
	 */
	public function maybe_serve_pdf() {
		$code = get_query_var( 'wpd_code' );

		if ( ! $code ) {
			return;
		}

		$settings = WPD_Plugin::settings();

		if ( empty( $settings['enable_verify'] ) ) {
			wp_die( esc_html__( 'Проверка сертификатов отключена.', 'wp-diplom' ), 404 );
		}

		$post = WPD_Certificate::by_code( $code );

		if ( ! $post ) {
			status_header( 404 );
			nocache_headers();

			wp_die(
				esc_html__( 'Сертификат с таким кодом не найден.', 'wp-diplom' ),
				esc_html__( 'Сертификат не найден', 'wp-diplom' ),
				array( 'response' => 404 )
			);
		}

		if ( get_post_meta( $post->ID, '_wpd_revoked', true ) ) {
			wp_die(
				esc_html__( 'Сертификат отозван.', 'wp-diplom' ),
				esc_html__( 'Сертификат отозван', 'wp-diplom' ),
				array( 'response' => 410 )
			);
		}

		$file = WPD_Certificate::pdf( $post->ID );

		if ( is_wp_error( $file ) ) {
			wp_die( esc_html( $file->get_error_message() ) );
		}

		$contents = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		nocache_headers();

		header( 'Content-Type: application/pdf' );
		header( 'Content-Length: ' . strlen( $contents ) );
		header( 'Content-Disposition: inline; filename="certificate-' . sanitize_file_name( $code ) . '.pdf"' );

		echo $contents; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Бинарный PDF.
		exit;
	}

	/**
	 * Шорткод [wpd_verify].
	 *
	 * @param array $atts Атрибуты.
	 * @return string
	 */
	public function shortcode_verify( $atts ) {
		$atts = shortcode_atts(
			array(
				'title' => __( 'Проверка сертификата', 'wp-diplom' ),
			),
			$atts,
			'wpd_verify'
		);

		$settings = WPD_Plugin::settings();

		if ( empty( $settings['enable_verify'] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$code = isset( $_GET['wpd_code'] ) ? sanitize_text_field( wp_unslash( $_GET['wpd_code'] ) ) : '';

		ob_start();
		?>
		<div class="wpd-verify">
			<h2 class="wpd-verify__title"><?php echo esc_html( $atts['title'] ); ?></h2>

			<form class="wpd-verify__form" method="get" action="">
				<label class="wpd-verify__label" for="wpd-verify-code">
					<?php esc_html_e( 'Введите код с сертификата', 'wp-diplom' ); ?>
				</label>

				<div class="wpd-verify__row">
					<input type="text" id="wpd-verify-code" name="wpd_code" class="wpd-verify__input"
						value="<?php echo esc_attr( $code ); ?>" placeholder="a1b2c3d4e5f6" autocomplete="off">
					<button type="submit" class="wpd-button"><?php esc_html_e( 'Проверить', 'wp-diplom' ); ?></button>
				</div>
			</form>

			<?php if ( '' !== $code ) : ?>
				<?php echo wp_kses_post( $this->verify_result( $code ) ); ?>
			<?php endif; ?>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Результат проверки кода.
	 *
	 * @param string $code Код.
	 * @return string HTML.
	 */
	private function verify_result( $code ) {
		$post = WPD_Certificate::by_code( $code );

		if ( ! $post ) {
			return sprintf(
				'<div class="wpd-result wpd-result--invalid"><p>%s</p></div>',
				esc_html__( 'Сертификат с таким кодом не найден. Проверьте код или обратитесь к организации, выдавшей документ.', 'wp-diplom' )
			);
		}

		if ( get_post_meta( $post->ID, '_wpd_revoked', true ) ) {
			return sprintf(
				'<div class="wpd-result wpd-result--revoked"><p>%s</p></div>',
				esc_html__( 'Сертификат отозван и недействителен.', 'wp-diplom' )
			);
		}

		$data = WPD_Certificate::data( $post->ID );

		$rows = array(
			__( 'Получатель', 'wp-diplom' )  => $data['recipient_name'],
			__( 'Программа', 'wp-diplom' )   => $data['course_title'],
			__( 'Часов', 'wp-diplom' )       => $data['course_hours'],
			__( 'Дата выдачи', 'wp-diplom' ) => $data['date'],
			__( 'Номер', 'wp-diplom' )       => $data['number'],
			__( 'Преподаватель', 'wp-diplom' ) => $data['teacher'],
		);

		$html  = '<div class="wpd-result wpd-result--valid">';
		$html .= '<p class="wpd-result__status">' . esc_html__( 'Сертификат действителен', 'wp-diplom' ) . '</p>';
		$html .= '<table class="wpd-result__table"><tbody>';

		foreach ( $rows as $label => $value ) {
			if ( '' === trim( (string) $value ) ) {
				continue;
			}

			$html .= sprintf(
				'<tr><th>%s</th><td>%s</td></tr>',
				esc_html( $label ),
				esc_html( $value )
			);
		}

		$html .= '</tbody></table>';
		$html .= sprintf(
			'<p><a class="wpd-button wpd-button--outline" href="%s" target="_blank" rel="noopener">%s</a></p>',
			esc_url( home_url( '/certificate/' . $data['code'] . '/' ) ),
			esc_html__( 'Открыть PDF', 'wp-diplom' )
		);
		$html .= '</div>';

		return $html;
	}

	/**
	 * Шорткод [wpd_certificate id="123"].
	 *
	 * @param array $atts Атрибуты.
	 * @return string
	 */
	public function shortcode_certificate( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'    => 0,
				'code'  => '',
				'title' => '',
			),
			$atts,
			'wpd_certificate'
		);

		$post = $atts['code']
			? WPD_Certificate::by_code( $atts['code'] )
			: get_post( absint( $atts['id'] ) );

		if ( ! $post || 'wpd_certificate' !== $post->post_type ) {
			return '';
		}

		if ( 'publish' !== $post->post_status || get_post_meta( $post->ID, '_wpd_revoked', true ) ) {
			return '';
		}

		$data  = WPD_Certificate::data( $post->ID );
		$title = $atts['title'] ? $atts['title'] : $data['number'];

		ob_start();
		?>
		<div class="wpd-card-certificate">
			<div class="wpd-card-certificate__body">
				<?php if ( $title ) : ?>
					<p class="wpd-card-certificate__number"><?php echo esc_html( $title ); ?></p>
				<?php endif; ?>

				<p class="wpd-card-certificate__name"><?php echo esc_html( $data['recipient_name'] ); ?></p>

				<?php if ( $data['course_title'] ) : ?>
					<p class="wpd-card-certificate__course"><?php echo esc_html( $data['course_title'] ); ?></p>
				<?php endif; ?>

				<?php if ( $data['date'] ) : ?>
					<p class="wpd-card-certificate__date"><?php echo esc_html( $data['date'] ); ?></p>
				<?php endif; ?>

				<p>
					<a class="wpd-button" href="<?php echo esc_url( home_url( '/certificate/' . $data['code'] . '/' ) ); ?>"
						target="_blank" rel="noopener">
						<?php esc_html_e( 'Скачать PDF', 'wp-diplom' ); ?>
					</a>
				</p>
			</div>
		</div>
		<?php

		return (string) ob_get_clean();
	}
}
