<?php
/**
 * Controller de Configurações - Central de Alertas Multi-Tenant
 * Gerencia ações de White-Label, usuários e sons por tenant.
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
