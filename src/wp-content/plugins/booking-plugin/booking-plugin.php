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
        
        register_activation_hook(__FILE__, array(__CLASS__, 'activate'));
    }

    /**
     * Activate the plugin
     */
    public static function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Time slots table
        $table_slots = $wpdb->prefix . 'snippen_time_slots';
        $sql_slots = "CREATE TABLE $table_slots (
            id INT NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            start_time TIME DEFAULT '00:00:00',
            end_time TIME DEFAULT '23:59:59',
            cleanup_hours INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            modified_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";
        dbDelta($sql_slots);

        // Bookings table
        $table_bookings = $wpdb->prefix . 'snippen_bookings';
        $sql_bookings = "CREATE TABLE $table_bookings (
            id BIGINT NOT NULL AUTO_INCREMENT,
            facility VARCHAR(50) NOT NULL,
            slot_id INT NOT NULL,
            booking_date DATE NOT NULL,
            customer_name VARCHAR(255) NOT NULL,
            customer_email VARCHAR(255) NOT NULL,
            customer_phone VARCHAR(50) DEFAULT '',
            description TEXT,
            status VARCHAR(20) DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            modified_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL,
            PRIMARY KEY (id),
            INDEX (booking_date),
            INDEX (facility),
            INDEX (slot_id)
        ) $charset_collate;";
        dbDelta($sql_bookings);

        // Insert default slot if not exists
        $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_slots WHERE name = %s", 'Hele dagen'));
        if (!$exists) {
            $wpdb->insert($table_slots, array(
                'name' => 'Hele dagen',
                'description' => 'Du booker rommet for hele dagen, og har til kl 12 neste dag til å vaske deg ut.',
                'start_time' => '00:00:00',
                'end_time' => '23:59:59',
                'cleanup_hours' => 12
            ));
        }
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
                        <option value="spisestuen">Spisestuen</option>
                        <option value="peisestuen">Peisestuen</option>
                    </select>
                </div>

                <div class="form-group" id="booking-date-group">
                    <label for="event-date">Date:</label>
                    <input type="date" name="event_date" id="event-date" required readonly>
                </div>

                <div class="form-group" id="booking-slot-group">
                    <label for="slot-id">Time Slot:</label>
                    <select name="slot_id" id="slot-id" required>
                        <!-- Options will be populated by JS -->
                    </select>
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
 * Get availability for a given facility and week
 */
function snippen_get_availability() {
    global $wpdb;
    
    $facility = sanitize_text_field($_GET['facility']);
    $start_date = sanitize_text_field($_GET['start_date']); // YYYY-MM-DD
    
    // Lead time (N days) - hardcoded for now, could be an option
    $offset_days = 0;
    
    // Calculate end date (7 days)
    $end_date = date('Y-m-d', strtotime($start_date . ' + 6 days'));
    
    $table_slots = $wpdb->prefix . 'snippen_time_slots';
    $table_bookings = $wpdb->prefix . 'snippen_bookings';
    
    // Get all active slots
    $slots = $wpdb->get_results("SELECT id, name, description, start_time, end_time FROM $table_slots WHERE deleted_at IS NULL");
    
    // Get all bookings for the range
    $bookings = $wpdb->get_results($wpdb->prepare(
        "SELECT booking_date, slot_id FROM $table_bookings 
         WHERE facility = %s 
         AND booking_date BETWEEN %s AND %s 
         AND deleted_at IS NULL",
        $facility, $start_date, $end_date
    ));
    
    // Organize bookings by date and slot
    $booked_slots = array();
    foreach ($bookings as $booking) {
        $booked_slots[$booking->booking_date][] = (int)$booking->slot_id;
    }
    
    wp_send_json_success(array(
        'slots' => $slots,
        'booked' => $booked_slots,
        'offset_days' => $offset_days
    ));
}
add_action('wp_ajax_snippen_get_availability', 'snippen_get_availability');
add_action('wp_ajax_nopriv_snippen_get_availability', 'snippen_get_availability');

/**
 * Handle AJAX booking submission
 */
function snippen_booking_submit() {
    global $wpdb;
    
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'snippen_booking_nonce')) {
        wp_send_json_error(array('message' => 'Security check failed'));
    }

    $facility = sanitize_text_field($_POST['facility']);
    $booking_date = sanitize_text_field($_POST['event_date']);
    $slot_id = intval($_POST['slot_id']);
    
    if (empty($facility) || empty($booking_date) || empty($slot_id)) {
        wp_send_json_error(array('message' => 'Mangler nødvendige felt.'));
    }

    // Check if already booked
    $table_bookings = $wpdb->prefix . 'snippen_bookings';
    $is_booked = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_bookings 
         WHERE facility = %s AND booking_date = %s AND slot_id = %d AND deleted_at IS NULL",
        $facility, $booking_date, $slot_id
    ));
    
    if ($is_booked) {
        wp_send_json_error(array('message' => 'Denne slotten er allerede booket.'));
    }

    // Process booking data
    $booking_data = array(
        'facility' => $facility,
        'booking_date' => $booking_date,
        'slot_id' => $slot_id,
        'customer_name' => sanitize_text_field($_POST['name']),
        'customer_email' => sanitize_email($_POST['email']),
        'customer_phone' => sanitize_text_field($_POST['phone']),
        'description' => sanitize_textarea_field($_POST['description']),
        'status' => 'pending',
        'created_at' => current_time('mysql')
    );

    $result = $wpdb->insert($table_bookings, $booking_data);

    if ($result) {
        // Send email notification (optional but good to keep)
        $to = get_option('admin_email');
        $subject = 'Ny Bookingforespørsel - ' . $facility;
        $message = "Ny bookingforespørsel mottatt:\n\n";
        $message .= "Lokale: " . $facility . "\n";
        $message .= "Dato: " . $booking_date . "\n";
        $message .= "Navn: " . $booking_data['customer_name'] . "\n";
        $message .= "Email: " . $booking_data['customer_email'] . "\n";
        $message .= "Telefon: " . $booking_data['customer_phone'] . "\n";
        $message .= "Beskrivelse: " . $booking_data['description'] . "\n";

        wp_mail($to, $subject, $message);

        wp_send_json_success(array(
            'message' => 'Bookingforespørsel sendt! Vi kontakter deg snart.'
        ));
    } else {
        wp_send_json_error(array('message' => 'Kunne ikke lagre bookingen. Vennligst prøv igjen.'));
    }
}

add_action('wp_ajax_snippen_booking_submit', 'snippen_booking_submit');
add_action('wp_ajax_nopriv_snippen_booking_submit', 'snippen_booking_submit');

