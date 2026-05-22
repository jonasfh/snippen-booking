<?php
/**
 * Import Manager
 *
 * @package SnippenBooking\Import
 */

namespace SnippenBooking\Import;

use SnippenBooking\Import\Provider\SimpleTextResidentImportProvider;
use SnippenBooking\Import\Provider\TsvResidentImportProvider;

/**
 * Class ImportManager
 */
class ImportManager {

	/**
	 * Get all registered import providers.
	 *
	 * @return ResidentImportProviderInterface[]
	 */
	public function get_providers(): array {
		$providers = array(
			new SimpleTextResidentImportProvider(),
			new TsvResidentImportProvider(),
		);

		return apply_filters( 'snippen_booking_import_providers', $providers );
	}

	/**
	 * Get a provider by its ID.
	 *
	 * @param string $id Provider ID.
	 * @return ResidentImportProviderInterface|null
	 */
	public function get_provider( string $id ): ?ResidentImportProviderInterface {
		foreach ( $this->get_providers() as $provider ) {
			if ( $provider->get_id() === $id ) {
				return $provider;
			}
		}
		return null;
	}
}
