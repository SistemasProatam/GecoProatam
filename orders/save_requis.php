<?php
// Incluir el gestor de sesiones UNA sola vez
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

// Verificar sesiÃ³n y prevenir caching
checkSession();
preventCaching();

require_once __DIR__ . "/../conexion.php";

require_once __DIR__ . '/../EmailHandler.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id'])) {
        die("Error: usuario no autenticado.");
    }

    $usuario_id     = $_SESSION['user_id'];
    $entidad_id     = $_POST['entidad_id'] ?? null;
    $solicitante_id = $_POST['solicitante_id'] ?? null;
    $categoria_id   = $_POST['categoria_id'] ?? null;

    // NUEVOS CAMPOS DE UBICACIÃ“N
    $proyecto_id    = $_POST['proyecto_id'] ?? null;
    $obra_id        = $_POST['obra_id'] ?? null;
    $catalogo_id    = $_POST['catalogo_id'] ?? null;
    // $concepto_id ya no se usa a nivel de requisiciÃ³n principal

    $descripcion    = $_POST['descripcion'] ?? '';
    $observaciones  = $_POST['observaciones'] ?? '';
    $extra          = $_POST['extra'] ?? '';
    $items          = $_POST['items'] ?? [];
    $fecha_solicitud = $_POST['fecha_solicitud'] ?? date('Y-m-d H:i:s');

    // ================================
    // Validar campos obligatorios
    // ================================
    $errores = [];

    if (empty($entidad_id)) {
        $errores[] = "La entidad es obligatoria.";
    }

    if (empty($solicitante_id)) {
        $errores[] = "El solicitante es obligatorio.";
    }

    if (empty($categoria_id)) {
        $errores[] = "La categorÃ­a es obligatoria.";
    }

    if (empty($proyecto_id)) {
        $errores[] = "El proyecto es obligatorio.";
    }

    if (empty($obra_id)) {
        $errores[] = "La obra es obligatoria.";
    }

    if (empty($catalogo_id)) {
        $errores[] = "El catÃ¡logo es obligatorio.";
    }

    if (empty($items)) {
        $errores[] = "Debe agregar al menos un item a la requisiciÃ³n.";
    }

    // Si hay errores, retornar respuesta JSON
    if (!empty($errores)) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => implode(' ', $errores)
        ]);
        exit;
    }

    // ================================
    // Generar folio
    // ================================
    $sql_last = "SELECT folio FROM requisiciones ORDER BY id DESC LIMIT 1";
    $res_last = $conn->query($sql_last);
    if ($res_last && $res_last->num_rows > 0) {
        $last_folio = $res_last->fetch_assoc()['folio'];
        $parts = explode("-", $last_folio); // ["REQ", "0001"]
        $num = intval($parts[1]) + 1;
    } else {
        $num = 1;
    }
    $folio = "REQ-" . str_pad($num, 4, "0", STR_PAD_LEFT);

    // ================================
    // Insertar requisiciÃ³n SIN concepto_id
    // ================================
    $sql = "INSERT INTO requisiciones 
                (folio, entidad_id, solicitante_id, categoria_id, 
                 proyecto_id, obra_id, catalogo_id,
                 descripcion, observaciones, extra, estado, fecha_solicitud) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente', ?)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) die("Error en la preparaciÃ³n: " . $conn->error);

    // Debug para ver quÃ© parÃ¡metros se estÃ¡n enviando
    error_log("Folio: " . $folio);
    error_log("Entidad ID: " . $entidad_id);
    error_log("Solicitante ID: " . $solicitante_id);
    error_log("Categoria ID: " . $categoria_id);
    error_log("Proyecto ID: " . $proyecto_id);
    error_log("Obra ID: " . $obra_id);
    error_log("Catalogo ID: " . $catalogo_id);
    error_log("DescripciÃ³n: " . $descripcion);
    error_log("Observaciones: " . $observaciones);
    error_log("Extra: " . $extra);
    error_log("Fecha solicitud: " . $fecha_solicitud);

    $stmt->bind_param(
        "siiiiisssss",
        $folio,
        $entidad_id,
        $solicitante_id,
        $categoria_id,
        $proyecto_id,
        $obra_id,
        $catalogo_id,
        $descripcion,
        $observaciones,
        $extra,
        $fecha_solicitud
    );

    if (!$stmt->execute()) die("Error al guardar la requisiciÃ³n: " . $stmt->error);

    $requisicion_id = $stmt->insert_id;

    // ================================
    // Insertar items dinÃ¡micos CON CONCEPTO POR ITEM
    // ================================
    if (!empty($items)) {
        // Verificar si la tabla tiene la columna concepto_id
        $check_column = $conn->query("SHOW COLUMNS FROM requisicion_items LIKE 'concepto_id'");
        $has_concepto_column = ($check_column && $check_column->num_rows > 0);

        if ($has_concepto_column) {
            $sql_item = "INSERT INTO requisicion_items 
                            (requisicion_id, tipo, producto_id, cantidad, unidad_id, concepto_id) 
                         VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_item = $conn->prepare($sql_item);
            if (!$stmt_item) die("Error preparaciÃ³n items: " . $conn->error);

            foreach ($items as $item) {
                $tipo        = $item['tipo'] ?? null;
                $producto_id = $item['producto_id'] ?? null;
                $cantidad    = $item['cantidad'] ?? 1;
                $unidad_id   = $item['unidad_id'] ?? null;
                $item_concepto_id = $item['concepto_id'] ?? null;

                // Si concepto_id estÃ¡ vacÃ­o, establecer como NULL
                if (empty($item_concepto_id)) {
                    $item_concepto_id = null;
                }

                $stmt_item->bind_param("isiiii", $requisicion_id, $tipo, $producto_id, $cantidad, $unidad_id, $item_concepto_id);
                if (!$stmt_item->execute()) {
                    error_log("Error insertando item: " . $stmt_item->error);
                }
            }
            $stmt_item->close();
        } else {
            // VersiÃ³n sin concepto_id en items (backward compatibility)
            $sql_item = "INSERT INTO requisicion_items 
                            (requisicion_id, tipo, producto_id, cantidad, unidad_id) 
                         VALUES (?, ?, ?, ?, ?)";
            $stmt_item = $conn->prepare($sql_item);
            if (!$stmt_item) die("Error preparaciÃ³n items: " . $conn->error);

            foreach ($items as $item) {
                $tipo        = $item['tipo'] ?? null;
                $producto_id = $item['producto_id'] ?? null;
                $cantidad    = $item['cantidad'] ?? 1;
                $unidad_id   = $item['unidad_id'] ?? null;

                $stmt_item->bind_param("isiii", $requisicion_id, $tipo, $producto_id, $cantidad, $unidad_id);
                if (!$stmt_item->execute()) {
                    error_log("Error insertando item: " . $stmt_item->error);
                }
            }
            $stmt_item->close();
        }
    }

    // ================================
    // Archivos adjuntos
    // ================================
    if (isset($_FILES['archivos']) && is_array($_FILES['archivos']['name'])) {

        $uploadDir = __DIR__ . '/../uploads/requisiciones/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $archivos_subidos = 0;

        foreach ($_FILES['archivos']['name'] as $key => $nombre) {
            if ($_FILES['archivos']['error'][$key] === UPLOAD_ERR_OK && !empty($nombre)) {

                $tmpName = $_FILES['archivos']['tmp_name'][$key];
                $tamaÃ±o = $_FILES['archivos']['size'][$key];
                $tipo = $_FILES['archivos']['type'][$key];

                $nombreArchivo = basename($nombre);
                $extension = pathinfo($nombreArchivo, PATHINFO_EXTENSION);
                $nombreSinExtension = pathinfo($nombreArchivo, PATHINFO_FILENAME);
                $nombreUnico = $nombreSinExtension . '_' . uniqid() . '.' . $extension;

                $rutaArchivo = $uploadDir . $nombreUnico;

                if (move_uploaded_file($tmpName, $rutaArchivo)) {
                    $sql_file = "INSERT INTO requisicion_archivos 
                                    (requisicion_id, nombre_archivo, ruta_archivo, tamaÃ±o_archivo, tipo_mime)
                                 VALUES (?, ?, ?, ?, ?)";
                    $stmt_file = $conn->prepare($sql_file);

                    if ($stmt_file) {
                        $stmt_file->bind_param("issis", $requisicion_id, $nombreArchivo, $rutaArchivo, $tamaÃ±o, $tipo);

                        if ($stmt_file->execute()) {
                            $archivos_subidos++;
                        } else {
                            error_log("Error al insertar archivo en BD: " . $stmt_file->error);
                        }

                        $stmt_file->close();
                    }
                }
            }
        }

        error_log("RequisiciÃ³n $requisicion_id: Se subieron $archivos_subidos archivos");
    }

    // ================================
    // NOTIFICACIONES POR CORREO
    // ================================

    // Obtener datos completos de la requisiciÃ³n
    $sql_requisicion = "SELECT r.*, e.nombre as entidad_nombre, c.nombre as categoria_nombre, 
                        p.nombre_proyecto, o.nombre_obra, cat.nombre_catalogo,
                        u.nombres, u.apellidos, u.correo_corporativo as solicitante_correo 
                        FROM requisiciones r 
                        LEFT JOIN entidades e ON r.entidad_id = e.id 
                        LEFT JOIN categorias c ON r.categoria_id = c.id 
                        LEFT JOIN proyectos p ON r.proyecto_id = p.id
                        LEFT JOIN obras o ON r.obra_id = o.id
                        LEFT JOIN catalogos cat ON r.catalogo_id = cat.id
                        LEFT JOIN usuarios u ON r.solicitante_id = u.id 
                        WHERE r.id = ?";
    $stmt_req = $conn->prepare($sql_requisicion);
    $stmt_req->bind_param("i", $requisicion_id);
    $stmt_req->execute();
    $requisicion_data = $stmt_req->get_result()->fetch_assoc();

    // Obtener items con conceptos para el email
    $check_column = $conn->query("SHOW COLUMNS FROM requisicion_items LIKE 'concepto_id'");
    $has_concepto_column = ($check_column && $check_column->num_rows > 0);

    if ($has_concepto_column) {
        $sql_items = "SELECT ri.*, ps.nombre as producto_nombre, u.nombre as unidad_nombre,
                             con.codigo_concepto, con.nombre_concepto
                      FROM requisicion_items ri
                      LEFT JOIN productos_servicios ps ON ri.producto_id = ps.id
                      LEFT JOIN unidades u ON ri.unidad_id = u.id
                      LEFT JOIN conceptos con ON ri.concepto_id = con.id
                      WHERE ri.requisicion_id = ?";
    } else {
        $sql_items = "SELECT ri.*, ps.nombre as producto_nombre, u.nombre as unidad_nombre
                      FROM requisicion_items ri
                      LEFT JOIN productos_servicios ps ON ri.producto_id = ps.id
                      LEFT JOIN unidades u ON ri.unidad_id = u.id
                      WHERE ri.requisicion_id = ?";
    }

    $stmt_items = $conn->prepare($sql_items);
    $stmt_items->bind_param("i", $requisicion_id);
    $stmt_items->execute();
    $items_data = $stmt_items->get_result()->fetch_all(MYSQLI_ASSOC);

    // Preparar datos para notificaciÃ³n
    $datosRequisicion = [
        'folio' => $requisicion_data['folio'],
        'solicitante' => $requisicion_data['nombres'] . ' ' . $requisicion_data['apellidos'],
        'fecha_solicitud' => $requisicion_data['fecha_solicitud'],
        'entidad' => $requisicion_data['entidad_nombre'],
        'categoria' => $requisicion_data['categoria_nombre'],
        'descripcion' => $requisicion_data['descripcion'] ?? '',
        'observaciones' => $requisicion_data['observaciones'] ?? '',
        'ubicacion' => generarTextoUbicacion($requisicion_data),
        'items' => $items_data
    ];

    // Obtener supervisores de proyecto
    $sql_supervisores = "SELECT correo_corporativo, CONCAT(nombres, ' ', apellidos) as nombre_completo 
                         FROM usuarios 
                         WHERE  departamento_id IN (
                            SELECT id FROM departamentos WHERE nombre IN ('Procura', 'Gerente de Operaciones')
                            )
                        AND activo = 1";
    $result_supervisores = $conn->query($sql_supervisores);
    $supervisores = [];

    if ($result_supervisores && $result_supervisores->num_rows > 0) {
        while ($row = $result_supervisores->fetch_assoc()) {
            $supervisores[] = $row;
        }
    }

    // Enviar notificaciÃ³n a supervisores usando EmailHandler
    if (!empty($supervisores)) {
        try {
            $emailHandler = new EmailHandler();

            foreach ($supervisores as $supervisor) {
                $datosEmail = [
                    'nombre' => $supervisor['nombre_completo'],
                    'folio' => $datosRequisicion['folio'],
                    'solicitante' => $datosRequisicion['solicitante'],
                    'fecha' => $datosRequisicion['fecha_solicitud'],
                    'entidad' => $datosRequisicion['entidad'],
                    'categoria' => $datosRequisicion['categoria'],
                    'descripcion' => $datosRequisicion['descripcion'],
                    'observaciones' => $datosRequisicion['observaciones'],
                    'ubicacion' => $datosRequisicion['ubicacion'],
                    'items' => $datosRequisicion['items'],
                    'url_sistema' => 'https://proatamgoc.duckdns.org/requisiciones/list_requis.php'
                ];

                $emailHandler->enviarNotificacionRequisicion(
                    $supervisor['correo_corporativo'],
                    $supervisor['nombre_completo'],
                    $datosEmail
                );
            }

            error_log("NotificaciÃ³n enviada a " . count($supervisores) . " supervisores para requisiciÃ³n " . $datosRequisicion['folio']);
        } catch (Exception $e) {
            error_log("Error enviando notificaciÃ³n: " . $e->getMessage());
        }
    }

    // ================================
    // Redirigir o retornar JSON
    // ================================
    // Si es una peticiÃ³n AJAX (fetch), retornar JSON
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'message' => 'RequisiciÃ³n guardada correctamente',
            'folio' => $folio,
            'requisicion_id' => $requisicion_id
        ]);
        exit;
    }

    // Si es una peticiÃ³n normal (form tradicional), redirigir
    header("Location: list_requis.php?msg=success&folio=" . urlencode($folio));
    exit;
}

/**
 * Genera texto descriptivo de la ubicaciÃ³n seleccionada
 */
function generarTextoUbicacion($requisicion_data)
{
    $ubicacion = [];

    if (!empty($requisicion_data['nombre_proyecto'])) {
        $ubicacion[] = "Proyecto: " . $requisicion_data['nombre_proyecto'];
    }

    if (!empty($requisicion_data['nombre_obra'])) {
        $ubicacion[] = "Obra: " . $requisicion_data['nombre_obra'];
    }

    if (!empty($requisicion_data['nombre_catalogo'])) {
        $ubicacion[] = "CatÃ¡logo: " . $requisicion_data['nombre_catalogo'];
    }

    return !empty($ubicacion) ? implode(" | ", $ubicacion) : "Sin ubicaciÃ³n especÃ­fica";
}



