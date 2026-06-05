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
		);

		// Evening block for Friday, Saturday, Sunday, and holidays (16-23)
		$blocks_to_create[] = array(
			'name'         => 'Evening',
			'description'  => 'Helg/Helligdag/Fredag Kveld (16-23)',
			'start_time'   => '16:00:00',
			'end_time'     => '23:00:00',
			'days_of_week' => '5,6,0,7',
			'type'         => 'weekend_evening',
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

		return array(
			'success' => true,
			'message' => 'Starter setup created successfully',
		);
	}
}
