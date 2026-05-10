<?php
/**
 * Plugin Name: Snippen Booking
 * Description: Booking plugin for Snippen community house.
 * Version: 0.1.0
 * Author: Snippen
 * Text Domain: snippen-booking
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main plugin class
 */
class SnippenBooking {

    /**
     * Initialize the plugin
     */
    public static function init() {
        add_action('init', array(__CLASS__, 'register_shortcodes'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_scripts'));
    }

    /**
     * Register shortcodes
     */
    public static function register_shortcodes() {
        add_shortcode('snippen_booking', array(__CLASS__, 'booking_shortcode'));
    }

    /**
     * Enqueue scripts and styles
     */
    public static function enqueue_scripts() {
        wp_enqueue_style(
            'snippen-booking-style',
            plugin_dir_url(__FILE__) . 'css/booking.css',
            array(),
            '0.1.0'
        );

        wp_enqueue_script(
            'snippen-booking-script',
            plugin_dir_url(__FILE__) . 'js/booking.js',
            array('jquery'),
            '0.1.0',
            true
        );

        wp_localize_script('snippen-booking-script', 'snippenBookingAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('snippen_booking_nonce')
        ));
    }

    /**
     * Booking shortcode handler
     */
    public static function booking_shortcode($atts) {
        $atts = shortcode_atts(array(
            'type' => 'form', // 'form' or 'calendar'
            'facility' => '', // specific facility ID
        ), $atts);

        ob_start();

        if ($atts['type'] === 'calendar') {
            self::render_calendar($atts);
        } else {
            self::render_booking_form($atts);
        }

        return ob_get_clean();
    }

    /**
     * Render booking form
     */
    private static function render_booking_form($atts) {
        ?>
        <div class="snippen-booking-form">
            <h3>Book a facility at Snippen</h3>
            <form id="booking-form" method="post">
                <div class="form-group">
                    <label for="facility">Facility:</label>
                    <select name="facility" id="facility" required>
                        <option value="">Select a facility</option>
                        <option value="main-hall">Main Hall</option>
                        <option value="kitchen">Kitchen</option>
                        <option value="meeting-room">Meeting Room</option>
                        <option value="garden">Garden Area</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="event-date">Date:</label>
                    <input type="date" name="event_date" id="event-date" required>
                </div>

                <div class="form-group">
                    <label for="start-time">Start Time:</label>
                    <input type="time" name="start_time" id="start-time" required>
                </div>

                <div class="form-group">
                    <label for="end-time">End Time:</label>
                    <input type="time" name="end_time" id="end-time" required>
                </div>

                <div class="form-group">
                    <label for="name">Your Name:</label>
                    <input type="text" name="name" id="name" required>
                </div>

                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" name="email" id="email" required>
                </div>

                <div class="form-group">
                    <label for="phone">Phone:</label>
                    <input type="tel" name="phone" id="phone">
                </div>

                <div class="form-group">
                    <label for="description">Event Description:</label>
                    <textarea name="description" id="description" rows="4"></textarea>
                </div>

                <button type="submit" class="booking-submit">Submit Booking Request</button>
            </form>

            <div id="booking-response" style="display: none;"></div>
        </div>
        <?php
    }

    /**
     * Render calendar view
     */
    private static function render_calendar($atts) {
        ?>
        <div class="snippen-booking-calendar">
            <h3>Facility Availability Calendar</h3>
            <div id="calendar-container">
                <!-- Calendar will be rendered here by JavaScript -->
                <p>Calendar view coming soon...</p>
            </div>
        </div>
        <?php
    }
}

// Initialize the plugin
SnippenBooking::init();

/**
 * Handle AJAX booking submission
 */
function snippen_booking_submit() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'snippen_booking_nonce')) {
        wp_die('Security check failed');
    }

    // Process booking data
    $booking_data = array(
        'facility' => sanitize_text_field($_POST['facility']),
        'event_date' => sanitize_text_field($_POST['event_date']),
        'start_time' => sanitize_text_field($_POST['start_time']),
        'end_time' => sanitize_text_field($_POST['end_time']),
        'name' => sanitize_text_field($_POST['name']),
        'email' => sanitize_email($_POST['email']),
        'phone' => sanitize_text_field($_POST['phone']),
        'description' => sanitize_textarea_field($_POST['description']),
        'submitted_at' => current_time('mysql')
    );

    // Here you would typically save to database
    // For now, just send an email notification

    $to = get_option('admin_email');
    $subject = 'New Booking Request - ' . $booking_data['facility'];
    $message = "New booking request received:\n\n";
    $message .= "Facility: " . $booking_data['facility'] . "\n";
    $message .= "Date: " . $booking_data['event_date'] . "\n";
    $message .= "Time: " . $booking_data['start_time'] . " - " . $booking_data['end_time'] . "\n";
    $message .= "Name: " . $booking_data['name'] . "\n";
    $message .= "Email: " . $booking_data['email'] . "\n";
    $message .= "Phone: " . $booking_data['phone'] . "\n";
    $message .= "Description: " . $booking_data['description'] . "\n";

    wp_mail($to, $subject, $message);

    wp_send_json_success(array(
        'message' => 'Booking request submitted successfully! We will contact you soon.'
    ));
}

add_action('wp_ajax_snippen_booking_submit', 'snippen_booking_submit');
add_action('wp_ajax_nopriv_snippen_booking_submit', 'snippen_booking_submit');

