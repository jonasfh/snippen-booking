<?php

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Admin\Pages\BookingsPage;

class BookingsPageTest extends TestCase {

    private $page_ids = array();

    protected function setUp(): void {
        parent::setUp();
        
        // Ensure post_tag taxonomy is registered for pages
        register_taxonomy_for_object_type( 'post_tag', 'page' );
    }

    protected function tearDown(): void {
        // Clean up created pages
        foreach ( $this->page_ids as $page_id ) {
            wp_delete_post( $page_id, true );
        }
        parent::tearDown();
    }

    /**
     * Test that render_tagged_pages renders the links when pages are tagged with 'snippen-booking'
     */
    public function test_render_tagged_pages_output() {
        // 1. Create a WordPress page
        $page_id = wp_insert_post( array(
            'post_title'   => 'Test Booking Page X',
            'post_content' => '[snippen-booking]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ) );
        
        if ( is_wp_error( $page_id ) ) {
            $this->fail( 'Failed to create test page: ' . $page_id->get_error_message() );
        }
        $this->page_ids[] = $page_id;

        // 2. Tag it with 'snippen-booking'
        wp_set_object_terms( $page_id, 'snippen-booking', 'post_tag' );

        // 3. Instantiate BookingsPage and capture output of the private method via reflection
        $bookings_page = new BookingsPage();
        
        $reflection = new \ReflectionClass( BookingsPage::class );
        $method = $reflection->getMethod( 'render_tagged_pages' );
        $method->setAccessible( true );

        ob_start();
        $method->invoke( $bookings_page );
        $output = ob_get_clean();

        // 4. Assertions
        $this->assertStringContainsString( 'Hurtiglenker til bookingsider:', $output );
        $this->assertStringContainsString( 'Test Booking Page X', $output );
        $this->assertStringContainsString( get_permalink( $page_id ), $output );
    }

    /**
     * Test that render_tagged_pages renders nothing when no pages are tagged
     */
    public function test_render_tagged_pages_empty_output() {
        $bookings_page = new BookingsPage();
        
        $reflection = new \ReflectionClass( BookingsPage::class );
        $method = $reflection->getMethod( 'render_tagged_pages' );
        $method->setAccessible( true );

        ob_start();
        $method->invoke( $bookings_page );
        $output = ob_get_clean();

        $this->assertEmpty( $output );
    }
}
