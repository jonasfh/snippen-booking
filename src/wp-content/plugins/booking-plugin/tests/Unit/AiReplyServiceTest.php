<?php
/**
 * Unit Tests for AiReplyService and AiContextBuilder
 *
 * @package SnippenBooking\Tests\Unit
 */

namespace SnippenBooking\Tests\Unit;

use SnippenBooking\Service\Ai\AiContextBuilder;
use SnippenBooking\Service\Ai\AiReplyService;
use SnippenBooking\Tests\TestCase;

/**
 * Class AiReplyServiceTest
 */
class AiReplyServiceTest extends TestCase {

	/**
	 * Clean up AI options after each test.
	 */
	public function tearDown(): void {
		delete_option( 'snippen_ai_enabled' );
		delete_option( 'snippen_ai_gemini_api_key' );
		delete_option( 'snippen_ai_gemini_model' );
		delete_option( 'snippen_ai_extra_context' );
		delete_option( 'snippen_ai_context_page_ids' );
		parent::tearDown();
	}

	/**
	 * Test is_enabled requires both toggle and API key.
	 */
	public function test_is_enabled_requires_toggle_and_api_key() {
		$this->assertFalse( AiReplyService::is_enabled() );

		update_option( 'snippen_ai_enabled', 'yes' );
		$this->assertFalse( AiReplyService::is_enabled() );

		update_option( 'snippen_ai_gemini_api_key', 'AIzaTestKey123' );
		$this->assertTrue( AiReplyService::is_enabled() );
	}

	/**
	 * Test suggest_sms_reply returns error when disabled.
	 */
	public function test_suggest_sms_reply_fails_when_disabled() {
		$service = new AiReplyService();
		$result  = $service->suggest_sms_reply( null, null, 'Test hint' );

		$this->assertFalse( $result['success'] );
		$this->assertSame( '', $result['draft'] );
		$this->assertNotEmpty( $result['error'] );
	}

	/**
	 * Test suggest_sms_reply calls Gemini API and returns draft.
	 */
	public function test_suggest_sms_reply_returns_gemini_draft() {
		update_option( 'snippen_ai_enabled', 'yes' );
		update_option( 'snippen_ai_gemini_api_key', 'AIzaTestKey123' );
		update_option( 'snippen_ai_gemini_model', 'gemini-3.8-flash' );
		update_option( 'snippen_ai_extra_context', 'Parkering finnes rett utenfor hovedinngangen.' );

		$captured_request = null;
		$filter_callback  = function ( $preempt, $args, $url ) use ( &$captured_request ) {
			$captured_request = array(
				'url'  => $url,
				'args' => $args,
			);
			return array(
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'body'     => wp_json_encode(
					array(
						'candidates' => array(
							array(
								'content' => array(
									'parts' => array(
										array( 'text' => 'Hei! Parkering finnes rett utenfor hovedinngangen. Velkommen!' ),
									),
								),
							),
						),
					)
				),
			);
		};

		add_filter( 'pre_http_request', $filter_callback, 10, 3 );

		$service = new AiReplyService( new AiContextBuilder() );
		$result  = $service->suggest_sms_reply( null, null, 'Svar om parkering' );

		remove_filter( 'pre_http_request', $filter_callback, 10 );

		$this->assertTrue( $result['success'] );
		$this->assertSame( 'Hei! Parkering finnes rett utenfor hovedinngangen. Velkommen!', $result['draft'] );
		$this->assertNotNull( $captured_request );
		$this->assertStringContainsString( 'gemini-3.8-flash:generateContent', $captured_request['url'] );
		$this->assertSame( 'AIzaTestKey123', $captured_request['args']['headers']['x-goog-api-key'] );
		$this->assertStringContainsString( 'Parkering finnes rett utenfor hovedinngangen.', $captured_request['args']['body'] );
		$this->assertStringContainsString( 'Svar om parkering', $captured_request['args']['body'] );
	}

	/**
	 * Test suggest_sms_reply handles API error response.
	 */
	public function test_suggest_sms_reply_handles_api_error() {
		update_option( 'snippen_ai_enabled', 'yes' );
		update_option( 'snippen_ai_gemini_api_key', 'AIzaBadKey' );

		$filter_callback = function () {
			return array(
				'response' => array(
					'code'    => 400,
					'message' => 'Bad Request',
				),
				'body'     => wp_json_encode(
					array(
						'error' => array(
							'message' => 'API key not valid.',
						),
					)
				),
			);
		};

		add_filter( 'pre_http_request', $filter_callback, 10, 3 );

		$service = new AiReplyService();
		$result  = $service->suggest_sms_reply( null, null, '' );

		remove_filter( 'pre_http_request', $filter_callback, 10 );

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'API key not valid.', $result['error'] );
	}
}
