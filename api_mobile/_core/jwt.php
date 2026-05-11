<?php
function b64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function b64url_decode(string $data): string {
    $padding = strlen($data) % 4;
    if ($padding > 0) {
        $data .= str_repeat('=', 4 - $padding);
    }
    return base64_decode(strtr($data, '-_', '+/'));
}

function jwt_sign(array $payload, string $secret, int $ttlSeconds = 43200): string {
    $header = ['alg' => 'HS256', 'typ' => 'JWT'];
    $now = time();
    $payload['iat'] = $now;
    $payload['exp'] = $now + $ttlSeconds;

    $h = b64url_encode(json_encode($header));
    $p = b64url_encode(json_encode($payload));
    $sig = hash_hmac('sha256', $h . '.' . $p, $secret, true);

    return $h . '.' . $p . '.' . b64url_encode($sig);
}

function jwt_verify(string $token, string $secret): array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        throw new RuntimeException('Token inválido');
    }

    [$h, $p, $s] = $parts;
    $expected = b64url_encode(hash_hmac('sha256', $h . '.' . $p, $secret, true));
    if (!hash_equals($expected, $s)) {
        throw new RuntimeException('Firma inválida');
    }

    $payload = json_decode(b64url_decode($p), true);
    if (!is_array($payload) || !isset($payload['exp'])) {
        throw new RuntimeException('Payload inválido');
    }
    if (time() >= (int)$payload['exp']) {
        throw new RuntimeException('Token expirado');
    }

    return $payload;
}