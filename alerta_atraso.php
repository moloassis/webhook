<?php
/**
 * API Endpoint para registrar / disparar notificações de atraso de chamado.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/tenant_context.php';

header("Content-Type: application/json; charset=UTF-8");

// Valida sessão do usuário logado
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Não autenticado.']);
    exit;
}

$empresaId = (int)$_SESSION['tenant_ativo_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Método não permitido. Utilize POST.']);
    exit;
}

// Obtém o ID do chamado
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'mensagem' => 'ID do chamado inválido.']);
    exit;
}

try {
    $db = obterConexao();
    
    // Busca informações do chamado para confirmar se pertence à empresa e está ativo
    $stmt = $db->prepare("SELECT nome_cliente, criado_em FROM chamados WHERE id = :id AND empresa_id = :empresa_id AND status IN ('pendente', 'aguardando')");
    $stmt->execute([':id' => $id, ':empresa_id' => $empresaId]);
    $chamado = $stmt->fetch();
    
    if ($chamado) {
        $criadoTimestamp = strtotime($chamado['criado_em']);
        $agoraTimestamp = time();
        $diffSeconds = $agoraTimestamp - $criadoTimestamp;
        
        $minutos = 0;
        if ($diffSeconds > 0) {
            $minutos = (int) floor($diffSeconds / 60);
        }
        
        $cliente = $chamado['nome_cliente'] ?: 'Desconhecido';
        
        $titulo = "⚠️ Alerta de Atraso no Atendimento";
        $mensagem = "O cliente \"{$cliente}\" está há {$minutos} minutos aguardando atendimento!";
        
        // Envia notificação para todos os atendentes/admins da empresa
        enviarPushNotificacaoCustom($titulo, $mensagem, './', $empresaId);
        
        echo json_encode([
            'sucesso' => true, 
            'mensagem' => 'Alerta de atraso enviado com sucesso via push.'
        ]);
    } else {
        echo json_encode([
            'sucesso' => false, 
            'mensagem' => 'Chamado não encontrado, já atendido ou pertence a outro tenant.'
        ]);
    }
} catch (Exception $e) {
    registrarErro("Erro ao disparar alerta de atraso para chamado #{$id}: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro interno ao processar o alerta push.']);
}
