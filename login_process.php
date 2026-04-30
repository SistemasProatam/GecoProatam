<?php
header('Content-Type: application/json');
session_start();

// Incluir conexiÃ³n
require_once __DIR__ . "/conexion.php";

// Verificar conexiÃ³n
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

// Validaciones bÃ¡sicas
if (empty($correo_corporativo) || empty($password)) {
    echo json_encode([
        "status" => "error",
        "message" => "Debes ingresar correo corporativo y contraseÃ±a"
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

// Si llegamos aquÃ­, el usuario existe
$row = $result->fetch_assoc();

// Verificar contraseÃ±a
if (password_verify($password, $row['password'])) {
    // Guardar datos en sesiÃ³n
    $_SESSION['user_id'] = $row['id'];
    $_SESSION['nombres'] = $row['nombres'];
    $_SESSION['apellidos'] = $row['apellidos'];
    $_SESSION['correo_corporativo'] = $row['correo_corporativo'];
    $_SESSION['departamento_id'] = $row['departamento_id'];
    $_SESSION['departamento'] = $row['departamento_nombre'] ?? '';

    // Si la contraseÃ±a es temporal, setear la bandera change_pass
    if ($row['password_temporal']) {
        $_SESSION['change_pass'] = true;
    }

    // RedirecciÃ³n segÃºn departamento
    $redirect = "index.php"; // Por defecto

    if ($row['password_temporal']) {
        $redirect = BASE_URL . "/change_password.php";
    } else if ($row['departamento_nombre'] === 'Gerente de Operaciones') {
        $redirect = BASE_URL . "/orders/list_requis.php";
    }

    /**
     * REDIRECT DESPUÃ‰S DEL LOGIN
     * (para cuando el usuario fue enviado al login desde otra pÃ¡gina)
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

// Si llega aquÃ­, correo o contraseÃ±a incorrectos
echo json_encode([
    "status" => "error",
    "message" => "Correo o contraseÃ±a incorrectos"
]);
exit;

