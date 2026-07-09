<?php
/**
 * Helper de Segurança - Central de Alertas
 * Gerencia tokens CSRF e validação de senhas robustas.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Gera um token CSRF e o armazena na sessão se não existir.
 * Retorna o token.
 */
function obterTokenCSRF(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Gera um campo HTML input oculto contendo o token CSRF.
 */
function renderizarCampoCSRF(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(obterTokenCSRF(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Verifica se o token CSRF enviado na requisição POST/GET corresponde ao token da sessão.
 */
function validarTokenCSRF(?string $token = null): bool {
    if ($token === null) {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        // Normaliza as chaves do header para comparação case-insensitive
        $normalizedHeaders = array_change_key_case($headers, CASE_LOWER);
        $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? $normalizedHeaders['x-csrf-token'] ?? '';
    }
    $sessaoToken = $_SESSION['csrf_token'] ?? '';
    return !empty($sessaoToken) && !empty($token) && hash_equals($sessaoToken, $token);
}

/**
 * Valida a força/complexidade de uma senha.
 * Retorna uma mensagem de erro string se inválida, ou null se válida.
 */
function validarForcaSenha(string $senha): ?string {
    if (strlen($senha) < 6) {
        return 'A senha deve ter pelo menos 6 caracteres.';
    }
    if (!preg_match('/[A-Z]/', $senha)) {
        return 'A senha deve conter pelo menos uma letra maiúscula.';
    }
    if (!preg_match('/[a-z]/', $senha)) {
        return 'A senha deve conter pelo menos uma letra minúscula.';
    }
    if (!preg_match('/[0-9]/', $senha)) {
        return 'A senha deve conter pelo menos um número.';
    }
    if (!preg_match('/[^a-zA-Z0-9]/', $senha)) {
        return 'A senha deve conter pelo menos um caractere especial (ex: @, #, $, etc.).';
    }
    return null;
}
