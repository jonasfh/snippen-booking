<?php

namespace SnippenBooking\Admin;

/**
 * Setup Wizard - Handles optional onboarding and seed data creation
 */
class SetupWizard {

	const OPTION_WIZARD_COMPLETED         = 'snippen_booking_wizard_completed';
	const OPTION_WIZARD_COMPLETED_VERSION = 'snippen_booking_wizard_completed_version';

	/**
	 * Check if wizard has been completed
	 */
	public static function is_completed() {
		return (bool) get_option( self::OPTION_WIZARD_COMPLETED, false );
	}

	/**
	 * Mark wizard as completed
	 */
	public static function mark_completed() {
		update_option( self::OPTION_WIZARD_COMPLETED, true );
		update_option( self::OPTION_WIZARD_COMPLETED_VERSION, SNIPPEN_BOOKING_VERSION );
	}

	/**
	 * Reset wizard (for testing/re-running)
	 */
	public static function reset() {
		delete_option( self::OPTION_WIZARD_COMPLETED );
		delete_option( self::OPTION_WIZARD_COMPLETED_VERSION );
	}

	/**
	 * Create starter configuration (booking objects, blocks, pricing rules)
	 */
	public static function create_starter_setup() {
		global $wpdb;

		// Check if data already exists (idempotency)
		$object_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}snippen_booking_objects WHERE deleted_at IS NULL" );
		if ( $object_count > 0 ) {
			return array(
				'success' => false,
				'message' => 'Starter setup already exists',
			);
		}

		$table_objects       = $wpdb->prefix . 'snippen_booking_objects';
		$table_blocks        = $wpdb->prefix . 'snippen_booking_blocks';
		$table_object_blocks = $wpdb->prefix . 'snippen_booking_object_booking_blocks';
		$table_rules         = $wpdb->prefix . 'snippen_pricing_rules';
		$table_rule_blocks   = $wpdb->prefix . 'snippen_pricing_rule_booking_blocks';
		$table_rule_objects  = $wpdb->prefix . 'snippen_pricing_rule_booking_objects';

		// 1. Create booking objects
		$wpdb->insert(
			$table_objects,
			array(
				'name'        => 'Festsalen',
				'description' => 'Vårt største lokale med plass til mange gjester.',
			)
		);
		$festsalen_id = $wpdb->insert_id;

		$wpdb->insert(
			$table_objects,
			array(
				'name'        => 'Peisestuen',
				'description' => 'Koselig lokale med peis, perfekt for mindre samlinger.',
			)
		);
		$peisestuen_id = $wpdb->insert_id;

		$object_ids = array( $festsalen_id, $peisestuen_id );

		// 2. Create default booking blocks
		$blocks_to_create = array();

		// Hourly blocks from 08-09 to 15-16 are available on Mon-Fri (1,2,3,4,5)
		for ( $hour = 8; $hour < 16; $hour++ ) {
			$next_hour = $hour + 1;
			$start_str = sprintf( '%02d:00:00', $hour );
			$end_str   = sprintf( '%02d:00:00', $next_hour );
			$name      = sprintf( '%02d-%02d', $hour, $next_hour );

			$blocks_to_create[] = array(
				'name'         => $name,
				'description'  => "Hverdagstime/Fredagstime $name",
				'start_time'   => $start_str,
				'end_time'     => $end_str,
				'days_of_week' => '1,2,3,4,5',
				'type'         => 'hourly_day',
				'sort_order'   => $hour * 10,
			);
		}

		// Hourly blocks from 16-17 to 22-23 are available on Mon-Thu (1,2,3,4)
		for ( $hour = 16; $hour < 23; $hour++ ) {
			$next_hour = $hour + 1;
			$start_str = sprintf( '%02d:00:00', $hour );
			$end_str   = sprintf( '%02d:00:00', $next_hour );
			$name      = sprintf( '%02d-%02d', $hour, $next_hour );

			$blocks_to_create[] = array(
				'name'         => $name,
				'description'  => "Hverdagstime $name",
				'start_time'   => $start_str,
				'end_time'     => $end_str,
				'days_of_week' => '1,2,3,4',
				'type'         => 'hourly_evening',
				'sort_order'   => $hour * 10,
			);
		}

		// Day block for Saturday, Sunday, and holidays (08-16)
		$blocks_to_create[] = array(
			'name'         => 'Day',
			'description'  => 'Helg/Helligdag Dag (08-16)',
			'start_time'   => '08:00:00',
			'end_time'     => '16:00:00',
			'days_of_week' => '6,0,7',
			'type'         => 'weekend_day',
			'sort_order'   => 80,
		);

		// Evening block for Friday, Saturday, Sunday, and holidays (16-23)
		$blocks_to_create[] = array(
			'name'         => 'Evening',
			'description'  => 'Helg/Helligdag/Fredag Kveld (16-23)',
			'start_time'   => '16:00:00',
			'end_time'     => '23:00:00',
			'days_of_week' => '5,6,0,7',
			'type'         => 'weekend_evening',
			'sort_order'   => 240,
		);

		// Insert blocks and link them to objects
		$created_blocks = array();
		foreach ( $blocks_to_create as $block_data ) {
			$type = $block_data['type'];
			unset( $block_data['type'] );

			$wpdb->insert( $table_blocks, $block_data );
			$block_id = $wpdb->insert_id;

			$created_blocks[] = array(
				'id'   => $block_id,
				'type' => $type,
			);

			// Link to both objects
			foreach ( $object_ids as $obj_id ) {
				$wpdb->insert(
					$table_object_blocks,
					array(
						'booking_object_id' => $obj_id,
						'booking_block_id'  => $block_id,
					)
				);
			}
		}

		// 3. Create example pricing rules and link them
		foreach ( $object_ids as $obj_id ) {
			$obj_name = $obj_id === $festsalen_id ? 'Festsalen' : 'Peisestuen';

			// Weekday Hourly Price Rule
			$wpdb->insert(
				$table_rules,
				array(
					'name'         => "$obj_name - Hverdag Timepris",
					'description'  => 'Timepris på hverdager (Mon-Thu, and Fri daytime)',
					'price'        => 100.00,
					'priority'     => 1,
					'days_of_week' => '1,2,3,4,5',
					'holiday_only' => 0,
				)
			);
			$weekday_rule_id = $wpdb->insert_id;
			$wpdb->insert(
				$table_rule_objects,
				array(
					'pricing_rule_id'   => $weekday_rule_id,
					'booking_object_id' => $obj_id,
				)
			);

			// Link this weekday rule to all hourly blocks
			foreach ( $created_blocks as $cb ) {
				if ( $cb['type'] === 'hourly_day' || $cb['type'] === 'hourly_evening' ) {
					$wpdb->insert(
						$table_rule_blocks,
						array(
							'pricing_rule_id'  => $weekday_rule_id,
							'booking_block_id' => $cb['id'],
						)
					);
				}
			}

			// Weekend Day Rule (1000 NOK)
			$wpdb->insert(
				$table_rules,
				array(
					'name'         => "$obj_name - Helg Dag",
					'description'  => 'Dagpris i helger',
					'price'        => 1000.00,
					'priority'     => 2,
					'days_of_week' => '6,0',
					'holiday_only' => 0,
				)
			);
			$weekend_day_rule_id = $wpdb->insert_id;
			$wpdb->insert(
				$table_rule_objects,
				array(
					'pricing_rule_id'   => $weekend_day_rule_id,
					'booking_object_id' => $obj_id,
				)
			);

			// Weekend Evening Rule (1500 NOK)
			$wpdb->insert(
				$table_rules,
				array(
					'name'         => "$obj_name - Helg Kveld",
					'description'  => 'Kveldspris i helger (Fri, Sat, Sun)',
					'price'        => 1500.00,
					'priority'     => 2,
					'days_of_week' => '5,6,0',
					'holiday_only' => 0,
				)
			);
			$weekend_evening_rule_id = $wpdb->insert_id;
			$wpdb->insert(
				$table_rule_objects,
				array(
					'pricing_rule_id'   => $weekend_evening_rule_id,
					'booking_object_id' => $obj_id,
				)
			);

			// Holiday Day Rule (2500 NOK, priority 100)
			$wpdb->insert(
				$table_rules,
				array(
					'name'         => "$obj_name - Helligdag Dag",
					'description'  => 'Helligdagspris på dagtid',
					'price'        => 2500.00,
					'priority'     => 100,
					'days_of_week' => '7',
					'holiday_only' => 1,
				)
			);
			$holiday_day_rule_id = $wpdb->insert_id;
			$wpdb->insert(
				$table_rule_objects,
				array(
					'pricing_rule_id'   => $holiday_day_rule_id,
					'booking_object_id' => $obj_id,
				)
			);

			// Holiday Evening Rule (2500 NOK, priority 100)
			$wpdb->insert(
				$table_rules,
				array(
					'name'         => "$obj_name - Helligdag Kveld",
					'description'  => 'Helligdagspris på kveldstid',
					'price'        => 2500.00,
					'priority'     => 100,
					'days_of_week' => '7',
					'holiday_only' => 1,
				)
			);
			$holiday_evening_rule_id = $wpdb->insert_id;
			$wpdb->insert(
				$table_rule_objects,
				array(
					'pricing_rule_id'   => $holiday_evening_rule_id,
					'booking_object_id' => $obj_id,
				)
			);

			// Link the weekend rules and holiday rules to their respective blocks
			foreach ( $created_blocks as $cb ) {
				if ( $cb['type'] === 'weekend_day' ) {
					$wpdb->insert(
						$table_rule_blocks,
						array(
							'pricing_rule_id'  => $weekend_day_rule_id,
							'booking_block_id' => $cb['id'],
						)
					);
					$wpdb->insert(
						$table_rule_blocks,
						array(
							'pricing_rule_id'  => $holiday_day_rule_id,
							'booking_block_id' => $cb['id'],
						)
					);
				} elseif ( $cb['type'] === 'weekend_evening' ) {
					$wpdb->insert(
						$table_rule_blocks,
						array(
							'pricing_rule_id'  => $weekend_evening_rule_id,
							'booking_block_id' => $cb['id'],
						)
					);
					$wpdb->insert(
						$table_rule_blocks,
						array(
							'pricing_rule_id'  => $holiday_evening_rule_id,
							'booking_block_id' => $cb['id'],
						)
					);
				}
			}
		}

		// 4. Create global legacy time slots (for backward compatibility during redesign)
		$table_slots             = $wpdb->prefix . 'snippen_time_slots';
		$table_prices            = $wpdb->prefix . 'snippen_prices';
		$table_time_slot_objects = $wpdb->prefix . 'snippen_time_slot_booking_objects';

		$base_slots = array(
			array(
				'name'          => 'Hele dagen',
				'description'   => 'Du booker rommet fra kl 11 til 23, og har til kl 11 neste dag til å rydde og vaske ut.',
				'start_time'    => '11:00:00',
				'end_time'      => '23:00:00',
				'cleanup_hours' => 12,
			),
			array(
				'name'          => 'Formiddag',
				'description'   => 'Fra kl 08:00 til 16:00. Du må vaske og ryddet lokalet når du forlater det.',
				'start_time'    => '08:00:00',
				'end_time'      => '16:00:00',
				'cleanup_hours' => 0,
			),
			array(
				'name'          => 'Ettermiddag',
				'description'   => 'Fra kl 16:00 til 23:00. Du har til kl 08:00 neste dag til å vaske deg ut',
				'start_time'    => '16:00:00',
				'end_time'      => '23:00:00',
				'cleanup_hours' => 9,
			),
		);

		$base_prices = array(
			'Hele dagen'  => 1000,
			'Formiddag'   => 500,
			'Ettermiddag' => 500,
		);

		$slot_variations = array(
			array(
				'suffix'       => '(Hverdag)',
				'days_of_week' => '1,2,3,4',
				'price_mult'   => 1,
			),
			array(
				'suffix'       => '(Helg)',
				'days_of_week' => '5,6,0',
				'price_mult'   => 2,
			),
			array(
				'suffix'       => '(Helligdager og høytider)',
				'days_of_week' => '7',
				'price_mult'   => 2,
			),
		);

		$combinations = array(
			array(
				'name_prefix' => 'Festsalen',
				'object_ids'  => array( $festsalen_id ),
				'price_mult'  => 1,
			),
			array(
				'name_prefix' => 'Peisestuen',
				'object_ids'  => array( $peisestuen_id ),
				'price_mult'  => 1,
			),
		);

		$combo_both = array(
			'name_prefix' => 'Hele området',
			'object_ids'  => array( $festsalen_id, $peisestuen_id ),
			'price_mult'  => 2,
		);

		foreach ( $base_slots as $base_slot ) {
			foreach ( $slot_variations as $var ) {
				$base_price = $base_prices[ $base_slot['name'] ] ?? 1000;
				$var_price  = $base_price * $var['price_mult'];

				$current_combinations = $combinations;
				if ( $base_slot['name'] === 'Hele dagen' ) {
					$current_combinations[] = $combo_both;
				}

				foreach ( $current_combinations as $combo ) {
					$slot_name = $combo['name_prefix'] . ' - ' . $base_slot['name'] . ' ' . $var['suffix'];

					$final_price = $var_price * $combo['price_mult'];
					$wpdb->insert(
						$table_prices,
						array(
							'name'     => $slot_name,
							'price'    => $final_price,
							'priority' => ( strpos( $var['days_of_week'], '7' ) !== false ) ? 100 : ( $var['price_mult'] > 1 ? 10 : 0 ),
						)
					);
					$price_id = $wpdb->insert_id;

					$slot_data = array_merge(
						$base_slot,
						array(
							'name'         => $slot_name,
							'days_of_week' => $var['days_of_week'],
							'price_id'     => $price_id,
						)
					);

					$wpdb->insert( $table_slots, $slot_data );
					$slot_id = $wpdb->insert_id;

					foreach ( $combo['object_ids'] as $obj_id ) {
						$wpdb->insert(
							$table_time_slot_objects,
							array(
								'time_slot_id'      => $slot_id,
								'booking_object_id' => $obj_id,
							)
						);
					}
				}
			}
		}

		return array(
			'success' => true,
			'message' => 'Starter setup created successfully',
		);
	}

	/**
	 * Create starter configuration variant 2 (2 blocks per day)
	 */
	public static function create_starter_setup_v2() {
		global $wpdb;

		// Check if data already exists (idempotency)
		$object_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}snippen_booking_objects WHERE deleted_at IS NULL" );
		if ( $object_count > 0 ) {
			return array(
				'success' => false,
				'message' => 'Starter setup already exists',
			);
		}

		$table_objects       = $wpdb->prefix . 'snippen_booking_objects';
		$table_blocks        = $wpdb->prefix . 'snippen_booking_blocks';
		$table_object_blocks = $wpdb->prefix . 'snippen_booking_object_booking_blocks';
		$table_rules         = $wpdb->prefix . 'snippen_pricing_rules';
		$table_rule_blocks   = $wpdb->prefix . 'snippen_pricing_rule_booking_blocks';
		$table_rule_objects  = $wpdb->prefix . 'snippen_pricing_rule_booking_objects';

		// 1. Create booking objects
		$wpdb->insert(
			$table_objects,
			array(
				'name'        => 'Festsalen',
				'description' => 'Vårt største lokale med plass til mange gjester.',
			)
		);
		$festsalen_id = $wpdb->insert_id;

		$wpdb->insert(
			$table_objects,
			array(
				'name'        => 'Peisestuen',
				'description' => 'Koselig lokale med peis, perfekt for mindre samlinger.',
			)
		);
		$peisestuen_id = $wpdb->insert_id;

		$object_ids = array( $festsalen_id, $peisestuen_id );

		// 2. Create booking blocks
		$blocks_to_create = array(
			array(
				'name'         => 'Dag',
				'description'  => 'Dagtid (08-16)',
				'start_time'   => '08:00:00',
				'end_time'     => '16:00:00',
				'days_of_week' => '1,2,3,4,5,6,0,7',
				'type'         => 'day',
				'sort_order'   => 10,
			),
			array(
				'name'         => 'Kveld',
				'description'  => 'Kveldstid (16-23)',
				'start_time'   => '16:00:00',
				'end_time'     => '23:00:00',
				'days_of_week' => '1,2,3,4,5,6,0,7',
				'type'         => 'evening',
				'sort_order'   => 20,
			),
		);

		$created_blocks = array();
		foreach ( $blocks_to_create as $block_data ) {
			$type = $block_data['type'];
			unset( $block_data['type'] );

			$wpdb->insert( $table_blocks, $block_data );
			$block_id = $wpdb->insert_id;

			$created_blocks[] = array(
				'id'   => $block_id,
				'type' => $type,
			);

			// Link to both objects
			foreach ( $object_ids as $obj_id ) {
				$wpdb->insert(
					$table_object_blocks,
					array(
						'booking_object_id' => $obj_id,
						'booking_block_id'  => $block_id,
					)
				);
			}
		}

		// 3. Create pricing rules and link them
		foreach ( $object_ids as $obj_id ) {
			$obj_name = $obj_id === $festsalen_id ? 'Festsalen' : 'Peisestuen';

			// Weekday Day Rule
			$wpdb->insert(
				$table_rules,
				array(
					'name'         => "$obj_name - Hverdag Dag",
					'description'  => 'Dagpris på hverdager (Mon-Fri)',
					'price'        => $obj_id === $festsalen_id ? 750.00 : 500.00,
					'priority'     => 1,
					'days_of_week' => '1,2,3,4,5',
					'holiday_only' => 0,
				)
			);
			$weekday_day_rule_id = $wpdb->insert_id;
			$wpdb->insert(
				$table_rule_objects,
				array(
					'pricing_rule_id'   => $weekday_day_rule_id,
					'booking_object_id' => $obj_id,
				)
			);

			// Weekday Evening Rule
			$wpdb->insert(
				$table_rules,
				array(
					'name'         => "$obj_name - Hverdag Kveld",
					'description'  => 'Kveldspris på hverdager (Mon-Thu)',
					'price'        => $obj_id === $festsalen_id ? 750.00 : 500.00,
					'priority'     => 1,
					'days_of_week' => '1,2,3,4',
					'holiday_only' => 0,
				)
			);
			$weekday_evening_rule_id = $wpdb->insert_id;
			$wpdb->insert(
				$table_rule_objects,
				array(
					'pricing_rule_id'   => $weekday_evening_rule_id,
					'booking_object_id' => $obj_id,
				)
			);

			// Weekend Day Rule
			$wpdb->insert(
				$table_rules,
				array(
					'name'         => "$obj_name - Helg Dag",
					'description'  => 'Dagpris i helger',
					'price'        => $obj_id === $festsalen_id ? 1500.00 : 1000.00,
					'priority'     => 2,
					'days_of_week' => '6,0',
					'holiday_only' => 0,
				)
			);
			$weekend_day_rule_id = $wpdb->insert_id;
			$wpdb->insert(
				$table_rule_objects,
				array(
					'pricing_rule_id'   => $weekend_day_rule_id,
					'booking_object_id' => $obj_id,
				)
			);

			// Weekend Evening Rule
			$wpdb->insert(
				$table_rules,
				array(
					'name'         => "$obj_name - Helg Kveld",
					'description'  => 'Kveldspris i helger (Fri, Sat, Sun)',
					'price'        => $obj_id === $festsalen_id ? 1500.00 : 1000.00,
					'priority'     => 2,
					'days_of_week' => '5,6,0',
					'holiday_only' => 0,
				)
			);
			$weekend_evening_rule_id = $wpdb->insert_id;
			$wpdb->insert(
				$table_rule_objects,
				array(
					'pricing_rule_id'   => $weekend_evening_rule_id,
					'booking_object_id' => $obj_id,
				)
			);

			// Holiday Day Rule
			$wpdb->insert(
				$table_rules,
				array(
					'name'         => "$obj_name - Helligdag Dag",
					'description'  => 'Helligdagspris på dagtid',
					'price'        => $obj_id === $festsalen_id ? 1500.00 : 1000.00,
					'priority'     => 100,
					'days_of_week' => '7',
					'holiday_only' => 1,
				)
			);
			$holiday_day_rule_id = $wpdb->insert_id;
			$wpdb->insert(
				$table_rule_objects,
				array(
					'pricing_rule_id'   => $holiday_day_rule_id,
					'booking_object_id' => $obj_id,
				)
			);

			// Holiday Evening Rule
			$wpdb->insert(
				$table_rules,
				array(
					'name'         => "$obj_name - Helligdag Kveld",
					'description'  => 'Helligdagspris på kveldstid',
					'price'        => $obj_id === $festsalen_id ? 1500.00 : 1000.00,
					'priority'     => 100,
					'days_of_week' => '7',
					'holiday_only' => 1,
				)
			);
			$holiday_evening_rule_id = $wpdb->insert_id;
			$wpdb->insert(
				$table_rule_objects,
				array(
					'pricing_rule_id'   => $holiday_evening_rule_id,
					'booking_object_id' => $obj_id,
				)
			);

			// Link the rules to their respective blocks
			foreach ( $created_blocks as $cb ) {
				if ( $cb['type'] === 'day' ) {
					$wpdb->insert(
						$table_rule_blocks,
						array(
							'pricing_rule_id'  => $weekday_day_rule_id,
							'booking_block_id' => $cb['id'],
						)
					);
					$wpdb->insert(
						$table_rule_blocks,
						array(
							'pricing_rule_id'  => $weekend_day_rule_id,
							'booking_block_id' => $cb['id'],
						)
					);
					$wpdb->insert(
						$table_rule_blocks,
						array(
							'pricing_rule_id'  => $holiday_day_rule_id,
							'booking_block_id' => $cb['id'],
						)
					);
				} elseif ( $cb['type'] === 'evening' ) {
					$wpdb->insert(
						$table_rule_blocks,
						array(
							'pricing_rule_id'  => $weekday_evening_rule_id,
							'booking_block_id' => $cb['id'],
						)
					);
					$wpdb->insert(
						$table_rule_blocks,
						array(
							'pricing_rule_id'  => $weekend_evening_rule_id,
							'booking_block_id' => $cb['id'],
						)
					);
					$wpdb->insert(
						$table_rule_blocks,
						array(
							'pricing_rule_id'  => $holiday_evening_rule_id,
							'booking_block_id' => $cb['id'],
						)
					);
				}
			}
		}

		// 4. Create global legacy time slots (for backward compatibility)
		$table_slots             = $wpdb->prefix . 'snippen_time_slots';
		$table_prices            = $wpdb->prefix . 'snippen_prices';
		$table_time_slot_objects = $wpdb->prefix . 'snippen_time_slot_booking_objects';

		$base_slots = array(
			array(
				'name'          => 'Formiddag',
				'description'   => 'Fra kl 08:00 til 16:00. Du må vaske og ryddet lokalet når du forlater det.',
				'start_time'    => '08:00:00',
				'end_time'      => '16:00:00',
				'cleanup_hours' => 0,
			),
			array(
				'name'          => 'Ettermiddag',
				'description'   => 'Fra kl 16:00 til 23:00. Du har til kl 08:00 neste dag til å vaske deg ut',
				'start_time'    => '16:00:00',
				'end_time'      => '23:00:00',
				'cleanup_hours' => 9,
			),
		);

		$base_prices = array(
			'Formiddag'   => 500,
			'Ettermiddag' => 500,
		);

		$slot_variations = array(
			array(
				'suffix'       => '(Hverdag)',
				'days_of_week' => '1,2,3,4',
				'price_mult'   => 1,
			),
			array(
				'suffix'       => '(Helg)',
				'days_of_week' => '5,6,0',
				'price_mult'   => 2,
			),
			array(
				'suffix'       => '(Helligdager og høytider)',
				'days_of_week' => '7',
				'price_mult'   => 2,
			),
		);

		$combinations = array(
			array(
				'name_prefix' => 'Festsalen',
				'object_ids'  => array( $festsalen_id ),
				'price_mult'  => 1.5,
			),
			array(
				'name_prefix' => 'Peisestuen',
				'object_ids'  => array( $peisestuen_id ),
				'price_mult'  => 1,
			),
		);

		foreach ( $base_slots as $base_slot ) {
			foreach ( $slot_variations as $var ) {
				$base_price = $base_prices[ $base_slot['name'] ] ?? 500;
				$var_price  = $base_price * $var['price_mult'];

				$current_combinations = $combinations;

				foreach ( $current_combinations as $combo ) {
					$slot_name = $combo['name_prefix'] . ' - ' . $base_slot['name'] . ' ' . $var['suffix'];

					$final_price = $var_price * $combo['price_mult'];
					$wpdb->insert(
						$table_prices,
						array(
							'name'     => $slot_name,
							'price'    => $final_price,
							'priority' => ( strpos( $var['days_of_week'], '7' ) !== false ) ? 100 : ( $var['price_mult'] > 1 ? 10 : 0 ),
						)
					);
					$price_id = $wpdb->insert_id;

					$slot_data = array_merge(
						$base_slot,
						array(
							'name'         => $slot_name,
							'days_of_week' => $var['days_of_week'],
							'price_id'     => $price_id,
						)
					);

					$wpdb->insert( $table_slots, $slot_data );
					$slot_id = $wpdb->insert_id;

					foreach ( $combo['object_ids'] as $obj_id ) {
						$wpdb->insert(
							$table_time_slot_objects,
							array(
								'time_slot_id'      => $slot_id,
								'booking_object_id' => $obj_id,
							)
						);
					}
				}
			}
		}

		return array(
			'success' => true,
			'message' => 'Starter setup (Variant 2) created successfully',
		);
	}
}
