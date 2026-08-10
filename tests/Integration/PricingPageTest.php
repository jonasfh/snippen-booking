<?php

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Admin\Pages\PricingPage;

class PricingPageTest extends TestCase {

	public function test_pricing_page_renders_block_details_in_rule_form_and_preview() {
		global $wpdb;

		// Insert test block with name, description, start/end time, and inactive state
		$table_blocks = $wpdb->prefix . 'snippen_booking_blocks';
		$wpdb->insert(
			$table_blocks,
			array(
				'name'        => 'Slot 8-9',
				'description' => 'Hverdager formiddag',
				'start_time'  => '08:00:00',
				'end_time'    => '09:00:00',
				'is_active'   => 0,
				'sort_order'  => 1,
				'created_at'  => current_time( 'mysql' ),
				'modified_at' => current_time( 'mysql' ),
			)
		);
		$block_id = $wpdb->insert_id;

		$page       = new PricingPage();
		$reflection = new \ReflectionClass( PricingPage::class );

		// 1. Test render_form outputs name, description, active status
		$form_method = $reflection->getMethod( 'render_form' );
		$form_method->setAccessible( true );

		ob_start();
		$form_method->invoke( $page, 0 );
		$form_output = ob_get_clean();

		$this->assertStringContainsString( 'Slot 8-9 (08:00-09:00) - Hverdager formiddag [Inaktiv]', $form_output );

		// 2. Test render_preview_tool outputs name, description, active status in option list
		$preview_method = $reflection->getMethod( 'render_preview_tool' );
		$preview_method->setAccessible( true );

		ob_start();
		$preview_method->invoke( $page );
		$preview_output = ob_get_clean();

		$this->assertStringContainsString( 'Slot 8-9 (08:00-09:00) - Hverdager formiddag [Inaktiv]', $preview_output );
	}
}
