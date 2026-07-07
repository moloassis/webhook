<?php
/**
 * Endpoint de Histórico - Retorna os últimos chamados registrados no banco.
 * Utilizado para popular o painel visual do atendente no momento do carregamento da página.
 */

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/tenant_context.php';

// Validar se o usuário está autenticado na sessão
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode([
        'erro' => true,
        'mensagem' => 'Não autorizado.'
    ]);
    exit;
}

$empresaId = (int)$_SESSION['tenant_ativo_id'];

try {
    $db = obterConexao();
    
    // Busca os chamados ativos (pendentes ou aguardando) vinculados a este tenant específico
    $sql = "SELECT id, nome_cliente, tipo, mensagem, session_id, status, criado_em 
            FROM chamados 
            WHERE status IN ('pendente', 'aguardando') AND empresa_id = :empresa_id 
            ORDER BY id DESC LIMIT 50";
            
    $stmt = $db->prepare($sql);
    $stmt->execute([':empresa_id' => $empresaId]);
    $historico = $stmt->fetchAll();

    echo json_encode($historico);
} catch (Exception $e) {
    registrarErro("Erro ao buscar histórico de chamados para empresa #{$empresaId}: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'erro' => true,
        'mensagem' => 'Não foi possível recuperar o histórico de chamados.'
    ]);
}
