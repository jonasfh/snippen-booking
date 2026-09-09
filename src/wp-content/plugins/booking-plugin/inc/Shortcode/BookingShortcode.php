<?php

namespace SnippenBooking\Shortcode;

use SnippenBooking\Helper\Capabilities;

/**
 * Handles booking shortcode rendering
 */
class BookingShortcode {

	/**
	 * Register the shortcode
	 */
	public static function register() {
		add_shortcode( 'snippen_booking', array( __CLASS__, 'render' ) );
	}

	/**
	 * Render the booking shortcode
	 *
	 * @param array $atts Shortcode attributes
	 * @return string
	 */
	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'object_id' => '',
			),
			$atts
		);

		global $wpdb;
		$table_objects = $wpdb->prefix . 'snippen_booking_objects';

		if ( empty( $atts['object_id'] ) ) {
			// Fetch all active objects
			$objects    = $wpdb->get_results( "SELECT * FROM $table_objects WHERE deleted_at IS NULL ORDER BY id ASC" );
			$object_ids = wp_list_pluck( $objects, 'id' );
			$object_ids = array_map( 'intval', $object_ids );
		} else {
			// Parse comma-separated object IDs
			$object_ids = array_map( 'intval', explode( ',', $atts['object_id'] ) );
			$object_ids = array_filter( $object_ids );

			if ( empty( $object_ids ) ) {
				return '<div class="snippen-booking-error">' . esc_html__( 'Ugyldig objekt-ID.', 'snippen-booking' ) . '</div>';
			}

			$in_clause = implode( ',', array_fill( 0, count( $object_ids ), '%d' ) );
			$query     = $wpdb->prepare( "SELECT * FROM $table_objects WHERE id IN ($in_clause) AND deleted_at IS NULL ORDER BY id ASC", ...$object_ids );
			$objects   = $wpdb->get_results( $query );
		}

		if ( empty( $objects ) ) {
			return '<div class="snippen-booking-error">' . esc_html__( 'Booking-objekt(er) ikke funnet.', 'snippen-booking' ) . '</div>';
		}

		$is_multiple_objects = count( $objects ) > 1;

		$is_logged_in = is_user_logged_in();
		$current_user = wp_get_current_user();
		$user_name    = $is_logged_in ? esc_attr( $current_user->display_name ) : '';
		$user_email   = $is_logged_in ? esc_attr( $current_user->user_email ) : '';
		$user_phone   = $is_logged_in ? get_user_meta( $current_user->ID, 'snippen_phone', true ) : '';

		$vipps_return_notice = self::handle_and_render_vipps_return();
		$is_vipps_enabled    = ( new \SnippenBooking\Service\Vipps\VippsService() )->is_enabled();

		ob_start();
		?>
		<div class="snippen-booking-container" 
			data-object-id="<?php echo esc_attr( wp_json_encode( $object_ids ) ); ?>" 
			data-logged-in="<?php echo $is_logged_in ? 'true' : 'false'; ?>"
			data-user-id="<?php echo esc_attr( get_current_user_id() ); ?>"
			data-user-name="<?php echo esc_attr( $user_name ); ?>"
			data-user-email="<?php echo esc_attr( $user_email ); ?>"
			data-user-phone="<?php echo esc_attr( $user_phone ); ?>"
			data-is-admin="<?php echo Capabilities::can_manage_bookings() ? 'true' : 'false'; ?>">

			<?php
			if ( ! empty( $vipps_return_notice ) ) {
				echo $vipps_return_notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			?>
			
			<div class="booking-header-section">
				<?php if ( $is_multiple_objects ) : ?>
					<div class="multiple-objects-header">
						<h4><?php esc_html_e( 'Objekter tilgjengelige for booking i denne kalenderen:', 'snippen-booking' ); ?></h4>
						<div class="object-drawers-list">
							<?php foreach ( $objects as $index => $obj ) : ?>
								<div class="object-drawer">
									<button type="button" 
										class="object-drawer-toggle" 
										aria-expanded="false">
										<span class="drawer-title"><?php echo esc_html( $obj->name ); ?></span>
										<span class="drawer-icon" aria-hidden="true">▾</span>
									</button>
									<div class="object-drawer-content" style="display: none;">
										<?php if ( ! empty( $obj->description ) ) : ?>
											<p class="object-summary"><?php echo esc_html( $obj->description ); ?></p>
										<?php endif; ?>
										<?php if ( ! empty( $obj->info_link ) ) : ?>
											<p class="object-infolink-wrapper">
												<a href="<?php echo esc_url( $obj->info_link ); ?>" class="info-link" target="_blank"><?php esc_html_e( 'Mer info &rarr;', 'snippen-booking' ); ?></a>
											</p>
										<?php endif; ?>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				<?php else : ?>
					<?php
					$single_obj = $objects[0];
					?>
					<div class="header-main">
						<h3><?php echo esc_html( $single_obj->name ); ?></h3>
						<?php if ( ! empty( $single_obj->info_link ) ) : ?>
							<a href="<?php echo esc_url( $single_obj->info_link ); ?>" class="info-link" target="_blank"><?php esc_html_e( 'Mer info &rarr;', 'snippen-booking' ); ?></a>
						<?php endif; ?>
					</div>
					<?php if ( ! empty( $single_obj->description ) ) : ?>
						<p class="object-summary"><?php echo esc_html( $single_obj->description ); ?></p>
					<?php endif; ?>
				<?php endif; ?>
			</div>

			<?php if ( ! $is_logged_in ) : ?>
				<div class="snippen-login-prompt">
					<p><?php esc_html_e( 'Du må være beboer og innlogget for å kunne booke. Kalenderen under viser kun tilgjengelighet.', 'snippen-booking' ); ?></p>
					<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="snippen-login-btn"><?php esc_html_e( 'Logg inn', 'snippen-booking' ); ?></a>
				</div>
			<?php endif; ?>

			<!-- Step 1: Date/Week Selection -->
			<div id="calendar-container" class="snippen-calendar-view <?php echo ! $is_logged_in ? 'readonly-mode' : ''; ?>">
				<div class="calendar-loader"><?php esc_html_e( 'Laster kalender...', 'snippen-booking' ); ?></div>
			</div>

			<?php if ( $is_logged_in ) : ?>
			<!-- Interactive Wizard Container -->
			<div id="booking-wizard-container" class="snippen-booking-wizard" style="display: none;">
				
				<div class="wizard-header">
					<h4><?php esc_html_e( 'Fullfør din bestilling', 'snippen-booking' ); ?></h4>
					<button type="button" class="close-wizard">&times;</button>
				</div>

				<div class="wizard-steps-grid">
					
					<!-- Step 2: Block Selection -->
					<div class="wizard-step" id="step-blocks">
						<h5>1. <?php esc_html_e( 'Velg tidspunkt', 'snippen-booking' ); ?></h5>
						<p class="step-desc"><?php esc_html_e( 'Velg én eller flere sammenhengende timer/blokker.', 'snippen-booking' ); ?></p>
						<div id="blocks-selection-grid" class="blocks-grid">
							<!-- Populated via JS -->
						</div>
					</div>

					<!-- Step 3: Room Selection -->
					<div class="wizard-step" id="step-rooms" style="display: none;">
						<h5>2. <?php esc_html_e( 'Velg lokale', 'snippen-booking' ); ?></h5>
						<p class="step-desc"><?php esc_html_e( 'Velg lokaler du ønsker å leie.', 'snippen-booking' ); ?></p>
						<div id="rooms-selection-grid" class="rooms-grid">
							<!-- Populated via JS -->
						</div>
					</div>

				</div>

				<!-- Step 4: Summary & Confirm -->
				<div class="wizard-step" id="step-confirm" style="display: none;">
					<hr class="wizard-separator">
					<h5>3. <?php esc_html_e( 'Oppsummering & Kontaktopplysninger', 'snippen-booking' ); ?></h5>
					
					<div class="booking-summary-card">
						<div class="summary-details">
							<div class="summary-item">
								<strong><?php esc_html_e( 'Dato:', 'snippen-booking' ); ?></strong>
								<span id="summary-date">-</span>
							</div>
							<div class="summary-item">
								<strong><?php esc_html_e( 'Tid:', 'snippen-booking' ); ?></strong>
								<span id="summary-time">-</span>
							</div>
							<div class="summary-item">
								<strong><?php esc_html_e( 'Lokale:', 'snippen-booking' ); ?></strong>
								<span id="summary-rooms">-</span>
							</div>
							<div class="summary-item price-item">
								<strong><?php esc_html_e( 'Totalpris:', 'snippen-booking' ); ?></strong>
								<span id="summary-price">-</span>
							</div>
						</div>
						<div id="summary-wash-notice" class="wash-time-notice" style="display: none; margin-top: 12px; padding: 10px 14px; background: #e0f2fe; border: 1px solid #bae6fd; border-radius: 6px; color: #0369a1; font-size: 0.9em; font-weight: 500;">
							<span style="font-weight: 700;">✦ <?php esc_html_e( 'Viktig info:', 'snippen-booking' ); ?></span>
							<span id="summary-wash-notice-text"></span>
						</div>
					</div>

					<form id="booking-form" method="post" class="snippen-form">
						<input type="hidden" name="event_date" id="event-date">
						<input type="hidden" name="user_id" id="selected-user-id" value="<?php echo esc_attr( get_current_user_id() ); ?>">
						
						<div class="form-grid">
							<div class="form-group full-width booking-type-group">
								<label class="booking-type-main-label" style="font-weight:600; margin-bottom:8px; display:block;"><?php esc_html_e( 'Type arrangement', 'snippen-booking' ); ?></label>
								<div class="booking-type-cards">
									<label class="booking-type-card selected" for="booking_type_private">
										<input type="radio" name="booking_type" id="booking_type_private" value="private" checked>
										<div class="booking-type-card-text">
											<div class="booking-type-title-row">
												<span class="booking-type-title"><?php esc_html_e( 'Privat arrangement', 'snippen-booking' ); ?></span>
												<?php if ( $is_vipps_enabled ) : ?>
													<span class="vipps-tag" title="<?php esc_attr_e( 'Betales med Vipps', 'snippen-booking' ); ?>">
														<svg class="vipps-tag-logo" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" aria-label="<?php esc_attr_e( 'Vipps', 'snippen-booking' ); ?>" role="img" focusable="false">
															<path fill="#ff5b24" fill-rule="evenodd" d="M20,6.33c0-.24,0-.49,0-.73,0-.21,0-.41,0-.61,0-.45-.05-.89-.12-1.33-.08-.44-.22-.87-.42-1.26-.41-.8-1.06-1.45-1.86-1.86-.4-.2-.82-.34-1.26-.42-.44-.07-.88-.11-1.33-.12-.2,0-.41,0-.61,0-.24,0-.48,0-.73,0h-7.33C6.09,0,5.85,0,5.61,0c-.2,0-.41,0-.61,0-.45,0-.89.05-1.33.12-.44.07-.87.22-1.26.42-.8.41-1.45,1.06-1.86,1.86-.2.4-.34.83-.42,1.26-.07.44-.11.88-.12,1.33,0,.21,0,.41,0,.61C0,5.85,0,6.09,0,6.33v7.33C0,13.91,0,14.15,0,14.39c0,.21,0,.41,0,.61,0,.45.04.89.12,1.33.08.44.22.87.42,1.27.41.8,1.06,1.45,1.86,1.86.4.2.82.34,1.26.42.44.07.88.11,1.33.12.2,0,.41,0,.61,0,.24,0,.48,0,.73,0h7.33c.24,0,.48,0,.73,0,.2,0,.41,0,.61,0,.45,0,.89-.04,1.33-.12.44-.07.87-.22,1.26-.42.8-.41,1.45-1.06,1.86-1.86.2-.4.34-.83.42-1.27.07-.44.11-.88.12-1.33,0-.2,0-.41,0-.61,0-.24,0-.48,0-.73v-7.33h0Z"/>
															<path fill="#ffffff" d="M10.3,12.72c1.75,0,2.74-.85,3.69-2.08.52-.66,1.18-.8,1.66-.43.47.38.52,1.09,0,1.75-1.37,1.8-3.12,2.89-5.35,2.89-2.41,0-4.54-1.32-6.01-3.64-.43-.62-.33-1.28.14-1.61s1.18-.19,1.61.47c1.04,1.56,2.46,2.65,4.26,2.65h0ZM13.57,6.9c0,.85-.66,1.42-1.42,1.42s-1.42-.57-1.42-1.42.66-1.42,1.42-1.42,1.42.62,1.42,1.42Z"/>
														</svg>
														<span class="vipps-tag-text"><?php esc_html_e( 'Vipps', 'snippen-booking' ); ?></span>
													</span>
												<?php endif; ?>
											</div>
											<span class="booking-type-desc"><?php esc_html_e( 'Kun for meg og mine gjester – krever leiebetaling.', 'snippen-booking' ); ?></span>
										</div>
									</label>
									<label class="booking-type-card" for="booking_type_open">
										<input type="radio" name="booking_type" id="booking_type_open" value="open">
										<div class="booking-type-card-text">
											<div class="booking-type-title-row">
												<span class="booking-type-title"><?php esc_html_e( 'Åpent arrangement for sameiet', 'snippen-booking' ); ?></span>
											</div>
											<span class="booking-type-desc"><?php esc_html_e( 'Åpent for alle beboere (f.eks. felleskaffe, dugnad, brettspillkveld) – gratis, krever styregodkjenning.', 'snippen-booking' ); ?></span>
										</div>
									</label>
								</div>
								<div id="open-booking-notice" class="open-booking-notice" style="display: none; margin-top: 10px; padding: 10px 14px; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 6px; color: #1e40af; font-size: 0.9em;">
									<span style="font-weight: 700;">ℹ️ <?php esc_html_e( 'Styregodkjenning:', 'snippen-booking' ); ?></span>
									<span><?php esc_html_e( 'Åpne arrangementer må forhåndsgodkjennes av styret/administrator. Dersom reservasjonen avslås, avbrytes bookingen og du kan eventuelt booke på nytt som en privat reservasjon.', 'snippen-booking' ); ?></span>
								</div>
							</div>

							<div class="form-group full-width cleaning-option-group" id="cleaning-option-container" style="display: none;">
								<div class="cleaning-option-card" style="padding: 12px 14px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px;">
									<label style="display: flex; align-items: flex-start; gap: 8px; cursor: pointer; margin: 0; font-weight: normal;">
										<input type="checkbox" name="include_cleaning" id="include_cleaning" value="1" style="margin-top: 3px;">
										<div>
											<?php
											$cleaning_end_time    = get_option( 'snippen_cleaning_end_time', '11:00' );
											$cleaning_end_display = preg_replace( '/:00$/', '', $cleaning_end_time );
											/* translators: %s: cleaning end time */
											$cleaning_label = sprintf( __( 'Utvask til neste dag kl %s', 'snippen-booking' ), $cleaning_end_display );
											?>
											<strong style="color: #166534;" id="cleaning-option-label"><?php echo esc_html( $cleaning_label ); ?></strong>
											<p style="margin: 2px 0 0 0; color: #15803d; font-size: 0.85em;" id="cleaning-option-desc">
												<?php
												/* translators: %s: cleaning end time */
												printf( esc_html__( 'Neste formiddag er ledig og kan reserveres vederlagsfritt til utvask (fram til kl. %s).', 'snippen-booking' ), esc_html( $cleaning_end_display ) );
												?>
											</p>
										</div>
									</label>
								</div>
							</div>
							<?php if ( Capabilities::can_manage_bookings() ) : ?>
							<div class="form-group full-width admin-only-field">
								<label for="user-search"><?php esc_html_e( 'Søk etter beboer (Admin)', 'snippen-booking' ); ?></label>
								<div class="user-search-wrapper">
									<input type="text" id="user-search" placeholder="<?php esc_attr_e( 'Søk etter navn eller e-post...', 'snippen-booking' ); ?>" autocomplete="off" value="<?php echo esc_attr( $user_name ); ?>">
									<div id="user-search-results" class="search-results-dropdown" style="display: none;"></div>
								</div>
								<p class="description"><?php esc_html_e( 'La tomt for å bestille i eget navn.', 'snippen-booking' ); ?></p>
							</div>
							<?php endif; ?>

							<div class="form-group">
								<label for="name"><?php esc_html_e( 'Navn på beboer', 'snippen-booking' ); ?></label>
								<input type="text" name="name" id="name" required placeholder="<?php esc_attr_e( 'Fullt navn', 'snippen-booking' ); ?>" value="<?php echo esc_attr( $user_name ); ?>" autocomplete="name" readonly>
							</div>
							
							<div class="form-group">
								<label for="email"><?php esc_html_e( 'E-post', 'snippen-booking' ); ?></label>
								<input type="email" name="email" id="email" required placeholder="<?php esc_attr_e( 'navn@eksempel.no', 'snippen-booking' ); ?>" value="<?php echo esc_attr( $user_email ); ?>" autocomplete="email" readonly>
							</div>
							
							<div class="form-group">
								<label for="phone"><?php esc_html_e( 'Telefon', 'snippen-booking' ); ?></label>
								<input type="tel" name="phone" id="phone" placeholder="+47..." value="<?php echo esc_attr( $user_phone ); ?>" autocomplete="tel" readonly required>
								<?php if ( empty( $user_phone ) && ! Capabilities::can_manage_bookings() ) : ?>
									<p class="field-error-msg" style="color: #d63638; font-size: 0.85em; margin-top: 5px;"><?php esc_html_e( 'Mangler telefonnummer på din profil. Kontakt administrator.', 'snippen-booking' ); ?></p>
								<?php endif; ?>
							</div>
							
							<div class="form-group full-width">
								<label for="description"><?php esc_html_e( 'Beskrivelse (valgfritt)', 'snippen-booking' ); ?></label>
								<textarea name="description" id="description" rows="3" placeholder="<?php esc_attr_e( 'F.eks. Bursdag, møte, etc.', 'snippen-booking' ); ?>"></textarea>
							</div>
							
							<?php
							$terms_url = get_option( 'snippen_terms_url', '' );
							if ( ! empty( $terms_url ) ) :
								?>
							<div class="form-group full-width terms-acceptance">
								<label for="accept_terms">
									<input type="checkbox" name="accept_terms" id="accept_terms" required>
									<span>
										<?php esc_html_e( 'Jeg har lest og aksepterer', 'snippen-booking' ); ?>
										<a href="<?php echo esc_url( $terms_url ); ?>" class="terms-link"><?php esc_html_e( 'leievilkårene', 'snippen-booking' ); ?></a>.
									</span>
								</label>
							</div>
							<?php endif; ?>
						</div>

						<button type="submit" class="booking-submit" <?php echo ( empty( $user_phone ) && ! Capabilities::can_manage_bookings() ) ? 'disabled' : ''; ?>>
							<?php esc_html_e( 'Send bookingforespørsel', 'snippen-booking' ); ?>
						</button>
					</form>
					<div id="booking-response" style="display: none;"></div>
				</div>

			</div>
			<?php endif; ?>

			<?php if ( Capabilities::can_manage_bookings() ) : ?>
			<div id="booking-info-modal" class="snippen-modal" style="display: none;">
				<div class="modal-overlay"></div>
				<div class="modal-content">
					<div class="modal-header">
						<h4><?php esc_html_e( 'Bookingdetaljer', 'snippen-booking' ); ?></h4>
						<button type="button" class="close-modal">&times;</button>
					</div>
					<div class="modal-body" id="booking-info-content"></div>
					<div class="modal-footer">
						<button type="button" class="snippen-modal-footer-close-btn close-modal"><?php esc_html_e( 'Lukk', 'snippen-booking' ); ?></button>
					</div>
				</div>
			</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Check and render Vipps return notice if arriving from Vipps redirect.
	 *
	 * @return string HTML notice or empty string.
	 */
	public static function handle_and_render_vipps_return() {
		if ( empty( $_GET['booking_uuid'] ) || empty( $_GET['payment_provider'] ) || 'vipps' !== $_GET['payment_provider'] ) {
			return '';
		}

		global $wpdb;
		$uuid = sanitize_text_field( wp_unslash( $_GET['booking_uuid'] ) );

		$booking_repo = new \SnippenBooking\Database\Repository\BookingRepository();
		$booking      = $booking_repo->find_by_uuid( $uuid );

		if ( ! $booking ) {
			return '<div class="snippen-vipps-return-notice error"><h4>' . esc_html__( 'Ugyldig reservasjon', 'snippen-booking' ) . '</h4><p>' . esc_html__( 'Fant ikke den forespurte bookingen.', 'snippen-booking' ) . '</p></div>';
		}

		// Extract Vipps reference if stored in payment_notes
		$reference = '';
		if ( ! empty( $booking->payment_notes ) && preg_match( '/snippen-\d+-\d+-\d+/', $booking->payment_notes, $matches ) ) {
			$reference = $matches[0];
		}

		// If booking is pending_payment, verify status directly against Vipps API
		if ( 'pending_payment' === $booking->status && $reference ) {
			$vipps_service = new \SnippenBooking\Service\Vipps\VippsService();
			$payment_info  = $vipps_service->get_booking_payment_status( $reference );

			if ( ! is_wp_error( $payment_info ) && ! empty( $payment_info['state'] ) ) {
				$state = $payment_info['state'];

				if ( 'AUTHORIZED' === $state ) {
					// Capture and confirm
					$capture = $vipps_service->capture_booking_payment( $reference, $booking->price );
					if ( ! is_wp_error( $capture ) ) {
						$table = $wpdb->prefix . 'snippen_bookings';
						$wpdb->update(
							$table,
							array(
								'status'             => 'confirmed',
								'payment_status_id'  => 2, // PAID
								'payment_updated_at' => current_time( 'mysql' ),
								'modified_at'        => current_time( 'mysql' ),
							),
							array( 'id' => $booking->id )
						);
						$booking->status            = 'confirmed';
						$booking->payment_status_id = 2;

						$notification_manager = new \SnippenBooking\Service\Notification\NotificationManager();
						$notification_manager->send_booking_confirmed_notification( (int) $booking->id );
					}
				} elseif ( in_array( $state, array( 'TERMINATED', 'CANCELLED', 'EXPIRED' ), true ) ) {
					$table = $wpdb->prefix . 'snippen_bookings';
					$wpdb->update(
						$table,
						array(
							'status'           => 'cancelled',
							'rejection_reason' => __( 'Vipps-betaling ble avbrutt eller utløpt.', 'snippen-booking' ),
							'modified_at'      => current_time( 'mysql' ),
						),
						array( 'id' => $booking->id )
					);
					$booking->status = 'cancelled';
				}
			}
		}

		if ( 'confirmed' === $booking->status ) {
			$ref_html = $reference ? '<div class="vipps-ref">' . esc_html__( 'Vipps-referanse:', 'snippen-booking' ) . ' ' . esc_html( $reference ) . '</div>' : '';
			return '<div class="snippen-vipps-return-notice success"><h4>' . esc_html__( '✓ Betaling fullført og reservasjon bekreftet!', 'snippen-booking' ) . '</h4><p>' . sprintf(
				/* translators: 1: Booking date, 2: Customer name */
				esc_html__( 'Takk, %2$s! Din reservasjon for %1$s er bekreftet. Bekreftelse er sendt på SMS/e-post.', 'snippen-booking' ),
				esc_html( date_i18n( get_option( 'date_format', 'd.m.Y' ), strtotime( $booking->booking_date ) ) ),
				esc_html( $booking->customer_name )
			) . '</p>' . $ref_html . '</div>';
		}

		if ( 'cancelled' === $booking->status ) {
			return '<div class="snippen-vipps-return-notice cancelled"><h4>' . esc_html__( 'Betaling ble ikke gjennomført', 'snippen-booking' ) . '</h4><p>' . esc_html__( 'Vipps-betalingen ble avbrutt eller utløp. Tidsluken er frigjort, og du kan velge et nytt tidspunkt i kalenderen under.', 'snippen-booking' ) . '</p></div>';
		}

		return '<div class="snippen-vipps-return-notice"><h4>' . esc_html__( 'Betaling behandles...', 'snippen-booking' ) . '</h4><p>' . esc_html__( 'Vi venter på bekreftelse fra Vipps. Du vil motta en bekreftelse så snart betalingen er registrert.', 'snippen-booking' ) . '</p></div>';
	}
}
