<?php
/**
 * Roteador Central PWA - Central de Alertas Made in AI
 * Determina o ambiente, calcula a base da URL e renderiza os templates e views dinâmicas.
 */

// 1. Carrega o banco de dados e arquivos de configuração globais
require_once __DIR__ . '/db.php';

// 2. Calcula dinamicamente o caminho base da URL (trata subpastas locais ex: '/webhook/' e raiz em prod '/')
$baseUrl = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';

// 3. Captura e limpa a rota da URL amigável (fornecida pelo mod_rewrite via query string 'route')
$route = isset($_GET['route']) ? trim($_GET['route'], '/') : 'dashboard';

// Se a rota for a raiz vazia, redireciona/define para o dashboard
if ($route === '') {
    $route = 'dashboard';
}

// 4. Carrega o Cabeçalho (Header) comum que contém o CSS, conexões PWA, Metatags e o Modal Urgente
require_once __DIR__ . '/templates/header.php';

// 5. Renderiza a View correspondente com base na rota atual
switch ($route) {
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

// 6. Carrega o Rodapé (Footer) comum que encerra o HTML e inclui os arquivos Javascript e Service Worker
require_once __DIR__ . '/templates/footer.php';
