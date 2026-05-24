<?php

namespace SnippenBooking\Database\Migrations;

/**
 * Migration for version 1.12.0
 */
class Migration_1_12_0 {

	/**
	 * Run the migration
	 */
	public function up() {
		global $wp_roles;
		if ( ! isset( $wp_roles ) ) {
			$wp_roles = new \WP_Roles();
		}

		$old_role = get_role( 'holmen_resident' );
		if ( $old_role ) {
			// Create the new role with the same capabilities
			add_role( 'snippen_resident', __( 'Snippen Beboer', 'snippen-booking' ), $old_role->capabilities );

			// Migrate all users
			$users = get_users(
				array(
					'role'   => 'holmen_resident',
					'fields' => 'ID',
				)
			);

			foreach ( $users as $user_id ) {
				$user = new \WP_User( $user_id );
				$user->add_role( 'snippen_resident' );
				$user->remove_role( 'holmen_resident' );
			}

			// Remove the old role
			remove_role( 'holmen_resident' );
		} else {
			// Add the new role just in case it's missing entirely
			$subscriber   = get_role( 'subscriber' );
			$capabilities = $subscriber ? $subscriber->capabilities : array( 'read' => true );
			add_role( 'snippen_resident', __( 'Snippen Beboer', 'snippen-booking' ), $capabilities );
		}
	}
}
