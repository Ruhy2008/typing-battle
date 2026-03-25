<?php
// src/Logic.php — Game Logic (Daffa)

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

require_once __DIR__ . '/db.php';

class Logic implements MessageComponentInterface
{
    // ── Konstanta ─────────────────────────────────────────────
    const MAX_PLAYERS    = 2;
    const MAX_ROUNDS     = 5;
    const ROUND_TIME_SEC = 60;
    const SENTENCES = [
        "the quick brown fox jumps over the lazy dog",
        "practice makes perfect and perfect makes permanent",
        "technology connects people across the world",
        "consistency is the key to mastering any skill",
        "real time applications require careful synchronization",
        "success is not final failure is not fatal it is the courage to continue",
        "the only way to do great work is to love what you do",
        "in the middle of every difficulty lies opportunity",
        "code is like humor when you have to explain it it is bad",
        "first solve the problem then write the code",
    ];

    // ── State ─────────────────────────────────────────────────
    private \SplObjectStorage $clients;
    private array  $players       = [];
    private bool   $gameStarted   = false;
    private int    $currentRound  = 0;
    private string $currentSentence = '';
    private array  $roundResults  = [];
    private array  $roundLog      = [];
    private ?string $sessionId    = null;
    private ?object $roundTimer   = null;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage();
        echo "[LOGIC] Logic instance dibuat.\n";
    }

    // ── Koneksi masuk ─────────────────────────────────────────
    public function onOpen(ConnectionInterface $conn): void
    {
        $this->clients->attach($conn);
        echo "[OPEN] Koneksi baru: {$conn->resourceId}\n";
    }

    // ── Pesan masuk ───────────────────────────────────────────
    public function onMessage(ConnectionInterface $from, $msg): void
    {
        $data = json_decode($msg, true);
        if (!$data || !isset($data['type'])) {
            $this->sendTo($from, ['type' => 'ERROR', 'message' => 'Format pesan tidak valid.']);
            return;
        }

        echo "[MSG] {$data['type']} dari {$from->resourceId}\n";

        switch ($data['type']) {
            case 'JOIN':
                $this->handleJoin($from, $data);
                break;
            case 'PROGRESS':
                $this->handleProgress($from, $data);
                break;
            case 'FINISH':
                $this->handleFinish($from, $data);
                break;
            case 'get_leaderboard':
                $this->handleLeaderboard($from);
                break;
            default:
                $this->sendTo($from, ['type' => 'ERROR', 'message' => 'Tipe pesan tidak dikenal.']);
        }
    }

    // ── Koneksi tutup ─────────────────────────────────────────
    public function onClose(ConnectionInterface $conn): void
    {
        $this->clients->detach($conn);
        $username = $this->getUsernameByConn($conn);
        if ($username) {
            unset($this->players[$username]);
            echo "[CLOSE] {$username} keluar.\n";
            $this->broadcast([
                'type'    => 'SYSTEM',
                'message' => "{$username} telah meninggalkan room.",
            ]);
            $this->broadcastPlayerList();

            // Jika game sedang berjalan dan pemain tersisa < 2
            if ($this->gameStarted && count($this->players) < 1) {
                $this->resetGame();
            }
        } else {
            echo "[CLOSE] Koneksi {$conn->resourceId} tutup (belum JOIN).\n";
        }
    }

    // ── Error ─────────────────────────────────────────────────
    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        echo "[ERROR] {$e->getMessage()}\n";
        $conn->close();
    }

    // ══════════════════════════════════════════════════════════
    // Handler JOIN
    // ══════════════════════════════════════════════════════════
    private function handleJoin(ConnectionInterface $conn, array $data): void
    {
        $username = trim($data['username'] ?? '');
        if (!$username) {
            $this->sendTo($conn, ['type' => 'ERROR', 'message' => 'Username tidak boleh kosong.']);
            return;
        }
        if (strlen($username) > 20) {
            $this->sendTo($conn, ['type' => 'ERROR', 'message' => 'Username maks 20 karakter.']);
            return;
        }
        if (isset($this->players[$username])) {
            $this->sendTo($conn, ['type' => 'ERROR', 'message' => 'Username sudah dipakai.']);
            return;
        }
        if (count($this->players) >= self::MAX_PLAYERS) {
            $this->sendTo($conn, ['type' => 'ERROR', 'message' => 'Room penuh.']);
            return;
        }
        if ($this->gameStarted) {
            $this->sendTo($conn, ['type' => 'ERROR', 'message' => 'Game sedang berlangsung.']);
            return;
        }

        $this->players[$username] = [
            'conn'         => $conn,
            'username'     => $username,
            'score'        => 0,
            'totalWpm'     => 0.0,
            'totalError'   => 0.0,
            'roundsPlayed' => 0,
            'roundHistory' => [],
            'finished'     => false,
        ];

        echo "[JOIN] {$username} bergabung. Total: " . count($this->players) . "\n";

        $this->sendTo($conn, [
            'type'    => 'SYSTEM',
            'message' => "Selamat datang, {$username}! Menunggu pemain lain...",
        ]);
        $this->broadcastPlayerList();

        // Mulai game jika sudah cukup pemain
        if (count($this->players) >= self::MAX_PLAYERS && !$this->gameStarted) {
            $this->startGame();
        }
    }

    // ══════════════════════════════════════════════════════════
    // Handler PROGRESS
    // ══════════════════════════════════════════════════════════
    private function handleProgress(ConnectionInterface $from, array $data): void
    {
        if (!$this->gameStarted) return;

        $username = $data['username'] ?? '';
        $progress = (int)($data['progress'] ?? 0);

        if (!isset($this->players[$username])) return;

        // Progress tidak boleh turun (anti-cheat)
        $prevProgress = $this->players[$username]['lastProgress'] ?? 0;
        if ($progress < $prevProgress) return;
        $this->players[$username]['lastProgress'] = $progress;

        // Broadcast ke semua pemain lain
        $this->broadcastExcept($from, [
            'type'     => 'PROGRESS_UPDATE',
            'username' => $username,
            'progress' => $progress,
        ]);
    }

    // ══════════════════════════════════════════════════════════
    // Handler FINISH
    // ══════════════════════════════════════════════════════════
    private function handleFinish(ConnectionInterface $from, array $data): void
    {
        if (!$this->gameStarted) return;

        $username  = $data['username'] ?? '';
        $wpm       = (float)($data['wpm'] ?? 0);
        $errorRate = (float)($data['errorRate'] ?? 0);
        $timeSec   = (float)($data['timeSec'] ?? 0);

        if (!isset($this->players[$username])) return;
        if ($this->players[$username]['finished']) return; // Jangan proses dua kali

        // ── Anti-cheat ────────────────────────────────────────
        if ($timeSec < 5) {
            echo "[ANTICHEAT] {$username} timeSec < 5, ditolak.\n";
            $this->sendTo($from, ['type' => 'ERROR', 'message' => 'Data tidak valid (waktu terlalu cepat).']);
            return;
        }
        if ($wpm > 300) {
            // Estimasi ulang dari timeSec
            $charCount = strlen($this->currentSentence);
            $wpm = round(($charCount / 5) / ($timeSec / 60), 1);
            echo "[ANTICHEAT] {$username} WPM > 300, di-estimasi ulang: {$wpm}\n";
        }
        $errorRate = max(0, min(100, $errorRate));

        $this->players[$username]['finished'] = true;

        // Hitung poin: WPM × (1 - error_rate/100)
        $points = (int)round($wpm * (1 - $errorRate / 100));
        $this->players[$username]['score']        += $points;
        $this->players[$username]['totalWpm']     += $wpm;
        $this->players[$username]['totalError']   += $errorRate;
        $this->players[$username]['roundsPlayed'] += 1;
        $this->players[$username]['roundHistory'][] = [
            'round'     => $this->currentRound,
            'wpm'       => $wpm,
            'errorRate' => $errorRate,
            'timeSec'   => $timeSec,
        ];

        // Simpan ke log ronde
        $this->roundResults[$username] = [
            'username'  => $username,
            'wpm'       => $wpm,
            'errorRate' => $errorRate,
            'timeSec'   => $timeSec,
            'points'    => $points,
        ];

        // Broadcast bahwa pemain ini selesai
        $this->broadcast([
            'type'      => 'PLAYER_FINISHED',
            'username'  => $username,
            'wpm'       => $wpm,
            'errorRate' => $errorRate,
        ]);

        echo "[FINISH] {$username} selesai. WPM={$wpm} Err={$errorRate}% Poin={$points}\n";

        // Cek apakah semua pemain sudah selesai
        $this->checkAllFinished();
    }

    // ══════════════════════════════════════════════════════════
    // Handler LEADERBOARD
    // ══════════════════════════════════════════════════════════
    private function handleLeaderboard(ConnectionInterface $conn): void
    {
        $lb = getLeaderboard();
        $this->sendTo($conn, ['type' => 'LEADERBOARD', 'data' => $lb]);
    }

    // ══════════════════════════════════════════════════════════
    // Game Flow
    // ══════════════════════════════════════════════════════════
    private function startGame(): void
    {
        $this->gameStarted = true;
        $this->currentRound = 0;
        $this->sessionId = 'S' . time();

        $this->broadcast(['type' => 'START_GAME']);
        echo "[GAME] Game dimulai! Session: {$this->sessionId}\n";

        // Mulai ronde pertama setelah 3 detik
        \React\EventLoop\Loop::get()->addTimer(3, function () {
            $this->startRound();
        });
    }

    private function startRound(): void
    {
        $this->currentRound++;
        $this->roundResults = [];

        // Reset status finished tiap pemain
        foreach ($this->players as $u => &$p) {
            $p['finished']    = false;
            $p['lastProgress'] = 0;
        }
        unset($p);

        // Pilih kalimat acak
        $sentences = self::SENTENCES;
        shuffle($sentences);
        $this->currentSentence = $sentences[0];

        echo "[ROUND] Ronde {$this->currentRound} dimulai. Kalimat: \"{$this->currentSentence}\"\n";

        $this->broadcast([
            'type'      => 'ROUND_START',
            'round'     => $this->currentRound,
            'sentence'  => $this->currentSentence,
            'timeLimit' => self::ROUND_TIME_SEC,
        ]);

        // Timer timeout ronde
        $this->roundTimer = \React\EventLoop\Loop::get()->addTimer(self::ROUND_TIME_SEC, function () {
            echo "[TIMER] Ronde {$this->currentRound} timeout.\n";
            $this->endRound();
        });
    }

    private function checkAllFinished(): void
    {
        foreach ($this->players as $p) {
            if (!$p['finished']) return; // Ada yang belum selesai
        }
        // Semua selesai — batalkan timer timeout
        if ($this->roundTimer !== null) {
            \React\EventLoop\Loop::get()->cancelTimer($this->roundTimer);
            $this->roundTimer = null;
        }
        $this->endRound();
    }

    private function endRound(): void
    {
        echo "[ROUND] Ronde {$this->currentRound} selesai.\n";

        // Simpan ke DB
        foreach ($this->roundResults as $res) {
            saveRoundLog(
                $this->sessionId,
                $this->currentRound,
                $res['username'],
                $res['wpm'],
                $res['errorRate'],
                $res['timeSec'],
                $this->currentSentence
            );
        }

        // Kirim hasil ronde
        $resultsArr = array_values($this->roundResults);
        usort($resultsArr, fn($a, $b) => $b['points'] - $a['points']); // Sort by points desc

        $this->broadcast([
            'type'    => 'ROUND_END',
            'round'   => $this->currentRound,
            'results' => $resultsArr,
        ]);

        if ($this->currentRound >= self::MAX_ROUNDS) {
            // Jeda sebentar lalu akhiri game
            \React\EventLoop\Loop::get()->addTimer(3, function () {
                $this->endGame();
            });
        } else {
            // Jeda antar ronde
            \React\EventLoop\Loop::get()->addTimer(5, function () {
                $this->startRound();
            });
        }
    }

    private function endGame(): void
    {
        echo "[GAME] Game selesai!\n";

        $stats = [];
        foreach ($this->players as $username => $p) {
            $rounds = $p['roundsPlayed'];
            $avgWpm   = $rounds > 0 ? round($p['totalWpm']   / $rounds, 1) : 0;
            $avgError = $rounds > 0 ? round($p['totalError'] / $rounds, 1) : 0;
            $bestWpm  = $rounds > 0 ? max(array_column($p['roundHistory'], 'wpm')) : 0;

            $stats[] = [
                'username'     => $username,
                'score'        => $p['score'],
                'avgWpm'       => $avgWpm,
                'avgErrorRate' => $avgError,
                'bestWpm'      => $bestWpm,
                'roundHistory' => $p['roundHistory'],
            ];

            // Simpan ke DB
            upsertPlayerStats($username, $p['score'], $avgWpm, $avgError, $bestWpm);
        }

        // Sort by score desc
        usort($stats, fn($a, $b) => $b['score'] - $a['score']);

        $this->broadcast(['type' => 'GAME_OVER', 'stats' => $stats]);

        // Reset untuk game berikutnya
        \React\EventLoop\Loop::get()->addTimer(5, function () {
            $this->resetGame();
        });
    }

    private function resetGame(): void
    {
        $this->gameStarted     = false;
        $this->currentRound    = 0;
        $this->currentSentence = '';
        $this->roundResults    = [];
        $this->roundLog        = [];
        $this->sessionId       = null;

        foreach ($this->players as $u => &$p) {
            $p['score']        = 0;
            $p['totalWpm']     = 0.0;
            $p['totalError']   = 0.0;
            $p['roundsPlayed'] = 0;
            $p['roundHistory'] = [];
            $p['finished']     = false;
            $p['lastProgress'] = 0;
        }
        unset($p);

        echo "[GAME] State di-reset.\n";
        $this->broadcast(['type' => 'SYSTEM', 'message' => 'Game selesai. Siap untuk sesi baru!']);
        $this->broadcastPlayerList();
    }

    // ══════════════════════════════════════════════════════════
    // Helper
    // ══════════════════════════════════════════════════════════
    private function sendTo(ConnectionInterface $conn, array $data): void
    {
        $conn->send(json_encode($data));
    }

    private function broadcast(array $data): void
    {
        $json = json_encode($data);
        foreach ($this->clients as $client) {
            $client->send($json);
        }
    }

    private function broadcastExcept(ConnectionInterface $exclude, array $data): void
    {
        $json = json_encode($data);
        foreach ($this->clients as $client) {
            if ($client !== $exclude) {
                $client->send($json);
            }
        }
    }

    private function broadcastPlayerList(): void
    {
        $list = [];
        foreach ($this->players as $u => $p) {
            $list[] = ['username' => $u, 'score' => $p['score']];
        }
        $this->broadcast(['type' => 'PLAYER_LIST', 'players' => $list]);
    }

    private function getUsernameByConn(ConnectionInterface $conn): ?string
    {
        foreach ($this->players as $username => $p) {
            if ($p['conn'] === $conn) return $username;
        }
        return null;
    }
}
