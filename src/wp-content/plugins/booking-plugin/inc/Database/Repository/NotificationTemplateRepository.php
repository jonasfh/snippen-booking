<?php
/**
 * Repository for notification templates
 *
 * @package SnippenBooking\Database\Repository
 */

namespace SnippenBooking\Database\Repository;

use SnippenBooking\Service\Notification\PlaceholderRegistry;

/**
 * Class NotificationTemplateRepository
 */
class NotificationTemplateRepository {

	/**
	 * Get database table name
	 *
	 * @return string
	 */
	private function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'snippen_notification_templates';
	}

	/**
	| Ensure database table exists
	|
	| @return void
	*/
	private function ensure_table_exists() {
		global $wpdb;
		$table = $this->get_table_name();
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
			$charset_collate = $wpdb->get_charset_collate();
			if ( file_exists( ABSPATH . 'wp-admin/includes/upgrade.php' ) ) {
				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			}
			$sql_templates = "CREATE TABLE $table (
                id BIGINT NOT NULL AUTO_INCREMENT,
                name VARCHAR(255) NOT NULL,
                type VARCHAR(20) NOT NULL,
                title VARCHAR(255) NULL,
                message TEXT NOT NULL,
                connected_to VARCHAR(50) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                modified_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY connected_type (connected_to, type)
            ) $charset_collate;";
			dbDelta( $sql_templates );
		}
	}

	/**
	 * Find a template by ID
	 *
	 * @param int $id Template ID.
	 * @return object|null
	 */
	public function find( int $id ): ?object {
		$this->ensure_table_exists();
		global $wpdb;
		$table = $this->get_table_name();
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				$id
			)
		);

		return $row ?: null;
	}

	/**
	 * Find a template by connected_to and type
	 *
	 * @param string $connected_to Feature connected to (e.g., account-activation or user_activation).
	 * @param string $type         Type (sms or email).
	 * @return object|null
	 */
	public function find_by_connected_and_type( string $connected_to, string $type ): ?object {
		$this->ensure_table_exists();
		global $wpdb;
		$table      = $this->get_table_name();
		$normalized = PlaceholderRegistry::normalize_context( $connected_to );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE (connected_to = %s OR connected_to = %s) AND type = %s LIMIT 1",
				$connected_to,
				$normalized,
				$type
			)
		);

		return $row ?: null;
	}

	/**
	 * Get all notification templates
	 *
	 * @return array
	 */
	public function get_all(): array {
		$this->ensure_table_exists();
		global $wpdb;
		$table   = $this->get_table_name();
		$results = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY connected_to ASC, type ASC, id ASC" );

		return $results ?: array();
	}

	/**
	 * Create a new template record
	 *
	 * @param array $data Template data (name, type, title, message, connected_to).
	 * @return int|false Inserted ID or false on failure.
	 */
	public function create( array $data ) {
		$this->ensure_table_exists();
		global $wpdb;
		$table = $this->get_table_name();

		$name         = sanitize_text_field( $data['name'] ?? '' );
		$type         = sanitize_text_field( $data['type'] ?? 'email' );
		$title        = isset( $data['title'] ) && null !== $data['title'] ? sanitize_text_field( $data['title'] ) : null;
		$message      = $data['message'] ?? '';
		$connected_to = ! empty( $data['connected_to'] ) ? PlaceholderRegistry::normalize_context( sanitize_text_field( $data['connected_to'] ) ) : null;

		if ( empty( $name ) || empty( $message ) ) {
			return false;
		}

		// Enforce uniqueness constraint for connected_to + type
		if ( ! empty( $connected_to ) ) {
			$existing = $this->find_by_connected_and_type( $connected_to, $type );
			if ( $existing ) {
				return false;
			}
		}

		$inserted = $wpdb->insert(
			$table,
			array(
				'name'         => $name,
				'type'         => $type,
				'title'        => $title,
				'message'      => $message,
				'connected_to' => $connected_to,
				'created_at'   => current_time( 'mysql' ),
				'modified_at'  => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update an existing template record
	 *
	 * @param int   $id   Template ID.
	 * @param array $data Template data to update.
	 * @return bool
	 */
	public function update( int $id, array $data ): bool {
		global $wpdb;
		$table = $this->get_table_name();

		$existing = $this->find( $id );
		if ( ! $existing ) {
			return false;
		}

		$update_data   = array(
			'modified_at' => current_time( 'mysql' ),
		);
		$update_format = array( '%s' );

		if ( isset( $data['name'] ) ) {
			$update_data['name'] = sanitize_text_field( $data['name'] );
			$update_format[]     = '%s';
		}
		if ( isset( $data['type'] ) ) {
			$update_data['type'] = sanitize_text_field( $data['type'] );
			$update_format[]     = '%s';
		}
		if ( array_key_exists( 'title', $data ) ) {
			$update_data['title'] = null !== $data['title'] ? sanitize_text_field( $data['title'] ) : null;
			$update_format[]      = '%s';
		}
		if ( isset( $data['message'] ) ) {
			$update_data['message'] = $data['message'];
			$update_format[]        = '%s';
		}
		if ( array_key_exists( 'connected_to', $data ) ) {
			$new_connected = ! empty( $data['connected_to'] ) ? PlaceholderRegistry::normalize_context( sanitize_text_field( $data['connected_to'] ) ) : null;
			$target_type   = $update_data['type'] ?? $existing->type;
			if ( ! empty( $new_connected ) ) {
				$conflict = $this->find_by_connected_and_type( $new_connected, $target_type );
				if ( $conflict && (int) $conflict->id !== $id ) {
					return false;
				}
			}
			$update_data['connected_to'] = $new_connected;
			$update_format[]             = '%s';
		}

		$result = $wpdb->update(
			$table,
			$update_data,
			array( 'id' => $id ),
			$update_format,
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Delete a template record
	 *
	 * @param int $id Template ID.
	 * @return bool
	 */
	public function delete( int $id ): bool {
		global $wpdb;
		$table  = $this->get_table_name();
		$result = $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );

		return (bool) $result;
	}

	/**
	 * Seed default templates into database idempotently
	 *
	 * @return void
	 */
	public function seed_defaults() {
		$defaults = array(
			array(
				'name'         => __( 'Kontoaktivering (SMS)', 'snippen-booking' ),
				'type'         => 'sms',
				'title'        => null,
				'message'      => __( 'Din bekreftelseskode for Snippen Booking er: {{confirmation_code}}. Koden er gyldig i 15 minutter.', 'snippen-booking' ),
				'connected_to' => 'account-activation',
			),
			array(
				'name'         => __( 'Kontoaktivering (E-post)', 'snippen-booking' ),
				'type'         => 'email',
				'title'        => __( 'Bekreftelseskode for Snippen Booking', 'snippen-booking' ),
				'message'      => __( "Hallo {{user_name}},\n\nDin bekreftelseskode for Snippen Booking er: {{confirmation_code}}\n\nKoden er gyldig i 15 minutter.\n\nVennligst enter koden på siden for å bekrefte kontoen din.", 'snippen-booking' ),
				'connected_to' => 'account-activation',
			),
			array(
				'name'         => __( 'Bookingbekreftelse (SMS)', 'snippen-booking' ),
				'type'         => 'sms',
				'title'        => null,
				'message'      => __( 'Takk for din bookingforespørsel for {{booking_objects}} den {{booking_date}}. Betaling: Bank {{bank_account}}, Vipps {{vipps_number}} ({{booking_price}} kr). {{payment_instructions}} Se detaljer: {{booking_url}}', 'snippen-booking' ),
				'connected_to' => 'booking-confirmation',
			),
			array(
				'name'         => __( 'Bookingbekreftelse (E-post)', 'snippen-booking' ),
				'type'         => 'email',
				'title'        => __( 'Bekreftelse på din bookingforespørsel', 'snippen-booking' ),
				'message'      => __( "Hallo {{user_name}},\n\nTakk for din bookingforespørsel for {{booking_objects}} den {{booking_date}}.\n\nBetalingsinformasjon:\nBankkontonr: {{bank_account}}\nVipps: {{vipps_number}}\nBeløp: {{booking_price}} kr\n\n{{payment_instructions}}\n\nDu kan se detaljer om din booking her: {{booking_url}}\n\nVed spørsmål, kontakt oss.", 'snippen-booking' ),
				'connected_to' => 'booking-confirmation',
			),
			array(
				'name'         => __( 'Admin Bookingvarsel (SMS)', 'snippen-booking' ),
				'type'         => 'sms',
				'title'        => null,
				'message'      => __( 'Ny bookingforespørsel for {{booking_objects}} den {{booking_date}} fra {{user_name}}.', 'snippen-booking' ),
				'connected_to' => 'admin-booking-alert',
			),
			array(
				'name'         => __( 'Admin Bookingvarsel (E-post)', 'snippen-booking' ),
				'type'         => 'email',
				'title'        => __( 'Ny Bookingforespørsel - {{booking_objects}}', 'snippen-booking' ),
				'message'      => __( "Ny bookingforespørsel mottatt:\n\nLokale: {{booking_objects}}\nDato: {{booking_date}}\nNavn: {{user_name}}\nEmail: {{user_email}}\nTelefon: {{user_phone}}\nBeskrivelse: {{booking_description}}\n\nVennligst logg inn i administrasjonsgrensesnittet for å håndtere denne forespørselen.", 'snippen-booking' ),
				'connected_to' => 'admin-booking-alert',
			),
			array(
				'name'         => __( 'Tilbakestill passord (SMS)', 'snippen-booking' ),
				'type'         => 'sms',
				'title'        => null,
				'message'      => __( 'For å tilbakestille passordet ditt, trykk på denne lenken: {{reset_link}}', 'snippen-booking' ),
				'connected_to' => 'password-reset',
			),
			array(
				'name'         => __( 'Tilbakestill passord (E-post)', 'snippen-booking' ),
				'type'         => 'email',
				'title'        => __( 'Tilbakestill passord', 'snippen-booking' ),
				'message'      => __( "Hallo {{user_name}},\n\nNoen har bedt om å tilbakestille passordet for din konto.\n\nHvis dette var en feiltakelse, kan du se bort fra denne e-posten.\n\nFor å tilbakestille passordet ditt, trykk på denne lenken:\n{{reset_link}}", 'snippen-booking' ),
				'connected_to' => 'password-reset',
			),
			array(
				'name'         => __( 'Betalingspurring (SMS)', 'snippen-booking' ),
				'type'         => 'sms',
				'title'        => null,
				'message'      => __( 'Påminnelse: Betaling for {{booking_objects}} den {{booking_date}} ({{booking_price}} kr) mangler. Bank: {{bank_account}}, Vipps: {{vipps_number}}. Last opp kvittering/skjermbilde her: {{booking_url}}', 'snippen-booking' ),
				'connected_to' => 'payment-reminder',
			),
			array(
				'name'         => __( 'Betalingspurring (E-post)', 'snippen-booking' ),
				'type'         => 'email',
				'title'        => __( 'Betalingspåminnelse for din booking', 'snippen-booking' ),
				'message'      => __( "Hallo {{user_name}},\n\nDette er en påminnelse om at betaling for din booking ({{booking_objects}} den {{booking_date}}) ennå ikke er registrert.\n\nBeløp: {{booking_price}} kr\nBankkontonr: {{bank_account}}\nVipps: {{vipps_number}}\n\nNår du har gjennomført betalingen, vennligst gå til bookinglenken og last opp kvittering eller skjermbilde fra betalingen:\n{{booking_url}}\n\n{{payment_instructions}}\n\nVed spørsmål, kontakt oss.", 'snippen-booking' ),
				'connected_to' => 'payment-reminder',
			),
		);

		$this->cleanup_duplicates();

		foreach ( $defaults as $tpl ) {
			$existing = $this->find_by_connected_and_type( $tpl['connected_to'], $tpl['type'] );
			if ( ! $existing ) {
				$this->create( $tpl );
			}
		}
	}

	/**
	 * Clean up duplicate templates for the same (connected_to, type) pair
	 *
	 * @return void
	 */
	public function cleanup_duplicates() {
		$this->ensure_table_exists();
		global $wpdb;
		$table = $this->get_table_name();

		$all  = $this->get_all();
		$seen = array();

		foreach ( $all as $tpl ) {
			if ( empty( $tpl->connected_to ) ) {
				continue;
			}
			$norm_conn = PlaceholderRegistry::normalize_context( $tpl->connected_to );
			$key       = $norm_conn . ':' . $tpl->type;

			if ( isset( $seen[ $key ] ) ) {
				$wpdb->delete( $table, array( 'id' => $tpl->id ), array( '%d' ) );
			} else {
				$seen[ $key ] = (int) $tpl->id;
				if ( $tpl->connected_to !== $norm_conn ) {
					$wpdb->update(
						$table,
						array( 'connected_to' => $norm_conn ),
						array( 'id' => $tpl->id ),
						array( '%s' ),
						array( '%d' )
					);
				}
			}
		}
	}
}
