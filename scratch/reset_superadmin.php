<?php
require_once __DIR__ . '/../db.php';

try {
    $db = obterConexao();
    $novaSenha = 'superadmin123';
    $hash = password_hash($novaSenha, PASSWORD_DEFAULT);
    
    // Verifica se o superadmin existe
    $stmtCheck = $db->prepare("SELECT id FROM usuarios WHERE email = 'superadmin@madeinai.com.br'");
    $stmtCheck->execute();
    $id = $stmtCheck->fetchColumn();
    
    if ($id) {
        $stmt = $db->prepare("UPDATE usuarios SET senha_hash = :hash WHERE id = :id");
        $stmt->execute([':hash' => $hash, ':id' => $id]);
        echo "Sucesso: A senha do superadmin (superadmin@madeinai.com.br) foi redefinida para: $novaSenha\n";
    } else {
        // Se por algum motivo o usuário não existir, vamos criá-lo
        $stmtInsert = $db->prepare("INSERT INTO usuarios (empresa_id, nome, email, senha_hash, role) 
            VALUES (NULL, 'Super Admin', 'superadmin@madeinai.com.br', :hash, 'superadmin')");
        $stmtInsert->execute([':hash' => $hash]);
        echo "Sucesso: O usuário superadmin não existia, então foi criado com o e-mail superadmin@madeinai.com.br e senha: $novaSenha\n";
    }
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
