<?php

namespace SnippenBooking\Admin\Pages;

use SnippenBooking\Database\Repository\BookingBlockRepository;

/**
 * Admin page for managing Booking Blocks
 */
class BookingBlocksPage {

	/**
	 * Render the page
	 */
	public function render() {
		$action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';
		$id     = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

		echo '<div class="snippen-booking-admin-wrap">';

		$this->render_header( $action );

		// Render messages
		if ( isset( $_GET['message'] ) ) {
			$msg_type = sanitize_text_field( $_GET['message'] );
			if ( $msg_type === 'created' ) {
				$this->show_message( __( 'Bookingblokk lagret.', 'snippen-booking' ) );
			} elseif ( $msg_type === 'updated' ) {
				$this->show_message( __( 'Bookingblokk oppdatert.', 'snippen-booking' ) );
			} elseif ( $msg_type === 'deleted' ) {
				$this->show_message( __( 'Bookingblokk slettet.', 'snippen-booking' ) );
			}
		}

		if ( isset( $_GET['error'] ) ) {
			$error = sanitize_text_field( $_GET['error'] );
			if ( $error === 'overlap' ) {
				$this->show_message( __( 'Feil: Bookingblokken overlapper med en eksisterende blokk for samme lokale og dag.', 'snippen-booking' ), 'error' );
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
		$title = __( 'Bookingblokker', 'snippen-booking' );
		if ( $action === 'add' ) {
			$title = __( 'Legg til ny bookingblokk', 'snippen-booking' );
		}
		if ( $action === 'edit' ) {
			$title = __( 'Rediger bookingblokk', 'snippen-booking' );
		}

		echo '<div class="snippen-admin-header">';
		echo '<h1>' . esc_html( $title ) . '</h1>';

		if ( $action === 'list' ) {
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=snippen-booking-blocks&action=add' ) ) . '" class="snippen-btn snippen-btn-primary">' . esc_html__( 'Legg til ny', 'snippen-booking' ) . '</a>';
		} else {
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=snippen-booking-blocks' ) ) . '" class="snippen-btn snippen-btn-outline">' . esc_html__( 'Tilbake til oversikt', 'snippen-booking' ) . '</a>';
		}

		echo '</div>';
	}

	/**
	 * Handle POST requests (Save/Delete)
	 */
	public function handle_request() {
		if ( ! isset( $_POST['snippen_block_nonce'] ) || ! wp_verify_nonce( $_POST['snippen_block_nonce'], 'snippen_save_block' ) ) {
			if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && isset( $_GET['id'] ) ) {
				if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'delete_block_' . $_GET['id'] ) ) {
					$this->delete_block( intval( $_GET['id'] ) );
				}
			}
			return;
		}

		$id           = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
		$name         = sanitize_text_field( $_POST['name'] );
		$description  = sanitize_textarea_field( $_POST['description'] );
		$start_time   = sanitize_text_field( $_POST['start_time'] );
		$end_time     = sanitize_text_field( $_POST['end_time'] );
		$sort_order   = isset( $_POST['sort_order'] ) ? intval( $_POST['sort_order'] ) : 0;
		$is_active    = isset( $_POST['is_active'] ) ? 1 : 0;
		$object_ids   = isset( $_POST['booking_objects'] ) ? array_map( 'intval', (array) $_POST['booking_objects'] ) : array();
		$days_of_week = isset( $_POST['days_of_week'] ) ? array_map( 'sanitize_text_field', $_POST['days_of_week'] ) : array();
		$days_of_week = ! empty( $days_of_week ) ? implode( ',', $days_of_week ) : null;

		$repo = new BookingBlockRepository();

		$data = array(
			'name'         => $name,
			'description'  => $description,
			'start_time'   => $start_time,
			'end_time'     => $end_time,
			'days_of_week' => $days_of_week,
			'sort_order'   => $sort_order,
			'is_active'    => $is_active,
		);

		$saved_id = $repo->save( $data, $id > 0 ? $id : null );

		if ( $saved_id ) {
			$repo->sync_booking_objects( $saved_id, $object_ids );
		}

		if ( $id > 0 ) {
			wp_safe_redirect( admin_url( 'admin.php?page=snippen-booking-blocks&message=updated' ) );
		} else {
			wp_safe_redirect( admin_url( 'admin.php?page=snippen-booking-blocks&message=created' ) );
		}
		exit;
	}

	/**
	 * Delete block (soft delete)
	 */
	private function delete_block( $id ) {
		$repo = new BookingBlockRepository();
		$repo->delete( $id );
		wp_safe_redirect( admin_url( 'admin.php?page=snippen-booking-blocks&message=deleted' ) );
		exit;
	}

	/**
	 * Show admin message
	 */
	private function show_message( $message, $type = 'success' ) {
		echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Render list table
	 */
	private function render_list() {
		global $wpdb;
		$table_blocks   = $wpdb->prefix . 'snippen_booking_blocks';
		$table_junction = $wpdb->prefix . 'snippen_booking_object_booking_blocks';
		$table_objects  = $wpdb->prefix . 'snippen_booking_objects';

		$query = "SELECT b.*, GROUP_CONCAT(bo.name SEPARATOR ', ') as object_names 
                  FROM $table_blocks b 
                  LEFT JOIN $table_junction j ON b.id = j.booking_block_id
                  LEFT JOIN $table_objects bo ON j.booking_object_id = bo.id
                  WHERE b.deleted_at IS NULL 
                  GROUP BY b.id ORDER BY b.sort_order ASC, b.start_time ASC";

		$blocks = $wpdb->get_results( $query );

		$this->render_weekly_preview( $blocks );

		echo '<div class="snippen-card" style="margin-top: 20px;">';
		echo '<h2>' . esc_html__( 'Alle bookingblokker', 'snippen-booking' ) . '</h2>';
		echo '<table class="snippen-list-table snippen-filterable-table" id="blocks-table">';
		echo '<thead><tr>';
		echo '<th data-filter-type="text" data-sort-type="string">' . esc_html__( 'Navn', 'snippen-booking' ) . '</th>';
		echo '<th data-filter-type="text" data-sort-type="string">' . esc_html__( 'Lokaler', 'snippen-booking' ) . '</th>';
		echo '<th data-filter-type="text" data-sort-type="string">' . esc_html__( 'Tid', 'snippen-booking' ) . '</th>';
		echo '<th data-filter-type="text" data-sort-type="string">' . esc_html__( 'Dager', 'snippen-booking' ) . '</th>';
		echo '<th data-sort-type="number">' . esc_html__( 'Sortering', 'snippen-booking' ) . '</th>';
		echo '<th data-filter-type="select" data-sort-type="string">' . esc_html__( 'Status', 'snippen-booking' ) . '</th>';
		echo '<th style="text-align:right;">' . esc_html__( 'Handlinger', 'snippen-booking' ) . '</th>';
		echo '</tr></thead>';
		echo '<tbody>';

		if ( empty( $blocks ) ) {
			echo '<tr><td colspan="7">' . esc_html__( 'Ingen bookingblokker funnet.', 'snippen-booking' ) . '</td></tr>';
		} else {
			$days_map = array(
				1 => 'Man',
				2 => 'Tir',
				3 => 'Ons',
				4 => 'Tor',
				5 => 'Fre',
				6 => 'Lør',
				0 => 'Søn',
				7 => 'Helligdag',
			);

			foreach ( $blocks as $block ) {
				$edit_url   = admin_url( 'admin.php?page=snippen-booking-blocks&action=edit&id=' . $block->id );
				$delete_url = wp_nonce_url( admin_url( 'admin.php?page=snippen-booking-blocks&action=delete&id=' . $block->id ), 'delete_block_' . $block->id );

				$days_text = '-';
				if ( $block->days_of_week !== null && $block->days_of_week !== '' ) {
					$selected_days = explode( ',', $block->days_of_week );
					$day_labels    = array();
					foreach ( $selected_days as $d ) {
						if ( isset( $days_map[ $d ] ) ) {
							$day_labels[] = $days_map[ $d ];
						}
					}
					$days_text = implode( ', ', $day_labels );
				} else {
					$days_text = __( 'Alle dager', 'snippen-booking' );
				}

				$is_active = ! isset( $block->is_active ) || (int) $block->is_active === 1;

				echo '<tr>';
				echo '<td><strong><a href="' . esc_url( $edit_url ) . '">' . esc_html( $block->name ) . '</a></strong></td>';
				echo '<td>' . esc_html( $block->object_names ?: '-' ) . '</td>';
				echo '<td>' . esc_html( substr( $block->start_time, 0, 5 ) . ' - ' . substr( $block->end_time, 0, 5 ) ) . '</td>';
				echo '<td>' . esc_html( $days_text ) . '</td>';
				echo '<td>' . esc_html( $block->sort_order ) . '</td>';
				echo '<td><label class="snippen-switch"><input type="checkbox" class="snippen-toggle-status" data-entity-type="time_slot" data-id="' . intval( $block->id ) . '" ' . checked( $is_active, true, false ) . '><span class="snippen-slider"></span></label></td>';
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
	 * Render a simple weekly preview matrix
	 */
	private function render_weekly_preview( $blocks ) {
		if ( empty( $blocks ) ) {
			return;
		}

		global $wpdb;
		$table_junction = $wpdb->prefix . 'snippen_booking_object_booking_blocks';

		// Pre-fetch associated booking objects for each block
		$block_objects = array();
		foreach ( $blocks as $block ) {
			$block_objects[ $block->id ] = $wpdb->get_col( $wpdb->prepare( "SELECT booking_object_id FROM $table_junction WHERE booking_block_id = %d", $block->id ) );
		}

		echo '<div class="snippen-card">';
		echo '<h2>' . esc_html__( 'Ukesoversikt', 'snippen-booking' ) . '</h2>';
		echo '<div style="overflow-x: auto;">';
		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Blokk', 'snippen-booking' ) . '</th>';
		echo '<th>Man</th><th>Tir</th><th>Ons</th><th>Tor</th><th>Fre</th><th>Lør</th><th>Søn</th><th>Helligdag</th>';
		echo '</tr></thead>';
		echo '<tbody>';

		$days_cols = array( 1, 2, 3, 4, 5, 6, 0, 7 );

		foreach ( $blocks as $block ) {
			$is_active  = ! isset( $block->is_active ) || (int) $block->is_active === 1;
			$row_style  = $is_active ? '' : ' style="opacity: 0.55; background-color: #f8fafc;"';
			$status_tag = $is_active ? '' : ' <span class="snippen-badge snippen-status-cancelled" style="font-size:10px; padding:2px 6px; margin-left:4px;">' . esc_html__( 'Deaktivert', 'snippen-booking' ) . '</span>';

			// Check if this active block overlaps with any other active block sharing the same object and days
			$has_active_overlap = false;
			if ( $is_active ) {
				$this_objs = $block_objects[ $block->id ] ?? array();
				$this_days = $block->days_of_week !== null && $block->days_of_week !== '' ? explode( ',', $block->days_of_week ) : array( '0', '1', '2', '3', '4', '5', '6', '7' );

				foreach ( $blocks as $other ) {
					if ( (int) $other->id === (int) $block->id ) {
						continue;
					}
					$other_active = ! isset( $other->is_active ) || (int) $other->is_active === 1;
					if ( ! $other_active ) {
						continue;
					}

					// Normalize time strings to HH:MM:SS for robust string comparison
					$b_start = strlen( $block->start_time ) === 5 ? $block->start_time . ':00' : $block->start_time;
					$b_end   = strlen( $block->end_time ) === 5 ? $block->end_time . ':00' : $block->end_time;
					$o_start = strlen( $other->start_time ) === 5 ? $other->start_time . ':00' : $other->start_time;
					$o_end   = strlen( $other->end_time ) === 5 ? $other->end_time . ':00' : $other->end_time;

					// Time overlap check (two time intervals [A_start, A_end) and [B_start, B_end) overlap iff A_start < B_end AND A_end > B_start)
					if ( $b_start < $o_end && $b_end > $o_start ) {
						$other_objs   = $block_objects[ $other->id ] ?? array();
						$shared_objs = array_intersect( $this_objs, $other_objs );

						if ( ! empty( $shared_objs ) ) {
							$other_days  = $other->days_of_week !== null && $other->days_of_week !== '' ? explode( ',', $other->days_of_week ) : array( '0', '1', '2', '3', '4', '5', '6', '7' );
							$shared_days = array_intersect( $this_days, $other_days );

							if ( ! empty( $shared_days ) ) {
								$has_active_overlap = true;
								break;
							}
						}
					}
				}
			}

			if ( $has_active_overlap ) {
				$status_tag .= ' <span class="snippen-badge snippen-status-pending" style="font-size:10px; padding:2px 6px; margin-left:4px; background:#f59e0b; color:#fff;" title="' . esc_attr__( 'Overlapper med en annen aktiv blokk', 'snippen-booking' ) . '">' . esc_html__( 'Overlapp', 'snippen-booking' ) . '</span>';
			}

			echo '<tr' . $row_style . '>';
			echo '<td><strong>' . esc_html( $block->name ) . '</strong>' . $status_tag . '<br><small>' . esc_html( substr( $block->start_time, 0, 5 ) . '-' . substr( $block->end_time, 0, 5 ) ) . '</small></td>';

			$block_days = $block->days_of_week !== null && $block->days_of_week !== ''
				? explode( ',', $block->days_of_week )
				: array( '0', '1', '2', '3', '4', '5', '6', '7' );

			foreach ( $days_cols as $day ) {
				if ( in_array( (string) $day, $block_days, true ) ) {
					$check_color = $is_active ? '#46b450' : '#94a3b8';
					echo '<td style="text-align:center; color: ' . $check_color . '; font-weight:bold;">✓</td>';
				} else {
					echo '<td></td>';
				}
			}
			echo '</tr>';
		}

		echo '</tbody></table></div></div>';
	}

	/**
	 * Render Add/Edit Form
	 */
	private function render_form( $id = 0 ) {
		global $wpdb;
		$repo  = new BookingBlockRepository();
		$block = $id > 0 ? $repo->find( $id ) : null;

		echo '<div class="snippen-card"><form method="post" action="">';
		wp_nonce_field( 'snippen_save_block', 'snippen_block_nonce' );
		echo '<input type="hidden" name="id" value="' . esc_attr( $id ) . '">';

		echo '<div class="snippen-form-group" style="display:flex; justify-content:space-between; align-items:center;">';
		echo '<div><label for="name">' . esc_html__( 'Navn på bookingblokk', 'snippen-booking' ) . '</label>';
		echo '<input type="text" name="name" id="name" value="' . esc_attr( $block ? $block->name : '' ) . '" required class="regular-text" placeholder="' . esc_attr__( 'F.eks. Hele dagen', 'snippen-booking' ) . '"></div>';

		echo '<div><label style="font-weight:bold; display:flex; align-items:center; gap:8px;">';
		$is_active = ! $block || ! isset( $block->is_active ) || (int) $block->is_active === 1;
		echo '<input type="checkbox" name="is_active" value="1" ' . checked( $is_active, true, false ) . '>';
		echo esc_html__( 'Tidsluken er aktiv', 'snippen-booking' );
		echo '</label></div>';
		echo '</div>';

		echo '<div class="snippen-form-group">';
		echo '<label for="description">' . esc_html__( 'Beskrivelse', 'snippen-booking' ) . '</label>';
		echo '<textarea name="description" id="description" rows="3" class="large-text">' . esc_textarea( $block ? $block->description : '' ) . '</textarea>';
		echo '</div>';

		echo '<div class="snippen-form-group" style="display:flex; gap:20px;">';
		echo '<div><label for="start_time">' . esc_html__( 'Starttid (HH:MM, 00:00-23:59)', 'snippen-booking' ) . '</label>';
		echo '<input type="time" name="start_time" id="start_time" value="' . esc_attr( $block ? substr( $block->start_time, 0, 5 ) : '08:00' ) . '" step="60" required style="max-width:160px;"></div>';
		echo '<div><label for="end_time">' . esc_html__( 'Sluttid (HH:MM, 00:00-23:59)', 'snippen-booking' ) . '</label>';
		echo '<input type="time" name="end_time" id="end_time" value="' . esc_attr( $block ? substr( $block->end_time, 0, 5 ) : '16:00' ) . '" step="60" required style="max-width:160px;"></div>';
		echo '</div>';

		echo '<div class="snippen-form-group">';
		echo '<label>' . esc_html__( 'Lokaler', 'snippen-booking' ) . '</label>';
		echo '<div style="display:flex; flex-direction:column; gap:5px; margin-top:5px;">';

		$objects          = $wpdb->get_results( "SELECT id, name FROM {$wpdb->prefix}snippen_booking_objects WHERE deleted_at IS NULL" );
		$selected_objects = array();
		if ( $id > 0 ) {
			$selected_objects = $wpdb->get_col( $wpdb->prepare( "SELECT booking_object_id FROM {$wpdb->prefix}snippen_booking_object_booking_blocks WHERE booking_block_id = %d", $id ) );
		}

		foreach ( $objects as $obj ) {
			echo '<label style="font-weight:normal;">';
			echo '<input type="checkbox" name="booking_objects[]" value="' . esc_attr( $obj->id ) . '" ' . checked( in_array( $obj->id, $selected_objects ), true, false ) . '> ';
			echo esc_html( $obj->name );
			echo '</label>';
		}
		echo '</div></div>';

		echo '<hr><h3 style="margin-top:25px;">' . esc_html__( 'Tilgjengelighet', 'snippen-booking' ) . '</h3>';

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
		$selected_days = $block && $block->days_of_week !== null && $block->days_of_week !== '' ? explode( ',', $block->days_of_week ) : array();
		foreach ( $days as $val => $label ) {
			echo '<label style="font-weight:normal;"><input type="checkbox" name="days_of_week[]" value="' . esc_attr( $val ) . '" ' . checked( in_array( (string) $val, $selected_days ), true, false ) . '> ' . esc_html( $label ) . '</label>';
		}
		echo '</div>';
		echo '<p class="description">' . esc_html__( 'La alle stå tomme hvis bookingblokken gjelder alle dager.', 'snippen-booking' ) . '</p>';
		echo '</div>';

		echo '<div class="snippen-form-group">';
		echo '<label for="sort_order">' . esc_html__( 'Sorteringsrekkefølge', 'snippen-booking' ) . '</label>';
		echo '<input type="number" name="sort_order" id="sort_order" value="' . esc_attr( $block ? $block->sort_order : 0 ) . '" style="max-width:100px;">';
		echo '<p class="description">' . esc_html__( 'Lavere tall vises først i listen og for kundene.', 'snippen-booking' ) . '</p>';
		echo '</div>';

		echo '<div class="snippen-form-actions">';
		echo '<button type="submit" class="snippen-btn snippen-btn-primary">' . esc_html__( 'Lagre bookingblokk', 'snippen-booking' ) . '</button>';
		echo '</div>';

		echo '</form></div>';
	}
}
