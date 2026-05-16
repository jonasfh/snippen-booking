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

        // Parse comma-separated object IDs
        $object_ids = array_map('intval', explode(',', $atts['object_id']));
        $object_ids = array_filter($object_ids);
        
        global $wpdb;
        $table_objects = $wpdb->prefix . 'snippen_booking_objects';
        
        if (empty($object_ids)) {
            return '<div class="snippen-booking-error">Ugyldig objekt-ID.</div>';
        }

        $in_clause = implode(',', array_fill(0, count($object_ids), '%d'));
        $query = $wpdb->prepare("SELECT * FROM $table_objects WHERE id IN ($in_clause) AND deleted_at IS NULL", ...$object_ids);
        $objects = $wpdb->get_results($query);

        if ( empty($objects) ) {
            return '<div class="snippen-booking-error">Booking-objekt(er) ikke funnet.</div>';
        }

        // Combine names and info
        $object_names = wp_list_pluck($objects, 'name');
        $combined_name = implode(' og ', $object_names);
        
        $descriptions = wp_list_pluck($objects, 'description');
        $combined_description = implode(' ', array_filter($descriptions));
        
        // Use first object's link if available
        $info_link = '';
        foreach ($objects as $obj) {
            if (!empty($obj->info_link)) {
                $info_link = $obj->info_link;
                break;
            }
        }

        $is_logged_in = is_user_logged_in();
        $current_user = wp_get_current_user();
        $user_name = $is_logged_in ? esc_attr( $current_user->display_name ) : '';
        $user_email = $is_logged_in ? esc_attr( $current_user->user_email ) : '';
        $user_phone = $is_logged_in ? get_user_meta( $current_user->ID, 'snippen_phone', true ) : '';

        ob_start();
        ?>
        <div class="snippen-booking-container" 
             data-object-id="<?php echo esc_attr( wp_json_encode( $object_ids ) ); ?>" 
             data-logged-in="<?php echo $is_logged_in ? 'true' : 'false'; ?>"
             data-is-admin="<?php echo current_user_can( 'manage_options' ) ? 'true' : 'false'; ?>">
            <div class="booking-header-section">
                <div class="header-main">
                    <h3><?php echo esc_html( $combined_name ); ?></h3>
                    <?php if ( $info_link ) : ?>
                        <a href="<?php echo esc_url( $info_link ); ?>" class="info-link" target="_blank">Mer info &rarr;</a>
                    <?php endif; ?>
                </div>
                <?php if ( $combined_description ) : ?>
                    <p class="object-summary"><?php echo esc_html( $combined_description ); ?></p>
                <?php endif; ?>
            </div>

            <?php if ( ! $is_logged_in ) : ?>
                <div class="snippen-login-prompt">
                    <p>Du må være beboer og innlogget for å kunne booke. Kalenderen under viser kun tilgjengelighet.</p>
                    <a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="vipps-login-btn">Logg inn med Vipps</a>
                </div>
            <?php endif; ?>

            <div id="calendar-container" class="snippen-calendar-view <?php echo ! $is_logged_in ? 'readonly-mode' : ''; ?>">
                <div class="calendar-loader">Laster kalender...</div>
            </div>

            <?php if ( $is_logged_in ) : ?>
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
                    <input type="hidden" name="booking_object_id" value="<?php echo esc_attr( wp_json_encode( $object_ids ) ); ?>">
                    <input type="hidden" name="user_id" id="selected-user-id" value="<?php echo get_current_user_id(); ?>">
                    
                    <div class="form-grid">
                        <?php if ( current_user_can( 'manage_options' ) ) : ?>
                        <div class="form-group full-width admin-only-field">
                            <label for="user-search">Søk etter beboer (Admin)</label>
                            <div class="user-search-wrapper">
                                <input type="text" id="user-search" placeholder="Begynn å skrive navn eller e-post..." autocomplete="off" value="<?php echo $user_name; ?>">
                                <div id="user-search-results" class="search-results-dropdown" style="display: none;"></div>
                            </div>
                            <p class="description">La feltet være tomt for å booke i ditt eget navn.</p>
                        </div>
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="name">Navn på beboer</label>
                            <input type="text" name="name" id="name" required placeholder="Fullt navn" value="<?php echo $user_name; ?>" <?php echo current_user_can('manage_options') ? '' : 'readonly'; ?>>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">E-post</label>
                            <input type="email" name="email" id="email" required placeholder="navn@eksempel.no" value="<?php echo $user_email; ?>" <?php echo current_user_can('manage_options') ? '' : 'readonly'; ?>>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Telefon</label>
                            <input type="tel" name="phone" id="phone" placeholder="+47..." value="<?php echo esc_attr( $user_phone ); ?>" readonly required>
                            <?php if ( $is_logged_in && empty( $user_phone ) && ! current_user_can( 'manage_options' ) ) : ?>
                                <p class="field-error-msg" style="color: #d63638; font-size: 0.85em; margin-top: 5px;">Mangler telefonnummer på din profil. Kontakt administrator.</p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="description">Beskrivelse av arrangement (valgfritt)</label>
                            <textarea name="description" id="description" rows="3" placeholder="F.eks. Bursdag, møte, etc."></textarea>
                        </div>
                    </div>

                    <button type="submit" class="booking-submit" <?php echo ( $is_logged_in && empty( $user_phone ) && ! current_user_can( 'manage_options' ) ) ? 'disabled' : ''; ?>>
                        Send bookingforespørsel
                    </button>
                </form>
                <div id="booking-response" style="display: none;"></div>
            </div>
            <?php endif; ?>

            <?php if ( current_user_can( 'manage_options' ) ) : ?>
            <div id="booking-info-modal" class="snippen-modal" style="display: none;">
                <div class="modal-overlay"></div>
                <div class="modal-content">
                    <div class="modal-header">
                        <h4>Bookingdetaljer</h4>
                        <button type="button" class="close-modal">&times;</button>
                    </div>
                    <div class="modal-body" id="booking-info-content"></div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
