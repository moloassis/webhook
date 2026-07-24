<?php
/**
 * Endpoint SSE (Server-Sent Events) - Transmissão em tempo real.
 * Mantém uma conexão persistente aberta com o painel do atendente,
 * buscando novos chamados pendentes e enviando-os como eventos.
 */

// 1. Configurar cabeçalhos necessários para Server-Sent Events (SSE)
header('Content-Type: text/event-stream');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Desativa o buffering de proxy do Nginx
header('Content-Encoding: none'); // Evita compressão de saída adicional pelo servidor web

// 2. Desabilitar limites e otimizar output buffering para streaming em tempo real
set_time_limit(0);

// Desativa a compressão zlib do PHP (essencial para que o streaming funcione)
ini_set('zlib.output_compression', 'Off');
@ini_set('output_buffering', 'Off');
@ini_set('implicit_flush', 'On');
ob_implicit_flush(true);

// Limpa completamente todas as camadas de buffers de saída ativos do PHP
while (ob_get_level()) {
    ob_end_clean();
}

// Carrega dependências de BD e JWT
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/jwt.php';

// --- AUTENTICAÇÃO VIA JWT ---
// SSE no browser não permite cabeçalhos customizados nativamente, então passamos o JWT via Query String.
$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$decoded = null;

if ($token) {
    $decoded = JWT::decode($token, JWT_SECRET);
}

if (!$decoded) {
    echo "event: auth_error\n";
    echo "data: " . json_encode(['erro' => true, 'mensagem' => 'Sessão inválida ou expirada. Faça login novamente.'], JSON_UNESCAPED_UNICODE) . "\n\n";
    if (ob_get_length()) {
        ob_flush();
    }
    flush();
    exit;
}

$empresaId = (int)$decoded['empresa_id'];

$db = obterConexao();

// Captura o último ID de chamado recebido pelo frontend (evita duplicidade e perda de eventos)
$lastId = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
if ($lastId <= 0) {
    // Monitoramos apenas eventos criados a partir de agora para este tenant
    $stmtMax = $db->prepare("SELECT MAX(id) AS max_id FROM chamados WHERE empresa_id = :empresa_id");
    $stmtMax->execute([':empresa_id' => $empresaId]);
    $rowMax = $stmtMax->fetch();
    $lastId = $rowMax['max_id'] ? (int)$rowMax['max_id'] : 0;
}

// Libera a sessão PHP se existir para evitar bloqueio de requisições concorrentes (Session Locking)
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

// Inicializa a lista de IDs pendentes/aguardando enviados localmente para controlar encerramentos em tempo real
$sentPendingIds = [];
// Setup flag file path
$flagDir = __DIR__ . '/flags';
$flagFile = $flagDir . "/update_{$empresaId}.txt";
$firstRun = true;
$lastFlagMtime = 0;

// Loop infinito de transmissão em tempo real
while (true) {
    // Heartbeat para verificar se o cliente ainda está conectado
    echo ": heartbeat\n\n";
    
    if (ob_get_length()) {
        ob_flush();
    }
    flush();

    // Se o cliente desconectou, encerra o loop
    if (connection_aborted()) {
        break;
    }

    // Verifica se houve alguma alteração (novos chamados ou resoluções) via arquivo flag
    $needQuery = false;
    if ($firstRun) {
        $needQuery = true;
        $firstRun = false;
        if (file_exists($flagFile)) {
            $lastFlagMtime = @filemtime($flagFile);
        } else {
            if (!is_dir($flagDir)) {
                @mkdir($flagDir, 0755, true);
            }
            @touch($flagFile);
            $lastFlagMtime = file_exists($flagFile) ? @filemtime($flagFile) : time();
        }
    } else {
        if (file_exists($flagFile)) {
            $currentMtime = @filemtime($flagFile);
            if ($currentMtime > $lastFlagMtime) {
                $needQuery = true;
                $lastFlagMtime = $currentMtime;
            }
        } else {
            if (!is_dir($flagDir)) {
                @mkdir($flagDir, 0755, true);
            }
            @touch($flagFile);
            $lastFlagMtime = file_exists($flagFile) ? @filemtime($flagFile) : time();
            $needQuery = true;
        }
    }

    if ($needQuery) {
        try {
            // 4. Buscar chamados ativos (pendentes ou aguardando) deste tenant (empresa_id)
            $sql = "SELECT id, nome_cliente, tipo, mensagem, session_id, status, criado_em 
                    FROM chamados 
                    WHERE status IN ('pendente', 'aguardando') AND empresa_id = :empresa_id 
                    ORDER BY id ASC";
            $stmt = $db->prepare($sql);
            $stmt->execute([':empresa_id' => $empresaId]);
            $currentPending = $stmt->fetchAll();
            
            $currentPendingIds = array_map(function($c) { return (int)$c['id']; }, $currentPending);

            // Detecta chamados resolvidos (estavam na lista anterior e não estão mais no banco como pendentes)
            $resolvedIds = array_diff($sentPendingIds, $currentPendingIds);
            foreach ($resolvedIds as $resId) {
                echo "data: " . json_encode(['action' => 'resolve', 'id' => $resId], JSON_UNESCAPED_UNICODE) . "\n\n";
                // Remove da lista local
                $sentPendingIds = array_filter($sentPendingIds, function($id) use ($resId) { return $id !== $resId; });
            }

            // Envia novos chamados pendentes
            foreach ($currentPending as $chamado) {
                $cId = (int)$chamado['id'];
                if (!in_array($cId, $sentPendingIds)) {
                    echo "data: " . json_encode($chamado, JSON_UNESCAPED_UNICODE) . "\n\n";
                    $sentPendingIds[] = $cId;
                }
            }

            if (ob_get_length()) {
                ob_flush();
            }
            flush();

        } catch (PDOException $e) {
            registrarErro("Erro de banco de dados no loop SSE para empresa #{$empresaId}: " . $e->getMessage());

            echo "data: " . json_encode(['erro' => true, 'mensagem' => 'A conexão com o banco de dados falhou temporariamente. Tentando reconectar...'], JSON_UNESCAPED_UNICODE) . "\n\n";
            if (ob_get_length()) {
                ob_flush();
            }
            flush();
            
            // Tenta reconectar chamando obterConexao com parâmetro true
            sleep(5);
            try {
                $db = obterConexao(true);
                // Força nova consulta no próximo loop
                $firstRun = true;
            } catch (Exception $ex) {
                registrarErro("Falha ao tentar reconectar banco no SSE: " . $ex->getMessage());
            }
        }
    }

    // Aguarda 2 segundos antes do próximo ciclo (evita consumo excessivo de CPU)
    sleep(2);
}
