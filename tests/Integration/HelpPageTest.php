<?php

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Admin\AdminLoader;
use SnippenBooking\Admin\Pages\HelpPage;
use SnippenBooking\Helper\Capabilities;

class HelpPageTest extends TestCase {

	public function setUp(): void {
		parent::setUp();
		wp_set_current_user( 1 );
		
		// Load plugin text domain for translation functions
		load_plugin_textdomain( 'snippen-booking', false, basename( dirname( __DIR__, 3 ) ) . '/languages/' );
	}

	public function test_help_page_renders_without_errors() {
		$page = new HelpPage();

		ob_start();
		$page->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Brukermanual &amp; Hjelp', $output );
		$this->assertStringContainsString( 'Hurtigstart (TL;DR)', $output );
		$this->assertStringContainsString( 'Bruk av Shortcodes', $output );
	}

}
