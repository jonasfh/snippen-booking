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
		$booking_type_filter   = isset( $_GET['booking_type'] ) ? sanitize_text_field( $_GET['booking_type'] ) : '';
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
		$this->render_filters( $status_filter, $payment_status_filter, $object_filter, $search, $show_all, $door_code_filter, $booking_type_filter );
		$this->render_list( $status_filter, $payment_status_filter, $object_filter, $search, $orderby, $order, $show_all, $door_code_filter, $booking_type_filter );
		$this->render_dispatch_modal();
		$this->render_edit_modal();

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

		echo '<div class="snippen-card snippen-quick-links">';
		echo '<div class="snippen-quick-links-header">';
		echo '<span class="dashicons dashicons-admin-links"></span>';
		echo '<span class="snippen-quick-links-title">' . esc_html__( 'Hurtiglenker til bookingsider:', 'snippen-booking' ) . '</span>';
		echo '</div>';
		echo '<div class="snippen-quick-links-list">';

		$first = true;
		foreach ( $pages as $p ) {
			if ( ! $first ) {
				echo '<span class="snippen-quick-links-separator">|</span>';
			}
			$first = false;
			echo '<a href="' . esc_url( get_permalink( $p->ID ) ) . '" target="_blank" class="snippen-quick-link">' . esc_html( $p->post_title ) . '</a>';
		}
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render filters
	 */
	private function render_filters( $status = '', $payment_status = '', $obj_id = 0, $s = '', $show_all = false, $door_code_filter = '', $booking_type = '' ) {
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
		echo '<option value="pending_payment" ' . selected( $status, 'pending_payment', false ) . '>' . esc_html__( 'Venter på betaling', 'snippen-booking' ) . '</option>';
		echo '<option value="confirmed" ' . selected( $status, 'confirmed', false ) . '>' . esc_html__( 'Bekreftet', 'snippen-booking' ) . '</option>';
		echo '<option value="cancelled" ' . selected( $status, 'cancelled', false ) . '>' . esc_html__( 'Avbrutt', 'snippen-booking' ) . '</option>';
		echo '</select></div>';

		echo '<div class="snippen-filter-group">';
		echo '<select name="booking_type" onchange="this.form.submit()">';
		echo '<option value="">' . esc_html__( 'Alle bookingtyper', 'snippen-booking' ) . '</option>';
		echo '<option value="private" ' . selected( $booking_type, 'private', false ) . '>' . esc_html__( 'Privat', 'snippen-booking' ) . '</option>';
		echo '<option value="open" ' . selected( $booking_type, 'open', false ) . '>' . esc_html__( 'Åpen for sameiet', 'snippen-booking' ) . '</option>';
		echo '<option value="cleaning" ' . selected( $booking_type, 'cleaning', false ) . '>' . esc_html__( 'Utvask', 'snippen-booking' ) . '</option>';
		echo '<option value="open_pending" ' . selected( $booking_type, 'open_pending', false ) . '>' . esc_html__( 'Åpne som venter på godkjenning', 'snippen-booking' ) . '</option>';
		echo '</select></div>';

		echo '<div class="snippen-filter-group">';
		echo '<select name="payment_status" onchange="this.form.submit()">';
		echo '<option value="">' . esc_html__( 'Alle betalingsstatuser', 'snippen-booking' ) . '</option>';
		echo '<option value="unpaid" ' . selected( $payment_status, 'unpaid', false ) . '>' . esc_html__( 'Mangler betaling', 'snippen-booking' ) . '</option>';
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

	private function render_list( $status = '', $payment_status = '', $obj_id = 0, $s = '', $orderby = 'booking_date', $order = 'ASC', $show_all = false, $door_code_filter = '', $booking_type = '' ) {
		global $wpdb;
		$table_bookings         = $wpdb->prefix . 'snippen_bookings';
		$table_slots            = $wpdb->prefix . 'snippen_time_slots';
		$table_junction         = $wpdb->prefix . 'snippen_bookings_booking_objects';
		$table_payment_statuses = $wpdb->prefix . 'snippen_payment_statuses';
		$table_booking_blocks   = $wpdb->prefix . 'snippen_booking_booking_blocks';
		$table_blocks           = $wpdb->prefix . 'snippen_booking_blocks';

		$query = "SELECT b.*, COALESCE(s.name, bb.name) as slot_name, 
                         COALESCE(MIN(bb.start_time), s.start_time) as start_time, 
                         COALESCE(MAX(bb.end_time), s.end_time) as end_time, 
                         ps.slug as payment_slug, ps.name as payment_name, ps.is_settled as payment_is_settled
                  FROM $table_bookings b 
                  LEFT JOIN $table_slots s ON b.slot_id = s.id 
                  LEFT JOIN $table_booking_blocks bbb ON b.id = bbb.booking_id
                  LEFT JOIN $table_blocks bb ON bbb.booking_block_id = bb.id
                  LEFT JOIN $table_payment_statuses ps ON b.payment_status_id = ps.id
                  WHERE b.deleted_at IS NULL";

		if ( $status ) {
			$query .= $wpdb->prepare( ' AND b.status = %s', $status );
		} else {
			$query .= " AND b.status != 'cancelled'";
		}

		if ( $booking_type ) {
			switch ( $booking_type ) {
				case 'private':
					$query .= " AND (b.booking_type = 'private' OR b.booking_type IS NULL OR b.booking_type = '')";
					break;
				case 'open':
					$query .= " AND b.booking_type = 'open'";
					break;
				case 'cleaning':
					$query .= " AND b.booking_type = 'cleaning'";
					break;
				case 'open_pending':
					$query .= " AND b.booking_type = 'open' AND b.status = 'pending'";
					break;
			}
		}

		if ( $payment_status ) {
			switch ( $payment_status ) {
				case 'unpaid':
					$query .= " AND (ps.slug = 'UNPAID' OR b.payment_status_id IS NULL OR b.payment_status_id = 1)";
					break;
				case 'paid':
					$query .= " AND ps.slug = 'PAID'";
					break;
				case 'exempt':
					$query .= " AND ps.slug = 'EXEMPT'";
					break;
				case 'unsettled':
					$query .= ' AND (ps.is_settled = 0 OR ps.is_settled IS NULL)';
					break;
				case 'settled':
					$query .= ' AND ps.is_settled = 1';
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

		$query .= ' GROUP BY b.id';

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

		if ( ! empty( $booking->booking_snapshot ) ) {
			$snapshot = json_decode( $booking->booking_snapshot, true );
			if ( ! empty( $snapshot['time_range_formatted'] ) ) {
				$time_range = $snapshot['time_range_formatted'];
			} elseif ( ! empty( $snapshot['start_time'] ) && ! empty( $snapshot['end_time'] ) ) {
				$time_range = date_i18n( 'H:i', strtotime( $snapshot['start_time'] ) ) . ' - ' . date_i18n( 'H:i', strtotime( $snapshot['end_time'] ) );
			}
		}

		if ( empty( $time_range ) && ! empty( $booking->start_time ) && ! empty( $booking->end_time ) ) {
			$time_range = date_i18n( 'H:i', strtotime( $booking->start_time ) ) . ' - ' . date_i18n( 'H:i', strtotime( $booking->end_time ) );
		}

		$payment_status = \SnippenBooking\Service\PaymentService::get_booking_payment_status( $booking );
		$all_statuses   = \SnippenBooking\Service\PaymentService::get_statuses();

		$custom_inst_tags = array();
		if ( ! empty( $booking->id ) ) {
			$block_repo   = new \SnippenBooking\Database\Repository\BookingBlockRepository();
			$booking_repo = new \SnippenBooking\Database\Repository\BookingRepository();
			$b_booking    = $booking_repo->find( $booking->id );
			if ( $b_booking && ! empty( $b_booking->booking_block_ids ) ) {
				$b_blocks = $block_repo->find_by_ids( $b_booking->booking_block_ids );
				foreach ( $b_blocks as $b_obj ) {
					if ( ! empty( $b_obj->custom_instructions ) ) {
						$custom_inst_tags[] = $b_obj->custom_instructions;
					}
				}
			}
		}

		$display_time = $time_range;
		if ( ! empty( $booking->slot_name ) && ! empty( $time_range ) && $booking->slot_name !== $time_range ) {
			$display_time = $booking->slot_name . ' (' . $time_range . ')';
		} elseif ( empty( $display_time ) && ! empty( $booking->slot_name ) ) {
			$display_time = $booking->slot_name;
		}

		echo '<tr class="snippen-booking-row" id="booking-' . esc_attr( $booking->id ) . '">';

		// Mobile single-cell summary (Visible on mobile <= 768px, hidden on desktop)
		echo '<td class="snippen-booking-mobile-summary" colspan="8">';
		echo '<div class="snippen-mobile-summary-card">';
		echo '<div class="snippen-mobile-summary-header">';
		echo '<strong class="snippen-mobile-customer-name">' . esc_html( $booking->customer_name ) . '</strong>';
		echo '<button type="button" class="snippen-btn-action toggle-details" title="' . esc_attr__( 'Vis detaljer', 'snippen-booking' ) . '" aria-expanded="false"><span class="dashicons dashicons-arrow-down-alt2"></span></button>';
		echo '</div>';
		echo '<div class="snippen-mobile-summary-time">';
		echo '<strong>' . esc_html( $booking_date ) . '</strong>';
		if ( ! empty( $display_time ) ) {
			echo ' &bull; ' . esc_html( $display_time );
		}
		if ( ! empty( $custom_inst_tags ) ) {
			echo ' <span class="snippen-badge" style="background:#e0f2fe; color:#0369a1; font-size:10px; padding:1px 5px; margin-left:4px;" title="' . esc_attr( implode( ' | ', $custom_inst_tags ) ) . '">' . esc_html__( 'Info', 'snippen-booking' ) . '</span>';
		}
		echo '</div>';
		echo '<div class="snippen-mobile-summary-objects">';
		foreach ( $objs as $oname ) {
			echo '<span class="snippen-tag">' . esc_html( $oname ) . '</span> ';
		}
		echo '</div>';
		echo '<div class="snippen-mobile-summary-badges" style="display:flex; gap:6px; flex-wrap:wrap; align-items:center; margin-top:2px;">';
		echo '<span class="snippen-badge snippen-status-badge ' . esc_attr( $status_class ) . '">' . esc_html( $this->get_status_label( $booking->status ) ) . '</span>';
		echo $this->render_type_badge( $booking->booking_type ?? 'private' );
		echo '<span class="snippen-badge snippen-payment-badge" style="background:' . ( $payment_status->is_settled ? '#dcfce7; color:#15803d' : '#fef3c7; color:#b45309' ) . ';">' . esc_html( $payment_status->name ) . '</span>';
		echo '</div>';
		echo '</div>';
		echo '</td>';

		echo '<td data-label="' . esc_attr__( 'Handlinger', 'snippen-booking' ) . '">';
		echo '<div style="display:flex; justify-content:flex-start; gap:8px;">';
		if ( $booking->status === 'pending' ) {
			echo '<button class="snippen-btn-action approve" data-id="' . esc_attr( $booking->id ) . '" title="' . esc_attr__( 'Godkjenn', 'snippen-booking' ) . '"><span class="dashicons dashicons-yes"></span></button>';
		}
		if ( $booking->status !== 'cancelled' ) {
			echo '<button class="snippen-btn-action cancel" data-id="' . esc_attr( $booking->id ) . '" title="' . esc_attr__( 'Avbryt', 'snippen-booking' ) . '"><span class="dashicons dashicons-no"></span></button>';
		}
		echo '<button type="button" class="snippen-btn-action edit snippen-btn-edit-booking" data-id="' . esc_attr( $booking->id ) . '" title="' . esc_attr__( 'Rediger booking', 'snippen-booking' ) . '"><span class="dashicons dashicons-edit"></span></button>';
		echo '</div></td>';
		echo '<td data-label="' . esc_attr__( 'Dato / Tid', 'snippen-booking' ) . '"><strong>' . esc_html( $booking_date ) . '</strong>' . ( ! empty( $display_time ) ? '<br><small>' . esc_html( $display_time ) . '</small>' : '' ) . ( ! empty( $custom_inst_tags ) ? '<br><span class="snippen-badge" style="background:#e0f2fe; color:#0369a1; font-size:10px; padding:2px 6px; margin-top:2px; display:inline-block;" title="' . esc_attr( implode( ' | ', $custom_inst_tags ) ) . '">' . esc_html__( 'Info', 'snippen-booking' ) . '</span>' : '' ) . '</td>';
		echo '<td data-label="' . esc_attr__( 'Kunde', 'snippen-booking' ) . '"><strong>' . esc_html( $booking->customer_name ) . '</strong><br><small>' . esc_html( $booking->customer_email ) . '</small></td>';
		echo '<td data-label="' . esc_attr__( 'Lokaler', 'snippen-booking' ) . '">';
		foreach ( $objs as $oname ) {
			echo '<span class="snippen-tag">' . esc_html( $oname ) . '</span> ';
		}
		echo '</td>';
		echo '<td data-label="' . esc_attr__( 'Pris', 'snippen-booking' ) . '" style="font-weight:600;">' . number_format( $booking->price, 0, ',', ' ' ) . ',-</td>';
		echo '<td data-label="' . esc_attr__( 'Status', 'snippen-booking' ) . '"><div style="display:flex; flex-direction:column; gap:4px; align-items:flex-start;"><span class="snippen-badge snippen-status-badge ' . esc_attr( $status_class ) . '">' . esc_html( $this->get_status_label( $booking->status ) ) . '</span>' . $this->render_type_badge( $booking->booking_type ?? 'private' ) . '</div></td>';

		echo '<td data-label="' . esc_attr__( 'Betaling', 'snippen-booking' ) . '">';
		echo '<span class="snippen-badge snippen-payment-badge" style="background:' . ( $payment_status->is_settled ? '#dcfce7; color:#15803d' : '#fef3c7; color:#b45309' ) . ';">' . esc_html( $payment_status->name ) . '</span>';
		if ( ! empty( $booking->payment_receipt_attachment_id ) ) {
			$receipt_url = wp_get_attachment_url( $booking->payment_receipt_attachment_id );
			if ( $receipt_url ) {
				echo '<br><a href="' . esc_url( $receipt_url ) . '" target="_blank" style="font-size:11px; text-decoration:none; color:#0284c7; margin-top:3px; display:inline-block;" title="' . esc_attr__( 'Vis kvittering', 'snippen-booking' ) . '"><span class="dashicons dashicons-paperclip" style="font-size:13px; width:13px; height:13px; line-height:13px; vertical-align:middle;"></span> ' . esc_html__( 'Kvittering', 'snippen-booking' ) . '</a>';
			}
		}
		echo '</td>';

		echo '<td data-label="' . esc_attr__( 'Detaljer', 'snippen-booking' ) . '" style="text-align:right;"><button class="snippen-btn-action toggle-details" title="' . esc_attr__( 'Vis detaljer', 'snippen-booking' ) . '" aria-expanded="false"><span class="dashicons dashicons-arrow-down-alt2"></span></button></td>';
		echo '</tr>';

		// Details Row (Hidden)
		echo '<tr class="snippen-details-row" id="details-' . esc_attr( $booking->id ) . '" style="display:none; background:#f8fafc;">';
		echo '<td colspan="8">';
		echo '<div class="details-content">';

		// Action buttons inside details row (prominent and colored)
		echo '<div class="booking-details-actions-wrap">';
		echo '<strong>' . esc_html__( 'Handlinger:', 'snippen-booking' ) . '</strong>';
		echo '<div class="booking-details-action-buttons">';
		echo '<button type="button" class="snippen-btn-action edit with-label snippen-btn-edit-booking" data-id="' . esc_attr( $booking->id ) . '" title="' . esc_attr__( 'Rediger booking', 'snippen-booking' ) . '"><span class="dashicons dashicons-edit"></span> <span>' . esc_html__( 'Rediger booking', 'snippen-booking' ) . '</span></button>';
		if ( $booking->status === 'pending' ) {
			echo '<button type="button" class="snippen-btn-action approve with-label" data-id="' . esc_attr( $booking->id ) . '" title="' . esc_attr__( 'Godkjenn', 'snippen-booking' ) . '"><span class="dashicons dashicons-yes"></span> <span>' . esc_html__( 'Godkjenn booking', 'snippen-booking' ) . '</span></button>';
		}
		if ( $booking->status !== 'cancelled' ) {
			echo '<button type="button" class="snippen-btn-action cancel with-label" data-id="' . esc_attr( $booking->id ) . '" title="' . esc_attr__( 'Avbryt', 'snippen-booking' ) . '"><span class="dashicons dashicons-no"></span> <span>' . esc_html__( 'Avbryt booking', 'snippen-booking' ) . '</span></button>';
		}
		echo '</div></div>';
		echo '<div><strong>' . esc_html__( 'Kontaktinfo:', 'snippen-booking' ) . '</strong><br>' . esc_html( $booking->customer_phone ?: '-' ) . '</div>';
		echo '<div><strong>' . esc_html__( 'Type arrangement:', 'snippen-booking' ) . '</strong><br>' . $this->render_type_badge( $booking->booking_type ?? 'private' ) . '</div>';
		echo '<div><strong>' . esc_html__( 'Lokale(r):', 'snippen-booking' ) . '</strong><br>' . esc_html( implode( ', ', $objs ) ) . '</div>';
		echo '<div><strong>' . esc_html__( 'Beskrivelse/Notater:', 'snippen-booking' ) . '</strong><br>' . esc_html( $booking->description ?: '-' ) . '</div>';
		if ( ! empty( $booking->rejection_reason ) ) {
			echo '<div style="grid-column: 1 / -1; background:#fef2f2; border:1px solid #fecaca; border-radius:6px; padding:10px 14px; color:#991b1b; font-size:13px; margin: 4px 0;">';
			echo '<strong><span class="dashicons dashicons-warning" style="vertical-align:middle; font-size:16px;"></span> ' . esc_html__( 'Begrunnelse for avslag:', 'snippen-booking' ) . '</strong><br>';
			echo esc_html( $booking->rejection_reason );
			echo '</div>';
		}
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
		echo '<div class="payment-admin-container" data-id="' . esc_attr( $booking->id ) . '">';
		echo '<strong>' . esc_html__( 'Betalingsadministrasjon:', 'snippen-booking' ) . '</strong><br>';
		echo '<div class="payment-status-radio-group">';
		foreach ( $all_statuses as $st ) {
			$radio_id = 'payment_status_' . esc_attr( $booking->id ) . '_' . esc_attr( $st->id );
			echo '<label class="payment-status-radio-label" for="' . esc_attr( $radio_id ) . '">';
			echo '<input type="radio" id="' . esc_attr( $radio_id ) . '" class="payment-status-radio" name="payment_status_' . esc_attr( $booking->id ) . '" value="' . esc_attr( $st->id ) . '" ' . checked( $payment_status->id, $st->id, false ) . '> ';
			echo '<span>' . esc_html( $st->name ) . '</span>';
			echo '</label>';
		}
		echo '</div>';

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

		echo '<div class="booking-assistant-actions" data-id="' . esc_attr( $booking->id ) . '" data-uuid="' . esc_attr( $booking->uuid ) . '">';
		echo '<strong>' . esc_html__( 'Booking-hjelper:', 'snippen-booking' ) . '</strong><br>';
		echo '<button class="button snippen-btn-dispatch" data-channel="email_customer" style="margin-top:6px; margin-bottom:6px; display:block; width:100%; text-align:left;"><span class="dashicons dashicons-email" style="vertical-align:middle; margin-right:4px; font-size:16px; width:16px; height:16px; line-height:16px;"></span> ' . esc_html__( 'E-post til kunde', 'snippen-booking' ) . '</button>';
		echo '<button class="button snippen-btn-dispatch" data-channel="sms_customer" style="margin-bottom:6px; display:block; width:100%; text-align:left;"><span class="dashicons dashicons-phone" style="vertical-align:middle; margin-right:4px; font-size:16px; width:16px; height:16px; line-height:16px;"></span> ' . esc_html__( 'SMS til kunde', 'snippen-booking' ) . '</button>';
		echo '<button class="button snippen-btn-dispatch" data-channel="email_admin" style="margin-bottom:6px; display:block; width:100%; text-align:left;"><span class="dashicons dashicons-email" style="vertical-align:middle; margin-right:4px; font-size:16px; width:16px; height:16px; line-height:16px;"></span> ' . esc_html__( 'Varsel til admin', 'snippen-booking' ) . '</button>';
		echo '<div class="assistant-feedback" style="margin-top:6px; font-size:11px; font-weight:600; min-height:15px;"></div>';
		echo '</div>';

		// Revision / Snapshot history block for this booking
		$booking_repo = new \SnippenBooking\Database\Repository\BookingRepository();
		$snapshots    = $booking_repo->get_snapshots( (int) $booking->id );
		$snap_count   = count( $snapshots );

		echo '<div class="booking-revisions-history' . ( $snap_count > 1 ? ' has-multiple-revisions' : '' ) . '" data-booking-id="' . esc_attr( $booking->id ) . '">';
		echo '<div class="rev-history-header" style="display:flex; justify-content:space-between; align-items:center; padding:8px 0;">';
		echo '<div class="rev-history-header-title"><strong style="font-size:13px; color:#1e293b;"><span class="dashicons dashicons-backup" style="vertical-align:middle; font-size:16px; width:16px; height:16px; line-height:16px; margin-right:4px;"></span> ' . esc_html__( 'Endringshistorikk:', 'snippen-booking' ) . ' (<span class="rev-count">' . $snap_count . '</span> ' . ( 1 === $snap_count ? esc_html__( 'versjon', 'snippen-booking' ) : esc_html__( 'versjoner', 'snippen-booking' ) ) . ')</strong></div>';
		echo '<div style="display:flex; gap:6px;">';
		echo '<button type="button" class="button button-small toggle-rev-history" aria-expanded="false">';
		echo '<span class="toggle-text">' . esc_html__( 'Vis historikk', 'snippen-booking' ) . '</span> ';
		echo '<span class="dashicons dashicons-arrow-down-alt2" style="font-size:14px; width:14px; height:14px; line-height:14px; vertical-align:middle;"></span>';
		echo '</button>';
		echo '<button type="button" class="button button-small snippen-btn-edit-booking" data-id="' . esc_attr( $booking->id ) . '" data-tab="history" title="' . esc_attr__( 'Åpne detaljert historikk i dialog', 'snippen-booking' ) . '">';
		echo '<span class="dashicons dashicons-external" style="font-size:14px; width:14px; height:14px; line-height:14px; vertical-align:middle;"></span> ' . esc_html__( 'Detaljer', 'snippen-booking' );
		echo '</button>';
		echo '</div>';
		echo '</div>'; // .rev-history-header

		echo '<div class="rev-history-body" style="display:none; margin-top:8px;">';
		if ( empty( $snapshots ) ) {
			echo '<p style="margin:0; font-size:12px; color:#64748b;">' . esc_html__( 'Ingen endringshistorikk registrert ennå.', 'snippen-booking' ) . '</p>';
		} else {
			echo '<div class="rev-list-container">';
			foreach ( $snapshots as $snap ) {
				$snap_date = date_i18n( get_option( 'date_format' ) . ' H:i', strtotime( $snap->created_at ) );
				$dec       = $snap->decoded_snapshot;
				$time_str  = ! empty( $dec['time_range_formatted'] ) ? $dec['time_range_formatted'] : '';
				$price_str = isset( $dec['price'] ) ? number_format( (float) $dec['price'], 0, ',', ' ' ) . ',-' : '';

				echo '<div class="rev-item" style="border-left:3px solid #0284c7; background:#fff; border-radius:4px; padding:8px 12px; margin-bottom:6px; box-shadow:0 1px 2px rgba(0,0,0,0.05); font-size:12px;">';
				echo '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">';
				echo '<div><strong style="color:#0f172a;">' . sprintf( esc_html__( 'Revisjon #%d', 'snippen-booking' ), (int) $snap->revision ) . '</strong>';
				if ( 1 === (int) $snap->revision ) {
					echo ' <span class="snippen-badge" style="background:#e0f2fe; color:#0369a1; font-size:10px; padding:1px 5px; margin-left:4px;">' . esc_html__( 'Opprinnelig', 'snippen-booking' ) . '</span>';
				}
				echo ' <span style="color:#64748b; margin-left:4px;">&bull; ' . sprintf( esc_html__( 'Endret av %s', 'snippen-booking' ), '<strong>' . esc_html( $snap->modifier_name ) . '</strong>' ) . '</span></div>';
				echo '<span style="color:#64748b; font-size:11px;">' . esc_html( $snap_date ) . '</span>';
				echo '</div>';
				if ( ! empty( $snap->changes_summary ) ) {
					echo '<div style="color:#334155; margin-bottom:4px;"><em>' . esc_html( $snap->changes_summary ) . '</em></div>';
				}
				if ( ! empty( $time_str ) || ! empty( $price_str ) ) {
					echo '<div style="color:#64748b; font-size:11px;">';
					if ( ! empty( $time_str ) ) {
						echo esc_html__( 'Tid:', 'snippen-booking' ) . ' ' . esc_html( $time_str ) . ' ';
					}
					if ( ! empty( $price_str ) ) {
						echo '&bull; ' . esc_html__( 'Pris:', 'snippen-booking' ) . ' ' . esc_html( $price_str );
					}
					echo '</div>';
				}
				echo '</div>'; // .rev-item
			}
			echo '</div>'; // .rev-list-container
		}
		echo '</div>'; // .rev-history-body
		echo '</div>'; // .booking-revisions-history

		// Communication / Messages history block for this booking
		$messages = \SnippenBooking\Service\Notification\MessageLoggerService::get_messages_for_booking( (int) $booking->id );

		$known_event_types = array(
			'booking_confirmation'            => __( 'Booking-bekreftelse', 'snippen-booking' ),
			'manual_dispatch_customer'        => __( 'Manuell leietakermelding', 'snippen-booking' ),
			'admin_booking'                   => __( 'Admin bookingvarsel', 'snippen-booking' ),
			'manual_dispatch_admin'           => __( 'Manuell adminmelding', 'snippen-booking' ),
			'user_activation'                 => __( 'Kontoaktivering', 'snippen-booking' ),
			'password_reset'                  => __( 'Passordtilbakestilling', 'snippen-booking' ),
			'payment_reminder'                => __( 'Betalingspåminnelse', 'snippen-booking' ),
			'payment_receipt_uploaded'        => __( 'Kvittering lastet opp', 'snippen-booking' ),
			'booking_confirmed'               => __( 'Booking godkjent', 'snippen-booking' ),
			'payment_received'                => __( 'Betaling bekreftet', 'snippen-booking' ),
			'inbound_sms'                     => __( 'Innkommende SMS', 'snippen-booking' ),
			'sms_disambiguation_prompt'       => __( 'Valgforespørsel (SMS)', 'snippen-booking' ),
			'sms_disambiguation_confirmation' => __( 'Valgbekreftelse (SMS)', 'snippen-booking' ),
		);

		$total_count    = count( $messages );
		$visible_count  = 0;
		$filtered_count = 0;
		foreach ( $messages as $msg ) {
			if ( self::is_filtered_message( $msg ) ) {
				++$filtered_count;
			} else {
				++$visible_count;
			}
		}

		$history_classes = 'booking-messages-history' . ( $visible_count > 0 ? ' has-visible-messages' : '' );

		echo '<div class="' . esc_attr( $history_classes ) . '" data-booking-id="' . esc_attr( $booking->id ) . '">';
		echo '<div class="msg-history-header">';
		echo '<div class="msg-history-header-title"><strong style="font-size:13px; color:#1e293b;"><span class="dashicons dashicons-format-chat" style="vertical-align:middle; font-size:16px; width:16px; height:16px; line-height:16px; margin-right:4px;"></span> ' . esc_html__( 'Kommunikasjonshistorikk:', 'snippen-booking' ) . ' (<span class="msg-count" data-visible-count="' . esc_attr( $visible_count ) . '" data-total-count="' . esc_attr( $total_count ) . '">' . $visible_count . '</span>)</strong></div>';
		echo '<button type="button" class="button button-small toggle-msg-history" aria-expanded="false">';
		echo '<span class="toggle-text">' . esc_html__( 'Vis kommunikasjon', 'snippen-booking' ) . '</span> ';
		echo '<span class="dashicons dashicons-arrow-down-alt2" style="font-size:14px; width:14px; height:14px; line-height:14px; vertical-align:middle;"></span>';
		echo '</button>';
		echo '</div>'; // .msg-history-header

		echo '<div class="msg-history-body" style="display:none;">';

		if ( empty( $messages ) ) {
			echo '<p class="no-messages-text" style="margin:0; font-size:12px; color:#64748b;">' . esc_html__( 'Ingen meldinger registrert på denne bookingen ennå.', 'snippen-booking' ) . '</p>';
		} else {
			echo '<div class="msg-history-toolbar">';
			echo '<label class="msg-history-filter-toggle">';
			echo '<input type="checkbox" class="snippen-toggle-all-messages" /> ';
			echo '<span>' . esc_html__( 'Vis all kommunikasjon', 'snippen-booking' ) . '</span>';
			echo '</label>';
			if ( $filtered_count > 0 ) {
				/* translators: %d: number of hidden messages */
				echo '<span class="msg-filtered-indicator">(' . esc_html( sprintf( _n( '%d skjult', '%d skjulte', $filtered_count, 'snippen-booking' ), $filtered_count ) ) . ')</span>';
			} else {
				echo '<span class="msg-filtered-indicator" style="display:none;"></span>';
			}
			echo '</div>'; // .msg-history-toolbar

			echo '<p class="no-visible-messages-text" style="margin:0; font-size:12px; color:#64748b;">' . esc_html__( 'Ingen meldinger å vise med gjeldende filter.', 'snippen-booking' ) . '</p>';

			echo '<div class="msg-list-container">';
			foreach ( $messages as $msg ) {
				$icon_class    = $msg->channel === 'sms' ? 'dashicons-smartphone' : 'dashicons-email-alt';
				$channel_label = strtoupper( $msg->channel );
				$status_badge  = '';
				if ( 'sent' === $msg->status ) {
					$status_badge = '<span class="snippen-badge" style="background:#dcfce7; color:#15803d; font-size:10px; padding:1px 5px;">' . esc_html__( 'Sendt', 'snippen-booking' ) . '</span>';
				} elseif ( 'queued' === $msg->status ) {
					$status_badge = '<span class="snippen-badge" style="background:#fef3c7; color:#b45309; font-size:10px; padding:1px 5px;">' . esc_html__( 'I kø', 'snippen-booking' ) . '</span>';
				} elseif ( 'received' === $msg->status ) {
					$status_badge = '<span class="snippen-badge" style="background:#e0e7ff; color:#3730a3; font-size:10px; padding:1px 5px;">' . esc_html__( 'Mottatt', 'snippen-booking' ) . '</span>';
				} else {
					$status_badge = '<span class="snippen-badge" style="background:#fee2e2; color:#b91c1c; font-size:10px; padding:1px 5px;">' . esc_html__( 'Feilet', 'snippen-booking' ) . '</span>';
				}

				$event_type  = $msg->event_type ?? '';
				$label_text  = isset( $known_event_types[ $event_type ] ) ? $known_event_types[ $event_type ] : $event_type;
				$is_filtered = self::is_filtered_message( $msg );
				$item_class  = 'msg-item' . ( $is_filtered ? ' msg-item-filtered' : '' );

				echo '<div class="' . esc_attr( $item_class ) . '" data-event-type="' . esc_attr( $event_type ) . '">';
				echo '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">';
				echo '<div><span class="dashicons ' . esc_attr( $icon_class ) . '" style="font-size:14px; width:14px; height:14px; line-height:14px; vertical-align:middle;"></span> <strong>' . esc_html( $channel_label ) . ' &bull; ' . esc_html( $msg->recipient ) . '</strong> ' . $status_badge . ' <span style="font-size:10px; color:#64748b; margin-left:4px;">(' . esc_html( $label_text ) . ')</span></div>';
				echo '<span style="font-size:11px; color:#64748b;">' . esc_html( $msg->created_at ) . '</span>';
				echo '</div>';
				if ( ! empty( $msg->subject ) ) {
					echo '<div style="font-weight:600; color:#334155; margin-bottom:2px;">' . esc_html__( 'Emne:', 'snippen-booking' ) . ' ' . esc_html( $msg->subject ) . '</div>';
				}
				echo '<div class="msg-item-body">' . esc_html( $msg->message ) . '</div>';
				echo '</div>';
			}
			echo '</div>'; // .msg-list-container
		}
		echo '</div>'; // .msg-history-body
		echo '</div>'; // .booking-messages-history

		echo '</div></td></tr>';
	}

	/**
	 * Render dispatch modal markup for editing message before sending
	 */
	private function render_dispatch_modal() {
		?>
		<div id="snippen-dispatch-modal" class="snippen-modal-backdrop" style="display:none;">
			<div class="snippen-modal-content">
				<div class="snippen-modal-header">
					<h2 class="snippen-modal-title"></h2>
					<button type="button" class="snippen-modal-close" aria-label="<?php esc_attr_e( 'Lukk', 'snippen-booking' ); ?>">&times;</button>
				</div>
				<div class="snippen-modal-body">
					<div class="snippen-form-group snippen-modal-recipient-wrap">
						<label><?php esc_html_e( 'Mottaker:', 'snippen-booking' ); ?></label>
						<input type="text" class="snippen-modal-recipient" readonly style="width:100%; background:#f1f5f9; color:#475569;">
					</div>
					<div class="snippen-form-group snippen-modal-template-wrap" style="margin-top: 12px;">
						<label><?php esc_html_e( 'Velg mal:', 'snippen-booking' ); ?></label>
						<select class="snippen-modal-template-select" style="width:100%;">
						</select>
					</div>
					<div class="snippen-form-group snippen-modal-subject-wrap" style="margin-top: 12px;">
						<label><?php esc_html_e( 'Emne:', 'snippen-booking' ); ?></label>
						<input type="text" class="snippen-modal-subject" style="width:100%;">
					</div>
					<div class="snippen-form-group" style="margin-top: 12px;">
						<label style="display:flex; justify-content:space-between; align-items:center;">
							<span><?php esc_html_e( 'Melding:', 'snippen-booking' ); ?></span>
							<span class="snippen-placeholder-copied-hint" style="font-size:11px; color:#15803d; font-weight:600; display:none;"><?php esc_html_e( 'Kopiert til utklippstavle og satt inn!', 'snippen-booking' ); ?></span>
						</label>
						<div class="snippen-modal-placeholders-wrap" style="margin-bottom:6px; display:flex; flex-wrap:wrap; gap:4px; max-height:100px; overflow-y:auto; padding:6px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:4px;">
						</div>
						<textarea class="snippen-modal-message" rows="8" style="width:100%; font-family:inherit; font-size:13px; padding:10px; border-radius:6px; border:1px solid #cbd5e1;"></textarea>
					</div>
					<div class="snippen-modal-feedback" style="margin-top:10px; font-size:12px; font-weight:600;"></div>
				</div>
				<div class="snippen-modal-footer">
					<button type="button" class="button snippen-modal-cancel"><?php esc_html_e( 'Avbryt', 'snippen-booking' ); ?></button>
					<button type="button" class="button button-primary snippen-modal-submit"></button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Get status label
	 */
	private function get_status_label( $status ) {
		$labels = array(
			'pending'         => __( 'Venter', 'snippen-booking' ),
			'pending_payment' => __( 'Venter på betaling', 'snippen-booking' ),
			'confirmed'       => __( 'Bekreftet', 'snippen-booking' ),
			'cancelled'       => __( 'Avbrutt', 'snippen-booking' ),
		);
		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}

	/**
	 * Render booking type badge
	 *
	 * @param string $booking_type
	 * @return string HTML badge
	 */
	private function render_type_badge( $booking_type ) {
		switch ( $booking_type ) {
			case 'open':
				return '<span class="snippen-badge snippen-type-badge snippen-type-open" style="background:#e0e7ff; color:#3730a3; font-weight:600;">' . esc_html__( 'Åpen for sameiet', 'snippen-booking' ) . '</span>';
			case 'cleaning':
				return '<span class="snippen-badge snippen-type-badge snippen-type-cleaning" style="background:#ccfbf1; color:#0f766e; font-weight:600;">' . esc_html__( 'Utvask', 'snippen-booking' ) . '</span>';
			case 'private':
			default:
				return '<span class="snippen-badge snippen-type-badge snippen-type-private" style="background:#f1f5f9; color:#475569;">' . esc_html__( 'Privat', 'snippen-booking' ) . '</span>';
		}
	}

	/**
	 * Determine if a communication message is an admin notification or numeric selection reply.
	 *
	 * @param object $msg Message object.
	 * @return bool True if message should be filtered by default.
	 */
	public static function is_filtered_message( object $msg ): bool {
		$admin_event_types = array(
			'admin_booking',
			'manual_dispatch_admin',
			'payment_receipt_uploaded',
		);

		$event_type = $msg->event_type ?? '';
		if ( in_array( $event_type, $admin_event_types, true ) || false !== strpos( $event_type, 'admin' ) ) {
			return true;
		}

		$metadata = array();
		if ( ! empty( $msg->metadata ) ) {
			$metadata = is_string( $msg->metadata ) ? json_decode( $msg->metadata, true ) : (array) $msg->metadata;
		}
		if ( is_array( $metadata ) && isset( $metadata['matched_rule'] ) && 'disambiguation_selection' === $metadata['matched_rule'] ) {
			return true;
		}

		$is_inbound = ( 'inbound_sms' === $event_type || 'received' === ( $msg->status ?? '' ) );
		if ( $is_inbound && ! empty( $msg->message ) ) {
			if ( preg_match( '/^\s*(?:nr\.?|nummer|valg|booking|#)?\s*\d+\.?\s*$/i', trim( $msg->message ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Render edit booking modal markup
	 */
	private function render_edit_modal() {
		?>
		<div id="snippen-edit-booking-modal" class="snippen-modal-backdrop" style="display:none;">
			<div class="snippen-modal-content snippen-modal-large" style="max-width:760px;">
				<div class="snippen-modal-header" style="flex-wrap:wrap; gap:10px;">
					<div style="display:flex; align-items:center; gap:8px;">
						<span class="dashicons dashicons-edit" style="color:#0284c7; font-size:22px; width:22px; height:22px;"></span>
						<h2 class="snippen-modal-title" id="snippen-edit-booking-title"><?php esc_html_e( 'Rediger booking', 'snippen-booking' ); ?></h2>
					</div>
					<button type="button" class="snippen-modal-close" aria-label="<?php esc_attr_e( 'Lukk', 'snippen-booking' ); ?>">&times;</button>
					<div class="snippen-modal-tabs" style="width:100%; display:flex; gap:8px; border-bottom:1px solid #e2e8f0; margin-top:8px; padding-bottom:2px;">
						<button type="button" class="snippen-modal-tab active" data-tab="edit-form">
							<span class="dashicons dashicons-edit" style="font-size:16px; width:16px; height:16px; line-height:16px; vertical-align:middle;"></span> <?php esc_html_e( 'Rediger opplysninger', 'snippen-booking' ); ?>
						</button>
						<button type="button" class="snippen-modal-tab" data-tab="history">
							<span class="dashicons dashicons-backup" style="font-size:16px; width:16px; height:16px; line-height:16px; vertical-align:middle;"></span> <?php esc_html_e( 'Snapshot-historikk', 'snippen-booking' ); ?> (<span class="snippen-tab-history-count">0</span>)
						</button>
					</div>
				</div>
				<div class="snippen-modal-body">
					<div id="snippen-edit-loading" style="text-align:center; padding:30px 10px; color:#64748b;">
						<span class="spinner is-active" style="float:none; margin:0 6px 0 0; vertical-align:middle;"></span> <?php esc_html_e( 'Henter bookingopplysninger...', 'snippen-booking' ); ?>
					</div>
					<form id="snippen-edit-booking-form" style="display:none;">
						<input type="hidden" name="booking_id" id="edit_booking_id" value="">

						<div class="snippen-tab-panel active" id="tab-panel-edit-form">
							<div class="snippen-edit-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:16px;">

								<div class="snippen-form-group">
									<label for="edit_booking_date"><?php esc_html_e( 'Dato:', 'snippen-booking' ); ?> *</label>
									<input type="date" name="booking_date" id="edit_booking_date" required style="width:100%;">
								</div>

								<div class="snippen-form-group">
									<label for="edit_booking_type"><?php esc_html_e( 'Type arrangement:', 'snippen-booking' ); ?></label>
									<select name="booking_type" id="edit_booking_type" style="width:100%;">
										<option value="private"><?php esc_html_e( 'Privat', 'snippen-booking' ); ?></option>
										<option value="open"><?php esc_html_e( 'Åpen for sameiet', 'snippen-booking' ); ?></option>
										<option value="cleaning"><?php esc_html_e( 'Utvask', 'snippen-booking' ); ?></option>
									</select>
								</div>

								<div class="snippen-form-group" style="grid-column: 1 / -1;">
									<label><?php esc_html_e( 'Lokale(r):', 'snippen-booking' ); ?> *</label>
									<div id="edit_objects_container" style="display:flex; flex-wrap:wrap; gap:12px; padding:10px 14px; background:#f8fafc; border:1px solid #cbd5e1; border-radius:6px;">
										<!-- Checkboxes populated via JS -->
									</div>
								</div>

								<div class="snippen-form-group" style="grid-column: 1 / -1;">
									<label><?php esc_html_e( 'Tidsblokk(er):', 'snippen-booking' ); ?> *</label>
									<div id="edit_blocks_container" style="display:flex; flex-wrap:wrap; gap:12px; padding:10px 14px; background:#f8fafc; border:1px solid #cbd5e1; border-radius:6px;">
										<!-- Checkboxes populated via JS -->
									</div>
								</div>

								<div class="snippen-form-group">
									<label for="edit_customer_name"><?php esc_html_e( 'Kundenavn:', 'snippen-booking' ); ?> *</label>
									<input type="text" name="customer_name" id="edit_customer_name" required style="width:100%;">
								</div>

								<div class="snippen-form-group">
									<label for="edit_customer_email"><?php esc_html_e( 'Kunde e-post:', 'snippen-booking' ); ?> *</label>
									<input type="email" name="customer_email" id="edit_customer_email" required style="width:100%;">
								</div>

								<div class="snippen-form-group">
									<label for="edit_customer_phone"><?php esc_html_e( 'Kunde telefon:', 'snippen-booking' ); ?></label>
									<input type="text" name="customer_phone" id="edit_customer_phone" style="width:100%;">
								</div>

								<div class="snippen-form-group">
									<label for="edit_door_code"><?php esc_html_e( 'Dørkode:', 'snippen-booking' ); ?></label>
									<input type="text" name="door_code" id="edit_door_code" placeholder="<?php esc_attr_e( 'F.eks. 1234', 'snippen-booking' ); ?>" style="width:100%;">
								</div>

								<div class="snippen-form-group">
									<label for="edit_price"><?php esc_html_e( 'Pris (kr):', 'snippen-booking' ); ?></label>
									<input type="number" step="0.01" name="price" id="edit_price" style="width:100%;">
								</div>

								<div class="snippen-form-group">
									<label for="edit_discount_amount"><?php esc_html_e( 'Rabatt (kr):', 'snippen-booking' ); ?></label>
									<input type="number" step="0.01" name="discount_amount" id="edit_discount_amount" style="width:100%;">
								</div>

								<div class="snippen-form-group">
									<label for="edit_status"><?php esc_html_e( 'Booking-status:', 'snippen-booking' ); ?></label>
									<select name="status" id="edit_status" style="width:100%;">
										<option value="pending"><?php esc_html_e( 'Venter på godkjenning', 'snippen-booking' ); ?></option>
										<option value="pending_payment"><?php esc_html_e( 'Venter på betaling', 'snippen-booking' ); ?></option>
										<option value="confirmed"><?php esc_html_e( 'Bekreftet', 'snippen-booking' ); ?></option>
										<option value="cancelled"><?php esc_html_e( 'Avbrutt', 'snippen-booking' ); ?></option>
									</select>
								</div>

								<div class="snippen-form-group">
									<label for="edit_payment_status_id"><?php esc_html_e( 'Betalingsstatus:', 'snippen-booking' ); ?></label>
									<select name="payment_status_id" id="edit_payment_status_id" style="width:100%;">
										<!-- Populated via JS -->
									</select>
								</div>

								<div class="snippen-form-group" style="grid-column: 1 / -1;">
									<label for="edit_description"><?php esc_html_e( 'Beskrivelse / Kundens formål:', 'snippen-booking' ); ?></label>
									<textarea name="description" id="edit_description" rows="2" style="width:100%;"></textarea>
								</div>

								<div class="snippen-form-group" style="grid-column: 1 / -1;">
									<label for="edit_payment_notes"><?php esc_html_e( 'Betalingsnotat (f.eks. Vipps-ref / transaksjon):', 'snippen-booking' ); ?></label>
									<textarea name="payment_notes" id="edit_payment_notes" rows="2" style="width:100%;"></textarea>
								</div>

								<div class="snippen-form-group" style="grid-column: 1 / -1; background:#f0f9ff; border:1px solid #bae6fd; border-radius:6px; padding:12px;">
									<label for="edit_changes_summary" style="color:#0369a1; font-weight:700;">
										<span class="dashicons dashicons-info" style="vertical-align:middle; font-size:16px;"></span>
										<?php esc_html_e( 'Begrunnelse / endringsnotat (loggføres i snapshot-historikk):', 'snippen-booking' ); ?>
									</label>
									<textarea name="changes_summary" id="edit_changes_summary" rows="2" placeholder="<?php esc_attr_e( 'F.eks.: Endret dato etter avtale med leietaker, eller lagt til ekstrarom.', 'snippen-booking' ); ?>" style="width:100%; margin-top:4px;"></textarea>
								</div>

							</div>
						</div>

						<div class="snippen-tab-panel" id="tab-panel-history" style="display:none;">
							<div id="edit_snapshots_timeline">
								<!-- Populated via JS -->
							</div>
						</div>

						<div class="snippen-edit-feedback" style="margin-top:12px; font-size:13px; font-weight:600;"></div>
					</form>
				</div>
				<div class="snippen-modal-footer">
					<button type="button" class="button snippen-modal-cancel"><?php esc_html_e( 'Lukk', 'snippen-booking' ); ?></button>
					<button type="submit" form="snippen-edit-booking-form" class="button button-primary snippen-btn-save-edit" style="display:none;">
						<span class="dashicons dashicons-saved" style="vertical-align:middle; margin-right:4px;"></span>
						<?php esc_html_e( 'Lagre endringer', 'snippen-booking' ); ?>
					</button>
				</div>
			</div>
		</div>
		<?php
	}
}
