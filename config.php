<?php
/**
 * Configuração de conexão com o banco de dados MySQL para a VPS Hostinger.
 */

// Define o fuso horário oficial para o Brasil (Brasília)
date_default_timezone_set('America/Sao_Paulo');

// Configurações globais de duração da sessão PHP (24 horas) para evitar deslogamentos involuntários
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', 86400);
    ini_set('session.cookie_lifetime', 86400);
}

// Detecção automática de ambiente (Local vs Produção na VPS Hostinger)
$isLocal = (php_sapi_name() === 'cli' 
    || (isset($_SERVER['HTTP_HOST']) && ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1'))
    || (isset($_SERVER['SERVER_NAME']) && ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_NAME'] === '127.0.0.1')));

if ($isLocal) {
    // Configurações de Banco de Dados Local (Desenvolvimento)
    define('DB_HOST', '127.0.0.1');
    define('DB_PORT', '3306');
    define('DB_NAME', 'webhook_db');
    define('DB_USER', 'root');
    define('DB_PASS', '');
} else {
    // Configurações da VPS Hostinger (Produção)
    define('DB_HOST', 'helena-crm_helenacrm-db');
    define('DB_PORT', '3306');
    define('DB_NAME', 'helena-crm');
    define('DB_USER', 'mysql');
    define('DB_PASS', 'oou4f9n98k8qug5lovhe');
}

// Configurações adicionais de charset para evitar problemas de acentuação (UTF-8)
define('DB_CHARSET', 'utf8mb4');

// --- CONFIGURAÇÃO DE CHAVES VAPID PARA PWA WEB PUSH ---
define('VAPID_PUBLIC_KEY', 'BOZa81Pmnrmb5N7i9XMDa4tgI_E_Im_6_lDH7dTjwwBn2aVm5nhk7UWxTDrmsJyZsSU96KPXhYO8GFoesloNDlw');
define('VAPID_PRIVATE_KEY', 'UhVqVY_ySkbmE1DXrUg0IjQ8BY6gTO0KvmEt29lXSrg');
define('VAPID_SUBJECT', 'mailto:contato@madeinai.com.br');

// --- CHAVE SECRETA PARA ASSINATURA JWT ---
define('JWT_SECRET', 'c651c2467d8b6fb6ad07a5436af7761274a28aed66a9ab4db4bd2921065aa425e');

// --- CONFIGURAÇÃO DE LOGS DE ERROS ---
// Não exibe erros brutos no navegador (essencial para que o SSE não quebre e por segurança na VPS Hostinger)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);

// Caminho padrão do log de erros nativos do PHP
ini_set('error_log', __DIR__ . '/erros_php.log');

// Reporta todos os erros
error_reporting(E_ALL);

/**
 * Função personalizada para registro de logs de erros específicos do sistema.
 * Grava mensagens com timestamps e contextos estruturados.
 * 
 * @param string $mensagem Mensagem descritiva do erro.
 * @param array $contexto Dados associados relevantes para depuração (ex: payload, IPs).
 */
function registrarErro(string $mensagem, array $contexto = []): void
{
    $caminhoLog = __DIR__ . '/erros_sistema.log';
    $dataHora = date('Y-m-d H:i:s');

    // Formata o contexto em JSON caso exista
    $contextoTexto = '';
    if (!empty($contexto)) {
        $contextoTexto = ' | Contexto: ' . json_encode($contexto, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    // Monta a linha do log
    $linhaLog = sprintf("[%s] ERRO: %s%s%s", $dataHora, $mensagem, $contextoTexto, PHP_EOL);

    // Grava de forma segura (Lock exclusivo contra concorrência de escrita)
    file_put_contents($caminhoLog, $linhaLog, FILE_APPEND | LOCK_EX);
}

// Carrega o helper de segurança globalmente
require_once __DIR__ . '/helpers/security.php';

