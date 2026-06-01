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

		// 2. Create global time slots (now with availability rules)
		$base_slots = array(
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

		$base_prices = array(
			'Hele dagen'  => 1000,
			'Formiddag'   => 500,
			'Ettermiddag' => 500,
		);

		$objects = $wpdb->get_results( "SELECT id, name FROM $table_objects WHERE deleted_at IS NULL" );

		// Create variations for each base slot
		$slot_variations = array(
			array(
				'suffix'       => '(Hverdag)',
				'days_of_week' => '1,2,3,4',
				'is_holiday'   => 0,
				'price_mult'   => 1,
			),
			array(
				'suffix'       => '(Helg)',
				'days_of_week' => '5,6,0',
				'is_holiday'   => 0,
				'price_mult'   => 2,
			),
			array(
				'suffix'       => '(Helligdager og høytider)',
				'days_of_week' => null,
				'is_holiday'   => 1,
				'price_mult'   => 2,
			),
		);

		foreach ( $base_slots as $base_slot ) {
			foreach ( $slot_variations as $var ) {
				$slot_name = $base_slot['name'] . ' ' . $var['suffix'];

				$slot_data = array_merge(
					$base_slot,
					array(
						'name'         => $slot_name,
						'days_of_week' => $var['days_of_week'],
						'is_holiday'   => $var['is_holiday'],
					)
				);

				$wpdb->insert( $table_slots, $slot_data );
				$slot_id = $wpdb->insert_id;

				$base_price = $base_prices[ $base_slot['name'] ] ?? 1000;
				$price      = $base_price * $var['price_mult'];

				// Individual prices for each object
				foreach ( $objects as $obj ) {
					$wpdb->insert(
						$table_prices,
						array(
							'name'     => $obj->name . ' - ' . $slot_name,
							'price'    => $price,
							'slot_id'  => $slot_id,
							'priority' => $var['is_holiday'] ? 100 : ( $var['price_mult'] > 1 ? 10 : 0 ),
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

				// Combined price (Hele området) for 'Hele dagen' slots
				if ( $base_slot['name'] === 'Hele dagen' ) {
					$combined_price = 2000 * $var['price_mult'];
					$wpdb->insert(
						$table_prices,
						array(
							'name'     => 'Hele området - ' . $slot_name,
							'price'    => $combined_price,
							'slot_id'  => $slot_id,
							'priority' => $var['is_holiday'] ? 100 : ( $var['price_mult'] > 1 ? 10 : 0 ),
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
				}
			}
		}

		return array(
			'success' => true,
			'message' => 'Starter setup created successfully',
		);
	}
}
