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
        $this->assertStringContainsString( __( 'Hurtiglenker til bookingsider:', 'snippen-booking' ), $output );
        $this->assertStringContainsString( 'Test Booking Page X', $output );
        $this->assertStringContainsString( get_permalink( $page_id ), $output );
    }

    /**
     * Test that render_tagged_pages renders nothing when no pages are tagged
     */
    public function test_render_tagged_pages_empty_output() {
        $existing = get_posts( array( 'post_type' => 'page', 'posts_per_page' => -1, 'tax_query' => array( array( 'taxonomy' => 'post_tag', 'field' => 'slug', 'terms' => 'snippen-booking' ) ) ) );
        foreach( $existing as $p ) { wp_delete_post( $p->ID, true ); }
        $bookings_page = new BookingsPage();
        
        $reflection = new \ReflectionClass( BookingsPage::class );
        $method = $reflection->getMethod( 'render_tagged_pages' );
        $method->setAccessible( true );

        ob_start();
        $method->invoke( $bookings_page );
        $output = ob_get_clean();

        $this->assertEmpty( $output );
    }

    /**
     * Test that render_filters outputs the correct hidden page parameter slug
     */
    public function test_render_filters_page_parameter() {
        $bookings_page = new BookingsPage();
        
        $reflection = new \ReflectionClass( BookingsPage::class );
        $method = $reflection->getMethod( 'render_filters' );
        $method->setAccessible( true );

        ob_start();
        // Arguments: status, obj_id, s, show_all
        $method->invoke( $bookings_page, '', 0, '', false );
        $output = ob_get_clean();

        $this->assertStringContainsString( '<input type="hidden" name="page" value="snippen-booking">', $output );
        $this->assertStringNotContainsString( 'value="snippen-booking-oversikt"', $output );
    }

    /**
     * Test that render_list renders time range for booking block bookings
     */
    public function test_render_list_time_range() {
        global $wpdb;

        // 1. Insert test booking block
        $wpdb->insert(
            $wpdb->prefix . 'snippen_booking_blocks',
            array(
                'name'       => 'Morgenblokk',
                'start_time' => '09:00:00',
                'end_time'   => '12:00:00',
            )
        );
        $block_id = $wpdb->insert_id;

        // 2. Insert test booking
        $wpdb->insert(
            $wpdb->prefix . 'snippen_bookings',
            array(
                'booking_date'  => '2026-09-01',
                'customer_name' => 'Kjell Test',
                'customer_email'=> 'kjell@example.com',
                'status'        => 'confirmed',
            )
        );
        $booking_id = $wpdb->insert_id;

        // 3. Link booking to block
        $wpdb->insert(
            $wpdb->prefix . 'snippen_booking_booking_blocks',
            array(
                'booking_id'       => $booking_id,
                'booking_block_id' => $block_id,
            )
        );

        $bookings_page = new BookingsPage();
        $reflection    = new \ReflectionClass( BookingsPage::class );
        $method        = $reflection->getMethod( 'render_list' );
        $method->setAccessible( true );

        ob_start();
        $method->invoke( $bookings_page, '', '', 0, 'Kjell Test', 'booking_date', 'ASC', true );
        $output = ob_get_clean();

        $this->assertStringContainsString( 'Tidsrom:', $output );
        $this->assertStringContainsString( '09:00 - 12:00', $output );
    }
}
