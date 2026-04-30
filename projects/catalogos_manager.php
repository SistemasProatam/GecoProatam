<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../config.php";
require_once __DIR__ . '/../config.php';
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";
require_once __DIR__ . "/../conexion.php";

checkSession();
preventCaching();
header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

// ================================================================
// HELPERS DE ÁRBOL
// ================================================================

/**
 * Calcula el sort_path para un nodo nuevo dentro de su padre.
 * Usa el número de hermanos existentes + 1 como posición ordinal.
 * Funciona con cualquier tipo de clave: texto libre, romano, numérico.
 */
function calcularSortPath(mysqli $conn, int $catalogo_id, ?int $parent_id): string {
    if ($parent_id === null) {
        // Contar raíces existentes en este catálogo
        $st = $conn->prepare("SELECT COUNT(*) FROM concepto_nodos WHERE catalogo_id = ? AND parent_id IS NULL");
        $st->bind_param("i", $catalogo_id);
    } else {
        // Contar hermanos existentes bajo este padre
        $st = $conn->prepare("SELECT COUNT(*) FROM concepto_nodos WHERE catalogo_id = ? AND parent_id = ?");
        $st->bind_param("ii", $catalogo_id, $parent_id);
    }
    $st->execute();
    $st->bind_result($count);
    $st->fetch();
    $st->close();

    $posicion = $count + 1; // siguiente posición disponible

    if ($parent_id === null) {
        return str_pad($posicion, 4, '0', STR_PAD_LEFT);
    }

    // Obtener sort_path del padre
    $sp = $conn->prepare("SELECT sort_path FROM concepto_nodos WHERE id = ?");
    $sp->bind_param("i", $parent_id);
    $sp->execute();
    $sp->bind_result($parent_sort);
    $sp->fetch();
    $sp->close();

    return $parent_sort . '.' . str_pad($posicion, 4, '0', STR_PAD_LEFT);
}

/**
 * Dado un catalogo_id y una clave como "CIMENTACION" o "I.2.1",
 * garantiza que existan todos los nodos intermedios y devuelve
 * el ID del nodo hoja.
 *
 * @param array $titulos  Mapa [clave_completa => titulo] opcional
 */
function obtenerOCrearNodo(mysqli $conn, int $catalogo_id, string $clave_completa, array $titulos = []): int {
    $clave_completa = trim($clave_completa);
    if ($clave_completa === '') return 0;

    $partes       = explode('.', $clave_completa);
    $parent_id    = null;
    $clave_actual = '';

    foreach ($partes as $i => $segmento) {
        $segmento     = trim($segmento);
        $clave_actual = $i === 0 ? $segmento : $clave_actual . '.' . $segmento;
        $nivel        = $i + 1;

        // Buscar si ya existe este nodo
        if ($parent_id === null) {
            $st = $conn->prepare("SELECT id FROM concepto_nodos WHERE catalogo_id = ? AND parent_id IS NULL AND clave = ?");
            $st->bind_param("is", $catalogo_id, $clave_actual);
        } else {
            $st = $conn->prepare("SELECT id FROM concepto_nodos WHERE catalogo_id = ? AND parent_id = ? AND clave = ?");
            $st->bind_param("iis", $catalogo_id, $parent_id, $clave_actual);
        }
        $st->execute();
        $st->bind_result($nodo_id_existente);
        $found = $st->fetch();
        $st->close();

        if ($found && $nodo_id_existente) {
            $parent_id = (int)$nodo_id_existente;
        } else {
            // Crear nodo nuevo
            $sort_path = calcularSortPath($conn, $catalogo_id, $parent_id);
            $titulo    = $titulos[$clave_actual] ?? $clave_actual;

            $ins = $conn->prepare(
                "INSERT INTO concepto_nodos (catalogo_id, parent_id, clave, titulo, nivel, sort_path)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $ins->bind_param("iissis", $catalogo_id, $parent_id, $clave_actual, $titulo, $nivel, $sort_path);
            if (!$ins->execute()) {
                error_log("Error insertando nodo: " . $ins->error);
            }
            $parent_id = (int)$conn->insert_id;
            $ins->close();
        }
    }

    return (int)$parent_id;
}

// ================================================================
// ROUTER
// ================================================================
try {
    switch ($action) {

        // --------------------------------------------------------
        case 'obtener_catalogos':
            $obra_id = (int)($_POST['obra_id'] ?? 0);
            if ($obra_id <= 0) { echo json_encode(['success' => false, 'error' => 'ID de obra invalido']); exit; }

            $stmt = $conn->prepare(
                "SELECT c.*, (SELECT COUNT(*) FROM conceptos WHERE catalogo_id = c.id) AS total_conceptos
                 FROM catalogos c WHERE c.obra_id = ? ORDER BY c.fecha_creacion DESC"
            );
            $stmt->bind_param("i", $obra_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $out = [];
            while ($r = $res->fetch_assoc()) $out[] = $r;
            echo json_encode($out);
            break;

        // --------------------------------------------------------
        case 'crear_catalogo':
            $obra_id = (int)($_POST['obra_id'] ?? 0);
            $nombre  = trim($_POST['nombre_catalogo'] ?? '');
            $desc    = trim($_POST['descripcion']     ?? '');
            if ($obra_id <= 0 || empty($nombre)) { echo json_encode(['success' => false, 'error' => 'Datos incompletos']); exit; }

            $stmt = $conn->prepare("INSERT INTO catalogos (obra_id, nombre_catalogo, descripcion, fecha_creacion) VALUES (?, ?, ?, NOW())");
            $stmt->bind_param("iss", $obra_id, $nombre, $desc);
            echo $stmt->execute()
                ? json_encode(['success' => true,  'message' => 'Catalogo creado correctamente'])
                : json_encode(['success' => false, 'error'   => 'Error: ' . $stmt->error]);
            break;

        // --------------------------------------------------------
        case 'actualizar_catalogo':
            $catalogo_id = (int)($_POST['catalogo_id'] ?? 0);
            $nombre      = trim($_POST['nombre_catalogo'] ?? '');
            $desc        = trim($_POST['descripcion']     ?? '');
            if ($catalogo_id <= 0 || empty($nombre)) { echo json_encode(['success' => false, 'error' => 'Datos incompletos']); exit; }

            $stmt = $conn->prepare("UPDATE catalogos SET nombre_catalogo = ?, descripcion = ? WHERE id = ?");
            $stmt->bind_param("ssi", $nombre, $desc, $catalogo_id);
            echo $stmt->execute()
                ? json_encode(['success' => true,  'message' => 'Catalogo actualizado correctamente'])
                : json_encode(['success' => false, 'error'   => 'Error: ' . $stmt->error]);
            break;

        // --------------------------------------------------------
        case 'eliminar_catalogo':
            $catalogo_id = (int)($_POST['catalogo_id'] ?? 0);
            if ($catalogo_id <= 0) { echo json_encode(['success' => false, 'error' => 'ID invalido']); exit; }

            $conn->begin_transaction();
            try {
                // Orden: conceptos → nodos → catálogo (respetar FKs)
                $s1 = $conn->prepare("DELETE FROM conceptos      WHERE catalogo_id = ?"); $s1->bind_param("i", $catalogo_id); $s1->execute();
                $s2 = $conn->prepare("DELETE FROM concepto_nodos WHERE catalogo_id = ?"); $s2->bind_param("i", $catalogo_id); $s2->execute();
                $s3 = $conn->prepare("DELETE FROM catalogos       WHERE id = ?");          $s3->bind_param("i", $catalogo_id); $s3->execute();
                $conn->commit();
                echo json_encode(['success' => true, 'message' => 'Catalogo eliminado correctamente']);
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;

        // --------------------------------------------------------
        case 'obtener_arbol_nodos':
            $catalogo_id = (int)($_POST['catalogo_id'] ?? 0);
            if ($catalogo_id <= 0) { echo json_encode(['success' => false, 'error' => 'ID invalido']); exit; }

            $stmt = $conn->prepare(
                "SELECT id, parent_id, clave, titulo, nivel, sort_path
                 FROM concepto_nodos WHERE catalogo_id = ? ORDER BY sort_path ASC"
            );
            $stmt->bind_param("i", $catalogo_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $out = [];
            while ($r = $res->fetch_assoc()) $out[] = $r;
            echo json_encode(['success' => true, 'nodos' => $out]);
            break;

        // --------------------------------------------------------
        case 'crear_nodo':
            $catalogo_id = (int)($_POST['catalogo_id'] ?? 0);
            $clave       = trim($_POST['clave']        ?? '');
            $titulo      = trim($_POST['titulo']       ?? '');
            if ($catalogo_id <= 0 || empty($clave)) { echo json_encode(['success' => false, 'error' => 'Datos incompletos']); exit; }

            $nodo_id = obtenerOCrearNodo($conn, $catalogo_id, $clave, [$clave => $titulo ?: $clave]);
            echo json_encode(['success' => true, 'nodo_id' => $nodo_id]);
            break;

        // --------------------------------------------------------
        case 'actualizar_nodo':
            $nodo_id = (int)($_POST['nodo_id'] ?? 0);
            $titulo  = trim($_POST['titulo']   ?? '');
            $clave   = trim($_POST['clave']    ?? '');
            if ($nodo_id <= 0) { echo json_encode(['success' => false, 'error' => 'ID de nodo invalido']); exit; }

            $stmt = $conn->prepare("UPDATE concepto_nodos SET titulo = ?, clave = ? WHERE id = ?");
            $stmt->bind_param("ssi", $titulo, $clave, $nodo_id);
            echo $stmt->execute()
                ? json_encode(['success' => true,  'message' => 'Nodo actualizado correctamente'])
                : json_encode(['success' => false, 'error'   => $stmt->error]);
            break;

        // --------------------------------------------------------
        case 'eliminar_nodo':
            // ON DELETE CASCADE en FK elimina hijos automáticamente.
            // Los conceptos quedan con nodo_id = NULL (ON DELETE SET NULL).
            $nodo_id = (int)($_POST['nodo_id'] ?? 0);
            if ($nodo_id <= 0) { echo json_encode(['success' => false, 'error' => 'ID invalido']); exit; }

            $stmt = $conn->prepare("DELETE FROM concepto_nodos WHERE id = ?");
            $stmt->bind_param("i", $nodo_id);
            echo $stmt->execute()
                ? json_encode(['success' => true,  'message' => 'Nodo eliminado correctamente'])
                : json_encode(['success' => false, 'error'   => $stmt->error]);
            break;

        // --------------------------------------------------------
        case 'obtener_conceptos':
            $catalogo_id = (int)($_POST['catalogo_id'] ?? 0);
            if ($catalogo_id <= 0) { echo json_encode(['success' => false, 'error' => 'ID invalido']); exit; }

            $sql = "SELECT c.*,
                           n.clave     AS nodo_clave,
                           n.titulo    AS nodo_titulo,
                           n.nivel     AS nodo_nivel,
                           n.sort_path AS nodo_sort_path,
                           n.parent_id AS nodo_parent_id,
                           (SELECT COUNT(*) FROM orden_compra_items oci
                             JOIN ordenes_compra oc ON oci.orden_compra_id = oc.id
                             WHERE oci.concepto_id = c.id AND oc.estado = 'pagado') AS total_items,
                           (SELECT COALESCE(SUM(oci.subtotal), 0) FROM orden_compra_items oci
                             JOIN ordenes_compra oc ON oci.orden_compra_id = oc.id
                             WHERE oci.concepto_id = c.id AND oc.estado = 'pagado') AS monto_total
                    FROM conceptos c
                    LEFT JOIN concepto_nodos n ON c.nodo_id = n.id
                    WHERE c.catalogo_id = ?
                    ORDER BY
                        COALESCE(n.sort_path, '9999') ASC,
                        CAST(NULLIF(c.numero_original, '') AS UNSIGNED) ASC,
                        c.codigo_concepto ASC";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $catalogo_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $out = [];
            while ($r = $res->fetch_assoc()) $out[] = $r;
            echo json_encode($out);
            break;

        // --------------------------------------------------------
        case 'obtener_conceptos_agrupados':
            $catalogo_id = (int)($_POST['catalogo_id'] ?? 0);
            if ($catalogo_id <= 0) { echo json_encode(['success' => false, 'error' => 'ID invalido']); exit; }

            $sql = "SELECT
                        n.id        AS nodo_id,
                        n.parent_id AS nodo_parent_id,
                        n.clave     AS nodo_clave,
                        n.titulo    AS nodo_titulo,
                        n.nivel     AS nodo_nivel,
                        n.sort_path AS nodo_sort_path,
                        COUNT(c.id) AS total_conceptos,
                        GROUP_CONCAT(c.id) AS conceptos_ids
                    FROM concepto_nodos n
                    LEFT JOIN conceptos c ON c.nodo_id = n.id AND c.catalogo_id = ?
                    WHERE n.catalogo_id = ?
                    GROUP BY n.id
                    ORDER BY n.sort_path ASC";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $catalogo_id, $catalogo_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $out = [];
            while ($r = $res->fetch_assoc()) $out[] = $r;
            echo json_encode($out);
            break;

        // --------------------------------------------------------
        case 'crear_concepto':
            $catalogo_id     = (int)($_POST['catalogo_id']     ?? 0);
            $codigo_concepto = trim($_POST['codigo_concepto']  ?? '');
            $nombre_concepto = trim($_POST['nombre_concepto']  ?? '');
            $descripcion     = trim($_POST['descripcion']      ?? '');
            $unidad_medida   = trim($_POST['unidad_medida']    ?? '');
            $numero_original = trim($_POST['numero_original']  ?? '');
            $nodo_clave      = trim($_POST['nodo_clave']       ?? '');
            $nodo_id_direct  = (int)($_POST['nodo_id']         ?? 0);
            $cantidad        = isset($_POST['cantidad'])        && $_POST['cantidad']        !== '' ? (float)$_POST['cantidad']        : null;
            $precio_unitario = isset($_POST['precio_unitario']) && $_POST['precio_unitario'] !== '' ? $_POST['precio_unitario']         : null;
            $importe         = isset($_POST['importe'])         && $_POST['importe']         !== '' ? $_POST['importe']                 : null;
            $fecha_inicio    = !empty($_POST['fecha_inicio'])  ? $_POST['fecha_inicio']     : null;
            $fecha_fin       = !empty($_POST['fecha_fin'])     ? $_POST['fecha_fin']        : null;
            $permitir_dup    = ($_POST['permitir_duplicados']  ?? 'false') === 'true';

            if ($catalogo_id <= 0 || empty($codigo_concepto) || empty($nombre_concepto)) {
                echo json_encode(['success' => false, 'error' => 'Datos incompletos']); exit;
            }

            // Resolver nodo_id
            $nodo_id = $nodo_id_direct > 0
                ? $nodo_id_direct
                : ($nodo_clave !== '' ? obtenerOCrearNodo($conn, $catalogo_id, $nodo_clave) : null);



            // nodo_id puede ser null — se usa tipo 's' para que MySQLi acepte null
            $nodo_id_str = $nodo_id !== null ? (string)(int)$nodo_id : null;

            $ins = $conn->prepare(
                "INSERT INTO conceptos
                 (catalogo_id, codigo_concepto, nombre_concepto, descripcion,
                  unidad_medida, numero_original, nodo_id,
                  cantidad, precio_unitario, importe,
                  fecha_inicio, fecha_fin, fecha_creacion)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
            );
            // 12 parámetros: i s s s s s s d d d s s
            $ins->bind_param("issssssdddss",
                $catalogo_id,
                $codigo_concepto, $nombre_concepto, $descripcion,
                $unidad_medida, $numero_original, $nodo_id_str,
                $cantidad, $precio_unitario, $importe,
                $fecha_inicio, $fecha_fin
            );
            echo $ins->execute()
                ? json_encode(['success' => true,  'message' => 'Concepto creado correctamente'])
                : json_encode(['success' => false, 'error'   => 'Error: ' . $ins->error]);
            break;

        // --------------------------------------------------------
        case 'actualizar_concepto':
            $concepto_id     = (int)($_POST['concepto_id']     ?? 0);
            $codigo_concepto = trim($_POST['codigo_concepto']  ?? '');
            $nombre_concepto = trim($_POST['nombre_concepto']  ?? '');
            $descripcion     = trim($_POST['descripcion']      ?? '');
            $unidad_medida   = trim($_POST['unidad_medida']    ?? '');
            $numero_original = trim($_POST['numero_original']  ?? '');
            $nodo_clave      = trim($_POST['nodo_clave']       ?? '');
            $nodo_id_direct  = (int)($_POST['nodo_id']         ?? 0);
            $cantidad        = isset($_POST['cantidad'])        && $_POST['cantidad']        !== '' ? (float)$_POST['cantidad']        : null;
            $precio_unitario = isset($_POST['precio_unitario']) && $_POST['precio_unitario'] !== '' ? $_POST['precio_unitario']         : null;
            $importe         = isset($_POST['importe'])         && $_POST['importe']         !== '' ? $_POST['importe']                 : null;
            $fecha_inicio    = !empty($_POST['fecha_inicio'])  ? $_POST['fecha_inicio']     : null;
            $fecha_fin       = !empty($_POST['fecha_fin'])     ? $_POST['fecha_fin']        : null;

            if ($concepto_id <= 0 || empty($codigo_concepto) || empty($nombre_concepto)) {
                echo json_encode(['success' => false, 'error' => 'Datos incompletos']); exit;
            }

            // Obtener catalogo_id del concepto existente
            $sc = $conn->prepare("SELECT catalogo_id FROM conceptos WHERE id = ?");
            $sc->bind_param("i", $concepto_id);
            $sc->execute();
            $row_c = $sc->get_result()->fetch_assoc();
            if (!$row_c) { echo json_encode(['success' => false, 'error' => 'Concepto no encontrado']); exit; }
            $catalogo_id = (int)$row_c['catalogo_id'];

            // Resolver nodo_id antes de la validación
            $nodo_id = $nodo_id_direct > 0
                ? $nodo_id_direct
                : ($nodo_clave !== '' ? obtenerOCrearNodo($conn, $catalogo_id, $nodo_clave) : null);





            // nodo_id puede ser null — tipo 's' para que MySQLi lo acepte
            $nodo_id_str = $nodo_id !== null ? (string)(int)$nodo_id : null;

            $upd = $conn->prepare(
                "UPDATE conceptos
                 SET nodo_id = ?, codigo_concepto = ?, nombre_concepto = ?, descripcion = ?,
                     unidad_medida = ?, numero_original = ?,
                     cantidad = ?, precio_unitario = ?, importe = ?,
                     fecha_inicio = ?, fecha_fin = ?
                 WHERE id = ?"
            );
            // 12 parametros: s s s s s s d d d s s i
            $upd->bind_param("ssssssdddssi",
                $nodo_id_str,
                $codigo_concepto, $nombre_concepto, $descripcion,
                $unidad_medida, $numero_original,
                $cantidad, $precio_unitario, $importe,
                $fecha_inicio, $fecha_fin,
                $concepto_id
            );
            echo $upd->execute()
                ? json_encode(['success' => true,  'message' => 'Concepto actualizado correctamente'])
                : json_encode(['success' => false, 'error'   => 'Error: ' . $upd->error]);
            break;

        // --------------------------------------------------------
        case 'eliminar_concepto':
            $concepto_id = (int)($_POST['concepto_id'] ?? 0);
            if ($concepto_id <= 0) { echo json_encode(['success' => false, 'error' => 'ID invalido']); exit; }

            $conn->begin_transaction();
            try {
                $s = $conn->prepare("DELETE FROM conceptos WHERE id = ?");
                $s->bind_param("i", $concepto_id);
                $s->execute();
                $conn->commit();
                echo json_encode(['success' => true, 'message' => 'Concepto eliminado correctamente']);
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;

        // --------------------------------------------------------
        case 'importar_conceptos_excel':
            $catalogo_id = (int)($_POST['catalogo_id']  ?? 0);
            $datos_excel = json_decode($_POST['datos_excel'] ?? '[]', true);
            if ($catalogo_id <= 0)   { echo json_encode(['success' => false, 'error' => 'ID invalido']); exit; }
            if (empty($datos_excel)) { echo json_encode(['success' => false, 'error' => 'Sin datos']);   exit; }

            $importados = 0;
            $errores    = [];
            $nodo_cache = []; // evitar queries repetidos para el mismo nodo en la misma importación

            $conn->begin_transaction();
            try {
                // PRIMERA PASADA: Recolectar títulos de nodos
                $titulos_nodos = [];
                foreach ($datos_excel as $row) {
                    if (isset($row['es_nodo']) && $row['es_nodo'] && !empty($row['nodo_clave'])) {
                        $titulos_nodos[$row['nodo_clave']] = trim($row['nombre'] ?? '');
                    }
                }

                foreach ($datos_excel as $idx => $row) {
                    // Si es una fila de definición de nodo, ya procesamos su título, saltar inserción
                    if (isset($row['es_nodo']) && $row['es_nodo']) {
                        continue;
                    }

                    $codigo          = trim($row['codigo_concepto']  ?? '');
                    $nombre          = trim($row['nombre_concepto']  ?? '');
                    $descripcion     = trim($row['descripcion']      ?? '');
                    $unidad          = trim($row['unidad_medida']    ?? '');
                    $numero_original = trim($row['numero_original']  ?? '');
                    $nodo_clave      = trim($row['nodo_clave']       ?? '');
                    $cantidad        = isset($row['cantidad'])        && $row['cantidad']        !== '' ? (float)$row['cantidad']        : null;
                    $precio_unitario = isset($row['precio_unitario']) && $row['precio_unitario'] !== '' ? $row['precio_unitario']         : null;
                    $importe         = isset($row['importe'])         && $row['importe']         !== '' ? $row['importe']                 : null;
                    $fecha_inicio    = !empty($row['fecha_inicio'])  ? $row['fecha_inicio']     : null;
                    $fecha_fin       = !empty($row['fecha_fin'])     ? $row['fecha_fin']        : null;

                    if (empty($codigo) || empty($nombre)) {
                        $errores[] = "Fila " . ($idx + 1) . ": codigo o nombre vacio";
                        continue;
                    }

                    // Resolver nodo con caché para no repetir queries
                    $nodo_id = null;
                    if ($nodo_clave !== '') {
                        if (!array_key_exists($nodo_clave, $nodo_cache)) {
                            $nodo_cache[$nodo_clave] = obtenerOCrearNodo($conn, $catalogo_id, $nodo_clave, $titulos_nodos);
                        }
                        $nodo_id = $nodo_cache[$nodo_clave] ?: null;
                    }

                    // nodo_id puede ser null — tipo 's' para que MySQLi lo acepte
                    $nodo_id_str = $nodo_id !== null ? (string)(int)$nodo_id : null;

                    $ins = $conn->prepare(
                        "INSERT INTO conceptos
                         (catalogo_id, codigo_concepto, nombre_concepto, descripcion,
                          unidad_medida, numero_original, nodo_id,
                          cantidad, precio_unitario, importe,
                          fecha_inicio, fecha_fin, fecha_creacion)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
                    );
                    // 12 parametros: i s s s s s s d d d s s
                    $ins->bind_param("issssssdddss",
                        $catalogo_id,
                        $codigo, $nombre, $descripcion,
                        $unidad, $numero_original, $nodo_id_str,
                        $cantidad, $precio_unitario, $importe,
                        $fecha_inicio, $fecha_fin
                    );

                    if ($ins->execute()) {
                        $importados++;
                    } else {
                        $errores[] = "Fila " . ($idx + 1) . ": " . $ins->error;
                    }
                }

                $conn->commit();
                echo json_encode([
                    'success'              => true,
                    'conceptos_importados' => $importados,
                    'errores'              => $errores,
                    'total_procesados'     => count($datos_excel),
                ]);

            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;

        // --------------------------------------------------------
        case 'obtener_detalle_concepto':
            $concepto_id = (int)($_POST['concepto_id'] ?? 0);
            if ($concepto_id <= 0) { echo json_encode(['error' => 'ID invalido']); exit; }

            $sql = "SELECT c.*,
                           n.clave     AS nodo_clave,
                           n.titulo    AS nodo_titulo,
                           n.nivel     AS nodo_nivel,
                           n.sort_path AS nodo_sort_path,
                           (SELECT COUNT(*) FROM orden_compra_items oci
                             JOIN ordenes_compra oc ON oci.orden_compra_id = oc.id
                             WHERE oci.concepto_id = c.id AND oc.estado = 'pagado') AS total_items,
                           (SELECT COALESCE(SUM(oci.subtotal), 0) FROM orden_compra_items oci
                             JOIN ordenes_compra oc ON oci.orden_compra_id = oc.id
                             WHERE oci.concepto_id = c.id AND oc.estado = 'pagado') AS monto_total
                    FROM conceptos c
                    LEFT JOIN concepto_nodos n ON c.nodo_id = n.id
                    WHERE c.id = ?";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $concepto_id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            echo $row
                ? json_encode(['success' => true,  'concepto' => $row])
                : json_encode(['success' => false, 'error'    => 'Concepto no encontrado']);
            break;

        // --------------------------------------------------------
        case 'obtener_items_concepto':
            $concepto_id = (int)($_POST['concepto_id'] ?? 0);

            $sql = "SELECT oci.id, oci.descripcion, oci.cantidad,
                           IFNULL(u.nombre, oci.unidad_medida) AS unidad_medida,
                           oci.precio_unitario, oci.subtotal, oci.fecha_creacion,
                           oc.folio AS orden_folio, oc.fecha_solicitud AS orden_fecha,
                           oci.orden_compra_id
                    FROM orden_compra_items oci
                    LEFT JOIN unidades u ON oci.unidad_id = u.id
                    JOIN ordenes_compra oc ON oci.orden_compra_id = oc.id
                    WHERE oci.concepto_id = ? AND oc.estado = 'pagado'
                    ORDER BY oci.fecha_creacion DESC";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $concepto_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $out = [];
            while ($r = $res->fetch_assoc()) $out[] = $r;
            echo json_encode($out);
            break;

        // --------------------------------------------------------
        default:
            echo json_encode(['success' => false, 'error' => 'Accion no valida']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error del servidor: ' . $e->getMessage()]);
}

$conn->close();
?>

