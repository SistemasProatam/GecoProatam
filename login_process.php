<?php
header('Content-Type: application/json');
session_start();

// Incluir conexión
require_once __DIR__ . "/conexion.php";

// Verificar conexión
if ($conn->connect_error) {
    echo json_encode([
        "status" => "error",
        "message" => "No se pudo conectar al servidor de base de datos: " . $conn->connect_error
    ]);
    exit;
}

// Obtener datos del formulario
$correo_corporativo = $_POST['correo_corporativo'] ?? '';
$password = $_POST['password'] ?? '';

// Validaciones básicas
if (empty($correo_corporativo) || empty($password)) {
    echo json_encode([
        "status" => "error",
        "message" => "Debes ingresar correo corporativo y contraseña"
    ]);
    exit;
}

// Verificar que sea correo de Proatam
if (!preg_match('/@proatam\.com$/i', $correo_corporativo)) {
    echo json_encode([
        "status" => "error",
        "message" => "solo_correo_proatam" // Identificador especial para el modal
    ]);
    exit;
}

// Preparar consulta
$stmt = $conn->prepare("
    SELECT 
        u.id, 
        u.nombres, 
        u.apellidos, 
        u.correo_corporativo, 
        u.password, 
        u.password_temporal, 
        u.activo, 
        u.departamento_id,
        d.nombre AS departamento_nombre
    FROM usuarios u
    LEFT JOIN departamentos d ON u.departamento_id = d.id
    WHERE u.correo_corporativo = ?
");
$stmt->bind_param("s", $correo_corporativo);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        "status" => "error",
        "message" => "correo_no_registrado" // Identificador especial
    ]);
    exit;
}

// Si hay múltiples usuarios con el mismo correo, debemos probar la contraseña con cada uno
$login_success = false;
$user_data = null;

while ($row = $result->fetch_assoc()) {
    if (password_verify($password, $row['password'])) {
        $login_success = true;
        $user_data = $row;
        break; // Encontramos al usuario correcto
    }
}

if ($login_success) {
    // Guardar datos en sesión
    $_SESSION['user_id'] = $user_data['id'];
    $_SESSION['nombres'] = $user_data['nombres'];
    $_SESSION['apellidos'] = $user_data['apellidos'];
    $_SESSION['correo_corporativo'] = $user_data['correo_corporativo'];
    $_SESSION['departamento_id'] = $user_data['departamento_id'];
    $_SESSION['departamento'] = $user_data['departamento_nombre'] ?? '';

    // Si la contraseña es temporal, setear la bandera change_pass
    if ($user_data['password_temporal']) {
        $_SESSION['change_pass'] = true;
    }

    // Redirección según departamento
    $redirect = "index.php"; // Por defecto

    if ($user_data['password_temporal']) {
        $redirect = BASE_URL . "/change_password.php";
    } else if ($user_data['departamento_nombre'] === 'Gerente de Operaciones') {
        $redirect = BASE_URL . "/orders/list_requis.php";
    }

    /**
     * REDIRECT DESPUÉS DEL LOGIN
     * (para cuando el usuario fue enviado al login desde otra página)
     */
    if (!empty($_SESSION['redirect_after_login'])) {
        $redirect = $_SESSION['redirect_after_login'];
        unset($_SESSION['redirect_after_login']);
        unset($_SESSION['qr_scan_nombre']);
    }

    echo json_encode([
        "status" => "success",
        "message" => "Bienvenido",
        "redirect" => $redirect
    ]);
    exit;
}

// Si llega aquí, correo o contraseña incorrectos
echo json_encode([
    "status" => "error",
    "message" => "Correo o contraseña incorrectos"
]);
exit;
