<?php
/**
 * Vipps MobilePay ePayment v1 API Client
 *
 * @package SnippenBooking\Service\Vipps
 */

namespace SnippenBooking\Service\Vipps;

/**
 * VippsClient handles direct communication with the Vipps MobilePay ePayment API v1.
 */
class VippsClient {

	const ENV_TEST = 'test';
	const ENV_PROD = 'prod';

	const URL_TEST = 'https://apitest.vipps.no';
	const URL_PROD = 'https://api.vipps.no';

	/**
	 * Client ID
	 *
	 * @var string
	 */
	private $client_id;

	/**
	 * Client Secret
	 *
	 * @var string
	 */
	private $client_secret;

	/**
	 * Subscription Key (Ocp-Apim-Subscription-Key)
	 *
	 * @var string
	 */
	private $subscription_key;

	/**
	 * Merchant Serial Number (MSN)
	 *
	 * @var string
	 */
	private $msn;

	/**
	 * Environment (test or prod)
	 *
	 * @var string
	 */
	private $environment;

	/**
	 * Constructor
	 *
	 * @param array $config Optional configuration parameters.
	 */
	public function __construct( array $config = array() ) {
		$this->client_id        = $config['client_id'] ?? $this->get_config_value( 'snippen_vipps_client_id', 'SNIPPEN_VIPPS_CLIENT_ID', 'VIPPS_CLIENT_ID' );
		$this->client_secret    = $config['client_secret'] ?? $this->get_config_value( 'snippen_vipps_client_secret', 'SNIPPEN_VIPPS_CLIENT_SECRET', 'VIPPS_CLIENT_SECRET' );
		$this->subscription_key = $config['subscription_key'] ?? $this->get_config_value( 'snippen_vipps_subscription_key', 'SNIPPEN_VIPPS_SUBSCRIPTION_KEY', 'VIPPS_SUBSCRIPTION_KEY' );
		$this->msn              = $config['msn'] ?? $this->get_config_value( 'snippen_vipps_msn', 'SNIPPEN_VIPPS_MSN', 'VIPPS_MSN' );
		$this->environment      = $config['environment'] ?? $this->get_config_value( 'snippen_vipps_environment', 'SNIPPEN_VIPPS_ENVIRONMENT', 'VIPPS_ENVIRONMENT', self::ENV_TEST );

		if ( empty( $this->environment ) ) {
			$this->environment = self::ENV_TEST;
		}
	}

	/**
	 * Helper to get config value with constant / env priority, falling back to WP options.
	 *
	 * @param string $option_key   WP Option key.
	 * @param string $constant_key Constant / environment variable key.
	 * @param string $alt_key      Alternative constant key.
	 * @param string $default      Default fallback value.
	 * @return string
	 */
	private function get_config_value( $option_key, $constant_key = '', $alt_key = '', $default = '' ) {
		if ( ! empty( $constant_key ) && defined( $constant_key ) ) {
			return (string) constant( $constant_key );
		}
		if ( ! empty( $constant_key ) && getenv( $constant_key ) !== false && getenv( $constant_key ) !== '' ) {
			return (string) getenv( $constant_key );
		}
		if ( ! empty( $alt_key ) && defined( $alt_key ) ) {
			return (string) constant( $alt_key );
		}
		if ( ! empty( $alt_key ) && getenv( $alt_key ) !== false && getenv( $alt_key ) !== '' ) {
			return (string) getenv( $alt_key );
		}
		return (string) get_option( $option_key, $default );
	}

	/**
	 * Get base API URL based on environment.
	 *
	 * @return string
	 */
	public function get_base_url() {
		return self::ENV_PROD === $this->environment ? self::URL_PROD : self::URL_TEST;
	}

	/**
	 * Check if required API credentials are configured.
	 *
	 * @return bool
	 */
	public function is_configured() {
		return ! empty( $this->client_id ) &&
			! empty( $this->client_secret ) &&
			! empty( $this->subscription_key ) &&
			! empty( $this->msn );
	}

	/**
	 * Get current environment.
	 *
	 * @return string
	 */
	public function get_environment() {
		return $this->environment;
	}

	/**
	 * Fetch or retrieve cached OAuth 2.0 access token.
	 *
	 * @param bool $force_refresh Force a new token request.
	 * @return string|null Access token or null on failure.
	 */
	public function get_access_token( $force_refresh = false ) {
		if ( ! $this->is_configured() ) {
			error_log( 'VippsClient: Missing required API credentials.' );
			return null;
		}

		$transient_key = 'snippen_vp_token_' . md5( $this->environment . $this->client_id . $this->subscription_key . $this->msn );

		if ( ! $force_refresh ) {
			$cached = get_transient( $transient_key );
			if ( ! empty( $cached ) && is_string( $cached ) ) {
				return $cached;
			}
		}

		$endpoint = $this->get_base_url() . '/accesstoken/get';
		$headers  = array(
			'client_id'                 => $this->client_id,
			'client_secret'             => $this->client_secret,
			'Ocp-Apim-Subscription-Key' => $this->subscription_key,
			'Merchant-Serial-Number'    => $this->msn,
			'Content-Length'            => '0',
		);

		$response = wp_remote_post(
			$endpoint,
			array(
				'headers'     => $headers,
				'httpversion' => '1.1',
				'timeout'     => 15,
				'body'        => '',
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( 'VippsClient Error: Token request failed (WP_Error): ' . $response->get_error_message() );
			return null;
		}

		$status_code   = (int) wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( $status_code < 200 || $status_code >= 300 ) {
			error_log( sprintf( 'VippsClient Error: Token request returned HTTP %d: %s', $status_code, $response_body ) );
			return null;
		}

		$data = json_decode( $response_body, true );
		if ( empty( $data ) || empty( $data['access_token'] ) ) {
			error_log( 'VippsClient Error: Malformed token response: ' . $response_body );
			return null;
		}

		$token      = (string) $data['access_token'];
		$expires_in = isset( $data['expires_in'] ) ? (int) $data['expires_in'] : 3600;

		// Set transient cache with 60 second safety buffer
		$ttl = max( 60, $expires_in - 60 );
		set_transient( $transient_key, $token, $ttl );

		return $token;
	}

	/**
	 * Test connection using provided or saved credentials.
	 *
	 * @param string|null $client_id        Client ID.
	 * @param string|null $client_secret    Client Secret.
	 * @param string|null $subscription_key Subscription Key.
	 * @param string|null $msn              Merchant Serial Number.
	 * @param string|null $environment      Environment.
	 * @return array
	 */
	public static function test_connection( $client_id = null, $client_secret = null, $subscription_key = null, $msn = null, $environment = null ) {
		$config = array();
		if ( null !== $client_id ) {
			$config['client_id'] = $client_id;
		}
		if ( null !== $client_secret ) {
			$config['client_secret'] = $client_secret;
		}
		if ( null !== $subscription_key ) {
			$config['subscription_key'] = $subscription_key;
		}
		if ( null !== $msn ) {
			$config['msn'] = $msn;
		}
		if ( null !== $environment ) {
			$config['environment'] = $environment;
		}

		$client = new self( $config );

		if ( ! $client->is_configured() ) {
			return array(
				'success' => false,
				'message' => __( 'Vennligst fyll ut alle feltene (Client ID, Client Secret, Subscription Key og MSN).', 'snippen-booking' ),
			);
		}

		$endpoint = $client->get_base_url() . '/accesstoken/get';
		$headers  = array(
			'client_id'                 => $client->client_id,
			'client_secret'             => $client->client_secret,
			'Ocp-Apim-Subscription-Key' => $client->subscription_key,
			'Merchant-Serial-Number'    => $client->msn,
			'Content-Length'            => '0',
		);

		$response = wp_remote_post(
			$endpoint,
			array(
				'headers'     => $headers,
				'httpversion' => '1.1',
				'timeout'     => 15,
				'body'        => '',
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: Error message */
					__( 'Tilkobling feilet: %s', 'snippen-booking' ),
					$response->get_error_message()
				),
			);
		}

		$status_code   = (int) wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( $status_code >= 200 && $status_code < 300 ) {
			$data = json_decode( $response_body, true );
			if ( ! empty( $data['access_token'] ) ) {
				$env_label = self::ENV_PROD === $client->get_environment() ? __( 'Produksjon', 'snippen-booking' ) : __( 'Test / Sandbox (MT)', 'snippen-booking' );
				return array(
					'success' => true,
					'message' => sprintf(
						/* translators: %s: Environment label */
						__( 'Tilkobling vellykket mot Vipps MobilePay (%s)! Autentiseringstoken ble bekreftet.', 'snippen-booking' ),
						$env_label
					),
				);
			}
		}

		// Try to extract readable error message from Vipps
		$data    = json_decode( $response_body, true );
		$err_msg = '';
		if ( ! empty( $data['message'] ) ) {
			$err_msg = $data['message'];
		} elseif ( ! empty( $data['error_description'] ) ) {
			$err_msg = $data['error_description'];
		} elseif ( ! empty( $data['extraInfo'] ) ) {
			$err_msg = $data['extraInfo'];
		} else {
			$err_msg = $response_body ?: sprintf( 'HTTP %d', $status_code );
		}

		return array(
			'success' => false,
			'message' => sprintf(
				/* translators: 1: HTTP status code, 2: Error detail */
				__( 'Vipps avviste forespørselen (HTTP %1$d): %2$s', 'snippen-booking' ),
				$status_code,
				$err_msg
			),
		);
	}

	/**
	 * Create a payment in Vipps ePayment v1.
	 *
	 * @param array $payment_data Payment payload data.
	 * @return array|\WP_Error
	 */
	public function create_payment( array $payment_data ) {
		$token = $this->get_access_token();
		if ( empty( $token ) ) {
			return new \WP_Error( 'vipps_auth_failed', __( 'Kunne ikke hente Vipps access token.', 'snippen-booking' ) );
		}

		$idempotency_key = ! empty( $payment_data['idempotencyKey'] ) ? $payment_data['idempotencyKey'] : wp_generate_uuid4();
		unset( $payment_data['idempotencyKey'] );

		$endpoint = $this->get_base_url() . '/epayment/v1/payments';
		$headers  = array(
			'Authorization'             => 'Bearer ' . $token,
			'Ocp-Apim-Subscription-Key' => $this->subscription_key,
			'Merchant-Serial-Number'    => $this->msn,
			'Idempotency-Key'           => $idempotency_key,
			'Content-Type'              => 'application/json',
		);

		$response = wp_remote_post(
			$endpoint,
			array(
				'headers'     => $headers,
				'httpversion' => '1.1',
				'body'        => wp_json_encode( $payment_data ),
				'timeout'     => 20,
				'data_format' => 'body',
			)
		);

		return $this->handle_response( $response, 'create_payment' );
	}

	/**
	 * Get payment status and details from Vipps ePayment v1.
	 *
	 * @param string $reference Payment reference.
	 * @return array|\WP_Error
	 */
	public function get_payment( $reference ) {
		$token = $this->get_access_token();
		if ( empty( $token ) ) {
			return new \WP_Error( 'vipps_auth_failed', __( 'Kunne ikke hente Vipps access token.', 'snippen-booking' ) );
		}

		$endpoint = $this->get_base_url() . '/epayment/v1/payments/' . rawurlencode( $reference );
		$headers  = array(
			'Authorization'             => 'Bearer ' . $token,
			'Ocp-Apim-Subscription-Key' => $this->subscription_key,
			'Merchant-Serial-Number'    => $this->msn,
		);

		$response = wp_remote_get(
			$endpoint,
			array(
				'headers'     => $headers,
				'httpversion' => '1.1',
				'timeout'     => 15,
			)
		);

		return $this->handle_response( $response, 'get_payment' );
	}

	/**
	 * Capture payment in Vipps ePayment v1.
	 *
	 * @param string $reference  Payment reference.
	 * @param int    $amount_ore Amount in øre (NOK).
	 * @return array|\WP_Error
	 */
	public function capture_payment( $reference, $amount_ore ) {
		$token = $this->get_access_token();
		if ( empty( $token ) ) {
			return new \WP_Error( 'vipps_auth_failed', __( 'Kunne ikke hente Vipps access token.', 'snippen-booking' ) );
		}

		$endpoint = $this->get_base_url() . '/epayment/v1/payments/' . rawurlencode( $reference ) . '/capture';
		$headers  = array(
			'Authorization'             => 'Bearer ' . $token,
			'Ocp-Apim-Subscription-Key' => $this->subscription_key,
			'Merchant-Serial-Number'    => $this->msn,
			'Idempotency-Key'           => wp_generate_uuid4(),
			'Content-Type'              => 'application/json',
		);

		$payload = array(
			'modificationAmount' => array(
				'value'    => (int) $amount_ore,
				'currency' => 'NOK',
			),
		);

		$response = wp_remote_post(
			$endpoint,
			array(
				'headers'     => $headers,
				'httpversion' => '1.1',
				'body'        => wp_json_encode( $payload ),
				'timeout'     => 20,
				'data_format' => 'body',
			)
		);

		return $this->handle_response( $response, 'capture_payment' );
	}

	/**
	 * Cancel payment in Vipps ePayment v1.
	 *
	 * @param string $reference Payment reference.
	 * @return array|\WP_Error
	 */
	public function cancel_payment( $reference ) {
		$token = $this->get_access_token();
		if ( empty( $token ) ) {
			return new \WP_Error( 'vipps_auth_failed', __( 'Kunne ikke hente Vipps access token.', 'snippen-booking' ) );
		}

		$endpoint = $this->get_base_url() . '/epayment/v1/payments/' . rawurlencode( $reference ) . '/cancel';
		$headers  = array(
			'Authorization'             => 'Bearer ' . $token,
			'Ocp-Apim-Subscription-Key' => $this->subscription_key,
			'Merchant-Serial-Number'    => $this->msn,
			'Idempotency-Key'           => wp_generate_uuid4(),
			'Content-Type'              => 'application/json',
		);

		$response = wp_remote_post(
			$endpoint,
			array(
				'headers'     => $headers,
				'httpversion' => '1.1',
				'body'        => wp_json_encode( new \stdClass() ),
				'timeout'     => 20,
				'data_format' => 'body',
			)
		);

		return $this->handle_response( $response, 'cancel_payment' );
	}

	/**
	 * Helper to process HTTP responses and wrap errors in \WP_Error.
	 *
	 * @param array|\WP_Error $response HTTP response.
	 * @param string          $action   Action context.
	 * @return array|\WP_Error
	 */
	private function handle_response( $response, $action ) {
		if ( is_wp_error( $response ) ) {
			error_log( "VippsClient Error ($action): " . $response->get_error_message() );
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( $code < 200 || $code >= 300 ) {
			error_log( "VippsClient Error ($action): Received HTTP $code. Response: $body" );
			$err_data = json_decode( $body, true );
			$message  = ! empty( $err_data['message'] ) ? $err_data['message'] : ( ! empty( $err_data['extraInfo'] ) ? $err_data['extraInfo'] : "HTTP $code" );
			return new \WP_Error(
				'vipps_api_error',
				$message,
				array(
					'status' => $code,
					'body'   => $body,
				)
			);
		}

		$data = json_decode( $body, true );
		return is_array( $data ) ? $data : array( 'raw' => $body );
	}
}
