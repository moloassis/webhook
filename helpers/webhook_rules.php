<?php
/**
 * Helper de Regras de Webhook - Central de Alertas
 * Motor único de classificação (categoria + modo de exibição) de eventos de webhook recebidos,
 * configurável por tenant. Elimina a duplicação de regex/keyword-matching que antes existia
 * separadamente em webhook.php e no frontend (assets/js/index.js).
 */

require_once __DIR__ . '/../db.php';

const CATEGORIAS_VALIDAS_WEBHOOK = ['atendimento_humano', 'novo_lead', 'novo_atendimento', 'alerta_sistema', 'ignorar'];
const MODOS_EXIBICAO_VALIDOS_WEBHOOK = ['normal', 'urgente_fullscreen', 'silencioso'];
const MODOS_DEDUP_VALIDOS_WEBHOOK = ['unificar', 'sempre_notificar'];

/**
 * Conjunto de regras padrão do sistema — tradução literal do comportamento hardcoded
 * histórico do webhook.php. É a única função que deve conter estas palavras-chave;
 * qualquer nova lógica de classificação deve ser expressa como regra, não como código.
 *
 * @return array<string, array<int, array{condicao_palavras: string, categoria: string, modo_exibicao: string}>>
 */
function regrasPadraoWebhook(): array
{
    return [
        'CONTACT_TAG_UPDATE' => [
            ['condicao_palavras' => 'atendimento humano', 'categoria' => 'atendimento_humano', 'modo_exibicao' => 'urgente_fullscreen'],
            ['condicao_palavras' => '', 'categoria' => 'ignorar', 'modo_exibicao' => 'normal'],
        ],
        // CONTACT_UPDATE: variante enviada pelo CRM para atualizações gerais de contato/tags (mesma lógica de CONTACT_TAG_UPDATE)
        'CONTACT_UPDATE' => [
            ['condicao_palavras' => 'atendimento humano', 'categoria' => 'atendimento_humano', 'modo_exibicao' => 'urgente_fullscreen'],
            ['condicao_palavras' => '', 'categoria' => 'ignorar', 'modo_exibicao' => 'normal'],
        ],
        'SESSION_COMPLETE' => [
            ['condicao_palavras' => 'transferida,aguarde,humano,suporte', 'categoria' => 'atendimento_humano', 'modo_exibicao' => 'urgente_fullscreen'],
            ['condicao_palavras' => '', 'categoria' => 'ignorar', 'modo_exibicao' => 'normal'],
        ],
        // SESSION_UPDATE: variante enviada durante a conversa em andamento (mesma lógica de SESSION_COMPLETE)
        'SESSION_UPDATE' => [
            ['condicao_palavras' => 'transferida,aguarde,humano,suporte', 'categoria' => 'atendimento_humano', 'modo_exibicao' => 'urgente_fullscreen'],
            ['condicao_palavras' => '', 'categoria' => 'ignorar', 'modo_exibicao' => 'normal'],
        ],
        'PANEL_CARD_STEP_CHANGE' => [
            ['condicao_palavras' => 'humano,suporte,atendente,human', 'categoria' => 'atendimento_humano', 'modo_exibicao' => 'urgente_fullscreen'],
            ['condicao_palavras' => 'lead,ia', 'categoria' => 'novo_lead', 'modo_exibicao' => 'normal'],
            ['condicao_palavras' => '', 'categoria' => 'ignorar', 'modo_exibicao' => 'normal'],
        ],
        'PANEL_CARD_UPDATE' => [
            ['condicao_palavras' => 'humano,suporte,atendente,human', 'categoria' => 'atendimento_humano', 'modo_exibicao' => 'urgente_fullscreen'],
            ['condicao_palavras' => 'lead,ia', 'categoria' => 'novo_lead', 'modo_exibicao' => 'normal'],
            ['condicao_palavras' => '', 'categoria' => 'ignorar', 'modo_exibicao' => 'normal'],
        ],
        // PANEL_CARD_NEW: card que já nasce diretamente numa etapa, sem STEP_CHANGE prévio (mesma lógica)
        'PANEL_CARD_NEW' => [
            ['condicao_palavras' => 'humano,suporte,atendente,human', 'categoria' => 'atendimento_humano', 'modo_exibicao' => 'urgente_fullscreen'],
            ['condicao_palavras' => 'lead,ia', 'categoria' => 'novo_lead', 'modo_exibicao' => 'normal'],
            ['condicao_palavras' => '', 'categoria' => 'ignorar', 'modo_exibicao' => 'normal'],
        ],
        'SESSION_NEW' => [
            ['condicao_palavras' => '', 'categoria' => 'novo_atendimento', 'modo_exibicao' => 'normal'],
        ],
        'MESSAGE_SENT' => [
            ['condicao_palavras' => '', 'categoria' => 'ignorar', 'modo_exibicao' => 'normal'],
        ],
        'MESSAGE_RECEIVED' => [
            ['condicao_palavras' => '', 'categoria' => 'ignorar', 'modo_exibicao' => 'normal'],
        ],
        'OUTRO' => [
            ['condicao_palavras' => '', 'categoria' => 'ignorar', 'modo_exibicao' => 'normal'],
        ],
    ];
}

/**
 * Carrega as regras efetivas de um tenant: parte do padrão do sistema e sobrepõe
 * com o que o tenant tiver customizado (por tipo de evento) em `sistema_config`.
 * Nunca lança exceção — qualquer falha de leitura/decodificação cai silenciosamente
 * no padrão, com log de erro, para nunca derrubar o recebimento de webhooks.
 *
 * @return array<string, array<int, array{condicao_palavras: string, categoria: string, modo_exibicao: string}>>
 */
function obterRegrasWebhook(int $empresaId): array
{
    $padrao = regrasPadraoWebhook();

    $configBruta = obterConfiguracao('webhook_event_rules', null, $empresaId);
    if (empty($configBruta)) {
        return $padrao;
    }

    try {
        $decodificado = json_decode($configBruta, true, 512, JSON_THROW_ON_ERROR);
    } catch (\JsonException $e) {
        registrarErro("JSON inválido em webhook_event_rules para empresa {$empresaId}: " . $e->getMessage());
        return $padrao;
    }

    if (!is_array($decodificado) || !isset($decodificado['eventos']) || !is_array($decodificado['eventos'])) {
        registrarErro("Estrutura inválida em webhook_event_rules para empresa {$empresaId}.");
        return $padrao;
    }

    // Sobrepõe apenas os tipos de evento explicitamente customizados; os demais mantêm o padrão
    foreach ($decodificado['eventos'] as $tipoEvento => $regras) {
        if (is_string($tipoEvento) && is_array($regras) && !empty($regras)) {
            $padrao[$tipoEvento] = $regras;
        }
    }

    return $padrao;
}

/**
 * Carrega o modo de deduplicação customizado por tipo de evento (chave "dedup_modos" dentro do
 * mesmo blob JSON de `webhook_event_rules`). Tipos não customizados não aparecem no retorno —
 * quem chama deve tratar a ausência como o padrão 'unificar'.
 *
 * @return array<string, string>
 */
function obterModosDedupWebhook(int $empresaId): array
{
    $configBruta = obterConfiguracao('webhook_event_rules', null, $empresaId);
    if (empty($configBruta)) {
        return [];
    }

    try {
        $decodificado = json_decode($configBruta, true, 512, JSON_THROW_ON_ERROR);
    } catch (\JsonException $e) {
        return [];
    }

    if (!is_array($decodificado) || !isset($decodificado['dedup_modos']) || !is_array($decodificado['dedup_modos'])) {
        return [];
    }

    $resultado = [];
    foreach ($decodificado['dedup_modos'] as $tipoEvento => $modo) {
        if (is_string($tipoEvento) && in_array($modo, MODOS_DEDUP_VALIDOS_WEBHOOK, true)) {
            $resultado[$tipoEvento] = $modo;
        }
    }

    return $resultado;
}

/**
 * Retorna o modo de deduplicação efetivo para um tipo de evento específico ('unificar' por padrão).
 */
function obterModoDedupEvento(string $eventType, int $empresaId): string
{
    $modos = obterModosDedupWebhook($empresaId);
    return $modos[$eventType] ?? 'unificar';
}

/**
 * Avalia as regras de um tenant para um evento recebido e retorna a classificação resultante.
 * Primeira regra cuja condição seja vazia (curinga) ou cuja palavra-chave apareça em $textoBusca vence.
 *
 * @return array{categoria: string, modo_exibicao: string, criar_chamado: bool}
 */
function avaliarRegrasWebhook(string $eventType, string $textoBusca, int $empresaId): array
{
    $regras = obterRegrasWebhook($empresaId);
    $chave = isset($regras[$eventType]) ? $eventType : 'OUTRO';
    $listaRegras = $regras[$chave] ?? [['condicao_palavras' => '', 'categoria' => 'ignorar', 'modo_exibicao' => 'normal']];

    foreach ($listaRegras as $regra) {
        $condicao = trim((string)($regra['condicao_palavras'] ?? ''));

        if ($condicao === '') {
            return montarResultadoRegra($regra);
        }

        $palavras = array_filter(array_map('trim', explode(',', $condicao)));
        foreach ($palavras as $palavra) {
            if ($palavra !== '' && mb_stripos($textoBusca, $palavra) !== false) {
                return montarResultadoRegra($regra);
            }
        }
    }

    return ['categoria' => 'ignorar', 'modo_exibicao' => 'normal', 'criar_chamado' => false];
}

/**
 * Normaliza e valida uma regra individual antes de retornar o resultado da avaliação.
 */
function montarResultadoRegra(array $regra): array
{
    $categoria = in_array($regra['categoria'] ?? '', CATEGORIAS_VALIDAS_WEBHOOK, true) ? $regra['categoria'] : 'ignorar';
    $modoExibicao = in_array($regra['modo_exibicao'] ?? '', MODOS_EXIBICAO_VALIDOS_WEBHOOK, true) ? $regra['modo_exibicao'] : 'normal';

    return [
        'categoria' => $categoria,
        'modo_exibicao' => $modoExibicao,
        'criar_chamado' => ($categoria !== 'ignorar'),
    ];
}
