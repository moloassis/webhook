<?php
/**
 * Resolver Chamado - Atualiza o status do chamado para 'resolvido' no banco de dados.
 * Garante que apenas chamados pertencentes à empresa do atendente sejam atualizados.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/tenant_context.php';

header("Content-Type: application/json; charset=UTF-8");

// Valida se o usuário está autenticado
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Usuário não autenticado.']);
    exit;
}

$empresaId = (int)$_SESSION['tenant_ativo_id'];

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
    
    // Busca os detalhes do chamado antes de dispensá-lo para a notificação
    $stmtSelect = $db->prepare("SELECT nome_cliente, tipo FROM chamados WHERE id = :id AND empresa_id = :empresa_id AND status = 'pendente'");
    $stmtSelect->execute([':id' => $id, ':empresa_id' => $empresaId]);
    $chamado = $stmtSelect->fetch();
    
    if (!$chamado) {
        http_response_code(404);
        echo json_encode(['sucesso' => false, 'mensagem' => 'Chamado não encontrado ou já dispensado.']);
        exit;
    }
    
    // Atualiza o chamado no banco para 'resolvido' filtrando pelo empresa_id do tenant logado
    $stmt = $db->prepare("UPDATE chamados SET status = 'resolvido' WHERE id = :id AND empresa_id = :empresa_id");
    $stmt->execute([
        ':id' => $id,
        ':empresa_id' => $empresaId
    ]);
    
    // Envia o alerta push para os administradores da empresa informando quem dispensou
    $usuarioNome = $_SESSION['usuario_nome'] ?? 'Operador';
    $clienteNome = $chamado['nome_cliente'] ?: 'Desconhecido';
    $horaDispensada = date('H:i');
    
    $titulo = "🚪 Chamado Dispensado/Atendido";
    $mensagemAlert = "O usuário {$usuarioNome} dispensou o alerta de \"{$clienteNome}\" às {$horaDispensada}.";
    
    enviarPushNotificacaoCustom($titulo, $mensagemAlert, './', $empresaId);
    
    echo json_encode([
        'sucesso' => true, 
        'mensagem' => 'Chamado dispensado e retirado da fila.'
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    registrarErro("Erro ao dispensar chamado #{$id} para empresa #{$empresaId}: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'sucesso' => false, 
        'mensagem' => 'Erro interno ao dispensar o chamado no banco.'
    ], JSON_UNESCAPED_UNICODE);
}
