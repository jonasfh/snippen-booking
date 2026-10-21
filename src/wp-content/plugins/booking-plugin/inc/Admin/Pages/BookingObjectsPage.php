<?php
/**
 * Admin page for managing Booking Objects and Door Locks
 *
 * @package SnippenBooking\Admin\Pages
 */

namespace SnippenBooking\Admin\Pages;

use SnippenBooking\Database\Repository\DoorLockRepository;

/**
 * Class BookingObjectsPage
 */
class BookingObjectsPage {

	/**
	 * Render the page
	 */
	public function render() {
		$tab    = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'objects';
		$action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';
		$id     = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

		echo '<div class="snippen-booking-admin-wrap">';

		$this->render_header( $tab, $action );

		// Render success/info messages passed in query params
		if ( isset( $_GET['message'] ) ) {
			$msg_type = sanitize_text_field( $_GET['message'] );
			switch ( $msg_type ) {
				case 'created':
					$this->show_message( __( 'Lokale lagret.', 'snippen-booking' ) );
					break;
				case 'updated':
					$this->show_message( __( 'Lokale oppdatert.', 'snippen-booking' ) );
					break;
				case 'deleted':
					$this->show_message( __( 'Lokale slettet.', 'snippen-booking' ) );
					break;
				case 'lock_created':
					$this->show_message( __( 'Dørlås opprettet.', 'snippen-booking' ) );
					break;
				case 'lock_updated':
					$this->show_message( __( 'Dørlås oppdatert.', 'snippen-booking' ) );
					break;
				case 'lock_deleted':
					$this->show_message( __( 'Dørlås slettet.', 'snippen-booking' ) );
					break;
			}
		}

		if ( 'locks' === $tab ) {
			switch ( $action ) {
				case 'add':
				case 'edit':
					$this->render_lock_form( $id );
					break;
				default:
					$this->render_lock_list();
					break;
			}
		} else {
			switch ( $action ) {
				case 'add':
				case 'edit':
					$this->render_form( $id );
					break;
				default:
					$this->render_list();
					break;
			}
		}

		echo '</div>';
	}

	/**
	 * Render header and navigation tabs
	 *
	 * @param string $tab Active tab.
	 * @param string $action Current action.
	 */
	private function render_header( $tab, $action ) {
		$title = __( 'Lokaler og Adgangskontroll', 'snippen-booking' );
		if ( 'locks' === $tab ) {
			if ( 'add' === $action ) {
				$title = __( 'Legg til ny dørlås', 'snippen-booking' );
			} elseif ( 'edit' === $action ) {
				$title = __( 'Rediger dørlås', 'snippen-booking' );
			} else {
				$title = __( 'Dørlåser (Smartlås / Yale Doorman)', 'snippen-booking' );
			}
		} else {
			if ( 'add' === $action ) {
				$title = __( 'Legg til nytt lokale', 'snippen-booking' );
			} elseif ( 'edit' === $action ) {
				$title = __( 'Rediger lokale', 'snippen-booking' );
			} else {
				$title = __( 'Lokaler', 'snippen-booking' );
			}
		}

		echo '<div class="snippen-admin-header">';
		echo '<h1>' . esc_html( $title ) . '</h1>';

		if ( 'locks' === $tab ) {
			if ( 'list' === $action ) {
				echo '<a href="' . esc_url( admin_url( 'admin.php?page=snippen-booking-objects&tab=locks&action=add' ) ) . '" class="snippen-btn snippen-btn-primary">' . esc_html__( 'Legg til ny dørlås', 'snippen-booking' ) . '</a>';
			} else {
				echo '<a href="' . esc_url( admin_url( 'admin.php?page=snippen-booking-objects&tab=locks' ) ) . '" class="snippen-btn snippen-btn-outline">' . esc_html__( 'Tilbake til dørlåser', 'snippen-booking' ) . '</a>';
			}
		} else {
			if ( 'list' === $action ) {
				echo '<a href="' . esc_url( admin_url( 'admin.php?page=snippen-booking-objects&action=add' ) ) . '" class="snippen-btn snippen-btn-primary">' . esc_html__( 'Legg til nytt lokale', 'snippen-booking' ) . '</a>';
			} else {
				echo '<a href="' . esc_url( admin_url( 'admin.php?page=snippen-booking-objects' ) ) . '" class="snippen-btn snippen-btn-outline">' . esc_html__( 'Tilbake til lokaler', 'snippen-booking' ) . '</a>';
			}
		}

		echo '</div>';

		// Render navigation tabs
		$objects_url = admin_url( 'admin.php?page=snippen-booking-objects' );
		$locks_url   = admin_url( 'admin.php?page=snippen-booking-objects&tab=locks' );
		echo '<h2 class="nav-tab-wrapper" style="margin-bottom:20px;">';
		echo '<a href="' . esc_url( $objects_url ) . '" class="nav-tab ' . ( 'locks' !== $tab ? 'nav-tab-active' : '' ) . '">' . esc_html__( 'Lokaler', 'snippen-booking' ) . '</a>';
		echo '<a href="' . esc_url( $locks_url ) . '" class="nav-tab ' . ( 'locks' === $tab ? 'nav-tab-active' : '' ) . '">' . esc_html__( 'Dørlåser', 'snippen-booking' ) . '</a>';
		echo '</h2>';
	}

	/**
	 * Handle POST requests (Save/Delete for objects and locks)
	 */
	public function handle_request() {
		// 1. Handle Door Lock Actions
		if ( isset( $_POST['snippen_lock_nonce'] ) && wp_verify_nonce( $_POST['snippen_lock_nonce'], 'snippen_save_lock' ) ) {
			$lock_repo   = new DoorLockRepository();
			$lock_id     = isset( $_POST['lock_id'] ) ? intval( $_POST['lock_id'] ) : 0;
			$name        = sanitize_text_field( $_POST['name'] ?? '' );
			$description = isset( $_POST['description'] ) ? sanitize_textarea_field( $_POST['description'] ) : null;

			if ( $lock_id > 0 ) {
				$lock_repo->update(
					$lock_id,
					array(
						'name'        => $name,
						'description' => $description,
					)
				);
				wp_safe_redirect( admin_url( 'admin.php?page=snippen-booking-objects&tab=locks&message=lock_updated' ) );
				exit;
			} else {
				$lock_repo->create(
					array(
						'name'        => $name,
						'description' => $description,
					)
				);
				wp_safe_redirect( admin_url( 'admin.php?page=snippen-booking-objects&tab=locks&message=lock_created' ) );
				exit;
			}
		}

		// Check for lock delete action (GET)
		if ( isset( $_GET['tab'] ) && 'locks' === $_GET['tab'] && isset( $_GET['action'] ) && 'delete' === $_GET['action'] && isset( $_GET['id'] ) ) {
			$del_id = intval( $_GET['id'] );
			if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'delete_lock_' . $del_id ) ) {
				$lock_repo = new DoorLockRepository();
				$lock_repo->delete( $del_id );
				wp_safe_redirect( admin_url( 'admin.php?page=snippen-booking-objects&tab=locks&message=lock_deleted' ) );
				exit;
			}
		}

		// 2. Handle Booking Object Actions
		if ( ! isset( $_POST['snippen_object_nonce'] ) || ! wp_verify_nonce( $_POST['snippen_object_nonce'], 'snippen_save_object' ) ) {
			// Check for object delete action (GET)
			if ( isset( $_GET['action'] ) && 'delete' === $_GET['action'] && isset( $_GET['id'] ) ) {
				if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'delete_object_' . $_GET['id'] ) ) {
					$this->delete_object( intval( $_GET['id'] ) );
				}
			}
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'snippen_booking_objects';

		$id           = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
		$name         = sanitize_text_field( $_POST['name'] ?? '' );
		$description  = sanitize_textarea_field( $_POST['description'] ?? '' );
		$door_lock_id = ! empty( $_POST['door_lock_id'] ) ? intval( $_POST['door_lock_id'] ) : null;

		$data = array(
			'name'         => $name,
			'description'  => $description,
			'door_lock_id' => $door_lock_id,
			'modified_at'  => current_time( 'mysql' ),
		);

		if ( $id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->update( $table, $data, array( 'id' => $id ) );
			wp_safe_redirect( admin_url( 'admin.php?page=snippen-booking-objects&message=updated' ) );
			exit;
		} else {
			$data['created_at'] = current_time( 'mysql' );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert( $table, $data );
			wp_safe_redirect( admin_url( 'admin.php?page=snippen-booking-objects&message=created' ) );
			exit;
		}
	}

	/**
	 * Delete object (soft delete)
	 *
	 * @param int $id Object ID.
	 */
	private function delete_object( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'snippen_booking_objects';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->update( $table, array( 'deleted_at' => current_time( 'mysql' ) ), array( 'id' => $id ) );
		wp_safe_redirect( admin_url( 'admin.php?page=snippen-booking-objects&message=deleted' ) );
		exit;
	}

	/**
	 * Show admin message
	 *
	 * @param string $message Notice message.
	 */
	private function show_message( $message ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Render list table for booking objects
	 */
	private function render_list() {
		global $wpdb;
		$table_objects = $wpdb->prefix . 'snippen_booking_objects';
		$table_locks   = $wpdb->prefix . 'snippen_door_locks';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$objects = $wpdb->get_results(
			"SELECT o.*, l.name AS lock_name 
			 FROM $table_objects o 
			 LEFT JOIN $table_locks l ON o.door_lock_id = l.id AND l.deleted_at IS NULL
			 WHERE o.deleted_at IS NULL 
			 ORDER BY o.name ASC"
		);

		echo '<div class="snippen-card">';
		echo '<table class="snippen-list-table">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Navn', 'snippen-booking' ) . '</th>';
		echo '<th>' . esc_html__( 'Beskrivelse', 'snippen-booking' ) . '</th>';
		echo '<th>' . esc_html__( 'Tilknyttet dørlås', 'snippen-booking' ) . '</th>';
		echo '<th style="text-align:right;">' . esc_html__( 'Handlinger', 'snippen-booking' ) . '</th>';
		echo '</tr></thead>';
		echo '<tbody>';

		if ( empty( $objects ) ) {
			echo '<tr><td colspan="4">' . esc_html__( 'Ingen lokaler funnet.', 'snippen-booking' ) . '</td></tr>';
		} else {
			foreach ( $objects as $obj ) {
				$edit_url   = admin_url( 'admin.php?page=snippen-booking-objects&action=edit&id=' . $obj->id );
				$delete_url = wp_nonce_url( admin_url( 'admin.php?page=snippen-booking-objects&action=delete&id=' . $obj->id ), 'delete_object_' . $obj->id );
				$lock_text  = ! empty( $obj->lock_name ) ? esc_html( $obj->lock_name ) : '<span style="color:#888;">' . esc_html__( 'Ingen smartlås', 'snippen-booking' ) . '</span>';

				echo '<tr>';
				echo '<td><strong><a href="' . esc_url( $edit_url ) . '">' . esc_html( $obj->name ) . '</a></strong></td>';
				echo '<td>' . esc_html( wp_trim_words( $obj->description, 10 ) ) . '</td>';
				echo '<td>' . $lock_text . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '<td style="text-align:right;">';
				echo '<a href="' . esc_url( $edit_url ) . '" class="snippen-btn snippen-btn-outline" style="margin-right:5px;">' . esc_html__( 'Rediger', 'snippen-booking' ) . '</a>';
				echo '<a href="' . esc_url( $delete_url ) . '" class="snippen-btn snippen-btn-outline snippen-btn-danger snippen-delete-confirm">' . esc_html__( 'Slett', 'snippen-booking' ) . '</a>';
				echo '</td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table></div>';
	}

	/**
	 * Render Add/Edit Form for Booking Object
	 *
	 * @param int $id Object ID.
	 */
	private function render_form( $id = 0 ) {
		global $wpdb;
		$table  = $wpdb->prefix . 'snippen_booking_objects';
		$object = null;

		if ( $id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$object = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );
		}

		$lock_repo = new DoorLockRepository();
		$locks     = $lock_repo->find_all();

		echo '<div class="snippen-card"><form method="post" action="">';
		wp_nonce_field( 'snippen_save_object', 'snippen_object_nonce' );
		echo '<input type="hidden" name="id" value="' . esc_attr( $id ) . '">';

		echo '<div class="snippen-form-group">';
		echo '<label for="name">' . esc_html__( 'Navn på lokale', 'snippen-booking' ) . '</label>';
		echo '<input type="text" name="name" id="name" value="' . esc_attr( $object ? $object->name : '' ) . '" required class="regular-text">';
		echo '</div>';

		echo '<div class="snippen-form-group">';
		echo '<label for="door_lock_id">' . esc_html__( 'Tilknyttet dørlås / adgangskontroll', 'snippen-booking' ) . '</label>';
		echo '<select name="door_lock_id" id="door_lock_id" class="regular-text">';
		echo '<option value="">' . esc_html__( 'Ingen smartlås', 'snippen-booking' ) . '</option>';
		foreach ( $locks as $lock ) {
			$selected = ( $object && (int) $object->door_lock_id === (int) $lock->id ) ? ' selected' : '';
			echo '<option value="' . esc_attr( $lock->id ) . '"' . $selected . '>' . esc_html( $lock->name ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Velg hvilken fysisk lås dette lokalet er tilknyttet (for automatisk adgangskontroll via Yale Doorman API).', 'snippen-booking' ) . '</p>';
		echo '</div>';

		echo '<div class="snippen-form-group">';
		echo '<label for="description">' . esc_html__( 'Beskrivelse', 'snippen-booking' ) . '</label>';
		echo '<textarea name="description" id="description" rows="5" class="large-text">' . esc_textarea( $object ? $object->description : '' ) . '</textarea>';
		echo '</div>';

		echo '<div class="snippen-form-actions">';
		echo '<button type="submit" class="snippen-btn snippen-btn-primary">' . esc_html__( 'Lagre lokale', 'snippen-booking' ) . '</button>';
		echo '</div>';

		echo '</form></div>';
	}

	/**
	 * Render list table for door locks
	 */
	private function render_lock_list() {
		$lock_repo = new DoorLockRepository();
		$locks     = $lock_repo->find_all_with_objects();

		echo '<div class="snippen-card">';
		echo '<table class="snippen-list-table">';
		echo '<thead><tr>';
		echo '<th style="width:60px;">' . esc_html__( 'ID', 'snippen-booking' ) . '</th>';
		echo '<th>' . esc_html__( 'Navn på dørlås', 'snippen-booking' ) . '</th>';
		echo '<th>' . esc_html__( 'Betjener lokaler', 'snippen-booking' ) . '</th>';
		echo '<th style="text-align:right;">' . esc_html__( 'Handlinger', 'snippen-booking' ) . '</th>';
		echo '</tr></thead>';
		echo '<tbody>';

		if ( empty( $locks ) ) {
			echo '<tr><td colspan="4">' . esc_html__( 'Ingen dørlåser funnet.', 'snippen-booking' ) . '</td></tr>';
		} else {
			foreach ( $locks as $lock ) {
				$lock_id    = (int) $lock['id'];
				$edit_url   = admin_url( 'admin.php?page=snippen-booking-objects&tab=locks&action=edit&id=' . $lock_id );
				$delete_url = wp_nonce_url( admin_url( 'admin.php?page=snippen-booking-objects&tab=locks&action=delete&id=' . $lock_id ), 'delete_lock_' . $lock_id );

				$handled_names = array_column( $lock['handles_objects'], 'name' );
				$venues_text   = ! empty( $handled_names ) ? esc_html( implode( ', ', $handled_names ) ) : '<span style="color:#888;">' . esc_html__( 'Ingen lokaler tilknyttet', 'snippen-booking' ) . '</span>';

				echo '<tr>';
				echo '<td>#' . esc_html( $lock_id ) . '</td>';
				echo '<td><strong><a href="' . esc_url( $edit_url ) . '">' . esc_html( $lock['name'] ) . '</a></strong></td>';
				echo '<td>' . $venues_text . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '<td style="text-align:right;">';
				echo '<a href="' . esc_url( $edit_url ) . '" class="snippen-btn snippen-btn-outline" style="margin-right:5px;">' . esc_html__( 'Rediger', 'snippen-booking' ) . '</a>';
				echo '<a href="' . esc_url( $delete_url ) . '" class="snippen-btn snippen-btn-outline snippen-btn-danger snippen-delete-confirm">' . esc_html__( 'Slett', 'snippen-booking' ) . '</a>';
				echo '</td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table></div>';
	}

	/**
	 * Render Add/Edit Form for Door Lock
	 *
	 * @param int $id Door lock ID.
	 */
	private function render_lock_form( $id = 0 ) {
		$lock_repo = new DoorLockRepository();
		$lock      = $id > 0 ? $lock_repo->find( $id ) : null;

		echo '<div class="snippen-card"><form method="post" action="">';
		wp_nonce_field( 'snippen_save_lock', 'snippen_lock_nonce' );
		echo '<input type="hidden" name="lock_id" value="' . esc_attr( $id ) . '">';

		echo '<div class="snippen-form-group">';
		echo '<label for="lock_name">' . esc_html__( 'Navn på dørlås', 'snippen-booking' ) . '</label>';
		echo '<input type="text" name="name" id="lock_name" value="' . esc_attr( $lock ? $lock->name : '' ) . '" required class="regular-text" placeholder="' . esc_attr__( 'F.eks. Hovedinngang, Badstuedør...', 'snippen-booking' ) . '">';
		echo '</div>';

		echo '<div class="snippen-form-group">';
		echo '<label for="lock_description">' . esc_html__( 'Beskrivelse / plassering', 'snippen-booking' ) . '</label>';
		echo '<textarea name="description" id="lock_description" rows="4" class="large-text">' . esc_textarea( $lock ? ( $lock->description ?? '' ) : '' ) . '</textarea>';
		echo '</div>';

		echo '<div class="snippen-form-actions">';
		echo '<button type="submit" class="snippen-btn snippen-btn-primary">' . esc_html__( 'Lagre dørlås', 'snippen-booking' ) . '</button>';
		echo '</div>';

		echo '</form></div>';
	}
}
