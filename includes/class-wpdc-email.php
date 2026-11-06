<?php
/**
 * Отправка сертификатов на e-mail.
 *
 * @package WP_Diplom
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Письма с вложением PDF.
 */
class WPDC_Email {

	/**
	 * Хуки.
	 */
	public function hooks() {
		add_filter( 'wp_mail_from_name', array( $this, 'filter_from_name' ) );
	}

	/**
	 * Имя отправителя из настроек плагина.
	 *
	 * @param string $name Текущее имя.
	 * @return string
	 */
	public function filter_from_name( $name ) {
		$settings = WPDC_Plugin::settings();

		return $settings['sender_name'] ? $settings['sender_name'] : $name;
	}

	/**
	 * Плейсхолдеры письма (данные + ссылки).
	 *
	 * @param int $post_id id сертификата.
	 * @return array<string, mixed>
	 */
	public static function email_data( $post_id ) {
		$data = WPDC_Certificate::data( $post_id );

		$data['verify_url'] = WPDC_Certificate::verify_url( $data['code'] );
		$data['site_url']   = home_url( '/' );

		return $data;
	}

	/**
	 * Тема и текст письма с подставленными значениями.
	 *
	 * @param int $post_id id сертификата.
	 * @return array{subject: string, body: string}
	 */
	public static function compose( $post_id ) {
		$settings = WPDC_Plugin::settings();
		$data     = self::email_data( $post_id );

		return array(
			'subject' => WPDC_Renderer::replace_placeholders( (string) $settings['email_subject'], $data ),
			'body'    => WPDC_Renderer::replace_placeholders( (string) $settings['email_body'], $data ),
		);
	}

	/**
	 * Отправляет сертификат получателю.
	 *
	 * @param int  $post_id id сертификата.
	 * @param bool $force   Отправить, даже если уже отправляли.
	 * @return true|WP_Error
	 */
	public static function send( $post_id, $force = false ) {
		$post_id = absint( $post_id );
		$email   = (string) get_post_meta( $post_id, '_wpdc_email', true );

		if ( ! is_email( $email ) ) {
			return new WP_Error( 'wpdc_no_email', __( 'У сертификата нет корректного e-mail.', 'wp-diplom' ) );
		}

		if ( ! $force && '' !== WPDC_Certificate::sent_at( $post_id ) ) {
			return new WP_Error( 'wpdc_already_sent', __( 'Сертификат уже отправляли. Используйте повторную отправку.', 'wp-diplom' ) );
		}

		$pdf = WPDC_Certificate::pdf( $post_id );

		if ( is_wp_error( $pdf ) ) {
			return $pdf;
		}

		$message  = self::compose( $post_id );
		$settings = WPDC_Plugin::settings();

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		if ( is_email( (string) $settings['sender_email'] ) ) {
			$headers[] = sprintf( 'From: %s <%s>', $settings['sender_name'], $settings['sender_email'] );
		}

		$sent = wp_mail( $email, $message['subject'], $message['body'], $headers, array( $pdf ) );

		if ( ! $sent ) {
			return new WP_Error( 'wpdc_mail_failed', __( 'wp_mail() не смог отправить письмо. Проверьте настройки SMTP.', 'wp-diplom' ) );
		}

		update_post_meta( $post_id, '_wpdc_sent_at', current_time( 'mysql' ) );
		update_post_meta( $post_id, '_wpdc_sent_to', $email );

		return true;
	}
}
