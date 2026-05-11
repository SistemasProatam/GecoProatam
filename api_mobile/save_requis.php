<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

include __DIR__ . '/../conexion.php';
// require_once __DIR__ . '/../EmailHandler.php';

require_once __DIR__ . '/_core/response.php';
require_once __DIR__ . '/_core/request.php';
require_once __DIR__ . '/_core/auth.php';

require_method('POST');
$auth = require_auth();

// El procesamiento de datos (JSON y _parts) ahora lo hace automáticamente conexion.php -> request.php


try {
    $entidad_id    = post_int('entidad_id',    true);
    $categoria_id  = post_int('categoria_id',  true);
    $proyecto_id   = post_int('proyecto_id',   true);
    $obra_id       = post_int('obra_id',       true);
    $catalogo_id   = post_int('catalogo_id',   true);

    // ── solicitante_id: puede llegar como 0 si el usuario tiene id=0 en la DB,
    //    aunque lo más común es que sea >= 1. Usamos min=0 para no rechazarlo.
    // ── Si no viene en el POST, tomamos el uid del token como fallback seguro.
    $solicitante_raw = $_POST['solicitante_id'] ?? null;
    if ($solicitante_raw === null || $solicitante_raw === '') {
        // Fallback: usar el usuario autenticado del token
        $solicitante_id = (int)($auth['uid'] ?? 0);
        if ($solicitante_id < 1) {
            throw new InvalidArgumentException("El campo solicitante_id es obligatorio.");
        }
   } else {
    if (!is_numeric($solicitante_raw)) {
        throw new InvalidArgumentException("El campo solicitante_id debe ser numérico.");
    }
    $solicitante_id = (int)$solicitante_raw;
    // Si llega 0, usar el uid del token como fallback
    if ($solicitante_id < 1) {
        $solicitante_id = (int)($auth['uid'] ?? 0);
        if ($solicitante_id < 1) {
            throw new InvalidArgumentException("No se pudo determinar el solicitante.");
        }
        error_log("[save_requis] solicitante_id vino como 0, usando uid del token: $solicitante_id");
    }
}

    $descripcion   = post_string('descripcion',   false, 2000) ?? '';
    $observaciones = post_string('observaciones', false, 2000) ?? '';
    $extra         = post_string('extra',         false, 2000) ?? '';
    $items         = post_json_array('items', true);
    $fecha_solicitud = date('Y-m-d H:i:s');

    // ── Validación de items ──────────────────────────────────────────────────
    $errors = [];
    if (count($items) === 0) {
        $errors[] = 'Agrega al menos un item.';
    } else {
        foreach ($items as $idx => $item) {
            if (!is_array($item)) {
                $errors[] = "Item #" . ($idx + 1) . " inválido.";
                continue;
            }
            if (empty($item['tipo']) || !in_array($item['tipo'], ['producto', 'servicio'], true)) {
                $errors[] = "Item #" . ($idx + 1) . ": tipo inválido.";
            }
            if (empty($item['producto_id']) || !is_numeric($item['producto_id'])) {
                $errors[] = "Item #" . ($idx + 1) . ": producto_id inválido.";
            }
            if (!isset($item['cantidad']) || !is_numeric($item['cantidad']) || (float)$item['cantidad'] <= 0) {
                $errors[] = "Item #" . ($idx + 1) . ": cantidad inválida.";
            }
            if (empty($item['unidad_id']) || !is_numeric($item['unidad_id'])) {
                $errors[] = "Item #" . ($idx + 1) . ": unidad_id inválida.";
            }
        }
    }

    if (!empty($errors)) {
        json_response(422, 'error', 'Validación fallida: ' . implode(' | ', $errors), [
            'errors'         => $errors,
            'received_items' => $items
        ]);
    }

    // ── Folio ────────────────────────────────────────────────────────────────
    $res_last = $conn->query("SELECT folio FROM requisiciones ORDER BY id DESC LIMIT 1");
    if ($res_last && $res_last->num_rows > 0) {
        $parts = explode('-', $res_last->fetch_assoc()['folio']);
        $num = isset($parts[1]) ? ((int)$parts[1] + 1) : 1;
    } else {
        $num = 1;
    }
    $folio = "REQ-" . str_pad((string)$num, 4, '0', STR_PAD_LEFT);

    $conn->begin_transaction();

    // ── Insertar requisición ─────────────────────────────────────────────────
    $stmt = $conn->prepare("
        INSERT INTO requisiciones
            (folio, entidad_id, solicitante_id, categoria_id, proyecto_id, obra_id, catalogo_id,
             descripcion, observaciones, extra, estado, fecha_solicitud)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente', ?)
    ");
    $stmt->bind_param(
        "siiiiisssss",
        $folio, $entidad_id, $solicitante_id, $categoria_id,
        $proyecto_id, $obra_id, $catalogo_id,
        $descripcion, $observaciones, $extra, $fecha_solicitud
    );
    if (!$stmt->execute()) {
        throw new RuntimeException('No se pudo guardar requisición');
    }

    $requisicion_id = $stmt->insert_id;

    // ── Detectar si existe columna concepto_id en items ──────────────────────
    $check_col  = $conn->query("SHOW COLUMNS FROM requisicion_items LIKE 'concepto_id'");
    $has_concepto = ($check_col && $check_col->num_rows > 0);

    // ── Insertar items ───────────────────────────────────────────────────────
    foreach ($items as $item) {
        $tipo        = (string)$item['tipo'];
        $producto_id = (int)$item['producto_id'];
        $cantidad    = (int)$item['cantidad'];
        $unidad_id   = (int)$item['unidad_id'];

        error_log("[save_requis] Insertando item: producto_id=$producto_id, tipo=$tipo, cantidad=$cantidad, unidad_id=$unidad_id");

        if ($has_concepto) {
            $concepto_id = (!empty($item['concepto_id']) && (int)$item['concepto_id'] > 0) ? (int)$item['concepto_id'] : null;
            $si = $conn->prepare("
                INSERT INTO requisicion_items
                    (requisicion_id, tipo, producto_id, cantidad, unidad_id, concepto_id)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $si->bind_param("isiiii", $requisicion_id, $tipo, $producto_id, $cantidad, $unidad_id, $concepto_id);
        } else {
            $si = $conn->prepare("
                INSERT INTO requisicion_items
                    (requisicion_id, tipo, producto_id, cantidad, unidad_id)
                VALUES (?, ?, ?, ?, ?)
            ");
            $si->bind_param("isiii", $requisicion_id, $tipo, $producto_id, $cantidad, $unidad_id);
        }

        if (!$si->execute()) {
            throw new RuntimeException('No se pudo guardar item');
        }
        $si->close();
    }

    // ── Archivos adjuntos ────────────────────────────────────────────────────
    if (isset($_FILES['archivos']) && is_array($_FILES['archivos']['name'])) {
        $uploadDir = __DIR__ . '/../uploads/requisiciones/';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            throw new RuntimeException('No se pudo crear directorio de uploads');
        }

        $allowedMime = ['image/jpeg', 'image/png', 'application/pdf'];
        $maxSize     = 8 * 1024 * 1024; // 8MB
        $finfo       = finfo_open(FILEINFO_MIME_TYPE);

        foreach ($_FILES['archivos']['name'] as $key => $originalName) {
            $err = $_FILES['archivos']['error'][$key];
            if ($err !== UPLOAD_ERR_OK || empty($originalName)) continue;

            $tmpName = $_FILES['archivos']['tmp_name'][$key];
            $size    = (int)$_FILES['archivos']['size'][$key];

            if ($size <= 0 || $size > $maxSize) {
                throw new RuntimeException("Archivo inválido (tamaño) en posición " . ($key + 1));
            }

            $mime = finfo_file($finfo, $tmpName);
            if (!in_array($mime, $allowedMime, true)) {
                throw new RuntimeException("Archivo inválido (tipo) en posición " . ($key + 1));
            }

            $safeBase = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
            $ext      = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'], true)) {
                $ext = $mime === 'application/pdf' ? 'pdf' : 'jpg';
            }

            $uniqueName   = $safeBase . '_' . uniqid('', true) . '.' . $ext;
            $absolutePath = $uploadDir . $uniqueName;
            $relativePath = 'uploads/requisiciones/' . $uniqueName;

            if (!move_uploaded_file($tmpName, $absolutePath)) {
                throw new RuntimeException('No se pudo mover archivo');
            }

            $sf = $conn->prepare("
                INSERT INTO requisicion_archivos
                    (requisicion_id, nombre_archivo, ruta_archivo, tamaño_archivo, tipo_mime)
                VALUES (?, ?, ?, ?, ?)
            ");
            $originalClean = basename($originalName);
            $sf->bind_param("issis", $requisicion_id, $originalClean, $relativePath, $size, $mime);
            if (!$sf->execute()) {
                throw new RuntimeException('No se pudo guardar metadato de archivo');
            }
            $sf->close();
        }

        finfo_close($finfo);
    }

    $conn->commit();

    // ── Notificaciones por correo (NO rompen flujo si fallan) ────────────────
    /* 
    try {
        $sql_sup = "SELECT correo_corporativo, CONCAT(nombres, ' ', apellidos) AS nombre_completo
                    FROM usuarios
                    WHERE departamento_id IN (
                        SELECT id FROM departamentos WHERE nombre IN ('Procura', 'Gerente de Operaciones')
                    ) AND activo = 1";
        $res_sup = $conn->query($sql_sup);

        if ($res_sup && $res_sup->num_rows > 0) {
            $emailHandler = new EmailHandler();
            while ($supervisor = $res_sup->fetch_assoc()) {
                $emailHandler->enviarNotificacionRequisicion(
                    $supervisor['correo_corporativo'],
                    $supervisor['nombre_completo'],
                    [
                        'folio'      => $folio,
                        'url_sistema' => '#'
                    ]
                );
            }
        }
    } catch (Throwable $mailError) {
        error_log('[api_mobile/save_requis][mail] ' . $mailError->getMessage());
    }
    */

    json_response(200, 'success', 'Requisición creada correctamente', [
        'folio'         => $folio,
        'requisicion_id' => $requisicion_id
    ]);

} catch (InvalidArgumentException $e) {
    if (isset($conn) && $conn->errno) {
        $conn->rollback();
    }
    json_response(422, 'error', $e->getMessage());
} catch (Throwable $e) {
    if (isset($conn)) $conn->rollback();
    error_log('[api_mobile/save_requis] ' . $e->getMessage());
    json_response(500, 'error', 'Error al guardar la requisición');
}