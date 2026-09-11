<?php
/**
 * CLI Tool for Vipps MobilePay Webhooks API
 *
 * Usage:
 *   php bin/vipps-webhook.php list
 *   php bin/vipps-webhook.php register <url>
 *   php bin/vipps-webhook.php delete <id>
 */

require_once __DIR__ . '/env-loader.php';
load_env( __DIR__ . '/../.env' );

// Bootstrap WordPress
$abspath = getenv( 'WP_ABSPATH' ) ?: ( file_exists( '/wordpress/wp-load.php' ) ? '/wordpress/' : ( file_exists( __DIR__ . '/../wordpress/wp-load.php' ) ? __DIR__ . '/../wordpress/' : '/wordpress/' ) );
if ( ! file_exists( $abspath . 'wp-load.php' ) ) {
	echo "Error: WordPress not found at $abspath\n";
	exit( 1 );
}

$_SERVER['HTTP_HOST']      = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET';

require_once $abspath . 'wp-load.php';

use SnippenBooking\Service\Vipps\VippsClient;

$client = new VippsClient();

$command = isset( $argv[1] ) ? strtolower( trim( $argv[1] ) ) : 'help';

switch ( $command ) {
	case 'status':
	case 'verify':
		handle_status( $client );
		break;

	case 'list':
		ensure_configured( $client );
		handle_list( $client );
		break;

	case 'register':
		$url = isset( $argv[2] ) ? trim( $argv[2] ) : '';
		handle_register( $client, $url );
		break;

	case 'delete':
		ensure_configured( $client );
		$id = isset( $argv[2] ) ? trim( $argv[2] ) : '';
		handle_delete( $client, $id );
		break;

	case 'help':
	case '--help':
	case '-h':
	default:
		show_help();
		break;
}

/**
 * Ensure credentials are fully configured before executing API requests.
 *
 * @param VippsClient $client
 */
function ensure_configured( VippsClient $client ) {
	if ( ! $client->is_configured() ) {
		echo "Error: Vipps API credentials are not configured.\n";
		echo "Please verify VIPPS_CLIENT_ID, VIPPS_CLIENT_SECRET, VIPPS_SUBSCRIPTION_KEY and VIPPS_MSN in .env or WP Admin.\n";
		exit( 1 );
	}
}

/**
 * Handle listing active webhooks.
 *
 * @param VippsClient $client
 */
function handle_list( VippsClient $client ) {
	echo "Fetching registered webhooks from Vipps (" . $client->get_environment() . ")...\n";
	$response = $client->list_webhooks();

	if ( is_wp_error( $response ) ) {
		echo "Error: " . $response->get_error_message() . "\n";
		exit( 1 );
	}

	$webhooks = array();
	if ( isset( $response['webhooks'] ) && is_array( $response['webhooks'] ) ) {
		$webhooks = $response['webhooks'];
	} elseif ( is_array( $response ) && isset( $response[0] ) ) {
		$webhooks = $response;
	}

	if ( empty( $webhooks ) ) {
		echo "No active webhooks found for this merchant.\n";
		exit( 0 );
	}

	echo sprintf( "Found %d active webhook(s):\n\n", count( $webhooks ) );
	foreach ( $webhooks as $index => $wh ) {
		$id     = $wh['id'] ?? 'N/A';
		$url    = $wh['url'] ?? 'N/A';
		$events = isset( $wh['events'] ) && is_array( $wh['events'] ) ? implode( ', ', $wh['events'] ) : 'N/A';

		echo sprintf( "[%d] ID:     %s\n", $index + 1, $id );
		echo sprintf( "    URL:    %s\n", $url );
		echo sprintf( "    Events: %s\n\n", $events );
	}
}

/**
 * Handle webhook registration.
 *
 * @param VippsClient $client
 * @param string      $url
 */
function handle_register( VippsClient $client, $url ) {
	if ( empty( $url ) ) {
		echo "Error: Missing webhook URL.\n";
		echo "Usage: php bin/vipps-webhook.php register <url>\n";
		echo "Example: php bin/vipps-webhook.php register https://example.com/wp-json/snippen/v1/vipps/webhook\n";
		exit( 1 );
	}

	if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
		echo "Error: Invalid URL format: '$url'\n";
		exit( 1 );
	}

	$webhook_path = '/wp-json/snippen/v1/vipps/webhook';
	if ( false === strpos( $url, $webhook_path ) ) {
		$url = rtrim( $url, '/' ) . $webhook_path;
		echo "Notice: Webhook endpoint path was missing. Automatically appended '$webhook_path'.\n";
	}

	$is_prod = ( VippsClient::ENV_PROD === $client->get_environment() );
	if ( $is_prod && 0 !== strpos( $url, 'https://' ) ) {
		echo "Error: Vipps produksjon (api.vipps.no) krever gyldig HTTPS-adresse. Registrering med HTTP er ikke tillatt.\n";
		exit( 1 );
	}

	if ( 0 !== strpos( $url, 'https://' ) && 0 !== strpos( $url, 'http://localhost' ) ) {
		echo "Warning: Vipps requires a public HTTPS URL (except for internal testing).\n";
	}

	$events = array(
		'epayments.payment.authorized.v1',
		'epayments.payment.aborted.v1',
		'epayments.payment.expired.v1',
		'epayments.payment.terminated.v1',
	);

	ensure_configured( $client );

	echo sprintf( "Registering webhook at Vipps (%s)...\n", $client->get_environment() );
	echo "Target URL: $url\n";
	echo "Subscribing to events: " . implode( ', ', $events ) . "\n\n";

	$result = $client->register_webhook( $url, $events );

	if ( is_wp_error( $result ) ) {
		echo "Error registering webhook: " . $result->get_error_message() . "\n";
		if ( ! empty( $result->get_error_data() ) ) {
			echo "Details: " . print_r( $result->get_error_data(), true ) . "\n";
		}
		exit( 1 );
	}

	$webhook_id = $result['id'] ?? ( $result['webhookId'] ?? 'unknown' );
	echo "Success! Webhook registered with Vipps.\n";
	echo "Webhook ID: $webhook_id\n";
	if ( ! empty( $result['secret'] ) ) {
		echo "Webhook Secret: " . $result['secret'] . "\n";
	}
}

/**
 * Handle deleting a webhook registration.
 *
 * @param VippsClient $client
 * @param string      $id
 */
function handle_delete( VippsClient $client, $id ) {
	if ( empty( $id ) ) {
		echo "Error: Missing webhook ID.\n";
		echo "Usage: php bin/vipps-webhook.php delete <id>\n";
		exit( 1 );
	}

	echo sprintf( "Deleting webhook '%s' from Vipps (%s)...\n", $id, $client->get_environment() );
	$result = $client->delete_webhook( $id );

	if ( is_wp_error( $result ) ) {
		echo "Error deleting webhook: " . $result->get_error_message() . "\n";
		exit( 1 );
	}

	echo "Success: Webhook '$id' deleted.\n";
}

/**
 * Handle status and configuration verification.
 *
 * @param VippsClient $client
 */
function handle_status( VippsClient $client ) {
	$env      = $client->get_environment();
	$is_prod  = ( VippsClient::ENV_PROD === $env );
	$base_url = $client->get_base_url();

	echo "========================================================\n";
	echo "  Vipps MobilePay ePayment & Webhook Status\n";
	echo "========================================================\n\n";

	// 1. Miljø
	echo "[1] Miljø / Endepunkt:\n";
	echo "    Aktivt miljø:     " . strtoupper( $env ) . ( $is_prod ? ' (PRODUKSJON - Live betalinger)' : ' (Test / MT Sandbox)' ) . "\n";
	echo "    API Base URL:     " . $base_url . "\n\n";

	// 2. Nøkler og kilder
	echo "[2] API-Nøkler & Konfigurasjonskilder:\n";
	$keys = array(
		'Client ID'        => array(
			'source' => VippsClient::get_setting_source( 'snippen_vipps_client_id', 'SNIPPEN_VIPPS_CLIENT_ID', 'VIPPS_CLIENT_ID' ),
			'value'  => $client->get_client_id() ?: '(ikke satt)',
		),
		'Client Secret'    => array(
			'source' => VippsClient::get_setting_source( 'snippen_vipps_client_secret', 'SNIPPEN_VIPPS_CLIENT_SECRET', 'VIPPS_CLIENT_SECRET' ),
			'value'  => $client->get_masked_client_secret() ?: '(ikke satt)',
		),
		'Subscription Key' => array(
			'source' => VippsClient::get_setting_source( 'snippen_vipps_subscription_key', 'SNIPPEN_VIPPS_SUBSCRIPTION_KEY', 'VIPPS_SUBSCRIPTION_KEY' ),
			'value'  => $client->get_masked_subscription_key() ?: '(ikke satt)',
		),
		'MSN (Salgssted)'  => array(
			'source' => VippsClient::get_setting_source( 'snippen_vipps_msn', 'SNIPPEN_VIPPS_MSN', 'VIPPS_MSN' ),
			'value'  => $client->get_msn() ?: '(ikke satt)',
		),
	);

	foreach ( $keys as $label => $info ) {
		$source_label = 'IKKE SATT';
		if ( 'constant' === $info['source'] ) {
			$source_label = 'wp-config.php (konstant)';
		} elseif ( 'env' === $info['source'] ) {
			$source_label = '.env / Miljøvariabel';
		} elseif ( 'option' === $info['source'] ) {
			$source_label = 'WordPress Options (database)';
		}
		echo sprintf( "    %-18s: %-15s [Kilde: %s]\n", $label, $info['value'], $source_label );
	}

	echo '    Konfigurasjonsstatus : ' . ( $client->is_configured() ? "Gyldig konfigurasjon (alle påkrevde nøkler er satt)\n\n" : "MANGLER PÅKREVDE NØKLER\n\n" );

	// 3. Nettverk & TLS
	echo "[3] Nettverk & Sikkerhet:\n";
	$curl_info = function_exists( 'curl_version' ) ? curl_version() : array();
	$ssl_ver   = $curl_info['ssl_version'] ?? 'Ukjent';
	$has_ssl   = ! empty( $curl_info['features'] & ( defined( 'CURL_VERSION_SSL' ) ? CURL_VERSION_SSL : 4 ) );
	echo '    cURL versjon     : ' . ( $curl_info['version'] ?? 'Ukjent' ) . "\n";
	echo '    SSL/TLS-motor    : ' . $ssl_ver . "\n";
	echo '    TLS 1.2+ støtte  : ' . ( $has_ssl ? 'Ja (Støttet)' : 'Nei (Advarsel: Krever TLS 1.2+)' ) . "\n";

	$site_url = function_exists( 'site_url' ) ? site_url() : '';
	$is_https = ( 0 === strpos( $site_url, 'https://' ) );
	echo '    Site URL         : ' . ( $site_url ?: 'Ikke satt' ) . "\n";
	if ( $is_prod && ! $is_https ) {
		echo "    HTTPS-status     : ADVARSEL! WordPress Site URL er ikke HTTPS. Produksjon krever HTTPS.\n\n";
	} else {
		echo '    HTTPS-status     : ' . ( $is_https ? 'OK (HTTPS aktivert)' : 'Merk: Ikke HTTPS (kun tillatt i test/lokalt miljø)' ) . "\n\n";
	}

	// 4. Webhooks
	echo "[4] Aktive Webhooks hos Vipps:\n";
	if ( ! $client->is_configured() ) {
		echo "    Kan ikke hente webhooks før API-nøkler er konfigurert.\n\n";
		return;
	}

	$response = $client->list_webhooks();
	if ( is_wp_error( $response ) ) {
		echo '    Feil ved henting av webhooks: ' . $response->get_error_message() . "\n\n";
		return;
	}

	$webhooks = array();
	if ( isset( $response['webhooks'] ) && is_array( $response['webhooks'] ) ) {
		$webhooks = $response['webhooks'];
	} elseif ( is_array( $response ) && isset( $response[0] ) ) {
		$webhooks = $response;
	}

	$count = count( $webhooks );
	echo sprintf( "    Antall registrerte webhooks: %d\n", $count );
	if ( $count > 0 ) {
		foreach ( $webhooks as $i => $wh ) {
			$id      = $wh['id'] ?? 'N/A';
			$url     = $wh['url'] ?? 'N/A';
			$events  = isset( $wh['events'] ) && is_array( $wh['events'] ) ? implode( ', ', $wh['events'] ) : 'N/A';
			$warning = '';
			if ( $is_prod && 0 !== strpos( $url, 'https://' ) ) {
				$warning = ' [ADVARSEL: Ikke HTTPS i produksjon!]';
			}
			echo sprintf( "    - [%d] ID: %s%s\n", $i + 1, $id, $warning );
			echo sprintf( "          URL:    %s\n", $url );
			echo sprintf( "          Events: %s\n", $events );
		}
	} else {
		echo "    Ingen aktive webhooks funnet for dette salgsstedet hos Vipps.\n";
	}
	echo "\n";
}

/**
 * Show usage instructions.
 */
function show_help() {
	echo "Vipps Webhooks Management CLI\n";
	echo "=============================\n\n";
	echo "Commands:\n";
	echo "  php bin/vipps-webhook.php status\n";
	echo "      Show environment status, TLS support, credential sources, and registered webhooks.\n\n";
	echo "  php bin/vipps-webhook.php list\n";
	echo "      List all currently registered webhooks in the configured environment.\n\n";
	echo "  php bin/vipps-webhook.php register <url>\n";
	echo "      Register a new webhook subscription for ePayment v1 events.\n";
	echo "      Example:\n";
	echo "        php bin/vipps-webhook.php register https://my-tunnel.trycloudflare.com/wp-json/snippen/v1/vipps/webhook\n\n";
	echo "  php bin/vipps-webhook.php delete <id>\n";
	echo "      Delete a webhook registration by its UUID.\n";
	echo "      Example:\n";
	echo "        php bin/vipps-webhook.php delete 497f6eca-6276-4993-bfeb-53cbbbba6f08\n\n";
}
