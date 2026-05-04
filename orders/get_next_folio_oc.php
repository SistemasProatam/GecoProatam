<?php
// Incluir el gestor de sesiones UNA sola vez
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

// Verificar sesión y prevenir caching
checkSession();
preventCaching();


require_once __DIR__ . "/../conexion.php";

// Obtener entidad_id
$entidad_id = $_GET['entidad_id'] ?? null;

if (!$entidad_id) {
    echo json_encode(['success' => false, 'message' => 'Entidad no proporcionada']);
    exit;
}

// Definir prefijos por entidad
$prefijos = [
    '1' => 'PRO-OC',  // PROATAM
    '2' => 'LBO-OC',  // LUBYCOMP
    '3' => 'DG-OC',   // DAVID GOMEZ
    '4' => 'ING-OC',  // INGETAM
    '5' => 'GEN-OC',  // GENERAL (para cualquier otra entidad)
];

$prefijo = $prefijos[$entidad_id] ?? 'OCGEN';


// Verificar si existe la tabla ordenes_compra
$check_table = $conn->query("SHOW TABLES LIKE 'ordenes_compra'");

if (!$check_table || $check_table->num_rows == 0) {
    // Si no existe la tabla, generar folio básico
    $next_number = 1;
    $folio = $prefijo . '-' . str_pad($next_number, 4, "0", STR_PAD_LEFT);
    
    echo json_encode([
        'success' => true,
        'folio' => $folio,
        'prefijo' => $prefijo,
        'numero' => $next_number,
        'nota' => 'Tabla ordenes_compra no existe. Folio generado sin contador.'
    ]);
    exit;
}

// Buscar el último folio de esta entidad en este año
$sql = "SELECT folio 
        FROM ordenes_compra 
        WHERE folio LIKE ? 
        ORDER BY id DESC 
        LIMIT 1";

$like_pattern =  $prefijo . '-%';
$stmt = $conn->prepare($sql);

if (!$stmt) {
    // Error en la preparación, generar folio básico
    $next_number = 1;
    $folio = $prefijo . '-' . str_pad($next_number, 4, "0", STR_PAD_LEFT);
    
    echo json_encode([
        'success' => true,
        'folio' => $folio,
        'prefijo' => $prefijo,
        'numero' => $next_number,
        'nota' => 'Error en consulta. Folio generado: ' . $conn->error
    ]);
    exit;
}

$stmt->bind_param("s", $like_pattern);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $last_folio = $result->fetch_assoc()['folio'];
    
    // Extraer el número del folio: PRO01-2025-0001
    $parts = explode("-", $last_folio);
    
    if (count($parts) >= 3) {
        $last_number = intval($parts[2]);
        $next_number = $last_number + 1;
    } else {
        $next_number = 1;
    }
} else {
    // Primera orden de compra de esta entidad en este año
    $next_number = 1;
}

// Generar el nuevo folio
$folio = $prefijo . '-' . str_pad($next_number, 4, "0", STR_PAD_LEFT);

echo json_encode([
    'success' => true,
    'folio' => $folio,
    'prefijo' => $prefijo,
    'numero' => $next_number
]);
?>


