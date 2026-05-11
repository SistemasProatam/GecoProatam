<?php
// Cargar configuración original si existe (para la versión de escritorio)
if (file_exists(__DIR__ . "/config.php")) {
    require_once __DIR__ . "/config.php";
}

// Variables de respaldo si no vienen de config.php
$db_host = isset($host) ? $host : "localhost";
$db_user = isset($user) ? $user : "root";
$db_pass = isset($pass) ? $pass : "";
$db_name = isset($db) ? $db : "proatam";

// Crear la conexión (Estilo Orientado a Objetos - recomendado)
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Verificar conexión
if ($conn->connect_error) {
    die("Error de conexión a la base de datos: " . $conn->connect_error);
}

// Configurar charset
$conn->set_charset("utf8mb4");

// --- TRADUCTOR UNIVERSAL (Solo para API Móvil) ---
// Solo cargamos el traductor si estamos en una ruta de api_mobile
if (strpos($_SERVER['REQUEST_URI'], 'api_mobile') !== false) {
    if (file_exists(__DIR__ . '/api_mobile/_core/request.php')) {
        require_once __DIR__ . '/api_mobile/_core/request.php';
    }
}