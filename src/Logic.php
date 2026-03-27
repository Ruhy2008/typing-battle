<?php

namespace TypingBattle;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use React\EventLoop\Loop;

class Logic implements MessageComponentInterface
{
    public $clients;
    public $rooms = array(); 

    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
        require_once __DIR__ . '/db.php';
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        echo "Player masuk: {$conn->resourceId}\n";
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $data = json_decode($msg, true);
        if (!$data) return;

        switch ($data['type']) {
            case 'GET_LEADERBOARD':
                try {
                    $board = getLeaderboard(); 
                    $from->send(json_encode([
                        'type' => 'LEADERBOARD',
                        'data' => $board
                    ]));
                } catch (\Exception $e) { }
                break;

            case 'JOIN':
                $username = htmlspecialchars($data['username']);
                $mode = isset($data['mode']) ? $data['mode'] : 'multi'; 
                $id_room = null;

                if ($mode === 'multi') {
                    foreach ($this->rooms as $id => $room) {
                        if ($room['status'] == 'lobby' && $room['mode'] == 'multi' && count($room['players']) < 5) {
                            $id_room = $id; break;
                        }
                    }
                }

                if ($id_room == null) {
                    $id_room = uniqid('rm_');
                    $bank_kalimat = [
                        'Pemrograman adalah seni memberi tahu komputer apa yang harus dilakukan langkah demi langkah.',
                        'Sebuah perjalanan ribuan mil dimulai dengan satu langkah kecil ke depan.',
                        'Kode yang bagus adalah kode yang bisa dibaca dan dipahami oleh manusia, bukan hanya mesin.',
                        'Teknologi diciptakan untuk mempermudah hidup manusia, bukan untuk menggantikan peran manusia seutuhnya.'
                    ];
                    
                    $this->rooms[$id_room] = [
                        'status' => 'lobby',
                        'mode' => $mode,
                        'kalimat' => $bank_kalimat[array_rand($bank_kalimat)],
                        'waktu_sisa' => 60,
                        'timer_loop' => null,
                        'players' => []
                    ];
                }

                $this->rooms[$id_room]['players'][$from->resourceId] = [
                    'conn' => $from, 'username' => $username, 'typedText' => '',
                    'progress' => 0, 'wpm' => 0, 'acc' => 100, 'selesai' => false, 'score' => 0,
                    'total_errors' => 0, 
                    'last_length' => 0 
                ];

                $this->broadcastLobbyState($id_room);
                break;

            case 'START_MATCH':
                $id_room = $this->findRoomByPlayer($from->resourceId);
                if ($id_room && $this->rooms[$id_room]['status'] == 'lobby') {
                    $player_count = count($this->rooms[$id_room]['players']);
                    $mode = $this->rooms[$id_room]['mode'];
                    
                    if ($mode === 'solo' || $player_count >= 2) {
                        $this->rooms[$id_room]['status'] = 'playing';
                        $this->rooms[$id_room]['start_time'] = microtime(true);
                        
                        foreach ($this->rooms[$id_room]['players'] as $p) {
                            $p['conn']->send(json_encode(['type' => 'START_GAME', 'kalimat' => $this->rooms[$id_room]['kalimat']]));
                        }

                        $this->rooms[$id_room]['timer_loop'] = Loop::addPeriodicTimer(1, function() use ($id_room) {
                            try {
                                if (!isset($this->rooms[$id_room])) return;
                                $this->rooms[$id_room]['waktu_sisa']--;
                                
                                if ($this->rooms[$id_room]['waktu_sisa'] <= 0) {
                                    $this->endGame($id_room);
                                } else {
                                    $this->broadcastGameState($id_room);
                                }
                            } catch (\Throwable $e) {}
                        });
                    }
                }
                break;

            case 'INPUT':
                $id_room = $this->findRoomByPlayer($from->resourceId);

                if ($id_room && $this->rooms[$id_room]['status'] == 'playing') {
                    $player = &$this->rooms[$id_room]['players'][$from->resourceId];
                    if ($player['selesai']) break;

                    $typed = $data['typedText'];
                    $kalimat = $this->rooms[$id_room]['kalimat'];
                    $panjang_ketik = strlen($typed);
                    $panjang_kalimat = strlen($kalimat);
                    
                    // ─── LOGIKA MENDETEKSI KESALAHAN PERMANEN ───
                    if ($panjang_ketik > $player['last_length']) {
                        $last_index = $panjang_ketik - 1; 
                        if ($last_index < $panjang_kalimat) {
                            if ($typed[$last_index] !== $kalimat[$last_index]) {
                                $player['total_errors']++;
                            }
                        }
                    }
                    $player['last_length'] = $panjang_ketik; 
                    // ─── Hitung WPM (Berdasarkan huruf benar) ───
                    $benar = 0;
                    $cek_len = min($panjang_ketik, $panjang_kalimat);
                    for ($i = 0; $i < $cek_len; $i++) {
                        if ($typed[$i] == $kalimat[$i]) $benar++;
                    }

                    $progress = $panjang_kalimat > 0 ? ($panjang_ketik / $panjang_kalimat) * 100 : 0;
                    if ($progress > 100) $progress = 100;

                    $waktu_jalan = max(0.01, (microtime(true) - $this->rooms[$id_room]['start_time']) / 60);
                    $wpm = ($benar / 5) / $waktu_jalan;
                    
                    // ─── Hitung Akurasi yang Adil & Akurat ───
                    $acc = 100;
                    if ($panjang_kalimat > 0) {
                        $error_rate_pct = ($player['total_errors'] / $panjang_kalimat) * 100;
                        $acc = max(0, 100 - $error_rate_pct); // Akurasi minimal 0% (tidak bisa minus)
                    }

                    $player['progress'] = $progress;
                    $player['wpm'] = $wpm;
                    $player['acc'] = $acc;

                    if ($benar >= $panjang_kalimat && $panjang_ketik == $panjang_kalimat) {
                        $player['selesai'] = true;
                        
                        if ($this->rooms[$id_room]['waktu_sisa'] > 5) {
                            $this->rooms[$id_room]['waktu_sisa'] = 5; 
                        }

                        $semua_selesai = true;
                        foreach ($this->rooms[$id_room]['players'] as $p) {
                            if (!$p['selesai']) $semua_selesai = false;
                        }
                        if ($semua_selesai) {
                            $this->endGame($id_room);
                            break;
                        }
                    }
                    $this->broadcastGameState($id_room);
                }
                break;
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);
        $id_room = $this->findRoomByPlayer($conn->resourceId);
        
        if ($id_room) {
            unset($this->rooms[$id_room]['players'][$conn->resourceId]);
            if ($this->rooms[$id_room]['status'] == 'lobby') {
                $this->broadcastLobbyState($id_room);
            }
            if (count($this->rooms[$id_room]['players']) == 0) {
                if ($this->rooms[$id_room]['timer_loop']) {
                    Loop::cancelTimer($this->rooms[$id_room]['timer_loop']);
                }
                unset($this->rooms[$id_room]);
            }
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e) { $conn->close(); }

    private function findRoomByPlayer($resourceId) {
        foreach ($this->rooms as $id => $room) {
            if (isset($room['players'][$resourceId])) return $id;
        }
        return null;
    }

    private function broadcastLobbyState($id_room) {
        if (!isset($this->rooms[$id_room])) return;
        $data_kirim = [];
        foreach ($this->rooms[$id_room]['players'] as $p) {
            $data_kirim[] = ['username' => $p['username'], 'progress' => 0];
        }
        $json = json_encode(['type' => 'GAME_STATE', 'phase' => 'lobby', 'mode' => $this->rooms[$id_room]['mode'], 'players' => $data_kirim]);
        foreach ($this->rooms[$id_room]['players'] as $p) {
            $p['conn']->send($json);
        }
    }

    private function broadcastGameState($id_room) {
        if (!isset($this->rooms[$id_room])) return;
        $data_kirim = [];
        foreach ($this->rooms[$id_room]['players'] as $p) {
            $data_kirim[] = [
                'username' => $p['username'],
                'progress' => round($p['progress'], 2),
                'wpm' => round($p['wpm'], 1),
                'acc' => round($p['acc'], 1),
                'selesai' => $p['selesai']
            ];
        }
        $json = json_encode(['type' => 'GAME_STATE', 'phase' => 'playing', 'waktu' => $this->rooms[$id_room]['waktu_sisa'], 'players' => $data_kirim]);
        foreach ($this->rooms[$id_room]['players'] as $p) {
            $p['conn']->send($json);
        }
    }

    private function endGame($id_room) {
        $room = &$this->rooms[$id_room];
        $room['status'] = 'game_over';
        
        if ($room['timer_loop']) Loop::cancelTimer($room['timer_loop']);

        $standings = [];
        foreach ($room['players'] as $p) {
            $skor_akhir = (int) floor($p['wpm'] * ($p['acc'] / 100));
            if (!$p['selesai']) $skor_akhir = (int)($skor_akhir * 0.5); 
            
            $wpm = round($p['wpm'], 1);
            $errorRate = 100 - round($p['acc'], 1);

            $standings[] = ['username' => $p['username'], 'totalScore' => $skor_akhir, 'avgWpm' => $wpm, 'bestWpm' => $wpm, 'avgAccuracy' => round($p['acc'], 1)];
            
            // HANYA SIMPAN KE DB JIKA MODE MULTIPLAYER
            if ($room['mode'] === 'multi') {
                try {
                    upsertPlayerStats($p['username'], $skor_akhir, $wpm, $errorRate, $wpm);
                    
                    $waktu_terpakai = 60 - $room['waktu_sisa'];
                    saveRoundLog($id_room, 1, $p['username'], $wpm, $errorRate, $waktu_terpakai, $room['kalimat']);
                } catch (\Exception $e) { }
            }
        }
        usort($standings, fn($a, $b) => $b['totalScore'] - $a['totalScore']);

        $json = json_encode(['type' => 'GAME_OVER_STATS', 'finalStandings' => $standings]);
        foreach ($room['players'] as $p) { $p['conn']->send($json); }

        Loop::addTimer(5, function() use ($id_room) {
            if (isset($this->rooms[$id_room])) unset($this->rooms[$id_room]);
        });
    }
}