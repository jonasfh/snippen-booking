<?php
/**
 * Unit Tests for NotificationTemplateRepository
 *
 * @package SnippenBooking\Tests\Unit\Repository
 */

namespace SnippenBooking\Tests\Unit\Repository;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Database\Repository\NotificationTemplateRepository;

/**
 * Class NotificationTemplateRepositoryTest
 */
class NotificationTemplateRepositoryTest extends TestCase {

	/**
	 * Repository instance
	 *
	 * @var NotificationTemplateRepository
	 */
	private $repository;

	/**
	 * Set up test environment
	 */
	public function setUp(): void {
		parent::setUp();
		$this->repository = new NotificationTemplateRepository();
		global $wpdb;
		$table = $wpdb->prefix . 'snippen_notification_templates';
		$wpdb->query( "TRUNCATE TABLE {$table}" );
	}

	/**
	 * Test seeding defaults idempotently
	 */
	public function test_seed_defaults_is_idempotent() {
		$this->repository->seed_defaults();
		$templates = $this->repository->get_all();
		$this->assertCount( 8, $templates );

		// Seed again, should not create duplicates
		$this->repository->seed_defaults();
		$templates_again = $this->repository->get_all();
		$this->assertCount( 8, $templates_again );
	}

	/**
	 * Test CRUD operations
	 */
	public function test_crud_operations() {
		$id = $this->repository->create(
			array(
				'name'         => 'Manual Template',
				'type'         => 'email',
				'title'        => 'Test Subject',
				'message'      => 'Hello {{user_name}}',
				'connected_to' => null,
			)
		);

		$this->assertIsInt( $id );
		$this->assertGreaterThan( 0, $id );

		$found = $this->repository->find( $id );
		$this->assertNotNull( $found );
		$this->assertEquals( 'Manual Template', $found->name );

		$updated = $this->repository->update( $id, array( 'name' => 'Updated Name' ) );
		$this->assertTrue( $updated );

		$found_updated = $this->repository->find( $id );
		$this->assertEquals( 'Updated Name', $found_updated->name );

		$deleted = $this->repository->delete( $id );
		$this->assertTrue( $deleted );
		$this->assertNull( $this->repository->find( $id ) );
	}

	/**
	 * Test connected_to uniqueness constraint
	 */
	public function test_connected_to_uniqueness_constraint() {
		$id1 = $this->repository->create(
			array(
				'name'         => 'Template 1',
				'type'         => 'email',
				'title'        => 'Subject 1',
				'message'      => 'Msg 1',
				'connected_to' => 'account-activation',
			)
		);
		$this->assertIsInt( $id1 );

		// Attempt duplicate for same connected_to and type
		$id2 = $this->repository->create(
			array(
				'name'         => 'Template 2',
				'type'         => 'email',
				'title'        => 'Subject 2',
				'message'      => 'Msg 2',
				'connected_to' => 'account-activation',
			)
		);
		$this->assertFalse( $id2 );
	}

	/**
	 * Test alias connected_to names map to canonical key and prevent duplicate creation
	 */
	public function test_alias_connected_to_uniqueness() {
		$id1 = $this->repository->create(
			array(
				'name'         => 'Template 1',
				'type'         => 'email',
				'title'        => 'Subject 1',
				'message'      => 'Msg 1',
				'connected_to' => 'user_activation',
			)
		);
		$this->assertIsInt( $id1 );

		// Attempt duplicate using hyphenated alias account-activation
		$id2 = $this->repository->create(
			array(
				'name'         => 'Template 2',
				'type'         => 'email',
				'title'        => 'Subject 2',
				'message'      => 'Msg 2',
				'connected_to' => 'account-activation',
			)
		);
		$this->assertFalse( $id2 );

		// Searching using either alias should return the same row
		$row1 = $this->repository->find_by_connected_and_type( 'user_activation', 'email' );
		$row2 = $this->repository->find_by_connected_and_type( 'account-activation', 'email' );
		$this->assertNotNull( $row1 );
		$this->assertEquals( $row1->id, $row2->id );
	}
}
