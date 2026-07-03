// Estado do Aplicativo
        let chamadosList = [];
        let filterActive = 'todos';
        let audioContext = null;
        let audioMuted = false;
        
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

        // Tenta tocar notificacao.mp3. Se falhar ou der erro (não encontrado), usa sintetizador
        function tocarAlertaSonoro(tipo) {
            if (audioMuted || !audioToggle.checked) return;

            const mp3 = new Audio('assets/audio/notificacao.mp3');
            
            // Força volume máximo (1.0) se for atendimento humano, senão usa o controle de volume
            let volume = (tipo === 'atendimento_humano') ? 1.0 : (volumeControl.value / 100);
            mp3.volume = volume;
            
            mp3.play().catch(err => {
                console.warn("Arquivo notificacao.mp3 indisponível ou bloqueado. Usando som sintético Web Audio API.");
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

                // Força volume 100% (1.0) se for chamado urgente, senão respeita o slider
                const volume = (tipo === 'atendimento_humano') ? 1.0 : (volumeControl.value / 100);
                
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

        // Listener dos controles de som
        btnTestSound.addEventListener('click', () => {
            ativandoAudioCtx();
            tocarSomSintetico(simTipo.value);
        });

        audioToggle.addEventListener('change', () => {
            audioMuted = !audioToggle.checked;
        });

        // 2. Conectar com o SSE-STREAM (EventSource)
        function iniciarConexaoSSE() {
            // Obtém o maior ID atual na lista local para sincronizar o cursor do stream
            const lastId = chamadosList.length > 0 ? Math.max(...chamadosList.map(c => c.id)) : 0;

            // Instancia o EventSource nativo apontando para o endpoint PHP passando last_id
            const source = new EventSource('sse-stream.php?last_id=' + lastId);

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
                    
                    // Valida se o objeto não é nulo/heartbeat
                    if (data && data.id) {
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
                    btnUrgentChat.href = `https://madeinai.wts.chat/chat2/sessions/${chamadoUrgente.session_id}`;
                    btnUrgentChat.style.display = 'inline-flex';
                } else {
                    btnUrgentChat.style.display = 'none';
                }
                
                modalUrgente.style.display = 'flex';
            } else {
                modalUrgente.style.display = 'none';
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

                let actionsHTML = '';
                if (item.session_id) {
                    actionsHTML += `<a href="https://madeinai.wts.chat/chat2/sessions/${item.session_id}" target="_blank" class="btn-action btn-open-chat">Atender 💬</a>`;
                }
                actionsHTML += `<button class="btn-action btn-resolve" onclick="resolverChamado(${item.id}, this)">Dispensar</button>`;

                card.innerHTML = `
                    <div class="card-details">
                        <div class="card-icon">${icon}</div>
                        <div class="card-info">
                            <div class="card-header-row">
                                <span class="client-name">${item.nome_cliente || 'N/A'}</span>
                                <span class="badge-type">${labelTipo}</span>
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
            document.getElementById('count-todos').textContent = chamadosList.length;
            
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

            fetch('webhook.php', {
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
            document.getElementById('btnRefreshHistory').addEventListener('click', carregarHistorico);

            // 5. Registra o Service Worker do PWA
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.register('sw.js')
                    .then(reg => console.log('Service Worker do PWA registrado com escopo:', reg.scope))
                    .catch(err => console.error('Erro ao registrar Service Worker do PWA:', err));
            }
        });