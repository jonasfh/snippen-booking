<?php
/**
 * KeySMS Service Implementation
 *
 * @package SnippenBooking\Service
 */

namespace SnippenBooking\Service;

/**
 * SMS service using KeySMS API
 */
class KeySmsService implements SmsServiceInterface {

	/**
	 * API Endpoint
	 *
	 * @var string
	 */
	private $endpoint = 'https://api.keysms.no/v1/messages';

	/**
	 * API Key
	 *
	 * @var string
	 */
	private $api_key;

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
		$this->api_key = get_option( 'snippen_keysms_api_key' );
		$this->sender  = get_option( 'snippen_sms_sender', 'Snippen' );
	}

	/**
	 * Send an SMS message
	 *
	 * @param string $to      The recipient phone number.
	 * @param string $message The message content.
	 * @return bool True on success, false on failure.
	 */
	public function send( string $to, string $message ): bool {
		if ( empty( $this->api_key ) ) {
			error_log( 'KeySMS Error: API Key is missing.' );
			return false;
		}

		if ( 'yes' !== get_option( 'snippen_sms_enabled' ) ) {
			return false;
		}

		$body = array(
			'message'   => $message,
			'receivers' => array( $to ),
		);

		if ( ! empty( $this->sender ) ) {
			$body['sender'] = $this->sender;
		}

		$args = array(
			'body'        => wp_json_encode( $body ),
			'headers'     => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Basic ' . base64_encode( ':' . $this->api_key ),
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

		return true;
	}
}
