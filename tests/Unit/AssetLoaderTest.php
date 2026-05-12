<?php

namespace SnippenBooking\Tests\Unit;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Assets\AssetLoader;

/**
 * Test suite for AssetLoader class
 */
class AssetLoaderTest extends TestCase {

    /**
     * Test that class has enqueue method
     */
    public function testClassHasEnqueueMethod() {
        $this->assertTrue( method_exists( AssetLoader::class, 'enqueue' ) );
    }

    /**
     * Test that enqueue method is public static
     */
    public function testEnqueueIsPublicStatic() {
        $reflection = new \ReflectionMethod( AssetLoader::class, 'enqueue' );
        $this->assertTrue( $reflection->isPublic() );
        $this->assertTrue( $reflection->isStatic() );
    }

    /**
     * Test that class uses private get_plugin_dir_url method
     */
    public function testClassHasGetPluginDirUrlMethod() {
        $reflection = new \ReflectionClass( AssetLoader::class );
        $this->assertTrue( $reflection->hasMethod( 'get_plugin_dir_url' ) );

        $method = $reflection->getMethod( 'get_plugin_dir_url' );
        $this->assertTrue( $method->isPrivate() );
        $this->assertTrue( $method->isStatic() );
    }

    /**
     * Test class methods count
     */
    public function testClassHasExpectedMethods() {
        $reflection = new \ReflectionClass( AssetLoader::class );
        $methods = $reflection->getMethods( \ReflectionMethod::IS_PUBLIC );

        // Should have at least enqueue method
        $method_names = array_map( function ( $m ) {
            return $m->getName();
        }, $methods );

        $this->assertContains( 'enqueue', $method_names );
    }
}
