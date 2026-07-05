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
            <!-- Alterna entre a visualização de Logs e Painel principal -->
            <?php if ($route === 'logs'): ?>
                <a href="./" class="btn-view-logs" style="background: rgba(30, 144, 255, 0.15); border-color: var(--color-default);">
                    📊 Dashboard
                </a>
            <?php else: ?>
                <a href="logs" class="btn-view-logs" onmouseover="this.style.borderColor='var(--color-default)'; this.style.background='rgba(30, 144, 255, 0.1)';" onmouseout="this.style.borderColor='var(--border-color)'; this.style.background='rgba(255, 255, 255, 0.05)';">
                    🔍 Webhooks
                </a>
            <?php endif; ?>
            
            <!-- Badge de Conexão SSE -->
            <div id="statusBadge" class="status-badge connecting">
                <span class="status-indicator"></span>
                <span id="statusText">Conectando...</span>
            </div>
        </div>
    </header>

    <!-- Conteúdo Principal -->
    <main class="<?php echo ($route === 'logs') ? 'main-full' : 'main-grid'; ?>">
        
        <!-- Banner de Permissão de Áudio do Navegador (Exibido se o autoplay estiver bloqueado) -->
        <div id="audioBanner" class="audio-banner" style="display: none; grid-column: 1 / -1; width: 100%;">
            <div class="audio-banner-text">
                <strong>Atenção:</strong> O navegador bloqueia sons automáticos antes da primeira interação. Clique em ativar para permitir alertas sonoros de chamados urgentes.
            </div>
            <button onclick="ativarContextoAudio()" class="audio-banner-btn">Ativar Sons</button>
        </div>
