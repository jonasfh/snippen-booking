<?php
/**
 * Plugin Name: Snippen Booking
 * Description: Booking plugin for Snippen community house.
 * Version: 0.1.0
 * Author: Snippen
 * Text Domain: snippen-booking
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Load autoloader
require_once __DIR__ . '/autoloader.php';

// Initialize plugin
\SnippenBooking\Plugin::init();

// Initialize Plugin Update Checker for automatic updates via GitHub
require_once __DIR__ . '/lib/plugin-update-checker/plugin-update-checker.php';
$myUpdateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
    'https://github.com/jonasfh/snippen-booking/',
    __FILE__,
    'snippen-booking'
);
$myUpdateChecker->setBranch('main');
