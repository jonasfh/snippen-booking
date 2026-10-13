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
				'id'           => (int) $b->id,
				'booking_date' => $b->booking_date,
				'door_code'    => $b->door_code,
				'tenant_key'   => $tenant_key,
				'real_start'   => $start_ts,
				'real_end'     => $end_ts,
				'booking_row'  => $b,
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

		?>
		<div class="wrap snippen-door-codes-admin">
			<h1 class="wp-heading-inline">
				<?php esc_html_e( 'Dørkoder & Adgangskontroll', 'snippen-booking' ); ?>
			</h1>
			<p class="description" style="margin-top:6px; font-size:14px; max-width:900px;">
				<?php esc_html_e( 'Her administrerer du dørkoder for oppkommende reservasjoner. Sammenhengende reservasjoner for samme leietaker er automatisk slått sammen til én kjede. Når du lagrer en dørkode, oppdateres reservasjonene og kunden varsles automatisk på SMS og e-post.', 'snippen-booking' ); ?>
			</p>

			<div class="tablenav top" style="display:flex; justify-content:space-between; align-items:center; margin:15px 0 10px;">
				<div class="alignleft actions">
					<form method="get" action="" style="display:inline-flex; gap:10px; align-items:center;">
						<input type="hidden" name="page" value="snippen-booking-door-codes">
						
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
						<th style="width:160px;"><?php esc_html_e( 'Reservasjon', 'snippen-booking' ); ?></th>
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
		</div>

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
}
