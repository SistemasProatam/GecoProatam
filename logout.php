<?php
require_once "config.php";

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

// Si se solicita logout silencioso (para UX inmersiva con AJAX o previo toast)
if (isset($_GET['silent'])) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } else {
        header("Location: " . BASE_URL . "/login.php");
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cerrando sesión...</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/ui.css">
<script>window.BASE_URL = '<?= BASE_URL ?>';</script>

    <link rel="icon" href="<?= BASE_URL ?>/assets/img/GECO_ISOLOGO.png" type="image/x-icon">
</head>
<body>
<script src="<?= BASE_URL ?>/assets/scripts/ui.js"></script>

<script>
const reason = "<?php echo $reason; ?>";
const message = "<?php echo $message; ?>";

window.addEventListener('DOMContentLoaded', () => {
  if (reason === 'timeout') {
    UI.toast.info(message, 3000);
  } else {
    UI.toast.success(message, 3000);
  }
  setTimeout(() => {
    window.location.href = '<?= BASE_URL ?>/login.php';
  }, 3000);
});
</script>

</body>
</html>


