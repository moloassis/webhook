        <!-- Sidebar: Configurações e Simulador Webhook -->
        <div class="sidebar">
            
            <!-- Painel para Instalar PWA (Exibido Dinamicamente se não estiver instalado) -->
            <div id="pwaInstallPanel" class="panel-box" style="display: none; background: linear-gradient(135deg, rgba(30, 144, 255, 0.12), rgba(12, 10, 31, 0.95)); border-color: rgba(30, 144, 255, 0.35);">
                <h2 class="panel-title" style="color: #1e90ff; display: flex; align-items: center; gap: 8px; margin-top: 0;">📲 Instalar Aplicativo</h2>
                <p class="label-text" style="font-size: 0.82rem; margin-bottom: 0.8rem; line-height: 1.4; color: var(--text-secondary);">Adicione a central de alertas à tela inicial do seu dispositivo para ter uma experiência nativa de aplicativo.</p>
                
                <!-- Botão para Chrome / Android / Desktop -->
                <button id="btnInstallPWA" class="btn-premium" style="width: 100%; margin: 0; background: linear-gradient(135deg, #1e90ff, #00bfff);">Instalar Aplicativo 📥</button>
                
                <!-- Instruções Personalizadas para iOS/Safari -->
                <div id="iosInstallInstructions" style="display: none; border-top: 1px solid rgba(255,255,255,0.08); padding-top: 0.8rem; margin-top: 0.6rem;">
                    <span class="label-text" style="font-size: 0.8rem; display: block; margin-bottom: 0.4rem; color: #ffa502; font-weight: 500;">Como instalar no iPhone:</span>
                    <ol style="margin: 0; padding-left: 1.1rem; font-size: 0.76rem; color: var(--text-secondary); line-height: 1.4;">
                        <li style="margin-bottom: 3px;">Toque no botão <strong>Compartilhar</strong> (ícone <span style="font-size: 1rem; line-height: 0;">⎋</span> ou 📥 na barra inferior).</li>
                        <li style="margin-bottom: 3px;">Role a lista e toque em <strong>Adicionar à Tela de Início</strong> ➕.</li>
                        <li>Confirme no canto superior direito para instalar.</li>
                    </ol>
                </div>
            </div>
            
            <!-- Painel de Controles de Áudio -->
            <div class="panel-box">
                <h2 class="panel-title">Notificações</h2>
                <div class="audio-controls">
                    <div class="control-row">
                        <span class="label-text">Habilitar Alerta Sonoro</span>
                        <label class="switch">
                            <input type="checkbox" id="audioToggle" checked>
                            <span class="slider"></span>
                        </label>
                    </div>
                    <div class="control-row" style="flex-direction: column; align-items: flex-start; gap: 0.4rem;">
                        <span class="label-text">Volume</span>
                        <input type="range" id="volumeControl" class="range-slider" min="0" max="100" value="80">
                    </div>
                    <button id="btnTestSound" class="btn-premium" style="margin-bottom: 0.4rem;">Testar Áudio Sintético 🔊</button>
                    
                    <!-- Controles de Web Push (Ocultos por padrão, exibidos se suportados) -->
                    <div id="pushControlRow" style="display: none; border-top: 1px solid var(--border-color); padding-top: 0.8rem; margin-top: 0.8rem; flex-direction: column; gap: 0.5rem; width: 100%;">
                        <span class="label-text" style="font-weight: 500;">Notificações Push (Celular)</span>
                        <button id="btnSubscribePush" class="btn-premium" style="width: 100%; margin: 0; background: linear-gradient(135deg, #ff4500, #ff8c00);">Ativar Notificações 🔔</button>
                        <span id="pushStatusMsg" class="label-text" style="font-size: 0.7rem; color: var(--text-secondary); text-align: center; width: 100%;">Verificando suporte a push...</span>
                    </div>
                </div>
            </div>

            <!-- Webhook Simulator (Superútil para testes rápidos e demonstração) -->
            <div class="panel-box">
                <h2 class="panel-title">Simulador de Webhook</h2>
                <form id="simulatorForm" class="sim-form" onsubmit="enviarSimulacao(event)">
                    <div class="form-group">
                        <label class="label-text" for="sim_nome">Nome do Cliente</label>
                        <input type="text" id="sim_nome" class="form-control" placeholder="Ex: Felipe Amorim" required>
                    </div>
                    <div class="form-group">
                        <label class="label-text" for="sim_tipo">Tipo do Evento</label>
                        <select id="sim_tipo" class="form-control" onchange="atualizarMensagemPadrao()">
                            <option value="SESSION_COMPLETE">SESSION_COMPLETE (Suporte Humano)</option>
                            <option value="SESSION_NEW">SESSION_NEW (Novo Atendimento)</option>
                            <option value="PANEL_CARD_STEP_CHANGE">PANEL_CARD_STEP_CHANGE (Novo Lead)</option>
                            <option value="MESSAGE_RECEIVED">MESSAGE_RECEIVED (Mensagem Cliente)</option>
                            <option value="MESSAGE_SENT">MESSAGE_SENT (Mensagem IA)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="label-text" for="sim_msg">Mensagem/Payload</label>
                        <textarea id="sim_msg" class="form-control" rows="2" placeholder="Descreva os detalhes..."></textarea>
                    </div>
                    <button type="submit" class="btn-send">Disparar Webhook (POST)</button>
                </form>
            </div>
            
        </div>

        <!-- Painel Central de Alertas -->
        <div class="dashboard-content">

            <!-- Filtros de Visualização -->
            <div class="filter-tabs" style="display: flex; align-items: center; width: 100%;">
                <button class="tab active" data-filter="todos">Todos (<span id="count-todos">0</span>)</button>
                <button class="tab" data-filter="atendimento_humano">Atendimentos (<span id="count-atendimento">0</span>)</button>
                <button class="tab" data-filter="novo_lead">Leads (<span id="count-lead">0</span>)</button>
                <button class="tab" data-filter="alerta_sistema">Erros/Avisos (<span id="count-sistema">0</span>)</button>
                
                <!-- Botão para Recarregar Histórico Manualmente -->
                <button id="btnRefreshHistory" style="margin-left: auto; background: rgba(255, 255, 255, 0.05); border: 1px solid var(--border-color); color: var(--text-secondary); padding: 0.4rem 0.8rem; border-radius: 8px; cursor: pointer; font-size: 0.8rem; font-weight: 500; transition: all 0.3s; display: flex; align-items: center; gap: 6px;" onmouseover="this.style.borderColor='var(--color-default)'; this.style.color='var(--text-primary)';" onmouseout="this.style.borderColor='var(--border-color)'; this.style.color='var(--text-secondary)';">
                    🔄 Recarregar Histórico
                </button>
            </div>

            <!-- Grid de Alertas em Tempo Real -->
            <div id="alertsGrid" class="alerts-grid">
                
                <!-- Estado Inicial Vazio -->
                <div id="emptyState" class="empty-state">
                    <div class="empty-icon">🔔</div>
                    <p class="empty-text">Aguardando novos chamados em tempo real...</p>
                </div>

            </div>

        </div>
