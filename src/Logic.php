<?php

namespace TypingBattle;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use React\EventLoop\Loop;

class Logic implements MessageComponentInterface
{
    public $clients;
    public $rooms = array(); 
    public $db;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
        
        // Panggil database buatan teman Anda
        try {
            require_once __DIR__ . '/db.php';
            $this->db = getDB();
        } catch (\Exception $e) {
            echo "Error DB: " . $e->getMessage() . "\n";
        }
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        echo "Player masuk dengan ID: {$conn->resourceId}\n";
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $data = json_decode($msg, true);
        if (!$data) return;

        switch ($data['type']) {
            case 'JOIN':
                $username = htmlspecialchars($data['username']);
                $mode = isset($data['mode']) ? $data['mode'] : 'multi'; // Deteksi Solo atau Multi
                $id_room = null;

                // Kalo modenya multi, cari room lobby yg blm penuh (maks 5)
                if ($mode === 'multi') {
                    foreach ($this->rooms as $id => $room) {
                        if ($room['status'] == 'lobby' && $room['mode'] == 'multi' && count($room['players']) < 5) {
                            $id_room = $id;
                            break;
                        }
                    }
                }

                // Kalo ga nemu room multi, atau dia milih Solo, bikin room baru
                if ($id_room == null) {
                    $id_room = uniqid('rm_');
                    
                    // Bank kalimat acak
                    $bank_kalimat = [
                        'Pemrograman adalah seni memberi tahu komputer apa yang harus dilakukan langkah demi langkah.',
                        'Sebuah perjalanan ribuan mil dimulai dengan satu langkah kecil ke depan.',
                        'Kode yang bagus adalah kode yang bisa dibaca dan dipahami oleh manusia, bukan hanya mesin.',
                        'Teknologi diciptakan untuk mempermudah hidup manusia, bukan untuk menggantikan peran manusia seutuhnya.',
                        'Rubah coklat yang tangkas melompati anjing malas di dekat tepi sungai yang tenang.',
                        'Mencari error di dalam kode program terkadang sama sulitnya dengan mencari jarum di tumpukan jerami.'
                    ];
                    
                    $this->rooms[$id_room] = [
                        'status' => 'lobby',
                        'mode' => $mode,
                        'kalimat' => $bank_kalimat[array_rand($bank_kalimat)],
                        'waktu_sisa' => 60,
                        'timer_loop' => null, // Untuk nyimpen interval timer nanti
                        'players' => []
                    ];
                }

                // Masukin player ke room
                $this->rooms[$id_room]['players'][$from->resourceId] = [
                    'conn' => $from,
                    'username' => $username,
                    'typedText' => '',
                    'progress' => 0,
                    'wpm' => 0,
                    'acc' => 100,
                    'selesai' => false,
                    'score' => 0
                ];

                echo "{$username} join ke room {$id_room} (Mode: {$mode})\n";
                $this->broadcastLobbyState($id_room);
                break;

            case 'START_MATCH':
                // Fitur Mulai Manual
                $id_room = $this->findRoomByPlayer($from->resourceId);
                
                if ($id_room && $this->rooms[$id_room]['status'] == 'lobby') {
                    $player_count = count($this->rooms[$id_room]['players']);
                    $mode = $this->rooms[$id_room]['mode'];
                    
                    // Cek syarat mulai: Solo = bebas, Multi = minimal 2 org
                    if ($mode === 'solo' || $player_count >= 2) {
                        $this->rooms[$id_room]['status'] = 'playing';
                        $this->rooms[$id_room]['start_time'] = microtime(true);
                        
                        // Kirim sinyal mulai ke semua org di room itu
                        foreach ($this->rooms[$id_room]['players'] as $p) {
                            $p['conn']->send(json_encode([
                                'type' => 'START_GAME',
                                'kalimat' => $this->rooms[$id_room]['kalimat']
                            ]));
                        }

                        // Bikin Timer detak 1 detik di server
                        $this->rooms[$id_room]['timer_loop'] = Loop::addPeriodicTimer(1, function() use ($id_room) {
                            if (!isset($this->rooms[$id_room])) return;
                            
                            $this->rooms[$id_room]['waktu_sisa']--;
                            
                            if ($this->rooms[$id_room]['waktu_sisa'] <= 0) {
                                $this->endGame($id_room);
                            } else {
                                $this->broadcastGameState($id_room);
                            }
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
                    
                    // Perhitungan anti-cheat (murni di server)
                    $benar = 0;
                    $cek_len = min(strlen($typed), strlen($kalimat));
                    for ($i = 0; $i < $cek_len; $i++) {
                        if ($typed[$i] == $kalimat[$i]) $benar++;
                    }

                    $progress = strlen($kalimat) > 0 ? ($benar / strlen($kalimat)) * 100 : 0;
                    $waktu_jalan = max(0.01, (microtime(true) - $this->rooms[$id_room]['start_time']) / 60);
                    $wpm = ($benar / 5) / $waktu_jalan;
                    $acc = strlen($typed) > 0 ? ($benar / strlen($typed)) * 100 : 100;

                    $player['progress'] = $progress;
                    $player['wpm'] = $wpm;
                    $player['acc'] = $acc;

                    // Trigger Selesai / Grace Period
                    if ($progress >= 100) {
                        $player['selesai'] = true;
                        $player['score'] += (int) floor($wpm * ($acc / 100));
                        
                        // Kalo dia yg pertama selesai, potong waktu sisa jadi 5 detik
                        if ($this->rooms[$id_room]['waktu_sisa'] > 5) {
                            $this->rooms[$id_room]['waktu_sisa'] = 5; 
                        }

                        // Cek apakah semuanya udah selesai
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

            case 'GET_LEADERBOARD':
                if (!$this->db) break;
                try {
                    $stmt = $this->db->query("SELECT username, total_score, avg_wpm, best_wpm, avg_error_rate, sessions_played FROM players_stats ORDER BY total_score DESC LIMIT 10");
                    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                    $from->send(json_encode(['type' => 'LEADERBOARD', 'data' => $rows]));
                } catch (\Exception $e) {}
                break;
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);
        $id_room = $this->findRoomByPlayer($conn->resourceId);
        
        if ($id_room) {
            unset($this->rooms[$id_room]['players'][$conn->resourceId]);
            $this->broadcastLobbyState($id_room);
            
            // Kalo room kosong, hancurkan
            if (count($this->rooms[$id_room]['players']) == 0) {
                if ($this->rooms[$id_room]['timer_loop']) {
                    Loop::cancelTimer($this->rooms[$id_room]['timer_loop']);
                }
                unset($this->rooms[$id_room]);
            }
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        $conn->close();
    }

    // ==========================================
    // FUNGSI BANTUAN (HELPERS)
    // ==========================================

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
        $json = json_encode([
            'type' => 'GAME_STATE', 
            'phase' => 'playing',
            'waktu' => $this->rooms[$id_room]['waktu_sisa'], 
            'players' => $data_kirim
        ]);
        foreach ($this->rooms[$id_room]['players'] as $p) {
            $p['conn']->send($json);
        }
    }

    private function endGame($id_room) {
        $room = &$this->rooms[$id_room];
        $room['status'] = 'game_over';
        
        if ($room['timer_loop']) {
            Loop::cancelTimer($room['timer_loop']);
        }

        $standings = [];
        foreach ($room['players'] as $p) {
            $skor_akhir = (int) floor($p['wpm'] * ($p['acc'] / 100));
            // Diskon kalo time out blm kelar
            if (!$p['selesai']) $skor_akhir = (int)($skor_akhir * 0.5); 
            
            $standings[] = [
                'username' => $p['username'],
                'totalScore' => $skor_akhir,
                'avgWpm' => round($p['wpm'], 1),
                'bestWpm' => round($p['wpm'], 1),
                'avgAccuracy' => round($p['acc'], 1)
            ];
        }

        usort($standings, fn($a, $b) => $b['totalScore'] - $a['totalScore']);

        $json = json_encode([
            'type' => 'GAME_OVER_STATS',
            'finalStandings' => $standings
        ]);

        foreach ($room['players'] as $p) {
            $p['conn']->send($json);
        }

        // Simpan ke DB
        if ($this->db) {
            $stmt = $this->db->prepare("INSERT INTO players_stats (username, sessions_played, total_score, avg_wpm, best_wpm, avg_error_rate) VALUES (:u, 1, :s, :w, :w, :e) ON DUPLICATE KEY UPDATE sessions_played = sessions_played + 1, total_score = total_score + VALUES(total_score), best_wpm = GREATEST(best_wpm, VALUES(best_wpm)), avg_wpm = (avg_wpm + VALUES(avg_wpm))/2, avg_error_rate = (avg_error_rate + VALUES(avg_error_rate))/2");
            foreach ($standings as $s) {
                $stmt->execute([':u'=>$s['username'], ':s'=>$s['totalScore'], ':w'=>$s['avgWpm'], ':e'=>100-$s['avgAccuracy']]);
            }
        }

        // Hancurkan room setelah 5 detik agar memori bersih
        Loop::addTimer(5, function() use ($id_room) {
            if (isset($this->rooms[$id_room])) unset($this->rooms[$id_room]);
        });
    }
}