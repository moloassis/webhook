<?php
/**
 * Visualizador de Logs de Webhook - Inspetor de Integração
 * Exibe todos os webhooks recebidos pelo sistema (processados com sucesso ou rejeitados).
 */

require_once __DIR__ . '/db.php';
$db = obterConexao();

// Ação opcional: Limpar logs se solicitado pelo administrador
if (isset($_POST['action']) && $_POST['action'] === 'clear') {
    try {
        $db->exec("TRUNCATE TABLE webhook_logs");
        header("Location: logs.php");
        exit;
    } catch (Exception $e) {
        $erroLimpar = "Falha ao limpar logs: " . $e->getMessage();
    }
}

// Buscar os últimos 100 logs registrados
try {
    $sql = "SELECT id, metodo, ip, event_type, payload, status_resposta, mensagem_resposta, criado_em 
            FROM webhook_logs 
            ORDER BY id DESC 
            LIMIT 100";
    $stmt = $db->query($sql);
    $logs = $stmt->fetchAll();
} catch (Exception $e) {
    $logs = [];
    $erroListar = "Erro ao buscar logs do banco de dados: " . $e->getMessage();
}

// Calcular estatísticas básicas dos logs carregados
$total = count($logs);
$sucessos = 0;
$falhas = 0;
foreach ($logs as $log) {
    if ($log['status_resposta'] >= 200 && $log['status_resposta'] < 300) {
        $sucessos++;
    } else {
        $falhas++;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HelenaCRM - Histórico Geral de Webhooks</title>
    
    <!-- Fonte Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --bg-gradient: radial-gradient(circle at top, #140f2d 0%, #08080c 100%);
            --panel-bg: rgba(20, 20, 35, 0.6);
            --border-color: rgba(255, 255, 255, 0.07);
            --text-primary: #f1f2f6;
            --text-secondary: #a4b0be;
            
            --success-color: #2ed573;
            --warning-color: #ffa502;
            --error-color: #ff4757;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Outfit', sans-serif;
        }

        body {
            background: var(--bg-gradient);
            color: var(--text-primary);
            min-height: 100vh;
            padding: 2rem;
        }

        .container {
            max-width: 1200px;
            width: 100%;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        /* Topo */
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        h1 {
            font-size: 1.6rem;
            font-weight: 600;
            background: linear-gradient(135deg, #fff 0%, #a4b0be 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .header-actions {
            display: flex;
            gap: 0.8rem;
        }

        .btn {
            padding: 0.6rem 1.2rem;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.12);
        }

        .btn-danger {
            background: rgba(255, 71, 87, 0.15);
            border: 1px solid rgba(255, 71, 87, 0.3);
            color: var(--error-color);
        }

        .btn-danger:hover {
            background: var(--error-color);
            color: #000;
            border-color: var(--error-color);
        }

        /* Estatísticas */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
        }

        .stat-card {
            background: var(--panel-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.25rem;
            backdrop-filter: blur(16px);
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .stat-val {
            font-size: 1.8rem;
            font-weight: 700;
        }

        .stat-label {
            font-size: 0.85rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Tabela de logs */
        .logs-panel {
            background: var(--panel-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 1.5rem;
            backdrop-filter: blur(16px);
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        th {
            padding: 0.75rem 1rem;
            border-bottom: 2px solid rgba(255, 255, 255, 0.08);
            color: var(--text-secondary);
            font-size: 0.85rem;
            text-transform: uppercase;
            font-weight: 600;
        }

        td {
            padding: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            font-size: 0.9rem;
            vertical-align: middle;
        }

        tr.log-row:hover {
            background: rgba(255, 255, 255, 0.02);
        }

        /* Badges de HTTP Status */
        .badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .badge-success {
            background: rgba(46, 213, 115, 0.15);
            color: var(--success-color);
            border: 1px solid rgba(46, 213, 115, 0.3);
        }

        .badge-warning {
            background: rgba(255, 165, 2, 0.15);
            color: var(--warning-color);
            border: 1px solid rgba(255, 165, 2, 0.3);
        }

        .badge-error {
            background: rgba(255, 71, 87, 0.15);
            color: var(--error-color);
            border: 1px solid rgba(255, 71, 87, 0.3);
        }

        /* Payload visualizador colapsável */
        .payload-container {
            display: none;
            margin-top: 0.5rem;
            background: rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 1rem;
            font-family: monospace;
            font-size: 0.8rem;
            white-space: pre-wrap;
            word-break: break-all;
            color: #3ae374;
            max-height: 250px;
            overflow-y: auto;
        }

        .btn-inspect {
            background: none;
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--text-secondary);
            padding: 0.3rem 0.6rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.75rem;
            transition: all 0.2s;
        }

        .btn-inspect:hover {
            border-color: var(--text-primary);
            color: var(--text-primary);
        }

        .empty-state {
            padding: 4rem;
            text-align: center;
            color: var(--text-secondary);
        }

        .empty-icon {
            font-size: 2.5rem;
            margin-bottom: 0.8rem;
            opacity: 0.3;
        }
    </style>
</head>
<body>

    <div class="container">
        
        <!-- Header -->
        <header>
            <div>
                <h1>Inspetor de Webhooks Recebidos</h1>
                <p style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 4px;">Monitoramento e auditoria em tempo real das conexões externas</p>
            </div>
            <div class="header-actions">
                <a href="index.html" class="btn btn-secondary">🖥️ Voltar ao Painel</a>
                
                <form action="logs.php" method="POST" onsubmit="return confirm('Tem certeza que deseja apagar todos os logs de webhooks salvos?')">
                    <input type="hidden" name="action" value="clear">
                    <button type="submit" class="btn btn-danger">🗑️ Limpar Histórico</button>
                </form>
            </div>
        </header>

        <!-- Exibição de Erros se houver -->
        <?php if (isset($erroLimpar)): ?>
            <div style="background: rgba(255,71,87,0.15); border: 1px solid var(--error-color); padding: 1rem; border-radius: 8px; color: var(--error-color); font-size: 0.9rem;">
                <?= htmlspecialchars($erroLimpar) ?>
            </div>
        <?php endif; ?>
        <?php if (isset($erroListar)): ?>
            <div style="background: rgba(255,71,87,0.15); border: 1px solid var(--error-color); padding: 1rem; border-radius: 8px; color: var(--error-color); font-size: 0.9rem;">
                <?= htmlspecialchars($erroListar) ?>
            </div>
        <?php endif; ?>

        <!-- Cartões de Métricas rápidos -->
        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-val"><?= $total ?></span>
                <span class="stat-label">Total Recebidos (Limite 100)</span>
            </div>
            <div class="stat-card" style="border-left: 3px solid var(--success-color);">
                <span class="stat-val" style="color: var(--success-color);"><?= $sucessos ?></span>
                <span class="stat-label">Processados com Sucesso (2xx)</span>
            </div>
            <div class="stat-card" style="border-left: 3px solid var(--error-color);">
                <span class="stat-val" style="color: var(--error-color);"><?= $falhas ?></span>
                <span class="stat-label">Rejeitados/Com Erro</span>
            </div>
        </div>

        <!-- Painel principal de Tabela -->
        <div class="logs-panel">
            <?php if (empty($logs)): ?>
                <div class="empty-state">
                    <div class="empty-icon">📁</div>
                    <p>Nenhum log de webhook registrado ainda no banco de dados.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 180px;">Data/Hora</th>
                                <th style="width: 140px;">IP Remetente</th>
                                <th style="width: 180px;">Tipo de Evento</th>
                                <th style="width: 80px;">Método</th>
                                <th style="width: 100px;">Status HTTP</th>
                                <th>Resposta da API</th>
                                <th style="width: 110px; text-align: center;">Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): 
                                $status = (int)$log['status_resposta'];
                                $badgeClass = 'badge-success';
                                if ($status >= 400 && $status < 500) {
                                    $badgeClass = 'badge-warning';
                                } elseif ($status >= 500 || $status === 405) {
                                    $badgeClass = 'badge-error';
                                }
                                
                                // Formata data
                                $dataLog = date('d/m/Y H:i:s', strtotime($log['criado_em']));
                            ?>
                                <tr class="log-row">
                                    <td style="color: var(--text-secondary);"><?= $dataLog ?></td>
                                    <td style="font-family: monospace; font-size: 0.85rem;"><?= htmlspecialchars($log['ip']) ?></td>
                                    <td style="font-family: monospace; font-size: 0.85rem; font-weight: 600; color: #ffb830;">
                                        <?= htmlspecialchars($log['event_type'] ?: 'Manual/Fallback') ?>
                                    </td>
                                    <td style="font-weight: 700; color: var(--success-color);"><?= htmlspecialchars($log['metodo']) ?></td>
                                    <td>
                                        <span class="badge <?= $badgeClass ?>"><?= $status ?></span>
                                    </td>
                                    <td style="font-weight: 500;"><?= htmlspecialchars($log['mensagem_resposta']) ?></td>
                                    <td style="text-align: center;">
                                        <button class="btn-inspect" onclick="togglePayload(<?= $log['id'] ?>)">Ver Dados</button>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="7" style="padding: 0; border: none;">
                                        <div id="payload-<?= $log['id'] ?>" class="payload-container"><?= htmlspecialchars(json_encode(json_decode($log['payload']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: $log['payload']) ?></div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <script>
        // Função para expandir/colapsar os dados brutos da requisição
        function togglePayload(id) {
            const container = document.getElementById('payload-' + id);
            if (container.style.display === 'block') {
                container.style.display = 'none';
            } else {
                // Esconde outros abertos para ficar organizado (opcional)
                document.querySelectorAll('.payload-container').forEach(el => {
                    if (el.id !== 'payload-' + id) el.style.display = 'none';
                });
                container.style.display = 'block';
            }
        }
    </script>
</body>
</html>
