<?php
namespace SnippenBooking\Helper;

class PhoneHelper {
	/**
	 * Normalize a phone number to standard format (+47XXXXXXXX).
	 * Rejects non-Norwegian formats.
	 *
	 * @param string $phone
	 * @return string|false Normalized phone string or false if invalid.
	 */
	public static function normalize_phone( $phone ) {
		if ( empty( $phone ) || ! is_string( $phone ) ) {
			return false;
		}

		// Reject input if it contains @ or alphabetic characters (username/email)
		if ( preg_match( '/[@a-zA-Z]/', $phone ) ) {
			return false;
		}

		// Strip everything except digits
		$clean = preg_replace( '/[^0-9]/', '', $phone );

		// If it's exactly 8 digits, we assume Norwegian and prepend +47
		if ( strlen( $clean ) === 8 ) {
			return '+47' . $clean;
		}

		// If it's 10 digits and starts with 47, we prepend +
		if ( strlen( $clean ) === 10 && strpos( $clean, '47' ) === 0 ) {
			return '+' . $clean;
		}

		// If it's 12 digits and starts with 0047, we prepend +
		if ( strlen( $clean ) === 12 && strpos( $clean, '0047' ) === 0 ) {
			return '+' . substr( $clean, 2 );
		}

		// Anything else is invalid (e.g. foreign numbers or wrong length)
		return false;
	}

	/**
	 * Check if a normalized phone number is already used by another user.
	 *
	 * @param string $normalized_phone The phone number to check (should be normalized).
	 * @param int    $exclude_user_id Optional. Exclude this user ID from the check.
	 * @return bool True if unique, false if already in use.
	 */
	public static function is_phone_unique( $normalized_phone, $exclude_user_id = 0 ) {
		$args = array(
			'meta_key'   => 'snippen_phone',
			'meta_value' => $normalized_phone,
			'number'     => 1,
			'fields'     => 'ID',
		);

		if ( $exclude_user_id > 0 ) {
			$args['exclude'] = array( $exclude_user_id );
		}

		$users = get_users( $args );

		return empty( $users );
	}
}
