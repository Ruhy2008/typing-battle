(() => {
    'use strict';

    // ─── KONFIGURASI KONEKSI ─────────────────────────
    const RAILWAY_URL = 'wss://typing-battle-production.up.railway.app'; 
    const IS_LOCAL    = location.hostname === 'localhost' || location.hostname === '127.0.0.1';
    const WS_URL      = IS_LOCAL ? `ws://${location.hostname}:8080` : RAILWAY_URL;

    // ─── CACHE DOM ELEMENTS ──────────────────────────
    const $  = (sel) => document.querySelector(sel);
    const $$ = (sel) => document.querySelectorAll(sel);

    const DOM = {
        // Screens
        screenLobby:       $('#lobby-screen'),
        screenGame:        $('#game-screen'),
        screenResult:      $('#result-screen'),
        screenFinal:       $('#final-screen'),

        // Lobby Inputs
        inputUsername:     $('#username-input'),
        btnJoinMulti:      $('#join-multi-btn'),
        btnJoinSolo:       $('#join-solo-btn'),
        
        // Lobby List & Status
        playerListContainer:$('#player-list-container'),
        playerCount:       $('#player-count'),
        playerList:        $('#player-list'),
        statusText:        $('#status-text'),
        btnStartManual:    $('#start-btn'),

        // Game Arena
        hudRound:          $('#round-indicator'),
        hudTimer:          $('#timer-display'),
        hudWpm:            $('#wpm-display'),
        sentenceDisplay:   $('#sentence-display'),
        typingInput:       $('#typing-input'),
        
        // Progress Bars
        myProgressRow:     $('#my-progress-row'),
        myProgressLabel:   $('#my-progress-label'),
        myProgressBar:     $('#my-progress'),
        opponentsProgress: $('#opponents-progress'),

        // Tables
        resultTableBody:   $('#result-table-body'),
        finalTableBody:    $('#final-table-body'),
    };

    // ─── STATE ───────────────────────────────────────
    let ws = null;
    let myUsername = '';
    let currentSentence = '';
    let isFinished = false;

    // ─── WEBSOCKET CONNECTION ────────────────────────
    function connect() {
        ws = new WebSocket(WS_URL);

        ws.onopen = () => console.log('[WS] Terhubung ke Server');
        ws.onclose = () => {
            console.log('[WS] Terputus. Mencoba reconnect...');
            setTimeout(connect, 3000);
        };
        ws.onerror = () => alert('Gagal terhubung ke server WebSocket!');

        ws.onmessage = (event) => {
            let data;
            try { data = JSON.parse(event.data); } catch { return; }
            handleMessage(data);
        };
    }

    function send(data) {
        if (ws && ws.readyState === WebSocket.OPEN) {
            ws.send(JSON.stringify(data));
        }
    }

    // ─── MESSAGE ROUTER ──────────────────────────────
    function handleMessage(data) {
        switch (data.type) {
            case 'GAME_STATE':    
                onGameState(data);       
                break;
            case 'START_GAME':    
                onStartGame(data);       
                break;
            case 'GAME_OVER_STATS': 
                onGameOver(data); 
                break;
        }
    }

    // ─── EVENT HANDLERS ──────────────────────────────
    function onGameState(data) {
        const players = data.players || [];
        
        // --- FASE LOBBY ---
        if (data.phase === 'lobby') {
            DOM.playerListContainer.classList.remove('hidden');
            DOM.playerCount.textContent = `(${players.length}/5)`;
            
            // Render list pemain
            DOM.playerList.innerHTML = players.map(p => 
                `<li class="${p.username === myUsername ? 'me' : ''}">
                    ${p.username === myUsername ? '👉 ' : '👤 '} ${escapeHtml(p.username)}
                </li>`
            ).join('');

            // LOGIKA TOMBOL MULAI: Solo = 1 orang cukup. Multi = min 2 orang.
            const isReadyToStart = (data.mode === 'solo') || (players.length >= 2);
            
            if (isReadyToStart) {
                DOM.btnStartManual.classList.remove('hidden');
                DOM.statusText.classList.add('hidden');
            } else {
                DOM.btnStartManual.classList.add('hidden');
                DOM.statusText.classList.remove('hidden');
            }
        }

        // --- FASE PLAYING ---
        if (data.phase === 'playing') {
            if (data.waktu !== undefined) {
                DOM.hudTimer.textContent = data.waktu;
                // UI Tweak: Merah saat waktu mau habis
                if (data.waktu <= 5) DOM.hudTimer.parentElement.style.color = 'var(--accent-pink)';
                else DOM.hudTimer.parentElement.style.color = '#fff';
            }
            renderPlayerProgress(players);
        }
    }

    function onStartGame(data) {
        currentSentence = data.kalimat || '';
        isFinished = false;

        // Persiapan UI Game
        DOM.typingInput.value = '';
        DOM.typingInput.disabled = false;
        DOM.hudTimer.textContent = '60';
        DOM.hudWpm.textContent = '0';
        
        renderSentence('', currentSentence);
        showScreen(DOM.screenGame);
        
        setTimeout(() => DOM.typingInput.focus(), 100);
    }

    function onGameOver(data) {
        DOM.typingInput.disabled = true;
        
        const standings = data.finalStandings || [];
        DOM.finalTableBody.innerHTML = standings.map((s, i) => {
            const rank = i === 0 ? '🥇' : i === 1 ? '🥈' : i === 2 ? '🥉' : `#${i+1}`;
            return `<tr>
                <td>${rank}</td>
                <td style="${s.username === myUsername ? 'color:var(--accent-cyan);font-weight:bold;' : ''}">${escapeHtml(s.username)}</td>
                <td>${s.totalScore}</td>
                <td>${s.avgWpm}</td>
                <td>${s.bestWpm}</td>
                <td>${100 - s.avgAccuracy}%</td>
            </tr>`;
        }).join('');

        // Simpan data untuk Dashboard Reaction Duel
        saveSessionData(standings);

        showScreen(DOM.screenFinal);
    }

    // ─── UI RENDERING ────────────────────────────────
    function renderPlayerProgress(players) {
        let opponentsHtml = '';
        
        players.forEach(p => {
            const pct = Math.min(100, Math.round(p.progress));
            const wpm = p.wpm ? Math.round(p.wpm) : 0;
            const isMe = p.username === myUsername;

            if (isMe) {
                DOM.myProgressBar.style.width = `${pct}%`;
                DOM.myProgressLabel.textContent = `${pct}%`;
                DOM.hudWpm.textContent = wpm;
                
                if (p.selesai && !isFinished) {
                    isFinished = true;
                    DOM.typingInput.disabled = true;
                    DOM.typingInput.value = "Menunggu pemain lain selesai...";
                }
            } else {
                const status = p.selesai ? 'DONE' : `${pct}%`;
                opponentsHtml += `
                <div class="prog-row">
                    <div class="prog-name"><span>${escapeHtml(p.username)}</span> <span>${status}</span></div>
                    <div class="prog-track"><div class="prog-fill" style="width:${pct}%"></div></div>
                </div>`;
            }
        });
        DOM.opponentsProgress.innerHTML = opponentsHtml;
    }

    function renderSentence(typed, sentence) {
        let html = '';
        for (let i = 0; i < sentence.length; i++) {
            const ch = sentence[i];
            if (i < typed.length) {
                if (typed[i] === ch) html += `<span class="correct">${ch}</span>`;
                else html += `<span class="wrong">${ch}</span>`;
            } else {
                html += `<span>${ch}</span>`;
            }
        }
        DOM.sentenceDisplay.innerHTML = html;
    }

    // ─── ACTION FLOW ─────────────────────────────────
    function doJoin(mode) {
        const username = DOM.inputUsername.value.trim();
        if (!username) {
            alert('Username tidak boleh kosong!');
            return;
        }
        myUsername = username;
        
        // Kunci input agar tidak diklik dua kali
        DOM.inputUsername.disabled = true;
        DOM.btnJoinMulti.disabled = true;
        DOM.btnJoinSolo.disabled = true;
        
        send({ type: 'JOIN', username: username, mode: mode });
    }

    function onTypingInput() {
        if (isFinished) return;
        const typed = DOM.typingInput.value;
        renderSentence(typed, currentSentence);
        send({ type: 'INPUT', typedText: typed });
    }

    function showScreen(screenEl) {
        $$('.screen').forEach(s => s.classList.add('hidden'));
        screenEl.classList.remove('hidden');
    }

    // ─── SIMPAN DATA UNTUK DASHBOARD ─────────────────
    function saveSessionData(standings) {
        try {
            const raw = localStorage.getItem('typingBattle_sessions');
            const sessions = raw ? JSON.parse(raw) : [];
            
            // Format agar sesuai dengan struktur "Reaction Duel Dashboard"
            const newSession = {
                timestamp: Date.now(),
                players: standings.map(s => ({
                    username: s.username,
                    avgWpm: s.avgWpm,
                    // Karena kita pakai array roundHistory di dashboard, kita buat simulasi ronde
                    roundHistory: [
                        { round: 1, wpm: s.bestWpm, errorRate: 100 - s.avgAccuracy }
                    ]
                }))
            };
            
            sessions.push(newSession);
            localStorage.setItem('typingBattle_sessions', JSON.stringify(sessions));
        } catch (e) {
            console.error('Gagal menyimpan ke localStorage', e);
        }
    }

    // ─── BINDINGS ────────────────────────────────────
    DOM.btnJoinMulti.addEventListener('click', () => doJoin('multi'));
    DOM.btnJoinSolo.addEventListener('click', () => doJoin('solo'));
    DOM.inputUsername.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') doJoin('multi'); // Default enter ke Multi
    });
    
    // Tombol Mulai Manual
    DOM.btnStartManual.addEventListener('click', () => {
        DOM.btnStartManual.disabled = true;
        DOM.btnStartManual.textContent = "MEMULAI...";
        send({ type: 'START_MATCH' });
    });

    DOM.typingInput.addEventListener('input', onTypingInput);
    DOM.typingInput.addEventListener('paste', (e) => {
        e.preventDefault();
        alert('Dilarang copas curang!');
    });

    // ─── INIT ────────────────────────────────────────
    connect();
    showScreen(DOM.screenLobby);
})();