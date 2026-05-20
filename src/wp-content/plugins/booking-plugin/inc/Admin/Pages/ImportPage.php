<?php
/**
 * Admin page for importing residents
 *
 * @package SnippenBooking\Admin\Pages
 */

namespace SnippenBooking\Admin\Pages;

use SnippenBooking\Helper\PhoneHelper;
use SnippenBooking\Helper\Capabilities;

/**
 * Import Page
 */
class ImportPage {

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
				echo '<li style="color: ' . esc_attr( $color ) . ';">' . $log . '</li>'; // phpcs:ignore
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
		$format  = isset( $_POST['snippen_import_format'] ) ? sanitize_text_field( $_POST['snippen_import_format'] ) : 'line';
		$mapping = isset( $_POST['snippen_import_mapping'] ) ? sanitize_text_field( $_POST['snippen_import_mapping'] ) : 'name,email,phone';

		echo '<div class="snippen-card">';
		echo '<form method="post" action="">';
		wp_nonce_field( 'snippen_import_residents', 'snippen_import_nonce' );

		echo '<div class="snippen-form-group">';
		echo '<label for="snippen_import_format">' . esc_html__( 'Dataformat', 'snippen-booking' ) . '</label>';
		echo '<select name="snippen_import_format" id="snippen_import_format" class="regular-text" onchange="toggleMappingField()">';
		echo '<option value="line" ' . selected( $format, 'line', false ) . '>' . esc_html__( 'Linje-for-linje (Navn, E-post, Telefon - Lookahead)', 'snippen-booking' ) . '</option>';
		echo '<option value="tsv" ' . selected( $format, 'tsv', false ) . '>' . esc_html__( 'Tabulator-separert (TSV / Tabell-kopi fra ABBL)', 'snippen-booking' ) . '</option>';
		echo '</select>';
		echo '</div>';

		echo '<div class="snippen-form-group" id="mapping-container" style="' . ( $format === 'tsv' ? '' : 'display:none;' ) . '">';
		echo '<label for="snippen_import_mapping">' . esc_html__( 'Kolonne-mapping (for TSV)', 'snippen-booking' ) . '</label>';
		echo '<input type="text" name="snippen_import_mapping" id="snippen_import_mapping" value="' . esc_attr( $mapping ) . '" class="regular-text" placeholder="name,email,phone">';
		echo '<p class="description">' . esc_html__( 'Definer rekkefølgen på kolonnene i tabellen (kommaseparert). Støttede felter: name, email, phone, address, unit', 'snippen-booking' ) . '</p>';
		echo '</div>';

		echo '<div class="snippen-form-group">';
		echo '<label for="snippen_import_data">' . esc_html__( 'Lim inn beboerdata', 'snippen-booking' ) . '</label>';
		echo '<textarea name="snippen_import_data" id="snippen_import_data" rows="15" style="width:100%; font-family:monospace; font-size:13px;" required></textarea>';
		echo '<p class="description">' . esc_html__( 'For linje-for-linje: 3 påfølgende linjer per beboer (1: Navn, 2: E-post, 3: Telefon). Linjer som inneholder nøyaktig "Reservert" ignoreres.', 'snippen-booking' ) . '</p>';
		echo '</div>';

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

		// Inline Javascript to toggle the mapping field dynamically and show overlay on submit
		echo '<script>
		function toggleMappingField() {
			var format = document.getElementById("snippen_import_format").value;
			var container = document.getElementById("mapping-container");
			if (format === "tsv") {
				container.style.display = "";
			} else {
				container.style.display = "none";
			}
		}

		document.addEventListener("DOMContentLoaded", function() {
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

		$raw_data = isset( $_POST['snippen_import_data'] ) ? trim( $_POST['snippen_import_data'] ) : '';
		$format   = isset( $_POST['snippen_import_format'] ) ? sanitize_text_field( $_POST['snippen_import_format'] ) : 'line';
		$mapping  = isset( $_POST['snippen_import_mapping'] ) ? sanitize_text_field( $_POST['snippen_import_mapping'] ) : 'name,email,phone';

		if ( empty( $raw_data ) ) {
			return array(
				'success' => 0,
				'deleted' => 0,
				'logs'    => array( 'ERROR: Ingen data oppgitt.' ),
			);
		}

		$success_count = 0;
		$logs          = array();
		$imported_ids  = array();

		if ( $format === 'tsv' ) {
			// Tab-separated parsing
			$mapping_fields = array_map( 'trim', explode( ',', strtolower( $mapping ) ) );
			$rows           = explode( "\n", $raw_data );

			foreach ( $rows as $row_index => $row_str ) {
				$row_str = trim( $row_str );
				if ( empty( $row_str ) || $row_str === 'Reservert' ) {
					continue;
				}

				$cols = explode( "\t", $row_str );
				$data = array();

				foreach ( $mapping_fields as $col_index => $field ) {
					$data[ $field ] = isset( $cols[ $col_index ] ) ? trim( $cols[ $col_index ] ) : '';
				}

				$name    = sanitize_text_field( $data['name'] ?? '' );
				$email   = sanitize_email( $data['email'] ?? '' );
				$phone   = sanitize_text_field( $data['phone'] ?? '' );
				$address = sanitize_text_field( $data['address'] ?? '' );
				$unit    = sanitize_text_field( $data['unit'] ?? '' );

				if ( empty( $name ) || empty( $email ) || empty( $phone ) ) {
					$logs[] = sprintf( 'ERROR: Rad %d hoppet over - Mangler navn, e-post eller telefonnummer.', $row_index + 1 );
					continue;
				}

				$is_email_valid   = is_email( $email );
				$normalized_phone = PhoneHelper::normalize_phone( $phone );

				if ( ! $is_email_valid ) {
					$logs[] = sprintf( "WARNING: Beboer '%s' har en ugyldig e-postadresse '%s'.", esc_html( $name ), esc_html( $email ) );
				}
				if ( ! $normalized_phone ) {
					$logs[] = sprintf( "WARNING: Beboer '%s' har et ikke-norsk eller ugyldig telefonnummer '%s'.", esc_html( $name ), esc_html( $phone ) );
				}

				$user_id = $this->upsert_resident( $name, $email, $normalized_phone, $address, $unit );

				if ( is_wp_error( $user_id ) ) {
					$logs[] = sprintf( "ERROR: Kunne ikke importere '%s' - %s", esc_html( $name ), $user_id->get_error_message() );
				} else {
					++$success_count;
					$imported_ids[] = $user_id;
				}
			}
		} else {
			// Line-by-line look-ahead parsing
			$lines       = explode( "\n", $raw_data );
			$total_lines = count( $lines );
			$i           = 0;

			while ( $i < $total_lines ) {
				$line = trim( $lines[ $i ] );

				if ( empty( $line ) || $line === 'Reservert' ) {
					++$i;
					continue;
				}

				// If it doesn't look like email or phone format, it's a potential Name!
				if ( ! $this->looks_like_email( $line ) && ! $this->looks_like_phone( $line ) ) {
					$name = sanitize_text_field( $line );

					// Look-ahead for email (Line + 1)
					$next_non_empty_idx = $i + 1;
					while ( $next_non_empty_idx < $total_lines && trim( $lines[ $next_non_empty_idx ] ) === '' ) {
						++$next_non_empty_idx;
					}

					$email_line = '';
					if ( $next_non_empty_idx < $total_lines ) {
						$email_line = trim( $lines[ $next_non_empty_idx ] );
					}

					// Look-ahead for phone (Line + 2)
					$next_next_non_empty_idx = $next_non_empty_idx + 1;
					while ( $next_next_non_empty_idx < $total_lines && trim( $lines[ $next_next_non_empty_idx ] ) === '' ) {
						++$next_next_non_empty_idx;
					}

					$phone_line = '';
					if ( $next_next_non_empty_idx < $total_lines ) {
						$phone_line = trim( $lines[ $next_next_non_empty_idx ] );
					}

					// Verification of formats
					if ( ! empty( $email_line ) && $this->looks_like_email( $email_line ) && ! empty( $phone_line ) && $this->looks_like_phone( $phone_line ) ) {
						// Valid block detected!
						$is_email_valid   = is_email( $email_line );
						$normalized_phone = PhoneHelper::normalize_phone( $phone_line );

						if ( ! $is_email_valid ) {
							$logs[] = sprintf( "WARNING: Beboer '%s' har en ugyldig e-postadresse '%s'.", esc_html( $name ), esc_html( $email_line ) );
						}
						if ( ! $normalized_phone ) {
							$logs[] = sprintf( "WARNING: Beboer '%s' har et ikke-norsk eller ugyldig telefonnummer '%s'.", esc_html( $name ), esc_html( $phone_line ) );
						}

						$user_id = $this->upsert_resident( $name, $email_line, $normalized_phone );

						if ( is_wp_error( $user_id ) ) {
							$logs[] = sprintf( "ERROR: Kunne ikke importere '%s' - %s", esc_html( $name ), $user_id->get_error_message() );
						} else {
							++$success_count;
							$imported_ids[] = $user_id;
						}

						// Advance loop pointer past this resident block
						$i = $next_next_non_empty_idx + 1;
					} else {
						// Shift / missing field detected! Skip current name and recover from next line
						$logs[] = sprintf( "ERROR: '%s' hoppet over - Mangler e-post eller telefonnummer.", esc_html( $name ) );
						++$i;
					}
				} else {
					// Orphan email/phone or unrelated lines
					++$i;
				}
			}
		}

		// Sync Deletion Logic
		$deleted_count = 0;
		$residents     = get_users(
			array(
				'role'   => 'holmen_resident',
				'fields' => 'ID',
			)
		);
		$residents     = array_map( 'intval', $residents );

		foreach ( $residents as $res_id ) {
			if ( ! in_array( $res_id, $imported_ids, true ) ) {
				$user_meta    = get_userdata( $res_id );
				$display_name = $user_meta ? $user_meta->display_name : 'ID ' . $res_id;

				update_user_meta( $res_id, 'snippen_user_deleted', 'yes' );
				++$deleted_count;
				$logs[] = sprintf( "INFO: '%s' er ikke lenger i import-listen, markert som slettet.", esc_html( $display_name ) );
			}
		}

		return array(
			'success' => $success_count,
			'deleted' => $deleted_count,
			'logs'    => $logs,
		);
	}

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
	private function upsert_resident( $name, $email, $normalized_phone, $address = '', $unit = '' ) {
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

	/**
	 * Check if a line looks like an email
	 *
	 * @param string $line
	 * @return bool
	 */
	private function looks_like_email( $line ) {
		return strpos( $line, '@' ) !== false;
	}

	/**
	 * Check if a line looks like a phone number
	 *
	 * @param string $line
	 * @return bool
	 */
	private function looks_like_phone( $line ) {
		$digits = preg_replace( '/[^0-9]/', '', $line );
		return preg_match( '/^\+?[0-9\s\-]+$/', $line ) && strlen( $digits ) >= 5;
	}
}
