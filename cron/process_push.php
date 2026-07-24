<?php
/**
 * Worker de Fila de Web Push - Made in AI
 * Processa as notificações em segundo plano de forma assíncrona.
 */

// Define limites de tempo e recursos
set_time_limit(0);
ini_set('memory_limit', '256M');

require_once __DIR__ . '/../db.php';

// Apenas permite execução por CLI ou via web com token válido
if (php_sapi_name() !== 'cli') {
    $token = $_GET['token'] ?? '';
    if (empty($token) || $token !== JWT_SECRET) {
        http_response_code(403);
        echo "Acesso negado. Token inválido.";
        exit;
    }
}

// Impede concorrência de execução através de um lockfile exclusivo
$lockFile = __DIR__ . '/process_push.lock';
$lock = fopen($lockFile, 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    echo "O worker process_push já está em execução.\n";
    exit;
}

$isCLI = (php_sapi_name() === 'cli');
$loop = $isCLI; // Executa continuamente em loop no CLI, apenas uma vez na Web
$maxExecutionTime = 55; // Limite de 55 segundos para execução web
$startTime = time();

echo "Iniciando worker de Web Push...\n";

do {
    try {
        $db = obterConexao();
        
        // Busca notificações pendentes ou com falha com limite de tentativas < 3
        $sql = "SELECT id, empresa_id, payload, status, tentativas 
                FROM push_queue 
                WHERE status = 'pendente' OR (status = 'falhou' AND tentativas < 3) 
                ORDER BY id ASC 
                LIMIT 15";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($itens)) {
            if (!$loop) {
                break;
            }
            sleep(1);
            continue;
        }
        
        foreach ($itens as $item) {
            $itemId = (int)$item['id'];
            $empresaId = (int)$item['empresa_id'];
            $payload = $item['payload'];
            
            // Marca o item como 'processando' para evitar envio duplicado por concorrência
            $db->prepare("UPDATE push_queue SET status = 'processando' WHERE id = :id")->execute([':id' => $itemId]);
            
            // Busca as assinaturas ativas para esta empresa
            $stmtSub = $db->prepare("SELECT p.id, p.endpoint, p.keys_p256dh, p.keys_auth 
                                     FROM pwa_subscriptions p
                                     JOIN usuarios u ON p.usuario_id = u.id
                                     WHERE u.empresa_id = :empresa_id");
            $stmtSub->execute([':empresa_id' => $empresaId]);
            $inscricoes = $stmtSub->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($inscricoes)) {
                // Se não há assinaturas ativas para a empresa, conclui o item da fila
                $db->prepare("UPDATE push_queue SET status = 'concluido' WHERE id = :id")->execute([':id' => $itemId]);
                continue;
            }
            
            // Configurações de autenticação VAPID
            $auth = [
                'VAPID' => [
                    'subject' => VAPID_SUBJECT,
                    'publicKey' => VAPID_PUBLIC_KEY,
                    'privateKey' => VAPID_PRIVATE_KEY,
                ],
            ];
            
            // Instancia a classe de disparo
            $webPush = new \Minishlink\WebPush\WebPush($auth);
            
            foreach ($inscricoes as $ins) {
                $webPush->queueNotification(
                    \Minishlink\WebPush\Subscription::create([
                        'endpoint' => $ins['endpoint'],
                        'publicKey' => $ins['keys_p256dh'],
                        'authToken' => $ins['keys_auth'],
                    ]),
                    $payload
                );
            }
            
            $idsParaRemover = [];
            $houveFalha = false;
            
            foreach ($webPush->flush() as $report) {
                if (!$report->isSuccess()) {
                    $response = $report->getResponse();
                    $statusCode = $response ? $response->getStatusCode() : null;
                    
                    // Remove endpoints inativos (Gone 410 ou Not Found 404)
                    if ($statusCode === 410 || $statusCode === 404) {
                        $endpointUrl = $report->getRequest()->getUri()->__toString();
                        foreach ($inscricoes as $ins) {
                            if ($ins['endpoint'] === $endpointUrl) {
                                $idsParaRemover[] = $ins['id'];
                                break;
                            }
                        }
                    } else {
                        $houveFalha = true;
                    }
                }
            }
            
            // Exclui inscrições inválidas do banco
            if (!empty($idsParaRemover)) {
                $placeholders = implode(',', array_fill(0, count($idsParaRemover), '?'));
                $stmtDel = $db->prepare("DELETE FROM pwa_subscriptions WHERE id IN ($placeholders)");
                $stmtDel->execute($idsParaRemover);
                registrarErro("Process Push Worker: Inscrições inválidas removidas: " . implode(', ', $idsParaRemover));
            }
            
            // Atualiza status final do item na fila
            if ($houveFalha) {
                $db->prepare("UPDATE push_queue SET status = 'falhou', tentativas = tentativas + 1 WHERE id = :id")->execute([':id' => $itemId]);
            } else {
                $db->prepare("UPDATE push_queue SET status = 'concluido' WHERE id = :id")->execute([':id' => $itemId]);
            }
        }
        
    } catch (Exception $e) {
        registrarErro("Process Push Worker: Falha catastrófica: " . $e->getMessage());
        if (!$loop) {
            break;
        }
        sleep(5); // Aguarda um tempo caso dê erro de conexão para não travar em loop rápido
    }
    
    // Controla limite de tempo na execução web
    if (!$isCLI && (time() - $startTime) > $maxExecutionTime) {
        echo "Limite de tempo de execução web atingido.\n";
        break;
    }
    
} while ($loop);

echo "Worker finalizado.\n";
