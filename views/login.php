<?php
/**
 * View de Login - Central de Alertas
 * Renderizado diretamente para rotas /login
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
                $_SESSION['usuario_id'] = (int)$user['id'];
                $_SESSION['usuario_nome'] = $user['nome'];
                $_SESSION['usuario_email'] = $user['email'];
                $_SESSION['usuario_role'] = $user['role'];
                $_SESSION['empresa_id'] = $user['empresa_id'] ? (int)$user['empresa_id'] : null;
                $_SESSION['empresa_slug'] = $user['empresa_slug'];
                $_SESSION['empresa_nome'] = $user['empresa_nome'];
                
                // Gera o JWT para autenticação do SSE (Server-Sent Events)
                $payload = [
                    'usuario_id' => (int)$user['id'],
                    'empresa_id' => $user['empresa_id'] ? (int)$user['empresa_id'] : null,
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
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Central de Alertas Made in AI</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #0c0a1f;
            --panel-bg: rgba(18, 15, 45, 0.45);
            --border-color: rgba(255, 255, 255, 0.08);
            --color-primary: #2ed573;
            --color-primary-hover: #26af5f;
            --text-primary: #f1f2f6;
            --text-secondary: #a4b0be;
            --error-bg: rgba(255, 71, 87, 0.15);
            --error-border: rgba(255, 71, 87, 0.3);
            --error-text: #ff4757;
        }

        body {
            margin: 0;
            padding: 0;
            background: var(--bg-color);
            color: var(--text-primary);
            font-family: 'Outfit', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            overflow: hidden;
            position: relative;
        }

        /* Efeito de Luzes de Fundo (Aura) */
        body::before {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(46, 213, 115, 0.15) 0%, rgba(12, 10, 31, 0) 70%);
            top: -150px;
            right: -150px;
            z-index: 0;
        }

        body::after {
            content: '';
            position: absolute;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(112, 161, 255, 0.1) 0%, rgba(12, 10, 31, 0) 70%);
            bottom: -200px;
            left: -200px;
            z-index: 0;
        }

        .login-container {
            width: 100%;
            max-width: 420px;
            padding: 1.5rem;
            box-sizing: border-box;
            z-index: 10;
        }

        .login-card {
            background: var(--panel-bg);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 2.5rem;
            backdrop-filter: blur(20px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
            display: flex;
            flex-direction: column;
            gap: 1.8rem;
        }

        .logo-header {
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.6rem;
        }

        .logo-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--color-primary);
            box-shadow: 0 0 15px var(--color-primary);
        }

        .logo-header h1 {
            margin: 0;
            font-size: 1.6rem;
            font-weight: 600;
            letter-spacing: -0.5px;
            background: linear-gradient(135deg, #fff 0%, #a4b0be 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .logo-header p {
            margin: 0;
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-group label {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-primary);
        }

        .form-input {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 0.8rem 1rem;
            font-family: inherit;
            color: var(--text-primary);
            font-size: 0.95rem;
            transition: all 0.25s ease;
            outline: none;
        }

        .form-input:focus {
            border-color: var(--color-primary);
            background: rgba(46, 213, 115, 0.02);
            box-shadow: 0 0 10px rgba(46, 213, 115, 0.1);
        }

        .btn-submit {
            background: var(--color-primary);
            color: #0c0a1f;
            border: none;
            border-radius: 10px;
            padding: 0.9rem;
            font-family: inherit;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 0.5rem;
        }

        .btn-submit:hover {
            background: var(--color-primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 5px 15px rgba(46, 213, 115, 0.3);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .error-message {
            background: var(--error-bg);
            border: 1px solid var(--error-border);
            color: var(--error-text);
            padding: 0.8rem 1rem;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .footer-credits {
            text-align: center;
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.2);
            margin-top: 1rem;
        }
    </style>
</head>
<body>

<div class="login-container">
    <div class="login-card">
        <div class="logo-header">
            <div class="logo-dot"></div>
            <h1>Central de Alertas</h1>
            <p>Made in AI • Plataforma SaaS</p>
        </div>

        <?php if ($erro): ?>
            <div class="error-message">
                ⚠️ <?php echo htmlspecialchars($erro); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" style="display: flex; flex-direction: column; gap: 1.2rem;">
            <div class="form-group">
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" class="form-input" placeholder="exemplo@madeinai.com.br" required autocomplete="username">
            </div>

            <div class="form-group">
                <label for="password">Senha</label>
                <input type="password" id="password" name="password" class="form-input" placeholder="••••••••" required autocomplete="current-password">
            </div>

            <button type="submit" class="btn-submit">
                Entrar no Workspace ➔
            </button>
        </form>
    </div>
    
    <div class="footer-credits">
        &copy; <?php echo date('Y'); ?> Made in AI. Todos os direitos reservados.
    </div>
</div>

</body>
</html>
