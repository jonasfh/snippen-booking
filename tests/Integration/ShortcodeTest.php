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
        global $wpdb;
        // Ensure user 1 exists and has privileges
        if ( ! get_userdata( 1 ) ) {
            $user_id = wp_create_user( 'admin', 'password', 'admin_shortcode@example.com' );
            $wpdb->update( $wpdb->users, array( 'ID' => 1 ), array( 'ID' => $user_id ) );
            $wpdb->update( $wpdb->usermeta, array( 'user_id' => 1 ), array( 'user_id' => $user_id ) );
        }
        $user = new \WP_User( 1 );
        $user->set_role( 'administrator' );
        
        // Simulate a logged in user to see the form
        wp_set_current_user( 1 ); // Standard admin user usually exists

        $output = do_shortcode( '[snippen_booking object_id="1"]' );

        $this->assertStringContainsString( 'class="snippen-booking-container"', $output );
        $this->assertStringContainsString( 'id="booking-form"', $output );
        $this->assertStringContainsString( 'data-object-id="[1]"', $output );
        // Facility dropdown should be gone
        $this->assertStringNotContainsString( 'id="facility"', $output );
        
        // Logout for other tests
        wp_set_current_user( 0 );
    }

    /**
     * Test if calendar renders correctly
     */
    public function test_calendar_renders() {
        $output = do_shortcode( '[snippen_booking object_id="1"]' );

        $this->assertStringContainsString( 'snippen-calendar-view', $output );
        $this->assertStringContainsString( 'id="calendar-container"', $output );
    }

}
