<?php

namespace SnippenBooking\Tests\Unit;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Helper\Security;

/**
 * Tests for the Security helper class
 */
class SecurityHelperTest extends TestCase {

	/**
	 * Whether the test requires database setup and seed data.
	 */
	protected $requires_db = false;

	/**
	 * Test esc_like escapes percent wildcards
	 */
	public function testEscLikeEscapesPercent() {
		$result = Security::esc_like( 'test%value' );
		$this->assertStringContainsString( '\\%', $result );
		$this->assertStringNotContainsString( 'test%', $result );
	}

	/**
	 * Test esc_like escapes underscore wildcards
	 */
	public function testEscLikeEscapesUnderscore() {
		$result = Security::esc_like( 'test_value' );
		$this->assertStringContainsString( '\\_', $result );
	}

	/**
	 * Test esc_like leaves safe strings unchanged
	 */
	public function testEscLikeLeavesSafeStrings() {
		$result = Security::esc_like( 'safe string' );
		$this->assertEquals( 'safe string', $result );
	}

	/**
	 * Test get_post_text returns default when key not set
	 */
	public function testGetPostTextReturnsDefault() {
		$_POST = array();
		$result = Security::get_post_text( 'nonexistent', 'default_val' );
		$this->assertEquals( 'default_val', $result );
	}

	/**
	 * Test get_post_text sanitizes value
	 */
	public function testGetPostTextSanitizes() {
		$_POST['test_key'] = '<script>alert("xss")</script>';
		$result = Security::get_post_text( 'test_key' );
		$this->assertStringNotContainsString( '<script>', $result );
		unset( $_POST['test_key'] );
	}

	/**
	 * Test get_query_text returns default when key not set
	 */
	public function testGetQueryTextReturnsDefault() {
		$_GET = array();
		$result = Security::get_query_text( 'nonexistent', 'fallback' );
		$this->assertEquals( 'fallback', $result );
	}

	/**
	 * Test get_query_text sanitizes value
	 */
	public function testGetQueryTextSanitizes() {
		$_GET['test_key'] = '<img onerror=alert(1) src=x>';
		$result = Security::get_query_text( 'test_key' );
		$this->assertStringNotContainsString( '<img', $result );
		unset( $_GET['test_key'] );
	}

	/**
	 * Test get_post_int returns default when key not set
	 */
	public function testGetPostIntReturnsDefault() {
		$_POST = array();
		$result = Security::get_post_int( 'nonexistent', 42 );
		$this->assertEquals( 42, $result );
	}

	/**
	 * Test get_post_int returns integer
	 */
	public function testGetPostIntReturnsInt() {
		$_POST['num'] = '123abc';
		$result = Security::get_post_int( 'num' );
		$this->assertSame( 123, $result );
		unset( $_POST['num'] );
	}

	/**
	 * Test get_query_int returns default when key not set
	 */
	public function testGetQueryIntReturnsDefault() {
		$_GET = array();
		$result = Security::get_query_int( 'nonexistent', 99 );
		$this->assertEquals( 99, $result );
	}

	/**
	 * Test get_query_int returns integer
	 */
	public function testGetQueryIntReturnsInt() {
		$_GET['num'] = '456def';
		$result = Security::get_query_int( 'num' );
		$this->assertSame( 456, $result );
		unset( $_GET['num'] );
	}

	/**
	 * Test verify_ajax_nonce returns false on invalid nonce with die=false
	 */
	public function testVerifyAjaxNonceReturnsFalseOnInvalid() {
		$_REQUEST['nonce'] = 'invalid_nonce_value';
		$result = Security::verify_ajax_nonce( 'test_action', 'nonce', false );
		$this->assertFalse( $result );
		unset( $_REQUEST['nonce'] );
	}

	/**
	 * Test verify_ajax_nonce returns true with valid nonce
	 */
	public function testVerifyAjaxNonceReturnsTrueOnValid() {
		$nonce = wp_create_nonce( 'test_valid_action' );
		$_REQUEST['nonce'] = $nonce;
		$result = Security::verify_ajax_nonce( 'test_valid_action', 'nonce', false );
		$this->assertTrue( $result );
		unset( $_REQUEST['nonce'] );
	}
}
