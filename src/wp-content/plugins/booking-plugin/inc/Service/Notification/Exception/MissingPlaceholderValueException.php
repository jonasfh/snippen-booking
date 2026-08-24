<?php
/**
 * Missing Placeholder Value Exception
 *
 * @package SnippenBooking\Service\Notification\Exception
 */

namespace SnippenBooking\Service\Notification\Exception;

/**
 * Class MissingPlaceholderValueException
 */
class MissingPlaceholderValueException extends PlaceholderException {

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
		parent::__construct( sprintf( 'Value for placeholder "%s" is missing in context.', $placeholder_name ) );
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
