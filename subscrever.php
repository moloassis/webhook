<?php
/**
 * API Endpoint para registrar inscrições de Web Push do PWA.
 * Recebe chaves criptográficas do cliente e armazena no banco de dados.
 */

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once __DIR__ . '/db.php';

// 1. Validar se o método é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Método não permitido. Utilize HTTP POST.'
    ]);
    exit;
}

// 2. Capturar os dados recebidos
$dadosBrutos = file_get_contents('php://input');
$dados = json_decode($dadosBrutos, true);

if (empty($dados) || empty($dados['endpoint']) || empty($dados['keys']['p256dh']) || empty($dados['keys']['auth'])) {
    http_response_code(400);
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Parâmetros de inscrição inválidos ou incompletos.'
    ]);
    exit;
}

$endpoint = trim($dados['endpoint']);
$keysP256dh = trim($dados['keys']['p256dh']);
$keysAuth = trim($dados['keys']['auth']);

try {
    $db = obterConexao();

    // Tenta inserir a nova inscrição ou atualizar caso já exista (usamos INSERT IGNORE ou ON DUPLICATE KEY UPDATE)
    // Nota: Como emulacao de prepares está desativada no db.php, não podemos repetir placeholders nomeados na mesma query.
    $sql = "INSERT INTO pwa_subscriptions (endpoint, keys_p256dh, keys_auth) 
            VALUES (:endpoint, :keys_p256dh, :keys_auth)
            ON DUPLICATE KEY UPDATE keys_p256dh = :keys_p256dh_update, keys_auth = :keys_auth_update";

    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':endpoint' => $endpoint,
        ':keys_p256dh' => $keysP256dh,
        ':keys_auth' => $keysAuth,
        ':keys_p256dh_update' => $keysP256dh,
        ':keys_auth_update' => $keysAuth
    ]);

    http_response_code(200);
    echo json_encode([
        'sucesso' => true,
        'mensagem' => 'Inscrição de notificações push registrada com sucesso!'
    ]);
} catch (Exception $e) {
    registrarErro("Erro ao registrar inscrição PWA Push: " . $e->getMessage(), [
        'payload' => $dados
    ]);
    
    http_response_code(500);
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Erro interno ao salvar inscrição de notificações.'
    ]);
}
