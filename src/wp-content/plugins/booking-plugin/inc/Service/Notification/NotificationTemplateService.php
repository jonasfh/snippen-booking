<?php
/**
 * Notification Template Service
 *
 * Manages notification template storage, retrieval, and rendering with placeholders.
 *
 * @package SnippenBooking\Service\Notification
 */

namespace SnippenBooking\Service\Notification;

/**
 * Class NotificationTemplateService
 */
class NotificationTemplateService {

	/**
	 * Get all templates organized by event type and channel
	 *
	 * @return array
	 */
	public function get_all_templates(): array {
		$templates = array();

		$event_types = array( 'user_activation', 'booking_confirmation', 'admin_booking', 'password_reset' );
		$channels    = array( 'sms', 'email' );

		foreach ( $event_types as $event_type ) {
			$templates[ $event_type ] = array();
			foreach ( $channels as $channel ) {
				$templates[ $event_type ][ $channel ] = $this->get_template( $event_type, $channel );
			}
		}

		return $templates;
	}

	/**
	 * Get a template by event type and channel
	 *
	 * @param string $event_type Event type.
	 * @param string $channel    Channel (sms/email).
	 * @return array Template data with keys: subject, body, is_default.
	 */
	public function get_template( string $event_type, string $channel ): array {
		$option_key = "snippen_template_{$event_type}_{$channel}";
		$custom     = get_option( $option_key );

		if ( $custom && is_array( $custom ) ) {
			$merged               = array_merge( $this->get_default_template( $event_type, $channel ), $custom );
			$merged['is_default'] = false;
			return $merged;
		}

		$default               = $this->get_default_template( $event_type, $channel );
		$default['is_default'] = true;

		return $default;
	}

	/**
	 * Get default template for an event and channel
	 *
	 * @param string $event_type Event type.
	 * @param string $channel    Channel (sms/email).
	 * @return array
	 */
	public function get_default_template( string $event_type, string $channel ): array {
		$defaults = array(
			'user_activation'      => array(
				'sms'   => array(
					'subject' => '',
					'body'    => __( 'Din bekreftelseskode for Snippen Booking er: {{confirmation_code}}. Koden er gyldig i 15 minutter.', 'snippen-booking' ),
				),
				'email' => array(
					'subject' => __( 'Bekreftelseskode for Snippen Booking', 'snippen-booking' ),
					'body'    => __( "Hallo {{user_name}},\n\nDin bekreftelseskode for Snippen Booking er: {{confirmation_code}}\n\nKoden er gyldig i 15 minutter.\n\nVennligst enter koden på siden for å bekrefte kontoen din.", 'snippen-booking' ),
				),
			),
			'booking_confirmation' => array(
				'sms'   => array(
					'subject' => '',
					'body'    => __( 'Takk for din bookingforespørsel for {{booking_objects}} den {{booking_date}}. Betaling: Bank {{bank_account}}, Vipps {{vipps_number}} ({{booking_price}} kr). {{payment_instructions}} Se detaljer: {{booking_url}}', 'snippen-booking' ),
				),
				'email' => array(
					'subject' => __( 'Bekreftelse på din bookingforespørsel', 'snippen-booking' ),
					'body'    => __( "Hallo {{user_name}},\n\nTakk for din bookingforespørsel for {{booking_objects}} den {{booking_date}}.\n\nBetalingsinformasjon:\nBankkontonr: {{bank_account}}\nVipps: {{vipps_number}}\nBeløp: {{booking_price}} kr\n\n{{payment_instructions}}\n\nDu kan se detaljer om din booking her: {{booking_url}}\n\nVed spørsmål, kontakt oss.", 'snippen-booking' ),
				),
			),
			'admin_booking'        => array(
				'sms'   => array(
					'subject' => '',
					'body'    => __( 'Ny bookingforespørsel for {{booking_objects}} den {{booking_date}} fra {{user_name}}.', 'snippen-booking' ),
				),
				'email' => array(
					'subject' => __( 'Ny Bookingforespørsel - {{booking_objects}}', 'snippen-booking' ),
					'body'    => __( "Ny bookingforespørsel mottatt:\n\nLokale: {{booking_objects}}\nDato: {{booking_date}}\nNavn: {{user_name}}\nEmail: {{user_email}}\nTelefon: {{user_phone}}\nBeskrivelse: {{booking_description}}\n\nVennligst logg inn i administrasjonsgrensesnittet for å håndtere denne forespørselen.", 'snippen-booking' ),
				),
			),
			'password_reset'       => array(
				'sms'   => array(
					'subject' => '',
					'body'    => __( 'For å tilbakestille passordet ditt, trykk på denne lenken: {{reset_link}}', 'snippen-booking' ),
				),
				'email' => array(
					'subject' => __( 'Tilbakestill passord', 'snippen-booking' ),
					'body'    => __( "Hallo {{user_name}},\n\nNoen har bedt om å tilbakestille passordet for din konto.\n\nHvis dette var en feiltakelse, kan du se bort fra denne e-posten.\n\nFor å tilbakestille passordet ditt, trykk på denne lenken:\n{{reset_link}}", 'snippen-booking' ),
				),
			),
		);

		return $defaults[ $event_type ][ $channel ] ?? array(
			'subject' => '',
			'body'    => '',
		);
	}

	/**
	 * Save a custom template
	 *
	 * @param string      $event_type Event type.
	 * @param string      $channel    Channel.
	 * @param string|null $subject    Subject (optional for SMS).
	 * @param string      $body       Message body.
	 * @return bool
	 */
	public function save_template( string $event_type, string $channel, ?string $subject, string $body ): bool {
		$option_key = "snippen_template_{$event_type}_{$channel}";

		$template = array(
			'subject' => $subject ?: '',
			'body'    => $body,
		);

		return update_option( $option_key, $template );
	}

	/**
	 * Reset a template to its default
	 *
	 * @param string $event_type Event type.
	 * @param string $channel    Channel.
	 * @return bool
	 */
	public function reset_template_to_default( string $event_type, string $channel ): bool {
		$option_key = "snippen_template_{$event_type}_{$channel}";
		return delete_option( $option_key );
	}

	/**
	 * Render a template with placeholder replacements
	 *
	 * @param string $event_type Event type.
	 * @param string $channel    Channel.
	 * @param array  $context    Context data for placeholder replacement.
	 * @return array With keys: subject, body.
	 */
	public function render_template( string $event_type, string $channel, array $context ): array {
		$template = $this->get_template( $event_type, $channel );

		$subject = $this->replace_placeholders( $template['subject'], $context );
		$body    = $this->replace_placeholders( $template['body'], $context );

		return array(
			'subject' => $subject,
			'body'    => $body,
		);
	}

	/**
	 * Replace placeholders in a string
	 *
	 * @param string $text    Text with {{placeholder}} syntax.
	 * @param array  $context Key-value pairs for replacement.
	 * @return string
	 */
	private function replace_placeholders( string $text, array $context ): string {
		foreach ( $context as $key => $value ) {
			$placeholder = '{{' . $key . '}}';
			$text        = str_replace( $placeholder, (string) $value, $text );
		}

		return $text;
	}

	/**
	 * Get all available placeholders and their descriptions
	 *
	 * @return array Placeholder => Description pairs.
	 */
	public function get_all_placeholders(): array {
		return array(
			'user_name'            => __( 'User / Customer name', 'snippen-booking' ),
			'user_email'           => __( 'Customer email', 'snippen-booking' ),
			'user_phone'           => __( 'Customer phone number', 'snippen-booking' ),
			'confirmation_code'    => __( '6-digit confirmation code', 'snippen-booking' ),
			'booking_objects'      => __( 'Booked venue names', 'snippen-booking' ),
			'booking_date'         => __( 'Booking date', 'snippen-booking' ),
			'booking_time'         => __( 'Booking time / time slot', 'snippen-booking' ),
			'booking_description'  => __( 'Booking description/notes', 'snippen-booking' ),
			'booking_url'          => __( 'Booking details URL', 'snippen-booking' ),
			'booking_price'        => __( 'Booking total price', 'snippen-booking' ),
			'bank_account'         => __( 'Payment bank account number', 'snippen-booking' ),
			'vipps_number'         => __( 'Payment Vipps number / info', 'snippen-booking' ),
			'payment_instructions' => __( 'Payment instructions / deadline text from payment settings', 'snippen-booking' ),
			'reset_link'           => __( 'Password reset URL', 'snippen-booking' ),
		);
	}

	/**
	 * Get available placeholders for a given event type
	 *
	 * @param string $event_type Event type (optional).
	 * @return array Placeholder => Description pairs.
	 */
	public function get_available_placeholders( string $event_type = '' ): array {
		// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		return $this->get_all_placeholders();
	}
}
