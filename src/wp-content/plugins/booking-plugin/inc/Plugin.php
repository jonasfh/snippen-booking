<?php

namespace SnippenBooking;

use SnippenBooking\Assets\AssetLoader;
use SnippenBooking\Database\Install;
use SnippenBooking\Shortcode\BookingShortcode;
use SnippenBooking\Api\AvailabilityApi;
use SnippenBooking\Api\BookingApi;
use SnippenBooking\Admin\AdminLoader;
use SnippenBooking\Helper\Capabilities;

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
		add_action( 'admin_init', array( __CLASS__, 'maybe_redirect_to_setup_wizard' ) );
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
		load_plugin_textdomain( 'snippen-booking', false, dirname( plugin_basename( dirname( __DIR__ ) . '/booking-plugin.php' ) ) . '/languages' );

		BookingShortcode::register();
		AvailabilityApi::register();
		BookingApi::register();
		\SnippenBooking\Api\PricingPreviewApi::register();
		\SnippenBooking\Api\ToggleStatusApi::register();
		AdminLoader::register();
		\SnippenBooking\Api\BookingActionsApi::register();
		\SnippenBooking\Api\UserApi::register();
		\SnippenBooking\Api\UploadPaymentReceiptApi::register();
		\SnippenBooking\Api\UpdatePaymentStatusApi::register();
		\SnippenBooking\Service\PhoneAuthenticationService::register();
		\SnippenBooking\Shortcode\AccountConfirmationShortcode::register();
		\SnippenBooking\Shortcode\BookingListShortcode::register();

		// Allow tagging pages (required for issue #25)
		register_taxonomy_for_object_type( 'post_tag', 'page' );

		// Render single booking popup in footer if booking_uuid query param is present
		add_action( 'wp_footer', array( __CLASS__, 'render_booking_popup' ) );

		// Asynchronous notification sending
		add_action( 'snippen_booking_send_notifications', array( __CLASS__, 'handle_background_notifications' ), 10, 2 );

		// Disable all emails if setting is true (Issue #166)
		if ( 'yes' === get_option( 'snippen_disable_all_emails', 'no' ) ) {
			add_filter( 'pre_wp_mail', '__return_true' );
		}

		// SMTP fallback / configuration hooks.
		if ( 'yes' === get_option( 'snippen_smtp_enabled', 'no' ) ) {
			add_action( 'phpmailer_init', array( __CLASS__, 'configure_smtp' ) );
			add_filter( 'wp_mail_from', array( __CLASS__, 'get_mail_from' ) );
			add_filter( 'wp_mail_from_name', array( __CLASS__, 'get_mail_from_name' ) );
		}

		// Blocks for deleted users (Issue #37)
		add_filter( 'wp_authenticate_user', array( __CLASS__, 'block_deleted_users_login' ), 10, 1 );
		add_filter( 'allow_password_reset', array( __CLASS__, 'block_deleted_users_password_reset' ), 10, 2 );

		// Always redirect 'snippen_resident' to the front page on login (Issue #45)
		add_filter( 'login_redirect', array( __CLASS__, 'redirect_snippen_resident_login' ), 10, 3 );

		// Dynamic capabilities for admin menu (Issue #69)
		add_filter( 'user_has_cap', array( __CLASS__, 'map_menu_capabilities' ), 10, 4 );

		// Intercept snippen_bare requests to serve bare pages for modals
		add_action( 'template_redirect', array( __CLASS__, 'handle_bare_template' ) );
	}

	/**
	 * Handle background notifications sending
	 *
	 * @param int    $booking_id The booking ID.
	 * @param string $uuid       The booking UUID.
	 */
	public static function handle_background_notifications( $booking_id, $uuid ) {
		$notification_manager = new \SnippenBooking\Service\Notification\NotificationManager();
		$notification_manager->send_booking_notifications( $booking_id, $uuid );
	}

	/**
	 * Map virtual capabilities for menu access so admins can see Help without booking capability
	 */
	public static function map_menu_capabilities( $allcaps, $caps, $args, $user ) {
		// Virtual capability to see the top level menu and the manual
		if ( isset( $caps[0] ) && in_array( $caps[0], array( 'view_snippen_booking_menu', 'view_snippen_booking_manual' ), true ) ) {
			if ( ! empty( $allcaps['manage_options'] ) || ! empty( $allcaps[ Capabilities::MANAGE_BOOKINGS ] ) ) {
				$allcaps[ $caps[0] ] = true;
			}
		}
		return $allcaps;
	}

	/**
	 * Block authentication for deleted/deactivated users
	 */
	public static function block_deleted_users_login( $user ) {
		if ( is_wp_error( $user ) ) {
			return $user;
		}
		if ( get_user_meta( $user->ID, 'snippen_user_deleted', true ) === 'yes' ) {
			return new \WP_Error( 'user_deleted', __( 'Kontoen din er slettet eller deaktivert. Vennligst kontakt administrator.', 'snippen-booking' ) );
		}
		return $user;
	}

	/**
	 * Block password reset requests for deleted/deactivated users
	 */
	public static function block_deleted_users_password_reset( $allow, $user_id ) {
		if ( get_user_meta( $user_id, 'snippen_user_deleted', true ) === 'yes' ) {
			return false;
		}
		return $allow;
	}

	/**
	 * Always redirect 'snippen_resident' users to the front page upon login (Issue #45)
	 *
	 * @param string             $redirect_to Where to redirect to.
	 * @param string             $request     The redirect destination requested.
	 * @param \WP_User|\WP_Error $user        WP_User object or WP_Error if login failed.
	 * @return string
	 */
	public static function redirect_snippen_resident_login( $redirect_to, $request, $user ) {
		if ( ! is_wp_error( $user ) && $user instanceof \WP_User ) {
			if ( in_array( 'snippen_resident', (array) $user->roles, true ) ) {
				return home_url( '/' );
			}
		}
		return $redirect_to;
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
				$status_class = 'snippen-badge snippen-status-' . esc_attr( $booking->status );

				$payment_status = \SnippenBooking\Service\PaymentService::get_booking_payment_status( $booking );

				echo '<div class="snippen-modal-details-content">';
				echo '<h2>' . esc_html__( 'Bookingdetaljer', 'snippen-booking' ) . '</h2>';
				echo '<div class="snippen-modal-badge-wrapper"><span class="' . esc_attr( $status_class ) . '">' . esc_html( $status_label ) . '</span></div>';

				echo '<div class="snippen-booking-details-grid">';

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

				echo '<div class="detail-item"><strong>' . esc_html__( 'Lokale(r)', 'snippen-booking' ) . ':</strong><span>' . esc_html( $object_names ) . '</span></div>';
				echo '<div class="detail-item"><strong>' . esc_html__( 'Dato', 'snippen-booking' ) . ':</strong><span>' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $booking->booking_date ) ) ) . '</span></div>';
				echo '<div class="detail-item"><strong>' . esc_html__( 'Tid', 'snippen-booking' ) . ':</strong><span>' . esc_html( $booking->slot_name ) . '</span></div>';
				echo '<div class="detail-item"><strong>' . esc_html__( 'Navn', 'snippen-booking' ) . ':</strong><span>' . esc_html( $booking->customer_name ) . '</span></div>';
				echo '<div class="detail-item"><strong>' . esc_html__( 'E-post', 'snippen-booking' ) . ':</strong><span>' . esc_html( $booking->customer_email ) . '</span></div>';
				echo '<div class="detail-item"><strong>' . esc_html__( 'Telefon', 'snippen-booking' ) . ':</strong><span>' . esc_html( $booking->customer_phone ?: '-' ) . '</span></div>';
			if ( $door_code_enabled ) {
				echo '<div class="detail-item"><strong>' . esc_html__( 'Dørkode', 'snippen-booking' ) . ':</strong><span>' . wp_kses_post( $door_code_display ) . '</span></div>';
			}

			if ( ! empty( $booking->description ) ) {
				echo '<div class="detail-item full-width"><strong>' . esc_html__( 'Beskrivelse', 'snippen-booking' ) . ':</strong><span class="detail-desc">' . esc_html( $booking->description ) . '</span></div>';
			}

				echo '<div class="detail-item"><strong>' . esc_html__( 'Pris', 'snippen-booking' ) . ':</strong><span class="detail-price">' . esc_html( number_format( $booking->price, 0, ',', ' ' ) ) . ',-</span></div>';
				echo '<div class="detail-item"><strong>' . esc_html__( 'Betalingsstatus', 'snippen-booking' ) . ':</strong><span class="payment-status-badge" style="font-weight:600; color:' . ( $payment_status->is_settled ? '#15803d' : '#b45309' ) . ';">' . esc_html( $payment_status->name ) . '</span></div>';

				echo '</div>'; // grid

				// Payment information and receipt upload section
				echo '<div class="snippen-payment-section" style="margin-top:24px; padding:16px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px;">';
				echo '<h3 style="margin-top:0; margin-bottom:12px; font-size:16px;">' . esc_html__( 'Betalingsinformasjon', 'snippen-booking' ) . '</h3>';

				$bank_acc  = get_option( 'snippen_payment_bank_account', '' );
				$vipps_no  = get_option( 'snippen_payment_vipps_number', '' );
				$instructs = get_option( 'snippen_payment_instructions', '' );

			if ( $bank_acc || $vipps_no || $instructs ) {
				echo '<div style="margin-bottom:12px; font-size:14px; line-height:1.5;">';
				if ( $bank_acc ) {
					echo '<div><strong>' . esc_html__( 'Bankkontonr', 'snippen-booking' ) . ':</strong> ' . esc_html( $bank_acc ) . '</div>';
				}
				if ( $vipps_no ) {
					echo '<div><strong>' . esc_html__( 'Vipps', 'snippen-booking' ) . ':</strong> ' . esc_html( $vipps_no ) . '</div>';
				}
				if ( $instructs ) {
					echo '<div style="margin-top:6px; color:#475569;">' . nl2br( esc_html( $instructs ) ) . '</div>';
				}
				echo '</div>';
			}

			if ( ! empty( $booking->payment_receipt_attachment_id ) ) {
				$url = wp_get_attachment_url( $booking->payment_receipt_attachment_id );
				if ( $url ) {
					echo '<div style="margin-bottom:12px;"><strong>' . esc_html__( 'Opplastet kvittering', 'snippen-booking' ) . ':</strong> <a href="' . esc_url( $url ) . '" target="_blank" style="color:#0284c7; text-decoration:underline;">' . esc_html__( 'Vis kvittering', 'snippen-booking' ) . '</a></div>';
				}
			}

			if ( ! $payment_status->is_settled ) {
				echo '<form id="snippen-receipt-upload-form" style="margin-top:12px;">';
				echo '<label style="display:block; font-weight:600; margin-bottom:6px;">' . esc_html__( 'Last opp kvittering / skjermbilde for betaling:', 'snippen-booking' ) . '</label>';
				echo '<input type="file" name="payment_receipt" id="payment_receipt_file" accept="image/*,.pdf" required style="margin-bottom:8px;">';
				echo '<br><button type="submit" class="button button-primary" style="background:#0284c7; border:none; color:#fff; padding:6px 14px; border-radius:4px; cursor:pointer;">' . esc_html__( 'Last opp kvittering', 'snippen-booking' ) . '</button>';
				echo '<div id="snippen-receipt-msg" style="margin-top:8px; font-weight:600;"></div>';
				echo '</form>';

				echo '<script>
					document.getElementById("snippen-receipt-upload-form").addEventListener("submit", function(e) {
						e.preventDefault();
						var fileInput = document.getElementById("payment_receipt_file");
						if (!fileInput.files.length) return;
						var formData = new FormData();
						formData.append("action", "snippen_upload_payment_receipt");
						formData.append("booking_id", "' . intval( $booking->id ) . '");
						formData.append("booking_uuid", "' . esc_js( $booking->uuid ) . '");
						formData.append("payment_receipt", fileInput.files[0]);

						var msgDiv = document.getElementById("snippen-receipt-msg");
						msgDiv.style.color = "#0284c7";
						msgDiv.textContent = "' . esc_js( __( 'Laster opp...', 'snippen-booking' ) ) . '";

						fetch("' . esc_url( admin_url( 'admin-ajax.php' ) ) . '", {
							method: "POST",
							body: formData
						}).then(function(r) { return r.json(); })
						.then(function(res) {
							if (res.success) {
								msgDiv.style.color = "#16a34a";
								msgDiv.textContent = res.data.message;
								setTimeout(function() { window.location.reload(); }, 1500);
							} else {
								msgDiv.style.color = "#dc2626";
								msgDiv.textContent = res.data.message || "' . esc_js( __( 'Feil ved opplasting.', 'snippen-booking' ) ) . '";
							}
						}).catch(function(err) {
							msgDiv.style.color = "#dc2626";
							msgDiv.textContent = "' . esc_js( __( 'Tilkoblingsfeil.', 'snippen-booking' ) ) . '";
						});
					});
					</script>';
			}

				echo '</div>'; // payment section

				echo '</div>'; // details content
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

	/**
	 * Configure PHPMailer to use SMTP
	 *
	 * @param object $phpmailer PHPMailer instance.
	 */
	public static function configure_smtp( $phpmailer ) {
		$phpmailer->isSMTP();
		// phpcs:ignore
		$phpmailer->Host       = get_option( 'snippen_smtp_host', 'smtp.gmail.com' );
		// phpcs:ignore
		$phpmailer->SMTPAuth   = true;
		// phpcs:ignore
		$phpmailer->Port       = intval( get_option( 'snippen_smtp_port', 587 ) );
		// phpcs:ignore
		$phpmailer->Username   = get_option( 'snippen_smtp_user' );
		// phpcs:ignore
		$phpmailer->Password   = get_option( 'snippen_smtp_pass' );
		// phpcs:ignore
		$phpmailer->SMTPSecure = get_option( 'snippen_smtp_encryption', 'tls' );
	}

	/**
	 * Set the custom email sender
	 *
	 * @param string $original_email Original sender email.
	 * @return string
	 */
	public static function get_mail_from( $original_email ) {
		$from_email = get_option( 'snippen_smtp_from_email' );
		return ! empty( $from_email ) ? $from_email : $original_email;
	}

	/**
	 * Set the custom email sender name
	 *
	 * @param string $original_name Original sender name.
	 * @return string
	 */
	public static function get_mail_from_name( $original_name ) {
		$from_name = get_option( 'snippen_smtp_from_name' );
		return ! empty( $from_name ) ? $from_name : $original_name;
	}

	/**
	 * Redirect to setup wizard on first plugin activation (only once)
	 *
	 * @return void
	 */
	public static function maybe_redirect_to_setup_wizard() {
		// Only for users with booking management capability
		if ( ! Capabilities::can_manage_bookings() ) {
			return;
		}

		// Check if we're on a plugin page already
		$page_param = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';
		if ( $page_param && strpos( $page_param, 'snippen-booking' ) === 0 ) {
			return;
		}

		// Don't redirect during bulk plugin activation
		if ( isset( $_GET['activate-multi'] ) ) {
			return;
		}

		// Check if wizard has been completed
		if ( \SnippenBooking\Admin\SetupWizard::is_completed() ) {
			return;
		}

		// Check if there's already setup data
		global $wpdb;
		$object_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}snippen_booking_objects WHERE deleted_at IS NULL" );
		if ( $object_count > 0 ) {
			\SnippenBooking\Admin\SetupWizard::mark_completed();
			return;
		}

		// Redirect to setup wizard
		wp_safe_remote_get(
			add_query_arg(
				'page',
				'snippen-booking-setup-wizard',
				admin_url( 'admin.php' )
			)
		);
		wp_safe_redirect( add_query_arg( 'page', 'snippen-booking-setup-wizard', admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Render bare template for modals
	 */
	public static function handle_bare_template() {
		if ( isset( $_GET['snippen_bare'] ) && (string) $_GET['snippen_bare'] === '1' ) {
			if ( have_posts() ) {
				while ( have_posts() ) {
					the_post();
					echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">';
					echo '<style>body{font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 25px; color: #1e293b; line-height: 1.6;} h1, h2, h3{color: #0f172a; margin-top:0;} iframe {max-width: 100%;}</style>';
					echo '</head><body>';
					the_title( '<h2>', '</h2>' );
					the_content();
					echo '</body></html>';
				}
			}
			exit;
		}
	}
}
