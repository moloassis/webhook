<?php
/**
 * Roteador Central PWA - Central de Alertas Multi-Tenant
 * Gerencia o fluxo de rotas, sessões, contextos de tenant e segurança.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Carrega o banco de dados e arquivos de configuração globais
require_once __DIR__ . '/db.php';

// 2. Calcula dinamicamente o caminho base da URL
$baseUrl = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';

// 3. Captura e limpa a rota da URL amigável
$route = isset($_GET['route']) ? trim($_GET['route'], '/') : '';

// 4. Tratamento das Rotas Públicas de Autenticação
if ($route === 'login') {
    require_once __DIR__ . '/views/login.php';
    exit;
}

if ($route === 'logout') {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    header("Location: login");
    exit;
}

// 5. Tratamento de Rotas do Superadmin
if ($route === 'superadmin' || strpos($route, 'superadmin/') === 0) {
    require_once __DIR__ . '/helpers/tenant_context.php';
    exigirRole(['superadmin']);
    
    // Roteamento interno do superadmin
    $subRoute = substr($route, 11); // Remove 'superadmin/' se existir
    require_once __DIR__ . '/views/superadmin.php';
    exit;
}

// 6. Tratamento de Rotas do Tenant (t/{slug}/{sub_route})
$tenantSlug = null;
$subRoute = '';

if (preg_match('/^t\/([a-zA-Z0-9_-]+)(?:\/(.*))?$/', $route, $matches)) {
    $tenantSlug = $matches[1];
    $subRoute = isset($matches[2]) ? trim($matches[2], '/') : 'dashboard';
    if ($subRoute === '') {
        $subRoute = 'dashboard';
    }
}

if ($tenantSlug !== null) {
    // Inicializa o contexto do tenant (verifica segurança, carrega cores e logo)
    require_once __DIR__ . '/helpers/tenant_context.php';
    $tenantConfig = inicializarContextoTenant($tenantSlug);
    
    // Armazena a rota para que views saibam qual renderizar
    $currentView = $subRoute;

    // Detecta requisições parciais (AJAX/POST) para responder sem o layout HTML global (header/footer)
    $isAjax = isset($_GET['render_library']) || isset($_POST['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
    $isPost = ($_SERVER['REQUEST_METHOD'] === 'POST');

    if ($isAjax || $isPost) {
        switch ($currentView) {
            case 'logs':
                require_once __DIR__ . '/controllers/logs_controller.php';
                exit;
            case 'settings':
            case 'configuracoes':
                require_once __DIR__ . '/controllers/settings_controller.php';
                exit;
        }
    }
    
    // Injeta o cabeçalho dinâmico (com CSS customizado e modal)
    require_once __DIR__ . '/templates/header.php';
    
    // Renderiza a view correspondente
    switch ($currentView) {
        case 'logs':
            require_once __DIR__ . '/views/logs.php';
            break;
            
        case 'settings':
        case 'configuracoes':
            require_once __DIR__ . '/views/settings.php';
            break;
            
        case 'dashboard':
        default:
            require_once __DIR__ . '/views/dashboard.php';
            break;
    }
    
    // Carrega o rodapé
    require_once __DIR__ . '/templates/footer.php';
    exit;
}

// 7. Rota Padrão / Raiz (Redirecionamento Inteligente)
if ($route === '' || $route === 'dashboard') {
    require_once __DIR__ . '/helpers/tenant_context.php';
    if (isset($_SESSION['usuario_id'])) {
        if ($_SESSION['usuario_role'] === 'superadmin') {
            header("Location: superadmin");
        } else {
            header("Location: t/" . $_SESSION['empresa_slug'] . "/dashboard");
        }
    } else {
        header("Location: login");
    }
    exit;
}

// Se nenhuma rota corresponder, exibe 404
http_response_code(404);
echo "<!DOCTYPE html>
<html lang='pt-BR'>
<head><meta charset='UTF-8'><title>404 - Não Encontrado</title></head>
<body style='background: #0c0a1f; color: #ff4757; font-family: sans-serif; text-align: center; padding: 5rem;'>
    <h1>🔍 404 - Página Não Encontrada</h1>
    <p style='color: #f1f2f6;'>A rota solicitada não existe ou é inválida.</p>
    <p><a href='./' style='color: #2ed573; text-decoration: none;'>Voltar ao início</a></p>
</body>
</html>";
