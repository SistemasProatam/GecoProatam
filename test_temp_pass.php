<?php
require_once "config.php";
require_once "conexion.php";
session_start();
if(isset($_SESSION['user_id'])) {
    $conn->query("UPDATE usuarios SET password_temporal = 1 WHERE id = " . $_SESSION['user_id']);
    echo "<h3 style='font-family:sans-serif;'>¡Modo Prueba Activado!</h3>";
    echo "<p style='font-family:sans-serif;'>Tu cuenta ahora tiene activada la bandera de <b>contraseña temporal</b>.</p>";
    echo "<p style='font-family:sans-serif;'>Para probar el flujo, haz clic aquí para <a href='logout.php'>cerrar sesión</a> y luego vuelve a iniciar sesión (puedes usar tu misma contraseña actual). Serás redirigido automáticamente al nuevo diseño de 'Cambio de Contraseña'.</p>";
} else {
    echo "<p style='font-family:sans-serif;'>Inicia sesión normalmente primero para poder activar el modo de prueba.</p>";
}
?>
