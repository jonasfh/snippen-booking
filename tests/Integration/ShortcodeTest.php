<?php

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Database\Install;
use SnippenBooking\Shortcode\BookingShortcode;

class ShortcodeTest extends TestCase {

    /**
     * Set up the test environment
     */
    protected function setUp(): void {
        parent::setUp();
        // Ensure database tables and seed data exist
        Install::activate();
        BookingShortcode::register();
    }

    /**
     * Test if the [snippen_booking] shortcode renders HTML
     */
    public function test_shortcode_renders() {
        $output = do_shortcode( '[snippen_booking object_id="1"]' );

        $this->assertStringContainsString( 'class="snippen-booking-container"', $output );
        $this->assertStringContainsString( 'id="booking-form"', $output );
        $this->assertStringContainsString( 'data-object-id="1"', $output );
        // Facility dropdown should be gone
        $this->assertStringNotContainsString( 'id="facility"', $output );
    }

    /**
     * Test if calendar renders correctly
     */
    public function test_calendar_renders() {
        $output = do_shortcode( '[snippen_booking object_id="1"]' );

        $this->assertStringContainsString( 'class="snippen-calendar-view"', $output );
        $this->assertStringContainsString( 'id="calendar-container"', $output );
    }

    /**
     * Test if the demo page is created correctly
     */
    public function test_demo_page_created() {
        // Delete if exists to force recreation with new shortcode
        $page = get_page_by_title( 'Booking Demo' );
        if ( $page ) {
            wp_delete_post( $page->ID, true );
        }

        // Trigger page creation
        Install::create_demo_page();
        
        $page = get_page_by_title( 'Booking Demo' );
        
        $this->assertNotNull( $page, 'Demo page was not created' );
        $this->assertStringContainsString( '[snippen_booking object_id="1"]', $page->post_content );
    }
}
