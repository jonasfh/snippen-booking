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
			$objects   = $wpdb->get_results( "SELECT * FROM $table_objects WHERE deleted_at IS NULL ORDER BY id ASC" );
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

		// Combine names and info
		$object_names  = wp_list_pluck( $objects, 'name' );
		$combined_name = implode( ' og ', $object_names );

		$descriptions         = wp_list_pluck( $objects, 'description' );
		$combined_description = implode( ' ', array_filter( $descriptions ) );

		// Use first object's link if available
		$info_link = '';
		foreach ( $objects as $obj ) {
			if ( ! empty( $obj->info_link ) ) {
				$info_link = $obj->info_link;
				break;
			}
		}

		$is_logged_in = is_user_logged_in();
		$current_user = wp_get_current_user();
		$user_name    = $is_logged_in ? esc_attr( $current_user->display_name ) : '';
		$user_email   = $is_logged_in ? esc_attr( $current_user->user_email ) : '';
		$user_phone   = $is_logged_in ? get_user_meta( $current_user->ID, 'snippen_phone', true ) : '';

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
			
			<div class="booking-header-section">
				<div class="header-main">
					<h3><?php echo esc_html( $combined_name ); ?></h3>
					<?php if ( $info_link ) : ?>
						<a href="<?php echo esc_url( $info_link ); ?>" class="info-link" target="_blank"><?php esc_html_e( 'Mer info &rarr;', 'snippen-booking' ); ?></a>
					<?php endif; ?>
				</div>
				<?php if ( $combined_description ) : ?>
					<p class="object-summary"><?php echo esc_html( $combined_description ); ?></p>
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
					</div>

					<form id="booking-form" method="post" class="snippen-form">
						<input type="hidden" name="event_date" id="event-date">
						<input type="hidden" name="user_id" id="selected-user-id" value="<?php echo esc_attr( get_current_user_id() ); ?>">
						
						<div class="form-grid">
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
								<input type="text" name="name" id="name" required placeholder="<?php esc_attr_e( 'Fullt navn', 'snippen-booking' ); ?>" value="<?php echo esc_attr( $user_name ); ?>" autocomplete="name" <?php echo Capabilities::can_manage_bookings() ? '' : 'readonly'; ?>>
							</div>
							
							<div class="form-group">
								<label for="email"><?php esc_html_e( 'E-post', 'snippen-booking' ); ?></label>
								<input type="email" name="email" id="email" required placeholder="<?php esc_attr_e( 'navn@eksempel.no', 'snippen-booking' ); ?>" value="<?php echo esc_attr( $user_email ); ?>" autocomplete="email" <?php echo Capabilities::can_manage_bookings() ? '' : 'readonly'; ?>>
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
				</div>
			</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
