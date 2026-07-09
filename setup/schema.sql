-- Script de Criação do Banco de Dados Multi-Tenant - Central de Alertas
-- Codificação: utf8mb4_unicode_ci

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Tabela para os tenants/empresas/workspaces
CREATE TABLE IF NOT EXISTS `tenants` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nome` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(100) NOT NULL,
    `logo_path` VARCHAR(255) DEFAULT NULL,
    `exibicao_logo` VARCHAR(20) DEFAULT 'logo_nome',
    `tempo_limite_espera` INT DEFAULT 5,
    `cor_primaria` VARCHAR(7) DEFAULT '#2ed573',
    `cor_secundaria` VARCHAR(7) DEFAULT '#70a1ff',
    `modo_visualizacao` ENUM('dark', 'light') DEFAULT 'dark',
    `webhook_token` VARCHAR(64) NOT NULL,
    `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `idx_tenants_slug` (`slug`),
    UNIQUE KEY `idx_tenants_token` (`webhook_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tabela para os usuários administrativos/operadores
CREATE TABLE IF NOT EXISTS `usuarios` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `empresa_id` INT DEFAULT NULL,
    `nome` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `senha_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('superadmin', 'admin', 'user') NOT NULL DEFAULT 'user',
    `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `idx_usuarios_email` (`email`),
    CONSTRAINT `fk_usuarios_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tabela para os chamados/alertas ativos das empresas
CREATE TABLE IF NOT EXISTS `chamados` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `empresa_id` INT NOT NULL,
    `nome_cliente` VARCHAR(255) DEFAULT NULL,
    `tipo` VARCHAR(100) NOT NULL DEFAULT 'atendimento_humano',
    `mensagem` TEXT DEFAULT NULL,
    `session_id` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('pendente', 'aguardando', 'resolvido') NOT NULL DEFAULT 'pendente',
    `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_chamados_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_chamados_status ON `chamados` (`status`);
CREATE INDEX idx_chamados_criado ON `chamados` (`criado_em`);
CREATE INDEX idx_chamados_empresa ON `chamados` (`empresa_id`);

-- 4. Tabela para auditoria geral de webhooks recebidos
CREATE TABLE IF NOT EXISTS `webhook_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `empresa_id` INT NOT NULL,
    `metodo` VARCHAR(10) NOT NULL,
    `ip` VARCHAR(45) NOT NULL,
    `event_type` VARCHAR(100) DEFAULT NULL,
    `payload` TEXT,
    `status_resposta` INT NOT NULL,
    `mensagem_resposta` VARCHAR(255),
    `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_logs_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_webhook_logs_criado ON `webhook_logs` (`criado_em`);
CREATE INDEX idx_webhook_logs_empresa ON `webhook_logs` (`empresa_id`);

-- 5. Tabela para assinaturas do PWA Web Push
CREATE TABLE IF NOT EXISTS `pwa_subscriptions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `usuario_id` INT NOT NULL,
    `endpoint` VARCHAR(750) NOT NULL,
    `keys_p256dh` VARCHAR(255) NOT NULL,
    `keys_auth` VARCHAR(255) NOT NULL,
    `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `idx_pwa_endpoint` (`endpoint`(255)),
    CONSTRAINT `fk_pwa_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Tabela para configurações persistentes e customizadas por empresa
CREATE TABLE IF NOT EXISTS `sistema_config` (
    `empresa_id` INT NOT NULL,
    `chave` VARCHAR(255) NOT NULL,
    `valor` TEXT NOT NULL,
    PRIMARY KEY (`empresa_id`, `chave`),
    CONSTRAINT `fk_config_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Tabela para logs de auditoria do Superadmin (Inspeção de Contas)
CREATE TABLE IF NOT EXISTS `superadmin_auditoria_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `usuario_id` INT NOT NULL,
    `usuario_nome` VARCHAR(255) NOT NULL,
    `usuario_email` VARCHAR(255) NOT NULL,
    `tenant_slug` VARCHAR(100) NOT NULL,
    `tenant_nome` VARCHAR(255) NOT NULL,
    `acao` VARCHAR(50) NOT NULL,
    `detalhes` TEXT NOT NULL,
    `ip` VARCHAR(45) NOT NULL,
    `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_auditoria_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_auditoria_criado ON `superadmin_auditoria_logs` (`criado_em`);

SET FOREIGN_KEY_CHECKS = 1;
