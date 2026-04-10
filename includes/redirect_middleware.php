<?php
require_once __DIR__ . '/../config.php';

session_start();

// Prevenir acceso directo a páginas sin sesión
function preventDirectAccess() {
    $allowed_pages = ['login.php', 'logout.php', 'unauthorized.php'];
    $current_page = basename($_SERVER['PHP_SELF']);
    
    // Si no hay sesión y no está en páginas permitidas, redirigir al login
    if (!isset($_SESSION['user_id']) && !in_array($current_page, $allowed_pages)) {
        header("Location: /login.php");
        exit();
    }
    
    // Si hay sesión y está en login, redirigir al index
    if (isset($_SESSION['user_id']) && $current_page == 'login.php') {
        header("Location: /index.php");
        exit();
    }
}

// Ejecutar la verificación
preventDirectAccess();
?>

