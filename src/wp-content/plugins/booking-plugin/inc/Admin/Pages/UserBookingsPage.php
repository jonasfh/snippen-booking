<?php

namespace SnippenBooking\Admin\Pages;

/**
 * Admin page for users to manage their own Bookings
 */
class UserBookingsPage {

	/**
	 * Render the page
	 */
	public function render() {
		global $wpdb;

		$user_id       = get_current_user_id();
		$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
		$orderby       = isset( $_GET['orderby'] ) ? sanitize_sql_orderby( $_GET['orderby'] ) : 'booking_date';
		$order         = isset( $_GET['order'] ) ? ( strtoupper( $_GET['order'] ) === 'DESC' ? 'DESC' : 'ASC' ) : 'ASC';

		echo '<div class="snippen-booking-admin-wrap">';

		$this->render_header();
		$this->render_filters( $status_filter );
		$this->render_list( $user_id, $status_filter, $orderby, $order );

		echo '</div>';
	}

	/**
	 * Render header
	 */
	private function render_header() {
		echo '<div class="snippen-admin-header">';
		echo '<h1>' . esc_html__( 'Mine Bookinger', 'snippen-booking' ) . '</h1>';
		echo '</div>';
	}

	/**
	 * Render filters
	 */
	private function render_filters( $status ) {
		echo '<div class="snippen-card" style="padding: 15px 24px; margin-bottom: 20px;">';
		echo '<form method="get" action="" style="display:flex; align-items:center; gap:15px; flex-wrap:wrap;">';
		echo '<input type="hidden" name="page" value="snippen-my-bookings">';

		echo '<div class="snippen-filter-group">';
		echo '<select name="status" onchange="this.form.submit()">';
		echo '<option value="">' . esc_html__( 'Alle statuser', 'snippen-booking' ) . '</option>';
		echo '<option value="pending" ' . selected( $status, 'pending', false ) . '>' . esc_html__( 'Venter', 'snippen-booking' ) . '</option>';
		echo '<option value="confirmed" ' . selected( $status, 'confirmed', false ) . '>' . esc_html__( 'Bekreftet', 'snippen-booking' ) . '</option>';
		echo '<option value="cancelled" ' . selected( $status, 'cancelled', false ) . '>' . esc_html__( 'Avbrutt', 'snippen-booking' ) . '</option>';
		echo '</select></div>';

		echo '</form></div>';
	}

	/**
	 * Render bookings list
	 */
	private function render_list( $user_id, $status, $orderby, $order ) {
		global $wpdb;
		$table_bookings = $wpdb->prefix . 'snippen_bookings';
		$table_slots    = $wpdb->prefix . 'snippen_time_slots';

		$query = $wpdb->prepare(
			"
            SELECT b.*, s.name as slot_name, s.start_time, s.end_time 
            FROM $table_bookings b 
            LEFT JOIN $table_slots s ON b.slot_id = s.id 
            WHERE b.user_id = %d AND b.deleted_at IS NULL",
			$user_id
		);

		if ( $status ) {
			$query .= $wpdb->prepare( ' AND b.status = %s', $status );
		}

		// Validate orderby
		$allowed_orderby = array( 'booking_date', 'price', 'status', 'created_at' );
		if ( ! in_array( $orderby, $allowed_orderby ) ) {
			$orderby = 'booking_date';
		}

		$query   .= " ORDER BY $orderby $order";
		$bookings = $wpdb->get_results( $query );

		echo '<div class="snippen-card" style="padding:0; overflow:hidden;">';
		echo '<table class="snippen-list-table bookings-table">';
		echo '<thead><tr>';
		echo '<th style="width:40px;"></th>';
		echo $this->render_sortable_header( 'booking_date', __( 'Dato / Tid', 'snippen-booking' ), $orderby, $order );
		echo '<th>' . esc_html__( 'Lokaler', 'snippen-booking' ) . '</th>';
		echo $this->render_sortable_header( 'price', __( 'Pris', 'snippen-booking' ), $orderby, $order );
		echo $this->render_sortable_header( 'status', __( 'Status', 'snippen-booking' ), $orderby, $order );
		echo '<th style="text-align:right;">' . esc_html__( 'Handlinger', 'snippen-booking' ) . '</th>';
		echo '</tr></thead>';
		echo '<tbody>';

		if ( empty( $bookings ) ) {
			echo '<tr><td colspan="6" style="padding:40px; text-align:center;">' . esc_html__( 'Du har ingen bookinger.', 'snippen-booking' ) . '</td></tr>';
		} else {
			foreach ( $bookings as $booking ) {
				$this->render_booking_row( $booking );
			}
		}

		echo '</tbody></table></div>';
	}

	/**
	 * Render sortable header
	 */
	private function render_sortable_header( $field, $label, $current_orderby, $current_order ) {
		$next_order = ( $field === $current_orderby && $current_order === 'ASC' ) ? 'desc' : 'asc';
		$url        = add_query_arg(
			array(
				'orderby' => $field,
				'order'   => $next_order,
			)
		);

		$icon = '';
		if ( $field === $current_orderby ) {
			$icon = $current_order === 'ASC' ? ' <span class="dashicons dashicons-arrow-up-alt2" style="font-size:16px;"></span>' : ' <span class="dashicons dashicons-arrow-down-alt2" style="font-size:16px;"></span>';
		}

		return '<th><a href="' . esc_url( $url ) . '" style="text-decoration:none; color:inherit; display:flex; align-items:center;">' . esc_html( $label ) . $icon . '</a></th>';
	}

	/**
	 * Render a single booking row
	 */
	private function render_booking_row( $booking ) {
		global $wpdb;
		$table_junction = $wpdb->prefix . 'snippen_bookings_booking_objects';
		$table_objects  = $wpdb->prefix . 'snippen_booking_objects';

		$objs = $wpdb->get_col(
			$wpdb->prepare(
				"
            SELECT o.name 
            FROM $table_junction bo 
            JOIN $table_objects o ON bo.booking_object_id = o.id 
            WHERE bo.booking_id = %d",
				$booking->id
			)
		);

		$status_class = 'snippen-status-' . $booking->status;
		$booking_date = date_i18n( get_option( 'date_format' ), strtotime( $booking->booking_date ) );

		echo '<tr class="snippen-booking-row" id="booking-' . $booking->id . '">';
		echo '<td><button class="snippen-btn-action toggle-details" title="' . esc_attr__( 'Vis detaljer', 'snippen-booking' ) . '"><span class="dashicons dashicons-arrow-down-alt2"></span></button></td>';
		echo '<td><strong>' . esc_html( $booking_date ) . '</strong><br><small>' . esc_html( $booking->slot_name ) . '</small></td>';
		echo '<td>';
		foreach ( $objs as $oname ) {
			echo '<span class="snippen-tag">' . esc_html( $oname ) . '</span> ';
		}
		echo '</td>';
		echo '<td style="font-weight:600;">' . number_format( $booking->price, 0, ',', ' ' ) . ',-</td>';
		echo '<td><span class="snippen-badge ' . esc_attr( $status_class ) . '">' . esc_html( $this->get_status_label( $booking->status ) ) . '</span></td>';
		echo '<td style="text-align:right;">';
		echo '<div style="display:flex; justify-content:flex-end; gap:8px;">';

		if ( $booking->status !== 'cancelled' ) {
			echo '<button class="snippen-btn-action cancel" data-id="' . $booking->id . '" title="' . esc_attr__( 'Avbryt', 'snippen-booking' ) . '"><span class="dashicons dashicons-no"></span></button>';
		}

		echo '</div></td></tr>';

		// Details Row
		\SnippenBooking\Service\DoorCodeService::sync_booking_door_code( $booking );

		$door_code_display = '';
		if ( \SnippenBooking\Service\DoorCodeService::is_in_window( $booking ) ) {
			$door_code_display = ! empty( $booking->door_code ) ? esc_html( $booking->door_code ) : esc_html__( 'Ikke satt', 'snippen-booking' );
		} else {
			$door_code_display = '<span style="color:#64748b; font-style:italic;">' . esc_html__( '<Koden er ikke tilgjengelig før nærmere booking start>', 'snippen-booking' ) . '</span>';
		}

		echo '<tr class="snippen-details-row" id="details-' . $booking->id . '" style="display:none; background:#f8fafc;">';
		echo '<td colspan="6" style="padding:20px 30px; border-bottom: 2px solid var(--border-color);">';
		echo '<div class="details-content" style="display:grid; grid-template-columns: repeat(4, 1fr); gap:30px;">';
		echo '<div><strong>' . esc_html__( 'Lokale(r):', 'snippen-booking' ) . '</strong><br>' . esc_html( implode( ', ', $objs ) ) . '</div>';
		echo '<div><strong>' . esc_html__( 'Beskrivelse:', 'snippen-booking' ) . '</strong><br>' . esc_html( $booking->description ?: '-' ) . '</div>';
		echo '<div><strong>' . esc_html__( 'Booket den:', 'snippen-booking' ) . '</strong><br>' . esc_html( $booking->created_at ) . '</div>';
		echo '<div><strong>' . esc_html__( 'Dørkode:', 'snippen-booking' ) . '</strong><br>' . $door_code_display . '</div>';
		echo '</div></td></tr>';
	}

	/**
	 * Get status label
	 */
	private function get_status_label( $status ) {
		$labels = array(
			'pending'   => __( 'Venter', 'snippen-booking' ),
			'confirmed' => __( 'Bekreftet', 'snippen-booking' ),
			'cancelled' => __( 'Avbrutt', 'snippen-booking' ),
		);
		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}
}
