<?php
/**
 * Conexão segura com o Banco de Dados via PDO.
 */

require_once __DIR__ . '/config.php';

function obterConexao(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = sprintf(
                "mysql:host=%s;port=%s;dbname=%s;charset=%s",
                DB_HOST,
                DB_PORT,
                DB_NAME,
                DB_CHARSET
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Dispara exceções em erros de SQL
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Retorna arrays associativos
                PDO::ATTR_EMULATE_PREPARES   => false,                  // Usa prepares reais do MySQL para segurança e performance
            ];

            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            
            // Configura o fuso horário da sessão do banco de dados para UTC-3 (Brasil)
            $pdo->exec("SET time_zone = '-03:00'");

            // Auto-migração/Self-healing para o campo exibicao_logo na tabela tenants
            try {
                $q = $pdo->query("SHOW COLUMNS FROM tenants LIKE 'exibicao_logo'");
                if ($q && $q->rowCount() === 0) {
                    $pdo->exec("ALTER TABLE tenants ADD COLUMN exibicao_logo VARCHAR(20) DEFAULT 'logo_nome' AFTER logo_path");
                }
            } catch (Exception $e) {
                // Silenciosamente ignora se a tabela tenants não existir ainda
            }

            // Auto-migração/Self-healing para o campo tempo_limite_espera na tabela tenants
            try {
                $q = $pdo->query("SHOW COLUMNS FROM tenants LIKE 'tempo_limite_espera'");
                if ($q && $q->rowCount() === 0) {
                    $pdo->exec("ALTER TABLE tenants ADD COLUMN tempo_limite_espera INT DEFAULT 5 AFTER exibicao_logo");
                }
            } catch (Exception $e) {
                // Silenciosamente ignora se a tabela tenants não existir ainda
            }

            // Auto-migração/Self-healing para alterar ENUM para VARCHAR na tabela chamados (suporta novos status)
            try {
                $pdo->exec("ALTER TABLE chamados MODIFY COLUMN status VARCHAR(20) NOT NULL DEFAULT 'pendente'");
            } catch (Exception $e) {
                // Silenciosamente ignora se a tabela chamados não existir ainda
            }

            // Auto-migração/Self-healing para criar a tabela de auditoria de inspeções
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS `superadmin_auditoria_logs` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `usuario_id` INT NOT NULL,
                    `usuario_nome` VARCHAR(255) NOT NULL,
                    `usuario_email` VARCHAR(255) NOT NULL,
                    `tenant_slug` VARCHAR(100) NOT NULL,
                    `tenant_nome` VARCHAR(255) NOT NULL,
                    `acao` VARCHAR(50) NOT NULL,
                    `detalhes` TEXT NULL,
                    `ip` VARCHAR(45) NOT NULL,
                    `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    CONSTRAINT `fk_auditoria_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            } catch (Exception $e) {
                // Silenciosamente ignora
            }
        } catch (PDOException $e) {
            // Registra o erro internamente com segurança
            registrarErro("Falha na conexão PDO: " . $e->getMessage(), [
                'host' => DB_HOST,
                'porta' => DB_PORT,
                'db' => DB_NAME
            ]);

            // Resposta segura e limpa ao cliente
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'erro' => true,
                'mensagem' => 'Erro interno de banco de dados. O incidente foi registrado.'
            ]);
            exit;
        }
    }

    return $pdo;
}

/**
 * Obtém o valor de uma configuração salva no banco de dados para um tenant específico.
 * 
 * @param string $chave Identificador da configuração.
 * @param mixed $padrao Valor de retorno padrão se a configuração não existir.
 * @param int|null $empresaId ID da empresa (se omitido, tenta obter do contexto de sessão/tenant).
 * @return mixed
 */
function obterConfiguracao(string $chave, $padrao = null, ?int $empresaId = null)
{
    // Resolução de tenant ativo
    if ($empresaId === null) {
        if (isset($_SESSION['tenant_ativo_id'])) {
            $empresaId = (int)$_SESSION['tenant_ativo_id'];
        } elseif (isset($_SESSION['empresa_id'])) {
            $empresaId = (int)$_SESSION['empresa_id'];
        }
    }

    if ($empresaId === null) {
        return $padrao;
    }

    try {
        $db = obterConexao();
        $stmt = $db->prepare("SELECT valor FROM sistema_config WHERE empresa_id = :empresa_id AND chave = :chave");
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':chave' => $chave
        ]);
        $res = $stmt->fetch();
        if ($res !== false) {
            return $res['valor'];
        }
    } catch (PDOException $e) {
        // Se a tabela não existir, tenta criar e prossegue (mantendo suporte automático)
        try {
            $db = obterConexao();
            $db->exec("CREATE TABLE IF NOT EXISTS `sistema_config` (
                `empresa_id` INT NOT NULL,
                `chave` VARCHAR(255) NOT NULL,
                `valor` TEXT NOT NULL,
                PRIMARY KEY (`empresa_id`, `chave`),
                CONSTRAINT `fk_config_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (Exception $ex) {
            registrarErro("Erro ao criar tabela sistema_config: " . $ex->getMessage());
        }
    }
    return $padrao;
}

/**
 * Grava ou atualiza uma configuração no banco de dados para um tenant específico.
 * 
 * @param string $chave Identificador da configuração.
 * @param string $valor Valor a ser gravado.
 * @param int|null $empresaId ID da empresa.
 * @return bool
 */
function salvarConfiguracao(string $chave, string $valor, ?int $empresaId = null): bool
{
    // Resolução de tenant ativo
    if ($empresaId === null) {
        if (isset($_SESSION['tenant_ativo_id'])) {
            $empresaId = (int)$_SESSION['tenant_ativo_id'];
        } elseif (isset($_SESSION['empresa_id'])) {
            $empresaId = (int)$_SESSION['empresa_id'];
        }
    }

    if ($empresaId === null) {
        registrarErro("Erro ao salvar configuração '{$chave}': Empresa ID não resolvido.");
        return false;
    }

    try {
        $db = obterConexao();
        $stmt = $db->prepare("INSERT INTO sistema_config (empresa_id, chave, valor) VALUES (:empresa_id, :chave, :valor) 
            ON DUPLICATE KEY UPDATE valor = :valor_update");
        return $stmt->execute([
            ':empresa_id' => $empresaId,
            ':chave' => $chave,
            ':valor' => $valor,
            ':valor_update' => $valor
        ]);
    } catch (PDOException $e) {
        // Se a tabela não existir, tenta criar e tenta novamente a inserção
        try {
            $db = obterConexao();
            $db->exec("CREATE TABLE IF NOT EXISTS `sistema_config` (
                `empresa_id` INT NOT NULL,
                `chave` VARCHAR(255) NOT NULL,
                `valor` TEXT NOT NULL,
                PRIMARY KEY (`empresa_id`, `chave`),
                CONSTRAINT `fk_config_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            
            $stmt = $db->prepare("INSERT INTO sistema_config (empresa_id, chave, valor) VALUES (:empresa_id, :chave, :valor) 
                ON DUPLICATE KEY UPDATE valor = :valor_update");
            return $stmt->execute([
                ':empresa_id' => $empresaId,
                ':chave' => $chave,
                ':valor' => $valor,
                ':valor_update' => $valor
            ]);
        } catch (Exception $ex) {
            registrarErro("Erro ao salvar configuração no banco: " . $ex->getMessage(), [
                'empresa_id' => $empresaId,
                'chave' => $chave,
                'valor' => $valor
            ]);
            return false;
        }
    }
}
