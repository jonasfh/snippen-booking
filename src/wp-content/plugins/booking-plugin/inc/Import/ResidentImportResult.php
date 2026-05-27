<?php
/**
 * Resident Import Result
 *
 * @package SnippenBooking\Import
 */

namespace SnippenBooking\Import;

/**
 * Class ResidentImportResult
 */
class ResidentImportResult {
	public int $success        = 0;
	public int $deleted        = 0;
	public array $logs         = array();
	public array $imported_ids = array();
}
