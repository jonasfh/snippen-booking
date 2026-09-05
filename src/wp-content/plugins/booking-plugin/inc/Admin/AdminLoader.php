<?php

namespace SnippenBooking\Admin;

use SnippenBooking\Helper\Capabilities;

/**
 * Main loader for the admin interface
 */
class AdminLoader {

	/**
	 * BookingObjectsPage instance
	 *
	 * @var \SnippenBooking\Admin\Pages\BookingObjectsPage|null
	 */
	private static $objects_page_instance = null;

	/**
	 * BookingBlocksPage instance
	 *
	 * @var \SnippenBooking\Admin\Pages\BookingBlocksPage|null
	 */
	private static $blocks_page_instance = null;

	/**
	 * PricingPage instance
	 *
	 * @var \SnippenBooking\Admin\Pages\PricingPage|null
	 */
	private static $pricing_page_instance = null;

	/**
	 * DiscountPage instance
	 *
	 * @var \SnippenBooking\Admin\Pages\DiscountPage|null
	 */
	private static $discount_page_instance = null;

	/**
	 * Register admin hooks
	 */
	public static function register() {
		if ( ! is_admin() ) {
			return;
		}

		add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );

		// Custom user profile fields
		UserProfile::register();
	}

	/**
	 * Add admin menu and submenus
	 */
	public static function add_admin_menu() {
		$is_booking_admin = current_user_can( Capabilities::MANAGE_BOOKINGS );

		add_menu_page(
			__( 'Snippen Booking', 'snippen-booking' ),
			__( 'Snippen Booking', 'snippen-booking' ),
			'view_snippen_booking_menu',
			'snippen-booking',
			$is_booking_admin ? array( self::class, 'render_bookings_page' ) : array( self::class, 'render_help_page' ),
			'dashicons-calendar-alt',
			25
		);

		add_menu_page(
			__( 'Mine Bookinger', 'snippen-booking' ),
			__( 'Mine Bookinger', 'snippen-booking' ),
			'read',
			'snippen-my-bookings',
			array( self::class, 'render_user_bookings_page' ),
			'dashicons-tickets-alt',
			26
		);

		if ( ! $is_booking_admin ) {
			add_submenu_page(
				'snippen-booking',
				__( 'Hjelp / Manual', 'snippen-booking' ),
				__( 'Hjelp / Manual', 'snippen-booking' ),
				'view_snippen_booking_manual',
				'snippen-booking',
				array( self::class, 'render_help_page' )
			);
		} else {
			add_submenu_page(
				'snippen-booking',
				__( 'Oversikt', 'snippen-booking' ),
				__( 'Oversikt', 'snippen-booking' ),
				Capabilities::MANAGE_BOOKINGS,
				'snippen-booking',
				array( self::class, 'render_bookings_page' )
			);
		}

		$objects_hook = add_submenu_page(
			'snippen-booking',
			__( 'Lokaler', 'snippen-booking' ),
			__( 'Lokaler', 'snippen-booking' ),
			Capabilities::MANAGE_BOOKINGS,
			'snippen-booking-objects',
			array( self::class, 'render_objects_page' )
		);

		$blocks_hook = add_submenu_page(
			'snippen-booking',
			__( 'Tidsluker', 'snippen-booking' ),
			__( 'Tidsluker', 'snippen-booking' ),
			Capabilities::MANAGE_BOOKINGS,
			'snippen-booking-blocks',
			array( self::class, 'render_blocks_page' )
		);

		$pricing_hook = add_submenu_page(
			'snippen-booking',
			__( 'Prisregler', 'snippen-booking' ),
			__( 'Prisregler', 'snippen-booking' ),
			Capabilities::MANAGE_BOOKINGS,
			'snippen-booking-pricing',
			array( self::class, 'render_pricing_page' )
		);

		$discount_hook = add_submenu_page(
			'snippen-booking',
			__( 'Rabattregler', 'snippen-booking' ),
			__( 'Rabattregler', 'snippen-booking' ),
			Capabilities::MANAGE_BOOKINGS,
			'snippen-booking-discounts',
			array( self::class, 'render_discount_page' )
		);

		add_submenu_page(
			'snippen-booking',
			__( 'Innstillinger', 'snippen-booking' ),
			__( 'Innstillinger', 'snippen-booking' ),
			Capabilities::MANAGE_BOOKINGS,
			'snippen-booking-settings',
			array( self::class, 'render_settings_page' )
		);

		add_submenu_page(
			'snippen-booking',
			__( 'SMS Innboks', 'snippen-booking' ),
			__( 'SMS Innboks', 'snippen-booking' ),
			Capabilities::MANAGE_BOOKINGS,
			'snippen-booking-sms-inbox',
			array( self::class, 'render_sms_inbox_page' )
		);

		$templates_hook = add_submenu_page(
			'snippen-booking',
			__( 'Varslingsmaler', 'snippen-booking' ),
			__( 'Varslingsmaler', 'snippen-booking' ),
			Capabilities::MANAGE_BOOKINGS,
			'snippen-booking-templates',
			array( self::class, 'render_templates_page' )
		);

		// Setup Wizard
		SetupWizardPage::register();

		add_submenu_page(
			'snippen-booking',
			__( 'Beboer Import', 'snippen-booking' ),
			__( 'Beboer Import', 'snippen-booking' ),
			Capabilities::MANAGE_BOOKINGS,
			'snippen-booking-import',
			array( self::class, 'render_import_page' )
		);

		if ( $is_booking_admin ) {
			add_submenu_page(
				'snippen-booking',
				__( 'Hjelp / Manual', 'snippen-booking' ),
				__( 'Hjelp / Manual', 'snippen-booking' ),
				'view_snippen_booking_manual',
				'snippen-booking-help',
				array( self::class, 'render_help_page' )
			);
		}

		if ( $objects_hook ) {
			add_action( 'load-' . $objects_hook, array( self::class, 'handle_objects_page_save' ) );
		}
		if ( $blocks_hook ) {
			add_action( 'load-' . $blocks_hook, array( self::class, 'handle_blocks_page_save' ) );
		}
		if ( $pricing_hook ) {
			add_action( 'load-' . $pricing_hook, array( self::class, 'handle_pricing_page_save' ) );
		}
		if ( $discount_hook ) {
			add_action( 'load-' . $discount_hook, array( self::class, 'handle_discount_page_save' ) );
		}
		if ( $templates_hook ) {
			add_action( 'load-' . $templates_hook, array( self::class, 'handle_templates_page_save' ) );
		}
	}

	/**
	 * Handle Objects Page save early (before headers)
	 */
	public static function handle_objects_page_save() {
		if ( class_exists( 'SnippenBooking\Admin\Pages\BookingObjectsPage' ) ) {
			self::$objects_page_instance = new \SnippenBooking\Admin\Pages\BookingObjectsPage();
			self::$objects_page_instance->handle_request();
		}
	}

	/**
	 * Handle Blocks Page save early (before headers)
	 */
	public static function handle_blocks_page_save() {
		if ( class_exists( 'SnippenBooking\Admin\Pages\BookingBlocksPage' ) ) {
			self::$blocks_page_instance = new \SnippenBooking\Admin\Pages\BookingBlocksPage();
			self::$blocks_page_instance->handle_request();
		}
	}

	/**
	 * Handle Pricing Page save early (before headers)
	 */
	public static function handle_pricing_page_save() {
		if ( class_exists( 'SnippenBooking\Admin\Pages\PricingPage' ) ) {
			self::$pricing_page_instance = new \SnippenBooking\Admin\Pages\PricingPage();
			self::$pricing_page_instance->handle_request();
		}
	}

	/**
	 * Handle Discount Page save early (before headers)
	 */
	public static function handle_discount_page_save() {
		if ( class_exists( 'SnippenBooking\Admin\Pages\DiscountPage' ) ) {
			self::$discount_page_instance = new \SnippenBooking\Admin\Pages\DiscountPage();
			self::$discount_page_instance->handle_request();
		}
	}

	/**
	 * Handle Templates Page save early (before headers)
	 */
	public static function handle_templates_page_save() {
		if ( class_exists( 'SnippenBooking\Admin\Pages\NotificationTemplatesPage' ) ) {
			// Instance should handle request early
			$page = new \SnippenBooking\Admin\Pages\NotificationTemplatesPage();
			$page->handle_request();
		}
	}

	/**
	 * Enqueue Admin Assets
	 */
	public static function enqueue_admin_assets( $hook ) {
		if ( strpos( $hook, 'snippen-booking' ) === false && strpos( $hook, 'snippen-my-bookings' ) === false ) {
			return;
		}

		wp_enqueue_style( 'snippen-booking-admin', plugins_url( 'css/admin.css', dirname( __DIR__, 1 ) ), array(), SNIPPEN_BOOKING_VERSION );
		wp_enqueue_script( 'snippen-booking-admin', plugins_url( 'js/admin.js', dirname( __DIR__, 1 ) ), array( 'jquery' ), SNIPPEN_BOOKING_VERSION, true );
		wp_enqueue_script( 'snippen-booking-admin-filter', plugins_url( 'js/admin-table-filter.js', dirname( __DIR__, 1 ) ), array( 'jquery' ), SNIPPEN_BOOKING_VERSION, true );

		wp_localize_script(
			'snippen-booking-admin',
			'snippenAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'snippen_admin_nonce' ),
				'strings' => array(
					'confirmDelete' => __( 'Er du sikker på at du vil slette dette?', 'snippen-booking' ),
					'confirmCancel' => __( 'Vil du virkelig avbryte denne bookingen?', 'snippen-booking' ),
					'error'         => __( 'Det oppsto en feil. Prøv igjen.', 'snippen-booking' ),
					'resetFilters'  => __( 'Rens alle filtre', 'snippen-booking' ),
					'showing'       => __( 'Viser', 'snippen-booking' ),
					'of'            => __( 'av', 'snippen-booking' ),
					'rows'          => __( 'rader', 'snippen-booking' ),
					'min'           => __( 'Min', 'snippen-booking' ),
					'max'           => __( 'Maks', 'snippen-booking' ),
					'all'           => __( 'Alle', 'snippen-booking' ),
				),
			)
		);
	}

	/**
	 * Render Bookings Page
	 */
	public static function render_bookings_page() {
		if ( class_exists( 'SnippenBooking\Admin\Pages\BookingsPage' ) ) {
			$page = new \SnippenBooking\Admin\Pages\BookingsPage();
			$page->render();
		} else {
			echo '<div class="wrap"><h1>' . esc_html__( 'Bookinger', 'snippen-booking' ) . '</h1><p>' . esc_html__( 'Under utvikling...', 'snippen-booking' ) . '</p></div>';
		}
	}

	/**
	 * Render Objects Page
	 */
	public static function render_objects_page() {
		if ( self::$objects_page_instance ) {
			self::$objects_page_instance->render();
		} elseif ( class_exists( 'SnippenBooking\Admin\Pages\BookingObjectsPage' ) ) {
			$page = new \SnippenBooking\Admin\Pages\BookingObjectsPage();
			$page->render();
		} else {
			echo '<div class="wrap"><h1>' . esc_html__( 'Lokaler', 'snippen-booking' ) . '</h1><p>' . esc_html__( 'Under utvikling...', 'snippen-booking' ) . '</p></div>';
		}
	}

	/**
	 * Render Blocks Page
	 */
	public static function render_blocks_page() {
		if ( self::$blocks_page_instance ) {
			self::$blocks_page_instance->render();
		} elseif ( class_exists( 'SnippenBooking\Admin\Pages\BookingBlocksPage' ) ) {
			$page = new \SnippenBooking\Admin\Pages\BookingBlocksPage();
			$page->render();
		} else {
			echo '<div class="wrap"><h1>' . esc_html__( 'Tidsluker', 'snippen-booking' ) . '</h1><p>' . esc_html__( 'Under utvikling...', 'snippen-booking' ) . '</p></div>';
		}
	}

	/**
	 * Render Pricing Page
	 */
	public static function render_pricing_page() {
		if ( self::$pricing_page_instance ) {
			self::$pricing_page_instance->render();
		} elseif ( class_exists( 'SnippenBooking\Admin\Pages\PricingPage' ) ) {
			$page = new \SnippenBooking\Admin\Pages\PricingPage();
			$page->render();
		} else {
			echo '<div class="wrap"><h1>' . esc_html__( 'Prisregler', 'snippen-booking' ) . '</h1><p>' . esc_html__( 'Under utvikling...', 'snippen-booking' ) . '</p></div>';
		}
	}

	/**
	 * Render Discount Page
	 */
	public static function render_discount_page() {
		if ( self::$discount_page_instance ) {
			self::$discount_page_instance->render();
		} elseif ( class_exists( 'SnippenBooking\Admin\Pages\DiscountPage' ) ) {
			$page = new \SnippenBooking\Admin\Pages\DiscountPage();
			$page->render();
		} else {
			echo '<div class="wrap"><h1>' . esc_html__( 'Rabattregler', 'snippen-booking' ) . '</h1><p>' . esc_html__( 'Under utvikling...', 'snippen-booking' ) . '</p></div>';
		}
	}

	/**
	 * Render User Bookings Page
	 */
	public static function render_user_bookings_page() {
		if ( class_exists( 'SnippenBooking\Admin\Pages\UserBookingsPage' ) ) {
			$page = new \SnippenBooking\Admin\Pages\UserBookingsPage();
			$page->render();
		} else {
			echo '<div class="wrap"><h1>' . esc_html__( 'Mine Bookinger', 'snippen-booking' ) . '</h1><p>' . esc_html__( 'Under utvikling...', 'snippen-booking' ) . '</p></div>';
		}
	}

	/**
	 * Render Settings Page
	 */
	public static function render_settings_page() {
		if ( class_exists( 'SnippenBooking\Admin\Pages\SettingsPage' ) ) {
			$page = new \SnippenBooking\Admin\Pages\SettingsPage();
			$page->render();
		} else {
			echo '<div class="wrap"><h1>' . esc_html__( 'Innstillinger', 'snippen-booking' ) . '</h1><p>' . esc_html__( 'Under utvikling...', 'snippen-booking' ) . '</p></div>';
		}
	}

	/**
	 * Render Templates Page
	 */
	public static function render_templates_page() {
		if ( class_exists( 'SnippenBooking\Admin\Pages\NotificationTemplatesPage' ) ) {
			$page = new \SnippenBooking\Admin\Pages\NotificationTemplatesPage();
			$page->render();
		} else {
			echo '<div class="wrap"><h1>' . esc_html__( 'Varslingsmaler', 'snippen-booking' ) . '</h1><p>' . esc_html__( 'Under utvikling...', 'snippen-booking' ) . '</p></div>';
		}
	}

	/**
	 * Render Import Page
	 */
	public static function render_import_page() {
		if ( class_exists( 'SnippenBooking\Admin\Pages\ImportPage' ) ) {
			$page = new \SnippenBooking\Admin\Pages\ImportPage();
			$page->render();
		} else {
			echo '<div class="wrap"><h1>' . esc_html__( 'Beboer Import', 'snippen-booking' ) . '</h1><p>' . esc_html__( 'Under utvikling...', 'snippen-booking' ) . '</p></div>';
		}
	}

	/**
	 * Render SMS Inbox Page
	 */
	public static function render_sms_inbox_page() {
		if ( class_exists( 'SnippenBooking\Admin\Pages\SmsInboxPage' ) ) {
			$page = new \SnippenBooking\Admin\Pages\SmsInboxPage();
			$page->render();
		} else {
			echo '<div class="wrap"><h1>' . esc_html__( 'SMS Innboks', 'snippen-booking' ) . '</h1><p>' . esc_html__( 'Under utvikling...', 'snippen-booking' ) . '</p></div>';
		}
	}

	/**
	 * Render Help Page
	 */
	public static function render_help_page() {
		if ( class_exists( 'SnippenBooking\Admin\Pages\HelpPage' ) ) {
			$page = new \SnippenBooking\Admin\Pages\HelpPage();
			$page->render();
		} else {
			echo '<div class="wrap"><h1>' . esc_html__( 'Hjelp / Manual', 'snippen-booking' ) . '</h1><p>' . esc_html__( 'Under utvikling...', 'snippen-booking' ) . '</p></div>';
		}
	}
}
