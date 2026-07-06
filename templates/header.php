<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <!-- Base URL dinâmica calculada pelo PHP para rotas amigáveis -->
    <base href="<?php echo $baseUrl; ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Made in AI - Central de Alertas em Tempo Real</title>
    
    <!-- PWA Manifest & Icons -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#0c0a1f">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Alertas AI">
    <link rel="apple-touch-icon" href="assets/img/icon_192.png">
    
    <!-- Fonte Premium Outfit da Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="assets/css/index.css">
    
    <!-- Configurações do Sistema injetadas do Backend -->
    <script>
        window.SYSTEM_CONFIG = {
            audioAlerta: "<?php echo htmlspecialchars(obterConfiguracao('audio_alerta', 'assets/audio/notificacao.mp3'), ENT_QUOTES, 'UTF-8'); ?>",
            limiteLogs: <?php echo (int) obterConfiguracao('limite_logs', 100); ?>
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
        <a href="./" style="text-decoration: none; display: flex; align-items: center; gap: 0.8rem;">
            <div class="logo-area">
                <div class="logo-dot"></div>
                <h1>Central de Alertas Made in AI</h1>
            </div>
        </a>
        
        <div class="header-actions">
            <!-- Navegação Tabular de Páginas -->
            <a href="./" class="btn-view-logs" style="<?php echo ($route === 'dashboard') ? 'background: rgba(30, 144, 255, 0.15); border-color: var(--color-default); font-weight: 600;' : ''; ?>" onmouseover="this.style.borderColor='var(--color-default)';" onmouseout="<?php echo ($route === 'dashboard') ? '' : "this.style.borderColor='var(--border-color)';"; ?>">
                📊 Dashboard
            </a>
            <a href="logs" class="btn-view-logs" style="<?php echo ($route === 'logs') ? 'background: rgba(30, 144, 255, 0.15); border-color: var(--color-default); font-weight: 600;' : ''; ?>" onmouseover="this.style.borderColor='var(--color-default)';" onmouseout="<?php echo ($route === 'logs') ? '' : "this.style.borderColor='var(--border-color)';"; ?>">
                🔍 Webhooks
            </a>
            <a href="settings" class="btn-view-logs" style="<?php echo ($route === 'settings' || $route === 'configuracoes') ? 'background: rgba(30, 144, 255, 0.15); border-color: var(--color-default); font-weight: 600;' : ''; ?>" onmouseover="this.style.borderColor='var(--color-default)';" onmouseout="<?php echo ($route === 'settings' || $route === 'configuracoes') ? '' : "this.style.borderColor='var(--border-color)';"; ?>">
                ⚙️ Configurações
            </a>
            
            <!-- Badge de Conexão SSE -->
            <div id="statusBadge" class="status-badge connecting">
                <span class="status-indicator"></span>
                <span id="statusText">Conectando...</span>
            </div>
        </div>
    </header>

    <!-- Conteúdo Principal -->
    <main class="<?php echo ($route === 'logs' || $route === 'settings' || $route === 'configuracoes') ? 'main-full' : 'main-grid'; ?>">
        
        <!-- Banner de Permissão de Áudio do Navegador (Exibido se o autoplay estiver bloqueado) -->
        <div id="audioBanner" class="audio-banner" style="display: none; grid-column: 1 / -1; width: 100%;">
            <div class="audio-banner-text">
                <strong>Atenção:</strong> O navegador bloqueia sons automáticos antes da primeira interação. Clique em ativar para permitir alertas sonoros de chamados urgentes.
            </div>
            <button onclick="ativarContextoAudio()" class="audio-banner-btn">Ativar Sons</button>
        </div>
