<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../config.php";
require_once __DIR__ . '/session_manager.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar que el usuario esté loggeado
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
    exit;
}

// Extender el tiempo de última actividad
if (isset($_SESSION['last_activity'])) {
    $_SESSION['last_activity'] = time();
    
    // También actualizar en las pestañas activas
    if (isset($_SESSION['active_tabs'])) {
        $current_tab = $_SESSION['tab_id'] ?? '';
        if ($current_tab && isset($_SESSION['active_tabs'][$current_tab])) {
            $_SESSION['active_tabs'][$current_tab] = time();
        }
    }
    
    echo json_encode(['success' => true, 'message' => 'Sesión extendida']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error extendiendo sesión']);
}
?>

