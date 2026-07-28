<?php
/**
 * Controller de Configurações - Central de Alertas Multi-Tenant
 * Gerencia ações de White-Label, usuários e sons por tenant.
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers/tenant_context.php';
require_once __DIR__ . '/../helpers/webhook_rules.php';

$empresaId = (int)$_SESSION['tenant_ativo_id'];
$isAdmin = ($_SESSION['usuario_role'] === 'admin' || $_SESSION['usuario_role'] === 'superadmin');

// Diretório de uploads de áudio e logotipos
$audioDir = __DIR__ . '/../uploads/audio/';
if (!is_dir($audioDir)) {
    mkdir($audioDir, 0755, true);
}
$logoDir = __DIR__ . '/../uploads/logos/';
if (!is_dir($logoDir)) {
    mkdir($logoDir, 0755, true);
}

// 0. Renderizar apenas a biblioteca de sons via AJAX GET
if (isset($_GET['render_library']) && $_SERVER['REQUEST_METHOD'] === 'GET') {
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
                    <?php echo renderizarCampoCSRF(); ?>
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
                                <?php echo renderizarCampoCSRF(); ?>
                                <input type="hidden" name="action" value="select_audio">
                                <input type="hidden" name="audio_filename" value="<?= htmlspecialchars($audioFile) ?>">
                                <button type="submit" class="btn-inspect" style="font-size: 0.75rem; padding: 0.3rem 0.6rem; border-radius: 6px;">Ativar</button>
                            </form>
                            <form action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8'); ?>" method="POST" style="margin: 0;" onsubmit="return confirm('Excluir este áudio de notificação?')">
                                <?php echo renderizarCampoCSRF(); ?>
                                <input type="hidden" name="action" value="delete_audio">
                                <input type="hidden" name="audio_filename" value="<?= htmlspecialchars($audioFile) ?>">
                                <button type="submit" class="btn-inspect" style="font-size: 0.75rem; padding: 0.3rem 0.6rem; border-radius: 6px; background: rgba(255, 71, 87, 0.15); border-color: rgba(255, 71, 87, 0.3); color: var(--color-atendimento);">
                                    Excluir
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                <audio controls style="width: 100%; height: 32px; background: none;" src="uploads/audio/<?= htmlspecialchars($audioFile) ?>"></audio>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php
    exit;
}

// 0.1 Renderizar apenas a lista de membros via AJAX GET
if (isset($_GET['render_users']) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $db = obterConexao();
        $stmtUsers = $db->prepare("SELECT id, nome, email, role FROM usuarios WHERE empresa_id = :empresa_id ORDER BY role, nome");
        $stmtUsers->execute([':empresa_id' => $empresaId]);
        $usuariosTenant = $stmtUsers->fetchAll();
    } catch (Exception $e) {
        $usuariosTenant = [];
    }
    
    foreach ($usuariosTenant as $u): ?>
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
                <form action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8'); ?>" method="POST" style="margin: 0;" onsubmit="event.preventDefault(); confirmarExcluirUsuario(this, <?= $u['id'] ?>, '<?= htmlspecialchars($u['nome'], ENT_QUOTES, 'UTF-8') ?>')">
                    <?php echo renderizarCampoCSRF(); ?>
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
    <?php endforeach;
    exit;
}

$sucessoMsg = isset($_GET['success']) ? $_GET['success'] : '';
$erroMsg = isset($_GET['error']) ? $_GET['error'] : '';

// 1. Processar Ações Admin (Salvar limite, Som, White-Label, Gerenciar Usuários)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjax = isset($_POST['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
    if (!validarTokenCSRF()) {
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'Sessão expirada ou token de segurança inválido. Recarregue a página.']);
            exit;
        }
        $statusQuery = 'error=' . urlencode('Sessão expirada ou token de segurança inválido. Recarregue a página.');
    } else {
        $statusQuery = '';
        
        // Bloqueio de gravação em Modo Inspeção (Somente Leitura)
        if (isTenantReadOnlyMode()) {
            $action = isset($_POST['action']) ? $_POST['action'] : 'desconhecida';
        try {
            $db = obterConexao();
            $stmtAudit = $db->prepare("INSERT INTO superadmin_auditoria_logs (usuario_id, usuario_nome, usuario_email, tenant_slug, tenant_nome, acao, detalhes, ip) 
                VALUES (:usuario_id, :usuario_nome, :usuario_email, :tenant_slug, :tenant_nome, 'acao_bloqueada', :detalhes, :ip)");
            $stmtAudit->execute([
                ':usuario_id' => (int)$_SESSION['usuario_id'],
                ':usuario_nome' => $_SESSION['usuario_nome'],
                ':usuario_email' => $_SESSION['usuario_email'],
                ':tenant_slug' => $_SESSION['tenant_ativo_slug'] ?? '',
                ':tenant_nome' => $_SESSION['tenant_ativo_nome'] ?? '',
                ':detalhes' => "Tentativa de realizar ação '{$action}' em configurações bloqueada por Somente Leitura.",
                ':ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
            ]);
        } catch (Exception $e) {
            registrarErro("Erro ao registrar tentativa bloqueada no log de auditoria: " . $e->getMessage());
        }

        if (isset($_POST['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'Ação não permitida em Modo de Inspeção (Somente Leitura).']);
            exit;
        }
        header("Location: " . $_SERVER['REQUEST_URI'] . (strpos($_SERVER['REQUEST_URI'], '?') === false ? '?' : '&') . 'error=' . urlencode('Ação não permitida em Modo de Inspeção (Somente Leitura).'));
        exit;
    }
    
        $action = isset($_POST['action']) ? $_POST['action'] : '';

        // AÇÃO: Atualizar Perfil do Usuário (disponível para todos os usuários logados)
        if ($action === 'update_profile') {
            $uId = (int)$_SESSION['usuario_id'];
            $uNome = isset($_POST['usuario_nome']) ? trim($_POST['usuario_nome']) : '';
            $uEmail = isset($_POST['usuario_email']) ? trim($_POST['usuario_email']) : '';
            $senhaAtual = isset($_POST['senha_atual']) ? $_POST['senha_atual'] : '';
            $novaSenha = isset($_POST['nova_senha']) ? $_POST['nova_senha'] : '';
            $confirmarSenha = isset($_POST['confirmar_senha']) ? $_POST['confirmar_senha'] : '';

            if (!$uNome || !$uEmail) {
                $statusQuery = 'error=' . urlencode('Preencha os campos Nome e E-mail.');
            } elseif (!filter_var($uEmail, FILTER_VALIDATE_EMAIL)) {
                $statusQuery = 'error=' . urlencode('Formato de e-mail inválido.');
            } else {
                try {
                    $db = obterConexao();

                    // 1. Busca os dados atuais do usuário no banco
                    $stmtUser = $db->prepare("SELECT * FROM usuarios WHERE id = :id");
                    $stmtUser->execute([':id' => $uId]);
                    $usuarioAtual = $stmtUser->fetch();

                    if (!$usuarioAtual) {
                        $statusQuery = 'error=' . urlencode('Usuário não encontrado.');
                    } else {
                        // 2. Verifica se o novo e-mail já pertence a outro usuário
                        $stmtCheckEmail = $db->prepare("SELECT COUNT(*) FROM usuarios WHERE email = :email AND id != :id");
                        $stmtCheckEmail->execute([':email' => $uEmail, ':id' => $uId]);
                        if ($stmtCheckEmail->fetchColumn() > 0) {
                            $statusQuery = 'error=' . urlencode('Este endereço de e-mail já está sendo utilizado por outro usuário.');
                        } else {
                            $trocarSenha = !empty($novaSenha) || !empty($confirmarSenha) || !empty($senhaAtual);
                            $sucesso = false;

                            if ($trocarSenha) {
                                // Validações para alteração de senha
                                if (empty($senhaAtual)) {
                                    $statusQuery = 'error=' . urlencode('Informe a sua senha atual para autorizar a alteração.');
                                } elseif (!password_verify($senhaAtual, $usuarioAtual['senha_hash'])) {
                                    $statusQuery = 'error=' . urlencode('A senha atual informada está incorreta.');
                                } elseif ($novaSenha !== $confirmarSenha) {
                                    $statusQuery = 'error=' . urlencode('A nova senha e a confirmação de senha não coincidem.');
                                } else {
                                    require_once __DIR__ . '/../helpers/security.php';
                                    $erroForca = validarForcaSenha($novaSenha);
                                    if ($erroForca) {
                                        $statusQuery = 'error=' . urlencode($erroForca);
                                    } else {
                                        $novaSenhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);
                                        $stmtUpdate = $db->prepare("UPDATE usuarios SET nome = :nome, email = :email, senha_hash = :senha_hash WHERE id = :id");
                                        $sucesso = $stmtUpdate->execute([
                                            ':nome' => $uNome,
                                            ':email' => $uEmail,
                                            ':senha_hash' => $novaSenhaHash,
                                            ':id' => $uId
                                        ]);
                                    }
                                }
                            } else {
                                // Apenas atualiza nome e e-mail
                                $stmtUpdate = $db->prepare("UPDATE usuarios SET nome = :nome, email = :email WHERE id = :id");
                                $sucesso = $stmtUpdate->execute([
                                    ':nome' => $uNome,
                                    ':email' => $uEmail,
                                    ':id' => $uId
                                ]);
                            }

                            if ($sucesso) {
                                $_SESSION['usuario_nome'] = $uNome;
                                $_SESSION['usuario_email'] = $uEmail;

                                // Recalcula token JWT
                                require_once __DIR__ . '/../helpers/jwt.php';
                                $payload = [
                                    'usuario_id' => $uId,
                                    'empresa_id' => $_SESSION['empresa_id'] ? (int)$_SESSION['empresa_id'] : null,
                                    'role' => $_SESSION['usuario_role']
                                ];
                                $_SESSION['jwt_token'] = JWT::encode($payload, JWT_SECRET);

                                $statusQuery = 'success=' . urlencode('Perfil atualizado com sucesso!');
                            } elseif (empty($statusQuery)) {
                                $statusQuery = 'error=' . urlencode('Erro ao atualizar os dados do perfil.');
                            }
                        }
                    }
                } catch (Exception $e) {
                    $statusQuery = 'error=' . urlencode('Erro interno ao atualizar perfil: ' . $e->getMessage());
                }
            }
        }
        // Segurança: Apenas Administradores alteram as demais configurações do tenant
        elseif (!$isAdmin) {
            http_response_code(403);
            if (isset($_POST['ajax'])) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'Permissão negada. Apenas administradores podem salvar configurações.']);
                exit;
            }
            $statusQuery = 'error=' . urlencode('Permissão negada.');
        } else {

        // AÇÃO: Salvar Configurações Gerais
        if ($action === 'save_general') {
            $limite = isset($_POST['limite_logs']) ? (int)$_POST['limite_logs'] : 100;
            if ($limite < 1) $limite = 1;
            if ($limite > 1000) $limite = 1000;

            $crmEnabled = isset($_POST['crm_integration_enabled']) ? '1' : '0';
            $crmUrl = isset($_POST['crm_api_url']) ? trim($_POST['crm_api_url']) : '';
            $crmKey = isset($_POST['crm_api_key']) ? trim($_POST['crm_api_key']) : '';
            $crmEtapaAtend = isset($_POST['crm_etapa_atendimento']) ? trim($_POST['crm_etapa_atendimento']) : '';
            $crmEtapaFin = isset($_POST['crm_etapa_finalizado']) ? trim($_POST['crm_etapa_finalizado']) : '';

            $s1 = salvarConfiguracao('limite_logs', (string)$limite, $empresaId);
            $s2 = salvarConfiguracao('crm_integration_enabled', $crmEnabled, $empresaId);
            $s3 = salvarConfiguracao('crm_api_url', $crmUrl, $empresaId);
            $s4 = salvarConfiguracao('crm_api_key', $crmKey, $empresaId);
            $s5 = salvarConfiguracao('crm_etapa_atendimento', $crmEtapaAtend, $empresaId);
            $s6 = salvarConfiguracao('crm_etapa_finalizado', $crmEtapaFin, $empresaId);

            if ($s1 && $s2 && $s3 && $s4 && $s5 && $s6) {
                $statusQuery = 'success=' . urlencode('Configurações gerais e de integração CRM salvas com sucesso!');
            } else {
                $statusQuery = 'error=' . urlencode('Falha ao salvar as configurações.');
            }
        }

        // AÇÃO: Salvar Mapeamento de Sons
        elseif ($action === 'save_sound_mappings') {
            $audioSuporte = isset($_POST['audio_suporte']) ? trim($_POST['audio_suporte']) : 'default';
            $audioLead = isset($_POST['audio_lead']) ? trim($_POST['audio_lead']) : 'default';
            $audioDefault = isset($_POST['audio_default']) ? trim($_POST['audio_default']) : 'default';
            
            $valSuporte = ($audioSuporte === 'default') ? 'assets/audio/notificacao.mp3' : 'uploads/audio/' . basename($audioSuporte);
            $valLead = ($audioLead === 'default') ? 'assets/audio/notificacao.mp3' : 'uploads/audio/' . basename($audioLead);
            $valDefault = ($audioDefault === 'default') ? 'assets/audio/notificacao.mp3' : 'uploads/audio/' . basename($audioDefault);

            $s1 = salvarConfiguracao('audio_alerta_suporte', $valSuporte, $empresaId);
            $s2 = salvarConfiguracao('audio_alerta_lead', $valLead, $empresaId);
            $s3 = salvarConfiguracao('audio_alerta', $valDefault, $empresaId); // default / fallback
            
            if ($s1 && $s2 && $s3) {
                $statusQuery = 'success=' . urlencode('Mapeamento de alertas sonoros atualizado!');
            } else {
                $statusQuery = 'error=' . urlencode('Falha ao atualizar o mapeamento de sons.');
            }
        }

        // AÇÃO: Salvar Regras de Webhook (categoria + modo de exibição + modo de deduplicação, por tipo de evento)
        elseif ($action === 'save_webhook_rules') {
            $regrasPost = isset($_POST['regras']) && is_array($_POST['regras']) ? $_POST['regras'] : [];
            $dedupPost = isset($_POST['dedup_modo']) && is_array($_POST['dedup_modo']) ? $_POST['dedup_modo'] : [];
            $tiposConhecidos = array_keys(regrasPadraoWebhook());
            $eventosValidados = [];
            $dedupValidados = [];

            foreach ($tiposConhecidos as $tipoEvento) {
                if (isset($regrasPost[$tipoEvento]) && is_array($regrasPost[$tipoEvento])) {
                    $linhasValidas = [];
                    foreach ($regrasPost[$tipoEvento] as $linha) {
                        if (!is_array($linha)) {
                            continue;
                        }

                        $categoria = trim((string)($linha['categoria'] ?? 'ignorar'));
                        $modoExibicao = trim((string)($linha['modo_exibicao'] ?? 'normal'));
                        $condicao = trim((string)($linha['condicao'] ?? ''));

                        if (!in_array($categoria, CATEGORIAS_VALIDAS_WEBHOOK, true)) {
                            continue;
                        }
                        if (!in_array($modoExibicao, MODOS_EXIBICAO_VALIDOS_WEBHOOK, true)) {
                            continue;
                        }

                        $condicao = mb_substr($condicao, 0, 300);

                        $linhasValidas[] = [
                            'condicao_palavras' => $condicao,
                            'categoria' => $categoria,
                            'modo_exibicao' => $modoExibicao
                        ];

                        if (count($linhasValidas) >= 20) {
                            break;
                        }
                    }

                    if (!empty($linhasValidas)) {
                        $eventosValidados[$tipoEvento] = $linhasValidas;
                    }
                }

                if (isset($dedupPost[$tipoEvento])) {
                    $modoDedup = trim((string)$dedupPost[$tipoEvento]);
                    if (in_array($modoDedup, MODOS_DEDUP_VALIDOS_WEBHOOK, true)) {
                        $dedupValidados[$tipoEvento] = $modoDedup;
                    }
                }
            }

            if (empty($eventosValidados)) {
                $statusQuery = 'error=' . urlencode('Nenhuma regra válida foi enviada.');
            } else {
                $estrutura = ['schema_version' => 1, 'eventos' => $eventosValidados, 'dedup_modos' => $dedupValidados];
                $okRegras = salvarConfiguracao('webhook_event_rules', json_encode($estrutura, JSON_UNESCAPED_UNICODE), $empresaId);
                $statusQuery = $okRegras
                    ? 'success=' . urlencode('Regras de webhook salvas com sucesso!')
                    : 'error=' . urlencode('Falha ao salvar as regras de webhook.');
            }
        }

        // AÇÃO: Restaurar Regras de Webhook para o padrão do sistema
        elseif ($action === 'reset_webhook_rules') {
            $ok = removerConfiguracao('webhook_event_rules', $empresaId);
            $statusQuery = $ok
                ? 'success=' . urlencode('Regras de webhook restauradas para o padrão do sistema!')
                : 'error=' . urlencode('Falha ao restaurar as regras de webhook.');
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
                            salvarConfiguracao('audio_alerta', 'uploads/audio/' . $newFileName, $empresaId);
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
                salvarConfiguracao('audio_alerta', 'uploads/audio/' . $selectedFile, $empresaId);
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
                if ($somAtivo === 'uploads/audio/' . $fileToDelete || $somAtivo === 'assets/audio/' . $fileToDelete) {
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
            $exibicaoLogo = isset($_POST['exibicao_logo']) ? trim($_POST['exibicao_logo']) : 'logo_nome';
            $tempoLimiteEspera = isset($_POST['tempo_limite_espera']) ? (int)$_POST['tempo_limite_espera'] : 5;

            try {
                $db = obterConexao();
                
                // Processa upload da logo se houver
                if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
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
                            
                            $logoPathDb = 'uploads/logos/' . $newLogoName;
                            $stmtL = $db->prepare("UPDATE tenants SET logo_path = :logo WHERE id = :id");
                            $stmtL->execute([':logo' => $logoPathDb, ':id' => $empresaId]);
                        }
                    }
                }

                $stmtUpdate = $db->prepare("UPDATE tenants SET cor_primaria = :cp, cor_secundaria = :cs, modo_visualizacao = :mv, exibicao_logo = :el, tempo_limite_espera = :tle WHERE id = :id");
                $stmtUpdate->execute([
                    ':cp' => $corPrimaria,
                    ':cs' => $corSecundaria,
                    ':mv' => $modoVisualizacao,
                    ':el' => $exibicaoLogo,
                    ':tle' => $tempoLimiteEspera,
                    ':id' => $empresaId
                ]);

                $statusQuery = 'success=' . urlencode('Visual da marca atualizado com sucesso!');
            } catch (Exception $e) {
                $statusQuery = 'error=' . urlencode('Erro ao atualizar configurações visuais: ' . $e->getMessage());
            }
        }

        // AÇÃO: Excluir Logotipo Personalizado
        elseif ($action === 'delete_logo') {
            try {
                $db = obterConexao();
                
                // Busca a logo atual para deletar o arquivo físico
                $logoPath = $db->query("SELECT logo_path FROM tenants WHERE id = $empresaId")->fetchColumn();
                if ($logoPath && file_exists(__DIR__ . '/../' . $logoPath)) {
                    unlink(__DIR__ . '/../' . $logoPath);
                }
                
                // Reseta a coluna logo_path para NULL
                $stmtDelLogo = $db->prepare("UPDATE tenants SET logo_path = NULL WHERE id = :id");
                $stmtDelLogo->execute([':id' => $empresaId]);
                
                $statusQuery = 'success=' . urlencode('Logotipo personalizado removido com sucesso!');
            } catch (Exception $e) {
                $statusQuery = 'error=' . urlencode('Erro ao remover logotipo: ' . $e->getMessage());
            }
        }

        // AÇÃO: Adicionar Usuário da Empresa
        elseif ($action === 'add_user') {
            $uNome = isset($_POST['usuario_nome']) ? trim($_POST['usuario_nome']) : '';
            $uEmail = isset($_POST['usuario_email']) ? trim($_POST['usuario_email']) : '';
            $uSenha = isset($_POST['usuario_senha']) ? $_POST['usuario_senha'] : '';
            $uRole = isset($_POST['usuario_role']) ? trim($_POST['usuario_role']) : 'user';

            if ($uNome && $uEmail && $uSenha) {
                $erroSenha = validarForcaSenha($uSenha);
                if ($erroSenha) {
                    $statusQuery = 'error=' . urlencode($erroSenha);
                } else {
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
        
        // Recarrega as configurações da marca para retornar o estado atualizado no AJAX
        $updatedConfig = carregarTenantPorSlug($_SESSION['tenant_ativo_slug']);
        
        echo json_encode([
            'success' => !empty($successMsg),
            'message' => $successMsg ?: $erroMsg,
            'limite_logs' => (int) obterConfiguracao('limite_logs', 100, $empresaId),
            'som_ativo' => obterConfiguracao('audio_alerta', 'assets/audio/notificacao.mp3', $empresaId),
            'marca' => [
                'cor_primaria' => $updatedConfig['cor_primaria'] ?? '#2ed573',
                'cor_secundaria' => $updatedConfig['cor_secundaria'] ?? '#70a1ff',
                'modo_visualizacao' => $updatedConfig['modo_visualizacao'] ?? 'dark',
                'exibicao_logo' => $updatedConfig['exibicao_logo'] ?? 'logo_nome',
                'logo_path' => $updatedConfig['logo_path'] ?? ''
            ]
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

// Granular sounds
$somSuporte = obterConfiguracao('audio_alerta_suporte', 'assets/audio/notificacao.mp3', $empresaId);
$somSuporteNome = basename($somSuporte);
$somLead = obterConfiguracao('audio_alerta_lead', 'assets/audio/notificacao.mp3', $empresaId);
$somLeadNome = basename($somLead);

// Regras de webhook (categoria + modo de exibição por tipo de evento) — já cai no padrão se o tenant não customizou
$webhookRegras = obterRegrasWebhook($empresaId);
$webhookRegrasCustomizadas = !empty(obterConfiguracao('webhook_event_rules', null, $empresaId));
$webhookDedupModos = obterModosDedupWebhook($empresaId);

// CRM settings
$crmIntegrationEnabled = obterConfiguracao('crm_integration_enabled', '0', $empresaId);
$crmApiUrl = obterConfiguracao('crm_api_url', '', $empresaId);
$crmApiKey = obterConfiguracao('crm_api_key', '', $empresaId);
$crmEtapaAtendimento = obterConfiguracao('crm_etapa_atendimento', '', $empresaId);
$crmEtapaFinalizado = obterConfiguracao('crm_etapa_finalizado', '', $empresaId);

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

// Carregar dados do perfil do usuário atualmente autenticado
$usuarioPerfil = [
    'nome' => $_SESSION['usuario_nome'] ?? '',
    'email' => $_SESSION['usuario_email'] ?? '',
    'role' => $_SESSION['usuario_role'] ?? 'user'
];
try {
    $db = obterConexao();
    $stmtMe = $db->prepare("SELECT id, nome, email, role, criado_em FROM usuarios WHERE id = :id");
    $stmtMe->execute([':id' => (int)$_SESSION['usuario_id']]);
    $meData = $stmtMe->fetch();
    if ($meData) {
        $usuarioPerfil = $meData;
    }
} catch (Exception $e) {
    // Mantém fallback das variáveis de sessão
}

