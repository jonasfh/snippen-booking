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

if ( ! $client->is_configured() ) {
	echo "Error: Vipps API credentials are not configured.\n";
	echo "Please verify VIPPS_CLIENT_ID, VIPPS_CLIENT_SECRET, VIPPS_SUBSCRIPTION_KEY and VIPPS_MSN in .env or WP Admin.\n";
	exit( 1 );
}

$command = isset( $argv[1] ) ? strtolower( trim( $argv[1] ) ) : 'help';

switch ( $command ) {
	case 'list':
		handle_list( $client );
		break;

	case 'register':
		$url = isset( $argv[2] ) ? trim( $argv[2] ) : '';
		handle_register( $client, $url );
		break;

	case 'delete':
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

	if ( 0 !== strpos( $url, 'https://' ) && 0 !== strpos( $url, 'http://localhost' ) ) {
		echo "Warning: Vipps requires a public HTTPS URL (except for internal testing).\n";
	}

	$events = array(
		'epayments.payment.authorized.v1',
		'epayments.payment.aborted.v1',
		'epayments.payment.expired.v1',
		'epayments.payment.terminated.v1',
	);

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
 * Show usage instructions.
 */
function show_help() {
	echo "Vipps Webhooks Management CLI\n";
	echo "=============================\n\n";
	echo "Commands:\n";
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
