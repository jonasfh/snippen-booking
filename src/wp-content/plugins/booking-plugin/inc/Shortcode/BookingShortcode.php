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
            'object_id' => 1,
        ), $atts );

        global $wpdb;
        $table_objects = $wpdb->prefix . 'snippen_booking_objects';
        $object = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_objects WHERE id = %d AND deleted_at IS NULL", $atts['object_id'] ) );

        if ( ! $object ) {
            return '<div class="snippen-booking-error">Booking-objekt (ID: ' . esc_html( $atts['object_id'] ) . ') ikke funnet.</div>';
        }

        ob_start();
        ?>
        <div class="snippen-booking-container" data-object-id="<?php echo esc_attr( $atts['object_id'] ); ?>">
            <div class="booking-header-section">
                <div class="header-main">
                    <h3><?php echo esc_html( $object->name ); ?></h3>
                    <?php if ( $object->info_link ) : ?>
                        <a href="<?php echo esc_url( $object->info_link ); ?>" class="info-link" target="_blank">Mer info &rarr;</a>
                    <?php endif; ?>
                </div>
                <?php if ( $object->description ) : ?>
                    <p class="object-summary"><?php echo esc_html( $object->description ); ?></p>
                <?php endif; ?>
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
                    <input type="hidden" name="booking_object_id" value="<?php echo esc_attr( $atts['object_id'] ); ?>">
                    
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
