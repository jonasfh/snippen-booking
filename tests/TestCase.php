<?php

namespace SnippenBooking\Tests;

/**
 * Base test case class for all tests
 */
abstract class TestCase extends \PHPUnit\Framework\TestCase {

    /**
     * Set up test environment
     */
    protected function setUp(): void {
        parent::setUp();
    }

    /**
     * Tear down test environment
     */
    protected function tearDown(): void {
        parent::tearDown();
    }
}
