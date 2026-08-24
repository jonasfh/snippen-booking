<?php
/**
 * Disallowed Placeholder Exception
 *
 * @package SnippenBooking\Service\Notification\Exception
 */

namespace SnippenBooking\Service\Notification\Exception;

/**
 * Class DisallowedPlaceholderException
 */
class DisallowedPlaceholderException extends PlaceholderException {

	/**
	 * Placeholder name
	 *
	 * @var string
	 */
	private $placeholder_name;

	/**
	 * Connected context
	 *
	 * @var string
	 */
	private $connected_to;

	/**
	 * Constructor
	 *
	 * @param string $placeholder_name Placeholder name.
	 * @param string $connected_to     Context event.
	 */
	public function __construct( string $placeholder_name, string $connected_to ) {
		$this->placeholder_name = $placeholder_name;
		$this->connected_to     = $connected_to;
		parent::__construct(
			sprintf(
				'Placeholder "%s" is not allowed for context "%s".',
				$placeholder_name,
				$connected_to
			)
		);
	}

	/**
	 * Get placeholder name
	 *
	 * @return string
	 */
	public function get_placeholder_name(): string {
		return $this->placeholder_name;
	}

	/**
	 * Get connected context
	 *
	 * @return string
	 */
	public function get_connected_to(): string {
		return $this->connected_to;
	}
}
