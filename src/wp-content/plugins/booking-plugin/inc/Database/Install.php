<?php

namespace SnippenBooking\Database;

use SnippenBooking\Helper\Capabilities;

/**
 * Handles plugin activation and database setup
 */
class Install {

	/**
	 * Run activation tasks
	 */
	public static function activate() {
		global $wpdb;

		// If running in tests and tables already exist, skip to prevent implicit commits from DDL statements
		if ( defined( 'SNIPPEN_BOOKING_TESTS_DIR' ) ) {
			$table_blocks = $wpdb->prefix . 'snippen_booking_blocks';
			if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_blocks'" ) === $table_blocks ) {
				return;
			}
		}

		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Booking objects table
		$table_objects = $wpdb->prefix . 'snippen_booking_objects';
		$sql_objects   = "CREATE TABLE $table_objects (
            id INT NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            info_link VARCHAR(255),
            door_code VARCHAR(255) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            modified_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
		dbDelta( $sql_objects );

		// Booking blocks table
		$table_blocks = $wpdb->prefix . 'snippen_booking_blocks';
		$sql_blocks   = "CREATE TABLE $table_blocks (
            id INT NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            start_time TIME DEFAULT '00:00:00',
            end_time TIME DEFAULT '23:59:59',
            days_of_week VARCHAR(50) DEFAULT NULL,
            sort_order INT DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            modified_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
		dbDelta( $sql_blocks );

		// Booking object booking blocks junction table
		$table_object_blocks = $wpdb->prefix . 'snippen_booking_object_booking_blocks';
		$sql_object_blocks   = "CREATE TABLE $table_object_blocks (
            booking_object_id INT NOT NULL,
            booking_block_id INT NOT NULL,
            PRIMARY KEY  (booking_object_id, booking_block_id),
            KEY booking_object_id (booking_object_id),
            KEY booking_block_id (booking_block_id)
        ) $charset_collate;";
		dbDelta( $sql_object_blocks );

		// Pricing rules table
		$table_pricing_rules = $wpdb->prefix . 'snippen_pricing_rules';
		$sql_pricing_rules   = "CREATE TABLE $table_pricing_rules (
            id INT NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            price DECIMAL(10,2) NOT NULL,
            priority INT DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            days_of_week VARCHAR(50) DEFAULT NULL,
            holiday_only TINYINT(1) DEFAULT 0,
            date_start DATE DEFAULT NULL,
            date_end DATE DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            modified_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL,
            PRIMARY KEY  (id),
            KEY priority (priority)
        ) $charset_collate;";
		dbDelta( $sql_pricing_rules );

		// Pricing rule booking blocks junction table
		$table_rule_blocks = $wpdb->prefix . 'snippen_pricing_rule_booking_blocks';
		$sql_rule_blocks   = "CREATE TABLE $table_rule_blocks (
            pricing_rule_id INT NOT NULL,
            booking_block_id INT NOT NULL,
            PRIMARY KEY  (pricing_rule_id, booking_block_id),
            KEY pricing_rule_id (pricing_rule_id),
            KEY booking_block_id (booking_block_id)
        ) $charset_collate;";
		dbDelta( $sql_rule_blocks );

		// Pricing rule booking objects junction table
		$table_rule_objects = $wpdb->prefix . 'snippen_pricing_rule_booking_objects';
		$sql_rule_objects   = "CREATE TABLE $table_rule_objects (
            pricing_rule_id INT NOT NULL,
            booking_object_id INT NOT NULL,
            PRIMARY KEY  (pricing_rule_id, booking_object_id),
            KEY pricing_rule_id (pricing_rule_id),
            KEY booking_object_id (booking_object_id)
        ) $charset_collate;";
		dbDelta( $sql_rule_objects );

		// Discount rules table
		$table_discount_rules = $wpdb->prefix . 'snippen_discount_rules';
		$sql_discount_rules   = "CREATE TABLE $table_discount_rules (
            id INT NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            discount_type VARCHAR(20) NOT NULL,
            discount_value DECIMAL(10,2) NOT NULL,
            min_duration_hours DECIMAL(10,2) NULL,
            max_duration_hours DECIMAL(10,2) NULL,
            days_of_week VARCHAR(50) NULL,
            holiday_only TINYINT(1) DEFAULT 0,
            priority INT DEFAULT 10,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            modified_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL,
            PRIMARY KEY  (id),
            KEY priority (priority)
        ) $charset_collate;";
		dbDelta( $sql_discount_rules );

		// Discount rule booking objects junction table
		$table_discount_rule_objects = $wpdb->prefix . 'snippen_discount_rule_booking_objects';
		$sql_discount_rule_objects   = "CREATE TABLE $table_discount_rule_objects (
            discount_rule_id INT NOT NULL,
            booking_object_id INT NOT NULL,
            PRIMARY KEY  (discount_rule_id, booking_object_id),
            KEY discount_rule_id (discount_rule_id),
            KEY booking_object_id (booking_object_id)
        ) $charset_collate;";
		dbDelta( $sql_discount_rule_objects );

		// Payment statuses table
		$table_payment_statuses = $wpdb->prefix . 'snippen_payment_statuses';
		$sql_payment_statuses   = "CREATE TABLE $table_payment_statuses (
            id INT NOT NULL AUTO_INCREMENT,
            slug VARCHAR(50) NOT NULL,
            name VARCHAR(100) NOT NULL,
            is_settled TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            modified_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug)
        ) $charset_collate;";
		dbDelta( $sql_payment_statuses );

		$default_statuses = array(
			array(
				'id'         => 1,
				'slug'       => 'UNPAID',
				'name'       => 'Mangler betaling',
				'is_settled' => 0,
			),
			array(
				'id'         => 2,
				'slug'       => 'PAID',
				'name'       => 'Betalt',
				'is_settled' => 1,
			),
			array(
				'id'         => 3,
				'slug'       => 'EXEMPT',
				'name'       => 'Fritatt / Gratis',
				'is_settled' => 1,
			),
		);
		foreach ( $default_statuses as $st ) {
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_payment_statuses WHERE slug = %s", $st['slug'] ) );
			if ( ! $exists ) {
				$wpdb->insert( $table_payment_statuses, $st );
			}
		}

		// Bookings table (with slot_id and facility restored for backward compatibility)
		$table_bookings = $wpdb->prefix . 'snippen_bookings';
		$sql_bookings   = "CREATE TABLE $table_bookings (
            id BIGINT NOT NULL AUTO_INCREMENT,
            uuid VARCHAR(36) NULL,
            facility VARCHAR(50),
            user_id BIGINT UNSIGNED NOT NULL,
            slot_id INT NOT NULL DEFAULT 0,
            booking_date DATE NOT NULL,
            customer_name VARCHAR(255) NOT NULL,
            customer_email VARCHAR(255) NOT NULL,
            customer_phone VARCHAR(50) DEFAULT '',
            description TEXT,
            price DECIMAL(10,2) DEFAULT 0,
            discount_amount DECIMAL(10,2) DEFAULT 0,
            discount_rule_id INT NULL,
            status VARCHAR(20) DEFAULT 'pending',
            payment_status_id INT DEFAULT 1,
            payment_receipt_attachment_id BIGINT UNSIGNED NULL,
            payment_notes TEXT NULL,
            payment_updated_at DATETIME NULL,
            door_code VARCHAR(255) NULL,
            booking_snapshot LONGTEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            modified_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uuid (uuid),
            KEY booking_date (booking_date),
            KEY slot_id (slot_id),
            KEY user_id (user_id)
        ) $charset_collate;";
		dbDelta( $sql_bookings );

		// Booking booking blocks junction table
		$table_booking_blocks = $wpdb->prefix . 'snippen_booking_booking_blocks';
		$sql_booking_blocks   = "CREATE TABLE $table_booking_blocks (
            booking_id BIGINT NOT NULL,
            booking_block_id INT NOT NULL,
            PRIMARY KEY  (booking_id, booking_block_id),
            KEY booking_id (booking_id),
            KEY booking_block_id (booking_block_id)
        ) $charset_collate;";
		dbDelta( $sql_booking_blocks );

		// Booking booking objects junction table
		$table_booking_objects = $wpdb->prefix . 'snippen_booking_booking_objects';
		$sql_booking_objects   = "CREATE TABLE $table_booking_objects (
            booking_id BIGINT NOT NULL,
            booking_object_id INT NOT NULL,
            PRIMARY KEY  (booking_id, booking_object_id),
            KEY booking_id (booking_id),
            KEY booking_object_id (booking_object_id)
        ) $charset_collate;";
		dbDelta( $sql_booking_objects );

		// Legacy tables below for compatibility during redesign phase:

		// Time slots table
		$table_slots = $wpdb->prefix . 'snippen_time_slots';
		$sql_slots   = "CREATE TABLE $table_slots (
            id INT NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            start_time TIME DEFAULT '00:00:00',
            end_time TIME DEFAULT '23:59:59',
            cleanup_hours INT DEFAULT 0,
            days_of_week VARCHAR(50) DEFAULT NULL,
            date_start DATE DEFAULT NULL,
            date_end DATE DEFAULT NULL,
            price_id INT DEFAULT NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            modified_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL,
            PRIMARY KEY  (id),
            KEY price_id (price_id)
        ) $charset_collate;";
		dbDelta( $sql_slots );

		// Pricing table
		$table_prices = $wpdb->prefix . 'snippen_prices';
		$sql_prices   = "CREATE TABLE $table_prices (
            id INT NOT NULL AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            price DECIMAL(10,2) NOT NULL,
            priority INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            modified_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY priority (priority)
        ) $charset_collate;";
		dbDelta( $sql_prices );

		// Time slot booking objects junction table
		$table_time_slot_objects = $wpdb->prefix . 'snippen_time_slot_booking_objects';
		$sql_time_slot_objects   = "CREATE TABLE $table_time_slot_objects (
            id INT NOT NULL AUTO_INCREMENT,
            time_slot_id INT NOT NULL,
            booking_object_id INT NOT NULL,
            PRIMARY KEY  (id),
            KEY time_slot_id (time_slot_id),
            KEY booking_object_id (booking_object_id),
            UNIQUE KEY unique_time_slot_object (time_slot_id, booking_object_id)
        ) $charset_collate;";
		dbDelta( $sql_time_slot_objects );

		// Booking objects junction table (many-to-many relationship)
		$table_bookings_booking_objects = $wpdb->prefix . 'snippen_bookings_booking_objects';
		$sql_bookings_booking_objects   = "CREATE TABLE $table_bookings_booking_objects (
            id INT NOT NULL AUTO_INCREMENT,
            booking_id BIGINT NOT NULL,
            booking_object_id INT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            modified_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY booking_id (booking_id),
            KEY booking_object_id (booking_object_id),
            UNIQUE KEY unique_booking_object (booking_id, booking_object_id)
        ) $charset_collate;";
		dbDelta( $sql_bookings_booking_objects );

		// Run migrations
		MigrationManager::run();

		// Register plugin capabilities
		// NOTE: Capabilities are NOT automatically assigned to any role.
		// Site administrators must manually assign them to roles via admin UI.
		self::register_capabilities();

		// Register custom resident role (Issue #37)
		$subscriber   = get_role( 'subscriber' );
		$capabilities = $subscriber ? $subscriber->capabilities : array( 'read' => true );
		add_role( 'snippen_resident', __( 'Snippen Beboer', 'snippen-booking' ), $capabilities );
	}

	/**
	 * Register plugin-specific capabilities.
	 *
	 * This method makes plugin capabilities available in WordPress.
	 * Capabilities are NOT automatically assigned to any role - site administrators
	 * must manually assign them to roles via the admin UI or programmatically.
	 *
	 * @return void
	 */
	private static function register_capabilities() {
		// Plugin capabilities are defined in the Capabilities helper class.
		// They can be assigned to roles via WordPress admin UI or programmatically.
		// This is a documentation point - capabilities are defined in Capabilities::get_all_capabilities()
	}
}
