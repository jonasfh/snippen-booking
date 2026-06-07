<?php

namespace SnippenBooking\Admin;

use SnippenBooking\Helper\Capabilities;

/**
 * Setup Wizard Admin Page
 */
class SetupWizardPage {

	const PAGE_SLUG = 'snippen-booking-setup-wizard';

	/**
	 * Register the setup wizard page
	 */
	public static function register() {
		add_submenu_page(
			'snippen-booking',
			__( 'Setup Wizard', 'snippen-booking' ),
			__( 'Setup Wizard', 'snippen-booking' ),
			Capabilities::MANAGE_BOOKINGS,
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Render the setup wizard page
	 */
	public static function render_page() {
		// Handle form submissions
		if ( isset( $_POST['action'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'snippen_booking_wizard' ) ) {
			$wizard_action = sanitize_text_field( wp_unslash( $_POST['action'] ) );
			if ( $wizard_action === 'create_starter_setup' ) {
				$result       = SetupWizard::create_starter_setup();
				$message_type = $result['success'] ? 'success' : 'error';
				$message      = $result['message'];
			}

			if ( $wizard_action === 'create_starter_setup_v2' ) {
				$result       = SetupWizard::create_starter_setup_v2();
				$message_type = $result['success'] ? 'success' : 'error';
				$message      = $result['message'];
			}

			if ( $wizard_action === 'skip_wizard' ) {
				SetupWizard::mark_completed();
				$message_type = 'info';
				$message      = __( 'Setup wizard skipped', 'snippen-booking' );
			}
		}

		$wizard_completed = SetupWizard::is_completed();
		$has_objects      = (bool) self::get_object_count();
		?>

		<div class="wrap snippen-booking-wizard">
			<h1><?php echo esc_html( __( 'Snippen Booking Setup Wizard', 'snippen-booking' ) ); ?></h1>

			<?php if ( ! empty( $message ) ) : ?>
				<div class="notice notice-<?php echo esc_attr( $message_type ); ?> is-dismissible">
					<p><?php echo esc_html( $message ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( $wizard_completed && $has_objects ) : ?>
				<!-- Wizard Already Completed -->
				<div class="card">
					<h2><?php esc_html_e( 'Setup Already Completed', 'snippen-booking' ); ?></h2>
					<p><?php esc_html_e( 'Your plugin is already configured with booking objects, time slots, and pricing. You can re-run the wizard if you need to recreate the starter setup.', 'snippen-booking' ); ?></p>

					<form method="post" style="margin-top: 20px;">
						<?php wp_nonce_field( 'snippen_booking_wizard' ); ?>
						<input type="hidden" name="action" value="reset_wizard">
						<button type="submit" class="button button-secondary" onclick="return confirm('<?php esc_attr_e( 'This will reset the wizard. Are you sure?', 'snippen-booking' ); ?>')">
							<?php esc_html_e( 'Re-run Setup Wizard', 'snippen-booking' ); ?>
						</button>
					</form>
				</div>
			<?php elseif ( $has_objects ) : ?>
				<!-- Existing Setup -->
				<div class="card">
					<h2><?php esc_html_e( 'Setup Completed', 'snippen-booking' ); ?></h2>
					<p><?php esc_html_e( 'Your plugin already has booking configuration. You can manage it in the admin settings.', 'snippen-booking' ); ?></p>
				</div>
			<?php else : ?>
				<!-- Welcome Screen -->
				<div class="card">
					<h2><?php esc_html_e( 'Velkommen til Snippen Booking', 'snippen-booking' ); ?></h2>
					<p><?php esc_html_e( 'Denne veiviseren hjelper deg å komme i gang med et forhåndsdefinert oppsett. Velg hvilken variant som passer ditt behov best:', 'snippen-booking' ); ?></p>

					<h2 class="nav-tab-wrapper" style="margin-top: 20px;">
						<a href="#variant-1" class="nav-tab nav-tab-active" id="tab-variant-1"><?php esc_html_e( 'Standard Oppsett (Fleksibelt)', 'snippen-booking' ); ?></a>
						<a href="#variant-2" class="nav-tab" id="tab-variant-2"><?php esc_html_e( 'Enkelt Oppsett (Dag/Kveld)', 'snippen-booking' ); ?></a>
					</h2>

					<div id="content-variant-1" class="wizard-tab-content" style="padding: 20px 0;">
						<p><?php esc_html_e( 'Dette oppsettet er best for lokaler med fleksible behov og kortere leieperioder på hverdager.', 'snippen-booking' ); ?></p>
						<ul style="list-style: disc; margin-left: 20px;">
							<li><?php esc_html_e( '2 booking-objekter: Festsalen og Peisestuen', 'snippen-booking' ); ?></li>
							<li><?php esc_html_e( 'Timebaserte blokker mandag til fredag (kl. 08-23)', 'snippen-booking' ); ?></li>
							<li><?php esc_html_e( 'Helg og helligdager har kun Dag (08-16) og Kveld (16-23)', 'snippen-booking' ); ?></li>
							<li><?php esc_html_e( 'Egne prisregler for hverdager, helger og helligdager', 'snippen-booking' ); ?></li>
						</ul>
						<form method="post" style="margin-top: 20px;">
							<?php wp_nonce_field( 'snippen_booking_wizard' ); ?>
							<button type="submit" name="action" value="create_starter_setup" class="button button-primary button-large">
								<?php esc_html_e( 'Installer Standard Oppsett', 'snippen-booking' ); ?>
							</button>
						</form>
					</div>

					<div id="content-variant-2" class="wizard-tab-content" style="display: none; padding: 20px 0;">
						<p><?php esc_html_e( 'Dette oppsettet er mer likt den gamle versjonen, hvor man kun har faste, lange bolker hver dag.', 'snippen-booking' ); ?></p>
						<ul style="list-style: disc; margin-left: 20px;">
							<li><?php esc_html_e( '2 booking-objekter: Festsalen og Peisestuen', 'snippen-booking' ); ?></li>
							<li><?php esc_html_e( 'Kun 2 tidsblokker per dag (alle dager): Dag (08-16) og Kveld (16-23)', 'snippen-booking' ); ?></li>
							<li><?php esc_html_e( 'Enkle prisregler med ulik pris for ukedager, helger og helligdager', 'snippen-booking' ); ?></li>
						</ul>
						<form method="post" style="margin-top: 20px;">
							<?php wp_nonce_field( 'snippen_booking_wizard' ); ?>
							<button type="submit" name="action" value="create_starter_setup_v2" class="button button-primary button-large">
								<?php esc_html_e( 'Installer Enkelt Oppsett', 'snippen-booking' ); ?>
							</button>
						</form>
					</div>

					<hr style="margin: 30px 0;">
					<p><strong><?php esc_html_e( 'Vil du heller sette opp alt selv fra bunnen av?', 'snippen-booking' ); ?></strong></p>
					<form method="post" style="margin-top: 10px;">
						<?php wp_nonce_field( 'snippen_booking_wizard' ); ?>
						<button type="submit" name="action" value="skip_wizard" class="button">
							<?php esc_html_e( 'Hopp over veiviseren', 'snippen-booking' ); ?>
						</button>
					</form>
				</div>

				<script>
					document.addEventListener('DOMContentLoaded', function() {
						var tab1 = document.getElementById('tab-variant-1');
						var tab2 = document.getElementById('tab-variant-2');
						var content1 = document.getElementById('content-variant-1');
						var content2 = document.getElementById('content-variant-2');

						if (tab1 && tab2 && content1 && content2) {
							tab1.addEventListener('click', function(e) {
								e.preventDefault();
								tab1.classList.add('nav-tab-active');
								tab2.classList.remove('nav-tab-active');
								content1.style.display = 'block';
								content2.style.display = 'none';
							});

							tab2.addEventListener('click', function(e) {
								e.preventDefault();
								tab2.classList.add('nav-tab-active');
								tab1.classList.remove('nav-tab-active');
								content2.style.display = 'block';
								content1.style.display = 'none';
							});
						}
					});
				</script>
			<?php endif; ?>
		</div>

		<style>
			.snippen-booking-wizard h1 {
				margin-bottom: 20px;
			}

			.snippen-booking-wizard .card {
				border: 1px solid #ccc;
				padding: 20px;
				margin-bottom: 20px;
				background: #f9f9f9;
			}

			.snippen-booking-wizard .card h2 {
				margin-top: 0;
			}

			.snippen-booking-wizard .card h3 {
				margin-top: 15px;
			}
		</style>
		<?php
	}

	/**
	 * Get count of booking objects
	 */
	private static function get_object_count() {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}snippen_booking_objects WHERE deleted_at IS NULL" );
	}
}
