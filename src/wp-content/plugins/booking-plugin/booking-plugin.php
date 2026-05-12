<?php
/**
 * Plugin Name: Snippen Booking
 * Description: Booking plugin for Snippen community house.
 * Version: 0.1.0
 * Author: Snippen
 * Text Domain: snippen-booking
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Load autoloader
require_once __DIR__ . '/autoloader.php';

// Initialize plugin
\SnippenBooking\Plugin::init();

