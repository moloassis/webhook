<?php
/**
 * Script de Limpeza Automática de Logs - Made in AI
 * Deleta registros de webhook_logs com mais de 15 dias de existência.
 */

require_once __DIR__ . '/../db.php';

// Apenas permite execução por CLI ou via web com token válido
if (php_sapi_name() !== 'cli') {
    $token = $_GET['token'] ?? '';
    if (empty($token) || $token !== JWT_SECRET) {
        http_response_code(403);
        echo "Acesso negado. Token inválido.";
        exit;
    }
}

try {
    $db = obterConexao();
    $stmt = $db->prepare("DELETE FROM webhook_logs WHERE criado_em < DATE_SUB(NOW(), INTERVAL 15 DAY)");
    $stmt->execute();
    $deletedRows = $stmt->rowCount();
    echo "Limpeza de logs executada com sucesso. Registros deletados: {$deletedRows}\n";
} catch (Exception $e) {
    registrarErro("Erro ao executar limpeza automática de logs: " . $e->getMessage());
    echo "Erro ao executar limpeza de logs: " . $e->getMessage() . "\n";
}
