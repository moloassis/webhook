<?php
/**
 * View de Login - Central de Alertas
 * Renderizado diretamente para rotas /login
 */

require_once __DIR__ . '/../controllers/login_controller.php';
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
    <link rel="stylesheet" href="assets/css/login.css">
</head>

<body>

    <div class="login-container">
        <div class="login-card">
            <div class="logo-header">
                <div class="logo-dot"></div>
                <h1>Central de Alertas</h1>
                <p>Made in AI</p>
            </div>

            <?php if ($erro): ?>
                <div class="error-message">
                    ⚠️ <?php echo htmlspecialchars($erro); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" style="display: flex; flex-direction: column; gap: 1.2rem;">
                <div class="form-group">
                    <label for="email">E-mail</label>
                    <input type="email" id="email" name="email" class="form-input" placeholder="exemplo@madeinai.com.br"
                        required autocomplete="username">
                </div>

                <div class="form-group">
                    <label for="password">Senha</label>
                    <input type="password" id="password" name="password" class="form-input" placeholder="••••••••"
                        required autocomplete="current-password">
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