<?php
require_once __DIR__ . '/config.php';

session_start();

// Determinar el mensaje según la razón del logout
$reason = $_GET['reason'] ?? 'logout';
$message = '';

switch ($reason) {
    case 'timeout':
        $message = 'Tu sesión ha expirado por inactividad.';
        break;
    case 'logout':
    default:
        $message = 'Has salido de tu cuenta correctamente.';
        break;
}

// Limpiar todas las pestañas
$_SESSION['active_tabs'] = [];

// Limpiar todas las variables de sesión
$_SESSION = array();

// Destruir la sesión
session_destroy();

// Limpiar cookies de sesión
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cerrando sesión...</title>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

<script>
const message = "<?php
 echo $message; ?>";
const icon = "<?php
 echo ($reason === 'timeout') ? 'info' : 'success'; ?>";

Swal.fire({
    title: '¡Sesión cerrada!',
    text: message,
    icon: icon,
    timer: 3000,
    showConfirmButton: false,
    timerProgressBar: true,
    willClose: () => {
        // Redirigir automáticamente al login al cerrar el modal
        window.location.href = '<?= BASE_URL ?>/login.php';
    }
});
</script>

</body>
</html>

