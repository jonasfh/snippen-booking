<?php
/**
 * KeySMS Notification Provider
 *
 * @package SnippenBooking\Service\Notification
 */

namespace SnippenBooking\Service\Notification;

/**
 * Class KeySmsProvider
 */
class KeySmsProvider implements SmsProviderInterface {

	/**
	 * Get the unique provider identifier.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'keysms';
	}

	/**
	 * Get the human-readable provider name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'KeySMS', 'snippen-booking' );
	}

	/**
	 * Get the dynamic settings schema.
	 *
	 * @return array
	 */
	public function get_settings_schema(): array {
		return array(
			array(
				'key'         => 'snippen_keysms_username',
				'label'       => __( 'KeySMS Brukernavn', 'snippen-booking' ),
				'type'        => 'text',
				'required'    => true,
				'description' => '',
			),
			array(
				'key'         => 'snippen_keysms_api_key',
				'label'       => __( 'KeySMS API-nøkkel (Secret)', 'snippen-booking' ),
				'type'        => 'password',
				'required'    => true,
				'description' => __( 'Finn din API-nøkkel i kontrollpanelet hos keysms.no.', 'snippen-booking' ),
			),
			array(
				'key'         => 'snippen_sms_sender',
				'label'       => __( 'Avsender', 'snippen-booking' ),
				'type'        => 'text',
				'required'    => false,
				'description' => __( 'Maks 11 tegn. Dette vises som avsender på mottakerens telefon.', 'snippen-booking' ),
			),
		);
	}

	/**
	 * Check if the provider has all required configuration parameters.
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		$username = get_option( 'snippen_keysms_username' );
		$api_key  = get_option( 'snippen_keysms_api_key' );
		return ! empty( $username ) && ! empty( $api_key );
	}

	/**
	 * Send an SMS message.
	 *
	 * @param string $to      Recipient phone.
	 * @param string $message Message.
	 * @return bool
	 */
	public function send_sms( string $to, string $message ): bool {
		$service = new \SnippenBooking\Service\KeySmsService();
		return $service->send( $to, $message );
	}
}
