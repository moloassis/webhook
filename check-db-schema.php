<?php
/**
 * Script temporário de diagnóstico de banco de dados.
 */
header('Content-Type: text/plain; charset=UTF-8');
require_once __DIR__ . '/db.php';

try {
    $db = obterConexao();
    echo "Status da Conexão: OK\n\n";
    
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tabelas no banco de dados:\n";
    print_r($tables);
    echo "\n";
    
    if (in_array('pwa_subscriptions', $tables)) {
        echo "Estrutura da tabela 'pwa_subscriptions':\n";
        $columns = $db->query("SHOW COLUMNS FROM pwa_subscriptions")->fetchAll(PDO::FETCH_ASSOC);
        print_r($columns);
    } else {
        echo "AVISO: A tabela 'pwa_subscriptions' NÃO existe no banco de dados!\n";
    }
} catch (Exception $e) {
    echo "ERRO FATAL: " . $e->getMessage() . "\n";
}
