<?php
/**
 * Admin page for plugin settings
 *
 * @package SnippenBooking\Admin\Pages
 */

namespace SnippenBooking\Admin\Pages;

/**
 * Settings Page
 */
class SettingsPage {

	/**
	 * Render the page
	 */
	public function render() {
		$this->handle_request();

		echo '<div class="snippen-booking-admin-wrap">';
		$this->render_header();
		$this->render_form();
		echo '</div>';
	}

	/**
	 * Render header
	 */
	private function render_header() {
		echo '<div class="snippen-admin-header">';
		echo '<h1>' . esc_html__( 'Innstillinger', 'snippen-booking' ) . '</h1>';
		echo '</div>';
	}

	/**
	 * Handle POST requests
	 */
	private function handle_request() {
		if ( ! isset( $_POST['snippen_settings_nonce'] ) || ! wp_verify_nonce( $_POST['snippen_settings_nonce'], 'snippen_save_settings' ) ) {
			return;
		}

		$sms_booking_confirmation_enabled = isset( $_POST['snippen_sms_booking_confirmation_enabled'] ) ? 'yes' : 'no';
		$sms_account_confirmation_enabled = isset( $_POST['snippen_sms_account_confirmation_enabled'] ) ? 'yes' : 'no';
		$username                         = sanitize_text_field( $_POST['snippen_keysms_username'] ?? '' );
		$api_key                          = sanitize_text_field( $_POST['snippen_keysms_api_key'] ?? '' );
		$sender                           = sanitize_text_field( $_POST['snippen_sms_sender'] ?? '' );
		$door_code_hours_before           = isset( $_POST['snippen_door_code_hours_before'] ) ? intval( $_POST['snippen_door_code_hours_before'] ) : 24;
		$door_code_hours_after            = isset( $_POST['snippen_door_code_hours_after'] ) ? intval( $_POST['snippen_door_code_hours_after'] ) : 2;

		update_option( 'snippen_sms_booking_confirmation_enabled', $sms_booking_confirmation_enabled );
		update_option( 'snippen_sms_account_confirmation_enabled', $sms_account_confirmation_enabled );
		update_option( 'snippen_keysms_username', $username );
		update_option( 'snippen_keysms_api_key', $api_key );
		update_option( 'snippen_sms_sender', $sender );
		update_option( 'snippen_door_code_hours_before', $door_code_hours_before );
		update_option( 'snippen_door_code_hours_after', $door_code_hours_after );

		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Innstillinger lagret.', 'snippen-booking' ) . '</p></div>';
	}

	/**
	 * Render the form
	 */
	private function render_form() {
		$sms_booking_confirmation_enabled = get_option( 'snippen_sms_booking_confirmation_enabled', 'no' );
		$sms_account_confirmation_enabled = get_option( 'snippen_sms_account_confirmation_enabled', 'no' );
		$username                         = get_option( 'snippen_keysms_username', '' );
		$api_key                          = get_option( 'snippen_keysms_api_key', '' );
		$sender                           = get_option( 'snippen_sms_sender', 'Snippen' );
		$door_code_hours_before           = get_option( 'snippen_door_code_hours_before', 24 );
		$door_code_hours_after            = get_option( 'snippen_door_code_hours_after', 2 );

		echo '<div class="snippen-card"><form method="post" action="">';
		wp_nonce_field( 'snippen_save_settings', 'snippen_settings_nonce' );

		echo '<h3>' . esc_html__( 'SMS Innstillinger (KeySMS)', 'snippen-booking' ) . '</h3>';

		echo '<div class="snippen-form-group">';
		echo '<label style="font-weight:normal; display: block; margin-bottom: 5px;"><input type="checkbox" name="snippen_sms_booking_confirmation_enabled" value="yes" ' . checked( $sms_booking_confirmation_enabled, 'yes', false ) . '> ' . esc_html__( 'Aktiver SMS-varsling ved booking', 'snippen-booking' ) . '</label>';
		echo '<label style="font-weight:normal; display: block;"><input type="checkbox" name="snippen_sms_account_confirmation_enabled" value="yes" ' . checked( $sms_account_confirmation_enabled, 'yes', false ) . '> ' . esc_html__( 'Aktiver SMS-varsling for kontobekreftelse', 'snippen-booking' ) . '</label>';
		echo '</div>';

		echo '<div class="snippen-form-group">';
		echo '<label for="snippen_keysms_username">' . esc_html__( 'KeySMS Brukernavn', 'snippen-booking' ) . '</label>';
		echo '<input type="text" name="snippen_keysms_username" id="snippen_keysms_username" value="' . esc_attr( $username ) . '" class="regular-text">';
		echo '</div>';

		echo '<div class="snippen-form-group">';
		echo '<label for="snippen_keysms_api_key">' . esc_html__( 'KeySMS API-nøkkel (Secret)', 'snippen-booking' ) . '</label>';
		echo '<input type="password" name="snippen_keysms_api_key" id="snippen_keysms_api_key" value="' . esc_attr( $api_key ) . '" class="regular-text">';
		echo '<p class="description">' . esc_html__( 'Finn din API-nøkkel i kontrollpanelet hos keysms.no.', 'snippen-booking' ) . '</p>';
		echo '</div>';

		echo '<div class="snippen-form-group">';
		echo '<label for="snippen_sms_sender">' . esc_html__( 'Avsender', 'snippen-booking' ) . '</label>';
		echo '<input type="text" name="snippen_sms_sender" id="snippen_sms_sender" value="' . esc_attr( $sender ) . '" class="regular-text" maxlength="11">';
		echo '<p class="description">' . esc_html__( 'Maks 11 tegn. Dette vises som avsender på mottakerens telefon.', 'snippen-booking' ) . '</p>';
		echo '</div>';

		echo '<h3 style="margin-top:40px;">' . esc_html__( 'Dørkode Innstillinger', 'snippen-booking' ) . '</h3>';

		echo '<div class="snippen-form-group">';
		echo '<label for="snippen_door_code_hours_before">' . esc_html__( 'Vis dørkode x timer før booking start', 'snippen-booking' ) . '</label>';
		echo '<input type="number" name="snippen_door_code_hours_before" id="snippen_door_code_hours_before" value="' . esc_attr( $door_code_hours_before ) . '" class="small-text" min="0">';
		echo '</div>';

		echo '<div class="snippen-form-group">';
		echo '<label for="snippen_door_code_hours_after">' . esc_html__( 'Vis dørkode y timer etter booking slutt', 'snippen-booking' ) . '</label>';
		echo '<input type="number" name="snippen_door_code_hours_after" id="snippen_door_code_hours_after" value="' . esc_attr( $door_code_hours_after ) . '" class="small-text" min="0">';
		echo '</div>';

		echo '<div class="snippen-form-actions" style="margin-top:30px;">';
		echo '<button type="submit" class="snippen-btn snippen-btn-primary">' . esc_html__( 'Lagre innstillinger', 'snippen-booking' ) . '</button>';
		echo '</div>';

		echo '</form></div>';
	}
}
