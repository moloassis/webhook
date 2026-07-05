<?php
/**
 * Script temporário para ler erros do sistema.
 */
header('Content-Type: text/plain; charset=UTF-8');

$caminhoLog = __DIR__ . '/erros_sistema.log';
if (file_exists($caminhoLog)) {
    echo "Conteúdo de erros_sistema.log (últimas 20 linhas):\n\n";
    $linhas = file($caminhoLog);
    $linhasExibir = array_slice($linhas, -20);
    echo implode("", $linhasExibir);
} else {
    echo "Arquivo erros_sistema.log não existe na raiz!\n";
}

echo "\n--- Fim do Log ---\n";
