<?php
/**
 * Configuração de conexão com o banco de dados MySQL para a VPS Hostinger.
 */

// Define o fuso horário oficial para o Brasil (Brasília)
date_default_timezone_set('America/Sao_Paulo');

// Host do banco de dados (geralmente 'localhost' ou '127.0.0.1' em VPS local)
define('DB_HOST', 'helena-crm_helenacrm-db');

// Porta do banco de dados (padrão MySQL: 3306)
define('DB_PORT', '3306');

// Nome do banco de dados
define('DB_NAME', 'helena-crm');

// Usuário do banco de dados
define('DB_USER', 'mysql');

// Senha do banco de dados
define('DB_PASS', 'oou4f9n98k8qug5lovhe');

// Configurações adicionais de charset para evitar problemas de acentuação (UTF-8)
define('DB_CHARSET', 'utf8mb4');

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

