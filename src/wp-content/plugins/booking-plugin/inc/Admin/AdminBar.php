<?php

namespace SnippenBooking\Admin;

use SnippenBooking\Database\Repository\BookingRepository;
use SnippenBooking\Helper\Capabilities;

/**
 * Handles WordPress Admin Bar integration and shortcuts for booking administrators.
 */
class AdminBar {

	/**
	 * Transient key for caching pending bookings count.
	 */
	const TRANSIENT_KEY = 'booking_nye_antall';

	/**
	 * Cache expiration in seconds (5 minutes).
	 */
	const CACHE_EXPIRATION = 300;

	/**
	 * Admin bar node ID.
	 */
	const NODE_ID = 'hurtiglenke-booking';

	/**
	 * Register hooks for Admin Bar and navigation redirects.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'admin_bar_menu', array( __CLASS__, 'add_admin_bar_node' ), 100 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_redirect_legacy_slug' ) );
	}

	/**
	 * Redirect legacy or requested 'booking-oversikt' slug to canonical 'snippen-booking'.
	 *
	 * @return void
	 */
	public static function maybe_redirect_legacy_slug() {
		if ( isset( $_GET['page'] ) && 'booking-oversikt' === $_GET['page'] ) {
			if ( wp_safe_redirect( admin_url( 'admin.php?page=snippen-booking' ) ) ) {
				exit;
			}
		}
	}

	/**
	 * Retrieve the count of pending bookings, utilizing Transient caching.
	 *
	 * @param bool $force_refresh Whether to bypass cache.
	 * @return int Number of pending bookings.
	 */
	public static function get_pending_count( $force_refresh = false ) {
		if ( ! $force_refresh ) {
			$cached = get_transient( self::TRANSIENT_KEY );
			if ( false !== $cached ) {
				return (int) $cached;
			}
		}

		$repository = new BookingRepository();
		$count      = $repository->count_pending();

		set_transient( self::TRANSIENT_KEY, $count, self::CACHE_EXPIRATION );

		return (int) $count;
	}

	/**
	 * Invalidate pending bookings count cache.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function delete_count_cache() {
		return delete_transient( self::TRANSIENT_KEY );
	}

	/**
	 * Add booking shortcut node with dynamic notification badge to Admin Bar.
	 *
	 * @param \WP_Admin_Bar $admin_bar WordPress admin bar instance.
	 * @return void
	 */
	public static function add_admin_bar_node( $admin_bar ) {
		if ( ! is_object( $admin_bar ) || ! method_exists( $admin_bar, 'add_node' ) ) {
			return;
		}

		// 1. Role / capability check: Only accessible for booking administrators or full admins.
		if ( ! current_user_can( Capabilities::MANAGE_BOOKINGS ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// 2. Fetch pending bookings count (cached in transient).
		$nye_bookinger = self::get_pending_count();

		// 3. Build title with optional notification badge.
		$title = '📅 ' . __( 'Reservasjoner', 'snippen-booking' );
		if ( $nye_bookinger > 0 ) {
			$title .= sprintf(
				' <span class="update-plugins count-%1$d"><span class="plugin-count">%1$d</span></span>',
				$nye_bookinger
			);
		}

		// 4. Register node in Admin Bar.
		$url = apply_filters( 'snippen_booking_admin_bar_url', admin_url( 'admin.php?page=snippen-booking' ) );

		$admin_bar->add_node(
			array(
				'id'    => self::NODE_ID,
				'title' => $title,
				'href'  => $url,
				'meta'  => array(
					'title' => __( 'Gå direkte til reservasjonslisten', 'snippen-booking' ),
				),
			)
		);
	}
}
