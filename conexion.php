<?php
// Cargar configuración original si existe (para la versión de escritorio)
if (file_exists(__DIR__ . "/config.php")) {
    require_once __DIR__ . "/config.php";
}

// Cargar variables de entorno para producción
if (file_exists(__DIR__ . '/.env')) {
    foreach (parse_ini_file(__DIR__ . '/.env') as $key => $value) {
        putenv("$key=$value");
    }
}

// Validar que las variables de configuración existan
if (!isset($host) || !isset($user) || !isset($pass) || !isset($db)) {
    die("Error: Archivo de configuración 'config.php' no configurado o incompleto. Crea uno a partir de 'config.php.example'.");
}

// Crear la conexión (Estilo Orientado a Objetos - recomendado)
$conn = new mysqli($host, $user, $pass, $db);

// Verificar conexión
if ($conn->connect_error) {
    // Si los errores están desactivados en producción, ocultar el detalle técnico
    if (ini_get('display_errors') == 0) {
        error_log("Error de conexión a la base de datos: " . $conn->connect_error);
        die("Error de conexión a la base de datos. Por favor, contacte al administrador.");
    } else {
        die("Error de conexión a la base de datos: " . $conn->connect_error);
    }
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
