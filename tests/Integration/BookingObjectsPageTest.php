<?php
/**
 * Integration tests for BookingObjectsPage
 *
 * @package SnippenBooking\Tests\Integration
 */

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Admin\Pages\BookingObjectsPage;
use SnippenBooking\Database\Repository\DoorLockRepository;

/**
 * Class BookingObjectsPageTest
 */
class BookingObjectsPageTest extends TestCase {

	/**
	 * Requires database
	 */
	protected $requires_db = true;

	/**
	 * Test rendering list of objects and door locks tab
	 */
	public function test_render_objects_and_locks_tabs() {
		$page = new BookingObjectsPage();

		// Render venues list
		ob_start();
		$page->render();
		$html_objects = ob_get_clean();

		$this->assertStringContainsString( 'Lokaler', $html_objects );
		$this->assertStringContainsString( 'Dørlåser', $html_objects );
		$this->assertStringContainsString( 'Tilknyttet dørlås', $html_objects );

		// Render locks list
		$_GET['tab'] = 'locks';
		ob_start();
		$page->render();
		$html_locks = ob_get_clean();
		unset( $_GET['tab'] );

		$this->assertStringContainsString( 'Dørlåser (Smartlås / Yale Doorman)', $html_locks );
		$this->assertStringContainsString( 'Betjener lokaler', $html_locks );
		$this->assertStringContainsString( 'Legg til ny dørlås', $html_locks );
	}

	/**
	 * Test rendering add venue form with door lock select dropdown
	 */
	public function test_render_venue_form_with_door_lock_dropdown() {
		$repo    = new DoorLockRepository();
		$lock_id = $repo->create( array( 'name' => 'Felles Hoveddør' ) );

		$_GET['action'] = 'add';
		$page           = new BookingObjectsPage();

		ob_start();
		$page->render();
		$html = ob_get_clean();
		unset( $_GET['action'] );

		$this->assertStringContainsString( 'Tilknyttet dørlås / adgangskontroll', $html );
		$this->assertStringContainsString( 'Felles Hoveddør', $html );
		$this->assertStringContainsString( 'Ingen smartlås', $html );
	}
}
