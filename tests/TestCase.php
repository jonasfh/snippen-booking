<?php

namespace SnippenBooking\Tests;

/**
 * Base test case class for all tests
 */
abstract class TestCase extends \PHPUnit\Framework\TestCase {

    /**
     * Whether the test requires database setup and seed data.
     */
    protected $requires_db = true;

    /**
     * Whether to create seed data before each test.
     */
    protected $requires_seed_data = true;

    /**
     * Track if database has been seeded for the current test suite session.
     */
    protected static $db_seeded = false;

    /**
     * Set up test environment
     */
    protected function setUp(): void {
        parent::setUp();
        global $wpdb;
        
        // Prevent translations from loading during tests to keep original Norwegian strings for assertions
        unload_textdomain('snippen-booking');
        
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        if ($this->requires_db && function_exists('update_option')) {
            update_option( 'snippen_enable_door_code', 'yes' );
        }
        
        // Globally prevent wp_mail from dispatching actual emails during tests
        add_filter( 'pre_wp_mail', array( $this, 'global_prevent_wp_mail' ), 5, 2 );
        // Globally mock remote HTTP requests to KeySMS to prevent actual SMS dispatch
        add_filter( 'pre_http_request', array( $this, 'global_mock_http_requests' ), 5, 3 );

        if ($this->requires_db && isset($wpdb)) {
            if ($this->requires_seed_data) {
                // If tables are empty, force re-seeding regardless of the static flag
                if (self::$db_seeded) {
                    $blocks_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}snippen_booking_blocks");
                    if ($blocks_count === 0) {
                        self::$db_seeded = false;
                    }
                }

                if (!self::$db_seeded) {
                    $wpdb->query("DELETE FROM {$wpdb->users} WHERE ID > 1");
                    $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE user_id > 1");
                    $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}snippen_bookings");
                    $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}snippen_booking_booking_blocks");
                    $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}snippen_booking_booking_objects");
                    $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}snippen_booking_objects");
                    $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}snippen_booking_blocks");
                    $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}snippen_booking_object_booking_blocks");
                    $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}snippen_pricing_rules");
                    $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}snippen_pricing_rule_booking_blocks");
                    $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}snippen_pricing_rule_booking_objects");
                    $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}snippen_time_slots");
                    $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}snippen_prices");
                    $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}snippen_time_slot_booking_objects");
                    $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}snippen_discount_rules");
                    $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}snippen_discount_rule_booking_objects");
                    $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}snippen_bookings_booking_objects");
                    
                    \SnippenBooking\Admin\SetupWizard::create_starter_setup();
                    self::$db_seeded = true;
                }
            } else {
                $wpdb->query("DELETE FROM {$wpdb->users} WHERE ID > 1");
                $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE user_id > 1");
                $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}snippen_bookings");
                $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}snippen_booking_booking_blocks");
                $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}snippen_booking_booking_objects");
                $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}snippen_booking_objects");
                $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}snippen_booking_blocks");
                $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}snippen_booking_object_booking_blocks");
                $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}snippen_pricing_rules");
                $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}snippen_pricing_rule_booking_blocks");
                $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}snippen_pricing_rule_booking_objects");
                $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}snippen_time_slots");
                $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}snippen_prices");
                $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}snippen_time_slot_booking_objects");
                $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}snippen_discount_rules");
                $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}snippen_discount_rule_booking_objects");
                $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}snippen_bookings_booking_objects");
                
                self::$db_seeded = false;
            }
            
            $wpdb->query("START TRANSACTION");
        }
    }

    /**
     * Tear down test environment
     */
     protected function tearDown(): void {
         global $wpdb;
         if ($this->requires_db && isset($wpdb)) {
             $wpdb->query("ROLLBACK");
         }
         if (function_exists('wp_cache_flush')) {
             wp_cache_flush();
         }
         if ($this->requires_db && function_exists('delete_option')) {
             delete_option( 'snippen_enable_door_code' );
         }
         remove_filter( 'pre_wp_mail', array( $this, 'global_prevent_wp_mail' ), 5 );
         remove_filter( 'pre_http_request', array( $this, 'global_mock_http_requests' ), 5 );
         parent::tearDown();
     }

     /**
      * Global mock filter to prevent actual wp_mail sending during tests.
      */
     public function global_prevent_wp_mail( $preempt, $args ) {
         return true; // Abort real mail delivery
     }

     /**
      * Global mock filter to prevent KeySMS remote HTTP POST requests.
      */
     public function global_mock_http_requests( $preempt, $parsed_args, $url ) {
         if ( strpos( $url, 'keysms.no' ) !== false ) {
             return array(
                 'headers'  => array(),
                 'body'     => '{"ok":true}',
                 'response' => array(
                     'code'    => 200,
                     'message' => 'OK',
                 ),
                 'cookies'  => array(),
                 'filename' => null,
             );
         }
         return $preempt;
     }
}
