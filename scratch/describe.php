<?php
require_once __DIR__ . '/../db.php';
$db = obterConexao();
echo "CHAMADOS SCHEMA:\n";
print_r($db->query("DESCRIBE chamados")->fetchAll());
echo "\nWEBHOOK_LOGS SCHEMA:\n";
print_r($db->query("DESCRIBE webhook_logs")->fetchAll());
echo "\nUSUARIOS SCHEMA:\n";
print_r($db->query("DESCRIBE usuarios")->fetchAll());
