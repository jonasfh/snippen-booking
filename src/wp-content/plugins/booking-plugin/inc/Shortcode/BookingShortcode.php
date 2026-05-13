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
            'facility' => 'spisestuen',
        ), $atts );

        ob_start();
        ?>
        <div class="snippen-booking-container">
            <div class="booking-header-section">
                <h3>Book lokale på Snippen</h3>
                <div class="facility-selector">
                    <label for="facility">Velg lokale:</label>
                    <div class="select-wrapper">
                        <select id="facility">
                            <option value="spisestuen" <?php selected( $atts['facility'], 'spisestuen' ); ?>>Spisestuen</option>
                            <option value="peisestuen" <?php selected( $atts['facility'], 'peisestuen' ); ?>>Peisestuen</option>
                        </select>
                    </div>
                </div>
            </div>

            <div id="calendar-container" class="snippen-calendar-view">
                <div class="calendar-loader">Laster kalender...</div>
            </div>

            <div id="booking-form-container" class="snippen-booking-form-wrapper" style="display: none;">
                <div class="form-header">
                    <div class="header-content">
                        <h4 id="selected-info-display"></h4>
                        <p id="selected-slot-description" class="slot-description-text"></p>
                    </div>
                    <button type="button" class="close-form">&times;</button>
                </div>
                
                <form id="booking-form" method="post">
                    <input type="hidden" name="event_date" id="event-date">
                    <input type="hidden" name="slot_id" id="slot-id">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="name">Ditt navn</label>
                            <input type="text" name="name" id="name" required placeholder="Fullt navn">
                        </div>
                        
                        <div class="form-group">
                            <label for="email">E-post</label>
                            <input type="email" name="email" id="email" required placeholder="navn@eksempel.no">
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Telefon</label>
                            <input type="tel" name="phone" id="phone" placeholder="8 siffer">
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="description">Beskrivelse av arrangement (valgfritt)</label>
                            <textarea name="description" id="description" rows="3" placeholder="F.eks. Bursdag, møte, etc."></textarea>
                        </div>
                    </div>

                    <button type="submit" class="booking-submit">Send bookingforespørsel</button>
                </form>
                <div id="booking-response" style="display: none;"></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
