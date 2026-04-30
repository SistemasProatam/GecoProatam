<?php
/** 
 * Punto de entrada cuando alguien escanea el QR fÃ­sico de un activo.
 * 
 * Flujo:
 *   1. Recibe ?token=UUID
 *   2. Busca el activo por qr_token en la BD
 *   3. Si no hay sesiÃ³n activa â†’ redirige al login guardando la URL destino
 *   4. Si hay sesiÃ³n activa â†’ redirige a details_activo.php?id=X
 */

require_once __DIR__ . "/../includes/session_manager.php";

require_once __DIR__ . "/../conexion.php";

// â”€â”€â”€ Validar token â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

$token = trim($_GET['token'] ?? '');

if (!$token || !preg_match('/^[A-Z0-9]{8}$/', $token)) {
    // Token invÃ¡lido o ausente
    header("Location: " . BASE_URL . "/activos/qr_invalido.php?razon=token");
    exit;
}

// â”€â”€â”€ Buscar activo por token â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

$stmt = $conn->prepare("SELECT id, nombre, estatus FROM activos WHERE qr_token = ? LIMIT 1");
$stmt->bind_param("s", $token);
$stmt->execute();
$activo = $stmt->get_result()->fetch_assoc();

if (!$activo) {
    header("Location: " . BASE_URL . "/activos/qr_invalido.php?razon=no_encontrado");
    exit;
}

// â”€â”€â”€ Verificar sesiÃ³n â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

// La URL destino una vez autenticado
$url_destino = '/activos/details_activo.php?id=' . (int)$activo['id'];

// Revisar si ya hay sesiÃ³n iniciada
session_start();
$sesion_activa = !empty($_SESSION['usuario_id']) || !empty($_SESSION['user_id']);

if (!$sesion_activa) {
    // Guardar la URL de destino para redirigir despuÃ©s del login
    $_SESSION['redirect_after_login'] = $url_destino;
    $_SESSION['qr_scan_nombre']       = $activo['nombre']; // para mostrar mensaje en login

    header("Location: " . BASE_URL . "/login.php?from=qr");
    exit;
}


// â”€â”€â”€ VALIDAR DEPARTAMENTO â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

$departamento_usuario = $_SESSION['departamento'] ?? '';

// Departamentos permitidos
$departamentos_permitidos = [
    'Director General',
    'Subdirector General',
    'Coordinador de Control de Documentos y FacturaciÃ³n',
    'Gerente de Seguridad Salud y Medio Ambiente',
    'Tecnico de Sistemas'
];

// Verificar acceso
if (!in_array($departamento_usuario, $departamentos_permitidos)) {

    header("Location: " . BASE_URL . "/activos/qr_invalido.php");
    exit;
}

// â”€â”€â”€ SesiÃ³n activa â†’ redirigir al detalle â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

header("Location: " . $url_destino);
exit;



