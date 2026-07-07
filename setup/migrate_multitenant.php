<?php
/**
 * Script de Migração do Banco de Dados para Multi-Tenant.
 * Executa as alterações estruturais necessárias de forma segura e incremental.
 */

header('Content-Type: text/plain; charset=UTF-8');
require_once __DIR__ . '/../db.php';

echo "=== INICIANDO MIGRAÇÃO PARA MULTI-TENANT ===\n\n";

try {
    $db = obterConexao();

    // Desativa temporariamente a verificação de chaves estrangeiras para evitar erros durante a reestruturação
    $db->exec("SET FOREIGN_KEY_CHECKS = 0;");

    // 1. Criar a tabela `tenants` (Empresas)
    echo "1. Verificando tabela 'tenants'...\n";
    $db->exec("CREATE TABLE IF NOT EXISTS `tenants` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "   ✔ Tabela 'tenants' pronta.\n";

    // 2. Criar a tabela `usuarios`
    echo "2. Verificando tabela 'usuarios'...\n";
    $db->exec("CREATE TABLE IF NOT EXISTS `usuarios` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `empresa_id` INT DEFAULT NULL,
        `nome` VARCHAR(255) NOT NULL,
        `email` VARCHAR(255) NOT NULL,
        `senha_hash` VARCHAR(255) NOT NULL,
        `role` ENUM('superadmin', 'admin', 'user') NOT NULL DEFAULT 'user',
        `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `idx_usuarios_email` (`email`),
        CONSTRAINT `fk_usuarios_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "   ✔ Tabela 'usuarios' pronta.\n";

    // 3. Criar ou Migrar a tabela `pwa_subscriptions`
    echo "3. Verificando tabela 'pwa_subscriptions'...\n";
    // Se a tabela já existir mas não tiver a coluna usuario_id, vamos recriar ou alterar.
    // Como assinaturas PWA antigas são dependentes de sessão anônima, é mais seguro recriar a tabela limpa
    // para associar corretamente com a tabela de usuários logados.
    $db->exec("DROP TABLE IF EXISTS `pwa_subscriptions`;");
    $db->exec("CREATE TABLE `pwa_subscriptions` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `usuario_id` INT NOT NULL,
        `endpoint` VARCHAR(750) NOT NULL,
        `keys_p256dh` VARCHAR(255) NOT NULL,
        `keys_auth` VARCHAR(255) NOT NULL,
        `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `idx_pwa_endpoint` (`endpoint`(255)),
        CONSTRAINT `fk_pwa_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "   ✔ Tabela 'pwa_subscriptions' recriada para suportar relacionamento com usuários.\n";

    // 4. Inserir a Empresa Padrão (Tenant Inicial) se não houver nenhuma
    echo "4. Configurando Empresa Padrão...\n";
    $stmt = $db->query("SELECT id FROM tenants WHERE slug = 'made-in-ai'");
    $tenantId = $stmt->fetchColumn();

    if (!$tenantId) {
        $webhookToken = bin2hex(random_bytes(16)); // Gera token único de 32 caracteres (hex)
        $stmtInsert = $db->prepare("INSERT INTO tenants (nome, slug, webhook_token, cor_primaria, cor_secundaria, modo_visualizacao) 
            VALUES ('Made in AI', 'made-in-ai', :token, '#2ed573', '#70a1ff', 'dark')");
        $stmtInsert->execute([':token' => $webhookToken]);
        $tenantId = $db->lastInsertId();
        echo "   ✔ Empresa 'Made in AI' criada com ID: $tenantId e Webhook Token: $webhookToken\n";
    } else {
        echo "   ✔ Empresa 'Made in AI' já existe (ID: $tenantId).\n";
    }

    // 5. Inserir Usuários Iniciais se a tabela de usuários estiver vazia
    echo "5. Verificando usuários administrativos iniciais...\n";
    $userCount = $db->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
    if ($userCount == 0) {
        // Criar Superadmin (Global)
        $senhaSuper = 'superadmin123';
        $hashSuper = password_hash($senhaSuper, PASSWORD_DEFAULT);
        $stmtUser = $db->prepare("INSERT INTO usuarios (empresa_id, nome, email, senha_hash, role) 
            VALUES (NULL, 'Super Admin', 'superadmin@madeinai.com.br', :hash, 'superadmin')");
        $stmtUser->execute([':hash' => $hashSuper]);

        // Criar Admin do Tenant Made in AI
        $senhaAdmin = 'admin123';
        $hashAdmin = password_hash($senhaAdmin, PASSWORD_DEFAULT);
        $stmtAdmin = $db->prepare("INSERT INTO usuarios (empresa_id, nome, email, senha_hash, role) 
            VALUES (:empresa_id, 'Admin Made in AI', 'admin@madeinai.com.br', :hash, 'admin')");
        $stmtAdmin->execute([
            ':empresa_id' => $tenantId,
            ':hash' => $hashAdmin
        ]);

        echo "   ✔ Usuários criados com sucesso:\n";
        echo "     - Superadmin: superadmin@madeinai.com.br (Senha: $senhaSuper)\n";
        echo "     - Admin da Empresa: admin@madeinai.com.br (Senha: $senhaAdmin)\n";
    } else {
        echo "   ✔ Usuários já configurados no banco de dados.\n";
    }

    // 6. Atualizar a tabela `chamados` para suportar `empresa_id`
    echo "6. Atualizando tabela 'chamados'...\n";
    $colsChamados = $db->query("SHOW COLUMNS FROM `chamados` LIKE 'empresa_id'")->fetchAll();
    if (empty($colsChamados)) {
        // Adiciona a coluna permitindo NULL temporariamente
        $db->exec("ALTER TABLE `chamados` ADD COLUMN `empresa_id` INT DEFAULT NULL AFTER `id`");
        // Associa todos os chamados existentes à empresa padrão
        $db->exec("UPDATE `chamados` SET `empresa_id` = $tenantId WHERE `empresa_id` IS NULL");
        // Modifica a coluna para ser NOT NULL e adiciona a foreign key
        $db->exec("ALTER TABLE `chamados` MODIFY COLUMN `empresa_id` INT NOT NULL");
        $db->exec("ALTER TABLE `chamados` ADD CONSTRAINT `fk_chamados_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE");
        echo "   ✔ Coluna 'empresa_id' adicionada e vinculada com sucesso à tabela 'chamados'.\n";
    } else {
        echo "   ✔ Tabela 'chamados' já está adaptada.\n";
    }

    // 7. Atualizar a tabela `webhook_logs` para suportar `empresa_id`
    echo "7. Atualizando tabela 'webhook_logs'...\n";
    $colsLogs = $db->query("SHOW COLUMNS FROM `webhook_logs` LIKE 'empresa_id'")->fetchAll();
    if (empty($colsLogs)) {
        // Adiciona a coluna permitindo NULL temporariamente
        $db->exec("ALTER TABLE `webhook_logs` ADD COLUMN `empresa_id` INT DEFAULT NULL AFTER `id`");
        // Associa todos os logs existentes à empresa padrão
        $db->exec("UPDATE `webhook_logs` SET `empresa_id` = $tenantId WHERE `empresa_id` IS NULL");
        // Modifica a coluna para ser NOT NULL e adiciona a foreign key
        $db->exec("ALTER TABLE `webhook_logs` MODIFY COLUMN `empresa_id` INT NOT NULL");
        $db->exec("ALTER TABLE `webhook_logs` ADD CONSTRAINT `fk_logs_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE");
        echo "   ✔ Coluna 'empresa_id' adicionada e vinculada com sucesso à tabela 'webhook_logs'.\n";
    } else {
        echo "   ✔ Tabela 'webhook_logs' já está adaptada.\n";
    }

    // 8. Reestruturar a tabela `sistema_config`
    echo "8. Reestruturando tabela 'sistema_config' para suporte a Multi-Tenant...\n";
    $hasConfigTable = $db->query("SHOW TABLES LIKE 'sistema_config'")->fetch();
    if ($hasConfigTable) {
        $colsConfig = $db->query("SHOW COLUMNS FROM `sistema_config` LIKE 'empresa_id'")->fetchAll();
        if (empty($colsConfig)) {
            // Existe a tabela de configurações mas não é multi-tenant. Vamos salvaguardar as configurações antigas.
            $configsAntigas = $db->query("SELECT chave, valor FROM sistema_config")->fetchAll();
            
            // Recriamos a tabela com chave primária composta
            $db->exec("DROP TABLE IF EXISTS `sistema_config`;");
            $db->exec("CREATE TABLE `sistema_config` (
                `empresa_id` INT NOT NULL,
                `chave` VARCHAR(255) NOT NULL,
                `valor` TEXT NOT NULL,
                PRIMARY KEY (`empresa_id`, `chave`),
                CONSTRAINT `fk_config_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            // Re-insere as configurações antigas vinculando-as ao tenant padrão
            if (!empty($configsAntigas)) {
                $stmtReinsert = $db->prepare("INSERT INTO sistema_config (empresa_id, chave, valor) VALUES (:empresa_id, :chave, :valor)");
                foreach ($configsAntigas as $conf) {
                    $stmtReinsert->execute([
                        ':empresa_id' => $tenantId,
                        ':chave' => $conf['chave'],
                        ':valor' => $conf['valor']
                    ]);
                }
            }
            echo "   ✔ Tabela 'sistema_config' convertida para Multi-Tenant (dados antigos preservados no tenant padrão).\n";
        } else {
            echo "   ✔ Tabela 'sistema_config' já está adaptada.\n";
        }
    } else {
        // Tabela não existia, cria do zero como multi-tenant
        $db->exec("CREATE TABLE `sistema_config` (
            `empresa_id` INT NOT NULL,
            `chave` VARCHAR(255) NOT NULL,
            `valor` TEXT NOT NULL,
            PRIMARY KEY (`empresa_id`, `chave`),
            CONSTRAINT `fk_config_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        echo "   ✔ Tabela 'sistema_config' criada do zero para suporte a Multi-Tenant.\n";
    }

    // Reativa a verificação de chaves estrangeiras
    $db->exec("SET FOREIGN_KEY_CHECKS = 1;");

    echo "\n=== MIGRAÇÃO CONCLUÍDA COM SUCESSO! ===\n";

} catch (Exception $e) {
    // Garante reativação das chaves estrangeiras caso dê erro
    try { $db->exec("SET FOREIGN_KEY_CHECKS = 1;"); } catch(Exception $ex) {}
    
    echo "\n❌ ERRO DURANTE A MIGRAÇÃO:\n";
    echo $e->getMessage() . "\n";
    exit(1);
}
