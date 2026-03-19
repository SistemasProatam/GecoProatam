<?php
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

checkSession();
preventCaching();

include(__DIR__ . "/../conexion.php");

// ─── Helpers ────────────────────────────────────────────────────────────────

function sanitize($val) {
    return isset($val) ? trim($val) : null;
}

function nullIfEmpty($val) {
    $v = sanitize($val);
    return ($v === '' || $v === null) ? null : $v;
}

function subirArchivo($inputName, $carpetaDestino, $maxMB = 10) {
    if (!isset($_FILES[$inputName]) || $_FILES[$inputName]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES[$inputName];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        error_log("Error subiendo {$inputName}: " . $file['error']);
        return null;
    }
    if ($maxMB > 0 && $file['size'] > $maxMB * 1024 * 1024) {
        error_log("Archivo {$inputName} supera {$maxMB} MB.");
        return null;
    }
    $ext    = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $nombre = uniqid('', true) . '.' . $ext;
    $dir = __DIR__ . '/../uploads/' . $carpetaDestino . '/';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $dest = $dir . $nombre;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        error_log("No se pudo mover {$inputName} a {$dest}");
        return null;
    }
    return '/uploads/' . $carpetaDestino . '/' . $nombre;
}

function subirArchivoMultiple($inputName, $carpetaDestino, $maxMB = 10) {
    $rutas = [];
    if (!isset($_FILES[$inputName]) || empty($_FILES[$inputName]['name'][0])) return $rutas;
    $total = count($_FILES[$inputName]['name']);
    for ($i = 0; $i < $total; $i++) {
        if ($_FILES[$inputName]['error'][$i] !== UPLOAD_ERR_OK) continue;
        if ($maxMB > 0 && $_FILES[$inputName]['size'][$i] > $maxMB * 1024 * 1024) continue;
        $ext    = strtolower(pathinfo($_FILES[$inputName]['name'][$i], PATHINFO_EXTENSION));
        $nombre = uniqid('', true) . '.' . $ext;
        $dir    = $_SERVER['DOCUMENT_ROOT'] . '/uploads/' . $carpetaDestino . '/';
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        $dest = $dir . $nombre;
        if (move_uploaded_file($_FILES[$inputName]['tmp_name'][$i], $dest)) {
            $rutas[] = '/uploads/' . $carpetaDestino . '/' . $nombre;
        }
    }
    return $rutas;
}

function eliminarArchivoFisico($ruta) {
    if (!$ruta) return;
    $path = $_SERVER['DOCUMENT_ROOT'] . $ruta;
    if (file_exists($path)) unlink($path);
}

// ─── Validar ID ──────────────────────────────────────────────────────────────

$activo_id = (int)($_POST['activo_id'] ?? 0);
if (!$activo_id) {
    header("Location: list_activos.php?error=id_invalido");
    exit;
}

// ─── Verificar que el activo existe ─────────────────────────────────────────

$stmt_check = $conn->prepare("SELECT id, tipo_id, img_foto_principal FROM activos WHERE id = ?");
$stmt_check->bind_param("i", $activo_id);
$stmt_check->execute();
$activo_actual = $stmt_check->get_result()->fetch_assoc();

if (!$activo_actual) {
    header("Location: list_activos.php?error=no_encontrado");
    exit;
}

// ─── Recoger POST ────────────────────────────────────────────────────────────

$tipo_id         = (int)($_POST['tipo_id'] ?? $activo_actual['tipo_id']);
$nombre          = sanitize($_POST['nombre'] ?? '');
$condicion       = nullIfEmpty($_POST['condicion']       ?? '');
$responsable_id  = nullIfEmpty($_POST['responsable_id']  ?? '');
$departamento_id = nullIfEmpty($_POST['departamento_id'] ?? '');
$fecha_adq       = nullIfEmpty($_POST['fecha_adquisicion'] ?? '');
$valor_factura   = nullIfEmpty($_POST['valor_factura']   ?? '');
$vida_util       = nullIfEmpty($_POST['vida_util']       ?? '');
$ubicacion       = nullIfEmpty($_POST['ubicacion']       ?? '');
$estatus         = sanitize($_POST['estatus']            ?? 'activo');
$notas           = nullIfEmpty($_POST['notas']           ?? '');

if ($nombre === '') {
    header("Location: edit_activo.php?id={$activo_id}&error=campos_requeridos");
    exit;
}

// ─── Foto principal ──────────────────────────────────────────────────────────

$img_principal = $activo_actual['img_foto_principal'];

// Eliminar si se marcó
if (!empty($_POST['eliminar_foto_principal'])) {
    eliminarArchivoFisico($img_principal);
    $img_principal = null;
}

// Reemplazar si se subió una nueva
$nueva_foto = subirArchivo('img_foto_principal', 'fotos', 10);
if ($nueva_foto) {
    eliminarArchivoFisico($img_principal); // borra la anterior si existía
    $img_principal = $nueva_foto;
}

// ─── Actualizar activo principal ─────────────────────────────────────────────

$sql = "UPDATE activos SET
          tipo_id=?, nombre=?, condicion=?, responsable_id=?, departamento_id=?,
          fecha_adquisicion=?, valor_factura=?, vida_util=?, ubicacion=?,
          estatus=?, notas=?, img_foto_principal=?
        WHERE id=?";

$stmt = $conn->prepare($sql);
$stmt->bind_param(
    "isssssdissssi",
    $tipo_id, $nombre, $condicion, $responsable_id, $departamento_id,
    $fecha_adq, $valor_factura, $vida_util, $ubicacion,
    $estatus, $notas, $img_principal, $activo_id
);

if (!$stmt->execute()) {
    error_log("Error actualizando activo: " . $stmt->error);
    header("Location: edit_activo.php?id={$activo_id}&error=db");
    exit;
}

// ─── Tipo ────────────────────────────────────────────────────────────────────

$stmt_tipo = $conn->prepare("SELECT nombre FROM activo_tipos WHERE id = ?");
$stmt_tipo->bind_param("i", $tipo_id);
$stmt_tipo->execute();
$tipo_row   = $stmt_tipo->get_result()->fetch_assoc();
$tipo_norm  = iconv('UTF-8', 'ASCII//TRANSLIT', strtolower($tipo_row['nombre'] ?? ''));

// ─── Función: upsert detalle ─────────────────────────────────────────────────

function upsertDetalle($conn, $tabla, $activo_id, $campos_vals, $tipos) {
    $stmt_chk = $conn->prepare("SELECT id FROM {$tabla} WHERE activo_id = ?");
    $stmt_chk->bind_param("i", $activo_id);
    $stmt_chk->execute();
    $existe = $stmt_chk->get_result()->fetch_assoc();

    if ($existe) {
        $sets   = implode(', ', array_map(fn($c) => "{$c}=?", array_keys($campos_vals)));
        $vals   = array_values($campos_vals);
        $vals[] = $activo_id;
        $t      = $tipos . 'i';
        $stmt   = $conn->prepare("UPDATE {$tabla} SET {$sets} WHERE activo_id=?");
        $stmt->bind_param($t, ...$vals);
    } else {
        $cols   = 'activo_id, ' . implode(', ', array_keys($campos_vals));
        $marks  = '?, ' . implode(', ', array_fill(0, count($campos_vals), '?'));
        $vals   = array_merge([$activo_id], array_values($campos_vals));
        $t      = 'i' . $tipos;
        $stmt   = $conn->prepare("INSERT INTO {$tabla} ({$cols}) VALUES ({$marks})");
        $stmt->bind_param($t, ...$vals);
    }
    $stmt->execute();
}

// ─── Vehículo ────────────────────────────────────────────────────────────────

if (str_contains($tipo_norm, 'vehiculo')) {
    upsertDetalle($conn, 'vehiculos_detalle', $activo_id, [
        'marca'                  => nullIfEmpty($_POST['v_marca']                     ?? ''),
        'modelo'                 => nullIfEmpty($_POST['v_modelo']                    ?? ''),
        'anio'                   => nullIfEmpty($_POST['v_anio']                      ?? ''),
        'color'                  => nullIfEmpty($_POST['v_color']                     ?? ''),
        'placa'                  => nullIfEmpty($_POST['v_placa']                     ?? ''),
        'vin'                    => nullIfEmpty($_POST['v_vin']                       ?? ''),
        'numero_motor'           => nullIfEmpty($_POST['v_numero_motor']              ?? ''),
        'entidad_federativa'     => nullIfEmpty($_POST['v_entidad_federativa']        ?? ''),
        'numero_pedimento'       => nullIfEmpty($_POST['v_numero_pedimento']          ?? ''),
        'origen'                 => nullIfEmpty($_POST['v_origen']                    ?? ''),
        'gravamen'               => nullIfEmpty($_POST['v_gravamen']                  ?? ''),
        'nombre_propietario'     => nullIfEmpty($_POST['v_nombre_propietario']        ?? ''),
        'nombre_aseguradora_mx'  => nullIfEmpty($_POST['v_nombre_aseguradora_mx']     ?? ''),
        'telefono_aseguradora_mx'=> nullIfEmpty($_POST['v_telefono_aseguradora_mx']   ?? ''),
        'fecha_venc_seguro_mx'   => nullIfEmpty($_POST['v_fecha_vencimiento_seguro_mx'] ?? ''),
        'nombre_aseguradora_usa' => nullIfEmpty($_POST['v_nombre_aseguradora_usa']    ?? ''),
        'telefono_aseguradora_usa'=> nullIfEmpty($_POST['v_telefono_aseguradora_usa'] ?? ''),
        'fecha_venc_seguro_usa'  => nullIfEmpty($_POST['v_fecha_vencimiento_seguro_usa'] ?? ''),
    ], 'ssisssssssssssssss');
}

// ─── Maquinaria ──────────────────────────────────────────────────────────────

if (str_contains($tipo_norm, 'maquinaria')) {
    // Foto motor
    $stmt_maq = $conn->prepare("SELECT foto_motor FROM maquinaria_detalle WHERE activo_id = ?");
    $stmt_maq->bind_param("i", $activo_id);
    $stmt_maq->execute();
    $maq_row    = $stmt_maq->get_result()->fetch_assoc();
    $foto_motor = $maq_row['foto_motor'] ?? null;

    if (!empty($_POST['eliminar_foto_motor'])) {
        eliminarArchivoFisico($foto_motor);
        $foto_motor = null;
    }
    $nueva_motor = subirArchivo('m_foto_motor', 'maquinaria', 10);
    if ($nueva_motor) {
        eliminarArchivoFisico($foto_motor);
        $foto_motor = $nueva_motor;
    }

    upsertDetalle($conn, 'maquinaria_detalle', $activo_id, [
        'marca'        => nullIfEmpty($_POST['m_marca']        ?? ''),
        'modelo'       => nullIfEmpty($_POST['m_modelo']       ?? ''),
        'numero_serie' => nullIfEmpty($_POST['m_numero_serie'] ?? ''),
        'kilometraje'  => nullIfEmpty($_POST['m_kilometraje']  ?? ''),
        'foto_motor'   => $foto_motor,
    ], 'sssis');
}

// ─── Mobiliario ──────────────────────────────────────────────────────────────

if (str_contains($tipo_norm, 'mobiliario')) {
    upsertDetalle($conn, 'mobiliario_detalle', $activo_id, [
        'marca'             => nullIfEmpty($_POST['mob_marca']             ?? ''),
        'modelo'            => nullIfEmpty($_POST['mob_modelo']            ?? ''),
        'numero_items'      => (int)nullIfEmpty($_POST['mob_numero_items'] ?? 1),
        'medida_aprox'      => nullIfEmpty($_POST['mob_medida_aprox']      ?? ''),
        'edificio'          => nullIfEmpty($_POST['mob_edificio']          ?? ''),
        'area_departamento' => nullIfEmpty($_POST['mob_area_departamento'] ?? ''),
        'direccion'         => nullIfEmpty($_POST['mob_direccion']         ?? ''),
        'descripcion'       => nullIfEmpty($_POST['mob_descripcion']       ?? ''),
    ], 'ssisssss');
}

// ─── Inmuebles ───────────────────────────────────────────────────────────────

if (str_contains($tipo_norm, 'inmueble')) {
    upsertDetalle($conn, 'inmuebles_detalle', $activo_id, [
        'tipo_inmueble'             => nullIfEmpty($_POST['inm_tipo']                      ?? ''),
        'tipo_posesion'             => nullIfEmpty($_POST['inm_tipo_posesion']             ?? ''),
        'uso'                       => nullIfEmpty($_POST['inm_uso']                       ?? ''),
        'direccion'                 => nullIfEmpty($_POST['inm_direccion']                 ?? ''),
        'coordenadas'               => nullIfEmpty($_POST['inm_coordenadas']               ?? ''),
        'superficie_terreno'        => nullIfEmpty($_POST['inm_superficie_terreno']        ?? ''),
        'superficie_construida'     => nullIfEmpty($_POST['inm_superficie_construida']     ?? ''),
        'niveles'                   => nullIfEmpty($_POST['inm_niveles']                   ?? ''),
        'valor_terreno'             => nullIfEmpty($_POST['inm_valor_terreno']             ?? ''),
        'folio_rpp'                 => nullIfEmpty($_POST['inm_folio_rpp']                 ?? ''),
        'predial'                   => nullIfEmpty($_POST['inm_predial']                   ?? ''),
        'estatus_legal'             => nullIfEmpty($_POST['inm_estatus_legal']             ?? ''),
        'responsable_administrativo'=> nullIfEmpty($_POST['inm_responsable_administrativo'] ?? ''),
    ], 'sssssddiissss');
}

// ─── Herramientas ─────────────────────────────────────────────────────────────

if (str_contains($tipo_norm, 'herramienta')) {
    upsertDetalle($conn, 'herramientas_detalle', $activo_id, [
        'marca'            => nullIfEmpty($_POST['h_marca']           ?? ''),
        'modelo'           => nullIfEmpty($_POST['h_modelo']          ?? ''),
        'numero_serie'     => nullIfEmpty($_POST['h_numero_serie']    ?? ''),
        'asignacion'       => nullIfEmpty($_POST['h_asignacion']      ?? ''),
        'ubicacion_fisica' => nullIfEmpty($_POST['h_ubicacion_fisica'] ?? ''),
        'descripcion'      => nullIfEmpty($_POST['h_descripcion']     ?? ''),
    ], 'ssssss');
}

// ─── TICs ─────────────────────────────────────────────────────────────────────

if (str_contains($tipo_norm, 'tic')) {
    upsertDetalle($conn, 'tics_detalle', $activo_id, [
        'marca'               => nullIfEmpty($_POST['t_marca']                ?? ''),
        'modelo'              => nullIfEmpty($_POST['t_modelo']               ?? ''),
        'numero_serie'        => nullIfEmpty($_POST['t_numero_serie']         ?? ''),
        'sistema_operativo'   => nullIfEmpty($_POST['t_sistema_operativo']    ?? ''),
        'procesador'          => nullIfEmpty($_POST['t_procesador']           ?? ''),
        'ram'                 => nullIfEmpty($_POST['t_ram']                  ?? ''),
        'almacenamiento'      => nullIfEmpty($_POST['t_almacenamiento']       ?? ''),
        'office'              => nullIfEmpty($_POST['t_office']               ?? ''),
        'correo'              => nullIfEmpty($_POST['t_correo']               ?? ''),
        'ubicacion_fisica'    => nullIfEmpty($_POST['t_ubicacion_fisica']     ?? ''),
        'programas_instalados'=> nullIfEmpty($_POST['t_programas_instalados'] ?? ''),
        'complementos'        => nullIfEmpty($_POST['t_complementos']         ?? ''),
    ], 'ssssssssssss');
}

// ─── Eliminar documentos marcados ────────────────────────────────────────────

if (!empty($_POST['eliminar_doc']) && is_array($_POST['eliminar_doc'])) {
    foreach ($_POST['eliminar_doc'] as $doc_id) {
        $doc_id = (int)$doc_id;
        $stmt_d = $conn->prepare("SELECT ruta_archivo FROM activos_documentos WHERE id=? AND activo_id=?");
        $stmt_d->bind_param("ii", $doc_id, $activo_id);
        $stmt_d->execute();
        $doc_row = $stmt_d->get_result()->fetch_assoc();
        if ($doc_row) {
            eliminarArchivoFisico($doc_row['ruta_archivo']);
            $stmt_del = $conn->prepare("DELETE FROM activos_documentos WHERE id=?");
            $stmt_del->bind_param("i", $doc_id);
            $stmt_del->execute();
        }
    }
}

// ─── Nuevos documentos ───────────────────────────────────────────────────────

$docs = [
    'doc_factura'              => ['carpeta'=>'documentos','maxMB'=>10,   'tipo'=>'factura'],
    'doc_pedimento'            => ['carpeta'=>'documentos','maxMB'=>10,   'tipo'=>'pedimento'],
    'doc_poliza_seguro'        => ['carpeta'=>'documentos','maxMB'=>10,   'tipo'=>'poliza_seguro_mx'],
    'doc_poliza_seguro_usa'    => ['carpeta'=>'documentos','maxMB'=>10,   'tipo'=>'poliza_seguro_usa'],
    'doc_manual'               => ['carpeta'=>'documentos','maxMB'=>10,   'tipo'=>'manual_usuario'],
    'doc_manual_mantenimiento' => ['carpeta'=>'documentos','maxMB'=>10,   'tipo'=>'manual_mantenimiento'],
    'doc_catalogo_refacciones' => ['carpeta'=>'documentos','maxMB'=>1024, 'tipo'=>'catalogo_refacciones'],
    'doc_contrato'             => ['carpeta'=>'documentos','maxMB'=>10,   'tipo'=>'contrato'],
];

$stmt_doc = $conn->prepare(
    "INSERT INTO activos_documentos (activo_id, tipo_documento, ruta_archivo, nombre_original, fecha_subida)
     VALUES (?, ?, ?, ?, NOW())"
);

foreach ($docs as $inputName => $cfg) {
    if (!isset($_FILES[$inputName]) || $_FILES[$inputName]['error'] === UPLOAD_ERR_NO_FILE) continue;
    $ruta = subirArchivo($inputName, $cfg['carpeta'], $cfg['maxMB']);
    if ($ruta) {
        $nom_orig = $_FILES[$inputName]['name'];
        $stmt_doc->bind_param("isss", $activo_id, $cfg['tipo'], $ruta, $nom_orig);
        $stmt_doc->execute();
    }
}

// ─── Eliminar imágenes marcadas ──────────────────────────────────────────────

if (!empty($_POST['eliminar_img']) && is_array($_POST['eliminar_img'])) {
    foreach ($_POST['eliminar_img'] as $img_id) {
        $img_id  = (int)$img_id;
        $stmt_i  = $conn->prepare("SELECT ruta_archivo FROM activos_imagenes WHERE id=? AND activo_id=?");
        $stmt_i->bind_param("ii", $img_id, $activo_id);
        $stmt_i->execute();
        $img_row = $stmt_i->get_result()->fetch_assoc();
        if ($img_row) {
            eliminarArchivoFisico($img_row['ruta_archivo']);
            $stmt_idel = $conn->prepare("DELETE FROM activos_imagenes WHERE id=?");
            $stmt_idel->bind_param("i", $img_id);
            $stmt_idel->execute();
        }
    }
}

// ─── Nuevas imágenes ─────────────────────────────────────────────────────────

$stmt_img = $conn->prepare(
    "INSERT INTO activos_imagenes (activo_id, tipo_imagen, ruta_archivo, fecha_subida)
     VALUES (?, ?, ?, NOW())"
);

// Fotos generales (múltiple)
$fotos_gen = subirArchivoMultiple('img_foto_general', 'fotos', 10);
foreach ($fotos_gen as $ruta) {
    $tipo = 'foto_general';
    $stmt_img->bind_param("iss", $activo_id, $tipo, $ruta);
    $stmt_img->execute();
}

// Foto placa
$foto_placa = subirArchivo('img_foto_placa', 'fotos', 10);
if ($foto_placa) {
    $tipo = 'foto_placa';
    $stmt_img->bind_param("iss", $activo_id, $tipo, $foto_placa);
    $stmt_img->execute();
}

// Foto serie
$foto_serie = subirArchivo('img_foto_numero_serie', 'fotos', 10);
if ($foto_serie) {
    $tipo = 'foto_numero_serie';
    $stmt_img->bind_param("iss", $activo_id, $tipo, $foto_serie);
    $stmt_img->execute();
}

// ─── Documentos extra (fiscal + extra combinados desde JS) ───────────────────

if (isset($_FILES['documentos']) && !empty($_FILES['documentos']['name'][0])) {
    $total = count($_FILES['documentos']['name']);
    $tipos_post = $_POST['documentos_tipo'] ?? [];
    $tipos_validos = ['expediente_predial', 'extra', 'factura', 'pedimento',
                  'poliza_seguro_mx', 'poliza_seguro_usa', 'manual_usuario',
                  'manual_mantenimiento', 'catalogo_refacciones', 'contrato'];
    $stmt_docx = $conn->prepare(
        "INSERT INTO activos_documentos (activo_id, tipo_documento, ruta_archivo, nombre_original, fecha_subida)
         VALUES (?, ?, ?, ?, NOW())"
    );
    for ($i = 0; $i < $total; $i++) {
        if ($_FILES['documentos']['error'][$i] !== UPLOAD_ERR_OK) continue;
        if ($_FILES['documentos']['size'][$i] > 10 * 1024 * 1024) continue;
        $ext  = strtolower(pathinfo($_FILES['documentos']['name'][$i], PATHINFO_EXTENSION));
        $nom  = uniqid('', true) . '.' . $ext;
        $dir  = $_SERVER['DOCUMENT_ROOT'] . '/uploads/documentos/';
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        $dest = $dir . $nom;
        if (move_uploaded_file($_FILES['documentos']['tmp_name'][$i], $dest)) {
            $ruta_doc = '/uploads/documentos/' . $nom;
            $nom_orig = $_FILES['documentos']['name'][$i];
            $tipo_doc = isset($tipos_post[$i]) && in_array($tipos_post[$i], $tipos_validos)
            ? $tipos_post[$i] : 'extra';
            $stmt_docx->bind_param("isss", $activo_id, $tipo_doc, $ruta_doc, $nom_orig);
            $stmt_docx->execute();
        }
    }
}

// ─── Redirigir ───────────────────────────────────────────────────────────────

header("Location: details_activo.php?id={$activo_id}&success=updated");
exit;