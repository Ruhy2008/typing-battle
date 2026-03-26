<?php

namespace TypingBattle;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use React\EventLoop\Loop;

/**
 * Typing Battle — Enhanced Game Logic
 *
 * Features:
 * - Broadcast full state to all players every second
 * - Phase-based game flow (lobby → countdown → playing → result → gameover)
 * - Enhanced input validation with typed-text verification
 * - Speed vs accuracy metrics (rawWpm, netWpm, accuracy%, errorRate%)
 * - Per-round statistics with first-vs-last comparison
 * - Anti-cheat: rate limiting, WPM cap, time checks, progress verification
 */
class Logic implements MessageComponentInterface
{
    // ========================================================================
    // CONSTANTS
    // ========================================================================

    private const MAX_ROUNDS         = 5;
    private const ROUND_TIME_LIMIT   = 60;
    private const MAX_WPM            = 300;
    private const MIN_TIME           = 5;
    private const MAX_USERNAME_LENGTH = 20;
    private const MAX_PROGRESS_RATE  = 10;   // Max PROGRESS events per second
    private const PROGRESS_TOLERANCE = 0.08; // 8% tolerance for progress verification
    private const WPM_TOLERANCE      = 0.25; // 25% tolerance for WPM verification

    // Game phases
    private const PHASE_LOBBY       = 'lobby';
    private const PHASE_COUNTDOWN   = 'countdown';
    private const PHASE_PLAYING     = 'playing';
    private const PHASE_ROUND_RESULT = 'round_result';
    private const PHASE_GAME_OVER   = 'game_over';

    // ========================================================================
    // STATE
    // ========================================================================

    private \SplObjectStorage $clients;
    private array  $players       = [];
    private int    $currentRound  = 0;
    private bool   $gameStarted   = false;
    private bool   $roundEnded    = false;
    private string $currentSentence = '';
    private int    $remainingTime = 0;
    private string $gameId        = '';
    private string $phase         = self::PHASE_LOBBY;

    // Timers
    private $roundTimer     = null;
    private $countdownTimer = null;
    private $stateTimer     = null;

    // Rate limiting: [resourceId => [timestamp, ...]]
    private array $progressRateLimit = [];

    // Round start timestamp for server-side time tracking
    private float $roundStartTime = 0.0;

    // Database (optional)
    private ?\PDO $db = null;

    // Sentence bank
    private array $sentences = [
        'The quick brown fox jumps over the lazy dog near the riverbank.',
        'Programming is the art of telling a computer what to do step by step.',
        'A journey of a thousand miles begins with a single step forward.',
        'The best way to predict the future is to invent it with your own hands.',
        'Success is not final and failure is not fatal, it is courage that counts.',
        'Every great developer you know got there by solving problems they were unqualified to solve.',
        'Code is like humor, when you have to explain it then it is simply bad code.',
        'The only way to do great work is to love what you do every single day.',
        'In the middle of difficulty lies great opportunity for those who seek it.',
        'Simplicity is the ultimate sophistication in design and engineering.',
        'First solve the problem, then write the code to implement the solution.',
        'The best error message is the one that never shows up on the screen.',
        'Talk is cheap, show me the code and let the results speak for themselves.',
        'Any fool can write code that a computer can understand, good programmers write it for humans.',
        'Experience is the name everyone gives to their mistakes in life and work.',
        'Knowledge is power, but enthusiasm pulls the switch and lights the way forward.',
        'The computer was born to solve problems that did not exist before its creation.',
        'Debugging is twice as hard as writing the code in the first place naturally.',
        'Make it work, make it right, make it fast, in that exact order always.',
        'Real artists ship their work, perfection is the enemy of good progress.',
    ];

    // ========================================================================
    // CONSTRUCTOR
    // ========================================================================

    public function __construct()
    {
        $this->clients = new \SplObjectStorage();

        // Optional database connection
        $dbHost = getenv('DB_HOST');
        $dbName = getenv('DB_NAME');
        $dbUser = getenv('DB_USER');
        $dbPass = getenv('DB_PASS');

        if ($dbHost && $dbName && $dbUser) {
            try {
                $this->db = new \PDO(
                    "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
                    $dbUser,
                    $dbPass ?: '',
                    [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
                );
                echo "[DB] Connected to MySQL.\n";
            } catch (\PDOException $e) {
                echo "[DB] Connection failed: {$e->getMessage()}. Running without database.\n";
                $this->db = null;
            }
        }
    }

    // ========================================================================
    // WEBSOCKET INTERFACE
    // ========================================================================

    public function onOpen(ConnectionInterface $conn): void
    {
        $this->clients->attach($conn);
        echo "[Server] New connection: #{$conn->resourceId}\n";
    }

    public function onMessage(ConnectionInterface $from, $msg): void
    {
        $data = json_decode($msg, true);
        if (!is_array($data) || !isset($data['type'])) {
            $this->sendToClient($from, [
                'type'    => 'ERROR',
                'message' => 'Invalid message format.',
            ]);
            return;
        }

        $type = strtoupper(trim($data['type']));

        switch ($type) {
            case 'JOIN':
                $this->handleJoin($from, $data);
                break;
            case 'PROGRESS':
                $this->handleProgress($from, $data);
                break;
            case 'FINISH':
                $this->handleFinish($from, $data);
                break;
            default:
                $this->sendToClient($from, [
                    'type'    => 'ERROR',
                    'message' => "Unknown message type: {$type}",
                ]);
        }
    }

    public function onClose(ConnectionInterface $conn): void
    {
        $this->clients->detach($conn);
        $resourceId = $conn->resourceId;

        if (isset($this->players[$resourceId])) {
            $username = $this->players[$resourceId]['username'];
            echo "[Server] Player '{$username}' disconnected (#{$resourceId}).\n";
            unset($this->players[$resourceId]);
            unset($this->progressRateLimit[$resourceId]);

            $this->broadcastPlayerList();

            $activePlayers = count($this->players);

            if ($activePlayers === 0) {
                $this->resetGame();
                echo "[Server] All players left. Game state reset.\n";
            } elseif ($activePlayers === 1 && $this->gameStarted) {
                echo "[Server] Only 1 player remains. Ending game.\n";
                $this->endGame();
            } elseif ($this->gameStarted) {
                $this->checkAllFinished();
            }
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        echo "[Server] Error on #{$conn->resourceId}: {$e->getMessage()}\n";
        $conn->close();
    }

    // ========================================================================
    // EVENT HANDLERS
    // ========================================================================

    /**
     * Handle JOIN — register a new player.
     */
    private function handleJoin(ConnectionInterface $conn, array $data): void
    {
        $username = trim($data['username'] ?? '');
        $username = strip_tags($username);
        $username = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');

        if ($username === '' || mb_strlen($username) > self::MAX_USERNAME_LENGTH) {
            $this->sendToClient($conn, [
                'type'    => 'ERROR',
                'message' => 'Invalid username. Must be 1-20 characters.',
            ]);
            return;
        }

        // Check uniqueness
        foreach ($this->players as $player) {
            if (strtolower($player['username']) === strtolower($username)) {
                $this->sendToClient($conn, [
                    'type'    => 'ERROR',
                    'message' => 'Username already taken.',
                ]);
                return;
            }
        }

        // No joining mid-game
        if ($this->gameStarted) {
            $this->sendToClient($conn, [
                'type'    => 'ERROR',
                'message' => 'Game in progress. Wait for it to finish.',
            ]);
            return;
        }

        // Register player with enhanced metrics
        $this->players[$conn->resourceId] = [
            'username'      => $username,
            'conn'          => $conn,
            'score'         => 0,
            'finished'      => false,
            'roundHistory'  => [],
            'totalWpm'      => 0.0,
            'totalNetWpm'   => 0.0,
            'totalErrors'   => 0,
            'totalAccuracy' => 0.0,
            'roundsPlayed'  => 0,
            'progress'      => 0.0,
            'typedText'     => '',
            'rawWpm'        => 0.0,
            'netWpm'        => 0.0,
            'accuracy'      => 100.0,
            'errorRate'     => 0.0,
            'currentWpm'    => 0.0,
        ];

        $this->progressRateLimit[$conn->resourceId] = [];

        echo "[Server] Player '{$username}' joined (#{$conn->resourceId}).\n";

        $this->broadcastPlayerList();

        // Auto-start if 2+ players
        if (count($this->players) >= 2 && !$this->gameStarted) {
            $this->startCountdown();
        }
    }

    /**
     * Handle PROGRESS — update player typing progress with validation.
     */
    private function handleProgress(ConnectionInterface $from, array $data): void
    {
        $resourceId = $from->resourceId;

        if (!isset($this->players[$resourceId])) {
            return;
        }
        if (!$this->gameStarted || $this->roundEnded) {
            return;
        }

        // Rate limiting
        $now = microtime(true);
        $this->progressRateLimit[$resourceId] = array_filter(
            $this->progressRateLimit[$resourceId] ?? [],
            fn($t) => $now - $t < 1.0
        );
        if (count($this->progressRateLimit[$resourceId]) >= self::MAX_PROGRESS_RATE) {
            return; // Too many events, silently drop
        }
        $this->progressRateLimit[$resourceId][] = $now;

        // Validate progress
        $progress = $data['progress'] ?? null;
        if (!is_numeric($progress) || $progress < 0 || $progress > 1) {
            return;
        }

        // Progress must not go backward
        if ($progress < $this->players[$resourceId]['progress'] - 0.01) {
            return;
        }

        // Validate typed text if provided
        $typedText = $data['typedText'] ?? '';
        if (is_string($typedText) && $typedText !== '') {
            $sentenceLen = mb_strlen($this->currentSentence);
            $typedLen = mb_strlen($typedText);

            // Server-side progress verification
            if ($sentenceLen > 0) {
                $serverProgress = min(1.0, $typedLen / $sentenceLen);
                if (abs($serverProgress - $progress) > self::PROGRESS_TOLERANCE) {
                    // Progress mismatch — use server-calculated value
                    $progress = $serverProgress;
                }

                // Calculate real-time accuracy
                $correctChars = 0;
                $checkLen = min($typedLen, $sentenceLen);
                for ($i = 0; $i < $checkLen; $i++) {
                    if (mb_substr($typedText, $i, 1) === mb_substr($this->currentSentence, $i, 1)) {
                        $correctChars++;
                    }
                }
                $accuracy = $typedLen > 0 ? ($correctChars / $typedLen) * 100 : 100.0;
                $this->players[$resourceId]['accuracy'] = round($accuracy, 1);
                $this->players[$resourceId]['typedText'] = $typedText;
            }
        }

        // Update current WPM values from client
        $currentWpm = $data['currentWpm'] ?? 0;
        $rawWpm = $data['rawWpm'] ?? $currentWpm;

        $this->players[$resourceId]['progress']   = (float) $progress;
        $this->players[$resourceId]['currentWpm']  = (float) $currentWpm;
        $this->players[$resourceId]['rawWpm']      = (float) $rawWpm;

        // Calculate net WPM from accuracy
        $acc = $this->players[$resourceId]['accuracy'] / 100;
        $this->players[$resourceId]['netWpm'] = round((float) $rawWpm * $acc, 1);

        // Broadcast is handled by periodic stateTimer, no need to broadcast per-event
    }

    /**
     * Handle FINISH — player completed the sentence.
     */
    private function handleFinish(ConnectionInterface $from, array $data): void
    {
        $resourceId = $from->resourceId;

        if (!isset($this->players[$resourceId])) {
            return;
        }
        if (!$this->gameStarted || $this->roundEnded) {
            return;
        }
        if ($this->players[$resourceId]['finished']) {
            return;
        }

        // Validate required fields
        $wpm     = $data['wpm'] ?? null;
        $errors  = $data['errors'] ?? null;
        $timeSec = $data['timeSec'] ?? null;
        $rawWpm  = $data['rawWpm'] ?? $wpm;
        $typedText = $data['typedText'] ?? '';

        if (!is_numeric($wpm) || !is_numeric($errors) || !is_numeric($timeSec)) {
            return;
        }

        $wpm     = (float) $wpm;
        $rawWpm  = (float) $rawWpm;
        $errors  = (int) $errors;
        $timeSec = (float) $timeSec;

        // Validate timeSec range
        $timeSec = max(0.1, min($timeSec, (float) self::ROUND_TIME_LIMIT));

        $sentenceLength = mb_strlen($this->currentSentence);

        // Validate errors range
        $errors = max(0, min($errors, $sentenceLength));

        // Anti-cheat: reject superhuman WPM
        if ($wpm > self::MAX_WPM || $rawWpm > self::MAX_WPM) {
            echo "[Anti-Cheat] Player '{$this->players[$resourceId]['username']}' rejected: WPM > " . self::MAX_WPM . ".\n";
            $wpm = 0;
            $rawWpm = 0;
            $errors = 0;
            $timeSec = (float) self::ROUND_TIME_LIMIT;
        }

        // Anti-cheat: reject impossibly fast time
        if ($timeSec < self::MIN_TIME) {
            echo "[Anti-Cheat] Player '{$this->players[$resourceId]['username']}' rejected: time < " . self::MIN_TIME . "s.\n";
            $wpm = 0;
            $rawWpm = 0;
            $errors = 0;
            $timeSec = (float) self::ROUND_TIME_LIMIT;
        }

        // Server-side WPM verification
        $correctChars = max(0, $sentenceLength - $errors);
        $serverNetWpm = $timeSec > 0 ? ($correctChars / 5) / ($timeSec / 60) : 0;
        $serverRawWpm = $timeSec > 0 ? ($sentenceLength / 5) / ($timeSec / 60) : 0;

        // Use server WPM if client-reported differs too much
        if ($wpm > 0 && abs($serverNetWpm - $wpm) / $wpm > self::WPM_TOLERANCE) {
            $wpm = round($serverNetWpm, 1);
        }
        if ($rawWpm > 0 && abs($serverRawWpm - $rawWpm) / $rawWpm > self::WPM_TOLERANCE) {
            $rawWpm = round($serverRawWpm, 1);
        }

        // Server-side accuracy from typed text
        $accuracy = 100.0;
        $errorRate = 0.0;
        if (is_string($typedText) && mb_strlen($typedText) > 0) {
            $typedLen = mb_strlen($typedText);
            $serverCorrect = 0;
            $checkLen = min($typedLen, $sentenceLength);
            for ($i = 0; $i < $checkLen; $i++) {
                if (mb_substr($typedText, $i, 1) === mb_substr($this->currentSentence, $i, 1)) {
                    $serverCorrect++;
                }
            }
            $accuracy = $typedLen > 0 ? round(($serverCorrect / $typedLen) * 100, 1) : 100.0;
            $errorRate = $sentenceLength > 0 ? round(($errors / $sentenceLength) * 100, 1) : 0.0;
        } else {
            $errorRate = $sentenceLength > 0 ? round(($errors / $sentenceLength) * 100, 1) : 0.0;
            $accuracy = max(0, round(100 - $errorRate, 1));
        }

        // Calculate net WPM: raw WPM adjusted by accuracy
        $netWpm = round($rawWpm * ($accuracy / 100), 1);

        // Score: netWpm × (accuracy / 100) — double-penalize errors
        $score = max(0, (int) floor($netWpm * ($accuracy / 100)));

        // Mark finished
        $this->players[$resourceId]['finished']  = true;
        $this->players[$resourceId]['progress']  = 1.0;
        $this->players[$resourceId]['totalWpm']  += $rawWpm;
        $this->players[$resourceId]['totalNetWpm'] += $netWpm;
        $this->players[$resourceId]['totalErrors'] += $errors;
        $this->players[$resourceId]['totalAccuracy'] += $accuracy;
        $this->players[$resourceId]['roundsPlayed']++;

        // Record round history
        $this->players[$resourceId]['roundHistory'][] = [
            'round'     => $this->currentRound,
            'rawWpm'    => round($rawWpm, 1),
            'netWpm'    => round($netWpm, 1),
            'wpm'       => round($wpm, 1),
            'errors'    => $errors,
            'accuracy'  => $accuracy,
            'errorRate' => $errorRate,
            'score'     => $score,
            'timeSec'   => round($timeSec, 1),
            'dnf'       => false,
        ];

        $this->players[$resourceId]['score'] += $score;

        echo "[Server] Player '{$this->players[$resourceId]['username']}' finished R{$this->currentRound}: rawWPM={$rawWpm}, netWPM={$netWpm}, acc={$accuracy}%, err={$errors}, score={$score}.\n";

        // Broadcast that this player finished
        $this->broadcast([
            'type'     => 'PLAYER_FINISHED',
            'username' => $this->players[$resourceId]['username'],
            'wpm'      => round($wpm, 1),
            'netWpm'   => round($netWpm, 1),
            'accuracy' => $accuracy,
            'timeSec'  => round($timeSec, 1),
        ]);

        $this->checkAllFinished();
    }

    // ========================================================================
    // GAME FLOW
    // ========================================================================

    /**
     * Start countdown before the game.
     */
    private function startCountdown(): void
    {
        echo "[Server] Starting 3-second countdown...\n";
        $this->setPhase(self::PHASE_COUNTDOWN);

        $this->broadcast([
            'type'    => 'START_GAME',
            'message' => 'Game starting in 3 seconds!',
        ]);

        Loop::addTimer(3, function () {
            $this->gameStarted = true;
            $this->gameId = $this->generateUUID();
            $this->startRound();
        });
    }

    /**
     * Start a new round.
     */
    private function startRound(): void
    {
        $this->currentRound++;
        $this->roundEnded = false;
        $this->currentSentence = $this->getRandomSentence();
        $this->remainingTime = self::ROUND_TIME_LIMIT;
        $this->roundStartTime = microtime(true);

        // Reset players for new round
        foreach ($this->players as &$player) {
            $player['finished']   = false;
            $player['progress']   = 0.0;
            $player['typedText']  = '';
            $player['rawWpm']     = 0.0;
            $player['netWpm']     = 0.0;
            $player['accuracy']   = 100.0;
            $player['errorRate']  = 0.0;
            $player['currentWpm'] = 0.0;
        }
        unset($player);

        // Clear rate limits
        foreach ($this->progressRateLimit as &$rl) {
            $rl = [];
        }
        unset($rl);

        $this->setPhase(self::PHASE_PLAYING);

        echo "[Server] Round {$this->currentRound}/" . self::MAX_ROUNDS . " started. Sentence: \"{$this->currentSentence}\"\n";

        $this->broadcast([
            'type'        => 'ROUND_START',
            'round'       => $this->currentRound,
            'totalRounds' => self::MAX_ROUNDS,
            'sentence'    => $this->currentSentence,
            'timeLimit'   => self::ROUND_TIME_LIMIT,
        ]);

        // Round timer
        $this->roundTimer = Loop::addTimer(self::ROUND_TIME_LIMIT, function () {
            $this->endRound();
        });

        // Countdown + state broadcast timer (every second)
        $this->countdownTimer = Loop::addPeriodicTimer(1, function () {
            $this->remainingTime--;

            // Broadcast timer
            $this->broadcast([
                'type'      => 'TIMER_UPDATE',
                'remaining' => $this->remainingTime,
            ]);

            // Broadcast full game state
            $this->broadcastFullState();

            if ($this->remainingTime <= 0 && $this->countdownTimer !== null) {
                Loop::cancelTimer($this->countdownTimer);
                $this->countdownTimer = null;
            }
        });
    }

    /**
     * End the current round.
     */
    private function endRound(): void
    {
        if ($this->roundEnded) {
            return;
        }
        $this->roundEnded = true;

        echo "[Server] Round {$this->currentRound} ended.\n";

        // Cancel timers
        if ($this->roundTimer !== null) {
            Loop::cancelTimer($this->roundTimer);
            $this->roundTimer = null;
        }
        if ($this->countdownTimer !== null) {
            Loop::cancelTimer($this->countdownTimer);
            $this->countdownTimer = null;
        }

        $sentenceLength = mb_strlen($this->currentSentence);

        // DNF for unfinished players
        foreach ($this->players as $id => &$player) {
            if (!$player['finished']) {
                $player['finished'] = true;
                $player['roundsPlayed']++;

                // Calculate partial stats for DNF players
                $typedLen = mb_strlen($player['typedText']);
                $partialAccuracy = 0.0;
                if ($typedLen > 0 && $sentenceLength > 0) {
                    $correctChars = 0;
                    $checkLen = min($typedLen, $sentenceLength);
                    for ($i = 0; $i < $checkLen; $i++) {
                        if (mb_substr($player['typedText'], $i, 1) === mb_substr($this->currentSentence, $i, 1)) {
                            $correctChars++;
                        }
                    }
                    $partialAccuracy = round(($correctChars / $typedLen) * 100, 1);
                }

                $player['roundHistory'][] = [
                    'round'     => $this->currentRound,
                    'rawWpm'    => round($player['rawWpm'], 1),
                    'netWpm'    => 0,
                    'wpm'       => 0,
                    'errors'    => 0,
                    'accuracy'  => $partialAccuracy,
                    'errorRate' => 100.0,
                    'score'     => 0,
                    'timeSec'   => self::ROUND_TIME_LIMIT,
                    'dnf'       => true,
                ];
            }
        }
        unset($player);

        // Build results + standings
        $results = [];
        $standings = [];
        foreach ($this->players as $player) {
            $lastRound = end($player['roundHistory']);
            $results[] = [
                'username'  => $player['username'],
                'rawWpm'    => $lastRound['rawWpm'] ?? 0,
                'netWpm'    => $lastRound['netWpm'] ?? 0,
                'wpm'       => $lastRound['wpm'] ?? 0,
                'errors'    => $lastRound['errors'] ?? 0,
                'accuracy'  => $lastRound['accuracy'] ?? 0,
                'errorRate' => $lastRound['errorRate'] ?? 0,
                'score'     => $lastRound['score'] ?? 0,
                'timeSec'   => $lastRound['timeSec'] ?? 0,
                'dnf'       => $lastRound['dnf'] ?? false,
            ];
            $standings[] = [
                'username'   => $player['username'],
                'totalScore' => $player['score'],
            ];
        }

        usort($standings, fn($a, $b) => $b['totalScore'] - $a['totalScore']);

        $this->saveRoundLog($results);

        $this->setPhase(self::PHASE_ROUND_RESULT);

        $this->broadcast([
            'type'      => 'ROUND_END',
            'round'     => $this->currentRound,
            'results'   => $results,
            'standings' => $standings,
        ]);

        if ($this->currentRound >= self::MAX_ROUNDS) {
            Loop::addTimer(3, function () {
                $this->endGame();
            });
        } else {
            Loop::addTimer(5, function () {
                if ($this->gameStarted && count($this->players) >= 1) {
                    $this->startRound();
                }
            });
        }
    }

    /**
     * End the entire game with enriched statistics.
     */
    private function endGame(): void
    {
        echo "[Server] Game over!\n";

        $this->setPhase(self::PHASE_GAME_OVER);

        $finalStandings = [];
        foreach ($this->players as $player) {
            $roundsPlayed = $player['roundsPlayed'];
            $avgRawWpm  = $roundsPlayed > 0 ? $player['totalWpm'] / $roundsPlayed : 0;
            $avgNetWpm  = $roundsPlayed > 0 ? $player['totalNetWpm'] / $roundsPlayed : 0;
            $avgErrors  = $roundsPlayed > 0 ? $player['totalErrors'] / $roundsPlayed : 0;
            $avgAccuracy = $roundsPlayed > 0 ? $player['totalAccuracy'] / $roundsPlayed : 0;

            // First vs last round comparison
            $firstVsLast = null;
            $history = $player['roundHistory'];
            if (count($history) >= 2) {
                $first = $history[0];
                $last = end($history);
                $wpmDelta = round($last['netWpm'] - $first['netWpm'], 1);
                $accDelta = round($last['accuracy'] - $first['accuracy'], 1);
                $firstVsLast = [
                    'firstRound' => [
                        'round'    => $first['round'],
                        'netWpm'   => $first['netWpm'],
                        'accuracy' => $first['accuracy'],
                        'score'    => $first['score'],
                    ],
                    'lastRound' => [
                        'round'    => $last['round'],
                        'netWpm'   => $last['netWpm'],
                        'accuracy' => $last['accuracy'],
                        'score'    => $last['score'],
                    ],
                    'wpmDelta'     => ($wpmDelta >= 0 ? '+' : '') . $wpmDelta,
                    'accuracyDelta' => ($accDelta >= 0 ? '+' : '') . $accDelta . '%',
                    'improved'     =>  ($wpmDelta > 0 || $accDelta > 0),
                ];
            }

            // Best and worst round
            $bestRound = null;
            $worstRound = null;
            foreach ($history as $r) {
                if ($bestRound === null || $r['score'] > $bestRound['score']) {
                    $bestRound = $r;
                }
                if ($worstRound === null || $r['score'] < $worstRound['score']) {
                    $worstRound = $r;
                }
            }

            // Overall stats
            $totalCorrectChars = 0;
            $totalErrorCount = 0;
            $totalTimeSec = 0;
            foreach ($history as $r) {
                $totalErrorCount += $r['errors'];
                $totalTimeSec += $r['timeSec'];
            }

            $finalStandings[] = [
                'username'       => $player['username'],
                'totalScore'     => $player['score'],
                'avgRawWpm'      => round($avgRawWpm, 1),
                'avgNetWpm'      => round($avgNetWpm, 1),
                'avgAccuracy'    => round($avgAccuracy, 1),
                'avgErrors'      => round($avgErrors, 1),
                'roundHistory'   => $history,
                'firstVsLast'    => $firstVsLast,
                'bestRound'      => $bestRound,
                'worstRound'     => $worstRound,
                'overallStats'   => [
                    'totalErrors'  => $totalErrorCount,
                    'totalTimeSec' => round($totalTimeSec, 1),
                    'roundsPlayed' => $roundsPlayed,
                ],
            ];
        }

        usort($finalStandings, fn($a, $b) => $b['totalScore'] - $a['totalScore']);

        $winner = !empty($finalStandings) ? $finalStandings[0]['username'] : 'Nobody';

        $this->saveGameStats($finalStandings, $winner);

        $this->broadcast([
            'type'           => 'GAME_OVER',
            'winner'         => $winner,
            'finalStandings' => $finalStandings,
        ]);

        echo "[Server] Winner: {$winner}\n";

        $this->resetGame();
    }

    /**
     * Reset all game state.
     */
    private function resetGame(): void
    {
        $this->currentRound  = 0;
        $this->gameStarted   = false;
        $this->roundEnded    = false;
        $this->currentSentence = '';
        $this->gameId        = '';
        $this->roundStartTime = 0.0;

        if ($this->roundTimer !== null) {
            Loop::cancelTimer($this->roundTimer);
            $this->roundTimer = null;
        }
        if ($this->countdownTimer !== null) {
            Loop::cancelTimer($this->countdownTimer);
            $this->countdownTimer = null;
        }
        if ($this->stateTimer !== null) {
            Loop::cancelTimer($this->stateTimer);
            $this->stateTimer = null;
        }

        foreach ($this->players as &$player) {
            $player['score']         = 0;
            $player['finished']      = false;
            $player['roundHistory']  = [];
            $player['totalWpm']      = 0.0;
            $player['totalNetWpm']   = 0.0;
            $player['totalErrors']   = 0;
            $player['totalAccuracy'] = 0.0;
            $player['roundsPlayed']  = 0;
            $player['progress']      = 0.0;
            $player['typedText']     = '';
            $player['rawWpm']        = 0.0;
            $player['netWpm']        = 0.0;
            $player['accuracy']      = 100.0;
            $player['errorRate']     = 0.0;
            $player['currentWpm']    = 0.0;
        }
        unset($player);

        $this->setPhase(self::PHASE_LOBBY);

        echo "[Server] Game state reset. Ready for new game.\n";

        if (count($this->players) > 0) {
            $this->broadcastPlayerList();

            if (count($this->players) >= 2) {
                Loop::addTimer(5, function () {
                    if (!$this->gameStarted && count($this->players) >= 2) {
                        $this->startCountdown();
                    }
                });
            }
        }
    }

    // ========================================================================
    // BROADCAST HELPERS
    // ========================================================================

    /**
     * Set and broadcast game phase.
     */
    private function setPhase(string $phase): void
    {
        $this->phase = $phase;
        $this->broadcast([
            'type'  => 'PHASE_CHANGE',
            'phase' => $phase,
        ]);
    }

    /**
     * Broadcast full game state to all players (called every second during play).
     */
    private function broadcastFullState(): void
    {
        $playersState = [];
        foreach ($this->players as $player) {
            $playersState[] = [
                'username'   => $player['username'],
                'progress'   => round($player['progress'], 3),
                'rawWpm'     => round($player['rawWpm'], 1),
                'netWpm'     => round($player['netWpm'], 1),
                'accuracy'   => round($player['accuracy'], 1),
                'currentWpm' => round($player['currentWpm'], 1),
                'finished'   => $player['finished'],
                'score'      => $player['score'],
            ];
        }

        $this->broadcast([
            'type'      => 'GAME_STATE',
            'phase'     => $this->phase,
            'round'     => $this->currentRound,
            'totalRounds' => self::MAX_ROUNDS,
            'remaining' => $this->remainingTime,
            'players'   => $playersState,
        ]);
    }

    /**
     * Check if all players finished.
     */
    private function checkAllFinished(): void
    {
        foreach ($this->players as $player) {
            if (!$player['finished']) {
                return;
            }
        }
        $this->endRound();
    }

    private function getRandomSentence(): string
    {
        return $this->sentences[array_rand($this->sentences)];
    }

    private function generateUUID(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    private function sendToClient(ConnectionInterface $conn, array $data): void
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

    private function broadcastPlayerList(): void
    {
        $playerList = [];
        foreach ($this->players as $player) {
            $playerList[] = [
                'username' => $player['username'],
                'score'    => $player['score'],
            ];
        }
        $this->broadcast([
            'type'    => 'PLAYER_LIST',
            'players' => $playerList,
        ]);
    }

    // ========================================================================
    // DATABASE
    // ========================================================================

    private function saveRoundLog(array $results): void
    {
        if ($this->db === null || $this->gameId === '') {
            return;
        }

        try {
            $stmt = $this->db->prepare(
                "INSERT INTO round_logs (game_id, round_number, username, sentence, wpm, net_wpm, raw_wpm, errors, accuracy, score, time_sec)
                 VALUES (:game_id, :round, :username, :sentence, :wpm, :net_wpm, :raw_wpm, :errors, :accuracy, :score, :time_sec)"
            );

            foreach ($results as $result) {
                $stmt->execute([
                    ':game_id'  => $this->gameId,
                    ':round'    => $this->currentRound,
                    ':username' => $result['username'],
                    ':sentence' => $this->currentSentence,
                    ':wpm'      => $result['wpm'],
                    ':net_wpm'  => $result['netWpm'],
                    ':raw_wpm'  => $result['rawWpm'],
                    ':errors'   => $result['errors'],
                    ':accuracy' => $result['accuracy'],
                    ':score'    => $result['score'],
                    ':time_sec' => $result['timeSec'],
                ]);
            }
        } catch (\PDOException $e) {
            echo "[DB] Error saving round log: {$e->getMessage()}\n";
        }
    }

    private function saveGameStats(array $finalStandings, string $winner): void
    {
        if ($this->db === null) {
            return;
        }

        try {
            $stmt = $this->db->prepare(
                "INSERT INTO players_stats (username, games_played, total_score, avg_wpm, avg_accuracy, avg_errors, best_wpm, best_accuracy, wins)
                 VALUES (:username, 1, :total_score, :avg_wpm, :avg_accuracy, :avg_errors, :best_wpm, :best_accuracy, :wins)
                 ON DUPLICATE KEY UPDATE
                    games_played = games_played + 1,
                    total_score = total_score + VALUES(total_score),
                    avg_wpm = (avg_wpm * (games_played - 1) + VALUES(avg_wpm)) / games_played,
                    avg_accuracy = (avg_accuracy * (games_played - 1) + VALUES(avg_accuracy)) / games_played,
                    avg_errors = (avg_errors * (games_played - 1) + VALUES(avg_errors)) / games_played,
                    best_wpm = GREATEST(best_wpm, VALUES(best_wpm)),
                    best_accuracy = GREATEST(best_accuracy, VALUES(best_accuracy)),
                    wins = wins + VALUES(wins)"
            );

            foreach ($finalStandings as $player) {
                $bestWpm = 0;
                $bestAcc = 0;
                foreach ($player['roundHistory'] as $round) {
                    if (($round['netWpm'] ?? 0) > $bestWpm) {
                        $bestWpm = $round['netWpm'];
                    }
                    if (($round['accuracy'] ?? 0) > $bestAcc) {
                        $bestAcc = $round['accuracy'];
                    }
                }

                $stmt->execute([
                    ':username'      => $player['username'],
                    ':total_score'   => $player['totalScore'],
                    ':avg_wpm'       => $player['avgNetWpm'],
                    ':avg_accuracy'  => $player['avgAccuracy'],
                    ':avg_errors'    => $player['avgErrors'],
                    ':best_wpm'      => $bestWpm,
                    ':best_accuracy' => $bestAcc,
                    ':wins'          => $player['username'] === $winner ? 1 : 0,
                ]);
            }

            echo "[DB] Game stats saved successfully.\n";
        } catch (\PDOException $e) {
            echo "[DB] Error saving game stats: {$e->getMessage()}\n";
        }
    }
}
