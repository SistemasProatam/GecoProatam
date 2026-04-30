<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../config.php";
/**
 * Gestor de Sesiones - Maneja sesiones de forma centralizada
 */

class SessionManager {
    private static $started = false;
    
    public static function start() {
        if (!self::$started && session_status() === PHP_SESSION_NONE) {
            // Configuración de seguridad para sesiones
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', 1); // Solo si usas HTTPS
            ini_set('session.use_strict_mode', 1);
            
            session_start();
            self::$started = true;
            
            // Regenerar ID de sesión periódicamente para seguridad
            if (!isset($_SESSION['created'])) {
                $_SESSION['created'] = time();
            } else if (time() - $_SESSION['created'] > 1800) { // 30 minutos
                session_regenerate_id(true);
                $_SESSION['created'] = time();
            }
        }
    }
    
    public static function destroy() {
        if (self::$started) {
            // Limpiar todas las variables de sesión
            $_SESSION = array();
            
            // Destruir la cookie de sesión
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            
            session_destroy();
            self::$started = false;
        }
    }
    
    public static function regenerate() {
        if (self::$started) {
            session_regenerate_id(true);
            $_SESSION['created'] = time();
        }
    }
    
    public static function get($key, $default = null) {
        self::start();
        return $_SESSION[$key] ?? $default;
    }
    
    public static function set($key, $value) {
        self::start();
        $_SESSION[$key] = $value;
    }
    
    public static function remove($key) {
        self::start();
        unset($_SESSION[$key]);
    }
    
    public static function exists($key) {
        self::start();
        return isset($_SESSION[$key]);
    }
    
    public static function flash($key, $value = null) {
        self::start();
        
        if ($value === null) {
            // Obtener y eliminar el mensaje flash
            $message = $_SESSION['_flash'][$key] ?? null;
            unset($_SESSION['_flash'][$key]);
            return $message;
        } else {
            // Establecer mensaje flash
            $_SESSION['_flash'][$key] = $value;
        }
    }
    
    public static function getAll() {
        self::start();
        return $_SESSION;
    }
    
    public static function clear() {
        self::start();
        $_SESSION = array();
    }
}
?>

