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
	 * Create starter configuration (booking objects, slots, pricing)
	 */
	public static function create_starter_setup() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		// Check if data already exists (idempotency)
		$object_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}snippen_booking_objects WHERE deleted_at IS NULL" );
		if ( $object_count > 0 ) {
			return array(
				'success' => false,
				'message' => 'Starter setup already exists',
			);
		}

		$table_objects       = $wpdb->prefix . 'snippen_booking_objects';
		$table_slots         = $wpdb->prefix . 'snippen_time_slots';
		$table_prices        = $wpdb->prefix . 'snippen_prices';
		$table_price_objects = $wpdb->prefix . 'snippen_price_booking_objects';

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

		// 2. Create global time slots
		$slots = array(
			array(
				'name'               => 'Hele dagen',
				'description'        => 'Du booker rommet fra kl 11 til 23, og har til kl 11 neste dag til å rydde og vaske ut.',
				'start_time'         => '11:00:00',
				'end_time'           => '23:00:00',
				'cleanup_hours'      => 12,
				'allow_multi_object' => 1,
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

		foreach ( $slots as $slot ) {
			$wpdb->insert( $table_slots, $slot );
		}

		// 3. Create pricing
		$objects      = $wpdb->get_results( "SELECT id, name FROM $table_objects WHERE deleted_at IS NULL" );
		$global_slots = $wpdb->get_results( "SELECT id, name FROM $table_slots WHERE deleted_at IS NULL" );

		$base_prices = array(
			'Hele dagen'  => 1000,
			'Formiddag'   => 500,
			'Ettermiddag' => 500,
		);

		// Individual prices for each object
		foreach ( $objects as $obj ) {
			foreach ( $global_slots as $slot_item ) {
				$slot_name = $slot_item->name;
				$price     = $base_prices[ $slot_name ] ?? 1000;

				// Standard Weekday Price (Mon-Thu)
				$wpdb->insert(
					$table_prices,
					array(
						'name'         => $obj->name . ' - ' . $slot_name . ' (Hverdag)',
						'price'        => $price,
						'slot_id'      => $slot_item->id,
						'days_of_week' => '1,2,3,4',
						'priority'     => 0,
					)
				);
				$price_id = $wpdb->insert_id;
				$wpdb->insert(
					$table_price_objects,
					array(
						'price_id'          => $price_id,
						'booking_object_id' => $obj->id,
					)
				);

				// Weekend Price (Fri-Sun)
				$wpdb->insert(
					$table_prices,
					array(
						'name'         => $obj->name . ' - ' . $slot_name . ' (Helg)',
						'price'        => $price * 2,
						'slot_id'      => $slot_item->id,
						'days_of_week' => '5,6,0',
						'priority'     => 10,
					)
				);
				$price_id = $wpdb->insert_id;
				$wpdb->insert(
					$table_price_objects,
					array(
						'price_id'          => $price_id,
						'booking_object_id' => $obj->id,
					)
				);

				// State holidays Price
				$wpdb->insert(
					$table_prices,
					array(
						'name'       => $obj->name . ' - ' . $slot_name . ' (Helligdager og høytider)',
						'price'      => $price * 2,
						'slot_id'    => $slot_item->id,
						'priority'   => 100,
						'is_holiday' => 1,
					)
				);
				$price_id = $wpdb->insert_id;
				$wpdb->insert(
					$table_price_objects,
					array(
						'price_id'          => $price_id,
						'booking_object_id' => $obj->id,
					)
				);
			}
		}

		// Combined prices (Hele området)
		$hele_dagen_slot = $wpdb->get_row( "SELECT id FROM $table_slots WHERE name = 'Hele dagen' LIMIT 1" );
		$hele_dagen_id   = $hele_dagen_slot ? $hele_dagen_slot->id : 0;

		// Standard
		$wpdb->insert(
			$table_prices,
			array(
				'name'         => 'Hele området - Hele dagen (Hverdag)',
				'price'        => 2000,
				'slot_id'      => $hele_dagen_id,
				'days_of_week' => '1,2,3,4',
				'priority'     => 0,
			)
		);
		$combined_price_id = $wpdb->insert_id;
		foreach ( $objects as $obj ) {
			$wpdb->insert(
				$table_price_objects,
				array(
					'price_id'          => $combined_price_id,
					'booking_object_id' => $obj->id,
				)
			);
		}

		// Weekend
		$wpdb->insert(
			$table_prices,
			array(
				'name'         => 'Hele området - Hele dagen (Helg)',
				'price'        => 4000,
				'slot_id'      => $hele_dagen_id,
				'days_of_week' => '5,6,0',
				'priority'     => 10,
			)
		);
		$combined_price_id = $wpdb->insert_id;
		foreach ( $objects as $obj ) {
			$wpdb->insert(
				$table_price_objects,
				array(
					'price_id'          => $combined_price_id,
					'booking_object_id' => $obj->id,
				)
			);
		}

		// State holidays
		$wpdb->insert(
			$table_prices,
			array(
				'name'       => 'Hele området - Hele dagen (Helligdager og høytider)',
				'price'      => 4000,
				'slot_id'    => $hele_dagen_id,
				'priority'   => 100,
				'is_holiday' => 1,
			)
		);
		$combined_price_id = $wpdb->insert_id;
		foreach ( $objects as $obj ) {
			$wpdb->insert(
				$table_price_objects,
				array(
					'price_id'          => $combined_price_id,
					'booking_object_id' => $obj->id,
				)
			);
		}

		return array(
			'success' => true,
			'message' => 'Starter setup created successfully',
		);
	}
}
