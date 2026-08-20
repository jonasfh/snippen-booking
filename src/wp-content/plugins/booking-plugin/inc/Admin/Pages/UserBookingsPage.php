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
		} else {
			// By default, do not show cancelled bookings in the overview unless specifically requested
			$query .= " AND b.status != 'cancelled'";
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
		echo '<th style="text-align:left; width:100px;">' . esc_html__( 'Handlinger', 'snippen-booking' ) . '</th>';
		echo $this->render_sortable_header( 'booking_date', __( 'Dato / Tid', 'snippen-booking' ), $orderby, $order );
		echo '<th>' . esc_html__( 'Lokaler', 'snippen-booking' ) . '</th>';
		echo $this->render_sortable_header( 'price', __( 'Pris', 'snippen-booking' ), $orderby, $order );
		echo $this->render_sortable_header( 'status', __( 'Status', 'snippen-booking' ), $orderby, $order );
		echo '<th>' . esc_html__( 'Betaling', 'snippen-booking' ) . '</th>';
		echo '<th style="width:40px; text-align:right;"></th>';
		echo '</tr></thead>';
		echo '<tbody>';

		if ( empty( $bookings ) ) {
			echo '<tr><td colspan="7" style="padding:40px; text-align:center;">' . esc_html__( 'Du har ingen bookinger.', 'snippen-booking' ) . '</td></tr>';
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
		$table_junction_new    = $wpdb->prefix . 'snippen_booking_booking_objects';
		$table_junction_legacy = $wpdb->prefix . 'snippen_bookings_booking_objects';
		$table_objects         = $wpdb->prefix . 'snippen_booking_objects';

		$objs       = array();
		$time_range = '';

		if ( ! empty( $booking->booking_snapshot ) ) {
			$snapshot = json_decode( $booking->booking_snapshot, true );
			if ( is_array( $snapshot ) ) {
				if ( ! empty( $snapshot['objects'] ) && is_array( $snapshot['objects'] ) ) {
					foreach ( $snapshot['objects'] as $obj_item ) {
						if ( ! empty( $obj_item['name'] ) ) {
							$objs[] = $obj_item['name'];
						}
					}
				}
				if ( ! empty( $snapshot['time_range_formatted'] ) ) {
					$time_range = $snapshot['time_range_formatted'];
				} elseif ( ! empty( $snapshot['blocks'] ) && is_array( $snapshot['blocks'] ) ) {
					$block_names = array_column( $snapshot['blocks'], 'name' );
					$time_range  = implode( ', ', array_filter( $block_names ) );
				} elseif ( ! empty( $snapshot['start_time'] ) && ! empty( $snapshot['end_time'] ) ) {
					$time_range = date_i18n( 'H:i', strtotime( $snapshot['start_time'] ) ) . ' - ' . date_i18n( 'H:i', strtotime( $snapshot['end_time'] ) );
				}
			}
		}

		if ( empty( $objs ) ) {
			$objs = $wpdb->get_col(
				$wpdb->prepare(
					"
					SELECT DISTINCT o.name 
					FROM $table_objects o
					JOIN (
						SELECT booking_id, booking_object_id FROM $table_junction_new
						UNION
						SELECT booking_id, booking_object_id FROM $table_junction_legacy
					) bo ON o.id = bo.booking_object_id
					WHERE bo.booking_id = %d",
					$booking->id
				)
			);
		}

		if ( empty( $time_range ) ) {
			$time_range = ! empty( $booking->slot_name ) ? $booking->slot_name : '';
		}

		$status_class   = 'snippen-status-' . $booking->status;
		$booking_date   = date_i18n( get_option( 'date_format' ), strtotime( $booking->booking_date ) );
		$payment_status = \SnippenBooking\Service\PaymentService::get_booking_payment_status( $booking );

		$can_user_cancel = false;
		if ( $booking->status !== 'cancelled' ) {
			if ( \SnippenBooking\Helper\Capabilities::can_manage_bookings() ) {
				$can_user_cancel = true;
			} elseif ( 'confirmed' !== $booking->status && ! $payment_status->is_settled ) {
				$cancellation_days = intval( get_option( 'snippen_user_cancellation_days', 14 ) );
				$today             = new \DateTime( 'today' );
				$booking_start     = new \DateTime( $booking->booking_date );
				$days_until_start  = (int) $today->diff( $booking_start )->format( '%r%a' );
				if ( $days_until_start >= $cancellation_days ) {
					$can_user_cancel = true;
				}
			}
		}

		echo '<tr class="snippen-booking-row" id="booking-' . esc_attr( $booking->id ) . '">';
		echo '<td data-label="' . esc_attr__( 'Handlinger', 'snippen-booking' ) . '">';
		echo '<div style="display:flex; justify-content:flex-start; gap:8px;">';
		if ( $can_user_cancel ) {
			echo '<button class="snippen-btn-action cancel" data-id="' . esc_attr( $booking->id ) . '" title="' . esc_attr__( 'Avbryt', 'snippen-booking' ) . '"><span class="dashicons dashicons-no"></span></button>';
		}
		echo '</div></td>';
		echo '<td data-label="' . esc_attr__( 'Dato / Tid', 'snippen-booking' ) . '"><strong>' . esc_html( $booking_date ) . '</strong><br><small>' . esc_html( $time_range ) . '</small></td>';
		echo '<td data-label="' . esc_attr__( 'Lokaler', 'snippen-booking' ) . '">';
		foreach ( $objs as $oname ) {
			echo '<span class="snippen-tag">' . esc_html( $oname ) . '</span> ';
		}
		echo '</td>';
		echo '<td data-label="' . esc_attr__( 'Pris', 'snippen-booking' ) . '" style="font-weight:600;">' . number_format( $booking->price, 0, ',', ' ' ) . ',-</td>';
		echo '<td data-label="' . esc_attr__( 'Status', 'snippen-booking' ) . '"><span class="snippen-badge ' . esc_attr( $status_class ) . '">' . esc_html( $this->get_status_label( $booking->status ) ) . '</span></td>';

		echo '<td data-label="' . esc_attr__( 'Betaling', 'snippen-booking' ) . '">';
		echo '<span class="snippen-badge" style="background:' . ( $payment_status->is_settled ? '#dcfce7; color:#15803d' : '#fef3c7; color:#b45309' ) . ';">' . esc_html( $payment_status->name ) . '</span>';
		if ( ! empty( $booking->payment_receipt_attachment_id ) ) {
			$r_url = wp_get_attachment_url( $booking->payment_receipt_attachment_id );
			if ( $r_url ) {
				echo '<br><a href="' . esc_url( $r_url ) . '" target="_blank" style="font-size:11px; text-decoration:none; color:#0284c7; margin-top:3px; display:inline-block;"><span class="dashicons dashicons-paperclip" style="font-size:13px; width:13px; height:13px; line-height:13px; vertical-align:middle;"></span> ' . esc_html__( 'Kvittering', 'snippen-booking' ) . '</a>';
			}
		}
		echo '</td>';

		echo '<td data-label="' . esc_attr__( 'Detaljer', 'snippen-booking' ) . '" style="text-align:right;"><button class="snippen-btn-action toggle-details" title="' . esc_attr__( 'Vis detaljer', 'snippen-booking' ) . '"><span class="dashicons dashicons-arrow-down-alt2"></span></button></td>';
		echo '</tr>';

		// Details Row
		$door_code_enabled = \SnippenBooking\Service\DoorCodeService::is_enabled();
		if ( $door_code_enabled ) {
			\SnippenBooking\Service\DoorCodeService::sync_booking_door_code( $booking );

			$door_code_display = '';
			if ( \SnippenBooking\Service\DoorCodeService::is_in_window( $booking ) ) {
				$door_code_display = ! empty( $booking->door_code ) ? esc_html( $booking->door_code ) : esc_html__( 'Ikke satt', 'snippen-booking' );
			} else {
				$door_code_display = '<span style="color:#64748b; font-style:italic;">' . esc_html__( '<Koden er ikke tilgjengelig før nærmere booking start>', 'snippen-booking' ) . '</span>';
			}
		}

		$cols = $door_code_enabled ? 4 : 3;

		echo '<tr class="snippen-details-row" id="details-' . esc_attr( $booking->id ) . '" style="display:none; background:#f8fafc;">';
		echo '<td colspan="7" style="padding:20px 30px; border-bottom: 2px solid var(--border-color);">';
		echo '<div class="details-content" style="display:grid; grid-template-columns: repeat(' . $cols . ', 1fr); gap:30px; margin-bottom:15px;">';
		echo '<div><strong>' . esc_html__( 'Lokale(r):', 'snippen-booking' ) . '</strong><br>' . esc_html( implode( ', ', $objs ) ) . '</div>';
		echo '<div><strong>' . esc_html__( 'Beskrivelse:', 'snippen-booking' ) . '</strong><br>' . esc_html( $booking->description ?: '-' ) . '</div>';
		echo '<div><strong>' . esc_html__( 'Booket den:', 'snippen-booking' ) . '</strong><br>' . esc_html( $booking->created_at ) . '</div>';
		if ( $door_code_enabled ) {
			echo '<div><strong>' . esc_html__( 'Dørkode:', 'snippen-booking' ) . '</strong><br>' . $door_code_display . '</div>';
		}

		echo '<div class="snippen-mobile-detail" style="display:none;"><strong>' . esc_html__( 'Pris:', 'snippen-booking' ) . '</strong><br>' . number_format( $booking->price, 0, ',', ' ' ) . ',-</div>';
		echo '<div class="snippen-mobile-detail" style="display:none;"><strong>' . esc_html__( 'Status:', 'snippen-booking' ) . '</strong><br><span class="snippen-badge ' . esc_attr( $status_class ) . '">' . esc_html( $this->get_status_label( $booking->status ) ) . '</span></div>';
		echo '</div>';

		// Payment & Receipt upload section
		$bank_acc  = get_option( 'snippen_payment_bank_account', '' );
		$vipps_no  = get_option( 'snippen_payment_vipps_number', '' );
		$instructs = get_option( 'snippen_payment_instructions', __( 'Vennligst overfør leiebeløpet innen 3 dager fra booking. Merk betalingen med ditt navn eller booking-ID.', 'snippen-booking' ) );

		echo '<div class="snippen-payment-box" style="padding:15px; background:#ffffff; border:1px solid #cbd5e1; border-radius:6px; margin-top:10px;">';
		echo '<h4 style="margin:0 0 10px 0; font-size:14px;">' . esc_html__( 'Betalingsinformasjon', 'snippen-booking' ) . '</h4>';

		if ( $bank_acc || $vipps_no || $instructs ) {
			echo '<div style="font-size:13px; line-height:1.4; margin-bottom:10px; color:#334155;">';
			if ( $bank_acc ) {
				echo '<div><strong>' . esc_html__( 'Bankkontonr', 'snippen-booking' ) . ':</strong> ' . esc_html( $bank_acc ) . '</div>';
			}
			if ( $vipps_no ) {
				echo '<div><strong>' . esc_html__( 'Vipps', 'snippen-booking' ) . ':</strong> ' . esc_html( $vipps_no ) . '</div>';
			}
			if ( $instructs ) {
				echo '<div style="margin-top:4px; color:#475569;">' . nl2br( esc_html( $instructs ) ) . '</div>';
			}
			echo '</div>';
		}

		if ( ! empty( $booking->payment_receipt_attachment_id ) ) {
			$url = wp_get_attachment_url( $booking->payment_receipt_attachment_id );
			if ( $url ) {
				echo '<div style="margin-bottom:10px; font-size:13px;"><strong>' . esc_html__( 'Opplastet kvittering:', 'snippen-booking' ) . '</strong> <a href="' . esc_url( $url ) . '" target="_blank" style="color:#0284c7; text-decoration:underline;">' . esc_html__( 'Vis kvittering', 'snippen-booking' ) . '</a></div>';
			}
		}

		if ( ! $payment_status->is_settled ) {
			$form_id = 'upload-form-mybooking-' . $booking->id;
			$msg_id  = 'upload-msg-mybooking-' . $booking->id;

			echo '<form id="' . esc_attr( $form_id ) . '" style="margin-top:8px; display:flex; align-items:center; gap:10px; flex-wrap:wrap;">';
			echo '<label style="font-size:13px; font-weight:600;">' . esc_html__( 'Last opp kvittering / skjermbilde:', 'snippen-booking' ) . '</label>';
			echo '<input type="file" name="payment_receipt" class="snippen-receipt-input" accept="image/*,.pdf" required style="font-size:13px;">';
			echo '<button type="submit" class="button button-small" style="background:#0284c7; border:none; color:#fff; cursor:pointer;">' . esc_html__( 'Last opp', 'snippen-booking' ) . '</button>';
			echo '<span id="' . esc_attr( $msg_id ) . '" style="font-size:13px; font-weight:600;"></span>';
			echo '</form>';

			echo '<script>
			document.getElementById("' . esc_js( $form_id ) . '").addEventListener("submit", function(e) {
				e.preventDefault();
				var fileInput = this.querySelector(".snippen-receipt-input");
				if (!fileInput.files.length) return;
				var formData = new FormData();
				formData.append("action", "snippen_upload_payment_receipt");
				formData.append("booking_id", "' . intval( $booking->id ) . '");
				formData.append("booking_uuid", "' . esc_js( $booking->uuid ) . '");
				formData.append("payment_receipt", fileInput.files[0]);

				var msgSpan = document.getElementById("' . esc_js( $msg_id ) . '");
				msgSpan.style.color = "#0284c7";
				msgSpan.textContent = "' . esc_js( __( 'Laster opp...', 'snippen-booking' ) ) . '";

				fetch("' . esc_url( admin_url( 'admin-ajax.php' ) ) . '", {
					method: "POST",
					body: formData
				}).then(function(r) { return r.json(); })
				.then(function(res) {
					if (res.success) {
						msgSpan.style.color = "#16a34a";
						msgSpan.textContent = res.data.message;
						setTimeout(function() { window.location.reload(); }, 1500);
					} else {
						msgSpan.style.color = "#dc2626";
						msgSpan.textContent = res.data.message || "' . esc_js( __( 'Feil ved opplasting.', 'snippen-booking' ) ) . '";
					}
				}).catch(function(err) {
					msgSpan.style.color = "#dc2626";
					msgSpan.textContent = "' . esc_js( __( 'Tilkoblingsfeil.', 'snippen-booking' ) ) . '";
				});
			});
			</script>';
		}

		echo '</div>'; // payment box

		echo '</td></tr>';
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
