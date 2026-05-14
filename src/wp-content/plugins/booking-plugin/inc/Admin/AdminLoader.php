<?php

namespace SnippenBooking\Admin;

/**
 * Main loader for the admin interface
 */
class AdminLoader {

    /**
     * Register admin hooks
     */
    public static function register() {
        if ( ! is_admin() ) {
            return;
        }

        add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
    }

    /**
     * Add admin menu and submenus
     */
    public static function add_admin_menu() {
        add_menu_page(
            __( 'Bookinger', 'snippen-booking' ),
            __( 'Bookinger', 'snippen-booking' ),
            'manage_options',
            'snippen-booking',
            array( self::class, 'render_bookings_page' ),
            'dashicons-calendar-alt',
            25
        );

        add_submenu_page(
            'snippen-booking',
            __( 'Oversikt', 'snippen-booking' ),
            __( 'Oversikt', 'snippen-booking' ),
            'manage_options',
            'snippen-booking',
            array( self::class, 'render_bookings_page' )
        );

        add_submenu_page(
            'snippen-booking',
            __( 'Lokaler', 'snippen-booking' ),
            __( 'Lokaler', 'snippen-booking' ),
            'manage_options',
            'snippen-booking-objects',
            array( self::class, 'render_objects_page' )
        );

        add_submenu_page(
            'snippen-booking',
            __( 'Tidsluker', 'snippen-booking' ),
            __( 'Tidsluker', 'snippen-booking' ),
            'manage_options',
            'snippen-booking-slots',
            array( self::class, 'render_slots_page' )
        );

        add_submenu_page(
            'snippen-booking',
            __( 'Prisregler', 'snippen-booking' ),
            __( 'Prisregler', 'snippen-booking' ),
            'manage_options',
            'snippen-booking-pricing',
            array( self::class, 'render_pricing_page' )
        );
    }

    /**
     * Enqueue Admin Assets
     */
    public static function enqueue_admin_assets( $hook ) {
        if ( strpos( $hook, 'snippen-booking' ) === false ) {
            return;
        }

        wp_enqueue_style( 'snippen-booking-admin', plugins_url( 'css/admin.css', dirname( __DIR__, 1 ) ), array(), '1.1.0' );
        wp_enqueue_script( 'snippen-booking-admin', plugins_url( 'js/admin.js', dirname( __DIR__, 1 ) ), array( 'jquery' ), '1.1.0', true );

        wp_localize_script( 'snippen-booking-admin', 'snippenAdmin', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'snippen_admin_nonce' ),
            'strings' => array(
                'confirmDelete' => __( 'Er du sikker på at du vil slette dette?', 'snippen-booking' ),
                'confirmCancel' => __( 'Vil du virkelig avbryte denne bookingen?', 'snippen-booking' ),
                'error'         => __( 'Det oppsto en feil. Prøv igjen.', 'snippen-booking' )
            )
        ) );
    }

    /**
     * Render Bookings Page
     */
    public static function render_bookings_page() {
        if ( class_exists( 'SnippenBooking\Admin\Pages\BookingsPage' ) ) {
            $page = new \SnippenBooking\Admin\Pages\BookingsPage();
            $page->render();
        } else {
            echo '<div class="wrap"><h1>' . esc_html__( 'Bookinger', 'snippen-booking' ) . '</h1><p>Under utvikling...</p></div>';
        }
    }

    /**
     * Render Objects Page
     */
    public static function render_objects_page() {
        if ( class_exists( 'SnippenBooking\Admin\Pages\BookingObjectsPage' ) ) {
            $page = new \SnippenBooking\Admin\Pages\BookingObjectsPage();
            $page->render();
        } else {
            echo '<div class="wrap"><h1>' . esc_html__( 'Lokaler', 'snippen-booking' ) . '</h1><p>Under utvikling...</p></div>';
        }
    }

    /**
     * Render Slots Page
     */
    public static function render_slots_page() {
        if ( class_exists( 'SnippenBooking\Admin\Pages\TimeSlotsPage' ) ) {
            $page = new \SnippenBooking\Admin\Pages\TimeSlotsPage();
            $page->render();
        } else {
            echo '<div class="wrap"><h1>' . esc_html__( 'Tidsluker', 'snippen-booking' ) . '</h1><p>Under utvikling...</p></div>';
        }
    }

    /**
     * Render Pricing Page
     */
    public static function render_pricing_page() {
        if ( class_exists( 'SnippenBooking\Admin\Pages\PricingPage' ) ) {
            $page = new \SnippenBooking\Admin\Pages\PricingPage();
            $page->render();
        } else {
            echo '<div class="wrap"><h1>' . esc_html__( 'Prisregler', 'snippen-booking' ) . '</h1><p>Under utvikling...</p></div>';
        }
    }
}
