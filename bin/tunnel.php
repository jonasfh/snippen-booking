<?php
/**
 * Cloudflare Tunnel CLI helper for Snippen Booking.
 *
 * Runs 'cloudflared tunnel' in the foreground, filters out log noise,
 * and clearly highlights the public tunnel URL and relevant endpoints.
 *
 * @package SnippenBooking
 */

require_once __DIR__ . '/env-loader.php';
load_env(__DIR__ . '/../.env');

$port  = getenv('PORT') ?: '8080';
$token = getenv('SNIPPEN_SMS_API_TOKEN') ?: 'test-integration-token';

// 1. Verify cloudflared binary is installed
exec('which cloudflared 2>/dev/null', $which_out, $which_code);
if (0 !== $which_code) {
    fwrite(STDERR, "\033[1;31mFeil: 'cloudflared' ble ikke funnet i systemet.\033[0m\n");
    fwrite(STDERR, "Installer cloudflared eller se DEV_README.md for instruksjoner.\n");
    exit(1);
}

// 2. Check if local webserver is responding on the expected port
$server_ok = false;
$ch = curl_init("http://localhost:{$port}/");
if ($ch) {
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 1);
    if (false !== curl_exec($ch)) {
        $server_ok = true;
    }
    curl_close($ch);
}

if (!$server_ok) {
    echo "\033[1;33mℹ️  Webserveren på http://localhost:{$port} svarer ikke.\033[0m\n";
    echo "   Starter bakgrunnstjenester (MariaDB & Apache) automatisk...\n";
    exec('bash /entrypoint.sh start > /dev/null 2>&1');
    sleep(1);
}

// 3. Launch cloudflared process
echo "\033[1;34m====================================================================\033[0m\n";
echo "🌐 Starter Cloudflare Tunnel mot http://localhost:{$port} ...\n";
echo "   Venter på tildelt domeneadresse fra Cloudflare...\n";
echo "\033[1;34m====================================================================\033[0m\n";

$cmd = "cloudflared tunnel --url http://localhost:{$port}";
$descriptors = array(
    0 => array('pipe', 'r'),
    1 => array('pipe', 'w'),
);

$process = proc_open("{$cmd} 2>&1", $descriptors, $pipes);
if (!is_resource($process)) {
    fwrite(STDERR, "\033[1;31mKunne ikke starte cloudflared-prosessen.\033[0m\n");
    exit(1);
}

$cleanup = function () use ($process, &$pipes) {
    echo "\n\033[1;33m🛑 Stopper Cloudflare Tunnel...\033[0m\n";
    if (is_resource($process)) {
        $status = proc_get_status($process);
        if (!empty($status['running']) && !empty($status['pid'])) {
            posix_kill($status['pid'], SIGTERM);
        }
        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }
        proc_close($process);
    }
    echo "Ferdig.\n";
    exit(0);
};

if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
}
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGINT, $cleanup);
    pcntl_signal(SIGTERM, $cleanup);
}
register_shutdown_function(function () use ($process) {
    if (is_resource($process)) {
        $status = proc_get_status($process);
        if (!empty($status['running']) && !empty($status['pid'])) {
            posix_kill($status['pid'], SIGTERM);
        }
    }
});

$found_url = false;
$tunnel_url = '';

// Patterns that clutter output during handshake
$noisy_patterns = array(
    'Thank you for trying Cloudflare Tunnel',
    'Requesting new quick Tunnel',
    'Cannot determine default configuration path',
    'Version 20',
    'GOOS:',
    'Settings: map[',
    'cloudflared will not automatically update',
    'Generated Connector ID:',
    'Initial protocol',
    'ICMP proxy will use',
    'Starting metrics server',
    'Tunnel connection curve',
    'Registered tunnel connection',
    'CONNECTIVITY PRE-CHECKS',
    'COMPONENT',
    'DNS Resolution',
    'UDP Connectivity',
    'TCP Connectivity',
    'Cloudflare API',
    'SUMMARY: Environment is healthy',
    'precheck component=',
    'precheck complete',
    '+------------------------------------------------',
    '|  Your quick Tunnel has been created',
);

while (!feof($pipes[1])) {
    $line = fgets($pipes[1]);
    if (false === $line) {
        break;
    }

    $trimmed = trim($line);
    if ('' === $trimmed) {
        continue;
    }

    // Detect public trycloudflare URL
    if (!$found_url && preg_match('/https:\/\/[a-zA-Z0-9-]+\.trycloudflare\.com/', $trimmed, $matches)) {
        $tunnel_url = $matches[0];
        $found_url = true;

        echo "\n";
        echo "\033[1;32m================================================================================\033[0m\n";
        echo "\033[1;32m🚀 CLOUDFLARE TUNNEL ER AKTIV!\033[0m\n";
        echo "\033[1;32m================================================================================\033[0m\n";
        echo "\033[1m🔗 Tunnel URL:          \033[36m{$tunnel_url}\033[0m\n";
        echo "\033[1m🌐 WordPress Forside:   \033[36m{$tunnel_url}/\033[0m\n";
        echo "\033[1m🛠️  WP Admin:           \033[36m{$tunnel_url}/wp-admin/\033[0m\n";
        echo "\033[1m📱 SMS Gateway Base:    \033[36m{$tunnel_url}/wp-json/snippen/v1/sms\033[0m\n";
        echo "\033[1m📬 SMS Innboks (POST):  \033[36m{$tunnel_url}/wp-json/snippen/v1/sms/inbox\033[0m\n";
        echo "\033[1m📤 SMS Outbox (GET):    \033[36m{$tunnel_url}/wp-json/snippen/v1/sms/outbox\033[0m\n";
        echo "\033[1m🔑 Auth Token:          \033[33m{$token}\033[0m (Authorization: Bearer {$token})\n";
        echo "\033[1;32m================================================================================\033[0m\n";
        echo "\033[1;30mℹ️  Tunnelen holdes i live i forgrunnen. Trykk Ctrl+C for å avslutte.\033[0m\n";
        echo "\033[1;32m================================================================================\033[0m\n\n";
        continue;
    }

    // Filter noisy initialization chatter
    $is_noise = false;
    foreach ($noisy_patterns as $pattern) {
        if (false !== strpos($trimmed, $pattern)) {
            $is_noise = true;
            break;
        }
    }

    // If noise, suppress; otherwise print errors or request info
    if (!$is_noise) {
        // Highlight errors or warnings
        if (false !== strpos($trimmed, 'ERR') || false !== strpos($trimmed, 'WRN')) {
            echo "\033[33m{$trimmed}\033[0m\n";
        } else {
            echo "{$trimmed}\n";
        }
    }
}

$cleanup();
