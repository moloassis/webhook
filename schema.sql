-- Tabela para os Chamados/Alertas Ativos
CREATE TABLE IF NOT EXISTS `chamados` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nome_cliente` VARCHAR(255) DEFAULT NULL,
    `tipo` VARCHAR(100) NOT NULL DEFAULT 'atendimento_humano',
    `mensagem` TEXT DEFAULT NULL,
    `status` ENUM('pendente', 'notificado') NOT NULL DEFAULT 'pendente',
    `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Índices de performance para a tabela de chamados
CREATE INDEX idx_chamados_status ON `chamados` (`status`);
CREATE INDEX idx_chamados_criado ON `chamados` (`criado_em`);

-- Tabela para Auditoria Geral de Webhooks
CREATE TABLE IF NOT EXISTS `webhook_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `metodo` VARCHAR(10) NOT NULL,
    `ip` VARCHAR(45) NOT NULL,
    `event_type` VARCHAR(100) DEFAULT NULL,
    `payload` TEXT,
    `status_resposta` INT NOT NULL,
    `mensagem_resposta` VARCHAR(255),
    `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Índices de performance para a tabela de logs
CREATE INDEX idx_webhook_logs_criado ON `webhook_logs` (`criado_em`);
