<?php

namespace SnippenBooking\Tests\Unit;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Api\BookingApi;

/**
 * Test suite for BookingApi class
 */
class BookingApiTest extends TestCase {

    /**
     * Test that class has register method
     */
    public function testClassHasRegisterMethod() {
        $this->assertTrue( method_exists( BookingApi::class, 'register' ) );
    }

    /**
     * Test that class has submit_booking method
     */
    public function testClassHasSubmitBookingMethod() {
        $this->assertTrue( method_exists( BookingApi::class, 'submit_booking' ) );
    }

    /**
     * Test that register method is public static
     */
    public function testRegisterIsPublicStatic() {
        $reflection = new \ReflectionMethod( BookingApi::class, 'register' );
        $this->assertTrue( $reflection->isPublic() );
        $this->assertTrue( $reflection->isStatic() );
    }

    /**
     * Test that submit_booking method is public static
     */
    public function testSubmitBookingIsPublicStatic() {
        $reflection = new \ReflectionMethod( BookingApi::class, 'submit_booking' );
        $this->assertTrue( $reflection->isPublic() );
        $this->assertTrue( $reflection->isStatic() );
    }

    /**
     * Test that class structure is correct
     */
    public function testClassStructure() {
        $reflection = new \ReflectionClass( BookingApi::class );
        $this->assertTrue( $reflection->hasMethod( 'register' ) );
        $this->assertTrue( $reflection->hasMethod( 'submit_booking' ) );
    }
}
