<?php
/**
 * Central Placeholder Registry Service
 *
 * @package SnippenBooking\Service\Notification
 */

namespace SnippenBooking\Service\Notification;

use SnippenBooking\Service\Notification\Exception\UnknownPlaceholderException;
use SnippenBooking\Service\Notification\Exception\DisallowedPlaceholderException;
use SnippenBooking\Service\Notification\Exception\MissingPlaceholderValueException;

/**
 * Class PlaceholderRegistry
 */
class PlaceholderRegistry {

	/**
	 * Registered placeholders
	 *
	 * @var array
	 */
	private $placeholders = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->register_default_placeholders();
	}

	/**
	 * Register default system placeholders
	 *
	 * @return void
	 */
	private function register_default_placeholders() {
		$this->register_placeholder(
			array(
				'name'         => 'user_name',
				'label'        => __( 'User / Customer name', 'snippen-booking' ),
				'description'  => __( 'Full name or display name of the user or customer.', 'snippen-booking' ),
				'connected_to' => array( 'user_activation', 'booking_confirmation', 'admin_booking', 'password_reset', 'payment_reminder' ),
				'resolver'     => function ( array $context ) {
					return $this->resolve_path( $context, array( 'user_name', 'user.name', 'user.display_name' ) );
				},
			)
		);

		$this->register_placeholder(
			array(
				'name'         => 'user_email',
				'label'        => __( 'Customer email', 'snippen-booking' ),
				'description'  => __( 'Email address of the user or customer.', 'snippen-booking' ),
				'connected_to' => array( 'admin_booking', 'booking_confirmation', 'payment_reminder' ),
				'resolver'     => function ( array $context ) {
					return $this->resolve_path( $context, array( 'user_email', 'user.email', 'user.user_email' ) );
				},
			)
		);

		$this->register_placeholder(
			array(
				'name'         => 'user_phone',
				'label'        => __( 'Customer phone number', 'snippen-booking' ),
				'description'  => __( 'Phone number of the user or customer.', 'snippen-booking' ),
				'connected_to' => array( 'admin_booking', 'booking_confirmation', 'payment_reminder' ),
				'resolver'     => function ( array $context ) {
					return $this->resolve_path( $context, array( 'user_phone', 'user.phone', 'user.user_phone' ) );
				},
			)
		);

		$this->register_placeholder(
			array(
				'name'         => 'confirmation_code',
				'label'        => __( '6-digit confirmation code', 'snippen-booking' ),
				'description'  => __( 'Account verification code.', 'snippen-booking' ),
				'connected_to' => array( 'user_activation' ),
				'resolver'     => function ( array $context ) {
					return $this->resolve_path( $context, array( 'confirmation_code', 'user_activation.code' ) );
				},
			)
		);

		$this->register_placeholder(
			array(
				'name'         => 'booking_objects',
				'label'        => __( 'Booked venue names', 'snippen-booking' ),
				'description'  => __( 'Names of booked venues or resources.', 'snippen-booking' ),
				'connected_to' => array( 'booking_confirmation', 'admin_booking', 'payment_reminder' ),
				'resolver'     => function ( array $context ) {
					return $this->resolve_path( $context, array( 'booking_objects', 'booking.objects', 'booking.object_names' ) );
				},
			)
		);

		$this->register_placeholder(
			array(
				'name'         => 'booking_date',
				'label'        => __( 'Booking date', 'snippen-booking' ),
				'description'  => __( 'Date for the booking.', 'snippen-booking' ),
				'connected_to' => array( 'booking_confirmation', 'admin_booking', 'payment_reminder' ),
				'resolver'     => function ( array $context ) {
					return $this->resolve_path( $context, array( 'booking_date', 'booking.date' ) );
				},
			)
		);

		$this->register_placeholder(
			array(
				'name'         => 'booking_time',
				'label'        => __( 'Booking time / time slot', 'snippen-booking' ),
				'description'  => __( 'Time slot or range for the booking.', 'snippen-booking' ),
				'connected_to' => array( 'booking_confirmation', 'admin_booking', 'payment_reminder' ),
				'resolver'     => function ( array $context ) {
					return $this->resolve_path( $context, array( 'booking_time', 'booking.time' ) );
				},
			)
		);

		$this->register_placeholder(
			array(
				'name'         => 'booking_description',
				'label'        => __( 'Booking description/notes', 'snippen-booking' ),
				'description'  => __( 'Customer notes or description for the booking.', 'snippen-booking' ),
				'connected_to' => array( 'admin_booking' ),
				'resolver'     => function ( array $context ) {
					return $this->resolve_path( $context, array( 'booking_description', 'booking.description' ) );
				},
			)
		);

		$this->register_placeholder(
			array(
				'name'         => 'booking_url',
				'label'        => __( 'Booking details URL', 'snippen-booking' ),
				'description'  => __( 'URL to view booking details.', 'snippen-booking' ),
				'connected_to' => array( 'booking_confirmation', 'payment_reminder' ),
				'resolver'     => function ( array $context ) {
					return $this->resolve_path( $context, array( 'booking_url', 'booking.url' ) );
				},
			)
		);

		$this->register_placeholder(
			array(
				'name'         => 'booking_price',
				'label'        => __( 'Booking total price', 'snippen-booking' ),
				'description'  => __( 'Total price for the booking.', 'snippen-booking' ),
				'connected_to' => array( 'booking_confirmation', 'payment_reminder' ),
				'resolver'     => function ( array $context ) {
					return $this->resolve_path( $context, array( 'booking_price', 'booking.price' ) );
				},
			)
		);

		$this->register_placeholder(
			array(
				'name'         => 'bank_account',
				'label'        => __( 'Payment bank account number', 'snippen-booking' ),
				'description'  => __( 'Bank account number for payment.', 'snippen-booking' ),
				'connected_to' => array( 'booking_confirmation', 'payment_reminder' ),
				'resolver'     => function ( array $context ) {
					return $this->resolve_path( $context, array( 'bank_account', 'payment.bank_account' ) );
				},
			)
		);

		$this->register_placeholder(
			array(
				'name'         => 'vipps_number',
				'label'        => __( 'Payment Vipps number / info', 'snippen-booking' ),
				'description'  => __( 'Vipps number for payment.', 'snippen-booking' ),
				'connected_to' => array( 'booking_confirmation', 'payment_reminder' ),
				'resolver'     => function ( array $context ) {
					return $this->resolve_path( $context, array( 'vipps_number', 'payment.vipps_number' ) );
				},
			)
		);

		$this->register_placeholder(
			array(
				'name'         => 'payment_instructions',
				'label'        => __( 'Payment instructions / deadline text', 'snippen-booking' ),
				'description'  => __( 'Payment instructions and deadline text from settings.', 'snippen-booking' ),
				'connected_to' => array( 'booking_confirmation', 'payment_reminder' ),
				'resolver'     => function ( array $context ) {
					return $this->resolve_path( $context, array( 'payment_instructions', 'payment.instructions' ) );
				},
			)
		);

		$this->register_placeholder(
			array(
				'name'         => 'reset_link',
				'label'        => __( 'Password reset URL', 'snippen-booking' ),
				'description'  => __( 'Password reset link URL.', 'snippen-booking' ),
				'connected_to' => array( 'password_reset' ),
				'resolver'     => function ( array $context ) {
					return $this->resolve_path( $context, array( 'reset_link', 'password_reset.link' ) );
				},
			)
		);
	}

	/**
	 * Register a new placeholder definition
	 *
	 * @param array $definition Placeholder metadata definition.
	 * @return void
	 */
	public function register_placeholder( array $definition ) {
		if ( empty( $definition['name'] ) ) {
			return;
		}

		$raw_connected = (array) ( $definition['connected_to'] ?? array() );
		$expanded      = array();
		foreach ( $raw_connected as $conn ) {
			$expanded[] = $conn;
			$expanded[] = self::normalize_context( $conn );
		}

		$this->placeholders[ $definition['name'] ] = array(
			'name'         => $definition['name'],
			'label'        => $definition['label'] ?? $definition['name'],
			'description'  => $definition['description'] ?? '',
			'connected_to' => array_values( array_unique( $expanded ) ),
			'resolver'     => $definition['resolver'] ?? null,
		);
	}

	/**
	 * Get all registered placeholders
	 *
	 * @return array
	 */
	public function get_registered_placeholders(): array {
		return $this->placeholders;
	}

	/**
	 * Get metadata for a specific placeholder
	 *
	 * @param string $name Placeholder name.
	 * @return array|null
	 */
	public function get_placeholder( string $name ): ?array {
		return $this->placeholders[ $name ] ?? null;
	}

	/**
	 * Normalize connected_to context string to canonical format
	 *
	 * @param string $context Context string (e.g. account-activation or user_activation).
	 * @return string
	 */
	public static function normalize_context( string $context ): string {
		$map = array(
			'user_activation'      => 'account-activation',
			'user-activation'      => 'account-activation',
			'account_activation'   => 'account-activation',
			'account-activation'   => 'account-activation',

			'booking_confirmation' => 'booking-confirmation',
			'booking-confirmation' => 'booking-confirmation',

			'admin_booking'        => 'admin-booking-alert',
			'admin-booking'        => 'admin-booking-alert',
			'admin_booking_alert'  => 'admin-booking-alert',
			'admin-booking-alert'  => 'admin-booking-alert',

			'password_reset'       => 'password-reset',
			'password-reset'       => 'password-reset',

			'payment_reminder'     => 'payment-reminder',
			'payment-reminder'     => 'payment-reminder',
		);

		return $map[ $context ] ?? $context;
	}

	/**
	 * Get placeholders allowed for a given connected_to context
	 *
	 * @param string $connected_to Context event type (e.g., booking_confirmation or booking-confirmation).
	 * @return array
	 */
	public function get_placeholders_for_context( string $connected_to ): array {
		if ( empty( $connected_to ) ) {
			return $this->placeholders;
		}

		$normalized = self::normalize_context( $connected_to );
		$filtered   = array();
		foreach ( $this->placeholders as $name => $definition ) {
			if ( in_array( $normalized, $definition['connected_to'], true ) || in_array( $connected_to, $definition['connected_to'], true ) ) {
				$filtered[ $name ] = $definition;
			}
		}

		return $filtered;
	}

	/**
	 * Extract placeholder names from a template string
	 *
	 * @param string $text Template text.
	 * @return array List of placeholder names without braces.
	 */
	public function extract_placeholders( string $text ): array {
		if ( empty( $text ) ) {
			return array();
		}

		preg_match_all( '/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', $text, $matches );

		if ( empty( $matches[1] ) ) {
			return array();
		}

		return array_values( array_unique( $matches[1] ) );
	}

	/**
	 * Validate a template string against registry and connected_to context
	 *
	 * @param string $text         Template text.
	 * @param string $connected_to Connected context (optional).
	 * @return array Array of validation error strings.
	 */
	public function validate_template( string $text, string $connected_to = '' ): array {
		$found      = $this->extract_placeholders( $text );
		$errors     = array();
		$normalized = self::normalize_context( $connected_to );

		foreach ( $found as $placeholder_name ) {
			$definition = $this->get_placeholder( $placeholder_name );
			if ( ! $definition ) {
				$errors[] = sprintf(
					/* translators: %s: Placeholder name */
					__( 'Ukjent placeholder: {{%s}} finnes ikke i registryet.', 'snippen-booking' ),
					$placeholder_name
				);
				continue;
			}

			if ( ! empty( $connected_to ) && ! in_array( $normalized, $definition['connected_to'], true ) && ! in_array( $connected_to, $definition['connected_to'], true ) ) {
				$errors[] = sprintf(
					/* translators: 1: Placeholder name, 2: Event context */
					__( 'Placeholder {{%1$s}} er ikke tillatt for hendelsen "%2$s".', 'snippen-booking' ),
					$placeholder_name,
					$connected_to
				);
			}
		}

		return $errors;
	}

	/**
	 * Resolve value for a single placeholder
	 *
	 * @param string $name         Placeholder name.
	 * @param string $connected_to Context event.
	 * @param array  $context      Context data map/objects.
	 * @return string Resolved value string.
	 *
	 * @throws UnknownPlaceholderException If placeholder is not registered.
	 * @throws DisallowedPlaceholderException If placeholder is not allowed for connected_to.
	 * @throws MissingPlaceholderValueException If value is missing in context.
	 */
	public function resolve( string $name, string $connected_to, array $context ): string {
		$definition = $this->get_placeholder( $name );
		if ( ! $definition ) {
			throw new UnknownPlaceholderException( $name );
		}

		$normalized = self::normalize_context( $connected_to );

		if ( ! empty( $connected_to ) && ! in_array( $normalized, $definition['connected_to'], true ) && ! in_array( $connected_to, $definition['connected_to'], true ) ) {
			throw new DisallowedPlaceholderException( $name, $connected_to );
		}

		$value = null;
		if ( is_callable( $definition['resolver'] ) ) {
			$value = call_user_func( $definition['resolver'], $context );
		}

		if ( null === $value || '' === $value ) {
			throw new MissingPlaceholderValueException( $name );
		}

		return (string) $value;
	}

	/**
	 * Render template string replacing placeholders
	 *
	 * @param string $text         Template text.
	 * @param string $connected_to Context event.
	 * @param array  $context      Data context.
	 * @param bool   $strict       Whether missing values throw exception or leave placeholder intact.
	 * @return string Rendered text.
	 *
	 * @throws UnknownPlaceholderException
	 * @throws DisallowedPlaceholderException
	 * @throws MissingPlaceholderValueException
	 */
	public function render_template( string $text, string $connected_to, array $context, bool $strict = true ): string {
		if ( empty( $text ) ) {
			return '';
		}

		$normalized = self::normalize_context( $connected_to );

		// First, check validation errors for unknown or disallowed placeholders
		$extracted = $this->extract_placeholders( $text );
		foreach ( $extracted as $name ) {
			$definition = $this->get_placeholder( $name );
			if ( ! $definition ) {
				throw new UnknownPlaceholderException( $name );
			}
			if ( ! empty( $connected_to ) && ! in_array( $normalized, $definition['connected_to'], true ) && ! in_array( $connected_to, $definition['connected_to'], true ) ) {
				throw new DisallowedPlaceholderException( $name, $connected_to );
			}
		}

		// Replace placeholders
		return preg_replace_callback(
			'/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/',
			function ( $matches ) use ( $connected_to, $context, $strict ) {
				$name = $matches[1];
				try {
					return $this->resolve( $name, $connected_to, $context );
				} catch ( MissingPlaceholderValueException $e ) {
					if ( $strict ) {
						throw $e;
					}
					return $matches[0];
				}
			},
			$text
		);
	}

	/**
	 * Helper function to resolve paths from flat or nested context
	 *
	 * @param array $context Data context.
	 * @param array $paths   Possible property paths.
	 * @return string|null
	 */
	private function resolve_path( array $context, array $paths ) {
		foreach ( $paths as $path ) {
			$keys = explode( '.', $path );
			$curr = $context;

			$found = true;
			foreach ( $keys as $key ) {
				if ( is_array( $curr ) && isset( $curr[ $key ] ) ) {
					$curr = $curr[ $key ];
				} elseif ( is_object( $curr ) && isset( $curr->$key ) ) {
					$curr = $curr->$key;
				} else {
					$found = false;
					break;
				}
			}

			if ( $found && null !== $curr && '' !== $curr ) {
				return is_scalar( $curr ) || ( is_object( $curr ) && method_exists( $curr, '__toString' ) )
					? (string) $curr
					: null;
			}
		}

		return null;
	}
}
