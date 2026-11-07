<?php
/**
 * Admin page for managing Door Codes and Doorman Access Periods
 *
 * @package SnippenBooking\Admin\Pages
 */

namespace SnippenBooking\Admin\Pages;

use SnippenBooking\Helper\Capabilities;
use SnippenBooking\Api\DoorLockApi;
use SnippenBooking\Service\DoorCodeService;
use SnippenBooking\Service\Notification\MessageLoggerService;

/**
 * Class DoorCodesPage
 */
class DoorCodesPage {

	/**
	 * Render the page
	 */
	public function render() {
		if ( ! current_user_can( Capabilities::MANAGE_BOOKINGS ) ) {
			wp_die( esc_html__( 'Du har ikke tilgang til denne siden.', 'snippen-booking' ) );
		}

		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'manage';
		$manage_url = admin_url( 'admin.php?page=snippen-booking-door-codes&tab=manage' );
		$status_url = admin_url( 'admin.php?page=snippen-booking-door-codes&tab=status' );

		?>
		<div class="wrap snippen-door-codes-admin">
			<h1 class="wp-heading-inline">
				<?php esc_html_e( 'Dørkoder & Adgangskontroll', 'snippen-booking' ); ?>
			</h1>
			<p class="description" style="margin-top:6px; font-size:14px; max-width:900px;">
				<?php esc_html_e( 'Her administrerer du dørkoder for oppkommende reservasjoner og overvåker synkroniseringsstatus mot Yale Doorman-dørlåsene.', 'snippen-booking' ); ?>
			</p>

			<h2 class="nav-tab-wrapper" style="margin-top:15px; margin-bottom:20px;">
				<a href="<?php echo esc_url( $manage_url ); ?>" class="nav-tab <?php echo 'status' !== $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Administrer dørkoder', 'snippen-booking' ); ?>
				</a>
				<a href="<?php echo esc_url( $status_url ); ?>" class="nav-tab <?php echo 'status' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Status i dørene & Doorman', 'snippen-booking' ); ?>
				</a>
			</h2>

			<?php
			if ( 'status' === $active_tab ) {
				$this->render_status_tab();
			} else {
				$this->render_manage_tab();
			}
			?>
		</div>
		<?php
	}

	/**
	 * Render Tab 1: Manage Door Codes
	 */
	private function render_manage_tab() {
		global $wpdb;
		$table_bookings = $wpdb->prefix . 'snippen_bookings';
		$table_bb       = $wpdb->prefix . 'snippen_booking_booking_blocks';
		$table_blocks   = $wpdb->prefix . 'snippen_booking_blocks';
		$table_slots    = $wpdb->prefix . 'snippen_time_slots';

		$horizon_days  = isset( $_GET['horizon'] ) ? max( 1, intval( $_GET['horizon'] ) ) : 30;
		$filter_status = isset( $_GET['filter_code'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_code'] ) ) : 'all';

		$today = current_time( 'Y-m-d' );
		$limit = date( 'Y-m-d', strtotime( "+{$horizon_days} days" ) );

		// Fetch approaching bookings
		$raw_bookings = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT b.id, b.user_id, b.customer_name, b.customer_phone, b.customer_email, b.booking_date, b.status,
				        b.door_code, b.door_code_updated_at, b.booking_type, b.booking_snapshot,
				        COALESCE(MIN(s.start_time), ts.start_time, '00:00:00') as block_start,
				        COALESCE(MAX(s.end_time), ts.end_time, '23:59:59') as block_end
				 FROM {$table_bookings} b
				 LEFT JOIN {$table_bb} bb ON b.id = bb.booking_id
				 LEFT JOIN {$table_blocks} s ON bb.booking_block_id = s.id
				 LEFT JOIN {$table_slots} ts ON b.slot_id = ts.id
				 WHERE b.deleted_at IS NULL
				   AND b.status IN ('confirmed', 'pending_payment')
				   AND b.booking_date >= %s
				   AND b.booking_date <= %s
				 GROUP BY b.id
				 ORDER BY b.booking_date ASC, block_start ASC, b.id ASC",
				$today,
				$limit
			)
		);

		if ( ! is_array( $raw_bookings ) ) {
			$raw_bookings = array();
		}

		// Normalize items for chain building
		$normalized = array();
		foreach ( $raw_bookings as $b ) {
			$start_time = $b->block_start ?: '00:00:00';
			$end_time   = $b->block_end ?: '23:59:59';

			if ( ! empty( $b->booking_snapshot ) ) {
				$snap = json_decode( $b->booking_snapshot, true );
				if ( is_array( $snap ) ) {
					if ( ! empty( $snap['start_time'] ) ) {
						$start_time = $snap['start_time'];
					}
					if ( ! empty( $snap['end_time'] ) ) {
						$end_time = $snap['end_time'];
					}
				}
			}

			$start_ts = strtotime( $b->booking_date . ' ' . $start_time );
			$end_ts   = ( $end_time < $start_time )
				? strtotime( date( 'Y-m-d', strtotime( $b->booking_date . ' +1 day' ) ) . ' ' . $end_time )
				: strtotime( $b->booking_date . ' ' . $end_time );

			$tenant_key = '';
			if ( ! empty( $b->user_id ) && (int) $b->user_id > 0 ) {
				$tenant_key = 'user_' . (int) $b->user_id;
			} elseif ( ! empty( $b->customer_phone ) ) {
				$clean_phone = preg_replace( '/[^0-9]/', '', (string) $b->customer_phone );
				$tenant_key  = 'phone_' . $clean_phone;
			} else {
				$tenant_key = 'email_' . strtolower( trim( (string) $b->customer_email ) );
			}

			$normalized[] = array(
				'id'                   => (int) $b->id,
				'booking_date'         => $b->booking_date,
				'door_code'            => $b->door_code,
				'door_code_updated_at' => $b->door_code_updated_at,
				'modified_at'          => isset( $b->modified_at ) ? $b->modified_at : null,
				'tenant_key'           => $tenant_key,
				'real_start'           => $start_ts,
				'real_end'             => $end_ts,
				'booking_row'          => $b,
			);
		}

		$chains = DoorLockApi::build_contiguous_chains( $normalized );

		// Filter chains based on code presence filter
		$display_chains = array();
		foreach ( $chains as $chain ) {
			$has_code = ! empty( $chain['door_code'] );
			if ( 'without_code' === $filter_status && $has_code ) {
				continue;
			}
			if ( 'with_code' === $filter_status && ! $has_code ) {
				continue;
			}
			$display_chains[] = $chain;
		}

		$buffer_minutes = intval( get_option( 'snippen_doorman_buffer_minutes_before', 30 ) );
		$grace_minutes  = intval( get_option( 'snippen_doorman_grace_minutes_after', 120 ) );
		$fleet_info     = DoorLockApi::get_all_services_status();
		$doorman_status = $fleet_info['aggregated'] ?? get_option( 'snippen_doorman_last_status', null );

		?>
		<div class="tablenav top" style="display:flex; justify-content:space-between; align-items:center; margin:15px 0 10px;">
			<div class="alignleft actions">
				<form method="get" action="" style="display:inline-flex; gap:10px; align-items:center;">
					<input type="hidden" name="page" value="snippen-booking-door-codes">
					<input type="hidden" name="tab" value="manage">

					<label for="filter_horizon" class="screen-reader-text"><?php esc_html_e( 'Tidshorisont', 'snippen-booking' ); ?></label>
					<select name="horizon" id="filter_horizon">
						<option value="7" <?php selected( $horizon_days, 7 ); ?>><?php esc_html_e( 'Neste 7 dager', 'snippen-booking' ); ?></option>
						<option value="14" <?php selected( $horizon_days, 14 ); ?>><?php esc_html_e( 'Neste 14 dager', 'snippen-booking' ); ?></option>
						<option value="30" <?php selected( $horizon_days, 30 ); ?>><?php esc_html_e( 'Neste 30 dager', 'snippen-booking' ); ?></option>
						<option value="60" <?php selected( $horizon_days, 60 ); ?>><?php esc_html_e( 'Neste 60 dager', 'snippen-booking' ); ?></option>
					</select>

					<label for="filter_code" class="screen-reader-text"><?php esc_html_e( 'Status', 'snippen-booking' ); ?></label>
					<select name="filter_code" id="filter_code">
						<option value="all" <?php selected( $filter_status, 'all' ); ?>><?php esc_html_e( 'Alle reservasjoner', 'snippen-booking' ); ?></option>
						<option value="without_code" <?php selected( $filter_status, 'without_code' ); ?>><?php esc_html_e( 'Mangler dørkode (ugyldig/ikke satt)', 'snippen-booking' ); ?></option>
						<option value="with_code" <?php selected( $filter_status, 'with_code' ); ?>><?php esc_html_e( 'Har tildelt dørkode', 'snippen-booking' ); ?></option>
					</select>

					<input type="submit" class="button" value="<?php esc_attr_e( 'Filtrer', 'snippen-booking' ); ?>">
				</form>
			</div>
			<div class="alignright actions">
				<button type="button" class="button button-secondary" id="snippen-bulk-save-door-codes">
					<?php esc_html_e( '💾 Lagre alle endrede koder', 'snippen-booking' ); ?>
				</button>
			</div>
		</div>

		<table class="wp-list-table widefat fixed striped" style="margin-top:10px;">
			<thead>
				<tr>
					<th style="width:200px;"><?php esc_html_e( 'Leietaker', 'snippen-booking' ); ?></th>
					<th style="width:180px;"><?php esc_html_e( 'Reservasjon & Lås-status', 'snippen-booking' ); ?></th>
					<th style="width:200px;"><?php esc_html_e( 'Avtalt Leietid', 'snippen-booking' ); ?></th>
					<th style="width:220px;"><?php esc_html_e( 'Gyldig Adgangsvindu (Doorman)', 'snippen-booking' ); ?></th>
					<th style="width:140px; text-align:center;"><?php esc_html_e( 'Dørkode', 'snippen-booking' ); ?></th>
					<th style="width:160px;"><?php esc_html_e( 'Varslingsstatus', 'snippen-booking' ); ?></th>
					<th style="width:180px;"><?php esc_html_e( 'Handling', 'snippen-booking' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $display_chains ) ) : ?>
					<tr>
						<td colspan="7" style="text-align:center; padding:20px; color:#64748b;">
							<?php esc_html_e( 'Ingen kommende reservasjoner funnet for valgt filter.', 'snippen-booking' ); ?>
						</td>
					</tr>
				<?php else : ?>
					<?php foreach ( $display_chains as $chain ) : ?>
						<?php
						$primary_id = $chain['primary_id'];
						$window     = DoorCodeService::get_access_window( $primary_id );

						// Lookup details for primary booking
						$primary_booking = null;
						foreach ( $normalized as $item ) {
							if ( $item['id'] === $primary_id ) {
								$primary_booking = $item['booking_row'];
								break;
							}
						}

						$booking_ids_str = '#' . implode( ', #', $chain['booking_ids'] );
						$user_name       = $primary_booking ? $primary_booking->customer_name : __( 'Ukjent', 'snippen-booking' );
						$user_phone      = $primary_booking ? $primary_booking->customer_phone : '';
						$user_email      = $primary_booking ? $primary_booking->customer_email : '';

						// Check if door_code_updated notification has been logged
						$last_notif = MessageLoggerService::get_messages_for_booking( $primary_id );
						$sent_info  = null;
						if ( is_array( $last_notif ) ) {
							foreach ( $last_notif as $msg ) {
								if ( isset( $msg->event_type ) && 'door_code_updated' === $msg->event_type ) {
									$sent_info = $msg;
									break;
								}
							}
						}

						$door_badges = self::get_doorman_badges_for_chain( $chain['booking_ids'], $chain['door_code'], $doorman_status );
						?>
						<tr class="door-code-row" data-id="<?php echo esc_attr( $primary_id ); ?>" data-initial-code="<?php echo esc_attr( $chain['door_code'] ?? '' ); ?>">
							<td>
								<strong><?php echo esc_html( $user_name ); ?></strong>
								<?php if ( ! empty( $user_phone ) ) : ?>
									<br><small style="color:#64748b;">📞 <?php echo esc_html( $user_phone ); ?></small>
								<?php endif; ?>
								<?php if ( ! empty( $user_email ) ) : ?>
									<br><small style="color:#64748b;">✉️ <?php echo esc_html( $user_email ); ?></small>
								<?php endif; ?>
							</td>
							<td>
								<strong><?php echo esc_html( $booking_ids_str ); ?></strong>
								<?php if ( count( $chain['booking_ids'] ) > 1 ) : ?>
									<span class="badge" style="background:#e0f2fe; color:#0369a1; padding:2px 6px; border-radius:4px; font-size:11px; font-weight:600;"><?php esc_html_e( 'Kjede', 'snippen-booking' ); ?></span>
								<?php endif; ?>
								<?php if ( ! empty( $door_badges ) ) : ?>
									<div style="margin-top:6px; display:flex; flex-direction:column; gap:4px;">
										<?php
										foreach ( $door_badges as $badge_html ) {
											echo $badge_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
										}
										?>
									</div>
								<?php endif; ?>
							</td>
							<td>
								<?php
								$start_fmt = date_i18n( 'd.m.Y H:i', $chain['group_start'] );
								$end_fmt   = ( date( 'Y-m-d', $chain['group_start'] ) === date( 'Y-m-d', $chain['group_end'] ) )
									? date_i18n( 'H:i', $chain['group_end'] )
									: date_i18n( 'd.m.Y H:i', $chain['group_end'] );
								echo esc_html( $start_fmt . ' – ' . $end_fmt );
								?>
							</td>
							<td>
								<div style="font-weight:600; color:#0f172a;">
									<?php echo esc_html( $window['validity_period'] ); ?>
								</div>
								<small style="color:#64748b; font-size:11px;">
									<?php
									printf(
										/* translators: 1: buffer minutes, 2: grace minutes */
										esc_html__( '(-%1$d min buffer, +%2$d min grace)', 'snippen-booking' ),
										$buffer_minutes,
										$grace_minutes
									);
									?>
								</small>
							</td>
							<td style="text-align:center;">
								<input type="text"
										class="door-code-field"
										maxlength="6"
										value="<?php echo esc_attr( $chain['door_code'] ?? '' ); ?>"
										placeholder="••••••"
										style="width:90px; text-align:center; font-family:monospace; font-size:15px; font-weight:bold; letter-spacing:2px; border-radius:4px; padding:4px;">
							</td>
							<td>
								<?php if ( $sent_info ) : ?>
									<span class="badge-notif-sent" style="display:inline-flex; align-items:center; gap:4px; color:#16a34a; font-weight:600; font-size:12px;">
										<span>✓</span> <?php printf( esc_html__( 'Sendt %s', 'snippen-booking' ), esc_html( date_i18n( 'd.m H:i', strtotime( $sent_info->created_at ) ) ) ); ?>
									</span>
								<?php else : ?>
									<span class="badge-notif-none" style="color:#94a3b8; font-size:12px;">
										<?php esc_html_e( 'Ikke varslet', 'snippen-booking' ); ?>
									</span>
								<?php endif; ?>
							</td>
							<td>
								<div style="display:flex; flex-direction:column; gap:6px;">
									<div style="display:flex; align-items:center; gap:6px;">
										<button type="button" class="button button-primary btn-save-row" style="font-weight:600;">
											<?php esc_html_e( 'Lagre', 'snippen-booking' ); ?>
										</button>
										<label style="font-size:11px; display:inline-flex; align-items:center; gap:3px;" title="<?php esc_attr_e( 'Send SMS og e-post varsel til kunden ved lagring', 'snippen-booking' ); ?>">
											<input type="checkbox" class="cb-notify-customer" checked="checked">
											<?php esc_html_e( 'Varsle', 'snippen-booking' ); ?>
										</label>
									</div>
									<span class="row-status-msg" style="font-size:11px; min-height:14px;"></span>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<script>
		jQuery(document).ready(function($) {
			function saveDoorCodeRow($row, callback) {
				var id = $row.data('id');
				var code = $row.find('.door-code-field').val().trim();
				var notify = $row.find('.cb-notify-customer').is(':checked');
				var $btn = $row.find('.btn-save-row');
				var $msg = $row.find('.row-status-msg');

				$btn.prop('disabled', true);
				$msg.text('Lagrer...').css('color', '#64748b');

				$.post(snippenAdmin.ajaxUrl, {
					action: 'snippen_update_door_code',
					nonce: snippenAdmin.nonce,
					id: id,
					door_code: code,
					dispatch_notification: notify ? 'true' : 'false'
				}, function(res) {
					if (res.success) {
						$row.data('initial-code', code);
						$msg.text('Lagret!').css('color', '#16a34a');
						if (notify && code !== '') {
							$row.find('.badge-notif-none').replaceWith('<span class="badge-notif-sent" style="display:inline-flex; align-items:center; gap:4px; color:#16a34a; font-weight:600; font-size:12px;"><span>✓</span> Varsel sendt</span>');
						}
						setTimeout(function() {
							$msg.fadeOut(function() { $(this).text('').show(); });
						}, 3000);
					} else {
						$msg.text(res.data ? res.data.message : 'Feil ved lagring').css('color', '#dc2626');
					}
				}).fail(function() {
					$msg.text('Nettverksfeil').css('color', '#dc2626');
				}).always(function() {
					$btn.prop('disabled', false);
					if (callback) callback();
				});
			}

			// Save individual row
			$('.snippen-door-codes-admin').on('click', '.btn-save-row', function() {
				var $row = $(this).closest('.door-code-row');
				saveDoorCodeRow($row);
			});

			// Enter key in input field triggers save
			$('.snippen-door-codes-admin').on('keypress', '.door-code-field', function(e) {
				if (e.which === 13) {
					e.preventDefault();
					var $row = $(this).closest('.door-code-row');
					saveDoorCodeRow($row);
				}
			});

			// Bulk save
			$('#snippen-bulk-save-door-codes').on('click', function() {
				var $btn = $(this);
				var rowsToSave = [];
				$('.door-code-row').each(function() {
					var $r = $(this);
					var current = $r.find('.door-code-field').val().trim();
					var initial = String($r.data('initial-code') || '').trim();
					if (current !== initial && current !== '') {
						rowsToSave.push($r);
					}
				});

				if (rowsToSave.length === 0) {
					alert('Ingen endrede dørkoder å lagre.');
					return;
				}

				$btn.prop('disabled', true).text('Lagrer ' + rowsToSave.length + '...');
				var done = 0;
				rowsToSave.forEach(function($row) {
					saveDoorCodeRow($row, function() {
						done++;
						if (done >= rowsToSave.length) {
							$btn.prop('disabled', false).text('💾 Lagre alle endrede koder');
						}
					});
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Render Tab 2: Status i dørene & Doorman
	 */
	private function render_status_tab() {
		$fleet_data      = DoorLockApi::get_all_services_status();
		$services        = $fleet_data['services'] ?? array();
		$services_status = $fleet_data['services_status'] ?? array();
		$aggregated      = $fleet_data['aggregated'] ?? null;
		$last_recv_at    = $fleet_data['last_received_at'] ?? null;

		if ( empty( $services ) && empty( $aggregated ) ) {
			?>
			<div class="notice notice-info inline" style="padding:15px; margin-top:15px;">
				<h3 style="margin-top:0;"><?php esc_html_e( 'Ingen status mottatt fra Doorman ennå', 'snippen-booking' ); ?></h3>
				<p>
					<?php esc_html_e( 'Ingen status-snapshot er mottatt fra snippen-doorman-service. Så snart en tjeneste utfører en synkronisering og sender data til POST /wp-json/snippen/v1/door/status, vil aktiv låsestatus, batterinivå og koder i dørene vises her automatisk.', 'snippen-booking' ); ?>
				</p>
			</div>
			<?php
			return;
		}

		$selected_service = isset( $_GET['service_id'] ) ? sanitize_key( wp_unslash( $_GET['service_id'] ) ) : 'all';
		if ( 'all' !== $selected_service && ! isset( $services_status[ $selected_service ] ) ) {
			$selected_service = 'all';
		}

		$is_filtered = ( 'all' !== $selected_service );
		if ( $is_filtered ) {
			$status         = $services_status[ $selected_service ];
			$active_service = $services[ $selected_service ] ?? null;
		} else {
			$status         = $aggregated ?: array();
			$active_service = null;
		}

		$locks            = $status['locks'] ?? array();
		$provisioned      = $status['provisioned_on_locks'] ?? array();
		$reservations     = $status['reservations_in_db'] ?? array();
		$recently_expired = $status['recently_expired'] ?? array();
		$generated_at     = ! empty( $status['generated_at'] ) ? date_i18n( 'd.m.Y H:i:s', strtotime( $status['generated_at'] ) ) : '-';
		$received_fmt     = ! empty( $last_recv_at ) ? date_i18n( 'd.m.Y H:i:s', strtotime( $last_recv_at ) ) : '-';

		// Check for offline nodes (> 15 min)
		$offline_nodes = array();
		foreach ( $services as $snode ) {
			if ( ! empty( $snode['is_offline'] ) ) {
				$offline_nodes[] = $snode;
			}
		}

		?>
		<?php if ( ! empty( $offline_nodes ) ) : ?>
			<div class="notice notice-error inline" style="padding:14px 18px; margin:15px 0 20px; border-left-color:#dc2626; background:#fef2f2; border-radius:4px;">
				<h3 style="margin:0 0 6px; color:#991b1b; font-size:15px;">
					⚠️ <?php esc_html_e( 'Advarsel: Én eller flere Doorman-tjenester er offline!', 'snippen-booking' ); ?>
				</h3>
				<p style="margin:0; color:#7f1d1d; font-size:13px; line-height:1.5;">
					<?php
					$offline_strings = array();
					foreach ( $offline_nodes as $on ) {
						$ls_fmt            = ! empty( $on['last_seen'] ) ? date_i18n( 'd.m.Y H:i:s', strtotime( $on['last_seen'] ) ) : __( 'aldri', 'snippen-booking' );
						$offline_strings[] = sprintf( '<strong>%s</strong> (sist sett: %s)', esc_html( $on['name'] ?? $on['service_id'] ), esc_html( $ls_fmt ) );
					}
					printf(
						/* translators: %s: list of offline node names and timestamps */
						esc_html__( 'Følgende noder har ikke rapportert status på over 15 minutter og kan være offline eller ha nettverksproblemer: %s.', 'snippen-booking' ),
						implode( ', ', $offline_strings ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					);
					?>
				</p>
			</div>
		<?php endif; ?>

		<div style="background:#fff; border:1px solid #cbd5e1; border-radius:6px; padding:14px 20px; margin-bottom:20px; display:flex; justify-content:space-between; align-items:center;">
			<div>
				<strong style="font-size:14px; color:#0f172a;"><?php esc_html_e( '📡 Yale Doorman-tjeneste tilkoblet', 'snippen-booking' ); ?></strong>
				<div style="color:#64748b; font-size:12px; margin-top:3px;">
					<?php printf( esc_html__( 'Sist mottatt fra Doorman-flåten: %1$s (Generert i tjeneste: %2$s)', 'snippen-booking' ), esc_html( $received_fmt ), esc_html( $generated_at ) ); ?>
				</div>
			</div>
			<div>
				<span class="badge" style="background:#dcfce7; color:#15803d; font-size:12px; font-weight:600; padding:4px 10px; border-radius:20px;">
					<?php esc_html_e( '● Aktiv synkronisering', 'snippen-booking' ); ?>
				</span>
			</div>
		</div>

		<!-- Filter / Visningsvelger for noder -->
		<div style="background:#fff; border:1px solid #cbd5e1; border-radius:6px; padding:12px 18px; margin-bottom:24px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
			<div style="display:flex; align-items:center; gap:12px;">
				<label for="snippen-service-filter" style="font-weight:700; font-size:13px; color:#1e293b;">
					<?php esc_html_e( 'Filter visning:', 'snippen-booking' ); ?>
				</label>
				<select id="snippen-service-filter" onchange="window.location.href=this.value;" style="padding:4px 10px; font-size:13px; border-radius:4px;">
					<option value="<?php echo esc_url( admin_url( 'admin.php?page=snippen-booking-door-codes&tab=status&service_id=all' ) ); ?>" <?php selected( $selected_service, 'all' ); ?>>
						<?php esc_html_e( '🌐 Alle noder (samlet oversikt)', 'snippen-booking' ); ?>
					</option>
					<?php foreach ( $services as $sid => $snode ) : ?>
						<option value="<?php echo esc_url( admin_url( 'admin.php?page=snippen-booking-door-codes&tab=status&service_id=' . $sid ) ); ?>" <?php selected( $selected_service, $sid ); ?>>
							<?php echo esc_html( ( $snode['is_offline'] ? '⚠️ ' : '● ' ) . ( $snode['name'] ?? $sid ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<div>
				<span style="font-size:12px; color:#64748b;">
					<?php printf( esc_html__( 'Totalt %d registrerte Doorman-noder', 'snippen-booking' ), count( $services ) ); ?>
				</span>
			</div>
		</div>

		<!-- Flåteoversikt: Statuskort per registrert node -->
		<h2 style="font-size:18px; margin-bottom:12px; color:#0f172a;">
			<?php esc_html_e( 'Tilknyttede Doorman-noder (Flåteoversikt)', 'snippen-booking' ); ?>
		</h2>
		<div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(360px, 1fr)); gap:16px; margin-bottom:30px;">
			<?php foreach ( $services as $sid => $snode ) : ?>
				<?php
				$is_node_offline = ! empty( $snode['is_offline'] );
				$card_border     = $is_node_offline ? '1px solid #fca5a5' : '1px solid #e2e8f0';
				$last_seen_fmt   = ! empty( $snode['last_seen'] ) ? date_i18n( 'd.m.Y H:i:s', strtotime( $snode['last_seen'] ) ) : '-';
				$ip_addr         = $snode['ip_address'] ?? '';
				$ip_url          = '';
				if ( ! empty( $ip_addr ) ) {
					$ip_url = ( 0 === strpos( $ip_addr, 'http://' ) || 0 === strpos( $ip_addr, 'https://' ) )
						? $ip_addr
						: ( false !== strpos( $ip_addr, ':' ) ? 'http://' . $ip_addr : 'http://' . $ip_addr . ':8080' );
				}
				$node_locks_cnt = $snode['lock_count'] ?? count( (array) ( $snode['lock_ids'] ?? array() ) );
				$min_batt       = isset( $snode['min_battery'] ) && null !== $snode['min_battery'] ? (int) $snode['min_battery'] : null;
				$batt_color     = ( null !== $min_batt && $min_batt >= 50 ) ? '#16a34a' : ( ( null !== $min_batt && $min_batt >= 20 ) ? '#d97706' : '#dc2626' );
				?>
				<div style="background:#fff; border:<?php echo esc_attr( $card_border ); ?>; border-radius:8px; padding:16px 18px; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
					<div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px;">
						<div>
							<h3 style="margin:0 0 4px; font-size:16px; font-weight:700; color:#1e293b;">
								<?php echo esc_html( $snode['name'] ?? $sid ); ?>
							</h3>
							<div style="font-family:monospace; font-size:12px; color:#64748b;">
								ID: <?php echo esc_html( $sid ); ?>
								<?php if ( ! empty( $snode['version'] ) ) : ?>
									· <span style="color:#0f172a; font-weight:600;">v<?php echo esc_html( $snode['version'] ); ?></span>
								<?php endif; ?>
							</div>
						</div>
						<div>
							<?php if ( $is_node_offline ) : ?>
								<span class="badge" style="background:#fee2e2; color:#b91c1c; font-size:11px; font-weight:700; padding:4px 8px; border-radius:12px;">
									⚠️ <?php esc_html_e( 'Offline (> 15m)', 'snippen-booking' ); ?>
								</span>
							<?php else : ?>
								<span class="badge" style="background:#dcfce7; color:#15803d; font-size:11px; font-weight:700; padding:4px 8px; border-radius:12px;">
									● <?php esc_html_e( 'Online', 'snippen-booking' ); ?>
								</span>
							<?php endif; ?>
						</div>
					</div>

					<div style="font-size:12px; color:#475569; display:flex; flex-direction:column; gap:4px; margin-top:10px; border-top:1px solid #f1f5f9; padding-top:10px;">
						<div>
							<strong><?php esc_html_e( 'Sist sett:', 'snippen-booking' ); ?></strong>
							<span style="color:#0f172a;"><?php echo esc_html( $last_seen_fmt ); ?></span>
						</div>
						<?php if ( ! empty( $snode['hostname'] ) ) : ?>
							<div>
								<strong><?php esc_html_e( 'Hostname:', 'snippen-booking' ); ?></strong>
								<code style="font-size:11px;"><?php echo esc_html( $snode['hostname'] ); ?></code>
							</div>
						<?php endif; ?>
						<?php if ( ! empty( $ip_url ) ) : ?>
							<div>
								<strong><?php esc_html_e( 'Lokalt dashbord:', 'snippen-booking' ); ?></strong>
								<a href="<?php echo esc_url( $ip_url ); ?>" target="_blank" rel="noopener noreferrer" style="font-family:monospace; font-size:11px; color:#2563eb; font-weight:600; text-decoration:none;">
									<?php echo esc_html( $ip_url ); ?> ↗
								</a>
							</div>
						<?php endif; ?>
						<div style="display:flex; justify-content:space-between; align-items:center; margin-top:4px;">
							<span>
								<strong><?php esc_html_e( 'Dører:', 'snippen-booking' ); ?></strong>
								<?php printf( esc_html__( '%d tilknyttet', 'snippen-booking' ), (int) $node_locks_cnt ); ?>
							</span>
							<?php if ( null !== $min_batt ) : ?>
								<span style="font-weight:700; color:<?php echo esc_attr( $batt_color ); ?>; font-size:12px;">
									🔋 <?php echo esc_html( $min_batt ); ?>%
								</span>
							<?php endif; ?>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<!-- 1. Fysiske dørlåser -->
		<h2 style="font-size:18px; margin-bottom:12px; color:#0f172a;">
			<?php esc_html_e( '1. Fysiske dørlåser (Yale Doorman)', 'snippen-booking' ); ?>
		</h2>
		<div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(360px, 1fr)); gap:16px; margin-bottom:30px;">
			<?php if ( empty( $locks ) ) : ?>
				<p style="color:#64748b;"><?php esc_html_e( 'Ingen fysiske låser registrert i Doorman-status.', 'snippen-booking' ); ?></p>
			<?php else : ?>
				<?php foreach ( $locks as $lock ) : ?>
					<?php
					$battery       = isset( $lock['battery'] ) ? (int) $lock['battery'] : null;
					$battery_color = ( null !== $battery && $battery >= 50 ) ? '#16a34a' : ( ( null !== $battery && $battery >= 20 ) ? '#d97706' : '#dc2626' );
					$lock_status   = $lock['lock_status'] ?? 'UNKNOWN';
					$door_status   = $lock['door_status'] ?? 'UNKNOWN';
					$handles_objs  = ! empty( $lock['handles_objects'] ) ? implode( ', ', wp_list_pluck( $lock['handles_objects'], 'name' ) ) : __( 'Peisestuen, Festsalen', 'snippen-booking' );
					$slots         = isset( $lock['slots'] ) && is_array( $lock['slots'] ) ? $lock['slots'] : array();
					$node_tag      = ! empty( $lock['service_name'] ) ? $lock['service_name'] : '';
					?>
					<div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:18px; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
						<div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px;">
							<div>
								<h3 style="margin:0 0 4px; font-size:16px; font-weight:700; color:#1e293b;">
									<?php echo esc_html( $lock['name'] ?? __( 'Fysisk lås', 'snippen-booking' ) ); ?>
								</h3>
								<div style="font-family:monospace; font-size:12px; color:#64748b;">
									MAC: <?php echo esc_html( $lock['address'] ?? '-' ); ?>
									<?php if ( ! empty( $node_tag ) && ! $is_filtered ) : ?>
										· <span style="color:#334155; font-weight:600;">🏢 <?php echo esc_html( $node_tag ); ?></span>
									<?php endif; ?>
								</div>
							</div>
							<div style="text-align:right;">
								<?php if ( null !== $battery ) : ?>
									<span style="font-weight:700; color:<?php echo esc_attr( $battery_color ); ?>; font-size:14px;">
										🔋 <?php echo esc_html( $battery ); ?>%
									</span>
								<?php endif; ?>
							</div>
						</div>

						<div style="display:flex; gap:8px; margin-bottom:14px; font-size:12px; flex-wrap:wrap;">
							<span class="badge" style="background:#f1f5f9; color:#334155; padding:3px 8px; border-radius:4px; font-weight:600;">
								🔒 <?php echo esc_html( $lock_status ); ?>
							</span>
							<span class="badge" style="background:#f1f5f9; color:#334155; padding:3px 8px; border-radius:4px; font-weight:600;">
								🚪 <?php echo esc_html( $door_status ); ?>
							</span>
							<span class="badge" style="background:#eff6ff; color:#1d4ed8; padding:3px 8px; border-radius:4px; font-weight:600;">
								🏛️ <?php echo esc_html( $handles_objs ); ?>
							</span>
						</div>

						<!-- Tastaturspor 3-9 visning -->
						<div style="margin-top:12px; border-top:1px solid #f1f5f9; padding-top:12px;">
							<div style="font-size:11px; font-weight:700; text-transform:uppercase; color:#64748b; margin-bottom:8px;">
								<?php esc_html_e( 'Tastaturspor i låsen (Slot 3–9)', 'snippen-booking' ); ?>
							</div>
							<div style="display:grid; grid-template-columns:repeat(7, 1fr); gap:6px;">
								<?php for ( $slot_idx = 3; $slot_idx <= 9; $slot_idx++ ) : ?>
									<?php
									$slot_key  = (string) $slot_idx;
									$is_used   = ! empty( $slots[ $slot_key ] );
									$slot_text = $is_used ? '#' . $slots[ $slot_key ] : __( 'Ledig', 'snippen-booking' );
									$bg_color  = $is_used ? '#dbeafe' : '#f8fafc';
									$txt_color = $is_used ? '#1e40af' : '#94a3b8';
									$border    = $is_used ? '1px solid #93c5fd' : '1px dashed #cbd5e1';
									?>
									<div style="background:<?php echo esc_attr( $bg_color ); ?>; border:<?php echo esc_attr( $border ); ?>; border-radius:4px; padding:6px 2px; text-align:center;">
										<div style="font-size:10px; font-weight:700; color:#475569;"><?php echo esc_html( $slot_idx ); ?></div>
										<div style="font-size:10px; color:<?php echo esc_attr( $txt_color ); ?>; font-weight:600; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?php echo esc_attr( $slot_text ); ?>">
											<?php echo esc_html( $slot_text ); ?>
										</div>
									</div>
								<?php endfor; ?>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>

		<!-- 2. Koder i dørene nå -->
		<h2 style="font-size:18px; margin-bottom:12px; color:#0f172a;">
			<?php esc_html_e( '2. Koder programmert i dørene akkurat nå', 'snippen-booking' ); ?>
		</h2>
		<table class="wp-list-table widefat fixed striped" style="margin-bottom:30px;">
			<thead>
				<tr>
					<th style="width:200px;"><?php esc_html_e( 'Fysisk dørlås', 'snippen-booking' ); ?></th>
					<?php if ( ! $is_filtered ) : ?>
						<th style="width:140px;"><?php esc_html_e( 'Node / Tjeneste', 'snippen-booking' ); ?></th>
					<?php endif; ?>
					<th style="width:90px; text-align:center;"><?php esc_html_e( 'Spor', 'snippen-booking' ); ?></th>
					<th style="width:120px; text-align:center;"><?php esc_html_e( 'PIN-kode', 'snippen-booking' ); ?></th>
					<th style="width:180px;"><?php esc_html_e( 'Lokale', 'snippen-booking' ); ?></th>
					<th style="width:220px;"><?php esc_html_e( 'Leietaker', 'snippen-booking' ); ?></th>
					<th style="width:240px;"><?php esc_html_e( 'Gyldighetsperiode', 'snippen-booking' ); ?></th>
					<th style="width:120px; text-align:center;"><?php esc_html_e( 'Status', 'snippen-booking' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $provisioned ) ) : ?>
					<tr>
						<td colspan="<?php echo $is_filtered ? 7 : 8; ?>" style="text-align:center; padding:18px; color:#64748b;">
							<?php esc_html_e( 'Ingen koder er programmert i dørlåsene akkurat nå.', 'snippen-booking' ); ?>
						</td>
					</tr>
				<?php else : ?>
					<?php foreach ( $provisioned as $p ) : ?>
						<?php
						$loc_names = ! empty( $p['booking_objects'] ) ? implode( ', ', wp_list_pluck( $p['booking_objects'], 'name' ) ) : __( 'Snippen', 'snippen-booking' );
						$t_name    = $p['customer_name'] ?? ( '#' . ( $p['booking_id'] ?? '' ) );
						$t_phone   = $p['customer_phone'] ?? '';
						$v_from    = ! empty( $p['valid_from'] ) ? date_i18n( 'd.m.Y H:i', strtotime( $p['valid_from'] ) ) : '-';
						$v_to      = ! empty( $p['valid_to'] ) ? date_i18n( 'd.m.Y H:i', strtotime( $p['valid_to'] ) ) : '-';
						?>
						<tr>
							<td>
								<strong><?php echo esc_html( $p['lock_name'] ?? ( 'Lås ' . ( $p['lock_id'] ?? '' ) ) ); ?></strong>
								<?php if ( ! empty( $p['lock_address'] ) ) : ?>
									<br><small style="font-family:monospace; color:#64748b;"><?php echo esc_html( $p['lock_address'] ); ?></small>
								<?php endif; ?>
							</td>
							<?php if ( ! $is_filtered ) : ?>
								<td>
									<span class="badge" style="background:#f8fafc; border:1px solid #cbd5e1; color:#334155; padding:2px 6px; border-radius:4px; font-weight:600; font-size:11px;">
										🏢 <?php echo esc_html( $p['service_name'] ?? ( $p['service_id'] ?? '-' ) ); ?>
									</span>
								</td>
							<?php endif; ?>
							<td style="text-align:center;">
								<span class="badge" style="background:#e0f2fe; color:#0369a1; padding:2px 8px; border-radius:12px; font-weight:700;">
									Spor <?php echo esc_html( $p['slot'] ?? '-' ); ?>
								</span>
							</td>
							<td style="text-align:center; font-family:monospace; font-size:15px; font-weight:bold; letter-spacing:2px; color:#0f172a;">
								<?php echo esc_html( $p['pin'] ?? '••••••' ); ?>
							</td>
							<td>
								<strong><?php echo esc_html( $loc_names ); ?></strong>
							</td>
							<td>
								<strong><?php echo esc_html( $t_name ); ?></strong>
								<?php if ( ! empty( $t_phone ) ) : ?>
									<br><small style="color:#64748b;">📞 <?php echo esc_html( $t_phone ); ?></small>
								<?php endif; ?>
							</td>
							<td>
								<?php echo esc_html( $v_from . ' – ' . $v_to ); ?>
							</td>
							<td style="text-align:center;">
								<span class="badge" style="background:#dcfce7; color:#15803d; padding:3px 8px; border-radius:4px; font-weight:700; font-size:11px;">
									PROVISIONED
								</span>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<!-- 3. Planlagte reservasjoner i Doorman-databasen -->
		<h2 style="font-size:18px; margin-bottom:12px; color:#0f172a;">
			<?php esc_html_e( '3. Planlagte reservasjoner i Doorman-databasen', 'snippen-booking' ); ?>
		</h2>
		<table class="wp-list-table widefat fixed striped" style="margin-bottom:30px;">
			<thead>
				<tr>
					<th style="width:120px;"><?php esc_html_e( 'Booking ID', 'snippen-booking' ); ?></th>
					<?php if ( ! $is_filtered ) : ?>
						<th style="width:140px;"><?php esc_html_e( 'Node / Tjeneste', 'snippen-booking' ); ?></th>
					<?php endif; ?>
					<th style="width:200px;"><?php esc_html_e( 'Leietaker', 'snippen-booking' ); ?></th>
					<th style="width:160px;"><?php esc_html_e( 'Lokale', 'snippen-booking' ); ?></th>
					<th style="width:110px; text-align:center;"><?php esc_html_e( 'PIN', 'snippen-booking' ); ?></th>
					<th style="width:230px;"><?php esc_html_e( 'Adgangsvindu', 'snippen-booking' ); ?></th>
					<th style="width:280px;"><?php esc_html_e( 'Status per fysisk lås', 'snippen-booking' ); ?></th>
					<th style="width:130px; text-align:center;"><?php esc_html_e( 'Synkronisering', 'snippen-booking' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $reservations ) ) : ?>
					<tr>
						<td colspan="<?php echo $is_filtered ? 7 : 8; ?>" style="text-align:center; padding:18px; color:#64748b;">
							<?php esc_html_e( 'Ingen planlagte reservasjoner funnet i Doorman-databasen.', 'snippen-booking' ); ?>
						</td>
					</tr>
				<?php else : ?>
					<?php foreach ( $reservations as $r ) : ?>
						<?php
						$loc_names = ! empty( $r['booking_objects'] ) ? implode( ', ', wp_list_pluck( $r['booking_objects'], 'name' ) ) : __( 'Peisestuen', 'snippen-booking' );
						$t_name    = $r['customer_name'] ?? ( '#' . ( $r['booking_id'] ?? '' ) );
						$t_phone   = $r['customer_phone'] ?? '';
						$v_from    = ! empty( $r['valid_from'] ) ? date_i18n( 'd.m.Y H:i', strtotime( $r['valid_from'] ) ) : '-';
						$v_to      = ! empty( $r['valid_to'] ) ? date_i18n( 'd.m.Y H:i', strtotime( $r['valid_to'] ) ) : '-';
						$plocks    = isset( $r['physical_locks'] ) && is_array( $r['physical_locks'] ) ? $r['physical_locks'] : array();
						$is_all    = ! empty( $r['all_locks_provisioned'] );
						?>
						<tr>
							<td>
								<strong>#<?php echo esc_html( $r['booking_id'] ?? '-' ); ?></strong>
							</td>
							<?php if ( ! $is_filtered ) : ?>
								<td>
									<span class="badge" style="background:#f8fafc; border:1px solid #cbd5e1; color:#334155; padding:2px 6px; border-radius:4px; font-weight:600; font-size:11px;">
										🏢 <?php echo esc_html( $r['service_name'] ?? ( $r['service_id'] ?? '-' ) ); ?>
									</span>
								</td>
							<?php endif; ?>
							<td>
								<strong><?php echo esc_html( $t_name ); ?></strong>
								<?php if ( ! empty( $t_phone ) ) : ?>
									<br><small style="color:#64748b;">📞 <?php echo esc_html( $t_phone ); ?></small>
								<?php endif; ?>
							</td>
							<td>
								<?php echo esc_html( $loc_names ); ?>
							</td>
							<td style="text-align:center; font-family:monospace; font-size:14px; font-weight:bold; letter-spacing:1px;">
								<?php echo esc_html( $r['pin'] ?? '••••••' ); ?>
							</td>
							<td>
								<?php echo esc_html( $v_from . ' – ' . $v_to ); ?>
							</td>
							<td>
								<div style="display:flex; flex-direction:column; gap:4px;">
									<?php if ( empty( $plocks ) ) : ?>
										<span style="color:#64748b;"><?php echo esc_html( $r['status'] ?? 'SCHEDULED' ); ?></span>
									<?php else : ?>
										<?php foreach ( $plocks as $pl ) : ?>
											<?php
											$p_status = $pl['status'] ?? 'SCHEDULED';
											$p_bg     = 'PROVISIONED' === $p_status ? '#dcfce7' : '#e0f2fe';
											$p_fg     = 'PROVISIONED' === $p_status ? '#15803d' : '#0369a1';
											$p_slot   = ! empty( $pl['slot'] ) ? ' (Spor ' . (int) $pl['slot'] . ')' : '';
											?>
											<div>
												<span class="badge" style="background:<?php echo esc_attr( $p_bg ); ?>; color:<?php echo esc_attr( $p_fg ); ?>; padding:2px 6px; border-radius:4px; font-size:11px; font-weight:600;">
													<?php echo esc_html( ( $pl['lock_name'] ?? 'Lås' ) . ': ' . $p_status . $p_slot ); ?>
												</span>
											</div>
										<?php endforeach; ?>
									<?php endif; ?>
								</div>
							</td>
							<td style="text-align:center;">
								<?php if ( $is_all ) : ?>
									<span class="badge" style="background:#dcfce7; color:#15803d; padding:3px 8px; border-radius:4px; font-weight:700; font-size:11px;">
										✓ I alle dører
									</span>
								<?php else : ?>
									<span class="badge" style="background:#e0f2fe; color:#0369a1; padding:3px 8px; border-radius:4px; font-weight:700; font-size:11px;">
										Planlagt
									</span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<!-- 4. Nylig utløpte koder -->
		<h2 style="font-size:18px; margin-bottom:12px; color:#0f172a;">
			<?php esc_html_e( '4. Nylig utløpte dørkoder (siste 7 dager)', 'snippen-booking' ); ?>
		</h2>
		<table class="wp-list-table widefat fixed striped" style="margin-bottom:20px;">
			<thead>
				<tr>
					<th style="width:120px;"><?php esc_html_e( 'Booking ID', 'snippen-booking' ); ?></th>
					<?php if ( ! $is_filtered ) : ?>
						<th style="width:140px;"><?php esc_html_e( 'Node / Tjeneste', 'snippen-booking' ); ?></th>
					<?php endif; ?>
					<th style="width:200px;"><?php esc_html_e( 'Leietaker', 'snippen-booking' ); ?></th>
					<th style="width:160px;"><?php esc_html_e( 'Lokale', 'snippen-booking' ); ?></th>
					<th style="width:110px; text-align:center;"><?php esc_html_e( 'PIN', 'snippen-booking' ); ?></th>
					<th style="width:240px;"><?php esc_html_e( 'Utløpt adgangsvindu', 'snippen-booking' ); ?></th>
					<th style="width:120px; text-align:center;"><?php esc_html_e( 'Status', 'snippen-booking' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $recently_expired ) ) : ?>
					<tr>
						<td colspan="<?php echo $is_filtered ? 6 : 7; ?>" style="text-align:center; padding:18px; color:#64748b;">
							<?php esc_html_e( 'Ingen utløpte koder i arkivet for de siste 7 dagene.', 'snippen-booking' ); ?>
						</td>
					</tr>
				<?php else : ?>
					<?php foreach ( $recently_expired as $exp ) : ?>
						<?php
						$loc_names = ! empty( $exp['booking_objects'] ) ? implode( ', ', wp_list_pluck( $exp['booking_objects'], 'name' ) ) : __( 'Peisestuen', 'snippen-booking' );
						$t_name    = $exp['customer_name'] ?? ( '#' . ( $exp['booking_id'] ?? '' ) );
						$t_phone   = $exp['customer_phone'] ?? '';
						$v_from    = ! empty( $exp['valid_from'] ) ? date_i18n( 'd.m.Y H:i', strtotime( $exp['valid_from'] ) ) : '-';
						$v_to      = ! empty( $exp['valid_to'] ) ? date_i18n( 'd.m.Y H:i', strtotime( $exp['valid_to'] ) ) : '-';
						?>
						<tr>
							<td>
								<strong>#<?php echo esc_html( $exp['booking_id'] ?? '-' ); ?></strong>
							</td>
							<?php if ( ! $is_filtered ) : ?>
								<td>
									<span class="badge" style="background:#f8fafc; border:1px solid #cbd5e1; color:#334155; padding:2px 6px; border-radius:4px; font-weight:600; font-size:11px;">
										🏢 <?php echo esc_html( $exp['service_name'] ?? ( $exp['service_id'] ?? '-' ) ); ?>
									</span>
								</td>
							<?php endif; ?>
							<td>
								<strong><?php echo esc_html( $t_name ); ?></strong>
								<?php if ( ! empty( $t_phone ) ) : ?>
									<br><small style="color:#64748b;">📞 <?php echo esc_html( $t_phone ); ?></small>
								<?php endif; ?>
							</td>
							<td>
								<?php echo esc_html( $loc_names ); ?>
							</td>
							<td style="text-align:center; font-family:monospace; font-size:14px; color:#94a3b8; text-decoration:line-through;">
								<?php echo esc_html( $exp['pin'] ?? '••••••' ); ?>
							</td>
							<td>
								<?php echo esc_html( $v_from . ' – ' . $v_to ); ?>
							</td>
							<td style="text-align:center;">
								<span class="badge" style="background:#f1f5f9; color:#64748b; padding:3px 8px; border-radius:4px; font-weight:700; font-size:11px;">
									EXPIRED
								</span>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Compute HTML sync badges for a booking chain based on Doorman status.
	 *
	 * @param array       $booking_ids    Booking IDs in the chain.
	 * @param string|null $chain_door_code Current door code on the chain.
	 * @param array|null  $doorman_status Last received Doorman status snapshot.
	 * @return array Array of badge HTML strings.
	 */
	public static function get_doorman_badges_for_chain( array $booking_ids, ?string $chain_door_code, ?array $doorman_status ) {
		if ( empty( $doorman_status ) || ! is_array( $doorman_status ) ) {
			return array();
		}

		$reservations = $doorman_status['reservations_in_db'] ?? array();
		$matched_res  = null;

		foreach ( $booking_ids as $bid ) {
			foreach ( $reservations as $r ) {
				if ( isset( $r['booking_id'] ) && (int) $r['booking_id'] === (int) $bid ) {
					$matched_res = $r;
					break 2;
				}
			}
		}

		$badges = array();

		if ( $matched_res ) {
			$physical_locks = isset( $matched_res['physical_locks'] ) && is_array( $matched_res['physical_locks'] ) ? $matched_res['physical_locks'] : array();

			if ( ! empty( $physical_locks ) ) {
				foreach ( $physical_locks as $pl ) {
					$pname    = $pl['lock_name'] ?? ( 'Lås ' . ( $pl['lock_id'] ?? '' ) );
					$p_status = $pl['status'] ?? 'SCHEDULED';
					$p_slot   = isset( $pl['slot'] ) && null !== $pl['slot'] ? (int) $pl['slot'] : null;

					if ( 'PROVISIONED' === $p_status && null !== $p_slot ) {
						// Check if pin matches chain door code
						if ( ! empty( $chain_door_code ) && ! empty( $matched_res['pin'] ) && $chain_door_code !== $matched_res['pin'] ) {
							$badges[] = sprintf(
								'<span class="badge" style="background:#fee2e2; color:#b91c1c; padding:2px 6px; border-radius:4px; font-size:11px; font-weight:600;">%s: %s</span>',
								esc_html( $pname ),
								esc_html__( 'Kode-avvik!', 'snippen-booking' )
							);
						} else {
							$badges[] = sprintf(
								'<span class="badge" style="background:#dcfce7; color:#15803d; padding:2px 6px; border-radius:4px; font-size:11px; font-weight:600;">%s: %s</span>',
								esc_html( $pname ),
								sprintf( esc_html__( 'I lås (Spor %d)', 'snippen-booking' ), $p_slot )
							);
						}
					} elseif ( 'SCHEDULED' === $p_status ) {
						$badges[] = sprintf(
							'<span class="badge" style="background:#e0f2fe; color:#0369a1; padding:2px 6px; border-radius:4px; font-size:11px; font-weight:600;">%s: %s</span>',
							esc_html( $pname ),
							esc_html__( 'Planlagt i Doorman', 'snippen-booking' )
						);
					} elseif ( 'EXPIRED' === $p_status ) {
						$badges[] = sprintf(
							'<span class="badge" style="background:#f1f5f9; color:#64748b; padding:2px 6px; border-radius:4px; font-size:11px; font-weight:600;">%s: %s</span>',
							esc_html( $pname ),
							esc_html__( 'Utløpt', 'snippen-booking' )
						);
					}
				}
			} else {
				$badges[] = '<span class="badge" style="background:#e0f2fe; color:#0369a1; padding:2px 6px; border-radius:4px; font-size:11px; font-weight:600;">' . esc_html__( 'Planlagt i Doorman', 'snippen-booking' ) . '</span>';
			}
		} else {
			// Check if present in provisioned_on_locks
			$provisioned = $doorman_status['provisioned_on_locks'] ?? array();
			$matched_p   = null;
			foreach ( $booking_ids as $bid ) {
				foreach ( $provisioned as $p ) {
					if ( isset( $p['booking_id'] ) && (int) $p['booking_id'] === (int) $bid ) {
						$matched_p = $p;
						break 2;
					}
				}
			}

			if ( $matched_p ) {
				$pname    = $matched_p['lock_name'] ?? ( 'Lås ' . ( $matched_p['lock_id'] ?? '' ) );
				$pslot    = isset( $matched_p['slot'] ) ? (int) $matched_p['slot'] : 0;
				$badges[] = sprintf(
					'<span class="badge" style="background:#dcfce7; color:#15803d; padding:2px 6px; border-radius:4px; font-size:11px; font-weight:600;">%s: %s</span>',
					esc_html( $pname ),
					sprintf( esc_html__( 'I lås (Spor %d)', 'snippen-booking' ), $pslot )
				);
			} elseif ( ! empty( $chain_door_code ) ) {
				$badges[] = '<span class="badge" style="background:#fef3c7; color:#b45309; padding:2px 6px; border-radius:4px; font-size:11px; font-weight:600;">' . esc_html__( 'Venter på Doorman-synk', 'snippen-booking' ) . '</span>';
			}
		}

		return $badges;
	}
}
