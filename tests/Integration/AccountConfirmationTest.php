<?php
namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Service\AccountConfirmationService;

class AccountConfirmationTest extends TestCase {

    public function setUp(): void {
        parent::setUp();
        if (!function_exists('wp_delete_user')) {
            require_once(ABSPATH . 'wp-admin/includes/user.php');
        }
        \update_option('snippen_sms_account_confirmation_enabled', 'yes');
        \update_option('snippen_keysms_username', 'testuser');
        \update_option('snippen_keysms_api_key', 'testkey');
    }

    public function testAccountConfirmationFlow() {
        // 1. Create a user
        $username = 'confirmtest' . time();
        $user_id = \wp_create_user($username, 'password', $username . '@example.com');
        if (\is_wp_error($user_id)) {
            $this->fail('Failed to create user: ' . $user_id->get_error_message());
        }
        \update_user_meta($user_id, 'snippen_phone', '+4799887766');
        
        $service = new AccountConfirmationService();
        $this->assertFalse($service->is_confirmed($user_id));

        // 2. Generate and verify code
        $code = $service->generate_code($user_id);
        $this->assertTrue($service->verify_code($user_id, $code));

        // 3. Confirm account
        $result = $service->confirm_account($user_id, 'newpassword123');
        $this->assertTrue($result);
        $this->assertTrue($service->is_confirmed($user_id));
        
        // 4. Verify password was updated
        $user = \get_user_by('id', $user_id);
        $this->assertTrue(\wp_check_password('newpassword123', $user->user_pass, $user->ID));

        // Cleanup
        \wp_delete_user($user_id);
    }

    public function testApiRequestCode() {
        // Create user
        $username = 'apirequest' . time();
        $user_id = \wp_create_user($username, 'password', $username . '@example.com');
        if (\is_wp_error($user_id)) {
             $this->fail('Failed to create user: ' . $user_id->get_error_message());
        }
        \update_user_meta($user_id, 'snippen_phone', '+4711223344');

        $_POST['phone'] = '+4711223344';
        $_POST['nonce'] = \wp_create_nonce('snippen_confirmation_nonce');
        $_REQUEST = $_POST;
        
        $output = '';
        add_filter(
            'wp_die_ajax_handler',
            function() {
                return function( $message ) {
                    throw new \Exception( is_string( $message ) ? $message : wp_json_encode( $message ) );
                };
            }
        );

        ob_start();
        try {
            \SnippenBooking\Api\UserApi::request_confirmation_code();
        } catch ( \Exception $e ) {
            $output = $e->getMessage();
        }
        if ( empty( $output ) ) {
            $output = ob_get_clean();
        } else {
            ob_end_clean();
        }

        $json = json_decode( $output, true );
        $this->assertTrue( $json['success'] );
        $this->assertEquals( 'Bekreftelseskode er sendt på e-post eller SMS.', $json['data']['message'] );
        $this->assertEquals( $user_id, $json['data']['user_id'] );

        \wp_delete_user($user_id);
    }
}
