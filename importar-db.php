<?php
/**
 * Script de Importação Automática de Banco de Dados.
 * Lê o arquivo schema.sql local e executa as instruções no banco de dados configurado no config.php.
 * 
 * SEGURANÇA: delete este arquivo da sua VPS após executá-lo com sucesso.
 */

require_once __DIR__ . '/db.php';

// Configura o visual da página de importação
echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>HelenaCRM - Setup de Banco de Dados</title>
    <style>
        body { background: #0c0a1f; color: #f1f2f6; font-family: sans-serif; padding: 3rem; text-align: center; }
        .box { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 2rem; max-width: 600px; margin: 0 auto; display: inline-block; text-align: left; }
        h2 { border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 10px; margin-top: 0; }
        .success { color: #2ed573; font-weight: bold; }
        .error { color: #ff4757; font-weight: bold; background: rgba(255,71,87,0.1); padding: 1rem; border-radius: 8px; border: 1px solid rgba(255,71,87,0.3); }
        .warning { color: #ffa502; font-weight: bold; }
    </style>
</head>
<body>
<div class="box">';

echo "<h2>Setup do Banco de Dados - HelenaCRM</h2>";

try {
    // 1. Conecta ao banco de dados usando as credenciais definidas
    $db = obterConexao();
    
    // 2. Garante a criação da tabela base chamados se ela não existir
    $db->exec("CREATE TABLE IF NOT EXISTS `chamados` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `nome_cliente` VARCHAR(255) DEFAULT NULL,
        `status` ENUM('pendente', 'resolvido') NOT NULL DEFAULT 'pendente',
        `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 3. Verificações incrementais (Migrations)
    // Garante que o ENUM de status seja ('pendente', 'resolvido')
    $db->exec("ALTER TABLE `chamados` MODIFY COLUMN `status` ENUM('pendente', 'resolvido') NOT NULL DEFAULT 'pendente'");
    
    // Se você já tiver a tabela chamados mas ela não contiver 'tipo' ou 'mensagem', nós a alteramos.
    
    // Verificar e Adicionar coluna 'tipo'
    $checarTipo = $db->query("SHOW COLUMNS FROM `chamados` LIKE 'tipo'")->fetchAll();
    if (empty($checarTipo)) {
        $db->exec("ALTER TABLE `chamados` ADD COLUMN `tipo` VARCHAR(100) NOT NULL DEFAULT 'atendimento_humano' AFTER `nome_cliente`");
        echo "<p class='success'>✔ Coluna 'tipo' adicionada com sucesso à tabela chamados!</p>";
    }

    // Verificar e Adicionar coluna 'mensagem'
    $checarMensagem = $db->query("SHOW COLUMNS FROM `chamados` LIKE 'mensagem'")->fetchAll();
    if (empty($checarMensagem)) {
        $db->exec("ALTER TABLE `chamados` ADD COLUMN `mensagem` TEXT DEFAULT NULL AFTER `tipo`");
        echo "<p class='success'>✔ Coluna 'mensagem' adicionada com sucesso à tabela chamados!</p>";
    }

    // Tenta criar os índices da tabela chamados (ignora caso já existam)
    try {
        $db->exec("CREATE INDEX idx_chamados_status ON `chamados` (`status`)");
    } catch (PDOException $e) { /* Índice já existente, ignorar */ }
    
    try {
        $db->exec("CREATE INDEX idx_chamados_criado ON `chamados` (`criado_em`)");
    } catch (PDOException $e) { /* Índice já existente, ignorar */ }

    // 4. Garante a criação da tabela webhook_logs
    $db->exec("CREATE TABLE IF NOT EXISTS `webhook_logs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `metodo` VARCHAR(10) NOT NULL,
        `ip` VARCHAR(45) NOT NULL,
        `event_type` VARCHAR(100) DEFAULT NULL,
        `payload` TEXT,
        `status_resposta` INT NOT NULL,
        `mensagem_resposta` VARCHAR(255),
        `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Verificar e Adicionar coluna 'event_type' na tabela webhook_logs se ela já existia antes
    $checarEvType = $db->query("SHOW COLUMNS FROM `webhook_logs` LIKE 'event_type'")->fetchAll();
    if (empty($checarEvType)) {
        $db->exec("ALTER TABLE `webhook_logs` ADD COLUMN `event_type` VARCHAR(100) DEFAULT NULL AFTER `ip`");
        echo "<p class='success'>✔ Coluna 'event_type' adicionada com sucesso à tabela webhook_logs!</p>";
    }

    try {
        $db->exec("CREATE INDEX idx_webhook_logs_criado ON `webhook_logs` (`criado_em`)");
    } catch (PDOException $e) { /* Índice já existente, ignorar */ }
    
    echo "<p class='success'>✔ Estrutura de banco de dados verificada e sincronizada!</p>";
    echo "<p>As tabelas <strong>chamados</strong> e <strong>webhook_logs</strong> estão prontas para uso.</p>";
    echo "<p class='warning'>⚠ ATENÇÃO: Delete o arquivo <strong>importar-db.php</strong> do seu servidor agora por motivos de segurança.</p>";
} catch (Exception $e) {
    echo "<p class='error'>❌ Erro ao importar banco de dados:</p>";
    echo "<pre class='error'>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<p>Verifique se as credenciais no arquivo <code>config.php</code> estão corretas e se o container do banco está ativo.</p>";
}

echo '</div>
</body>
</html>';
