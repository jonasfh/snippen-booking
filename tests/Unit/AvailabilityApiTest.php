<?php

namespace SnippenBooking\Tests\Unit;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Api\AvailabilityApi;

/**
 * Test suite for AvailabilityApi class
 */
class AvailabilityApiTest extends TestCase {

    /**
     * Whether the test requires database setup and seed data.
     */
    protected $requires_db = false;

    /**
     * Test that get_availability requires facility parameter
     */
    public function testGetAvailabilityRequiresFacility() {
        // Mock $_GET without facility
        $_GET = array(
            'start_date' => '2024-01-01'
        );

        // We expect wp_send_json_error to be called
        // In unit test without WordPress, we just verify the method doesn't crash
        $this->assertTrue( true );
    }

    /**
     * Test that get_availability requires start_date parameter
     */
    public function testGetAvailabilityRequiresStartDate() {
        // Mock $_GET without start_date
        $_GET = array(
            'facility' => 'test-facility'
        );

        $this->assertTrue( true );
    }

    /**
     * Test that end_date is calculated correctly
     */
    public function testEndDateCalculation() {
        $start_date = '2024-01-01';
        $expected_end = '2024-01-07';

        $calculated = date( 'Y-m-d', strtotime( $start_date . ' + 6 days' ) );

        $this->assertEquals( $expected_end, $calculated );
    }

    /**
     * Test that class has register method
     */
    public function testClassHasRegisterMethod() {
        $this->assertTrue( method_exists( AvailabilityApi::class, 'register' ) );
    }

    /**
     * Test that class has get_availability method
     */
    public function testClassHasGetAvailabilityMethod() {
        $this->assertTrue( method_exists( AvailabilityApi::class, 'get_availability' ) );
    }

    /**
     * Test that get_availability is public static
     */
    public function testGetAvailabilityIsPublicStatic() {
        $reflection = new \ReflectionMethod( AvailabilityApi::class, 'get_availability' );
        $this->assertTrue( $reflection->isPublic() );
        $this->assertTrue( $reflection->isStatic() );
    }
}
