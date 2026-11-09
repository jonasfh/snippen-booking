<?php
/**
 * AJAX API Endpoints for managing Yale Doorman Personal Access Tokens (PAT).
 *
 * @package SnippenBooking\Api
 */

namespace SnippenBooking\Api;

/**
 * Class DoormanApiKeyApi
 */
class DoormanApiKeyApi {

	/**
	 * Register AJAX hooks.
	 */
	public static function register() {
		add_action( 'wp_ajax_snippen_create_doorman_api_key', array( __CLASS__, 'ajax_create_key' ) );
		add_action( 'wp_ajax_snippen_delete_doorman_api_key', array( __CLASS__, 'ajax_delete_key' ) );
	}

	/**
	 * Hash a raw API token using HMAC-SHA256 and WordPress auth salt.
	 *
	 * @param string $token Raw plain-text token.
	 * @return string Hashed token.
	 */
	public static function hash_token( string $token ): string {
		$salt = function_exists( 'wp_salt' ) ? wp_salt( 'auth' ) : 'snippen_default_salt';
		return hash_hmac( 'sha256', $token, $salt );
	}

	/**
	 * Generate a masked representation of a token for secure display.
	 *
	 * @param string $token Plain-text token.
	 * @return string Masked token (e.g. sdo_8f2a••••••••••••••••b4e1).
	 */
	public static function mask_token( string $token ): string {
		$len = strlen( $token );
		if ( $len >= 12 ) {
			return substr( $token, 0, 8 ) . '••••••••••••••••' . substr( $token, -4 );
		}
		if ( $len >= 4 ) {
			return substr( $token, 0, 2 ) . '••••••••' . substr( $token, -2 );
		}
		return '••••••••';
	}

	/**
	 * Generate a secure random PAT token.
	 * Format: sdo_ (Snippen Doorman) followed by 32 hex characters.
	 *
	 * @return string Generated plain token.
	 */
	public static function generate_token(): string {
		return 'sdo_' . bin2hex( random_bytes( 16 ) );
	}

	/**
	 * Get all configured Doorman API keys with auto-migration of legacy plaintext tokens.
	 *
	 * @return array<array<string, mixed>> List of keys.
	 */
	public static function get_keys(): array {
		$keys = get_option( 'snippen_doorman_api_keys', array() );
		if ( ! is_array( $keys ) ) {
			return array();
		}

		$migrated = false;
		foreach ( $keys as $idx => $key_data ) {
			if ( ! is_array( $key_data ) ) {
				continue;
			}
			// Migrate legacy plaintext token to hash and masked token
			if ( ! empty( $key_data['token'] ) && empty( $key_data['token_hash'] ) ) {
				$raw_token                    = (string) $key_data['token'];
				$keys[ $idx ]['token_hash']   = self::hash_token( $raw_token );
				$keys[ $idx ]['masked_token'] = self::mask_token( $raw_token );
				unset( $keys[ $idx ]['token'] );
				$migrated = true;
			}
		}

		if ( $migrated ) {
			update_option( 'snippen_doorman_api_keys', $keys );
		}

		return $keys;
	}

	/**
	 * AJAX endpoint to create a new Doorman API key.
	 */
	public static function ajax_create_key() {
		check_ajax_referer( 'snippen_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_snippen_bookings' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Ingen tilgang.', 'snippen-booking' ) ), 403 );
		}

		$name = isset( $_POST['name'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['name'] ) ) ) : '';
		if ( '' === $name ) {
			$name = __( 'Doorman-tjeneste', 'snippen-booking' );
		}

		$plain_token  = self::generate_token();
		$token_hash   = self::hash_token( $plain_token );
		$masked_token = self::mask_token( $plain_token );
		$key_id       = 'key_' . wp_generate_password( 8, false );
		$created_at   = current_time( 'mysql' );

		$keys   = self::get_keys();
		$keys[] = array(
			'id'           => $key_id,
			'name'         => $name,
			'token_hash'   => $token_hash,
			'masked_token' => $masked_token,
			'created_at'   => $created_at,
		);

		update_option( 'snippen_doorman_api_keys', $keys );

		wp_send_json_success(
			array(
				'key'     => array(
					'id'           => $key_id,
					'name'         => $name,
					'masked_token' => $masked_token,
					'created_at'   => $created_at,
					'plain_token'  => $plain_token,
				),
				'message' => __( 'API-nøkkel opprettet!', 'snippen-booking' ),
			)
		);
	}

	/**
	 * AJAX endpoint to delete a Doorman API key.
	 */
	public static function ajax_delete_key() {
		check_ajax_referer( 'snippen_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_snippen_bookings' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Ingen tilgang.', 'snippen-booking' ) ), 403 );
		}

		$key_id = isset( $_POST['key_id'] ) ? sanitize_key( wp_unslash( $_POST['key_id'] ) ) : '';
		if ( '' === $key_id ) {
			wp_send_json_error( array( 'message' => __( 'Mangler nøkkel-ID.', 'snippen-booking' ) ), 400 );
		}

		$keys     = self::get_keys();
		$filtered = array();
		$found    = false;

		foreach ( $keys as $k ) {
			if ( is_array( $k ) && isset( $k['id'] ) && $k['id'] === $key_id ) {
				$found = true;
				continue;
			}
			$filtered[] = $k;
		}

		if ( ! $found ) {
			wp_send_json_error( array( 'message' => __( 'Nøkkel ikke funnet.', 'snippen-booking' ) ), 404 );
		}

		update_option( 'snippen_doorman_api_keys', array_values( $filtered ) );

		wp_send_json_success(
			array(
				'message' => __( 'API-nøkkel slettet.', 'snippen-booking' ),
			)
		);
	}
}
