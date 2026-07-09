<?php
/**
 * Controller de Logs - Central de Alertas
 * Gerencia a limpeza, busca de logs de webhook e cálculo de estatísticas.
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers/tenant_context.php';

$empresaId = (int)$_SESSION['tenant_ativo_id'];

$erroLimpar = '';
$erroListar = '';

if (isset($_POST['action']) && $_POST['action'] === 'clear') {
    if (!validarTokenCSRF()) {
        $erroLimpar = 'Sessão expirada ou token de segurança inválido. Recarregue a página.';
    } elseif (isTenantReadOnlyMode()) {
        try {
            $db = obterConexao();
            $stmtAudit = $db->prepare("INSERT INTO superadmin_auditoria_logs (usuario_id, usuario_nome, usuario_email, tenant_slug, tenant_nome, acao, detalhes, ip) 
                VALUES (:usuario_id, :usuario_nome, :usuario_email, :tenant_slug, :tenant_nome, 'acao_bloqueada', 'Tentativa de limpar logs do webhook bloqueada por Somente Leitura.', :ip)");
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
        $erroLimpar = 'Ação não permitida em Modo de Inspeção (Somente Leitura).';
    } else {
        try {
            $db = obterConexao();
            $stmtDel = $db->prepare("DELETE FROM webhook_logs WHERE empresa_id = :empresa_id");
            $stmtDel->execute([':empresa_id' => $empresaId]);
            // Recarrega a URL atual de forma limpa para limpar o POST
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        } catch (Exception $e) {
            $erroLimpar = "Falha ao limpar logs: " . $e->getMessage();
        }
    }
}

// Buscar os logs de acordo com o limite configurado no sistema (isolar por empresa_id)
$limiteLogs = (int) obterConfiguracao('limite_logs', 100);

try {
    $db = obterConexao();
    $sql = "SELECT id, metodo, ip, event_type, payload, status_resposta, mensagem_resposta, criado_em 
            FROM webhook_logs 
            WHERE empresa_id = :empresa_id
            ORDER BY id DESC 
            LIMIT :limite";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
    $stmt->bindValue(':limite', $limiteLogs, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll();
} catch (Exception $e) {
    $logs = [];
    $erroListar = "Erro ao buscar logs do banco de dados: " . $e->getMessage();
}

// Calcular estatísticas básicas dos logs carregados
$total = count($logs);
$sucessos = 0;
$falhas = 0;
foreach ($logs as $log) {
    if ($log['status_resposta'] >= 200 && $log['status_resposta'] < 300) {
        $sucessos++;
    } else {
        $falhas++;
    }
}
