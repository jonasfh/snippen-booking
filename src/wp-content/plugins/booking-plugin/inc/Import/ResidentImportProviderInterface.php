<?php
/**
 * Resident Import Provider Interface
 *
 * @package SnippenBooking\Import
 */

namespace SnippenBooking\Import;

/**
 * Interface ResidentImportProviderInterface
 */
interface ResidentImportProviderInterface {
	/**
	 * Get the unique identifier for this provider.
	 */
	public function get_id(): string;

	/**
	 * Get the display name for this provider.
	 */
	public function get_name(): string;

	/**
	 * Get a description of how this provider works.
	 */
	public function get_description(): string;

	/**
	 * Render the UI for the provider's input form.
	 */
	public function render_ui(): void;

	/**
	 * Process the import based on the provided input.
	 *
	 * @param mixed $input Form input (typically $_POST data).
	 * @return ResidentImportResult
	 */
	public function import( $input ): ResidentImportResult;
}
