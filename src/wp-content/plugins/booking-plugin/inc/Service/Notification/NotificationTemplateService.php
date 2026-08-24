<?php
/**
 * Notification Template Service
 *
 * Manages notification template storage, retrieval, and rendering with placeholders.
 *
 * @package SnippenBooking\Service\Notification
 */

namespace SnippenBooking\Service\Notification;

use SnippenBooking\Database\Repository\NotificationTemplateRepository;

/**
 * Class NotificationTemplateService
 */
class NotificationTemplateService {

	/**
	 * Template repository instance
	 *
	 * @var NotificationTemplateRepository
	 */
	private $repository;

	/**
	 * Placeholder registry instance
	 *
	 * @var PlaceholderRegistry
	 */
	private $registry;

	/**
	 * Constructor
	 *
	 * @param PlaceholderRegistry|null            $registry   Optional custom registry.
	 * @param NotificationTemplateRepository|null $repository Optional custom repository.
	 */
	public function __construct( ?PlaceholderRegistry $registry = null, ?NotificationTemplateRepository $repository = null ) {
		$this->registry   = $registry ?: new PlaceholderRegistry();
		$this->repository = $repository ?: new NotificationTemplateRepository();
	}

	/**
	 * Get repository instance
	 *
	 * @return NotificationTemplateRepository
	 */
	public function get_repository(): NotificationTemplateRepository {
		return $this->repository;
	}

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
		$db_row = $this->repository->find_by_connected_and_type( $event_type, $channel );

		if ( $db_row ) {
			return array(
				'id'         => (int) $db_row->id,
				'subject'    => $db_row->title ?: '',
				'body'       => $db_row->message,
				'is_default' => false,
			);
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
		$norm_type = PlaceholderRegistry::normalize_context( $event_type );

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

		return $defaults[ $norm_type ][ $channel ] ?? array(
			'subject' => '',
			'body'    => '',
		);
	}

	/**
	 * Save a custom template
	 *
	 * @param string      $event_type Event type / connected_to.
	 * @param string      $channel    Channel (sms/email).
	 * @param string|null $subject    Subject (optional for SMS).
	 * @param string      $body       Message body.
	 * @return bool
	 */
	public function save_template( string $event_type, string $channel, ?string $subject, string $body ): bool {
		$existing = $this->repository->find_by_connected_and_type( $event_type, $channel );

		if ( $existing ) {
			return $this->repository->update(
				(int) $existing->id,
				array(
					'title'   => $subject ?: null,
					'message' => $body,
				)
			);
		}

		$inserted = $this->repository->create(
			array(
				'name'         => sprintf( '%s (%s)', ucfirst( str_replace( '_', ' ', $event_type ) ), strtoupper( $channel ) ),
				'type'         => $channel,
				'title'        => $subject ?: null,
				'message'      => $body,
				'connected_to' => $event_type,
			)
		);

		return false !== $inserted;
	}

	/**
	 * Reset a template to its default
	 *
	 * @param string $event_type Event type.
	 * @param string $channel    Channel.
	 * @return bool
	 */
	public function reset_template_to_default( string $event_type, string $channel ): bool {
		$existing = $this->repository->find_by_connected_and_type( $event_type, $channel );

		if ( $existing ) {
			return $this->repository->delete( (int) $existing->id );
		}

		return true;
	}

	/**
	 * Get the central placeholder registry
	 *
	 * @return PlaceholderRegistry
	 */
	public function get_registry(): PlaceholderRegistry {
		return $this->registry;
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

		$subject = $this->registry->render_template( $template['subject'], $event_type, $context, false );
		$body    = $this->registry->render_template( $template['body'], $event_type, $context, false );

		return array(
			'subject' => $subject,
			'body'    => $body,
		);
	}

	/**
	 * Get all available placeholders and their descriptions
	 *
	 * @return array Placeholder => Description pairs.
	 */
	public function get_all_placeholders(): array {
		$result       = array();
		$placeholders = $this->registry->get_registered_placeholders();

		foreach ( $placeholders as $name => $definition ) {
			$result[ $name ] = $definition['label'];
		}

		return $result;
	}

	/**
	 * Get available placeholders for a given event type
	 *
	 * @param string $event_type Event type (optional).
	 * @return array Placeholder => Description pairs.
	 */
	public function get_available_placeholders( string $event_type = '' ): array {
		$result       = array();
		$placeholders = $this->registry->get_placeholders_for_context( $event_type );

		foreach ( $placeholders as $name => $definition ) {
			$result[ $name ] = $definition['label'];
		}

		return $result;
	}
}
