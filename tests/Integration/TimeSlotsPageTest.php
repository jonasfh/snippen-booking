<?php

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Admin\Pages\TimeSlotsPage;

class TimeSlotsPageTest extends TestCase {

    /**
     * Test that the edit form renders the associated price name, amount, and edit link
     */
    public function test_render_form_shows_associated_price() {
        global $wpdb;

        // 1. Get a time slot
        $slot = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}snippen_time_slots LIMIT 1" );
        if ( ! $slot ) {
            $this->fail( 'No time slots found in test database.' );
        }

        // 2. Create a price and associate it with the slot
        $wpdb->insert(
            "{$wpdb->prefix}snippen_prices",
            array(
                'name'        => 'Test Prisregel X',
                'price'       => 1500,
                'priority'    => 10,
                'created_at'  => current_time( 'mysql' ),
                'modified_at' => current_time( 'mysql' ),
            )
        );
        $price_id = $wpdb->insert_id;

        $wpdb->update(
            "{$wpdb->prefix}snippen_time_slots",
            array( 'price_id' => $price_id ),
            array( 'id' => $slot->id )
        );

        // 3. Render the form using reflection on the private render_form method
        $page = new TimeSlotsPage();
        $reflection = new \ReflectionClass( TimeSlotsPage::class );
        $method = $reflection->getMethod( 'render_form' );
        $method->setAccessible( true );

        ob_start();
        $method->invoke( $page, $slot->id );
        $output = ob_get_clean();

        // 4. Assertions
        $this->assertStringContainsString( 'Tilknyttet pris', $output );
        $this->assertStringContainsString( 'Test Prisregel X', $output );
        $this->assertStringContainsString( '1 500 kr', $output );
        $this->assertStringContainsString( 'page=snippen-booking-pricing', $output );
        $this->assertStringContainsString( 'action=edit', $output );
        $this->assertStringContainsString( 'id=' . $price_id, $output );
    }

    /**
     * Test that the edit form shows "Ingen pris tilknyttet" when no price is set
     */
    public function test_render_form_shows_no_price_message() {
        global $wpdb;

        $slot = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}snippen_time_slots LIMIT 1" );
        if ( ! $slot ) {
            $this->fail( 'No time slots found in test database.' );
        }

        // Dissociate any price
        $wpdb->update(
            "{$wpdb->prefix}snippen_time_slots",
            array( 'price_id' => null ),
            array( 'id' => $slot->id )
        );

        $page = new TimeSlotsPage();
        $reflection = new \ReflectionClass( TimeSlotsPage::class );
        $method = $reflection->getMethod( 'render_form' );
        $method->setAccessible( true );

        ob_start();
        $method->invoke( $page, $slot->id );
        $output = ob_get_clean();

        $this->assertStringContainsString( 'Tilknyttet pris', $output );
        $this->assertStringContainsString( 'Ingen pris tilknyttet.', $output );
    }

    /**
     * Test that the empty state list table uses colspan="7"
     */
    public function test_render_list_colspan_when_empty() {
        global $wpdb;

        // Clear all slots temporarily to force empty state
        $wpdb->query( "DELETE FROM {$wpdb->prefix}snippen_time_slots" );

        $page = new TimeSlotsPage();
        $reflection = new \ReflectionClass( TimeSlotsPage::class );
        $method = $reflection->getMethod( 'render_list' );
        $method->setAccessible( true );

        ob_start();
        $method->invoke( $page, '' );
        $output = ob_get_clean();

        $this->assertStringContainsString( '<td colspan="7">', $output );
    }
}
