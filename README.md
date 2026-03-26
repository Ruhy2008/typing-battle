# ⌨️ Typing Battle

Real-time multiplayer typing speed game built with **PHP (Ratchet WebSocket)** and vanilla **HTML/CSS/JS**.

![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4?logo=php&logoColor=white)
![WebSocket](https://img.shields.io/badge/WebSocket-Ratchet-orange)
![License](https://img.shields.io/badge/license-MIT-green)

## ✨ Features

- **Real-time multiplayer** — race against other players via WebSocket
- **5-round matches** — different sentence each round, 60s time limit
- **Live progress bars** — see everyone's typing progress in real-time
- **Character-level highlighting** — green for correct, red for mistakes
- **WPM calculation** — words per minute updated live as you type
- **Anti-cheat** — server-side WPM verification, paste prevention
- **Score system** — WPM × (1 − errorRate), cumulative standings
- **Auto game management** — auto-start when 2+ players join, auto-restart after game over
- **Database persistence** — optional MySQL storage for player stats & round logs
- **Dark glassmorphism UI** — premium dark theme with smooth animations

## 📁 Project Structure

```
game_klik/
├── bin/
│   └── server.php          # WebSocket server entry point
├── public/
│   ├── index.html          # Game UI (4 screens)
│   ├── style.css           # Dark glassmorphism theme
│   └── client.js           # WebSocket client & typing logic
├── sql/
│   └── schema.sql          # MySQL schema (optional)
├── src/
│   └── Logic.php           # Core game logic (862 lines)
├── composer.json           # PHP dependencies
└── README.md
```

## 🚀 Quick Start

### Prerequisites

- PHP 8.1+
- Composer

### 1. Install Dependencies

```bash
cd game_klik
composer install
```

### 2. Start the WebSocket Server

```bash
php bin/server.php
```

The server starts on **port 8080**.

### 3. Open the Game

Open `public/index.html` in your browser. You can:

- **Local file** — double-click `index.html` (WebSocket connects to `localhost:8080`)
- **PHP built-in server** — `php -S localhost:3000 -t public` then visit `http://localhost:3000`

### 4. Play!

1. Enter a username and click **Join Game**
2. Wait for a second player (open another browser tab)
3. Game auto-starts with a 3-second countdown
4. Type the sentence as fast and accurately as you can!

## 🗄️ Database Setup (Optional)

The game works **without a database**. To enable stat persistence:

### 1. Create the Database

```sql
CREATE DATABASE typing_battle;
```

### 2. Import Schema

```bash
mysql -u root -p typing_battle < sql/schema.sql
```

### 3. Configure Environment

```bash
export DB_HOST=localhost
export DB_NAME=typing_battle
export DB_USER=root
export DB_PASS=yourpassword
```

Then restart the server. Player stats and round logs will be saved automatically.

## 🎮 Game Rules

| Rule | Detail |
|------|--------|
| Rounds | 5 per game |
| Time Limit | 60 seconds per round |
| Scoring | WPM × (1 − error rate) |
| Anti-cheat | Max 300 WPM, min 5s finish time |
| Auto-start | Game begins when 2+ players join |
| DNF | Unfinished rounds score 0 |

## 🔧 Configuration

Edit constants in `src/Logic.php`:

```php
private const MAX_ROUNDS = 5;          // Rounds per game
private const ROUND_TIME_LIMIT = 60;   // Seconds per round
private const MAX_WPM = 300;           // Anti-cheat WPM cap
private const MIN_TIME = 5;            // Anti-cheat min seconds
```

## 📡 WebSocket Protocol

### Client → Server

| Type | Fields | Description |
|------|--------|-------------|
| `JOIN` | `username` | Register as a player |
| `PROGRESS` | `progress`, `currentWpm` | Update typing progress (0-1) |
| `FINISH` | `wpm`, `errors`, `timeSec` | Submit round results |

### Server → Client

| Type | Key Fields | Description |
|------|------------|-------------|
| `PLAYER_LIST` | `players[]` | Lobby player list update |
| `START_GAME` | `message` | 3-second countdown started |
| `ROUND_START` | `round`, `sentence`, `timeLimit` | New round begins |
| `TIMER_UPDATE` | `remaining` | Countdown tick (every second) |
| `PROGRESS_UPDATE` | `players[]` | All players' progress |
| `PLAYER_FINISHED` | `username`, `wpm` | A player finished |
| `ROUND_END` | `results[]`, `standings[]` | Round results & standings |
| `GAME_OVER` | `winner`, `finalStandings[]` | Final results |
| `ERROR` | `message` | Error message |

## 📄 License

MIT
