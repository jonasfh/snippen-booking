<?php

namespace SnippenBooking\Service\Ai;

use SnippenBooking\Service\Notification\MessageLoggerService;

/**
 * Builds anonymized, structured context for AI-assisted SMS reply suggestions.
 */
class AiContextBuilder {

	/**
	 * Maximum characters of combined page/terms text to include in prompt.
	 */
	const MAX_KNOWLEDGE_CHARS = 15000;

	/**
	 * Build system instruction and user prompt for a given SMS message or booking.
	 *
	 * @param int|null $message_id Optional inbox message ID.
	 * @param int|null $booking_id Optional booking ID.
	 * @param string   $hint       Optional admin hint/instruction.
	 * @return array{system_instruction: string, user_prompt: string}
	 */
	public function build_context( ?int $message_id = null, ?int $booking_id = null, string $hint = '' ): array {
		$message = $message_id ? MessageLoggerService::get_message( $message_id ) : null;
		if ( ! $booking_id && $message && ! empty( $message->booking_id ) ) {
			$booking_id = (int) $message->booking_id;
		}

		$system_instruction = implode(
			"\n",
			array(
				'Du er en hjelpsom og høflig kundeservice-assistent for Snippen Grendehus (Vestre Holmen Sameie).',
				'Oppgaven din er å skrive et forslag til et kort, tydelig og vennlig SMS-svar på norsk som administrator kan gjennomgå før sending.',
				'Regler:',
				'- Skriv kun selve SMS-teksten (ingen innledning, ingen anførselstegn, ingen forklaring rundt).',
				'- Hold svaret kort og egnet for SMS (helst 1-2 SMS-deler, maks ca. 300 tegn med mindre spørsmålet krever mer).',
				'- Baser svaret KUN på oppgitt bakgrunnsinformasjon (vilkår, nettsider, utleieregler og bookingstatus).',
				'- Ikke finn på priser, klokkeslett, dørkoder eller regler som ikke står i bakgrunnsinformasjonen. Hvis noe er uklart eller mangler, formuler et høflig svar der du ber om avklaring eller sier at styret/utleier følger opp.',
				'- Bruk aldri personopplysninger som dørkoder, e-post eller telefonnummer med mindre det allerede står i oppgitt offentlig kontaktinfo.',
			)
		);

		$sections = array();

		$knowledge_text = $this->get_pages_knowledge_text();
		if ( '' !== $knowledge_text ) {
			$sections[] = "=== VILKÅR OG NETTSIDEINFORMASJON ===\n" . $knowledge_text;
		}

		$extra_context = trim( (string) get_option( 'snippen_ai_extra_context', '' ) );
		if ( '' !== $extra_context ) {
			$sections[] = "=== EKSTRA PRAKTISK INFO / FAQ ===\n" . $extra_context;
		}

		$facility_rules = $this->get_facility_and_rules_text();
		if ( '' !== $facility_rules ) {
			$sections[] = "=== LOKALER OG GENERELLE REGLER ===\n" . $facility_rules;
		}

		$booking_phone = '';
		if ( $booking_id ) {
			$booking_info = $this->get_anonymized_booking_info( $booking_id );
			if ( '' !== $booking_info['text'] ) {
				$sections[] = "=== ANONYMISERT BOOKINGSTATUS ===\n" . $booking_info['text'];
			}
			$booking_phone = $booking_info['phone'];
		}

		$thread_phone = $message ? (string) $message->recipient : $booking_phone;
		$thread_text  = $this->get_conversation_thread_text( $thread_phone, $booking_id );
		if ( '' !== $thread_text ) {
			$sections[] = "=== SAMTALEHISTORIKK (SMS) ===\n" . $thread_text;
		}

		$clean_hint = trim( $hint );
		if ( '' !== $clean_hint ) {
			$sections[] = "=== STIKKORD / INSTRUKS FRA ADMIN ===\n" . $clean_hint;
		}

		$sections[] = 'Skriv nå et kort og vennlig SMS-utkast til kunden basert på informasjonen over.';

		return array(
			'system_instruction' => $system_instruction,
			'user_prompt'        => implode( "\n\n", $sections ),
		);
	}

	/**
	 * Collect plain text from configured WordPress pages and the local terms page.
	 *
	 * @return string
	 */
	private function get_pages_knowledge_text(): string {
		$page_ids = get_option( 'snippen_ai_context_page_ids', array() );
		if ( ! is_array( $page_ids ) ) {
			$page_ids = array();
		}
		$page_ids = array_map( 'absint', $page_ids );

		$terms_url = trim( (string) get_option( 'snippen_terms_url', '' ) );
		if ( '' !== $terms_url && function_exists( 'url_to_postid' ) ) {
			$terms_page_id = (int) url_to_postid( $terms_url );
			if ( $terms_page_id > 0 ) {
				array_unshift( $page_ids, $terms_page_id );
			}
		}

		$page_ids = array_values( array_unique( array_filter( $page_ids ) ) );
		if ( empty( $page_ids ) ) {
			return '';
		}

		$chunks = array();
		foreach ( $page_ids as $page_id ) {
			$post = get_post( $page_id );
			if ( ! $post || 'publish' !== $post->post_status ) {
				continue;
			}

			$raw_content = (string) $post->post_content;
			if ( function_exists( 'strip_shortcodes' ) ) {
				$raw_content = strip_shortcodes( $raw_content );
			}
			$clean_content = trim( preg_replace( "/\n{3,}/", "\n\n", wp_strip_all_tags( $raw_content ) ) );
			if ( '' === $clean_content ) {
				continue;
			}

			$chunks[] = '--- ' . wp_strip_all_tags( (string) $post->post_title ) . " ---\n" . $clean_content;
		}

		$combined = implode( "\n\n", $chunks );
		if ( mb_strlen( $combined ) > self::MAX_KNOWLEDGE_CHARS ) {
			$combined = mb_substr( $combined, 0, self::MAX_KNOWLEDGE_CHARS ) . "\n[... avkortet ...]";
		}

		return $combined;
	}

	/**
	 * Collect rental objects and general payment/cancellation/cleaning settings.
	 *
	 * @return string
	 */
	private function get_facility_and_rules_text(): string {
		global $wpdb;

		$lines = array();

		$objects_table = $wpdb->prefix . 'snippen_booking_objects';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$objects = $wpdb->get_results( "SELECT name, description FROM {$objects_table} WHERE deleted_at IS NULL ORDER BY id ASC" );
		if ( is_array( $objects ) && ! empty( $objects ) ) {
			$lines[] = 'Utleieobjekter:';
			foreach ( $objects as $obj ) {
				$desc    = trim( wp_strip_all_tags( (string) ( $obj->description ?? '' ) ) );
				$lines[] = '- ' . $obj->name . ( '' !== $desc ? ': ' . $desc : '' );
			}
		}

		$cancellation_days = (int) get_option( 'snippen_user_cancellation_days', 14 );
		$cleaning_end      = (string) get_option( 'snippen_cleaning_end_time', '11:00' );
		$bank_account      = trim( (string) get_option( 'snippen_payment_bank_account', '' ) );
		$vipps_number      = trim( (string) get_option( 'snippen_payment_vipps_number', '' ) );

		$lines[] = sprintf( 'Avbestillingsfrist (ubetalt/ubekreftet): %d dager før bookingstart.', $cancellation_days );
		$lines[] = sprintf( 'Utvask neste dag slutter kl. %s.', '' !== $cleaning_end ? $cleaning_end : '11:00' );
		if ( '' !== $bank_account ) {
			$lines[] = 'Kontonummer for manuell betaling: ' . $bank_account;
		}
		if ( '' !== $vipps_number ) {
			$lines[] = 'Vipps: ' . $vipps_number;
		}

		return implode( "\n", $lines );
	}

	/**
	 * Build anonymized booking summary without exposing PII or door codes.
	 *
	 * @param int $booking_id Booking ID.
	 * @return array{text: string, phone: string}
	 */
	private function get_anonymized_booking_info( int $booking_id ): array {
		global $wpdb;

		$bookings_table = $wpdb->prefix . 'snippen_bookings';
		$booking        = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT id, booking_date, booking_type, status, payment_status_id, price, discount_amount, door_code, customer_phone FROM {$bookings_table} WHERE id = %d AND deleted_at IS NULL",
				$booking_id
			)
		);

		if ( ! $booking ) {
			return array(
				'text'  => '',
				'phone' => '',
			);
		}

		$rel_table = $wpdb->prefix . 'snippen_bookings_booking_objects';
		$obj_table = $wpdb->prefix . 'snippen_booking_objects';
		$obj_names = $wpdb->get_col(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT o.name FROM {$obj_table} o INNER JOIN {$rel_table} r ON r.booking_object_id = o.id WHERE r.booking_id = %d",
				$booking_id
			)
		);

		$status_map = array(
			'pending'         => 'Venter på godkjenning',
			'pending_payment' => 'Venter på betaling',
			'confirmed'       => 'Bekreftet',
			'cancelled'       => 'Avbrutt',
			'rejected'        => 'Avslått',
		);
		$pay_map    = array(
			1 => 'Ubetalt',
			2 => 'Betalt',
			3 => 'Venter på kontroll',
			4 => 'Refundert',
		);

		$lines   = array();
		$lines[] = 'Booking #' . (int) $booking->id;
		if ( ! empty( $obj_names ) ) {
			$lines[] = 'Lokale(r): ' . implode( ', ', $obj_names );
		}
		if ( ! empty( $booking->booking_date ) ) {
			$lines[] = 'Dato: ' . $booking->booking_date;
		}
		$lines[] = 'Type: ' . (string) $booking->booking_type;
		$lines[] = 'Status: ' . ( $status_map[ $booking->status ] ?? (string) $booking->status );
		$lines[] = 'Betalingsstatus: ' . ( $pay_map[ (int) $booking->payment_status_id ] ?? 'Ukjent' );

		$net_price = max( 0, (float) $booking->price - (float) $booking->discount_amount );
		$lines[]   = sprintf( 'Pris: %.0f kr', $net_price );
		$lines[]   = 'Dørkode tildelt: ' . ( ! empty( $booking->door_code ) ? 'Ja (sendes/vises på bookingsiden)' : 'Ikke tildelt ennå' );

		return array(
			'text'  => implode( "\n", $lines ),
			'phone' => (string) ( $booking->customer_phone ?? '' ),
		);
	}

	/**
	 * Format recent SMS messages in the thread without sender phone numbers.
	 *
	 * @param string   $phone      Phone number for thread lookup.
	 * @param int|null $booking_id Optional booking ID.
	 * @return string
	 */
	private function get_conversation_thread_text( string $phone, ?int $booking_id ): string {
		if ( '' === trim( $phone ) && ! $booking_id ) {
			return '';
		}

		$thread = MessageLoggerService::get_conversation_thread( $phone, $booking_id, 12 );
		if ( empty( $thread ) ) {
			return '';
		}

		$lines = array();
		foreach ( $thread as $item ) {
			$is_inbound = ( 'inbound_sms' === $item->event_type || 'received' === $item->status || 'quarantine' === $item->status );
			$speaker    = $is_inbound ? 'Kunde' : 'Utleier';
			$time       = ! empty( $item->created_at ) ? get_date_from_gmt( $item->created_at, 'd.m H:i' ) : '';
			$prefix     = '' !== $time ? "[{$time}] {$speaker}" : $speaker;
			$lines[]    = $prefix . ': ' . trim( (string) $item->message );
		}

		return implode( "\n", $lines );
	}
}
