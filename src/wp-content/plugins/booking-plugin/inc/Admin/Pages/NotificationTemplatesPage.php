<?php
/**
 * Notification Templates Admin Page
 *
 * Allows admins to view, edit, and reset notification templates.
 *
 * @package SnippenBooking\Admin\Pages
 */

namespace SnippenBooking\Admin\Pages;

use SnippenBooking\Service\Notification\NotificationTemplateService;

/**
 * Class NotificationTemplatesPage
 */
class NotificationTemplatesPage {

	/**
	 * Template service
	 *
	 * @var NotificationTemplateService
	 */
	private $template_service;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->template_service = new NotificationTemplateService();
	}

	/**
	 * Handle form submissions (PRG pattern)
	 *
	 * @return void
	 */
	public function handle_request() {
		if ( ! current_user_can( 'manage_snippen_bookings' ) ) {
			wp_die( esc_html__( 'Unauthorized access', 'snippen-booking' ) );
		}

		$action = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : '';

		if ( 'save_template' === $action ) {
			check_admin_referer( 'snippen_template_nonce' );

			$event_type = isset( $_POST['event_type'] ) ? sanitize_text_field( wp_unslash( $_POST['event_type'] ) ) : '';
			$channel    = isset( $_POST['channel'] ) ? sanitize_text_field( wp_unslash( $_POST['channel'] ) ) : '';
			$subject    = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
			$body       = isset( $_POST['body'] ) ? wp_kses_post( wp_unslash( $_POST['body'] ) ) : '';

			if ( $event_type && $channel && $body ) {
				$this->template_service->save_template( $event_type, $channel, $subject ?: null, $body );
				add_settings_error(
					'snippen_templates',
					'settings_updated',
					__( 'Template saved successfully.', 'snippen-booking' ),
					'success'
				);
			} else {
				add_settings_error(
					'snippen_templates',
					'invalid_input',
					__( 'Please fill in all required fields.', 'snippen-booking' ),
					'error'
				);
			}

			wp_safe_redirect( add_query_arg( 'page', 'snippen-booking-templates', admin_url( 'admin.php' ) ) );
			exit;
		} elseif ( 'reset_template' === $action ) {
			check_admin_referer( 'snippen_template_nonce' );

			$event_type = isset( $_POST['event_type'] ) ? sanitize_text_field( wp_unslash( $_POST['event_type'] ) ) : '';
			$channel    = isset( $_POST['channel'] ) ? sanitize_text_field( wp_unslash( $_POST['channel'] ) ) : '';

			if ( $event_type && $channel ) {
				$this->template_service->reset_template_to_default( $event_type, $channel );
				add_settings_error(
					'snippen_templates',
					'settings_updated',
					__( 'Template reset to default.', 'snippen-booking' ),
					'success'
				);
			}

			wp_safe_redirect( add_query_arg( 'page', 'snippen-booking-templates', admin_url( 'admin.php' ) ) );
			exit;
		}
	}

	/**
	 * Render the admin page
	 *
	 * @return void
	 */
	public function render() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Notification Templates', 'snippen-booking' ); ?></h1>

			<?php settings_errors( 'snippen_templates' ); ?>

			<p><?php esc_html_e( 'Configure notification templates for different events and channels. Use placeholders like {{user_name}}, {{booking_date}}, etc.', 'snippen-booking' ); ?></p>

			<?php $this->render_placeholders_summary(); ?>

			<div class="snippen-templates-container">
				<?php $this->render_templates(); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render summary of all available placeholders top of page
	 *
	 * @return void
	 */
	private function render_placeholders_summary() {
		$placeholders = $this->template_service->get_all_placeholders();
		if ( empty( $placeholders ) ) {
			return;
		}
		?>
		<div class="snippen-card" style="margin-bottom: 20px; background: #f9f9f9; padding: 15px; border-left: 4px solid #2271b1;">
			<h3 style="margin-top: 0;"><?php esc_html_e( 'Available Placeholders', 'snippen-booking' ); ?></h3>
			<p><?php esc_html_e( 'The following placeholders are available for use in all notification templates:', 'snippen-booking' ); ?></p>
			<ul style="margin: 10px 0; padding-left: 20px; display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 8px;">
				<?php foreach ( $placeholders as $placeholder => $description ) : ?>
					<li><code>{{<?php echo esc_html( $placeholder ); ?>}}</code> - <?php echo esc_html( $description ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}

	/**
	 * Render all templates grouped by event type
	 *
	 * @return void
	 */
	private function render_templates() {
		$all_templates = $this->template_service->get_all_templates();

		// Define readable event names
		$event_names = array(
			'user_activation'      => __( 'Account Activation (Confirmation Code)', 'snippen-booking' ),
			'booking_confirmation' => __( 'Booking Confirmation', 'snippen-booking' ),
			'admin_booking'        => __( 'Admin Booking Alert', 'snippen-booking' ),
			'password_reset'       => __( 'Password Reset (SMS / Email)', 'snippen-booking' ),
		);

		foreach ( $event_names as $event_type => $event_label ) {
			echo '<div class="snippen-card" style="margin-bottom: 30px;">';
			echo '<h2 style="margin-top: 0;">' . esc_html( $event_label ) . '</h2>';

			if ( isset( $all_templates[ $event_type ] ) ) {
				foreach ( $all_templates[ $event_type ] as $channel => $template ) {
					$this->render_template_editor( $event_type, $channel, $template );
				}
			}

			echo '</div>';
		}
	}

	/**
	 * Render a single template editor
	 *
	 * @param string $event_type Event type.
	 * @param string $channel    Channel (sms/email).
	 * @param array  $template   Template data.
	 * @return void
	 */
	private function render_template_editor( string $event_type, string $channel, array $template ) {
		$channel_label = 'sms' === $channel ? __( 'SMS', 'snippen-booking' ) : __( 'Email', 'snippen-booking' );
		$is_default    = $template['is_default'];

		echo '<div style="background: white; border: 1px solid #ddd; padding: 20px; margin-bottom: 15px; border-radius: 4px;">';
		echo '<h3 style="margin-top: 0;">' . esc_html( $channel_label ) . '</h3>';

		if ( $is_default ) {
			echo '<p style="color: #666; font-style: italic; margin-bottom: 15px;">' . esc_html__( '(Default template)', 'snippen-booking' ) . '</p>';
		} else {
			echo '<p style="color: #2271b1; font-style: italic; margin-bottom: 15px;">' . esc_html__( '(Custom template)', 'snippen-booking' ) . '</p>';
		}

		?>
		<form method="post" action="">
			<?php wp_nonce_field( 'snippen_template_nonce' ); ?>
			<input type="hidden" name="action" value="save_template">
			<input type="hidden" name="event_type" value="<?php echo esc_attr( $event_type ); ?>">
			<input type="hidden" name="channel" value="<?php echo esc_attr( $channel ); ?>">

			<?php
			// Only show subject field for email templates
			if ( 'email' === $channel ) {
				?>
				<div class="snippen-form-group" style="margin-bottom: 15px;">
					<label for="subject_<?php echo esc_attr( $event_type . '_' . $channel ); ?>" style="display: block; font-weight: bold; margin-bottom: 5px;">
						<?php esc_html_e( 'Subject', 'snippen-booking' ); ?>
					</label>
					<input 
						type="text" 
						id="subject_<?php echo esc_attr( $event_type . '_' . $channel ); ?>" 
						name="subject" 
						value="<?php echo esc_attr( $template['subject'] ); ?>" 
						style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"
					>
				</div>
				<?php
			}
			?>

			<div class="snippen-form-group" style="margin-bottom: 15px;">
				<label for="body_<?php echo esc_attr( $event_type . '_' . $channel ); ?>" style="display: block; font-weight: bold; margin-bottom: 5px;">
					<?php esc_html_e( 'Message Template', 'snippen-booking' ); ?>
				</label>
				<textarea 
					id="body_<?php echo esc_attr( $event_type . '_' . $channel ); ?>" 
					name="body" 
					rows="6"
					style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace;"
				><?php echo esc_textarea( $template['body'] ); ?></textarea>
				<small style="display: block; margin-top: 5px; color: #666;">
					<?php esc_html_e( 'Use {{placeholder}} syntax for dynamic values. See list above.', 'snippen-booking' ); ?>
				</small>
			</div>

			<div style="display: flex; gap: 10px;">
				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Save Template', 'snippen-booking' ); ?>
				</button>

				<?php if ( ! $is_default ) { ?>
					<button type="submit" name="action" value="reset_template" class="button" onclick="return confirm('<?php esc_attr_e( 'Reset to default template? Your changes will be lost.', 'snippen-booking' ); ?>')">
						<?php esc_html_e( 'Reset to Default', 'snippen-booking' ); ?>
					</button>
				<?php } ?>
			</div>
		</form>
		<?php

		echo '</div>';
	}
}
