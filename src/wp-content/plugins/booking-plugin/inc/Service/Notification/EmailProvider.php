<?php
/**
 * Native Email Notification Provider
 *
 * @package SnippenBooking\Service\Notification
 */

namespace SnippenBooking\Service\Notification;

/**
 * Class EmailProvider
 */
class EmailProvider implements EmailProviderInterface {

	/**
	 * Get the unique provider identifier.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'email';
	}

	/**
	 * Get the human-readable provider name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'Kun E-post', 'snippen-booking' );
	}

	/**
	 * Get the dynamic settings schema.
	 *
	 * @return array
	 */
	public function get_settings_schema(): array {
		return array(
			array(
				'key'         => 'snippen_smtp_enabled',
				'label'       => __( 'Aktiver e-post via SMTP', 'snippen-booking' ),
				'type'        => 'checkbox',
				'required'    => false,
				'description' => '',
			),
			array(
				'key'         => 'snippen_smtp_host',
				'label'       => __( 'SMTP Vert (Host)', 'snippen-booking' ),
				'type'        => 'text',
				'required'    => false,
				'description' => '',
			),
			array(
				'key'         => 'snippen_smtp_port',
				'label'       => __( 'SMTP Port', 'snippen-booking' ),
				'type'        => 'number',
				'required'    => false,
				'description' => '',
			),
			array(
				'key'         => 'snippen_smtp_user',
				'label'       => __( 'SMTP Brukernavn', 'snippen-booking' ),
				'type'        => 'text',
				'required'    => false,
				'description' => '',
			),
			array(
				'key'         => 'snippen_smtp_pass',
				'label'       => __( 'SMTP Passord', 'snippen-booking' ),
				'type'        => 'password',
				'required'    => false,
				'description' => '',
			),
			array(
				'key'         => 'snippen_smtp_encryption',
				'label'       => __( 'Kryptering (Encryption)', 'snippen-booking' ),
				'type'        => 'select',
				'required'    => false,
				'options'     => array(
					'none' => __( 'Ingen', 'snippen-booking' ),
					'ssl'  => __( 'SSL', 'snippen-booking' ),
					'tls'  => __( 'TLS', 'snippen-booking' ),
				),
				'description' => '',
			),
			array(
				'key'         => 'snippen_smtp_from_email',
				'label'       => __( 'Avsender E-post (From Email)', 'snippen-booking' ),
				'type'        => 'email',
				'required'    => false,
				'description' => '',
			),
			array(
				'key'         => 'snippen_smtp_from_name',
				'label'       => __( 'Avsender Navn (From Name)', 'snippen-booking' ),
				'type'        => 'text',
				'required'    => false,
				'description' => '',
			),
		);
	}

	/**
	 * Check if configured. Email fallback is always considered configured.
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		return true;
	}

	/**
	 * Send an email.
	 *
	 * @param string $to      Recipient.
	 * @param string $subject Subject.
	 * @param string $message Message.
	 * @return bool
	 */
	public function send_email( string $to, string $subject, string $message ): bool {
		return (bool) wp_mail( $to, $subject, $message );
	}
}
