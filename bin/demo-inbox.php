<?php
/**
 * CLI tool for testing and simulating incoming SMS messages.
 *
 * Usage:
 *   composer demo:inbox <telefonnummer> "<meldingstekst>" [--token=<token>] [--raw]
 *   composer demo:inbox -- <telefonnummer> "<meldingstekst>" [--token=<token>] [--raw]
 *   php bin/demo-inbox.php <telefonnummer> "<meldingstekst>" [--token=<token>] [--raw]
 *
 * @package SnippenBooking
 */

require_once __DIR__ . '/env-loader.php';
load_env( __DIR__ . '/../.env' );

// Bootstrap WordPress
$abspath = getenv( 'WP_ABSPATH' ) ?: '/wordpress/';
if ( ! file_exists( $abspath . 'wp-load.php' ) ) {
	echo "Feil: WordPress ble ikke funnet på {$abspath}\n";
	exit( 1 );
}

$_SERVER['HTTP_HOST']      = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'POST';

require_once $abspath . 'wp-load.php';

/**
 * Print script usage and examples.
 */
function print_usage() {
	echo "Bruk:\n";
	echo "  composer demo:inbox <telefonnummer> \"<meldingstekst>\" [--token=<token>] [--raw]\n";
	echo "  composer demo:inbox -- <telefonnummer> \"<meldingstekst>\" [--token=<token>] [--raw]\n";
	echo "  php bin/demo-inbox.php <telefonnummer> \"<meldingstekst>\" [--token=<token>] [--raw]\n\n";
	echo "Beskrivelse:\n";
	echo "  Simulerer mottak av en innkommende SMS til Snippen SMS Gateway (POST /wp-json/snippen/v1/sms/inbox).\n";
	echo "  Kjører meldingen gjennom oppløsningsmotoren og rapporterer matchet booking, bruker og handlinger.\n\n";
	echo "Argumenter:\n";
	echo "  <telefonnummer>   Telefonnummeret meldingen mottas fra (f.eks. 90688031 eller +4799887766).\n";
	echo "  <meldingstekst>   Innholdet i SMS-en (f.eks. \"Hei, fungerer nøkkelen?\", \"1\").\n\n";
	echo "Opsjoner:\n";
	echo "  --token=<token>   Overstyr API-token i Authorization-header (for testing av 401/403).\n";
	echo "                    (Merk: Bruk 'composer demo:inbox -- ...' eller 'token=<token>' ved kjøring via composer).\n";
	echo "  --raw, raw        Skriver ut rå JSON-respons i stedet for formatert sammendrag.\n";
	echo "  -h, --help        Vis denne hjelpeteksten.\n\n";
	echo "Eksempler:\n";
	echo "  composer demo:inbox 90688031 \"Her er en testmelding\"\n";
	echo "  composer demo:inbox +4799887766 \"1\"\n";
	echo "  composer demo:inbox -- 99887766 \"Status på nøkkel?\" --raw\n";
	echo "  composer demo:inbox -- 99887766 \"Test uautorisert\" --token=ugyldig-token\n";
}

// 1. Parse command line arguments
$raw            = false;
$token_override = null;
$phone_arg      = null;
$message_arg    = null;

$args  = array_slice( $argv, 1 );
$count = count( $args );

for ( $i = 0; $i < $count; $i++ ) {
	$arg = $args[ $i ];

	if ( '--raw' === $arg || '-r' === $arg || 'raw' === strtolower( $arg ) ) {
		$raw = true;
	} elseif ( strpos( $arg, '--token=' ) === 0 ) {
		$token_override = substr( $arg, 8 );
	} elseif ( strpos( $arg, 'token=' ) === 0 ) {
		$token_override = substr( $arg, 6 );
	} elseif ( strpos( $arg, 'token:' ) === 0 ) {
		$token_override = substr( $arg, 6 );
	} elseif ( '--token' === $arg || '-t' === $arg ) {
		if ( isset( $args[ $i + 1 ] ) ) {
			$token_override = $args[ ++$i ];
		}
	} elseif ( '--help' === $arg || '-h' === $arg || 'help' === strtolower( $arg ) ) {
		print_usage();
		exit( 0 );
	} elseif ( strpos( $arg, '--' ) === 0 ) {
		echo "Ukjent opsjon: {$arg}\n\n";
		print_usage();
		exit( 1 );
	} else {
		if ( null === $phone_arg ) {
			$phone_arg = $arg;
		} elseif ( null === $message_arg ) {
			$message_arg = $arg;
		} else {
			$message_arg .= ' ' . $arg;
		}
	}
}

if ( null === $phone_arg || null === $message_arg || '' === trim( $phone_arg ) || '' === trim( $message_arg ) ) {
	print_usage();
	exit( 1 );
}

// 2. Normalize phone number
if ( class_exists( '\SnippenBooking\Helper\PhoneHelper' ) ) {
	$normalized_phone = \SnippenBooking\Helper\PhoneHelper::normalize_phone( $phone_arg );
} else {
	$clean = preg_replace( '/[^0-9]/', '', $phone_arg );
	if ( 8 === strlen( $clean ) ) {
		$normalized_phone = '+47' . $clean;
	} elseif ( 10 === strlen( $clean ) && 0 === strpos( $clean, '47' ) ) {
		$normalized_phone = '+' . $clean;
	} else {
		$normalized_phone = false;
	}
}

if ( false === $normalized_phone ) {
	if ( ! $raw ) {
		echo "Feil: Ugyldig telefonnummer '{$phone_arg}'. Vennligst oppgi et 8-sifret norsk nummer eller et nummer som starter med +47.\n";
	} else {
		echo wp_json_encode(
			array(
				'success' => false,
				'error'   => 'invalid_phone',
				'message' => "Ugyldig telefonnummer '{$phone_arg}'",
			),
			JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
		) . "\n";
	}
	exit( 1 );
}

// 3. API Token handling
if ( ! empty( $token_override ) ) {
	$token = $token_override;
} else {
	$token = get_option( 'snippen_sms_service_api_token' );
	if ( empty( $token ) ) {
		$env_token = getenv( 'SNIPPEN_SMS_API_TOKEN' );
		if ( ! empty( $env_token ) ) {
			$token = $env_token;
			update_option( 'snippen_sms_service_api_token', $token );
		} else {
			$token = 'test-integration-token';
			update_option( 'snippen_sms_service_api_token', $token );
			if ( ! $raw ) {
				echo "Notice: Ingen API-token var satt i WordPress. Konfigurerte standardtoken '{$token}'.\n";
				echo "Tips: Kjør 'composer demo:gateway' for å provisjonere et komplett testoppsett med demobookinger.\n\n";
			}
		}
	}
}

// 4. Build payload and dispatch request
$payload = array(
	'messages' => array(
		array(
			'sender'      => $normalized_phone,
			'body'        => $message_arg,
			'gateway_id'  => (int) microtime( true ),
			'received_at' => gmdate( 'c' ),
		),
	),
);

$http_success  = false;
$status_code   = 0;
$response_data = null;
$transport     = '';

// Attempt HTTP POST request to local site REST endpoint
$rest_url     = get_rest_url( null, '/snippen/v1/sms/inbox' );
$request_args = array(
	'headers'   => array(
		'Authorization' => 'Bearer ' . $token,
		'Content-Type'  => 'application/json',
	),
	'body'      => wp_json_encode( $payload ),
	'timeout'   => 3,
	'sslverify' => false,
);

$http_response = wp_remote_post( $rest_url, $request_args );

if ( ! is_wp_error( $http_response ) ) {
	$code = (int) wp_remote_retrieve_response_code( $http_response );
	if ( $code > 0 ) {
		$status_code   = $code;
		$body          = wp_remote_retrieve_body( $http_response );
		$response_data = json_decode( $body, true );
		$transport     = 'HTTP (' . $rest_url . ')';
		$http_success  = true;
	}
}

// Fallback to internal REST dispatch via rest_do_request() if local webserver is not responding
if ( ! $http_success ) {
	if ( class_exists( '\SnippenBooking\Api\SmsGatewayApi' ) ) {
		\SnippenBooking\Api\SmsGatewayApi::register();
		do_action( 'rest_api_init' );
	}

	$rest_req = new \WP_REST_Request( 'POST', '/snippen/v1/sms/inbox' );
	$rest_req->add_header( 'Authorization', 'Bearer ' . $token );
	$rest_req->add_header( 'Content-Type', 'application/json' );
	$rest_req->set_body( wp_json_encode( $payload ) );

	$internal_res  = rest_do_request( $rest_req );
	$status_code   = $internal_res->get_status();
	$response_data = $internal_res->get_data();
	$transport     = 'Intern dispatch (rest_do_request)';
}

if ( is_object( $response_data ) ) {
	$response_data = json_decode( wp_json_encode( $response_data ), true );
}

// 5. Output result
if ( $raw ) {
	echo wp_json_encode( $response_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) . "\n";
	exit( ( $status_code >= 200 && $status_code < 300 ) ? 0 : 1 );
}

// Structured human-readable presentation
echo "======================================================================\n";
echo "  Snippen SMS Gateway - Simulering av innkommende SMS\n";
echo "======================================================================\n";
printf( "%-18s: %s\n", 'Avsender', $normalized_phone );
printf( "%-18s: \"%s\"\n", 'Melding', $message_arg );
printf( "%-18s: %s\n", 'Transport', $transport );
printf( "%-18s: %d %s\n", 'HTTP Status', $status_code, ( $status_code >= 200 && $status_code < 300 ) ? 'OK' : 'Feil' );
echo "----------------------------------------------------------------------\n";

if ( $status_code < 200 || $status_code >= 300 ) {
	$err_code = $response_data['code'] ?? 'ukjent_feil';
	$err_msg  = $response_data['message'] ?? ( is_string( $response_data ) ? $response_data : 'Forespørselen feilet' );

	echo "STATUS: FEIL (HTTP {$status_code})\n";
	printf( "%-18s: %s\n", 'Feilkode', $err_code );
	printf( "%-18s: %s\n", 'Beskrivelse', $err_msg );
	echo "======================================================================\n";
	exit( 1 );
}

$results = $response_data['results'] ?? array();

if ( empty( $results ) ) {
	echo "Ingen meldingsresultater returnert fra endepunktet.\n";
	echo "======================================================================\n";
	exit( 0 );
}

global $wpdb;

foreach ( $results as $res ) {
	$status      = $res['status'] ?? 'ukjent';
	$rule        = $res['rule'] ?? 'ukjent';
	$booking_id  = ! empty( $res['booking_id'] ) ? (int) $res['booking_id'] : null;
	$user_id     = ! empty( $res['user_id'] ) ? (int) $res['user_id'] : null;
	$prompt_sent = ! empty( $res['prompt_sent'] );
	$logged_id   = ! empty( $res['message_id'] ) ? (int) $res['message_id'] : null;

	// Status description
	$status_labels = array(
		'received'          => 'received (mottatt og koblet til booking)',
		'pending_selection' => 'pending_selection (avventer valg mellom reservasjoner)',
		'general_inquiry'   => 'general_inquiry (generell henvendelse uten aktiv booking)',
		'quarantine'        => 'quarantine (plassert i karantene)',
	);
	$status_text   = $status_labels[ $status ] ?? $status;

	// Rule description matching SmsInboxResolverService
	$rule_labels = array(
		'active_session'             => 'active_session (videreføring av nylig aktiv dialog/booking)',
		'disambiguation_selection'   => 'disambiguation_selection (svar på flervalgsdialog løst til booking)',
		'single_active_booking'      => 'single_active_booking (enkelt aktiv reservasjon matchet)',
		'multiple_active_bookings'   => 'multiple_active_bookings (flere aktive reservasjoner krever valg)',
		'registered_user_no_booking' => 'registered_user_no_booking (registrert leietaker uten aktiv reservasjon)',
		'unknown_sender'             => 'unknown_sender (ukjent telefonnummer / ikke registrert)',
	);
	$rule_text   = $rule_labels[ $rule ] ?? $rule;

	printf( "%-18s: %s\n", 'Løsningsstatus', $status_text );
	printf( "%-18s: %s\n", 'Oppløsningsregel', $rule_text );

	if ( $logged_id ) {
		printf( "%-18s: #%d\n", 'Meldings-ID', $logged_id );
	}

	// Booking description
	if ( $booking_id ) {
		$table_b  = $wpdb->prefix . 'snippen_bookings';
		$booking  = $wpdb->get_row( $wpdb->prepare( "SELECT id, booking_date, status, customer_name FROM {$table_b} WHERE id = %d", $booking_id ) );
		$b_detail = $booking ? "Booking #{$booking_id} ({$booking->customer_name}, dato: {$booking->booking_date}, status: {$booking->status})" : "Booking #{$booking_id}";
		printf( "%-18s: %s\n", 'Tilknyttet booking', $b_detail );
	} else {
		printf( "%-18s: %s\n", 'Tilknyttet booking', 'Ingen' );
	}

	// User description
	if ( $user_id ) {
		$user     = get_userdata( $user_id );
		$u_detail = $user ? "Bruker #{$user_id} ({$user->display_name}, {$user->user_email})" : "Bruker #{$user_id}";
		printf( "%-18s: %s\n", 'Tilknyttet bruker', $u_detail );
	} else {
		printf( "%-18s: %s\n", 'Tilknyttet bruker', 'Ukjent / ikke registrert' );
	}

	// Action description
	printf( '%-18s: ', 'Utført handling' );
	if ( 'pending_selection' === $status && $prompt_sent ) {
		echo "Valg-SMS ble generert og lagt i utboks for flervalg.\n";

		// Query latest queued prompt text
		$table_m     = $wpdb->prefix . 'snippen_messages';
		$last_prompt = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT message FROM {$table_m} WHERE recipient = %s AND event_type = %s ORDER BY id DESC LIMIT 1",
				$normalized_phone,
				'sms_disambiguation_prompt'
			)
		);
		if ( $last_prompt ) {
			echo "\n--- [Generert valg-SMS lagt i utboks] ---\n";
			echo trim( $last_prompt ) . "\n";
			echo "-----------------------------------------\n";
		}
	} elseif ( 'received' === $status ) {
		if ( 'disambiguation_selection' === $rule ) {
			echo "Valg bekreftet. Meldingen ble knyttet til booking #{$booking_id}, og bekreftelses-SMS ble lagt i utboksen.\n";

			// Query latest queued confirmation text
			$table_m           = $wpdb->prefix . 'snippen_messages';
			$last_confirmation = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT message FROM {$table_m} WHERE recipient = %s AND event_type = %s ORDER BY id DESC LIMIT 1",
					$normalized_phone,
					'sms_disambiguation_confirmation'
				)
			);
			if ( $last_confirmation ) {
				echo "\n--- [Generert bekreftelses-SMS lagt i utboks] ---\n";
				echo trim( $last_confirmation ) . "\n";
				echo "--------------------------------------------------\n";
			}
		} else {
			echo "Meldingen ble knyttet til booking #{$booking_id} og lagret i innboksen.\n";
		}
	} elseif ( 'general_inquiry' === $status ) {
		echo "Meldingen ble lagret som generell henvendelse for brukeren.\n";
	} elseif ( 'quarantine' === $status ) {
		echo "Meldingen ble lagt i karantene. Administrator kan godkjenne i admin-grensesnittet.\n";
	} else {
		echo "Meldingen ble behandlet.\n";
	}
}

echo "======================================================================\n";
exit( 0 );
