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
		$action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';
		$filter = isset( $_GET['filter'] ) ? sanitize_text_field( $_GET['filter'] ) : '';
		$id     = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

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
				$this->render_list( $filter );
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

		$id            = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
		$name          = sanitize_text_field( $_POST['name'] );
		$description   = sanitize_textarea_field( $_POST['description'] );
		$start_time    = sanitize_text_field( $_POST['start_time'] );
		$end_time      = sanitize_text_field( $_POST['end_time'] );
		$cleanup_hours      = intval( $_POST['cleanup_hours'] );
		$is_active          = isset( $_POST['is_active'] ) ? 1 : 0;
		$includes_wash_time = isset( $_POST['includes_wash_time'] ) ? 1 : 0;
		$object_ids         = isset( $_POST['booking_objects'] ) ? array_map( 'intval', (array) $_POST['booking_objects'] ) : array();
		$days_of_week       = isset( $_POST['days_of_week'] ) ? array_map( 'sanitize_text_field', $_POST['days_of_week'] ) : array();
		$days_of_week       = ! empty( $days_of_week ) ? implode( ',', $days_of_week ) : null;
		$start_date         = ! empty( $_POST['start_date'] ) ? sanitize_text_field( $_POST['start_date'] ) : null;
		$end_date           = ! empty( $_POST['end_date'] ) ? sanitize_text_field( $_POST['end_date'] ) : null;

		$data = array(
			'name'               => $name,
			'description'        => $description,
			'start_time'         => $start_time,
			'end_time'           => $end_time,
			'cleanup_hours'      => $cleanup_hours,
			'is_active'          => $is_active,
			'includes_wash_time' => $includes_wash_time,
			'days_of_week'       => $days_of_week,
			'date_start'         => $start_date,
			'date_end'           => $end_date,
			'modified_at'        => current_time( 'mysql' ),
		);

		if ( $id > 0 ) {
			$wpdb->update( $table, $data, array( 'id' => $id ) );
			$slot_id      = $id;
			$redirect_msg = 'updated';
		} else {
			$data['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $table, $data );
			$slot_id      = $wpdb->insert_id;
			$redirect_msg = 'created';
		}

		// Update booking objects junction
		$table_time_slot_objects = $wpdb->prefix . 'snippen_time_slot_booking_objects';
		$wpdb->query( $wpdb->prepare( "DELETE FROM $table_time_slot_objects WHERE time_slot_id = %d", $slot_id ) );
		foreach ( $object_ids as $obj_id ) {
			$wpdb->insert(
				$table_time_slot_objects,
				array(
					'time_slot_id'      => $slot_id,
					'booking_object_id' => $obj_id,
				)
			);
		}

		if ( $redirect_msg === 'updated' ) {
			wp_safe_redirect( admin_url( 'admin.php?page=snippen-booking-slots&action=edit&id=' . $id . '&message=updated' ) );
		} else {
			wp_safe_redirect( admin_url( 'admin.php?page=snippen-booking-slots&message=created' ) );
		}
		exit;
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
	private function render_list( $filter = '' ) {
		global $wpdb;
		$table_slots = $wpdb->prefix . 'snippen_time_slots';

		// Query slots with object names grouped
		$query = "SELECT s.*, p.name as price_name, GROUP_CONCAT(bo.name SEPARATOR ', ') as object_names 
                  FROM $table_slots s 
                  LEFT JOIN {$wpdb->prefix}snippen_time_slot_booking_objects tso ON s.id = tso.time_slot_id
                  LEFT JOIN {$wpdb->prefix}snippen_booking_objects bo ON tso.booking_object_id = bo.id
                  LEFT JOIN {$wpdb->prefix}snippen_prices p ON s.price_id = p.id
                  WHERE s.deleted_at IS NULL ";

		if ( $filter === 'no_price' ) {
			$query .= ' AND s.price_id IS NULL ';
		}

		$query .= ' GROUP BY s.id ORDER BY s.start_time ASC';

		$slots = $wpdb->get_results( $query );

		echo '<div style="margin-bottom: 15px;">';
		$active_all      = $filter !== 'no_price' ? 'nav-tab-active' : '';
		$active_no_price = $filter === 'no_price' ? 'nav-tab-active' : '';
		echo '<a href="?page=snippen-booking-slots" class="nav-tab ' . esc_attr( $active_all ) . '">' . esc_html__( 'Alle', 'snippen-booking' ) . '</a>';
		echo '<a href="?page=snippen-booking-slots&filter=no_price" class="nav-tab ' . esc_attr( $active_no_price ) . '">' . esc_html__( 'Uten pris', 'snippen-booking' ) . '</a>';
		echo '</div>';

		echo '<div class="snippen-card">';
		echo '<table class="snippen-list-table snippen-filterable-table" id="slots-table">';
		echo '<thead><tr>';
		echo '<th data-filter-type="text" data-sort-type="string">' . esc_html__( 'Navn', 'snippen-booking' ) . '</th>';
		echo '<th data-filter-type="text" data-sort-type="string">' . esc_html__( 'Rom', 'snippen-booking' ) . '</th>';
		echo '<th data-filter-type="text" data-sort-type="string">' . esc_html__( 'Tid', 'snippen-booking' ) . '</th>';
		echo '<th data-filter-type="minmax" data-sort-type="number">' . esc_html__( 'Vask (t)', 'snippen-booking' ) . '</th>';
		echo '<th data-filter-type="text" data-sort-type="string">' . esc_html__( 'Betingelser', 'snippen-booking' ) . '</th>';
		echo '<th data-filter-type="text" data-sort-type="string">' . esc_html__( 'Tilknyttet Pris', 'snippen-booking' ) . '</th>';
		echo '<th data-filter-type="select" data-sort-type="string">' . esc_html__( 'Status', 'snippen-booking' ) . '</th>';
		echo '<th style="text-align:right;">' . esc_html__( 'Handlinger', 'snippen-booking' ) . '</th>';
		echo '</tr></thead>';
		echo '<tbody>';

		if ( empty( $slots ) ) {
			echo '<tr><td colspan="8">' . esc_html__( 'Ingen tidsluker funnet.', 'snippen-booking' ) . '</td></tr>';
		} else {
			foreach ( $slots as $slot ) {
				$edit_url   = admin_url( 'admin.php?page=snippen-booking-slots&action=edit&id=' . $slot->id );
				$delete_url = wp_nonce_url( admin_url( 'admin.php?page=snippen-booking-slots&action=delete&id=' . $slot->id ), 'delete_slot_' . $slot->id );

				$conditions = array();
				if ( $slot->days_of_week !== null && $slot->days_of_week !== '' ) {
					$days          = array(
						1 => 'Man',
						2 => 'Tir',
						3 => 'Ons',
						4 => 'Tor',
						5 => 'Fre',
						6 => 'Lør',
						0 => 'Søn',
						7 => 'Helligdag',
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

				$is_active = ! isset( $slot->is_active ) || (int) $slot->is_active === 1;

				echo '<tr>';
				echo '<td><strong><a href="' . esc_url( $edit_url ) . '">' . esc_html( $slot->name ) . '</a></strong></td>';
				echo '<td>' . esc_html( $slot->object_names ?: '-' ) . '</td>';
				echo '<td>' . esc_html( substr( $slot->start_time, 0, 5 ) . ' - ' . substr( $slot->end_time, 0, 5 ) ) . '</td>';
				echo '<td>' . esc_html( $slot->cleanup_hours ) . '</td>';
				echo '<td><small>' . esc_html( implode( ' | ', $conditions ) ?: '-' ) . '</small></td>';
				echo '<td>' . esc_html( $slot->price_name ?: '-' ) . '</td>';
				echo '<td><label class="snippen-switch"><input type="checkbox" class="snippen-toggle-status" data-entity-type="time_slot" data-id="' . intval( $slot->id ) . '" ' . checked( $is_active, true, false ) . '><span class="snippen-slider"></span></label></td>';
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

		echo '<div class="snippen-form-group" style="display:flex; justify-content:space-between; align-items:center;">';
		echo '<div><label for="name">' . esc_html__( 'Navn på tidsluke', 'snippen-booking' ) . '</label>';
		echo '<input type="text" name="name" id="name" value="' . esc_attr( $slot ? $slot->name : '' ) . '" required class="regular-text" placeholder="' . esc_attr__( 'F.eks. Hele dagen', 'snippen-booking' ) . '"></div>';

		echo '<div><label style="font-weight:bold; display:flex; align-items:center; gap:8px;">';
		$is_active = ! $slot || ! isset( $slot->is_active ) || (int) $slot->is_active === 1;
		echo '<input type="checkbox" name="is_active" value="1" ' . checked( $is_active, true, false ) . '>';
		echo esc_html__( 'Tidsluken er aktiv', 'snippen-booking' );
		echo '</label></div>';
		echo '</div>';

		if ( $id > 0 ) {
			$price_info = '';
			if ( $slot && $slot->price_id ) {
				$price_row = $wpdb->get_row( $wpdb->prepare( "SELECT id, name, price FROM {$wpdb->prefix}snippen_prices WHERE id = %d", $slot->price_id ) );
				if ( $price_row ) {
					$edit_price_url = admin_url( 'admin.php?page=snippen-booking-pricing&action=edit&id=' . $price_row->id );
					$price_info     = sprintf(
						'<strong><a href="%s">%s</a></strong> (%s kr)',
						esc_url( $edit_price_url ),
						esc_html( $price_row->name ),
						esc_html( number_format( $price_row->price, 0, ',', ' ' ) )
					);
				}
			}

			echo '<div class="snippen-form-group">';
			echo '<label>' . esc_html__( 'Tilknyttet pris', 'snippen-booking' ) . '</label>';
			if ( ! empty( $price_info ) ) {
				echo '<p style="margin: 5px 0 0 0; font-size: 14px;">' . $price_info . '</p>';
			} else {
				echo '<p style="margin: 5px 0 0 0; font-size: 14px; color: #666;">' . esc_html__( 'Ingen pris tilknyttet.', 'snippen-booking' ) . '</p>';
			}
			echo '</div>';
		}

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
		echo '<label style="font-weight:bold; display:flex; align-items:center; gap:8px;">';
		$includes_wash_time = $slot && isset( $slot->includes_wash_time ) && (int) $slot->includes_wash_time === 1;
		echo '<input type="checkbox" name="includes_wash_time" value="1" ' . checked( $includes_wash_time, true, false ) . '>';
		echo esc_html__( 'Inkluderer utvask-tid neste morgen (frem til kl. 11:00)', 'snippen-booking' );
		echo '</label>';
		echo '<p class="description">' . esc_html__( 'Markér hvis kunden kan benytte lokalet til utvask påfølgende morgen fram til kl. 11:00 uten ekstra kostnad.', 'snippen-booking' ) . '</p>';
		echo '</div>';

		echo '<div class="snippen-form-group">';
		echo '<label>' . esc_html__( 'Rom', 'snippen-booking' ) . '</label>';
		echo '<div style="display:flex; flex-direction:column; gap:5px; margin-top:5px;">';

		$objects          = $wpdb->get_results( "SELECT id, name FROM {$wpdb->prefix}snippen_booking_objects WHERE deleted_at IS NULL" );
		$selected_objects = array();
		if ( $id > 0 ) {
			$selected_objects = $wpdb->get_col( $wpdb->prepare( "SELECT booking_object_id FROM {$wpdb->prefix}snippen_time_slot_booking_objects WHERE time_slot_id = %d", $id ) );
		}

		foreach ( $objects as $obj ) {
			echo '<label style="font-weight:normal;">';
			echo '<input type="checkbox" name="booking_objects[]" value="' . esc_attr( $obj->id ) . '" ' . checked( in_array( $obj->id, $selected_objects ), true, false ) . '> ';
			echo esc_html( $obj->name );
			echo '</label>';
		}
		echo '</div></div>';

		echo '<hr><h3 style="margin-top:25px;">' . esc_html__( 'Betingelser (Valgfritt)', 'snippen-booking' ) . '</h3>';

		echo '<div class="snippen-form-group">';
		echo '<label>' . esc_html__( 'Gyldige dager', 'snippen-booking' ) . '</label>';
		echo '<div style="display:flex; flex-wrap:wrap; gap:15px; margin-top:5px;">';
		$days          = array(
			'1' => __( 'Mandag', 'snippen-booking' ),
			'2' => __( 'Tirsdag', 'snippen-booking' ),
			'3' => __( 'Onsdag', 'snippen-booking' ),
			'4' => __( 'Torsdag', 'snippen-booking' ),
			'5' => __( 'Fredag', 'snippen-booking' ),
			'6' => __( 'Lørdag', 'snippen-booking' ),
			'0' => __( 'Søndag', 'snippen-booking' ),
			'7' => __( 'Helligdag', 'snippen-booking' ),
		);
		$selected_days = $slot && $slot->days_of_week !== null ? explode( ',', $slot->days_of_week ) : array();
		foreach ( $days as $val => $label ) {
			echo '<label style="font-weight:normal;"><input type="checkbox" name="days_of_week[]" value="' . esc_attr( $val ) . '" ' . checked( in_array( (string) $val, $selected_days ), true, false ) . '> ' . esc_html( $label ) . '</label>';
		}
		echo '</div>';
		echo '<p class="description">' . esc_html__( 'La alle stå tomme hvis tidsluken gjelder uansett ukedag.', 'snippen-booking' ) . '</p>';
		echo '</div>';

		echo '<div class="snippen-form-group" style="display:flex; gap:20px;">';
		echo '<div><label for="start_date">' . esc_html__( 'Startdato (YYYY-MM-DD)', 'snippen-booking' ) . '</label>';
		echo '<input type="date" name="start_date" id="start_date" value="' . esc_attr( $slot ? $slot->date_start : '' ) . '"></div>';
		echo '<div><label for="end_date">' . esc_html__( 'Sluttdato (YYYY-MM-DD)', 'snippen-booking' ) . '</label>';
		echo '<input type="date" name="end_date" id="end_date" value="' . esc_attr( $slot ? $slot->date_end : '' ) . '"></div>';
		echo '</div>';

		echo '<div class="snippen-form-actions">';
		echo '<button type="submit" class="snippen-btn snippen-btn-primary">' . esc_html__( 'Lagre tidsluke', 'snippen-booking' ) . '</button>';
		echo '</div>';

		echo '</form></div>';
	}
}
