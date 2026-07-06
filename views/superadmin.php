<?php
/**
 * View do Painel do Superadmin - Gestão da Plataforma SaaS.
 * Restrito exclusivamente a usuários com role 'superadmin'.
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
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Superadmin - Central de Alertas</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/index.css">
    <style>
        :root {
            --color-default: #70a1ff;
            --color-lead: #2ed573;
            --color-atendimento: #ff4757;
        }
        body {
            background: radial-gradient(circle at top, #140f36 0%, #08080c 100%);
            font-family: 'Outfit', sans-serif;
            color: var(--text-primary);
            min-height: 100vh;
            padding: 2rem;
            box-sizing: border-box;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }
        header.sa-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 1.5rem;
        }
        .panel-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }
        @media (max-width: 900px) {
            .panel-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Header -->
    <header class="sa-header">
        <div>
            <span style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 2px; color: var(--color-default); font-weight: 700;">ÁREA RESTRITA</span>
            <h1 style="margin-top: 5px; font-size: 1.8rem; background: linear-gradient(135deg, #fff 0%, #a4b0be 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">SaaS Superadmin Panel</h1>
        </div>
        <div style="display: flex; gap: 0.8rem; align-items: center;">
            <span class="badge" style="border-color: rgba(255,255,255,0.15); color: var(--text-secondary);">Logado como Superadmin</span>
            <a href="logout" class="btn-view-logs" style="border-color: rgba(255, 71, 87, 0.25); color: #ff4757;" onmouseover="this.style.background='rgba(255,71,87,0.1)'" onmouseout="this.style.background='none'">
                🚪 Sair da Plataforma
            </a>
        </div>
    </header>

    <!-- Feedbacks -->
    <?php if ($sucesso): ?>
        <div style="background: rgba(46, 213, 115, 0.15); border: 1px solid #2ed573; padding: 1rem; border-radius: 10px; color: #2ed573; font-weight: 500; font-size: 0.9rem;">
            ✔ <?= htmlspecialchars($sucesso) ?>
        </div>
    <?php endif; ?>
    <?php if ($erro): ?>
        <div style="background: rgba(255, 71, 87, 0.15); border: 1px solid #ff4757; padding: 1rem; border-radius: 10px; color: #ff4757; font-weight: 500; font-size: 0.9rem;">
            ❌ <?= htmlspecialchars($erro) ?>
        </div>
    <?php endif; ?>

    <!-- Main Content Grid -->
    <div class="panel-grid">
        
        <!-- COLUNA ESQUERDA: Lista de Empresas (Tenants) -->
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
            <div class="panel-box" style="background: var(--panel-bg); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; backdrop-filter: blur(12px);">
                <h2 class="panel-title" style="margin-top: 0; font-size: 1.2rem; color: var(--text-primary); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 8px;">
                    🏢 Organizações Ativas (Workspaces)
                </h2>

                <div style="overflow-x: auto; width: 100%;">
                    <table class="webhook-table" style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.88rem;">
                        <thead>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <th style="padding: 0.8rem; color: var(--text-secondary); font-weight: 600;">Workspace / Nome</th>
                                <th style="padding: 0.8rem; color: var(--text-secondary); font-weight: 600;">Slug / Rota</th>
                                <th style="padding: 0.8rem; color: var(--text-secondary); font-weight: 600;">Token Webhook</th>
                                <th style="padding: 0.8rem; color: var(--text-secondary); font-weight: 600; text-align: center;">Membros</th>
                                <th style="padding: 0.8rem; color: var(--text-secondary); font-weight: 600; text-align: center;">Chamados</th>
                                <th style="padding: 0.8rem; color: var(--text-secondary); font-weight: 600; text-align: center;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($tenants)): ?>
                                <tr>
                                    <td colspan="6" style="padding: 2rem; text-align: center; color: var(--text-secondary);">Nenhuma empresa cadastrada no momento.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($tenants as $t): ?>
                                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.03); transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.01)'" onmouseout="this.style.background='none'">
                                        <td style="padding: 0.8rem; font-weight: 600; color: var(--text-primary);">
                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                <div style="width: 8px; height: 8px; border-radius: 50%; background: <?= htmlspecialchars($t['cor_primaria']) ?>; box-shadow: 0 0 8px <?= htmlspecialchars($t['cor_primaria']) ?>;"></div>
                                                <?= htmlspecialchars($t['nome']) ?>
                                            </div>
                                        </td>
                                        <td style="padding: 0.8rem; font-family: monospace; color: var(--color-default);">
                                            /t/<?= htmlspecialchars($t['slug']) ?>/
                                        </td>
                                        <td style="padding: 0.8rem;">
                                            <span style="font-family: monospace; font-size: 0.8rem; background: rgba(255,255,255,0.03); padding: 0.2rem 0.5rem; border-radius: 6px; border: 1px solid var(--border-color); color: var(--text-secondary);" title="Token Completo: <?= htmlspecialchars($t['webhook_token']) ?>">
                                                <?= htmlspecialchars(substr($t['webhook_token'], 0, 8)) ?>...
                                            </span>
                                        </td>
                                        <td style="padding: 0.8rem; text-align: center; font-weight: 500;"><?= $t['user_count'] ?></td>
                                        <td style="padding: 0.8rem; text-align: center; color: var(--color-lead); font-weight: 500;"><?= $t['chamado_count'] ?></td>
                                        <td style="padding: 0.8rem; text-align: center;">
                                            <div style="display: flex; justify-content: center; gap: 6px;">
                                                <a href="t/<?= htmlspecialchars($t['slug']) ?>/dashboard" target="_blank" class="btn-inspect" style="text-decoration: none; font-size: 0.72rem; padding: 0.25rem 0.5rem; border-radius: 6px;">Inspecionar ➔</a>
                                                
                                                <form action="" method="POST" style="margin: 0;" onsubmit="return confirm('ATENÇÃO: Isso deletará permanentemente a empresa <?= htmlspecialchars($t['nome']) ?>, incluindo TODOS os usuários, configurações, históricos de logs e chamados ativos. Deseja prosseguir?')">
                                                    <input type="hidden" name="action" value="delete_tenant">
                                                    <input type="hidden" name="tenant_id" value="<?= $t['id'] ?>">
                                                    <button type="submit" class="btn-inspect" style="font-size: 0.72rem; padding: 0.25rem 0.5rem; border-radius: 6px; background: rgba(255, 71, 87, 0.12); border-color: rgba(255, 71, 87, 0.25); color: var(--color-atendimento);">Excluir</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- COLUNA DIREITA: Criar Nova Empresa (Tenant) -->
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
            <div class="panel-box" style="background: var(--panel-bg); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; backdrop-filter: blur(12px);">
                <h2 class="panel-title" style="margin-top: 0; font-size: 1.2rem; color: var(--text-primary); margin-bottom: 1.2rem; display: flex; align-items: center; gap: 8px;">
                    🏢 Registrar Organização
                </h2>
                
                <form action="" method="POST" style="display: flex; flex-direction: column; gap: 1.2rem;">
                    <input type="hidden" name="action" value="create_tenant">

                    <span class="label-text" style="font-weight: 600; display: block; border-bottom: 1px solid var(--border-color); padding-bottom: 5px; margin-bottom: 5px; color: var(--color-default);">Dados da Empresa</span>
                    
                    <div class="form-group">
                        <label class="label-text" for="tenant_nome" style="font-size: 0.85rem;">Nome da Organização</label>
                        <input type="text" id="tenant_nome" name="tenant_nome" required placeholder="Ex: Helena CRM Corp" class="form-control" style="background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); border-radius: 8px; padding: 0.6rem; color: var(--text-primary); outline: none;">
                    </div>

                    <div class="form-group">
                        <label class="label-text" for="tenant_slug" style="font-size: 0.85rem;">Slug Rota (Subpasta)</label>
                        <input type="text" id="tenant_slug" name="tenant_slug" required placeholder="Ex: helenacrm" class="form-control" style="background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); border-radius: 8px; padding: 0.6rem; color: var(--text-primary); outline: none;" oninput="this.value = this.value.toLowerCase().replace(/[^a-z0-9_-]/g, '')">
                        <span class="label-text" style="font-size: 0.7rem; color: var(--text-secondary);">
                            Rota única na URL: /t/<strong>slug</strong>/dashboard
                        </span>
                    </div>

                    <span class="label-text" style="font-weight: 600; display: block; border-bottom: 1px solid var(--border-color); padding-bottom: 5px; margin-bottom: 5px; margin-top: 10px; color: var(--color-default);">Administrador Principal</span>

                    <div class="form-group">
                        <label class="label-text" for="admin_nome" style="font-size: 0.85rem;">Nome do Admin</label>
                        <input type="text" id="admin_nome" name="admin_nome" required placeholder="Ex: Carlos Albuquerque" class="form-control" style="background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); border-radius: 8px; padding: 0.6rem; color: var(--text-primary); outline: none;">
                    </div>

                    <div class="form-group">
                        <label class="label-text" for="admin_email" style="font-size: 0.85rem;">E-mail do Admin</label>
                        <input type="email" id="admin_email" name="admin_email" required placeholder="Ex: admin@helenacrm.com" class="form-control" style="background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); border-radius: 8px; padding: 0.6rem; color: var(--text-primary); outline: none;">
                    </div>

                    <div class="form-group">
                        <label class="label-text" for="admin_senha" style="font-size: 0.85rem;">Senha Inicial</label>
                        <input type="password" id="admin_senha" name="admin_senha" required placeholder="Min 6 caracteres" class="form-control" style="background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); border-radius: 8px; padding: 0.6rem; color: var(--text-primary); outline: none;">
                    </div>

                    <button type="submit" class="btn-premium" style="width: 100%; margin: 0; padding: 0.75rem; font-weight: 600; background: linear-gradient(135deg, #1e90ff, #00bfff);">
                        Provisionar Workspace 🚀
                    </button>
                </form>
            </div>
        </div>

    </div>
</div>

</body>
</html>
