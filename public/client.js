/* =======================================================
   Typing Battle — Pixel Arena — Client JS
   WebSocket client for the PHP Ratchet backend
   ======================================================= */

(() => {
    'use strict';

    // ─── Config ──────────────────────────────────────
    const WS_URL = `ws://${location.hostname}:8080`;
    const PROGRESS_THROTTLE_MS = 100;

    // ─── DOM Cache ───────────────────────────────────
    const $  = (sel) => document.querySelector(sel);
    const $$ = (sel) => document.querySelectorAll(sel);

    const DOM = {
        // Screens
        screenJoin:        $('#screen-join'),
        screenCountdown:   $('#screen-countdown'),
        screenPlaying:     $('#screen-playing'),
        screenRoundResult: $('#screen-round-result'),
        screenGameOver:    $('#screen-game-over'),

        // Join
        inputUsername:   $('#input-username'),
        btnJoin:         $('#btn-join'),
        joinError:       $('#join-error'),
        lobbyPlayers:    $('#lobby-players'),
        playerListLobby: $('#player-list-lobby'),

        // Countdown
        countdownNumber: $('#countdown-number'),

        // Playing
        hudRound:       $('#hud-round'),
        hudTimer:       $('#hud-timer'),
        sentenceDisplay:$('#sentence-display'),
        typingInput:    $('#typing-input'),
        liveRawWpm:     $('#live-raw-wpm'),
        liveNetWpm:     $('#live-net-wpm'),
        liveAccuracy:   $('#live-accuracy'),
        liveErrors:     $('#live-errors'),
        accuracyGauge:  $('#accuracy-gauge'),
        playerProgressContainer: $('#player-progress-container'),

        // Round Result
        roundResultTitle: $('#round-result-title'),
        roundResultsBody: $('#round-results-body'),
        roundStandings:   $('#round-standings'),

        // Game Over
        winnerName:        $('#winner-name'),
        finalStandingsBody:$('#final-standings-body'),
        playerStatsPanels: $('#player-stats-panels'),
        btnPlayAgain:      $('#btn-play-again'),

        // Connection
        wsStatusDot:  $('#ws-status-dot'),
        wsStatusText: $('#ws-status-text'),
    };

    // ─── State ───────────────────────────────────────
    let ws = null;
    let myUsername = '';
    let currentSentence = '';
    let typingStartTime = 0;
    let lastProgressSend = 0;
    let isFinished = false;
    let roundActive = false;

    // ─── Sounds (Web Audio API — tiny beeps) ─────────
    const AudioCtx = window.AudioContext || window.webkitAudioContext;
    let audioCtx = null;

    function initAudio() {
        if (!audioCtx) {
            try { audioCtx = new AudioCtx(); } catch (_) {}
        }
    }

    function playBeep(freq = 660, duration = 0.06, vol = 0.08) {
        if (!audioCtx) return;
        try {
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.type = 'square';
            osc.frequency.value = freq;
            gain.gain.value = vol;
            gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + duration);
            osc.connect(gain).connect(audioCtx.destination);
            osc.start();
            osc.stop(audioCtx.currentTime + duration);
        } catch (_) {}
    }

    function sfxType()   { playBeep(880, 0.04, 0.04); }
    function sfxError()  { playBeep(220, 0.12, 0.1); }
    function sfxFinish() { playBeep(1320, 0.15, 0.08); }
    function sfxRound()  { playBeep(660, 0.2, 0.06); }

    // ─── Screen Management ───────────────────────────
    function showScreen(screenEl) {
        $$('.screen').forEach(s => s.classList.remove('active'));
        screenEl.classList.add('active');
    }

    // ─── Toast Notification ──────────────────────────
    function showToast(msg, type = 'info', duration = 3000) {
        const toast = document.createElement('div');
        toast.className = `toast ${type === 'error' ? 'toast-error' : type === 'success' ? 'toast-success' : ''}`;
        toast.textContent = msg;
        document.body.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.3s';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }

    // ─── WebSocket ───────────────────────────────────
    function connect() {
        ws = new WebSocket(WS_URL);

        ws.onopen = () => {
            DOM.wsStatusDot.style.background = '#00ff88';
            DOM.wsStatusDot.style.boxShadow = '0 0 6px #00ff88';
            DOM.wsStatusText.textContent = 'CONNECTED';
        };

        ws.onclose = () => {
            DOM.wsStatusDot.style.background = '#ff3366';
            DOM.wsStatusDot.style.boxShadow = '0 0 6px #ff3366';
            DOM.wsStatusText.textContent = 'DISCONNECTED';

            // Auto-reconnect after 3s
            setTimeout(connect, 3000);
        };

        ws.onerror = () => {
            showToast('Connection error', 'error');
        };

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

    // ─── Message Router ──────────────────────────────
    function handleMessage(data) {
        switch (data.type) {
            case 'PLAYER_LIST':    onPlayerList(data);      break;
            case 'START_GAME':     onStartGame(data);       break;
            case 'PHASE_CHANGE':   onPhaseChange(data);     break;
            case 'ROUND_START':    onRoundStart(data);      break;
            case 'TIMER_UPDATE':   onTimerUpdate(data);     break;
            case 'GAME_STATE':     onGameState(data);       break;
            case 'PLAYER_FINISHED':onPlayerFinished(data);  break;
            case 'ROUND_END':      onRoundEnd(data);        break;
            case 'GAME_OVER':      onGameOver(data);        break;
            case 'ERROR':          onError(data);           break;
        }
    }

    // ─── Event Handlers ──────────────────────────────

    function onPlayerList(data) {
        const players = data.players || [];
        DOM.playerListLobby.innerHTML = players.map(p =>
            `<div class="player-chip">
                <span class="dot"></span>
                ${escapeHtml(p.username)}
            </div>`
        ).join('');
        DOM.lobbyPlayers.classList.remove('hidden');
    }

    function onStartGame(data) {
        showScreen(DOM.screenCountdown);
        sfxRound();

        // 3-second countdown
        let count = 3;
        DOM.countdownNumber.textContent = count;
        const iv = setInterval(() => {
            count--;
            if (count > 0) {
                DOM.countdownNumber.textContent = count;
                sfxRound();
            } else {
                clearInterval(iv);
            }
        }, 1000);
    }

    function onPhaseChange(data) {
        // Phase changes are mostly handled by specific events
        // But this ensures we're in sync if something odd happens
    }

    function onRoundStart(data) {
        currentSentence = data.sentence || '';
        isFinished = false;
        roundActive = true;
        typingStartTime = 0;

        DOM.hudRound.textContent = `${data.round}/${data.totalRounds}`;
        DOM.hudTimer.textContent = data.timeLimit || 60;

        // Render sentence with per-character spans
        renderSentence('', currentSentence);

        // Reset input
        DOM.typingInput.value = '';
        DOM.typingInput.disabled = false;

        // Reset live stats
        DOM.liveRawWpm.textContent = '0';
        DOM.liveNetWpm.textContent = '0';
        DOM.liveAccuracy.textContent = '100%';
        DOM.liveErrors.textContent = '0';
        updateAccuracyGauge(100);

        showScreen(DOM.screenPlaying);
        sfxRound();

        // Focus the textarea
        setTimeout(() => DOM.typingInput.focus(), 100);
    }

    function onTimerUpdate(data) {
        const remaining = data.remaining ?? 0;
        DOM.hudTimer.textContent = remaining;

        // Flash red when low
        if (remaining <= 5) {
            DOM.hudTimer.style.color = '#ff3366';
            DOM.hudTimer.style.textShadow = '0 0 8px rgba(255,51,102,0.5)';
        } else {
            DOM.hudTimer.style.color = '';
            DOM.hudTimer.style.textShadow = '';
        }
    }

    function onGameState(data) {
        const players = data.players || [];
        renderPlayerProgress(players);
    }

    function onPlayerFinished(data) {
        if (data.username === myUsername) return;
        showToast(`${data.username} finished!`, 'info', 2000);
    }

    function onRoundEnd(data) {
        roundActive = false;
        DOM.typingInput.disabled = true;

        DOM.roundResultTitle.textContent = `Round ${data.round} Results`;

        // Results table
        const results = data.results || [];
        // Sort by score descending
        const sorted = [...results].sort((a, b) => b.score - a.score);

        DOM.roundResultsBody.innerHTML = sorted.map((r, i) => {
            const rank = i + 1;
            const dnfLabel = r.dnf ? '<span class="dnf-tag">DNF</span>' : '';
            return `<tr>
                <td class="font-pixel text-[10px] text-pixel-muted">${rank}</td>
                <td class="${r.username === myUsername ? 'text-pixel-primary' : ''}">${escapeHtml(r.username)} ${dnfLabel}</td>
                <td class="text-pixel-cyan">${r.netWpm}</td>
                <td class="text-pixel-accent">${r.accuracy}%</td>
                <td class="text-pixel-primary">${r.score}</td>
            </tr>`;
        }).join('');

        // Standings
        const standings = data.standings || [];
        DOM.roundStandings.innerHTML = standings.map((s, i) => {
            const cls = i === 0 ? 'gold' : i === 1 ? 'silver' : i === 2 ? 'bronze' : '';
            const medal = i === 0 ? '🥇' : i === 1 ? '🥈' : i === 2 ? '🥉' : `#${i+1}`;
            return `<div class="standing-row ${cls}">
                <span class="font-pixel text-[10px] ${s.username === myUsername ? 'text-pixel-primary' : 'text-pixel-text'}">${medal} ${escapeHtml(s.username)}</span>
                <span class="font-pixel text-[10px] text-pixel-cyan">${s.totalScore} pts</span>
            </div>`;
        }).join('');

        showScreen(DOM.screenRoundResult);
    }

    function onGameOver(data) {
        roundActive = false;

        DOM.winnerName.textContent = data.winner || 'Nobody';

        // Final standings table
        const standings = data.finalStandings || [];
        DOM.finalStandingsBody.innerHTML = standings.map((s, i) => {
            const rank = i === 0 ? '🥇' : i === 1 ? '🥈' : i === 2 ? '🥉' : `#${i+1}`;
            return `<tr>
                <td class="font-pixel text-[10px]">${rank}</td>
                <td class="${s.username === myUsername ? 'text-pixel-primary' : ''}">${escapeHtml(s.username)}</td>
                <td class="text-pixel-cyan">${s.totalScore}</td>
                <td>${s.avgNetWpm}</td>
                <td>${s.avgAccuracy}%</td>
            </tr>`;
        }).join('');

        // Per-player stat panels
        DOM.playerStatsPanels.innerHTML = standings.map(p => {
            let deltaHTML = '';
            if (p.firstVsLast) {
                const fl = p.firstVsLast;
                const wpmColor = fl.improved ? 'stat-improved' : 'stat-declined';
                deltaHTML = `
                    <div class="stat-row">
                        <span class="stat-label">First → Last WPM</span>
                        <span class="${wpmColor}">${fl.firstRound.netWpm} → ${fl.lastRound.netWpm} (${fl.wpmDelta})</span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">First → Last Acc</span>
                        <span class="${wpmColor}">${fl.firstRound.accuracy}% → ${fl.lastRound.accuracy}% (${fl.accuracyDelta})</span>
                    </div>`;
            }

            let bestWorstHTML = '';
            if (p.bestRound && p.worstRound) {
                bestWorstHTML = `
                    <div class="stat-row">
                        <span class="stat-label">Best Round</span>
                        <span class="stat-improved">R${p.bestRound.round} — ${p.bestRound.score} pts</span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Worst Round</span>
                        <span class="stat-declined">R${p.worstRound.round} — ${p.worstRound.score} pts</span>
                    </div>`;
            }

            const os = p.overallStats || {};
            return `<div class="stat-panel">
                <div class="stat-panel-header">${escapeHtml(p.username)}</div>
                <div class="stat-row">
                    <span class="stat-label">Total Score</span>
                    <span class="stat-value text-pixel-primary">${p.totalScore}</span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Avg Net WPM</span>
                    <span class="stat-value">${p.avgNetWpm}</span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Avg Accuracy</span>
                    <span class="stat-value">${p.avgAccuracy}%</span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Avg Errors</span>
                    <span class="stat-value">${p.avgErrors}</span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Total Errors</span>
                    <span class="stat-value">${os.totalErrors ?? 0}</span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Rounds Played</span>
                    <span class="stat-value">${os.roundsPlayed ?? 0}</span>
                </div>
                ${deltaHTML}
                ${bestWorstHTML}
            </div>`;
        }).join('');

        sfxFinish();
        showScreen(DOM.screenGameOver);
    }

    function onError(data) {
        const msg = data.message || 'Unknown error';

        // Show on join screen if we're there
        if (DOM.screenJoin.classList.contains('active')) {
            DOM.joinError.textContent = msg;
            DOM.joinError.classList.remove('hidden');
        }

        showToast(msg, 'error');
    }

    // ─── Sentence Rendering (char-by-char) ───────────
    function renderSentence(typed, sentence) {
        let html = '';
        for (let i = 0; i < sentence.length; i++) {
            const ch = sentence[i] === ' ' ? '&nbsp;' : escapeHtml(sentence[i]);
            if (i < typed.length) {
                if (typed[i] === sentence[i]) {
                    html += `<span class="char-correct">${ch}</span>`;
                } else {
                    html += `<span class="char-incorrect">${ch}</span>`;
                }
            } else if (i === typed.length) {
                html += `<span class="char-current">${ch}</span>`;
            } else {
                html += `<span class="char-untyped">${ch}</span>`;
            }
        }
        DOM.sentenceDisplay.innerHTML = html;
    }

    // ─── Accuracy Gauge ──────────────────────────────
    function updateAccuracyGauge(accuracy) {
        const pct = Math.max(0, Math.min(100, accuracy));
        DOM.accuracyGauge.style.width = pct + '%';

        // Color based on accuracy
        if (pct >= 90) {
            DOM.accuracyGauge.style.background = '#00ff88';
        } else if (pct >= 70) {
            DOM.accuracyGauge.style.background = '#ffcc00';
        } else if (pct >= 50) {
            DOM.accuracyGauge.style.background = '#ff8833';
        } else {
            DOM.accuracyGauge.style.background = '#ff3366';
        }
    }

    // ─── Player Progress Bars ────────────────────────
    function renderPlayerProgress(players) {
        DOM.playerProgressContainer.innerHTML = players.map((p, i) => {
            const colorIdx = i % 6;
            const pct = Math.round(p.progress * 100);
            const wpmLabel = p.finished ? '✓ DONE' : `${p.currentWpm || p.netWpm} wpm`;
            const isMe = p.username === myUsername;
            const nameClass = isMe ? 'text-pixel-primary' : '';
            return `<div class="player-progress-row player-color-${colorIdx}">
                <span class="player-progress-name ${nameClass}">${escapeHtml(p.username)}</span>
                <div class="player-progress-bar-track">
                    <div class="player-progress-bar-fill" style="width:${pct}%"></div>
                </div>
                <span class="player-progress-wpm">${wpmLabel}</span>
            </div>`;
        }).join('');
    }

    // ─── Typing Engine ───────────────────────────────
    function onTypingInput() {
        if (!roundActive || isFinished) return;

        const typed = DOM.typingInput.value;

        // Start timer on first keystroke
        if (typingStartTime === 0 && typed.length > 0) {
            typingStartTime = Date.now();
        }

        // Re-render sentence display
        renderSentence(typed, currentSentence);

        // Calculate local stats
        const stats = calcLocalStats(typed);

        // Update HUD
        DOM.liveRawWpm.textContent = stats.rawWpm;
        DOM.liveNetWpm.textContent = stats.netWpm;
        DOM.liveAccuracy.textContent = stats.accuracy + '%';
        DOM.liveErrors.textContent = stats.errors;
        updateAccuracyGauge(stats.accuracy);

        // Sound effects
        if (typed.length > 0) {
            const lastChar = typed[typed.length - 1];
            const expectedChar = currentSentence[typed.length - 1] || '';
            if (lastChar === expectedChar) {
                sfxType();
            } else {
                sfxError();
            }
        }

        // Throttled progress send
        const now = Date.now();
        if (now - lastProgressSend >= PROGRESS_THROTTLE_MS) {
            lastProgressSend = now;
            send({
                type: 'PROGRESS',
                typedText: typed,
            });
        }

        // Check finish
        if (typed === currentSentence) {
            isFinished = true;
            roundActive = false;
            DOM.typingInput.disabled = true;
            sfxFinish();

            send({
                type: 'FINISH',
                typedText: typed,
            });

            showToast('You finished! 🎉', 'success', 2000);
        }
    }

    function calcLocalStats(typed) {
        if (typed.length === 0 || typingStartTime === 0) {
            return { rawWpm: 0, netWpm: 0, accuracy: 100, errors: 0 };
        }

        const elapsedMin = (Date.now() - typingStartTime) / 60000;
        if (elapsedMin <= 0) {
            return { rawWpm: 0, netWpm: 0, accuracy: 100, errors: 0 };
        }

        const rawWpm = Math.round((typed.length / 5) / elapsedMin);

        let correct = 0;
        let errors = 0;
        const checkLen = Math.min(typed.length, currentSentence.length);
        for (let i = 0; i < checkLen; i++) {
            if (typed[i] === currentSentence[i]) {
                correct++;
            } else {
                errors++;
            }
        }
        // Extra chars beyond sentence = errors
        if (typed.length > currentSentence.length) {
            errors += typed.length - currentSentence.length;
        }

        const accuracy = typed.length > 0 ? Math.round((correct / typed.length) * 100) : 100;
        const netWpm = Math.max(0, Math.round(rawWpm * (accuracy / 100)));

        return { rawWpm, netWpm, accuracy, errors };
    }

    // ─── Join Flow ───────────────────────────────────
    function doJoin() {
        initAudio();
        const username = DOM.inputUsername.value.trim();
        if (!username) {
            DOM.joinError.textContent = 'Please enter a username';
            DOM.joinError.classList.remove('hidden');
            return;
        }
        myUsername = username;
        DOM.joinError.classList.add('hidden');
        send({ type: 'JOIN', username });
    }

    // ─── Play Again ──────────────────────────────────
    function doPlayAgain() {
        showScreen(DOM.screenJoin);
        DOM.lobbyPlayers.classList.add('hidden');
    }

    // ─── Utility ─────────────────────────────────────
    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // ─── Event Bindings ──────────────────────────────
    DOM.btnJoin.addEventListener('click', doJoin);
    DOM.inputUsername.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') doJoin();
    });
    DOM.typingInput.addEventListener('input', onTypingInput);
    DOM.btnPlayAgain.addEventListener('click', doPlayAgain);

    // Block paste in typing input
    DOM.typingInput.addEventListener('paste', (e) => {
        e.preventDefault();
        showToast('Paste is disabled!', 'error', 1500);
    });

    // ─── Init ────────────────────────────────────────
    connect();
    showScreen(DOM.screenJoin);
})();
