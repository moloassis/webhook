<?php
require_once __DIR__ . '/../db.php';

try {
    $db = obterConexao();
    $db->exec("UPDATE sistema_config SET valor = REPLACE(valor, 'assets/audio/', 'uploads/audio/') WHERE valor LIKE 'assets/audio/1%'");
    $db->exec("UPDATE tenants SET logo_path = REPLACE(logo_path, 'assets/img/logos/', 'uploads/logos/') WHERE logo_path LIKE 'assets/img/logos/%'");
    echo "Banco de dados atualizado com os caminhos uploads/audio/ e uploads/logos/\n";
} catch (Exception $e) {
    echo "Erro ao atualizar banco: " . $e->getMessage() . "\n";
}
