<?php
/**
 * Typing Battle — WebSocket Server Entry Point
 * 
 * Boots the Ratchet WebSocket server on port 8080.
 * Usage: php bin/server.php
 */

require dirname(__DIR__) . '/vendor/autoload.php';

// Load .env file if it exists
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if (!getenv($key)) {
                putenv("{$key}={$value}");
            }
        }
    }
    echo "[Env] Loaded .env file.\n";
}

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use TypingBattle\Logic;

$port = (int) (getenv('WS_PORT') ?: 8080);

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new Logic()
        )
    ),
    $port
);

echo "===================================\n";
echo "  Typing Battle WebSocket Server\n";
echo "  Listening on 0.0.0.0:{$port}\n";
echo "===================================\n";

$server->run();
