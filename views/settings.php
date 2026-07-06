<?php
/**
 * View de Configurações - Central de Alertas Multi-Tenant
 * Permite gerenciar parâmetros do painel, sons, White-Label e usuários da empresa.
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers/tenant_context.php';

$empresaId = (int)$_SESSION['tenant_ativo_id'];
$isAdmin = ($_SESSION['usuario_role'] === 'admin' || $_SESSION['usuario_role'] === 'superadmin');

// Diretório de áudios
$audioDir = __DIR__ . '/../assets/audio/';
if (!is_dir($audioDir)) {
    mkdir($audioDir, 0755, true);
}

// 0. Renderizar apenas a biblioteca de sons via AJAX GET
if (isset($_GET['render_library'])) {
    $somAtivo = obterConfiguracao('audio_alerta', 'assets/audio/notificacao.mp3');
    $somAtivoNome = basename($somAtivo);
    
    $audiosDisponiveis = [];
    if (is_dir($audioDir)) {
        $files = scandir($audioDir);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..' && is_file($audioDir . $file)) {
                if ($file !== 'notificacao.mp3') {
                    $audiosDisponiveis[] = $file;
                }
            }
        }
    }
    ?>
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
            <?php elseif ($isAdmin): ?>
                <form action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8'); ?>" method="POST" style="margin: 0;">
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
                            <?= htmlspecialchars(substr($audioFile, 11)) ?: htmlspecialchars($audioFile) ?>
                        </span>
                        <span class="label-text" style="font-size: 0.7rem; color: var(--text-secondary); display: block;">
                            Enviado em: <?= date('d/m/Y H:i', (int)substr($audioFile, 0, 10)) ?>
                        </span>
                    </div>
                    <div style="display: flex; gap: 0.4rem; align-items: center;">
                        <?php if ($isActive): ?>
                            <span class="badge badge-success" style="font-size: 0.7rem; padding: 0.2rem 0.5rem; background: var(--color-lead); border-color: var(--color-lead);">ATIVADO</span>
                        <?php elseif ($isAdmin): ?>
                            <form action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8'); ?>" method="POST" style="margin: 0;">
                                <input type="hidden" name="action" value="select_audio">
                                <input type="hidden" name="audio_filename" value="<?= htmlspecialchars($audioFile) ?>">
                                <button type="submit" class="btn-inspect" style="font-size: 0.75rem; padding: 0.3rem 0.6rem; border-radius: 6px;">Ativar</button>
                            </form>
                            <form action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8'); ?>" method="POST" style="margin: 0;" onsubmit="return confirm('Excluir este áudio de notificação?')">
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
    <?php
    exit;
}

$sucessoMsg = isset($_GET['success']) ? $_GET['success'] : '';
$erroMsg = isset($_GET['error']) ? $_GET['error'] : '';

// 1. Processar Ações Admin (Salvar limite, Som, White-Label, Gerenciar Usuários)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $statusQuery = '';
    
    // Segurança: Apenas Administradores alteram as configurações do tenant
    if (!$isAdmin) {
        http_response_code(403);
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'Permissão negada. Apenas administradores podem salvar configurações.']);
            exit;
        }
        $statusQuery = 'error=' . urlencode('Permissão negada.');
    } else {
        $action = isset($_POST['action']) ? $_POST['action'] : '';

        // AÇÃO: Salvar Configurações Gerais
        if ($action === 'save_general') {
            $limite = isset($_POST['limite_logs']) ? (int)$_POST['limite_logs'] : 100;
            if ($limite < 1) $limite = 1;
            if ($limite > 1000) $limite = 1000;

            if (salvarConfiguracao('limite_logs', (string)$limite, $empresaId)) {
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
                $allowedExtensions = ['mp3', 'wav', 'ogg', 'm4a', 'aac', 'webm'];

                if (in_array($fileExtension, $allowedExtensions)) {
                    if ($fileSize <= 8 * 1024 * 1024) {
                        $cleanName = preg_replace('/[^a-zA-Z0-9._-]/', '', pathinfo($fileName, PATHINFO_FILENAME));
                        $newFileName = time() . '_' . $cleanName . '.' . $fileExtension;
                        $dest_path = $audioDir . $newFileName;

                        if (move_uploaded_file($fileTmpPath, $dest_path)) {
                            salvarConfiguracao('audio_alerta', 'assets/audio/' . $newFileName, $empresaId);
                            $statusQuery = 'success=' . urlencode('Áudio enviado e ativado com sucesso!');
                        } else {
                            $statusQuery = 'error=' . urlencode('Erro ao salvar o arquivo no servidor.');
                        }
                    } else {
                        $statusQuery = 'error=' . urlencode('O tamanho máximo do arquivo de áudio é de 8MB.');
                    }
                } else {
                    $statusQuery = 'error=' . urlencode('Formato de arquivo não suportado.');
                }
            } else {
                $statusQuery = 'error=' . urlencode('Nenhum arquivo enviado ou erro no upload.');
            }
        }

        // AÇÃO: Selecionar Som Ativo
        elseif ($action === 'select_audio') {
            $selectedFile = isset($_POST['audio_filename']) ? trim($_POST['audio_filename']) : '';
            $selectedFile = basename($selectedFile);
            
            if ($selectedFile === 'default') {
                salvarConfiguracao('audio_alerta', 'assets/audio/notificacao.mp3', $empresaId);
                $statusQuery = 'success=' . urlencode('Áudio padrão do sistema ativado!');
            } elseif ($selectedFile && file_exists($audioDir . $selectedFile)) {
                salvarConfiguracao('audio_alerta', 'assets/audio/' . $selectedFile, $empresaId);
                $statusQuery = 'success=' . urlencode('Áudio customizado ativado com sucesso!');
            } else {
                $statusQuery = 'error=' . urlencode('Arquivo de áudio não encontrado.');
            }
        }

        // AÇÃO: Excluir Som Customizado
        elseif ($action === 'delete_audio') {
            $fileToDelete = isset($_POST['audio_filename']) ? trim($_POST['audio_filename']) : '';
            $fileToDelete = basename($fileToDelete);
            
            if ($fileToDelete && $fileToDelete !== 'notificacao.mp3' && file_exists($audioDir . $fileToDelete)) {
                $somAtivo = obterConfiguracao('audio_alerta', 'assets/audio/notificacao.mp3', $empresaId);
                if ($somAtivo === 'assets/audio/' . $fileToDelete) {
                    salvarConfiguracao('audio_alerta', 'assets/audio/notificacao.mp3', $empresaId);
                }
                unlink($audioDir . $fileToDelete);
                $statusQuery = 'success=' . urlencode('Áudio removido com sucesso!');
            } else {
                $statusQuery = 'error=' . urlencode('Não foi possível excluir o arquivo de áudio.');
            }
        }

        // AÇÃO: Salvar Configurações de White-Label (Cores, Tema, Logo)
        elseif ($action === 'save_whitelabel') {
            $corPrimaria = isset($_POST['cor_primaria']) ? trim($_POST['cor_primaria']) : '#2ed573';
            $corSecundaria = isset($_POST['cor_secundaria']) ? trim($_POST['cor_secundaria']) : '#70a1ff';
            $modoVisualizacao = isset($_POST['modo_visualizacao']) ? trim($_POST['modo_visualizacao']) : 'dark';

            try {
                $db = obterConexao();
                
                // Processa upload da logo se houver
                if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
                    $logoDir = __DIR__ . '/../assets/img/logos/';
                    if (!is_dir($logoDir)) {
                        mkdir($logoDir, 0755, true);
                    }
                    
                    $fileTmp = $_FILES['logo_file']['tmp_name'];
                    $fileExt = strtolower(pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION));
                    $allowedExts = ['png', 'jpg', 'jpeg', 'svg', 'webp'];
                    
                    if (in_array($fileExt, $allowedExts)) {
                        $newLogoName = 'logo_' . $empresaId . '_' . time() . '.' . $fileExt;
                        $destLogo = $logoDir . $newLogoName;
                        
                        if (move_uploaded_file($fileTmp, $destLogo)) {
                            // Deleta a logo anterior
                            $oldLogo = $db->query("SELECT logo_path FROM tenants WHERE id = $empresaId")->fetchColumn();
                            if ($oldLogo && file_exists(__DIR__ . '/../' . $oldLogo)) {
                                unlink(__DIR__ . '/../' . $oldLogo);
                            }
                            
                            $logoPathDb = 'assets/img/logos/' . $newLogoName;
                            $stmtL = $db->prepare("UPDATE tenants SET logo_path = :logo WHERE id = :id");
                            $stmtL->execute([':logo' => $logoPathDb, ':id' => $empresaId]);
                        }
                    }
                }

                $stmtUpdate = $db->prepare("UPDATE tenants SET cor_primaria = :cp, cor_secundaria = :cs, modo_visualizacao = :mv WHERE id = :id");
                $stmtUpdate->execute([
                    ':cp' => $corPrimaria,
                    ':cs' => $corSecundaria,
                    ':mv' => $modoVisualizacao,
                    ':id' => $empresaId
                ]);

                $statusQuery = 'success=' . urlencode('Visual da marca atualizado com sucesso!');
            } catch (Exception $e) {
                $statusQuery = 'error=' . urlencode('Erro ao atualizar configurações visuais: ' . $e->getMessage());
            }
        }

        // AÇÃO: Adicionar Usuário da Empresa
        elseif ($action === 'add_user') {
            $uNome = isset($_POST['usuario_nome']) ? trim($_POST['usuario_nome']) : '';
            $uEmail = isset($_POST['usuario_email']) ? trim($_POST['usuario_email']) : '';
            $uSenha = isset($_POST['usuario_senha']) ? $_POST['usuario_senha'] : '';
            $uRole = isset($_POST['usuario_role']) ? trim($_POST['usuario_role']) : 'user';

            if ($uNome && $uEmail && $uSenha) {
                try {
                    $db = obterConexao();
                    // Verifica se o e-mail já existe globalmente
                    $stmtCheck = $db->prepare("SELECT COUNT(*) FROM usuarios WHERE email = :email");
                    $stmtCheck->execute([':email' => $uEmail]);
                    if ($stmtCheck->fetchColumn() > 0) {
                        $statusQuery = 'error=' . urlencode('Este endereço de e-mail já está sendo utilizado.');
                    } else {
                        $uSenhaHash = password_hash($uSenha, PASSWORD_DEFAULT);
                        $stmtAdd = $db->prepare("INSERT INTO usuarios (empresa_id, nome, email, senha_hash, role) 
                            VALUES (:empresa_id, :nome, :email, :senha_hash, :role)");
                        $stmtAdd->execute([
                            ':empresa_id' => $empresaId,
                            ':nome' => $uNome,
                            ':email' => $uEmail,
                            ':senha_hash' => $uSenhaHash,
                            ':role' => $uRole
                        ]);
                        $statusQuery = 'success=' . urlencode('Novo usuário cadastrado com sucesso!');
                    }
                } catch (Exception $e) {
                    $statusQuery = 'error=' . urlencode('Erro ao cadastrar usuário: ' . $e->getMessage());
                }
            } else {
                $statusQuery = 'error=' . urlencode('Preencha todos os campos do usuário.');
            }
        }

        // AÇÃO: Excluir Usuário da Empresa
        elseif ($action === 'delete_user') {
            $uId = isset($_POST['usuario_id']) ? (int)$_POST['usuario_id'] : 0;
            if ($uId === (int)$_SESSION['usuario_id']) {
                $statusQuery = 'error=' . urlencode('Você não pode excluir a sua própria conta.');
            } else {
                try {
                    $db = obterConexao();
                    $stmtDel = $db->prepare("DELETE FROM usuarios WHERE id = :id AND empresa_id = :empresa_id");
                    $stmtDel->execute([
                        ':id' => $uId,
                        ':empresa_id' => $empresaId
                    ]);
                    $statusQuery = 'success=' . urlencode('Usuário removido da organização.');
                } catch (Exception $e) {
                    $statusQuery = 'error=' . urlencode('Erro ao excluir usuário: ' . $e->getMessage());
                }
            }
        }
    }
    
    // Retorno para Fetch AJAX
    $isAjax = isset($_POST['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        
        $successMsg = '';
        $erroMsg = '';
        if ($statusQuery) {
            parse_str($statusQuery, $output);
            if (isset($output['success'])) {
                $successMsg = $output['success'];
            } elseif (isset($output['error'])) {
                $erroMsg = $output['error'];
            }
        }
        
        echo json_encode([
            'success' => !empty($successMsg),
            'message' => $successMsg ?: $erroMsg,
            'limite_logs' => (int) obterConfiguracao('limite_logs', 100, $empresaId),
            'som_ativo' => obterConfiguracao('audio_alerta', 'assets/audio/notificacao.mp3', $empresaId)
        ]);
        exit;
    }

    // Recarrega a página de forma limpa para evitar reenvio de formulário
    $requestUri = $_SERVER['REQUEST_URI'];
    $urlParts = parse_url($requestUri);
    $path = $urlParts['path'];
    $queryParams = [];
    if (isset($urlParts['query'])) {
        parse_str($urlParts['query'], $queryParams);
    }
    unset($queryParams['success']);
    unset($queryParams['error']);
    if ($statusQuery) {
        parse_str($statusQuery, $newParams);
        $queryParams = array_merge($queryParams, $newParams);
    }
    $queryString = http_build_query($queryParams);
    header("Location: " . $path . ($queryString ? '?' . $queryString : ''));
    exit;
}

// 2. Carregar valores atuais para exibição na tela
$limiteLogs = (int) obterConfiguracao('limite_logs', 100, $empresaId);
$somAtivo = obterConfiguracao('audio_alerta', 'assets/audio/notificacao.mp3', $empresaId);
$somAtivoNome = basename($somAtivo);

// Listar arquivos de sons customizados
$audiosDisponiveis = [];
if (is_dir($audioDir)) {
    $files = scandir($audioDir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && is_file($audioDir . $file)) {
            if ($file !== 'notificacao.mp3') {
                $audiosDisponiveis[] = $file;
            }
        }
    }
}

// Carregar informações visuais do tenant atual
$tenantConfig = carregarTenantPorSlug($_SESSION['tenant_ativo_slug']);

// Listar usuários do tenant
try {
    $db = obterConexao();
    $stmtUsers = $db->prepare("SELECT id, nome, email, role FROM usuarios WHERE empresa_id = :empresa_id ORDER BY role, nome");
    $stmtUsers->execute([':empresa_id' => $empresaId]);
    $usuariosTenant = $stmtUsers->fetchAll();
} catch (Exception $e) {
    $usuariosTenant = [];
}
?>

<div class="container" style="max-width: 1100px; width: 100%; margin: 0 auto; display: flex; flex-direction: column; gap: 1.5rem; padding-bottom: 2rem;">

    <!-- Subheader Interno de Configurações -->
    <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 0.8rem; border-bottom: 1px solid var(--border-color); flex-wrap: wrap; gap: 1rem;">
        <div>
            <h2 style="font-size: 1.4rem; font-weight: 600; color: var(--text-primary);">Configurações do Workspace</h2>
            <p style="font-size: 0.82rem; color: var(--text-secondary); margin-top: 4px;">Gerencie limites do servidor, identidade visual white-label e controle de acessos da equipe</p>
        </div>
    </div>

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

    <!-- Layout Grid de Painéis -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem;">
        
        <!-- COLUNA ESQUERDA: Configurações Globais / White-Label -->
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
            
            <!-- Painel 1: Alertas no Navegador (Visível para todos) -->
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

            <?php if ($isAdmin): ?>
            <!-- Painel 2: Parâmetros do Painel (Apenas Admin) -->
            <div class="panel-box" style="background: var(--panel-bg); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; backdrop-filter: blur(16px);">
                <h3 class="panel-title" style="margin-top: 0; font-size: 1.1rem; color: var(--text-primary); margin-bottom: 1.2rem; display: flex; align-items: center; gap: 8px;">
                    🎛️ Parâmetros do Painel
                </h3>
                <form action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8'); ?>" method="POST" style="display: flex; flex-direction: column; gap: 1.2rem;">
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

            <!-- Painel 3: Identidade Visual / White-Label (Apenas Admin) -->
            <div class="panel-box" style="background: var(--panel-bg); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; backdrop-filter: blur(16px);">
                <h3 class="panel-title" style="margin-top: 0; font-size: 1.1rem; color: var(--text-primary); margin-bottom: 1.2rem; display: flex; align-items: center; gap: 8px;">
                    🎨 Customização Visual (White-Label)
                </h3>
                <form action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8'); ?>" method="POST" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 1.2rem;">
                    <input type="hidden" name="action" value="save_whitelabel">
                    
                    <!-- Cores -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
                            <label class="label-text" for="cor_primaria" style="font-size: 0.8rem;">Cor Primária</label>
                            <div style="display: flex; gap: 8px; align-items: center;">
                                <input type="color" id="cor_primaria" name="cor_primaria" value="<?= htmlspecialchars($tenantConfig['cor_primaria'] ?? '#2ed573') ?>" style="width: 42px; height: 36px; border: none; border-radius: 6px; cursor: pointer; background: none;">
                                <span class="label-text" style="font-size: 0.8rem; font-family: monospace;"><?= htmlspecialchars($tenantConfig['cor_primaria'] ?? '#2ed573') ?></span>
                            </div>
                        </div>
                        
                        <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
                            <label class="label-text" for="cor_secundaria" style="font-size: 0.8rem;">Cor Secundária</label>
                            <div style="display: flex; gap: 8px; align-items: center;">
                                <input type="color" id="cor_secundaria" name="cor_secundaria" value="<?= htmlspecialchars($tenantConfig['cor_secundaria'] ?? '#70a1ff') ?>" style="width: 42px; height: 36px; border: none; border-radius: 6px; cursor: pointer; background: none;">
                                <span class="label-text" style="font-size: 0.8rem; font-family: monospace;"><?= htmlspecialchars($tenantConfig['cor_secundaria'] ?? '#70a1ff') ?></span>
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

                    <!-- Logo File Upload -->
                    <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <label class="label-text" for="logo_file" style="font-size: 0.85rem;">Logotipo da Empresa</label>
                        <input type="file" id="logo_file" name="logo_file" accept="image/*" class="form-control" style="width: 100%; background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); border-radius: 8px; padding: 0.5rem; color: var(--text-primary);">
                        <span class="label-text" style="font-size: 0.72rem; color: var(--text-secondary);">
                            Formatos sugeridos: PNG transparente ou SVG (max: 2MB).
                        </span>
                    </div>

                    <button type="submit" class="btn-premium" style="width: 100%; margin: 0; padding: 0.7rem; font-weight: 600;">
                        Atualizar Identidade Visual
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- COLUNA DIREITA: Biblioteca de Sons e Equipe (Apenas Admin) -->
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
            
            <?php if ($isAdmin): ?>
            <!-- Painel 4: Biblioteca de Sons -->
            <div class="panel-box" style="background: var(--panel-bg); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; backdrop-filter: blur(16px);">
                <h3 class="panel-title" style="margin-top: 0; font-size: 1.1rem; color: var(--text-primary); margin-bottom: 1.2rem; display: flex; align-items: center; gap: 8px;">
                    📂 Alertas Sonoros da Empresa
                </h3>
                
                <form action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8'); ?>" method="POST" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 0.8rem; margin-bottom: 1.2rem; border-bottom: 1px solid var(--border-color); padding-bottom: 1.2rem;">
                    <input type="hidden" name="action" value="upload_audio">
                    <div style="display: flex; gap: 8px; width: 100%;">
                        <input type="file" id="audio_file" name="audio_file" accept="audio/*" required class="form-control" style="flex: 1; font-size: 0.8rem; background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); padding: 0.4rem; border-radius: 8px;">
                        <button type="submit" class="btn-premium" style="margin: 0; padding: 0.5rem 1rem; width: auto; font-size: 0.8rem;">Upload</button>
                    </div>
                </form>

                <div id="audioLibraryContainer" style="display: flex; flex-direction: column; gap: 0.8rem; max-height: 250px; overflow-y: auto;">
                    <!-- O trecho PHP de biblioteca de sons roda aqui dinamicamente e no GET render_library -->
                    <script>
                        document.addEventListener("DOMContentLoaded", function() {
                            reloadAudioLibrary();
                        });
                    </script>
                </div>
            </div>

            <!-- Painel 5: Controle de Acessos / Usuários (Apenas Admin) -->
            <div class="panel-box" style="background: var(--panel-bg); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; backdrop-filter: blur(16px);">
                <h3 class="panel-title" style="margin-top: 0; font-size: 1.1rem; color: var(--text-primary); margin-bottom: 1.2rem; display: flex; align-items: center; gap: 8px;">
                    👥 Membros da Organização
                </h3>
                
                <!-- Lista de Usuários Existentes -->
                <div style="display: flex; flex-direction: column; gap: 0.6rem; margin-bottom: 1.5rem; max-height: 220px; overflow-y: auto; padding-right: 4px;">
                    <?php foreach ($usuariosTenant as $u): ?>
                        <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: 8px; padding: 0.6rem 0.8rem; display: flex; justify-content: space-between; align-items: center; gap: 0.5rem;">
                            <div style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1;">
                                <span class="label-text" style="font-weight: 600; color: var(--text-primary); font-size: 0.85rem; display: block; overflow: hidden; text-overflow: ellipsis;">
                                    <?= htmlspecialchars($u['nome']) ?>
                                </span>
                                <span class="label-text" style="font-size: 0.72rem; color: var(--text-secondary); display: block;">
                                    <?= htmlspecialchars($u['email']) ?> • <strong style="text-transform: uppercase; color: var(--color-default); font-size: 0.65rem;"><?= htmlspecialchars($u['role']) ?></strong>
                                </span>
                            </div>
                            <?php if ($u['id'] !== (int)$_SESSION['usuario_id']): ?>
                                <form action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8'); ?>" method="POST" style="margin: 0;" onsubmit="return confirm('Remover o usuário <?= htmlspecialchars($u['nome']) ?> do painel?')">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="usuario_id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="btn-inspect" style="font-size: 0.7rem; padding: 0.25rem 0.5rem; border-radius: 6px; background: rgba(255, 71, 87, 0.15); border-color: rgba(255, 71, 87, 0.3); color: var(--color-atendimento);">
                                        Excluir
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="badge" style="font-size: 0.65rem; border-color: rgba(255,255,255,0.1); color: var(--text-secondary);">Você</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Formulário para Adicionar Novo Usuário -->
                <form action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8'); ?>" method="POST" style="border-top: 1px solid var(--border-color); padding-top: 1.2rem; display: flex; flex-direction: column; gap: 0.8rem;">
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
            <?php endif; ?>

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

    document.addEventListener('submit', function(e) {
        if (e.defaultPrevented) return;
        
        const form = e.target;
        const actionInput = form.querySelector('input[name="action"]');
        if (!actionInput) return; // Se não for um form de ação de configurações, ignora
        
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
                
                // Recarrega a página inteira em ações críticas de marca/estrutura para aplicar as mudanças visuais
                if (actionVal === 'save_whitelabel' || actionVal === 'delete_user' || actionVal === 'add_user') {
                    setTimeout(() => window.location.reload(), 1200);
                    return;
                }
                
                if (data.limite_logs && window.SYSTEM_CONFIG) {
                    window.SYSTEM_CONFIG.limiteLogs = data.limite_logs;
                }
                if (data.som_ativo && window.SYSTEM_CONFIG) {
                    window.SYSTEM_CONFIG.audioAlerta = data.som_ativo;
                }
                
                if (actionVal === 'upload_audio' || actionVal === 'select_audio' || actionVal === 'delete_audio') {
                    reloadAudioLibrary();
                }
                
                if (actionVal === 'upload_audio') {
                    form.reset();
                    const fileLabel = document.getElementById('file_selected_name');
                    if (fileLabel) fileLabel.style.display = 'none';
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
