<?php
/**
 * Controller do Superadmin - Central de Alertas
 * Gerencia o provisionamento, listagem e remoção de tenants/workspaces e seus admins.
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers/tenant_context.php';

// Proteção reforçada
exigirRole(['superadmin']);

$sucesso = '';
$erro = '';

// Processamento de Ações do Superadmin
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
                
                // Evita deletar a empresa padrão da migração se for ID 1 (opcional, mas seguro)
                $stmtDel = $db->prepare("DELETE FROM tenants WHERE id = :id");
                $stmtDel->execute([':id' => $tenantId]);
                
                $sucesso = "Organização removida permanentemente do banco de dados.";
            } catch (Exception $e) {
                registrarErro("Erro ao excluir tenant #$tenantId no Superadmin: " . $e->getMessage());
                $erro = "Erro interno ao remover organização: " . $e->getMessage();
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
