// client.js — Frontend Logic (Fairuz)

// ══════════════════════════════════════════════════════════
// Konfigurasi Server
// ══════════════════════════════════════════════════════════
// Lokal:   ws://localhost:8080
// Railway: wss://typing-battle-XXXX.up.railway.app
const SERVER_URL = 'ws://localhost:8080';

// ══════════════════════════════════════════════════════════
// State Global
// ══════════════════════════════════════════════════════════
let ws            = null;
let myUsername    = '';
let currentSentence = '';
let startTime     = null;
let timerInterval = null;
let wpmInterval   = null;
let timeLeft      = 60;
let gameFinished  = false;
let currentRound  = 0;

// ══════════════════════════════════════════════════════════
// DOM Helpers
// ══════════════════════════════════════════════════════════
const screen       = (id) => document.getElementById(id);
const showScreen   = (id) => {
    ['lobby-screen', 'game-screen', 'result-screen', 'final-screen'].forEach(s => {
        document.getElementById(s).classList.add('hidden');
    });
    document.getElementById(id).classList.remove('hidden');
};

// ══════════════════════════════════════════════════════════
// WebSocket
// ══════════════════════════════════════════════════════════
function connectWS() {
    ws = new WebSocket(SERVER_URL);

    ws.onopen = () => {
        console.log('[WS] Terhubung ke server.');
        addLog('Terhubung ke server!', 'success');
    };

    ws.onmessage = (event) => {
        const data = JSON.parse(event.data);
        handleMessage(data);
    };

    ws.onclose = () => {
        console.log('[WS] Koneksi terputus.');
        addLog('Koneksi terputus dari server.', 'error');
    };

    ws.onerror = (err) => {
        console.error('[WS] Error:', err);
        addLog('Gagal terhubung ke server.', 'error');
    };
}

function sendMsg(obj) {
    if (ws && ws.readyState === WebSocket.OPEN) {
        ws.send(JSON.stringify(obj));
    }
}

// ══════════════════════════════════════════════════════════
// Message Handler
// ══════════════════════════════════════════════════════════
function handleMessage(data) {
    console.log('[MSG]', data);
    switch (data.type) {
        case 'SYSTEM':
            addLog(data.message, 'info');
            break;
        case 'ERROR':
            addLog('ERROR: ' + data.message, 'error');
            break;
        case 'PLAYER_LIST':
            renderPlayerList(data.players);
            break;
        case 'START_GAME':
            addLog('Game dimulai! Bersiap...', 'success');
            break;
        case 'ROUND_START':
            startRound(data);
            break;
        case 'PROGRESS_UPDATE':
            updateOpponentProgress(data.username, data.progress);
            break;
        case 'PLAYER_FINISHED':
            addLog(`${data.username} selesai! WPM: ${data.wpm} | Error: ${data.errorRate}%`, 'info');
            break;
        case 'ROUND_END':
            showRoundResult(data);
            break;
        case 'GAME_OVER':
            showFinalResult(data.stats);
            break;
        case 'LEADERBOARD':
            renderLeaderboard(data.data);
            break;
    }
}

// ══════════════════════════════════════════════════════════
// Lobby
// ══════════════════════════════════════════════════════════
function joinGame() {
    const usernameInput = document.getElementById('username-input');
    myUsername = usernameInput.value.trim();
    if (!myUsername) {
        alert('Masukkan username dulu!');
        return;
    }
    if (myUsername.length > 20) {
        alert('Username maks 20 karakter!');
        return;
    }
    connectWS();
    setTimeout(() => {
        sendMsg({ type: 'JOIN', username: myUsername });
    }, 500);
    document.getElementById('join-btn').disabled = true;
    addLog(`Bergabung sebagai ${myUsername}...`, 'info');
}

function renderPlayerList(players) {
    const list = document.getElementById('player-list');
    list.innerHTML = '';
    players.forEach(p => {
        const li = document.createElement('li');
        li.textContent = `${p.username} — Skor: ${p.score}`;
        if (p.username === myUsername) li.classList.add('me');
        list.appendChild(li);
    });
    document.getElementById('player-count').textContent = `${players.length} / 2 pemain`;
}

// ══════════════════════════════════════════════════════════
// Game Screen
// ══════════════════════════════════════════════════════════
function startRound(data) {
    currentRound    = data.round;
    currentSentence = data.sentence;
    timeLeft        = data.timeLimit;
    gameFinished    = false;
    startTime       = null;

    showScreen('game-screen');

    document.getElementById('round-label').textContent = `Ronde ${currentRound} / 5`;
    document.getElementById('timer-display').textContent = timeLeft;
    document.getElementById('wpm-display').textContent = '0';
    document.getElementById('typing-input').value = '';
    document.getElementById('typing-input').disabled = false;
    document.getElementById('typing-input').focus();

    renderSentence(currentSentence);

    // Reset progress bar diri sendiri
    setProgress(myUsername, 0);

    // Mulai countdown timer
    clearInterval(timerInterval);
    timerInterval = setInterval(() => {
        timeLeft--;
        document.getElementById('timer-display').textContent = timeLeft;
        if (timeLeft <= 0) {
            clearInterval(timerInterval);
            if (!gameFinished) finishTyping(true);
        }
    }, 1000);

    addLog(`Ronde ${currentRound} dimulai! Mulai mengetik...`, 'success');
}

function renderSentence(sentence) {
    const el = document.getElementById('sentence-display');
    el.innerHTML = sentence.split('').map((ch, i) =>
        `<span id="char-${i}">${ch === ' ' ? '&nbsp;' : ch}</span>`
    ).join('');
}

function onTyping() {
    const input    = document.getElementById('typing-input').value;
    const sentence = currentSentence;

    if (!startTime && input.length > 0) {
        startTime = Date.now();
    }

    if (gameFinished) return;

    // Warna karakter
    for (let i = 0; i < sentence.length; i++) {
        const span = document.getElementById(`char-${i}`);
        if (!span) continue;
        if (i < input.length) {
            span.className = input[i] === sentence[i] ? 'correct' : 'wrong';
        } else {
            span.className = '';
        }
    }

    // Hitung progress (% karakter benar berurutan dari awal)
    let correctCount = 0;
    for (let i = 0; i < input.length && i < sentence.length; i++) {
        if (input[i] === sentence[i]) correctCount++;
        else break;
    }
    const progress = Math.round((correctCount / sentence.length) * 100);
    setProgress(myUsername, progress);

    // Live WPM
    if (startTime) {
        const wpm = calculateWPM(input, startTime);
        document.getElementById('wpm-display').textContent = Math.min(wpm, 300);
    }

    // Kirim progress ke server
    sendMsg({
        type:     'PROGRESS',
        username: myUsername,
        typed:    input,
        progress: progress,
    });

    // Cek selesai
    if (input === sentence) {
        finishTyping(false);
    }
}

function finishTyping(isTimeout) {
    if (gameFinished) return;
    gameFinished = true;
    clearInterval(timerInterval);

    document.getElementById('typing-input').disabled = true;

    const typed    = document.getElementById('typing-input').value;
    const timeSec  = startTime ? ((Date.now() - startTime) / 1000) : 60;
    const wpm      = calculateWPM(typed, startTime || (Date.now() - 60000));
    const errorRate = calculateErrorRate(typed, currentSentence);

    // Validasi client-side
    const safeWpm = Math.min(wpm, 300);

    sendMsg({
        type:      'FINISH',
        username:  myUsername,
        wpm:       safeWpm,
        errorRate: parseFloat(errorRate),
        timeSec:   parseFloat(timeSec.toFixed(2)),
    });

    addLog(isTimeout ? 'Waktu habis!' : 'Selesai mengetik! Menunggu pemain lain...', 'info');
}

function setProgress(username, pct) {
    // Diri sendiri
    if (username === myUsername) {
        const bar = document.getElementById('my-progress');
        if (bar) bar.style.width = pct + '%';
        const label = document.getElementById('my-progress-label');
        if (label) label.textContent = pct + '%';
        return;
    }
    // Pemain lain
    const bar = document.getElementById(`progress-${username}`);
    if (bar) bar.style.width = pct + '%';
    const label = document.getElementById(`progress-label-${username}`);
    if (label) label.textContent = pct + '%';
}

function updateOpponentProgress(username, progress) {
    // Buat elemen progress jika belum ada
    const container = document.getElementById('opponents-progress');
    if (!container) return;

    let row = document.getElementById(`row-${username}`);
    if (!row) {
        row = document.createElement('div');
        row.id = `row-${username}`;
        row.className = 'progress-row';
        row.innerHTML = `
            <span class="progress-name">${username}</span>
            <div class="progress-bar-wrap">
                <div class="progress-bar" id="progress-${username}" style="width:0%"></div>
            </div>
            <span class="progress-pct" id="progress-label-${username}">0%</span>
        `;
        container.appendChild(row);
    }
    setProgress(username, progress);
}

// ══════════════════════════════════════════════════════════
// Round Result Screen
// ══════════════════════════════════════════════════════════
function showRoundResult(data) {
    clearInterval(timerInterval);
    showScreen('result-screen');

    document.getElementById('result-round-label').textContent = `Hasil Ronde ${data.round}`;
    const tbody = document.getElementById('result-table-body');
    tbody.innerHTML = '';

    data.results.forEach((r, i) => {
        const tr = document.createElement('tr');
        if (r.username === myUsername) tr.classList.add('highlight');
        tr.innerHTML = `
            <td>${i + 1}</td>
            <td>${r.username}</td>
            <td>${r.wpm}</td>
            <td>${r.errorRate}%</td>
            <td>${r.timeSec}s</td>
            <td>${r.points}</td>
        `;
        tbody.appendChild(tr);
    });

    const msg = data.round < 5
        ? `Ronde berikutnya dimulai dalam 5 detik...`
        : `Ini ronde terakhir! Menghitung hasil akhir...`;
    document.getElementById('result-next-msg').textContent = msg;
}

// ══════════════════════════════════════════════════════════
// Final Screen
// ══════════════════════════════════════════════════════════
function showFinalResult(stats) {
    showScreen('final-screen');

    // Simpan ke localStorage untuk dashboard
    saveToLocalStorage(stats);

    const tbody = document.getElementById('final-table-body');
    tbody.innerHTML = '';
    stats.forEach((s, i) => {
        const tr = document.createElement('tr');
        if (s.username === myUsername) tr.classList.add('highlight');
        tr.innerHTML = `
            <td>${i + 1}</td>
            <td>${s.username}</td>
            <td>${s.score}</td>
            <td>${s.avgWpm}</td>
            <td>${s.bestWpm}</td>
            <td>${s.avgErrorRate}%</td>
        `;
        tbody.appendChild(tr);
    });
}

// ══════════════════════════════════════════════════════════
// LocalStorage (untuk Dashboard Jems)
// ══════════════════════════════════════════════════════════
function saveToLocalStorage(stats) {
    const sessions = JSON.parse(localStorage.getItem('typingBattle_sessions') || '[]');
    sessions.push({
        timestamp: new Date().toISOString(),
        players:   stats,
    });
    // Simpan max 20 sesi terakhir
    if (sessions.length > 20) sessions.shift();
    localStorage.setItem('typingBattle_sessions', JSON.stringify(sessions));
    console.log('[LS] Data sesi disimpan ke localStorage.');
}

// ══════════════════════════════════════════════════════════
// Kalkulasi WPM & Error Rate
// ══════════════════════════════════════════════════════════
function calculateWPM(typedText, startTimeMs) {
    if (!startTimeMs || !typedText) return 0;
    const minutes = (Date.now() - startTimeMs) / 60000;
    if (minutes <= 0) return 0;
    const words = typedText.trim().split(/\s+/).length;
    return Math.round(words / minutes);
}

function calculateErrorRate(typed, original) {
    if (!typed || !original) return '0.0';
    let errors = 0;
    for (let i = 0; i < typed.length; i++) {
        if (typed[i] !== original[i]) errors++;
    }
    return ((errors / original.length) * 100).toFixed(1);
}

// ══════════════════════════════════════════════════════════
// Log
// ══════════════════════════════════════════════════════════
function addLog(msg, type = 'info') {
    const log = document.getElementById('log-area');
    if (!log) return;
    const li = document.createElement('li');
    li.textContent = `[${new Date().toLocaleTimeString()}] ${msg}`;
    li.className = type;
    log.prepend(li);
    if (log.children.length > 30) log.removeChild(log.lastChild);
}

// ══════════════════════════════════════════════════════════
// Init
// ══════════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('join-btn').addEventListener('click', joinGame);
    document.getElementById('username-input').addEventListener('keydown', (e) => {
        if (e.key === 'Enter') joinGame();
    });
    document.getElementById('typing-input').addEventListener('input', onTyping);
    showScreen('lobby-screen');
});
