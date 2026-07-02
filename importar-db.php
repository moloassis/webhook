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
    
    // 2. Lê o arquivo schema.sql
    $caminhoSql = __DIR__ . '/schema.sql';
    if (!file_exists($caminhoSql)) {
        throw new Exception("O arquivo schema.sql não foi localizado em: " . $caminhoSql);
    }
    
    $sql = file_get_contents($caminhoSql);
    
    // 3. Executa a query completa
    $db->exec($sql);
    
    echo "<p class='success'>✔ Banco de dados importado com sucesso!</p>";
    echo "<p>As seguintes tabelas foram configuradas no banco <code>" . htmlspecialchars(DB_NAME) . "</code>:</p>";
    echo "<ul>
            <li><strong>chamados</strong> (Tabela de alertas ativos)</li>
            <li><strong>webhook_logs</strong> (Log histórico de webhooks)</li>
          </ul>";
    echo "<p class='warning'>⚠ ATENÇÃO: Delete o arquivo <strong>importar-db.php</strong> do seu servidor agora por motivos de segurança.</p>";
} catch (Exception $e) {
    echo "<p class='error'>❌ Erro ao importar banco de dados:</p>";
    echo "<pre class='error'>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<p>Verifique se as credenciais no arquivo <code>config.php</code> estão corretas e se o container do banco está ativo.</p>";
}

echo '</div>
</body>
</html>';
