<?php
/**
 * REST API Endpoints for Snippen Yale Doorman Service Integration
 *
 * @package SnippenBooking\Api
 */

namespace SnippenBooking\Api;

/**
 * Class DoorLockApi
 */
class DoorLockApi {

	/**
	 * REST API Namespace
	 */
	const REST_NAMESPACE = 'snippen/v1';

	/**
	 * REST API Route Base
	 */
	const REST_BASE = 'door';

	/**
	 * Register REST API routes
	 */
	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Check if Doorman API feature is enabled
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return 'yes' === get_option( 'snippen_enable_doorman_api', 'no' );
	}

	/**
	 * Register routes with WordPress REST API
	 */
	public static function register_routes() {
		// If feature is disabled, do not register routes (results in 404 Not Found)
		if ( ! self::is_enabled() ) {
			return;
		}

		// GET /wp-json/snippen/v1/door/locks
		register_rest_route(
			self::REST_NAMESPACE,
			'/' . self::REST_BASE . '/locks',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_locks' ),
					'permission_callback' => array( __CLASS__, 'verify_token' ),
				),
			)
		);

		// GET /wp-json/snippen/v1/door/bookings
		register_rest_route(
			self::REST_NAMESPACE,
			'/' . self::REST_BASE . '/bookings',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_bookings' ),
					'permission_callback' => array( __CLASS__, 'verify_token' ),
					'args'                => array(
						'lock_id' => array(
							'required'          => false,
							'validate_callback' => function ( $param ) {
								return is_numeric( $param ) && (int) $param > 0;
							},
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		// PATCH /wp-json/snippen/v1/door/bookings/(?P<id>\d+)/code
		register_rest_route(
			self::REST_NAMESPACE,
			'/' . self::REST_BASE . '/bookings/(?P<id>\d+)/code',
			array(
				array(
					'methods'             => 'PATCH',
					'callback'            => array( __CLASS__, 'update_door_code' ),
					'permission_callback' => array( __CLASS__, 'verify_token' ),
					'args'                => array(
						'id'        => array(
							'required'          => true,
							'validate_callback' => function ( $param ) {
								return is_numeric( $param ) && (int) $param > 0;
							},
						),
						'door_code' => array(
							'required' => true,
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
		if ( ! self::is_enabled() ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'Doorman API er ikke aktivert.', 'snippen-booking' ),
				array( 'status' => 403 )
			);
		}

		$configured_token = defined( 'SNIPPEN_DOORMAN_API_TOKEN' )
			? constant( 'SNIPPEN_DOORMAN_API_TOKEN' )
			: get_option( 'snippen_doorman_api_token', '' );

		if ( empty( $configured_token ) ) {
			return new \WP_Error(
				'rest_unauthorized',
				__( 'Doorman API-token er ikke konfigurert.', 'snippen-booking' ),
				array( 'status' => 401 )
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
				'rest_unauthorized',
				__( 'Ugyldig eller manglende autorisasjonstoken.', 'snippen-booking' ),
				array( 'status' => 401 )
			);
		}

		return true;
	}

	/**
	 * Handle GET /wp-json/snippen/v1/door/locks
	 *
	 * Returns full list of active door locks and the venues they serve.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response object.
	 */
	public static function get_locks( \WP_REST_Request $request ) {
		$repo  = new \SnippenBooking\Database\Repository\DoorLockRepository();
		$locks = $repo->find_all_with_objects();

		return new \WP_REST_Response(
			array(
				'locks' => $locks,
			),
			200
		);
	}

	/**
	 * Handle GET /wp-json/snippen/v1/door/bookings
	 *
	 * Returns full list of active access periods for Yale Doorman service.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error Response object.
	 */
	public static function get_bookings( \WP_REST_Request $request ) {
		global $wpdb;

		$lock_repo = new \SnippenBooking\Database\Repository\DoorLockRepository();
		$lock_id   = $request->get_param( 'lock_id' );

		if ( ! empty( $lock_id ) ) {
			$lock_id = (int) $lock_id;
			$lock    = $lock_repo->find( $lock_id );
			if ( ! $lock ) {
				return new \WP_Error(
					'lock_not_found',
					__( 'Dørlås ble ikke funnet.', 'snippen-booking' ),
					array( 'status' => 404 )
				);
			}
		} else {
			$all_locks = $lock_repo->find_all();
			if ( count( $all_locks ) > 1 ) {
				return new \WP_Error(
					'missing_lock_id',
					__( "Parameteret 'lock_id' er påkrevd når det finnes flere låser registrert.", 'snippen-booking' ),
					array( 'status' => 400 )
				);
			}
			$lock_id = ! empty( $all_locks ) ? (int) $all_locks[0]->id : 0;
		}

		$expose_hours   = intval( get_option( 'snippen_doorman_expose_hours_before', 168 ) );
		$buffer_minutes = intval( get_option( 'snippen_doorman_buffer_minutes_before', 30 ) );
		$grace_minutes  = intval( get_option( 'snippen_doorman_grace_minutes_after', 120 ) );

		$configured_statuses = get_option( 'snippen_doorman_booking_statuses', 'confirmed' );
		if ( is_array( $configured_statuses ) ) {
			$statuses = $configured_statuses;
		} else {
			$statuses = array_filter( array_map( 'trim', explode( ',', (string) $configured_statuses ) ) );
		}
		if ( empty( $statuses ) ) {
			$statuses = array( 'confirmed' );
		}

		$table_bookings = $wpdb->prefix . 'snippen_bookings';
		$table_bb       = $wpdb->prefix . 'snippen_booking_booking_blocks';
		$table_blocks   = $wpdb->prefix . 'snippen_booking_blocks';
		$table_slots    = $wpdb->prefix . 'snippen_time_slots';
		$table_bbo      = $wpdb->prefix . 'snippen_booking_booking_objects';
		$table_objects  = $wpdb->prefix . 'snippen_booking_objects';

		$status_placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		$query_params        = $statuses;

		$lock_filter_sql = '';
		if ( $lock_id > 0 ) {
			$all_active_locks   = $lock_repo->find_all();
			$has_multiple_locks = count( $all_active_locks ) > 1;

			if ( $has_multiple_locks ) {
				$lock_filter_sql = " AND (
					b.id IN (
						SELECT bbo.booking_id FROM {$table_bbo} bbo
						INNER JOIN {$table_objects} bo ON bbo.booking_object_id = bo.id
						WHERE bo.door_lock_id = %d AND bo.deleted_at IS NULL
					)
				)";
				$query_params[]  = $lock_id;
			} else {
				// Single lock fallback allows bookings with no object link (e.g. legacy test helpers) to match
				$lock_filter_sql = " AND (
					b.id IN (
						SELECT bbo.booking_id FROM {$table_bbo} bbo
						INNER JOIN {$table_objects} bo ON bbo.booking_object_id = bo.id
						WHERE bo.door_lock_id = %d AND bo.deleted_at IS NULL
					)
					OR NOT EXISTS (SELECT 1 FROM {$table_bbo} bbo_check WHERE bbo_check.booking_id = b.id)
				)";
				$query_params[]  = $lock_id;
			}
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$query = $wpdb->prepare(
			"SELECT b.id, b.user_id, b.customer_phone, b.customer_email, b.booking_date, b.status, 
			        b.door_code, b.booking_type, b.booking_snapshot,
			        COALESCE(MIN(s.start_time), ts.start_time, '00:00:00') as block_start,
			        COALESCE(MAX(s.end_time), ts.end_time, '23:59:59') as block_end
			 FROM {$table_bookings} b
			 LEFT JOIN {$table_bb} bb ON b.id = bb.booking_id
			 LEFT JOIN {$table_blocks} s ON bb.booking_block_id = s.id
			 LEFT JOIN {$table_slots} ts ON b.slot_id = ts.id
			 WHERE b.deleted_at IS NULL AND b.status IN ({$status_placeholders})
			 {$lock_filter_sql}
			 GROUP BY b.id
			 ORDER BY b.booking_date ASC, block_start ASC, b.id ASC",
			...$query_params
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$raw_bookings = $wpdb->get_results( $query );
		if ( ! is_array( $raw_bookings ) ) {
			$raw_bookings = array();
		}

		// Calculate precise real start and end timestamps for each booking
		$normalized = array();
		foreach ( $raw_bookings as $b ) {
			$start_time = $b->block_start ?: '00:00:00';
			$end_time   = $b->block_end ?: '23:59:59';

			if ( ! empty( $b->booking_snapshot ) ) {
				$snap = json_decode( $b->booking_snapshot, true );
				if ( is_array( $snap ) ) {
					if ( ! empty( $snap['start_time'] ) ) {
						$start_time = $snap['start_time'];
					}
					if ( ! empty( $snap['end_time'] ) ) {
						$end_time = $snap['end_time'];
					}
				}
			}

			$start_ts = strtotime( $b->booking_date . ' ' . $start_time );
			if ( $end_time < $start_time ) {
				$end_ts = strtotime( date( 'Y-m-d', strtotime( $b->booking_date . ' +1 day' ) ) . ' ' . $end_time );
			} else {
				$end_ts = strtotime( $b->booking_date . ' ' . $end_time );
			}

			// Tenant grouping key
			$tenant_key = '';
			if ( ! empty( $b->user_id ) && (int) $b->user_id > 0 ) {
				$tenant_key = 'user_' . (int) $b->user_id;
			} elseif ( ! empty( $b->customer_phone ) ) {
				$clean_phone = preg_replace( '/[^0-9]/', '', (string) $b->customer_phone );
				$tenant_key  = 'phone_' . $clean_phone;
			} else {
				$tenant_key = 'email_' . strtolower( trim( (string) $b->customer_email ) );
			}

			$normalized[] = array(
				'id'           => (int) $b->id,
				'booking_date' => $b->booking_date,
				'door_code'    => $b->door_code,
				'tenant_key'   => $tenant_key,
				'real_start'   => $start_ts,
				'real_end'     => $end_ts,
			);
		}

		// Group adjacent / consecutive bookings per tenant
		$grouped_chains = self::build_contiguous_chains( $normalized );

		// Filter groups based on expose horizon and grace period
		$now_ts         = time();
		$expose_seconds = $expose_hours * 3600;
		$buffer_seconds = $buffer_minutes * 60;
		$grace_seconds  = $grace_minutes * 60;

		$response_bookings = array();
		foreach ( $grouped_chains as $group ) {
			$calc_start = $group['group_start'] - $buffer_seconds;
			$calc_end   = $group['group_end'] + $grace_seconds;

			$expose_limit = $group['group_start'] - $expose_seconds;

			// Must be within expose horizon and not expired beyond grace period
			if ( $now_ts >= $expose_limit && $now_ts <= $calc_end ) {
				$response_bookings[] = array(
					'id'         => $group['primary_id'],
					'start_time' => gmdate( 'Y-m-d\TH:i:s\Z', $calc_start ),
					'end_time'   => gmdate( 'Y-m-d\TH:i:s\Z', $calc_end ),
					'door_code'  => ! empty( $group['door_code'] ) ? (string) $group['door_code'] : null,
				);
			}
		}

		return rest_ensure_response( array( 'bookings' => $response_bookings ) );
	}

	/**
	 * Build contiguous / adjacent booking chains per tenant.
	 *
	 * Two bookings for the same tenant are chained if they are on the same calendar date
	 * or on consecutive calendar days (or start when/before previous ended).
	 *
	 * @param array $bookings Normalized booking items.
	 * @return array Grouped chains.
	 */
	public static function build_contiguous_chains( array $bookings ) {
		// Group by tenant
		$by_tenant = array();
		foreach ( $bookings as $b ) {
			$by_tenant[ $b['tenant_key'] ][] = $b;
		}

		$all_chains = array();

		foreach ( $by_tenant as $tenant_bookings ) {
			// Sort chronologically
			usort(
				$tenant_bookings,
				function ( $a, $b ) {
					if ( $a['real_start'] === $b['real_start'] ) {
						return $a['id'] <=> $b['id'];
					}
					return $a['real_start'] <=> $b['real_start'];
				}
			);

			$current_chain = null;

			foreach ( $tenant_bookings as $item ) {
				if ( null === $current_chain ) {
					$current_chain = array(
						'primary_id'  => $item['id'],
						'booking_ids' => array( $item['id'] ),
						'group_start' => $item['real_start'],
						'group_end'   => $item['real_end'],
						'door_code'   => $item['door_code'],
						'last_date'   => $item['booking_date'],
					);
					continue;
				}

				// Check if item is adjacent to the current chain (same date or consecutive calendar days)
				$prev_date_ts      = strtotime( $current_chain['last_date'] );
				$curr_date_ts      = strtotime( $item['booking_date'] );
				$booking_days_diff = ( $curr_date_ts - $prev_date_ts ) / 86400;

				$chain_end_date_ts  = strtotime( date( 'Y-m-d', $current_chain['group_end'] ) );
				$item_start_date_ts = strtotime( date( 'Y-m-d', $item['real_start'] ) );
				$end_to_start_days  = ( $item_start_date_ts - $chain_end_date_ts ) / 86400;

				$is_adjacent = false;

				// Same calendar date, or consecutive calendar day (<= 1 day apart), or overlapping in time
				if ( ( $booking_days_diff >= 0 && $booking_days_diff <= 1.05 ) || ( $end_to_start_days >= 0 && $end_to_start_days <= 1.05 ) ) {
					$is_adjacent = true;
				} elseif ( $item['real_start'] <= ( $current_chain['group_end'] + 3600 ) ) {
					$is_adjacent = true;
				}

				if ( $is_adjacent ) {
					$current_chain['booking_ids'][] = $item['id'];
					$current_chain['group_start']   = min( $current_chain['group_start'], $item['real_start'] );
					$current_chain['group_end']     = max( $current_chain['group_end'], $item['real_end'] );
					$current_chain['last_date']     = ( $item['booking_date'] > $current_chain['last_date'] ) ? $item['booking_date'] : $current_chain['last_date'];
					if ( empty( $current_chain['door_code'] ) && ! empty( $item['door_code'] ) ) {
						$current_chain['door_code'] = $item['door_code'];
					}
				} else {
					$all_chains[]  = $current_chain;
					$current_chain = array(
						'primary_id'  => $item['id'],
						'booking_ids' => array( $item['id'] ),
						'group_start' => $item['real_start'],
						'group_end'   => $item['real_end'],
						'door_code'   => $item['door_code'],
						'last_date'   => $item['booking_date'],
					);
				}
			}

			if ( null !== $current_chain ) {
				$all_chains[] = $current_chain;
			}
		}

		// Sort all chains by group_start
		usort(
			$all_chains,
			function ( $a, $b ) {
				return $a['group_start'] <=> $b['group_start'];
			}
		);

		return $all_chains;
	}

	/**
	 * Find all adjacent booking IDs in the same chain as a given booking ID.
	 *
	 * @param int $booking_id Booking ID.
	 * @return array Array of booking IDs.
	 */
	public static function find_adjacent_booking_ids( $booking_id ) {
		global $wpdb;
		$table_bookings = $wpdb->prefix . 'snippen_bookings';

		$target = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, user_id, customer_phone, customer_email, booking_date, status
				 FROM {$table_bookings}
				 WHERE id = %d AND deleted_at IS NULL",
				$booking_id
			)
		);

		if ( ! $target ) {
			return array( $booking_id );
		}

		// Build tenant condition
		if ( ! empty( $target->user_id ) && (int) $target->user_id > 0 ) {
			$where_sql = $wpdb->prepare( 'user_id = %d', $target->user_id );
		} elseif ( ! empty( $target->customer_phone ) ) {
			$clean_phone = preg_replace( '/[^0-9]/', '', (string) $target->customer_phone );
			$where_sql   = $wpdb->prepare( "REPLACE(REPLACE(REPLACE(customer_phone, ' ', ''), '-', ''), '+', '') LIKE %s", '%' . $clean_phone . '%' );
		} else {
			$where_sql = $wpdb->prepare( 'customer_email = %s', $target->customer_email );
		}

		$table_bb     = $wpdb->prefix . 'snippen_booking_booking_blocks';
		$table_blocks = $wpdb->prefix . 'snippen_booking_blocks';
		$table_slots  = $wpdb->prefix . 'snippen_time_slots';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			"SELECT b.id, b.user_id, b.customer_phone, b.customer_email, b.booking_date, b.status, 
			        b.door_code, b.booking_type, b.booking_snapshot,
			        COALESCE(MIN(s.start_time), ts.start_time, '00:00:00') as block_start,
			        COALESCE(MAX(s.end_time), ts.end_time, '23:59:59') as block_end
			 FROM {$table_bookings} b
			 LEFT JOIN {$table_bb} bb ON b.id = bb.booking_id
			 LEFT JOIN {$table_blocks} s ON bb.booking_block_id = s.id
			 LEFT JOIN {$table_slots} ts ON b.slot_id = ts.id
			 WHERE b.deleted_at IS NULL AND {$where_sql}
			 GROUP BY b.id
			 ORDER BY b.booking_date ASC, block_start ASC, b.id ASC"
		);

		if ( ! is_array( $rows ) || count( $rows ) <= 1 ) {
			return array( $booking_id );
		}

		$normalized = array();
		foreach ( $rows as $b ) {
			$start_time = $b->block_start ?: '00:00:00';
			$end_time   = $b->block_end ?: '23:59:59';
			$start_ts   = strtotime( $b->booking_date . ' ' . $start_time );
			$end_ts     = ( $end_time < $start_time )
				? strtotime( date( 'Y-m-d', strtotime( $b->booking_date . ' +1 day' ) ) . ' ' . $end_time )
				: strtotime( $b->booking_date . ' ' . $end_time );

			$normalized[] = array(
				'id'           => (int) $b->id,
				'booking_date' => $b->booking_date,
				'door_code'    => $b->door_code,
				'tenant_key'   => 'tenant',
				'real_start'   => $start_ts,
				'real_end'     => $end_ts,
			);
		}

		$chains = self::build_contiguous_chains( $normalized );
		foreach ( $chains as $chain ) {
			if ( in_array( (int) $booking_id, $chain['booking_ids'], true ) ) {
				return $chain['booking_ids'];
			}
		}

		return array( $booking_id );
	}

	/**
	 * Handle PATCH /wp-json/snippen/v1/door/bookings/(?P<id>\d+)/code
	 *
	 * Updates the door code on the specified booking and any adjacent bookings in its chain.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error Response object.
	 */
	public static function update_door_code( \WP_REST_Request $request ) {
		$id        = (int) $request->get_param( 'id' );
		$door_code = $request->get_param( 'door_code' );

		// Validate door_code: must be a 4-6 numeric digit string, or empty string to clear code.
		if ( ! is_string( $door_code ) || ( '' !== $door_code && ! preg_match( '/^[0-9]{4,6}$/', $door_code ) ) ) {
			return new \WP_Error(
				'rest_invalid_param',
				__( 'Ugyldig dørkode. Koden må bestå av 4–6 numeriske sifre.', 'snippen-booking' ),
				array( 'status' => 400 )
			);
		}

		$result = \SnippenBooking\Service\DoorCodeService::apply_door_code( $id, $door_code, true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'id'      => $id,
			)
		);
	}
}
