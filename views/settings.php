<?php
/**
 * View de Configurações - Central de Alertas Multi-Tenant
 * Permite gerenciar parâmetros do painel, sons, White-Label e usuários da empresa.
 */

require_once __DIR__ . '/../controllers/settings_controller.php';
?>

<div class="container">

    <!-- Subheader Interno de Configurações -->
    <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 0.8rem; border-bottom: 1px solid var(--border-color); flex-wrap: wrap; gap: 1rem;">
        <div>
            <h2 style="font-size: 1.4rem; font-weight: 600; color: var(--text-primary);">Configurações do Workspace</h2>
            <p style="font-size: 0.82rem; color: var(--text-secondary); margin-top: 4px;">Gerencie limites do servidor, identidade visual white-label e controle de acessos da equipe</p>
        </div>
    </div>

    <?php if (isTenantReadOnlyMode()): ?>
        <!-- Banner de Aviso de Modo Inspeção -->
        <div style="background: rgba(255, 165, 0, 0.1); border: 1px solid rgba(255, 165, 0, 0.3); padding: 1rem; border-radius: 12px; color: #ffa502; font-weight: 500; font-size: 0.9rem; margin-top: 1.5rem; display: flex; align-items: center; gap: 10px; width: 100%;">
            <span>⚠️</span>
            <span><strong>Modo de Inspeção (Somente Leitura):</strong> Você está visualizando as configurações desta organização como Superadmin. Alterações estão desabilitadas.</span>
        </div>
    <?php endif; ?>

    <!-- Mensagens de Feedback de Operação -->
    <div id="feedbackContainer" style="display: <?php echo ($sucessoMsg || $erroMsg) ? 'block' : 'none'; ?>;">
        <div id="feedbackMessage" style="<?php 
            if ($sucessoMsg) echo 'background: rgba(46, 213, 115, 0.15); border: 1px solid #2ed573; color: #2ed573;';
            elseif ($erroMsg) echo 'background: rgba(255, 71, 87, 0.15); border: 1px solid var(--error-color); color: var(--error-color);';
        ?> padding: 0.8rem 1.2rem; border-radius: 8px; font-size: 0.9rem; font-weight: 500; display: flex; align-items: center; gap: 8px;">
            <span id="feedbackIcon"><?= $sucessoMsg ? '✔' : ($erroMsg ? '❌' : '') ?></span> 
            <span id="feedbackText"><?= htmlspecialchars($sucessoMsg ?: $erroMsg) ?></span>
        </div>
    </div>

    <style>
    /* Layout principal das configurações */
    .settings-layout {
        display: flex;
        gap: 2rem;
        margin-top: 1.5rem;
        align-items: flex-start;
    }

    /* Sidebar das Abas */
    .settings-sidebar {
        width: 300px;
        flex-shrink: 0;
        display: flex;
        flex-direction: column;
        gap: 0.6rem;
        background: var(--panel-bg);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        padding: 1.2rem;
        backdrop-filter: blur(16px);
    }

    /* Botão de Aba */
    .settings-tab-btn {
        background: transparent;
        border: 1px solid transparent;
        border-radius: 10px;
        padding: 0.8rem 1rem;
        text-align: left;
        display: flex;
        align-items: center;
        gap: 0.8rem;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        color: var(--text-secondary);
        width: 100%;
    }

    .settings-tab-btn:hover {
        background: rgba(255, 255, 255, 0.03);
        color: var(--text-primary);
        transform: translateX(4px);
    }

    /* Estado Ativo do Botão de Aba */
    .settings-tab-btn.active {
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.05) 0%, rgba(255, 255, 255, 0.01) 100%);
        border-color: var(--color-default);
        color: var(--text-primary);
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15), 0 0 10px var(--color-default-glow);
    }

    .settings-tab-btn.active .tab-icon {
        transform: scale(1.15);
        filter: drop-shadow(0 0 5px var(--color-default-glow));
    }

    /* Ícones e Textos nas Abas */
    .tab-icon {
        font-size: 1.2rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: transform 0.3s ease;
    }

    .tab-text {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .tab-title {
        font-size: 0.9rem;
        font-weight: 600;
    }

    .tab-desc {
        font-size: 0.72rem;
        color: var(--text-secondary);
        font-weight: 400;
    }

    .settings-tab-btn.active .tab-desc {
        color: var(--text-primary);
        opacity: 0.8;
    }

    /* Área de Conteúdo */
    .settings-content {
        flex-grow: 1;
        min-width: 0; /* Impede overflow em flex */
    }

    /* Conteúdo de Aba Individual */
    .settings-tab-content {
        display: none;
        animation: settingsFadeIn 0.4s ease forwards;
    }

    .settings-tab-content.active {
        display: block;
    }

    /* Efeito Fade-In */
    @keyframes settingsFadeIn {
        from {
            opacity: 0;
            transform: translateY(8px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Responsividade */
    @media (max-width: 900px) {
        .settings-layout {
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .settings-sidebar {
            width: 100%;
            flex-direction: row;
            overflow-x: auto;
            white-space: nowrap;
            gap: 0.8rem;
            padding: 0.8rem;
            border-radius: 12px;
        }
        
        .settings-tab-btn {
            width: auto;
            flex-shrink: 0;
            padding: 0.6rem 0.8rem;
        }
        
        .settings-tab-btn:hover {
            transform: none;
        }
        
        .tab-desc {
            display: none; /* Oculta descrição no mobile */
        }
    }
    </style>

    <!-- Layout das Abas Verticais -->
    <div class="settings-layout">
        
        <!-- Sidebar de Navegação -->
        <div class="settings-sidebar">
            <button type="button" class="settings-tab-btn active" data-tab="tab-preferencias">
                <span class="tab-icon">🔊</span>
                <div class="tab-text">
                    <span class="tab-title">Preferências Locais</span>
                    <span class="tab-desc">Som e alertas no dispositivo</span>
                </div>
            </button>
            
            <?php if ($isAdmin): ?>
            <button type="button" class="settings-tab-btn" data-tab="tab-parametros">
                <span class="tab-icon">🎛️</span>
                <div class="tab-text">
                    <span class="tab-title">Parâmetros do Painel</span>
                    <span class="tab-desc">Limites do painel e webhooks</span>
                </div>
            </button>
            
            <button type="button" class="settings-tab-btn" data-tab="tab-whitelabel">
                <span class="tab-icon">🎨</span>
                <div class="tab-text">
                    <span class="tab-title">Customização Visual</span>
                    <span class="tab-desc">Cores, logo e white-label</span>
                </div>
            </button>
            
            <button type="button" class="settings-tab-btn" data-tab="tab-sons">
                <span class="tab-icon">📂</span>
                <div class="tab-text">
                    <span class="tab-title">Biblioteca de Sons</span>
                    <span class="tab-desc">Alertas sonoros da empresa</span>
                </div>
            </button>
            
            <button type="button" class="settings-tab-btn" data-tab="tab-equipe">
                <span class="tab-icon">👥</span>
                <div class="tab-text">
                    <span class="tab-title">Membros da Equipe</span>
                    <span class="tab-desc">Usuários e controle de acesso</span>
                </div>
            </button>
            <?php endif; ?>
        </div>

        <!-- Área de Conteúdo das Abas -->
        <div class="settings-content">
            
            <!-- ABA 1: Preferências Locais -->
            <div id="tab-preferencias" class="settings-tab-content active">
                <div class="panel-box" style="background: var(--panel-bg); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; backdrop-filter: blur(16px);">
                    <h3 class="panel-title" style="margin-top: 0; font-size: 1.1rem; color: var(--text-primary); margin-bottom: 1.2rem; display: flex; align-items: center; gap: 8px;">
                        🔊 Preferências Locais (Dispositivo)
                    </h3>
                    <div class="audio-controls" style="display: flex; flex-direction: column; gap: 1.2rem;">
                        <div class="control-row" style="display: flex; justify-content: space-between; align-items: center;">
                            <span class="label-text" style="font-weight: 500;">Habilitar Alerta Sonoro</span>
                            <label class="switch">
                                <input type="checkbox" id="audioToggle">
                                <span class="slider"></span>
                            </label>
                        </div>
                        
                        <div class="control-row" style="display: flex; flex-direction: column; align-items: flex-start; gap: 0.5rem;">
                            <div style="display: flex; justify-content: space-between; width: 100%;">
                                <span class="label-text" style="font-weight: 500;">Volume do Alerta</span>
                                <span id="volumeValue" class="label-text" style="color: var(--color-lead); font-weight: 600;">80%</span>
                            </div>
                            <input type="range" id="volumeControl" class="range-slider" min="0" max="100" value="80" style="width: 100%;">
                        </div>
                        
                        <button id="btnTestSound" class="btn-premium" style="margin: 0; padding: 0.6rem; background: rgba(255,255,255,0.05); border-color: var(--border-color);">
                            Testar Áudio Atual 🔊
                        </button>
                        
                        <!-- Web Push Controls -->
                        <div id="pushControlRow" style="display: none; border-top: 1px solid var(--border-color); padding-top: 1rem; margin-top: 0.5rem; flex-direction: column; gap: 0.8rem; width: 100%;">
                            <span class="label-text" style="font-weight: 500;">Notificações Push no Dispositivo</span>
                            <button id="btnSubscribePush" class="btn-premium" style="width: 100%; margin: 0; background: linear-gradient(135deg, #ff4500, #ff8c00);">
                                Ativar Notificações 🔔
                            </button>
                            <span id="pushStatusMsg" class="label-text" style="font-size: 0.75rem; color: var(--text-secondary); text-align: center; width: 100%;">
                                Verificando suporte a push...
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($isAdmin): ?>
            <!-- ABA 2: Parâmetros do Painel -->
            <div id="tab-parametros" class="settings-tab-content">
                <div class="panel-box" style="background: var(--panel-bg); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; backdrop-filter: blur(16px);">
                    <h3 class="panel-title" style="margin-top: 0; font-size: 1.1rem; color: var(--text-primary); margin-bottom: 1.2rem; display: flex; align-items: center; gap: 8px;">
                        🎛️ Parâmetros do Painel
                    </h3>
                    <form action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8'); ?>" method="POST" style="display: flex; flex-direction: column; gap: 1.2rem;">
                        <?php echo renderizarCampoCSRF(); ?>
                        <input type="hidden" name="action" value="save_general">
                        
                        <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
                            <label class="label-text" for="limite_logs" style="font-weight: 500; font-size: 0.85rem;">Limite de Logs Exibidos (Webhooks)</label>
                            <input type="number" id="limite_logs" name="limite_logs" class="form-control" 
                                   value="<?= $limiteLogs ?>" min="1" max="1000" required
                                   style="width: 100%; background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); border-radius: 8px; padding: 0.6rem; color: var(--text-primary); outline: none;">
                            <span class="label-text" style="font-size: 0.72rem; color: var(--text-secondary);">
                                Número máximo de requisições de webhook no painel de Logs.
                            </span>
                        </div>
                        
                        <button type="submit" class="btn-premium" style="width: 100%; margin: 0; padding: 0.7rem; font-weight: 600;">
                            Salvar Limite de Logs
                        </button>
                    </form>
                </div>
            </div>

            <!-- ABA 3: Customização Visual -->
            <div id="tab-whitelabel" class="settings-tab-content">
                <div class="panel-box" style="background: var(--panel-bg); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; backdrop-filter: blur(16px);">
                    <h3 class="panel-title" style="margin-top: 0; font-size: 1.1rem; color: var(--text-primary); margin-bottom: 1.2rem; display: flex; align-items: center; gap: 8px;">
                        🎨 Customização Visual (White-Label)
                    </h3>
                    <form action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8'); ?>" method="POST" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 1.2rem;">
                        <?php echo renderizarCampoCSRF(); ?>
                        <input type="hidden" name="action" value="save_whitelabel">
                        
                        <!-- Cores -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
                                <label class="label-text" for="cor_primaria" style="font-size: 0.8rem;">Cor Primária</label>
                                <div style="display: flex; gap: 8px; align-items: center;">
                                    <input type="color" id="cor_primaria" name="cor_primaria" value="<?= htmlspecialchars($tenantConfig['cor_primaria'] ?? '#2ed573') ?>" style="width: 42px; height: 36px; border: none; border-radius: 6px; cursor: pointer; background: none;">
                                    <span class="label-text" style="font-size: 0.8rem; font-family: monospace; text-transform: uppercase;"><?= htmlspecialchars($tenantConfig['cor_primaria'] ?? '#2ed573') ?></span>
                                </div>
                            </div>
                            
                            <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
                                <label class="label-text" for="cor_secundaria" style="font-size: 0.8rem;">Cor Secundária</label>
                                <div style="display: flex; gap: 8px; align-items: center;">
                                    <input type="color" id="cor_secundaria" name="cor_secundaria" value="<?= htmlspecialchars($tenantConfig['cor_secundaria'] ?? '#70a1ff') ?>" style="width: 42px; height: 36px; border: none; border-radius: 6px; cursor: pointer; background: none;">
                                    <span class="label-text" style="font-size: 0.8rem; font-family: monospace; text-transform: uppercase;"><?= htmlspecialchars($tenantConfig['cor_secundaria'] ?? '#70a1ff') ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Modo Visualização Padrão -->
                        <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
                            <label class="label-text" for="modo_visualizacao" style="font-size: 0.85rem;">Tema de Exibição Padrão</label>
                            <select id="modo_visualizacao" name="modo_visualizacao" class="form-control" style="width: 100%; background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); border-radius: 8px; padding: 0.6rem; color: var(--text-primary); outline: none;">
                                <option value="dark" <?= ($tenantConfig['modo_visualizacao'] === 'dark') ? 'selected' : '' ?>>Modo Escuro (Padrão)</option>
                                <option value="light" <?= ($tenantConfig['modo_visualizacao'] === 'light') ? 'selected' : '' ?>>Modo Claro</option>
                            </select>
                        </div>

                        <!-- Exibição do Logo/Nome no Cabeçalho -->
                        <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
                            <label class="label-text" for="exibicao_logo" style="font-size: 0.85rem;">Exibição no Cabeçalho</label>
                            <select id="exibicao_logo" name="exibicao_logo" class="form-control" style="width: 100%; background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); border-radius: 8px; padding: 0.6rem; color: var(--text-primary); outline: none;">
                                <option value="logo_nome" <?= (($tenantConfig['exibicao_logo'] ?? 'logo_nome') === 'logo_nome') ? 'selected' : '' ?>>Logotipo + Nome da Empresa</option>
                                <option value="logo" <?= (($tenantConfig['exibicao_logo'] ?? 'logo_nome') === 'logo') ? 'selected' : '' ?>>Somente o Logotipo</option>
                                <option value="nome" <?= (($tenantConfig['exibicao_logo'] ?? 'logo_nome') === 'nome') ? 'selected' : '' ?>>Somente o Nome da Empresa</option>
                            </select>
                        </div>

                        <!-- Tempo Limite de Espera (Aguardando Resposta) -->
                        <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
                            <label class="label-text" for="tempo_limite_espera" style="font-size: 0.85rem;">Tempo Limite de Espera (minutos)</label>
                            <input type="number" id="tempo_limite_espera" name="tempo_limite_espera" min="1" max="120" value="<?= htmlspecialchars($tenantConfig['tempo_limite_espera'] ?? 5) ?>" class="form-control" style="width: 100%; background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); border-radius: 8px; padding: 0.6rem; color: var(--text-primary); outline: none;">
                            <span class="label-text" style="font-size: 0.72rem; color: var(--text-secondary);">
                                Tempo sem resposta do suporte necessário para disparar a sirene e alertas sonoros/push (padrão: 5).
                            </span>
                        </div>

                        <!-- Logo File Upload -->
                        <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
                            <label class="label-text" for="logo_file" style="font-size: 0.85rem;">Logotipo da Empresa</label>
                            <input type="file" id="logo_file" name="logo_file" accept="image/*" class="form-control" style="width: 100%; background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); border-radius: 8px; padding: 0.5rem; color: var(--text-primary);">
                            <span class="label-text" style="font-size: 0.72rem; color: var(--text-secondary);">
                                Formatos sugeridos: PNG transparente ou SVG (max: 2MB).
                            </span>
                            
                            <div id="logoPreviewContainer" style="width: 100%;">
                                <?php if (!empty($tenantConfig['logo_path']) && file_exists(__DIR__ . '/../' . $tenantConfig['logo_path'])): ?>
                                    <!-- Exibe logo atual e botão de remoção -->
                                    <div style="display: flex; align-items: center; justify-content: space-between; background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); padding: 0.6rem; border-radius: 8px; margin-top: 0.5rem; width: 100%;">
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <img src="<?= htmlspecialchars($tenantConfig['logo_path']) ?>" style="max-height: 28px; max-width: 80px; object-fit: contain; border-radius: 4px;">
                                            <span class="label-text" style="font-size: 0.72rem; color: var(--text-secondary);">Logo ativa</span>
                                        </div>
                                        <button type="button" onclick="confirmarRemoverLogo()" class="btn-inspect" style="font-size: 0.7rem; padding: 0.25rem 0.5rem; border-radius: 6px; background: rgba(255, 71, 87, 0.15); border-color: rgba(255, 71, 87, 0.3); color: #ff4757; cursor: pointer;">
                                            Excluir Logo 🗑️
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <button type="submit" class="btn-premium" style="width: 100%; margin: 0; padding: 0.7rem; font-weight: 600;">
                            Atualizar Identidade Visual
                        </button>
                    </form>

                    <!-- Formulário oculto para excluir logotipo -->
                    <form id="deleteLogoForm" action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8'); ?>" method="POST" style="display:none;">
                        <?php echo renderizarCampoCSRF(); ?>
                        <input type="hidden" name="action" value="delete_logo">
                    </form>
                </div>
            </div>

            <!-- ABA 4: Biblioteca de Sons -->
            <div id="tab-sons" class="settings-tab-content">
                <div class="panel-box" style="background: var(--panel-bg); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; backdrop-filter: blur(16px);">
                    <h3 class="panel-title" style="margin-top: 0; font-size: 1.1rem; color: var(--text-primary); margin-bottom: 1.2rem; display: flex; align-items: center; gap: 8px;">
                        📂 Alertas Sonoros da Empresa
                    </h3>
                    
                    <form action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8'); ?>" method="POST" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 0.8rem; margin-bottom: 1.2rem; border-bottom: 1px solid var(--border-color); padding-bottom: 1.2rem;">
                        <?php echo renderizarCampoCSRF(); ?>
                        <input type="hidden" name="action" value="upload_audio">
                        <div style="display: flex; gap: 8px; width: 100%;">
                            <input type="file" id="audio_file" name="audio_file" accept="audio/*" required class="form-control" style="flex: 1; font-size: 0.8rem; background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); padding: 0.4rem; border-radius: 8px;">
                            <button type="submit" class="btn-premium" style="margin: 0; padding: 0.5rem 1rem; width: auto; font-size: 0.8rem;">Upload</button>
                        </div>
                    </form>

                    <div id="audioLibraryContainer" style="display: flex; flex-direction: column; gap: 0.8rem; max-height: 350px; overflow-y: auto;">
                        <!-- O trecho PHP de biblioteca de sons roda aqui dinamicamente -->
                    </div>
                </div>
            </div>

            <!-- ABA 5: Membros da Equipe -->
            <div id="tab-equipe" class="settings-tab-content">
                <div class="panel-box" style="background: var(--panel-bg); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; backdrop-filter: blur(16px);">
                    <h3 class="panel-title" style="margin-top: 0; font-size: 1.1rem; color: var(--text-primary); margin-bottom: 1.2rem; display: flex; align-items: center; gap: 8px;">
                        👥 Membros da Organização
                    </h3>
                    
                    <!-- Lista de Usuários Existentes -->
                    <div id="userListContainer" style="display: flex; flex-direction: column; gap: 0.6rem; margin-bottom: 1.5rem; max-height: 250px; overflow-y: auto; padding-right: 4px;">
                        <!-- Usuários renderizados dinamicamente -->
                    </div>

                    <!-- Formulário para Adicionar Novo Usuário -->
                    <form id="addUserForm" action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8'); ?>" method="POST" style="border-top: 1px solid var(--border-color); padding-top: 1.2rem; display: flex; flex-direction: column; gap: 0.8rem;">
                        <?php echo renderizarCampoCSRF(); ?>
                        <input type="hidden" name="action" value="add_user">
                        <span class="label-text" style="font-weight: 600; display: block; margin-bottom: 2px;">➕ Cadastrar Novo Membro</span>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                            <input type="text" name="usuario_nome" placeholder="Nome completo" required class="form-control" style="background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); border-radius: 6px; padding: 0.5rem; font-size: 0.8rem; color: var(--text-primary);">
                            <input type="email" name="usuario_email" placeholder="E-mail de acesso" required class="form-control" style="background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); border-radius: 6px; padding: 0.5rem; font-size: 0.8rem; color: var(--text-primary);">
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                            <input type="password" name="usuario_senha" placeholder="Senha inicial" required class="form-control" style="background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); border-radius: 6px; padding: 0.5rem; font-size: 0.8rem; color: var(--text-primary);">
                            <select name="usuario_role" class="form-control" style="background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); border-radius: 6px; padding: 0.5rem; font-size: 0.8rem; color: var(--text-primary); outline: none;">
                                <option value="user">Operador (Leitura)</option>
                                <option value="admin">Administrador (Total)</option>
                            </select>
                        </div>

                        <button type="submit" class="btn-premium" style="width: 100%; margin: 0; padding: 0.6rem; font-weight: 600; font-size: 0.85rem;">
                            Criar Conta de Acesso
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>

</div>

<script>
    function confirmarRemoverLogo() {
        if (confirm("Deseja realmente remover o logotipo personalizado?")) {
            const form = document.getElementById("deleteLogoForm");
            if (form) {
                form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
            }
        }
    }

    function confirmarExcluirUsuario(form, id, nome) {
        if (confirm("Deseja realmente remover o usuário " + nome + "?")) {
            form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        }
    }

    function showFeedback(message, isSuccess) {
        const container = document.getElementById('feedbackContainer');
        const msgDiv = document.getElementById('feedbackMessage');
        const iconSpan = document.getElementById('feedbackIcon');
        const textSpan = document.getElementById('feedbackText');
        
        if (!container || !msgDiv || !iconSpan || !textSpan) return;
        
        textSpan.textContent = message;
        if (isSuccess) {
            msgDiv.style.background = 'rgba(46, 213, 115, 0.15)';
            msgDiv.style.borderColor = '#2ed573';
            msgDiv.style.borderStyle = 'solid';
            msgDiv.style.borderWidth = '1px';
            msgDiv.style.color = '#2ed573';
            iconSpan.textContent = '✔';
        } else {
            msgDiv.style.background = 'rgba(255, 71, 87, 0.15)';
            msgDiv.style.borderColor = 'var(--error-color)';
            msgDiv.style.borderStyle = 'solid';
            msgDiv.style.borderWidth = '1px';
            msgDiv.style.color = 'var(--error-color)';
            iconSpan.textContent = '❌';
        }
        container.style.display = 'block';
        
        setTimeout(() => {
            container.style.display = 'none';
        }, 6000);
    }

    function reloadAudioLibrary() {
        const url = new URL(window.location.href);
        url.searchParams.set('render_library', '1');
        
        fetch(url.toString(), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) throw new Error('Falha ao recarregar a biblioteca');
            return response.text();
        })
        .then(html => {
            const container = document.getElementById('audioLibraryContainer');
            if (container) {
                container.innerHTML = html;
            }
        })
        .catch(err => console.error('Erro ao recarregar biblioteca de sons:', err));
    }

    function reloadUserList() {
        const container = document.getElementById('userListContainer');
        if (!container) return; // Se não for admin, pula

        const url = new URL(window.location.href);
        url.searchParams.set('render_users', '1');
        
        fetch(url.toString(), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) throw new Error('Falha ao recarregar lista de membros');
            return response.text();
        })
        .then(html => {
            container.innerHTML = html;
        })
        .catch(err => console.error('Erro ao recarregar lista de membros:', err));
    }

    function hex2rgba(hex, alpha = 0.4) {
        hex = hex.replace('#', '');
        let r, g, b;
        if (hex.length === 3) {
            r = parseInt(hex.charAt(0) + hex.charAt(0), 16);
            g = parseInt(hex.charAt(1) + hex.charAt(1), 16);
            b = parseInt(hex.charAt(2) + hex.charAt(2), 16);
        } else {
            r = parseInt(hex.substring(0, 2), 16);
            g = parseInt(hex.substring(2, 4), 16);
            b = parseInt(hex.substring(4, 6), 16);
        }
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    }

    function updateVisuals(marca) {
        if (!marca) return;
        
        // 1. Atualizar Cores no :root
        document.documentElement.style.setProperty('--color-default', marca.cor_primaria);
        document.documentElement.style.setProperty('--color-default-glow', hex2rgba(marca.cor_primaria, 0.4));
        document.documentElement.style.setProperty('--color-sistema', marca.cor_secundaria);
        document.documentElement.style.setProperty('--color-sistema-glow', hex2rgba(marca.cor_secundaria, 0.4));
        
        // Atualizar inputs de cor e rótulos HEX na tela
        const corPrimariaInput = document.getElementById('cor_primaria');
        if (corPrimariaInput) {
            corPrimariaInput.value = marca.cor_primaria;
            const textSpan = corPrimariaInput.nextElementSibling;
            if (textSpan) textSpan.textContent = marca.cor_primaria.toUpperCase();
        }
        const corSecundariaInput = document.getElementById('cor_secundaria');
        if (corSecundariaInput) {
            corSecundariaInput.value = marca.cor_secundaria;
            const textSpan = corSecundariaInput.nextElementSibling;
            if (textSpan) textSpan.textContent = marca.cor_secundaria.toUpperCase();
        }
        
        // 2. Atualizar Modo Visualização (Claro / Escuro)
        if (marca.modo_visualizacao === 'light') {
            document.body.classList.add('light-mode');
        } else {
            document.body.classList.remove('light-mode');
        }
        
        // 3. Atualizar Exibição do Logotipo / Nome no Header
        const logoArea = document.querySelector('header .logo-area');
        if (logoArea) {
            let logoHtml = '';
            const exibicao = marca.exibicao_logo;
            
            if (exibicao === 'logo_nome' || exibicao === 'logo') {
                if (marca.logo_path) {
                    logoHtml += `<img src="${marca.logo_path}?t=${new Date().getTime()}" alt="Logo" style="max-height: 38px; border-radius: 6px; object-fit: contain;">`;
                } else {
                    logoHtml += `<div class="logo-dot"></div>`;
                }
            }
            
            if (exibicao === 'logo_nome' || exibicao === 'nome') {
                const tenantNome = window.SYSTEM_CONFIG ? window.SYSTEM_CONFIG.tenantNome || '' : '';
                logoHtml += `<h1>${tenantNome}</h1>`;
            }
            
            logoArea.innerHTML = logoHtml;
        }
        
        // 4. Atualizar o mini painel de pré-visualização de logotipo na página de configurações
        const previewContainer = document.getElementById('logoPreviewContainer');
        if (previewContainer) {
            if (marca.logo_path) {
                previewContainer.innerHTML = `
                    <div style="display: flex; align-items: center; justify-content: space-between; background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); padding: 0.6rem; border-radius: 8px; margin-top: 0.5rem; width: 100%;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <img src="${marca.logo_path}?t=${new Date().getTime()}" style="max-height: 28px; max-width: 80px; object-fit: contain; border-radius: 4px;">
                            <span class="label-text" style="font-size: 0.72rem; color: var(--text-secondary);">Logo ativa</span>
                        </div>
                        <button type="button" onclick="confirmarRemoverLogo()" class="btn-inspect" style="font-size: 0.7rem; padding: 0.25rem 0.5rem; border-radius: 6px; background: rgba(255, 71, 87, 0.15); border-color: rgba(255, 71, 87, 0.3); color: #ff4757; cursor: pointer;">
                            Excluir Logo 🗑️
                        </button>
                    </div>
                `;
            } else {
                previewContainer.innerHTML = '';
            }
        }
    }

    // Inicialização da Navegação por Abas
    document.addEventListener("DOMContentLoaded", function() {
        // Carrega sons e usuários (se existirem)
        reloadAudioLibrary();
        reloadUserList();

        const tabs = document.querySelectorAll(".settings-tab-btn");
        const contents = document.querySelectorAll(".settings-tab-content");
        
        // Tenta ler a aba ativa do localStorage
        let activeTabId = localStorage.getItem("activeSettingsTab");
        
        // Valida se a aba ativa recuperada realmente existe (pode não existir se mudou de permissão)
        if (activeTabId) {
            const btn = document.querySelector(`.settings-tab-btn[data-tab="${activeTabId}"]`);
            if (!btn) activeTabId = null;
        }
        
        // Se não tiver aba ativa válida, define a primeira por padrão
        if (!activeTabId && tabs.length > 0) {
            activeTabId = tabs[0].getAttribute("data-tab");
        }
        
        function activateTab(tabId) {
            tabs.forEach(btn => {
                if (btn.getAttribute("data-tab") === tabId) {
                    btn.classList.add("active");
                } else {
                    btn.classList.remove("active");
                }
            });
            
            contents.forEach(content => {
                if (content.getAttribute("id") === tabId) {
                    content.classList.add("active");
                } else {
                    content.classList.remove("active");
                }
            });
            
            localStorage.setItem("activeSettingsTab", tabId);
        }
        
        tabs.forEach(btn => {
            btn.addEventListener("click", () => {
                const tabId = btn.getAttribute("data-tab");
                activateTab(tabId);
            });
        });
        
        if (activeTabId) {
            activateTab(activeTabId);
        }
    });

    // Submissão de Formulários via AJAX
    document.addEventListener('submit', function(e) {
        if (e.defaultPrevented) return;
        
        const form = e.target;
        const actionInput = form.querySelector('input[name="action"]');
        if (!actionInput) return; // Ignora se não for formulário de ação
        
        e.preventDefault();
        
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn ? submitBtn.innerHTML : '';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Processando... ⏳';
        }
        
        const formData = new FormData(form);
        formData.append('ajax', '1');
        
        fetch(form.getAttribute('action') || window.location.href, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) throw new Error('Erro na requisição');
            return response.json();
        })
        .then(data => {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            }
            
            showFeedback(data.message, data.success);
            
            if (data.success) {
                const actionVal = actionInput.value;
                
                // Reseta formulários de upload e cadastro
                if (actionVal === 'upload_audio' || actionVal === 'add_user') {
                    form.reset();
                }
                
                // Atualizações em tempo real da marca (Sem Reload!)
                if (actionVal === 'save_whitelabel' || actionVal === 'delete_logo') {
                    updateVisuals(data.marca);
                }
                
                // Atualização em tempo real de membros (Sem Reload!)
                if (actionVal === 'add_user' || actionVal === 'delete_user') {
                    reloadUserList();
                }
                
                // Atualização local de limites no JS global
                if (data.limite_logs && window.SYSTEM_CONFIG) {
                    window.SYSTEM_CONFIG.limiteLogs = data.limite_logs;
                }
                if (data.som_ativo && window.SYSTEM_CONFIG) {
                    window.SYSTEM_CONFIG.audioAlerta = data.som_ativo;
                }
                
                // Recarrega biblioteca de sons se necessário
                if (actionVal === 'upload_audio' || actionVal === 'select_audio' || actionVal === 'delete_audio') {
                    reloadAudioLibrary();
                }
            }
        })
        .catch(err => {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            }
            console.error('Erro na requisição AJAX:', err);
            showFeedback('Erro interno ao processar a ação.', false);
        });
    });
</script>

<?php if (isTenantReadOnlyMode()): ?>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Desabilita todos os formulários e botões na página de configurações
    const inputs = document.querySelectorAll('input, select, textarea, button');
    inputs.forEach(el => {
        // Ignora os botões de navegação das abas de configurações e o botão de sair
        if (!el.classList.contains('settings-tab-btn') && !el.classList.contains('btn-view-logs')) {
            el.disabled = true;
            el.style.opacity = '0.5';
            el.style.cursor = 'not-allowed';
        }
    });

    // Desabilita cliques em links que executam ações (exceto navegação)
    const links = document.querySelectorAll('a');
    links.forEach(el => {
        const href = el.getAttribute('href');
        if (href && (href.startsWith('javascript:') || href === '#')) {
            el.style.pointerEvents = 'none';
            el.style.opacity = '0.5';
            el.style.cursor = 'not-allowed';
        }
    });
});
</script>
<?php endif; ?>
