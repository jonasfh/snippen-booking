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
        </table>
        <?php
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
            update_user_meta( $user_id, 'snippen_phone', sanitize_text_field( $_POST['snippen_phone'] ) );
        }
    }
}
