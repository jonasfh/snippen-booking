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
        $this->assertStringContainsString( 'snippen-quick-links', $output );
        $this->assertStringContainsString( 'snippen-quick-link', $output );
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
        $this->assertStringContainsString( '<small>Morgenblokk (09:00 - 12:00)</small>', $output );
    }

    /**
     * Test that render_booking_row outputs HH:mm formatted time in the date column
     */
    public function test_render_booking_row_displays_hh_mm_time() {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'snippen_bookings',
            array(
                'booking_date'     => '2026-09-15',
                'customer_name'    => 'Ola Nordmann',
                'customer_email'   => 'ola@example.com',
                'status'           => 'confirmed',
                'booking_snapshot' => wp_json_encode( array(
                    'start_time'           => '14:00:00',
                    'end_time'             => '18:00:00',
                    'time_range_formatted' => '14:00 - 18:00',
                ) ),
            )
        );
        $booking_id = $wpdb->insert_id;

        $bookings_page = new BookingsPage();
        $reflection    = new \ReflectionClass( BookingsPage::class );
        $method        = $reflection->getMethod( 'render_booking_row' );
        $method->setAccessible( true );

        $booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}snippen_bookings WHERE id = %d", $booking_id ) );

        ob_start();
        $method->invoke( $bookings_page, $booking );
        $output = ob_get_clean();

        $this->assertStringContainsString( '14:00 - 18:00', $output );
        $this->assertStringContainsString( '<small>14:00 - 18:00</small>', $output );
    }

    /**
     * Test that Migration_2_9_0 adds column and backfills existing booking_snapshot
     */
    public function test_migration_2_9_0_backfills_booking_snapshot() {
        global $wpdb;

        // Create block
        $wpdb->insert(
            $wpdb->prefix . 'snippen_booking_blocks',
            array(
                'name'       => 'Kveldsblokk',
                'start_time' => '17:00:00',
                'end_time'   => '22:00:00',
            )
        );
        $block_id = $wpdb->insert_id;

        // Create booking without snapshot
        $wpdb->insert(
            $wpdb->prefix . 'snippen_bookings',
            array(
                'booking_date'  => '2026-10-15',
                'customer_name' => 'Kari Nordmann',
                'customer_email'=> 'kari@example.com',
                'status'        => 'confirmed',
                'price'         => 1200,
            )
        );
        $booking_id = $wpdb->insert_id;

        // Link block
        $wpdb->insert(
            $wpdb->prefix . 'snippen_booking_booking_blocks',
            array(
                'booking_id'       => $booking_id,
                'booking_block_id' => $block_id,
            )
        );

        // Run Migration_2_9_0
        $migration = new \SnippenBooking\Database\Migrations\Migration_2_9_0();
        $migration->up();

        $snapshot_raw = $wpdb->get_var(
            $wpdb->prepare( "SELECT booking_snapshot FROM {$wpdb->prefix}snippen_bookings WHERE id = %d", $booking_id )
        );

        $this->assertNotEmpty( $snapshot_raw );
        $snapshot = json_decode( $snapshot_raw, true );

        $this->assertEquals( '17:00:00', $snapshot['start_time'] );
        $this->assertEquals( '22:00:00', $snapshot['end_time'] );
        $this->assertEquals( '17:00 - 22:00', $snapshot['time_range_formatted'] );
        $this->assertEquals( 'Kveldsblokk', $snapshot['blocks'][0]['name'] );
    }

    /**
     * Test that render_details outputs communication history with collapsible toggle button and without nested scrollbars
     */
    public function test_render_booking_details_communication_toggle_and_structure() {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'snippen_bookings',
            array(
                'booking_date'   => '2026-09-20',
                'customer_name'  => 'Test Person',
                'customer_email' => 'testperson@example.com',
                'customer_phone' => '+4799887766',
                'status'         => 'confirmed',
                'price'          => 1500,
            )
        );
        $booking_id = $wpdb->insert_id;

        // Log a test message for this booking
        \SnippenBooking\Service\Notification\MessageLoggerService::log_message(
            $booking_id,
            null,
            'email',
            'testperson@example.com',
            'Bookingbekreftelse',
            'Hei Test Person, din booking er bekreftet.',
            'booking_confirmation',
            'sent'
        );

        $bookings_page = new BookingsPage();
        $reflection    = new \ReflectionClass( BookingsPage::class );
        $method        = $reflection->getMethod( 'render_booking_row' );
        $method->setAccessible( true );

        $booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}snippen_bookings WHERE id = %d", $booking_id ) );

        ob_start();
        $method->invoke( $bookings_page, $booking );
        $output = ob_get_clean();

        // Check header, toggle button, and closed body
        $this->assertStringContainsString( 'class="booking-messages-history"', $output );
        $this->assertStringContainsString( 'class="msg-history-header"', $output );
        $this->assertStringContainsString( 'class="button button-small toggle-msg-history"', $output );
        $this->assertStringContainsString( 'Vis kommunikasjon', $output );
        $this->assertStringContainsString( 'class="msg-history-body" style="display:none;"', $output );

        // Check message body markup
        $this->assertStringContainsString( 'class="msg-item-body"', $output );
        $this->assertStringContainsString( 'Hei Test Person, din booking er bekreftet.', $output );
        $this->assertStringContainsString( '<div class="msg-item" data-event-type="booking_confirmation">', $output );

        // Ensure event filter controls are dropped
        $this->assertStringNotContainsString( 'class="msg-filter-controls"', $output );
        $this->assertStringNotContainsString( 'msg-filter-cb', $output );

        // Ensure no internal scrollbar styles are present
        $this->assertStringNotContainsString( 'max-height:240px; overflow-y:auto;', $output );
        $this->assertStringNotContainsString( 'max-height:100px; overflow-y:auto;', $output );
    }
}
