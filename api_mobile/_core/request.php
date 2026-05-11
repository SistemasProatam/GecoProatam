<?php
require_once __DIR__ . '/response.php';

// Soporte Universal para Datos: Captura JSON o Form-Data sin importar el Content-Type
$input = file_get_contents('php://input');
if ($input) {
    $decoded = json_decode($input, true);
    if (is_array($decoded)) {
        // Soporte especial para React Native FormData deserializado (_parts)
        if (isset($decoded['_parts']) && is_array($decoded['_parts'])) {
            foreach ($decoded['_parts'] as $part) {
                if (isset($part[0]) && isset($part[1])) {
                    $_POST[$part[0]] = $part[1];
                    $_REQUEST[$part[0]] = $part[1];
                }
            }
        }
        
        $_POST = array_merge($_POST, $decoded);
        $_REQUEST = array_merge($_REQUEST, $decoded);
    }
}

// Escáner de emergencia: Si algo no está en POST pero sí en GET, moverlo para compatibilidad
foreach ($_GET as $key => $value) {
    if (!isset($_POST[$key])) {
        $_POST[$key] = $value;
    }
}


function require_method(string $method): void {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
    if (strtoupper($_SERVER['REQUEST_METHOD']) !== strtoupper($method)) {
        json_response(405, 'error', 'Método no permitido');
    }
}

function post_string(string $key, bool $required = false, int $maxLen = 5000): ?string {
    $value = isset($_POST[$key]) ? trim((string)$_POST[$key]) : null;
    if ($required && ($value === null || $value === '')) {
        error_log("DEBUG 422: Falta '$key'. POST: " . json_encode($_POST) . " GET: " . json_encode($_GET));
        throw new InvalidArgumentException("El campo {$key} es obligatorio.");
    }
    if ($value !== null && mb_strlen($value) > $maxLen) {
        throw new InvalidArgumentException("El campo {$key} excede la longitud permitida.");
    }
    return $value;
}

function post_int(string $key, bool $required = false, int $min = 1): ?int {
    $raw = $_POST[$key] ?? null;
    if (($raw === null || $raw === '') && $required) {
        error_log("DEBUG 422: Falta '$key'. POST: " . json_encode($_POST) . " GET: " . json_encode($_GET));
        throw new InvalidArgumentException("El campo {$key} es obligatorio.");
    }
    if ($raw === null || $raw === '') return null;
    if (!is_numeric($raw)) {
        throw new InvalidArgumentException("El campo {$key} debe ser numérico.");
    }
    $v = (int)$raw;
    if ($v < $min) {
        throw new InvalidArgumentException("El campo {$key} es inválido.");
    }
    return $v;
}

function post_json_array(string $key, bool $required = false): array {
    $raw = $_POST[$key] ?? '';
    if ($required && trim((string)$raw) === '') {
        throw new InvalidArgumentException("El campo {$key} es obligatorio.");
    }
    if (trim((string)$raw) === '') return [];
    $decoded = json_decode((string)$raw, true);
    if (!is_array($decoded)) {
        throw new InvalidArgumentException("El campo {$key} debe ser un arreglo JSON válido.");
    }
    return $decoded;
}