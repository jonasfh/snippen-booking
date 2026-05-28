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

		// Save non-provider general settings
		$door_code_hours_before = isset( $_POST['snippen_door_code_hours_before'] ) ? intval( $_POST['snippen_door_code_hours_before'] ) : 24;
		$door_code_hours_after  = isset( $_POST['snippen_door_code_hours_after'] ) ? intval( $_POST['snippen_door_code_hours_after'] ) : 2;
		update_option( 'snippen_door_code_hours_before', $door_code_hours_before );
		update_option( 'snippen_door_code_hours_after', $door_code_hours_after );

		// Save routing options
		$route_user = sanitize_text_field( $_POST['snippen_route_user_activation'] ?? 'email' );
		$route_book = sanitize_text_field( $_POST['snippen_route_booking_confirmation'] ?? 'email' );
		$route_adm  = sanitize_text_field( $_POST['snippen_route_admin_booking'] ?? 'email' );
		$route_pw   = sanitize_text_field( $_POST['snippen_route_password_reset'] ?? 'email' );

		update_option( 'snippen_route_user_activation', $route_user );
		update_option( 'snippen_route_booking_confirmation', $route_book );
		update_option( 'snippen_route_admin_booking', $route_adm );
		update_option( 'snippen_route_password_reset', $route_pw );

		// Save terms URL
		$terms_url = isset( $_POST['snippen_terms_url'] ) ? esc_url_raw( $_POST['snippen_terms_url'] ) : '';
		update_option( 'snippen_terms_url', $terms_url );

		// Save uninstall settings
		$preserve_data = isset( $_POST['snippen_preserve_data_on_uninstall'] ) ? 'yes' : 'no';
		update_option( 'snippen_preserve_data_on_uninstall', $preserve_data );

		// Sync legacy checkboxes for 100% backward compatibility
		update_option( 'snippen_sms_account_confirmation_enabled', ( 'sms' === $route_user ) ? 'yes' : 'no' );
		update_option( 'snippen_sms_booking_confirmation_enabled', ( 'sms' === $route_book ) ? 'yes' : 'no' );

		// Save sandbox mode
		$sandbox_mode = isset( $_POST['snippen_sms_sandbox_mode'] ) ? 'yes' : 'no';
		update_option( 'snippen_sms_sandbox_mode', $sandbox_mode );

		// Save active provider selection
		if ( isset( $_POST['snippen_active_notification_provider'] ) ) {
			update_option( 'snippen_active_notification_provider', sanitize_text_field( $_POST['snippen_active_notification_provider'] ) );
		}

		// Save dynamic provider settings
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
		$door_code_hours_before = get_option( 'snippen_door_code_hours_before', 24 );
		$door_code_hours_after  = get_option( 'snippen_door_code_hours_after', 2 );

		$manager            = new NotificationManager();
		$providers          = $manager->get_providers();
		$active_provider_id = $manager->get_active_provider_id();
		$sandbox_mode       = $manager->is_sandbox_mode();

		$route_user = $manager->get_channel_route( NotificationManager::TYPE_USER_ACTIVATION );
		$route_book = $manager->get_channel_route( NotificationManager::TYPE_BOOKING_CONFIRMATION );
		$route_adm  = $manager->get_channel_route( NotificationManager::TYPE_ADMIN_BOOKING );
		$route_pw   = $manager->get_channel_route( NotificationManager::TYPE_PASSWORD_RESET );

		echo '<div class="snippen-card"><form method="post" action="">';
		wp_nonce_field( 'snippen_save_settings', 'snippen_settings_nonce' );

		// 1. Varslingskanaler Settings Panel
		echo '<h3>' . esc_html__( 'Varslingskanaler', 'snippen-booking' ) . '</h3>';

		echo '<div class="snippen-form-group" style="background:#f8fafc; border:1px solid #e2e8f0; padding:16px; border-radius:8px; margin-bottom:24px;">';
		echo '<label style="font-weight:700; color:#0f172a; display: flex; align-items: center; gap:8px;">';
		echo '<input type="checkbox" name="snippen_sms_sandbox_mode" value="yes" ' . checked( $sandbox_mode, true, false ) . ' style="margin:0;">';
		echo esc_html__( 'SMS Sandbox / Utviklingsmodus (Ruter all SMS via E-post fallback)', 'snippen-booking' );
		echo '</label>';
		echo '<p class="description" style="margin: 4px 0 0 24px;">' . esc_html__( 'Aktiver dette under utvikling eller testing for å rute all SMS-utsending til e-post-fallback, slik at du sparer API-kostnader.', 'snippen-booking' ) . '</p>';
		echo '</div>';

		echo '<div class="snippen-form-group">';
		echo '<label for="snippen_route_user_activation">' . esc_html__( 'Kontoaktivering (Bruker)', 'snippen-booking' ) . '</label>';
		echo '<select name="snippen_route_user_activation" id="snippen_route_user_activation" style="max-width:300px;">';
		echo '<option value="email" ' . selected( $route_user, 'email', false ) . '>' . esc_html__( 'Kun E-post', 'snippen-booking' ) . '</option>';
		echo '<option value="sms" ' . selected( $route_user, 'sms', false ) . '>' . esc_html__( 'SMS (med e-post fallback)', 'snippen-booking' ) . '</option>';
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Hvordan engangskoder for kontoaktivering skal sendes til beboere.', 'snippen-booking' ) . '</p>';
		echo '</div>';

		echo '<div class="snippen-form-group">';
		echo '<label for="snippen_route_booking_confirmation">' . esc_html__( 'Bookingbekreftelse (Kunde)', 'snippen-booking' ) . '</label>';
		echo '<select name="snippen_route_booking_confirmation" id="snippen_route_booking_confirmation" style="max-width:300px;">';
		echo '<option value="email" ' . selected( $route_book, 'email', false ) . '>' . esc_html__( 'Kun E-post', 'snippen-booking' ) . '</option>';
		echo '<option value="sms" ' . selected( $route_book, 'sms', false ) . '>' . esc_html__( 'SMS (med e-post fallback)', 'snippen-booking' ) . '</option>';
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Mottakere av bookingforespørsler vil motta bekreftelse via valgt kanal.', 'snippen-booking' ) . '</p>';
		echo '</div>';

		echo '<div class="snippen-form-group">';
		echo '<label for="snippen_route_admin_booking">' . esc_html__( 'Varsel om ny booking (Admin)', 'snippen-booking' ) . '</label>';
		echo '<select name="snippen_route_admin_booking" id="snippen_route_admin_booking" style="max-width:300px;">';
		echo '<option value="email" ' . selected( $route_adm, 'email', false ) . '>' . esc_html__( 'Kun E-post', 'snippen-booking' ) . '</option>';
		echo '<option value="sms" ' . selected( $route_adm, 'sms', false ) . '>' . esc_html__( 'SMS (med e-post fallback)', 'snippen-booking' ) . '</option>';
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Hvordan bookingansvarlige varsles når en ny forespørsel sendes inn.', 'snippen-booking' ) . '</p>';
		echo '</div>';

		echo '<div class="snippen-form-group">';
		echo '<label for="snippen_route_password_reset">' . esc_html__( 'Tilbakestill passord (for SMS-brukere)', 'snippen-booking' ) . '</label>';
		echo '<select name="snippen_route_password_reset" id="snippen_route_password_reset" style="max-width:300px;">';
		echo '<option value="email" ' . selected( $route_pw, 'email', false ) . '>' . esc_html__( 'Kun E-post', 'snippen-booking' ) . '</option>';
		echo '<option value="sms" ' . selected( $route_pw, 'sms', false ) . '>' . esc_html__( 'SMS (med e-post fallback)', 'snippen-booking' ) . '</option>';
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Kanal for å sende tilbakestillingslenke når bruker ber om nytt passord via telefonnummer.', 'snippen-booking' ) . '</p>';
		echo '</div>';

		// 2. Active Notification Provider Selector Cards
		echo '<h3 style="margin-top:40px;">' . esc_html__( 'Varslingstilbyder (Transport)', 'snippen-booking' ) . '</h3>';
		echo '<p class="description" style="margin-bottom:16px;">' . esc_html__( 'Velg hvilken leverandør som skal utføre transporten når SMS-kanalen er i bruk.', 'snippen-booking' ) . '</p>';

		echo '<div class="snippen-provider-selector">';
		foreach ( $providers as $provider ) {
			$id         = $provider->get_id();
			$name       = $provider->get_name();
			$is_active  = ( $active_provider_id === $id );
			$configured = $provider->is_configured();
			$status_lbl = $configured ? esc_html__( 'Konfigurert', 'snippen-booking' ) : esc_html__( 'Mangler oppsett', 'snippen-booking' );
			$status_cls = $configured ? 'configured' : 'unconfigured';
			$active_cls = $is_active ? 'active' : '';

			echo '<div class="snippen-provider-option ' . esc_attr( $active_cls ) . '" id="provider-card-' . esc_attr( $id ) . '" onclick="selectProvider(\'' . esc_attr( $id ) . '\')">';
			echo '<input type="radio" name="snippen_active_notification_provider" value="' . esc_attr( $id ) . '" ' . checked( $is_active, true, false ) . ' style="pointer-events:none;">';
			echo '<div class="snippen-provider-name">' . esc_html( $name ) . '</div>';
			echo '<div class="snippen-provider-status ' . esc_attr( $status_cls ) . '">● ' . esc_html( $status_lbl ) . '</div>';
			echo '</div>';
		}
		echo '</div>';

		// 3. Dynamically Discovered Provider Setting Panels
		foreach ( $providers as $provider ) {
			$id        = $provider->get_id();
			$schema    = $provider->get_settings_schema();
			$is_active = ( $active_provider_id === $id );

			echo '<div class="provider-settings-section" id="provider-settings-' . esc_attr( $id ) . '" style="' . ( $is_active ? 'display:block;' : 'display:none;' ) . '">';
			echo '<h4 style="margin-top:0; margin-bottom:20px; font-size:16px; border-bottom:1px solid #cbd5e1; padding-bottom:8px; color:#0f172a;">';
			printf( esc_html__( 'Innstillinger for %s', 'snippen-booking' ), esc_html( $provider->get_name() ) );
			echo '</h4>';

			if ( empty( $schema ) ) {
				echo '<p style="color:#64748b; font-style:italic; margin:0;">' . esc_html__( 'Ingen innstillinger påkrevet for denne tilbyderen.', 'snippen-booking' ) . '</p>';
			} else {
				foreach ( $schema as $field ) {
					$key      = $field['key'];
					$label    = $field['label'];
					$type     = $field['type'];
					$required = $field['required'] ?? false;
					$desc     = $field['description'] ?? '';
					$value    = get_option( $key, '' );
					$req_mark = $required ? ' <span style="color:#dc2626;">*</span>' : '';

					echo '<div class="snippen-form-group">';

					if ( 'checkbox' === $type ) {
						echo '<label style="font-weight:normal; display:flex; align-items:center; gap:8px;">';
						echo '<input type="checkbox" name="' . esc_attr( $key ) . '" id="' . esc_attr( $key ) . '" value="yes" ' . checked( $value, 'yes', false ) . ' style="margin:0;">';
						echo esc_html( $label ) . $req_mark;
						echo '</label>';
					} else {
						echo '<label for="' . esc_attr( $key ) . '">' . esc_html( $label ) . $req_mark . '</label>';
						if ( 'password' === $type ) {
							echo '<input type="password" name="' . esc_attr( $key ) . '" id="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" class="regular-text">';
						} elseif ( 'select' === $type ) {
							echo '<select name="' . esc_attr( $key ) . '" id="' . esc_attr( $key ) . '">';
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
						echo '<p class="description">' . esc_html( $desc ) . '</p>';
					}
					echo '</div>';
				}
			}
			echo '</div>';
		}

		// 4. General Dørkode Settings Panel
		echo '<h3 style="margin-top:40px;">' . esc_html__( 'Dørkode Innstillinger', 'snippen-booking' ) . '</h3>';

		echo '<div class="snippen-form-group">';
		echo '<label for="snippen_door_code_hours_before">' . esc_html__( 'Vis dørkode x timer før booking start', 'snippen-booking' ) . '</label>';
		echo '<input type="number" name="snippen_door_code_hours_before" id="snippen_door_code_hours_before" value="' . esc_attr( $door_code_hours_before ) . '" class="small-text" min="0">';
		echo '</div>';

		echo '<div class="snippen-form-group">';
		echo '<label for="snippen_door_code_hours_after">' . esc_html__( 'Vis dørkode y timer etter booking slutt', 'snippen-booking' ) . '</label>';
		echo '<input type="number" name="snippen_door_code_hours_after" id="snippen_door_code_hours_after" value="' . esc_attr( $door_code_hours_after ) . '" class="small-text" min="0">';
		echo '</div>';

		// 4b. Booking og Vilkår
		$terms_url = get_option( 'snippen_terms_url', '' );
		echo '<h3 style="margin-top:40px;">' . esc_html__( 'Booking og Vilkår', 'snippen-booking' ) . '</h3>';
		echo '<div class="snippen-form-group">';
		echo '<label for="snippen_terms_url">' . esc_html__( 'Lenke til vilkår/regler for leie', 'snippen-booking' ) . '</label>';
		echo '<input type="url" name="snippen_terms_url" id="snippen_terms_url" value="' . esc_url( $terms_url ) . '" class="regular-text" placeholder="https://...">';
		echo '<p class="description">' . esc_html__( 'Hvis feltet er tomt, vises ikke akseptboksen i booking-skjemaet.', 'snippen-booking' ) . '</p>';
		echo '</div>';

		// 5. Avinstallering Settings Panel
		$preserve_data = get_option( 'snippen_preserve_data_on_uninstall', 'no' );
		echo '<h3 style="margin-top:40px;">' . esc_html__( 'Avinstallering', 'snippen-booking' ) . '</h3>';
		echo '<div class="snippen-form-group" style="background:#fff1f2; border:1px solid #fecdd3; padding:16px; border-radius:8px;">';
		echo '<label style="font-weight:700; color:#9f1239; display: flex; align-items: center; gap:8px;">';
		echo '<input type="checkbox" name="snippen_preserve_data_on_uninstall" value="yes" ' . checked( $preserve_data, 'yes', false ) . ' style="margin:0;">';
		echo esc_html__( 'Behold data ved avinstallering', 'snippen-booking' );
		echo '</label>';
		echo '<p class="description" style="margin: 4px 0 0 24px;">' . esc_html__( 'Hvis dette ikke er krysset av, vil all data (tabeller, innstillinger og brukermeta) knyttet til Snippen Booking bli slettet når pluginen slettes fra WordPress.', 'snippen-booking' ) . '</p>';
		echo '</div>';

		// Form actions
		echo '<div class="snippen-form-actions" style="margin-top:30px;">';
		echo '<button type="submit" class="snippen-btn snippen-btn-primary">' . esc_html__( 'Lagre innstillinger', 'snippen-booking' ) . '</button>';
		echo '</div>';

		echo '</form></div>';

		// 5. Custom JavaScript toggler enqueued inline
		echo '<script>
		function selectProvider(providerId) {
			document.querySelectorAll(".snippen-provider-option").forEach(function(card) {
				card.classList.remove("active");
				var radio = card.querySelector("input[type=\'radio\']");
				if (radio) {
					radio.checked = false;
				}
			});
			
			var selectedCard = document.getElementById("provider-card-" + providerId);
			if (selectedCard) {
				selectedCard.classList.add("active");
				var radio = selectedCard.querySelector("input[type=\'radio\']");
				if (radio) {
					radio.checked = true;
				}
			}
			
			document.querySelectorAll(".provider-settings-section").forEach(function(section) {
				section.style.display = "none";
			});
			var activeSection = document.getElementById("provider-settings-" + providerId);
			if (activeSection) {
				activeSection.style.display = "block";
			}
		}
		</script>';
	}
}
