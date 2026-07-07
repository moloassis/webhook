<?php
/**
 * Gerenciador de Contexto de Tenant e Autenticação.
 * Controla sessões, níveis de acesso (roles) e isolamento visual/funcional.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Exige que o usuário esteja autenticado. Caso contrário, redireciona para o login.
 */
function exigirAutenticacao(): void
{
    if (!isset($_SESSION['usuario_id'])) {
        // Salva a URL solicitada para redirecionamento pós-login
        $redirect = $_SERVER['REQUEST_URI'];
        global $baseUrl;
        $base = isset($baseUrl) ? $baseUrl : '/';
        $loginUrl = $base . 'login?redirect=' . urlencode($redirect);
        header("Location: " . $loginUrl);
        exit;
    }
}

/**
 * Verifica se o usuário autenticado possui uma das roles permitidas.
 * 
 * @param array $rolesPermitidas Lista de roles permitidas (ex: ['superadmin', 'admin'])
 */
function exigirRole(array $rolesPermitidas): void
{
    exigirAutenticacao();
    if (!in_array($_SESSION['usuario_role'], $rolesPermitidas)) {
        http_response_code(403);
        echo "<!DOCTYPE html>
        <html lang='pt-BR'>
        <head><meta charset='UTF-8'><title>Acesso Negado</title></head>
        <body style='background: #0c0a1f; color: #ff4757; font-family: sans-serif; text-align: center; padding: 5rem;'>
            <h1>❌ 403 - Acesso Negado</h1>
            <p style='color: #f1f2f6;'>Você não tem permissão para acessar esta área.</p>
            <p><a href='./' style='color: #2ed573; text-decoration: none;'>Voltar ao painel principal</a></p>
        </body>
        </html>";
        exit;
    }
}

/**
 * Obtém os dados de um tenant pelo seu slug.
 * 
 * @param string $slug Slug do tenant.
 * @return array|null Dados do tenant ou null se não encontrado.
 */
function carregarTenantPorSlug(string $slug): ?array
{
    try {
        $db = obterConexao();
        $stmt = $db->prepare("SELECT * FROM tenants WHERE slug = :slug");
        $stmt->execute([':slug' => $slug]);
        $tenant = $stmt->fetch();
        return $tenant ?: null;
    } catch (Exception $e) {
        registrarErro("Erro ao carregar tenant pelo slug {$slug}: " . $e->getMessage());
        return null;
    }
}

/**
 * Carrega o contexto do tenant ativo e injeta as variáveis globais de estilização e PWA.
 * Também garante o isolamento: impede que usuários vejam outros tenants (exceto Superadmins).
 * 
 * @param string $slug Slug do tenant da rota atual.
 * @return array Dados do tenant configurado.
 */
function inicializarContextoTenant(string $slug): array
{
    exigirAutenticacao();

    $tenant = carregarTenantPorSlug($slug);

    if (!$tenant) {
        http_response_code(404);
        echo "<!DOCTYPE html>
        <html lang='pt-BR'>
        <head><meta charset='UTF-8'><title>Não Encontrado</title></head>
        <body style='background: #0c0a1f; color: #ff4757; font-family: sans-serif; text-align: center; padding: 5rem;'>
            <h1>🔍 404 - Workspace não encontrado</h1>
            <p style='color: #f1f2f6;'>O espaço de trabalho '$slug' não existe ou foi removido.</p>
        </body>
        </html>";
        exit;
    }

    // Controle de Acesso / Isolamento:
    // Se o usuário logado não for Superadmin, ele deve pertencer obrigatoriamente a este tenant.
    if ($_SESSION['usuario_role'] !== 'superadmin' && (int)$_SESSION['empresa_id'] !== (int)$tenant['id']) {
        http_response_code(403);
        echo "<!DOCTYPE html>
        <html lang='pt-BR'>
        <head><meta charset='UTF-8'><title>Acesso Proibido</title></head>
        <body style='background: #0c0a1f; color: #ff4757; font-family: sans-serif; text-align: center; padding: 5rem;'>
            <h1>❌ 403 - Acesso Proibido</h1>
            <p style='color: #f1f2f6;'>Você pertence a outra organização e não pode acessar este painel.</p>
            <p><a href='logout' style='color: #2ed573; text-decoration: none;'>Logar com outra conta</a></p>
        </body>
        </html>";
        exit;
    }

    // Injeta os dados do tenant na sessão e no escopo global para o header.php
    $_SESSION['tenant_ativo_id'] = $tenant['id'];
    $_SESSION['tenant_ativo_slug'] = $tenant['slug'];
    $_SESSION['tenant_ativo_nome'] = $tenant['nome'];
    
    // Retorna as configurações do tenant
    return $tenant;
}

/**
 * Envia uma notificação push customizada para todos os atendentes inscritos de uma empresa.
 */
function enviarPushNotificacaoCustom(string $titulo, string $mensagemPush, string $urlRedirect, int $empresaId): void
{
    if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
        require_once __DIR__ . '/../vendor/autoload.php';
    }
    
    try {
        $db = obterConexao();
        // Carrega todas as inscrições PWA dos usuários desta empresa
        $stmt = $db->prepare("SELECT p.id, p.endpoint, p.keys_p256dh, p.keys_auth 
                               FROM pwa_subscriptions p
                               JOIN usuarios u ON p.usuario_id = u.id
                               WHERE u.empresa_id = :empresa_id");
        $stmt->execute([':empresa_id' => $empresaId]);
        $inscricoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($inscricoes)) {
            return;
        }
        
        $auth = [
            'VAPID' => [
                'subject' => VAPID_SUBJECT,
                'publicKey' => VAPID_PUBLIC_KEY,
                'privateKey' => VAPID_PRIVATE_KEY,
            ],
        ];

        $webPush = new \Minishlink\WebPush\WebPush($auth);
        
        foreach ($inscricoes as $ins) {
            $webPush->queueNotification(
                \Minishlink\WebPush\Subscription::create([
                    'endpoint' => $ins['endpoint'],
                    'publicKey' => $ins['keys_p256dh'],
                    'authToken' => $ins['keys_auth'],
                ]),
                json_encode([
                    'titulo' => $titulo,
                    'mensagem' => $mensagemPush,
                    'url' => $urlRedirect
                ], JSON_UNESCAPED_UNICODE)
            );
        }

        $idsParaRemover = [];
        foreach ($webPush->flush() as $report) {
            if (!$report->isSuccess()) {
                $response = $report->getResponse();
                $statusCode = $response ? $response->getStatusCode() : null;
                if ($statusCode === 410 || $statusCode === 404) {
                    $endpointUrl = $report->getRequest()->getUri()->__toString();
                    foreach ($inscricoes as $ins) {
                        if ($ins['endpoint'] === $endpointUrl) {
                            $idsParaRemover[] = $ins['id'];
                            break;
                        }
                    }
                }
            }
        }

        if (!empty($idsParaRemover)) {
            $placeholders = implode(',', array_fill(0, count($idsParaRemover), '?'));
            $sqlDelete = "DELETE FROM pwa_subscriptions WHERE id IN ($placeholders)";
            $stmtDel = $db->prepare($sqlDelete);
            $stmtDel->execute($idsParaRemover);
            registrarErro("Inscrições de Web Push inativas removidas em lote: " . implode(', ', $idsParaRemover));
        }

    } catch (Exception $e) {
        registrarErro("Falha ao processar Web Push Customizado: " . $e->getMessage());
    }
}

