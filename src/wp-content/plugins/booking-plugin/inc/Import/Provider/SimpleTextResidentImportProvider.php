<?php
/**
 * Simple Text Resident Import Provider
 *
 * @package SnippenBooking\Import\Provider
 */

namespace SnippenBooking\Import\Provider;

use SnippenBooking\Import\ResidentImportResult;
use SnippenBooking\Helper\PhoneHelper;

/**
 * Class SimpleTextResidentImportProvider
 */
class SimpleTextResidentImportProvider extends AbstractResidentImportProvider {

	/**
	 * Get the unique identifier for this provider.
	 */
	public function get_id(): string {
		return 'simple_text';
	}

	/**
	 * Get the display name for this provider.
	 */
	public function get_name(): string {
		return esc_html__( 'Linje-for-linje (Navn, E-post, Telefon - Lookahead)', 'snippen-booking' );
	}

	/**
	 * Get a description of how this provider works.
	 */
	public function get_description(): string {
		return esc_html__( '3 påfølgende linjer per beboer (1: Navn, 2: E-post, 3: Telefon). Linjer som inneholder nøyaktig "Reservert" ignoreres.', 'snippen-booking' );
	}

	/**
	 * Render the UI for the provider's input form.
	 */
	public function render_ui(): void {
		echo '<div class="snippen-form-group">';
		echo '<label for="snippen_import_data_text_' . esc_attr( $this->get_id() ) . '">' . esc_html__( 'Lim inn beboerdata', 'snippen-booking' ) . '</label>';
		echo '<textarea name="snippen_import_data" id="snippen_import_data_text_' . esc_attr( $this->get_id() ) . '" rows="15" style="width:100%; font-family:monospace; font-size:13px;" required></textarea>';
		echo '<p class="description">' . $this->get_description() . '</p>';
		echo '</div>';
	}

	/**
	 * Process the import based on the provided input.
	 *
	 * @param mixed $input Form input.
	 * @return ResidentImportResult
	 */
	public function import( $input ): ResidentImportResult {
		$result = new ResidentImportResult();

		$raw_data = isset( $input['snippen_import_data'] ) ? trim( $input['snippen_import_data'] ) : '';

		if ( empty( $raw_data ) ) {
			$result->logs[] = 'ERROR: Ingen data oppgitt.';
			return $result;
		}

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
						$result->logs[] = sprintf( "WARNING: Beboer '%s' har en ugyldig e-postadresse '%s'.", esc_html( $name ), esc_html( $email_line ) );
					}
					if ( ! $normalized_phone ) {
						$result->logs[] = sprintf( "WARNING: Beboer '%s' har et ikke-norsk eller ugyldig telefonnummer '%s'.", esc_html( $name ), esc_html( $phone_line ) );
					}

					$user_id = $this->upsert_resident( $name, $email_line, $normalized_phone );

					if ( is_wp_error( $user_id ) ) {
						$result->logs[] = sprintf( "ERROR: Kunne ikke importere '%s' - %s", esc_html( $name ), $user_id->get_error_message() );
					} else {
						++$result->success;
						$result->imported_ids[] = $user_id;
					}

					// Advance loop pointer past this resident block
					$i = $next_next_non_empty_idx + 1;
				} else {
					// Shift / missing field detected! Skip current name and recover from next line
					$result->logs[] = sprintf( "ERROR: '%s' hoppet over - Mangler e-post eller telefonnummer.", esc_html( $name ) );
					++$i;
				}
			} else {
				// Orphan email/phone or unrelated lines
				++$i;
			}
		}

		return $result;
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
