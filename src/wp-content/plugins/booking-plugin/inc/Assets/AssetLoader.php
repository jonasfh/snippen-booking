<?php

namespace SnippenBooking\Assets;

/**
 * Handles script and style enqueuing
 */
class AssetLoader {

	/**
	 * Get the plugin directory URL
	 *
	 * @return string
	 */
	private static function get_plugin_dir_url() {
		return plugin_dir_url( dirname( dirname( __DIR__ ) ) . '/booking-plugin.php' );
	}

	/**
	 * Enqueue scripts and styles
	 */
	public static function enqueue() {
		wp_enqueue_style(
			'snippen-booking-style',
			self::get_plugin_dir_url() . 'css/booking.css',
			array(),
			SNIPPEN_BOOKING_VERSION
		);

		wp_enqueue_script(
			'snippen-booking-script',
			self::get_plugin_dir_url() . 'js/booking.js',
			array( 'jquery' ),
			SNIPPEN_BOOKING_VERSION,
			true
		);

		wp_localize_script(
			'snippen-booking-script',
			'snippenBookingAjax',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'snippen_booking_nonce' ),
			)
		);
	}
}
