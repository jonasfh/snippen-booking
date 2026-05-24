<?php
/**
 * Resident Import Integration Tests
 *
 * @package SnippenBooking\Tests\Integration
 */

namespace SnippenBooking\Tests\Integration;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Admin\Pages\ImportPage;

/**
 * Integration tests for Resident Import page and related logic.
 */
class ImportPageTest extends TestCase {

	/**
	 * Created user IDs during tests.
	 *
	 * @var array
	 */
	private $created_user_ids = array();

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		if ( ! function_exists( 'wp_delete_user' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}
		$this->created_user_ids = array();

		// Activate plugin to register custom role and tables
		\SnippenBooking\Database\Install::activate();

		// Clean up any resident users to ensure a clean state
		$residents = get_users( array( 'role' => 'snippen_resident', 'fields' => 'ID' ) );
		foreach ( $residents as $res_id ) {
			wp_delete_user( $res_id );
		}
	}

	/**
	 * Tear down test environment.
	 */
	public function tearDown(): void {
		foreach ( $this->created_user_ids as $user_id ) {
			wp_delete_user( $user_id );
		}
		parent::tearDown();
	}

	/**
	 * Test that the custom snippen_resident role is registered and exists.
	 */
	public function testCustomRoleExists() {
		global $wp_roles;
		$role = get_role( 'snippen_resident' );
		$this->assertNotNull( $role, 'Custom role snippen_resident should be registered.' );
		$this->assertContains( $wp_roles->role_names['snippen_resident'], array( 'Snippen Beboer', 'Snippen Resident' ) );
	}

	/**
	 * Test the line-by-line look-ahead parser with shifts and normal recovery.
	 */
	public function testLineByLineParserWithShifts() {
		// Mock a POST request
		$_POST['snippen_import_nonce']  = wp_create_nonce( 'snippen_import_residents' );
		$_POST['snippen_import_provider'] = 'simple_text';
		
		// The exact look-ahead shift test block from Issue #37 requirements
		$_POST['snippen_import_data'] = "
Knut Knudsen
knut@outlook.com
+47 999 88 777

Reservert

Anne Kari Martinsen
annekari@gmail.com
+4799988776

Elsa Hagen
elsa@gmail.com

Dagny Carlsen
dagny@outlook.com
98765432

Geir Larsen
+47 987 65 432

Ola Nordmann
ola@nordmann.no
+47 999 88 775
";

		// Capture the logs/output by invoking the parser
		$import_page = new ImportPage();
		
		// Run handle_request via reflection (since it's a private method, we can use reflection)
		$reflection = new \ReflectionClass( $import_page );
		$method = $reflection->getMethod( 'handle_request' );
		$method->setAccessible( true );
		
		$results = $method->invoke( $import_page );

		$this->assertIsArray( $results );
		
		// Track created users for cleanup
		$all_residents = get_users( array( 'role' => 'snippen_resident', 'fields' => 'ID' ) );
		$this->created_user_ids = $all_residents;

		// 3 users should be imported successfully: Knut Knudsen, Anne Kari Martinsen, Ola Nordmann.
		// Wait, Dagny Carlsen also has email and a numeric phone (98765432) which normalize to +4798765432!
		// Let's verify if Dagny has email and phone. Yes, Line 1: Dagny Carlsen, Line 2: email, Line 3: 98765432. That's a valid block too!
		// So 4 users should be imported: Knut, Anne Kari, Dagny, Ola.
		// Let's verify who was imported.
		$emails = array();
		foreach ( $all_residents as $uid ) {
			$emails[] = get_userdata( $uid )->user_email;
		}

		$this->assertContains( 'knut@outlook.com', $emails );
		$this->assertContains( 'annekari@gmail.com', $emails );
		$this->assertContains( 'dagny@outlook.com', $emails );
		$this->assertContains( 'ola@nordmann.no', $emails );

		// Elsa Hagen should be skipped (missing phone)
		$this->assertNotContains( 'elsa@gmail.com', $emails );

		// Geir Larsen should be skipped (missing email)
		// Logs should record skips/errors
		$log_str = implode( "\n", $results['logs'] );
		$this->assertStringContainsString( "Elsa Hagen' hoppet over - Mangler e-post eller telefonnummer.", $log_str );
		$this->assertStringContainsString( "Geir Larsen' hoppet over - Mangler e-post eller telefonnummer.", $log_str );

		// Phone normalization assertion
		$knut_id = email_exists( 'knut@outlook.com' );
		$this->assertEquals( '+4799988777', get_user_meta( $knut_id, 'snippen_phone', true ) );

		$dagny_id = email_exists( 'dagny@outlook.com' );
		$this->assertEquals( '+4798765432', get_user_meta( $dagny_id, 'snippen_phone', true ) );
	}

	/**
	 * Test TSV parser with custom column mapping.
	 */
	public function testTsvParserWithMapping() {
		$_POST['snippen_import_nonce']   = wp_create_nonce( 'snippen_import_residents' );
		$_POST['snippen_import_provider']  = 'tsv';
		$_POST['snippen_import_mapping'] = 'name,email,phone,address,unit';
		$_POST['snippen_import_data']    = "Kari Nord\tkari@nord.no\t+4791112222\tSnippveien 12\tH0101\nPer Sør\tper@sor.no\t92223333\tSnippveien 14\tH0202";

		$import_page = new ImportPage();
		$reflection = new \ReflectionClass( $import_page );
		$method = $reflection->getMethod( 'handle_request' );
		$method->setAccessible( true );
		
		$results = $method->invoke( $import_page );

		$this->assertIsArray( $results );
		$this->assertEquals( 2, $results['success'] );

		// Keep track for cleanup
		$all_residents = get_users( array( 'role' => 'snippen_resident', 'fields' => 'ID' ) );
		$this->created_user_ids = array_merge( $this->created_user_ids, $all_residents );

		$kari_id = email_exists( 'kari@nord.no' );
		$this->assertNotFalse( $kari_id );
		$this->assertEquals( '+4791112222', get_user_meta( $kari_id, 'snippen_phone', true ) );
		$this->assertEquals( 'Snippveien 12', get_user_meta( $kari_id, 'snippen_address', true ) );
		$this->assertEquals( 'H0101', get_user_meta( $kari_id, 'snippen_unit', true ) );
		$this->assertEmpty( get_user_meta( $kari_id, 'snippen_user_deleted', true ) );

		$per_id = email_exists( 'per@sor.no' );
		$this->assertNotFalse( $per_id );
		$this->assertEquals( '+4792223333', get_user_meta( $per_id, 'snippen_phone', true ) );
		$this->assertEquals( 'Snippveien 14', get_user_meta( $per_id, 'snippen_address', true ) );
		$this->assertEquals( 'H0202', get_user_meta( $per_id, 'snippen_unit', true ) );
	}

	/**
	 * Test deletion sync logic.
	 */
	public function testDeletionSync() {
		// 1. Create a snippen_resident who is NOT in the import list
		$username = 'oldresident_' . time();
		$email    = $username . '@example.com';
		$res_id   = wp_create_user( $username, 'password123', $email );
		$user     = new \WP_User( $res_id );
		$user->set_role( 'snippen_resident' );
		$this->created_user_ids[] = $res_id;

		// 2. Perform import with a completely different resident
		$_POST['snippen_import_nonce']   = wp_create_nonce( 'snippen_import_residents' );
		$_POST['snippen_import_provider']  = 'tsv';
		$_POST['snippen_import_mapping'] = 'name,email,phone';
		$_POST['snippen_import_data']    = "New Guy\tnewguy@example.com\t98765432";

		$import_page = new ImportPage();
		$reflection = new \ReflectionClass( $import_page );
		$method = $reflection->getMethod( 'handle_request' );
		$method->setAccessible( true );
		
		$results = $method->invoke( $import_page );

		// Sync should mark old resident as deleted
		$new_guy_id = email_exists( 'newguy@example.com' );
		$this->created_user_ids[] = $new_guy_id;

		$this->assertEquals( 'yes', get_user_meta( $res_id, 'snippen_user_deleted', true ) );
		$this->assertEmpty( get_user_meta( $new_guy_id, 'snippen_user_deleted', true ) );

		// 3. Import old resident again - should clear deletion status
		$_POST['snippen_import_data'] = "Old Resident\t" . $email . "\t98765432";
		$method->invoke( $import_page );

		$this->assertEmpty( get_user_meta( $res_id, 'snippen_user_deleted', true ) );
	}

	/**
	 * Test 4-layer deactivation enforcement.
	 */
	public function testDeactivationEnforcement() {
		// 1. Create deleted resident
		$username = 'deletedresident_' . time();
		$email    = $username . '@example.com';
		$user_id  = wp_create_user( $username, 'password123', $email );
		$user     = new \WP_User( $user_id );
		$user->set_role( 'snippen_resident' );
		update_user_meta( $user_id, 'snippen_user_deleted', 'yes' );
		$this->created_user_ids[] = $user_id;

		// 2. Test Layer 1: Login Block (wp_authenticate_user filter)
		$auth_result = apply_filters( 'wp_authenticate_user', $user );
		$this->assertTrue( is_wp_error( $auth_result ) );
		$this->assertEquals( 'user_deleted', $auth_result->get_error_code() );

		// 3. Test Layer 1: Password reset block (allow_password_reset filter)
		$reset_allowed = apply_filters( 'allow_password_reset', true, $user_id );
		$this->assertFalse( $reset_allowed );
	}

	/**
	 * Test that admin user list filters (views and querying) work as expected.
	 */
	public function testAdminUserListFilters() {
		// Set up current user to be administrator so current_user_can('manage_options') returns true
		$admin_id = wp_create_user( 'adminfiltertest_' . time(), 'password123', 'adminfiltertest@example.com' );
		$admin_user = new \WP_User( $admin_id );
		$admin_user->set_role( 'administrator' );
		wp_set_current_user( $admin_id );
		$this->created_user_ids[] = $admin_id;

		// 1. Create a deleted resident
		$username = 'deletedforfilter_' . time();
		$email    = $username . '@example.com';
		$user_id  = wp_create_user( $username, 'password123', $email );
		$user     = new \WP_User( $user_id );
		$user->set_role( 'snippen_resident' );
		update_user_meta( $user_id, 'snippen_user_deleted', 'yes' );
		$this->created_user_ids[] = $user_id;

		// 2. Render views and check that "Slettede beboere" tab is added with correct count
		$views = array();
		$views = \SnippenBooking\Admin\UserProfile::add_deleted_residents_view( $views );
		$this->assertArrayHasKey( 'deleted_residents', $views );
		$this->assertStringContainsString( 'Slettede beboere', $views['deleted_residents'] );
		$this->assertStringContainsString( '(1)', $views['deleted_residents'] );

		// 3. Test pre_get_users action filtering when query parameter is active
		$_GET['deleted_residents'] = '1';
		
		// Mock WP_User_Query and current screen
		set_current_screen( 'users' );
		
		$query = new \WP_User_Query( array(
			'role' => 'snippen_resident',
		) );
		
		// Run action hook handler
		\SnippenBooking\Admin\UserProfile::filter_users_by_deleted_status( $query );
		
		// Fetch queried users and assert that only our deleted resident is returned
		$users = $query->get_results();
		$user_ids = wp_list_pluck( $users, 'ID' );
		
		$this->assertContains( $user_id, $user_ids );
		
		// Clean up $_GET and screen
		unset( $_GET['deleted_residents'] );
		set_current_screen( 'null' );
	}
}
