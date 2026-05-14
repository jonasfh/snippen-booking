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
            __( 'Snippen Booking', 'snippen-booking' ),
            __( 'Snippen Booking', 'snippen-booking' ),
            'manage_options',
            'snippen-booking',
            array( __CLASS__, 'render_dashboard' ),
            'dashicons-calendar-alt',
            30
        );

        add_submenu_page(
            'snippen-booking',
            __( 'Lokaler', 'snippen-booking' ),
            __( 'Lokaler', 'snippen-booking' ),
            'manage_options',
            'snippen-booking-objects',
            array( __CLASS__, 'render_objects_page' )
        );

        add_submenu_page(
            'snippen-booking',
            __( 'Tidsluker', 'snippen-booking' ),
            __( 'Tidsluker', 'snippen-booking' ),
            'manage_options',
            'snippen-booking-slots',
            array( __CLASS__, 'render_slots_page' )
        );

        add_submenu_page(
            'snippen-booking',
            __( 'Prising', 'snippen-booking' ),
            __( 'Prising', 'snippen-booking' ),
            'manage_options',
            'snippen-booking-pricing',
            array( __CLASS__, 'render_pricing_page' )
        );
        
        // Remove the default duplicate menu item
        remove_submenu_page( 'snippen-booking', 'snippen-booking' );
        
        // Re-add dashboard as the first submenu item if desired, or just use the first one
        add_submenu_page(
            'snippen-booking',
            __( 'Oversikt', 'snippen-booking' ),
            __( 'Oversikt', 'snippen-booking' ),
            'manage_options',
            'snippen-booking',
            array( __CLASS__, 'render_dashboard' )
        );
    }

    /**
     * Enqueue admin assets
     */
    public static function enqueue_admin_assets( $hook ) {
        if ( strpos( $hook, 'snippen-booking' ) === false ) {
            return;
        }

        wp_enqueue_style( 'snippen-booking-admin', plugins_url( 'css/admin.css', dirname( dirname( __FILE__ ) ) ), array(), '0.1.0' );
        wp_enqueue_script( 'snippen-booking-admin', plugins_url( 'js/admin.js', dirname( dirname( __FILE__ ) ) ), array( 'jquery' ), '0.1.0', true );
    }

    /**
     * Render Dashboard
     */
    public static function render_dashboard() {
        echo '<div class="wrap"><h1>' . esc_html__( 'Snippen Booking Oversikt', 'snippen-booking' ) . '</h1></div>';
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
