<?php

namespace SnippenBooking\Admin\Pages;

/**
 * Admin page for managing Pricing Rules
 */
class PricingPage {

	/**
	 * Validation errors
	 *
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
		$table_prices = $wpdb->prefix . 'snippen_prices';

		$id         = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
		$name       = sanitize_text_field( $_POST['name'] );
		$price      = floatval( $_POST['price'] );
		$priority   = intval( $_POST['priority'] );
		$time_slots = isset( $_POST['time_slots'] ) && is_array( $_POST['time_slots'] ) ? array_map( 'intval', $_POST['time_slots'] ) : array();

		$data = array(
			'name'        => $name,
			'price'       => $price,
			'priority'    => $priority,
			'modified_at' => current_time( 'mysql' ),
		);

		if ( $id > 0 ) {
			$wpdb->update( $table_prices, $data, array( 'id' => $id ) );
			$message_key = 'updated';
		} else {
			$data['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $table_prices, $data );
			$id          = $wpdb->insert_id;
			$message_key = 'created';
		}

		$table_slots = $wpdb->prefix . 'snippen_time_slots';
		$wpdb->update( $table_slots, array( 'price_id' => null ), array( 'price_id' => $id ) );
		if ( ! empty( $time_slots ) ) {
			$slot_in = implode( ',', $time_slots );
			$wpdb->query( $wpdb->prepare( "UPDATE $table_slots SET price_id = %d WHERE id IN ($slot_in)", $id ) );
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
		$table_prices = $wpdb->prefix . 'snippen_prices';

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
		$table_prices = $wpdb->prefix . 'snippen_prices';
		$table_slots  = $wpdb->prefix . 'snippen_time_slots';

		$rules = $wpdb->get_results(
			"
            SELECT p.*, GROUP_CONCAT(s.name SEPARATOR '<br>') as slot_names 
            FROM $table_prices p 
            LEFT JOIN $table_slots s ON p.id = s.price_id 
            GROUP BY p.id
            ORDER BY p.priority DESC, p.name ASC"
		);

		echo '<div class="snippen-card" id="snippen-pricing-list">';
		echo '<table class="snippen-list-table snippen-filterable-table" id="pricing-rules-table">';
		echo '<thead><tr>';
		echo '<th data-filter-type="text" data-sort-type="string">' . esc_html__( 'Navn', 'snippen-booking' ) . '</th>';
		echo '<th data-filter-type="multiselect" data-sort-type="string">' . esc_html__( 'Tidsluke', 'snippen-booking' ) . '</th>';
		echo '<th data-filter-type="minmax" data-sort-type="number">' . esc_html__( 'Pris', 'snippen-booking' ) . '</th>';
		echo '<th data-filter-type="minmax" data-sort-type="number">' . esc_html__( 'Prioritet', 'snippen-booking' ) . '</th>';
		echo '<th style="text-align:right;">' . esc_html__( 'Handlinger', 'snippen-booking' ) . '</th>';
		echo '</tr></thead>';
		echo '<tbody>';

		if ( empty( $rules ) ) {
			echo '<tr><td colspan="6">' . esc_html__( 'Ingen prisregler funnet.', 'snippen-booking' ) . '</td></tr>';
		} else {
			foreach ( $rules as $rule ) {
				$edit_url   = admin_url( 'admin.php?page=snippen-booking-pricing&action=edit&id=' . $rule->id );
				$delete_url = wp_nonce_url( admin_url( 'admin.php?page=snippen-booking-pricing&action=delete&id=' . $rule->id ), 'delete_price_' . $rule->id );
				echo '<tr>';
				echo '<td><strong><a href="' . esc_url( $edit_url ) . '">' . esc_html( $rule->name ) . '</a></strong></td>';
				echo '<td>' . wp_kses_post( $rule->slot_names ) . '</td>';
				echo '<td>' . esc_html( $rule->price ) . ' kr</td>';
				echo '<td>' . esc_html( $rule->priority ) . '</td>';
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
		$table_prices = $wpdb->prefix . 'snippen_prices';
		$table_slots  = $wpdb->prefix . 'snippen_time_slots';

		$rule = null;

		if ( $id > 0 ) {
			$rule = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_prices WHERE id = %d", $id ) );
		}

		$all_slots = $wpdb->get_results(
			"
            SELECT s.id, s.name, s.start_time 
            FROM $table_slots s 
            WHERE s.deleted_at IS NULL 
            ORDER BY s.start_time ASC"
		);

		echo '<div class="snippen-card"><form method="post" action="">';
		wp_nonce_field( 'snippen_save_price', 'snippen_price_nonce' );
		echo '<input type="hidden" name="id" value="' . esc_attr( $id ) . '">';

		echo '<div class="snippen-form-group">';
		echo '<label for="name">' . esc_html__( 'Navn på prisregel', 'snippen-booking' ) . '</label>';
		echo '<input type="text" name="name" id="name" value="' . esc_attr( $rule ? $rule->name : '' ) . '" required class="regular-text" placeholder="' . esc_attr__( 'F.eks. Helgepris Festsalen', 'snippen-booking' ) . '">';
		echo '</div>';

		$selected_slots = array();
		if ( $id > 0 ) {
			$selected_slots = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM $table_slots WHERE price_id = %d", $id ) );
		}

		echo '<div class="snippen-form-group">';
		echo '<label>' . esc_html__( 'Tidsluker', 'snippen-booking' ) . '</label>';
		echo '<input type="text" id="snippen-timeslot-filter" placeholder="' . esc_attr__( 'Søk i tidsluker...', 'snippen-booking' ) . '" style="width: 100%; margin-bottom: 10px; max-width: 400px; display: block;" autocomplete="off">';
		echo '<div id="snippen-timeslot-list" style="max-height: 250px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #fff; border-radius: 4px;">';

		if ( empty( $all_slots ) ) {
			echo '<p>' . esc_html__( 'Ingen tidsluker funnet.', 'snippen-booking' ) . '</p>';
		} else {
			foreach ( $all_slots as $s ) {
				$checked = in_array( $s->id, $selected_slots ) ? 'checked' : '';
				echo '<div class="snippen-timeslot-item" style="margin-bottom: 5px;" data-search="' . esc_attr( strtolower( $s->name ) ) . '">';
				echo '<label style="font-weight: normal; display: flex; align-items: center; gap: 8px;">';
				echo '<input type="checkbox" name="time_slots[]" value="' . esc_attr( $s->id ) . '" ' . $checked . '>';
				echo esc_html( $s->name ) . ' (' . substr( $s->start_time, 0, 5 ) . ')';
				echo '</label>';
				echo '</div>';
			}
		}
		
		echo '</div>';
		echo '</div>';

		echo '<div class="snippen-form-group" style="display:flex; gap:20px;">';
		echo '<div><label for="price">' . esc_html__( 'Pris (kr)', 'snippen-booking' ) . '</label>';
		echo '<input type="number" name="price" id="price" value="' . esc_attr( $rule ? $rule->price : 0 ) . '" required style="max-width:150px;"></div>';
		echo '<div><label for="priority">' . esc_html__( 'Prioritet', 'snippen-booking' ) . '</label>';
		echo '<input type="number" name="priority" id="priority" value="' . esc_attr( $rule ? $rule->priority : 10 ) . '" style="max-width:100px;"></div>';
		echo '</div>';
		echo '<p class="description">' . esc_html__( 'Høyere prioritet (f.eks. 100) overstyrer lavere (f.eks. 10). Bruk høy prioritet for spesielle regler.', 'snippen-booking' ) . '</p>';

		echo '<div class="snippen-form-actions" style="margin-top:30px;">';
		echo '<button type="submit" class="snippen-btn snippen-btn-primary">' . esc_html__( 'Lagre prisregel', 'snippen-booking' ) . '</button>';
		echo '</div>';

		echo '</form></div>';

		// Script for filtering
		?>
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			var filterInput = document.getElementById('snippen-timeslot-filter');
			if (filterInput) {
				filterInput.addEventListener('input', function(e) {
					var term = e.target.value.toLowerCase();
					// Convert term to a regex: escape all special chars except '*', then replace '*' with '.*'
					var regexTerm = term.replace(/[.+?^${}()|[\]\\]/g, '\\$&').replace(/\*/g, '.*');
					var regex = new RegExp(regexTerm);

					var items = document.querySelectorAll('.snippen-timeslot-item');
					items.forEach(function(item) {
						var searchData = item.getAttribute('data-search');
						if (regex.test(searchData)) {
							item.style.display = '';
						} else {
							item.style.display = 'none';
						}
					});
				});
			}
		});
		</script>
		<?php
	}
}
