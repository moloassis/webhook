<?php
/**
 * Configuração da Evolution API.
 *
 * PREENCHA os dois valores abaixo com os dados do seu servidor Evolution API.
 * Este arquivo fica apenas no backend (servidor) — a API Key nunca é
 * enviada para o navegador do cliente, então fica segura.
 *
 * Depois de preencher, garanta que esta pasta "api/" não seja acessível
 * publicamente por outra rota que não seja proxy.php (ex: bloqueie
 * listagem de diretório no servidor).
 */

return [
    // URL base da sua Evolution API, sem barra no final.
    // Exemplo: 'https://evolution.seudominio.com.br'
    'base_url' => 'https://madeinia-evolution-api.rejeye.easypanel.host/',

    // API Key global da sua Evolution API (a mesma usada no header "apikey").
    'api_key'  => '429683C4C977415CAAFCCE10F7D57E11',

    // Prefixo opcional adicionado ao nome da instância criada para cada
    // cliente, evitando colisão de nomes. Ex: "cliente-" + nome digitado.
    'instance_prefix' => 'cliente-',
];
