<?php
/**
 * Uninstall plugin routine.
 *
 * @package SnippenBooking
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Check if data should be preserved.
$preserve_data = get_option( 'snippen_preserve_data_on_uninstall', 'no' );
if ( 'yes' === $preserve_data ) {
	return;
}

global $wpdb;

// 1. Drop plugin tables.
$tables = array(
	$wpdb->prefix . 'snippen_booking_objects',
	$wpdb->prefix . 'snippen_time_slots',
	$wpdb->prefix . 'snippen_bookings',
	$wpdb->prefix . 'snippen_bookings_booking_objects',
	$wpdb->prefix . 'snippen_prices',
	$wpdb->prefix . 'snippen_price_booking_objects',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
}

// 2. Delete options and transients.
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", 'snippen\_%', '_transient_%snippen\_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

// 3. Delete user meta.
if ( 'keep_usermeta' !== $preserve_data ) {
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s", 'snippen\_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
}

// 4. Remove plugin capabilities from all roles.
require_once __DIR__ . '/inc/Helper/Capabilities.php';
$capabilities = \SnippenBooking\Helper\Capabilities::get_all_capabilities();

$roles_obj = wp_roles();
foreach ( $roles_obj->roles as $role_name => $role_info ) {
	$role_obj = $roles_obj->get_role( $role_name );
	if ( $role_obj ) {
		foreach ( $capabilities as $cap ) {
			$role_obj->remove_cap( $cap );
		}
	}
}

// 5. Remove custom role.
if ( 'keep_usermeta' !== $preserve_data ) {
	remove_role( 'snippen_resident' );
}

// 6. Clear scheduled cron jobs.
// We clear any scheduled cron jobs related to the plugin.
// Currently there are none, but we add a generic pattern for the future.
$crons = _get_cron_array();
if ( is_array( $crons ) ) {
	foreach ( $crons as $timestamp => $cronhooks ) {
		foreach ( $cronhooks as $hook => $keys ) {
			if ( strpos( $hook, 'snippen_' ) === 0 ) {
				wp_clear_scheduled_hook( $hook );
			}
		}
	}
}
