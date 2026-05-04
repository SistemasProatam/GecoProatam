<?php
// update_cotizacion.php — Actualiza una cotización existente (con cambio de folio si cambia entidad)
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";
checkSession();

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

function pp(string $k): string {
    return isset($_POST[$k]) ? trim(strip_tags($_POST[$k])) : '';
}

$id            = (int)pp('id');
$entidadNombre = pp('entidad')   ?: 'PROATAM';
$atencion      = pp('atencion');
$compania      = pp('compania');
$fecha         = pp('fecha_emision');
$lugar         = pp('lugar');
$tiempo        = pp('tiempo_ejecucion');
$formaPago     = pp('forma_pago');
$vigencia      = pp('vigencia');
$moneda        = pp('moneda')    ?: 'MXN';
$notas         = pp('notas');
$emisorNombre  = pp('emisor_nombre');
$emisorDepto   = pp('emisor_depto');

if (!$id || !$atencion) {
    echo json_encode(['status' => 'error', 'message' => 'Datos incompletos (id y atencion requeridos)']);
    exit;
}

require_once __DIR__ . "/../conexion.php";

// Obtener la cotización actual para verificar si cambió la entidad
$stmtOld = $conn->prepare("SELECT entidades_id, folio FROM cotizaciones WHERE id = ?");
$stmtOld->bind_param("i", $id);
$stmtOld->execute();
$oldData = $stmtOld->get_result()->fetch_assoc();
$oldEntidadId = $oldData['entidades_id'] ?? null;
$oldFolio = $oldData['folio'] ?? '';
$stmtOld->close();

// Obtener entidades_id por nombre
$entidades_id = 1;
$stmtEnt = $conn->prepare("SELECT id FROM entidades WHERE nombre = ? LIMIT 1");
$stmtEnt->bind_param("s", $entidadNombre);
$stmtEnt->execute();
if ($rowEnt = $stmtEnt->get_result()->fetch_assoc()) {
    $entidades_id = (int)$rowEnt['id'];
}
$stmtEnt->close();

// Función para generar nuevo folio (INCREMENTA el contador)
function generarNuevoFolio(string $entidad): string {
    $prefijos = [
        'PROATAM'     => 'CO-PRO',
        'INGETAM'     => 'CO-ING',
        'LUBYCOMP'    => 'CO-LUB',
        'DAVID GOMEZ' => 'CO-DAG',
    ];
    $prefijo   = $prefijos[$entidad] ?? 'CO-PRO';
    $dir       = __DIR__ . '/data';
    $folioFile = "$dir/folio_{$entidad}.txt";
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    if (!file_exists($folioFile)) file_put_contents($folioFile, '1');
    
    // Leer el número actual y luego incrementar para la siguiente vez
    $num = (int)file_get_contents($folioFile);
    $nuevoNum = $num + 1;
    file_put_contents($folioFile, (string)$nuevoNum);
    
    return $prefijo . '-' . str_pad($num, 4, '0', STR_PAD_LEFT);
}

$nuevoFolio = null;
$folioActualizado = false;

// Si cambió la entidad, generar nuevo folio
if ($oldEntidadId != $entidades_id) {
    $nuevoFolio = generarNuevoFolio($entidadNombre);
    $folioActualizado = true;
}

// Preparar la consulta UPDATE
if ($folioActualizado) {
    $stmt = $conn->prepare("
        UPDATE cotizaciones SET
            entidades_id      = ?,
            folio             = ?,
            atencion          = ?,
            compania          = ?,
            fecha_emision     = ?,
            lugar             = ?,
            tiempo_ejecucion  = ?,
            forma_pago        = ?,
            vigencia          = ?,
            moneda            = ?,
            notas             = ?,
            emisor_nombre     = ?,
            emisor_depto      = ?
        WHERE id = ?
    ");
    $stmt->bind_param(
        'issssssssssssi',
        $entidades_id, $nuevoFolio, $atencion, $compania, $fecha, $lugar,
        $tiempo, $formaPago, $vigencia, $moneda, $notas,
        $emisorNombre, $emisorDepto,
        $id
    );
} else {
    $stmt = $conn->prepare("
        UPDATE cotizaciones SET
            entidades_id      = ?,
            atencion          = ?,
            compania          = ?,
            fecha_emision     = ?,
            lugar             = ?,
            tiempo_ejecucion  = ?,
            forma_pago        = ?,
            vigencia          = ?,
            moneda            = ?,
            notas             = ?,
            emisor_nombre     = ?,
            emisor_depto      = ?
        WHERE id = ?
    ");
    $stmt->bind_param(
        'isssssssssssi',
        $entidades_id, $atencion, $compania, $fecha, $lugar,
        $tiempo, $formaPago, $vigencia, $moneda, $notas,
        $emisorNombre, $emisorDepto,
        $id
    );
}

if ($stmt->execute()) {
    $response = ['status' => 'success', 'message' => 'Cotización actualizada correctamente.'];
    if ($folioActualizado) {
        $response['folio_actualizado'] = true;
        $response['nuevo_folio'] = $nuevoFolio;
        $response['message'] = 'Cotización actualizada. El folio cambió a: ' . $nuevoFolio;
    }
    echo json_encode($response);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Error al actualizar: ' . $conn->error]);
}

$stmt->close();
$conn->close();


