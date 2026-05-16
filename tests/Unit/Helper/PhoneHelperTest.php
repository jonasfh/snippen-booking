<?php
namespace SnippenBooking\Tests\Unit\Helper;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Helper\PhoneHelper;

class PhoneHelperTest extends TestCase {

    public function testNormalizePhone() {
        // Valid 8 digit numbers
        $this->assertEquals('+4790688031', PhoneHelper::normalize_phone('90688031'));
        $this->assertEquals('+4790688031', PhoneHelper::normalize_phone('90 68 80 31'));
        $this->assertEquals('+4790688031', PhoneHelper::normalize_phone('90-68-80-31'));

        // Valid 10 digit numbers (with country code)
        $this->assertEquals('+4790688031', PhoneHelper::normalize_phone('4790688031'));
        $this->assertEquals('+4790688031', PhoneHelper::normalize_phone('+4790688031'));
        $this->assertEquals('+4790688031', PhoneHelper::normalize_phone('+47 906 88 031'));
        $this->assertEquals('+4790688031', PhoneHelper::normalize_phone('004790688031'));

        // Invalid numbers (foreign or wrong length)
        $this->assertFalse(PhoneHelper::normalize_phone('1234567')); // Too short
        $this->assertFalse(PhoneHelper::normalize_phone('123456789')); // 9 digits
        $this->assertFalse(PhoneHelper::normalize_phone('4690688031')); // Swedish country code
        $this->assertFalse(PhoneHelper::normalize_phone('+4690688031')); // Swedish country code
        $this->assertFalse(PhoneHelper::normalize_phone('abc')); // Letters
    }

    public function testIsPhoneUnique() {
        // Create two users for testing
        $user_id_1 = \wp_create_user('testuser1' . time(), 'password', 'test1' . time() . '@example.com');
        $user_id_2 = \wp_create_user('testuser2' . time(), 'password', 'test2' . time() . '@example.com');

        if ( \is_wp_error( $user_id_1 ) ) {
            $this->fail( 'Failed to create user 1: ' . $user_id_1->get_error_message() );
        }

        // Assign a phone number to user 1
        $test_phone = '+47111' . rand(10000, 99999);
        \update_user_meta($user_id_1, 'snippen_phone', $test_phone);

        // Check uniqueness
        $this->assertTrue(PhoneHelper::is_phone_unique('+4722222222')); // Not in use
        $this->assertFalse(PhoneHelper::is_phone_unique($test_phone)); // Used by user 1

        // Check uniqueness excluding user 1
        $this->assertTrue(PhoneHelper::is_phone_unique($test_phone, $user_id_1)); // Used by user 1, but excluded

        // Cleanup
        if (!function_exists('wp_delete_user')) {
            require_once(ABSPATH . 'wp-admin/includes/user.php');
        }
        \wp_delete_user($user_id_1);
        \wp_delete_user($user_id_2);
    }
}
