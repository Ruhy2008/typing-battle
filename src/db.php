<?php


function getDB(): ?PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $host = getenv('MYSQLHOST')     ?: 'localhost';
    $port = getenv('MYSQLPORT')     ?: '3306';
    $db   = getenv('MYSQLDATABASE') ?: 'railway';
    $user = getenv('MYSQLUSER')     ?: 'root';
    $pass = getenv('MYSQLPASSWORD') ?: '';

    try {
        $pdo = new PDO(
            "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4",
            $user, $pass,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_PERSISTENT         => false,
            ]
        );
        echo "[DB] Koneksi berhasil!\n";
        return $pdo;
    } catch (\PDOException $e) {
        echo "[DB ERR] {$e->getMessage()}\n";
        return null; // Server tetap hidup meski DB down
    }
}

/* Simpan hasil satu ronde ke round_logs*/
function saveRoundLog(string $sessionId, int $roundNum, string $username, float $wpm, float $errorRate, float $timeSec, string $sentence): void {
    $pdo = getDB();
    if (!$pdo) return;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO round_logs (session_id, round_num, username, wpm, error_rate, time_sec, sentence)
            VALUES (:session_id, :round_num, :username, :wpm, :error_rate, :time_sec, :sentence)
        ");
        $stmt->execute([
            ':session_id'  => $sessionId,
            ':round_num'   => $roundNum,
            ':username'    => $username,
            ':wpm'         => $wpm,
            ':error_rate'  => $errorRate,
            ':time_sec'    => $timeSec,
            ':sentence'    => $sentence,
        ]);
    } catch (\PDOException $e) {
        echo "[DB ERR] saveRoundLog: {$e->getMessage()}\n";
    }
}

/*Update / insert statistik akumulatif pemain*/
function upsertPlayerStats(string $username, int $score, float $wpm, float $errorRate, float $bestWpm): void {
    $pdo = getDB();
    if (!$pdo) return;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO players_stats (username, total_score, avg_wpm, best_wpm, avg_error_rate, sessions_played)
            VALUES (:username, :score, :wpm, :best_wpm, :error_rate, 1)
            ON DUPLICATE KEY UPDATE
                total_score     = total_score + VALUES(total_score),
                avg_wpm         = (avg_wpm * sessions_played + VALUES(avg_wpm)) / (sessions_played + 1),
                best_wpm        = GREATEST(best_wpm, VALUES(best_wpm)),
                avg_error_rate  = (avg_error_rate * sessions_played + VALUES(avg_error_rate)) / (sessions_played + 1),
                sessions_played = sessions_played + 1
        ");
        $stmt->execute([
            ':username'   => $username,
            ':score'      => $score,
            ':wpm'        => $wpm,
            ':best_wpm'   => $bestWpm,
            ':error_rate' => $errorRate,
        ]);
    } catch (\PDOException $e) {
        echo "[DB ERR] upsertPlayerStats: {$e->getMessage()}\n";
    }
}

/* Ambil leaderboard top 10*/
function getLeaderboard(): array {
    $pdo = getDB();
    if (!$pdo) return [];
    try {
        $stmt = $pdo->query("
            SELECT username, total_score, avg_wpm, best_wpm, avg_error_rate, sessions_played
            FROM players_stats
            ORDER BY total_score DESC
            LIMIT 10
        ");
        return $stmt->fetchAll();
    } catch (\PDOException $e) {
        echo "[DB ERR] getLeaderboard: {$e->getMessage()}\n";
        return [];
    }
}
