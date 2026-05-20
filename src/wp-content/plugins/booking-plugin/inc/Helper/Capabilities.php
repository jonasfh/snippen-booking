<?php

namespace SnippenBooking\Helper;

/**
 * Capabilities helper class.
 *
 * Manages plugin-specific WordPress capabilities and provides
 * centralized helper methods for capability checks.
 *
 * @package SnippenBooking
 */
class Capabilities {

	/**
	 * Capability to manage bookings (create, edit, delete, view).
	 */
	const MANAGE_BOOKINGS = 'manage_snippen_bookings';

	/**
	 * Capability to manage plugin settings.
	 */
	const MANAGE_SETTINGS = 'manage_snippen_settings';

	/**
	 * Capability to view booking reports and statistics.
	 */
	const VIEW_REPORTS = 'view_snippen_reports';

	/**
	 * Get all plugin capabilities.
	 *
	 * @return array Array of capability strings.
	 */
	public static function get_all_capabilities() {
		return array(
			self::MANAGE_BOOKINGS,
			self::MANAGE_SETTINGS,
			self::VIEW_REPORTS,
		);
	}

	/**
	 * Check if current user can manage bookings.
	 *
	 * @return bool True if user can manage bookings, false otherwise.
	 */
	public static function can_manage_bookings() {
		return current_user_can( self::MANAGE_BOOKINGS );
	}

	/**
	 * Check if current user can manage settings.
	 *
	 * @return bool True if user can manage settings, false otherwise.
	 */
	public static function can_manage_settings() {
		return current_user_can( self::MANAGE_SETTINGS );
	}

	/**
	 * Check if current user can view reports.
	 *
	 * @return bool True if user can view reports, false otherwise.
	 */
	public static function can_view_reports() {
		return current_user_can( self::VIEW_REPORTS );
	}

	/**
	 * Add capabilities to a role.
	 *
	 * @param \WP_Role $role  The WordPress role object.
	 * @param array    $capabilities Optional. Array of capability strings to add.
	 *                              If empty, adds all plugin capabilities.
	 *
	 * @return void
	 */
	public static function add_to_role( $role, $capabilities = array() ) {
		if ( ! is_a( $role, '\WP_Role' ) ) {
			return;
		}

		if ( empty( $capabilities ) ) {
			$capabilities = self::get_all_capabilities();
		}

		foreach ( $capabilities as $cap ) {
			$role->add_cap( $cap );
		}
	}

	/**
	 * Remove capabilities from a role.
	 *
	 * @param \WP_Role $role  The WordPress role object.
	 * @param array    $capabilities Optional. Array of capability strings to remove.
	 *                              If empty, removes all plugin capabilities.
	 *
	 * @return void
	 */
	public static function remove_from_role( $role, $capabilities = array() ) {
		if ( ! is_a( $role, '\WP_Role' ) ) {
			return;
		}

		if ( empty( $capabilities ) ) {
			$capabilities = self::get_all_capabilities();
		}

		foreach ( $capabilities as $cap ) {
			$role->remove_cap( $cap );
		}
	}
}
