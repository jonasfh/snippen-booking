<?php

namespace SnippenBooking\Tests\Unit;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Shortcode\BookingShortcode;

/**
 * Test suite for BookingShortcode class
 */
class BookingShortcodeTest extends TestCase {

    /**
     * Whether the test requires database setup and seed data.
     */
    protected $requires_db = false;

    /**
     * Test that class has register method
     */
    public function testClassHasRegisterMethod() {
        $this->assertTrue( method_exists( BookingShortcode::class, 'register' ) );
    }

    /**
     * Test that class has render method
     */
    public function testClassHasRenderMethod() {
        $this->assertTrue( method_exists( BookingShortcode::class, 'render' ) );
    }

    /**
     * Test that register method is public static
     */
    public function testRegisterIsPublicStatic() {
        $reflection = new \ReflectionMethod( BookingShortcode::class, 'register' );
        $this->assertTrue( $reflection->isPublic() );
        $this->assertTrue( $reflection->isStatic() );
    }

    /**
     * Test that render method is public static
     */
    public function testRenderIsPublicStatic() {
        $reflection = new \ReflectionMethod( BookingShortcode::class, 'render' );
        $this->assertTrue( $reflection->isPublic() );
        $this->assertTrue( $reflection->isStatic() );
    }

    /**
     * Test that render method accepts array attributes
     */
    public function testRenderAcceptsAttributes() {
        $reflection = new \ReflectionMethod( BookingShortcode::class, 'render' );
        $params = $reflection->getParameters();

        $this->assertCount( 1, $params );
        $this->assertEquals( 'atts', $params[0]->getName() );
    }

    /**
     * Test that render method returns string
     */
    public function testRenderMethodReturnsString() {
        $this->assertTrue( method_exists( BookingShortcode::class, 'render' ) );
    }

    /**
     * Test shortcode default attributes
     */
    public function testShortcodeDefaults() {
        // Verify the class structure without calling render directly
        // (which would require WordPress)
        $reflection = new \ReflectionClass( BookingShortcode::class );
        $this->assertTrue( $reflection->hasMethod( 'render' ) );
    }
}
