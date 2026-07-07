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

            <?php if ($modoVisualizacao === 'light'): ?>
                --bg-gradient: linear-gradient(135deg, #f5f6fa 0%, #dfe4ea 100%);
                --panel-bg: rgba(255, 255, 255, 0.85);
                --card-bg: rgba(255, 255, 255, 0.95);
                --border-color: rgba(0, 0, 0, 0.08);
                --text-primary: #2f3542;
                --text-secondary: #747d8c;
            <?php endif; ?>
        }

        <?php if ($modoVisualizacao === 'light'): ?>
            /* Sobrescreve estilos escuros nativos para White-Label Light Mode */
            h1,
            .panel-title,
            .label-text,
            .metric-value,
            .metric-label {
                background: none !important;
                -webkit-text-fill-color: initial !important;
                color: var(--text-primary) !important;
            }

            header {
                background: rgba(255, 255, 255, 0.9) !important;
            }

            .logo-header h1 {
                background: none !important;
                -webkit-text-fill-color: initial !important;
                color: var(--text-primary) !important;
            }

            .btn-view-logs {
                background: rgba(0, 0, 0, 0.03) !important;
                color: var(--text-primary) !important;
            }

            .btn-view-logs:hover {
                background: rgba(0, 0, 0, 0.06) !important;
            }

            .form-control,
            input,
            select,
            textarea {
                background: rgba(255, 255, 255, 0.95) !important;
                color: var(--text-primary) !important;
                border-color: rgba(0, 0, 0, 0.15) !important;
            }
            .form-control:focus,
            input:focus,
            select:focus,
            textarea:focus {
                border-color: var(--color-default) !important;
                box-shadow: 0 0 5px rgba(30, 144, 255, 0.25) !important;
            }
            .form-control option,
            select option {
                background-color: #ffffff !important;
                color: var(--text-primary) !important;
            }
            ::placeholder,
            .form-control::placeholder,
            input::placeholder,
            textarea::placeholder {
                color: #a4b0be !important;
                opacity: 1 !important;
            }
            .alert-card:hover {
                background: #ffffff !important;
                border-color: rgba(0, 0, 0, 0.12) !important;
                box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06) !important;
            }
            #pwaInstallPanel {
                background: linear-gradient(135deg, rgba(30, 144, 255, 0.06), rgba(255, 255, 255, 0.85)) !important;
                border-color: rgba(30, 144, 255, 0.22) !important;
            }
            #pwaInstallPanel .panel-title {
                color: #0066cc !important;
            }
            .webhook-table th {
                background: rgba(0, 0, 0, 0.02) !important;
                color: var(--text-primary) !important;
            }
            .webhook-table tr:hover {
                background: rgba(0, 0, 0, 0.01) !important;
            }

        <?php endif; ?>
    </style>

    <!-- Configurações do Sistema injetadas do Backend -->
    <script>
        window.SYSTEM_CONFIG = {
            audioAlerta: "<?php echo htmlspecialchars(obterConfiguracao('audio_alerta', 'assets/audio/notificacao.mp3'), ENT_QUOTES, 'UTF-8'); ?>",
            limiteLogs: <?php echo (int) obterConfiguracao('limite_logs', 100); ?>,
            jwtToken: "<?php echo $_SESSION['jwt_token'] ?? ''; ?>",
            tenantSlug: "<?php echo htmlspecialchars($tenantSlug, ENT_QUOTES, 'UTF-8'); ?>",
            webhookToken: "<?php echo htmlspecialchars($tenantConfig['webhook_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>",
            tempoLimiteEspera: <?php echo (int)($tenantConfig['tempo_limite_espera'] ?? 5); ?>
        };
    </script>
</head>

<body>

    <!-- Modal Urgente em Tela Cheia (Disponível em qualquer página do App) -->
    <div id="urgentAlertModal" class="urgent-modal">
        <button id="btnUrgentResolve" class="urgent-modal-close">Dispensar &times;</button>
        <div class="urgent-modal-content">
            <div class="urgent-modal-icon">🚨</div>
            <div class="urgent-modal-title">ATENDIMENTO HUMANO REQUERIDO</div>
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

            <!-- Navegação Tabular de Páginas -->
            <a href="t/<?php echo $tenantSlug; ?>/dashboard" class="btn-view-logs"
                style="<?php echo ($currentView === 'dashboard') ? 'background: rgba(30, 144, 255, 0.15); border-color: var(--color-default); font-weight: 600;' : ''; ?>"
                onmouseover="this.style.borderColor='var(--color-default)';"
                onmouseout="<?php echo ($currentView === 'dashboard') ? '' : "this.style.borderColor='var(--border-color)';"; ?>">
                📊 Dashboard
            </a>
            <a href="t/<?php echo $tenantSlug; ?>/logs" class="btn-view-logs"
                style="<?php echo ($currentView === 'logs') ? 'background: rgba(30, 144, 255, 0.15); border-color: var(--color-default); font-weight: 600;' : ''; ?>"
                onmouseover="this.style.borderColor='var(--color-default)';"
                onmouseout="<?php echo ($currentView === 'logs') ? '' : "this.style.borderColor='var(--border-color)';"; ?>">
                🔍 Webhooks
            </a>
            <a href="t/<?php echo $tenantSlug; ?>/settings" class="btn-view-logs"
                style="<?php echo ($currentView === 'settings' || $currentView === 'configuracoes') ? 'background: rgba(30, 144, 255, 0.15); border-color: var(--color-default); font-weight: 600;' : ''; ?>"
                onmouseover="this.style.borderColor='var(--color-default)';"
                onmouseout="<?php echo ($currentView === 'settings' || $currentView === 'configuracoes') ? '' : "this.style.borderColor='var(--border-color)';"; ?>">
                ⚙️ Configurações
            </a>

            <!-- Botão de Sair -->
            <a href="logout" class="btn-view-logs" style="border-color: rgba(255, 71, 87, 0.2); color: #ff4757;"
                onmouseover="this.style.background='rgba(255,71,87,0.1)'" onmouseout="this.style.background='none'">
                🚪 Sair
            </a>

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