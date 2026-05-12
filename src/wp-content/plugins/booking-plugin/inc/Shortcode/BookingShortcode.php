<?php

namespace SnippenBooking\Shortcode;

/**
 * Handles booking shortcode rendering
 */
class BookingShortcode {

    /**
     * Register the shortcode
     */
    public static function register() {
        add_shortcode( 'snippen_booking', array( __CLASS__, 'render' ) );
    }

    /**
     * Render the booking shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string
     */
    public static function render( $atts ) {
        $atts = shortcode_atts( array(
            'type' => 'form', // 'form' or 'calendar'
            'facility' => '', // specific facility ID
        ), $atts );

        ob_start();

        if ( $atts['type'] === 'calendar' ) {
            self::render_calendar( $atts );
        } else {
            self::render_booking_form( $atts );
        }

        return ob_get_clean();
    }

    /**
     * Render booking form
     *
     * @param array $atts
     */
    private static function render_booking_form( $atts ) {
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
     *
     * @param array $atts
     */
    private static function render_calendar( $atts ) {
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
