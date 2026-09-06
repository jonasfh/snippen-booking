<?php
/**
 * REST API Endpoints for Snippen SMS Gateway Synchronization
 *
 * @package SnippenBooking\Api
 */

namespace SnippenBooking\Api;

use SnippenBooking\Service\Notification\MessageLoggerService;
use SnippenBooking\Service\Sms\SmsInboxResolverService;
use SnippenBooking\Helper\PhoneHelper;

/**
 * Class SmsGatewayApi
 */
class SmsGatewayApi {

	/**
	 * REST API Namespace
	 */
	const REST_NAMESPACE = 'snippen/v1';

	/**
	 * REST API Route Base
	 */
	const REST_BASE = 'sms';

	/**
	 * Register REST API routes
	 */
	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register routes with WordPress REST API
	 */
	public static function register_routes() {
		// GET /wp-json/snippen/v1/sms/outbox
		register_rest_route(
			self::REST_NAMESPACE,
			'/' . self::REST_BASE . '/outbox',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_outbox' ),
					'permission_callback' => array( __CLASS__, 'verify_token' ),
					'args'                => array(
						'limit' => array(
							'default'           => 50,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		// POST /wp-json/snippen/v1/sms/outbox/status
		register_rest_route(
			self::REST_NAMESPACE,
			'/' . self::REST_BASE . '/outbox/status',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'update_outbox_status' ),
					'permission_callback' => array( __CLASS__, 'verify_token' ),
				),
			)
		);

		// POST /wp-json/snippen/v1/sms/inbox
		register_rest_route(
			self::REST_NAMESPACE,
			'/' . self::REST_BASE . '/inbox',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'report_inbox' ),
					'permission_callback' => array( __CLASS__, 'verify_token' ),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_inbox' ),
					'permission_callback' => array( __CLASS__, 'verify_token' ),
					'args'                => array(
						'status'     => array(
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'booking_id' => array(
							'required'          => false,
							'sanitize_callback' => 'absint',
						),
						'user_id'    => array(
							'required'          => false,
							'sanitize_callback' => 'absint',
						),
						'phone'      => array(
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'search'     => array(
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'limit'      => array(
							'default'           => 50,
							'sanitize_callback' => 'absint',
						),
						'offset'     => array(
							'default'           => 0,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		// GET /wp-json/snippen/v1/sms/bookings
		register_rest_route(
			self::REST_NAMESPACE,
			'/' . self::REST_BASE . '/bookings',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_bookings' ),
					'permission_callback' => array( __CLASS__, 'verify_token' ),
					'args'                => array(
						'phone' => array(
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);
	}

	/**
	 * Verify API token from Authorization Bearer header or X-API-Key header.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return true|\WP_Error True if valid, WP_Error otherwise.
	 */
	public static function verify_token( \WP_REST_Request $request ) {
		$configured_token = get_option( 'snippen_sms_service_api_token' );
		if ( empty( $configured_token ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'SMS Service API-token er ikke konfigurert i systemet.', 'snippen-booking' ),
				array( 'status' => 403 )
			);
		}

		$auth_header = $request->get_header( 'authorization' );
		$token       = '';

		if ( ! empty( $auth_header ) && preg_match( '/Bearer\s+(.*)$/i', $auth_header, $matches ) ) {
			$token = trim( $matches[1] );
		}

		if ( empty( $token ) ) {
			$token = $request->get_header( 'x-api-key' );
		}

		if ( empty( $token ) || ! hash_equals( (string) $configured_token, (string) $token ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'Ugyldig eller manglende autorisasjonstoken.', 'snippen-booking' ),
				array( 'status' => 401 )
			);
		}

		return true;
	}

	/**
	 * Handle GET /outbox: Fetch pending outgoing SMS messages.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response object.
	 */
	public static function get_outbox( \WP_REST_Request $request ) {
		$limit    = $request->get_param( 'limit' ) ?: 50;
		$messages = MessageLoggerService::get_pending_outbox( (int) $limit );
		$sender   = get_option( 'snippen_sms_service_sender', 'Snippen' ) ?: 'Snippen';

		$formatted = array();
		foreach ( $messages as $msg ) {
			$formatted[] = array(
				'id'          => (int) $msg->id,
				'external_id' => (string) $msg->id,
				'recipient'   => $msg->recipient,
				'body'        => $msg->message,
				'sender'      => $sender,
				'booking_id'  => $msg->booking_id ? (string) $msg->booking_id : null,
				'event_type'  => $msg->event_type,
				'created_at'  => mysql_to_rfc3339( $msg->created_at ),
			);
		}

		return rest_ensure_response( array( 'messages' => $formatted ) );
	}

	/**
	 * Handle POST /outbox/status: Receive status updates for outbound SMS.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response object.
	 */
	public static function update_outbox_status( \WP_REST_Request $request ) {
		$body     = $request->get_json_params();
		$statuses = array();

		if ( is_array( $body ) ) {
			if ( isset( $body['statuses'] ) && is_array( $body['statuses'] ) ) {
				$statuses = $body['statuses'];
			} elseif ( isset( $body[0] ) && is_array( $body[0] ) ) {
				$statuses = $body;
			}
		}

		$updated_count = 0;
		foreach ( $statuses as $item ) {
			$raw_id = $item['external_id'] ?? $item['id'] ?? null;
			if ( null === $raw_id ) {
				continue;
			}

			$message_id = (int) $raw_id;
			$status     = sanitize_text_field( $item['status'] ?? 'sent' );
			$extra      = array();

			if ( isset( $item['gateway_id'] ) ) {
				$extra['gateway_id'] = $item['gateway_id'];
			}
			if ( isset( $item['modem_message_id'] ) ) {
				$extra['modem_message_id'] = $item['modem_message_id'];
			}
			if ( isset( $item['error_message'] ) && ! empty( $item['error_message'] ) ) {
				$extra['error_message'] = $item['error_message'];
			}

			if ( MessageLoggerService::update_message_status( $message_id, $status, $extra ) ) {
				++$updated_count;
			}
		}

		return rest_ensure_response(
			array(
				'success'       => true,
				'updated_count' => $updated_count,
			)
		);
	}

	/**
	 * Handle POST /inbox: Receive reported inbound SMS messages.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response object.
	 */
	public static function report_inbox( \WP_REST_Request $request ) {
		$body     = $request->get_json_params();
		$messages = array();

		if ( is_array( $body ) ) {
			if ( isset( $body['messages'] ) && is_array( $body['messages'] ) ) {
				$messages = $body['messages'];
			} elseif ( isset( $body[0] ) && is_array( $body[0] ) ) {
				$messages = $body;
			}
		}

		$processed_ids = array();
		$results       = array();

		foreach ( $messages as $item ) {
			$sender     = sanitize_text_field( $item['sender'] ?? '' );
			$text       = sanitize_textarea_field( $item['body'] ?? $item['message'] ?? '' );
			$gateway_id = isset( $item['gateway_id'] ) ? (int) $item['gateway_id'] : null;
			$modem_id   = isset( $item['modem_message_id'] ) ? sanitize_text_field( $item['modem_message_id'] ) : null;
			$recv_at    = isset( $item['received_at'] ) ? sanitize_text_field( $item['received_at'] ) : null;

			if ( empty( $sender ) || empty( $text ) ) {
				continue;
			}

			// Run through rule resolution engine
			$resolution = SmsInboxResolverService::resolve_message(
				$sender,
				$text,
				$gateway_id,
				$modem_id,
				$recv_at
			);

			if ( false !== $resolution['logged_id'] && $gateway_id !== null ) {
				$processed_ids[] = $gateway_id;
			}

			$results[] = array(
				'gateway_id'        => $gateway_id,
				'message_id'        => $resolution['logged_id'],
				'status'            => $resolution['status'],
				'booking_id'        => $resolution['booking_id'],
				'user_id'           => $resolution['user_id'],
				'rule'              => $resolution['rule'],
				'prompt_sent'       => $resolution['prompt_sent'],
				'confirmation_sent' => $resolution['confirmation_sent'] ?? false,
			);
		}

		return rest_ensure_response(
			array(
				'success'       => true,
				'processed_ids' => $processed_ids,
				'results'       => $results,
			)
		);
	}

	/**
	 * Handle GET /inbox: Query and filter received inbound SMS messages.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response object.
	 */
	public static function get_inbox( \WP_REST_Request $request ) {
		$args = array(
			'status'     => $request->get_param( 'status' ),
			'booking_id' => $request->get_param( 'booking_id' ),
			'user_id'    => $request->get_param( 'user_id' ),
			'phone'      => $request->get_param( 'phone' ),
			'search'     => $request->get_param( 'search' ),
			'limit'      => $request->get_param( 'limit' ) ?: 50,
			'offset'     => $request->get_param( 'offset' ) ?: 0,
		);

		$messages = MessageLoggerService::get_inbound_messages( $args );
		$total    = MessageLoggerService::count_inbound_messages( $args );

		$formatted = array();
		foreach ( $messages as $msg ) {
			$meta        = ! empty( $msg->metadata ) ? json_decode( $msg->metadata, true ) : array();
			$formatted[] = array(
				'id'               => (int) $msg->id,
				'booking_id'       => $msg->booking_id ? (int) $msg->booking_id : null,
				'user_id'          => $msg->user_id ? (int) $msg->user_id : null,
				'sender'           => $msg->recipient,
				'body'             => $msg->message,
				'status'           => $msg->status,
				'gateway_id'       => $meta['gateway_id'] ?? null,
				'modem_message_id' => $meta['modem_message_id'] ?? null,
				'matched_rule'     => $meta['matched_rule'] ?? null,
				'created_at'       => mysql_to_rfc3339( $msg->created_at ),
			);
		}

		return rest_ensure_response(
			array(
				'messages' => $formatted,
				'total'    => $total,
				'limit'    => (int) $args['limit'],
				'offset'   => (int) $args['offset'],
			)
		);
	}

	/**
	 * Handle GET /bookings: Lookup bookings for a given phone number.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response object.
	 */
	public static function get_bookings( \WP_REST_Request $request ) {
		global $wpdb;

		$phone = $request->get_param( 'phone' );
		if ( empty( $phone ) ) {
			return rest_ensure_response( array( 'bookings' => array() ) );
		}

		$table_bookings = $wpdb->prefix . 'snippen_bookings';
		$table_junction = $wpdb->prefix . 'snippen_bookings_booking_objects';
		$table_objects  = $wpdb->prefix . 'snippen_booking_objects';
		$table_slots    = $wpdb->prefix . 'snippen_time_slots';

		$phone_clean   = preg_replace( '/[^0-9]/', '', (string) $phone );
		$phone_norway  = ltrim( $phone_clean, '47' );
		$search_phones = array_unique(
			array_filter(
				array(
					$phone,
					'+' . $phone_clean,
					$phone_clean,
					$phone_norway,
					'+47' . $phone_norway,
				)
			)
		);

		$placeholders = implode( ',', array_fill( 0, count( $search_phones ), '%s' ) );

		$query = $wpdb->prepare(
			"SELECT b.*, s.start_time as slot_start, s.end_time as slot_end 
			 FROM {$table_bookings} b 
			 LEFT JOIN {$table_slots} s ON b.slot_id = s.id 
			 WHERE b.customer_phone IN ({$placeholders}) 
			   AND b.deleted_at IS NULL 
			 ORDER BY b.booking_date DESC, b.id DESC 
			 LIMIT 20", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			...$search_phones
		);

		$rows = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! is_array( $rows ) ) {
			$rows = array();
		}

		$bookings = array();
		foreach ( $rows as $b ) {
			$object_names = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT o.name 
					 FROM {$table_junction} bo 
					 JOIN {$table_objects} o ON bo.booking_object_id = o.id 
					 WHERE bo.booking_id = %d",
					$b->id
				)
			);

			$resource_name = implode( ', ', $object_names );
			$start_dt      = $b->booking_date . 'T' . ( $b->slot_start ?: '00:00:00' ) . 'Z';
			$end_dt        = $b->booking_date . 'T' . ( $b->slot_end ?: '23:59:59' ) . 'Z';

			$bookings[] = array(

				'id'             => (string) $b->id,
				'booking_id'     => (string) $b->id,
				'customer_name'  => $b->customer_name,
				'customer_phone' => $b->customer_phone,
				'start_time'     => $start_dt,
				'end_time'       => $end_dt,
				'resource_name'  => $resource_name,
				'status'         => $b->status,
			);
		}

		return rest_ensure_response( array( 'bookings' => $bookings ) );
	}
}
