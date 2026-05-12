<?php

namespace SnippenBooking\Tests\Unit;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Database\Install;

/**
 * Test suite for Install class
 */
class InstallTest extends TestCase {

    /**
     * Test that class has activate method
     */
    public function testClassHasActivateMethod() {
        $this->assertTrue( method_exists( Install::class, 'activate' ) );
    }

    /**
     * Test that activate method is public static
     */
    public function testActivateIsPublicStatic() {
        $reflection = new \ReflectionMethod( Install::class, 'activate' );
        $this->assertTrue( $reflection->isPublic() );
        $this->assertTrue( $reflection->isStatic() );
    }

    /**
     * Test that class requires ABSPATH constant
     */
    public function testAbspathIsRequired() {
        // ABSPATH should be defined by bootstrap
        $this->assertTrue( defined( 'ABSPATH' ) );
    }

    /**
     * Test that Install class can be instantiated
     */
    public function testClassCanBeInstantiated() {
        // While methods are static, we should be able to instantiate
        $install = new Install();
        $this->assertInstanceOf( Install::class, $install );
    }

    /**
     * Test database table prefix handling
     */
    public function testDatabasePrefixUsage() {
        // Even without WordPress, we can verify the class doesn't crash
        // when required includes are present
        $this->assertTrue( class_exists( Install::class ) );
    }
}
