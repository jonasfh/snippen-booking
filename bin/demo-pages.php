<?php
/**
 * Generate Demo Pages for Snippen Booking
 * 
 * Usage:
 * php bin/demo-pages.php
 */

// Bootstrap WordPress
$abspath = getenv( 'WP_ABSPATH' ) ?: '/wordpress/';
if ( ! file_exists( $abspath . 'wp-load.php' ) ) {
    echo "Error: WordPress not found at $abspath\n";
    exit(1);
}

// Set up globals that WP expects
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET';

require_once $abspath . 'wp-load.php';

global $wpdb;
$table_objects = $wpdb->prefix . 'snippen_booking_objects';

echo "Cleaning up old demo pages...\n";
// Delete existing pages with 'Booking Demo' in title to avoid duplicates
$existing_pages = get_pages();
foreach ($existing_pages as $page) {
    if (strpos($page->post_title, 'Booking Demo') !== false) {
        wp_delete_post($page->ID, true);
    }
}

$objects = $wpdb->get_results("SELECT id, name FROM $table_objects WHERE deleted_at IS NULL");

if (empty($objects)) {
    echo "Error: No booking objects found. Please activate the plugin first.\n";
    exit(1);
}

echo "Generating new demo pages...\n";

// 1. Create individual pages for each object
$all_ids = [];
foreach ($objects as $obj) {
    $all_ids[] = $obj->id;
    
    $page_title = 'Booking Demo - ' . $obj->name;
    $page_content = '[snippen_booking object_id="' . $obj->id . '"]';
    
    $new_page = array(
        'post_type'    => 'page',
        'post_title'   => $page_title,
        'post_content' => $page_content,
        'post_status'  => 'publish',
        'post_author'  => 1,
    );
    $post_id = wp_insert_post( $new_page );
    if ( $post_id && ! is_wp_error( $post_id ) ) {
        wp_set_object_terms( $post_id, 'snippen-booking', 'post_tag' );
    }
    echo "Created: $page_title\n";
}

// 2. Create combined page
if (count($objects) > 1) {
    $page_title = 'Booking Demo - Felleskalender';
    $page_content = '[snippen_booking object_id="' . implode(',', $all_ids) . '"]';
    
    $new_page = array(
        'post_type'    => 'page',
        'post_title'   => $page_title,
        'post_content' => $page_content,
        'post_status'  => 'publish',
        'post_author'  => 1,
    );
    $post_id = wp_insert_post( $new_page );
    if ( $post_id && ! is_wp_error( $post_id ) ) {
        wp_set_object_terms( $post_id, 'snippen-booking', 'post_tag' );
    }
    echo "Created: $page_title\n";
}

// 3. Create Account Confirmation page
$page_title = 'Booking Demo - Aktivering av konto';
$page_content = '[snippen_account_confirmation]' . "\n\n<h3>Mine Bookinger (Demo)</h3>\n" . '[snippen_booking_list login-form=1]';

$new_page = array(
    'post_type'    => 'page',
    'post_title'   => $page_title,
    'post_content' => $page_content,
    'post_status'  => 'publish',
    'post_author'  => 1,
);
wp_insert_post( $new_page );
echo "Created: $page_title\n";

echo "Success: Demo pages generated.\n";
exit(0);
