<?php
/**
 * Cabeçalho Comum (Header) - Central de Alertas Multi-Tenant
 * Carrega a estilização, injeta configurações dinâmicas de White-Label e define o menu.
 */

// Se o contexto do tenant estiver ativo, injeta os estilos dinâmicos do White-Label
$tenantNome = $_SESSION['tenant_ativo_nome'] ?? 'Made in AI';
$tenantSlug = $_SESSION['tenant_ativo_slug'] ?? '';
$corPrimaria = $tenantConfig['cor_primaria'] ?? '#1e90ff';
$corSecundaria = $tenantConfig['cor_secundaria'] ?? '#ffa502';
$modoVisualizacao = $tenantConfig['modo_visualizacao'] ?? 'dark';
$logoPath = $tenantConfig['logo_path'] ?? '';
$exibicaoLogo = $tenantConfig['exibicao_logo'] ?? 'logo_nome';

// Função auxiliar para converter Hex em RGBA para os glows das bordas/fundo
function hex2rgba($hex, $alpha = 0.4)
{
    $hex = str_replace('#', '', $hex);
    if (strlen($hex) == 3) {
        $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
        $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
        $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
    } else {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
    }
    return "rgba($r, $g, $b, $alpha)";
}

$corPrimariaGlow = hex2rgba($corPrimaria, 0.4);
$corSecundariaGlow = hex2rgba($corSecundaria, 0.4);
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <!-- Base URL dinâmica calculada pelo PHP para rotas amigáveis -->
    <base href="<?php echo $baseUrl; ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($tenantNome, ENT_QUOTES, 'UTF-8'); ?> - Central de Alertas</title>

    <!-- PWA Manifest Dinâmico com base no tenant -->
    <link rel="manifest" href="manifest.php?slug=<?php echo urlencode($tenantSlug); ?>">
    <meta name="theme-color" content="<?php echo ($modoVisualizacao === 'light') ? '#f5f6fa' : '#0c0a1f'; ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="<?php echo htmlspecialchars($tenantNome, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="apple-touch-icon" href="<?php echo $logoPath ?: 'assets/img/icon_192.png'; ?>">

    <!-- Fonte Premium Outfit da Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="assets/css/index.css">

    <style>
        :root {
            --color-default:
                <?php echo $corPrimaria; ?>
            ;
            --color-default-glow:
                <?php echo $corPrimariaGlow; ?>
            ;
            --color-sistema:
                <?php echo $corSecundaria; ?>
            ;
            --color-sistema-glow:
                <?php echo $corSecundariaGlow; ?>
            ;

        }

        /* Estilos do Tema Claro (Light Mode) */
        body.light-mode {
            --bg-gradient: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            --panel-bg: rgba(255, 255, 255, 0.95);
            --card-bg: #ffffff;
            --border-color: rgba(0, 0, 0, 0.08);
            --text-primary: #0f172a;
            --text-secondary: #475569;
        }

        body.light-mode h1,
        body.light-mode h2,
        body.light-mode h3,
        body.light-mode .panel-title,
        body.light-mode .metric-value,
        body.light-mode .metric-label {
            background: none !important;
            -webkit-text-fill-color: initial !important;
            color: #0f172a !important;
        }

        body.light-mode .label-text {
            color: #475569;
        }

        body.light-mode header {
            background: rgba(255, 255, 255, 0.92) !important;
            border-bottom-color: rgba(0, 0, 0, 0.08) !important;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04) !important;
        }

        body.light-mode .logo-header h1 {
            background: none !important;
            -webkit-text-fill-color: initial !important;
            color: #0f172a !important;
        }

        body.light-mode .btn-view-logs {
            background: rgba(0, 0, 0, 0.04) !important;
            border-color: rgba(0, 0, 0, 0.1) !important;
            color: #1e293b !important;
        }

        body.light-mode .btn-view-logs:hover {
            background: rgba(0, 0, 0, 0.08) !important;
            color: #0f172a !important;
        }

        /* Formulários e Inputs no Tema Claro */
        body.light-mode .form-control,
        body.light-mode input:not([type="range"]):not([type="checkbox"]):not([type="radio"]),
        body.light-mode select,
        body.light-mode textarea {
            background: #ffffff !important;
            color: #0f172a !important;
            border: 1px solid #cbd5e1 !important;
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.03) !important;
        }
        
        body.light-mode .form-control:focus,
        body.light-mode input:not([type="range"]):not([type="checkbox"]):not([type="radio"]):focus,
        body.light-mode select:focus,
        body.light-mode textarea:focus {
            border-color: var(--color-default) !important;
            box-shadow: 0 0 0 3px rgba(30, 144, 255, 0.15) !important;
        }
        
        body.light-mode .form-control option,
        body.light-mode select option {
            background-color: #ffffff !important;
            color: #0f172a !important;
        }
        
        body.light-mode ::placeholder,
        body.light-mode .form-control::placeholder,
        body.light-mode input::placeholder,
        body.light-mode textarea::placeholder {
            color: #94a3b8 !important;
            opacity: 1 !important;
        }

        /* Range Slider no Tema Claro */
        body.light-mode input[type="range"].range-slider,
        body.light-mode .range-slider {
            background: #cbd5e1 !important;
            border-radius: 3px;
        }

        /* Toggle Switch no Tema Claro */
        body.light-mode .slider {
            background-color: #cbd5e1 !important;
        }

        body.light-mode input:checked + .slider {
            background-color: var(--color-lead) !important;
        }

        /* Sidebar & Botões de Abas de Configurações no Tema Claro */
        body.light-mode .settings-sidebar {
            background: #ffffff !important;
            border-color: rgba(0, 0, 0, 0.08) !important;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04) !important;
        }

        body.light-mode .settings-tab-btn {
            color: #475569 !important;
        }

        body.light-mode .settings-tab-btn .tab-title {
            color: #1e293b !important;
        }

        body.light-mode .settings-tab-btn .tab-desc {
            color: #64748b !important;
        }

        body.light-mode .settings-tab-btn:hover {
            background: rgba(0, 0, 0, 0.03) !important;
            color: #0f172a !important;
        }

        body.light-mode .settings-tab-btn.active {
            background: #f8fafc !important;
            border-color: var(--color-default) !important;
            color: #0f172a !important;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.06), inset 0 0 0 1px var(--color-default-glow) !important;
        }

        body.light-mode .settings-tab-btn.active .tab-title {
            color: var(--color-default) !important;
            font-weight: 700 !important;
        }

        body.light-mode .settings-tab-btn.active .tab-desc {
            color: #334155 !important;
        }

        /* Painel e Conteúdo no Tema Claro */
        body.light-mode .panel-box {
            background: #ffffff !important;
            border-color: rgba(0, 0, 0, 0.08) !important;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.04) !important;
        }

        /* Botões Especiais no Tema Claro */
        body.light-mode .btn-premium,
        body.light-mode #btnTestSound {
            background: #f1f5f9 !important;
            border: 1px solid #cbd5e1 !important;
            color: #1e293b !important;
            font-weight: 600 !important;
        }

        body.light-mode .btn-premium:hover,
        body.light-mode #btnTestSound:hover {
            background: #e2e8f0 !important;
            border-color: var(--color-default) !important;
            color: #0f172a !important;
        }

        body.light-mode .btn-inspect {
            background: rgba(0, 0, 0, 0.04) !important;
            border-color: rgba(0, 0, 0, 0.12) !important;
            color: #1e293b !important;
        }

        body.light-mode .btn-inspect:hover {
            background: rgba(0, 0, 0, 0.08) !important;
        }

        /* Sub-containers de itens (como lista de sons e lista de membros) */
        body.light-mode div[style*="background: rgba(255,255,255"] {
            background: #f8fafc !important;
            border-color: rgba(0, 0, 0, 0.08) !important;
        }

        /* Cards de Alerta no Tema Claro */
        body.light-mode .alert-card {
            background: #ffffff !important;
            border-color: rgba(0, 0, 0, 0.08) !important;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.03) !important;
        }

        body.light-mode .alert-card:hover {
            background: #ffffff !important;
            border-color: rgba(0, 0, 0, 0.15) !important;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06) !important;
        }

        /* Tabela de Webhooks no Tema Claro */
        body.light-mode .webhook-table th {
            background: #f8fafc !important;
            color: #1e293b !important;
            border-bottom-color: rgba(0, 0, 0, 0.08) !important;
        }

        body.light-mode .webhook-table tr:hover {
            background: #f1f5f9 !important;
        }

        body.light-mode .webhook-table td {
            border-bottom-color: rgba(0, 0, 0, 0.05) !important;
            color: #334155 !important;
        }

        /* Tabs de Filtro no Dashboard */
        body.light-mode .filter-tabs .tab {
            background: rgba(0, 0, 0, 0.03) !important;
            border-color: rgba(0, 0, 0, 0.08) !important;
            color: #475569 !important;
        }

        body.light-mode .filter-tabs .tab:hover {
            background: rgba(0, 0, 0, 0.06) !important;
            color: #0f172a !important;
        }

        body.light-mode .filter-tabs .tab.active {
            background: #ffffff !important;
            border-color: var(--color-default) !important;
            color: var(--color-default) !important;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06) !important;
        }

        /* PWA Banner no Tema Claro */
        body.light-mode #pwaInstallPanel {
            background: linear-gradient(135deg, rgba(30, 144, 255, 0.08), #ffffff) !important;
            border-color: rgba(30, 144, 255, 0.25) !important;
        }

        body.light-mode #pwaInstallPanel .panel-title {
            color: #0066cc !important;
        }

        /* Audio Banner */
        body.light-mode .audio-banner {
            background: linear-gradient(135deg, rgba(255, 165, 2, 0.1), rgba(255, 71, 87, 0.1)) !important;
            border-color: rgba(255, 165, 2, 0.4) !important;
        }

        body.light-mode .audio-banner-text {
            color: #d97706 !important;
        }
    </style>

    <!-- Configurações do Sistema injetadas do Backend -->
    <script>
        window.SYSTEM_CONFIG = {
            audioAlerta: "<?php echo htmlspecialchars(obterConfiguracao('audio_alerta', 'assets/audio/notificacao.mp3'), ENT_QUOTES, 'UTF-8'); ?>",
            audioAlertaSuporte: "<?php echo htmlspecialchars(obterConfiguracao('audio_alerta_suporte', 'assets/audio/notificacao.mp3'), ENT_QUOTES, 'UTF-8'); ?>",
            audioAlertaLead: "<?php echo htmlspecialchars(obterConfiguracao('audio_alerta_lead', 'assets/audio/notificacao.mp3'), ENT_QUOTES, 'UTF-8'); ?>",
            limiteLogs: <?php echo (int) obterConfiguracao('limite_logs', 100); ?>,
            jwtToken: "<?php echo $_SESSION['jwt_token'] ?? ''; ?>",
            tenantSlug: "<?php echo htmlspecialchars($tenantSlug, ENT_QUOTES, 'UTF-8'); ?>",
            tenantNome: "<?php echo htmlspecialchars($tenantNome, ENT_QUOTES, 'UTF-8'); ?>",
            webhookToken: "<?php echo htmlspecialchars($tenantConfig['webhook_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>",
            tempoLimiteEspera: <?php echo (int)($tenantConfig['tempo_limite_espera'] ?? 5); ?>,
            serverTime: "<?php echo date('Y/m/d H:i:s'); ?>",
            userRole: "<?php echo $_SESSION['usuario_role'] ?? 'user'; ?>",
            csrfToken: "<?php echo obterTokenCSRF(); ?>"
        };
    </script>
</head>

<body class="<?php echo ($modoVisualizacao === 'light') ? 'light-mode' : ''; ?>">

    <!-- Modal Urgente em Tela Cheia (Disponível em qualquer página do App) -->
    <div id="urgentAlertModal" class="urgent-modal">
        <button id="btnUrgentResolve" class="urgent-modal-close">Dispensar &times;</button>
        <div class="urgent-modal-content">
            <div class="urgent-modal-icon">🚨</div>
            <div class="urgent-modal-title" id="urgentModalTitle">ATENDIMENTO HUMANO REQUERIDO</div>
            <div class="urgent-modal-client" id="urgentModalClient">-</div>
            <div class="urgent-modal-msg" id="urgentModalMsg">-</div>
            <a href="#" target="_blank" id="btnUrgentChat" class="urgent-modal-chat-btn">ATENDER CONVERSA 💬</a>
            <div class="urgent-modal-time" id="urgentModalTime">🕒 -</div>
        </div>
    </div>

    <!-- Header / Navbar -->
    <header>
        <a href="t/<?php echo $tenantSlug; ?>/dashboard"
            style="text-decoration: none; display: flex; align-items: center; gap: 0.8rem;">
            <div class="logo-area">
                <?php 
                if ($exibicaoLogo === 'logo_nome' || $exibicaoLogo === 'logo'): 
                    if ($logoPath && file_exists(__DIR__ . '/../' . $logoPath)): ?>
                        <img src="<?php echo htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8'); ?>"
                            alt="<?php echo htmlspecialchars($tenantNome, ENT_QUOTES, 'UTF-8'); ?>"
                            style="max-height: 38px; border-radius: 6px; object-fit: contain;">
                    <?php else: ?>
                        <div class="logo-dot"></div>
                    <?php endif; 
                endif; 

                if ($exibicaoLogo === 'logo_nome' || $exibicaoLogo === 'nome'): ?>
                    <h1><?php echo htmlspecialchars($tenantNome, ENT_QUOTES, 'UTF-8'); ?></h1>
                <?php endif; ?>
            </div>
        </a>

        <div class="header-actions">

            <!-- Badge de Conexão SSE -->
            <div id="statusBadge" class="status-badge connecting">
                <span class="status-indicator"></span>
                <span id="statusText">Conectando...</span>
            </div>

            <?php if (isset($_SESSION['usuario_role']) && $_SESSION['usuario_role'] === 'superadmin'): ?>
                <!-- Badge de Modo Inspeção (apenas para superadmin inspecionando contas) -->
                <div class="status-badge" style="background: rgba(255, 165, 0, 0.1); border-color: rgba(255, 165, 0, 0.3); color: #ffa502; font-weight: 600;">
                    <span class="status-indicator" style="background-color: #ffa502; box-shadow: 0 0 8px #ffa502;"></span>
                    <span>Modo Inspeção</span>
                </div>
            <?php endif; ?>

            <!-- Navegação Tabular de Páginas -->
            <a href="t/<?php echo $tenantSlug; ?>/dashboard" class="btn-view-logs"
                style="<?php echo ($currentView === 'dashboard') ? 'background: rgba(30, 144, 255, 0.15); border-color: var(--color-default); font-weight: 600;' : ''; ?>"
                onmouseover="this.style.borderColor='var(--color-default)';"
                onmouseout="<?php echo ($currentView === 'dashboard') ? '' : "this.style.borderColor='var(--border-color)';"; ?>">
                📊 Dashboard
            </a>
            <?php if (isset($_SESSION['usuario_role']) && in_array($_SESSION['usuario_role'], ['admin', 'superadmin'])): ?>
                <a href="t/<?php echo $tenantSlug; ?>/logs" class="btn-view-logs"
                    style="<?php echo ($currentView === 'logs') ? 'background: rgba(30, 144, 255, 0.15); border-color: var(--color-default); font-weight: 600;' : ''; ?>"
                    onmouseover="this.style.borderColor='var(--color-default)';"
                    onmouseout="<?php echo ($currentView === 'logs') ? '' : "this.style.borderColor='var(--border-color)';"; ?>">
                    🔍 Webhooks
                </a>
            <?php endif; ?>
            <a href="t/<?php echo $tenantSlug; ?>/settings" class="btn-view-logs"
                style="<?php echo ($currentView === 'settings' || $currentView === 'configuracoes') ? 'background: rgba(30, 144, 255, 0.15); border-color: var(--color-default); font-weight: 600;' : ''; ?>"
                onmouseover="this.style.borderColor='var(--color-default)';"
                onmouseout="<?php echo ($currentView === 'settings' || $currentView === 'configuracoes') ? '' : "this.style.borderColor='var(--border-color)';"; ?>">
                ⚙️ Configurações
            </a>

            <!-- Botão de Sair -->
            <?php if (isset($_SESSION['usuario_role']) && $_SESSION['usuario_role'] === 'superadmin'): ?>
                <a class="btn-view-logs" style="border-color: rgba(255, 71, 87, 0.15); color: rgba(255, 71, 87, 0.4); cursor: not-allowed; opacity: 0.6; pointer-events: none; text-decoration: none;" title="O logout está desabilitado durante a inspeção de conta.">
                    🚪 Sair
                </a>
            <?php else: ?>
                <a href="logout" class="btn-view-logs" style="border-color: rgba(255, 71, 87, 0.2); color: #ff4757;"
                    onmouseover="this.style.background='rgba(255,71,87,0.1)'" onmouseout="this.style.background='none'">
                    🚪 Sair
                </a>
            <?php endif; ?>

        </div>
    </header>

    <!-- Conteúdo Principal -->
    <main
        class="<?php echo ($currentView === 'logs' || $currentView === 'settings' || $currentView === 'configuracoes') ? 'main-full' : 'main-grid'; ?>">

        <!-- Banner de Permissão de Áudio do Navegador (Exibido se o autoplay estiver bloqueado) -->
        <div id="audioBanner" class="audio-banner" style="display: none; grid-column: 1 / -1; width: 100%;">
            <div class="audio-banner-text">
                <strong>Atenção:</strong> O navegador bloqueia sons automáticos antes da primeira interação. Clique em
                ativar para permitir alertas sonoros de chamados urgentes.
            </div>
            <button onclick="ativarContextoAudio()" class="audio-banner-btn">Ativar Sons</button>
        </div>