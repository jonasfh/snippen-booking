<?php

namespace SnippenBooking\Tests\Unit;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Plugin;

/**
 * Test suite for Plugin bootstrapper class
 */
class PluginTest extends TestCase {

    /**
     * Test that class has init method
     */
    public function testClassHasInitMethod() {
        $this->assertTrue( method_exists( Plugin::class, 'init' ) );
    }

    /**
     * Test that class has activate method
     */
    public function testClassHasActivateMethod() {
        $this->assertTrue( method_exists( Plugin::class, 'activate' ) );
    }

    /**
     * Test that class has register_hooks method
     */
    public function testClassHasRegisterHooksMethod() {
        $this->assertTrue( method_exists( Plugin::class, 'register_hooks' ) );
    }

    /**
     * Test that class has enqueue_assets method
     */
    public function testClassHasEnqueueAssetsMethod() {
        $this->assertTrue( method_exists( Plugin::class, 'enqueue_assets' ) );
    }

    /**
     * Test that all main methods are public static
     */
    public function testMainMethodsArePublicStatic() {
        $methods = array( 'init', 'activate', 'register_hooks', 'enqueue_assets' );

        foreach ( $methods as $method_name ) {
            $reflection = new \ReflectionMethod( Plugin::class, $method_name );
            $this->assertTrue( $reflection->isPublic(), "Method $method_name should be public" );
            $this->assertTrue( $reflection->isStatic(), "Method $method_name should be static" );
        }
    }

    /**
     * Test class namespace
     */
    public function testPluginNamespace() {
        $reflection = new \ReflectionClass( Plugin::class );
        $this->assertEquals( 'SnippenBooking', $reflection->getNamespaceName() );
    }
}
