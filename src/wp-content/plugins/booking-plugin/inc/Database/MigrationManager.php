<?php

namespace SnippenBooking\Database;

/**
 * Manages database migrations
 */
class MigrationManager {

	/**
	 * Run all pending migrations
	 */
	public static function run() {
		$current_version = get_option( 'snippen_booking_db_version', '0.0.0' );

		// Use version from main plugin file
		$plugin_data    = get_file_data( dirname( dirname( __DIR__ ) ) . '/booking-plugin.php', array( 'Version' => 'Version' ) );
		$target_version = $plugin_data['Version'];

		if ( version_compare( $current_version, $target_version, '<' ) ) {
			self::execute_migrations( $current_version, $target_version );
			update_option( 'snippen_booking_db_version', $target_version );
		}
	}

	/**
	 * Execute migrations sequentially
	 */
	private static function execute_migrations( $current, $target ) {
		$migrations = array(
			'1.0.0'  => \SnippenBooking\Database\Migrations\Migration_1_0_0::class,
			'1.3.2'  => \SnippenBooking\Database\Migrations\Migration_1_3_2::class,
			'1.4.0'  => \SnippenBooking\Database\Migrations\Migration_1_4_0::class,
			'1.5.0'  => \SnippenBooking\Database\Migrations\Migration_1_5_0::class,
			'1.12.0' => \SnippenBooking\Database\Migrations\Migration_1_12_0::class,
			'1.19.0' => \SnippenBooking\Database\Migrations\Migration_1_19_0::class,
			'1.20.0' => \SnippenBooking\Database\Migrations\Migration_1_20_0::class,
			'1.21.0' => \SnippenBooking\Database\Migrations\Migration_1_21_0::class,
			'1.22.0' => \SnippenBooking\Database\Migrations\Migration_1_22_0::class,
			'1.25.0' => \SnippenBooking\Database\Migrations\Migration_1_25_0::class,
			'1.26.0' => \SnippenBooking\Database\Migrations\Migration_1_26_0::class,
			'2.6.0'  => \SnippenBooking\Database\Migrations\Migration_2_6_0::class,
			'2.6.1'  => \SnippenBooking\Database\Migrations\Migration_2_6_1::class,
			'2.9.0'  => \SnippenBooking\Database\Migrations\Migration_2_9_0::class,
		);

		foreach ( $migrations as $version => $class ) {
			if ( version_compare( $current, $version, '<' ) && version_compare( $version, $target, '<=' ) ) {
				if ( class_exists( $class ) ) {
					$migration = new $class();
					$migration->up();
				}
			}
		}
	}
}
