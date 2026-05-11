<?php
require_once __DIR__ . '/response.php';
require_once __DIR__ . '/jwt.php';

function get_bearer_token(): ?string {
    // 1. Priorizar parámetros GET/POST (el método más compatible en redes difíciles)
    $token_raw = $_GET['token'] ?? $_POST['token'] ?? null;
    if ($token_raw) {
        return trim($token_raw);
    }

    // 2. Intentar por encabezados estándar
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? $_SERVER['HTTP_X_AUTHORIZATION'] ?? '';

    if (!$header && function_exists('getallheaders')) {
        $headers = getallheaders();
        $header = $headers['Authorization'] ?? $headers['authorization'] ?? $headers['X-Authorization'] ?? $headers['x-authorization'] ?? '';
    }

    if (preg_match('/Bearer\s+(.+)/i', $header, $m)) {
        return trim($m[1]);
    }

    return null;
}

function require_auth(): array {
    $token = get_bearer_token();
    
    // Si no hay token, intentar por sesión (para el dashboard web)
    if (!$token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (isset($_SESSION['user_id']) || isset($_SESSION['id_usuario'])) {
            return [
                'uid' => $_SESSION['user_id'] ?? $_SESSION['id_usuario'],
                'email' => $_SESSION['correo_corporativo'] ?? '',
                'departamento_id' => $_SESSION['departamento_id'] ?? null,
            ];
        }
        
        json_response(401, 'error', 'No autenticado');
    }

    $secret = env('JWT_SECRET', '');
    if ($secret === '') {
        json_response(500, 'error', 'JWT_SECRET no configurado');
    }

    try {
        return jwt_verify($token, $secret);
    } catch (Throwable $e) {
        json_response(401, 'error', 'Token inválido');
    }

    // Extra safety for static analyzers.
    throw new RuntimeException('Flujo inesperado en require_auth');
}