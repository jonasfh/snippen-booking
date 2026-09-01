<?php
/**
 * Admin page for plugin settings
 *
 * @package SnippenBooking\Admin\Pages
 */

namespace SnippenBooking\Admin\Pages;

use SnippenBooking\Service\Notification\NotificationManager;
use SnippenBooking\Service\Notification\SmsProviderInterface;

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

		// Save General Settings
		$enable_door_code = isset( $_POST['snippen_enable_door_code'] ) ? 'yes' : 'no';
		update_option( 'snippen_enable_door_code', $enable_door_code );

		$door_code_hours_before = isset( $_POST['snippen_door_code_hours_before'] ) ? intval( $_POST['snippen_door_code_hours_before'] ) : 24;
		$door_code_hours_after  = isset( $_POST['snippen_door_code_hours_after'] ) ? intval( $_POST['snippen_door_code_hours_after'] ) : 2;
		update_option( 'snippen_door_code_hours_before', $door_code_hours_before );
		update_option( 'snippen_door_code_hours_after', $door_code_hours_after );

		// Run sync/clear door codes immediately when settings are saved
		\SnippenBooking\Service\DoorCodeService::update_approaching_bookings_door_codes();

		$dispatch_method = sanitize_text_field( $_POST['snippen_notification_dispatch_method'] ?? 'async' );
		update_option( 'snippen_notification_dispatch_method', $dispatch_method );

		$terms_url = isset( $_POST['snippen_terms_url'] ) ? esc_url_raw( $_POST['snippen_terms_url'] ) : '';
		update_option( 'snippen_terms_url', $terms_url );

		$horizon_weeks = isset( $_POST['snippen_booking_horizon_weeks'] ) ? intval( $_POST['snippen_booking_horizon_weeks'] ) : 52;
		update_option( 'snippen_booking_horizon_weeks', $horizon_weeks );

		$preserve_data = isset( $_POST['snippen_preserve_data_on_uninstall'] ) ? 'yes' : 'no';
		update_option( 'snippen_preserve_data_on_uninstall', $preserve_data );

		$disable_all_emails = isset( $_POST['snippen_disable_all_emails'] ) ? 'yes' : 'no';
		update_option( 'snippen_disable_all_emails', $disable_all_emails );

		$user_cancellation_days = isset( $_POST['snippen_user_cancellation_days'] ) ? intval( $_POST['snippen_user_cancellation_days'] ) : 14;
		update_option( 'snippen_user_cancellation_days', $user_cancellation_days );

		// Save Payment Settings
		$payment_bank_account = isset( $_POST['snippen_payment_bank_account'] ) ? sanitize_text_field( wp_unslash( $_POST['snippen_payment_bank_account'] ) ) : '';
		update_option( 'snippen_payment_bank_account', $payment_bank_account );

		$payment_vipps_number = isset( $_POST['snippen_payment_vipps_number'] ) ? sanitize_text_field( wp_unslash( $_POST['snippen_payment_vipps_number'] ) ) : '';
		update_option( 'snippen_payment_vipps_number', $payment_vipps_number );

		$payment_instructions = isset( $_POST['snippen_payment_instructions'] ) ? sanitize_textarea_field( wp_unslash( $_POST['snippen_payment_instructions'] ) ) : '';
		update_option( 'snippen_payment_instructions', $payment_instructions );

		$payment_admin_emails = isset( $_POST['snippen_payment_admin_emails'] ) ? sanitize_text_field( wp_unslash( $_POST['snippen_payment_admin_emails'] ) ) : '';
		update_option( 'snippen_payment_admin_emails', $payment_admin_emails );

		$payment_notify_admin = isset( $_POST['snippen_payment_notify_admin'] ) ? 'yes' : 'no';
		update_option( 'snippen_payment_notify_admin', $payment_notify_admin );

		// Save Active Toggles per channel and type
		update_option( 'snippen_email_booking_confirmation_enabled', isset( $_POST['snippen_email_booking_confirmation_enabled'] ) ? 'yes' : 'no' );
		update_option( 'snippen_email_admin_booking_enabled', isset( $_POST['snippen_email_admin_booking_enabled'] ) ? 'yes' : 'no' );
		update_option( 'snippen_email_user_activation_enabled', isset( $_POST['snippen_email_user_activation_enabled'] ) ? 'yes' : 'no' );
		update_option( 'snippen_email_password_reset_enabled', isset( $_POST['snippen_email_password_reset_enabled'] ) ? 'yes' : 'no' );
		update_option( 'snippen_email_payment_reminder_enabled', isset( $_POST['snippen_email_payment_reminder_enabled'] ) ? 'yes' : 'no' );
		$email_receipt_uploaded = isset( $_POST['snippen_email_payment_receipt_uploaded_enabled'] ) ? 'yes' : 'no';
		update_option( 'snippen_email_payment_receipt_uploaded_enabled', $email_receipt_uploaded );
		update_option( 'snippen_payment_notify_admin', $email_receipt_uploaded );

		update_option( 'snippen_sms_booking_confirmation_enabled', isset( $_POST['snippen_sms_booking_confirmation_enabled'] ) ? 'yes' : 'no' );
		update_option( 'snippen_sms_admin_booking_enabled', isset( $_POST['snippen_sms_admin_booking_enabled'] ) ? 'yes' : 'no' );
		update_option( 'snippen_sms_user_activation_enabled', isset( $_POST['snippen_sms_user_activation_enabled'] ) ? 'yes' : 'no' );
		update_option( 'snippen_sms_password_reset_enabled', isset( $_POST['snippen_sms_password_reset_enabled'] ) ? 'yes' : 'no' );
		update_option( 'snippen_sms_payment_reminder_enabled', isset( $_POST['snippen_sms_payment_reminder_enabled'] ) ? 'yes' : 'no' );
		update_option( 'snippen_sms_payment_receipt_uploaded_enabled', isset( $_POST['snippen_sms_payment_receipt_uploaded_enabled'] ) ? 'yes' : 'no' );

		$reminder_days = isset( $_POST['snippen_payment_reminder_days'] ) ? sanitize_text_field( wp_unslash( $_POST['snippen_payment_reminder_days'] ) ) : '30, 21';
		update_option( 'snippen_payment_reminder_days', $reminder_days );

		if ( isset( $_POST['snippen_active_notification_provider'] ) ) {
			update_option( 'snippen_active_notification_provider', sanitize_text_field( wp_unslash( $_POST['snippen_active_notification_provider'] ) ) );
		}

		// Save provider settings dynamically
		$manager = new NotificationManager();
		foreach ( $manager->get_providers() as $provider ) {
			foreach ( $provider->get_settings_schema() as $field ) {
				$key = $field['key'];
				if ( $field['type'] === 'checkbox' ) {
					$value = isset( $_POST[ $key ] ) ? 'yes' : 'no';
					update_option( $key, $value );
				} elseif ( isset( $_POST[ $key ] ) ) {
					if ( $field['type'] === 'email' ) {
						$value = sanitize_email( wp_unslash( $_POST[ $key ] ) );
					} elseif ( $field['type'] === 'number' ) {
						$value = intval( $_POST[ $key ] );
					} else {
						$value = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
					}
					update_option( $key, $value );
				}
			}
		}

		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Innstillinger lagret.', 'snippen-booking' ) . '</p></div>';
	}

	/**
	 * Render the form
	 */
	private function render_form() {
		$enable_door_code       = get_option( 'snippen_enable_door_code', 'no' );
		$door_code_hours_before = get_option( 'snippen_door_code_hours_before', 24 );
		$door_code_hours_after  = get_option( 'snippen_door_code_hours_after', 2 );
		$dispatch_method        = get_option( 'snippen_notification_dispatch_method', 'async' );
		$terms_url              = get_option( 'snippen_terms_url', '' );
		$preserve_data          = get_option( 'snippen_preserve_data_on_uninstall', 'no' );
		$horizon_weeks          = get_option( 'snippen_booking_horizon_weeks', 52 );
		$disable_all_emails     = get_option( 'snippen_disable_all_emails', 'no' );

		$payment_bank_account = get_option( 'snippen_payment_bank_account', '' );
		$payment_vipps_number = get_option( 'snippen_payment_vipps_number', '' );
		$payment_instructions = get_option( 'snippen_payment_instructions', '' );
		$payment_admin_emails = get_option( 'snippen_payment_admin_emails', '' );

		$email_booking          = get_option( 'snippen_email_booking_confirmation_enabled', 'yes' );
		$email_admin            = get_option( 'snippen_email_admin_booking_enabled', 'yes' );
		$email_activation       = get_option( 'snippen_email_user_activation_enabled', 'yes' );
		$email_password         = get_option( 'snippen_email_password_reset_enabled', 'yes' );
		$email_reminder         = get_option( 'snippen_email_payment_reminder_enabled', 'yes' );
		$email_receipt_uploaded = get_option( 'snippen_email_payment_receipt_uploaded_enabled', 'yes' );

		$sms_booking          = get_option( 'snippen_sms_booking_confirmation_enabled', 'no' );
		$sms_admin            = get_option( 'snippen_sms_admin_booking_enabled', 'no' );
		$sms_activation       = get_option( 'snippen_sms_user_activation_enabled', 'no' );
		$sms_password         = get_option( 'snippen_sms_password_reset_enabled', 'no' );
		$sms_reminder         = get_option( 'snippen_sms_payment_reminder_enabled', 'no' );
		$sms_receipt_uploaded = get_option( 'snippen_sms_payment_receipt_uploaded_enabled', 'no' );

		$payment_reminder_days = get_option( 'snippen_payment_reminder_days', '30, 21' );
		$active_provider       = get_option( 'snippen_active_notification_provider', 'keysms' );

		$manager              = new NotificationManager();
		$email_provider       = $manager->get_provider( 'email' );
		$keysms_provider      = $manager->get_provider( 'keysms' );
		$snippen_sms_provider = $manager->get_provider( 'snippen_sms_service' );

		echo '<div class="snippen-card" style="padding:0; background:none; border:none; box-shadow:none;"><form method="post" action="">';
		wp_nonce_field( 'snippen_save_settings', 'snippen_settings_nonce' );

		// Tabs navigation
		echo '<h2 class="nav-tab-wrapper" style="margin-bottom:20px; border-bottom:1px solid #ccd0d4; padding-left:0;">';
		echo '<a href="#" class="nav-tab nav-tab-active" data-tab="email">' . esc_html__( 'E-post', 'snippen-booking' ) . '</a>';
		echo '<a href="#" class="nav-tab" data-tab="keysms">' . esc_html__( 'KeySMS (SMS)', 'snippen-booking' ) . '</a>';
		echo '<a href="#" class="nav-tab" data-tab="snippen_sms">' . esc_html__( 'Snippen SMS (Gateway)', 'snippen-booking' ) . '</a>';
		echo '<a href="#" class="nav-tab" data-tab="payment">' . esc_html__( 'Betaling', 'snippen-booking' ) . '</a>';
		echo '<a href="#" class="nav-tab" data-tab="general">' . esc_html__( 'Generelt', 'snippen-booking' ) . '</a>';
		echo '</h2>';

		// 1. E-post tab content
		echo '<div class="tab-content" id="tab-email" style="display:block; background:#fff; padding:24px; border:1px solid #ccd0d4; border-radius:4px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">';
		echo '<h3 style="margin-top:0;">' . esc_html__( 'E-post-varsler', 'snippen-booking' ) . '</h3>';
		echo '<div class="snippen-form-group" style="background:#f8fafc; border:1px solid #e2e8f0; padding:16px; border-radius:8px; margin-bottom:24px;">';
		echo '<h4 style="margin:0 0 12px 0;">' . esc_html__( 'Aktiver varslingstyper for E-post:', 'snippen-booking' ) . '</h4>';
		echo '<label style="font-weight:600; display: flex; align-items: center; gap:8px; margin-bottom:8px;">';
		echo '<input type="checkbox" name="snippen_email_booking_confirmation_enabled" value="yes" ' . checked( $email_booking, 'yes', false ) . ' style="margin:0;">';
		echo esc_html__( 'Send bookingbekreftelse til kunde på e-post', 'snippen-booking' );
		echo '</label>';
		echo '<label style="font-weight:600; display: flex; align-items: center; gap:8px; margin-bottom:8px;">';
		echo '<input type="checkbox" name="snippen_email_admin_booking_enabled" value="yes" ' . checked( $email_admin, 'yes', false ) . ' style="margin:0;">';
		echo esc_html__( 'Send varsel om ny booking til administratorer på e-post', 'snippen-booking' );
		echo '</label>';
		echo '<label style="font-weight:600; display: flex; align-items: center; gap:8px; margin-bottom:8px;">';
		echo '<input type="checkbox" name="snippen_email_user_activation_enabled" value="yes" ' . checked( $email_activation, 'yes', false ) . ' style="margin:0;">';
		echo esc_html__( 'Send kontoregistreringskode på e-post', 'snippen-booking' );
		echo '</label>';
		echo '<label style="font-weight:600; display: flex; align-items: center; gap:8px; margin-bottom:8px;">';
		echo '<input type="checkbox" name="snippen_email_password_reset_enabled" value="yes" ' . checked( $email_password, 'yes', false ) . ' style="margin:0;">';
		echo esc_html__( 'Send tilbakestilling av passord på e-post', 'snippen-booking' );
		echo '</label>';
		echo '<label style="font-weight:600; display: flex; align-items: center; gap:8px; margin-bottom:8px;">';
		echo '<input type="checkbox" name="snippen_email_payment_reminder_enabled" value="yes" ' . checked( $email_reminder, 'yes', false ) . ' style="margin:0;">';
		echo esc_html__( 'Send automatisk betalingspurring til kunde på e-post', 'snippen-booking' );
		echo '</label>';
		echo '<label style="font-weight:600; display: flex; align-items: center; gap:8px; margin-bottom:0;">';
		echo '<input type="checkbox" name="snippen_email_payment_receipt_uploaded_enabled" value="yes" ' . checked( $email_receipt_uploaded, 'yes', false ) . ' style="margin:0;">';
		echo esc_html__( 'Send varsel om opplastet betalingskvittering til administratorer på e-post', 'snippen-booking' );
		echo '</label>';
		echo '</div>';

		if ( $email_provider ) {
			foreach ( $email_provider->get_settings_schema() as $field ) {
				$this->render_field( $field );
			}
		}
		echo '</div>';

		// 2. KeySMS tab content
		echo '<div class="tab-content" id="tab-keysms" style="display:none; background:#fff; padding:24px; border:1px solid #ccd0d4; border-radius:4px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">';
		echo '<h3 style="margin-top:0;">' . esc_html__( 'KeySMS (SMS) Varsler', 'snippen-booking' ) . '</h3>';

		echo '<div class="snippen-form-group" style="margin-bottom:20px; padding:12px 16px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px;">';
		echo '<label for="snippen_active_notification_provider_keysms" style="display:block; font-weight:700; margin-bottom:6px;">' . esc_html__( 'Aktiv SMS-tilbyder i systemet:', 'snippen-booking' ) . '</label>';
		echo '<select name="snippen_active_notification_provider" id="snippen_active_notification_provider_keysms" style="min-width:300px;">';
		echo '<option value="keysms" ' . selected( $active_provider, 'keysms', false ) . '>' . esc_html__( 'KeySMS (Direkte API)', 'snippen-booking' ) . '</option>';
		echo '<option value="snippen_sms_service" ' . selected( $active_provider, 'snippen_sms_service', false ) . '>' . esc_html__( 'Snippen SMS Service (Gateway)', 'snippen-booking' ) . '</option>';
		echo '</select>';
		echo '<p class="description" style="margin-top:4px;">' . esc_html__( 'Velg hvilken SMS-tjeneste som skal benyttes ved utsendelse av SMS fra systemet.', 'snippen-booking' ) . '</p>';
		echo '</div>';

		echo '<div class="snippen-form-group" style="background:#f8fafc; border:1px solid #e2e8f0; padding:16px; border-radius:8px; margin-bottom:24px;">';
		echo '<h4 style="margin:0 0 12px 0;">' . esc_html__( 'Aktiver varslingstyper for SMS:', 'snippen-booking' ) . '</h4>';
		echo '<label style="font-weight:600; display: flex; align-items: center; gap:8px; margin-bottom:8px;">';
		echo '<input type="checkbox" name="snippen_sms_booking_confirmation_enabled" value="yes" ' . checked( $sms_booking, 'yes', false ) . ' style="margin:0;">';
		echo esc_html__( 'Send bookingbekreftelse til kunde på SMS', 'snippen-booking' );
		echo '</label>';
		echo '<label style="font-weight:600; display: flex; align-items: center; gap:8px; margin-bottom:8px;">';
		echo '<input type="checkbox" name="snippen_sms_admin_booking_enabled" value="yes" ' . checked( $sms_admin, 'yes', false ) . ' style="margin:0;">';
		echo esc_html__( 'Send varsel om ny booking til administratorer på SMS', 'snippen-booking' );
		echo '</label>';
		echo '<label style="font-weight:600; display: flex; align-items: center; gap:8px; margin-bottom:8px;">';
		echo '<input type="checkbox" name="snippen_sms_user_activation_enabled" value="yes" ' . checked( $sms_activation, 'yes', false ) . ' style="margin:0;">';
		echo esc_html__( 'Send kontoregistreringskode på SMS', 'snippen-booking' );
		echo '</label>';
		echo '<label style="font-weight:600; display: flex; align-items: center; gap:8px; margin-bottom:8px;">';
		echo '<input type="checkbox" name="snippen_sms_password_reset_enabled" value="yes" ' . checked( $sms_password, 'yes', false ) . ' style="margin:0;">';
		echo esc_html__( 'Send tilbakestilling av passord på SMS', 'snippen-booking' );
		echo '</label>';
		echo '<label style="font-weight:600; display: flex; align-items: center; gap:8px; margin-bottom:8px;">';
		echo '<input type="checkbox" name="snippen_sms_payment_reminder_enabled" value="yes" ' . checked( $sms_reminder, 'yes', false ) . ' style="margin:0;">';
		echo esc_html__( 'Send automatisk betalingspurring til kunde på SMS', 'snippen-booking' );
		echo '</label>';
		echo '<label style="font-weight:600; display: flex; align-items: center; gap:8px; margin-bottom:0;">';
		echo '<input type="checkbox" name="snippen_sms_payment_receipt_uploaded_enabled" value="yes" ' . checked( $sms_receipt_uploaded, 'yes', false ) . ' style="margin:0;">';
		echo esc_html__( 'Send varsel om opplastet betalingskvittering til administratorer på SMS', 'snippen-booking' );
		echo '</label>';
		echo '</div>';

		if ( $keysms_provider ) {
			foreach ( $keysms_provider->get_settings_schema() as $field ) {
				$this->render_field( $field );
			}
		}
		echo '</div>';

		// 3. Snippen SMS Gateway tab content
		echo '<div class="tab-content" id="tab-snippen_sms" style="display:none; background:#fff; padding:24px; border:1px solid #ccd0d4; border-radius:4px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">';
		echo '<h3 style="margin-top:0;">' . esc_html__( 'Snippen SMS Service (Gateway)', 'snippen-booking' ) . '</h3>';

		echo '<div class="snippen-form-group" style="margin-bottom:20px; padding:12px 16px; background:#eff6ff; border:1px solid #bfdbfe; border-radius:6px;">';
		echo '<p style="margin:0 0 6px 0; font-size:13px;"><strong>' . esc_html__( 'REST API Sync Endepunkt:', 'snippen-booking' ) . '</strong> <code style="background:#fff; padding:2px 6px; border:1px solid #cbd5e1; border-radius:3px;">' . esc_url( rest_url( 'snippen/v1/sms' ) ) . '</code></p>';
		echo '<p class="description" style="margin:0;">' . esc_html__( 'Konfigurer SMS Gateway med SNIPPEN_SMS_API_URL satt til denne adressen og SNIPPEN_SMS_API_TOKEN satt til tokenet under.', 'snippen-booking' ) . '</p>';
		echo '</div>';

		if ( $snippen_sms_provider ) {
			foreach ( $snippen_sms_provider->get_settings_schema() as $field ) {
				$this->render_field( $field );
			}
		}
		echo '</div>';

		// 4. Betaling tab content
		echo '<div class="tab-content" id="tab-payment" style="display:none; background:#fff; padding:24px; border:1px solid #ccd0d4; border-radius:4px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">';
		echo '<h3 style="margin-top:0;">' . esc_html__( 'Betalingsinnstillinger', 'snippen-booking' ) . '</h3>';

		echo '<div class="snippen-form-group" style="margin-bottom:20px;">';
		echo '<label for="snippen_payment_bank_account" style="display:block; font-weight:600; margin-bottom:5px;">' . esc_html__( 'Bankkontonummer for betaling', 'snippen-booking' ) . '</label>';
		echo '<input type="text" name="snippen_payment_bank_account" id="snippen_payment_bank_account" value="' . esc_attr( $payment_bank_account ) . '" class="regular-text" placeholder="1234.56.78901">';
		echo '<p class="description">' . esc_html__( 'Kontonummer som vises til kundene ved manuell betaling.', 'snippen-booking' ) . '</p>';
		echo '</div>';

		echo '<div class="snippen-form-group" style="margin-bottom:20px;">';
		echo '<label for="snippen_payment_vipps_number" style="display:block; font-weight:600; margin-bottom:5px;">' . esc_html__( 'Vipps-nummer / Instruksjon', 'snippen-booking' ) . '</label>';
		echo '<input type="text" name="snippen_payment_vipps_number" id="snippen_payment_vipps_number" value="' . esc_attr( $payment_vipps_number ) . '" class="regular-text" placeholder="F.eks. #12345 (Snippen Samfunnshus)">';
		echo '<p class="description">' . esc_html__( 'Vipps-nummer eller Vipps-instruksjon for kundene.', 'snippen-booking' ) . '</p>';
		echo '</div>';

		$payment_instructions_default = __( 'Vennligst overfør leiebeløpet innen 3 dager fra booking. Merk betalingen med ditt navn eller booking-ID.', 'snippen-booking' );
		$payment_instructions         = get_option( 'snippen_payment_instructions', $payment_instructions_default );

		echo '<div class="snippen-form-group" style="margin-bottom:20px;">';
		echo '<label for="snippen_payment_instructions" style="display:block; font-weight:600; margin-bottom:5px;">' . esc_html__( 'Betalingsinstruksjoner / Betalingsfrist', 'snippen-booking' ) . '</label>';
		echo '<textarea name="snippen_payment_instructions" id="snippen_payment_instructions" class="large-text" rows="4" placeholder="' . esc_attr( $payment_instructions_default ) . '">' . esc_textarea( $payment_instructions ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'Instruksjonstekst og betalingsfrist. Denne teksten vises ved bookingvisning for leietaker og er også tilgjengelig som plassholder ({{payment_instructions}}) i Bookingbekreftelse varslingsmalen.', 'snippen-booking' ) . '</p>';
		echo '</div>';

		echo '<div class="snippen-form-group" style="background:#f8fafc; border:1px solid #e2e8f0; padding:16px; border-radius:8px; margin-top:24px;">';
		echo '<h4 style="margin:0 0 12px 0;">' . esc_html__( 'Konfigurasjon for automatiske betalingspurringer:', 'snippen-booking' ) . '</h4>';
		echo '<div style="margin-bottom:12px;">';
		echo '<label for="snippen_payment_reminder_days" style="display:block; font-weight:600; margin-bottom:5px;">' . esc_html__( 'Purreintervaller (dager før booking start)', 'snippen-booking' ) . '</label>';
		echo '<input type="text" name="snippen_payment_reminder_days" id="snippen_payment_reminder_days" value="' . esc_attr( $payment_reminder_days ) . '" class="regular-text" placeholder="30, 21">';
		echo '<p class="description">' . esc_html__( 'Kommaseparert liste over antall dager før bookingdato hvor automatisk betalingspurring skal sendes (f.eks. "30, 21").', 'snippen-booking' ) . '</p>';
		echo '</div>';
		echo '</div>';
		echo '</div>';

		// 4. Generelt tab content
		echo '<div class="tab-content" id="tab-general" style="display:none; background:#fff; padding:24px; border:1px solid #ccd0d4; border-radius:4px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">';
		echo '<h3 style="margin-top:0;">' . esc_html__( 'Generelle innstillinger', 'snippen-booking' ) . '</h3>';

		echo '<div class="snippen-form-group" style="margin-bottom: 20px;">';
		echo '<label for="snippen_notification_dispatch_method" style="display:block; font-weight:600; margin-bottom:5px;">' . esc_html__( 'Utsendelsesmetode', 'snippen-booking' ) . '</label>';
		echo '<select name="snippen_notification_dispatch_method" id="snippen_notification_dispatch_method" style="min-width:300px;">';
		echo '<option value="async" ' . selected( $dispatch_method, 'async', false ) . '>' . esc_html__( 'Asynkron (via WP-Cron - anbefalt)', 'snippen-booking' ) . '</option>';
		echo '<option value="sync" ' . selected( $dispatch_method, 'sync', false ) . '>' . esc_html__( 'Synkron (Send direkte ved booking)', 'snippen-booking' ) . '</option>';
		echo '</select>';
		echo '<p class="description" style="margin-top:4px;">' . esc_html__( 'Velg Synkron hvis WP-Cron er sperret eller ustabil på webhotellet.', 'snippen-booking' ) . '</p>';
		echo '</div>';

		echo '<div class="snippen-form-group" style="margin-bottom: 20px;">';
		echo '<label style="font-weight:700; display:flex; align-items:center; gap:8px;">';
		echo '<input type="checkbox" name="snippen_disable_all_emails" id="snippen_disable_all_emails" value="yes" ' . checked( $disable_all_emails, 'yes', false ) . ' style="margin:0;">';
		echo esc_html__( 'Deaktiver all e-postutsendelse fullstendig', 'snippen-booking' );
		echo '</label>';
		echo '<p class="description" style="margin:4px 0 0 24px;">' . esc_html__( 'Kryss av her hvis serveren mangler e-postoppsett for å unngå at systemet henger ved passordtilbakestilling og andre e-poster (løser #166).', 'snippen-booking' ) . '</p>';
		echo '</div>';

		echo '<div class="snippen-form-group" style="margin-bottom: 20px;">';
		echo '<label style="font-weight:700; display:flex; align-items:center; gap:8px;">';
		echo '<input type="checkbox" name="snippen_enable_door_code" id="snippen_enable_door_code" value="yes" ' . checked( $enable_door_code, 'yes', false ) . ' style="margin:0;">';
		echo esc_html__( 'Aktiver dørkode-system', 'snippen-booking' );
		echo '</label>';
		echo '<p class="description" style="margin:4px 0 0 24px;">' . esc_html__( 'Aktiver integrasjon med dørkoder for bookingobjekter og bookinger.', 'snippen-booking' ) . '</p>';
		echo '</div>';

		echo '<div id="snippen-door-code-hours-settings" style="' . ( 'yes' === $enable_door_code ? '' : 'display:none;' ) . '">';
		echo '<div class="snippen-form-group" style="margin-bottom: 20px;">';
		echo '<label for="snippen_door_code_hours_before" style="display:block; font-weight:600; margin-bottom:5px;">' . esc_html__( 'Vis dørkode x timer før booking start', 'snippen-booking' ) . '</label>';
		echo '<input type="number" name="snippen_door_code_hours_before" id="snippen_door_code_hours_before" value="' . esc_attr( $door_code_hours_before ) . '" class="small-text" min="0">';
		echo '</div>';

		echo '<div class="snippen-form-group" style="margin-bottom: 20px;">';
		echo '<label for="snippen_door_code_hours_after" style="display:block; font-weight:600; margin-bottom:5px;">' . esc_html__( 'Vis dørkode y timer etter booking slutt', 'snippen-booking' ) . '</label>';
		echo '<input type="number" name="snippen_door_code_hours_after" id="snippen_door_code_hours_after" value="' . esc_attr( $door_code_hours_after ) . '" class="small-text" min="0">';
		echo '</div>';
		echo '</div>';

		echo '<div class="snippen-form-group" style="margin-bottom: 20px;">';
		echo '<label for="snippen_terms_url" style="display:block; font-weight:600; margin-bottom:5px;">' . esc_html__( 'Lenke til vilkår/regler for leie', 'snippen-booking' ) . '</label>';
		echo '<input type="url" name="snippen_terms_url" id="snippen_terms_url" value="' . esc_url( $terms_url ) . '" class="regular-text" placeholder="https://...">';
		echo '<p class="description" style="margin-top:4px;">' . esc_html__( 'Hvis feltet er tomt, vises ikke akseptboksen i booking-skjemaet.', 'snippen-booking' ) . '</p>';
		echo '</div>';

		echo '<div class="snippen-form-group" style="margin-bottom: 20px;">';
		echo '<label for="snippen_booking_horizon_weeks" style="display:block; font-weight:600; margin-bottom:5px;">' . esc_html__( 'Hvor mange uker frem i tid skal kalenderen vise?', 'snippen-booking' ) . '</label>';
		echo '<input type="number" name="snippen_booking_horizon_weeks" id="snippen_booking_horizon_weeks" value="' . esc_attr( $horizon_weeks ) . '" class="small-text" min="1" max="520">';
		echo '<p class="description" style="margin-top:4px;">' . esc_html__( 'Standard er 52 uker (1 år). Kalender-nedtrekksmenyen vil vise dette antallet uker frem i tid.', 'snippen-booking' ) . '</p>';
		echo '</div>';

		$user_cancellation_days = get_option( 'snippen_user_cancellation_days', 14 );
		echo '<div class="snippen-form-group" style="margin-bottom: 20px;">';
		echo '<label for="snippen_user_cancellation_days" style="display:block; font-weight:600; margin-bottom:5px;">' . esc_html__( 'Frist for avbestilling (dager før booking start)', 'snippen-booking' ) . '</label>';
		echo '<input type="number" name="snippen_user_cancellation_days" id="snippen_user_cancellation_days" value="' . esc_attr( $user_cancellation_days ) . '" class="small-text" min="0">';
		echo '<p class="description" style="margin-top:4px;">' . esc_html__( 'Antall dager før booking start at bruker kan slette egne ubekreftede og ubetalte bookinger. Standard er 14 dager.', 'snippen-booking' ) . '</p>';
		echo '</div>';

		echo '<div class="snippen-form-group" style="background:#fff1f2; border:1px solid #fecdd3; padding:16px; border-radius:8px; margin-top:20px;">';
		echo '<label style="font-weight:700; color:#9f1239; display: flex; align-items: center; gap:8px;">';
		echo '<input type="checkbox" name="snippen_preserve_data_on_uninstall" value="yes" ' . checked( $preserve_data, 'yes', false ) . ' style="margin:0;">';
		echo esc_html__( 'Behold data ved avinstallering', 'snippen-booking' );
		echo '</label>';
		echo '<p class="description" style="margin: 4px 0 0 24px;">' . esc_html__( 'Hvis dette ikke er krysset av, vil all data knyttet til Snippen Booking bli slettet når pluginen slettes.', 'snippen-booking' ) . '</p>';
		echo '</div>';

		echo '</div>';

		// Form actions
		echo '<div class="snippen-form-actions" style="margin-top:20px;">';
		echo '<button type="submit" class="button button-primary">' . esc_html__( 'Lagre innstillinger', 'snippen-booking' ) . '</button>';
		echo '</div>';

		echo '</form></div>';

		// Tab switcher & toggle script
		echo '<script>
		jQuery(document).ready(function($) {
			$(".nav-tab-wrapper a").on("click", function(e) {
				e.preventDefault();
				$(".nav-tab-wrapper a").removeClass("nav-tab-active");
				$(this).addClass("nav-tab-active");
				$(".tab-content").hide();
				$("#tab-" + $(this).data("tab")).show();
			});
			$("#snippen_enable_door_code").on("change", function() {
				if ($(this).is(":checked")) {
					$("#snippen-door-code-hours-settings").show();
				} else {
					$("#snippen-door-code-hours-settings").hide();
				}
			});
		});
		</script>';
	}

	/**
	 * Helper to render settings field
	 */
	private function render_field( $field ) {
		$key      = $field['key'];
		$label    = $field['label'];
		$type     = $field['type'];
		$required = $field['required'] ?? false;
		$desc     = $field['description'] ?? '';
		$value    = get_option( $key, '' );
		$req_mark = $required ? ' <span style="color:#dc2626;">*</span>' : '';

		echo '<div class="snippen-form-group" style="margin-bottom: 20px;">';

		if ( 'checkbox' === $type ) {
			echo '<label style="font-weight:normal; display:flex; align-items:center; gap:8px;">';
			echo '<input type="checkbox" name="' . esc_attr( $key ) . '" id="' . esc_attr( $key ) . '" value="yes" ' . checked( $value, 'yes', false ) . ' style="margin:0;">';
			echo esc_html( $label ) . $req_mark;
			echo '</label>';
		} else {
			echo '<label for="' . esc_attr( $key ) . '" style="display:block; font-weight:600; margin-bottom:5px;">' . esc_html( $label ) . $req_mark . '</label>';
			if ( 'password' === $type ) {
				echo '<input type="password" name="' . esc_attr( $key ) . '" id="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" class="regular-text" autocomplete="new-password">';
			} elseif ( 'select' === $type ) {
				echo '<select name="' . esc_attr( $key ) . '" id="' . esc_attr( $key ) . '" style="min-width:300px;">';
				foreach ( $field['options'] as $opt_val => $opt_lbl ) {
					echo '<option value="' . esc_attr( $opt_val ) . '" ' . selected( $value, $opt_val, false ) . '>' . esc_html( $opt_lbl ) . '</option>';
				}
				echo '</select>';
			} elseif ( 'number' === $type ) {
				echo '<input type="number" name="' . esc_attr( $key ) . '" id="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" class="small-text">';
			} elseif ( 'email' === $type ) {
				echo '<input type="email" name="' . esc_attr( $key ) . '" id="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" class="regular-text">';
			} else {
				echo '<input type="text" name="' . esc_attr( $key ) . '" id="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" class="regular-text">';
			}
		}

		if ( ! empty( $desc ) ) {
			echo '<p class="description" style="margin-top:4px;">' . esc_html( $desc ) . '</p>';
		}
		echo '</div>';
	}
}
