# Conectar WhatsApp — Evolution API

Página web onde o cliente digita o nome da empresa e escaneia um QR Code
para conectar o WhatsApp à sua Evolution API, de qualquer lugar.

## Estrutura

Tudo em uma pasta só — pensada para subir dentro de uma hospedagem que já
tem outros arquivos no root (ex.: `seusite.com/connect/`):

```
connect/
├── index.html      ← página que o cliente acessa
├── style.css
├── app.js
├── proxy.php         ← fala com a Evolution API (a única parte "backend")
├── config.php        ← preencha com sua URL e API Key
└── .htaccess          ← bloqueia acesso direto ao config.php
```

## Instalação

1. Suba a pasta `connect/` inteira para dentro do root do seu site
   (ex.: via FTP/SFTP, ficando em `seusite.com/connect/`).
2. Abra `config.php` e preencha:
   - `base_url`: URL da sua Evolution API (ex.: `https://evolution.seudominio.com.br`)
   - `api_key`: a API Key global da sua Evolution API
3. Garanta que o servidor tem PHP com a extensão `curl` habilitada (padrão na maioria dos hosts).
4. Acesse `https://seusite.com/connect/` — pronto, já é acessível de qualquer lugar.

## Segurança

- A API Key fica só em `config.php`, que nunca é enviado ao navegador.
- `.htaccess` bloqueia qualquer requisição direta a `config.php` — funciona em
  servidores **Apache** (a grande maioria das hospedagens compartilhadas).
  Se o seu servidor usa **Nginx**, adicione ao `server{}`:
  ```nginx
  location = /connect/config.php { deny all; }
  ```
  Se não tiver certeza, teste depois de publicar: acessar
  `https://seusite.com/connect/config.php` direto no navegador deve dar
  erro (403 ou página em branco), nunca mostrar o conteúdo do arquivo.
- Recomenda-se servir a página em HTTPS.

## Como funciona o fluxo

1. O cliente digita o nome da empresa/unidade.
2. `app.js` chama `proxy.php?action=connect`, que:
   - verifica se já existe uma instância com esse nome na Evolution API;
   - se já estiver conectada, pula direto pra tela de sucesso;
   - senão, cria a instância se necessário e devolve o QR Code em base64.
3. A página faz *polling* a cada 3 segundos em `proxy.php?action=status`
   até o estado da instância virar `open` (conectado).
4. Na tela de sucesso, o botão **Desconectar** chama `proxy.php?action=disconnect`
   (faz logout mas mantém a instância), voltando o cliente pra etapa inicial —
   de onde ele pode reconectar quando quiser.

## Personalização rápida

- Cores, fontes e textos: `style.css` e `index.html`.
- Prefixo do nome da instância (evita colisão entre clientes): `instance_prefix` em `config.php`.
