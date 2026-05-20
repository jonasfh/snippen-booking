<?php

namespace SnippenBooking\Admin;

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
			'manage_options',
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
			if ( $_POST['action'] === 'create_starter_setup' ) {
				$result = SetupWizard::create_starter_setup();
				$message_type = $result['success'] ? 'success' : 'error';
				$message = $result['message'];
			}

			if ( $_POST['action'] === 'skip_wizard' ) {
				SetupWizard::mark_completed();
				$message_type = 'info';
				$message = __( 'Setup wizard skipped', 'snippen-booking' );
			}
		}

		$wizard_completed = SetupWizard::is_completed();
		$has_objects = (bool) self::get_object_count();
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
					<h2><?php esc_html_e( 'Welcome to Snippen Booking', 'snippen-booking' ); ?></h2>
					<p><?php esc_html_e( 'This setup wizard will help you configure the plugin with a starter setup including booking objects, time slots, and pricing.', 'snippen-booking' ); ?></p>

					<h3><?php esc_html_e( 'What you can do:', 'snippen-booking' ); ?></h3>
					<ul style="list-style: disc; margin-left: 20px;">
						<li><?php esc_html_e( 'Create booking objects (e.g., rooms, venues)', 'snippen-booking' ); ?></li>
						<li><?php esc_html_e( 'Set up time slots for availability', 'snippen-booking' ); ?></li>
						<li><?php esc_html_e( 'Configure pricing models', 'snippen-booking' ); ?></li>
					</ul>

					<p style="margin-top: 20px;"><strong><?php esc_html_e( 'You can also skip this wizard and configure everything manually later.', 'snippen-booking' ); ?></strong></p>

					<form method="post" style="margin-top: 20px;">
						<?php wp_nonce_field( 'snippen_booking_wizard' ); ?>

						<div style="display: flex; gap: 10px;">
							<button type="submit" name="action" value="create_starter_setup" class="button button-primary">
								<?php esc_html_e( 'Create Starter Setup', 'snippen-booking' ); ?>
							</button>

							<button type="submit" name="action" value="skip_wizard" class="button">
								<?php esc_html_e( 'Skip for Now', 'snippen-booking' ); ?>
							</button>
						</div>
					</form>
				</div>
			<?php endif; ?>

			<!-- Info Section -->
			<div class="card" style="margin-top: 20px;">
				<h3><?php esc_html_e( 'About Starter Setup', 'snippen-booking' ); ?></h3>
				<p><?php esc_html_e( 'The starter setup includes:', 'snippen-booking' ); ?></p>
				<ul style="list-style: disc; margin-left: 20px;">
					<li><?php esc_html_e( '2 sample booking objects: Festsalen and Peisestuen', 'snippen-booking' ); ?></li>
					<li><?php esc_html_e( '3 time slots: Hele dagen, Formiddag, Ettermiddag', 'snippen-booking' ); ?></li>
					<li><?php esc_html_e( 'Pricing for weekdays, weekends, and holidays', 'snippen-booking' ); ?></li>
				</ul>
				<p><?php esc_html_e( 'You can edit or delete these entries later from the plugin settings.', 'snippen-booking' ); ?></p>
			</div>
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
