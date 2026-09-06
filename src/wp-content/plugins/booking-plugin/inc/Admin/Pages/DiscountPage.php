<?php

namespace SnippenBooking\Admin\Pages;

use SnippenBooking\Database\Repository\DiscountRuleRepository;

/**
 * Admin page for managing Discount Rules
 */
class DiscountPage {

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

		if ( ! empty( $this->errors ) ) {
			foreach ( $this->errors as $error ) {
				echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>';
			}
		}

		if ( isset( $_GET['message'] ) ) {
			$msg_type = sanitize_text_field( $_GET['message'] );
			if ( $msg_type === 'created' ) {
				$this->show_message( __( 'Rabattregel lagret.', 'snippen-booking' ) );
			} elseif ( $msg_type === 'updated' ) {
				$this->show_message( __( 'Rabattregel oppdatert.', 'snippen-booking' ) );
			} elseif ( $msg_type === 'deleted' ) {
				$this->show_message( __( 'Rabattregel slettet.', 'snippen-booking' ) );
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

	private function render_header( $action ) {
		$title = __( 'Rabattregler (Varighetsbasert)', 'snippen-booking' );
		if ( $action === 'add' ) {
			$title = __( 'Legg til ny rabattregel', 'snippen-booking' );
		}
		if ( $action === 'edit' ) {
			$title = __( 'Rediger rabattregel', 'snippen-booking' );
		}

		echo '<div class="snippen-admin-header">';
		echo '<h1>' . esc_html( $title ) . '</h1>';

		if ( $action === 'list' ) {
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=snippen-booking-discounts&action=add' ) ) . '" class="snippen-btn snippen-btn-primary">' . esc_html__( 'Legg til ny', 'snippen-booking' ) . '</a>';
		} else {
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=snippen-booking-discounts' ) ) . '" class="snippen-btn snippen-btn-outline">' . esc_html__( 'Tilbake til oversikt', 'snippen-booking' ) . '</a>';
		}

		echo '</div>';
	}

	public function handle_request() {
		if ( ! isset( $_POST['snippen_discount_nonce'] ) || ! wp_verify_nonce( $_POST['snippen_discount_nonce'], 'snippen_save_discount' ) ) {
			if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && isset( $_GET['id'] ) ) {
				if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'delete_discount_' . $_GET['id'] ) ) {
					$this->delete_discount( intval( $_GET['id'] ) );
				}
			}
			return;
		}

		$id                 = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
		$name               = sanitize_text_field( $_POST['name'] );
		$description        = sanitize_textarea_field( $_POST['description'] );
		$discount_type      = sanitize_text_field( $_POST['discount_type'] );
		$discount_value     = floatval( $_POST['discount_value'] );
		$min_duration_hours = ! empty( $_POST['min_duration_hours'] ) ? floatval( $_POST['min_duration_hours'] ) : null;
		$max_duration_hours = ! empty( $_POST['max_duration_hours'] ) ? floatval( $_POST['max_duration_hours'] ) : null;
		$priority           = intval( $_POST['priority'] );
		$is_active          = isset( $_POST['is_active'] ) ? 1 : 0;

		$days_of_week = isset( $_POST['days_of_week'] ) ? array_map( 'sanitize_text_field', $_POST['days_of_week'] ) : array();
		$days_of_week = ! empty( $days_of_week ) ? implode( ',', $days_of_week ) : null;
		$holiday_only = isset( $_POST['holiday_only'] ) ? 1 : 0;

		$object_ids = isset( $_POST['booking_objects'] ) ? array_map( 'intval', (array) $_POST['booking_objects'] ) : array();

		if ( empty( $object_ids ) ) {
			$this->errors[] = __( 'Du må velge minst ett lokale.', 'snippen-booking' );
			return;
		}

		$data = array(
			'name'               => $name,
			'description'        => $description,
			'discount_type'      => $discount_type,
			'discount_value'     => $discount_value,
			'min_duration_hours' => $min_duration_hours,
			'max_duration_hours' => $max_duration_hours,
			'days_of_week'       => $days_of_week,
			'holiday_only'       => $holiday_only,
			'priority'           => $priority,
			'is_active'          => $is_active,
		);

		$repo     = new DiscountRuleRepository();
		$saved_id = $repo->save( $data, $id > 0 ? $id : null );

		if ( $saved_id ) {
			$repo->sync_booking_objects( $saved_id, $object_ids );
		}

		if ( $id > 0 ) {
			wp_safe_redirect( admin_url( 'admin.php?page=snippen-booking-discounts&message=updated' ) );
		} else {
			wp_safe_redirect( admin_url( 'admin.php?page=snippen-booking-discounts&message=created' ) );
		}
		exit;
	}

	private function delete_discount( $id ) {
		$repo = new DiscountRuleRepository();
		$repo->delete( $id );
		wp_safe_redirect( admin_url( 'admin.php?page=snippen-booking-discounts&message=deleted' ) );
		exit;
	}

	private function show_message( $message ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}

	private function render_list() {
		global $wpdb;
		$table_rules        = $wpdb->prefix . 'snippen_discount_rules';
		$table_rule_objects = $wpdb->prefix . 'snippen_discount_rule_booking_objects';
		$table_objects      = $wpdb->prefix . 'snippen_booking_objects';

		$query = "SELECT r.*, 
		          GROUP_CONCAT(DISTINCT o.name SEPARATOR ', ') as object_names
                  FROM $table_rules r 
                  LEFT JOIN $table_rule_objects ro ON r.id = ro.discount_rule_id
                  LEFT JOIN $table_objects o ON ro.booking_object_id = o.id
                  WHERE r.deleted_at IS NULL 
                  GROUP BY r.id 
				  ORDER BY r.priority DESC, r.name ASC";

		$rules = $wpdb->get_results( $query );

		echo '<div class="snippen-card">';
		echo '<div class="snippen-table-responsive">';
		echo '<table class="snippen-list-table snippen-filterable-table">';
		echo '<thead><tr>';
		echo '<th data-filter-type="text" data-sort-type="string">' . esc_html__( 'Navn', 'snippen-booking' ) . '</th>';
		echo '<th data-filter-type="text" data-sort-type="string">' . esc_html__( 'Lokaler', 'snippen-booking' ) . '</th>';
		echo '<th data-filter-type="text" data-sort-type="string">' . esc_html__( 'Varighet', 'snippen-booking' ) . '</th>';
		echo '<th data-filter-type="text" data-sort-type="string">' . esc_html__( 'Rabatt', 'snippen-booking' ) . '</th>';
		echo '<th data-filter-type="minmax" data-sort-type="number">' . esc_html__( 'Prioritet', 'snippen-booking' ) . '</th>';
		echo '<th data-filter-type="select" data-sort-type="string">' . esc_html__( 'Status', 'snippen-booking' ) . '</th>';
		echo '<th style="text-align:right;">' . esc_html__( 'Handlinger', 'snippen-booking' ) . '</th>';
		echo '</tr></thead>';
		echo '<tbody>';

		if ( empty( $rules ) ) {
			echo '<tr><td colspan="7">' . esc_html__( 'Ingen rabattregler funnet.', 'snippen-booking' ) . '</td></tr>';
		} else {
			foreach ( $rules as $rule ) {
				$edit_url   = admin_url( 'admin.php?page=snippen-booking-discounts&action=edit&id=' . $rule->id );
				$delete_url = wp_nonce_url( admin_url( 'admin.php?page=snippen-booking-discounts&action=delete&id=' . $rule->id ), 'delete_discount_' . $rule->id );

				$duration = '';
				if ( $rule->min_duration_hours && $rule->max_duration_hours ) {
					$duration = $rule->min_duration_hours . ' - ' . $rule->max_duration_hours . ' timer';
				} elseif ( $rule->min_duration_hours ) {
					$duration = '>= ' . $rule->min_duration_hours . ' timer';
				} elseif ( $rule->max_duration_hours ) {
					$duration = '<= ' . $rule->max_duration_hours . ' timer';
				} else {
					$duration = 'Alle';
				}

				if ( $rule->discount_type === 'percentage' ) {
					$discount = $rule->discount_value . ' %';
				} elseif ( $rule->discount_type === 'fixed_price' ) {
					$discount = sprintf( __( 'Fast pris: %s kr', 'snippen-booking' ), number_format( $rule->discount_value, 0, ',', ' ' ) );
				} else {
					$discount = number_format( $rule->discount_value, 0, ',', ' ' ) . ' kr';
				}

				$is_active = ! isset( $rule->is_active ) || (int) $rule->is_active === 1;

				echo '<tr>';
				echo '<td><strong><a href="' . esc_url( $edit_url ) . '">' . esc_html( $rule->name ) . '</a></strong></td>';
				echo '<td>' . esc_html( $rule->object_names ?: '-' ) . '</td>';
				echo '<td>' . esc_html( $duration ) . '</td>';
				echo '<td>' . esc_html( $discount ) . '</td>';
				echo '<td>' . esc_html( $rule->priority ) . '</td>';
				echo '<td><label class="snippen-switch"><input type="checkbox" class="snippen-toggle-status" data-entity-type="discount_rule" data-id="' . intval( $rule->id ) . '" ' . checked( $is_active, true, false ) . '><span class="snippen-slider"></span></label></td>';
				echo '<td style="text-align:right;">';
				echo '<a href="' . esc_url( $edit_url ) . '" class="snippen-btn snippen-btn-outline" style="margin-right:5px;" title="' . esc_attr__( 'Rediger', 'snippen-booking' ) . '"><span class="dashicons dashicons-edit"></span></a>';
				echo '<a href="' . esc_url( $delete_url ) . '" class="snippen-btn snippen-btn-outline snippen-btn-danger snippen-delete-confirm" title="' . esc_attr__( 'Slett', 'snippen-booking' ) . '"><span class="dashicons dashicons-trash"></span></a>';
				echo '</td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table></div></div>';
	}

	private function render_form( $id = 0 ) {
		global $wpdb;
		$repo = new DiscountRuleRepository();
		$rule = $id > 0 ? $repo->find( $id ) : null;

		echo '<div class="snippen-card"><form method="post" action="">';
		wp_nonce_field( 'snippen_save_discount', 'snippen_discount_nonce' );
		echo '<input type="hidden" name="id" value="' . esc_attr( $id ) . '">';

		echo '<div class="snippen-form-group" style="display:flex; justify-content:space-between; align-items:center;">';
		echo '<div><label for="name">' . esc_html__( 'Navn på rabattregel', 'snippen-booking' ) . '</label>';
		echo '<input type="text" name="name" id="name" value="' . esc_attr( $rule ? $rule->name : '' ) . '" required class="regular-text"></div>';

		echo '<div><label style="font-weight:bold; display:flex; align-items:center; gap:8px;">';
		$is_active = ! $rule || ! isset( $rule->is_active ) || (int) $rule->is_active === 1;
		echo '<input type="checkbox" name="is_active" value="1" ' . checked( $is_active, true, false ) . '>';
		echo esc_html__( 'Regelen er aktiv', 'snippen-booking' );
		echo '</label></div>';
		echo '</div>';

		echo '<div class="snippen-form-group">';
		echo '<label for="description">' . esc_html__( 'Beskrivelse (valgfritt)', 'snippen-booking' ) . '</label>';
		echo '<textarea name="description" id="description" rows="2" class="large-text">' . esc_textarea( $rule ? $rule->description : '' ) . '</textarea>';
		echo '</div>';

		echo '<div class="snippen-form-group" style="display:flex; gap:20px;">';
		echo '<div><label for="discount_type">' . esc_html__( 'Rabattype', 'snippen-booking' ) . '</label>';
		echo '<select name="discount_type" id="discount_type" required>';
		echo '<option value="percentage" ' . selected( $rule ? $rule->discount_type : '', 'percentage', false ) . '>' . esc_html__( 'Prosent (%)', 'snippen-booking' ) . '</option>';
		echo '<option value="fixed_amount" ' . selected( $rule ? $rule->discount_type : '', 'fixed_amount', false ) . '>' . esc_html__( 'Fast sum (kr)', 'snippen-booking' ) . '</option>';
		echo '<option value="fixed_price" ' . selected( $rule ? $rule->discount_type : '', 'fixed_price', false ) . '>' . esc_html__( 'Fast pris (kr)', 'snippen-booking' ) . '</option>';
		echo '</select></div>';
		echo '<div><label for="discount_value">' . esc_html__( 'Verdi', 'snippen-booking' ) . '</label>';
		echo '<input type="number" step="0.01" name="discount_value" id="discount_value" value="' . esc_attr( $rule ? $rule->discount_value : 0 ) . '" required style="max-width:150px;"></div>';
		echo '</div>';

		echo '<div class="snippen-form-group" style="display:flex; gap:20px;">';
		echo '<div><label for="min_duration_hours">' . esc_html__( 'Min. varighet (timer)', 'snippen-booking' ) . '</label>';
		echo '<input type="number" step="0.5" name="min_duration_hours" id="min_duration_hours" value="' . esc_attr( $rule ? $rule->min_duration_hours : '' ) . '" style="max-width:150px;"></div>';
		echo '<div><label for="max_duration_hours">' . esc_html__( 'Maks. varighet (timer)', 'snippen-booking' ) . '</label>';
		echo '<input type="number" step="0.5" name="max_duration_hours" id="max_duration_hours" value="' . esc_attr( $rule ? $rule->max_duration_hours : '' ) . '" style="max-width:150px;"></div>';
		echo '<div><label for="priority">' . esc_html__( 'Prioritet', 'snippen-booking' ) . '</label>';
		echo '<input type="number" name="priority" id="priority" value="' . esc_attr( $rule ? $rule->priority : 10 ) . '" style="max-width:100px;"></div>';
		echo '</div>';

		echo '<div class="snippen-form-group">';
		echo '<label>' . esc_html__( 'Gjelder for lokaler (Alle valgte må være inkludert i bookingen):', 'snippen-booking' ) . '</label>';
		echo '<div style="display:flex; flex-direction:column; gap:5px; margin-top:5px;">';
		$objects          = $wpdb->get_results( "SELECT id, name FROM {$wpdb->prefix}snippen_booking_objects WHERE deleted_at IS NULL" );
		$selected_objects = $id > 0 ? $repo->get_rule_objects( $id ) : array();
		foreach ( $objects as $obj ) {
			echo '<label style="font-weight:normal;">';
			echo '<input type="checkbox" name="booking_objects[]" value="' . esc_attr( $obj->id ) . '" ' . checked( in_array( $obj->id, $selected_objects ), true, false ) . '> ';
			echo esc_html( $obj->name );
			echo '</label>';
		}
		echo '</div></div>';

		echo '<hr><h3 style="margin-top:25px;">' . esc_html__( 'Dato- og dagbegrensninger (Valgfritt)', 'snippen-booking' ) . '</h3>';

		echo '<div class="snippen-form-group">';
		echo '<label>' . esc_html__( 'Gjelder kun disse ukedagene:', 'snippen-booking' ) . '</label>';
		echo '<div style="display:flex; flex-wrap:wrap; gap:15px; margin-top:5px;">';
		$days          = array(
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
		echo '<button type="submit" class="snippen-btn snippen-btn-primary">' . esc_html__( 'Lagre rabattregel', 'snippen-booking' ) . '</button>';
		echo '</div>';

		echo '</form></div>';
	}
}
