<?php
/**
 * Webhook Receiver - HelenaCRM integration
 * Recebe chamados em tempo real do CRM via HTTP POST e salva no Banco de Dados.
 * Grava logs estruturados de todas as execuções (sucesso ou falha).
 */

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Carrega o arquivo de conexão
require_once __DIR__ . '/db.php';

/**
 * Envia a resposta HTTP, grava o log no banco de dados e encerra a execução.
 */
function enviarRespostaELog(int $statusCode, bool $sucesso, string $mensagemResponse, $dadosExtra = null, string $dadosBrutos = '', array $dados = []) {
    // 1. Gravar o log da requisição na tabela `webhook_logs`
    try {
        $db = obterConexao();
        $sqlLog = "INSERT INTO webhook_logs (metodo, ip, payload, status_resposta, mensagem_resposta) 
                   VALUES (:metodo, :ip, :payload, :status_resposta, :mensagem_resposta)";
        
        $stmtLog = $db->prepare($sqlLog);
        
        // Se não tiver body bruto, usa o array de dados decodificado
        $payloadLog = !empty($dadosBrutos) ? $dadosBrutos : json_encode($dados, JSON_UNESCAPED_UNICODE);
        
        $stmtLog->execute([
            ':metodo' => $_SERVER['REQUEST_METHOD'] ?? 'POST',
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'desconhecido',
            ':payload' => $payloadLog,
            ':status_resposta' => $statusCode,
            ':mensagem_resposta' => $mensagemResponse
        ]);
    } catch (Exception $e) {
        // Se falhar o log no banco, registra no erro de sistema para não travar o webhook
        registrarErro("Falha ao salvar log de webhook no banco: " . $e->getMessage());
    }

    // 2. Responder ao cliente em formato JSON
    http_response_code($statusCode);
    $resposta = [
        'sucesso' => $sucesso,
        'mensagem' => $mensagemResponse
    ];
    
    if ($dadosExtra !== null) {
        $resposta['dados'] = $dadosExtra;
    }
    
    echo json_encode($resposta, JSON_UNESCAPED_UNICODE);
    exit;
}

// 1. Validar se o método HTTP é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    enviarRespostaELog(405, false, 'Método não permitido. Utilize HTTP POST.');
}

// 2. Capturar os dados enviados (suporta JSON ou $_POST convencional)
$dadosBrutos = file_get_contents('php://input');
$dados = json_decode($dadosBrutos, true);

// Se não for JSON válido, tenta pegar via POST tradicional (form-urlencoded)
if (json_last_error() !== JSON_ERROR_NONE || empty($dados)) {
    $dados = $_POST;
}

// --- INSPEÇÃO/LOG EM ARQUIVO LOCAL (MANTIDO COMO BACKUP) ---
$logPath = __DIR__ . '/webhooks_recebidos.log';
$headers = function_exists('getallheaders') ? getallheaders() : [];
$dadosLog = [
    'data' => date('Y-m-d H:i:s'),
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'desconhecido',
    'metodo' => $_SERVER['REQUEST_METHOD'],
    'headers' => $headers,
    'get' => $_GET,
    'post_bruto' => $dadosBrutos,
    'post_decodificado' => $dados
];
$separador = str_repeat('=', 60) . PHP_EOL;
file_put_contents(
    $logPath, 
    $separador . json_encode($dadosLog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL . $separador . PHP_EOL, 
    FILE_APPEND | LOCK_EX
);

// 3. Extrair e sanitizar variáveis
$nomeCliente = isset($dados['nome_cliente']) ? trim(filter_var($dados['nome_cliente'], FILTER_SANITIZE_SPECIAL_CHARS)) : null;
$tipoEvent = isset($dados['tipo']) ? trim(filter_var($dados['tipo'], FILTER_SANITIZE_SPECIAL_CHARS)) : 'atendimento_humano';
$mensagem = isset($dados['mensagem']) ? trim(filter_var($dados['mensagem'], FILTER_SANITIZE_SPECIAL_CHARS)) : null;

// 4. Validar dados essenciais
if (empty($nomeCliente) && empty($mensagem)) {
    registrarErro("Webhook recebeu dados inválidos (sem nome ou mensagem).", [
        'payload' => $dados,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'desconhecido'
    ]);
    enviarRespostaELog(400, false, 'Dados inválidos. Envie pelo menos o "nome_cliente" ou "mensagem".', null, $dadosBrutos, $dados);
}

try {
    // 5. Inserir chamado ativo com status 'pendente'
    $db = obterConexao();
    
    $sql = "INSERT INTO chamados (nome_cliente, tipo, mensagem, status, criado_em) 
            VALUES (:nome_cliente, :tipo, :mensagem, 'pendente', NOW())";
            
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':nome_cliente', $nomeCliente, PDO::PARAM_STR);
    $stmt->bindValue(':tipo', $tipoEvent, PDO::PARAM_STR);
    $stmt->bindValue(':mensagem', $mensagem, PDO::PARAM_STR);
    
    if ($stmt->execute()) {
        $lastId = $db->lastInsertId();
        
        $dadosSucesso = [
            'id' => $lastId,
            'nome_cliente' => $nomeCliente,
            'tipo' => $tipoEvent,
            'mensagem' => $mensagem,
            'status' => 'pendente'
        ];
        
        enviarRespostaELog(201, true, 'Chamado registrado com sucesso!', $dadosSucesso, $dadosBrutos, $dados);
    } else {
        throw new Exception("Falha ao executar a inserção do chamado.");
    }
} catch (Exception $e) {
    registrarErro("Erro de inserção no Webhook: " . $e->getMessage(), [
        'payload' => $dados,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'desconhecido'
    ]);
    enviarRespostaELog(500, false, 'Erro interno do servidor ao salvar o chamado.', null, $dadosBrutos, $dados);
}
