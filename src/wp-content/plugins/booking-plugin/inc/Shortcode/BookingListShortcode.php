<?php
/**
 * Handles booking list shortcode rendering
 *
 * @package SnippenBooking\Shortcode
 */

namespace SnippenBooking\Shortcode;

/**
 * Booking List Shortcode
 */
class BookingListShortcode {

	/**
	 * Register the shortcode
	 */
	public static function register() {
		add_shortcode( 'snippen_booking_list', array( __CLASS__, 'render' ) );
	}

	/**
	 * Render the shortcode
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'login-form' => 0,
			),
			$atts
		);

		$is_logged_in = is_user_logged_in();

		if ( ! $is_logged_in ) {
			// Handle various truthy values for login-form.
			$login_form_enabled = filter_var( $atts['login-form'], FILTER_VALIDATE_BOOLEAN )
				|| '1' === $atts['login-form']
				|| 1 === $atts['login-form']
				|| 'yes' === $atts['login-form']
				|| 'true' === $atts['login-form'];

			if ( $login_form_enabled ) {
				return self::render_login_form();
			}
			return '';
		}

		return self::render_booking_list();
	}

	/**
	 * Render a beautiful, premium login form
	 *
	 * @return string
	 */
	private static function render_login_form() {
		$redirect_url = get_permalink();
		if ( ! $redirect_url ) {
			$redirect_url = home_url( '/' );
		}

		ob_start();
		?>
		<div class="snippen-booking-login-card">
			<div class="login-card-header">
				<h3><?php esc_html_e( 'Logg inn', 'snippen-booking' ); ?></h3>
				<p><?php esc_html_e( 'Logg inn med din beboerkonto for å se dine bookinger.', 'snippen-booking' ); ?></p>
			</div>

			<form name="loginform" id="loginform" action="<?php echo esc_url( site_url( 'wp-login.php', 'login_post' ) ); ?>" method="post" class="snippen-login-form">
				<input type="hidden" name="redirect_to" value="<?php echo esc_url( $redirect_url ); ?>" />
				
				<div class="form-group">
					<label for="user_login"><?php esc_html_e( 'Brukernavn, e-post eller telefonnummer', 'snippen-booking' ); ?></label>
					<input type="text" name="log" id="user_login" class="input" value="" required placeholder="<?php esc_attr_e( 'navn@eksempel.no', 'snippen-booking' ); ?>" autocomplete="username" />
				</div>
				
				<div class="form-group">
					<label for="user_pass"><?php esc_html_e( 'Passord', 'snippen-booking' ); ?></label>
					<input type="password" name="pwd" id="user_pass" class="input" value="" required placeholder="<?php esc_attr_e( 'Ditt passord', 'snippen-booking' ); ?>" autocomplete="current-password" />
				</div>
				
				<div class="form-row-remember" style="display: flex; justify-content: space-between; align-items: center;">
					<label for="rememberme">
						<input name="rememberme" type="checkbox" id="rememberme" value="forever" />
						<?php esc_html_e( 'Husk meg', 'snippen-booking' ); ?>
					</label>
					<a href="<?php echo esc_url( wp_lostpassword_url() ); ?>" class="lost-password-link" style="font-size: 13px; color: #3b82f6; text-decoration: none;">
						<?php esc_html_e( 'Glemt passord?', 'snippen-booking' ); ?>
					</a>
				</div>
				
				<button type="submit" name="wp-submit" id="wp-submit" class="booking-submit">
					<?php esc_html_e( 'Logg inn', 'snippen-booking' ); ?>
				</button>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render the user's booking list
	 *
	 * @return string
	 */
	private static function render_booking_list() {
		global $wpdb;
		$user_id = get_current_user_id();

		$table_bookings = $wpdb->prefix . 'snippen_bookings';
		$table_slots    = $wpdb->prefix . 'snippen_time_slots';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$view = isset( $_GET['booking_view'] ) && 'archive' === $_GET['booking_view'] ? 'archive' : 'upcoming';

		if ( 'archive' === $view ) {
			// Archive view: bookings before CURDATE(), sorted DESC
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$query = $wpdb->prepare(
				"
				SELECT b.*, s.name as slot_name, s.start_time, s.end_time 
				FROM $table_bookings b 
				LEFT JOIN $table_slots s ON b.slot_id = s.id 
				WHERE b.user_id = %d AND b.deleted_at IS NULL AND b.booking_date < CURDATE()
				ORDER BY b.booking_date DESC, s.start_time DESC",
				$user_id
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} else {
			// Upcoming view: bookings today and future (>= CURDATE()), sorted ASC
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$query = $wpdb->prepare(
				"
				SELECT b.*, s.name as slot_name, s.start_time, s.end_time 
				FROM $table_bookings b 
				LEFT JOIN $table_slots s ON b.slot_id = s.id 
				WHERE b.user_id = %d AND b.deleted_at IS NULL AND b.booking_date >= CURDATE()
				ORDER BY b.booking_date ASC, s.start_time ASC",
				$user_id
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$bookings = $wpdb->get_results( $query );

		$current_url  = remove_query_arg( 'booking_view' );
		$upcoming_url = add_query_arg( 'booking_view', 'upcoming', $current_url );
		$archive_url  = add_query_arg( 'booking_view', 'archive', $current_url );

		ob_start();
		?>
		<div class="snippen-booking-list-container">
			<div class="list-header-section">
				<h3><?php esc_html_e( 'Mine Bookinger', 'snippen-booking' ); ?></h3>
				<div class="booking-view-toggle">
					<a href="<?php echo esc_url( $upcoming_url ); ?>" class="view-toggle-btn <?php echo 'upcoming' === $view ? 'active' : ''; ?>">
						<?php esc_html_e( 'Kommende bookinger', 'snippen-booking' ); ?>
					</a>
					<a href="<?php echo esc_url( $archive_url ); ?>" class="view-toggle-btn <?php echo 'archive' === $view ? 'active' : ''; ?>">
						<?php esc_html_e( 'Arkiv', 'snippen-booking' ); ?>
					</a>
				</div>
			</div>

			<?php if ( empty( $bookings ) ) : ?>
				<div class="snippen-empty-bookings-card">
					<span class="empty-icon">
						<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
							<path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
						</svg>
					</span>
					<p>
						<?php
						if ( 'archive' === $view ) {
							esc_html_e( 'Du har ingen tidligere bookinger i arkivet.', 'snippen-booking' );
						} else {
							esc_html_e( 'Du har ingen kommende bookinger.', 'snippen-booking' );
						}
						?>
					</p>
				</div>
			<?php else : ?>
				<div class="booking-compact-list">
					<?php
					foreach ( $bookings as $booking ) {
						self::render_booking_row( $booking );
					}
					?>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render a single booking row in a compact list
	 *
	 * @param object $booking The booking object to render.
	 */
	private static function render_booking_row( $booking ) {
		global $wpdb;
		$table_junction = $wpdb->prefix . 'snippen_bookings_booking_objects';
		$table_objects  = $wpdb->prefix . 'snippen_booking_objects';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
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
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Synchronize/get door code.
		$door_code_enabled = \SnippenBooking\Service\DoorCodeService::is_enabled();
		$door_code_active  = false;
		$door_code_display = '';

		if ( $door_code_enabled ) {
			\SnippenBooking\Service\DoorCodeService::sync_booking_door_code( $booking );
			$door_code_active = \SnippenBooking\Service\DoorCodeService::is_in_window( $booking );

			if ( $door_code_active ) {
				$door_code_display = ! empty( $booking->door_code ) ? esc_html( $booking->door_code ) : esc_html__( 'Ikke satt', 'snippen-booking' );
			} else {
				$door_code_display = esc_html__( '<Koden er ikke tilgjengelig før nærmere booking start>', 'snippen-booking' );
			}
		}

		$status_class = 'snippen-status-' . $booking->status;
		$status_label = '';
		switch ( $booking->status ) {
			case 'pending':
				$status_label = __( 'Venter', 'snippen-booking' );
				break;
			case 'confirmed':
				$status_label = __( 'Bekreftet', 'snippen-booking' );
				break;
			case 'cancelled':
				$status_label = __( 'Avbrutt', 'snippen-booking' );
				break;
			default:
				$status_label = $booking->status;
		}

		$booking_date_formatted = date_i18n( get_option( 'date_format' ), strtotime( $booking->booking_date ) );
		?>
		<div class="booking-compact-row" id="booking-card-<?php echo esc_attr( $booking->id ); ?>">
			<div class="booking-compact-main">
				<div class="booking-compact-date-slot">
					<span class="booking-date"><?php echo esc_html( $booking_date_formatted ); ?></span>
					<span class="booking-slot-name"><?php echo esc_html( $booking->slot_name ); ?></span>
				</div>

				<div class="booking-compact-objects">
					<?php foreach ( $objs as $obj_name ) : ?>
						<span class="snippen-tag"><?php echo esc_html( $obj_name ); ?></span>
					<?php endforeach; ?>
				</div>

				<?php if ( ! empty( $booking->description ) ) : ?>
					<div class="booking-compact-desc" title="<?php echo esc_attr( $booking->description ); ?>">
						<?php echo esc_html( $booking->description ); ?>
					</div>
				<?php endif; ?>

				<div class="booking-compact-meta">
					<span class="booking-price"><?php echo esc_html( number_format( $booking->price, 0, ',', ' ' ) ); ?>,-</span>
					<span class="snippen-badge <?php echo esc_attr( $status_class ); ?>">
						<?php echo esc_html( $status_label ); ?>
					</span>
				</div>
			</div>

			<?php if ( $door_code_enabled || 'cancelled' !== $booking->status ) : ?>
				<div class="booking-compact-sub">
					<?php if ( $door_code_enabled ) : ?>
						<div class="booking-door-code-badge <?php echo $door_code_active ? 'active-code' : 'hidden-code'; ?>">
							<span class="label"><?php esc_html_e( 'Dørkode:', 'snippen-booking' ); ?></span>
							<span class="value">
								<?php if ( $door_code_active ) : ?>
									<span class="lock-icon">🔓</span> <strong><?php echo esc_html( $door_code_display ); ?></strong>
								<?php else : ?>
									<span class="lock-icon">🔒</span> <span class="code-unavailable"><?php echo esc_html( $door_code_display ); ?></span>
								<?php endif; ?>
							</span>
						</div>
					<?php endif; ?>

					<?php if ( 'cancelled' !== $booking->status && strtotime( $booking->booking_date ) >= strtotime( 'today' ) ) : ?>
						<div class="booking-compact-actions">
							<button class="snippen-btn-cancel-booking cancel" data-id="<?php echo esc_attr( $booking->id ); ?>" title="<?php esc_attr_e( 'Avbryt booking', 'snippen-booking' ); ?>">
								<?php esc_html_e( 'Avbryt booking', 'snippen-booking' ); ?>
							</button>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}
