<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');


include __DIR__ . '/../conexion.php';

require_once __DIR__ . '/_core/response.php';
require_once __DIR__ . '/_core/request.php';
require_once __DIR__ . '/_core/jwt.php';

require_method('POST');

try {
    if ($conn->connect_error) {
        json_response(500, 'error', 'No se pudo conectar al servidor');
    }

    $correo_corporativo = post_string('correo_corporativo', true, 120);
    $password = post_string('password', true, 120);

    if (!filter_var($correo_corporativo, FILTER_VALIDATE_EMAIL)) {
        json_response(422, 'error', 'Correo inválido');
    }

    if (!preg_match('/@(proatam\.com|local\.dev)$/i', $correo_corporativo)) {
        json_response(403, 'error', 'Solo se permiten correos corporativos de Proatam');
    }

    $ip_cliente = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $limite_intentos = 5;
    $ventana_minutos = 15;

    $stmt_bl = $conn->prepare("SELECT bloqueado_hasta FROM login_intentos WHERE ip = ? AND bloqueado_hasta > NOW() LIMIT 1");
    $stmt_bl->bind_param("s", $ip_cliente);
    $stmt_bl->execute();
    $res_bl = $stmt_bl->get_result();
    if ($res_bl->num_rows > 0) {
        $row_bl = $res_bl->fetch_assoc();
        $minutos = max(1, (int)ceil((strtotime($row_bl['bloqueado_hasta']) - time()) / 60));
        json_response(429, 'error', "Demasiados intentos fallidos. Intenta en {$minutos} minutos.");
    }

    $esLocal = env('APP_ENV', 'production') === 'local';
    $localAdmPass = env('LOCAL_ADMIN_PASS', '');
    $jwtSecret = env('JWT_SECRET', '');

    if ($jwtSecret === '') {
        json_response(500, 'error', 'JWT_SECRET no configurado');
    }

    if ($esLocal && !empty($localAdmPass) && $correo_corporativo === 'admin@local.dev' && $password === $localAdmPass) {
        $token = jwt_sign([
            'uid' => 0,
            'email' => $correo_corporativo,
            'departamento' => 'SUPER_ADMIN',
        ], $jwtSecret, 43200);

        json_response(200, 'success', 'Bienvenido', [
            'token' => $token,
            'expires_in' => 43200,
            'usuario' => [
                'id' => 0,
                'nombres' => 'Super',
                'apellidos' => 'Admin',
                'correo' => $correo_corporativo,
                'departamento' => 'SUPER_ADMIN',
                'departamento_id' => null,
                'change_pass' => false,
            ]
        ]);
    }

    $stmt = $conn->prepare("
        SELECT u.id, u.nombres, u.apellidos, u.correo_corporativo, u.password, u.password_temporal, u.activo,
               u.departamento_id, d.nombre AS departamento_nombre
        FROM usuarios u
        LEFT JOIN departamentos d ON u.departamento_id = d.id
        WHERE u.correo_corporativo = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $correo_corporativo);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        json_response(401, 'error', 'Correo o contraseña incorrectos');
    }

    $row = $result->fetch_assoc();

    if (!(int)$row['activo']) {
        json_response(403, 'error', 'Tu cuenta está desactivada. Contacta al administrador.');
    }

    if (!password_verify($password, $row['password'])) {
        registrarIntentoFallido($conn, $ip_cliente, $limite_intentos, $ventana_minutos);
        json_response(401, 'error', 'Correo o contraseña incorrectos');
    }

    // --- RESTRICCIÓN DE DEPARTAMENTOS SOLICITADA ---
    // 1: Director General, 2: Subdirector General, 5: Gerente de Operaciones, 13: Residentes de Obra, 16: Técnico en Sistemas
    $allowed_departments = [1, 2, 5, 13, 16];
    $user_dept_id = (int)$row['departamento_id'];

    if (!in_array($user_dept_id, $allowed_departments)) {
        json_response(403, 'error', 'Tu perfil no tiene los permisos necesarios para entrar a la App.');
    }
    // ----------------------------------------------

    $stmt_clean = $conn->prepare("DELETE FROM login_intentos WHERE ip = ?");
    $stmt_clean->bind_param("s", $ip_cliente);
    $stmt_clean->execute();

    $token = jwt_sign([
        'uid' => (int)$row['id'],
        'email' => $row['correo_corporativo'],
        'departamento_id' => $row['departamento_id'],
    ], $jwtSecret, 43200);

    json_response(200, 'success', 'Bienvenido', [
        'token' => $token,
        'expires_in' => 43200,
        'usuario' => [
            'id' => (int)$row['id'],
            'nombres' => $row['nombres'],
            'apellidos' => $row['apellidos'],
            'correo' => $row['correo_corporativo'],
            'departamento' => $row['departamento_nombre'] ?? '',
            'departamento_id' => $row['departamento_id'],
            'change_pass' => (bool)$row['password_temporal'],
        ]
    ]);
} catch (InvalidArgumentException $e) {
    json_response(422, 'error', $e->getMessage());
} catch (Throwable $e) {
    error_log('[api_mobile/login] ' . $e->getMessage());
    json_response(500, 'error', 'Error interno: ' . $e->getMessage());
}

function registrarIntentoFallido(mysqli $conn, string $ip, int $limite, int $ventana): void {
    $stmt_check = $conn->prepare("
        SELECT id, intentos FROM login_intentos
        WHERE ip = ? AND ultimo_intento > DATE_SUB(NOW(), INTERVAL ? MINUTE)
        LIMIT 1
    ");
    if (!$stmt_check) return;
    $stmt_check->bind_param("si", $ip, $ventana);
    $stmt_check->execute();
    $res_check = $stmt_check->get_result();

    if ($res_check->num_rows > 0) {
        $registro = $res_check->fetch_assoc();
        $nuevos = (int)$registro['intentos'] + 1;
        $bloqueado = $nuevos >= $limite ? date('Y-m-d H:i:s', strtotime("+{$ventana} minutes")) : null;

        $stmt_upd = $conn->prepare("UPDATE login_intentos SET intentos=?, ultimo_intento=NOW(), bloqueado_hasta=? WHERE id=?");
        if ($stmt_upd) {
            $stmt_upd->bind_param("isi", $nuevos, $bloqueado, $registro['id']);
            $stmt_upd->execute();
            $stmt_upd->close();
        }
    } else {
        $stmt_ins = $conn->prepare("INSERT INTO login_intentos (ip, intentos, ultimo_intento) VALUES (?, 1, NOW())");
        if ($stmt_ins) {
            $stmt_ins->bind_param("s", $ip);
            $stmt_ins->execute();
            $stmt_ins->close();
        }
    }
    $stmt_check->close();
}
