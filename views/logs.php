<?php
/**
 * View de Logs de Webhook - Inspetor de Integração
 * Renderizado dentro do roteador index.php
 */

$empresaId = (int)$_SESSION['tenant_ativo_id'];

// Ação opcional: Limpar logs se solicitado pelo administrador (isolar por empresa_id)
if (isset($_POST['action']) && $_POST['action'] === 'clear') {
    try {
        $db = obterConexao();
        $stmtDel = $db->prepare("DELETE FROM webhook_logs WHERE empresa_id = :empresa_id");
        $stmtDel->execute([':empresa_id' => $empresaId]);
        // Recarrega a URL atual de forma limpa para limpar o POST
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    } catch (Exception $e) {
        $erroLimpar = "Falha ao limpar logs: " . $e->getMessage();
    }
}

// Buscar os logs de acordo com o limite configurado no sistema (isolar por empresa_id)
$limiteLogs = (int) obterConfiguracao('limite_logs', 100);

try {
    $db = obterConexao();
    $sql = "SELECT id, metodo, ip, event_type, payload, status_resposta, mensagem_resposta, criado_em 
            FROM webhook_logs 
            WHERE empresa_id = :empresa_id
            ORDER BY id DESC 
            LIMIT :limite";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
    $stmt->bindValue(':limite', $limiteLogs, PDO::PARAM_INT);
    $stmt->execute();
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

<div class="container" style="max-width: 1200px; width: 100%; margin: 0 auto; display: flex; flex-direction: column; gap: 1.5rem; padding-bottom: 2rem;">

    <!-- Subheader Interno do Painel de Logs -->
    <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 0.8rem; border-bottom: 1px solid var(--border-color); flex-wrap: wrap; gap: 1rem;">
        <div>
            <h2 style="font-size: 1.4rem; font-weight: 600; color: var(--text-primary);">Logs de Integração</h2>
            <p style="font-size: 0.82rem; color: var(--text-secondary); margin-top: 4px;">Auditoria e monitoramento de requisições de webhooks recebidos</p>
        </div>
        <div>
            <form action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8'); ?>" method="POST" onsubmit="return confirm('Tem certeza que deseja apagar todos os logs de webhooks salvos?')">
                <input type="hidden" name="action" value="clear">
                <button type="submit" class="btn-premium" style="background: rgba(255, 71, 87, 0.15); border-color: rgba(255, 71, 87, 0.3); color: var(--color-atendimento); font-weight: 600; width: auto; font-size: 0.8rem; padding: 0.5rem 1rem;">
                    🗑️ Limpar Histórico
                </button>
            </form>
        </div>
    </div>

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
    <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
        <div class="stat-card">
            <span class="stat-val"><?= $total ?></span>
            <span class="stat-label">Total Recebidos (Limite <?= $limiteLogs ?>)</span>
        </div>
        <div class="stat-card" style="border-left: 3.5px solid var(--color-lead);">
            <span class="stat-val" style="color: var(--color-lead);"><?= $sucessos ?></span>
            <span class="stat-label">Processados com Sucesso (2xx)</span>
        </div>
        <div class="stat-card" style="border-left: 3.5px solid var(--color-atendimento);">
            <span class="stat-val" style="color: var(--color-atendimento);"><?= $falhas ?></span>
            <span class="stat-label">Rejeitados/Com Erro</span>
        </div>
    </div>

    <!-- Painel principal de Tabela -->
    <div class="logs-panel" style="background: var(--panel-bg); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.5rem; backdrop-filter: blur(16px); overflow: hidden; width: 100%;">
        <?php if (empty($logs)): ?>
            <div class="empty-state">
                <div class="empty-icon">📁</div>
                <p>Nenhum log de webhook registrado ainda no banco de dados.</p>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto; width: 100%;">
                <table style="width: 100%; border-collapse: collapse; text-align: left;">
                    <thead>
                        <tr>
                            <th style="width: 160px;">Data/Hora</th>
                            <th style="width: 130px;">IP Remetente</th>
                            <th style="width: 180px;">Tipo de Evento</th>
                            <th style="width: 80px;">Método</th>
                            <th style="width: 100px;">Status HTTP</th>
                            <th>Resposta da API</th>
                            <th style="width: 110px; text-align: center;">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log):
                            $status = (int) $log['status_resposta'];
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
                                <td style="font-weight: 700; color: var(--color-lead);">
                                    <?= htmlspecialchars($log['metodo']) ?></td>
                                <td>
                                    <span class="badge <?= $badgeClass ?>"><?= $status ?></span>
                                </td>
                                <td style="font-weight: 500; color: var(--text-primary);"><?= htmlspecialchars($log['mensagem_resposta']) ?></td>
                                <td style="text-align: center;">
                                    <button class="btn-inspect" onclick="togglePayload(<?= $log['id'] ?>)">Ver Dados</button>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="7" style="padding: 0; border: none;">
                                    <div id="payload-<?= $log['id'] ?>" class="payload-container">
                                        <?= htmlspecialchars(json_encode(json_decode($log['payload']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: $log['payload']) ?>
                                    </div>
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
            // Esconde outros abertos para ficar organizado
            document.querySelectorAll('.payload-container').forEach(el => {
                if (el.id !== 'payload-' + id) el.style.display = 'none';
            });
            container.style.display = 'block';
        }
    }
</script>
