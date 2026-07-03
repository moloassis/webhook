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

// IMPRESCINDÍVEL PARA VPS HOSTINGER (se rodar Nginx como proxy reverso)
// Desativa o buffering de proxy do Nginx. Sem isso, o Nginx segura os dados 
// no buffer e não os envia em tempo real para o frontend.
header('X-Accel-Buffering: no');

// 2. Desabilitar limite de tempo de execução do PHP para manter o script vivo
set_time_limit(0);

// Desativa completamente o output buffering do PHP se estiver ativo
if (ob_get_level()) {
    ob_end_clean();
}

// Carrega o banco de dados
require_once __DIR__ . '/db.php';
$db = obterConexao();

// Captura o último ID de chamado recebido pelo frontend (evita duplicidade e perda de eventos)
$lastId = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
if ($lastId <= 0) {
    // Se for conexão inicial limpa, monitoramos apenas eventos criados a partir de agora
    $stmtMax = $db->query("SELECT MAX(id) AS max_id FROM chamados");
    $rowMax = $stmtMax->fetch();
    $lastId = $rowMax['max_id'] ? (int)$rowMax['max_id'] : 0;
}

/**
 * IMPORTANTE: Se o seu sistema usar sessões PHP (session_start), você DEVE
 * liberar o arquivo de sessão imediatamente usando 'session_write_close()'.
 * Caso contrário, o PHP bloqueará qualquer outra requisição concorrente do mesmo
 * usuário enquanto este script SSE estiver em loop (travando o carregamento do site).
 */
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

// Loop infinito de transmissão em tempo real
while (true) {
    // 3. Gerenciamento de Conexão: Verificar se o cliente fechou a aba/dashboard.
    // O PHP só percebe que o cliente desconectou quando tenta enviar dados.
    // Por isso, enviamos um "comentário" SSE (iniciado com dois pontos) como batimento cardíaco (heartbeat).
    echo ": heartbeat\n\n";
    
    // Tenta forçar o envio imediato para o navegador
    if (ob_get_length()) {
        ob_flush();
    }
    flush();

    // Se o cliente desconectou, paramos o loop para poupar processador e conexão da VPS
    if (connection_aborted()) {
        break;
    }

    try {
        // 4. Buscar novos chamados pendentes (criados após o último transmitido)
        $sql = "SELECT id, nome_cliente, tipo, mensagem, session_id, criado_em 
                FROM chamados 
                WHERE status = 'pendente' AND id > :last_id 
                ORDER BY id ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute([':last_id' => $lastId]);
        $novosChamados = $stmt->fetchAll();

        if (!empty($novosChamados)) {
            foreach ($novosChamados as $chamado) {
                // Formato padrão SSE: "data: <json>\n\n"
                echo "data: " . json_encode($chamado, JSON_UNESCAPED_UNICODE) . "\n\n";

                // Atualiza o cursor local para o ID que acabou de ser enviado
                $lastId = (int)$chamado['id'];
            }

            // Força a saída de dados acumulada para o frontend imediatamente
            if (ob_get_length()) {
                ob_flush();
            }
            flush();
        }

    } catch (PDOException $e) {
        // Grava o erro no arquivo de log de forma segura
        registrarErro("Erro de banco de dados no loop SSE: " . $e->getMessage());

        // Envia log de erro no formato SSE em caso de falha no banco
        echo "data: " . json_encode(['erro' => true, 'mensagem' => 'A conexão com o banco de dados falhou temporariamente.']) . "\n\n";
        if (ob_get_length()) {
            ob_flush();
        }
        flush();
    }

    // 5. Evitar travamento do Servidor (Uso de CPU):
    // Dormimos por 2 segundos. Sem esta pausa (sleep), o loop while rodará milhões de vezes
    // por segundo consumindo 100% da CPU da VPS Hostinger, o que travará o servidor e resultará
    // em erro 504/502 (Bad Gateway). 2 segundos é um tempo excelente para parecer instantâneo.
    sleep(2);
}
