<?php
/**
 * SMS Inbox Resolver Service
 *
 * Implements rule-based matching of inbound SMS messages to reservations and users,
 * handles multi-booking disambiguation prompts, and manages quarantine workflows.
 *
 * @package SnippenBooking\Service\Sms
 */

namespace SnippenBooking\Service\Sms;

use SnippenBooking\Service\Notification\MessageLoggerService;
use SnippenBooking\Helper\PhoneHelper;

/**
 * Class SmsInboxResolverService
 */
class SmsInboxResolverService {

	const STATUS_RECEIVED          = 'received';
	const STATUS_PENDING_SELECTION = 'pending_selection';
	const STATUS_GENERAL_INQUIRY   = 'general_inquiry';
	const STATUS_QUARANTINE        = 'quarantine';

	const EVENT_INBOUND_SMS                 = 'inbound_sms';
	const EVENT_DISAMBIGUATION_PROMPT       = 'sms_disambiguation_prompt';
	const EVENT_DISAMBIGUATION_CONFIRMATION = 'sms_disambiguation_confirmation';

	/**
	 * Parse selection choice (1-based index) from user text.
	 *
	 * Supports numeric patterns ('1', '2', 'nr. 1', '#2') and Norwegian ordinals ('første', 'andre').
	 *
	 * @param string $text        Raw text received.
	 * @param int    $max_options Maximum allowed choice number.
	 * @return int|null 1-based index or null if not a valid selection.
	 */
	public static function parse_selection( string $text, int $max_options ): ?int {
		$clean = trim( mb_strtolower( $text ) );
		if ( '' === $clean || $max_options < 1 ) {
			return null;
		}

		if ( preg_match( '/^\s*(?:nr\.?|nummer|valg|booking|alternativ|#)?\s*(\d+)\.?\s*$/i', $clean, $matches ) ) {
			$val = (int) $matches[1];
			if ( $val >= 1 && $val <= $max_options ) {
				return $val;
			}
		}

		$ordinals = array(
			'første'     => 1,
			'den første' => 1,
			'1ste'       => 1,
			'andre'      => 2,
			'den andre'  => 2,
			'2dre'       => 2,
			'tredje'     => 3,
			'den tredje' => 3,
			'3dje'       => 3,
			'fjerde'     => 4,
			'den fjerde' => 4,
			'femte'      => 5,
			'den femte'  => 5,
		);

		if ( isset( $ordinals[ $clean ] ) && $ordinals[ $clean ] <= $max_options ) {
			return $ordinals[ $clean ];
		}

		return null;
	}

	/**
	 * Format a clear, natural-language label for a single booking.
	 * E.g. "Peisestuen (27.09.2026 kl. 11:00)"
	 *
	 * @param object $booking Booking object.
	 * @return string Formatted booking label.
	 */
	public static function format_booking_label( object $booking ): string {
		$resource_name  = ! empty( $booking->resource_name ) ? $booking->resource_name : __( 'Lokale/ressurs', 'snippen-booking' );
		$date_formatted = ! empty( $booking->booking_date ) ? date_i18n( 'd.m.Y', strtotime( $booking->booking_date ) ) : '';
		$time_formatted = ! empty( $booking->slot_start ) ? substr( $booking->slot_start, 0, 5 ) : '';

		$dt_str = $date_formatted;
		if ( ! empty( $time_formatted ) ) {
			$dt_str .= ' kl. ' . $time_formatted;
		}

		if ( ! empty( $dt_str ) ) {
			return sprintf( '%s (%s)', $resource_name, $dt_str );
		}

		return $resource_name;
	}

	/**
	 * Format a natural-language confirmation acknowledging the user's booking selection.
	 *
	 * @param object|null $booking Booking object or null if unavailable.
	 * @return string Norwegian confirmation text.
	 */
	public static function format_disambiguation_confirmation( ?object $booking ): string {
		if ( ! $booking ) {
			return __( 'Henvendelsen og kommende meldinger er nå knyttet til din valgte reservasjon.', 'snippen-booking' );
		}

		$label = self::format_booking_label( $booking );

		return sprintf(
			/* translators: %s: formatted booking label */
			__( 'Henvendelsen og kommende meldinger knyttes til reservasjon: %s', 'snippen-booking' ),
			$label
		);
	}

	/**
	 * Format a clear, natural-language prompt asking the user to choose between active bookings.
	 *
	 * @param array $bookings Array of booking objects.
	 * @return string Norwegian prompt text.
	 */
	public static function format_disambiguation_prompt( array $bookings ): string {
		$lines = array(
			__( 'Du har flere aktive reservasjoner på Snippen:', 'snippen-booking' ),
		);

		foreach ( $bookings as $index => $booking ) {
			$num     = $index + 1;
			$lines[] = sprintf( '%d. %s', $num, self::format_booking_label( $booking ) );
		}

		$lines[] = __( 'Hvilken reservasjon gjelder henvendelsen? Svar med tallet (f.eks. 1 eller 2).', 'snippen-booking' );

		return implode( "\n", $lines );
	}

	/**
	 * Generate search phone variants (E.164, national without prefix, etc.)
	 *
	 * @param string $phone Raw phone string.
	 * @return array List of normalized phone variants for SQL matching.
	 */
	public static function get_search_phones( string $phone ): array {
		$phone_clean  = preg_replace( '/[^0-9]/', '', (string) $phone );
		$phone_norway = ltrim( $phone_clean, '47' );

		return array_values(
			array_unique(
				array_filter(
					array(
						$phone,
						'+' . $phone_clean,
						$phone_clean,
						$phone_norway,
						'+47' . $phone_norway,
						'0047' . $phone_norway,
					)
				)
			)
		);
	}

	/**
	 * Find WordPress User ID by phone number.
	 *
	 * @param array $search_phones List of phone variants.
	 * @return int|null User ID or null if not found.
	 */
	public static function find_user_by_phones( array $search_phones ): ?int {
		global $wpdb;

		if ( empty( $search_phones ) ) {
			return null;
		}

		$placeholders = implode( ',', array_fill( 0, count( $search_phones ), '%s' ) );

		// 1. Check user meta (snippen_phone, phone, billing_phone, or user_phone)
		$query = $wpdb->prepare(
			"SELECT user_id FROM {$wpdb->usermeta} 
			 WHERE meta_key IN ('snippen_phone', 'phone', 'billing_phone', 'user_phone') 
			   AND meta_value IN ({$placeholders}) 
			 LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			...$search_phones
		);

		$user_id = $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( $user_id ) {
			return (int) $user_id;
		}

		// 2. Check past bookings for an associated user_id
		$table_bookings = $wpdb->prefix . 'snippen_bookings';
		$b_query        = $wpdb->prepare(
			"SELECT user_id FROM {$table_bookings} 
			 WHERE customer_phone IN ({$placeholders}) 
			   AND user_id IS NOT NULL 
			   AND user_id > 0 
			 ORDER BY id DESC 
			 LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			...$search_phones
		);

		$b_user_id = $wpdb->get_var( $b_query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( $b_user_id ) {
			return (int) $b_user_id;
		}

		return null;
	}

	/**
	 * Retrieve candidate active bookings for phone numbers.
	 *
	 * A booking is active if:
	 * - status != 'cancelled' AND deleted_at IS NULL
	 * - Future: booking_date >= CURRENT_DATE
	 * - Past paid: booking_date >= (CURRENT_DATE - <paid_days> days)
	 * - Past unpaid: booking_date >= (CURRENT_DATE - <unpaid_days> days)
	 *
	 * @param array $search_phones List of phone variants.
	 * @return array List of booking objects with resource_name attached.
	 */
	public static function get_active_bookings_for_phones( array $search_phones ): array {
		global $wpdb;

		if ( empty( $search_phones ) ) {
			return array();
		}

		$paid_past_days   = (int) get_option( 'snippen_sms_active_booking_past_days', 2 );
		$unpaid_past_days = (int) get_option( 'snippen_sms_unpaid_booking_past_days', 0 );

		$table_bookings = $wpdb->prefix . 'snippen_bookings';
		$table_junction = $wpdb->prefix . 'snippen_bookings_booking_objects';
		$table_objects  = $wpdb->prefix . 'snippen_booking_objects';
		$table_slots    = $wpdb->prefix . 'snippen_time_slots';
		$table_statuses = $wpdb->prefix . 'snippen_payment_statuses';

		$placeholders = implode( ',', array_fill( 0, count( $search_phones ), '%s' ) );

		$query = $wpdb->prepare(
			"SELECT b.*, s.start_time as slot_start, s.end_time as slot_end, ps.slug as payment_slug, ps.is_settled 
			 FROM {$table_bookings} b 
			 LEFT JOIN {$table_slots} s ON b.slot_id = s.id 
			 LEFT JOIN {$table_statuses} ps ON b.payment_status_id = ps.id 
			 WHERE b.customer_phone IN ({$placeholders}) 
			   AND b.deleted_at IS NULL 
			   AND b.status != 'cancelled' 
			 ORDER BY b.booking_date ASC, b.id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			...$search_phones
		);

		$rows = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$today  = current_time( 'Y-m-d' );
		$active = array();

		foreach ( $rows as $b ) {
			$b_date         = $b->booking_date;
			$is_paid        = ( 1 === (int) ( $b->is_settled ?? 0 ) || 'PAID' === strtoupper( $b->payment_slug ?? '' ) || 2 === (int) ( $b->payment_status_id ?? 0 ) );
			$past_allowance = $is_paid ? $paid_past_days : $unpaid_past_days;

			// Calculate cutoff date
			$cutoff_date = gmdate( 'Y-m-d', strtotime( "-{$past_allowance} days", strtotime( $today ) ) );

			if ( $b_date >= $cutoff_date ) {
				// Fetch resource names
				$object_names     = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT o.name 
						 FROM {$table_junction} bo 
						 JOIN {$table_objects} o ON bo.booking_object_id = o.id 
						 WHERE bo.booking_id = %d",
						$b->id
					)
				);
				$b->resource_name = implode( ', ', $object_names );
				$active[]         = $b;
			}
		}

		return $active;
	}

	/**
	 * Get summary details for a single booking (including resource names and slot start/end).
	 *
	 * @param int $booking_id Booking ID.
	 * @return object|null Booking object or null if not found.
	 */
	public static function get_booking_summary( int $booking_id ): ?object {
		global $wpdb;

		$table_bookings = $wpdb->prefix . 'snippen_bookings';
		$table_junction = $wpdb->prefix . 'snippen_bookings_booking_objects';
		$table_objects  = $wpdb->prefix . 'snippen_booking_objects';
		$table_slots    = $wpdb->prefix . 'snippen_time_slots';

		$query = $wpdb->prepare(
			"SELECT b.*, s.start_time as slot_start, s.end_time as slot_end 
			 FROM {$table_bookings} b 
			 LEFT JOIN {$table_slots} s ON b.slot_id = s.id 
			 WHERE b.id = %d 
			 LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$booking_id
		);

		$booking = $wpdb->get_row( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! $booking ) {
			return null;
		}

		$object_names           = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT o.name 
				 FROM {$table_junction} bo 
				 JOIN {$table_objects} o ON bo.booking_object_id = o.id 
				 WHERE bo.booking_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$booking->id
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$booking->resource_name = ! empty( $object_names ) ? implode( ', ', $object_names ) : '';

		return $booking;
	}

	/**
	 * Resolve an inbound SMS message according to the 6 prioritized rules.
	 *
	 * @param string      $sender_phone  Sender phone number.
	 * @param string      $body          SMS text content.
	 * @param int|null    $gateway_id    Optional gateway-assigned ID.
	 * @param string|null $modem_msg_id  Optional modem message ID.
	 * @param string|null $received_at   Optional ISO 8601 timestamp.
	 * @return array Resolution details: [logged_id, status, booking_id, user_id, rule, prompt_sent].
	 */
	public static function resolve_message(
		string $sender_phone,
		string $body,
		?int $gateway_id = null,
		?string $modem_msg_id = null,
		?string $received_at = null
	): array {
		global $wpdb;

		$sender_phone  = trim( $sender_phone );
		$body          = trim( $body );
		$search_phones = self::get_search_phones( $sender_phone );
		$found_user_id = self::find_user_by_phones( $search_phones );

		$metadata = array(
			'direction' => 'inbound',
		);
		if ( null !== $gateway_id ) {
			$metadata['gateway_id'] = $gateway_id;
		}
		if ( ! empty( $modem_msg_id ) ) {
			$metadata['modem_message_id'] = sanitize_text_field( $modem_msg_id );
		}
		if ( ! empty( $received_at ) ) {
			$metadata['received_at'] = sanitize_text_field( $received_at );
		}

		$table_messages = $wpdb->prefix . 'snippen_messages';
		$ttl_minutes    = (int) get_option( 'snippen_sms_conversation_ttl_minutes', 120 );
		$ttl_seconds    = max( 60, $ttl_minutes * 60 );
		$current_ts     = time();

		// Retrieve active bookings for phone numbers
		$active_bookings = self::get_active_bookings_for_phones( $search_phones );
		$booking_count   = count( $active_bookings );

		// -------------------------------------------------------------
		// REGEL 1: Pågående samtale innenfor samtale-TTL
		// Sjekk om det har vært en SMS (inn/ut) knyttet til en booking nylig.
		// Samtalen avbrytes dersom bookingen ikke lenger er aktiv, eller hvis
		// en annen booking er opprettet eller endret etter siste samtale-SMS.
		// -------------------------------------------------------------
		$recent_placeholders = implode( ',', array_fill( 0, count( $search_phones ), '%s' ) );
		$recent_query        = $wpdb->prepare(
			"SELECT * FROM {$table_messages} 
			 WHERE channel = 'sms' 
			   AND booking_id IS NOT NULL 
			   AND recipient IN ({$recent_placeholders}) 
			 ORDER BY created_at DESC, id DESC 
			 LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			...$search_phones
		);
		$last_session_msg    = $wpdb->get_row( $recent_query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( $last_session_msg ) {
			$msg_ts = strtotime( $last_session_msg->created_at );
			if ( ( $current_ts - $msg_ts ) <= $ttl_seconds ) {
				$session_booking_id = (int) $last_session_msg->booking_id;

				// Verify that session booking is currently active
				$is_session_active   = false;
				$session_invalidated = false;

				foreach ( $active_bookings as $ab ) {
					if ( (int) $ab->id === $session_booking_id ) {
						$is_session_active = true;
						break;
					}
				}

				// If session booking is active and user has other active bookings,
				// check if any other booking has been created or modified AFTER the session message
				if ( $is_session_active && $booking_count > 1 ) {
					foreach ( $active_bookings as $ab ) {
						if ( (int) $ab->id === $session_booking_id ) {
							continue;
						}
						$b_created  = ! empty( $ab->created_at ) ? strtotime( $ab->created_at ) : 0;
						$b_modified = ! empty( $ab->modified_at ) ? strtotime( $ab->modified_at ) : 0;

						if ( $b_created > $msg_ts || $b_modified > $msg_ts ) {
							$session_invalidated = true;
							break;
						}
					}
				}

				if ( $is_session_active && ! $session_invalidated ) {
					$booking_id = $session_booking_id;
					$user_id    = ! empty( $last_session_msg->user_id ) ? (int) $last_session_msg->user_id : $found_user_id;

					$metadata['matched_rule'] = 'active_session';
					$logged_id                = MessageLoggerService::log_message(
						$booking_id,
						$user_id,
						'sms',
						$sender_phone,
						null,
						$body,
						self::EVENT_INBOUND_SMS,
						self::STATUS_RECEIVED,
						$metadata
					);

					return array(
						'logged_id'         => $logged_id,
						'status'            => self::STATUS_RECEIVED,
						'booking_id'        => $booking_id,
						'user_id'           => $user_id,
						'rule'              => 'active_session',
						'prompt_sent'       => false,
						'confirmation_sent' => false,
					);
				}
			}
		}

		// -------------------------------------------------------------
		// REGEL 2: Svar på pågående flervalgsforespørsel
		// Sjekk om forrige utgående melding var en disambiguation prompt
		// -------------------------------------------------------------
		$prompt_query = $wpdb->prepare(
			"SELECT * FROM {$table_messages} 
			 WHERE channel = 'sms' 
			   AND event_type = %s 
			   AND recipient IN ({$recent_placeholders}) 
			 ORDER BY created_at DESC, id DESC 
			 LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			self::EVENT_DISAMBIGUATION_PROMPT,
			...$search_phones
		);
		$last_prompt  = $wpdb->get_row( $prompt_query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( $last_prompt ) {
			$prompt_ts = strtotime( $last_prompt->created_at );
			if ( ( $current_ts - $prompt_ts ) <= $ttl_seconds && ! empty( $last_prompt->metadata ) ) {
				$prompt_meta = json_decode( $last_prompt->metadata, true );
				$candidates  = $prompt_meta['candidate_booking_ids'] ?? array();

				if ( is_array( $candidates ) && ! empty( $candidates ) ) {
					$choice = self::parse_selection( $body, count( $candidates ) );
					if ( null !== $choice ) {
						$resolved_booking_id         = (int) $candidates[ $choice - 1 ];
						$metadata['matched_rule']    = 'disambiguation_selection';
						$metadata['selected_option'] = $choice;

						$logged_id = MessageLoggerService::log_message(
							$resolved_booking_id,
							$found_user_id,
							'sms',
							$sender_phone,
							null,
							$body,
							self::EVENT_INBOUND_SMS,
							self::STATUS_RECEIVED,
							$metadata
						);

						// Also resolve the original message that was pending selection
						$pending_msg_id = $prompt_meta['pending_message_id'] ?? null;
						if ( $pending_msg_id ) {
							MessageLoggerService::assign_message_to_booking(
								(int) $pending_msg_id,
								$resolved_booking_id,
								$found_user_id
							);
						}

						// Send confirmation SMS back to the user acknowledging the selection
						$auto_prompt       = ( 'no' !== get_option( 'snippen_sms_auto_disambiguate', 'yes' ) );
						$confirmation_sent = false;

						if ( $auto_prompt ) {
							$selected_booking = null;
							foreach ( $active_bookings as $b ) {
								if ( (int) $b->id === $resolved_booking_id ) {
									$selected_booking = $b;
									break;
								}
							}
							if ( ! $selected_booking ) {
								$selected_booking = self::get_booking_summary( $resolved_booking_id );
							}

							$confirmation_text = self::format_disambiguation_confirmation( $selected_booking );
							$conf_meta         = array(
								'direction'  => 'outbound',
								'booking_id' => $resolved_booking_id,
							);

							MessageLoggerService::log_message(
								$resolved_booking_id,
								$found_user_id,
								'sms',
								$sender_phone,
								null,
								$confirmation_text,
								self::EVENT_DISAMBIGUATION_CONFIRMATION,
								'queued',
								$conf_meta
							);
							$confirmation_sent = true;
						}

						return array(
							'logged_id'         => $logged_id,
							'status'            => self::STATUS_RECEIVED,
							'booking_id'        => $resolved_booking_id,
							'user_id'           => $found_user_id,
							'rule'              => 'disambiguation_selection',
							'prompt_sent'       => false,
							'confirmation_sent' => $confirmation_sent,
						);
					}
				}
			}
		}

		// -------------------------------------------------------------
		// REGEL 3: Nøyaktig 1 aktiv reservasjon
		// -------------------------------------------------------------
		if ( 1 === $booking_count ) {
			$single_booking = $active_bookings[0];
			$booking_id     = (int) $single_booking->id;
			$user_id        = ! empty( $single_booking->user_id ) ? (int) $single_booking->user_id : $found_user_id;

			$metadata['matched_rule'] = 'single_active_booking';
			$logged_id                = MessageLoggerService::log_message(
				$booking_id,
				$user_id,
				'sms',
				$sender_phone,
				null,
				$body,
				self::EVENT_INBOUND_SMS,
				self::STATUS_RECEIVED,
				$metadata
			);

			return array(
				'logged_id'         => $logged_id,
				'status'            => self::STATUS_RECEIVED,
				'booking_id'        => $booking_id,
				'user_id'           => $user_id,
				'rule'              => 'single_active_booking',
				'prompt_sent'       => false,
				'confirmation_sent' => false,
			);
		}

		// -------------------------------------------------------------
		// REGEL 4: Flere aktive reservasjoner (> 1)
		// -------------------------------------------------------------
		if ( $booking_count > 1 ) {
			$metadata['matched_rule']          = 'multiple_active_bookings';
			$metadata['candidate_booking_ids'] = wp_list_pluck( $active_bookings, 'id' );

			$logged_id = MessageLoggerService::log_message(
				null,
				$found_user_id,
				'sms',
				$sender_phone,
				null,
				$body,
				self::EVENT_INBOUND_SMS,
				self::STATUS_PENDING_SELECTION,
				$metadata
			);

			$auto_prompt = ( 'no' !== get_option( 'snippen_sms_auto_disambiguate', 'yes' ) );
			$prompt_sent = false;

			if ( $auto_prompt ) {
				$prompt_text   = self::format_disambiguation_prompt( $active_bookings );
				$candidate_ids = array_map( 'intval', wp_list_pluck( $active_bookings, 'id' ) );

				$prompt_meta = array(
					'direction'             => 'outbound',
					'candidate_booking_ids' => $candidate_ids,
					'pending_message_id'    => $logged_id,
				);

				// Enqueue outbound prompt to outbox
				MessageLoggerService::log_message(
					null,
					$found_user_id,
					'sms',
					$sender_phone,
					null,
					$prompt_text,
					self::EVENT_DISAMBIGUATION_PROMPT,
					'queued',
					$prompt_meta
				);
				$prompt_sent = true;
			}

			return array(
				'logged_id'         => $logged_id,
				'status'            => self::STATUS_PENDING_SELECTION,
				'booking_id'        => null,
				'user_id'           => $found_user_id,
				'rule'              => 'multiple_active_bookings',
				'prompt_sent'       => $prompt_sent,
				'confirmation_sent' => false,
			);
		}

		// -------------------------------------------------------------
		// REGEL 5: Registrert bruker, men INGEN aktive reservasjoner
		// -------------------------------------------------------------
		if ( null !== $found_user_id ) {
			$metadata['matched_rule'] = 'registered_user_no_booking';

			$logged_id = MessageLoggerService::log_message(
				null,
				$found_user_id,
				'sms',
				$sender_phone,
				null,
				$body,
				self::EVENT_INBOUND_SMS,
				self::STATUS_GENERAL_INQUIRY,
				$metadata
			);

			return array(
				'logged_id'         => $logged_id,
				'status'            => self::STATUS_GENERAL_INQUIRY,
				'booking_id'        => null,
				'user_id'           => $found_user_id,
				'rule'              => 'registered_user_no_booking',
				'prompt_sent'       => false,
				'confirmation_sent' => false,
			);
		}

		// -------------------------------------------------------------
		// REGEL 6: Ukjent avsender (ingen bruker, ingen bookinger)
		// -------------------------------------------------------------
		$metadata['matched_rule'] = 'unknown_sender';
		$logged_id                = MessageLoggerService::log_message(
			null,
			null,
			'sms',
			$sender_phone,
			null,
			$body,
			self::EVENT_INBOUND_SMS,
			self::STATUS_QUARANTINE,
			$metadata
		);

		return array(
			'logged_id'         => $logged_id,
			'status'            => self::STATUS_QUARANTINE,
			'booking_id'        => null,
			'user_id'           => null,
			'rule'              => 'unknown_sender',
			'prompt_sent'       => false,
			'confirmation_sent' => false,
		);
	}
}
