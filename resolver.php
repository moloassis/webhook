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

// Bloqueio de gravação em Modo Inspeção (Somente Leitura)
if (isTenantReadOnlyMode()) {
    try {
        $db = obterConexao();
        $stmtAudit = $db->prepare("INSERT INTO superadmin_auditoria_logs (usuario_id, usuario_nome, usuario_email, tenant_slug, tenant_nome, acao, detalhes, ip) 
            VALUES (:usuario_id, :usuario_nome, :usuario_email, :tenant_slug, :tenant_nome, 'acao_bloqueada', 'Tentativa de resolver chamado bloqueada por Somente Leitura.', :ip)");
        $stmtAudit->execute([
            ':usuario_id' => (int)$_SESSION['usuario_id'],
            ':usuario_nome' => $_SESSION['usuario_nome'],
            ':usuario_email' => $_SESSION['usuario_email'],
            ':tenant_slug' => $_SESSION['tenant_ativo_slug'] ?? '',
            ':tenant_nome' => $_SESSION['tenant_ativo_nome'] ?? '',
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ]);
    } catch (Exception $e) {
        registrarErro("Erro ao registrar tentativa bloqueada no log de auditoria: " . $e->getMessage());
    }

    http_response_code(403);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Ação não permitida em Modo de Inspeção (Somente Leitura).'], JSON_UNESCAPED_UNICODE);
    exit;
}

$empresaId = (int)$_SESSION['tenant_ativo_id'];

// Valida se o método é POST para segurança
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Método não permitido. Utilize POST.']);
    exit;
}

// Valida token CSRF
if (!validarTokenCSRF()) {
    http_response_code(403);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Sessão expirada ou token de segurança inválido. Recarregue a página.']);
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
    
    // Busca os detalhes do chamado antes de alterá-lo (suporta pendente ou aguardando)
    $stmtSelect = $db->prepare("SELECT nome_cliente, tipo, status FROM chamados WHERE id = :id AND empresa_id = :empresa_id AND status IN ('pendente', 'aguardando')");
    $stmtSelect->execute([':id' => $id, ':empresa_id' => $empresaId]);
    $chamado = $stmtSelect->fetch();
    
    if (!$chamado) {
        http_response_code(404);
        echo json_encode(['sucesso' => false, 'mensagem' => 'Chamado não encontrado ou já resolvido.']);
        exit;
    }
    
    // Lê o status de destino desejado (default: resolvido)
    $status = isset($_GET['status']) ? trim($_GET['status']) : 'resolvido';
    if (!in_array($status, ['aguardando', 'resolvido'])) {
        $status = 'resolvido';
    }
    
    // Regra de segurança: se o chamado já está 'aguardando', apenas admins/superadmins podem dispensar/resolver manualmente
    $usuarioRole = $_SESSION['usuario_role'] ?? 'user';
    if ($chamado['status'] === 'aguardando' && $status === 'resolvido' && $usuarioRole !== 'admin' && $usuarioRole !== 'superadmin') {
        http_response_code(403);
        echo json_encode(['sucesso' => false, 'mensagem' => 'Apenas administradores podem dispensar chamados em espera.']);
        exit;
    }
    
    // Atualiza o chamado no banco filtrando pelo empresa_id do tenant logado
    $stmt = $db->prepare("UPDATE chamados SET status = :status WHERE id = :id AND empresa_id = :empresa_id");
    $stmt->execute([
        ':status' => $status,
        ':id' => $id,
        ':empresa_id' => $empresaId
    ]);
    
    // Só envia o alerta push de finalização aos demais atendentes se for resolvido de fato
    if ($status === 'resolvido') {
        $usuarioNome = $_SESSION['usuario_nome'] ?? 'Operador';
        $clienteNome = $chamado['nome_cliente'] ?: 'Desconhecido';
        $horaDispensada = date('H:i');
        
        $titulo = "🚪 Chamado Dispensado/Atendido";
        $mensagemAlert = "O usuário {$usuarioNome} dispensou o alerta de \"{$clienteNome}\" às {$horaDispensada}.";
        
        enviarPushNotificacaoCustom($titulo, $mensagemAlert, './', $empresaId);
    }
    
    echo json_encode([
        'sucesso' => true, 
        'mensagem' => $status === 'aguardando' ? 'Chamado minimizado (aguardando suporte).' : 'Chamado finalizado e removido da fila.'
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    registrarErro("Erro ao dispensar chamado #{$id} para empresa #{$empresaId}: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'sucesso' => false, 
        'mensagem' => 'Erro interno ao dispensar o chamado no banco.'
    ], JSON_UNESCAPED_UNICODE);
}
