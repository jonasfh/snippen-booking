<?php

namespace SnippenBooking\Admin\Pages;

/**
 * Admin page for managing Pricing Rules
 */
class PricingPage {

	/**
	 * Validation errors
	 * @var array
	 */
	private $errors = array();

	/**
	 * Render the page
	 */
	public function render() {
		$action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';
		$id     = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

		echo '<div class="snippen-booking-admin-wrap">';

		$this->render_header( $action );

		// Render any validation errors
		if ( ! empty( $this->errors ) ) {
			foreach ( $this->errors as $error ) {
				echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>';
			}
		}

		// Render success/info messages passed in query params
		if ( isset( $_GET['message'] ) ) {
			$msg_type = sanitize_text_field( $_GET['message'] );
			if ( $msg_type === 'created' ) {
				$this->show_message( __( 'Prisregel lagret.', 'snippen-booking' ) );
			} elseif ( $msg_type === 'updated' ) {
				$this->show_message( __( 'Prisregel oppdatert.', 'snippen-booking' ) );
			} elseif ( $msg_type === 'deleted' ) {
				$this->show_message( __( 'Prisregel slettet.', 'snippen-booking' ) );
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
		$title = __( 'Prisregler', 'snippen-booking' );
		if ( $action === 'add' ) {
			$title = __( 'Legg til ny prisregel', 'snippen-booking' );
		}
		if ( $action === 'edit' ) {
			$title = __( 'Rediger prisregel', 'snippen-booking' );
		}

		echo '<div class="snippen-admin-header">';
		echo '<h1>' . esc_html( $title ) . '</h1>';

		if ( $action === 'list' ) {
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=snippen-booking-pricing&action=add' ) ) . '" class="snippen-btn snippen-btn-primary">' . esc_html__( 'Legg til ny', 'snippen-booking' ) . '</a>';
		} else {
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=snippen-booking-pricing' ) ) . '" class="snippen-btn snippen-btn-outline">' . esc_html__( 'Tilbake til oversikt', 'snippen-booking' ) . '</a>';
		}

		echo '</div>';
	}

	/**
	 * Handle POST requests (Save/Delete)
	 */
	public function handle_request() {
		if ( ! isset( $_POST['snippen_price_nonce'] ) || ! wp_verify_nonce( $_POST['snippen_price_nonce'], 'snippen_save_price' ) ) {
			if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && isset( $_GET['id'] ) ) {
				if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'delete_price_' . $_GET['id'] ) ) {
					$this->delete_price( intval( $_GET['id'] ) );
				}
			}
			return;
		}

		global $wpdb;
		$table_prices        = $wpdb->prefix . 'snippen_prices';
		$table_price_objects = $wpdb->prefix . 'snippen_price_booking_objects';

		$id          = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
		$name        = sanitize_text_field( $_POST['name'] );
		$slot_id     = intval( $_POST['slot_id'] );
		$price       = floatval( $_POST['price'] );
		$priority    = intval( $_POST['priority'] );
		$day_of_week = isset( $_POST['days_of_week'] ) ? implode( ',', array_map( 'intval', $_POST['days_of_week'] ) ) : null;
		if ( $day_of_week === '' ) {
			$day_of_week = null;
		}

		$start_date = ! empty( $_POST['start_date'] ) ? sanitize_text_field( $_POST['start_date'] ) : null;
		$end_date   = ! empty( $_POST['end_date'] ) ? sanitize_text_field( $_POST['end_date'] ) : null;
		$is_holiday = isset( $_POST['is_holiday'] ) ? 1 : 0;
		$object_ids = isset( $_POST['object_ids'] ) ? array_map( 'intval', $_POST['object_ids'] ) : array();

		if ( empty( $object_ids ) ) {
			$this->errors[] = __( 'Du må velge minst ett lokale.', 'snippen-booking' );
			return;
		}

		$data = array(
			'name'         => $name,
			'slot_id'      => $slot_id,
			'price'        => $price,
			'priority'     => $priority,
			'days_of_week' => $day_of_week,
			'date_start'   => $start_date,
			'date_end'     => $end_date,
			'is_holiday'   => $is_holiday,
			'modified_at'  => current_time( 'mysql' ),
		);

		if ( $id > 0 ) {
			$wpdb->update( $table_prices, $data, array( 'id' => $id ) );
			$message_key = 'updated';
		} else {
			$data['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $table_prices, $data );
			$id = $wpdb->insert_id;
			$message_key = 'created';
		}

		// Update objects junction
		$wpdb->delete( $table_price_objects, array( 'price_id' => $id ) );
		foreach ( $object_ids as $obj_id ) {
			$wpdb->insert(
				$table_price_objects,
				array(
					'price_id'          => $id,
					'booking_object_id' => $obj_id,
				)
			);
		}

		if ( $message_key === 'created' ) {
			wp_safe_redirect( admin_url( 'admin.php?page=snippen-booking-pricing&message=created' ) );
		} else {
			wp_safe_redirect( admin_url( 'admin.php?page=snippen-booking-pricing&action=edit&id=' . $id . '&message=updated' ) );
		}
		exit;
	}

	/**
	 * Delete price
	 */
	private function delete_price( $id ) {
		global $wpdb;
		$table_prices        = $wpdb->prefix . 'snippen_prices';
		$table_price_objects = $wpdb->prefix . 'snippen_price_booking_objects';

		$wpdb->delete( $table_price_objects, array( 'price_id' => $id ) );
		$wpdb->delete( $table_prices, array( 'id' => $id ) );
		wp_safe_redirect( admin_url( 'admin.php?page=snippen-booking-pricing&message=deleted' ) );
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
		$table_prices        = $wpdb->prefix . 'snippen_prices';
		$table_slots         = $wpdb->prefix . 'snippen_time_slots';
		$table_price_objects = $wpdb->prefix . 'snippen_price_booking_objects';
		$table_objects       = $wpdb->prefix . 'snippen_booking_objects';

		$rules = $wpdb->get_results(
			"
            SELECT p.*, s.name as slot_name 
            FROM $table_prices p 
            JOIN $table_slots s ON p.slot_id = s.id 
            ORDER BY p.priority DESC, p.name ASC"
		);

		echo '<div class="snippen-card">';
		echo '<table class="snippen-list-table">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Navn', 'snippen-booking' ) . '</th>';
		echo '<th>' . esc_html__( 'Lokaler', 'snippen-booking' ) . '</th>';
		echo '<th>' . esc_html__( 'Tidsluke', 'snippen-booking' ) . '</th>';
		echo '<th>' . esc_html__( 'Pris', 'snippen-booking' ) . '</th>';
		echo '<th>' . esc_html__( 'Prioritet', 'snippen-booking' ) . '</th>';
		echo '<th>' . esc_html__( 'Betingelser', 'snippen-booking' ) . '</th>';
		echo '<th style="text-align:right;">' . esc_html__( 'Handlinger', 'snippen-booking' ) . '</th>';
		echo '</tr></thead>';
		echo '<tbody>';

		if ( empty( $rules ) ) {
			echo '<tr><td colspan="7">' . esc_html__( 'Ingen prisregler funnet.', 'snippen-booking' ) . '</td></tr>';
		} else {
			foreach ( $rules as $rule ) {
				$edit_url   = admin_url( 'admin.php?page=snippen-booking-pricing&action=edit&id=' . $rule->id );
				$delete_url = wp_nonce_url( admin_url( 'admin.php?page=snippen-booking-pricing&action=delete&id=' . $rule->id ), 'delete_price_' . $rule->id );

				// Get linked objects
				$objs        = $wpdb->get_col(
					$wpdb->prepare(
						"
                    SELECT o.name 
                    FROM $table_price_objects po 
                    JOIN $table_objects o ON po.booking_object_id = o.id 
                    WHERE po.price_id = %d",
						$rule->id
					)
				);
				$object_list = implode( ', ', $objs );

				$conditions = array();
				if ( $rule->is_holiday ) {
					$conditions[] = __( 'Helligdag', 'snippen-booking' );
				}
				if ( $rule->days_of_week !== null && $rule->days_of_week !== '' ) {
					$days          = array(
						1 => 'Man',
						2 => 'Tir',
						3 => 'Ons',
						4 => 'Tor',
						5 => 'Fre',
						6 => 'Lør',
						0 => 'Søn',
					);
					$selected_days = explode( ',', $rule->days_of_week );
					$day_labels    = array();
					foreach ( $selected_days as $d ) {
						if ( isset( $days[ $d ] ) ) {
							$day_labels[] = $days[ $d ];
						}
					}
					$conditions[] = implode( ',', $day_labels );
				}
				if ( $rule->date_start ) {
					$conditions[] = substr( $rule->date_start, 5 ) . ' til ' . substr( $rule->date_end, 5 );
				}

				echo '<tr>';
				echo '<td><strong><a href="' . esc_url( $edit_url ) . '">' . esc_html( $rule->name ) . '</a></strong></td>';
				echo '<td><small>' . esc_html( $object_list ) . '</small></td>';
				echo '<td>' . esc_html( $rule->slot_name ) . '</td>';
				echo '<td>' . esc_html( number_format( $rule->price, 0, ',', ' ' ) ) . ',-</td>';
				echo '<td>' . esc_html( $rule->priority ) . '</td>';
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
		$table_prices        = $wpdb->prefix . 'snippen_prices';
		$table_slots         = $wpdb->prefix . 'snippen_time_slots';
		$table_objects       = $wpdb->prefix . 'snippen_booking_objects';
		$table_price_objects = $wpdb->prefix . 'snippen_price_booking_objects';

		$rule             = null;
		$selected_objects = array();

		if ( $id > 0 ) {
			$rule             = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_prices WHERE id = %d", $id ) );
			$selected_objects = $wpdb->get_col( $wpdb->prepare( "SELECT booking_object_id FROM $table_price_objects WHERE price_id = %d", $id ) );
		}

		$all_slots   = $wpdb->get_results(
			"
            SELECT s.id, s.name, s.start_time, o.name as object_name 
            FROM $table_slots s 
            JOIN $table_objects o ON s.booking_object_id = o.id 
            WHERE s.deleted_at IS NULL 
            ORDER BY o.name ASC, s.start_time ASC"
		);
		$all_objects = $wpdb->get_results( "SELECT id, name FROM $table_objects WHERE deleted_at IS NULL ORDER BY name ASC" );

		echo '<div class="snippen-card"><form method="post" action="">';
		wp_nonce_field( 'snippen_save_price', 'snippen_price_nonce' );
		echo '<input type="hidden" name="id" value="' . esc_attr( $id ) . '">';

		echo '<div class="snippen-form-group">';
		echo '<label for="name">' . esc_html__( 'Navn på prisregel', 'snippen-booking' ) . '</label>';
		echo '<input type="text" name="name" id="name" value="' . esc_attr( $rule ? $rule->name : '' ) . '" required class="regular-text" placeholder="F.eks. Helgepris Festsalen">';
		echo '</div>';

		echo '<div class="snippen-form-group">';
		echo '<label>' . esc_html__( 'Gjelder for følgende lokaler:', 'snippen-booking' ) . '</label>';
		echo '<div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap:10px; margin-top:10px; background:#f1f5f9; padding:15px; border-radius:8px;">';
		foreach ( $all_objects as $obj ) {
			echo '<label style="font-weight:normal;"><input type="checkbox" name="object_ids[]" value="' . esc_attr( $obj->id ) . '" ' . checked( in_array( $obj->id, $selected_objects ), true, false ) . '> ' . esc_html( $obj->name ) . '</label>';
		}
		echo '</div></div>';

		echo '<div class="snippen-form-group">';
		echo '<label for="slot_id">' . esc_html__( 'Tidsluke', 'snippen-booking' ) . '</label>';
		echo '<select name="slot_id" id="slot_id" required>';
		echo '<option value="">' . esc_html__( 'Velg tidsluke...', 'snippen-booking' ) . '</option>';

		$current_obj = '';
		foreach ( $all_slots as $s ) {
			if ( $current_obj !== $s->object_name ) {
				if ( $current_obj !== '' ) {
					echo '</optgroup>';
				}
				echo '<optgroup label="' . esc_attr( $s->object_name ) . '">';
				$current_obj = $s->object_name;
			}
			echo '<option value="' . esc_attr( $s->id ) . '" ' . selected( $rule ? $rule->slot_id : 0, $s->id, false ) . '>' . esc_html( $s->name ) . ' (' . substr( $s->start_time, 0, 5 ) . ')</option>';
		}
		if ( $current_obj !== '' ) {
			echo '</optgroup>';
		}
		echo '</select>';
		echo '</div>';

		echo '<div class="snippen-form-group" style="display:flex; gap:20px;">';
		echo '<div><label for="price">' . esc_html__( 'Pris (kr)', 'snippen-booking' ) . '</label>';
		echo '<input type="number" name="price" id="price" value="' . esc_attr( $rule ? $rule->price : 0 ) . '" required style="max-width:150px;"></div>';
		echo '<div><label for="priority">' . esc_html__( 'Prioritet', 'snippen-booking' ) . '</label>';
		echo '<input type="number" name="priority" id="priority" value="' . esc_attr( $rule ? $rule->priority : 10 ) . '" style="max-width:100px;"></div>';
		echo '</div>';
		echo '<p class="description">' . esc_html__( 'Høyere prioritet (f.eks. 100) overstyrer lavere (f.eks. 10). Bruk høy prioritet for spesielle datoer.', 'snippen-booking' ) . '</p>';

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
		$selected_days = $rule && $rule->days_of_week !== null ? explode( ',', $rule->days_of_week ) : array();
		foreach ( $days as $val => $label ) {
			echo '<label style="font-weight:normal;"><input type="checkbox" name="days_of_week[]" value="' . esc_attr( $val ) . '" ' . checked( in_array( (string) $val, $selected_days ), true, false ) . '> ' . esc_html( $label ) . '</label>';
		}
		echo '</div></div>';

		echo '<div class="snippen-form-group" style="display:flex; gap:20px;">';
		echo '<div><label for="start_date">' . esc_html__( 'Startdato (YYYY-MM-DD)', 'snippen-booking' ) . '</label>';
		echo '<input type="date" name="start_date" id="start_date" value="' . esc_attr( $rule ? $rule->date_start : '' ) . '"></div>';
		echo '<div><label for="end_date">' . esc_html__( 'Sluttdato (YYYY-MM-DD)', 'snippen-booking' ) . '</label>';
		echo '<input type="date" name="end_date" id="end_date" value="' . esc_attr( $rule ? $rule->date_end : '' ) . '"></div>';
		echo '</div>';

		echo '<div class="snippen-form-group">';
		echo '<label><input type="checkbox" name="is_holiday" value="1" ' . checked( $rule ? $rule->is_holiday : 0, 1, false ) . '> ' . esc_html__( 'Gjelder kun helligdager', 'snippen-booking' ) . '</label>';
		echo '</div>';

		echo '<div class="snippen-form-actions" style="margin-top:30px;">';
		echo '<button type="submit" class="snippen-btn snippen-btn-primary">' . esc_html__( 'Lagre prisregel', 'snippen-booking' ) . '</button>';
		echo '</div>';

		echo '</form></div>';
	}
}
