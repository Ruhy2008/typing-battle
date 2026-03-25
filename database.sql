-- ============================================
-- TYPING BATTLE - Database Schema
-- Jalankan file ini di MySQL/Railway
-- ============================================

CREATE DATABASE IF NOT EXISTS railway CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE railway;

-- Tabel statistik pemain (akumulatif)
CREATE TABLE IF NOT EXISTS players_stats (
    username        VARCHAR(50)  PRIMARY KEY,
    total_score     INT          DEFAULT 0,
    avg_wpm         FLOAT        DEFAULT NULL,
    best_wpm        FLOAT        DEFAULT NULL,
    avg_error_rate  FLOAT        DEFAULT NULL,
    sessions_played INT          DEFAULT 0,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabel log per ronde
CREATE TABLE IF NOT EXISTS round_logs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    session_id  VARCHAR(20),
    round_num   INT,
    username    VARCHAR(50),
    wpm         FLOAT,
    error_rate  FLOAT,
    time_sec    FLOAT,
    sentence    TEXT,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Index untuk query dashboard
CREATE INDEX IF NOT EXISTS idx_round_logs_username ON round_logs(username);
CREATE INDEX IF NOT EXISTS idx_round_logs_session  ON round_logs(session_id);
