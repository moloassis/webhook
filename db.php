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
