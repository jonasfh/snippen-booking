<?php
/**
 * Notification Templates Admin Page
 *
 * Allows admins to view, edit, create, and delete notification templates.
 *
 * @package SnippenBooking\Admin\Pages
 */

namespace SnippenBooking\Admin\Pages;

use SnippenBooking\Service\Notification\NotificationTemplateService;
use SnippenBooking\Service\Notification\PlaceholderRegistry;

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

		$action     = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : '';
		$repository = $this->template_service->get_repository();

		if ( 'save_template' === $action || 'create_template' === $action ) {
			check_admin_referer( 'snippen_template_nonce' );

			$template_id  = isset( $_POST['template_id'] ) ? (int) $_POST['template_id'] : 0;
			$name         = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
			$event_type   = isset( $_POST['event_type'] ) ? sanitize_text_field( wp_unslash( $_POST['event_type'] ) ) : '';
			$channel      = isset( $_POST['channel'] ) ? sanitize_text_field( wp_unslash( $_POST['channel'] ) ) : 'email';
			$subject      = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
			$body         = isset( $_POST['body'] ) ? wp_kses_post( wp_unslash( $_POST['body'] ) ) : '';
			$connected_to = ! empty( $event_type ) ? $event_type : null;

			if ( empty( $name ) ) {
				$name = ! empty( $event_type )
					? sprintf( '%s (%s)', ucfirst( str_replace( array( '_', '-' ), ' ', $event_type ) ), strtoupper( $channel ) )
					: sprintf( __( 'Manuell mal (%s)', 'snippen-booking' ), strtoupper( $channel ) );
			}

			if ( $body ) {
				$registry          = $this->template_service->get_registry();
				$validation_errors = array_merge(
					$registry->validate_template( $subject ?: '', $event_type ?: '' ),
					$registry->validate_template( $body, $event_type ?: '' )
				);

				if ( ! empty( $validation_errors ) ) {
					foreach ( $validation_errors as $err ) {
						error_log( 'Snippen Booking Template Validation Error: ' . $err );
						add_settings_error( 'snippen_templates', 'invalid_placeholder', $err, 'error' );
					}
				} else {
					if ( $template_id > 0 ) {
						$updated = $repository->update(
							$template_id,
							array(
								'name'         => $name,
								'type'         => $channel,
								'title'        => 'email' === $channel ? $subject : null,
								'message'      => $body,
								'connected_to' => $connected_to,
							)
						);
						if ( $updated ) {
							error_log( sprintf( 'Snippen Booking Template updated successfully: ID %d', $template_id ) );
							add_settings_error( 'snippen_templates', 'settings_updated', __( 'Template updated successfully.', 'snippen-booking' ), 'success' );
						} else {
							error_log( sprintf( 'Snippen Booking Template update failed: ID %d', $template_id ) );
							add_settings_error( 'snippen_templates', 'update_failed', __( 'Could not update template. Check for duplicate connected_to constraints.', 'snippen-booking' ), 'error' );
						}
					} else {
						$created_id = $repository->create(
							array(
								'name'         => $name,
								'type'         => $channel,
								'title'        => 'email' === $channel ? $subject : null,
								'message'      => $body,
								'connected_to' => $connected_to,
							)
						);
						if ( $created_id ) {
							error_log( sprintf( 'Snippen Booking Template created successfully: ID %d', $created_id ) );
							add_settings_error( 'snippen_templates', 'settings_updated', __( 'Template created successfully.', 'snippen-booking' ), 'success' );
							$template_id = $created_id;
						} else {
							error_log( 'Snippen Booking Template create failed: duplicate constraint' );
							add_settings_error( 'snippen_templates', 'create_failed', __( 'Could not create template. Only one template per channel is allowed per connected event.', 'snippen-booking' ), 'error' );
						}
					}
				}
			} else {
				add_settings_error( 'snippen_templates', 'invalid_input', __( 'Please fill in all required fields.', 'snippen-booking' ), 'error' );
			}

			// Persist settings errors across PRG redirect
			$errors = get_settings_errors( 'snippen_templates' );
			if ( ! empty( $errors ) ) {
				set_transient( 'snippen_templates_errors', $errors, 60 );
			}

			$redirect_url = $template_id > 0
				? add_query_arg(
					array(
						'page' => 'snippen-booking-templates',
						'edit' => $template_id,
					),
					admin_url( 'admin.php' )
				)
				: add_query_arg( 'page', 'snippen-booking-templates', admin_url( 'admin.php' ) );

			wp_safe_redirect( $redirect_url );
			exit;

		} elseif ( 'delete_template' === $action ) {
			check_admin_referer( 'snippen_template_nonce' );
			$template_id = isset( $_REQUEST['template_id'] ) ? (int) $_REQUEST['template_id'] : 0;

			if ( $template_id > 0 ) {
				$repository->delete( $template_id );
				add_settings_error( 'snippen_templates', 'settings_updated', __( 'Template deleted successfully.', 'snippen-booking' ), 'success' );
			}

			$errors = get_settings_errors( 'snippen_templates' );
			if ( ! empty( $errors ) ) {
				set_transient( 'snippen_templates_errors', $errors, 60 );
			}

			wp_safe_redirect( add_query_arg( 'page', 'snippen-booking-templates', admin_url( 'admin.php' ) ) );
			exit;

		} elseif ( 'reset_template' === $action ) {
			check_admin_referer( 'snippen_template_nonce' );
			$event_type = isset( $_POST['event_type'] ) ? sanitize_text_field( wp_unslash( $_POST['event_type'] ) ) : '';
			$channel    = isset( $_POST['channel'] ) ? sanitize_text_field( wp_unslash( $_POST['channel'] ) ) : '';

			if ( $event_type && $channel ) {
				$this->template_service->reset_template_to_default( $event_type, $channel );
				add_settings_error( 'snippen_templates', 'settings_updated', __( 'Template reset to default.', 'snippen-booking' ), 'success' );
			}

			$errors = get_settings_errors( 'snippen_templates' );
			if ( ! empty( $errors ) ) {
				set_transient( 'snippen_templates_errors', $errors, 60 );
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
		// Ensure default templates exist in DB and clean up any duplicates
		$repository = $this->template_service->get_repository();
		$repository->seed_defaults();

		// Retrieve transient settings errors from PRG redirect
		$transient_errors = get_transient( 'snippen_templates_errors' );
		if ( $transient_errors && is_array( $transient_errors ) ) {
			delete_transient( 'snippen_templates_errors' );
			foreach ( $transient_errors as $err ) {
				add_settings_error( 'snippen_templates', $err['code'], $err['message'], $err['type'] );
			}
		}

		$edit_id       = isset( $_GET['edit'] ) ? (int) $_GET['edit'] : 0;
		$is_new_action = ( isset( $_GET['action'] ) && 'new' === $_GET['action'] ) || isset( $_GET['new_template'] );
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Notification Templates', 'snippen-booking' ); ?></h1>
			<a href="
			<?php
			echo esc_url(
				add_query_arg(
					array(
						'page'   => 'snippen-booking-templates',
						'action' => 'new',
					),
					admin_url( 'admin.php' )
				)
			);
			?>
						" class="page-title-action">
				<?php esc_html_e( 'Add New Template', 'snippen-booking' ); ?>
			</a>

			<?php settings_errors( 'snippen_templates' ); ?>

			<p><?php esc_html_e( 'Configure notification templates for different events and channels. Placeholders are validated against the central registry.', 'snippen-booking' ); ?></p>

			<?php
			if ( $edit_id > 0 || $is_new_action ) {
				$this->render_edit_form( $edit_id );
			} else {
				$this->render_placeholders_summary();
				$this->render_templates_table();
			}
			?>
		</div>
		<script>
			function snippenInsertPlaceholder(targetId, placeholder) {
				var textarea = document.getElementById(targetId);
				if (!textarea) return;
				var start = textarea.selectionStart || 0;
				var end = textarea.selectionEnd || 0;
				var text = textarea.value;
				var tag = '{{' + placeholder + '}}';
				textarea.value = text.substring(0, start) + tag + text.substring(end);
				textarea.focus();
				textarea.selectionStart = textarea.selectionEnd = start + tag.length;
			}

			function snippenUpdatePlaceholderStates(selectEl, containerId) {
				var val = selectEl.value;
				var container = document.getElementById(containerId);
				if (!container) return;

				var normMap = {
					'account-activation': 'user_activation',
					'booking-confirmation': 'booking_confirmation',
					'admin-booking-alert': 'admin_booking',
					'password-reset': 'password_reset',
					'payment-reminder': 'payment_reminder',
					'payment-receipt-uploaded': 'payment_receipt_uploaded',
					'booking-confirmed': 'booking_confirmed',
					'payment-received': 'payment_received'
				};
				var normVal = normMap[val] || val;

				var chips = container.querySelectorAll('.snippen-placeholder-chip');
				chips.forEach(function(chip) {
					var contexts = chip.getAttribute('data-contexts') ? chip.getAttribute('data-contexts').split(',') : [];
					if (!normVal || contexts.indexOf(normVal) !== -1 || contexts.indexOf(val) !== -1) {
						chip.style.opacity = '1';
						chip.style.filter = 'none';
						chip.style.pointerEvents = 'auto';
						chip.classList.remove('disabled');
					} else {
						chip.style.opacity = '0.35';
						chip.style.filter = 'grayscale(1)';
						chip.style.pointerEvents = 'none';
						chip.classList.add('disabled');
					}
				});
			}
		</script>
		<?php
	}

	/**
	 * Render table listing of all notification templates
	 *
	 * @return void
	 */
	private function render_templates_table() {
		$repository = $this->template_service->get_repository();
		$templates  = $repository->get_all();
		?>
		<div class="snippen-card" style="margin-top: 20px;">
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'snippen-booking' ); ?></th>
						<th><?php esc_html_e( 'Type', 'snippen-booking' ); ?></th>
						<th><?php esc_html_e( 'Connected To', 'snippen-booking' ); ?></th>
						<th><?php esc_html_e( 'Subject / Title', 'snippen-booking' ); ?></th>
						<th><?php esc_html_e( 'Created', 'snippen-booking' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'snippen-booking' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $templates ) ) : ?>
						<tr>
							<td colspan="6"><?php esc_html_e( 'No notification templates found in database.', 'snippen-booking' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $templates as $tpl ) : ?>
							<tr>
								<td><strong><a href="
								<?php
								echo esc_url(
									add_query_arg(
										array(
											'page' => 'snippen-booking-templates',
											'edit' => $tpl->id,
										),
										admin_url( 'admin.php' )
									)
								);
								?>
														"><?php echo esc_html( $tpl->name ); ?></a></strong></td>
								<td><span class="badge" style="text-transform: uppercase; font-weight: bold; background: #e0e0e0; padding: 2px 6px; border-radius: 3px; font-size: 11px;"><?php echo esc_html( $tpl->type ); ?></span></td>
								<td>
									<?php if ( ! empty( $tpl->connected_to ) ) : ?>
										<code><?php echo esc_html( $tpl->connected_to ); ?></code>
									<?php else : ?>
										<em style="color: #888;"><?php esc_html_e( '(Manual / Unconnected)', 'snippen-booking' ); ?></em>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $tpl->title ?: '-' ); ?></td>
								<td><?php echo esc_html( date_i18n( 'Y-m-d H:i', strtotime( $tpl->created_at ) ) ); ?></td>
								<td>
									<a href="
									<?php
									echo esc_url(
										add_query_arg(
											array(
												'page' => 'snippen-booking-templates',
												'edit' => $tpl->id,
											),
											admin_url( 'admin.php' )
										)
									);
									?>
												" class="button button-small">
										<?php esc_html_e( 'Edit', 'snippen-booking' ); ?>
									</a>
									<form method="post" style="display: inline-block;" onsubmit="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this template?', 'snippen-booking' ); ?>');">
										<?php wp_nonce_field( 'snippen_template_nonce' ); ?>
										<input type="hidden" name="action" value="delete_template">
										<input type="hidden" name="template_id" value="<?php echo esc_attr( $tpl->id ); ?>">
										<button type="submit" class="button button-small button-link-delete" style="color: #a00;">
											<?php esc_html_e( 'Delete', 'snippen-booking' ); ?>
										</button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render edit or add form for a notification template
	 *
	 * @param int $template_id ID of template to edit or 0 for new template.
	 * @return void
	 */
	private function render_edit_form( int $template_id ) {
		$repository = $this->template_service->get_repository();
		$template   = $template_id > 0 ? $repository->find( $template_id ) : null;
		$registry   = $this->template_service->get_registry();
		$all_ph     = $registry->get_registered_placeholders();

		$name         = $template ? $template->name : '';
		$type         = $template ? $template->type : 'email';
		$title        = $template ? $template->title : '';
		$message      = $template ? $template->message : '';
		$connected_to = $template ? ( $template->connected_to ?? '' ) : '';

		$connected_options = array(
			''                         => __( '-- Ingenting (Manuell mal) --', 'snippen-booking' ),
			'account-activation'       => __( 'account-activation (Kontoaktivering)', 'snippen-booking' ),
			'booking-confirmation'     => __( 'booking-confirmation (Bookingbekreftelse)', 'snippen-booking' ),
			'admin-booking-alert'      => __( 'admin-booking-alert (Admin bookingvarsel)', 'snippen-booking' ),
			'password-reset'           => __( 'password-reset (Tilbakestill passord)', 'snippen-booking' ),
			'payment-reminder'         => __( 'payment-reminder (Betalingspurring)', 'snippen-booking' ),
			'payment-receipt-uploaded' => __( 'payment-receipt-uploaded (Opplastet kvittering varsel)', 'snippen-booking' ),
			'booking-confirmed'        => __( 'booking-confirmed (Reservasjon bekreftet)', 'snippen-booking' ),
			'payment-received'         => __( 'payment-received (Betalingsstatus: Betalt)', 'snippen-booking' ),
		);
		?>
		<div class="snippen-card" style="margin-top: 20px; background: white; padding: 20px; border: 1px solid #ccc; border-radius: 4px;">
			<h2>
				<?php echo $template ? esc_html__( 'Edit Notification Template', 'snippen-booking' ) : esc_html__( 'Add New Notification Template', 'snippen-booking' ); ?>
			</h2>

			<form method="post" action="">
				<?php wp_nonce_field( 'snippen_template_nonce' ); ?>
				<input type="hidden" name="action" value="<?php echo $template ? 'save_template' : 'create_template'; ?>">
				<input type="hidden" name="template_id" value="<?php echo esc_attr( $template_id ); ?>">

				<table class="form-table">
					<tr>
						<th scope="row"><label for="name"><?php esc_html_e( 'Template Name', 'snippen-booking' ); ?></label></th>
						<td>
							<input type="text" name="name" id="name" class="regular-text" value="<?php echo esc_attr( $name ); ?>" required>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="type"><?php esc_html_e( 'Type / Channel', 'snippen-booking' ); ?></label></th>
						<td>
							<select name="channel" id="type">
								<option value="email" <?php selected( $type, 'email' ); ?>><?php esc_html_e( 'Email', 'snippen-booking' ); ?></option>
								<option value="sms" <?php selected( $type, 'sms' ); ?>><?php esc_html_e( 'SMS', 'snippen-booking' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="event_type"><?php esc_html_e( 'Connected To (Hendelse)', 'snippen-booking' ); ?></label></th>
						<td>
							<select name="event_type" id="event_type" onchange="snippenUpdatePlaceholderStates(this, 'placeholder-chips-container')">
								<?php foreach ( $connected_options as $val => $label ) : ?>
									<option value="<?php echo esc_attr( $val ); ?>" <?php selected( PlaceholderRegistry::normalize_context( $connected_to ), PlaceholderRegistry::normalize_context( $val ) ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Select feature connected to, or leave empty for manual templates. Max one SMS and one Email template per connected event.', 'snippen-booking' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="subject"><?php esc_html_e( 'Subject / Title', 'snippen-booking' ); ?></label></th>
						<td>
							<input type="text" name="subject" id="subject" class="regular-text" value="<?php echo esc_attr( $title ); ?>">
							<p class="description"><?php esc_html_e( 'Used for Email subject line (can be left empty for SMS).', 'snippen-booking' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="body_message"><?php esc_html_e( 'Message Template', 'snippen-booking' ); ?></label></th>
						<td>
							<textarea name="body" id="body_message" rows="8" class="large-text code"><?php echo esc_textarea( $message ); ?></textarea>

							<div style="margin-top: 12px; background: #f9f9f9; padding: 12px; border: 1px solid #ddd; border-radius: 4px;">
								<strong style="display: block; margin-bottom: 6px; font-size: 13px;"><?php esc_html_e( 'Available Placeholders (Click to insert):', 'snippen-booking' ); ?></strong>
								<div id="placeholder-chips-container" style="display: flex; flex-wrap: wrap; gap: 6px;">
									<?php
									$norm_conn = PlaceholderRegistry::normalize_context( $connected_to );
									foreach ( $all_ph as $ph_name => $ph_def ) :
										$is_allowed = empty( $norm_conn ) || in_array( $norm_conn, $ph_def['connected_to'], true );
										$chip_style = $is_allowed ? 'opacity: 1;' : 'opacity: 0.35; filter: grayscale(1); pointer-events: none;';
										?>
										<button 
											type="button" 
											class="button button-small snippen-placeholder-chip <?php echo $is_allowed ? '' : 'disabled'; ?>" 
											style="<?php echo esc_attr( $chip_style ); ?>"
											data-contexts="<?php echo esc_attr( implode( ',', $ph_def['connected_to'] ) ); ?>"
											onclick="snippenInsertPlaceholder('body_message', '<?php echo esc_js( $ph_name ); ?>')"
											title="<?php echo esc_attr( $ph_def['description'] ); ?>"
										>
											+ {{<?php echo esc_html( $ph_name ); ?>}}
										</button>
									<?php endforeach; ?>
								</div>
								<small style="display: block; margin-top: 6px; color: #666; font-size: 11px;">
									<?php esc_html_e( 'Placeholders disabled (greyed out) are not available for the selected connected_to event.', 'snippen-booking' ); ?>
								</small>
							</div>
						</td>
					</tr>
				</table>

				<p class="submit" style="display: flex; gap: 10px;">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Template', 'snippen-booking' ); ?></button>
					<a href="<?php echo esc_url( add_query_arg( 'page', 'snippen-booking-templates', admin_url( 'admin.php' ) ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'snippen-booking' ); ?></a>
				</p>
			</form>
		</div>
		<script>
			// Initialize placeholder chip states on load
			document.addEventListener('DOMContentLoaded', function() {
				var selectEl = document.getElementById('event_type');
				if (selectEl) {
					snippenUpdatePlaceholderStates(selectEl, 'placeholder-chips-container');
				}
			});
		</script>
		<?php
	}

	/**
	 * Render summary of all available placeholders top of page
	 *
	 * @return void
	 */
	private function render_placeholders_summary() {
		$registry     = $this->template_service->get_registry();
		$placeholders = $registry->get_registered_placeholders();

		if ( empty( $placeholders ) ) {
			return;
		}
		?>
		<div class="snippen-card" style="margin-bottom: 20px; background: #f9f9f9; padding: 15px; border-left: 4px solid #2271b1;">
			<h3 style="margin-top: 0;"><?php esc_html_e( 'Placeholder Registry', 'snippen-booking' ); ?></h3>
			<p><?php esc_html_e( 'The central placeholder registry defines valid dynamic tokens and their allowed contexts:', 'snippen-booking' ); ?></p>
			<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 12px; margin-top: 10px;">
				<?php foreach ( $placeholders as $name => $def ) : ?>
					<div style="background: white; border: 1px solid #ddd; padding: 10px; border-radius: 4px;">
						<strong><code>{{<?php echo esc_html( $name ); ?>}}</code></strong> - <?php echo esc_html( $def['label'] ); ?>
						<p style="margin: 4px 0 6px 0; color: #666; font-size: 12px;"><?php echo esc_html( $def['description'] ); ?></p>
						<span style="font-size: 11px; color: #444; background: #eef; padding: 2px 6px; border-radius: 3px;">
							<?php esc_html_e( 'Allowed in:', 'snippen-booking' ); ?> <?php echo esc_html( implode( ', ', $def['connected_to'] ) ); ?>
						</span>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}
}
