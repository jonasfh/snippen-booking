<?php
/**
 * Abstract Resident Import Provider
 *
 * @package SnippenBooking\Import\Provider
 */

namespace SnippenBooking\Import\Provider;

use SnippenBooking\Import\ResidentImportProviderInterface;
use SnippenBooking\Helper\PhoneHelper;

/**
 * Class AbstractResidentImportProvider
 */
abstract class AbstractResidentImportProvider implements ResidentImportProviderInterface {

	/**
	 * Perform Upsert on Resident
	 *
	 * @param string       $name
	 * @param string       $email
	 * @param string|false $normalized_phone
	 * @param string       $address
	 * @param string       $unit
	 * @return int|\WP_Error User ID on success or WP_Error on failure.
	 */
	protected function upsert_resident( $name, $email, $normalized_phone, $address = '', $unit = '' ) {
		$name_parts = explode( ' ', $name, 2 );
		$first_name = $name_parts[0];
		$last_name  = isset( $name_parts[1] ) ? $name_parts[1] : '';

		$user_id = email_exists( $email );

		if ( $user_id ) {
			// Update existing user
			$user = new \WP_User( $user_id );
			$user->set_role( 'holmen_resident' );

			wp_update_user(
				array(
					'ID'           => $user_id,
					'display_name' => $name,
					'first_name'   => $first_name,
					'last_name'    => $last_name,
				)
			);
		} else {
			// Create new user
			$username = $email;
			$password = wp_generate_password( 12, false );
			$user_id  = wp_create_user( $username, $password, $email );

			if ( is_wp_error( $user_id ) ) {
				return $user_id;
			}

			$user = new \WP_User( $user_id );
			$user->set_role( 'holmen_resident' );

			wp_update_user(
				array(
					'ID'           => $user_id,
					'display_name' => $name,
					'first_name'   => $first_name,
					'last_name'    => $last_name,
				)
			);
		}

		// Ensure we clear deletion status if they are present in the import
		delete_user_meta( $user_id, 'snippen_user_deleted' );

		// Save phone number using global helper holmen_save_phone_number
		if ( $normalized_phone ) {
			if ( function_exists( 'holmen_save_phone_number' ) ) {
				holmen_save_phone_number( $user_id, $normalized_phone );
			} else {
				update_user_meta( $user_id, 'snippen_phone', $normalized_phone );
			}
		}

		// Save address & unit if defined
		if ( ! empty( $address ) ) {
			update_user_meta( $user_id, 'snippen_address', $address );
		}
		if ( ! empty( $unit ) ) {
			update_user_meta( $user_id, 'snippen_unit', $unit );
		}

		return $user_id;
	}
}
