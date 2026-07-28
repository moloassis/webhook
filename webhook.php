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
require_once __DIR__ . '/helpers/webhook_rules.php';

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

// 2. Capturar os dados enviados (suporta JSON ou $_POST convencional)
$dadosBrutos = file_get_contents('php://input');
$dados = json_decode($dadosBrutos, true);

// Se não for JSON válido, tenta pegar via POST tradicional (form-urlencoded)
if (json_last_error() !== JSON_ERROR_NONE || empty($dados)) {
    $dados = $_POST;
}

try {
    $db = obterConexao();
    $stmt = $db->prepare("SELECT id, webhook_token FROM tenants WHERE webhook_token = :token");
    $stmt->execute([':token' => $token]);
    $tenant = $stmt->fetch();

    if (!$tenant) {
        enviarRespostaELogSemEmpresa(403, false, 'Token do webhook inválido ou inativo.');
    }
    
    $empresaId = (int)$tenant['id'];

    // Validação de assinatura digital opcional (HMAC-SHA256)
    $signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? $_SERVER['HTTP_X_SIGNATURE'] ?? '';
    if (!empty($signature)) {
        if (strpos($signature, 'sha256=') === 0) {
            $signature = substr($signature, 7);
        }
        $expectedSignature = hash_hmac('sha256', $dadosBrutos, $tenant['webhook_token']);
        if (!hash_equals($expectedSignature, $signature)) {
            enviarRespostaELogSemEmpresa(403, false, 'Assinatura digital do webhook inválida.');
        }
    }
} catch (Exception $e) {
    registrarErro("Erro ao validar token/assinatura de webhook: " . $e->getMessage());
    enviarRespostaELogSemEmpresa(500, false, 'Erro interno ao processar webhook.');
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
$categoria = 'ignorar';
$modoExibicao = 'normal';
$textoBusca = ''; // Texto usado pelo motor de regras (helpers/webhook_rules.php) para casar palavras-chave

if ($eventType) {
    // Processamento estruturado dos payloads reais do Made in AI.
    // Aqui só extraímos os campos do payload ($nomeCliente/$mensagem/$sessionId/$textoBusca);
    // a decisão de criar chamado + categoria + modo de exibição é feita pelo motor de regras
    // (avaliarRegrasWebhook), configurável por tenant, logo após o switch.
    switch ($eventType) {
        case 'MESSAGE_SENT':
            $tipoEvent = 'MESSAGE_SENT';
            $to = $dados['content']['details']['to'] ?? '';
            $origin = $dados['content']['origin'] ?? 'AI';
            $text = $dados['content']['text'] ?? '';
            $mensagem = "IA ({$origin}) enviou mensagem para {$to}: \"{$text}\"";

            // Se uma resposta foi enviada pelo atendente humano (origem DEFAULT), encerra os chamados de suporte para este contato
            if ($origin === 'DEFAULT' && !empty($sessionId)) {
                try {
                    $db = obterConexao();
                    $stmtUpdate = $db->prepare("UPDATE chamados SET status = 'resolvido' WHERE session_id = :session_id AND status IN ('pendente', 'aguardando') AND empresa_id = :empresa_id");
                    $stmtUpdate->execute([
                        ':session_id' => $sessionId,
                        ':empresa_id' => (int)$empresaId
                    ]);

                    // Notifica SSE atualizando o arquivo flag
                    $flagDir = __DIR__ . '/flags';
                    if (!is_dir($flagDir)) {
                        mkdir($flagDir, 0755, true);
                    }
                    touch($flagDir . "/update_{$empresaId}.txt");
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
            break;

        case 'SESSION_NEW':
            $tipoEvent = 'SESSION_NEW';
            $nomeCliente = $dados['content']['contactDetails']['name'] ?? 'Desconhecido';
            $phone = $dados['content']['contactDetails']['phonenumberFormatted'] ?? '';
            $mensagem = "Nova conversa iniciada pelo WhatsApp ({$phone}).";
            break;

        case 'CONTACT_TAG_UPDATE':
        case 'CONTACT_UPDATE':
            // CONTACT_UPDATE é a variante que o CRM também envia para atualizações gerais de contato/tags
            $tipoEvent = $eventType;
            $nomeCliente = $dados['content']['name'] ?? 'Desconhecido';
            $tags = $dados['content']['tags'] ?? [];

            // Converte todas as tags para minúsculo para busca segura e sem erros de caixa alta
            $tagsMinusculas = array_map(function ($t) {
                return mb_strtolower(trim($t)); }, $tags);

            $textoBusca = implode(',', $tagsMinusculas);
            $mensagem = "Tags atualizadas do contato: " . implode(', ', $tags);
            break;

        case 'SESSION_COMPLETE':
        case 'SESSION_UPDATE':
            // SESSION_UPDATE é a variante enviada durante a conversa (não apenas ao finalizar)
            $tipoEvent = $eventType;
            $nomeCliente = $dados['content']['contactDetails']['name'] ?? 'Desconhecido';
            $lastText = $dados['content']['lastMessageText'] ?? '';

            $textoBusca = $lastText;
            $mensagem = ($eventType === 'SESSION_COMPLETE')
                ? "Sessão do chatbot finalizada. Última msg: \"{$lastText}\""
                : "Sessão atualizada durante o atendimento. Última msg: \"{$lastText}\"";
            break;

        case 'PANEL_CARD_STEP_CHANGE':
        case 'PANEL_CARD_UPDATE':
        case 'PANEL_CARD_NEW':
            // PANEL_CARD_NEW é enviado quando o card já nasce diretamente numa etapa (sem STEP_CHANGE prévio)
            $tipoEvent = $eventType;
            $nomeCliente = $dados['content']['contacts'][0]['name'] ?? ($dados['content']['title'] ?? 'Lead');
            $stepTitle = $dados['content']['stepTitle'] ?? '';

            $textoBusca = $stepTitle;
            $mensagem = ($eventType === 'PANEL_CARD_NEW')
                ? "Novo card criado no CRM na etapa: \"{$stepTitle}\""
                : "Card movido no CRM para: \"{$stepTitle}\"";
            break;

        default:
            $tipoEvent = $eventType;
            $mensagem = "Evento Made in AI não mapeado: \"{$eventType}\"";
            break;
    }

    require_once __DIR__ . '/helpers/webhook_rules.php';
    $resultadoRegra = avaliarRegrasWebhook($eventType, $textoBusca, $empresaId);
    $criarChamadoAtivo = $resultadoRegra['criar_chamado'];
    $categoria = $resultadoRegra['categoria'];
    $modoExibicao = $resultadoRegra['modo_exibicao'];

    // Ajusta a redação da mensagem/log conforme a categoria resultante (apenas texto, não reclassifica)
    if (in_array($eventType, ['CONTACT_TAG_UPDATE', 'CONTACT_UPDATE'], true) && $categoria === 'atendimento_humano') {
        $mensagem = "Cliente etiquetado para Atendimento Humano no CRM.";
    } elseif ($eventType === 'SESSION_COMPLETE' && $categoria === 'atendimento_humano') {
        $mensagem = "Chatbot finalizado para transferência humana. Última msg: \"{$lastText}\"";
    } elseif ($eventType === 'SESSION_UPDATE' && $categoria === 'atendimento_humano') {
        $mensagem = "Cliente solicitou atendimento humano durante a conversa. Última msg: \"{$lastText}\"";
    } elseif (in_array($eventType, ['PANEL_CARD_STEP_CHANGE', 'PANEL_CARD_UPDATE', 'PANEL_CARD_NEW'], true)) {
        if ($categoria === 'atendimento_humano') {
            $mensagem = "Lead transferido para suporte humano na coluna: \"{$stepTitle}\"";
        } elseif ($categoria === 'novo_lead') {
            $mensagem = "Card movido para etapa de qualificação: \"{$stepTitle}\"";
        }
    }
} else {
    // FALLBACK: Mantém retrocompatibilidade com o simulador de webhook da interface ou disparos manuais.
    // Não passa pelo motor de regras (não há eventType real do Made in AI) — a categoria é o próprio
    // valor enviado pelo simulador/POST manual, preservando o comportamento histórico desse caminho.
    $nomeCliente = isset($dados['nome_cliente']) ? trim(filter_var($dados['nome_cliente'], FILTER_SANITIZE_SPECIAL_CHARS)) : null;
    $tipoEvent = isset($dados['tipo']) ? trim(filter_var($dados['tipo'], FILTER_SANITIZE_SPECIAL_CHARS)) : 'atendimento_humano';
    $mensagem = isset($dados['mensagem']) ? trim(filter_var($dados['mensagem'], FILTER_SANITIZE_SPECIAL_CHARS)) : null;
    $sessionId = isset($dados['session_id']) ? trim(filter_var($dados['session_id'], FILTER_SANITIZE_SPECIAL_CHARS)) : null;

    // Se tiver dados mínimos, cria o chamado ativo na tela
    if (!empty($nomeCliente) || !empty($mensagem)) {
        $criarChamadoAtivo = true;
        $categoria = $tipoEvent;
        $modoExibicao = ($tipoEvent === 'atendimento_humano') ? 'urgente_fullscreen' : 'normal';
    }
}

// 4. Se o evento exigir ação/atenção imediata, salva na tabela `chamados` com status 'pendente'
if ($criarChamadoAtivo) {
    try {
        $db = obterConexao();

        // Evita duplicidade: se já existe um chamado ativo (pendente ou aguardando) para este contato/sessão nesta empresa, ignora a inserção
        if (!empty($sessionId) || !empty($nomeCliente)) {
            $sqlCheck = "SELECT id, status, mensagem, criado_em FROM chamados WHERE status IN ('pendente', 'aguardando') AND empresa_id = :empresa_id AND (";
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
            $sqlCheck .= implode(" OR ", $conds) . ") ORDER BY id DESC LIMIT 1";
            
            $stmtCheck = $db->prepare($sqlCheck);
            $stmtCheck->execute($paramsCheck);
            $existingChamado = $stmtCheck->fetch();
            
            if ($existingChamado) {
                // Se a mensagem do novo evento for a mesma do chamado ativo existente,
                // significa que é apenas um webhook duplicado para a mesma ação (ex: STEP_CHANGE + UPDATE).
                // Nesse caso, apenas unificamos sem gerar novos chamados ou excluir o atual (sempre, independente de configuração).
                if ($mensagem === $existingChamado['mensagem']) {
                    enviarRespostaELog(200, true, "Chamado ativo já existente com a mesma mensagem. Evento unificado sem alterações.", null, $dadosBrutos, $dados, (int)$empresaId);
                }

                // Eventos de mudança de card no CRM (Kanban) sempre resolvem o chamado anterior e criam um novo alerta
                // atualizado, já que representam uma mudança real de etapa/estado do card, não uma duplicata.
                $ehMudancaDeCard = in_array($eventType, ['PANEL_CARD_STEP_CHANGE', 'PANEL_CARD_UPDATE', 'PANEL_CARD_NEW'], true);

                // Configuração do tenant: "unificar" (padrão) ignora o novo evento se já existir chamado ativo
                // para o mesmo contato; "sempre_notificar" resolve o antigo e sempre cria um alerta novo e atualizado.
                $modoDedup = obterConfiguracao('webhook_dedup_modo', 'unificar', $empresaId);

                if ($ehMudancaDeCard || $modoDedup === 'sempre_notificar') {
                    $sqlResolve = "UPDATE chamados SET status = 'resolvido' WHERE status IN ('pendente', 'aguardando') AND empresa_id = :empresa_id AND (";
                    $sqlResolve .= implode(" OR ", $conds) . ")";
                    $stmtResolve = $db->prepare($sqlResolve);
                    $stmtResolve->execute($paramsCheck);

                    // Notifica SSE atualizando o arquivo flag
                    $flagDir = __DIR__ . '/flags';
                    if (!is_dir($flagDir)) {
                        mkdir($flagDir, 0755, true);
                    }
                    touch($flagDir . "/update_{$empresaId}.txt");
                } else {
                    // Já existe um chamado ativo para este cliente. Unifica ignorando a criação do segundo card duplicado.
                    enviarRespostaELog(200, true, "Chamado ativo já existente para este cliente. Evento unificado com sucesso.", null, $dadosBrutos, $dados, (int)$empresaId);
                }
            }
        }

        $sql = "INSERT INTO chamados (empresa_id, nome_cliente, tipo, categoria, modo_exibicao, mensagem, session_id, status, criado_em)
                VALUES (:empresa_id, :nome_cliente, :tipo, :categoria, :modo_exibicao, :mensagem, :session_id, 'pendente', NOW())";

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
        $stmt->bindValue(':nome_cliente', $nomeCliente, PDO::PARAM_STR);
        $stmt->bindValue(':tipo', $tipoEvent, PDO::PARAM_STR);
        $stmt->bindValue(':categoria', $categoria, PDO::PARAM_STR);
        $stmt->bindValue(':modo_exibicao', $modoExibicao, PDO::PARAM_STR);
        $stmt->bindValue(':mensagem', $mensagem, PDO::PARAM_STR);
        $stmt->bindValue(':session_id', $sessionId, PDO::PARAM_STR);

        if ($stmt->execute()) {
            $lastId = $db->lastInsertId();

            $dadosSucesso = [
                'id' => $lastId,
                'nome_cliente' => $nomeCliente,
                'tipo' => $tipoEvent,
                'categoria' => $categoria,
                'modo_exibicao' => $modoExibicao,
                'mensagem' => $mensagem,
                'session_id' => $sessionId,
                'status' => 'pendente'
            ];

            // Notifica SSE atualizando o arquivo flag
            $flagDir = __DIR__ . '/flags';
            if (!is_dir($flagDir)) {
                mkdir($flagDir, 0755, true);
            }
            touch($flagDir . "/update_{$empresaId}.txt");

            // Envia notificações push em segundo plano para os atendentes inscritos
            enviarPushNotificacoes($nomeCliente, $categoria, $mensagem, $sessionId, (int)$empresaId);

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
 * Enfileira notificações push na tabela push_queue para processamento assíncrono.
 */
function enviarPushNotificacoes(?string $nomeCliente, string $categoria, ?string $mensagem, ?string $sessionId, int $empresaId): void
{
    try {
        // Define o título e mensagem amigáveis para a notificação, conforme a categoria já classificada
        switch ($categoria) {
            case 'novo_atendimento':
                $titulo = "ℹ️ Novo Atendimento Iniciado";
                $mensagemPush = "Cliente: " . ($nomeCliente ?? 'Desconhecido');
                break;
            case 'novo_lead':
                $titulo = "💵 Novo Lead Qualificado";
                $mensagemPush = "Lead: " . ($nomeCliente ?? 'Desconhecido');
                break;
            case 'alerta_sistema':
                $titulo = "⚠️ Alerta do Sistema";
                $mensagemPush = "Cliente: " . ($nomeCliente ?? 'Desconhecido');
                break;
            case 'atendimento_humano':
            default:
                $titulo = "🚨 Atendimento Humano Requerido";
                $mensagemPush = "Cliente: " . ($nomeCliente ?? 'Desconhecido');
                break;
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

        $db = obterConexao();
        $payload = json_encode([
            'titulo' => $titulo,
            'mensagem' => $mensagemPush,
            'url' => $urlRedirect
        ], JSON_UNESCAPED_UNICODE);

        $stmt = $db->prepare("INSERT INTO push_queue (empresa_id, payload, status, tentativas, criado_em) VALUES (:empresa_id, :payload, 'pendente', 0, NOW())");
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':payload' => $payload
        ]);
    } catch (Exception $e) {
        registrarErro("Falha ao enfileirar Web Push: " . $e->getMessage());
    }
}
