<?php
// bin/server.php — Entry point WebSocket server (Daffa)

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/src/Logic.php';

$port = (int)(getenv('PORT') ?: 8080);

$server = IoServer::factory(
    new HttpServer(new WsServer(new Logic())),
    $port,
    '0.0.0.0'   // Bind ke semua interface agar bisa diakses dari luar
);

echo "🚀 TYPING BATTLE SERVER NYALA! Port: {$port}\n";
echo "📡 Menunggu koneksi WebSocket...\n";

$server->run();
