<?php
/**
 * Utilitário nativo para codificação, decodificação e validação de tokens JWT (HS256).
 */

class JWT
{
    private static function base64url_encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64url_decode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Gera um token JWT assinado com HS256.
     * 
     * @param array $payload Dados associados ao token.
     * @param string $secret Chave secreta de assinatura.
     * @param int $lifetime Tempo de expiração em segundos (padrão: 3 horas).
     * @return string Token assinado.
     */
    public static function encode(array $payload, string $secret, int $lifetime = 10800): string
    {
        $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);
        
        // Injeta a expiração se não existir no payload
        if (!isset($payload['exp'])) {
            $payload['exp'] = time() + $lifetime;
        }
        
        $payloadJson = json_encode($payload);
        
        $base64UrlHeader = self::base64url_encode($header);
        $base64UrlPayload = self::base64url_encode($payloadJson);
        
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secret, true);
        $base64UrlSignature = self::base64url_encode($signature);
        
        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    /**
     * Valida e decodifica um token JWT. Retorna null se for inválido ou expirado.
     * 
     * @param string $token Token JWT.
     * @param string $secret Chave secreta de assinatura.
     * @return array|null Payload decodificado ou null se inválido.
     */
    public static function decode(string $token, string $secret): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        
        list($base64UrlHeader, $base64UrlPayload, $base64UrlSignature) = $parts;
        
        // Verifica a assinatura
        $signature = self::base64url_decode($base64UrlSignature);
        $expectedSignature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secret, true);
        
        if (!hash_equals($signature, $expectedSignature)) {
            return null;
        }
        
        // Decodifica o payload
        $payloadJson = self::base64url_decode($base64UrlPayload);
        $payload = json_decode($payloadJson, true);
        
        if (!$payload) {
            return null;
        }
        
        // Verifica expiração (se configurada)
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null; // Token expirado
        }
        
        return $payload;
    }
}
