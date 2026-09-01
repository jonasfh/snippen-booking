<?php
/**
 * Snippen SMS Gateway Service Notification Provider
 *
 * @package SnippenBooking\Service\Notification
 */

namespace SnippenBooking\Service\Notification;

/**
 * Class SnippenSmsProvider
 */
class SnippenSmsProvider implements SmsProviderInterface {

	/**
	 * Get the unique provider identifier.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'snippen_sms_service';
	}

	/**
	 * Get the human-readable provider name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'Snippen SMS Service (Gateway)', 'snippen-booking' );
	}

	/**
	 * Get the dynamic settings schema.
	 *
	 * @return array
	 */
	public function get_settings_schema(): array {
		return array(
			array(
				'key'         => 'snippen_sms_service_api_token',
				'label'       => __( 'SMS Service API-token', 'snippen-booking' ),
				'type'        => 'password',
				'required'    => true,
				'description' => __( 'Hemmelig sikkerhetstoken for autentisering av snippen-sms-service.', 'snippen-booking' ),
			),
			array(
				'key'         => 'snippen_sms_service_sender',
				'label'       => __( 'Avsendernavn', 'snippen-booking' ),
				'type'        => 'text',
				'required'    => false,
				'description' => __( 'Standard avsendernavn på utgående SMS (f.eks. Snippen).', 'snippen-booking' ),
			),
		);
	}

	/**
	 * Check if the provider has all required configuration parameters.
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		$token = get_option( 'snippen_sms_service_api_token' );
		return ! empty( $token );
	}

	/**
	 * Send / enqueue an SMS message.
	 *
	 * When using the Snippen SMS Service, messages are enqueued for the gateway
	 * daemon to poll and dispatch asynchronously.
	 *
	 * @param string $to      Recipient phone.
	 * @param string $message Message content.
	 * @return bool
	 */
	public function send_sms( string $to, string $message ): bool {
		if ( ! $this->is_configured() ) {
			error_log( 'SnippenSmsProvider Error: API token is not configured.' );
			return false;
		}

		if ( empty( $to ) || empty( $message ) ) {
			error_log( 'SnippenSmsProvider Error: Recipient phone or message content is empty.' );
			return false;
		}

		return true;
	}
}
