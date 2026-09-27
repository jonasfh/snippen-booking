<?php
/**
 * Admin page for managing Inbound SMS Messages and Quarantine
 *
 * @package SnippenBooking\Admin\Pages
 */

namespace SnippenBooking\Admin\Pages;

use SnippenBooking\Service\Notification\MessageLoggerService;
use SnippenBooking\Service\Notification\NotificationManager;
use SnippenBooking\Helper\PhoneHelper;

/**
 * Class SmsInboxPage
 */
class SmsInboxPage {

	/**
	 * Render the page
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Du har ikke tilstrekkelige rettigheter til å åpne denne siden.', 'snippen-booking' ) );
		}

		$this->handle_actions();

		$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
		$connection    = isset( $_GET['connection'] ) ? sanitize_text_field( wp_unslash( $_GET['connection'] ) ) : '';
		$date_filter   = isset( $_GET['date_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['date_filter'] ) ) : '';
		$search        = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$orderby       = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'created_at';
		$order         = ( isset( $_GET['order'] ) && 'asc' === strtolower( (string) $_GET['order'] ) ) ? 'asc' : 'desc';
		$page_num      = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$view_id       = isset( $_GET['view_message'] ) ? absint( $_GET['view_message'] ) : 0;
		$limit         = 30;
		$offset        = ( $page_num - 1 ) * $limit;

		$args = array(
			'status'      => $status_filter,
			'connection'  => $connection,
			'date_filter' => $date_filter,
			'search'      => $search,
			'orderby'     => $orderby,
			'order'       => $order,
			'limit'       => $limit,
			'offset'      => $offset,
		);

		$messages = MessageLoggerService::get_inbound_messages( $args );
		$total    = MessageLoggerService::count_inbound_messages( $args );

		// Status counts for navigation tabs
		$counts = array(
			'all'               => MessageLoggerService::count_inbound_messages( array() ),
			'quarantine'        => MessageLoggerService::count_inbound_messages( array( 'status' => 'quarantine' ) ),
			'pending_selection' => MessageLoggerService::count_inbound_messages( array( 'status' => 'pending_selection' ) ),
			'general_inquiry'   => MessageLoggerService::count_inbound_messages( array( 'status' => 'general_inquiry' ) ),
			'received'          => MessageLoggerService::count_inbound_messages( array( 'status' => 'received' ) ),
		);

		echo '<div class="wrap snippen-booking-admin-wrap">';

		$this->render_header();
		$this->render_status_tabs( $status_filter, $counts );
		$this->render_filters( $status_filter, $connection, $date_filter, $search, $orderby, $order );
		$this->render_list( $messages, $total, $page_num, $limit, $orderby, $order );

		if ( $view_id > 0 ) {
			$this->render_detail_modal( $view_id );
		}

		$this->render_inline_scripts();

		echo '</div>';
	}

	/**
	 * Handle admin actions like manual message assignment, single delete, bulk delete, and SMS replies.
	 */
	private function handle_actions() {
		if ( ! isset( $_POST['snippen_inbox_action'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Uautorisert handling.', 'snippen-booking' ) );
		}

		$action = sanitize_text_field( wp_unslash( $_POST['snippen_inbox_action'] ) );

		if ( 'assign_to_booking' === $action ) {
			check_admin_referer( 'snippen_assign_sms_message', 'snippen_inbox_nonce' );

			$message_id = absint( $_POST['message_id'] ?? 0 );
			$booking_id = absint( $_POST['booking_id'] ?? 0 );

			if ( $message_id > 0 && $booking_id > 0 ) {
				if ( MessageLoggerService::assign_message_to_booking( $message_id, $booking_id ) ) {
					echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Meldingen ble vellykket koblet til reservasjonen.', 'snippen-booking' ) . '</p></div>';
				} else {
					echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Kunne ikke koble meldingen til reservasjonen.', 'snippen-booking' ) . '</p></div>';
				}
			}
		} elseif ( 'delete_single' === $action ) {
			check_admin_referer( 'snippen_delete_sms_message', 'snippen_inbox_nonce' );

			$message_id = absint( $_POST['message_id'] ?? 0 );
			if ( $message_id > 0 ) {
				if ( MessageLoggerService::delete_message( $message_id ) ) {
					echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Meldingen ble slettet.', 'snippen-booking' ) . '</p></div>';
				} else {
					echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Kunne ikke slette meldingen.', 'snippen-booking' ) . '</p></div>';
				}
			}
		} elseif ( 'bulk_action' === $action ) {
			check_admin_referer( 'snippen_bulk_sms_action', 'snippen_inbox_nonce' );

			$bulk_op = sanitize_text_field( wp_unslash( $_POST['bulk_operation'] ?? '' ) );
			$ids     = isset( $_POST['message_ids'] ) && is_array( $_POST['message_ids'] ) ? array_map( 'absint', $_POST['message_ids'] ) : array();

			if ( 'delete' === $bulk_op && ! empty( $ids ) ) {
				$deleted = MessageLoggerService::delete_messages( $ids );
				if ( $deleted > 0 ) {
					/* translators: %d: number of deleted SMS messages */
					$notice = sprintf( _n( '%d SMS-melding ble slettet.', '%d SMS-meldinger ble slettet.', $deleted, 'snippen-booking' ), $deleted );
					echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $notice ) . '</p></div>';
				} else {
					echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Ingen meldinger ble slettet.', 'snippen-booking' ) . '</p></div>';
				}
			}
		} elseif ( 'send_reply' === $action ) {
			check_admin_referer( 'snippen_reply_sms_message', 'snippen_inbox_nonce' );

			$message_id = absint( $_POST['message_id'] ?? 0 );
			$recipient  = sanitize_text_field( wp_unslash( $_POST['recipient'] ?? '' ) );
			$reply_body = sanitize_textarea_field( wp_unslash( $_POST['reply_message'] ?? '' ) );
			$booking_id = ! empty( $_POST['booking_id'] ) ? absint( $_POST['booking_id'] ) : null;
			$user_id    = ! empty( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : null;

			if ( empty( $recipient ) ) {
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Ugyldig eller manglende telefonnummer for avsender.', 'snippen-booking' ) . '</p></div>';
				return;
			}

			if ( '' === trim( $reply_body ) ) {
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'SMS-svaret kan ikke være tomt.', 'snippen-booking' ) . '</p></div>';
				return;
			}

			$sms_provider = ( new NotificationManager() )->get_active_sms_provider();
			if ( ! $sms_provider || ! $sms_provider->is_configured() ) {
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Ingen SMS-leverandør er konfigurert. Gå til innstillinger for å sette opp aktiv leverandør.', 'snippen-booking' ) . '</p></div>';
				return;
			}

			$sent           = $sms_provider->send_sms( $recipient, $reply_body );
			$initial_status = ( 'snippen_sms_service' === $sms_provider->get_id() ) ? 'queued' : ( $sent ? 'sent' : 'failed' );

			MessageLoggerService::log_message(
				$booking_id,
				$user_id,
				'sms',
				$recipient,
				null,
				$reply_body,
				'admin_sms_reply',
				$initial_status,
				array(
					'direction'     => 'outbound',
					'in_reply_to'   => $message_id,
					'sender'        => get_option( 'snippen_sms_service_sender', 'Snippen' ),
					'admin_user_id' => get_current_user_id(),
				)
			);

			if ( $sent ) {
				$msg = ( 'queued' === $initial_status )
					? sprintf( esc_html__( 'SMS-svar til %s ble lagt i utboksen for utsending via gateway.', 'snippen-booking' ), esc_html( $recipient ) )
					: sprintf( esc_html__( 'SMS-svar ble sendt til %s.', 'snippen-booking' ), esc_html( $recipient ) );
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
			} else {
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Utsending av SMS feilet. Sjekk modemet eller tilkoblingen til SMS-leverandøren.', 'snippen-booking' ) . '</p></div>';
			}
		}
	}

	/**
	 * Render header
	 */
	private function render_header() {
		echo '<div class="snippen-admin-header" style="margin-bottom:20px;">';
		echo '<div>';
		echo '<h1><span class="dashicons dashicons-email-alt" style="font-size:28px; width:28px; height:28px; vertical-align:middle; margin-right:8px;"></span>' . esc_html__( 'SMS Innboks & Karantene', 'snippen-booking' ) . '</h1>';
		echo '<p class="description" style="margin-top:4px;">' . esc_html__( 'Oversikt over innkommende SMS-meldinger, automatisk reservasjonskobling, detaljvisning, sletting og direkte oppfølging.', 'snippen-booking' ) . '</p>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render quick filter status tabs
	 *
	 * @param string $current_status Current active status filter.
	 * @param array  $counts         Associative array of counts.
	 */
	private function render_status_tabs( string $current_status, array $counts ) {
		$tabs = array(
			''                  => array(
				'label' => __( 'Alle meldinger', 'snippen-booking' ),
				'count' => $counts['all'],
			),
			'quarantine'        => array(
				'label' => __( 'Karantene / Ukjent', 'snippen-booking' ),
				'count' => $counts['quarantine'],
			),
			'pending_selection' => array(
				'label' => __( 'Venter på valg', 'snippen-booking' ),
				'count' => $counts['pending_selection'],
			),
			'general_inquiry'   => array(
				'label' => __( 'Uten booking', 'snippen-booking' ),
				'count' => $counts['general_inquiry'],
			),
			'received'          => array(
				'label' => __( 'Koblet til reservasjon', 'snippen-booking' ),
				'count' => $counts['received'],
			),
		);

		echo '<ul class="subsubsub" style="margin-bottom:15px; float:none;">';
		$i     = 0;
		$total = count( $tabs );
		foreach ( $tabs as $status_key => $tab ) {
			++$i;
			$url   = add_query_arg(
				array(
					'page'   => 'snippen-booking-sms-inbox',
					'status' => $status_key,
					'paged'  => 1,
				),
				admin_url( 'admin.php' )
			);
			$class = ( $current_status === $status_key ) ? 'current' : '';
			echo '<li>';
			echo '<a href="' . esc_url( $url ) . '" class="' . esc_attr( $class ) . '">';
			echo esc_html( $tab['label'] ) . ' <span class="count">(' . (int) $tab['count'] . ')</span>';
			echo '</a>';
			if ( $i < $total ) {
				echo ' | ';
			}
			echo '</li>';
		}
		echo '</ul><div class="clear"></div>';
	}

	/**
	 * Render filter and search bar
	 *
	 * @param string $status      Current status filter.
	 * @param string $connection  Current connection filter.
	 * @param string $date_filter Current date filter.
	 * @param string $search      Current search query.
	 * @param string $orderby     Current orderby column.
	 * @param string $order       Current order direction.
	 */
	private function render_filters( string $status, string $connection, string $date_filter, string $search, string $orderby, string $order ) {
		echo '<div class="snippen-card" style="background:#fff; padding:16px 20px; border:1px solid #ccd0d4; border-radius:6px; margin-bottom:20px;">';
		echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '" style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">';
		echo '<input type="hidden" name="page" value="snippen-booking-sms-inbox">';
		echo '<input type="hidden" name="orderby" value="' . esc_attr( $orderby ) . '">';
		echo '<input type="hidden" name="order" value="' . esc_attr( $order ) . '">';

		// Status filter
		echo '<div class="snippen-filter-group">';
		echo '<label for="filter-status" style="font-weight:600; margin-right:6px; font-size:13px;">' . esc_html__( 'Status:', 'snippen-booking' ) . '</label>';
		echo '<select name="status" id="filter-status" onchange="this.form.submit()">';
		echo '<option value="">' . esc_html__( 'Alle statuser', 'snippen-booking' ) . '</option>';
		echo '<option value="quarantine" ' . selected( $status, 'quarantine', false ) . '>' . esc_html__( 'Karantene / Ukjent avsender', 'snippen-booking' ) . '</option>';
		echo '<option value="pending_selection" ' . selected( $status, 'pending_selection', false ) . '>' . esc_html__( 'Venter på flervalg', 'snippen-booking' ) . '</option>';
		echo '<option value="general_inquiry" ' . selected( $status, 'general_inquiry', false ) . '>' . esc_html__( 'Brukerhenvendelse (uten aktiv booking)', 'snippen-booking' ) . '</option>';
		echo '<option value="received" ' . selected( $status, 'received', false ) . '>' . esc_html__( 'Koblet til reservasjon', 'snippen-booking' ) . '</option>';
		echo '</select>';
		echo '</div>';

		// Connection filter
		echo '<div class="snippen-filter-group">';
		echo '<label for="filter-connection" style="font-weight:600; margin-right:6px; font-size:13px;">' . esc_html__( 'Kobling:', 'snippen-booking' ) . '</label>';
		echo '<select name="connection" id="filter-connection" onchange="this.form.submit()">';
		echo '<option value="">' . esc_html__( 'Alle koblinger', 'snippen-booking' ) . '</option>';
		echo '<option value="linked" ' . selected( $connection, 'linked', false ) . '>' . esc_html__( 'Kun koblet til booking', 'snippen-booking' ) . '</option>';
		echo '<option value="unlinked" ' . selected( $connection, 'unlinked', false ) . '>' . esc_html__( 'Kun ukoblede meldinger', 'snippen-booking' ) . '</option>';
		echo '</select>';
		echo '</div>';

		// Date filter
		echo '<div class="snippen-filter-group">';
		echo '<label for="filter-date" style="font-weight:600; margin-right:6px; font-size:13px;">' . esc_html__( 'Periode:', 'snippen-booking' ) . '</label>';
		echo '<select name="date_filter" id="filter-date" onchange="this.form.submit()">';
		echo '<option value="">' . esc_html__( 'Hele perioden', 'snippen-booking' ) . '</option>';
		echo '<option value="today" ' . selected( $date_filter, 'today', false ) . '>' . esc_html__( 'I dag', 'snippen-booking' ) . '</option>';
		echo '<option value="7days" ' . selected( $date_filter, '7days', false ) . '>' . esc_html__( 'Siste 7 dager', 'snippen-booking' ) . '</option>';
		echo '<option value="30days" ' . selected( $date_filter, '30days', false ) . '>' . esc_html__( 'Siste 30 dager', 'snippen-booking' ) . '</option>';
		echo '</select>';
		echo '</div>';

		// Search input
		echo '<div class="snippen-filter-group" style="display:flex; gap:6px; flex-grow:1; max-width:420px;">';
		echo '<input type="search" name="s" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Søk telefon, tekst, navn, booking #...', 'snippen-booking' ) . '" class="regular-text" style="width:100%;">';
		echo '<button type="submit" class="button button-primary">' . esc_html__( 'Søk', 'snippen-booking' ) . '</button>';
		if ( ! empty( $status ) || ! empty( $connection ) || ! empty( $date_filter ) || ! empty( $search ) ) {
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=snippen-booking-sms-inbox' ) ) . '" class="button button-link" style="align-self:center; text-decoration:none;">' . esc_html__( 'Nullstill', 'snippen-booking' ) . '</a>';
		}
		echo '</div>';

		echo '</form>';
		echo '</div>';
	}

	/**
	 * Render messages list with bulk actions, sorting headers and assignment
	 *
	 * @param array  $messages Message records.
	 * @param int    $total    Total records matching filter.
	 * @param int    $page     Current page number.
	 * @param int    $limit    Items per page.
	 * @param string $orderby  Current orderby.
	 * @param string $order    Current order.
	 */
	private function render_list( array $messages, int $total, int $page, int $limit, string $orderby, string $order ) {
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

		echo '<form method="post" id="snippen-sms-inbox-form" action="">';
		wp_nonce_field( 'snippen_bulk_sms_action', 'snippen_inbox_nonce' );
		echo '<input type="hidden" name="snippen_inbox_action" value="bulk_action">';

		// Bulk actions top bar
		echo '<div class="tablenav top" style="margin-bottom:10px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">';
		echo '<div class="alignleft actions bulkactions" style="display:flex; gap:6px; align-items:center;">';
		echo '<select name="bulk_operation" id="bulk-action-selector-top">';
		echo '<option value="">' . esc_html__( 'Massehandlinger', 'snippen-booking' ) . '</option>';
		echo '<option value="delete">' . esc_html__( 'Slett valgte', 'snippen-booking' ) . '</option>';
		echo '</select>';
		echo '<button type="submit" id="doaction" class="button action" onclick="return confirmBulkAction();">' . esc_html__( 'Bruk', 'snippen-booking' ) . '</button>';
		echo '</div>';

		echo '<div class="tablenav-pages">';
		echo '<span class="displaying-num">' . sprintf( esc_html__( '%d meldinger totalt', 'snippen-booking' ), $total ) . '</span>';
		echo '</div>';
		echo '</div>';

		echo '<div class="snippen-card" style="background:#fff; border:1px solid #ccd0d4; border-radius:6px; overflow:hidden; padding:0; margin-bottom:20px;">';
		echo '<table class="wp-list-table widefat fixed striped" style="border:none;">';
		echo '<thead>';
		echo '<tr>';
		echo '<td id="cb" class="manage-column column-cb check-column" style="width:36px; padding:10px 0 10px 10px;"><input type="checkbox" id="snippen-select-all"></td>';
		echo $this->render_sortable_header( 'created_at', __( 'Tidspunkt', 'snippen-booking' ), $orderby, $order, '130px' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->render_sortable_header( 'recipient', __( 'Avsender', 'snippen-booking' ), $orderby, $order, '140px' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<th scope="col">' . esc_html__( 'Melding', 'snippen-booking' ) . '</th>';
		echo $this->render_sortable_header( 'status', __( 'Status / Regel', 'snippen-booking' ), $orderby, $order, '160px' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->render_sortable_header( 'booking_id', __( 'Tilknyttet Reservasjon', 'snippen-booking' ), $orderby, $order, '220px' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<th scope="col" style="width:130px; text-align:right;">' . esc_html__( 'Handlinger', 'snippen-booking' ) . '</th>';
		echo '</tr>';
		echo '</thead>';
		echo '<tbody>';

		if ( empty( $messages ) ) {
			echo '<tr><td colspan="7" style="text-align:center; padding:36px; color:#64748b; font-size:14px;">' . esc_html__( 'Ingen innkommende meldinger funnet for dette utvalget.', 'snippen-booking' ) . '</td></tr>';
		} else {
			foreach ( $messages as $msg ) {
				$meta        = ! empty( $msg->metadata ) ? json_decode( $msg->metadata, true ) : array();
				$status_html = $this->format_status_badge( $msg->status, $meta['matched_rule'] ?? '' );

				$time_str      = mysql_to_rfc3339( $msg->created_at );
				$readable_time = get_date_from_gmt( $msg->created_at, 'd.m.Y H:i' );
				$detail_url    = add_query_arg( 'view_message', $msg->id );

				echo '<tr>';
				echo '<th scope="row" class="check-column" style="padding:10px 0 10px 10px;"><input type="checkbox" name="message_ids[]" value="' . esc_attr( $msg->id ) . '" class="snippen-message-item"></th>';
				echo '<td><time datetime="' . esc_attr( $time_str ) . '">' . esc_html( $readable_time ) . '</time></td>';

				echo '<td>';
				echo '<strong>' . esc_html( $msg->recipient ) . '</strong>';
				if ( ! empty( $msg->user_id ) ) {
					$user = get_userdata( (int) $msg->user_id );
					if ( $user ) {
						$user_link = admin_url( 'user-edit.php?user_id=' . (int) $user->ID );
						echo '<br><a href="' . esc_url( $user_link ) . '" style="font-size:11px; color:#2563eb; text-decoration:none;" title="' . esc_attr__( 'Åpne brukerprofil', 'snippen-booking' ) . '">' . esc_html( $user->display_name ) . '</a>';
					}
				}
				echo '</td>';

				// Message snippet with detail view link
				$raw_text = (string) $msg->message;
				$snippet  = mb_strlen( $raw_text ) > 110 ? mb_substr( $raw_text, 0, 107 ) . '...' : $raw_text;
				echo '<td style="font-size:13px; line-height:1.45;">';
				echo '<span>' . nl2br( esc_html( $snippet ) ) . '</span>';
				echo '</td>';

				echo '<td>' . $status_html . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

				// Booking connection column
				echo '<td>';
				if ( ! empty( $msg->booking_id ) ) {
					$booking_url = admin_url( 'admin.php?page=snippen-booking&s=' . (int) $msg->booking_id );
					echo '<a href="' . esc_url( $booking_url ) . '" class="button button-small" style="display:inline-flex; align-items:center; gap:4px;"><span class="dashicons dashicons-calendar-alt" style="font-size:14px; width:14px; height:14px; line-height:14px;"></span> ' . sprintf( esc_html__( 'Booking #%d', 'snippen-booking' ), (int) $msg->booking_id ) . '</a>';
				} else {
					echo '<div style="display:flex; gap:4px; align-items:center;">';
					echo '<select class="snippen-quick-assign-select" data-message-id="' . esc_attr( $msg->id ) . '" style="font-size:11px; max-width:130px;">';
					echo '<option value="">' . esc_html__( 'Koble til...', 'snippen-booking' ) . '</option>';
					foreach ( $active_candidates as $cand ) {
						$opt_label = sprintf( '#%d: %s (%s)', $cand->id, $cand->customer_name ?: $cand->customer_phone, $cand->booking_date );
						echo '<option value="' . esc_attr( $cand->id ) . '">' . esc_html( $opt_label ) . '</option>';
					}
					echo '</select>';
					echo '<button type="button" class="button button-small snippen-quick-assign-btn" data-message-id="' . esc_attr( $msg->id ) . '">' . esc_html__( 'Koble', 'snippen-booking' ) . '</button>';
					echo '</div>';
				}
				echo '</td>';

				// Action buttons
				echo '<td style="text-align:right; white-space:nowrap;">';
				echo '<a href="' . esc_url( $detail_url ) . '" class="button button-small button-primary" style="margin-right:4px;" title="' . esc_attr__( 'Se detaljer og svar', 'snippen-booking' ) . '">' . esc_html__( 'Detaljer', 'snippen-booking' ) . '</a>';
				echo '<button type="button" class="button button-small snippen-delete-single-btn" data-message-id="' . esc_attr( $msg->id ) . '" style="color:#b91c1c;" title="' . esc_attr__( 'Slett melding', 'snippen-booking' ) . '"><span class="dashicons dashicons-trash" style="font-size:14px; width:14px; height:14px; line-height:14px; vertical-align:text-bottom;"></span></button>';
				echo '</td>';

				echo '</tr>';
			}
		}

		echo '</tbody>';
		echo '</table>';
		echo '</div>';
		echo '</form>';

		// Hidden standalone form for single actions (delete & quick assign)
		echo '<form method="post" id="snippen-single-action-form" style="display:none;" action="">';
		wp_nonce_field( 'snippen_delete_sms_message', 'snippen_inbox_nonce' );
		echo '<input type="hidden" name="snippen_inbox_action" id="snippen-single-action-type" value="delete_single">';
		echo '<input type="hidden" name="message_id" id="snippen-single-action-id" value="0">';
		echo '<input type="hidden" name="booking_id" id="snippen-single-action-booking-id" value="0">';
		echo '</form>';

		// Pagination bar
		$total_pages = ceil( $total / $limit );
		if ( $total_pages > 1 ) {
			echo '<div class="tablenav" style="padding:10px 0;"><div class="tablenav-pages">';
			echo '<span class="displaying-num">' . sprintf( esc_html__( '%d meldinger', 'snippen-booking' ), $total ) . '</span>';
			for ( $i = 1; $i <= $total_pages; ++$i ) {
				$page_url = add_query_arg( 'paged', $i );
				$class    = ( $i === $page ) ? 'current-page button disabled' : 'button';
				echo '<a href="' . esc_url( $page_url ) . '" class="' . esc_attr( $class ) . '" style="margin:0 2px;">' . (int) $i . '</a>';
			}
			echo '</div></div>';
		}
	}

	/**
	 * Render sortable table header column
	 *
	 * @param string $column_key Key of the column.
	 * @param string $title      Title of the column.
	 * @param string $current_ob Current orderby.
	 * @param string $current_or Current order direction.
	 * @param string $width      Optional CSS width.
	 * @return string HTML th tag.
	 */
	private function render_sortable_header( string $column_key, string $title, string $current_ob, string $current_or, string $width = '' ): string {
		$is_sorted = ( $current_ob === $column_key );
		$new_order = ( $is_sorted && 'desc' === $current_or ) ? 'asc' : 'desc';

		$url = add_query_arg(
			array(
				'orderby' => $column_key,
				'order'   => $new_order,
				'paged'   => 1,
			)
		);

		$icon = 'dashicons-sort';
		if ( $is_sorted ) {
			$icon = ( 'asc' === $current_or ) ? 'dashicons-arrow-up-alt2' : 'dashicons-arrow-down-alt2';
		}

		$style = ! empty( $width ) ? 'width:' . esc_attr( $width ) . ';' : '';

		$html  = '<th scope="col" style="' . $style . '">';
		$html .= '<a href="' . esc_url( $url ) . '" style="display:inline-flex; align-items:center; gap:4px; text-decoration:none; color:inherit; font-weight:600;">';
		$html .= esc_html( $title );
		$html .= '<span class="dashicons ' . esc_attr( $icon ) . '" style="font-size:14px; width:14px; height:14px; line-height:14px;"></span>';
		$html .= '</a>';
		$html .= '</th>';

		return $html;
	}

	/**
	 * Render Detail Modal with thread and reply form
	 *
	 * @param int $message_id Message ID to view.
	 */
	private function render_detail_modal( int $message_id ) {
		$message = MessageLoggerService::get_message( $message_id );
		if ( ! $message ) {
			return;
		}

		$close_url     = remove_query_arg( 'view_message' );
		$meta          = ! empty( $message->metadata ) ? json_decode( $message->metadata, true ) : array();
		$readable_time = get_date_from_gmt( $message->created_at, 'd.m.Y H:i:s' );
		$thread        = MessageLoggerService::get_conversation_thread( $message->recipient, $message->booking_id ? (int) $message->booking_id : null, 40 );

		// Provider info
		$provider      = ( new NotificationManager() )->get_active_sms_provider();
		$provider_name = $provider ? $provider->get_name() : __( 'Ingen SMS-leverandør', 'snippen-booking' );

		// User info
		$user_details = null;
		if ( ! empty( $message->user_id ) ) {
			$user_details = get_userdata( (int) $message->user_id );
		}

		echo '<div id="snippen-sms-detail-modal" class="snippen-modal-backdrop">';
		echo '<div class="snippen-modal-content" style="max-width:680px; width:100%;">';

		// Header
		echo '<div class="snippen-modal-header">';
		echo '<div style="display:flex; align-items:center; gap:10px;">';
		echo '<h2 class="snippen-modal-title">' . sprintf( esc_html__( 'SMS-detaljer #%d', 'snippen-booking' ), (int) $message->id ) . '</h2>';
		echo $this->format_status_badge( $message->status, $meta['matched_rule'] ?? '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';
		echo '<a href="' . esc_url( $close_url ) . '" class="snippen-modal-close" style="text-decoration:none;" aria-label="' . esc_attr__( 'Lukk', 'snippen-booking' ) . '">&times;</a>';
		echo '</div>';

		// Body
		echo '<div class="snippen-modal-body" style="padding:20px;">';

		// Info cards grid
		echo '<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:12px; margin-bottom:16px;">';

		// Sender card
		echo '<div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:12px;">';
		echo '<div style="font-size:11px; font-weight:600; color:#64748b; text-transform:uppercase; margin-bottom:4px;">' . esc_html__( 'Avsender', 'snippen-booking' ) . '</div>';
		echo '<div style="font-size:14px; font-weight:700; color:#0f172a;">' . esc_html( $message->recipient ) . '</div>';
		if ( $user_details ) {
			echo '<div style="font-size:12px; color:#2563eb; margin-top:2px;">' . esc_html( $user_details->display_name ) . ' (' . esc_html( $user_details->user_email ) . ')</div>';
		} else {
			echo '<div style="font-size:12px; color:#94a3b8; margin-top:2px;">' . esc_html__( 'Ukjent avsender / ikke registrert bruker', 'snippen-booking' ) . '</div>';
		}
		echo '</div>';

		// Booking card
		echo '<div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:12px;">';
		echo '<div style="font-size:11px; font-weight:600; color:#64748b; text-transform:uppercase; margin-bottom:4px;">' . esc_html__( 'Tilknyttet Reservasjon', 'snippen-booking' ) . '</div>';
		if ( ! empty( $message->booking_id ) ) {
			$b_url = admin_url( 'admin.php?page=snippen-booking&s=' . (int) $message->booking_id );
			echo '<div style="font-size:14px; font-weight:700;"><a href="' . esc_url( $b_url ) . '" style="color:#2563eb; text-decoration:none;">' . sprintf( esc_html__( 'Booking #%d', 'snippen-booking' ), (int) $message->booking_id ) . ' &rarr;</a></div>';
		} else {
			echo '<div style="font-size:13px; color:#e11d48; font-weight:600;">' . esc_html__( 'Ikke tilknyttet booking', 'snippen-booking' ) . '</div>';
		}
		echo '<div style="font-size:11px; color:#64748b; margin-top:2px;">' . sprintf( esc_html__( 'Mottatt: %s', 'snippen-booking' ), esc_html( $readable_time ) ) . '</div>';
		echo '</div>';

		echo '</div>';

		// Technical metadata drawer if present
		if ( ! empty( $meta ) ) {
			echo '<details style="margin-bottom:16px; background:#f1f5f9; border-radius:6px; padding:8px 12px; font-size:12px;">';
			echo '<summary style="cursor:pointer; font-weight:600; color:#475569;">' . esc_html__( 'Vis tekniske metadata / ruteregler', 'snippen-booking' ) . '</summary>';
			echo '<table style="width:100%; margin-top:8px; border-collapse:collapse;">';
			foreach ( $meta as $k => $v ) {
				$val_str = is_array( $v ) ? wp_json_encode( $v ) : (string) $v;
				echo '<tr style="border-top:1px solid #e2e8f0;"><td style="padding:4px 0; font-weight:600; color:#334155; width:160px;">' . esc_html( $k ) . ':</td><td style="padding:4px 0; color:#475569;">' . esc_html( $val_str ) . '</td></tr>';
			}
			echo '</table>';
			echo '</details>';
		}

		// Conversation thread section
		echo '<div style="margin-bottom:20px;">';
		echo '<h3 style="font-size:14px; font-weight:700; color:#1e293b; margin:0 0 10px 0; display:flex; align-items:center; gap:6px;">';
		echo '<span class="dashicons dashicons-format-chat" style="font-size:18px; width:18px; height:18px;"></span> ' . esc_html__( 'Samtalelogg for dette telefonnummeret', 'snippen-booking' );
		echo '</h3>';

		echo '<div class="snippen-conversation-thread" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:16px; max-height:260px; overflow-y:auto; display:flex; flex-direction:column; gap:12px;">';

		if ( empty( $thread ) ) {
			echo '<div style="text-align:center; color:#94a3b8; font-size:13px;">' . esc_html__( 'Ingen tidligere meldinger i denne samtalen.', 'snippen-booking' ) . '</div>';
		} else {
			foreach ( $thread as $item ) {
				$is_inbound    = ( 'inbound_sms' === $item->event_type || 'received' === $item->status || 'quarantine' === $item->status );
				$is_current    = ( (int) $item->id === (int) $message->id );
				$item_time     = get_date_from_gmt( $item->created_at, 'd.m H:i' );
				$bubble_bg     = $is_inbound ? '#ffffff' : '#eff6ff';
				$bubble_border = $is_current ? '#2563eb' : ( $is_inbound ? '#cbd5e1' : '#bfdbfe' );
				$align_self    = $is_inbound ? 'flex-start' : 'flex-end';
				$label         = $is_inbound ? __( 'Innkommende', 'snippen-booking' ) : __( 'Snippen Booking (Utgående)', 'snippen-booking' );

				echo '<div style="align-self:' . esc_attr( $align_self ) . '; max-width:85%; background:' . esc_attr( $bubble_bg ) . '; border:1px solid ' . esc_attr( $bubble_border ) . '; border-radius:8px; padding:10px 12px; box-shadow:0 1px 2px rgba(0,0,0,0.05);">';
				echo '<div style="display:flex; justify-content:space-between; gap:10px; font-size:11px; color:#64748b; margin-bottom:4px;">';
				echo '<strong>' . esc_html( $label ) . '</strong>';
				echo '<span>' . esc_html( $item_time );
				if ( $is_current ) {
					echo ' &bull; <strong style="color:#2563eb;">' . esc_html__( 'Denne meldingen', 'snippen-booking' ) . '</strong>';
				}
				echo '</span>';
				echo '</div>';
				echo '<div style="font-size:13px; line-height:1.4; color:#1e293b;">' . nl2br( esc_html( $item->message ) ) . '</div>';
				echo '</div>';
			}
		}

		echo '</div>';
		echo '</div>';

		// Reply form section
		echo '<div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:8px; padding:16px;">';
		echo '<h3 style="font-size:14px; font-weight:700; color:#1e293b; margin:0 0 10px 0; display:flex; align-items:center; gap:6px;">';
		echo '<span class="dashicons dashicons-undo" style="font-size:16px; width:16px; height:16px;"></span> ' . sprintf( esc_html__( 'Svar direkte til %s', 'snippen-booking' ), esc_html( $message->recipient ) );
		echo '</h3>';

		echo '<form method="post" action="">';
		wp_nonce_field( 'snippen_reply_sms_message', 'snippen_inbox_nonce' );
		echo '<input type="hidden" name="snippen_inbox_action" value="send_reply">';
		echo '<input type="hidden" name="message_id" value="' . esc_attr( $message->id ) . '">';
		echo '<input type="hidden" name="recipient" value="' . esc_attr( $message->recipient ) . '">';
		echo '<input type="hidden" name="booking_id" value="' . esc_attr( $message->booking_id ?? '' ) . '">';
		echo '<input type="hidden" name="user_id" value="' . esc_attr( $message->user_id ?? '' ) . '">';

		echo '<div style="margin-bottom:8px;">';
		echo '<textarea name="reply_message" id="snippen-reply-text" rows="3" required placeholder="' . esc_attr__( 'Skriv et SMS-svar til gjesten her...', 'snippen-booking' ) . '" style="width:100%; border-radius:6px; border:1px solid #cbd5e1; padding:10px; font-size:13px; font-family:inherit;"></textarea>';
		echo '</div>';

		echo '<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">';
		echo '<div style="font-size:12px; color:#64748b;">';
		echo '<span id="snippen-reply-counter">0</span> / 160 ' . esc_html__( 'tegn', 'snippen-booking' ) . ' (<span id="snippen-reply-parts">1</span> SMS) &bull; ';
		echo '<span style="color:#0f172a;">' . sprintf( esc_html__( 'Leverandør: %s', 'snippen-booking' ), esc_html( $provider_name ) ) . '</span>';
		echo '</div>';

		echo '<button type="submit" class="button button-primary" style="display:inline-flex; align-items:center; gap:6px;">';
		echo '<span class="dashicons dashicons-email-alt" style="font-size:16px; width:16px; height:16px; line-height:16px;"></span>';
		echo esc_html__( 'Send SMS-svar', 'snippen-booking' );
		echo '</button>';
		echo '</div>';

		echo '</form>';
		echo '</div>';

		echo '</div>'; // End modal body

		// Footer
		echo '<div class="snippen-modal-footer">';
		echo '<a href="' . esc_url( $close_url ) . '" class="button button-secondary">' . esc_html__( 'Lukk', 'snippen-booking' ) . '</a>';
		echo '</div>';

		echo '</div>'; // End modal content
		echo '</div>'; // End modal backdrop
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

	/**
	 * Output inline Javascript for select-all, character counting, and delete confirmations.
	 */
	private function render_inline_scripts() {
		?>
		<script type="text/javascript">
		function confirmBulkAction() {
			var bulkOp = document.getElementById('bulk-action-selector-top').value;
			if (!bulkOp) {
				alert('<?php echo esc_js( __( 'Vennligst velg en massehandling.', 'snippen-booking' ) ); ?>');
				return false;
			}
			var checkedBoxes = document.querySelectorAll('.snippen-message-item:checked');
			if (checkedBoxes.length === 0) {
				alert('<?php echo esc_js( __( 'Ingen meldinger er valgt.', 'snippen-booking' ) ); ?>');
				return false;
			}
			if (bulkOp === 'delete') {
				return confirm('<?php echo esc_js( __( 'Er du sikker på at du vil slette de valgte meldingene? Handlingen kan ikke angres.', 'snippen-booking' ) ); ?>');
			}
			return true;
		}

		document.addEventListener('DOMContentLoaded', function() {
			// Select all checkboxes
			var selectAll = document.getElementById('snippen-select-all');
			if (selectAll) {
				selectAll.addEventListener('change', function() {
					var items = document.querySelectorAll('.snippen-message-item');
					for (var i = 0; i < items.length; i++) {
						items[i].checked = selectAll.checked;
					}
				});
			}

			// Single delete buttons
			var deleteBtns = document.querySelectorAll('.snippen-delete-single-btn');
			deleteBtns.forEach(function(btn) {
				btn.addEventListener('click', function(e) {
					e.preventDefault();
					var id = this.getAttribute('data-message-id');
					if (confirm('<?php echo esc_js( __( 'Er du sikker på at du vil slette denne SMS-meldingen?', 'snippen-booking' ) ); ?>')) {
						var form = document.getElementById('snippen-single-action-form');
						document.getElementById('snippen-single-action-type').value = 'delete_single';
						document.getElementById('snippen-single-action-id').value = id;
						form.submit();
					}
				});
			});

			// Quick assign buttons
			var assignBtns = document.querySelectorAll('.snippen-quick-assign-btn');
			assignBtns.forEach(function(btn) {
				btn.addEventListener('click', function(e) {
					e.preventDefault();
					var id = this.getAttribute('data-message-id');
					var select = document.querySelector('.snippen-quick-assign-select[data-message-id="' + id + '"]');
					if (!select || !select.value) {
						alert('<?php echo esc_js( __( 'Vennligst velg en reservasjon først.', 'snippen-booking' ) ); ?>');
						return;
					}
					var form = document.getElementById('snippen-single-action-form');
					document.getElementById('snippen-single-action-type').value = 'assign_to_booking';
					document.getElementById('snippen-single-action-id').value = id;
					document.getElementById('snippen-single-action-booking-id').value = select.value;
					form.submit();
				});
			});

			// SMS Reply live character counter
			var replyText = document.getElementById('snippen-reply-text');
			var charCounter = document.getElementById('snippen-reply-counter');
			var partCounter = document.getElementById('snippen-reply-parts');
			if (replyText && charCounter && partCounter) {
				replyText.addEventListener('input', function() {
					var len = this.value.length;
					charCounter.textContent = len;
					var parts = 1;
					if (len > 160) {
						parts = Math.ceil(len / 153);
					}
					partCounter.textContent = parts;
				});
			}

			// Modal close on escape key
			var modal = document.getElementById('snippen-sms-detail-modal');
			if (modal) {
				document.addEventListener('keydown', function(e) {
					if (e.key === 'Escape') {
						var closeBtn = modal.querySelector('.snippen-modal-close');
						if (closeBtn) {
							closeBtn.click();
						}
					}
				});
				modal.addEventListener('click', function(e) {
					if (e.target === modal) {
						var closeBtn = modal.querySelector('.snippen-modal-close');
						if (closeBtn) {
							closeBtn.click();
						}
					}
				});
			}
		});
		</script>
		<?php
	}
}
