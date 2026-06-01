<?php

namespace SnippenBooking\Admin\Pages;

/**
 * Admin page for managing Time Slots
 */
class TimeSlotsPage {

	/**
	 * Render the page
	 */
	public function render() {
		$action        = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';
		$id            = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
		$object_filter = isset( $_GET['object_id'] ) ? intval( $_GET['object_id'] ) : 0;

		echo '<div class="snippen-booking-admin-wrap">';

		$this->render_header( $action );

		// Render success/info messages passed in query params
		if ( isset( $_GET['message'] ) ) {
			$msg_type = sanitize_text_field( $_GET['message'] );
			if ( $msg_type === 'created' ) {
				$this->show_message( __( 'Tidsluke lagret.', 'snippen-booking' ) );
			} elseif ( $msg_type === 'updated' ) {
				$this->show_message( __( 'Tidsluke oppdatert.', 'snippen-booking' ) );
			} elseif ( $msg_type === 'deleted' ) {
				$this->show_message( __( 'Tidsluke slettet.', 'snippen-booking' ) );
			}
		}

		switch ( $action ) {
			case 'add':
			case 'edit':
				$this->render_form( $id );
				break;
			default:
				$this->render_list( $object_filter );
				break;
		}

		echo '</div>';
	}

	/**
	 * Render header
	 */
	private function render_header( $action ) {
		$title = __( 'Tidsluker', 'snippen-booking' );
		if ( $action === 'add' ) {
			$title = __( 'Legg til ny tidsluke', 'snippen-booking' );
		}
		if ( $action === 'edit' ) {
			$title = __( 'Rediger tidsluke', 'snippen-booking' );
		}

		echo '<div class="snippen-admin-header">';
		echo '<h1>' . esc_html( $title ) . '</h1>';

		if ( $action === 'list' ) {
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=snippen-booking-slots&action=add' ) ) . '" class="snippen-btn snippen-btn-primary">' . esc_html__( 'Legg til ny', 'snippen-booking' ) . '</a>';
		} else {
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=snippen-booking-slots' ) ) . '" class="snippen-btn snippen-btn-outline">' . esc_html__( 'Tilbake til oversikt', 'snippen-booking' ) . '</a>';
		}

		echo '</div>';
	}

	/**
	 * Handle POST requests (Save/Delete)
	 */
	public function handle_request() {
		if ( ! isset( $_POST['snippen_slot_nonce'] ) || ! wp_verify_nonce( $_POST['snippen_slot_nonce'], 'snippen_save_slot' ) ) {
			if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && isset( $_GET['id'] ) ) {
				if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'delete_slot_' . $_GET['id'] ) ) {
					$this->delete_slot( intval( $_GET['id'] ) );
				}
			}
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'snippen_time_slots';

		$id                 = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
		$name               = sanitize_text_field( $_POST['name'] );
		$description        = sanitize_textarea_field( $_POST['description'] );
		$start_time         = sanitize_text_field( $_POST['start_time'] );
		$end_time           = sanitize_text_field( $_POST['end_time'] );
		$cleanup_hours      = intval( $_POST['cleanup_hours'] );
		$allow_multi_object = isset( $_POST['allow_multi_object'] ) ? 1 : 0;
		$day_of_week        = isset( $_POST['days_of_week'] ) ? implode( ',', array_map( 'intval', $_POST['days_of_week'] ) ) : null;
		if ( $day_of_week === '' ) {
			$day_of_week = null;
		}
		$start_date = ! empty( $_POST['start_date'] ) ? sanitize_text_field( $_POST['start_date'] ) : null;
		$end_date   = ! empty( $_POST['end_date'] ) ? sanitize_text_field( $_POST['end_date'] ) : null;
		$is_holiday = isset( $_POST['is_holiday'] ) ? 1 : 0;

		$data = array(
			'name'               => $name,
			'description'        => $description,
			'start_time'         => $start_time,
			'end_time'           => $end_time,
			'cleanup_hours'      => $cleanup_hours,
			'allow_multi_object' => $allow_multi_object,
			'days_of_week'       => $day_of_week,
			'date_start'         => $start_date,
			'date_end'           => $end_date,
			'is_holiday'         => $is_holiday,
			'modified_at'        => current_time( 'mysql' ),
		);

		if ( $id > 0 ) {
			$wpdb->update( $table, $data, array( 'id' => $id ) );
			wp_safe_redirect( admin_url( 'admin.php?page=snippen-booking-slots&action=edit&id=' . $id . '&message=updated' ) );
			exit;
		} else {
			$data['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $table, $data );
			wp_safe_redirect( admin_url( 'admin.php?page=snippen-booking-slots&message=created' ) );
			exit;
		}
	}

	/**
	 * Delete slot (soft delete)
	 */
	private function delete_slot( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'snippen_time_slots';
		$wpdb->update( $table, array( 'deleted_at' => current_time( 'mysql' ) ), array( 'id' => $id ) );
		wp_safe_redirect( admin_url( 'admin.php?page=snippen-booking-slots&message=deleted' ) );
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
	private function render_list( $object_filter = 0 ) {
		global $wpdb;
		$table_slots = $wpdb->prefix . 'snippen_time_slots';

		// Query slots
		$query = "SELECT s.* 
                  FROM $table_slots s 
                  WHERE s.deleted_at IS NULL 
                  ORDER BY s.start_time ASC";

		$slots = $wpdb->get_results( $query );

		echo '<div class="snippen-card">';
		echo '<table class="snippen-list-table snippen-filterable-table" id="slots-table">';
		echo '<thead><tr>';
		echo '<th data-filter-type="text" data-sort-type="string">' . esc_html__( 'Navn', 'snippen-booking' ) . '</th>';
		echo '<th data-filter-type="text" data-sort-type="string">' . esc_html__( 'Tid', 'snippen-booking' ) . '</th>';
		echo '<th data-filter-type="minmax" data-sort-type="number">' . esc_html__( 'Vask (t)', 'snippen-booking' ) . '</th>';
		echo '<th data-filter-type="multiselect" data-sort-type="string">' . esc_html__( 'Felles?', 'snippen-booking' ) . '</th>';
		echo '<th data-filter-type="text" data-sort-type="string">' . esc_html__( 'Betingelser', 'snippen-booking' ) . '</th>';
		echo '<th style="text-align:right;">' . esc_html__( 'Handlinger', 'snippen-booking' ) . '</th>';
		echo '</tr></thead>';
		echo '<tbody>';

		if ( empty( $slots ) ) {
			echo '<tr><td colspan="5">' . esc_html__( 'Ingen tidsluker funnet.', 'snippen-booking' ) . '</td></tr>';
		} else {
			foreach ( $slots as $slot ) {
				$edit_url   = admin_url( 'admin.php?page=snippen-booking-slots&action=edit&id=' . $slot->id );
				$delete_url = wp_nonce_url( admin_url( 'admin.php?page=snippen-booking-slots&action=delete&id=' . $slot->id ), 'delete_slot_' . $slot->id );

				$conditions = array();
				if ( $slot->is_holiday ) {
					$conditions[] = __( 'Helligdag', 'snippen-booking' );
				}
				if ( $slot->days_of_week !== null && $slot->days_of_week !== '' ) {
					$days          = array(
						1 => 'Man',
						2 => 'Tir',
						3 => 'Ons',
						4 => 'Tor',
						5 => 'Fre',
						6 => 'Lør',
						0 => 'Søn',
					);
					$selected_days = explode( ',', $slot->days_of_week );
					$day_labels    = array();
					foreach ( $selected_days as $d ) {
						if ( isset( $days[ $d ] ) ) {
							$day_labels[] = $days[ $d ];
						}
					}
					$conditions[] = implode( ',', $day_labels );
				}
				if ( $slot->date_start ) {
					$conditions[] = substr( $slot->date_start, 5 ) . ' til ' . substr( $slot->date_end, 5 );
				}

				echo '<tr>';
				echo '<td><strong><a href="' . esc_url( $edit_url ) . '">' . esc_html( $slot->name ) . '</a></strong></td>';
				echo '<td>' . esc_html( substr( $slot->start_time, 0, 5 ) . ' - ' . substr( $slot->end_time, 0, 5 ) ) . '</td>';
				echo '<td>' . esc_html( $slot->cleanup_hours ) . '</td>';
				echo '<td>' . ( $slot->allow_multi_object ? '<span class="dashicons dashicons-yes-alt" style="color:var(--success-color)">Ja</span>' : 'Nei' ) . '</td>';
				echo '<td><small>' . esc_html( implode( ' | ', $conditions ) ?: '-' ) . '</small></td>';
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
		$table_slots = $wpdb->prefix . 'snippen_time_slots';
		$slot        = null;

		if ( $id > 0 ) {
			$slot = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_slots WHERE id = %d", $id ) );
		}

		echo '<div class="snippen-card"><form method="post" action="">';
		wp_nonce_field( 'snippen_save_slot', 'snippen_slot_nonce' );
		echo '<input type="hidden" name="id" value="' . esc_attr( $id ) . '">';

		echo '<div class="snippen-form-group">';
		echo '<label for="name">' . esc_html__( 'Navn på tidsluke', 'snippen-booking' ) . '</label>';
		echo '<input type="text" name="name" id="name" value="' . esc_attr( $slot ? $slot->name : '' ) . '" required class="regular-text" placeholder="' . esc_attr__( 'F.eks. Hele dagen', 'snippen-booking' ) . '">';
		echo '</div>';

		echo '<div class="snippen-form-group" style="display:flex; gap:20px;">';
		echo '<div><label for="start_time">' . esc_html__( 'Starttid (HH:MM)', 'snippen-booking' ) . '</label>';
		echo '<input type="text" name="start_time" id="start_time" value="' . esc_attr( $slot ? substr( $slot->start_time, 0, 5 ) : '08:00' ) . '" required style="max-width:100px;"></div>';
		echo '<div><label for="end_time">' . esc_html__( 'Sluttid (HH:MM)', 'snippen-booking' ) . '</label>';
		echo '<input type="text" name="end_time" id="end_time" value="' . esc_attr( $slot ? substr( $slot->end_time, 0, 5 ) : '16:00' ) . '" required style="max-width:100px;"></div>';
		echo '</div>';

		echo '<div class="snippen-form-group">';
		echo '<label for="cleanup_hours">' . esc_html__( 'Vasketid (timer)', 'snippen-booking' ) . '</label>';
		echo '<input type="number" name="cleanup_hours" id="cleanup_hours" value="' . esc_attr( $slot ? $slot->cleanup_hours : 0 ) . '" min="0" max="24" style="max-width:100px;">';
		echo '<p class="description">' . esc_html__( 'Antall timer rommet er utilgjengelig etter sluttid.', 'snippen-booking' ) . '</p>';
		echo '</div>';

		echo '<div class="snippen-form-group">';
		echo '<label><input type="checkbox" name="allow_multi_object" value="1" ' . checked( $slot ? $slot->allow_multi_object : 0, 1, false ) . '> ' . esc_html__( 'Tillat fellesbooking (Hele området)', 'snippen-booking' ) . '</label>';
		echo '</div>';

		echo '<hr><h3 style="margin-top:25px;">' . esc_html__( 'Betingelser (Valgfritt)', 'snippen-booking' ) . '</h3>';

		echo '<div class="snippen-form-group">';
		echo '<label>' . esc_html__( 'Ukedager', 'snippen-booking' ) . '</label>';
		echo '<div style="display:flex; gap:15px; margin-top:5px;">';
		$days          = array(
			1 => 'Man',
			2 => 'Tir',
			3 => 'Ons',
			4 => 'Tor',
			5 => 'Fre',
			6 => 'Lør',
			0 => 'Søn',
		);
		$selected_days = $slot && $slot->days_of_week !== null ? explode( ',', $slot->days_of_week ) : array();
		foreach ( $days as $val => $label ) {
			echo '<label style="font-weight:normal;"><input type="checkbox" name="days_of_week[]" value="' . esc_attr( $val ) . '" ' . checked( in_array( (string) $val, $selected_days ), true, false ) . '> ' . esc_html( $label ) . '</label>';
		}
		echo '</div></div>';

		echo '<div class="snippen-form-group" style="display:flex; gap:20px;">';
		echo '<div><label for="start_date">' . esc_html__( 'Startdato (YYYY-MM-DD)', 'snippen-booking' ) . '</label>';
		echo '<input type="date" name="start_date" id="start_date" value="' . esc_attr( $slot ? $slot->date_start : '' ) . '"></div>';
		echo '<div><label for="end_date">' . esc_html__( 'Sluttdato (YYYY-MM-DD)', 'snippen-booking' ) . '</label>';
		echo '<input type="date" name="end_date" id="end_date" value="' . esc_attr( $slot ? $slot->date_end : '' ) . '"></div>';
		echo '</div>';

		echo '<div class="snippen-form-group">';
		echo '<label><input type="checkbox" name="is_holiday" value="1" ' . checked( $slot ? $slot->is_holiday : 0, 1, false ) . '> ' . esc_html__( 'Gjelder kun helligdager', 'snippen-booking' ) . '</label>';
		echo '</div>';

		echo '<div class="snippen-form-actions">';
		echo '<button type="submit" class="snippen-btn snippen-btn-primary">' . esc_html__( 'Lagre tidsluke', 'snippen-booking' ) . '</button>';
		echo '</div>';

		echo '</form></div>';
	}
}
