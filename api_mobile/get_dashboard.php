<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');


include __DIR__ . '/../conexion.php';
require_once __DIR__ . '/_core/response.php';
require_once __DIR__ . '/_core/auth.php';

$auth = require_auth();
$userId    = (int)$auth['uid'];
$userDept  = (int)$auth['departamento_id'];

try {
    // 1. Datos del usuario + nombre de departamento
    $stmtUser = $conn->prepare("
        SELECT u.nombres, u.apellidos, u.correo_corporativo, d.nombre AS departamento
        FROM usuarios u
        LEFT JOIN departamentos d ON u.departamento_id = d.id
        WHERE u.id = ?
    ");
    $stmtUser->bind_param("i", $userId);
    $stmtUser->execute();
    $userData = $stmtUser->get_result()->fetch_assoc();

    // 2. Requisiciones — solo las del usuario si no es admin
    if (in_array($userDept, [1, 2, 5, 16])) {
        // Admins ven todo
        $resReq = $conn->query("SELECT COUNT(*) as total, 
                                SUM(CASE WHEN estado = 'pendiente' OR estado = 'espera' THEN 1 ELSE 0 END) as pendientes 
                                FROM requisiciones");
    } else {
        // Residentes: solo sus propias requisiciones
        $stmtReq = $conn->prepare("SELECT COUNT(*) as total, 
                                   SUM(CASE WHEN estado = 'pendiente' OR estado = 'espera' THEN 1 ELSE 0 END) as pendientes 
                                   FROM requisiciones WHERE solicitante_id = ?");
        $stmtReq->bind_param("i", $userId);
        $stmtReq->execute();
        $resReq = $stmtReq->get_result();
    }
    $reqStats = $resReq->fetch_assoc();

    // 3. Obras — solo las asignadas al usuario si es residente
    if ($userDept === 13) {
        // Residente: obras donde está asignado
        $stmtObras = $conn->prepare("SELECT COUNT(*) as total FROM obras 
                                     WHERE residente_id_1 = ? OR residente_id_2 = ? OR residente_id_3 = ?");
        $stmtObras->bind_param("iii", $userId, $userId, $userId);
        $stmtObras->execute();
        $resObras = $stmtObras->get_result();
    } else {
        $resObras = $conn->query("SELECT COUNT(*) as total FROM obras");
    }
    $obrasStats = $resObras->fetch_assoc();

    json_response(200, 'success', 'Dashboard data', [
        'usuario' => $userData,
        'stats' => [
            'requisiciones_total'     => (int)($reqStats['total'] ?? 0),
            'requisiciones_pendientes'=> (int)($reqStats['pendientes'] ?? 0),
            'obras_total'             => (int)($obrasStats['total'] ?? 0)
        ]
    ]);
} catch (Throwable $e) {
    error_log('[api_mobile/get_dashboard] ' . $e->getMessage());
    json_response(500, 'error', 'Error al cargar dashboard');
}
