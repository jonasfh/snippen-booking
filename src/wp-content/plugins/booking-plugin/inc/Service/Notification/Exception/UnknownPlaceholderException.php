<?php
/**
 * Unknown Placeholder Exception
 *
 * @package SnippenBooking\Service\Notification\Exception
 */

namespace SnippenBooking\Service\Notification\Exception;

/**
 * Class UnknownPlaceholderException
 */
class UnknownPlaceholderException extends PlaceholderException {

	/**
	 * Placeholder name
	 *
	 * @var string
	 */
	private $placeholder_name;

	/**
	 * Constructor
	 *
	 * @param string $placeholder_name Placeholder name.
	 */
	public function __construct( string $placeholder_name ) {
		$this->placeholder_name = $placeholder_name;
		parent::__construct( sprintf( 'Placeholder "%s" is not registered in the system.', $placeholder_name ) );
	}

	/**
	 * Get placeholder name
	 *
	 * @return string
	 */
	public function get_placeholder_name(): string {
		return $this->placeholder_name;
	}
}
