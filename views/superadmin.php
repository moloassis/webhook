<?php
/**
 * View do Painel do Superadmin - Gestão da Plataforma SaaS.
 * Restrito exclusivamente a usuários com role 'superadmin'.
 */

require_once __DIR__ . '/../controllers/superadmin_controller.php';

// Detecta a sub-rota atual para navegação
$subRoute = isset($subRoute) ? trim($subRoute, '/') : '';
if ($subRoute === '' || $subRoute === 'dashboard') {
    $subRoute = 'dashboard';
}

// Carrega os logs apenas se estiver na aba de logs
$sistemaLog = '';
$phpLog = '';
$auditoriaLogs = [];
if ($subRoute === 'logs') {
    $sistemaLogPath = __DIR__ . '/../erros_sistema.log';
    $phpLogPath = __DIR__ . '/../erros_php.log';
    $sistemaLog = file_exists($sistemaLogPath) ? trim(file_get_contents($sistemaLogPath)) : '';
    $phpLog = file_exists($phpLogPath) ? trim(file_get_contents($phpLogPath)) : '';
    
    try {
        $db = obterConexao();
        $auditoriaLogs = $db->query("SELECT * FROM superadmin_auditoria_logs ORDER BY id DESC LIMIT 500")->fetchAll();
    } catch (Exception $e) {
        registrarErro("Erro ao buscar logs de auditoria no Superadmin: " . $e->getMessage());
    }
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
    <link rel="stylesheet" href="<?= $baseUrl ?>assets/css/index.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>assets/css/superadmin.css">
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
            <a href="<?= $baseUrl ?>logout" class="btn-view-logs" style="border-color: rgba(255, 71, 87, 0.25); color: #ff4757; text-decoration: none;" onmouseover="this.style.background='rgba(255,71,87,0.1)'" onmouseout="this.style.background='none'">
                🚪 Sair da Plataforma
            </a>
        </div>
    </header>

    <!-- Navegação por Abas -->
    <nav class="sa-nav">
        <a href="<?= $baseUrl ?>superadmin" class="nav-item <?= ($subRoute === 'dashboard') ? 'active' : '' ?>">📊 Dashboard</a>
        <a href="<?= $baseUrl ?>superadmin/organizations" class="nav-item <?= ($subRoute === 'organizations') ? 'active' : '' ?>">🏢 Gerenciar Organizações</a>
        <a href="<?= $baseUrl ?>superadmin/register" class="nav-item <?= ($subRoute === 'register') ? 'active' : '' ?>">➕ Registrar Nova Organização</a>
        <a href="<?= $baseUrl ?>superadmin/logs" class="nav-item <?= ($subRoute === 'logs') ? 'active' : '' ?>">📜 Logs do Sistema</a>
    </nav>

    <!-- Feedbacks -->
    <?php if ($sucesso): ?>
        <div style="background: rgba(46, 213, 115, 0.15); border: 1px solid #2ed573; padding: 1rem; border-radius: 10px; color: #2ed573; font-weight: 500; font-size: 0.9rem; margin-bottom: 1.5rem;">
            ✔ <?= htmlspecialchars($sucesso) ?>
        </div>
    <?php endif; ?>
    <?php if ($erro): ?>
        <div style="background: rgba(255, 71, 87, 0.15); border: 1px solid #ff4757; padding: 1rem; border-radius: 10px; color: #ff4757; font-weight: 500; font-size: 0.9rem; margin-bottom: 1.5rem;">
            ❌ <?= htmlspecialchars($erro) ?>
        </div>
    <?php endif; ?>

    <!-- Renderização das Abas -->
    <?php if ($subRoute === 'dashboard'): ?>
        
        <!-- Grid de Métricas -->
        <div class="metrics-grid">
            <div class="metric-card">
                <div class="metric-icon">🏢</div>
                <div class="metric-info">
                    <h3>Organizações</h3>
                    <p><?= $metricas['tenants'] ?></p>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-icon">👥</div>
                <div class="metric-info">
                    <h3>Usuários</h3>
                    <p><?= $metricas['usuarios'] ?></p>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-icon">⚡</div>
                <div class="metric-info">
                    <h3>Webhooks</h3>
                    <p><?= $metricas['webhook_logs'] ?></p>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-icon">🚨</div>
                <div class="metric-info">
                    <h3>Chamados Ativos</h3>
                    <p><?= $metricas['chamados'] ?></p>
                </div>
            </div>
        </div>

        <!-- Seção de Gráficos -->
        <div class="charts-container">
            <div class="chart-box">
                <h3>⚡ Requisições Webhook (Últimos 7 Dias)</h3>
                <div style="position: relative; height: 300px;">
                    <canvas id="webhookVolumeChart"></canvas>
                </div>
            </div>
            <div class="chart-box">
                <h3>📊 Distribuição de Status</h3>
                <div style="position: relative; height: 300px;">
                    <canvas id="webhookStatusChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Tabela de Organizações Ativas -->
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
                                        <?php
                                        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                                        $domainName = $_SERVER['HTTP_HOST'];
                                        $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
                                        $webhookUrl = $protocol . $domainName . $basePath . '/webhook.php?token=' . $t['webhook_token'];
                                        ?>
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <span style="font-family: monospace; font-size: 0.8rem; background: rgba(255,255,255,0.03); padding: 0.2rem 0.5rem; border-radius: 6px; border: 1px solid var(--border-color); color: var(--text-secondary);" title="Token Completo: <?= htmlspecialchars($t['webhook_token']) ?>">
                                                <?= htmlspecialchars(substr($t['webhook_token'], 0, 8)) ?>...
                                            </span>
                                            <button type="button" onclick="copiarUrlWebhook(this, '<?= htmlspecialchars($webhookUrl) ?>')" class="btn-inspect" style="font-size: 0.72rem; padding: 0.25rem 0.5rem; border-radius: 6px; cursor: pointer; border-color: rgba(30, 144, 255, 0.25); color: #1e90ff; background: rgba(30, 144, 255, 0.12); border-style: solid; border-width: 1px; transition: all 0.2s; font-weight: 500;" onmouseover="this.style.background='rgba(30,144,255,0.2)'" onmouseout="this.style.background='rgba(30,144,255,0.12)'" title="Copiar URL completa do webhook com token">
                                                📋 Copiar URL
                                            </button>
                                        </div>
                                    </td>
                                    <td style="padding: 0.8rem; text-align: center; font-weight: 500;"><?= $t['user_count'] ?></td>
                                    <td style="padding: 0.8rem; text-align: center; color: var(--color-lead); font-weight: 500;"><?= $t['chamado_count'] ?></td>
                                    <td style="padding: 0.8rem; text-align: center;">
                                        <div style="display: flex; justify-content: center; gap: 6px;">
                                            <a href="t/<?= htmlspecialchars($t['slug']) ?>/dashboard" target="_blank" class="btn-inspect" style="text-decoration: none; font-size: 0.72rem; padding: 0.25rem 0.5rem; border-radius: 6px;">Inspecionar ➔</a>
                                            <a href="<?= $baseUrl ?>superadmin/organizations#org-<?= $t['id'] ?>" class="btn-inspect" style="text-decoration: none; font-size: 0.72rem; padding: 0.25rem 0.5rem; border-radius: 6px; background: rgba(255, 255, 255, 0.05); color: var(--text-primary);">Configurar</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($subRoute === 'organizations'): ?>
        
        <!-- Tela de Gerenciamento das Organizações (Accordions) -->
        <h2 style="font-size: 1.3rem; margin-bottom: 1.5rem; color: var(--text-primary);">🏢 Gerenciamento das Organizações</h2>
        
        <div class="org-list">
            <?php if (empty($tenants)): ?>
                <div class="panel-box" style="text-align: center; padding: 2rem; color: var(--text-secondary);">
                    Nenhuma organização cadastrada.
                </div>
            <?php else: ?>
                <?php foreach ($tenants as $t): ?>
                    <div class="org-item" id="org-<?= $t['id'] ?>">
                        <div class="org-header" onclick="toggleOrgDetails(<?= $t['id'] ?>)">
                            <div style="display: flex; align-items: center; gap: 15px;">
                                <div class="org-color-dot" style="background: <?= htmlspecialchars($t['cor_primaria']) ?>; box-shadow: 0 0 8px <?= htmlspecialchars($t['cor_primaria']) ?>;"></div>
                                <div style="text-align: left;">
                                    <h3 class="org-name"><?= htmlspecialchars($t['nome']) ?></h3>
                                    <span class="org-slug">Slug: /t/<?= htmlspecialchars($t['slug']) ?>/</span>
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 15px;">
                                <span class="badge" style="background: rgba(255,255,255,0.03); color: var(--text-primary);">👤 Membros: <?= $t['user_count'] ?></span>
                                <span class="badge" style="background: rgba(46, 213, 115, 0.08); color: var(--color-lead);">🚨 Chamados: <?= $t['chamado_count'] ?></span>
                                <span class="badge" style="background: rgba(30, 144, 255, 0.08); color: var(--color-default);">⚡ Webhooks: <?= $t['log_count'] ?></span>
                                <span class="accordion-arrow">▼</span>
                            </div>
                        </div>
                        
                        <div class="org-details" id="details-<?= $t['id'] ?>" style="display: none;">
                            <div class="org-details-grid">
                                <!-- Configurações Gerais e Visuais -->
                                <div class="org-section">
                                    <h4>🎨 Estilo & Identidade Visual</h4>
                                    <form action="" method="POST" style="display: flex; flex-direction: column; gap: 1rem;">
                                        <?php echo renderizarCampoCSRF(); ?>
                                        <input type="hidden" name="action" value="edit_tenant">
                                        <input type="hidden" name="tenant_id" value="<?= $t['id'] ?>">
                                        
                                        <div class="form-group">
                                            <label style="font-size: 0.82rem; color: var(--text-secondary);">Nome da Organização</label>
                                            <input type="text" name="tenant_nome" value="<?= htmlspecialchars($t['nome']) ?>" required class="form-control">
                                        </div>
                                        
                                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                            <div class="form-group">
                                                <label style="font-size: 0.82rem; color: var(--text-secondary);">Cor Primária</label>
                                                <input type="color" name="cor_primaria" value="<?= htmlspecialchars($t['cor_primaria']) ?>" class="form-control color-picker">
                                            </div>
                                            <div class="form-group">
                                                <label style="font-size: 0.82rem; color: var(--text-secondary);">Cor Secundária</label>
                                                <input type="color" name="cor_secundaria" value="<?= htmlspecialchars($t['cor_secundaria']) ?>" class="form-control color-picker">
                                            </div>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label style="font-size: 0.82rem; color: var(--text-secondary);">Modo de Visualização</label>
                                            <select name="modo_visualizacao" class="form-control">
                                                <option value="dark" <?= $t['modo_visualizacao'] === 'dark' ? 'selected' : '' ?>>Escuro (Dark Theme)</option>
                                                <option value="light" <?= $t['modo_visualizacao'] === 'light' ? 'selected' : '' ?>>Claro (Light Theme)</option>
                                            </select>
                                        </div>
                                        
                                        <button type="submit" class="btn-submit-small">Salvar Configurações</button>
                                    </form>
                                </div>
                                
                                <!-- Usuários da Organização -->
                                <div class="org-section">
                                    <h4>👥 Administradores & Membros</h4>
                                    <div class="users-list">
                                        <?php 
                                        $users = isset($usuarios_por_tenant[$t['id']]) ? $usuarios_por_tenant[$t['id']] : [];
                                        if (empty($users)): 
                                        ?>
                                            <p style="color: var(--text-secondary); font-size: 0.88rem; padding: 1rem 0;">Nenhum usuário administrativo cadastrado.</p>
                                        <?php else: ?>
                                            <table class="users-table">
                                                <thead>
                                                    <tr>
                                                        <th>Nome</th>
                                                        <th>E-mail</th>
                                                        <th>Nível</th>
                                                        <th style="text-align: right;">Ações</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($users as $u): ?>
                                                        <tr>
                                                            <td style="font-weight: 500; color: var(--text-primary);"><?= htmlspecialchars($u['nome']) ?></td>
                                                            <td style="color: var(--text-secondary);"><?= htmlspecialchars($u['email']) ?></td>
                                                            <td>
                                                                <span class="badge" style="font-size: 0.72rem; padding: 0.2rem 0.4rem; border-color: rgba(255,255,255,0.08); background: rgba(255,255,255,0.03);">
                                                                    <?= htmlspecialchars($u['role']) ?>
                                                                </span>
                                                            </td>
                                                            <td style="text-align: right;">
                                                                <button type="button" onclick="showResetPasswordModal(<?= $u['id'] ?>, '<?= htmlspecialchars($u['nome']) ?>')" class="btn-action-small">
                                                                    🔑 Resetar Senha
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Ações Destrutivas -->
                            <div class="org-footer-actions">
                                <form action="" method="POST" onsubmit="return confirm('ATENÇÃO: Essa ação é irreversível! Isso excluirá permanentemente a organização <?= htmlspecialchars($t['nome']) ?> e todos os seus dados. Confirma?')">
                                    <?php echo renderizarCampoCSRF(); ?>
                                    <input type="hidden" name="action" value="delete_tenant">
                                    <input type="hidden" name="tenant_id" value="<?= $t['id'] ?>">
                                    <button type="submit" class="btn-danger-small">🚨 Excluir Organização Permanentemente</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    <?php elseif ($subRoute === 'register'): ?>
        
        <!-- Tela de Cadastro de Nova Organização -->
        <div style="max-width: 600px; margin: 0 auto;">
            <div class="panel-box" style="background: var(--panel-bg); border: 1px solid var(--border-color); border-radius: 16px; padding: 2rem; backdrop-filter: blur(12px);">
                <h2 class="panel-title" style="margin-top: 0; font-size: 1.3rem; color: var(--text-primary); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 8px;">
                    🏢 Registrar Organização & Workspace
                </h2>
                <p style="color: var(--text-secondary); font-size: 0.88rem; margin-bottom: 1.5rem;">Preencha os campos abaixo para provisionar uma nova estrutura multi-tenant exclusiva e configurar o primeiro usuário administrador do sistema.</p>
                
                <form action="" method="POST" style="display: flex; flex-direction: column; gap: 1.2rem;">
                    <?php echo renderizarCampoCSRF(); ?>
                    <input type="hidden" name="action" value="create_tenant">

                    <span class="label-text" style="font-weight: 600; display: block; border-bottom: 1px solid var(--border-color); padding-bottom: 5px; margin-bottom: 5px; color: var(--color-default);">Dados da Empresa</span>
                    
                    <div class="form-group">
                        <label class="label-text" for="tenant_nome" style="font-size: 0.85rem;">Nome da Organização</label>
                        <input type="text" id="tenant_nome" name="tenant_nome" required placeholder="Ex: Helena CRM Corp" class="form-control" style="background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); border-radius: 8px; padding: 0.6rem; color: var(--text-primary); outline: none;">
                    </div>

                    <div class="form-group">
                        <label class="label-text" for="tenant_slug" style="font-size: 0.85rem;">Slug Rota (Subpasta da URL)</label>
                        <input type="text" id="tenant_slug" name="tenant_slug" required placeholder="Ex: helenacrm" class="form-control" style="background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); border-radius: 8px; padding: 0.6rem; color: var(--text-primary); outline: none;" oninput="this.value = this.value.toLowerCase().replace(/[^a-z0-9_-]/g, '')">
                        <span class="label-text" style="font-size: 0.7rem; color: var(--text-secondary);">
                            Rota única na URL: /t/<strong>slug</strong>/dashboard
                        </span>
                    </div>

                    <span class="label-text" style="font-weight: 600; display: block; border-bottom: 1px solid var(--border-color); padding-bottom: 5px; margin-bottom: 5px; margin-top: 10px; color: var(--color-default);">Administrador Principal</span>

                    <div class="form-group">
                        <label class="label-text" for="admin_nome" style="font-size: 0.85rem;">Nome do Administrador</label>
                        <input type="text" id="admin_nome" name="admin_nome" required placeholder="Ex: Carlos Albuquerque" class="form-control" style="background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); border-radius: 8px; padding: 0.6rem; color: var(--text-primary); outline: none;">
                    </div>

                    <div class="form-group">
                        <label class="label-text" for="admin_email" style="font-size: 0.85rem;">E-mail do Administrador</label>
                        <input type="email" id="admin_email" name="admin_email" required placeholder="Ex: admin@helenacrm.com" class="form-control" style="background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); border-radius: 8px; padding: 0.6rem; color: var(--text-primary); outline: none;">
                    </div>

                    <div class="form-group">
                        <label class="label-text" for="admin_senha" style="font-size: 0.85rem;">Senha Inicial</label>
                        <input type="password" id="admin_senha" name="admin_senha" required placeholder="Min 6 caracteres" class="form-control" style="background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); border-radius: 8px; padding: 0.6rem; color: var(--text-primary); outline: none;" minlength="6">
                    </div>

                    <button type="submit" class="btn-premium" style="width: 100%; margin: 0; padding: 0.75rem; font-weight: 600; background: linear-gradient(135deg, #1e90ff, #00bfff); border: none; color:#fff; border-radius:8px; cursor:pointer;">
                        Provisionar Workspace 🚀
                    </button>
                </form>
            </div>
        </div>

    <?php elseif ($subRoute === 'logs'): ?>
        
        <!-- Tela de Leitura de Logs do Sistema -->
        <h2 style="font-size: 1.3rem; margin-bottom: 1.5rem; color: var(--text-primary);">📜 Visualizador de Logs do Sistema</h2>
        
        <div class="logs-container">
            <div class="logs-tabs">
                <button type="button" class="log-tab-btn active" id="btn-tab-sistema" onclick="switchLogView('sistema')">Logs do Sistema (Alertas)</button>
                <button type="button" class="log-tab-btn" id="btn-tab-php" onclick="switchLogView('php')">Logs de Erros PHP</button>
                <button type="button" class="log-tab-btn" id="btn-tab-auditoria" onclick="switchLogView('auditoria')">Log de Auditoria de Inspeções</button>
            </div>
            
            <!-- Logs do Sistema -->
            <div class="log-viewer-wrapper" id="log-wrapper-sistema">
                <div class="log-viewer-header">
                    <span>📄 erros_sistema.log</span>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <button type="button" onclick="window.location.reload()" class="btn-action-small">🔄 Recarregar</button>
                        <form action="" method="POST" style="margin: 0;">
                            <?php echo renderizarCampoCSRF(); ?>
                            <input type="hidden" name="action" value="clear_log">
                            <input type="hidden" name="log_type" value="sistema">
                            <button type="submit" class="btn-action-small" style="color: var(--color-atendimento); border-color: rgba(255, 71, 87, 0.25);" onmouseover="this.style.background='rgba(255, 71, 87, 0.1)'" onmouseout="this.style.background='none'">🗑️ Limpar Log</button>
                        </form>
                    </div>
                </div>
                <pre class="log-terminal"><?= htmlspecialchars($sistemaLog ?: 'Nenhum log registrado no arquivo erros_sistema.log.') ?></pre>
            </div>
            
            <!-- Logs PHP -->
            <div class="log-viewer-wrapper" id="log-wrapper-php" style="display: none;">
                <div class="log-viewer-header">
                    <span>📄 erros_php.log</span>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <button type="button" onclick="window.location.reload()" class="btn-action-small">🔄 Recarregar</button>
                        <form action="" method="POST" style="margin: 0;">
                            <?php echo renderizarCampoCSRF(); ?>
                            <input type="hidden" name="action" value="clear_log">
                            <input type="hidden" name="log_type" value="php">
                            <button type="submit" class="btn-action-small" style="color: var(--color-atendimento); border-color: rgba(255, 71, 87, 0.25);" onmouseover="this.style.background='rgba(255, 71, 87, 0.1)'" onmouseout="this.style.background='none'">🗑️ Limpar Log</button>
                        </form>
                    </div>
                </div>
                <pre class="log-terminal"><?= htmlspecialchars($phpLog ?: 'Nenhum log de erro PHP registrado.') ?></pre>
            </div>

            <!-- Logs de Auditoria -->
            <div class="log-viewer-wrapper" id="log-wrapper-auditoria" style="display: none; flex-direction: column; width: 100%;">
                <div class="log-viewer-header">
                    <span>📊 Registro de Auditoria de Inspeções</span>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <a href="<?= $baseUrl ?>superadmin/logs?export=audit" target="_blank" class="btn-action-small" style="text-decoration: none; border-color: rgba(30, 144, 255, 0.25); color: #1e90ff;" onmouseover="this.style.background='rgba(30,144,255,0.1)'" onmouseout="this.style.background='none'">📥 Exportar CSV</a>
                        <button type="button" onclick="window.location.reload()" class="btn-action-small">🔄 Recarregar</button>
                    </div>
                </div>
                <div style="overflow-x: auto; width: 100%; background: rgba(0,0,0,0.25); padding: 1.2rem; border-radius: 8px; border: 1px solid var(--border-color);">
                    <?php if (empty($auditoriaLogs)): ?>
                        <p style="color: var(--text-secondary); font-size: 0.88rem; text-align: center; padding: 2rem 0;">Nenhum registro de auditoria encontrado.</p>
                    <?php else: ?>
                        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.82rem;">
                            <thead>
                                <tr style="border-bottom: 1px solid var(--border-color); color: var(--color-default);">
                                    <th style="padding: 10px; font-weight: 600;">Data/Hora</th>
                                    <th style="padding: 10px; font-weight: 600;">Superadmin</th>
                                    <th style="padding: 10px; font-weight: 600;">E-mail</th>
                                    <th style="padding: 10px; font-weight: 600;">Organização Inspecionada</th>
                                    <th style="padding: 10px; font-weight: 600;">Ação</th>
                                    <th style="padding: 10px; font-weight: 600;">Detalhes</th>
                                    <th style="padding: 10px; font-weight: 600;">IP</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($auditoriaLogs as $alog): ?>
                                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.03); color: var(--text-primary);">
                                        <td style="padding: 10px; color: var(--text-secondary); white-space: nowrap;"><?= date('d/m/Y H:i:s', strtotime($alog['criado_em'])) ?></td>
                                        <td style="padding: 10px; font-weight: 600;"><?= htmlspecialchars($alog['usuario_nome']) ?></td>
                                        <td style="padding: 10px; color: var(--text-secondary);"><?= htmlspecialchars($alog['usuario_email']) ?></td>
                                        <td style="padding: 10px;">
                                            <strong style="color: #ffa502;"><?= htmlspecialchars($alog['tenant_nome']) ?></strong> 
                                            <span style="font-size: 0.72rem; color: var(--text-secondary);">(<?= htmlspecialchars($alog['tenant_slug']) ?>)</span>
                                        </td>
                                        <td style="padding: 10px;">
                                            <?php if ($alog['acao'] === 'inspecionar_inicio'): ?>
                                                <span class="badge" style="background: rgba(30, 144, 255, 0.15); border-color: rgba(30, 144, 255, 0.3); color: #1e90ff; font-size: 0.7rem; font-weight: bold; padding: 0.2rem 0.5rem; border-radius: 4px; display: inline-block;">INSPEÇÃO</span>
                                            <?php elseif ($alog['acao'] === 'acao_bloqueada'): ?>
                                                <span class="badge" style="background: rgba(255, 71, 87, 0.15); border-color: rgba(255, 71, 87, 0.3); color: #ff4757; font-size: 0.7rem; font-weight: bold; padding: 0.2rem 0.5rem; border-radius: 4px; display: inline-block;">BLOQUEIO</span>
                                            <?php else: ?>
                                                <span class="badge" style="font-size: 0.7rem; padding: 0.2rem 0.5rem; border-radius: 4px; display: inline-block;"><?= htmlspecialchars($alog['acao']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 10px; color: var(--text-secondary);"><?= htmlspecialchars($alog['detalhes']) ?></td>
                                        <td style="padding: 10px; font-family: monospace; font-size: 0.75rem;"><?= htmlspecialchars($alog['ip']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    <?php endif; ?>
</div>

<!-- Modal Dialog de Redefinição de Senha -->
<dialog id="reset-password-dialog">
    <h3 style="margin-top:0; margin-bottom:0.5rem; font-size:1.1rem; color:var(--text-primary);">Resetar Senha</h3>
    <p style="font-size:0.82rem; color:var(--text-secondary); margin-bottom:1.2rem;">
        Alterar senha do usuário <strong id="reset-user-name" style="color:var(--color-default);"></strong>
    </p>
    <form action="" method="POST" style="display:flex; flex-direction:column; gap:1rem;">
        <?php echo renderizarCampoCSRF(); ?>
        <input type="hidden" name="action" value="reset_password">
        <input type="hidden" id="reset-user-id" name="usuario_id" value="">
        
        <div class="form-group" style="margin: 0;">
            <label style="font-size:0.78rem; color:var(--text-secondary); margin-bottom: 0.3rem;">Nova Senha (mín. 6 caracteres)</label>
            <input type="password" name="nova_senha" required placeholder="Digite a nova senha" class="form-control" minlength="6">
        </div>
        
        <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:0.8rem;">
            <button type="button" onclick="closeResetPasswordModal()" class="btn-action-small" style="padding:0.5rem 1rem;">Cancelar</button>
            <button type="submit" class="btn-submit-small" style="width:auto; margin:0; padding:0.5rem 1rem;">Confirmar</button>
        </div>
    </form>
</dialog>

<script>
// Copiar URL do Webhook
function copiarUrlWebhook(btn, url) {
    navigator.clipboard.writeText(url).then(function() {
        const originalText = btn.innerHTML;
        btn.innerHTML = '✔ Copiado!';
        btn.style.background = 'rgba(46, 213, 115, 0.2)';
        btn.style.borderColor = '#2ed573';
        btn.style.color = '#2ed573';
        
        setTimeout(function() {
            btn.innerHTML = originalText;
            btn.style.background = 'rgba(30, 144, 255, 0.12)';
            btn.style.borderColor = 'rgba(30, 144, 255, 0.25)';
            btn.style.color = '#1e90ff';
        }, 2000);
    }).catch(function(err) {
        console.error('Erro ao copiar: ', err);
        alert('Erro ao copiar URL para a área de transferência.');
    });
}

// Acordeão de Organizações
function toggleOrgDetails(id) {
    const item = document.getElementById('org-' + id);
    const details = document.getElementById('details-' + id);
    if (!item || !details) return;
    
    const isActive = item.classList.contains('active');
    
    // Opcional: fechar outros accordions para focar no aberto
    document.querySelectorAll('.org-item').forEach(el => {
        el.classList.remove('active');
    });
    document.querySelectorAll('.org-details').forEach(el => {
        el.style.display = 'none';
    });
    
    if (!isActive) {
        item.classList.add('active');
        details.style.display = 'block';
    }
}

// Dialog Modal de Reset de Senha
function showResetPasswordModal(id, name) {
    const dialog = document.getElementById('reset-password-dialog');
    document.getElementById('reset-user-id').value = id;
    document.getElementById('reset-user-name').innerText = name;
    dialog.showModal();
}

function closeResetPasswordModal() {
    document.getElementById('reset-password-dialog').close();
}

// Alternar entre logs
function switchLogView(type) {
    const wrapperSistema = document.getElementById('log-wrapper-sistema');
    const wrapperPhp = document.getElementById('log-wrapper-php');
    const wrapperAuditoria = document.getElementById('log-wrapper-auditoria');
    const btnSistema = document.getElementById('btn-tab-sistema');
    const btnPhp = document.getElementById('btn-tab-php');
    const btnAuditoria = document.getElementById('btn-tab-auditoria');
    
    wrapperSistema.style.display = 'none';
    wrapperPhp.style.display = 'none';
    wrapperAuditoria.style.display = 'none';
    btnSistema.classList.remove('active');
    btnPhp.classList.remove('active');
    btnAuditoria.classList.remove('active');
    
    if (type === 'sistema') {
        wrapperSistema.style.display = 'flex';
        btnSistema.classList.add('active');
    } else if (type === 'php') {
        wrapperPhp.style.display = 'flex';
        btnPhp.classList.add('active');
    } else if (type === 'auditoria') {
        wrapperAuditoria.style.display = 'flex';
        btnAuditoria.classList.add('active');
    }
}

// Se o hash da URL tiver #org-{id}, abre automaticamente o accordion correspondente
window.addEventListener('DOMContentLoaded', () => {
    if (window.location.hash) {
        const matches = window.location.hash.match(/#org-(\d+)/);
        if (matches) {
            const orgId = matches[1];
            toggleOrgDetails(orgId);
        }
    }
});
</script>

<?php if ($subRoute === 'dashboard'): ?>
<!-- Scripts da Dashboard (Chart.js) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    <?php
    // Montagem dinâmica dos dados de webhooks dos últimos 7 dias para o gráfico
    $labels = [];
    $totals = [];
    $dateMap = [];
    foreach ($grafico_webhooks as $point) {
        $dateMap[$point['data']] = (int)$point['total'];
    }
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $labels[] = date('d/m', strtotime($d));
        $totals[] = isset($dateMap[$d]) ? $dateMap[$d] : 0;
    }
    
    $sucessos = $grafico_status['sucesso'];
    $falhas = $grafico_status['falha'];
    ?>
    
    // Gráfico de Volume de Webhooks (Últimos 7 Dias)
    const ctxLine = document.getElementById('webhookVolumeChart');
    if (ctxLine) {
        new Chart(ctxLine, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [{
                    label: 'Requisições Webhook',
                    data: <?php echo json_encode($totals); ?>,
                    borderColor: '#1e90ff',
                    backgroundColor: 'rgba(30, 144, 255, 0.12)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3,
                    pointBackgroundColor: '#1e90ff',
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(255, 255, 255, 0.05)' },
                        ticks: { color: '#a4b0be', font: { family: 'Outfit' } }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: '#a4b0be', font: { family: 'Outfit' } }
                    }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }

    // Gráfico de Distribuição de Status
    const ctxDoughnut = document.getElementById('webhookStatusChart');
    if (ctxDoughnut) {
        const totalWebhooks = <?= ($sucessos + $falhas) ?>;
        
        // Dados padrão caso não existam requisições para evitar gráfico vazio
        const data = totalWebhooks > 0 ? [<?= $sucessos ?>, <?= $falhas ?>] : [1, 0];
        const labels = totalWebhooks > 0 ? ['Sucesso (2xx)', 'Erro/Outros'] : ['Sem registros', 'Sem registros'];
        const colors = totalWebhooks > 0 ? ['#2ed573', '#ff4757'] : ['rgba(255,255,255,0.06)', 'rgba(0,0,0,0)'];

        new Chart(ctxDoughnut, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: colors,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: '#a4b0be', font: { family: 'Outfit', size: 11 } }
                    }
                },
                cutout: '70%'
            }
        });
    }
});
</script>
<?php endif; ?>

</body>
</html>
