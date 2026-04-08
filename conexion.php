<?php
// Configuración de la base de datos
require_once __DIR__ . '/config.php';

// Crear conexión
$conn = new mysqli($host, $user, $pass, $db);

// Verificar conexión
if ($conn->connect_error) {
    die("Error de conexión a la base de datos: " . $conn->connect_error);
}

// Configurar charset
$conn->set_charset("utf8mb4");
?>