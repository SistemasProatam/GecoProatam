<?php
function json_response(
    int $httpCode,
    string $status,
    string $message,
    $data = null,
    array $errors = []
): void {
    http_response_code($httpCode);
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data ?? new stdClass(),
        'errors' => $errors,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!function_exists('env')) {
    function env($key, $default = null) {
        $val = getenv($key);
        if ($val !== false) return $val;
        if (isset($_ENV[$key])) return $_ENV[$key];
        
        if ($key === 'JWT_SECRET') {
            return 'proatam_super_secret_key_2026_fallback';
        }
        
        return $default;
    }
}