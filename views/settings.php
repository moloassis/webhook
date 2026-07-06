<?php
/**
 * View de Configurações - Central de Alertas
 * Renderizado dentro do roteador index.php
 */

// Diretório de áudios
$audioDir = __DIR__ . '/../assets/audio/';
if (!is_dir($audioDir)) {
    mkdir($audioDir, 0755, true);
}

$sucessoMsg = isset($_GET['success']) ? $_GET['success'] : '';
$erroMsg = isset($_GET['error']) ? $_GET['error'] : '';

// 1. Processar Ações (Salvar limite, Upload de som, Selecionar som, Excluir som)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $statusQuery = '';
    
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        // AÇÃO: Salvar Configurações Gerais
        if ($action === 'save_general') {
            $limite = isset($_POST['limite_logs']) ? (int)$_POST['limite_logs'] : 100;
            if ($limite < 1) $limite = 1;
            if ($limite > 1000) $limite = 1000;

            if (salvarConfiguracao('limite_logs', (string)$limite)) {
                $statusQuery = 'success=' . urlencode('Limite de logs atualizado com sucesso!');
            } else {
                $statusQuery = 'error=' . urlencode('Falha ao salvar limite de logs.');
            }
        }

        // AÇÃO: Upload de Áudio Customizado
        elseif ($action === 'upload_audio') {
            if (isset($_FILES['audio_file']) && $_FILES['audio_file']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath = $_FILES['audio_file']['tmp_name'];
                $fileName = $_FILES['audio_file']['name'];
                $fileSize = $_FILES['audio_file']['size'];
                
                $fileNameCmps = explode(".", $fileName);
                $fileExtension = strtolower(end($fileNameCmps));

                // Extensões permitidas
                $allowedExtensions = ['mp3', 'wav', 'ogg', 'm4a', 'aac', 'webm'];

                if (in_array($fileExtension, $allowedExtensions)) {
                    // Limite de 8MB para áudio
                    if ($fileSize <= 8 * 1024 * 1024) {
                        // Limpa o nome do arquivo para segurança
                        $cleanName = preg_replace('/[^a-zA-Z0-9._-]/', '', pathinfo($fileName, PATHINFO_FILENAME));
                        $newFileName = time() . '_' . $cleanName . '.' . $fileExtension;
                        $dest_path = $audioDir . $newFileName;

                        if (move_uploaded_file($fileTmpPath, $dest_path)) {
                            // Define o áudio como ativo automaticamente
                            salvarConfiguracao('audio_alerta', 'assets/audio/' . $newFileName);
                            $statusQuery = 'success=' . urlencode('Áudio enviado e ativado com sucesso!');
                        } else {
                            $statusQuery = 'error=' . urlencode('Erro ao salvar o arquivo no servidor.');
                        }
                    } else {
                        $statusQuery = 'error=' . urlencode('O tamanho máximo do arquivo de áudio é de 8MB.');
                    }
                } else {
                    $statusQuery = 'error=' . urlencode('Formato de arquivo não suportado. Envie MP3, WAV, OGG, M4A, AAC ou WEBM.');
                }
            } else {
                $statusQuery = 'error=' . urlencode('Nenhum arquivo enviado ou erro no upload.');
            }
        }

        // AÇÃO: Selecionar Som Ativo
        elseif ($action === 'select_audio') {
            $selectedFile = isset($_POST['audio_filename']) ? trim($_POST['audio_filename']) : '';
            $selectedFile = basename($selectedFile); // Previne Directory Traversal
            
            if ($selectedFile === 'default') {
                salvarConfiguracao('audio_alerta', 'assets/audio/notificacao.mp3');
                $statusQuery = 'success=' . urlencode('Áudio padrão do sistema ativado!');
            } elseif ($selectedFile && file_exists($audioDir . $selectedFile)) {
                salvarConfiguracao('audio_alerta', 'assets/audio/' . $selectedFile);
                $statusQuery = 'success=' . urlencode('Áudio customizado ativado com sucesso!');
            } else {
                $statusQuery = 'error=' . urlencode('Arquivo de áudio não encontrado.');
            }
        }

        // AÇÃO: Excluir Som Customizado
        elseif ($action === 'delete_audio') {
            $fileToDelete = isset($_POST['audio_filename']) ? trim($_POST['audio_filename']) : '';
            $fileToDelete = basename($fileToDelete); // Previne Directory Traversal
            
            if ($fileToDelete && $fileToDelete !== 'notificacao.mp3' && file_exists($audioDir . $fileToDelete)) {
                // Se o arquivo que está sendo deletado for o ativo, volta para o padrão
                $somAtivo = obterConfiguracao('audio_alerta', 'assets/audio/notificacao.mp3');
                if ($somAtivo === 'assets/audio/' . $fileToDelete) {
                    salvarConfiguracao('audio_alerta', 'assets/audio/notificacao.mp3');
                }
                
                unlink($audioDir . $fileToDelete);
                $statusQuery = 'success=' . urlencode('Áudio removido com sucesso!');
            } else {
                $statusQuery = 'error=' . urlencode('Não foi possível excluir o arquivo de áudio.');
            }
        }
    }
    
    // Recarrega a página de forma limpa para evitar reenvio de formulário
    header("Location: settings" . ($statusQuery ? '?' . $statusQuery : ''));
    exit;
}

// 2. Carregar valores atuais
$limiteLogs = (int) obterConfiguracao('limite_logs', 100);
$somAtivo = obterConfiguracao('audio_alerta', 'assets/audio/notificacao.mp3');

// Garantir que existe o som padrão senão copiar do repositório ou criar
$somAtivoNome = basename($somAtivo);

// Listar arquivos na pasta assets/audio
$audiosDisponiveis = [];
if (is_dir($audioDir)) {
    $files = scandir($audioDir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && is_file($audioDir . $file)) {
            // Ignorar o arquivo base padrão do PWA no loop da lista customizada
            if ($file !== 'notificacao.mp3') {
                $audiosDisponiveis[] = $file;
            }
        }
    }
}
?>

<div class="container" style="max-width: 1100px; width: 100%; margin: 0 auto; display: flex; flex-direction: column; gap: 1.5rem; padding-bottom: 2rem;">

    <!-- Subheader Interno de Configurações -->
    <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 0.8rem; border-bottom: 1px solid var(--border-color); flex-wrap: wrap; gap: 1rem;">
        <div>
            <h2 style="font-size: 1.4rem; font-weight: 600; color: var(--text-primary);">Configurações do Sistema</h2>
            <p style="font-size: 0.82rem; color: var(--text-secondary); margin-top: 4px;">Gerencie limites do servidor, alertas sonoros personalizados e notificações locais</p>
        </div>
    </div>

    <!-- Mensagens de Feedback de Operação -->
    <?php if ($sucessoMsg): ?>
        <div style="background: rgba(46, 213, 115, 0.15); border: 1px solid #2ed573; padding: 0.8rem 1.2rem; border-radius: 8px; color: #2ed573; font-size: 0.9rem; font-weight: 500;">
            ✔ <?= htmlspecialchars($sucessoMsg) ?>
        </div>
    <?php endif; ?>
    <?php if ($erroMsg): ?>
        <div style="background: rgba(255, 71, 87, 0.15); border: 1px solid var(--error-color); padding: 0.8rem 1.2rem; border-radius: 8px; color: var(--error-color); font-size: 0.9rem; font-weight: 500;">
            ❌ <?= htmlspecialchars($erroMsg) ?>
        </div>
    <?php endif; ?>

    <!-- Layout Grid de Painéis -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem;">
        
        <!-- COLUNA ESQUERDA: Configurações do Servidor e Local -->
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
            
            <!-- Painel 1: Configurações do Servidor (Geral) -->
            <div class="panel-box" style="background: var(--panel-bg); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; backdrop-filter: blur(16px);">
                <h3 class="panel-title" style="margin-top: 0; font-size: 1.1rem; color: var(--text-primary); margin-bottom: 1.2rem; display: flex; align-items: center; gap: 8px;">
                    🎛️ Parâmetros do Painel
                </h3>
                <form action="settings" method="POST" style="display: flex; flex-direction: column; gap: 1.2rem;">
                    <input type="hidden" name="action" value="save_general">
                    
                    <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <label class="label-text" for="limite_logs" style="font-weight: 500; font-size: 0.85rem;">Limite de Logs Exibidos (Webhooks)</label>
                        <input type="number" id="limite_logs" name="limite_logs" class="form-control" 
                               value="<?= $limiteLogs ?>" min="1" max="1000" required
                               style="width: 100%; background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); border-radius: 8px; padding: 0.6rem; color: var(--text-primary); outline: none;">
                        <span class="label-text" style="font-size: 0.72rem; color: var(--text-secondary);">
                            Define o número máximo de requisições de webhook registradas exibidas na tela de Logs (máx: 1000).
                        </span>
                    </div>
                    
                    <button type="submit" class="btn-premium" style="width: 100%; margin: 0; padding: 0.7rem; font-weight: 600;">
                        Salvar Configurações Gerais
                    </button>
                </form>
            </div>

            <!-- Painel 2: Notificações do Navegador (Local / localStorage) -->
            <div class="panel-box" style="background: var(--panel-bg); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; backdrop-filter: blur(16px);">
                <h3 class="panel-title" style="margin-top: 0; font-size: 1.1rem; color: var(--text-primary); margin-bottom: 1.2rem; display: flex; align-items: center; gap: 8px;">
                    🔊 Alertas no Navegador
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

        <!-- COLUNA DIREITA: Biblioteca de Sons e Upload -->
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
            
            <!-- Painel 3: Envio de Novo Áudio -->
            <div class="panel-box" style="background: var(--panel-bg); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; backdrop-filter: blur(16px);">
                <h3 class="panel-title" style="margin-top: 0; font-size: 1.1rem; color: var(--text-primary); margin-bottom: 1.2rem; display: flex; align-items: center; gap: 8px;">
                    📤 Enviar Som de Notificação
                </h3>
                <form action="settings" method="POST" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 1rem;">
                    <input type="hidden" name="action" value="upload_audio">
                    
                    <div style="border: 2px dashed var(--border-color); border-radius: 12px; padding: 2rem; text-align: center; background: rgba(255,255,255,0.01); cursor: pointer; transition: border-color 0.3s;" 
                         onclick="document.getElementById('audio_file').click();"
                         onmouseover="this.style.borderColor='var(--color-default)';"
                         onmouseout="this.style.borderColor='var(--border-color)';">
                        <span style="font-size: 2.2rem; display: block; margin-bottom: 0.5rem;">🎵</span>
                        <span class="label-text" style="font-weight: 500; font-size: 0.85rem; color: var(--text-primary); display: block;">Selecione ou arraste seu áudio</span>
                        <span class="label-text" style="font-size: 0.72rem; color: var(--text-secondary); display: block; margin-top: 4px;">Formatos aceitos: MP3, WAV, OGG, M4A, AAC (Máx 8MB)</span>
                        <input type="file" id="audio_file" name="audio_file" accept="audio/*" style="display: none;" onchange="updateFileNameLabel(this)">
                        <div id="file_selected_name" class="label-text" style="margin-top: 0.8rem; font-weight: 600; color: var(--color-lead); display: none;">-</div>
                    </div>
                    
                    <button type="submit" class="btn-premium" style="width: 100%; margin: 0; padding: 0.7rem; font-weight: 600;">
                        Fazer Upload e Ativar 🚀
                    </button>
                </form>
            </div>

            <!-- Painel 4: Biblioteca de Sons -->
            <div class="panel-box" style="background: var(--panel-bg); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; backdrop-filter: blur(16px);">
                <h3 class="panel-title" style="margin-top: 0; font-size: 1.1rem; color: var(--text-primary); margin-bottom: 1.2rem; display: flex; align-items: center; gap: 8px;">
                    📂 Biblioteca de Sons
                </h3>
                
                <div style="display: flex; flex-direction: column; gap: 0.8rem; max-height: 380px; overflow-y: auto; padding-right: 4px;">
                    
                    <!-- Som Padrão -->
                    <div style="background: rgba(255,255,255,0.02); border: 1px solid <?= ($somAtivoNome === 'notificacao.mp3') ? 'var(--color-default)' : 'var(--border-color)' ?>; border-radius: 10px; padding: 0.8rem; display: flex; flex-direction: column; gap: 0.6rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <span class="label-text" style="font-weight: 600; color: var(--text-primary); font-size: 0.85rem; display: block;">
                                    Sintetizador / Som Padrão
                                </span>
                                <span class="label-text" style="font-size: 0.7rem; color: var(--text-secondary);">
                                    notificacao.mp3 (Som padrão do PWA)
                                </span>
                            </div>
                            <?php if ($somAtivoNome === 'notificacao.mp3'): ?>
                                <span class="badge badge-success" style="font-size: 0.7rem; padding: 0.2rem 0.5rem; background: var(--color-lead); border-color: var(--color-lead);">ATIVADO</span>
                            <?php else: ?>
                                <form action="settings" method="POST" style="margin: 0;">
                                    <input type="hidden" name="action" value="select_audio">
                                    <input type="hidden" name="audio_filename" value="default">
                                    <button type="submit" class="btn-inspect" style="font-size: 0.75rem; padding: 0.3rem 0.6rem; border-radius: 6px;">Ativar</button>
                                </form>
                            <?php endif; ?>
                        </div>
                        <audio controls style="width: 100%; height: 32px; background: none;" src="assets/audio/notificacao.mp3"></audio>
                    </div>
                    
                    <!-- Sons Customizados -->
                    <?php if (empty($audiosDisponiveis)): ?>
                        <div style="text-align: center; padding: 1.5rem; border: 1px dashed var(--border-color); border-radius: 10px;">
                            <span class="label-text" style="font-size: 0.8rem; color: var(--text-secondary);">Nenhum áudio customizado enviado ainda.</span>
                        </div>
                    <?php else: ?>
                        <?php foreach ($audiosDisponiveis as $audioFile): 
                            $isActive = ($somAtivoNome === $audioFile);
                            ?>
                            <div style="background: rgba(255,255,255,0.02); border: 1px solid <?= $isActive ? 'var(--color-default)' : 'var(--border-color)' ?>; border-radius: 10px; padding: 0.8rem; display: flex; flex-direction: column; gap: 0.6rem;">
                                <div style="display: flex; justify-content: space-between; align-items: center; gap: 0.5rem;">
                                    <div style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1;">
                                        <span class="label-text" style="font-weight: 600; color: var(--text-primary); font-size: 0.85rem; display: block; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($audioFile) ?>">
                                            <?= htmlspecialchars(substr($audioFile, 11)) ?: htmlspecialchars($audioFile) // Remove o timestamp prefixo do nome para visualização ?>
                                        </span>
                                        <span class="label-text" style="font-size: 0.7rem; color: var(--text-secondary); display: block;">
                                            Enviado em: <?= date('d/m/Y H:i', (int)substr($audioFile, 0, 10)) ?>
                                        </span>
                                    </div>
                                    <div style="display: flex; gap: 0.4rem; align-items: center;">
                                        <?php if ($isActive): ?>
                                            <span class="badge badge-success" style="font-size: 0.7rem; padding: 0.2rem 0.5rem; background: var(--color-lead); border-color: var(--color-lead);">ATIVADO</span>
                                        <?php else: ?>
                                            <form action="settings" method="POST" style="margin: 0;">
                                                <input type="hidden" name="action" value="select_audio">
                                                <input type="hidden" name="audio_filename" value="<?= htmlspecialchars($audioFile) ?>">
                                                <button type="submit" class="btn-inspect" style="font-size: 0.75rem; padding: 0.3rem 0.6rem; border-radius: 6px;">Ativar</button>
                                            </form>
                                            <form action="settings" method="POST" style="margin: 0;" onsubmit="return confirm('Excluir este áudio de notificação?')">
                                                <input type="hidden" name="action" value="delete_audio">
                                                <input type="hidden" name="audio_filename" value="<?= htmlspecialchars($audioFile) ?>">
                                                <button type="submit" class="btn-inspect" style="font-size: 0.75rem; padding: 0.3rem 0.6rem; border-radius: 6px; background: rgba(255, 71, 87, 0.15); border-color: rgba(255, 71, 87, 0.3); color: var(--color-atendimento);">
                                                    Excluir
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <audio controls style="width: 100%; height: 32px; background: none;" src="assets/audio/<?= htmlspecialchars($audioFile) ?>"></audio>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                </div>
            </div>

        </div>

    </div>

</div>

<script>
    function updateFileNameLabel(input) {
        const label = document.getElementById('file_selected_name');
        if (input.files && input.files.length > 0) {
            label.textContent = "Selecionado: " + input.files[0].name;
            label.style.display = 'block';
        } else {
            label.style.display = 'none';
        }
    }
</script>
