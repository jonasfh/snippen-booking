<?php

namespace SnippenBooking\Admin\Pages;

/**
 * Admin page for managing Booking Objects
 */
class BookingObjectsPage {

	/**
	 * Render the page
	 */
	public function render() {
		$action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';
		$id     = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

		echo '<div class="snippen-booking-admin-wrap">';

		$this->render_header( $action );

		// Render success/info messages passed in query params
		if ( isset( $_GET['message'] ) ) {
			$msg_type = sanitize_text_field( $_GET['message'] );
			if ( $msg_type === 'created' ) {
				$this->show_message( __( 'Lokale lagret.', 'snippen-booking' ) );
			} elseif ( $msg_type === 'updated' ) {
				$this->show_message( __( 'Lokale oppdatert.', 'snippen-booking' ) );
			} elseif ( $msg_type === 'deleted' ) {
				$this->show_message( __( 'Lokale slettet.', 'snippen-booking' ) );
			}
		}

		switch ( $action ) {
			case 'add':
			case 'edit':
				$this->render_form( $id );
				break;
			default:
				$this->render_list();
				break;
		}

		echo '</div>';
	}

	/**
	 * Render header
	 */
	private function render_header( $action ) {
		$title = __( 'Lokaler', 'snippen-booking' );
		if ( $action === 'add' ) {
			$title = __( 'Legg til nytt lokale', 'snippen-booking' );
		}
		if ( $action === 'edit' ) {
			$title = __( 'Rediger lokale', 'snippen-booking' );
		}

		echo '<div class="snippen-admin-header">';
		echo '<h1>' . esc_html( $title ) . '</h1>';

		if ( $action === 'list' ) {
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=snippen-booking-objects&action=add' ) ) . '" class="snippen-btn snippen-btn-primary">' . esc_html__( 'Legg til ny', 'snippen-booking' ) . '</a>';
		} else {
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=snippen-booking-objects' ) ) . '" class="snippen-btn snippen-btn-outline">' . esc_html__( 'Tilbake til oversikt', 'snippen-booking' ) . '</a>';
		}

		echo '</div>';
	}

	/**
	 * Handle POST requests (Save/Delete)
	 */
	public function handle_request() {
		if ( ! isset( $_POST['snippen_object_nonce'] ) || ! wp_verify_nonce( $_POST['snippen_object_nonce'], 'snippen_save_object' ) ) {

			// Check for delete action (GET)
			if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && isset( $_GET['id'] ) ) {
				if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'delete_object_' . $_GET['id'] ) ) {
					$this->delete_object( intval( $_GET['id'] ) );
				}
			}
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'snippen_booking_objects';

		$id          = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
		$name        = sanitize_text_field( $_POST['name'] );
		$description = sanitize_textarea_field( $_POST['description'] );
		$door_code   = isset( $_POST['door_code'] ) ? sanitize_text_field( $_POST['door_code'] ) : '';

		$data = array(
			'name'        => $name,
			'description' => $description,
			'door_code'   => $door_code,
			'modified_at' => current_time( 'mysql' ),
		);

		if ( $id > 0 ) {
			// Check if door code changed
			$old_door_code = $wpdb->get_var( $wpdb->prepare( "SELECT door_code FROM $table WHERE id = %d", $id ) );

			$wpdb->update( $table, $data, array( 'id' => $id ) );

			if ( $old_door_code !== $door_code ) {
				\SnippenBooking\Service\DoorCodeService::handle_object_door_code_change( $id, $door_code );
			}

			wp_safe_redirect( admin_url( 'admin.php?page=snippen-booking-objects&action=edit&id=' . $id . '&message=updated' ) );
			exit;
		} else {
			$data['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $table, $data );
			$new_id = $wpdb->insert_id;

			if ( ! empty( $door_code ) ) {
				\SnippenBooking\Service\DoorCodeService::handle_object_door_code_change( $new_id, $door_code );
			}

			// Redirect to list
			wp_safe_redirect( admin_url( 'admin.php?page=snippen-booking-objects&message=created' ) );
			exit;
		}
	}

	/**
	 * Delete object (soft delete)
	 */
	private function delete_object( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'snippen_booking_objects';
		$wpdb->update( $table, array( 'deleted_at' => current_time( 'mysql' ) ), array( 'id' => $id ) );
		wp_safe_redirect( admin_url( 'admin.php?page=snippen-booking-objects&message=deleted' ) );
		exit;
	}

	/**
	 * Show admin message
	 */
	private function show_message( $message ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Render list table
	 */
	private function render_list() {
		global $wpdb;
		$table             = $wpdb->prefix . 'snippen_booking_objects';
		$objects           = $wpdb->get_results( "SELECT * FROM $table WHERE deleted_at IS NULL ORDER BY name ASC" );
		$door_code_enabled = \SnippenBooking\Service\DoorCodeService::is_enabled();

		echo '<div class="snippen-card">';
		echo '<table class="snippen-list-table">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Navn', 'snippen-booking' ) . '</th>';
		echo '<th>' . esc_html__( 'Beskrivelse', 'snippen-booking' ) . '</th>';
		if ( $door_code_enabled ) {
			echo '<th>' . esc_html__( 'Dørkode', 'snippen-booking' ) . '</th>';
		}
		echo '<th style="text-align:right;">' . esc_html__( 'Handlinger', 'snippen-booking' ) . '</th>';
		echo '</tr></thead>';
		echo '<tbody>';

		if ( empty( $objects ) ) {
			$colspan = $door_code_enabled ? 4 : 3;
			echo '<tr><td colspan="' . $colspan . '">' . esc_html__( 'Ingen lokaler funnet.', 'snippen-booking' ) . '</td></tr>';
		} else {
			foreach ( $objects as $obj ) {
				$edit_url   = admin_url( 'admin.php?page=snippen-booking-objects&action=edit&id=' . $obj->id );
				$delete_url = wp_nonce_url( admin_url( 'admin.php?page=snippen-booking-objects&action=delete&id=' . $obj->id ), 'delete_object_' . $obj->id );

				echo '<tr>';
				echo '<td><strong><a href="' . esc_url( $edit_url ) . '">' . esc_html( $obj->name ) . '</a></strong></td>';
				echo '<td>' . esc_html( wp_trim_words( $obj->description, 10 ) ) . '</td>';
				if ( $door_code_enabled ) {
					echo '<td>' . esc_html( $obj->door_code ?: '-' ) . '</td>';
				}
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
	 * Render Add/Edit Form
	 */
	private function render_form( $id = 0 ) {
		global $wpdb;
		$table  = $wpdb->prefix . 'snippen_booking_objects';
		$object = null;

		if ( $id > 0 ) {
			$object = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );
		}

		echo '<div class="snippen-card"><form method="post" action="">';
		wp_nonce_field( 'snippen_save_object', 'snippen_object_nonce' );
		echo '<input type="hidden" name="id" value="' . esc_attr( $id ) . '">';

		echo '<div class="snippen-form-group">';
		echo '<label for="name">' . esc_html__( 'Navn på lokale', 'snippen-booking' ) . '</label>';
		echo '<input type="text" name="name" id="name" value="' . esc_attr( $object ? $object->name : '' ) . '" required class="regular-text">';
		echo '</div>';

		echo '<div class="snippen-form-group">';
		echo '<label for="description">' . esc_html__( 'Beskrivelse', 'snippen-booking' ) . '</label>';
		echo '<textarea name="description" id="description" rows="5" class="large-text">' . esc_textarea( $object ? $object->description : '' ) . '</textarea>';
		echo '</div>';

		if ( \SnippenBooking\Service\DoorCodeService::is_enabled() ) {
			echo '<div class="snippen-form-group">';
			echo '<label for="door_code">' . esc_html__( 'Gjeldende dørkode', 'snippen-booking' ) . '</label>';
			echo '<input type="text" name="door_code" id="door_code" value="' . esc_attr( $object ? $object->door_code : '' ) . '" class="regular-text">';
			echo '<p class="description">' . esc_html__( 'Denne koden vil automatisk bli synkronisert til brukernes bookinger når bookingtiden nærmer seg.', 'snippen-booking' ) . '</p>';
			echo '</div>';
		}

		echo '<div class="snippen-form-actions">';
		echo '<button type="submit" class="snippen-btn snippen-btn-primary">' . esc_html__( 'Lagre lokale', 'snippen-booking' ) . '</button>';
		echo '</div>';

		echo '</form></div>';
	}
}
