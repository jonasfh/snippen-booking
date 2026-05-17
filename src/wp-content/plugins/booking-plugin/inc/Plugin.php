<?php

namespace SnippenBooking;

use SnippenBooking\Assets\AssetLoader;
use SnippenBooking\Database\Install;
use SnippenBooking\Shortcode\BookingShortcode;
use SnippenBooking\Api\AvailabilityApi;
use SnippenBooking\Api\BookingApi;
use SnippenBooking\Admin\AdminLoader;

/**
 * Main plugin class - bootstrapper
 */
class Plugin {

	/**
	 * Initialize the plugin
	 */
	public static function init() {
		// Register activation hook
		register_activation_hook( dirname( __DIR__ ) . '/booking-plugin.php', array( __CLASS__, 'activate' ) );

		// Hook into WordPress init
		add_action( 'init', array( __CLASS__, 'register_hooks' ) );
		add_action( 'admin_init', array( __CLASS__, 'check_for_updates' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Handle plugin activation
	 */
	public static function activate() {
		Install::activate();
	}

	/**
	 * Register all hooks
	 */
	public static function register_hooks() {
		BookingShortcode::register();
		AvailabilityApi::register();
		BookingApi::register();
		AdminLoader::register();
		\SnippenBooking\Api\BookingActionsApi::register();
		\SnippenBooking\Api\UserApi::register();
		\SnippenBooking\Shortcode\AccountConfirmationShortcode::register();

		// Allow tagging pages (required for issue #25)
		register_taxonomy_for_object_type( 'post_tag', 'page' );
	}

	/**
	 * Enqueue assets
	 */
	public static function enqueue_assets() {
		AssetLoader::enqueue();
	}

	/**
	 * Check for database updates
	 */
	public static function check_for_updates() {
		\SnippenBooking\Database\MigrationManager::run();
	}
}
