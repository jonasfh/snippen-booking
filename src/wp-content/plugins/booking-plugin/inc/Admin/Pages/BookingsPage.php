<?php

namespace SnippenBooking\Admin\Pages;

/**
 * Admin page for managing Bookings
 */
class BookingsPage {

	/**
	 * Render the page
	 */
	public function render() {
		global $wpdb;

		$status_filter         = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
		$payment_status_filter = isset( $_GET['payment_status'] ) ? sanitize_text_field( $_GET['payment_status'] ) : '';
		$object_filter         = isset( $_GET['object_id'] ) ? intval( $_GET['object_id'] ) : 0;
		$search                = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
		$orderby               = isset( $_GET['orderby'] ) ? sanitize_sql_orderby( $_GET['orderby'] ) : 'booking_date';
		$order                 = isset( $_GET['order'] ) ? ( strtoupper( $_GET['order'] ) === 'DESC' ? 'DESC' : 'ASC' ) : 'ASC';
		$show_all              = isset( $_GET['show_all'] ) && $_GET['show_all'] === '1';
		$door_code_filter      = isset( $_GET['door_code_filter'] ) ? sanitize_text_field( $_GET['door_code_filter'] ) : '';

		echo '<div class="snippen-booking-admin-wrap">';

		$this->render_header();
		$this->render_tagged_pages();
		$this->render_filters( $status_filter, $payment_status_filter, $object_filter, $search, $show_all, $door_code_filter );
		$this->render_list( $status_filter, $payment_status_filter, $object_filter, $search, $orderby, $order, $show_all, $door_code_filter );

		echo '</div>';
	}

	/**
	 * Render header
	 */
	private function render_header() {
		echo '<div class="snippen-admin-header">';
		echo '<h1>' . esc_html__( 'Booking Oversikt', 'snippen-booking' ) . '</h1>';
		echo '</div>';
	}

	/**
	 * Render tagged pages at the top
	 */
	private function render_tagged_pages() {
		$pages = get_posts(
			array(
				'post_type'      => 'page',
				'posts_per_page' => -1,
				'tax_query'      => array(
					array(
						'taxonomy' => 'post_tag',
						'field'    => 'slug',
						'terms'    => 'snippen-booking',
					),
				),
			)
		);

		if ( empty( $pages ) ) {
			return;
		}

		echo '<div class="snippen-card" style="padding: 15px 24px; margin-bottom: 20px; border-left: 4px solid #0073aa; display: flex; align-items: center; gap: 10px;">';
		echo '<span class="dashicons dashicons-admin-links" style="color: #0073aa; font-size: 20px; width: 20px; height: 20px; line-height: 20px;"></span>';
		echo '<span style="font-weight: 600; color: #1d2327;">' . esc_html__( 'Hurtiglenker til bookingsider:', 'snippen-booking' ) . '</span> ';

		$first = true;
		foreach ( $pages as $p ) {
			if ( ! $first ) {
				echo ' <span style="color: #c3c4c7;">|</span> ';
			}
			$first = false;
			echo '<a href="' . esc_url( get_permalink( $p->ID ) ) . '" target="_blank" style="font-weight: 500; text-decoration: none; color: #0073aa;">' . esc_html( $p->post_title ) . '</a>';
		}
		echo '</div>';
	}

	/**
	 * Render filters
	 */
	private function render_filters( $status = '', $payment_status = '', $obj_id = 0, $s = '', $show_all = false, $door_code_filter = '' ) {
		global $wpdb;
		$table_objects = $wpdb->prefix . 'snippen_booking_objects';
		$objects       = $wpdb->get_results( "SELECT id, name FROM $table_objects WHERE deleted_at IS NULL ORDER BY name ASC" );

		echo '<div class="snippen-card" style="padding: 15px 24px; margin-bottom: 20px;">';
		echo '<form method="get" action="" style="display:flex; align-items:center; gap:15px; flex-wrap:wrap;">';
		echo '<input type="hidden" name="page" value="snippen-booking">';

		echo '<div class="snippen-filter-group">';
		echo '<select name="status" onchange="this.form.submit()">';
		echo '<option value="">' . esc_html__( 'Alle statuser', 'snippen-booking' ) . '</option>';
		echo '<option value="pending" ' . selected( $status, 'pending', false ) . '>' . esc_html__( 'Venter på godkjenning', 'snippen-booking' ) . '</option>';
		echo '<option value="confirmed" ' . selected( $status, 'confirmed', false ) . '>' . esc_html__( 'Bekreftet', 'snippen-booking' ) . '</option>';
		echo '<option value="cancelled" ' . selected( $status, 'cancelled', false ) . '>' . esc_html__( 'Avbrutt', 'snippen-booking' ) . '</option>';
		echo '</select></div>';

		echo '<div class="snippen-filter-group">';
		echo '<select name="payment_status" onchange="this.form.submit()">';
		echo '<option value="">' . esc_html__( 'Alle betalingsstatuser', 'snippen-booking' ) . '</option>';
		echo '<option value="unpaid" ' . selected( $payment_status, 'unpaid', false ) . '>' . esc_html__( 'Mangler betaling', 'snippen-booking' ) . '</option>';
		echo '<option value="pending" ' . selected( $payment_status, 'pending', false ) . '>' . esc_html__( 'Venter bekreftelse', 'snippen-booking' ) . '</option>';
		echo '<option value="paid" ' . selected( $payment_status, 'paid', false ) . '>' . esc_html__( 'Betalt', 'snippen-booking' ) . '</option>';
		echo '<option value="exempt" ' . selected( $payment_status, 'exempt', false ) . '>' . esc_html__( 'Fritatt / Gratis', 'snippen-booking' ) . '</option>';
		echo '<option value="unsettled" ' . selected( $payment_status, 'unsettled', false ) . '>' . esc_html__( 'Utestående betalinger', 'snippen-booking' ) . '</option>';
		echo '<option value="settled" ' . selected( $payment_status, 'settled', false ) . '>' . esc_html__( 'Oppgjorte betalinger', 'snippen-booking' ) . '</option>';
		echo '</select></div>';

		echo '<div class="snippen-filter-group">';
		echo '<select name="object_id" onchange="this.form.submit()">';
		echo '<option value="0">' . esc_html__( 'Alle lokaler', 'snippen-booking' ) . '</option>';
		foreach ( $objects as $obj ) {
			echo '<option value="' . esc_attr( $obj->id ) . '" ' . selected( $obj_id, $obj->id, false ) . '>' . esc_html( $obj->name ) . '</option>';
		}
		echo '</select></div>';

		echo '<div class="snippen-filter-group" style="flex-grow:1;">';
		echo '<input type="text" name="s" value="' . esc_attr( $s ) . '" placeholder="' . esc_attr__( 'Søk i navn/e-post...', 'snippen-booking' ) . '" style="width:100%; max-width:300px;">';
		echo ' <button type="submit" class="button">' . esc_html__( 'Søk', 'snippen-booking' ) . '</button>';
		echo '</div>';

		echo '<div class="snippen-filter-group">';
		echo '<label><input type="checkbox" name="show_all" value="1" ' . checked( $show_all, true, false ) . ' onchange="this.form.submit()"> ' . esc_html__( 'Vis historikk / eldre bookinger', 'snippen-booking' ) . '</label>';
		echo '</div>';

		echo '<div class="snippen-filter-group">';
		echo '<select name="door_code_filter" onchange="this.form.submit()">';
		echo '<option value="">' . esc_html__( 'Alle dørkoder', 'snippen-booking' ) . '</option>';
		echo '<option value="missing" ' . selected( $door_code_filter, 'missing', false ) . '>' . esc_html__( 'Mangler dørkode', 'snippen-booking' ) . '</option>';
		echo '</select></div>';

		echo '</form></div>';
	}

	/**
	 * Render bookings list
	 */
	private function render_list( $status = '', $payment_status = '', $obj_id = 0, $s = '', $orderby = 'booking_date', $order = 'ASC', $show_all = false, $door_code_filter = '' ) {
		global $wpdb;
		$table_bookings         = $wpdb->prefix . 'snippen_bookings';
		$table_slots            = $wpdb->prefix . 'snippen_time_slots';
		$table_junction         = $wpdb->prefix . 'snippen_bookings_booking_objects';
		$table_payment_statuses = $wpdb->prefix . 'snippen_payment_statuses';

		$query = "SELECT b.*, s.name as slot_name, s.start_time, s.end_time, ps.slug as payment_slug, ps.name as payment_name, ps.is_settled as payment_is_settled
                  FROM $table_bookings b 
                  LEFT JOIN $table_slots s ON b.slot_id = s.id 
                  LEFT JOIN $table_payment_statuses ps ON b.payment_status_id = ps.id
                  WHERE b.deleted_at IS NULL";

		if ( $status ) {
			$query .= $wpdb->prepare( ' AND b.status = %s', $status );
		} else {
			$query .= " AND b.status != 'cancelled'";
		}

		if ( $payment_status ) {
			switch ( $payment_status ) {
				case 'unpaid':
					$query .= " AND (ps.slug = 'UNPAID' OR b.payment_status_id IS NULL OR b.payment_status_id = 1)";
					break;
				case 'pending':
					$query .= " AND ps.slug = 'PENDING_VERIFICATION'";
					break;
				case 'paid':
					$query .= " AND ps.slug = 'PAID'";
					break;
				case 'exempt':
					$query .= " AND ps.slug = 'EXEMPT'";
					break;
				case 'unsettled':
					$query .= " AND (ps.is_settled = 0 OR ps.is_settled IS NULL)";
					break;
				case 'settled':
					$query .= " AND ps.is_settled = 1";
					break;
			}
		}

		if ( $obj_id > 0 ) {
			$query .= $wpdb->prepare( " AND b.id IN (SELECT booking_id FROM $table_junction WHERE booking_object_id = %d)", $obj_id );
		}

		if ( $s ) {
			$like_search = '%' . $wpdb->esc_like( $s ) . '%';
			$query      .= $wpdb->prepare( ' AND (b.customer_name LIKE %s OR b.customer_email LIKE %s)', $like_search, $like_search );
		}

		if ( ! $show_all && ! $s ) {
			$query .= ' AND b.booking_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)';
		}

		if ( $door_code_filter === 'missing' ) {
			$query .= " AND (b.door_code IS NULL OR b.door_code = '')";
		}

		$allowed_orderby = array( 'booking_date', 'customer_name', 'price', 'status', 'created_at' );
		if ( ! in_array( $orderby, $allowed_orderby ) ) {
			$orderby = 'booking_date';
		}

		$query   .= " ORDER BY $orderby $order";
		$bookings = $wpdb->get_results( $query );

		echo '<div class="snippen-card" style="padding:0; overflow:hidden;">';
		echo '<table class="snippen-list-table bookings-table">';
		echo '<thead><tr>';
		echo '<th style="text-align:left; width:100px;">' . esc_html__( 'Handlinger', 'snippen-booking' ) . '</th>';
		echo $this->render_sortable_header( 'booking_date', __( 'Dato / Tid', 'snippen-booking' ), $orderby, $order );
		echo $this->render_sortable_header( 'customer_name', __( 'Kunde', 'snippen-booking' ), $orderby, $order );
		echo '<th>' . esc_html__( 'Lokaler', 'snippen-booking' ) . '</th>';
		echo $this->render_sortable_header( 'price', __( 'Pris', 'snippen-booking' ), $orderby, $order );
		echo $this->render_sortable_header( 'status', __( 'Status', 'snippen-booking' ), $orderby, $order );
		echo '<th>' . esc_html__( 'Betaling', 'snippen-booking' ) . '</th>';
		echo '<th style="width:40px; text-align:right;"></th>';
		echo '</tr></thead>';
		echo '<tbody>';

		if ( empty( $bookings ) ) {
			echo '<tr><td colspan="8" style="padding:40px; text-align:center;">' . esc_html__( 'Ingen bookinger funnet.', 'snippen-booking' ) . '</td></tr>';
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
		$time_range   = '';
		if ( ! empty( $booking->start_time ) && ! empty( $booking->end_time ) ) {
			$time_range = date_i18n( 'H:i', strtotime( $booking->start_time ) ) . ' - ' . date_i18n( 'H:i', strtotime( $booking->end_time ) );
		}

		$payment_status = \SnippenBooking\Service\PaymentService::get_booking_payment_status( $booking );
		$all_statuses   = \SnippenBooking\Service\PaymentService::get_statuses();

		echo '<tr class="snippen-booking-row" id="booking-' . esc_attr( $booking->id ) . '">';
		echo '<td data-label="' . esc_attr__( 'Handlinger', 'snippen-booking' ) . '">';
		echo '<div style="display:flex; justify-content:flex-start; gap:8px;">';
		if ( $booking->status === 'pending' ) {
			echo '<button class="snippen-btn-action approve" data-id="' . esc_attr( $booking->id ) . '" title="' . esc_attr__( 'Godkjenn', 'snippen-booking' ) . '"><span class="dashicons dashicons-yes"></span></button>';
		}
		if ( $booking->status !== 'cancelled' ) {
			echo '<button class="snippen-btn-action cancel" data-id="' . esc_attr( $booking->id ) . '" title="' . esc_attr__( 'Avbryt', 'snippen-booking' ) . '"><span class="dashicons dashicons-no"></span></button>';
		}
		echo '</div></td>';
		echo '<td data-label="' . esc_attr__( 'Dato / Tid', 'snippen-booking' ) . '"><strong>' . esc_html( $booking_date ) . '</strong><br><small>' . esc_html( $booking->slot_name ) . '</small></td>';
		echo '<td data-label="' . esc_attr__( 'Kunde', 'snippen-booking' ) . '"><strong>' . esc_html( $booking->customer_name ) . '</strong><br><small>' . esc_html( $booking->customer_email ) . '</small></td>';
		echo '<td data-label="' . esc_attr__( 'Lokaler', 'snippen-booking' ) . '">';
		foreach ( $objs as $oname ) {
			echo '<span class="snippen-tag">' . esc_html( $oname ) . '</span> ';
		}
		echo '</td>';
		echo '<td data-label="' . esc_attr__( 'Pris', 'snippen-booking' ) . '" style="font-weight:600;">' . number_format( $booking->price, 0, ',', ' ' ) . ',-</td>';
		echo '<td data-label="' . esc_attr__( 'Status', 'snippen-booking' ) . '"><span class="snippen-badge ' . esc_attr( $status_class ) . '">' . esc_html( $this->get_status_label( $booking->status ) ) . '</span></td>';
		
		echo '<td data-label="' . esc_attr__( 'Betaling', 'snippen-booking' ) . '">';
		echo '<span class="snippen-badge" style="background:' . ( $payment_status->is_settled ? '#dcfce7; color:#15803d' : '#fef3c7; color:#b45309' ) . ';">' . esc_html( $payment_status->name ) . '</span>';
		echo '</td>';

		echo '<td data-label="' . esc_attr__( 'Detaljer', 'snippen-booking' ) . '" style="text-align:right;"><button class="snippen-btn-action toggle-details" title="' . esc_attr__( 'Vis detaljer', 'snippen-booking' ) . '"><span class="dashicons dashicons-arrow-down-alt2"></span></button></td>';
		echo '</tr>';

		// Details Row (Hidden)
		echo '<tr class="snippen-details-row" id="details-' . esc_attr( $booking->id ) . '" style="display:none; background:#f8fafc;">';
		echo '<td colspan="8" style="padding:20px 30px; border-bottom: 2px solid var(--border-color);">';
		echo '<div class="details-content" style="display:grid; grid-template-columns: repeat(5, 1fr); gap:30px;">';
		echo '<div><strong>' . esc_html__( 'Kontaktinfo:', 'snippen-booking' ) . '</strong><br>' . esc_html( $booking->customer_phone ?: '-' ) . '</div>';
		echo '<div><strong>' . esc_html__( 'Lokale(r):', 'snippen-booking' ) . '</strong><br>' . esc_html( implode( ', ', $objs ) ) . '</div>';
		echo '<div><strong>' . esc_html__( 'Beskrivelse/Notater:', 'snippen-booking' ) . '</strong><br>' . esc_html( $booking->description ?: '-' ) . '</div>';
		echo '<div><strong>' . esc_html__( 'Tidsrom:', 'snippen-booking' ) . '</strong><br>' . esc_html( $time_range ?: '-' ) . '</div>';
		echo '<div><strong>' . esc_html__( 'Dørkode:', 'snippen-booking' ) . '</strong><br>';
		echo '<div class="door-code-edit-container" data-id="' . esc_attr( $booking->id ) . '" style="display: flex; align-items: center; margin-top: 4px;">';
		echo '<input type="text" class="door-code-input" value="' . esc_attr( $booking->door_code ) . '" placeholder="' . esc_attr__( 'Ingen kode', 'snippen-booking' ) . '" style="width: 100px; margin-right: 5px; height: 30px;">';
		echo '<button type="button" class="button button-small snippen-btn-save-door-code" style="height: 30px; line-height: 1;">' . esc_html__( 'Lagre', 'snippen-booking' ) . '</button>';
		echo '<span class="door-code-feedback" style="margin-left: 5px; font-size: 11px; font-weight: 600;"></span>';
		echo '</div></div>';
		echo '<div><strong>' . esc_html__( 'Rabatt:', 'snippen-booking' ) . '</strong><br>' . ( $booking->discount_amount > 0 ? esc_html( number_format( $booking->discount_amount, 0, ',', ' ' ) . ',-' ) : '-' ) . '</div>';
		echo '<div><strong>' . esc_html__( 'Booket den:', 'snippen-booking' ) . '</strong><br>' . esc_html( $booking->created_at ) . '</div>';

		// Payment Management Section in details row
		echo '<div class="payment-admin-container" data-id="' . esc_attr( $booking->id ) . '" style="grid-column: span 2; background:#fff; padding:12px; border:1px solid #e2e8f0; border-radius:6px;">';
		echo '<strong>' . esc_html__( 'Betalingsadministrasjon:', 'snippen-booking' ) . '</strong><br>';
		echo '<div style="margin-top:6px; display:flex; flex-direction:column; gap:6px;">';
		echo '<select class="payment-status-select" style="width:100%; height:30px;">';
		foreach ( $all_statuses as $st ) {
			echo '<option value="' . esc_attr( $st->id ) . '" ' . selected( $payment_status->id, $st->id, false ) . '>' . esc_html( $st->name ) . '</option>';
		}
		echo '</select>';

		echo '<textarea class="payment-notes-input" placeholder="' . esc_attr__( 'Betalingsnotat (f.eks. transaksjons-ref)...', 'snippen-booking' ) . '" style="width:100%; height:45px; font-size:12px;">' . esc_textarea( $booking->payment_notes ?: '' ) . '</textarea>';

		if ( ! empty( $booking->payment_receipt_attachment_id ) ) {
			$receipt_url = wp_get_attachment_url( $booking->payment_receipt_attachment_id );
			if ( $receipt_url ) {
				echo '<div><a href="' . esc_url( $receipt_url ) . '" target="_blank" class="button button-small" style="text-decoration:none;"><span class="dashicons dashicons-paperclip" style="vertical-align:middle; font-size:14px; width:14px; height:14px; line-height:14px;"></span> ' . esc_html__( 'Vis kvittering', 'snippen-booking' ) . '</a></div>';
			}
		}

		echo '<div style="display:flex; align-items:center; gap:8px; margin-top:4px;">';
		echo '<button type="button" class="button button-small button-primary snippen-btn-save-payment">' . esc_html__( 'Lagre betalingsstatus', 'snippen-booking' ) . '</button>';
		echo '<span class="payment-feedback" style="font-size:11px; font-weight:600;"></span>';
		echo '</div>';
		echo '</div></div>';
		
		echo '<div class="snippen-mobile-detail" style="display:none;"><strong>' . esc_html__( 'Pris:', 'snippen-booking' ) . '</strong><br>' . number_format( $booking->price, 0, ',', ' ' ) . ',-</div>';
		echo '<div class="snippen-mobile-detail" style="display:none;"><strong>' . esc_html__( 'Status:', 'snippen-booking' ) . '</strong><br><span class="snippen-badge ' . esc_attr( $status_class ) . '">' . esc_html( $this->get_status_label( $booking->status ) ) . '</span></div>';

		echo '<div class="booking-assistant-actions" data-id="' . esc_attr( $booking->id ) . '" data-uuid="' . esc_attr( $booking->uuid ) . '">';
		echo '<strong>' . esc_html__( 'Booking-hjelper:', 'snippen-booking' ) . '</strong><br>';
		echo '<button class="button snippen-btn-dispatch" data-channel="email_customer" style="margin-top:6px; margin-bottom:6px; display:block; width:100%; text-align:left;"><span class="dashicons dashicons-email" style="vertical-align:middle; margin-right:4px; font-size:16px; width:16px; height:16px; line-height:16px;"></span> ' . esc_html__( 'E-post til kunde', 'snippen-booking' ) . '</button>';
		echo '<button class="button snippen-btn-dispatch" data-channel="sms_customer" style="margin-bottom:6px; display:block; width:100%; text-align:left;"><span class="dashicons dashicons-phone" style="vertical-align:middle; margin-right:4px; font-size:16px; width:16px; height:16px; line-height:16px;"></span> ' . esc_html__( 'SMS til kunde', 'snippen-booking' ) . '</button>';
		echo '<button class="button snippen-btn-dispatch" data-channel="email_admin" style="margin-bottom:6px; display:block; width:100%; text-align:left;"><span class="dashicons dashicons-email" style="vertical-align:middle; margin-right:4px; font-size:16px; width:16px; height:16px; line-height:16px;"></span> ' . esc_html__( 'Varsel til admin', 'snippen-booking' ) . '</button>';
		echo '<div class="assistant-feedback" style="margin-top:6px; font-size:11px; font-weight:600; min-height:15px;"></div>';
		echo '</div>';
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
