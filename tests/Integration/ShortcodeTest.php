<?php

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;

class ShortcodeTest extends TestCase {

    /**
     * Test if the [snippen_booking] shortcode renders HTML
     */
    public function test_shortcode_renders() {
        // Ensure the shortcode is registered
        \SnippenBooking\Shortcode\BookingShortcode::register();
        
        $output = do_shortcode( '[snippen_booking]' );

        $this->assertStringContainsString( 'class="snippen-booking-form"', $output );
        $this->assertStringContainsString( 'id="booking-form"', $output );
        $this->assertStringContainsString( 'name="facility"', $output );
    }

    /**
     * Test if calendar type shortcode renders correctly
     */
    public function test_calendar_shortcode_renders() {
        // Ensure the shortcode is registered
        \SnippenBooking\Shortcode\BookingShortcode::register();
        
        $output = do_shortcode( '[snippen_booking type="calendar"]' );

        $this->assertStringContainsString( 'class="snippen-booking-calendar"', $output );
        $this->assertStringContainsString( 'id="calendar-container"', $output );
    }

    /**
     * Test if the demo page is created correctly
     */
    public function test_demo_page_created() {
        // Trigger page creation
        \SnippenBooking\Database\Install::create_demo_page();
        
        $page = get_page_by_title( 'Booking Demo' );
        
        $this->assertNotNull( $page, 'Demo page was not created' );
        $this->assertStringContainsString( '[snippen_booking]', $page->post_content );
    }
}
