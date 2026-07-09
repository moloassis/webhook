<?php
/**
 * Controller do Superadmin - Central de Alertas
 * Gerencia o provisionamento, listagem e remoção de tenants/workspaces e seus admins.
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers/tenant_context.php';

// Proteção reforçada
exigirRole(['superadmin']);

// Exportação de Logs de Auditoria para CSV
if (isset($_GET['export']) && $_GET['export'] === 'audit') {
    try {
        $db = obterConexao();
        $stmt = $db->query("SELECT criado_em, usuario_nome, usuario_email, tenant_nome, tenant_slug, acao, detalhes, ip FROM superadmin_auditoria_logs ORDER BY id DESC");
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=auditoria_inspecoes_' . date('Y-m-d_H-i-s') . '.csv');
        
        $output = fopen('php://output', 'w');
        // Injeta o BOM para suporte correto a UTF-8 no Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Cabeçalhos do CSV
        fputcsv($output, ['Data/Hora', 'Superadmin', 'E-mail', 'Organização Inspecionada', 'Slug', 'Ação', 'Detalhes', 'IP']);
        
        foreach ($logs as $row) {
            fputcsv($output, [
                $row['criado_em'],
                $row['usuario_nome'],
                $row['usuario_email'],
                $row['tenant_nome'],
                $row['tenant_slug'],
                $row['acao'] === 'inspecionar_inicio' ? 'INSPEÇÃO' : ($row['acao'] === 'acao_bloqueada' ? 'BLOQUEIO' : $row['acao']),
                $row['detalhes'],
                $row['ip']
            ]);
        }
        fclose($output);
        exit;
    } catch (Exception $e) {
        registrarErro("Erro ao exportar logs de auditoria para CSV: " . $e->getMessage());
        die("Erro interno ao exportar logs.");
    }
}

$sucesso = '';
$erro = '';

// Processamento de Ações do Superadmin
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF()) {
        $erro = "Sessão expirada ou token de segurança inválido. Recarregue a página.";
    } else {
        $action = isset($_POST['action']) ? $_POST['action'] : '';

        // AÇÃO: Criar Nova Empresa (Tenant) e seu Admin Inicial
        if ($action === 'create_tenant') {
        $tNome = isset($_POST['tenant_nome']) ? trim($_POST['tenant_nome']) : '';
        $tSlug = isset($_POST['tenant_slug']) ? trim($_POST['tenant_slug']) : '';
        $uNome = isset($_POST['admin_nome']) ? trim($_POST['admin_nome']) : '';
        $uEmail = isset($_POST['admin_email']) ? trim($_POST['admin_email']) : '';
        $uSenha = isset($_POST['admin_senha']) ? $_POST['admin_senha'] : '';

        // Validações básicas
        $tSlug = preg_replace('/[^a-z0-9_-]/', '', strtolower($tSlug)); // Sanitiza slug

        if ($tNome && $tSlug && $uNome && $uEmail && $uSenha) {
            $erroSenha = validarForcaSenha($uSenha);
            if ($erroSenha) {
                $erro = $erroSenha;
            } else {
                try {
                $db = obterConexao();
                
                // Verifica se slug já existe
                $stmtCheckSlug = $db->prepare("SELECT COUNT(*) FROM tenants WHERE slug = :slug");
                $stmtCheckSlug->execute([':slug' => $tSlug]);
                
                // Verifica se e-mail de admin já existe
                $stmtCheckEmail = $db->prepare("SELECT COUNT(*) FROM usuarios WHERE email = :email");
                $stmtCheckEmail->execute([':email' => $uEmail]);

                if ($stmtCheckSlug->fetchColumn() > 0) {
                    $erro = "O slug '$tSlug' já está em uso por outra empresa.";
                } elseif ($stmtCheckEmail->fetchColumn() > 0) {
                    $erro = "O endereço de e-mail '$uEmail' já está cadastrado no sistema.";
                } else {
                    $db->beginTransaction();

                    // 1. Inserir o tenant
                    $webhookToken = bin2hex(random_bytes(16)); // Token único de 32 chars
                    $stmtTenant = $db->prepare("INSERT INTO tenants (nome, slug, webhook_token, cor_primaria, cor_secundaria, modo_visualizacao) 
                        VALUES (:nome, :slug, :token, '#1e90ff', '#ffa502', 'dark')");
                    $stmtTenant->execute([
                        ':nome' => $tNome,
                        ':slug' => $tSlug,
                        ':token' => $webhookToken
                    ]);
                    $newTenantId = $db->lastInsertId();

                    // 2. Inserir o administrador
                    $uSenhaHash = password_hash($uSenha, PASSWORD_DEFAULT);
                    $stmtAdmin = $db->prepare("INSERT INTO usuarios (empresa_id, nome, email, senha_hash, role) 
                        VALUES (:empresa_id, :nome, :email, :senha_hash, 'admin')");
                    $stmtAdmin->execute([
                        ':empresa_id' => $newTenantId,
                        ':nome' => $uNome,
                        ':email' => $uEmail,
                        ':senha_hash' => $uSenhaHash
                    ]);

                    $db->commit();
                    $sucesso = "Organização '$tNome' criada com sucesso! Token gerado: $webhookToken";
                }
            } catch (Exception $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    registrarErro("Erro ao cadastrar tenant/admin no Superadmin: " . $e->getMessage());
                    $erro = "Erro interno ao processar cadastro: " . $e->getMessage();
                }
            }
        } else {
            $erro = "Preencha todos os campos do formulário.";
        }
    }

    // AÇÃO: Excluir Empresa (Cascade exclui usuários/logs/chamados)
    elseif ($action === 'delete_tenant') {
        $tenantId = isset($_POST['tenant_id']) ? (int)$_POST['tenant_id'] : 0;
        if ($tenantId > 0) {
            try {
                $db = obterConexao();
                
                $stmtDel = $db->prepare("DELETE FROM tenants WHERE id = :id");
                $stmtDel->execute([':id' => $tenantId]);
                
                $sucesso = "Organização removida permanentemente do banco de dados.";
            } catch (Exception $e) {
                registrarErro("Erro ao excluir tenant #$tenantId no Superadmin: " . $e->getMessage());
                $erro = "Erro interno ao remover organização: " . $e->getMessage();
            }
        }
    }

    // AÇÃO: Editar Organização
    elseif ($action === 'edit_tenant') {
        $tenantId = isset($_POST['tenant_id']) ? (int)$_POST['tenant_id'] : 0;
        $tNome = isset($_POST['tenant_nome']) ? trim($_POST['tenant_nome']) : '';
        $corPrimaria = isset($_POST['cor_primaria']) ? trim($_POST['cor_primaria']) : '#2ed573';
        $corSecundaria = isset($_POST['cor_secundaria']) ? trim($_POST['cor_secundaria']) : '#70a1ff';
        $modoVisualizacao = isset($_POST['modo_visualizacao']) ? $_POST['modo_visualizacao'] : 'dark';

        if ($tenantId > 0 && $tNome) {
            try {
                $db = obterConexao();
                $stmtEdit = $db->prepare("UPDATE tenants SET nome = :nome, cor_primaria = :cor_primaria, cor_secundaria = :cor_secundaria, modo_visualizacao = :modo_visualizacao WHERE id = :id");
                $stmtEdit->execute([
                    ':id' => $tenantId,
                    ':nome' => $tNome,
                    ':cor_primaria' => $corPrimaria,
                    ':cor_secundaria' => $corSecundaria,
                    ':modo_visualizacao' => $modoVisualizacao
                ]);
                $sucesso = "Configurações da organização '$tNome' atualizadas com sucesso!";
            } catch (Exception $e) {
                registrarErro("Erro ao editar tenant #$tenantId no Superadmin: " . $e->getMessage());
                $erro = "Erro interno ao atualizar configurações: " . $e->getMessage();
            }
        } else {
            $erro = "Preencha todos os campos obrigatórios.";
        }
    }

    // AÇÃO: Redefinir Senha de Usuário
    elseif ($action === 'reset_password') {
        $userId = isset($_POST['usuario_id']) ? (int)$_POST['usuario_id'] : 0;
        $novaSenha = isset($_POST['nova_senha']) ? $_POST['nova_senha'] : '';

        $erroSenha = validarForcaSenha($novaSenha);
        if ($erroSenha) {
            $erro = $erroSenha;
        } elseif ($userId > 0) {
            try {
                $db = obterConexao();
                // Verifica se o usuário existe e não é superadmin
                $stmtCheckUser = $db->prepare("SELECT nome, role FROM usuarios WHERE id = :id");
                $stmtCheckUser->execute([':id' => $userId]);
                $userToReset = $stmtCheckUser->fetch();

                if ($userToReset) {
                    $senhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);
                    $stmtReset = $db->prepare("UPDATE usuarios SET senha_hash = :hash WHERE id = :id");
                    $stmtReset->execute([
                        ':id' => $userId,
                        ':hash' => $senhaHash
                    ]);
                    $sucesso = "Senha do usuário '{$userToReset['nome']}' alterada com sucesso!";
                } else {
                    $erro = "Usuário não encontrado.";
                }
            } catch (Exception $e) {
                registrarErro("Erro ao resetar senha do usuário #$userId: " . $e->getMessage());
                $erro = "Erro interno ao resetar senha: " . $e->getMessage();
            }
        } else {
            $erro = "Dados inválidos para redefinição.";
        }
    }

    // AÇÃO: Limpar Log do Sistema
    elseif ($action === 'clear_log') {
        $logType = isset($_POST['log_type']) ? $_POST['log_type'] : '';
        $filePath = '';
        if ($logType === 'sistema') {
            $filePath = __DIR__ . '/../erros_sistema.log';
        } elseif ($logType === 'php') {
            $filePath = __DIR__ . '/../erros_php.log';
        }

        if ($filePath) {
            try {
                file_put_contents($filePath, '');
                $sucesso = "Arquivo de log limpo com sucesso!";
            } catch (Exception $e) {
                $erro = "Erro ao limpar arquivo de log: " . $e->getMessage();
            }
        } else {
            $erro = "Tipo de log inválido.";
        }
    }
    }
}

// Buscar todas as empresas cadastradas e estatísticas básicas
try {
    $db = obterConexao();
    $sql = "SELECT t.*, 
            (SELECT COUNT(*) FROM usuarios WHERE empresa_id = t.id) as user_count,
            (SELECT COUNT(*) FROM chamados WHERE empresa_id = t.id) as chamado_count,
            (SELECT COUNT(*) FROM webhook_logs WHERE empresa_id = t.id) as log_count
            FROM tenants t 
            ORDER BY t.nome ASC";
    $tenants = $db->query($sql)->fetchAll();
} catch (Exception $e) {
    $tenants = [];
    $erro = "Erro ao carregar organizações do banco de dados: " . $e->getMessage();
}

// Inicializar variáveis de métricas e gráficos
$metricas = [
    'tenants' => count($tenants),
    'usuarios' => 0,
    'chamados' => 0,
    'webhook_logs' => 0
];
$grafico_webhooks = [];
$grafico_status = ['sucesso' => 0, 'falha' => 0];
$usuarios_por_tenant = [];

try {
    if ($db) {
        // 1. Contagens gerais
        $metricas['usuarios'] = (int)$db->query("SELECT COUNT(*) FROM usuarios WHERE role != 'superadmin'")->fetchColumn();
        $metricas['chamados'] = (int)$db->query("SELECT COUNT(*) FROM chamados")->fetchColumn();
        $metricas['webhook_logs'] = (int)$db->query("SELECT COUNT(*) FROM webhook_logs")->fetchColumn();

        // 2. Todos os usuários (para listar por tenant)
        $allUsers = $db->query("SELECT id, empresa_id, nome, email, role, criado_em FROM usuarios ORDER BY nome ASC")->fetchAll();
        foreach ($allUsers as $u) {
            $empId = $u['empresa_id'] ? (int)$u['empresa_id'] : 0;
            $usuarios_por_tenant[$empId][] = $u;
        }

        // 3. Gráfico: Volume de webhooks nos últimos 7 dias
        $stmtChart = $db->query("SELECT DATE(criado_em) as data, COUNT(*) as total 
                                 FROM webhook_logs 
                                 WHERE criado_em >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
                                 GROUP BY DATE(criado_em) 
                                 ORDER BY data ASC");
        $grafico_webhooks = $stmtChart->fetchAll();

        // 4. Gráfico: Status breakdown
        $stmtStatus = $db->query("SELECT 
                                    SUM(CASE WHEN status_resposta >= 200 AND status_resposta < 300 THEN 1 ELSE 0 END) as sucessos,
                                    SUM(CASE WHEN status_resposta < 200 OR status_resposta >= 300 THEN 1 ELSE 0 END) as falhas 
                                 FROM webhook_logs");
        $statusData = $stmtStatus->fetch();
        if ($statusData) {
            $grafico_status['sucesso'] = (int)$statusData['sucessos'];
            $grafico_status['falha'] = (int)$statusData['falhas'];
        }
    }
} catch (Exception $e) {
    registrarErro("Erro ao buscar métricas do Superadmin: " . $e->getMessage());
}
