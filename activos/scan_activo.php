<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../config.php";
/** 
 * Punto de entrada cuando alguien escanea el QR físico de un activo.
 * 
 * Flujo:
 *   1. Recibe ?token=UUID
 *   2. Busca el activo por qr_token en la BD
 *   3. Si no hay sesión activa → redirige al login guardando la URL destino
 *   4. Si hay sesión activa → redirige a details_activo.php?id=X
 */

require_once __DIR__ . "/../includes/session_manager.php";

include(__DIR__ . "/../conexion.php");

// ─── Validar token ───────────────────────────────────────────────────────────

$token = trim($_GET['token'] ?? '');

if (!$token || !preg_match('/^[A-Z0-9]{8}$/', $token)) {
    // Token inválido o ausente
    header("Location: " . BASE_URL . "/activos/qr_invalido.php?razon=token");
    exit;
}

// ─── Buscar activo por token ─────────────────────────────────────────────────

$stmt = $conn->prepare("SELECT id, nombre, estatus FROM activos WHERE qr_token = ? LIMIT 1");
$stmt->bind_param("s", $token);
$stmt->execute();
$activo = $stmt->get_result()->fetch_assoc();

if (!$activo) {
    header("Location: " . BASE_URL . "/activos/qr_invalido.php?razon=no_encontrado");
    exit;
}

// ─── Verificar sesión ────────────────────────────────────────────────────────

// La URL destino una vez autenticado
$url_destino = '/activos/details_activo.php?id=' . (int)$activo['id'];

// Revisar si ya hay sesión iniciada
session_start();
$sesion_activa = !empty($_SESSION['usuario_id']) || !empty($_SESSION['user_id']);

if (!$sesion_activa) {
    // Guardar la URL de destino para redirigir después del login
    $_SESSION['redirect_after_login'] = $url_destino;
    $_SESSION['qr_scan_nombre']       = $activo['nombre']; // para mostrar mensaje en login

    header("Location: " . BASE_URL . "/login.php?from=qr");
    exit;
}


// ─── VALIDAR DEPARTAMENTO ─────────────────────────────────

$departamento_usuario = $_SESSION['departamento'] ?? '';

// Departamentos permitidos
$departamentos_permitidos = [
    'Director General',
    'Subdirector General',
    'Coordinador de Control de Documentos y Facturación',
    'Gerente de Seguridad Salud y Medio Ambiente',
    'Tecnico de Sistemas'
];

// Verificar acceso
if (!in_array($departamento_usuario, $departamentos_permitidos)) {

    header("Location: " . BASE_URL . "/activos/qr_invalido.php");
    exit;
}

// ─── Sesión activa → redirigir al detalle ────────────────────────────────────

header("Location: " . $url_destino);
exit;


