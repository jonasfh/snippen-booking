<?php
/**
 * Centralized security helper utilities
 *
 * Provides reusable methods for nonce verification, input sanitization,
 * and safe request access following WordPress security best practices.
 *
 * @package SnippenBooking\Helper
 */

namespace SnippenBooking\Helper;

/**
 * Security Helper Class
 */
class Security {

	/**
	 * Verify an AJAX nonce and die with JSON error on failure.
	 *
	 * Wraps check_ajax_referer() with a consistent JSON error response
	 * for use in AJAX handlers.
	 *
	 * @param string $action    The nonce action name.
	 * @param string $query_arg The key to look for the nonce in $_REQUEST. Default 'nonce'.
	 * @param bool   $die       Whether to die on failure. Default true.
	 * @return bool True if the nonce is valid.
	 */
	public static function verify_ajax_nonce( $action, $query_arg = 'nonce', $die = true ) {
		$result = check_ajax_referer( $action, $query_arg, false );

		if ( false === $result ) {
			if ( $die ) {
				wp_send_json_error(
					array( 'message' => __( 'Security check failed. Please reload the page and try again.', 'snippen-booking' ) ),
					403
				);
			}
			return false;
		}

		return true;
	}

	/**
	 * Safely retrieve and sanitize a text value from POST data.
	 *
	 * @param string $key     The POST key to retrieve.
	 * @param string $default Default value if key is not set.
	 * @return string Sanitized text value.
	 */
	public static function get_post_text( $key, $default = '' ) {
		if ( ! isset( $_POST[ $key ] ) ) {
			return $default;
		}
		return sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
	}

	/**
	 * Safely retrieve and sanitize a text value from GET data.
	 *
	 * @param string $key     The GET key to retrieve.
	 * @param string $default Default value if key is not set.
	 * @return string Sanitized text value.
	 */
	public static function get_query_text( $key, $default = '' ) {
		if ( ! isset( $_GET[ $key ] ) ) {
			return $default;
		}
		return sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
	}

	/**
	 * Safely retrieve and sanitize an integer value from POST data.
	 *
	 * @param string $key     The POST key to retrieve.
	 * @param int    $default Default value if key is not set.
	 * @return int Sanitized integer value.
	 */
	public static function get_post_int( $key, $default = 0 ) {
		if ( ! isset( $_POST[ $key ] ) ) {
			return $default;
		}
		return intval( $_POST[ $key ] );
	}

	/**
	 * Safely retrieve and sanitize an integer value from GET data.
	 *
	 * @param string $key     The GET key to retrieve.
	 * @param int    $default Default value if key is not set.
	 * @return int Sanitized integer value.
	 */
	public static function get_query_int( $key, $default = 0 ) {
		if ( ! isset( $_GET[ $key ] ) ) {
			return $default;
		}
		return intval( $_GET[ $key ] );
	}

	/**
	 * Escape a string for use in a SQL LIKE clause.
	 *
	 * Wraps $wpdb->esc_like() for convenience.
	 *
	 * @param string $str The string to escape.
	 * @return string The escaped string, safe for use in LIKE clauses.
	 */
	public static function esc_like( $str ) {
		global $wpdb;
		return $wpdb->esc_like( $str );
	}
}
