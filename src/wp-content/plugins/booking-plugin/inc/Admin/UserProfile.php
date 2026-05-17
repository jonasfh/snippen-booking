<?php

namespace SnippenBooking\Admin;

/**
 * Handles custom user profile fields for administrators
 */
class UserProfile {

	/**
	 * Register hooks
	 */
	public static function register() {
		if ( ! is_admin() ) {
			return;
		}

		// Show fields on profile pages
		add_action( 'show_user_profile', array( __CLASS__, 'render_user_fields' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'render_user_fields' ) );

		// Save fields
		add_action( 'personal_options_update', array( __CLASS__, 'save_user_fields' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save_user_fields' ) );

		// Validate fields
		add_action( 'user_profile_update_errors', array( __CLASS__, 'validate_user_fields' ), 10, 3 );
	}

	/**
	 * Render custom fields on user profile page
	 *
	 * @param \WP_User $user
	 */
	public static function render_user_fields( $user ) {
		// Only allow admins to see/edit this field
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$phone = get_user_meta( $user->ID, 'snippen_phone', true );
		?>
		<hr />
		<h3><?php _e( 'Snippen Booking Informasjon', 'snippen-booking' ); ?></h3>
		<table class="form-table">
			<tr>
				<th><label for="snippen_phone"><?php _e( 'Telefonnummer', 'snippen-booking' ); ?></label></th>
				<td>
					<input type="text" name="snippen_phone" id="snippen_phone" value="<?php echo esc_attr( $phone ); ?>" class="regular-text" placeholder="+47XXXXXXXX" />
					<p class="description"><?php _e( 'Bruk E.164-format (f.eks. +4799887766). Dette nummeret brukes i bookingskjemaet og kan kun endres av administrator.', 'snippen-booking' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label><?php _e( 'Kontostatus', 'snippen-booking' ); ?></label></th>
				<td>
					<?php
					$confirmed = get_user_meta( $user->ID, 'snippen_account_confirmed', true ) === 'yes';
					if ( $confirmed ) :
						?>
						<span style="color: green; font-weight: bold;"><?php _e( 'Bekreftet', 'snippen-booking' ); ?></span>
					<?php else : ?>
						<span style="color: red; font-weight: bold;"><?php _e( 'Ikke bekreftet', 'snippen-booking' ); ?></span>
						<label style="margin-left: 20px;">
							<input type="checkbox" name="snippen_force_confirm" value="yes"> <?php _e( 'Marker som bekreftet manuelt', 'snippen-booking' ); ?>
						</label>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><label for="snippen_user_deleted"><?php _e( 'Slettet beboer', 'snippen-booking' ); ?></label></th>
				<td>
					<label>
						<input type="checkbox" name="snippen_user_deleted" id="snippen_user_deleted" value="yes" <?php checked( get_user_meta( $user->ID, 'snippen_user_deleted', true ), 'yes' ); ?> />
						<?php _e( 'Marker som slettet (kan ikke logge inn eller booke)', 'snippen-booking' ); ?>
					</label>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Validate custom fields
	 *
	 * @param \WP_Error $errors
	 * @param bool      $update
	 * @param \WP_User  $user
	 */
	public static function validate_user_fields( $errors, $update, $user ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_POST['snippen_phone'] ) ) {
			$raw_phone = sanitize_text_field( $_POST['snippen_phone'] );
			if ( ! empty( $raw_phone ) ) {
				$normalized_phone = \SnippenBooking\Helper\PhoneHelper::normalize_phone( $raw_phone );

				if ( ! $normalized_phone ) {
					$errors->add( 'invalid_phone', __( 'Ugyldig telefonnummer. Må være et gyldig norsk nummer.', 'snippen-booking' ) );
				} elseif ( ! \SnippenBooking\Helper\PhoneHelper::is_phone_unique( $normalized_phone, $user->ID ) ) {
					$errors->add( 'duplicate_phone', __( 'Dette telefonnummeret er allerede i bruk av en annen bruker.', 'snippen-booking' ) );
				}
			}
		}
	}

	/**
	 * Save custom fields
	 *
	 * @param int $user_id
	 */
	public static function save_user_fields( $user_id ) {
		// Only allow admins to save this field
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_POST['snippen_phone'] ) ) {
			$raw_phone = sanitize_text_field( $_POST['snippen_phone'] );
			if ( empty( $raw_phone ) ) {
				update_user_meta( $user_id, 'snippen_phone', '' );
			} else {
				$normalized_phone = \SnippenBooking\Helper\PhoneHelper::normalize_phone( $raw_phone );
				if ( $normalized_phone ) {
					update_user_meta( $user_id, 'snippen_phone', $normalized_phone );
				}
			}
		}

		if ( isset( $_POST['snippen_force_confirm'] ) && $_POST['snippen_force_confirm'] === 'yes' ) {
			update_user_meta( $user_id, 'snippen_account_confirmed', 'yes' );
		}

		if ( isset( $_POST['snippen_user_deleted'] ) && $_POST['snippen_user_deleted'] === 'yes' ) {
			update_user_meta( $user_id, 'snippen_user_deleted', 'yes' );
		} else {
			delete_user_meta( $user_id, 'snippen_user_deleted' );
		}
	}
}
