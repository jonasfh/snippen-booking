<?php
/**
 * Shortcode for user account confirmation
 *
 * @package SnippenBooking\Shortcode
 */

namespace SnippenBooking\Shortcode;

/**
 * Account Confirmation Shortcode
 */
class AccountConfirmationShortcode {

	/**
	 * Register the shortcode
	 */
	public static function register() {
		add_shortcode( 'snippen_account_confirmation', array( __CLASS__, 'render' ) );
	}

	/**
	 * Render the shortcode
	 *
	 * @return string
	 */
	public static function render() {
		if ( is_user_logged_in() ) {
			return '';
		}

		wp_enqueue_style( 'snippen-booking-public', plugins_url( 'css/booking.css', dirname( __DIR__, 1 ) ), array(), '1.1.0' );
		wp_enqueue_script( 'snippen-account-confirmation', plugins_url( 'js/account-confirmation.js', dirname( __DIR__, 1 ) ), array( 'jquery' ), '1.1.0', true );

		wp_localize_script(
			'snippen-account-confirmation',
			'snippenConfirmation',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'snippen_confirmation_nonce' ),
				'strings' => array(
					'error'               => __( 'Det oppsto en feil. Prøv igjen.', 'snippen-booking' ),
					'success'             => __( 'Din konto er nå bekreftet! Du blir videresendt til innlogging.', 'snippen-booking' ),
					'enterPhone'          => __( 'Vennligst skriv inn telefonnummer.', 'snippen-booking' ),
					'sending'             => __( 'Sender...', 'snippen-booking' ),
					'fillAllFields'       => __( 'Vennligst fyll ut alle felt.', 'snippen-booking' ),
					'passwordsNotMatch'   => __( 'Passordene er ikke like.', 'snippen-booking' ),
					'passwordMinLength'   => __( 'Passordet må være minst 8 tegn.', 'snippen-booking' ),
					'verifying'           => __( 'Verifiserer...', 'snippen-booking' ),
					'sendCode'            => __( 'Send kode', 'snippen-booking' ),
					'confirmAndSavePass'  => __( 'Bekreft og lagre passord', 'snippen-booking' ),
				),
			)
		);

		$sms_enabled = 'yes' === get_option( 'snippen_sms_account_confirmation_enabled' );
		ob_start();
		?>
		<div class="snippen-confirmation-container">
			<div id="confirmation-step-1" class="confirmation-step">
				<h3><?php esc_html_e( 'Bekreft din konto', 'snippen-booking' ); ?></h3>
				<p>
					<?php
					if ( $sms_enabled ) {
						esc_html_e( 'Skriv inn ditt telefonnummer for å motta en bekreftelseskode på SMS.', 'snippen-booking' );
					} else {
						esc_html_e( 'Skriv inn ditt telefonnummer for å motta en bekreftelseskode på e-post.', 'snippen-booking' );
					}
					?>
				</p>
				<div class="snippen-form-group">
					<label for="snippen_phone_confirm"><?php esc_html_e( 'Telefonnummer', 'snippen-booking' ); ?></label>
					<input type="tel" id="snippen_phone_confirm" placeholder="+47XXXXXXXX" class="regular-text">
				</div>
				<button type="button" id="snippen-request-code" class="snippen-btn snippen-btn-primary">
					<?php esc_html_e( 'Send kode', 'snippen-booking' ); ?>
				</button>
			</div>

			<div id="confirmation-step-2" class="confirmation-step" style="display: none;">
				<h3><?php esc_html_e( 'Skriv inn kode', 'snippen-booking' ); ?></h3>
				<p>
					<?php
					if ( $sms_enabled ) {
						esc_html_e( 'Vi har sendt en 6-sifret kode til ditt telefonnummer.', 'snippen-booking' );
					} else {
						esc_html_e( 'Vi har sendt en 6-sifret kode til din e-post.', 'snippen-booking' );
					}
					?>
				</p>
				<input type="hidden" id="snippen_confirm_user_id">
				
				<div class="snippen-form-group">
					<label for="snippen_code"><?php esc_html_e( 'Bekreftelseskode', 'snippen-booking' ); ?></label>
					<input type="text" id="snippen_code" maxlength="6" placeholder="000000" class="regular-text">
				</div>

				<div class="snippen-form-group">
					<label for="snippen_new_password"><?php esc_html_e( 'Velg nytt passord', 'snippen-booking' ); ?></label>
					<input type="password" id="snippen_new_password" class="regular-text">
					<p class="description"><?php esc_html_e( 'Minst 8 tegn.', 'snippen-booking' ); ?></p>
				</div>

				<div class="snippen-form-group">
					<label for="snippen_confirm_password"><?php esc_html_e( 'Gjenta passord', 'snippen-booking' ); ?></label>
					<input type="password" id="snippen_confirm_password" class="regular-text">
				</div>

				<button type="button" id="snippen-verify-code" class="snippen-btn snippen-btn-primary">
					<?php esc_html_e( 'Bekreft og lagre passord', 'snippen-booking' ); ?>
				</button>
			</div>

			<div id="confirmation-response" class="snippen-response-msg" style="display: none;"></div>
		</div>
		<style>
			.snippen-confirmation-container { max-width: 400px; margin: 20px auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; background: #fff; }
			.snippen-confirmation-container h3 { margin-top: 0; }
			.snippen-form-group { margin-bottom: 15px; }
			.snippen-form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
			.snippen-form-group input { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
			.snippen-btn { display: inline-block; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: bold; }
			.snippen-btn-primary { background: #0073aa; color: #fff; }
			.snippen-btn-primary:hover { background: #006799; }
			.snippen-response-msg { margin-top: 15px; padding: 10px; border-radius: 4px; }
			.snippen-response-msg.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
			.snippen-response-msg.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
		</style>
		<?php
		return ob_get_clean();
	}
}
