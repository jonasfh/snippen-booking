<?php

namespace SnippenBooking\Assets;

/**
 * Handles script and style enqueuing
 */
class AssetLoader {

	/**
	 * Get the plugin directory URL
	 *
	 * @return string
	 */
	private static function get_plugin_dir_url() {
		return plugin_dir_url( dirname( dirname( __DIR__ ) ) . '/booking-plugin.php' );
	}

	/**
	 * Enqueue scripts and styles
	 */
	public static function enqueue() {
		wp_enqueue_style(
			'snippen-booking-style',
			self::get_plugin_dir_url() . 'css/booking.css',
			array(),
			SNIPPEN_BOOKING_VERSION
		);

		wp_enqueue_script(
			'snippen-booking-script',
			self::get_plugin_dir_url() . 'js/booking.js',
			array( 'jquery' ),
			SNIPPEN_BOOKING_VERSION,
			true
		);

		wp_localize_script(
			'snippen-booking-script',
			'snippenBookingAjax',
			array(
				'ajaxurl'             => admin_url( 'admin-ajax.php' ),
				'nonce'               => wp_create_nonce( 'snippen_booking_nonce' ),
				'admin_nonce'         => wp_create_nonce( 'snippen_admin_nonce' ),
				'login_nonce'         => wp_create_nonce( 'snippen_login_nonce' ),
				'bookingHorizonWeeks' => get_option( 'snippen_booking_horizon_weeks', 52 ),
				'strings'             => array(
					'updatingAvailability' => __( 'Oppdaterer tilgjengelighet...', 'snippen-booking' ),
					'errorLoadingCalendar' => __( 'Kunne ikke laste kalender.', 'snippen-booking' ),
					'blockedByCleanup'     => __( 'Blokkert av utvasktid', 'snippen-booking' ),
					'weekLabel'            => __( 'Uke', 'snippen-booking' ),
					'sendingRequest'       => __( 'Sender forespørsel...', 'snippen-booking' ),
					'somethingWentWrong'   => __( 'Noe gikk galt.', 'snippen-booking' ),
					'connectionError'      => __( 'Tilkoblingsfeil.', 'snippen-booking' ),
					'tryAgain'             => __( 'Prøv igjen', 'snippen-booking' ),
					'noResidentsFound'     => __( 'Ingen beboere funnet.', 'snippen-booking' ),
					'missingPhoneShort'    => __( 'Mangler tlf', 'snippen-booking' ),
					'missingPhoneLong'     => __( 'Denne brukeren mangler telefonnummer og kan ikke booke.', 'snippen-booking' ),
					'confirmCancel'        => __( 'Vil du virkelig avbryte denne bookingen?', 'snippen-booking' ),
					'errorTryAgain'        => __( 'Det oppsto en feil. Prøv igjen.', 'snippen-booking' ),
					'notSet'               => __( 'Ikke satt', 'snippen-booking' ),
					'termsTitle'           => __( 'Vilkår for leie', 'snippen-booking' ),
					'termsRequired'        => __( 'Vennligst kryss av i boksen for å gå videre.', 'snippen-booking' ),
					'close'                => __( 'Lukk', 'snippen-booking' ),
					'weekdays'             => array(
						__( 'Man', 'snippen-booking' ),
						__( 'Tir', 'snippen-booking' ),
						__( 'Ons', 'snippen-booking' ),
						__( 'Tor', 'snippen-booking' ),
						__( 'Fre', 'snippen-booking' ),
						__( 'Lør', 'snippen-booking' ),
						__( 'Søn', 'snippen-booking' ),
					),
				),
			)
		);
	}
}
