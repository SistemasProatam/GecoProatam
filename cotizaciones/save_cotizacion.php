<?php
// save_cotizacion.php — Guarda cotización en BD, devuelve JSON con id
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";
checkSession();

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

include_once __DIR__ . '/../conexion.php';

// Helpers de limpieza
function sPost(string $k): string { return trim($_POST[$k] ?? ''); }
function fPost(string $k): float  { return (float)str_replace(['$', ',', ' '], '', $_POST[$k] ?? '0'); }

// Datos principales
$folio        = sPost('folio');
$fecha        = sPost('fecha')   ?: date('Y-m-d');
$atencion     = sPost('atencion');
$compania     = sPost('compania');
$lugar        = sPost('lugar');
$entNombre    = sPost('entidad') ?: 'PROATAM';
$subtotal     = fPost('subtotal');
$iva          = fPost('iva');
$total        = fPost('total');
$tasaIva      = in_array((int)sPost('tasa_iva'), [0, 8, 16]) ? (int)sPost('tasa_iva') : 16;
$tiempo       = sPost('tiempo');
$formaPago    = sPost('forma_pago');
$vigencia     = sPost('vigencia') ?: '30 días naturales';
$moneda       = sPost('moneda')  ?: 'MXN';
$notas        = sPost('notas');
$emisorNombre = sPost('emisor_nombre');
$emisorDepto  = sPost('emisor_depto');

// Obtener ID del usuario que crea la cotización (ajusta según tu variable de sesión)
$creado_por = $_SESSION['usuario_id'] ?? $_SESSION['id'] ?? $_SESSION['user_id'] ?? null;

// Validar obligatorio
if (!$atencion) {
    echo json_encode(['status' => 'error', 'message' => 'El campo "Atención a" es obligatorio.']);
    exit;
}
if (!$folio) {
    echo json_encode(['status' => 'error', 'message' => 'No se recibió el folio.']);
    exit;
}

// Validar que haya al menos un concepto
$descs = $_POST['desc'] ?? [];
$tieneConcepto = false;
foreach ($descs as $d) { 
    if (trim($d) !== '') { 
        $tieneConcepto = true; 
        break; 
    } 
}
if (!$tieneConcepto) {
    echo json_encode(['status' => 'error', 'message' => 'Debes agregar al menos un concepto.']);
    exit;
}

// Obtener entidades_id por nombre
$entidades_id = 1;
$stmtEnt = $conn->prepare("SELECT id FROM entidades WHERE nombre = ? LIMIT 1");
$stmtEnt->bind_param("s", $entNombre);
$stmtEnt->execute();
if ($rowEnt = $stmtEnt->get_result()->fetch_assoc()) {
    $entidades_id = (int)$rowEnt['id'];
}
$stmtEnt->close();

// Separar alcances en dos campos
$alcances_seleccionados = $_POST['alcances'] ?? [];
$alcances_extra_text = trim($_POST['alcances_extra'] ?? '');

// Alcances → JSON (solo los seleccionados)
$alcances_json = json_encode([
    'seleccionados' => array_values($alcances_seleccionados)
], JSON_UNESCAPED_UNICODE);

// Conceptos → JSON (con clave 'descripcion' para compatibilidad con descargar)
$unidades = $_POST['unidad']   ?? [];
$cants    = $_POST['cantidad'] ?? [];
$precios  = $_POST['precio']   ?? [];
$conceptos = [];
foreach ($descs as $i => $desc) {
    if (empty(trim($desc))) continue;
    $conceptos[] = [
        'descripcion' => trim($desc),
        'unidad'      => trim($unidades[$i] ?? ''),
        'cantidad'    => (float)($cants[$i]   ?? 0),
        'precio'      => (float)($precios[$i] ?? 0),
    ];
}
$conceptos_json = json_encode($conceptos, JSON_UNESCAPED_UNICODE);

// Estado por defecto
$estado = 'activa';
$activo = 1;

// Verificar folio duplicado
$stmtCheck = $conn->prepare("SELECT id FROM cotizaciones WHERE folio = ? LIMIT 1");
$stmtCheck->bind_param("s", $folio);
$stmtCheck->execute();
if ($stmtCheck->get_result()->num_rows > 0) {
    // Folio ya existe — regenerar con timestamp para evitar colisión
    $folio = $folio . '-' . time();
}
$stmtCheck->close();

// INSERT completo con todos los campos de la tabla
$sql = "INSERT INTO cotizaciones (
    folio, fecha_emision, atencion, compania, lugar, entidades_id,
    subtotal, iva, total, tasa_iva,
    tiempo_ejecucion, forma_pago, vigencia, moneda, notas,
    emisor_nombre, emisor_depto, alcances, conceptos, alcances_extra,
    estado, activo, creado_por
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Error preparando consulta: ' . $conn->error]);
    exit;
}

// Tipos: s s s s s i d d d i s s s s s s s s s s s i i
//       1 2 3 4 5 6 7 8 9 10 11 12 13 14 15 16 17 18 19 20 21 22 23
$stmt->bind_param(
    'sssssidddisssssssssssii',  // 23 caracteres para 23 variables
    $folio,                      // 1. s
    $fecha,                      // 2. s
    $atencion,                   // 3. s
    $compania,                   // 4. s
    $lugar,                      // 5. s
    $entidades_id,               // 6. i
    $subtotal,                   // 7. d
    $iva,                        // 8. d
    $total,                      // 9. d
    $tasaIva,                    // 10. i
    $tiempo,                     // 11. s
    $formaPago,                  // 12. s
    $vigencia,                   // 13. s
    $moneda,                     // 14. s
    $notas,                      // 15. s
    $emisorNombre,               // 16. s
    $emisorDepto,                // 17. s
    $alcances_json,              // 18. s
    $conceptos_json,             // 19. s
    $alcances_extra_text,        // 20. s
    $estado,                     // 21. s
    $activo,                     // 22. i
    $creado_por                  // 23. i
);

if ($stmt->execute()) {
    $newId = (int)$stmt->insert_id;
    $stmt->close();

    // Actualizar folio consecutivo por entidad
    $folioFile = __DIR__ . "/data/folio_{$entNombre}.txt";
    $folioDir = dirname($folioFile);
    if (!is_dir($folioDir)) {
        mkdir($folioDir, 0755, true);
    }
    
    if (file_exists($folioFile)) {
        $currentNum = (int)file_get_contents($folioFile);
        file_put_contents($folioFile, $currentNum + 1);
    } else {
        file_put_contents($folioFile, 2);
    }

    echo json_encode([
        'status'  => 'success',
        'message' => 'Cotización guardada correctamente.',
        'id'      => $newId,
        'folio'   => $folio,
    ]);
} else {
    $err = $conn->error;
    $stmt->close();
    echo json_encode(['status' => 'error', 'message' => 'Error al guardar: ' . $err]);
}

$conn->close();
?>