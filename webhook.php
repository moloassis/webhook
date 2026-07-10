<?php
/**
 * Webhook Receiver - Made in AI integration
 * Recebe chamados em tempo real do CRM via HTTP POST e salva no Banco de Dados.
 * Grava logs estruturados de todas as execuções (sucesso ou falha).
 */

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Carrega o arquivo de conexão
require_once __DIR__ . '/db.php';

// Carrega dependências do Composer (necessário para WebPush)
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

/**
 * Envia a resposta HTTP, grava o log no banco de dados e encerra a execução.
 */
function enviarRespostaELogSemEmpresa(int $statusCode, bool $sucesso, string $mensagemResponse)
{
    http_response_code($statusCode);
    echo json_encode([
        'sucesso' => $sucesso,
        'mensagem' => $mensagemResponse
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function enviarRespostaELog(int $statusCode, bool $sucesso, string $mensagemResponse, $dadosExtra = null, string $dadosBrutos = '', array $dados = [], ?int $empresaId = null)
{
    // 1. Gravar o log da requisição na tabela `webhook_logs`
    if ($empresaId !== null) {
        try {
            $db = obterConexao();
            $sqlLog = "INSERT INTO webhook_logs (empresa_id, metodo, ip, event_type, payload, status_resposta, mensagem_resposta) 
                       VALUES (:empresa_id, :metodo, :ip, :event_type, :payload, :status_resposta, :mensagem_resposta)";

            $stmtLog = $db->prepare($sqlLog);

            // Tenta obter o eventType do payload decodificado
            $eventTypeLog = isset($dados['eventType']) ? trim(filter_var($dados['eventType'], FILTER_SANITIZE_SPECIAL_CHARS)) : null;

            // Se não tiver body bruto, usa o array de dados decodificado
            $payloadLog = !empty($dadosBrutos) ? $dadosBrutos : json_encode($dados, JSON_UNESCAPED_UNICODE);

            $stmtLog->execute([
                ':empresa_id' => $empresaId,
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
    enviarRespostaELogSemEmpresa(405, false, 'Método não permitido. Utilize HTTP POST.');
}

// 1.2 Validar token do webhook para isolar a empresa
$token = isset($_GET['token']) ? trim($_GET['token']) : '';
if (empty($token)) {
    enviarRespostaELogSemEmpresa(401, false, 'Token do webhook não fornecido.');
}

try {
    $db = obterConexao();
    $stmt = $db->prepare("SELECT id FROM tenants WHERE webhook_token = :token");
    $stmt->execute([':token' => $token]);
    $empresaId = $stmt->fetchColumn();

    if (!$empresaId) {
        enviarRespostaELogSemEmpresa(403, false, 'Token do webhook inválido ou inativo.');
    }
} catch (Exception $e) {
    registrarErro("Erro ao validar token de webhook: " . $e->getMessage());
    enviarRespostaELogSemEmpresa(500, false, 'Erro interno ao processar webhook.');
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

// 3. Identificar o tipo de evento (Made in AI ou Fallback customizado)
$eventType = isset($dados['eventType']) ? trim($dados['eventType']) : null;

// Inicializa variáveis para o chamado
$nomeCliente = null;
$tipoEvent = 'default';
$mensagem = null;
$sessionId = null;
if (isset($dados['content']['sessionId'])) {
    $sessionId = trim($dados['content']['sessionId']);
} elseif (isset($dados['sessionId'])) {
    $sessionId = trim($dados['sessionId']);
} elseif (isset($dados['content']['id'])) {
    $sessionId = trim($dados['content']['id']);
} elseif (isset($dados['id'])) {
    $sessionId = trim($dados['id']);
}
$criarChamadoAtivo = false; // Define se vai subir alerta com som na tela do atendente

if ($eventType) {
    // Processamento estruturado dos payloads reais do Made in AI
    switch ($eventType) {
        case 'MESSAGE_SENT':
            $tipoEvent = 'MESSAGE_SENT';
            $to = $dados['content']['details']['to'] ?? '';
            $origin = $dados['content']['origin'] ?? 'AI';
            $text = $dados['content']['text'] ?? '';
            $mensagem = "IA ({$origin}) enviou mensagem para {$to}: \"{$text}\"";
            $criarChamadoAtivo = false; // Apenas informativo

            // Se uma resposta foi enviada pelo atendente humano (origem DEFAULT), encerra os chamados de suporte para este contato
            if ($origin === 'DEFAULT' && !empty($sessionId)) {
                try {
                    $db = obterConexao();
                    $stmtUpdate = $db->prepare("UPDATE chamados SET status = 'resolvido' WHERE session_id = :session_id AND status IN ('pendente', 'aguardando') AND empresa_id = :empresa_id");
                    $stmtUpdate->execute([
                        ':session_id' => $sessionId,
                        ':empresa_id' => (int)$empresaId
                    ]);
                } catch (Exception $e) {
                    registrarErro("Erro ao fechar chamado por MESSAGE_SENT: " . $e->getMessage());
                }
            }
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

        case 'CONTACT_TAG_UPDATE':
            $tipoEvent = 'CONTACT_TAG_UPDATE';
            $nomeCliente = $dados['content']['name'] ?? 'Desconhecido';
            $tags = $dados['content']['tags'] ?? [];

            // Converte todas as tags para minúsculo para busca segura e sem erros de caixa alta
            $tagsMinusculas = array_map(function ($t) {
                return mb_strtolower(trim($t)); }, $tags);

            if (in_array('atendimento humano', $tagsMinusculas)) {
                $mensagem = "Cliente etiquetado para Atendimento Humano no CRM.";
                $criarChamadoAtivo = true; // Dispara alerta vermelho na tela do atendente
            } else {
                $mensagem = "Tags atualizadas do contato: " . implode(', ', $tags);
                $criarChamadoAtivo = false; // Apenas entra no log de histórico
            }
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
            $mensagem = "Evento Made in AI não mapeado: \"{$eventType}\"";
            $criarChamadoAtivo = false;
            break;
    }
} else {
    // FALLBACK: Mantém retrocompatibilidade com o simulador de webhook da interface ou disparos manuais
    $nomeCliente = isset($dados['nome_cliente']) ? trim(filter_var($dados['nome_cliente'], FILTER_SANITIZE_SPECIAL_CHARS)) : null;
    $tipoEvent = isset($dados['tipo']) ? trim(filter_var($dados['tipo'], FILTER_SANITIZE_SPECIAL_CHARS)) : 'atendimento_humano';
    $mensagem = isset($dados['mensagem']) ? trim(filter_var($dados['mensagem'], FILTER_SANITIZE_SPECIAL_CHARS)) : null;
    $sessionId = isset($dados['session_id']) ? trim(filter_var($dados['session_id'], FILTER_SANITIZE_SPECIAL_CHARS)) : null;

    // Se tiver dados mínimos, cria o chamado ativo na tela
    if (!empty($nomeCliente) || !empty($mensagem)) {
        $criarChamadoAtivo = true;
    }
}

// 4. Se o evento exigir ação/atenção imediata, salva na tabela `chamados` com status 'pendente'
if ($criarChamadoAtivo) {
    try {
        $db = obterConexao();

        // Evita duplicidade: se já existe um chamado ativo (pendente ou aguardando) para este contato/sessão nesta empresa, ignora a inserção
        if (!empty($sessionId) || !empty($nomeCliente)) {
            $sqlCheck = "SELECT COUNT(*) FROM chamados WHERE status IN ('pendente', 'aguardando') AND empresa_id = :empresa_id AND (";
            $paramsCheck = [':empresa_id' => $empresaId];
            $conds = [];
            if (!empty($sessionId)) {
                $conds[] = "session_id = :session_id";
                $paramsCheck[':session_id'] = $sessionId;
            }
            if (!empty($nomeCliente)) {
                $conds[] = "nome_cliente = :nome_cliente";
                $paramsCheck[':nome_cliente'] = $nomeCliente;
            }
            $sqlCheck .= implode(" OR ", $conds) . ")";
            
            $stmtCheck = $db->prepare($sqlCheck);
            $stmtCheck->execute($paramsCheck);
            $exists = (int)$stmtCheck->fetchColumn();
            if ($exists > 0) {
                // Se for alteração de etapa do card no CRM (Kanban), resolvemos o chamado anterior
                // para que o novo estágio seja inserido e gere um novo alerta visual/sonoro atualizado.
                if ($eventType === 'PANEL_CARD_STEP_CHANGE' || $eventType === 'PANEL_CARD_UPDATE') {
                    $sqlResolve = "UPDATE chamados SET status = 'resolvido' WHERE status IN ('pendente', 'aguardando') AND empresa_id = :empresa_id AND (";
                    $sqlResolve .= implode(" OR ", $conds) . ")";
                    $stmtResolve = $db->prepare($sqlResolve);
                    $stmtResolve->execute($paramsCheck);
                } else {
                    // Já existe um chamado ativo para este cliente. Unifica ignorando a criação do segundo card duplicado.
                    enviarRespostaELog(200, true, "Chamado ativo já existente para este cliente. Evento unificado com sucesso.", null, $dadosBrutos, $dados, (int)$empresaId);
                }
            }
        }

        $sql = "INSERT INTO chamados (empresa_id, nome_cliente, tipo, mensagem, session_id, status, criado_em) 
                VALUES (:empresa_id, :nome_cliente, :tipo, :mensagem, :session_id, 'pendente', NOW())";

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
        $stmt->bindValue(':nome_cliente', $nomeCliente, PDO::PARAM_STR);
        $stmt->bindValue(':tipo', $tipoEvent, PDO::PARAM_STR);
        $stmt->bindValue(':mensagem', $mensagem, PDO::PARAM_STR);
        $stmt->bindValue(':session_id', $sessionId, PDO::PARAM_STR);

        if ($stmt->execute()) {
            $lastId = $db->lastInsertId();

            $dadosSucesso = [
                'id' => $lastId,
                'nome_cliente' => $nomeCliente,
                'tipo' => $tipoEvent,
                'mensagem' => $mensagem,
                'session_id' => $sessionId,
                'status' => 'pendente'
            ];

            // Envia notificações push em segundo plano para os atendentes inscritos
            enviarPushNotificacoes($nomeCliente, $tipoEvent, $mensagem, $sessionId, (int)$empresaId);

            enviarRespostaELog(201, true, "Chamado ativo registrado e enviado ao painel.", $dadosSucesso, $dadosBrutos, $dados, (int)$empresaId);
        } else {
            throw new Exception("Falha ao executar a inserção do chamado ativo.");
        }
    } catch (Exception $e) {
        registrarErro("Erro de inserção de chamado ativo: " . $e->getMessage(), [
            'payload' => $dados,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'desconhecido'
        ]);
        enviarRespostaELog(500, false, "Erro interno do servidor ao salvar chamado ativo.", null, $dadosBrutos, $dados, (int)$empresaId);
    }
} else {
    // Se for apenas informativo, encerra com HTTP 200 registrando o log
    enviarRespostaELog(200, true, "Evento processado e registrado no histórico de logs (sem chamado ativo).", null, $dadosBrutos, $dados, (int)$empresaId);
}

/**
 * Envia notificações push para todos os navegadores/celulares inscritos.
 */
function enviarPushNotificacoes(?string $nomeCliente, string $tipo, ?string $mensagem, ?string $sessionId, int $empresaId): void
{
    try {
        $db = obterConexao();
        $stmt = $db->prepare("SELECT p.id, p.endpoint, p.keys_p256dh, p.keys_auth 
                               FROM pwa_subscriptions p
                               JOIN usuarios u ON p.usuario_id = u.id
                               WHERE u.empresa_id = :empresa_id");
        $stmt->execute([':empresa_id' => $empresaId]);
        $inscricoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($inscricoes)) {
            return; // Nenhuma inscrição registrada no banco
        }
        
        // Define o título e mensagem amigáveis para a notificação
        $titulo = "🚨 Atendimento Humano Requerido";
        $mensagemPush = "Cliente: " . ($nomeCliente ?? 'Desconhecido');
        
        // Formata a mensagem com base no tipo
        if ($tipo === 'SESSION_NEW') {
            $titulo = "ℹ️ Novo Atendimento Iniciado";
            $mensagemPush = "Cliente: " . ($nomeCliente ?? 'Desconhecido');
        } elseif (preg_match('/(lead|ia)/i', $mensagem)) {
            $titulo = "💵 Novo Lead Qualificado";
            $mensagemPush = "Lead: " . ($nomeCliente ?? 'Desconhecido');
        }
        
        if (!empty($mensagem)) {
            // Limita a exibição do payload
            $resumoMsg = mb_strimwidth($mensagem, 0, 100, "...");
            $mensagemPush .= "\n" . $resumoMsg;
        }

        // URL de destino para abrir no chat (raiz do PWA)
        $urlRedirect = "./";
        if (!empty($sessionId)) {
            if (strpos($sessionId, 'contact:') === 0) {
                $contactId = str_replace('contact:', '', $sessionId);
                $urlRedirect = "https://madeinai.wts.chat/contacts/" . $contactId;
            } else {
                $urlRedirect = "https://madeinai.wts.chat/chat2/sessions/" . $sessionId;
            }
        }

        // Configuração de autenticação VAPID
        $auth = [
            'VAPID' => [
                'subject' => VAPID_SUBJECT,
                'publicKey' => VAPID_PUBLIC_KEY,
                'privateKey' => VAPID_PRIVATE_KEY,
            ],
        ];

        // Instancia a classe de disparo da biblioteca minishlink/web-push
        $webPush = new \Minishlink\WebPush\WebPush($auth);
        
        // Adiciona à fila de disparo para cada assinatura ativa
        foreach ($inscricoes as $ins) {
            $webPush->queueNotification(
                \Minishlink\WebPush\Subscription::create([
                    'endpoint' => $ins['endpoint'],
                    'publicKey' => $ins['keys_p256dh'],
                    'authToken' => $ins['keys_auth'],
                ]),
                json_encode([
                    'titulo' => $titulo,
                    'mensagem' => $mensagemPush,
                    'url' => $urlRedirect
                ], JSON_UNESCAPED_UNICODE)
            );
        }

        // Executa os envios em paralelo e limpa endpoints inválidos (desinstalados)
        $idsParaRemover = [];
        foreach ($webPush->flush() as $report) {
            if (!$report->isSuccess()) {
                $response = $report->getResponse();
                $statusCode = $response ? $response->getStatusCode() : null;
                
                // Código 410 (Gone) ou 404 (Not Found) indica que o app foi desinstalado ou permissão revogada
                if ($statusCode === 410 || $statusCode === 404) {
                    $endpointUrl = $report->getRequest()->getUri()->__toString();
                    foreach ($inscricoes as $ins) {
                        if ($ins['endpoint'] === $endpointUrl) {
                            $idsParaRemover[] = $ins['id'];
                            break;
                        }
                    }
                }
            }
        }

        // Limpa inscrições inválidas do banco para evitar desperdício de requisições
        if (!empty($idsParaRemover)) {
            $placeholders = implode(',', array_fill(0, count($idsParaRemover), '?'));
            $sqlDelete = "DELETE FROM pwa_subscriptions WHERE id IN ($placeholders)";
            $stmtDel = $db->prepare($sqlDelete);
            $stmtDel->execute($idsParaRemover);
            registrarErro("Inscrições de Web Push inativas removidas em lote: " . implode(', ', $idsParaRemover));
        }

    } catch (Exception $e) {
        registrarErro("Falha catastrófica ao processar Web Push: " . $e->getMessage());
    }
}
