<?php
/**
 * Controller de Login - Central de Alertas
 * Gerencia a autenticação, controle de sessões e geração de tokens JWT.
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers/jwt.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$erro = '';

// Se já estiver logado, redireciona apropriadamente
if (isset($_SESSION['usuario_id'])) {
    if ($_SESSION['usuario_role'] === 'superadmin') {
        header("Location: superadmin");
        exit;
    } elseif (isset($_SESSION['empresa_slug'])) {
        header("Location: t/" . $_SESSION['empresa_slug'] . "/dashboard");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $senha = isset($_POST['password']) ? $_POST['password'] : '';

    if ($email && $senha) {
        try {
            $db = obterConexao();
            $stmt = $db->prepare("SELECT u.*, t.slug as empresa_slug, t.nome as empresa_nome 
                                  FROM usuarios u 
                                  LEFT JOIN tenants t ON u.empresa_id = t.id 
                                  WHERE u.email = :email");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();

            if ($user && password_verify($senha, $user['senha_hash'])) {
                // Login com sucesso!
                $_SESSION['usuario_id'] = (int) $user['id'];
                $_SESSION['usuario_nome'] = $user['nome'];
                $_SESSION['usuario_email'] = $user['email'];
                $_SESSION['usuario_role'] = $user['role'];
                $_SESSION['empresa_id'] = $user['empresa_id'] ? (int) $user['empresa_id'] : null;
                $_SESSION['empresa_slug'] = $user['empresa_slug'];
                $_SESSION['empresa_nome'] = $user['empresa_nome'];

                // Gera o JWT para autenticação do SSE (Server-Sent Events)
                $payload = [
                    'usuario_id' => (int) $user['id'],
                    'empresa_id' => $user['empresa_id'] ? (int) $user['empresa_id'] : null,
                    'role' => $user['role']
                ];
                $_SESSION['jwt_token'] = JWT::encode($payload, JWT_SECRET);

                // Redirecionamento
                $redirectUrl = isset($_GET['redirect']) ? $_GET['redirect'] : '';
                if ($redirectUrl) {
                    header("Location: " . $redirectUrl);
                    exit;
                }

                if ($user['role'] === 'superadmin') {
                    header("Location: superadmin");
                } else {
                    header("Location: t/" . $user['empresa_slug'] . "/dashboard");
                }
                exit;
            } else {
                $erro = 'E-mail ou senha incorretos.';
            }
        } catch (Exception $e) {
            registrarErro("Erro na autenticação: " . $e->getMessage());
            $erro = 'Erro interno ao processar autenticação. Tente novamente.';
        }
    } else {
        $erro = 'Preencha todos os campos.';
    }
}
