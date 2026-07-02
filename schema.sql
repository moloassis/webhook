-- Criar banco de dados se não existir (opcional, dependendo do ambiente VPS)
-- CREATE DATABASE IF NOT EXISTS seu_banco CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE seu_banco;

CREATE TABLE IF NOT EXISTS `chamados` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nome_cliente` VARCHAR(255) DEFAULT NULL,
    `tipo` VARCHAR(100) NOT NULL DEFAULT 'atendimento_humano',
    `mensagem` TEXT DEFAULT NULL,
    `status` ENUM('pendente', 'notificado') NOT NULL DEFAULT 'pendente',
    `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Índice no status para otimizar a consulta do loop SSE (evita Table Scans constantes)
CREATE INDEX idx_chamados_status ON `chamados` (`status`);

-- Índice opcional para ordenação e relatórios
CREATE INDEX idx_chamados_criado ON `chamados` (`criado_em`);
