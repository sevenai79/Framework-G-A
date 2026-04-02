-- Schema tabella logs
-- MySQL / MariaDB

CREATE TABLE IF NOT EXISTS logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    posto VARCHAR(100) NOT NULL,
    operazione VARCHAR(100) NOT NULL,
    operatore VARCHAR(100) NOT NULL,
    file VARCHAR(255) NOT NULL,
    dati_mandati JSON NULL,
    scritto_il DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_logs_operatore (operatore),
    INDEX idx_logs_operazione (operazione),
    INDEX idx_logs_posto (posto),
    INDEX idx_logs_scritto_il (scritto_il)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

