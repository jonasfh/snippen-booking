<?php

namespace SnippenBooking;

use SnippenBooking\Assets\AssetLoader;
use SnippenBooking\Database\Install;
use SnippenBooking\Shortcode\BookingShortcode;
use SnippenBooking\Api\AvailabilityApi;
use SnippenBooking\Api\BookingApi;
use SnippenBooking\Admin\AdminLoader;

/**
 * Main plugin class - bootstrapper
 */
class Plugin {

	/**
	 * Initialize the plugin
	 */
	public static function init() {
		// Register activation hook
		register_activation_hook( dirname( __DIR__ ) . '/booking-plugin.php', array( __CLASS__, 'activate' ) );

		// Hook into WordPress init
		add_action( 'init', array( __CLASS__, 'register_hooks' ) );
		add_action( 'admin_init', array( __CLASS__, 'check_for_updates' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Handle plugin activation
	 */
	public static function activate() {
		Install::activate();
	}

	/**
	 * Register all hooks
	 */
	public static function register_hooks() {
		BookingShortcode::register();
		AvailabilityApi::register();
		BookingApi::register();
		AdminLoader::register();
		\SnippenBooking\Api\BookingActionsApi::register();
		\SnippenBooking\Api\UserApi::register();
		\SnippenBooking\Shortcode\AccountConfirmationShortcode::register();

		// Allow tagging pages (required for issue #25)
		register_taxonomy_for_object_type( 'post_tag', 'page' );

		// Render single booking popup in footer if booking_uuid query param is present
		add_action( 'wp_footer', array( __CLASS__, 'render_booking_popup' ) );
	}

	/**
	 * Enqueue assets
	 */
	public static function enqueue_assets() {
		AssetLoader::enqueue();
	}

	/**
	 * Check for database updates
	 */
	public static function check_for_updates() {
		\SnippenBooking\Database\MigrationManager::run();
	}

	/**
	 * Render single booking details popup in footer if requested
	 */
	public static function render_booking_popup() {
		if ( ! isset( $_GET['booking_uuid'] ) ) {
			return;
		}

		$uuid = sanitize_text_field( $_GET['booking_uuid'] );
		if ( empty( $uuid ) ) {
			return;
		}

		global $wpdb;
		$table_bookings = $wpdb->prefix . 'snippen_bookings';
		$table_slots    = $wpdb->prefix . 'snippen_time_slots';
		$table_junction = $wpdb->prefix . 'snippen_bookings_booking_objects';
		$table_objects  = $wpdb->prefix . 'snippen_booking_objects';

		$booking = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT b.*, s.name as slot_name, s.start_time, s.end_time 
				 FROM $table_bookings b 
				 LEFT JOIN $table_slots s ON b.slot_id = s.id 
				 WHERE b.uuid = %s AND b.deleted_at IS NULL",
				$uuid
			)
		);

		// Render the overlay container
		echo '<div class="snippen-booking-modal-overlay" id="snippen-booking-modal">';
		echo '<div class="snippen-booking-modal-content">';
		echo '<button class="snippen-booking-modal-close" onclick="closeSnippenBookingModal()">&times;</button>';

		if ( ! $booking ) {
			// 1. Not found
			echo '<div class="snippen-modal-error-content">';
			echo '<h2>' . esc_html__( 'Booking ikke funnet', 'snippen-booking' ) . '</h2>';
			echo '<p>' . esc_html__( 'Forespurt booking ble ikke funnet eller har blitt slettet.', 'snippen-booking' ) . '</p>';
			echo '</div>';
		} elseif ( ! is_user_logged_in() ) {
			// 2. Not logged in -> show login prompt with redirect
			$current_url = add_query_arg( 'booking_uuid', $uuid, home_url( '/' ) );
			$login_url   = wp_login_url( $current_url );

			echo '<div class="snippen-modal-login-content">';
			echo '<h2>' . esc_html__( 'Logg inn kreves', 'snippen-booking' ) . '</h2>';
			echo '<p>' . esc_html__( 'Du må logge inn for å se denne bookingen.', 'snippen-booking' ) . '</p>';
			echo '<a href="' . esc_url( $login_url ) . '" class="vipps-login-btn">' . esc_html__( 'Logg inn med Vipps', 'snippen-booking' ) . '</a>';
			echo '</div>';
		} else {
			// 3. Logged in -> check permission
			$current_user_id = get_current_user_id();
			$is_admin        = current_user_can( 'manage_options' );
			$is_owner        = intval( $booking->user_id ) === $current_user_id;

			if ( ! $is_admin && ! $is_owner ) {
				echo '<div class="snippen-modal-error-content">';
				echo '<h2>' . esc_html__( 'Ingen tilgang', 'snippen-booking' ) . '</h2>';
				echo '<p>' . esc_html__( 'Du har ikke tilgang til å se denne bookingen.', 'snippen-booking' ) . '</p>';
				echo '</div>';
			} else {
				// Get associated objects/locales
				$objs         = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT o.name 
						 FROM $table_junction bo 
						 JOIN $table_objects o ON bo.booking_object_id = o.id 
						 WHERE bo.booking_id = %d",
						$booking->id
					)
				);
				$object_names = implode( ' og ', $objs );

				// Get status details
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
				$status_class = 'snippen-badge snippen-status-' . $booking->status;

				echo '<div class="snippen-modal-details-content">';
				echo '<h2>' . esc_html__( 'Bookingdetaljer', 'snippen-booking' ) . '</h2>';
				echo '<div class="snippen-modal-badge-wrapper"><span class="' . esc_attr( $status_class ) . '">' . esc_html( $status_label ) . '</span></div>';

				echo '<div class="snippen-booking-details-grid">';

				\SnippenBooking\Service\DoorCodeService::sync_booking_door_code( $booking );

				$door_code_display = '';
				if ( \SnippenBooking\Service\DoorCodeService::is_in_window( $booking ) ) {
					$door_code_display = ! empty( $booking->door_code ) ? esc_html( $booking->door_code ) : esc_html__( 'Ikke satt', 'snippen-booking' );
				} else {
					$door_code_display = '<span style="color:#64748b; font-style:italic;">' . esc_html__( '<Koden er ikke tilgjengelig før nærmere booking start>', 'snippen-booking' ) . '</span>';
				}

				echo '<div class="detail-item"><strong>' . esc_html__( 'Lokale(r)', 'snippen-booking' ) . ':</strong><span>' . esc_html( $object_names ) . '</span></div>';
				echo '<div class="detail-item"><strong>' . esc_html__( 'Dato', 'snippen-booking' ) . ':</strong><span>' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $booking->booking_date ) ) ) . '</span></div>';
				echo '<div class="detail-item"><strong>' . esc_html__( 'Tid', 'snippen-booking' ) . ':</strong><span>' . esc_html( $booking->slot_name ) . '</span></div>';
				echo '<div class="detail-item"><strong>' . esc_html__( 'Navn', 'snippen-booking' ) . ':</strong><span>' . esc_html( $booking->customer_name ) . '</span></div>';
				echo '<div class="detail-item"><strong>' . esc_html__( 'E-post', 'snippen-booking' ) . ':</strong><span>' . esc_html( $booking->customer_email ) . '</span></div>';
				echo '<div class="detail-item"><strong>' . esc_html__( 'Telefon', 'snippen-booking' ) . ':</strong><span>' . esc_html( $booking->customer_phone ?: '-' ) . '</span></div>';
				echo '<div class="detail-item"><strong>' . esc_html__( 'Dørkode', 'snippen-booking' ) . ':</strong><span>' . $door_code_display . '</span></div>';

				if ( ! empty( $booking->description ) ) {
					echo '<div class="detail-item full-width"><strong>' . esc_html__( 'Beskrivelse', 'snippen-booking' ) . ':</strong><span class="detail-desc">' . esc_html( $booking->description ) . '</span></div>';
				}

				echo '<div class="detail-item"><strong>' . esc_html__( 'Pris', 'snippen-booking' ) . ':</strong><span class="detail-price">' . number_format( $booking->price, 0, ',', ' ' ) . ',-</span></div>';
				echo '<div class="detail-item"><strong>' . esc_html__( 'Booket den', 'snippen-booking' ) . ':</strong><span>' . esc_html( date_i18n( get_option( 'date_format' ) . ' H:i', strtotime( $booking->created_at ) ) ) . '</span></div>';

				echo '</div>'; // grid
				echo '</div>'; // details content
			}
		}

		echo '</div>'; // content
		echo '</div>'; // overlay

		// Add simple close script inline
		echo '<script>
		function closeSnippenBookingModal() {
			var url = new URL(window.location.href);
			url.searchParams.delete("booking_uuid");
			window.history.replaceState({}, "", url.pathname + url.search);
			var modal = document.getElementById("snippen-booking-modal");
			if (modal) {
				modal.style.display = "none";
			}
		}
		document.getElementById("snippen-booking-modal").addEventListener("click", function(e) {
			if (e.target === this) {
				closeSnippenBookingModal();
			}
		});
		</script>';
	}
}
