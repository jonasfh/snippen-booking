<?php

namespace SnippenBooking\Service\Ai;

/**
 * Generates SMS reply drafts using the Google Gemini API (Free Tier compatible).
 */
class AiReplyService {

	/**
	 * Default Gemini model.
	 */
	const DEFAULT_MODEL = 'gemini-3.8-flash';

	/**
	 * Gemini API base endpoint.
	 */
	const API_BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/models/';

	/**
	 * Context builder instance.
	 *
	 * @var AiContextBuilder
	 */
	private $context_builder;

	/**
	 * Constructor.
	 *
	 * @param AiContextBuilder|null $context_builder Optional context builder.
	 */
	public function __construct( ?AiContextBuilder $context_builder = null ) {
		$this->context_builder = $context_builder ?? new AiContextBuilder();
	}

	/**
	 * Check whether AI SMS reply suggestions are enabled and configured.
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		return 'yes' === get_option( 'snippen_ai_enabled', 'no' )
			&& '' !== trim( (string) get_option( 'snippen_ai_gemini_api_key', '' ) );
	}

	/**
	 * Suggest an SMS reply draft for a message and/or booking.
	 *
	 * @param int|null $message_id Optional inbox message ID.
	 * @param int|null $booking_id Optional booking ID.
	 * @param string   $hint       Optional admin hint.
	 * @return array{success: bool, draft: string, error: string}
	 */
	public function suggest_sms_reply( ?int $message_id = null, ?int $booking_id = null, string $hint = '' ): array {
		if ( 'yes' !== get_option( 'snippen_ai_enabled', 'no' ) ) {
			return array(
				'success' => false,
				'draft'   => '',
				'error'   => __( 'AI-assistent er ikke aktivert i innstillingene.', 'snippen-booking' ),
			);
		}

		$api_key = trim( (string) get_option( 'snippen_ai_gemini_api_key', '' ) );
		if ( '' === $api_key ) {
			return array(
				'success' => false,
				'draft'   => '',
				'error'   => __( 'Gemini API-nøkkel mangler. Legg den inn under Innstillinger → SMS.', 'snippen-booking' ),
			);
		}

		$model = trim( (string) get_option( 'snippen_ai_gemini_model', self::DEFAULT_MODEL ) );
		if ( '' === $model ) {
			$model = self::DEFAULT_MODEL;
		}

		$context  = $this->context_builder->build_context( $message_id, $booking_id, $hint );
		$endpoint = self::API_BASE_URL . rawurlencode( $model ) . ':generateContent';

		$payload = array(
			'system_instruction' => array(
				'parts' => array(
					array( 'text' => $context['system_instruction'] ),
				),
			),
			'contents'           => array(
				array(
					'role'  => 'user',
					'parts' => array(
						array( 'text' => $context['user_prompt'] ),
					),
				),
			),
			'generationConfig'   => array(
				'temperature'     => 0.3,
				'maxOutputTokens' => 1024,
			),
		);

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => 25,
				'headers' => array(
					'Content-Type'   => 'application/json',
					'x-goog-api-key' => $api_key,
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'draft'   => '',
				'error'   => sprintf(
					/* translators: %s: error message */
					__( 'Kunne ikke kontakte Gemini API: %s', 'snippen-booking' ),
					$response->get_error_message()
				),
			);
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$raw_body    = (string) wp_remote_retrieve_body( $response );
		$decoded     = json_decode( $raw_body, true );

		if ( $status_code < 200 || $status_code >= 300 ) {
			$api_error = $decoded['error']['message'] ?? sprintf( 'HTTP %d', $status_code );
			return array(
				'success' => false,
				'draft'   => '',
				'error'   => sprintf(
					/* translators: %s: API error message */
					__( 'Gemini API returnerte feil: %s', 'snippen-booking' ),
					$api_error
				),
			);
		}

		$draft = trim( (string) ( $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '' ) );
		if ( '' === $draft ) {
			return array(
				'success' => false,
				'draft'   => '',
				'error'   => __( 'Gemini returnerte et tomt svar. Prøv igjen eller legg inn et stikkord.', 'snippen-booking' ),
			);
		}

		return array(
			'success' => true,
			'draft'   => $draft,
			'error'   => '',
		);
	}
}
