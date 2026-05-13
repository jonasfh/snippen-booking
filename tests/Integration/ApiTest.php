<?php

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;

class ApiTest extends TestCase {

    /**
     * Test if AJAX actions are registered
     */
    public function test_ajax_actions_registered() {
        // Register API handlers
        \SnippenBooking\Api\AvailabilityApi::register();
        \SnippenBooking\Api\BookingApi::register();

        // Check if actions are registered in WordPress
        $this->assertGreaterThan( 0, has_action( 'wp_ajax_snippen_get_availability' ), 'AJAX action snippen_get_availability not registered' );
        $this->assertGreaterThan( 0, has_action( 'wp_ajax_nopriv_snippen_get_availability' ), 'Public AJAX action snippen_get_availability not registered' );
        $this->assertGreaterThan( 0, has_action( 'wp_ajax_snippen_booking_submit' ), 'AJAX action snippen_booking_submit not registered' );
        $this->assertGreaterThan( 0, has_action( 'wp_ajax_nopriv_snippen_booking_submit' ), 'Public AJAX action snippen_booking_submit not registered' );
    }
}
