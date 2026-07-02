<?php
/**
 * Endpoint de Histórico - Retorna os últimos chamados registrados no banco.
 * Utilizado para popular o painel visual do atendente no momento do carregamento da página.
 */

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

require_once __DIR__ . '/db.php';

try {
    $db = obterConexao();
    
    // Busca apenas os chamados ainda 'pendentes' (que não foram notificados/resolvidos)
    $sql = "SELECT id, nome_cliente, tipo, mensagem, status, criado_em FROM chamados WHERE status = 'pendente' ORDER BY id DESC LIMIT 50";
    $stmt = $db->query($sql);
    $historico = $stmt->fetchAll();

    echo json_encode($historico);
} catch (Exception $e) {
    registrarErro("Erro ao buscar histórico de chamados: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'erro' => true,
        'mensagem' => 'Não foi possível recuperar o histórico de chamados.'
    ]);
}
