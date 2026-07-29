<?php
/**
 * Proxy entre a página pública e a Evolution API.
 *
 * A página do cliente (public/app.js) só conversa com este arquivo.
 * Este arquivo é o único que conhece a URL real e a API Key da
 * Evolution API — nada disso chega ao navegador.
 *
 * Ações suportadas (parâmetro ?action=):
 *   connect  (POST)  -> cria a instância (se não existir) e devolve o QR Code
 *   status   (GET)   -> devolve o estado atual da conexão (open/connecting/close)
 */

header('Content-Type: application/json; charset=utf-8');

// Ajuste/restrinja este header se a página ficar em outro domínio do backend.
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$config = require __DIR__ . '/config.php';
$baseUrl = rtrim($config['base_url'], '/');
$apiKey  = $config['api_key'];
$prefix  = $config['instance_prefix'] ?? '';

function respond(int $status, array $data): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Faz uma chamada HTTP para a Evolution API.
 */
function evolutionRequest(string $baseUrl, string $apiKey, string $method, string $path, ?array $body = null): array
{
    $ch = curl_init($baseUrl . $path);

    $headers = [
        'apikey: ' . $apiKey,
        'Content-Type: application/json',
    ];

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 20,
    ]);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
    }

    $raw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['ok' => false, 'status' => 0, 'error' => $error];
    }

    $decoded = json_decode($raw, true);
    return ['ok' => $httpCode >= 200 && $httpCode < 300, 'status' => $httpCode, 'data' => $decoded];
}

/**
 * Normaliza o nome digitado pelo cliente para um slug seguro de instância.
 */
function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim($text, '-');
    return $text !== '' ? $text : 'instancia-' . substr(md5((string) microtime()), 0, 6);
}

$action = $_GET['action'] ?? '';

if ($action === 'connect' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $rawName = $input['instance'] ?? '';

    if (trim($rawName) === '') {
        respond(400, ['error' => 'Informe um nome/identificação para a conexão.']);
    }

    $instanceName = $prefix . slugify($rawName);

    // 1) Verifica se a instância já existe.
    $check = evolutionRequest($baseUrl, $apiKey, 'GET', '/instance/fetchInstances?instanceName=' . urlencode($instanceName));
    $exists = $check['ok'] && !empty($check['data']);

    // 1.1) Se já existe, verifica se já está conectada — nesse caso não
    // precisa gerar QR Code, só avisa o front-end que já está pronta.
    if ($exists) {
        $state = evolutionRequest($baseUrl, $apiKey, 'GET', '/instance/connectionState/' . urlencode($instanceName));
        $currentState = $state['data']['instance']['state'] ?? $state['data']['state'] ?? null;
        if ($currentState === 'open') {
            respond(200, ['instance' => $instanceName, 'qrcode' => null, 'alreadyConnected' => true]);
        }
    }

    // 2) Se não existir, cria a instância já pedindo o QR Code.
    if (!$exists) {
        $create = evolutionRequest($baseUrl, $apiKey, 'POST', '/instance/create', [
            'instanceName' => $instanceName,
            'qrcode'       => true,
            'integration'  => 'WHATSAPP-BAILEYS',
        ]);

        if (!$create['ok']) {
            respond(502, ['error' => 'Não foi possível criar a instância na Evolution API.', 'details' => $create['data'] ?? $create['error'] ?? null]);
        }

        // Algumas versões já devolvem o QR na própria criação.
        $qr = $create['data']['qrcode']['base64'] ?? $create['data']['base64'] ?? null;
        if ($qr) {
            respond(200, ['instance' => $instanceName, 'qrcode' => $qr]);
        }
    }

    // 3) Busca (ou re-busca) o QR Code de conexão.
    $connect = evolutionRequest($baseUrl, $apiKey, 'GET', '/instance/connect/' . urlencode($instanceName));

    if (!$connect['ok']) {
        respond(502, ['error' => 'Não foi possível obter o QR Code.', 'details' => $connect['data'] ?? $connect['error'] ?? null]);
    }

    $qr = $connect['data']['base64'] ?? $connect['data']['qrcode']['base64'] ?? null;

    if (!$qr) {
        // Pode já estar conectado, por exemplo.
        respond(200, ['instance' => $instanceName, 'qrcode' => null, 'raw' => $connect['data']]);
    }

    respond(200, ['instance' => $instanceName, 'qrcode' => $qr]);
}

if ($action === 'status' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $rawName = $_GET['instance'] ?? '';
    if (trim($rawName) === '') {
        respond(400, ['error' => 'Instância não informada.']);
    }

    $result = evolutionRequest($baseUrl, $apiKey, 'GET', '/instance/connectionState/' . urlencode($rawName));

    if (!$result['ok']) {
        respond(502, ['error' => 'Não foi possível consultar o status.', 'details' => $result['data'] ?? $result['error'] ?? null]);
    }

    $state = $result['data']['instance']['state'] ?? $result['data']['state'] ?? 'unknown';
    respond(200, ['instance' => $rawName, 'state' => $state]);
}

if ($action === 'disconnect' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $instanceName = $input['instance'] ?? '';

    if (trim($instanceName) === '') {
        respond(400, ['error' => 'Instância não informada.']);
    }

    // "logout" desconecta o número do WhatsApp mas mantém a instância
    // cadastrada, permitindo reconectar depois só com um novo QR Code.
    $result = evolutionRequest($baseUrl, $apiKey, 'DELETE', '/instance/logout/' . urlencode($instanceName));

    if (!$result['ok']) {
        respond(502, ['error' => 'Não foi possível desconectar.', 'details' => $result['data'] ?? $result['error'] ?? null]);
    }

    respond(200, ['instance' => $instanceName, 'disconnected' => true]);
}

respond(404, ['error' => 'Ação inválida.']);
