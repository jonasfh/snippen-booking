<?php
/**
 * Migration 2.28.0
 *
 * @package SnippenBooking\Database\Migrations
 */

namespace SnippenBooking\Database\Migrations;

use SnippenBooking\Database\Repository\NotificationTemplateRepository;

/**
 * Migration 2.28.0
 * Seed default templates for booking-confirmed and payment-received events.
 */
class Migration_2_28_0 {

	/**
	 * Run migration
	 */
	public function up() {
		$repository = new NotificationTemplateRepository();
		$repository->seed_defaults();
	}
}
