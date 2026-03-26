<?php


header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once dirname(__DIR__) . '/src/db.php';

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'leaderboard':
        echo json_encode(['status' => 'ok', 'data' => getLeaderboard()]);
        break;

    case 'round_logs':
        $username = $_GET['username'] ?? null;
        $pdo = getDB();
        if (!$pdo) { echo json_encode(['status' => 'error', 'message' => 'DB tidak tersedia']); break; }
        try {
            if ($username) {
                $stmt = $pdo->prepare("SELECT * FROM round_logs WHERE username = :u ORDER BY created_at DESC LIMIT 100");
                $stmt->execute([':u' => $username]);
            } else {
                $stmt = $pdo->query("SELECT * FROM round_logs ORDER BY created_at DESC LIMIT 200");
            }
            echo json_encode(['status' => 'ok', 'data' => $stmt->fetchAll()]);
        } catch (\PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Action tidak dikenal. Gunakan: leaderboard, round_logs']);
}
