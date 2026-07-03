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
        $sqlLog = "INSERT INTO webhook_logs (metodo, ip, event_type, payload, status_resposta, mensagem_resposta) 
                   VALUES (:metodo, :ip, :event_type, :payload, :status_resposta, :mensagem_resposta)";
        
        $stmtLog = $db->prepare($sqlLog);
        
        // Tenta obter o eventType do payload decodificado
        $eventTypeLog = isset($dados['eventType']) ? trim(filter_var($dados['eventType'], FILTER_SANITIZE_SPECIAL_CHARS)) : null;
        
        // Se não tiver body bruto, usa o array de dados decodificado
        $payloadLog = !empty($dadosBrutos) ? $dadosBrutos : json_encode($dados, JSON_UNESCAPED_UNICODE);
        
        $stmtLog->execute([
            ':metodo' => $_SERVER['REQUEST_METHOD'] ?? 'POST',
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'desconhecido',
            ':event_type' => $eventTypeLog,
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

// 3. Identificar o tipo de evento (HelenaCRM ou Fallback customizado)
$eventType = isset($dados['eventType']) ? trim($dados['eventType']) : null;

// Inicializa variáveis para o chamado
$nomeCliente = null;
$tipoEvent = 'default';
$mensagem = null;
$criarChamadoAtivo = false; // Define se vai subir alerta com som na tela do atendente

if ($eventType) {
    // Processamento estruturado dos payloads reais do HelenaCRM
    switch ($eventType) {
        case 'MESSAGE_SENT':
            $tipoEvent = 'MESSAGE_SENT';
            $to = $dados['content']['details']['to'] ?? '';
            $origin = $dados['content']['origin'] ?? 'AI';
            $text = $dados['content']['text'] ?? '';
            $mensagem = "IA ({$origin}) enviou mensagem para {$to}: \"{$text}\"";
            $criarChamadoAtivo = false; // Apenas informativo
            break;

        case 'MESSAGE_RECEIVED':
            $tipoEvent = 'MESSAGE_RECEIVED';
            $from = $dados['content']['details']['from'] ?? '';
            $text = $dados['content']['text'] ?? '';
            $nomeCliente = $from;
            $mensagem = "Cliente ({$from}) enviou: \"{$text}\"";
            $criarChamadoAtivo = false; // Apenas informativo
            break;

        case 'SESSION_NEW':
            $tipoEvent = 'SESSION_NEW';
            $nomeCliente = $dados['content']['contactDetails']['name'] ?? 'Desconhecido';
            $phone = $dados['content']['contactDetails']['phonenumberFormatted'] ?? '';
            $mensagem = "Nova conversa iniciada pelo WhatsApp ({$phone}).";
            $criarChamadoAtivo = true; // Exibe alerta suave
            break;

        case 'SESSION_COMPLETE':
            $tipoEvent = 'SESSION_COMPLETE';
            $nomeCliente = $dados['content']['contactDetails']['name'] ?? 'Desconhecido';
            $lastText = $dados['content']['lastMessageText'] ?? '';
            
            // Verifica se houve transferência pelo texto da última mensagem
            // Palavras-chave: "transferida", "aguarde", "humano", "suporte"
            if (preg_match('/(transferida|aguarde|humano|suporte)/i', $lastText)) {
                $mensagem = "Chatbot finalizado para transferência humana. Última msg: \"{$lastText}\"";
                $criarChamadoAtivo = true; // Alerta URGENTE de atendimento humano
            } else {
                $mensagem = "Sessão do chatbot finalizada sem transferência. Última msg: \"{$lastText}\"";
                $criarChamadoAtivo = false; // Apenas informativo
            }
            break;

        case 'PANEL_CARD_STEP_CHANGE':
        case 'PANEL_CARD_UPDATE':
            $tipoEvent = $eventType; // PANEL_CARD_STEP_CHANGE ou PANEL_CARD_UPDATE
            $nomeCliente = $dados['content']['contacts'][0]['name'] ?? ($dados['content']['title'] ?? 'Lead');
            $stepTitle = $dados['content']['stepTitle'] ?? '';
            
            // Verifica se a coluna de destino do card no CRM representa atendimento humano ou lead
            if (preg_match('/(humano|suporte|atendente|human)/i', $stepTitle)) {
                $mensagem = "Lead transferido para suporte humano na coluna: \"{$stepTitle}\"";
                $criarChamadoAtivo = true;
            } elseif (preg_match('/(lead|ia)/i', $stepTitle)) {
                $mensagem = "Card movido para etapa de qualificação: \"{$stepTitle}\"";
                $criarChamadoAtivo = true;
            } else {
                $mensagem = "Card movido no CRM para: \"{$stepTitle}\"";
                $criarChamadoAtivo = false; // Apenas informativo
            }
            break;

        default:
            $tipoEvent = $eventType;
            $mensagem = "Evento HelenaCRM não mapeado: \"{$eventType}\"";
            $criarChamadoAtivo = false;
            break;
    }
} else {
    // FALLBACK: Mantém retrocompatibilidade com o simulador de webhook da interface ou disparos manuais
    $nomeCliente = isset($dados['nome_cliente']) ? trim(filter_var($dados['nome_cliente'], FILTER_SANITIZE_SPECIAL_CHARS)) : null;
    $tipoEvent = isset($dados['tipo']) ? trim(filter_var($dados['tipo'], FILTER_SANITIZE_SPECIAL_CHARS)) : 'atendimento_humano';
    $mensagem = isset($dados['mensagem']) ? trim(filter_var($dados['mensagem'], FILTER_SANITIZE_SPECIAL_CHARS)) : null;
    
    // Se tiver dados mínimos, cria o chamado ativo na tela
    if (!empty($nomeCliente) || !empty($mensagem)) {
        $criarChamadoAtivo = true;
    }
}

// 4. Se o evento exigir ação/atenção imediata, salva na tabela `chamados` com status 'pendente'
if ($criarChamadoAtivo) {
    try {
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
            
            enviarRespostaELog(201, true, "Chamado ativo registrado e enviado ao painel.", $dadosSucesso, $dadosBrutos, $dados);
        } else {
            throw new Exception("Falha ao executar a inserção do chamado ativo.");
        }
    } catch (Exception $e) {
        registrarErro("Erro de inserção de chamado ativo: " . $e->getMessage(), [
            'payload' => $dados,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'desconhecido'
        ]);
        enviarRespostaELog(500, false, "Erro interno do servidor ao salvar chamado ativo.", null, $dadosBrutos, $dados);
    }
} else {
    // Se for apenas informativo, encerra com HTTP 200 registrando o log
    enviarRespostaELog(200, true, "Evento processado e registrado no histórico de logs (sem chamado ativo).", null, $dadosBrutos, $dados);
}
