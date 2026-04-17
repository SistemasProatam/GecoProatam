<?php
require_once __DIR__ . '/../config.php';

require_once __DIR__ . '/session_manager.php';

// Generar un ID único para cada pestaña
function getTabId() {
    if (!SessionManager::exists('tab_id')) {
        SessionManager::set('tab_id', uniqid('tab_', true));
    }
    return SessionManager::get('tab_id');
}

// Verificar si la sesión está activa
function checkSession() {
    // Iniciar sesión solo cuando sea necesario
    SessionManager::start();
    
    // Verificar si hay un ID de pestaña activo
    if (!SessionManager::exists('active_tabs')) {
        SessionManager::set('active_tabs', []);
    }
    
    $current_tab = getTabId();
    $active_tabs = SessionManager::get('active_tabs', []);
    
    // Marcar esta pestaña como activa
    $active_tabs[$current_tab] = time();
    SessionManager::set('active_tabs', $active_tabs);
    
    // Limpiar pestañas inactivas (más de 5 minutos)
    $inactive_time = 300; // 5 minutos
    foreach ($active_tabs as $tab_id => $last_active) {
        if (time() - $last_active > $inactive_time) {
            unset($active_tabs[$tab_id]);
        }
    }
    SessionManager::set('active_tabs', $active_tabs);
    
    // Verificar si el usuario está loggeado
    if (!SessionManager::exists('user_id') || !SessionManager::exists('nombres')) {
        SessionManager::destroy();
        safeRedirect("" . BASE_URL . "/login.php?error=session_expired");
    }
    
    // Verificar inactividad general (30 minutos para cierre forzoso)
    if (SessionManager::exists('last_activity')) {
        $inactive_time = 1800; // 30 minutos
        $current_time = time();
        
        if (($current_time - SessionManager::get('last_activity')) > $inactive_time) {
            SessionManager::destroy();
            safeRedirect("" . BASE_URL . "/login.php?error=session_timeout");
        }
    }
    
    // Actualizar tiempo de última actividad
    SessionManager::set('last_activity', time());
}

// Función para prevenir caching
function preventCaching() {
    if (!headers_sent()) {
        header("Cache-Control: no-cache, no-store, must-revalidate");
        header("Pragma: no-cache");
        header("Expires: 0");
    }
}

// Función auxiliar para redirección segura
function safeRedirect($url) {
    if (headers_sent()) {
        echo '<script>window.location.href = "' . $url . '";</script>';
        exit();
    } else {
        header("Location: " . $url);
        exit();
    }
}

// Limpiar pestañas inactivas
function cleanupInactiveTabs() {
    if (!SessionManager::exists('active_tabs')) {
        return;
    }
    
    $active_tabs = SessionManager::get('active_tabs', []);
    $inactive_time = 300; // 5 minutos
    
    foreach ($active_tabs as $tab_id => $last_active) {
        if (time() - $last_active > $inactive_time) {
            unset($active_tabs[$tab_id]);
        }
    }
    
    SessionManager::set('active_tabs', $active_tabs);
}

// Limpiar pestañas inactivas en cada carga
cleanupInactiveTabs();
?>

