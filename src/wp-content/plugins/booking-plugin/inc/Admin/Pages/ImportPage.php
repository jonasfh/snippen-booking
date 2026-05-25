<?php
/**
 * Admin page for importing residents
 *
 * @package SnippenBooking\Admin\Pages
 */

namespace SnippenBooking\Admin\Pages;

use SnippenBooking\Helper\Capabilities;
use SnippenBooking\Import\ImportManager;

/**
 * Import Page
 */
class ImportPage {

	/**
	 * @var ImportManager
	 */
	private $import_manager;

	public function __construct() {
		$this->import_manager = new ImportManager();
	}

	/**
	 * Render the page
	 */
	public function render() {
		if ( ! Capabilities::can_manage_bookings() ) {
			wp_die( esc_html__( 'Ingen tilgang.', 'snippen-booking' ) );
		}

		$results = $this->handle_request();

		echo '<div class="snippen-booking-admin-wrap">';
		$this->render_header();

		if ( $results ) {
			$this->render_results( $results );
		}

		$this->render_form();
		echo '</div>';
	}

	/**
	 * Render header
	 */
	private function render_header() {
		echo '<div class="snippen-admin-header">';
		echo '<h1>' . esc_html__( 'Beboer Import', 'snippen-booking' ) . '</h1>';
		echo '</div>';
	}

	/**
	 * Render results summary
	 *
	 * @param array $results
	 */
	private function render_results( $results ) {
		echo '<div class="snippen-card" style="border-left: 4px solid var(--primary-color);">';
		echo '<h3>' . esc_html__( 'Importresultat', 'snippen-booking' ) . '</h3>';
		echo '<p><strong>' . sprintf( esc_html__( '%d beboere ble vellykket importert/oppdatert.', 'snippen-booking' ), $results['success'] ) . '</strong></p>';
		echo '<p><strong>' . sprintf( esc_html__( '%d beboere ble merket som slettet (ikke lenger i importen).', 'snippen-booking' ), $results['deleted'] ) . '</strong></p>';

		if ( ! empty( $results['logs'] ) ) {
			echo '<div style="margin-top: 15px; padding: 15px; background: #f8fafc; border: 1px solid var(--border-color); border-radius: 8px; max-height: 300px; overflow-y: auto;">';
			echo '<h4 style="margin-top:0;">' . esc_html__( 'Logger og feilmeldinger:', 'snippen-booking' ) . '</h4>';
			echo '<ul style="margin:0; padding-left:20px; font-family: monospace; font-size:12px; line-height: 1.5;">';
			foreach ( $results['logs'] as $log ) {
				$color = ( strpos( $log, 'ERROR:' ) === 0 ) ? 'var(--error-color)' : ( ( strpos( $log, 'WARNING:' ) === 0 ) ? '#d97706' : 'var(--text-main)' );
				echo '<li style="color: ' . esc_attr( $color ) . ';">' . esc_html( $log ) . '</li>';
			}
			echo '</ul>';
			echo '</div>';
		}
		echo '</div>';
	}

	/**
	 * Render the form
	 */
	private function render_form() {
		$providers = $this->import_manager->get_providers();
		$active_provider_id = isset( $_POST['snippen_import_provider'] ) ? sanitize_text_field( wp_unslash( $_POST['snippen_import_provider'] ) ) : ( ! empty( $providers ) ? $providers[0]->get_id() : '' );

		echo '<div class="snippen-card">';
		echo '<form method="post" action="">';
		wp_nonce_field( 'snippen_import_residents', 'snippen_import_nonce' );

		echo '<div class="snippen-form-group">';
		echo '<label for="snippen_import_provider">' . esc_html__( 'Velg Importmetode', 'snippen-booking' ) . '</label>';
		echo '<select name="snippen_import_provider" id="snippen_import_provider" class="regular-text" onchange="snippenToggleProviderUI()">';
		foreach ( $providers as $provider ) {
			echo '<option value="' . esc_attr( $provider->get_id() ) . '" ' . selected( $active_provider_id, $provider->get_id(), false ) . '>' . esc_html( $provider->get_name() ) . '</option>';
		}
		echo '</select>';
		echo '</div>';

		foreach ( $providers as $provider ) {
			$display = ( $provider->get_id() === $active_provider_id ) ? 'block' : 'none';
			echo '<div id="provider_ui_' . esc_attr( $provider->get_id() ) . '" class="snippen-provider-ui" style="display: ' . esc_attr( $display ) . ';">';
			$provider->render_ui();
			echo '</div>';
		}

		echo '<div class="snippen-form-actions">';
		echo '<button type="submit" class="snippen-btn snippen-btn-primary">' . esc_html__( 'Start Import', 'snippen-booking' ) . '</button>';
		echo '</div>';

		echo '</form>';
		echo '</div>';

		// Glassmorphic Loading Overlay
		echo '<div id="snippen-import-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); z-index: 999999; justify-content: center; align-items: center; flex-direction: column; color: #fff; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;">';
		echo '<div style="background: rgba(30, 41, 59, 0.95); padding: 40px; border-radius: 16px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); text-align: center; max-width: 400px; border: 1px solid rgba(255,255,255,0.1); margin: 20px;">';
		echo '<div class="snippen-spinner-ring" style="width: 70px; height: 70px; border: 4px solid rgba(255,255,255,0.1); border-top-color: #3b82f6; border-radius: 50%; animation: snippen-spin 1s infinite linear; margin: 0 auto 24px auto;"></div>';
		echo '<h3 style="color: #f8fafc; font-size: 20px; font-weight: 600; margin: 0 0 12px 0; border: none; padding: 0; line-height: 1.2;">' . esc_html__( 'Behandler import...', 'snippen-booking' ) . '</h3>';
		echo '<p style="color: #94a3b8; font-size: 14px; margin: 0 0 20px 0; line-height: 1.5;">' . esc_html__( 'Vennligst vent mens vi synkroniserer og oppdaterer beboerlisten. Dette kan ta noen sekunder.', 'snippen-booking' ) . '</p>';
		echo '<p style="color: #ef4444; font-size: 11px; font-weight: 600; margin: 0; text-transform: uppercase; letter-spacing: 0.05em;">' . esc_html__( 'Lukk eller oppdater ikke dette vinduet', 'snippen-booking' ) . '</p>';
		echo '</div>';
		echo '</div>';

		echo '<style>
		@keyframes snippen-spin {
			0% { transform: rotate(0deg); }
			100% { transform: rotate(360deg); }
		}
		</style>';

		// Inline Javascript
		echo '<script>
		function snippenToggleProviderUI() {
			var activeProvider = document.getElementById("snippen_import_provider").value;
			var uis = document.querySelectorAll(".snippen-provider-ui");
			uis.forEach(function(ui) {
				ui.style.display = "none";
				// Disable inputs in hidden providers to avoid post conflicts
				var inputs = ui.querySelectorAll("input, textarea, select");
				inputs.forEach(function(input) {
					input.disabled = true;
				});
			});
			
			var activeUI = document.getElementById("provider_ui_" + activeProvider);
			if (activeUI) {
				activeUI.style.display = "block";
				// Enable inputs in active provider
				var activeInputs = activeUI.querySelectorAll("input, textarea, select");
				activeInputs.forEach(function(input) {
					input.disabled = false;
				});
			}
		}

		document.addEventListener("DOMContentLoaded", function() {
			snippenToggleProviderUI(); // Initialize correct state on load
			
			var form = document.querySelector(".snippen-card form");
			if (form) {
				form.addEventListener("submit", function() {
					var overlay = document.getElementById("snippen-import-overlay");
					if (overlay) {
						overlay.style.display = "flex";
					}
				});
			}
		});
		</script>';
	}

	/**
	 * Handle POST request
	 *
	 * @return array|false Results array or false if not a valid post.
	 */
	private function handle_request() {
		if ( ! isset( $_POST['snippen_import_nonce'] ) || ! wp_verify_nonce( $_POST['snippen_import_nonce'], 'snippen_import_residents' ) ) {
			return false;
		}

		if ( ! ini_get( 'safe_mode' ) ) {
			@set_time_limit( 0 ); // Prevent timeout for larger lists
		}
		@ignore_user_abort( true ); // Keep running even if client disconnects

		$provider_id = isset( $_POST['snippen_import_provider'] ) ? sanitize_text_field( wp_unslash( $_POST['snippen_import_provider'] ) ) : '';
		
		$provider = $this->import_manager->get_provider( $provider_id );
		
		if ( ! $provider ) {
			return array(
				'success' => 0,
				'deleted' => 0,
				'logs'    => array( 'ERROR: Ugyldig importmetode valgt.' ),
			);
		}

		$result = $provider->import( wp_unslash( $_POST ) );

		// Sync Deletion Logic (apply to all providers uniformly)
		$deleted_count = 0;
		$residents     = get_users(
			array(
				'role'   => 'snippen_resident',
				'fields' => 'ID',
			)
		);
		$residents     = array_map( 'intval', $residents );
		$logs          = $result->logs;

		foreach ( $residents as $res_id ) {
			if ( ! in_array( $res_id, $result->imported_ids, true ) ) {
				$user_meta    = get_userdata( $res_id );
				
				if ( $user_meta && in_array( 'administrator', (array) $user_meta->roles, true ) ) {
					$display_name = $user_meta ? $user_meta->display_name : 'ID ' . $res_id;
					$logs[] = sprintf( esc_html__( "WARNING: Administrator '%s' er ikke i import-listen, og slettes ikke automatisk.", 'snippen-booking' ), esc_html( $display_name ) );
					continue;
				}

				$display_name = $user_meta ? $user_meta->display_name : 'ID ' . $res_id;

				update_user_meta( $res_id, 'snippen_user_deleted', 'yes' );
				++$deleted_count;
				$logs[] = sprintf( "INFO: '%s' er ikke lenger i import-listen, markert som slettet.", esc_html( $display_name ) );
			}
		}

		return array(
			'success' => $result->success,
			'deleted' => $deleted_count,
			'logs'    => $logs,
		);
	}
}
