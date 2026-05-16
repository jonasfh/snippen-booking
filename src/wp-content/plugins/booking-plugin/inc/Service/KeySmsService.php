<?php
/**
 * KeySMS Service Implementation
 *
 * @package SnippenBooking\Service
 */

namespace SnippenBooking\Service;

/**
 * SMS service using KeySMS API (Signed Payload method)
 */
class KeySmsService implements SmsServiceInterface {

	/**
	 * API Endpoint
	 *
	 * @var string
	 */
	private $endpoint = 'https://app.keysms.no/messages';

	/**
	 * API Key (Secret)
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Username
	 *
	 * @var string
	 */
	private $username;

	/**
	 * Sender Name
	 *
	 * @var string
	 */
	private $sender;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->username = get_option( 'snippen_keysms_username' );
		$this->api_key  = get_option( 'snippen_keysms_api_key' );
		$this->sender   = get_option( 'snippen_sms_sender', 'Snippen' );
	}

	/**
	 * Send an SMS message
	 *
	 * @param string $to      The recipient phone number.
	 * @param string $message The message content.
	 * @return bool True on success, false on failure.
	 */
	public function send( string $to, string $message ): bool {
		if ( empty( $this->api_key ) || empty( $this->username ) ) {
			error_log( 'KeySMS Error: API Key or Username is missing.' );
			return false;
		}

		if ( 'yes' !== get_option( 'snippen_sms_enabled' ) ) {
			return false;
		}

		$payload = array(
			'message'   => $message,
			'receivers' => array( $to ),
		);

		if ( ! empty( $this->sender ) ) {
			$payload['sender'] = $this->sender;
		}

		$payload_json = wp_json_encode( $payload );
		$signature    = md5( $payload_json . $this->api_key );

		$body = array(
			'payload'   => $payload_json,
			'username'  => $this->username,
			'signature' => $signature,
		);

		$args = array(
			'body'        => wp_json_encode( $body ),
			'headers'     => array(
				'Content-Type' => 'application/json',
			),
			'timeout'     => 15,
			'data_format' => 'body',
		);

		$response = wp_remote_post( $this->endpoint, $args );

		if ( is_wp_error( $response ) ) {
			error_log( 'KeySMS Error: ' . $response->get_error_message() );
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( $code < 200 || $code >= 300 ) {
			$error_body = wp_remote_retrieve_body( $response );
			error_log( "KeySMS Error: Received HTTP $code. Response: $error_body" );
			return false;
		}

		$response_data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! empty( $response_data ) && isset( $response_data['ok'] ) && ! $response_data['ok'] ) {
			error_log( 'KeySMS API Error: ' . wp_json_encode( $response_data ) );
			return false;
		}

		return true;
	}
}
