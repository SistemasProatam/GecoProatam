<?php
/**
 * Wrapper para reutilizar la lógica de recuperación de contraseña de la web
 */
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

// Habilitamos errores para el log
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Redirigimos la petición al archivo original de la raíz
require_once __DIR__ . '/../send_reset_token.php';
