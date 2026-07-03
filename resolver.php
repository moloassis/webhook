<?php
/**
 * Resolver Chamado - Atualiza o status do chamado para 'resolvido' no banco de dados.
 */

require_once __DIR__ . '/db.php';
header("Content-Type: application/json; charset=UTF-8");

// Valida se o método é POST para segurança
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Método não permitido. Utilize POST.']);
    exit;
}

// Obtém o ID do chamado
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'mensagem' => 'ID inválido fornecido.']);
    exit;
}

try {
    $db = obterConexao();
    
    // Atualiza o chamado no banco para 'resolvido' para que não reapareça
    $stmt = $db->prepare("UPDATE chamados SET status = 'resolvido' WHERE id = :id");
    $stmt->execute([':id' => $id]);
    
    echo json_encode([
        'sucesso' => true, 
        'mensagem' => 'Chamado dispensado e retirado da fila.'
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    registrarErro("Erro ao dispensar chamado #{$id}: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'sucesso' => false, 
        'mensagem' => 'Erro interno ao dispensar o chamado no banco.'
    ], JSON_UNESCAPED_UNICODE);
}
