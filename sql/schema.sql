-- ============================================================================
-- Typing Battle — Enhanced Schema
-- ============================================================================

CREATE DATABASE IF NOT EXISTS typing_battle;
USE typing_battle;

-- Round-level logs with enhanced metrics
CREATE TABLE IF NOT EXISTS round_logs (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    game_id      VARCHAR(36) NOT NULL,
    round_number INT NOT NULL,
    username     VARCHAR(50) NOT NULL,
    sentence     TEXT NOT NULL,
    wpm          DECIMAL(6,1) DEFAULT 0,
    net_wpm      DECIMAL(6,1) DEFAULT 0,
    raw_wpm      DECIMAL(6,1) DEFAULT 0,
    errors       INT DEFAULT 0,
    accuracy     DECIMAL(5,1) DEFAULT 100.0,
    score        INT DEFAULT 0,
    time_sec     DECIMAL(5,1) DEFAULT 0,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_game_id (game_id),
    INDEX idx_username (username)
);

-- Aggregated player stats with enhanced metrics
CREATE TABLE IF NOT EXISTS players_stats (
    username      VARCHAR(50) PRIMARY KEY,
    games_played  INT DEFAULT 0,
    total_score   INT DEFAULT 0,
    avg_wpm       DECIMAL(6,1) DEFAULT 0,
    avg_accuracy  DECIMAL(5,1) DEFAULT 0,
    avg_errors    DECIMAL(5,1) DEFAULT 0,
    best_wpm      DECIMAL(6,1) DEFAULT 0,
    best_accuracy DECIMAL(5,1) DEFAULT 0,
    wins          INT DEFAULT 0,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
