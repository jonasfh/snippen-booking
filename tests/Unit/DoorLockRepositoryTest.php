<?php
/**
 * Tests for DoorLockRepository
 *
 * @package SnippenBooking\Tests\Unit
 */

namespace SnippenBooking\Tests\Unit;

use SnippenBooking\Tests\TestCase;
use SnippenBooking\Database\Repository\DoorLockRepository;

/**
 * Class DoorLockRepositoryTest
 */
class DoorLockRepositoryTest extends TestCase {

	/**
	 * Requires database
	 */
	protected $requires_db = true;

	/**
	 * Test CRUD operations on DoorLockRepository
	 */
	public function test_crud_operations() {
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_door_locks" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}snippen_booking_objects" );

		$repo = new DoorLockRepository();

		// Create
		$lock_id = $repo->create(
			array(
				'name'        => 'Hovedinngang',
				'description' => 'Felles inngangsdør mot gaten',
			)
		);
		$this->assertGreaterThan( 0, $lock_id );

		// Find
		$lock = $repo->find( $lock_id );
		$this->assertNotNull( $lock );
		$this->assertSame( 'Hovedinngang', $lock->name );
		$this->assertSame( 'Felles inngangsdør mot gaten', $lock->description );
		$this->assertNotNull( $lock->created_at );
		$this->assertNotNull( $lock->modified_at );

		// Update
		$updated = $repo->update(
			$lock_id,
			array(
				'name'        => 'Hovedinngang (Endret)',
				'description' => 'Oppdatert beskrivelse',
			)
		);
		$this->assertTrue( $updated );

		$lock_updated = $repo->find( $lock_id );
		$this->assertSame( 'Hovedinngang (Endret)', $lock_updated->name );
		$this->assertSame( 'Oppdatert beskrivelse', $lock_updated->description );

		// Soft Delete
		$deleted = $repo->delete( $lock_id );
		$this->assertTrue( $deleted );

		$lock_deleted = $repo->find( $lock_id );
		$this->assertNull( $lock_deleted );

		$all_active = $repo->find_all( false );
		$this->assertCount( 0, $all_active );

		$all_including_deleted = $repo->find_all( true );
		$this->assertCount( 1, $all_including_deleted );
		$this->assertNotNull( $all_including_deleted[0]->deleted_at );
	}
}
