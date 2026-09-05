<?php
/**
 * Admin page for managing Inbound SMS Messages and Quarantine
 *
 * @package SnippenBooking\Admin\Pages
 */

namespace SnippenBooking\Admin\Pages;

use SnippenBooking\Service\Notification\MessageLoggerService;

/**
 * Class SmsInboxPage
 */
class SmsInboxPage {

	/**
	 * Render the page
	 */
	public function render() {
		$this->handle_actions();

		$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
		$search        = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$page_num      = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$limit         = 30;
		$offset        = ( $page_num - 1 ) * $limit;

		$args = array(
			'status' => $status_filter,
			'search' => $search,
			'limit'  => $limit,
			'offset' => $offset,
		);

		$messages = MessageLoggerService::get_inbound_messages( $args );
		$total    = MessageLoggerService::count_inbound_messages( $args );

		echo '<div class="wrap snippen-booking-admin-wrap">';

		$this->render_header();
		$this->render_filters( $status_filter, $search );
		$this->render_list( $messages, $total, $page_num, $limit );

		echo '</div>';
	}

	/**
	 * Handle admin actions like manual message assignment
	 */
	private function handle_actions() {
		if ( ! isset( $_POST['snippen_inbox_action'] ) ) {
			return;
		}

		check_admin_referer( 'snippen_assign_sms_message', 'snippen_inbox_nonce' );

		if ( 'assign_to_booking' === $_POST['snippen_inbox_action'] ) {
			$message_id = absint( $_POST['message_id'] ?? 0 );
			$booking_id = absint( $_POST['booking_id'] ?? 0 );

			if ( $message_id > 0 && $booking_id > 0 ) {
				if ( MessageLoggerService::assign_message_to_booking( $message_id, $booking_id ) ) {
					echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Meldingen ble vellykket koblet til reservasjonen.', 'snippen-booking' ) . '</p></div>';
				} else {
					echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Kunne ikke koble meldingen til reservasjonen.', 'snippen-booking' ) . '</p></div>';
				}
			}
		}
	}

	/**
	 * Render header
	 */
	private function render_header() {
		echo '<div class="snippen-admin-header" style="margin-bottom:20px;">';
		echo '<h1><span class="dashicons dashicons-email-alt" style="font-size:28px; width:28px; height:28px; vertical-align:middle; margin-right:8px;"></span>' . esc_html__( 'SMS Innboks & Karantene', 'snippen-booking' ) . '</h1>';
		echo '<p class="description">' . esc_html__( 'Oversikt over innkommende SMS-meldinger, automatisk reservasjonskobling og meldinger i karantene.', 'snippen-booking' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Render filter bar
	 *
	 * @param string $status Current status filter.
	 * @param string $search Current search query.
	 */
	private function render_filters( string $status, string $search ) {
		echo '<div class="snippen-card" style="background:#fff; padding:15px 20px; border:1px solid #ccd0d4; border-radius:4px; margin-bottom:20px;">';
		echo '<form method="get" action="" style="display:flex; align-items:center; gap:15px; flex-wrap:wrap;">';
		echo '<input type="hidden" name="page" value="snippen-booking-sms-inbox">';

		echo '<div class="snippen-filter-group">';
		echo '<label for="filter-status" style="font-weight:600; margin-right:6px;">' . esc_html__( 'Status:', 'snippen-booking' ) . '</label>';
		echo '<select name="status" id="filter-status" onchange="this.form.submit()">';
		echo '<option value="">' . esc_html__( 'Alle innkommende statuser', 'snippen-booking' ) . '</option>';
		echo '<option value="quarantine" ' . selected( $status, 'quarantine', false ) . '>' . esc_html__( 'Karantene / Ukjent avsender', 'snippen-booking' ) . '</option>';
		echo '<option value="pending_selection" ' . selected( $status, 'pending_selection', false ) . '>' . esc_html__( 'Venter på flervalg fra bruker', 'snippen-booking' ) . '</option>';
		echo '<option value="general_inquiry" ' . selected( $status, 'general_inquiry', false ) . '>' . esc_html__( 'Brukerhenvendelse (uten aktiv booking)', 'snippen-booking' ) . '</option>';
		echo '<option value="received" ' . selected( $status, 'received', false ) . '>' . esc_html__( 'Koblet til reservasjon', 'snippen-booking' ) . '</option>';
		echo '</select>';
		echo '</div>';

		echo '<div class="snippen-filter-group" style="display:flex; gap:6px;">';
		echo '<input type="search" name="s" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Søk på telefon eller tekst...', 'snippen-booking' ) . '" class="regular-text">';
		echo '<button type="submit" class="button">' . esc_html__( 'Søk', 'snippen-booking' ) . '</button>';
		if ( ! empty( $status ) || ! empty( $search ) ) {
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=snippen-booking-sms-inbox' ) ) . '" class="button button-link" style="align-self:center;">' . esc_html__( 'Nullstill', 'snippen-booking' ) . '</a>';
		}
		echo '</div>';

		echo '</form>';
		echo '</div>';
	}

	/**
	 * Render messages list
	 *
	 * @param array $messages Message records.
	 * @param int   $total    Total records matching filter.
	 * @param int   $page     Current page number.
	 * @param int   $limit    Items per page.
	 */
	private function render_list( array $messages, int $total, int $page, int $limit ) {
		global $wpdb;

		$table_bookings = $wpdb->prefix . 'snippen_bookings';

		// Pre-fetch recent active bookings for assignment dropdown
		$active_candidates = $wpdb->get_results(
			"SELECT id, customer_name, customer_phone, booking_date 
			 FROM {$table_bookings} 
			 WHERE deleted_at IS NULL AND status != 'cancelled' 
			 ORDER BY booking_date DESC, id DESC 
			 LIMIT 40"
		);

		echo '<div class="snippen-card" style="background:#fff; border:1px solid #ccd0d4; border-radius:4px; overflow:hidden;">';
		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead>';
		echo '<tr>';
		echo '<th style="width:130px;">' . esc_html__( 'Tidspunkt', 'snippen-booking' ) . '</th>';
		echo '<th style="width:140px;">' . esc_html__( 'Avsender', 'snippen-booking' ) . '</th>';
		echo '<th>' . esc_html__( 'Melding', 'snippen-booking' ) . '</th>';
		echo '<th style="width:160px;">' . esc_html__( 'Status / Regel', 'snippen-booking' ) . '</th>';
		echo '<th style="width:230px;">' . esc_html__( 'Tilknyttet Reservasjon', 'snippen-booking' ) . '</th>';
		echo '</tr>';
		echo '</thead>';
		echo '<tbody>';

		if ( empty( $messages ) ) {
			echo '<tr><td colspan="5" style="text-align:center; padding:30px; color:#64748b;">' . esc_html__( 'Ingen innkommende meldinger funnet for dette filteret.', 'snippen-booking' ) . '</td></tr>';
		} else {
			foreach ( $messages as $msg ) {
				$meta        = ! empty( $msg->metadata ) ? json_decode( $msg->metadata, true ) : array();
				$status_html = $this->format_status_badge( $msg->status, $meta['matched_rule'] ?? '' );

				$time_str      = mysql_to_rfc3339( $msg->created_at );
				$readable_time = get_date_from_gmt( $msg->created_at, 'd.m.Y H:i' );

				echo '<tr>';
				echo '<td><time datetime="' . esc_attr( $time_str ) . '">' . esc_html( $readable_time ) . '</time></td>';
				echo '<td><strong>' . esc_html( $msg->recipient ) . '</strong>';
				if ( ! empty( $msg->user_id ) ) {
					$user = get_userdata( (int) $msg->user_id );
					if ( $user ) {
						echo '<br><span style="font-size:11px; color:#64748b;">' . esc_html( $user->display_name ) . '</span>';
					}
				}
				echo '</td>';
				echo '<td style="font-size:13px; line-height:1.4;">' . nl2br( esc_html( $msg->message ) ) . '</td>';
				echo '<td>' . $status_html . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '<td>';

				if ( ! empty( $msg->booking_id ) ) {
					$booking_url = admin_url( 'admin.php?page=snippen-booking&s=' . (int) $msg->booking_id );
					echo '<a href="' . esc_url( $booking_url ) . '" class="button button-small" style="display:inline-flex; align-items:center; gap:4px;"><span class="dashicons dashicons-calendar-alt" style="font-size:14px; width:14px; height:14px; line-height:14px;"></span> ' . sprintf( esc_html__( 'Booking #%d', 'snippen-booking' ), (int) $msg->booking_id ) . '</a>';
				} else {
					// Assignment form
					echo '<form method="post" action="" style="display:flex; gap:4px; align-items:center; flex-wrap:wrap;">';
					wp_nonce_field( 'snippen_assign_sms_message', 'snippen_inbox_nonce' );
					echo '<input type="hidden" name="snippen_inbox_action" value="assign_to_booking">';
					echo '<input type="hidden" name="message_id" value="' . esc_attr( $msg->id ) . '">';
					echo '<select name="booking_id" style="font-size:11px; max-width:140px;" required>';
					echo '<option value="">' . esc_html__( 'Velg booking...', 'snippen-booking' ) . '</option>';
					foreach ( $active_candidates as $cand ) {
						$opt_label = sprintf( '#%d: %s (%s)', $cand->id, $cand->customer_name ?: $cand->customer_phone, $cand->booking_date );
						echo '<option value="' . esc_attr( $cand->id ) . '">' . esc_html( $opt_label ) . '</option>';
					}
					echo '</select>';
					echo '<button type="submit" class="button button-small" title="' . esc_attr__( 'Koble til valgt reservasjon', 'snippen-booking' ) . '">' . esc_html__( 'Koble', 'snippen-booking' ) . '</button>';
					echo '</form>';
				}

				echo '</td>';
				echo '</tr>';
			}
		}

		echo '</tbody>';
		echo '</table>';

		// Pagination
		$total_pages = ceil( $total / $limit );
		if ( $total_pages > 1 ) {
			echo '<div class="tablenav" style="padding:10px 15px;"><div class="tablenav-pages">';
			echo '<span class="displaying-num">' . sprintf( esc_html__( '%d meldinger', 'snippen-booking' ), $total ) . '</span>';
			for ( $i = 1; $i <= $total_pages; ++$i ) {
				$page_url = add_query_arg( 'paged', $i );
				$class    = ( $i === $page ) ? 'current-page button disabled' : 'button';
				echo '<a href="' . esc_url( $page_url ) . '" class="' . esc_attr( $class ) . '" style="margin:0 2px;">' . (int) $i . '</a>';
			}
			echo '</div></div>';
		}

		echo '</div>';
	}

	/**
	 * Format HTML status badge
	 *
	 * @param string $status Status string.
	 * @param string $rule   Matched rule string.
	 * @return string HTML badge.
	 */
	private function format_status_badge( string $status, string $rule ): string {
		$badge_text = esc_html__( 'Mottatt', 'snippen-booking' );
		$bg_color   = '#e0e7ff';
		$text_color = '#3730a3';

		if ( 'quarantine' === $status ) {
			$badge_text = esc_html__( 'Karantene (Ukjent)', 'snippen-booking' );
			$bg_color   = '#fee2e2';
			$text_color = '#991b1b';
		} elseif ( 'pending_selection' === $status ) {
			$badge_text = esc_html__( 'Venter på flervalg', 'snippen-booking' );
			$bg_color   = '#fef3c7';
			$text_color = '#92400e';
		} elseif ( 'general_inquiry' === $status ) {
			$badge_text = esc_html__( 'Generell henvendelse', 'snippen-booking' );
			$bg_color   = '#e0f2fe';
			$text_color = '#075985';
		} elseif ( 'received' === $status ) {
			$badge_text = esc_html__( 'Koblet til booking', 'snippen-booking' );
			$bg_color   = '#dcfce7';
			$text_color = '#166534';
		}

		$rule_labels = array(
			'active_session'             => __( 'Pågående dialog', 'snippen-booking' ),
			'disambiguation_selection'   => __( 'Svar på flervalg', 'snippen-booking' ),
			'single_active_booking'      => __( 'Entydig aktiv booking', 'snippen-booking' ),
			'multiple_active_bookings'   => __( 'Flere aktive bookinger', 'snippen-booking' ),
			'registered_user_no_booking' => __( 'Bruker uten booking', 'snippen-booking' ),
			'unknown_sender'             => __( 'Ukjent avsender', 'snippen-booking' ),
		);

		$rule_text = $rule_labels[ $rule ] ?? '';

		$html = '<span class="snippen-badge" style="background:' . esc_attr( $bg_color ) . '; color:' . esc_attr( $text_color ) . '; font-weight:600; font-size:11px; padding:2px 8px; border-radius:4px; display:inline-block;">' . esc_html( $badge_text ) . '</span>';
		if ( ! empty( $rule_text ) ) {
			$html .= '<br><span style="font-size:10px; color:#64748b;">' . esc_html( $rule_text ) . '</span>';
		}

		return $html;
	}
}
