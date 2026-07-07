// Estado do Aplicativo
        const tenantSlug = window.SYSTEM_CONFIG ? window.SYSTEM_CONFIG.tenantSlug : '';
        const getTenantKey = (key) => tenantSlug ? `${tenantSlug}:${key}` : key;

        let chamadosList = [];
        let filterActive = 'todos';
        const alertasEnviados = new Set();
        
        // Calcula a diferença de relógio (skew) entre o navegador e o servidor
        let clockSkew = 0;
        if (window.SYSTEM_CONFIG && window.SYSTEM_CONFIG.serverTime) {
            const serverTimeDate = new Date(window.SYSTEM_CONFIG.serverTime);
            clockSkew = new Date() - serverTimeDate;
            console.log("Diferença de relógio (clock skew) detectada:", clockSkew / 1000, "segundos");
        }

        let audioContext = null;
        let audioMuted = localStorage.getItem(getTenantKey('audio_habilitado')) === 'false';
        let audioVolume = parseInt(localStorage.getItem(getTenantKey('audio_volume')) || '80', 10);
        
        // Controle de Repetição de Alerta Sonoro Urgente (Atendimento Humano)
        let urgentAudioIntervalId = null;
        let urgentAudioIntervalTime = 10000; // Tempo de repetição em milissegundos (padrão: 10s)
        
        // Elementos DOM
        const statusBadge = document.getElementById('statusBadge');
        const statusText = document.getElementById('statusText');
        const alertsGrid = document.getElementById('alertsGrid');
        const emptyState = document.getElementById('emptyState');
        const audioToggle = document.getElementById('audioToggle');
        const volumeControl = document.getElementById('volumeControl');
        const btnTestSound = document.getElementById('btnTestSound');
        const audioBanner = document.getElementById('audioBanner');
        const simNome = document.getElementById('sim_nome');
        const simTipo = document.getElementById('sim_tipo');
        const simMsg = document.getElementById('sim_msg');

        // Configuração Inicial de Mensagem de Simulação
        function atualizarMensagemPadrao() {
            if (!simTipo || !simMsg) return;
            const tipo = simTipo.value;
            if (tipo === 'CONTACT_TAG_UPDATE') {
                simMsg.value = 'ATENDIMENTO HUMANO';
            } else if (tipo === 'SESSION_COMPLETE') {
                simMsg.value = 'Sua conversa está sendo transferida. Por favor, aguarde um momento.';
            } else if (tipo === 'SESSION_NEW') {
                simMsg.value = 'Nova conversa iniciada pelo WhatsApp.';
            } else if (tipo === 'PANEL_CARD_STEP_CHANGE') {
                simMsg.value = 'Suporte Humano';
            } else if (tipo === 'MESSAGE_RECEIVED') {
                simMsg.value = 'boa noite';
            } else {
                simMsg.value = 'Como posso ajudar você hoje?';
            }
        }
        atualizarMensagemPadrao();

        // Determina a URL correta do CRM (Sessão de Chat ou Contato direto) com base no session_id
        function obterUrlChat(sessionId) {
            if (!sessionId) return '';
            if (sessionId.startsWith('contact:')) {
                const contactId = sessionId.replace('contact:', '');
                return `https://madeinai.wts.chat/contacts/${contactId}`;
            }
            return `https://madeinai.wts.chat/chat2/sessions/${sessionId}`;
        }

        // Helper para mapear o eventType bruto em estilo visual (classe, ícone, label)
        function obterEstiloEvento(item) {
            const tipo = item.tipo;
            const msg = item.mensagem || '';
            
            // Atendimento Humano (Urgente)
            if (tipo === 'atendimento_humano' || 
                (tipo === 'CONTACT_TAG_UPDATE' && msg.includes('Atendimento Humano')) ||
                (tipo === 'SESSION_COMPLETE' && /(transferida|aguarde|humano|suporte)/i.test(msg)) ||
                (/(PANEL_CARD_STEP_CHANGE|PANEL_CARD_UPDATE)/.test(tipo) && /(humano|suporte|atendente|human)/i.test(msg))) {
                return {
                    classe: 'type-atendimento_humano',
                    icon: '🚨',
                    label: 'Atendimento Humano'
                };
            }
            
            // Novo Lead (Verde)
            if (tipo === 'novo_lead' || 
                (/(PANEL_CARD_STEP_CHANGE|PANEL_CARD_UPDATE)/.test(tipo) && /(lead|ia|qualificação)/i.test(msg))) {
                return {
                    classe: 'type-novo_lead',
                    icon: '💵',
                    label: 'Novo Lead'
                };
            }
            
            // Novo Atendimento (Suave)
            if (tipo === 'SESSION_NEW' || tipo === 'novo_atendimento') {
                return {
                    classe: 'type-default',
                    icon: 'ℹ️',
                    label: 'Novo Atendimento'
                };
            }

            // Alerta do Sistema
            if (tipo === 'alerta_sistema') {
                return {
                    classe: 'type-alerta_sistema',
                    icon: '⚠️',
                    label: 'Alerta Sistema'
                };
            }
            
            // Padrão / Outros
            return {
                classe: 'type-default',
                icon: '🔔',
                label: 'Notificação'
            };
        }

        // Proactive check to see if browser is blocking audio autoplay
        function verificarBloqueioAudio() {
            try {
                const testContext = new (window.AudioContext || window.webkitAudioContext)();
                
                if (testContext.state === 'suspended') {
                    // Browser is blocking audio, show the activation banner
                    audioBanner.style.display = 'flex';
                    
                    // Automatically hide banner and activate audio on any page interaction
                    const autoUnlock = () => {
                        testContext.resume().then(() => {
                            if (testContext.state === 'running') {
                                audioContext = testContext;
                                audioBanner.style.display = 'none';
                                window.removeEventListener('click', autoUnlock);
                                window.removeEventListener('keydown', autoUnlock);
                            }
                        });
                    };
                    window.addEventListener('click', autoUnlock, { capture: true, passive: true });
                    window.addEventListener('keydown', autoUnlock, { capture: true, passive: true });
                } else {
                    // Autoplay is already allowed by user gesture history, keep banner hidden
                    audioContext = testContext;
                    audioBanner.style.display = 'none';
                }
            } catch (e) {
                // Fallback for older browsers
                audioBanner.style.display = 'flex';
            }
        }

        // 1. Lógica do Web Audio API (Sons Sintéticos em Fallback)
        function ativandoAudioCtx() {
            if (!audioContext) {
                audioContext = new (window.AudioContext || window.webkitAudioContext)();
            }
            if (audioContext.state === 'suspended') {
                audioContext.resume();
            }
            audioBanner.style.display = 'none';
        }

        // Tenta tocar notificacao.mp3. Se falhar ou der erro (não encontrado), usa sintetizador
        function ativarContextoAudio() {
            ativandoAudioCtx();
            tocarSomSintetico('default');
        }

        // Tenta tocar o arquivo de áudio configurado. Se falhar ou der erro, usa sintetizador
        function tocarAlertaSonoro(tipo) {
            const habilitado = !audioMuted;
            if (!habilitado) return;

            const audioSrc = (window.SYSTEM_CONFIG && window.SYSTEM_CONFIG.audioAlerta)
                ? window.SYSTEM_CONFIG.audioAlerta
                : 'assets/audio/notificacao.mp3';

            const mp3 = new Audio(audioSrc);
            
            // Força volume máximo (1.0) se for atendimento humano, senão usa o controle de volume persistido
            let volume = (tipo === 'atendimento_humano') ? 1.0 : (audioVolume / 100);
            mp3.volume = volume;
            
            mp3.play().catch(err => {
                console.warn("Arquivo de áudio customizado indisponível ou bloqueado. Usando som sintético Web Audio API.", err);
                tocarSomSintetico(tipo);
            });
        }

        // Gera sons diferentes para cada tipo de evento usando osciladores
        function tocarSomSintetico(tipo) {
            try {
                if (!audioContext) {
                    audioContext = new (window.AudioContext || window.webkitAudioContext)();
                }
                if (audioContext.state === 'suspended') {
                    // Se estiver suspenso, não toca e exibe o banner novamente
                    audioBanner.style.display = 'flex';
                    return;
                }

                // Força volume 100% (1.0) se for chamado urgente, senão respeita o slider persistido
                const volume = (tipo === 'atendimento_humano') ? 1.0 : (audioVolume / 100);
                
                // Volume geral
                const gainNode = audioContext.createGain();
                const ganhoBase = (tipo === 'atendimento_humano') ? 0.35 : 0.15; // Ganho extra para chamados humanos
                gainNode.gain.setValueAtTime(volume * ganhoBase, audioContext.currentTime);
                gainNode.connect(audioContext.destination);

                if (tipo === 'atendimento_humano') {
                    // Som Urgente de Volume Alto: Sirene bitonal oscilante e repetida (4 beeps)
                    const tempos = [0, 0.2, 0.4, 0.6];
                    tempos.forEach((t, index) => {
                        const freq = (index % 2 === 0) ? 987.77 : 783.99; // Nota Si5 e Sol5 alternando
                        const osc = audioContext.createOscillator();
                        osc.type = 'sine';
                        osc.frequency.setValueAtTime(freq, audioContext.currentTime + t);
                        
                        const indGain = audioContext.createGain();
                        indGain.gain.setValueAtTime(volume * 0.35, audioContext.currentTime + t);
                        indGain.gain.exponentialRampToValueAtTime(0.001, audioContext.currentTime + t + 0.18);
                        
                        osc.connect(indGain);
                        indGain.connect(audioContext.destination);
                        
                        osc.start(audioContext.currentTime + t);
                        osc.stop(audioContext.currentTime + t + 0.2);
                    });

                } else if (tipo === 'novo_lead') {
                    // Som de Sucesso (Arpejo musical alegre de 3 notas)
                    const notas = [392.00, 523.25, 659.25]; // G4, C5, E5
                    notas.forEach((freq, idx) => {
                        const osc = audioContext.createOscillator();
                        osc.type = 'triangle';
                        osc.frequency.setValueAtTime(freq, audioContext.currentTime + (idx * 0.08));
                        
                        const indGain = audioContext.createGain();
                        indGain.gain.setValueAtTime(volume * 0.15, audioContext.currentTime + (idx * 0.08));
                        indGain.gain.exponentialRampToValueAtTime(0.001, audioContext.currentTime + (idx * 0.08) + 0.2);
                        
                        osc.connect(indGain);
                        indGain.connect(audioContext.destination);
                        
                        osc.start(audioContext.currentTime + (idx * 0.08));
                        osc.stop(audioContext.currentTime + (idx * 0.08) + 0.25);
                    });

                } else if (tipo === 'alerta_sistema') {
                    // Som de Aviso / Erro (Onda dente-de-serra grave de alerta rápido)
                    const osc = audioContext.createOscillator();
                    osc.type = 'sawtooth';
                    osc.frequency.setValueAtTime(180, audioContext.currentTime);
                    
                    const indGain = audioContext.createGain();
                    indGain.gain.setValueAtTime(volume * 0.1, audioContext.currentTime);
                    indGain.gain.linearRampToValueAtTime(0, audioContext.currentTime + 0.4);
                    
                    osc.connect(indGain);
                    indGain.connect(audioContext.destination);
                    
                    osc.start(audioContext.currentTime);
                    osc.stop(audioContext.currentTime + 0.4);
                } else {
                    // Som Informativo Padrão (Plop suave)
                    const osc = audioContext.createOscillator();
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(523.25, audioContext.currentTime); // C5
                    osc.frequency.exponentialRampToValueAtTime(261.63, audioContext.currentTime + 0.12); // C4
                    
                    const indGain = audioContext.createGain();
                    indGain.gain.setValueAtTime(volume * 0.15, audioContext.currentTime);
                    indGain.gain.exponentialRampToValueAtTime(0.001, audioContext.currentTime + 0.15);
                    
                    osc.connect(indGain);
                    indGain.connect(audioContext.destination);
                    
                    osc.start(audioContext.currentTime);
                    osc.stop(audioContext.currentTime + 0.18);
                }
            } catch (e) {
                console.error("Erro ao reproduzir áudio:", e);
            }
        }

        // Inicialização e listeners dos controles de áudio locais (persistidos)
        if (audioToggle) {
            audioToggle.checked = !audioMuted;
            audioToggle.addEventListener('change', () => {
                audioMuted = !audioToggle.checked;
                localStorage.setItem(getTenantKey('audio_habilitado'), String(audioToggle.checked));
            });
        }

        if (volumeControl) {
            volumeControl.value = audioVolume;
            const volValue = document.getElementById('volumeValue');
            if (volValue) volValue.textContent = audioVolume + '%';

            volumeControl.addEventListener('input', () => {
                audioVolume = parseInt(volumeControl.value, 10);
                localStorage.setItem(getTenantKey('audio_volume'), String(audioVolume));
                if (volValue) volValue.textContent = audioVolume + '%';
            });
        }

        if (btnTestSound) {
            btnTestSound.addEventListener('click', () => {
                ativandoAudioCtx();
                // Testar com o som ativo configurado
                tocarAlertaSonoro(simTipo ? simTipo.value : 'default');
            });
        }

        // 2. Conectar com o SSE-STREAM (EventSource)
        function iniciarConexaoSSE() {
            // Obtém o maior ID atual na lista local para sincronizar o cursor do stream
            const lastId = chamadosList.length > 0 ? Math.max(...chamadosList.map(c => c.id)) : 0;

            // Instancia o EventSource nativo apontando para o endpoint PHP passando last_id e JWT token
            const token = window.SYSTEM_CONFIG ? window.SYSTEM_CONFIG.jwtToken : '';
            const source = new EventSource('sse-stream.php?last_id=' + lastId + '&token=' + encodeURIComponent(token));

            // Ouvinte de erros de autenticação disparados pelo SSE
            source.addEventListener('auth_error', function(e) {
                console.error("Erro de autenticação no SSE:", e.data);
                window.location.href = 'login';
            });

            // Quando a conexão é aberta com sucesso
            source.onopen = function() {
                statusBadge.className = 'status-badge connected';
                statusText.textContent = 'Online';
                console.log("SSE conectado com last_id:", lastId);
            };

            // Quando ocorrem erros de conexão (ex: timeout da VPS, reinicializações de rede)
            // O EventSource nativo reconecta AUTOMATICAMENTE em caso de falha.
            source.onerror = function(err) {
                statusBadge.className = 'status-badge disconnected';
                statusText.textContent = 'Offline';
                console.warn("SSE desconectado. Tentando reconectar automaticamente...", err);
            };

            // Ouvinte de mensagens SSE recebidas do endpoint PHP
            source.onmessage = function(event) {
                try {
                    const data = JSON.parse(event.data);
                    
                    if (data && data.action === 'resolve') {
                        // Remove o card da lista local
                        chamadosList = chamadosList.filter(item => item.id !== data.id);
                        renderizarAlertas();
                        atualizarContadores();
                    } else if (data && data.id) {
                        adicionarChamado(data);
                    }
                } catch (e) {
                    console.error("Erro ao decodificar JSON do evento SSE:", e);
                }
            };
        }

        // 3. Processar novo Chamado/Evento e injetar no Dashboard
        function adicionarChamado(chamado) {
            // Evita duplicados na lista da sessão
            if (chamadosList.some(item => item.id === chamado.id)) return;

            // Insere no início da array local (mais recente primeiro)
            chamadosList.unshift(chamado);
            
            // Toca a notificação correspondente baseando-se no estilo resolvido
            const estilo = obterEstiloEvento(chamado);
            if (estilo.classe === 'type-atendimento_humano') {
                tocarAlertaSonoro('atendimento_humano');
            } else if (estilo.classe === 'type-novo_lead') {
                tocarAlertaSonoro('novo_lead');
            } else if (estilo.classe === 'type-alerta_sistema') {
                tocarAlertaSonoro('alerta_sistema');
            } else {
                tocarAlertaSonoro('default');
            }
            
            // Atualiza a renderização na tela
            renderizarAlertas();
            atualizarContadores();
        }

        // Envia requisição para dispensar o chamado no banco e remove o card da tela
        function resolverChamado(id, btnElement) {
            btnElement.disabled = true;
            btnElement.textContent = 'Dispensando...';

            fetch('resolver.php?id=' + id, {
                method: 'POST'
            })
            .then(res => {
                if (!res.ok) throw new Error('Status HTTP ' + res.status);
                return res.json();
            })
            .then(data => {
                if (data.sucesso) {
                    const card = btnElement.closest('.alert-card');
                    card.style.animation = 'fade-out 0.3s forwards';
                    
                    setTimeout(() => {
                        chamadosList = chamadosList.filter(item => item.id !== id);
                        renderizarAlertas();
                        atualizarContadores();
                    }, 300);
                } else {
                    alert('Erro ao dispensar chamado: ' + data.mensagem);
                    btnElement.disabled = false;
                    btnElement.textContent = 'Dispensar';
                }
            })
            .catch(err => {
                console.error(err);
                alert('Falha na comunicação com o servidor ao tentar dispensar o chamado.');
                btnElement.disabled = false;
                btnElement.textContent = 'Dispensar';
            });
        }

        // Envia requisição para dispensar o chamado urgente em tela cheia
        function resolverChamadoUrgente(id, btnElement) {
            btnElement.disabled = true;
            btnElement.textContent = 'Dispensando...';

            fetch('resolver.php?id=' + id, {
                method: 'POST'
            })
            .then(res => {
                if (!res.ok) throw new Error('Status HTTP ' + res.status);
                return res.json();
            })
            .then(data => {
                if (data.sucesso) {
                    // Remove do array local
                    chamadosList = chamadosList.filter(item => item.id !== id);
                    
                    // Atualiza a tela (vai fechar o modal ou passar para o próximo se houver)
                    renderizarAlertas();
                    atualizarContadores();
                } else {
                    alert('Erro ao dispensar chamado: ' + data.mensagem);
                }
            })
            .catch(err => {
                console.error(err);
                alert('Falha na comunicação com o servidor ao tentar dispensar o chamado.');
            })
            .finally(() => {
                btnElement.disabled = false;
                btnElement.textContent = 'Dispensar \u2715';
            });
        }

        // Dispensa o chamado de forma silenciosa e imediata ao clicar no link de redirecionamento
        function resolverChamadoSilencioso(id, linkElement) {
            const card = linkElement.closest('.alert-card');
            if (card) {
                card.style.animation = 'fade-out 0.3s forwards';
            }
            
            setTimeout(() => {
                chamadosList = chamadosList.filter(item => item.id !== id);
                renderizarAlertas();
                atualizarContadores();
            }, 300);

            // Dispara para o banco de dados em segundo plano
            fetch('resolver.php?id=' + id, { method: 'POST' })
                .catch(err => console.error("Erro ao dispensar em background:", err));
        }

        // Dispensa o chamado urgente do modal de forma silenciosa e imediata
        function resolverChamadoUrgenteSilencioso(id) {
            chamadosList = chamadosList.filter(item => item.id !== id);
            renderizarAlertas();
            atualizarContadores();

            // Dispara para o banco de dados em segundo plano
            fetch('resolver.php?id=' + id, { method: 'POST' })
                .catch(err => console.error("Erro ao dispensar em background:", err));
        }

        // Renderiza o grid de alertas baseando-se no filtro ativo
        function renderizarAlertas() {
            // Verifica se existe algum chamado de Atendimento Humano (Urgente) para exibir em Fullscreen
            const chamadoUrgente = chamadosList.find(c => {
                const estilo = obterEstiloEvento(c);
                return estilo.classe === 'type-atendimento_humano';
            });

            const modalUrgente = document.getElementById('urgentAlertModal');
            if (chamadoUrgente) {
                document.getElementById('urgentModalClient').textContent = chamadoUrgente.nome_cliente || 'Desconhecido';
                document.getElementById('urgentModalMsg').textContent = chamadoUrgente.mensagem || 'Requer suporte humano.';
                
                const horaFormatada = new Date(chamadoUrgente.criado_em.replace(/-/g, "/")).toLocaleTimeString('pt-BR', {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
                document.getElementById('urgentModalTime').textContent = '\u23F3 Recebido \u00e0s ' + horaFormatada;
                
                const btnUrgentResolve = document.getElementById('btnUrgentResolve');
                btnUrgentResolve.onclick = function() {
                    resolverChamadoUrgente(chamadoUrgente.id, btnUrgentResolve);
                };

                const btnUrgentChat = document.getElementById('btnUrgentChat');
                if (chamadoUrgente.session_id) {
                    btnUrgentChat.href = obterUrlChat(chamadoUrgente.session_id);
                    if (chamadoUrgente.session_id.startsWith('contact:')) {
                        btnUrgentChat.textContent = 'VER CONTATO 👤';
                    } else {
                        btnUrgentChat.textContent = 'ATENDER CONVERSA 💬';
                    }
                    
                    // Vincula ação de auto-dispensar silencioso ao clicar
                    btnUrgentChat.onclick = function() {
                        resolverChamadoUrgenteSilencioso(chamadoUrgente.id);
                    };
                    
                    btnUrgentChat.style.display = 'inline-flex';
                } else {
                    btnUrgentChat.style.display = 'none';
                }
                
                modalUrgente.style.display = 'flex';
            } else {
                modalUrgente.style.display = 'none';
            }

            const alertsGrid = document.getElementById('alertsGrid');
            if (!alertsGrid) {
                // Se não estiver na dashboard, apenas gerencia os sons repetitivos urgentes
                gerenciarAlertasSonorosUrgentes();
                return;
            }

            // Filtrar itens baseando-se no estilo resolvido
            const itensFiltrados = chamadosList.filter(item => {
                if (filterActive === 'todos') return true;
                const estilo = obterEstiloEvento(item);
                
                if (filterActive === 'atendimento_humano') return estilo.classe === 'type-atendimento_humano';
                if (filterActive === 'novo_lead') return estilo.classe === 'type-novo_lead';
                if (filterActive === 'alerta_sistema') return estilo.classe === 'type-alerta_sistema';
                return false;
            });

            // Limpa o grid
            alertsGrid.innerHTML = '';

            if (itensFiltrados.length === 0) {
                alertsGrid.appendChild(emptyState);
                emptyState.style.display = 'flex';
                return;
            }

            emptyState.style.display = 'none';

             // Montar cada card dinamicamente
             itensFiltrados.forEach(item => {
                 const estilo = obterEstiloEvento(item);
                 const card = document.createElement('div');
                 card.className = `alert-card ${estilo.classe}`;
                 
                 // Obter dados dinâmicos do estilo
                 const icon = estilo.icon;
                 const labelTipo = estilo.label;
 
                 // Formatar hora
                 const horaFormatada = new Date(item.criado_em.replace(/-/g, "/")).toLocaleTimeString('pt-BR', {
                     hour: '2-digit',
                     minute: '2-digit',
                     second: '2-digit'
                 });
 
                 // Calcula tempo de espera em minutos para atendimento humano (ajustado pelo clockSkew do servidor)
                 const criadoEmDate = new Date(item.criado_em.replace(/-/g, "/"));
                 const adjustedNow = new Date(new Date().getTime() - clockSkew);
                 const diffSeconds = Math.floor((adjustedNow - criadoEmDate) / 1000);
                 const diffMinutes = Math.floor(diffSeconds / 60);
                 const limiteMinutos = (window.SYSTEM_CONFIG && window.SYSTEM_CONFIG.tempoLimiteEspera) ? parseInt(window.SYSTEM_CONFIG.tempoLimiteEspera, 10) : 5;
 
                 let waitingHTML = '';
                 if (estilo.classe === 'type-atendimento_humano') {
                     if (diffMinutes >= limiteMinutos) {
                         waitingHTML = `<span class="waiting-badge warning" style="animation: pulse-border 1.5s infinite; background: rgba(255, 71, 87, 0.15); color: #ff4757; font-weight: 600; font-size: 0.72rem; padding: 0.2rem 0.5rem; border-radius: 6px; border: 1px solid rgba(255, 71, 87, 0.3); display: inline-flex; align-items: center; gap: 4px;">⚠️ SEM RESPOSTA HÁ ${diffMinutes}m</span>`;
                         
                         // Envia notificação nativa uma única vez por chamado ao bater o limite
                         if (!alertasEnviados.has(item.id)) {
                             alertasEnviados.add(item.id);
                             
                             // Dispara som de alerta urgente imediatamente
                             tocarAlertaSonoro('atendimento_humano');
                             
                             // Dispara notificação do navegador se permitido
                             if (Notification.permission === 'granted') {
                                 try {
                                     new Notification("🚨 Alerta de Espera - Central de Alertas", {
                                         body: `O cliente "${item.nome_cliente || 'Desconhecido'}" está aguardando atendimento humano há mais de ${limiteMinutos} minutos!`,
                                         icon: 'assets/img/icon_192.png'
                                     });
                                 } catch (err) {
                                     console.error("Erro ao disparar notificação de mesa:", err);
                                 }
                             }
                             
                             // Envia o alerta push para os administradores da empresa via backend
                             fetch('alerta_atraso.php?id=' + item.id, { method: 'POST' })
                                 .then(res => res.json())
                                 .then(resData => {
                                     console.log('Alerta de atraso enviado via push:', resData);
                                 })
                                 .catch(err => console.error('Erro ao enviar alerta de atraso push:', err));
                         }
                     } else {
                         waitingHTML = `<span class="waiting-badge" style="background: rgba(30, 144, 255, 0.1); color: var(--color-default); font-weight: 500; font-size: 0.72rem; padding: 0.2rem 0.5rem; border-radius: 6px; border: 1px solid rgba(30, 144, 255, 0.2); display: inline-flex; align-items: center; gap: 4px;">⏱️ Esperando há ${diffMinutes}m</span>`;
                     }
                 }
 
                 let actionsHTML = '';
                 if (item.session_id) {
                     const labelText = item.session_id.startsWith('contact:') ? 'Ver Contato 👤' : 'Atender 💬';
                     actionsHTML += `<a href="${obterUrlChat(item.session_id)}" target="_blank" class="btn-action btn-open-chat" onclick="resolverChamadoSilencioso(${item.id}, this)">${labelText}</a>`;
                 }
                 actionsHTML += `<button class="btn-action btn-resolve" onclick="resolverChamado(${item.id}, this)">Dispensar</button>`;
 
                 card.innerHTML = `
                     <div class="card-details">
                         <div class="card-icon">${icon}</div>
                         <div class="card-info">
                             <div class="card-header-row">
                                 <span class="client-name">${item.nome_cliente || 'N/A'}</span>
                                 <span class="badge-type">${labelTipo}</span>
                                 ${waitingHTML}
                                 <span class="card-time">🕒 ${horaFormatada}</span>
                             </div>
                             <p class="card-msg">${item.mensagem || 'Sem mensagem descritiva.'}</p>
                         </div>
                     </div>
                     <div class="card-actions">
                         ${actionsHTML}
                     </div>
                 `;
                 alertsGrid.appendChild(card);
             });

            // Gerencia a reprodução repetitiva da sirene para chamados urgentes ativos
            gerenciarAlertasSonorosUrgentes();
        }

        // Inicia ou para o cronômetro da sirene baseado nos chamados urgentes ativos
        function gerenciarAlertasSonorosUrgentes() {
            const temUrgente = chamadosList.some(c => {
                const estilo = obterEstiloEvento(c);
                return estilo.classe === 'type-atendimento_humano';
            });

            if (temUrgente) {
                if (!urgentAudioIntervalId) {
                    console.log("Iniciando sirene de repetição urgente a cada " + (urgentAudioIntervalTime / 1000) + " segundos.");
                    urgentAudioIntervalId = setInterval(() => {
                        tocarAlertaSonoro('atendimento_humano');
                    }, urgentAudioIntervalTime);
                }
            } else {
                if (urgentAudioIntervalId) {
                    console.log("Parando sirene de repetição urgente (sem chamados ativos).");
                    clearInterval(urgentAudioIntervalId);
                    urgentAudioIntervalId = null;
                }
            }
        }

        // Atualizar badges numéricas das abas superiores
        function atualizarContadores() {
            const countTodos = document.getElementById('count-todos');
            if (!countTodos) return; // Pula se não estiver na tela do dashboard
            
            countTodos.textContent = chamadosList.length;
            
            const contadores = {
                atendimento_humano: 0,
                novo_lead: 0,
                alerta_sistema: 0
            };
            
            chamadosList.forEach(item => {
                const estilo = obterEstiloEvento(item);
                if (estilo.classe === 'type-atendimento_humano') {
                    contadores.atendimento_humano++;
                } else if (estilo.classe === 'type-novo_lead') {
                    contadores.novo_lead++;
                } else if (estilo.classe === 'type-alerta_sistema') {
                    contadores.alerta_sistema++;
                }
            });

            document.getElementById('count-atendimento').textContent = contadores.atendimento_humano;
            document.getElementById('count-lead').textContent = contadores.novo_lead;
            document.getElementById('count-sistema').textContent = contadores.alerta_sistema;
        }

        // Configuração das Abas de Filtros
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelector('.tab.active').classList.remove('active');
                tab.classList.add('active');
                filterActive = tab.getAttribute('data-filter');
                renderizarAlertas();
            });
        });

        // 4. Lógica do Webhook Simulator
        // Executa um disparo HTTP POST nativo para 'webhook.php' e simula o backend do Made in AI
        function enviarSimulacao(e) {
            e.preventDefault();
            ativandoAudioCtx(); // Desbloqueia áudio caso o usuário clique em enviar primeiro

            const btn = e.target.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Enviando...';

            const nome = simNome.value || 'Cliente Teste';
            const msg = simMsg.value || '';
            const event = simTipo.value;

            // Constrói a estrutura real do JSON que o Made in AI enviaria
            let payload = {
                eventType: event,
                date: new Date().toISOString(),
                content: {}
            };

            if (event === 'CONTACT_TAG_UPDATE') {
                payload.content = {
                    id: (typeof crypto !== 'undefined' && crypto.randomUUID) ? crypto.randomUUID() : 'sim-id-' + Math.random(),
                    sessionId: 'sim-session-' + Math.floor(Math.random() * 100000), // Simula um ID de sessão para link direto
                    name: nome,
                    phonenumberFormatted: '(22) 98131-0167',
                    tags: [msg] // A tag a ser adicionada/atualizada (ex: ATENDIMENTO HUMANO)
                };
            } else if (event === 'SESSION_COMPLETE') {
                payload.content = {
                    id: (typeof crypto !== 'undefined' && crypto.randomUUID) ? crypto.randomUUID() : 'sim-id-' + Math.random(),
                    sessionId: 'sim-session-' + Math.floor(Math.random() * 100000), // Simula um ID de sessão para link direto
                    status: 'COMPLETED',
                    lastMessageText: msg,
                    contactDetails: { name: nome }
                };
            } else if (event === 'SESSION_NEW') {
                payload.content = {
                    id: (typeof crypto !== 'undefined' && crypto.randomUUID) ? crypto.randomUUID() : 'sim-id-' + Math.random(),
                    sessionId: 'sim-session-' + Math.floor(Math.random() * 100000), // Simula um ID de sessão para link direto
                    status: 'PENDING',
                    contactDetails: {
                        name: nome,
                        phonenumberFormatted: '(22) 98131-0167'
                    }
                };
            } else if (event === 'PANEL_CARD_STEP_CHANGE' || event === 'PANEL_CARD_UPDATE') {
                payload.content = {
                    id: (typeof crypto !== 'undefined' && crypto.randomUUID) ? crypto.randomUUID() : 'sim-id-' + Math.random(),
                    title: nome,
                    stepTitle: msg, // Passa o título da coluna
                    contacts: [{ name: nome, phonenumberFormatted: '(22) 99968-4330' }]
                };
            } else if (event === 'MESSAGE_RECEIVED') {
                payload.content = {
                    text: msg,
                    details: { from: nome }
                };
            } else if (event === 'MESSAGE_SENT') {
                payload.content = {
                    text: msg,
                    origin: 'AI',
                    details: { to: nome }
                };
            }

            const webhookToken = window.SYSTEM_CONFIG ? window.SYSTEM_CONFIG.webhookToken : '';
            fetch('webhook.php?token=' + encodeURIComponent(webhookToken), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            })
            .then(res => {
                if (!res.ok) {
                    throw new Error('Falha no simulador: Status HTTP ' + res.status);
                }
                return res.json();
            })
            .then(data => {
                console.log('Resposta do simulador de webhook:', data);
                // Limpar campos de input mantendo o tipo
                simNome.value = '';
                simNome.focus();
            })
            .catch(err => {
                alert('Erro ao disparar webhook de simulação. Verifique se o PHP está ativo no localhost e a conexão do banco funciona!\nErro: ' + err.message);
                console.error(err);
            })
            .finally(() => {
                btn.disabled = false;
                btn.textContent = 'Disparar Webhook (POST)';
            });
        }

        // Função para buscar o histórico de chamados manualmente ou no load
        function carregarHistorico() {
            const btn = document.getElementById('btnRefreshHistory');
            if (btn) {
                btn.textContent = '🔄 Carregando...';
                btn.style.opacity = '0.7';
            }

            return fetch('historico.php')
                .then(res => {
                    if (!res.ok) throw new Error('Falha HTTP: ' + res.status);
                    return res.json();
                })
                .then(dados => {
                    if (Array.isArray(dados)) {
                        // Limpa e atualiza com os dados do banco
                        chamadosList = dados;
                        renderizarAlertas();
                        atualizarContadores();
                    }
                })
                .catch(err => {
                    console.warn('Não foi possível carregar o histórico de chamados:', err);
                })
                .finally(() => {
                    if (btn) {
                        btn.textContent = '🔄 Recarregar Histórico';
                        btn.style.opacity = '1';
                    }
                });
        }

        // ==========================================
        // LÓGICA DE PROMPT DE INSTALAÇÃO DO PWA
        // ==========================================
        let deferredPrompt = null;

        function inicializarPromptInstalacaoPWA() {
            const pwaInstallPanel = document.getElementById('pwaInstallPanel');
            const btnInstallPWA = document.getElementById('btnInstallPWA');
            const iosInstallInstructions = document.getElementById('iosInstallInstructions');

            if (!pwaInstallPanel) return;

            // 1. Detecta se já está rodando em modo standalone (PWA instalado)
            const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;

            if (isStandalone) {
                // Já está instalado, garante que o painel fique oculto
                pwaInstallPanel.style.display = 'none';
                
                // Tenta bloquear a tela na orientação retrato (portrait) se suportado
                if (window.screen && window.screen.orientation && window.screen.orientation.lock) {
                    window.screen.orientation.lock('portrait').catch(err => {
                        console.log('Bloqueio de orientação via API de tela indisponível ou recusado:', err);
                    });
                }
                return;
            }

            // 2. Detecta se o dispositivo é iOS (iPhone/iPad)
            const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;

            if (isIOS) {
                // Safari iOS não dispara beforeinstallprompt, mas podemos mostrar instruções manuais
                pwaInstallPanel.style.display = 'block';
                if (btnInstallPWA) btnInstallPWA.style.display = 'none';
                if (iosInstallInstructions) iosInstallInstructions.style.display = 'block';
            } else {
                // Escuta o evento de instalação do Chrome/Android/Edge
                window.addEventListener('beforeinstallprompt', (e) => {
                    e.preventDefault();
                    deferredPrompt = e;
                    pwaInstallPanel.style.display = 'block';
                });

                if (btnInstallPWA) {
                    btnInstallPWA.addEventListener('click', () => {
                        if (!deferredPrompt) return;
                        deferredPrompt.prompt();
                        deferredPrompt.userChoice.then((choiceResult) => {
                            if (choiceResult.outcome === 'accepted') {
                                console.log('Usuário aceitou a instalação do PWA');
                                pwaInstallPanel.style.display = 'none';
                            } else {
                                console.log('Usuário recusou a instalação do PWA');
                            }
                            deferredPrompt = null;
                        });
                    });
                }

                // Oculta o painel caso o app seja instalado com sucesso fora do botão
                window.addEventListener('appinstalled', () => {
                    console.log('PWA instalado com sucesso!');
                    pwaInstallPanel.style.display = 'none';
                    deferredPrompt = null;
                });
            }
        }

        // ==========================================
        // LÓGICA DE NOTIFICAÇÕES WEB PUSH PWA
        // ==========================================
        const VAPID_PUBLIC_KEY = 'BOZa81Pmnrmb5N7i9XMDa4tgI_E_Im_6_lDH7dTjwwBn2aVm5nhk7UWxTDrmsJyZsSU96KPXhYO8GFoesloNDlw';

        // Converte a chave pública VAPID de Base64 para Uint8Array
        function urlB64ToUint8Array(base64String) {
            const padding = '='.repeat((4 - base64String.length % 4) % 4);
            const base64 = (base64String + padding)
                .replace(/\-/g, '+')
                .replace(/_/g, '/');

            const rawData = window.atob(base64);
            const outputArray = new Uint8Array(rawData.length);

            for (let i = 0; i < rawData.length; ++i) {
                outputArray[i] = rawData.charCodeAt(i);
            }
            return outputArray;
        }

        function inicializarPushNotifications() {
            const pushControlRow = document.getElementById('pushControlRow');
            const btnSubscribePush = document.getElementById('btnSubscribePush');
            const pushStatusMsg = document.getElementById('pushStatusMsg');

            if (!pushControlRow || !btnSubscribePush) return;

            // Verifica compatibilidade com Service Worker e PushManager
            if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
                console.warn('Este navegador não suporta Web Push.');
                pushStatusMsg.textContent = 'Não suportado neste navegador.';
                return;
            }

            // Exibe a linha de controle do Push
            pushControlRow.style.display = 'flex';

            // Verifica o estado atual de inscrição
            navigator.serviceWorker.ready.then(reg => {
                reg.pushManager.getSubscription().then(subscription => {
                    atualizarInterfacePush(subscription);
                });
            });

            btnSubscribePush.addEventListener('click', () => {
                ativandoAudioCtx(); // Desbloqueia também o áudio ao interagir
                navigator.serviceWorker.ready.then(reg => {
                    reg.pushManager.getSubscription().then(subscription => {
                        if (subscription) {
                            desinscreverUsuarioPush(subscription);
                        } else {
                            inscreverUsuarioPush(reg);
                        }
                    });
                });
            });
        }

        function atualizarInterfacePush(subscription) {
            const btnSubscribePush = document.getElementById('btnSubscribePush');
            const pushStatusMsg = document.getElementById('pushStatusMsg');

            if (!btnSubscribePush || !pushStatusMsg) return;

            if (subscription) {
                btnSubscribePush.textContent = 'Desativar Notificações 🔕';
                btnSubscribePush.style.background = 'linear-gradient(135deg, #747d8c, #2f3542)';
                pushStatusMsg.textContent = 'Notificações ativas no celular!';
                pushStatusMsg.style.color = '#2ed573';
            } else {
                btnSubscribePush.textContent = 'Ativar Notificações 🔔';
                btnSubscribePush.style.background = 'linear-gradient(135deg, #ff4500, #ff8c00)';
                
                if (Notification.permission === 'denied') {
                    pushStatusMsg.textContent = 'Permissão negada no navegador.';
                    pushStatusMsg.style.color = '#ff4757';
                } else {
                    pushStatusMsg.textContent = 'Notificações push inativas.';
                    pushStatusMsg.style.color = 'var(--text-secondary)';
                }
            }
        }

        function inscreverUsuarioPush(reg) {
            const btnSubscribePush = document.getElementById('btnSubscribePush');
            const pushStatusMsg = document.getElementById('pushStatusMsg');
            
            btnSubscribePush.disabled = true;
            pushStatusMsg.textContent = 'Solicitando permissão...';

            const options = {
                userVisibleOnly: true,
                applicationServerKey: urlB64ToUint8Array(VAPID_PUBLIC_KEY)
            };

            reg.pushManager.subscribe(options)
                .then(subscription => {
                    console.log('Inscrição do Push realizada:', subscription);
                    
                    return fetch('subscrever.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: json_serialize_subscription(subscription)
                    })
                    .then(res => {
                        if (!res.ok) throw new Error('Falha HTTP ao salvar no servidor');
                        return res.json();
                    })
                    .then(data => {
                        if (data.sucesso) {
                            atualizarInterfacePush(subscription);
                        } else {
                            throw new Error(data.mensagem || 'Erro do servidor');
                        }
                    });
                })
                .catch(err => {
                    console.error('Erro ao inscrever para Push:', err);
                    atualizarInterfacePush(null);
                    pushStatusMsg.textContent = 'Erro ao ativar. Tente novamente.';
                    pushStatusMsg.style.color = '#ff4757';
                })
                .finally(() => {
                    btnSubscribePush.disabled = false;
                });
        }

        function desinscreverUsuarioPush(subscription) {
            const btnSubscribePush = document.getElementById('btnSubscribePush');
            const pushStatusMsg = document.getElementById('pushStatusMsg');

            btnSubscribePush.disabled = true;
            pushStatusMsg.textContent = 'Desativando...';

            subscription.unsubscribe()
                .then(successful => {
                    if (successful) {
                        console.log('Inscrição de Push cancelada.');
                        atualizarInterfacePush(null);
                    } else {
                        throw new Error('Unsubscribe falhou no navegador');
                    }
                })
                .catch(err => {
                    console.error('Erro ao cancelar inscrição:', err);
                    pushStatusMsg.textContent = 'Falha ao desativar.';
                    pushStatusMsg.style.color = '#ff4757';
                })
                .finally(() => {
                    btnSubscribePush.disabled = false;
                });
        }

        function json_serialize_subscription(subscription) {
            const key = subscription.getKey ? subscription.getKey('p256dh') : null;
            const auth = subscription.getKey ? subscription.getKey('auth') : null;
            
            return JSON.stringify({
                endpoint: subscription.endpoint,
                keys: {
                    p256dh: key ? btoa(String.fromCharCode(...new Uint8Array(key))) : null,
                    auth: auth ? btoa(String.fromCharCode(...new Uint8Array(auth))) : null
                }
            });
        }

        // Iniciar tudo ao carregar a página
        window.addEventListener('DOMContentLoaded', () => {
            // 1. Verifica proativamente se o navegador bloqueia áudio
            verificarBloqueioAudio();

            // 2. Carrega o histórico inicial
            carregarHistorico().finally(() => {
                // 3. Conecta ao canal de eventos em tempo real (SSE)
                iniciarConexaoSSE();
            });

            // 4. Ouvinte para recarregar manual do histórico ao clicar no botão
            const btnRefreshHistory = document.getElementById('btnRefreshHistory');
            if (btnRefreshHistory) {
                btnRefreshHistory.addEventListener('click', carregarHistorico);
            }

            // Atualiza os contadores de tempo e alertas de 5 minutos a cada 10 segundos
            setInterval(renderizarAlertas, 10000);

            // 5. Registra o Service Worker do PWA
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.register('sw.js')
                    .then(reg => {
                        console.log('Service Worker do PWA registrado com escopo:', reg.scope);
                        // Inicializa lógicas que dependem do Service Worker
                        inicializarPromptInstalacaoPWA();
                        inicializarPushNotifications();
                    })
                    .catch(err => console.error('Erro ao registrar Service Worker do PWA:', err));
            }
        });