<?php

namespace SnippenBooking\Tests\Unit;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Shortcode\BookingListShortcode;

/**
 * Test suite for BookingListShortcode class
 */
class BookingListShortcodeTest extends TestCase {

    /**
     * Test that class has register method
     */
    public function testClassHasRegisterMethod() {
        $this->assertTrue( method_exists( BookingListShortcode::class, 'register' ) );
    }

    /**
     * Test that class has render method
     */
    public function testClassHasRenderMethod() {
        $this->assertTrue( method_exists( BookingListShortcode::class, 'render' ) );
    }

    /**
     * Test that register method is public static
     */
    public function testRegisterIsPublicStatic() {
        $reflection = new \ReflectionMethod( BookingListShortcode::class, 'register' );
        $this->assertTrue( $reflection->isPublic() );
        $this->assertTrue( $reflection->isStatic() );
    }

    /**
     * Test that render method is public static
     */
    public function testRenderIsPublicStatic() {
        $reflection = new \ReflectionMethod( BookingListShortcode::class, 'render' );
        $this->assertTrue( $reflection->isPublic() );
        $this->assertTrue( $reflection->isStatic() );
    }

    /**
     * Test that render method accepts array attributes
     */
    public function testRenderAcceptsAttributes() {
        $reflection = new \ReflectionMethod( BookingListShortcode::class, 'render' );
        $params = $reflection->getParameters();

        $this->assertCount( 1, $params );
        $this->assertEquals( 'atts', $params[0]->getName() );
    }
}
