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
						'lock_id'              => array(
							'required'          => false,
							'validate_callback' => function ( $param ) {
								return is_numeric( $param ) && (int) $param > 0;
							},
							'sanitize_callback' => 'absint',
						),
						'include_expired_days' => array(
							'required'          => false,
							'default'           => 0,
							'validate_callback' => function ( $param ) {
								return is_numeric( $param ) && (int) $param >= 0;
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

		// POST and GET /wp-json/snippen/v1/door/status
		register_rest_route(
			self::REST_NAMESPACE,
			'/' . self::REST_BASE . '/status',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'receive_status' ),
					'permission_callback' => array( __CLASS__, 'verify_token' ),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_status' ),
					'permission_callback' => array( __CLASS__, 'verify_token_or_admin' ),
					'args'                => array(
						'service_id' => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_key',
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

		$api_keys     = DoormanApiKeyApi::get_keys();
		$single_token = get_option( 'snippen_doorman_api_token', '' );
		$has_const    = defined( 'SNIPPEN_DOORMAN_API_TOKEN' ) && ! empty( constant( 'SNIPPEN_DOORMAN_API_TOKEN' ) );

		if ( ! $has_const && empty( $api_keys ) && empty( $single_token ) ) {
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

		if ( empty( $token ) ) {
			return new \WP_Error(
				'rest_unauthorized',
				__( 'Ugyldig eller manglende autorisasjonstoken.', 'snippen-booking' ),
				array( 'status' => 401 )
			);
		}

		// 1. Check SNIPPEN_DOORMAN_API_TOKEN constant
		if ( $has_const && hash_equals( (string) constant( 'SNIPPEN_DOORMAN_API_TOKEN' ), (string) $token ) ) {
			return true;
		}

		// 2. Check hashed keys in snippen_doorman_api_keys
		$incoming_hash = DoormanApiKeyApi::hash_token( (string) $token );
		if ( is_array( $api_keys ) ) {
			foreach ( $api_keys as $key_data ) {
				if ( ! is_array( $key_data ) ) {
					continue;
				}
				if ( ! empty( $key_data['token_hash'] ) && hash_equals( (string) $key_data['token_hash'], $incoming_hash ) ) {
					return true;
				}
				// Support legacy unhashed token in array if any
				if ( ! empty( $key_data['token'] ) && hash_equals( (string) $key_data['token'], (string) $token ) ) {
					return true;
				}
			}
		}

		// 3. Check legacy single token option
		if ( ! empty( $single_token ) && hash_equals( (string) $single_token, (string) $token ) ) {
			return true;
		}

		return new \WP_Error(
			'rest_unauthorized',
			__( 'Ugyldig eller manglende autorisasjonstoken.', 'snippen-booking' ),
			array( 'status' => 401 )
		);
	}

	/**
	 * Get all configured Doorman API tokens.
	 *
	 * Checks SNIPPEN_DOORMAN_API_TOKEN constant, snippen_doorman_api_keys option array,
	 * and snippen_doorman_api_token legacy single token option.
	 *
	 * @return array<string> List of valid API tokens.
	 */
	public static function get_configured_tokens(): array {
		$tokens = array();

		if ( defined( 'SNIPPEN_DOORMAN_API_TOKEN' ) && ! empty( constant( 'SNIPPEN_DOORMAN_API_TOKEN' ) ) ) {
			$tokens[] = (string) constant( 'SNIPPEN_DOORMAN_API_TOKEN' );
		}

		$api_keys = DoormanApiKeyApi::get_keys();
		if ( is_array( $api_keys ) ) {
			foreach ( $api_keys as $key_data ) {
				if ( is_array( $key_data ) ) {
					if ( ! empty( $key_data['token_hash'] ) ) {
						$tokens[] = (string) $key_data['token_hash'];
					} elseif ( ! empty( $key_data['token'] ) ) {
						$tokens[] = (string) $key_data['token'];
					}
				}
			}
		}

		$single_token = get_option( 'snippen_doorman_api_token', '' );
		if ( ! empty( $single_token ) && ! in_array( (string) $single_token, $tokens, true ) ) {
			$tokens[] = (string) $single_token;
		}

		return array_values( array_unique( $tokens ) );
	}

	/**
	 * Verify token from header or allow authenticated admin users.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return true|\WP_Error True if valid, WP_Error otherwise.
	 */
	public static function verify_token_or_admin( \WP_REST_Request $request ) {
		if ( current_user_can( 'manage_snippen_bookings' ) || current_user_can( 'manage_options' ) ) {
			return true;
		}
		return self::verify_token( $request );
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

		$include_expired_days = absint( $request->get_param( 'include_expired_days' ) );
		$expose_hours         = intval( get_option( 'snippen_doorman_expose_hours_before', 168 ) );
		$buffer_minutes       = intval( get_option( 'snippen_doorman_buffer_minutes_before', 30 ) );
		$grace_minutes        = intval( get_option( 'snippen_doorman_grace_minutes_after', 120 ) );

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
			        b.door_code, b.door_code_updated_at, b.modified_at, b.booking_type, b.booking_snapshot,
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

		// Preload booking objects and locks
		$raw_booking_ids    = wp_list_pluck( $raw_bookings, 'id' );
		$objects_by_booking = array();
		if ( ! empty( $raw_booking_ids ) ) {
			$id_placeholders = implode( ',', array_fill( 0, count( $raw_booking_ids ), '%d' ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$obj_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT bbo.booking_id, bo.id as object_id, bo.name as object_name, bo.door_lock_id
					 FROM {$table_bbo} bbo
					 INNER JOIN {$table_objects} bo ON bbo.booking_object_id = bo.id
					 WHERE bbo.booking_id IN ({$id_placeholders}) AND bo.deleted_at IS NULL
					 ORDER BY bo.id ASC",
					...$raw_booking_ids
				)
			);
			if ( is_array( $obj_rows ) ) {
				foreach ( $obj_rows as $orow ) {
					$objects_by_booking[ (int) $orow->booking_id ][] = $orow;
				}
			}
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

			$timestamps = self::parse_booking_timestamps( $b->booking_date, $start_time, $end_time );
			$start_ts   = $timestamps['start_ts'];
			$end_ts     = $timestamps['end_ts'];

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

			$b_objs  = array();
			$b_locks = array();
			if ( isset( $objects_by_booking[ (int) $b->id ] ) ) {
				foreach ( $objects_by_booking[ (int) $b->id ] as $orow ) {
					$b_objs[] = array(
						'id'   => (int) $orow->object_id,
						'name' => (string) $orow->object_name,
					);
					if ( ! empty( $orow->door_lock_id ) ) {
						$b_locks[] = (int) $orow->door_lock_id;
					}
				}
			}
			if ( empty( $b_locks ) && $lock_id > 0 ) {
				$b_locks[] = (int) $lock_id;
			}
			$b_locks = array_values( array_unique( $b_locks ) );

			$normalized[] = array(
				'id'                   => (int) $b->id,
				'booking_date'         => $b->booking_date,
				'door_code'            => $b->door_code,
				'door_code_updated_at' => $b->door_code_updated_at,
				'modified_at'          => $b->modified_at,
				'tenant_key'           => $tenant_key,
				'real_start'           => $start_ts,
				'real_end'             => $end_ts,
				'booking_objects'      => $b_objs,
				'lock_ids'             => $b_locks,
			);
		}

		// Group adjacent / consecutive bookings per tenant
		$grouped_chains = self::build_contiguous_chains( $normalized );

		// Filter groups based on expose horizon, expired horizon, and grace period
		$now_ts         = time();
		$expose_seconds = $expose_hours * 3600;
		$buffer_seconds = $buffer_minutes * 60;
		$grace_seconds  = $grace_minutes * 60;

		$min_end = ( $include_expired_days > 0 )
			? ( $now_ts - ( $include_expired_days * 86400 ) )
			: $now_ts;

		$response_bookings = array();
		foreach ( $grouped_chains as $group ) {
			$calc_start = $group['group_start'] - $buffer_seconds;
			$calc_end   = $group['group_end'] + $grace_seconds;

			$expose_limit = $group['group_start'] - $expose_seconds;

			// Must be within expose horizon and not expired beyond grace / expired horizon
			if ( $now_ts >= $expose_limit && $calc_end >= $min_end ) {
				$door_code_updated_at_iso = ( ! empty( $group['door_code'] ) && ! empty( $group['door_code_updated_at'] ) )
					? gmdate( 'Y-m-d\TH:i:s\Z', strtotime( $group['door_code_updated_at'] ) )
					: null;

				$response_bookings[] = array(
					'id'                   => (int) $group['primary_id'],
					'booking_ids'          => array_values( array_map( 'intval', $group['booking_ids'] ) ),
					'booking_objects'      => ! empty( $group['booking_objects'] ) ? array_values( $group['booking_objects'] ) : array(),
					'lock_ids'             => ! empty( $group['lock_ids'] ) ? array_values( array_map( 'intval', $group['lock_ids'] ) ) : array(),
					'start_time'           => gmdate( 'Y-m-d\TH:i:s\Z', $calc_start ),
					'end_time'             => gmdate( 'Y-m-d\TH:i:s\Z', $calc_end ),
					'door_code'            => ! empty( $group['door_code'] ) ? (string) $group['door_code'] : null,
					'door_code_updated_at' => $door_code_updated_at_iso,
				);
			}
		}

		return rest_ensure_response(
			array(
				'server_time' => gmdate( 'Y-m-d\TH:i:s\Z', $now_ts ),
				'bookings'    => $response_bookings,
			)
		);
	}

	/**
	 * Parse local booking date and times into UTC timestamps.
	 *
	 * Uses wp_timezone() to interpret the local date and start/end times in the
	 * WordPress site timezone, converting them to true Unix timestamps (UTC seconds since epoch).
	 *
	 * @param string $booking_date Y-m-d format.
	 * @param string $start_time   H:i:s or H:i format.
	 * @param string $end_time     H:i:s or H:i format.
	 * @return array{start_ts: int, end_ts: int}
	 */
	public static function parse_booking_timestamps( string $booking_date, string $start_time, string $end_time ): array {
		$tz       = wp_timezone();
		$start_dt = new \DateTime( $booking_date . ' ' . $start_time, $tz );
		$start_ts = $start_dt->getTimestamp();

		if ( $end_time < $start_time ) {
			$next_day = ( clone $start_dt )->modify( '+1 day' )->format( 'Y-m-d' );
			$end_dt   = new \DateTime( $next_day . ' ' . $end_time, $tz );
		} else {
			$end_dt = new \DateTime( $booking_date . ' ' . $end_time, $tz );
		}
		$end_ts = $end_dt->getTimestamp();

		return array(
			'start_ts' => $start_ts,
			'end_ts'   => $end_ts,
		);
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

		$get_code_ts = function ( $item ) {
			if ( empty( $item['door_code'] ) ) {
				return 0;
			}
			if ( ! empty( $item['door_code_updated_at'] ) ) {
				return strtotime( $item['door_code_updated_at'] );
			}
			if ( ! empty( $item['modified_at'] ) ) {
				return strtotime( $item['modified_at'] );
			}
			return 1;
		};

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
				$item_code_ts = $get_code_ts( $item );

				if ( null === $current_chain ) {
					$current_chain = array(
						'primary_id'           => $item['id'],
						'booking_ids'          => array( $item['id'] ),
						'group_start'          => $item['real_start'],
						'group_end'            => $item['real_end'],
						'door_code'            => ! empty( $item['door_code'] ) ? (string) $item['door_code'] : null,
						'door_code_updated_at' => ( ! empty( $item['door_code'] ) && ! empty( $item['door_code_updated_at'] ) ) ? $item['door_code_updated_at'] : null,
						'code_version_ts'      => $item_code_ts,
						'last_date'            => $item['booking_date'],
						'booking_objects'      => isset( $item['booking_objects'] ) ? $item['booking_objects'] : array(),
						'lock_ids'             => isset( $item['lock_ids'] ) ? $item['lock_ids'] : array(),
					);
					continue;
				}

				// Check if item is adjacent to the current chain (same date or consecutive calendar days)
				$prev_date_ts      = strtotime( $current_chain['last_date'] );
				$curr_date_ts      = strtotime( $item['booking_date'] );
				$booking_days_diff = ( $curr_date_ts - $prev_date_ts ) / 86400;

				$chain_end_date_ts  = strtotime( wp_date( 'Y-m-d', $current_chain['group_end'] ) );
				$item_start_date_ts = strtotime( wp_date( 'Y-m-d', $item['real_start'] ) );
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

					if ( ! empty( $item['booking_objects'] ) ) {
						$existing_ids = wp_list_pluck( $current_chain['booking_objects'], 'id' );
						foreach ( $item['booking_objects'] as $obj ) {
							if ( ! in_array( $obj['id'], $existing_ids, true ) ) {
								$current_chain['booking_objects'][] = $obj;
								$existing_ids[]                     = $obj['id'];
							}
						}
					}

					if ( ! empty( $item['lock_ids'] ) ) {
						$current_chain['lock_ids'] = array_values( array_unique( array_merge( $current_chain['lock_ids'], $item['lock_ids'] ) ) );
					}

					// The code with the newest door_code_updated_at (fallback modified_at) wins
					if ( $item_code_ts > $current_chain['code_version_ts'] ) {
						$current_chain['door_code']            = (string) $item['door_code'];
						$current_chain['door_code_updated_at'] = ! empty( $item['door_code_updated_at'] ) ? $item['door_code_updated_at'] : null;
						$current_chain['code_version_ts']      = $item_code_ts;
					}
				} else {
					$all_chains[]  = $current_chain;
					$current_chain = array(
						'primary_id'           => $item['id'],
						'booking_ids'          => array( $item['id'] ),
						'group_start'          => $item['real_start'],
						'group_end'            => $item['real_end'],
						'door_code'            => ! empty( $item['door_code'] ) ? (string) $item['door_code'] : null,
						'door_code_updated_at' => ( ! empty( $item['door_code'] ) && ! empty( $item['door_code_updated_at'] ) ) ? $item['door_code_updated_at'] : null,
						'code_version_ts'      => $item_code_ts,
						'last_date'            => $item['booking_date'],
						'booking_objects'      => isset( $item['booking_objects'] ) ? $item['booking_objects'] : array(),
						'lock_ids'             => isset( $item['lock_ids'] ) ? $item['lock_ids'] : array(),
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

		if ( ! $target || 'cancelled' === $target->status ) {
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
			        b.door_code, b.door_code_updated_at, b.modified_at, b.booking_type, b.booking_snapshot,
			        COALESCE(MIN(s.start_time), ts.start_time, '00:00:00') as block_start,
			        COALESCE(MAX(s.end_time), ts.end_time, '23:59:59') as block_end
			 FROM {$table_bookings} b
			 LEFT JOIN {$table_bb} bb ON b.id = bb.booking_id
			 LEFT JOIN {$table_blocks} s ON bb.booking_block_id = s.id
			 LEFT JOIN {$table_slots} ts ON b.slot_id = ts.id
			 WHERE b.deleted_at IS NULL AND b.status != 'cancelled' AND {$where_sql}
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

			$timestamps = self::parse_booking_timestamps( $b->booking_date, $start_time, $end_time );
			$start_ts   = $timestamps['start_ts'];
			$end_ts     = $timestamps['end_ts'];

			$normalized[] = array(
				'id'                   => (int) $b->id,
				'booking_date'         => $b->booking_date,
				'door_code'            => $b->door_code,
				'door_code_updated_at' => $b->door_code_updated_at,
				'modified_at'          => $b->modified_at,
				'tenant_key'           => 'tenant',
				'real_start'           => $start_ts,
				'real_end'             => $end_ts,
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

		global $wpdb;
		$table_bookings = $wpdb->prefix . 'snippen_bookings';
		$row            = $wpdb->get_row( $wpdb->prepare( "SELECT door_code, door_code_updated_at FROM {$table_bookings} WHERE id = %d", $id ) );

		$updated_ids = self::find_adjacent_booking_ids( $id );

		$door_code_updated_at_iso = ( $row && ! empty( $row->door_code ) && ! empty( $row->door_code_updated_at ) )
			? gmdate( 'Y-m-d\TH:i:s\Z', strtotime( $row->door_code_updated_at ) )
			: null;

		return rest_ensure_response(
			array(
				'success'              => true,
				'id'                   => $id,
				'updated_booking_ids'  => array_values( array_map( 'intval', $updated_ids ) ),
				'door_code'            => ( $row && ! empty( $row->door_code ) ) ? (string) $row->door_code : null,
				'door_code_updated_at' => $door_code_updated_at_iso,
			)
		);
	}

	/**
	 * Handle POST /wp-json/snippen/v1/door/status
	 *
	 * Receives complete status snapshot from a snippen-doorman-service node.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error Response object.
	 */
	public static function receive_status( \WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( empty( $params ) || ! is_array( $params ) ) {
			return new \WP_Error(
				'rest_invalid_json',
				__( 'Ugyldig eller manglende JSON i forespørselen.', 'snippen-booking' ),
				array( 'status' => 400 )
			);
		}

		// Extract and sanitize service_id
		$raw_service_id = $params['service_id'] ?? ( $params['service']['id'] ?? 'default' );
		$service_id     = sanitize_key( (string) $raw_service_id );
		if ( empty( $service_id ) ) {
			$service_id = 'default';
		}

		// Parse service metadata
		$service_meta = isset( $params['service'] ) && is_array( $params['service'] ) ? $params['service'] : array();
		$service_name = ! empty( $service_meta['name'] )
			? sanitize_text_field( $service_meta['name'] )
			: ( 'default' === $service_id ? __( 'Standard Doorman-tjeneste', 'snippen-booking' ) : ucfirst( str_replace( array( '-', '_' ), ' ', $service_id ) ) );

		$normalized_service = array(
			'id'          => $service_id,
			'name'        => $service_name,
			'version'     => sanitize_text_field( $service_meta['version'] ?? '' ),
			'hostname'    => sanitize_text_field( $service_meta['hostname'] ?? '' ),
			'ip_address'  => sanitize_text_field( $service_meta['ip_address'] ?? '' ),
			'mac_address' => sanitize_text_field( $service_meta['mac_address'] ?? '' ),
			'lock_ids'    => isset( $service_meta['lock_ids'] ) && is_array( $service_meta['lock_ids'] )
				? array_values( array_map( 'strval', $service_meta['lock_ids'] ) )
				: array(),
		);

		$locks      = isset( $params['locks'] ) && is_array( $params['locks'] ) ? $params['locks'] : array();
		$lock_count = count( $locks );
		if ( 0 === $lock_count && ! empty( $normalized_service['lock_ids'] ) ) {
			$lock_count = count( $normalized_service['lock_ids'] );
		}

		$min_battery = null;
		foreach ( $locks as $l ) {
			if ( isset( $l['battery'] ) && is_numeric( $l['battery'] ) ) {
				$b = (int) $l['battery'];
				if ( null === $min_battery || $b < $min_battery ) {
					$min_battery = $b;
				}
			}
		}

		$now_iso = gmdate( 'Y-m-d\TH:i:s\Z' );

		$params['service_id']  = $service_id;
		$params['service']     = $normalized_service;
		$params['received_at'] = $now_iso;

		// 1. Save per-service status
		update_option( "snippen_door_status_{$service_id}", $params );

		// 2. Update shared services registry
		$registry = get_option( 'snippen_door_services_registry', array() );
		if ( ! is_array( $registry ) ) {
			$registry = array();
		}

		$registry[ $service_id ] = array(
			'service_id'  => $service_id,
			'name'        => $normalized_service['name'],
			'version'     => $normalized_service['version'],
			'hostname'    => $normalized_service['hostname'],
			'ip_address'  => $normalized_service['ip_address'],
			'mac_address' => $normalized_service['mac_address'],
			'lock_ids'    => $normalized_service['lock_ids'],
			'last_seen'   => $now_iso,
			'lock_count'  => $lock_count,
			'min_battery' => $min_battery,
		);
		update_option( 'snippen_door_services_registry', $registry );

		// 3. For backward compatibility: update global last status & received_at
		update_option( 'snippen_doorman_last_status', $params );
		update_option( 'snippen_doorman_last_received_at', $now_iso );

		return rest_ensure_response(
			array(
				'success'     => true,
				'service_id'  => $service_id,
				'received_at' => $now_iso,
			)
		);
	}

	/**
	 * Handle GET /wp-json/snippen/v1/door/status
	 *
	 * Returns last received status snapshot enriched with booking metadata.
	 * If ?service_id=<id> is specified, returns status for that node.
	 * Without parameters, returns status for all registered nodes and an aggregated view.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error Response object.
	 */
	public static function get_status( \WP_REST_Request $request ) {
		$service_id  = $request->get_param( 'service_id' );
		$server_time = gmdate( 'Y-m-d\TH:i:s\Z' );

		// Specific service requested
		if ( ! empty( $service_id ) ) {
			$clean_id   = sanitize_key( $service_id );
			$raw_status = get_option( "snippen_door_status_{$clean_id}", null );

			if ( empty( $raw_status ) || ! is_array( $raw_status ) ) {
				// Fallback: check if snippen_doorman_last_status matches
				$last_global = get_option( 'snippen_doorman_last_status', null );
				if ( is_array( $last_global ) && ( ( $last_global['service_id'] ?? '' ) === $clean_id || ( 'default' === $clean_id && empty( $last_global['service_id'] ) ) ) ) {
					$raw_status = $last_global;
				}
			}

			if ( empty( $raw_status ) || ! is_array( $raw_status ) ) {
				return new \WP_Error(
					'rest_service_not_found',
					sprintf( __( 'Tjeneste med service_id "%s" ble ikke funnet.', 'snippen-booking' ), $clean_id ),
					array( 'status' => 404 )
				);
			}

			$enriched = self::enrich_status_metadata( $raw_status );
			return rest_ensure_response(
				array(
					'server_time'              => $server_time,
					'service_id'               => $clean_id,
					'doorman_last_received_at' => $raw_status['received_at'] ?? ( get_option( 'snippen_doorman_last_received_at', null ) ),
					'doorman_status'           => $enriched,
				)
			);
		}

		// No service_id: Return all registered nodes and aggregated overview
		$all_data = self::get_all_services_status();

		return rest_ensure_response(
			array(
				'server_time'              => $server_time,
				'services'                 => $all_data['services'],
				'services_status'          => $all_data['services_status'],
				'aggregated'               => $all_data['aggregated'],
				'doorman_last_received_at' => $all_data['last_received_at'],
				'doorman_status'           => $all_data['aggregated'], // For backward compatibility
			)
		);
	}

	/**
	 * Get enriched status for all registered services and an aggregated fleet overview.
	 *
	 * @return array Array with 'services', 'services_status', 'aggregated', and 'last_received_at'.
	 */
	public static function get_all_services_status() {
		$registry = get_option( 'snippen_door_services_registry', array() );
		if ( ! is_array( $registry ) ) {
			$registry = array();
		}

		// Backward compatibility: If registry is empty but snippen_doorman_last_status exists
		if ( empty( $registry ) ) {
			$last_global = get_option( 'snippen_doorman_last_status', null );
			if ( is_array( $last_global ) ) {
				$last_seen   = get_option( 'snippen_doorman_last_received_at', gmdate( 'Y-m-d\TH:i:s\Z' ) );
				$def_service = $last_global['service'] ?? array();
				$def_id      = sanitize_key( $last_global['service_id'] ?? ( $def_service['id'] ?? 'default' ) );
				if ( empty( $def_id ) ) {
					$def_id = 'default';
				}
				$registry[ $def_id ] = array(
					'service_id'  => $def_id,
					'name'        => $def_service['name'] ?? __( 'Standard Doorman-tjeneste', 'snippen-booking' ),
					'version'     => $def_service['version'] ?? '',
					'hostname'    => $def_service['hostname'] ?? '',
					'ip_address'  => $def_service['ip_address'] ?? '',
					'mac_address' => $def_service['mac_address'] ?? '',
					'lock_ids'    => $def_service['lock_ids'] ?? array(),
					'last_seen'   => $last_seen,
					'lock_count'  => count( $last_global['locks'] ?? array() ),
					'min_battery' => null,
				);
			}
		}

		$services_list    = array();
		$services_status  = array();
		$last_received_at = null;

		$all_locks        = array();
		$all_reservations = array();
		$all_provisioned  = array();
		$all_expired      = array();
		$latest_gen_at    = null;
		$max_win_days     = 7;

		$current_time = time();

		foreach ( $registry as $sid => $node_meta ) {
			$node_status = get_option( "snippen_door_status_{$sid}", null );
			if ( empty( $node_status ) || ! is_array( $node_status ) ) {
				// Check fallback to global last status
				$last_global = get_option( 'snippen_doorman_last_status', null );
				if ( is_array( $last_global ) && ( ( $last_global['service_id'] ?? '' ) === $sid || 'default' === $sid ) ) {
					$node_status = $last_global;
				}
			}

			// Calculate online/offline status (15 min = 900s)
			$last_seen_ts            = ! empty( $node_meta['last_seen'] ) ? strtotime( $node_meta['last_seen'] ) : 0;
			$is_offline              = ( 0 === $last_seen_ts ) || ( ( $current_time - $last_seen_ts ) > 900 );
			$node_meta['is_offline'] = $is_offline;
			$node_meta['status']     = $is_offline ? 'offline' : 'online';

			$services_list[ $sid ] = $node_meta;

			if ( is_array( $node_status ) ) {
				if ( ! empty( $node_meta['last_seen'] ) ) {
					if ( null === $last_received_at || strtotime( $node_meta['last_seen'] ) > strtotime( $last_received_at ) ) {
						$last_received_at = $node_meta['last_seen'];
					}
				}

				$enriched                = self::enrich_status_metadata( $node_status );
				$services_status[ $sid ] = $enriched;

				// Tag items with service info for display clarity
				$s_name = $node_meta['name'] ?? $sid;

				if ( ! empty( $enriched['locks'] ) && is_array( $enriched['locks'] ) ) {
					foreach ( $enriched['locks'] as $l ) {
						$l['service_id']   = $sid;
						$l['service_name'] = $s_name;
						$all_locks[]       = $l;
					}
				}

				if ( ! empty( $enriched['reservations_in_db'] ) && is_array( $enriched['reservations_in_db'] ) ) {
					foreach ( $enriched['reservations_in_db'] as $r ) {
						$r['service_id']    = $sid;
						$r['service_name']  = $s_name;
						$all_reservations[] = $r;
					}
				}

				if ( ! empty( $enriched['provisioned_on_locks'] ) && is_array( $enriched['provisioned_on_locks'] ) ) {
					foreach ( $enriched['provisioned_on_locks'] as $p ) {
						$p['service_id']   = $sid;
						$p['service_name'] = $s_name;
						$all_provisioned[] = $p;
					}
				}

				if ( ! empty( $enriched['recently_expired'] ) && is_array( $enriched['recently_expired'] ) ) {
					foreach ( $enriched['recently_expired'] as $e ) {
						$e['service_id']   = $sid;
						$e['service_name'] = $s_name;
						$all_expired[]     = $e;
					}
				}

				if ( ! empty( $enriched['generated_at'] ) ) {
					if ( null === $latest_gen_at || strtotime( $enriched['generated_at'] ) > strtotime( $latest_gen_at ) ) {
						$latest_gen_at = $enriched['generated_at'];
					}
				}

				if ( ! empty( $enriched['expired_window_days'] ) ) {
					$max_win_days = max( $max_win_days, (int) $enriched['expired_window_days'] );
				}
			}
		}

		$aggregated = null;
		if ( ! empty( $services_status ) || ! empty( $all_locks ) || ! empty( $all_reservations ) ) {
			$aggregated = array(
				'generated_at'         => $latest_gen_at ?: gmdate( 'Y-m-d\TH:i:s\Z' ),
				'expired_window_days'  => $max_win_days,
				'locks'                => $all_locks,
				'reservations_in_db'   => $all_reservations,
				'provisioned_on_locks' => $all_provisioned,
				'recently_expired'     => $all_expired,
			);
		} else {
			$last_received_at = null;
		}

		return array(
			'services'         => $services_list,
			'services_status'  => $services_status,
			'aggregated'       => $aggregated,
			'last_received_at' => $last_received_at,
		);
	}

	/**
	 * Enrich Doorman status snapshot with booking metadata and venue info.
	 *
	 * @param array $status Raw Doorman status array.
	 * @return array Enriched status array.
	 */
	public static function enrich_status_metadata( array $status ) {
		global $wpdb;

		$table_bookings = $wpdb->prefix . 'snippen_bookings';
		$table_bbo      = $wpdb->prefix . 'snippen_booking_booking_objects';
		$table_objects  = $wpdb->prefix . 'snippen_booking_objects';

		$booking_ids = array();
		foreach ( array( 'reservations_in_db', 'provisioned_on_locks', 'recently_expired' ) as $key ) {
			if ( ! empty( $status[ $key ] ) && is_array( $status[ $key ] ) ) {
				foreach ( $status[ $key ] as $item ) {
					if ( ! empty( $item['booking_id'] ) && is_numeric( $item['booking_id'] ) ) {
						$booking_ids[] = (int) $item['booking_id'];
					}
				}
			}
		}

		$booking_ids = array_values( array_unique( array_filter( $booking_ids ) ) );
		$meta_map    = array();

		if ( ! empty( $booking_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $booking_ids ), '%d' ) );

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$b_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, customer_name, customer_phone, customer_email, status, booking_date
					 FROM {$table_bookings}
					 WHERE id IN ({$placeholders})",
					...$booking_ids
				)
			);

			if ( is_array( $b_rows ) ) {
				foreach ( $b_rows as $row ) {
					$meta_map[ (int) $row->id ] = array(
						'customer_name'   => $row->customer_name,
						'customer_phone'  => $row->customer_phone,
						'customer_email'  => $row->customer_email,
						'status'          => $row->status,
						'booking_date'    => $row->booking_date,
						'booking_objects' => array(),
					);
				}
			}

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$obj_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT bbo.booking_id, bo.id as object_id, bo.name as object_name, bo.door_lock_id
					 FROM {$table_bbo} bbo
					 INNER JOIN {$table_objects} bo ON bbo.booking_object_id = bo.id
					 WHERE bbo.booking_id IN ({$placeholders}) AND bo.deleted_at IS NULL
					 ORDER BY bo.id ASC",
					...$booking_ids
				)
			);

			if ( is_array( $obj_rows ) ) {
				foreach ( $obj_rows as $orow ) {
					$b_id = (int) $orow->booking_id;
					if ( isset( $meta_map[ $b_id ] ) ) {
						$meta_map[ $b_id ]['booking_objects'][] = array(
							'id'   => (int) $orow->object_id,
							'name' => (string) $orow->object_name,
						);
					}
				}
			}
		}

		// Enrich collections
		foreach ( array( 'reservations_in_db', 'provisioned_on_locks', 'recently_expired' ) as $key ) {
			if ( ! empty( $status[ $key ] ) && is_array( $status[ $key ] ) ) {
				foreach ( $status[ $key ] as &$item ) {
					$b_id = ! empty( $item['booking_id'] ) ? (int) $item['booking_id'] : 0;
					if ( $b_id > 0 && isset( $meta_map[ $b_id ] ) ) {
						$item['customer_name']   = $meta_map[ $b_id ]['customer_name'];
						$item['customer_phone']  = $meta_map[ $b_id ]['customer_phone'];
						$item['customer_email']  = $meta_map[ $b_id ]['customer_email'];
						$item['booking_objects'] = $meta_map[ $b_id ]['booking_objects'];
					} else {
						$item['customer_name']   = $item['customer_name'] ?? null;
						$item['customer_phone']  = $item['customer_phone'] ?? null;
						$item['customer_email']  = $item['customer_email'] ?? null;
						$item['booking_objects'] = $item['booking_objects'] ?? array();
					}
				}
				unset( $item );
			}
		}

		// Attach venues to locks
		if ( ! empty( $status['locks'] ) && is_array( $status['locks'] ) ) {
			$lock_repo  = new \SnippenBooking\Database\Repository\DoorLockRepository();
			$repo_locks = $lock_repo->find_all_with_objects();
			$lock_map   = array();
			foreach ( $repo_locks as $rl ) {
				$rl_id              = is_object( $rl ) ? (int) $rl->id : (int) $rl['id'];
				$rl_handles         = is_object( $rl ) ? $rl->handles_objects : ( $rl['handles_objects'] ?? array() );
				$lock_map[ $rl_id ] = $rl_handles;
			}

			foreach ( $status['locks'] as &$lock_item ) {
				$l_id = ! empty( $lock_item['lock_id'] ) ? (int) $lock_item['lock_id'] : 0;
				if ( $l_id > 0 && isset( $lock_map[ $l_id ] ) ) {
					$lock_item['handles_objects'] = $lock_map[ $l_id ];
				} else {
					$lock_item['handles_objects'] = $lock_item['handles_objects'] ?? array();
				}
			}
			unset( $lock_item );
		}

		return $status;
	}
}
