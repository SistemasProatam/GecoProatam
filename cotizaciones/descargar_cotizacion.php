<?php
// descargar_cotizacion.php — Lee cotización de BD y genera el PDF
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";
checkSession();
preventCaching();

$dep_id_sesion  = $_SESSION['departamento_id'] ?? null;
$es_super_admin = ($_SESSION['departamento']   ?? '') === 'SUPER_ADMIN';
if (!$es_super_admin && !in_array($dep_id_sesion, [1, 2, 10, 16])) {
    header("Location: " . BASE_URL . "/index.php?acceso=denegado"); exit;
}

require_once __DIR__ . "/../conexion.php";

$id = (int)($_GET['id'] ?? 0);
if (!$id) { http_response_code(400); die('ID inválido'); }

$stmt = $conn->prepare("
    SELECT c.*, e.nombre AS entidad_nombre
    FROM cotizaciones c
    LEFT JOIN entidades e ON c.entidades_id = e.id
    WHERE c.id = ? AND c.activo = 1
    LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$cot = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$cot) { http_response_code(404); die('Cotización no encontrada'); }

// Reconstruir $_POST desde la BD para pasar a generar_pdf.php
$_POST['folio']         = $cot['folio'];
$_POST['fecha']         = $cot['fecha_emision'];
$_POST['atencion']      = $cot['atencion'];
$_POST['compania']      = $cot['compania'];
$_POST['lugar']         = $cot['lugar'];
$_POST['tiempo']        = $cot['tiempo_ejecucion'];
$_POST['forma_pago']    = $cot['forma_pago'];
$_POST['vigencia']      = $cot['vigencia'];
$_POST['moneda']        = $cot['moneda'];
$_POST['tasa_iva']      = $cot['tasa_iva'];
$_POST['notas']         = $cot['notas'];
$_POST['emisor_nombre'] = $cot['emisor_nombre'];
$_POST['emisor_depto']  = $cot['emisor_depto'];
$_POST['entidad']       = $cot['entidad_nombre'] ?? 'PROATAM';

// Alcances desde JSON
$alcDatos = json_decode($cot['alcances'] ?? '[]', true);
if (is_array($alcDatos)) {
    $_POST['alcances']       = $alcDatos['seleccionados'] ?? [];
    $_POST['alcances_extra'] = $alcDatos['adicionales']   ?? '';
} else {
    $_POST['alcances']       = [];
    $_POST['alcances_extra'] = '';
}

// Conceptos desde JSON — clave puede ser 'descripcion' o 'desc'
$conceptosRaw = json_decode($cot['conceptos'] ?? '[]', true);
if (is_array($conceptosRaw) && !empty($conceptosRaw)) {
    $_POST['desc']     = array_map(fn($c) => $c['descripcion'] ?? $c['desc'] ?? '', $conceptosRaw);
    $_POST['unidad']   = array_column($conceptosRaw, 'unidad');
    $_POST['cantidad'] = array_column($conceptosRaw, 'cantidad');
    $_POST['precio']   = array_column($conceptosRaw, 'precio');
} else {
    // Fallback si no hay conceptos guardados
    $_POST['desc']     = ['(Ver cotización original)'];
    $_POST['unidad']   = [''];
    $_POST['cantidad'] = [1];
    $_POST['precio']   = [(float)($cot['subtotal'] ?? 0)];
}

if (ob_get_length()) ob_end_clean();

// generar_pdf.php lee $_POST y $_GET['inline']
require __DIR__ . '/generar_pdf.php';


