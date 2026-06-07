<?php

namespace SnippenBooking\Admin\Pages;

use SnippenBooking\Database\Repository\PricingRuleRepository;
use SnippenBooking\Database\Repository\BookingBlockRepository;

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

		// Render success/info messages
		if ( isset( $_GET['message'] ) ) {
			$msg_type = sanitize_text_field( $_GET['message'] );
			if ( $msg_type === 'created' ) {
				$this->show_message( __( 'Prisregel lagret.', 'snippen-booking' ) );
			} elseif ( $msg_type === 'updated' ) {
				$this->show_message( __( 'Prisregel oppdatert.', 'snippen-booking' ) );
			} elseif ( $msg_type === 'deleted' ) {
				$this->show_message( __( 'Prisregel slettet.', 'snippen-booking' ) );
			} elseif ( $msg_type === 'duplicated' ) {
				$this->show_message( __( 'Prisregel duplisert.', 'snippen-booking' ) );
			}
		}

		switch ( $action ) {
			case 'add':
			case 'edit':
				$this->render_form( $id );
				break;
			default:
				$this->render_list();
				$this->render_preview_tool();
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
	 * Handle POST requests (Save/Delete/Duplicate)
	 */
	public function handle_request() {
		if ( ! isset( $_POST['snippen_price_nonce'] ) || ! wp_verify_nonce( $_POST['snippen_price_nonce'], 'snippen_save_price' ) ) {
			if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && isset( $_GET['id'] ) ) {
				if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'delete_price_' . $_GET['id'] ) ) {
					$this->delete_price( intval( $_GET['id'] ) );
				}
			}
			if ( isset( $_GET['action'] ) && $_GET['action'] === 'duplicate' && isset( $_GET['id'] ) ) {
				if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'duplicate_price_' . $_GET['id'] ) ) {
					$this->duplicate_price( intval( $_GET['id'] ) );
				}
			}
			return;
		}

		$id             = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
		$name           = sanitize_text_field( $_POST['name'] );
		$description    = sanitize_textarea_field( $_POST['description'] );
		$price          = floatval( $_POST['price'] );
		$priority       = intval( $_POST['priority'] );
		$is_active      = isset( $_POST['is_active'] ) ? 1 : 0;
		$holiday_only   = isset( $_POST['holiday_only'] ) ? 1 : 0;
		$date_start     = ! empty( $_POST['date_start'] ) ? sanitize_text_field( $_POST['date_start'] ) : null;
		$date_end       = ! empty( $_POST['date_end'] ) ? sanitize_text_field( $_POST['date_end'] ) : null;

		$object_ids     = isset( $_POST['booking_objects'] ) ? array_map( 'intval', (array) $_POST['booking_objects'] ) : array();
		$block_ids      = isset( $_POST['booking_blocks'] ) ? array_map( 'intval', (array) $_POST['booking_blocks'] ) : array();
		
		$days_of_week   = isset( $_POST['days_of_week'] ) ? array_map( 'sanitize_text_field', $_POST['days_of_week'] ) : array();
		$days_of_week   = ! empty( $days_of_week ) ? implode( ',', $days_of_week ) : null;

		if ( empty( $object_ids ) || empty( $block_ids ) ) {
			$this->errors[] = __( 'Du må velge minst ett lokale og én bookingblokk.', 'snippen-booking' );
			return;
		}

		$data = array(
			'name'         => $name,
			'description'  => $description,
			'price'        => $price,
			'priority'     => $priority,
			'is_active'    => $is_active,
			'holiday_only' => $holiday_only,
			'date_start'   => $date_start,
			'date_end'     => $date_end,
			'days_of_week' => $days_of_week,
		);

		$repo = new PricingRuleRepository();
		$saved_id = $repo->save( $data, $id > 0 ? $id : null );

		if ( $saved_id ) {
			$repo->sync_booking_objects( $saved_id, $object_ids );
			$repo->sync_booking_blocks( $saved_id, $block_ids );
		}

		if ( $id > 0 ) {
			wp_safe_redirect( admin_url( 'admin.php?page=snippen-booking-pricing&message=updated' ) );
		} else {
			wp_safe_redirect( admin_url( 'admin.php?page=snippen-booking-pricing&message=created' ) );
		}
		exit;
	}

	/**
	 * Delete price
	 */
	private function delete_price( $id ) {
		$repo = new PricingRuleRepository();
		$repo->delete( $id );
		wp_safe_redirect( admin_url( 'admin.php?page=snippen-booking-pricing&message=deleted' ) );
		exit;
	}

	/**
	 * Duplicate price
	 */
	private function duplicate_price( $id ) {
		$repo = new PricingRuleRepository();
		$rule = $repo->find( $id );
		if ( $rule ) {
			$data = array(
				'name'         => $rule->name . ' (Kopi)',
				'description'  => $rule->description,
				'price'        => $rule->price,
				'priority'     => $rule->priority,
				'is_active'    => 0, // Disable by default on duplicate
				'holiday_only' => $rule->holiday_only,
				'date_start'   => $rule->date_start,
				'date_end'     => $rule->date_end,
				'days_of_week' => $rule->days_of_week,
			);
			
			$new_id = $repo->save( $data );
			if ( $new_id ) {
				$objects = $repo->get_rule_objects( $id );
				$blocks = $repo->get_rule_blocks( $id );
				$repo->sync_booking_objects( $new_id, $objects );
				$repo->sync_booking_blocks( $new_id, $blocks );
			}
		}
		wp_safe_redirect( admin_url( 'admin.php?page=snippen-booking-pricing&message=duplicated' ) );
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
		$table_rules = $wpdb->prefix . 'snippen_pricing_rules';
		$table_rule_objects = $wpdb->prefix . 'snippen_pricing_rule_booking_objects';
		$table_rule_blocks = $wpdb->prefix . 'snippen_pricing_rule_booking_blocks';
		$table_objects = $wpdb->prefix . 'snippen_booking_objects';
		$table_blocks = $wpdb->prefix . 'snippen_booking_blocks';

		$query = "SELECT r.*, 
		          GROUP_CONCAT(DISTINCT o.name SEPARATOR ', ') as object_names,
				  GROUP_CONCAT(DISTINCT b.name SEPARATOR ', ') as block_names
                  FROM $table_rules r 
                  LEFT JOIN $table_rule_objects ro ON r.id = ro.pricing_rule_id
                  LEFT JOIN $table_objects o ON ro.booking_object_id = o.id
				  LEFT JOIN $table_rule_blocks rb ON r.id = rb.pricing_rule_id
				  LEFT JOIN $table_blocks b ON rb.booking_block_id = b.id
                  WHERE r.deleted_at IS NULL 
                  GROUP BY r.id 
				  ORDER BY r.priority DESC, r.name ASC";

		$rules = $wpdb->get_results( $query );

		echo '<div class="snippen-card" id="snippen-pricing-list">';
		echo '<table class="snippen-list-table snippen-filterable-table" id="pricing-rules-table">';
		echo '<thead><tr>';
		echo '<th data-filter-type="text" data-sort-type="string">' . esc_html__( 'Navn', 'snippen-booking' ) . '</th>';
		echo '<th data-filter-type="text" data-sort-type="string">' . esc_html__( 'Lokaler', 'snippen-booking' ) . '</th>';
		echo '<th data-filter-type="text" data-sort-type="string">' . esc_html__( 'Bookingblokker', 'snippen-booking' ) . '</th>';
		echo '<th data-filter-type="minmax" data-sort-type="number">' . esc_html__( 'Pris', 'snippen-booking' ) . '</th>';
		echo '<th data-filter-type="minmax" data-sort-type="number">' . esc_html__( 'Prioritet', 'snippen-booking' ) . '</th>';
		echo '<th data-filter-type="text" data-sort-type="string">' . esc_html__( 'Aktiv Periode', 'snippen-booking' ) . '</th>';
		echo '<th data-filter-type="select" data-sort-type="string">' . esc_html__( 'Status', 'snippen-booking' ) . '</th>';
		echo '<th style="text-align:right;">' . esc_html__( 'Handlinger', 'snippen-booking' ) . '</th>';
		echo '</tr></thead>';
		echo '<tbody>';

		if ( empty( $rules ) ) {
			echo '<tr><td colspan="8">' . esc_html__( 'Ingen prisregler funnet.', 'snippen-booking' ) . '</td></tr>';
		} else {
			foreach ( $rules as $rule ) {
				$edit_url   = admin_url( 'admin.php?page=snippen-booking-pricing&action=edit&id=' . $rule->id );
				$delete_url = wp_nonce_url( admin_url( 'admin.php?page=snippen-booking-pricing&action=delete&id=' . $rule->id ), 'delete_price_' . $rule->id );
				$duplicate_url = wp_nonce_url( admin_url( 'admin.php?page=snippen-booking-pricing&action=duplicate&id=' . $rule->id ), 'duplicate_price_' . $rule->id );

				$period = '-';
				if ( ! empty( $rule->date_start ) || ! empty( $rule->date_end ) ) {
					$start = ! empty( $rule->date_start ) ? $rule->date_start : '...';
					$end   = ! empty( $rule->date_end ) ? $rule->date_end : '...';
					$period = $start . ' til ' . $end;
				}

				$status = (int) $rule->is_active === 1 
					? '<span class="snippen-badge snippen-status-confirmed">' . esc_html__( 'Aktiv', 'snippen-booking' ) . '</span>'
					: '<span class="snippen-badge snippen-status-cancelled">' . esc_html__( 'Deaktivert', 'snippen-booking' ) . '</span>';

				echo '<tr>';
				echo '<td><strong><a href="' . esc_url( $edit_url ) . '">' . esc_html( $rule->name ) . '</a></strong></td>';
				echo '<td>' . esc_html( $rule->object_names ?: '-' ) . '</td>';
				echo '<td>' . esc_html( $rule->block_names ?: '-' ) . '</td>';
				echo '<td>' . esc_html( number_format( $rule->price, 0, ',', ' ' ) ) . ' kr</td>';
				echo '<td>' . esc_html( $rule->priority ) . '</td>';
				echo '<td>' . esc_html( $period ) . '</td>';
				echo '<td>' . wp_kses_post( $status ) . '</td>';
				echo '<td style="text-align:right;">';
				echo '<a href="' . esc_url( $edit_url ) . '" class="snippen-btn snippen-btn-outline" style="margin-right:5px;" title="' . esc_attr__( 'Rediger', 'snippen-booking' ) . '"><span class="dashicons dashicons-edit"></span></a>';
				echo '<a href="' . esc_url( $duplicate_url ) . '" class="snippen-btn snippen-btn-outline" style="margin-right:5px;" title="' . esc_attr__( 'Dupliser', 'snippen-booking' ) . '"><span class="dashicons dashicons-admin-page"></span></a>';
				echo '<a href="' . esc_url( $delete_url ) . '" class="snippen-btn snippen-btn-outline snippen-btn-danger snippen-delete-confirm" title="' . esc_attr__( 'Slett', 'snippen-booking' ) . '"><span class="dashicons dashicons-trash"></span></a>';
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
		$repo = new PricingRuleRepository();
		$rule = $id > 0 ? $repo->find( $id ) : null;

		echo '<div class="snippen-card"><form method="post" action="">';
		wp_nonce_field( 'snippen_save_price', 'snippen_price_nonce' );
		echo '<input type="hidden" name="id" value="' . esc_attr( $id ) . '">';

		echo '<div class="snippen-form-group" style="display:flex; justify-content:space-between; align-items:center;">';
		echo '<div><label for="name">' . esc_html__( 'Navn på prisregel', 'snippen-booking' ) . '</label>';
		echo '<input type="text" name="name" id="name" value="' . esc_attr( $rule ? $rule->name : '' ) . '" required class="regular-text" placeholder="' . esc_attr__( 'F.eks. Helgepris Festsalen', 'snippen-booking' ) . '"></div>';
		
		echo '<div><label style="font-weight:bold; display:flex; align-items:center; gap:8px;">';
		$is_active = $rule ? (int) $rule->is_active : 1;
		echo '<input type="checkbox" name="is_active" value="1" ' . checked( $is_active, 1, false ) . '>';
		echo esc_html__( 'Regelen er aktiv', 'snippen-booking' );
		echo '</label></div>';
		echo '</div>';

		echo '<div class="snippen-form-group">';
		echo '<label for="description">' . esc_html__( 'Beskrivelse (valgfritt)', 'snippen-booking' ) . '</label>';
		echo '<textarea name="description" id="description" rows="2" class="large-text">' . esc_textarea( $rule ? $rule->description : '' ) . '</textarea>';
		echo '</div>';

		echo '<div class="snippen-form-group" style="display:flex; gap:20px;">';
		echo '<div><label for="price">' . esc_html__( 'Pris (kr)', 'snippen-booking' ) . '</label>';
		echo '<input type="number" name="price" id="price" value="' . esc_attr( $rule ? $rule->price : 0 ) . '" required style="max-width:150px;"></div>';
		echo '<div><label for="priority">' . esc_html__( 'Prioritet', 'snippen-booking' ) . '</label>';
		echo '<input type="number" name="priority" id="priority" value="' . esc_attr( $rule ? $rule->priority : 10 ) . '" style="max-width:100px;"></div>';
		echo '</div>';
		echo '<p class="description" style="margin-top:-10px; margin-bottom:20px;">' . esc_html__( 'Høyere prioritet overstyrer lavere prioritet. Standard regler bør ha lav prioritet (f.eks. 10), unntak bør ha høy (f.eks. 100).', 'snippen-booking' ) . '</p>';

		echo '<hr><h3 style="margin-top:25px;">' . esc_html__( 'Omfang', 'snippen-booking' ) . '</h3>';

		echo '<div style="display:flex; gap:40px;">';
		
		// Booking Objects
		echo '<div class="snippen-form-group" style="flex:1;">';
		echo '<label>' . esc_html__( 'Gjelder for lokaler:', 'snippen-booking' ) . '</label>';
		echo '<div style="display:flex; flex-direction:column; gap:5px; margin-top:5px;">';
		$objects = $wpdb->get_results( "SELECT id, name FROM {$wpdb->prefix}snippen_booking_objects WHERE deleted_at IS NULL" );
		$selected_objects = $id > 0 ? $repo->get_rule_objects( $id ) : array();
		foreach ( $objects as $obj ) {
			echo '<label style="font-weight:normal;">';
			echo '<input type="checkbox" name="booking_objects[]" value="' . esc_attr( $obj->id ) . '" ' . checked( in_array( $obj->id, $selected_objects ), true, false ) . '> ';
			echo esc_html( $obj->name );
			echo '</label>';
		}
		echo '</div></div>';

		// Booking Blocks
		echo '<div class="snippen-form-group" style="flex:1;">';
		echo '<label>' . esc_html__( 'Gjelder for bookingblokker:', 'snippen-booking' ) . '</label>';
		echo '<div style="display:flex; flex-direction:column; gap:5px; margin-top:5px; max-height:200px; overflow-y:auto; padding:10px; border:1px solid #ddd; background:#fff;">';
		$blocks = $wpdb->get_results( "SELECT id, name, start_time, end_time FROM {$wpdb->prefix}snippen_booking_blocks WHERE deleted_at IS NULL ORDER BY sort_order ASC" );
		$selected_blocks = $id > 0 ? $repo->get_rule_blocks( $id ) : array();
		foreach ( $blocks as $b ) {
			echo '<label style="font-weight:normal;">';
			echo '<input type="checkbox" name="booking_blocks[]" value="' . esc_attr( $b->id ) . '" ' . checked( in_array( $b->id, $selected_blocks ), true, false ) . '> ';
			echo esc_html( $b->name ) . ' (' . esc_html( substr( $b->start_time, 0, 5 ) . '-' . substr( $b->end_time, 0, 5 ) ) . ')';
			echo '</label>';
		}
		echo '</div></div>';

		echo '</div>'; // flex

		echo '<hr><h3 style="margin-top:25px;">' . esc_html__( 'Dato- og dagbegrensninger (Valgfritt)', 'snippen-booking' ) . '</h3>';

		echo '<div class="snippen-form-group" style="display:flex; gap:20px;">';
		echo '<div><label for="date_start">' . esc_html__( 'Fra og med dato', 'snippen-booking' ) . '</label>';
		echo '<input type="date" name="date_start" id="date_start" value="' . esc_attr( $rule ? $rule->date_start : '' ) . '" style="max-width:150px;"></div>';
		echo '<div><label for="date_end">' . esc_html__( 'Til og med dato', 'snippen-booking' ) . '</label>';
		echo '<input type="date" name="date_end" id="date_end" value="' . esc_attr( $rule ? $rule->date_end : '' ) . '" style="max-width:150px;"></div>';
		echo '</div>';

		echo '<div class="snippen-form-group">';
		echo '<label>' . esc_html__( 'Gjelder kun disse ukedagene:', 'snippen-booking' ) . '</label>';
		echo '<div style="display:flex; flex-wrap:wrap; gap:15px; margin-top:5px;">';
		$days = array(
			'1' => __( 'Mandag', 'snippen-booking' ),
			'2' => __( 'Tirsdag', 'snippen-booking' ),
			'3' => __( 'Onsdag', 'snippen-booking' ),
			'4' => __( 'Torsdag', 'snippen-booking' ),
			'5' => __( 'Fredag', 'snippen-booking' ),
			'6' => __( 'Lørdag', 'snippen-booking' ),
			'0' => __( 'Søndag', 'snippen-booking' ),
		);
		$selected_days = $rule && $rule->days_of_week !== null && $rule->days_of_week !== '' ? explode( ',', $rule->days_of_week ) : array();
		foreach ( $days as $val => $label ) {
			echo '<label style="font-weight:normal;"><input type="checkbox" name="days_of_week[]" value="' . esc_attr( $val ) . '" ' . checked( in_array( (string) $val, $selected_days ), true, false ) . '> ' . esc_html( $label ) . '</label>';
		}
		echo '</div>';
		echo '<p class="description">' . esc_html__( 'La alle stå tomme for å gjelde alle dager.', 'snippen-booking' ) . '</p>';
		echo '</div>';

		echo '<div class="snippen-form-group">';
		$holiday_only = $rule ? (int) $rule->holiday_only : 0;
		echo '<label style="font-weight:bold; display:flex; align-items:center; gap:8px;">';
		echo '<input type="checkbox" name="holiday_only" value="1" ' . checked( $holiday_only, 1, false ) . '>';
		echo esc_html__( 'Gjelder KUN på helligdager', 'snippen-booking' );
		echo '</label>';
		echo '</div>';

		echo '<div class="snippen-form-actions" style="margin-top:30px;">';
		echo '<button type="submit" class="snippen-btn snippen-btn-primary">' . esc_html__( 'Lagre prisregel', 'snippen-booking' ) . '</button>';
		echo '</div>';

		echo '</form></div>';
	}

	/**
	 * Render Preview Tool UI
	 */
	private function render_preview_tool() {
		global $wpdb;
		$objects = $wpdb->get_results( "SELECT id, name FROM {$wpdb->prefix}snippen_booking_objects WHERE deleted_at IS NULL" );
		$blocks = $wpdb->get_results( "SELECT id, name, start_time, end_time FROM {$wpdb->prefix}snippen_booking_blocks WHERE deleted_at IS NULL ORDER BY sort_order ASC" );

		echo '<div class="snippen-card" style="margin-top: 30px; border-top: 4px solid #3b82f6;">';
		echo '<h2>' . esc_html__( 'Prisforhåndsvisning (Test av regler)', 'snippen-booking' ) . '</h2>';
		echo '<p>' . esc_html__( 'Bruk dette verktøyet for å teste hvilken prisregel som vil vinne i et gitt scenario.', 'snippen-booking' ) . '</p>';
		
		echo '<div style="display:flex; flex-wrap:wrap; gap:15px; margin-bottom: 20px;">';
		
		echo '<div>';
		echo '<label style="display:block; margin-bottom:5px; font-weight:600;">' . esc_html__( 'Dato', 'snippen-booking' ) . '</label>';
		echo '<input type="date" id="preview-date" value="' . esc_attr( date( 'Y-m-d' ) ) . '">';
		echo '</div>';

		echo '<div>';
		echo '<label style="display:block; margin-bottom:5px; font-weight:600;">' . esc_html__( 'Lokale', 'snippen-booking' ) . '</label>';
		echo '<select id="preview-object">';
		foreach ( $objects as $obj ) {
			echo '<option value="' . esc_attr( $obj->id ) . '">' . esc_html( $obj->name ) . '</option>';
		}
		echo '</select>';
		echo '</div>';

		echo '<div>';
		echo '<label style="display:block; margin-bottom:5px; font-weight:600;">' . esc_html__( 'Bookingblokk', 'snippen-booking' ) . '</label>';
		echo '<select id="preview-block">';
		foreach ( $blocks as $b ) {
			echo '<option value="' . esc_attr( $b->id ) . '">' . esc_html( $b->name ) . '</option>';
		}
		echo '</select>';
		echo '</div>';

		echo '<div style="display:flex; align-items:flex-end;">';
		echo '<button type="button" id="preview-btn" class="snippen-btn snippen-btn-primary">' . esc_html__( 'Finn pris', 'snippen-booking' ) . '</button>';
		echo '</div>';

		echo '</div>'; // flex inputs

		echo '<div id="preview-result" style="display:none; padding:15px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:4px;"></div>';

		echo '</div>'; // card

		?>
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			var previewBtn = document.getElementById('preview-btn');
			if(previewBtn) {
				previewBtn.addEventListener('click', function() {
					var date = document.getElementById('preview-date').value;
					var object_id = document.getElementById('preview-object').value;
					var block_id = document.getElementById('preview-block').value;
					var resultContainer = document.getElementById('preview-result');

					if(!date || !object_id || !block_id) {
						alert('Velg dato, lokale og bookingblokk.');
						return;
					}

					previewBtn.disabled = true;
					previewBtn.innerText = 'Beregner...';
					resultContainer.style.display = 'block';
					resultContainer.innerHTML = 'Laster...';

					var data = new URLSearchParams();
					data.append('action', 'snippen_pricing_preview');
					data.append('nonce', snippenAdmin.nonce);
					data.append('date', date);
					data.append('object_id', object_id);
					data.append('block_id', block_id);

					fetch(snippenAdmin.ajaxUrl, {
						method: 'POST',
						body: data
					})
					.then(response => response.json())
					.then(res => {
						previewBtn.disabled = false;
						previewBtn.innerText = 'Finn pris';
						
						if(res.success) {
							if(res.data.found) {
								var html = '<h3 style="margin-top:0; color:#16a34a;">Vinnende prisregel: ' + res.data.rule_name + '</h3>';
								if (res.data.discount_amount > 0) {
									html += '<p style="margin:5px 0;">Grunnpris: ' + res.data.rule_price.toLocaleString('no-NO') + ' kr</p>';
									html += '<p style="margin:5px 0; color:#16a34a;">Rabatt (' + res.data.discount_name + '): -' + res.data.discount_amount.toLocaleString('no-NO') + ' kr</p>';
									html += '<p style="font-size:24px; font-weight:bold; margin:10px 0;">Totalpris: ' + res.data.final_price.toLocaleString('no-NO') + ' kr</p>';
								} else {
									html += '<p style="font-size:24px; font-weight:bold; margin:10px 0;">' + res.data.rule_price.toLocaleString('no-NO') + ' kr</p>';
								}
								html += '<p style="margin:0;">Prioritet: ' + res.data.priority + '</p>';
								if(res.data.description) {
									html += '<p style="margin-top:5px; color:#64748b;">' + res.data.description + '</p>';
								}
								resultContainer.innerHTML = html;
							} else {
								resultContainer.innerHTML = '<p style="color:#b91c1c; font-weight:bold;">' + res.data.message + '</p>';
							}
						} else {
							resultContainer.innerHTML = '<p style="color:#b91c1c; font-weight:bold;">Feil: ' + res.data.message + '</p>';
						}
					})
					.catch(err => {
						previewBtn.disabled = false;
						previewBtn.innerText = 'Finn pris';
						resultContainer.innerHTML = '<p style="color:#b91c1c; font-weight:bold;">Nettverksfeil.</p>';
					});
				});
			}
		});
		</script>
		<?php
	}
}
