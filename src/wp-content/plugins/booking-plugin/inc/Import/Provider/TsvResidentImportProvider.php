<?php
/**
 * TSV Resident Import Provider
 *
 * @package SnippenBooking\Import\Provider
 */

namespace SnippenBooking\Import\Provider;

use SnippenBooking\Import\ResidentImportResult;
use SnippenBooking\Helper\PhoneHelper;

/**
 * Class TsvResidentImportProvider
 */
class TsvResidentImportProvider extends AbstractResidentImportProvider {

	/**
	 * Get the unique identifier for this provider.
	 */
	public function get_id(): string {
		return 'tsv';
	}

	/**
	 * Get the display name for this provider.
	 */
	public function get_name(): string {
		return esc_html__( 'Tabulator-separert (TSV / Tabell-kopi fra ABBL)', 'snippen-booking' );
	}

	/**
	 * Get a description of how this provider works.
	 */
	public function get_description(): string {
		return esc_html__( 'Kopier tabell direkte fra Excel eller ABBL.', 'snippen-booking' );
	}

	/**
	 * Render the UI for the provider's input form.
	 */
	public function render_ui(): void {
		$mapping = 'name,email,phone';

		echo '<div class="snippen-form-group">';
		echo '<label for="snippen_import_mapping_' . esc_attr( $this->get_id() ) . '">' . esc_html__( 'Kolonne-mapping', 'snippen-booking' ) . '</label>';
		echo '<input type="text" name="snippen_import_mapping" id="snippen_import_mapping_' . esc_attr( $this->get_id() ) . '" value="' . esc_attr( $mapping ) . '" class="regular-text" placeholder="' . esc_attr__( 'name,email,phone', 'snippen-booking' ) . '">';
		echo '<p class="description">' . esc_html__( 'Definer rekkefølgen på kolonnene i tabellen (kommaseparert). Støttede felter: name, email, phone, address, unit', 'snippen-booking' ) . '</p>';
		echo '</div>';

		echo '<div class="snippen-form-group">';
		echo '<label for="snippen_import_data_' . esc_attr( $this->get_id() ) . '">' . esc_html__( 'Lim inn TSV-data', 'snippen-booking' ) . '</label>';
		echo '<textarea name="snippen_import_data" id="snippen_import_data_' . esc_attr( $this->get_id() ) . '" rows="15" style="width:100%; font-family:monospace; font-size:13px;" required></textarea>';
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
		$mapping  = isset( $input['snippen_import_mapping'] ) ? sanitize_text_field( $input['snippen_import_mapping'] ) : 'name,email,phone';

		if ( empty( $raw_data ) ) {
			$result->logs[] = 'ERROR: Ingen data oppgitt.';
			return $result;
		}

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
				$result->logs[] = sprintf( 'ERROR: Rad %d hoppet over - Mangler navn, e-post eller telefonnummer.', $row_index + 1 );
				continue;
			}

			$is_email_valid   = is_email( $email );
			$normalized_phone = PhoneHelper::normalize_phone( $phone );

			if ( ! $is_email_valid ) {
				$result->logs[] = sprintf( "WARNING: Beboer '%s' har en ugyldig e-postadresse '%s'.", esc_html( $name ), esc_html( $email ) );
			}
			if ( ! $normalized_phone ) {
				$result->logs[] = sprintf( "WARNING: Beboer '%s' har et ikke-norsk eller ugyldig telefonnummer '%s'.", esc_html( $name ), esc_html( $phone ) );
			}

			$user_id = $this->upsert_resident( $name, $email, $normalized_phone, $address, $unit );

			if ( is_wp_error( $user_id ) ) {
				$result->logs[] = sprintf( "ERROR: Kunne ikke importere '%s' - %s", esc_html( $name ), $user_id->get_error_message() );
			} else {
				++$result->success;
				$result->imported_ids[] = $user_id;
			}
		}

		return $result;
	}
}
