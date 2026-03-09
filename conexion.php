<?php
// =======================================
// conexion.php
// =======================================

// Mostrar todos los errores (solo en desarrollo)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuración de la base de datos
$host = "localhost";         // Servidor MySQL
$user = "root";              // Usuario
$pass = "Proatam2025.";              // Contraseña
$db   = "proatam";           // Nombre de la base de datos

// Crear conexión
$conn = new mysqli($host, $user, $pass, $db);

// Verificar conexión
if ($conn->connect_error) {
    die("Error de conexión a la base de datos: " . $conn->connect_error);
}

// Configurar charset
$conn->set_charset("utf8mb4");
?>