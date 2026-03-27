<?php

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

// Railway injects PORT, fallback ke WS_PORT lalu 8080
$port = (int)(getenv('PORT') ?: getenv('WS_PORT') ?: 8080);

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new Logic()
        )
    ),
    $port,
    '0.0.0.0'   //Untuk ngebind ke semua interface agar Railway bisa akses
);

echo "===================================\n";
echo "  Typing Battle WebSocket Server\n";
echo "  Listening on 0.0.0.0:{$port}\n";
echo "===================================\n";

$server->run();