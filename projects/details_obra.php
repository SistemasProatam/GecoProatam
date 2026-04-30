<?php
// details_obra.php
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";
require_once __DIR__ . "/../EmailHandler.php";
$emailHandler = new EmailHandler();
checkSession();
preventCaching();

include(__DIR__ . "/../conexion.php");

$obra_id = $_GET['id'] ?? 0;

if ($obra_id <= 0) {
  header("Location: list_obras_view.php");
  exit;
}

// Obtener información completa de la obra
$sql_obra = "SELECT o.*, p.nombre_proyecto, p.numero_licitacion, p.numero_contrato,
             (SELECT COALESCE(SUM(costo_directo_utilizado), 0) FROM presupuesto_control 
              WHERE obra_id = o.id) as costo_directo_utilizado,
             (SELECT COUNT(*) FROM catalogos WHERE obra_id = o.id) as total_catalogos,
             (SELECT COUNT(*) FROM conceptos c 
              JOIN catalogos cat ON c.catalogo_id = cat.id 
              WHERE cat.obra_id = o.id) as total_conceptos
             FROM obras o 
             LEFT JOIN proyectos p ON o.proyecto_id = p.id 
             WHERE o.id = ?";
$stmt = $conn->prepare($sql_obra);
$stmt->bind_param("i", $obra_id);
$stmt->execute();
$obra = $stmt->get_result()->fetch_assoc();

if (!$obra) {
  header("Location: list_obras_view.php");
  exit;
}

// Calcular valores
$costo_disponible = $obra['costo_directo'] - $obra['costo_directo_utilizado'];
$porcentaje_utilizado = $obra['costo_directo'] > 0 ?
  ($obra['costo_directo_utilizado'] / $obra['costo_directo']) * 100 : 0;

// Obtener catálogos de la obra
$sql_catalogos = "SELECT * FROM catalogos WHERE obra_id = ? ORDER BY fecha_creacion DESC";
$stmt_catalogos = $conn->prepare($sql_catalogos);
$stmt_catalogos->bind_param("i", $obra_id);
$stmt_catalogos->execute();
$catalogos = $stmt_catalogos->get_result();
// AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: application/json');
  $action = $_POST['action'] ?? '';
  try {
    switch ($action) {

      case 'obtener_subcontratos':
        $oid = (int)($_POST['obra_id'] ?? 0);
        $sql = "SELECT sc.id, sc.proveedor_id, p.nombre AS proveedor_nombre,
                   sc.total_estimado, sc.anticipo_pct, sc.anticipo_monto,
                   sc.descripcion,
                   (SELECT COALESCE(SUM(total), 0) FROM ordenes_compra 
                    WHERE subcontrato_id = sc.id AND obra_id = sc.obra_id AND estado != 'rechazado') AS monto_real,
                   (SELECT COUNT(*) FROM subcontrato_conceptos WHERE subcontrato_id=sc.id) AS total_conceptos,
                   COALESCE(ext.total_ext,0) AS total_extraordinarios,
                   COALESCE(ext.num_ext,0)   AS num_extraordinarios
            FROM subcontratos sc
            LEFT JOIN proveedores p ON p.id=sc.proveedor_id
            LEFT JOIN (SELECT subcontrato_id, SUM(monto) AS total_ext, COUNT(*) AS num_ext
                       FROM subcontrato_extraordinarios GROUP BY subcontrato_id) ext
                   ON ext.subcontrato_id=sc.id
            WHERE sc.obra_id=? ORDER BY p.nombre ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $oid);
        $stmt->execute();
        echo json_encode(['success' => true, 'subcontratos' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
        break;

      case 'obtener_proveedores':
        $stmt = $conn->prepare("SELECT id, nombre FROM proveedores ORDER BY nombre ASC");
        $stmt->execute();
        echo json_encode(['success' => true, 'proveedores' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
        break;

      case 'obtener_conceptos_obra':
        $oid = (int)($_POST['obra_id'] ?? 0);
        $sql = "SELECT c.id, c.codigo_concepto, c.nombre_concepto, c.cantidad, c.unidad_medida, c.categoria, c.subcategoria,
                               GROUP_CONCAT(DISTINCT scc.subcontrato_id ORDER BY scc.subcontrato_id) AS subcontratos_ids,
                               GROUP_CONCAT(DISTINCT COALESCE(pr_a.nombre,'') ORDER BY scc.subcontrato_id) AS proveedores_asignados
                        FROM conceptos c
                        JOIN catalogos cat ON cat.id=c.catalogo_id AND cat.obra_id=?
                        LEFT JOIN subcontrato_conceptos scc ON scc.concepto_id=c.id
                        LEFT JOIN subcontratos sc_a ON sc_a.id=scc.subcontrato_id AND sc_a.obra_id=?
                        LEFT JOIN proveedores pr_a ON pr_a.id=sc_a.proveedor_id
                        GROUP BY c.id,c.codigo_concepto,c.nombre_concepto,c.unidad_medida,c.categoria,c.subcategoria
                        ORDER BY CASE c.categoria
                            WHEN 'I' THEN 1 WHEN 'II' THEN 2 WHEN 'III' THEN 3 WHEN 'IV' THEN 4
                            WHEN 'V' THEN 5 WHEN 'VI' THEN 6 WHEN 'VII' THEN 7 WHEN 'VIII' THEN 8
                            WHEN 'IX' THEN 9 WHEN 'X' THEN 10 ELSE 99 END ASC,
                        CAST(SUBSTRING_INDEX(c.subcategoria,'.',-1) AS UNSIGNED) ASC,
                        CAST(c.numero_original AS UNSIGNED) ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $oid, $oid);
        $stmt->execute();
        echo json_encode(['success' => true, 'conceptos' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
        break;

      case 'crear_subcontrato':
        $oid         = $obra_id;
        $proveedor_id = (int)($_POST['proveedor_id'] ?? 0) ?: null;
        $total       = (float)($_POST['total_estimado'] ?? 0);
        $pct         = (float)($_POST['anticipo_pct'] ?? 0);
        $ant         = round($total * $pct / 100, 2);
        $desc        = trim($_POST['descripcion'] ?? '');
        $stmt = $conn->prepare("INSERT INTO subcontratos (obra_id,proveedor_id,total_estimado,anticipo_pct,anticipo_monto,descripcion) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param("iiddds", $oid, $proveedor_id, $total, $pct, $ant, $desc);
        if ($stmt->execute())
          echo json_encode(['success' => true, 'id' => $conn->insert_id, 'anticipo_monto' => $ant]);
        else
          echo json_encode(['success' => false, 'error' => $stmt->error]);
        break;

      case 'actualizar_subcontrato':
        $id          = (int)($_POST['id'] ?? 0);
        $proveedor_id = (int)($_POST['proveedor_id'] ?? 0) ?: null;
        $total       = (float)($_POST['total_estimado'] ?? 0);
        $pct         = (float)($_POST['anticipo_pct'] ?? 0);
        $ant         = round($total * $pct / 100, 2);
        $desc        = trim($_POST['descripcion'] ?? '');
        $stmt = $conn->prepare("UPDATE subcontratos SET proveedor_id=?,total_estimado=?,anticipo_pct=?,anticipo_monto=?,descripcion=? WHERE id=?");
        $stmt->bind_param("idddsi", $proveedor_id, $total, $pct, $ant, $desc, $id);
        if ($stmt->execute())
          echo json_encode(['success' => true, 'anticipo_monto' => $ant]);
        else
          echo json_encode(['success' => false, 'error' => $stmt->error]);
        break;

      case 'eliminar_subcontrato':
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM subcontratos WHERE id=?");
        $stmt->bind_param("i", $id);
        echo json_encode(['success' => $stmt->execute()]);
        break;

      case 'guardar_conceptos':
        $sc_id = (int)($_POST['subcontrato_id'] ?? 0);
        $cids  = json_decode($_POST['concepto_ids'] ?? '[]', true);
        $conn->begin_transaction();
        try {
          $stmt = $conn->prepare("DELETE FROM subcontrato_conceptos WHERE subcontrato_id=?");
          $stmt->bind_param("i", $sc_id);
          $stmt->execute();
          if (!empty($cids)) {
            $s2 = $conn->prepare("INSERT IGNORE INTO subcontrato_conceptos (subcontrato_id,concepto_id) VALUES (?,?)");
            foreach ($cids as $cid) {
              $cid = (int)$cid;
              if ($cid > 0) {
                $s2->bind_param("ii", $sc_id, $cid);
                $s2->execute();
              }
            }
          }
          $conn->commit();
          echo json_encode(['success' => true, 'total' => count($cids)]);
        } catch (Exception $e) {
          $conn->rollback();
          echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;

      case 'obtener_extraordinarios':
        $sc_id = (int)($_POST['subcontrato_id'] ?? 0);
        $stmt  = $conn->prepare("SELECT id,monto,descripcion,fecha FROM subcontrato_extraordinarios WHERE subcontrato_id=? ORDER BY fecha DESC,id DESC");
        $stmt->bind_param("i", $sc_id);
        $stmt->execute();
        echo json_encode(['success' => true, 'extraordinarios' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
        break;

      case 'agregar_extraordinario':
        $sc_id = (int)($_POST['subcontrato_id'] ?? 0);
        $monto = (float)($_POST['monto'] ?? 0);
        $desc  = trim($_POST['descripcion'] ?? '');
        $fecha = $_POST['fecha'] ?: date('Y-m-d');
        if ($sc_id <= 0 || $monto == 0 || empty($desc)) {
          echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
          exit;
        }
        $stmt = $conn->prepare("INSERT INTO subcontrato_extraordinarios (subcontrato_id,monto,descripcion,fecha) VALUES (?,?,?,?)");
        $stmt->bind_param("idss", $sc_id, $monto, $desc, $fecha);
        if ($stmt->execute()) {
          $st2 = $conn->prepare("SELECT COALESCE(SUM(monto),0) AS total, COUNT(*) AS num FROM subcontrato_extraordinarios WHERE subcontrato_id=?");
          $st2->bind_param("i", $sc_id);
          $st2->execute();
          $t = $st2->get_result()->fetch_assoc();
          echo json_encode(['success' => true, 'id' => $conn->insert_id, 'total_extraordinarios' => $t['total'], 'num_extraordinarios' => $t['num']]);
        } else {
          echo json_encode(['success' => false, 'error' => $stmt->error]);
        }
        break;

      case 'eliminar_extraordinario':
        $id    = (int)($_POST['id'] ?? 0);
        $sc_id = (int)($_POST['subcontrato_id'] ?? 0);
        $stmt  = $conn->prepare("DELETE FROM subcontrato_extraordinarios WHERE id=? AND subcontrato_id=?");
        $stmt->bind_param("ii", $id, $sc_id);
        if ($stmt->execute()) {
          $st2 = $conn->prepare("SELECT COALESCE(SUM(monto),0) AS total, COUNT(*) AS num FROM subcontrato_extraordinarios WHERE subcontrato_id=?");
          $st2->bind_param("i", $sc_id);
          $st2->execute();
          $t = $st2->get_result()->fetch_assoc();
          echo json_encode(['success' => true, 'total_extraordinarios' => $t['total'], 'num_extraordinarios' => $t['num']]);
        } else {
          echo json_encode(['success' => false, 'error' => $stmt->error]);
        }
        break;

      case 'notificar_exceso_subcontratos':
        $oid = (int)($_POST['obra_id'] ?? 0);

        // Obtener datos actuales de la obra y suma de subcontratos
        $stmt = $conn->prepare("SELECT o.id, o.nombre_obra, o.costo_directo,
        COALESCE(SUM(sc.total_estimado), 0) + COALESCE(SUM(ext.total_ext), 0) AS suma_subcontratos
        FROM obras o
        LEFT JOIN subcontratos sc ON sc.obra_id = o.id
        LEFT JOIN (SELECT subcontrato_id, SUM(monto) AS total_ext
                   FROM subcontrato_extraordinarios GROUP BY subcontrato_id) ext
               ON ext.subcontrato_id = sc.id
        WHERE o.id = ?
        GROUP BY o.id");
        $stmt->bind_param("i", $oid);
        $stmt->execute();
        $info = $stmt->get_result()->fetch_assoc();

        if (!$info) {
          echo json_encode(['success' => false, 'error' => 'Obra no encontrada']);
          break;
        }

        // Nombre del usuario en sesión (ajusta según tu sistema de sesiones)
        $usuarioNombre = $_SESSION['nombre_completo']
          ?? ($_SESSION['nombres'] ?? 'Usuario del sistema');

        $datosAlerta = [
          'obra_id'          => $oid,
          'obra_nombre'      => $info['nombre_obra'],
          'costo_directo'    => number_format($info['costo_directo'], 2),
          'suma_subcontratos' => number_format($info['suma_subcontratos'], 2),
          'exceso'           => number_format($info['suma_subcontratos'] - $info['costo_directo'], 2),
          'usuario'          => $usuarioNombre,
        ];

        $enviado = false;

        try {
          $sql_subdirector = "SELECT u.correo_corporativo,
                                   CONCAT(u.nombres, ' ', u.apellidos) AS nombre_completo
                            FROM usuarios u
                            INNER JOIN departamentos d ON u.departamento_id = d.id
                            WHERE d.nombre LIKE '%Subdirec%'
                            AND u.activo = 1
                            AND u.correo_corporativo IS NOT NULL
                            AND u.correo_corporativo != ''";
          $result_subdirector = $conn->query($sql_subdirector);

          if ($result_subdirector && $result_subdirector->num_rows > 0) {
            $emailHandler = new EmailHandler();
            while ($subdirector = $result_subdirector->fetch_assoc()) {
              $emailHandler->enviarAlertaExcesoSubcontratos(
                $subdirector['correo_corporativo'],
                $subdirector['nombre_completo'],
                $datosAlerta
              );
            }
            $enviado = true;
            error_log("Alerta de exceso enviada a Subdirección - Obra ID: {$oid}");
          } else {
            error_log("No se encontró Subdirector activo con correo - Obra ID: {$oid}");
          }
        } catch (Exception $e) {
          error_log("Error al enviar alerta de exceso: " . $e->getMessage());
        }

        echo json_encode([
          'success' => true,
          'enviado' => $enviado,
          'message' => $enviado
            ? 'Notificación enviada a Subdirección'
            : 'Subcontrato guardado. No se encontró Subdirector con correo registrado.'
        ]);
        break;

      default:
        echo json_encode(['success' => false, 'error' => 'Accion no valida']);
    }
  } catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
  }
  $conn->close();
  exit;
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <title>Detalles de Obra - <?= htmlspecialchars($obra['nombre_obra']) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  <link rel="icon" href="/assets/img/LogoCuadro.ico" type="image/x-icon">
  <link rel="stylesheet" href="/assets/styles/details.css">
  <style>
    .sc-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
      gap: 16px
    }

    .sc-card {
      border: 1px solid rgba(0, 0, 0, .09);
      border-radius: 12px;
      overflow: hidden;
      background: #fff;
      transition: box-shadow .2s, transform .2s;
      box-shadow: 0 2px 6px rgba(0, 0, 0, .05)
    }

    .sc-card:hover {
      box-shadow: 0 6px 20px rgba(0, 0, 0, .10);
      transform: translateY(-2px)
    }

    .sc-card-head {
      padding: 14px 16px 12px;
      border-bottom: 1px solid rgba(0, 0, 0, .06);
      display: flex;
      align-items: flex-start;
      gap: 10px
    }

    .sc-avatar {
      width: 38px;
      height: 38px;
      border-radius: 9px;
      background: rgba(63, 117, 85, .12);
      display: flex;
      align-items: center;
      justify-content: center;
      color: #3f7555;
      font-size: 16px;
      flex-shrink: 0
    }

    .sc-name {
      font-size: .88rem;
      font-weight: 700;
      color: #0f172a;
      line-height: 1.3
    }

    .sc-sub {
      font-size: .72rem;
      color: #8fa3b8;
      margin-top: 2px
    }

    .sc-body {
      padding: 12px 16px
    }

    .sc-foot {
      padding: 9px 16px;
      background: rgba(0, 0, 0, .02);
      border-top: 1px solid rgba(0, 0, 0, .05);
      display: flex;
      gap: 6px;
      justify-content: flex-end;
      flex-wrap: wrap
    }

    .monto-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 8px;
      margin-bottom: 10px
    }

    .monto-item {
      text-align: center
    }

    .monto-lbl {
      font-size: .62rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: .06em;
      color: #0f172a;
      margin-bottom: 2px
    }

    .monto-val {
      font-family: 'Outfit', sans-serif;
      font-size: .88rem;
      font-weight: 700;
      color: #0f172a
    }

    .mv-blue {
      color: #1a60a8
    }

    .mv-amber {
      color: #b47800
    }

    .mv-green {
      color: #1a7555
    }

    .mv-red {
      color: #c0392b
    }

    .mv-purple {
      color: #6d28d9
    }

    .prog-wrap {
      height: 5px;
      background: rgba(0, 0, 0, .07);
      border-radius: 99px;
      overflow: hidden;
      margin-top: 4px
    }

    .prog-fill {
      height: 100%;
      border-radius: 99px;
      background: linear-gradient(90deg, #3f7555, #5fbe8a);
      transition: width .6s cubic-bezier(.16, 1, .3, 1)
    }

    .tag-cnt {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      font-size: .68rem;
      font-weight: 600;
      padding: 2px 8px;
      border-radius: 20px;
      background: rgba(63, 117, 85, .1);
      color: #3f7555;
      border: 1px solid rgba(63, 117, 85, .2)
    }

    .tag-ext {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      font-size: .68rem;
      font-weight: 600;
      padding: 2px 8px;
      border-radius: 20px;
      background: rgba(109, 40, 217, .08);
      color: #6d28d9;
      border: 1px solid rgba(109, 40, 217, .2);
      cursor: pointer;
      transition: background .15s
    }

    .tag-ext:hover {
      background: rgba(109, 40, 217, .15)
    }

    /* editor */
    .sc-editor-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(15, 23, 42, .52);
      z-index: 1060;
      align-items: center;
      justify-content: center;
      backdrop-filter: blur(4px)
    }

    .sc-editor-overlay.open {
      display: flex
    }

    .sc-editor-box {
      background: #fff;
      border-radius: 16px;
      width: 96%;
      max-width: 940px;
      max-height: 88vh;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      box-shadow: 0 20px 60px rgba(0, 0, 0, .22);
      animation: scFadeUp .28s cubic-bezier(.16, 1, .3, 1)
    }

    @keyframes scFadeUp {
      from {
        opacity: 0;
        transform: translateY(14px)
      }

      to {
        opacity: 1;
        transform: translateY(0)
      }
    }

    .sc-editor-head {
      padding: 18px 22px 14px;
      border-bottom: 1px solid rgba(0, 0, 0, .07);
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-shrink: 0
    }

    .sc-editor-body {
      flex: 1;
      overflow: hidden;
      display: grid;
      grid-template-columns: 1fr 1fr
    }

    .sc-editor-col {
      display: flex;
      flex-direction: column;
      overflow: hidden;
      padding: 14px
    }

    .sc-editor-col:first-child {
      border-right: 1px solid rgba(0, 0, 0, .07)
    }

    .sc-editor-foot {
      padding: 12px 22px;
      border-top: 1px solid rgba(0, 0, 0, .07);
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-shrink: 0;
      font-size: .75rem;
      color: #8fa3b8
    }

    .dd-head {
      font-size: .72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .07em;
      color: #8fa3b8;
      margin-bottom: 8px;
      display: flex;
      align-items: center;
      gap: 6px
    }

    .dd-cnt {
      background: rgba(0, 0, 0, .07);
      padding: 1px 7px;
      border-radius: 20px;
      font-size: .68rem;
      font-weight: 600
    }

    .dd-cnt.green {
      background: rgba(63, 117, 85, .15);
      color: #3f7555
    }

    .dd-search {
      width: 100%;
      padding: 6px 10px;
      border: 1px solid rgba(0, 0, 0, .1);
      border-radius: 7px;
      font-size: .8rem;
      background: #f8f9fa;
      margin-bottom: 7px;
      font-family: inherit
    }

    .dd-search:focus {
      outline: none;
      border-color: #3f7555
    }

    .dd-zone {
      flex: 1;
      overflow-y: auto;
      padding: 4px;
      border-radius: 8px;
      min-height: 260px;
      max-height: 340px;
      transition: background .15s
    }

    .dd-zone.drag-over {
      background: rgba(63, 117, 85, .06);
      outline: 2px dashed rgba(63, 117, 85, .35);
      outline-offset: -3px
    }

    .concept-chip {
      display: flex;
      align-items: center;
      gap: 7px;
      padding: 7px 9px;
      border-radius: 7px;
      border: 1px solid rgba(0, 0, 0, .07);
      background: #fff;
      cursor: grab;
      margin-bottom: 4px;
      transition: box-shadow .15s, border-color .15s;
      user-select: none;
      font-size: .78rem
    }

    .concept-chip:hover {
      border-color: rgba(63, 117, 85, .35);
      box-shadow: 0 2px 8px rgba(0, 0, 0, .08)
    }

    .concept-chip.dragging {
      opacity: .4
    }

    .concept-chip.already {
      border-color: rgba(109, 40, 217, .25);
      background: rgba(109, 40, 217, .04)
    }

    .chip-cat {
      flex-shrink: 0;
      font-size: .6rem;
      font-weight: 700;
      padding: 2px 5px;
      border-radius: 4px;
      background: rgba(63, 117, 85, .12);
      color: #3f7555;
      white-space: nowrap
    }

    .chip-cod {
      font-size: .65rem;
      color: #8fa3b8;
      font-weight: 600;
      white-space: nowrap;
      flex-shrink: 0
    }

    .chip-nom {
      flex: 1;
      min-width: 0;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      font-weight: 500;
      color: #0f172a
    }

    .chip-um {
      font-size: .62rem;
      color: #8fa3b8;
      flex-shrink: 0
    }

    .chip-also {
      font-size: .62rem;
      color: #6d28d9;
      flex-shrink: 0;
      font-style: italic;
      white-space: nowrap
    }

    .dd-empty {
      text-align: center;
      padding: 30px 10px;
      color: #8fa3b8;
      font-size: .78rem
    }

    .dd-empty i {
      font-size: 1.6rem;
      display: block;
      margin-bottom: 6px;
      opacity: .4
    }

    /* modal sc */
    .sc-modal-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(15, 23, 42, .5);
      z-index: 1070;
      align-items: center;
      justify-content: center;
      backdrop-filter: blur(3px)
    }

    .sc-modal-overlay.open {
      display: flex
    }

    .sc-modal-box {
      background: #fff;
      border-radius: 14px;
      padding: 26px;
      width: 100%;
      max-width: 500px;
      box-shadow: 0 16px 48px rgba(0, 0, 0, .18);
      position: relative;
      animation: scFadeUp .25s cubic-bezier(.16, 1, .3, 1)
    }

    .sc-modal-title {
      font-size: .98rem;
      font-weight: 700;
      color: #0f172a;
      margin-bottom: 16px;
      padding-bottom: 10px;
      border-bottom: 2px solid #3f7555
    }

    .sc-mc {
      position: absolute;
      top: 14px;
      right: 14px;
      background: none;
      border: none;
      color: #8fa3b8;
      font-size: 17px;
      cursor: pointer
    }

    .sc-mc:hover {
      color: #e8445a
    }

    .sc-fg {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px
    }

    .sc-fgi {
      display: flex;
      flex-direction: column;
      gap: 3px
    }

    .sc-fgi.full {
      grid-column: 1/-1
    }

    .sc-lbl {
      font-size: .7rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: .06em;
      color: #8fa3b8;
      margin-bottom: 3px
    }

    .sc-inp {
      background: #f8f9fa;
      border: 1px solid rgba(0, 0, 0, .12);
      border-radius: 7px;
      color: #0f172a;
      padding: 7px 11px;
      font-family: inherit;
      font-size: .84rem;
      transition: border-color .2s;
      width: 100%
    }

    .sc-inp:focus {
      outline: none;
      border-color: #3f7555
    }

    .sc-fa {
      display: flex;
      justify-content: flex-end;
      gap: 8px;
      margin-top: 16px
    }

    .anticipo-preview {
      background: rgba(63, 117, 85, .07);
      border: 1px solid rgba(63, 117, 85, .2);
      border-radius: 7px;
      padding: 8px 12px;
      font-size: .8rem;
      color: #3f7555;
      font-weight: 600;
      margin-top: 4px;
      display: none
    }

    /* extraordinarios */
    .ext-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(15, 23, 42, .5);
      z-index: 1080;
      align-items: center;
      justify-content: center;
      backdrop-filter: blur(3px)
    }

    .ext-overlay.open {
      display: flex
    }

    .ext-box {
      background: #fff;
      border-radius: 14px;
      padding: 0;
      width: 100%;
      max-width: 580px;
      max-height: 85vh;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      box-shadow: 0 16px 48px rgba(0, 0, 0, .18);
      animation: scFadeUp .25s cubic-bezier(.16, 1, .3, 1)
    }

    .ext-head {
      padding: 18px 22px 14px;
      border-bottom: 1px solid rgba(0, 0, 0, .07);
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-shrink: 0
    }

    .ext-head-title {
      font-size: .95rem;
      font-weight: 700;
      color: #0f172a
    }

    .ext-head-sub {
      font-size: .74rem;
      color: #8fa3b8;
      margin-top: 2px
    }

    .ext-body {
      flex: 1;
      overflow-y: auto;
      padding: 18px 22px
    }

    .ext-foot {
      padding: 14px 22px;
      border-top: 1px solid rgba(0, 0, 0, .07);
      flex-shrink: 0
    }

    .ext-form {
      background: #f8f9fa;
      border: 1px solid rgba(0, 0, 0, .08);
      border-radius: 10px;
      padding: 14px;
      margin-bottom: 18px
    }

    .ext-form-title {
      font-size: .74rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .06em;
      color: #3f7555;
      margin-bottom: 10px
    }

    .ext-fg {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px
    }

    .ext-fgi {
      display: flex;
      flex-direction: column;
      gap: 3px
    }

    .ext-fgi.full {
      grid-column: 1/-1
    }

    .ext-list-title {
      font-size: .74rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .06em;
      color: #8fa3b8;
      margin-bottom: 10px
    }

    .ext-item {
      display: flex;
      align-items: flex-start;
      gap: 12px;
      padding: 10px 12px;
      border-radius: 8px;
      border: 1px solid rgba(0, 0, 0, .07);
      background: #fff;
      margin-bottom: 8px
    }

    .ext-item-monto {
      font-family: 'Outfit', sans-serif;
      font-size: .92rem;
      font-weight: 700;
      color: #6d28d9;
      white-space: nowrap;
      flex-shrink: 0;
      min-width: 90px
    }

    .ext-item-info {
      flex: 1;
      min-width: 0
    }

    .ext-item-desc {
      font-size: .82rem;
      font-weight: 500;
      color: #0f172a;
      margin-bottom: 2px
    }

    .ext-item-fecha {
      font-size: .72rem;
      color: #8fa3b8
    }

    .ext-item-del {
      background: none;
      border: none;
      color: #8fa3b8;
      cursor: pointer;
      font-size: .9rem;
      padding: 2px 6px;
      border-radius: 5px;
      transition: color .15s, background .15s;
      flex-shrink: 0
    }

    .ext-item-del:hover {
      color: #e8445a;
      background: rgba(232, 68, 90, .08)
    }

    .ext-total-bar {
      background: rgba(109, 40, 217, .07);
      border: 1px solid rgba(109, 40, 217, .2);
      border-radius: 8px;
      padding: 10px 14px;
      display: flex;
      justify-content: space-between;
      align-items: center
    }

    .ext-total-lbl {
      font-size: .8rem;
      font-weight: 600;
      color: #6d28d9
    }

    .ext-total-val {
      font-family: 'Outfit', sans-serif;
      font-size: 1rem;
      font-weight: 700;
      color: #6d28d9
    }

    /* extraordinarios inline en card */
    .sc-ext-panel {
      margin-top: 10px;
      border-top: 1px solid rgba(109, 40, 217, .12);
      padding-top: 10px;
      display: none
    }

    .sc-ext-panel.open {
      display: block
    }

    .sc-ext-toggle {
      width: 100%;
      background: rgba(109, 40, 217, .06);
      border: 1px solid rgba(109, 40, 217, .15);
      border-radius: 7px;
      padding: 6px 10px;
      font-size: .72rem;
      font-weight: 600;
      color: #6d28d9;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: space-between;
      transition: background .15s;
      font-family: inherit
    }

    .sc-ext-toggle:hover {
      background: rgba(109, 40, 217, .12)
    }

    .sc-ext-toggle i.arrow {
      transition: transform .2s
    }

    .sc-ext-toggle.open i.arrow {
      transform: rotate(180deg)
    }

    .sc-ext-row {
      display: flex;
      align-items: flex-start;
      gap: 10px;
      padding: 7px 0;
      border-bottom: 1px solid rgba(0, 0, 0, .05);
      font-size: .78rem
    }

    .sc-ext-row:last-child {
      border-bottom: none
    }

    .sc-ext-monto {
      font-weight: 700;
      color: #6d28d9;
      white-space: nowrap;
      min-width: 80px;
      font-family: 'Outfit', sans-serif
    }

    .sc-ext-info {
      flex: 1;
      min-width: 0
    }

    .sc-ext-desc {
      color: #0f172a;
      font-weight: 500
    }

    .sc-ext-fecha {
      color: #8fa3b8;
      font-size: .68rem;
      margin-top: 1px
    }

    .sc-ext-del {
      background: none;
      border: none;
      color: #ccc;
      cursor: pointer;
      padding: 2px 5px;
      border-radius: 4px;
      transition: color .15s, background .15s;
      font-size: .78rem
    }

    .sc-ext-del:hover {
      color: #e8445a;
      background: rgba(232, 68, 90, .08)
    }

    .sc-total-real {
      margin-top: 10px;
      background: linear-gradient(90deg, rgba(26, 96, 168, .07), rgba(63, 117, 85, .07));
      border: 1px solid rgba(63, 117, 85, .18);
      border-radius: 8px;
      padding: 8px 12px;
      display: flex;
      justify-content: space-between;
      align-items: center
    }

    .sc-total-real-lbl {
      font-size: .68rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .06em;
      color: #1a7555
    }

    .sc-total-real-val {
      font-family: 'Outfit', sans-serif;
      font-size: .95rem;
      font-weight: 800;
      color: #1a7555
    }

    .sc-desc-badge {
      margin-top: 6px;
      font-size: .73rem;
      color: #475569;
      line-height: 1.4;
      padding: 5px 8px;
      background: rgba(0, 0, 0, .03);
      border-radius: 6px;
      border-left: 2px solid #cbd5e1;
      white-space: pre-wrap;
      word-break: break-word
    }

    /* botones */
    .btn-sc-p {
      background: #3f7555;
      border: none;
      border-radius: 7px;
      color: #fff;
      padding: 8px 18px;
      font-family: inherit;
      font-size: .82rem;
      font-weight: 700;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      transition: opacity .2s
    }

    .btn-sc-p:hover {
      opacity: .88
    }

    .btn-sc-p.purple {
      background: #6d28d9
    }

    .btn-sc-s {
      background: #f8f9fa;
      border: 1px solid rgba(0, 0, 0, .13);
      border-radius: 7px;
      color: #0f172a;
      padding: 8px 18px;
      font-family: inherit;
      font-size: .82rem;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      transition: all .2s
    }

    .btn-sc-s:hover {
      border-color: #3f7555;
      color: #3f7555
    }

    .btn-sc-icon {
      background: none;
      border: 1px solid rgba(0, 0, 0, .11);
      border-radius: 6px;
      color: #8fa3b8;
      padding: 4px 8px;
      cursor: pointer;
      font-size: .8rem;
      transition: all .15s;
      display: inline-flex;
      align-items: center;
      gap: 3px
    }

    .btn-sc-icon:hover {
      border-color: #3f7555;
      color: #3f7555
    }

    .btn-sc-icon.danger:hover {
      border-color: #e8445a;
      color: #e8445a
    }

    .btn-sc-icon.purple:hover {
      border-color: #6d28d9;
      color: #6d28d9
    }

    #sc-toast {
      position: fixed;
      bottom: 22px;
      right: 22px;
      background: #fff;
      border-left: 4px solid #3f7555;
      border-radius: 9px;
      padding: 11px 16px;
      font-size: .82rem;
      font-weight: 500;
      max-width: 320px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, .14);
      z-index: 9999;
      transform: translateY(60px);
      opacity: 0;
      transition: all .3s cubic-bezier(.16, 1, .3, 1);
      pointer-events: none;
      color: #0f172a
    }

    #sc-toast.show {
      transform: translateY(0);
      opacity: 1
    }

    #sc-toast.error {
      border-color: #e8445a
    }

    #sc-toast.success {
      border-color: #3f7555
    }

    #sc-toast.info {
      border-color: #6d28d9
    }

    @keyframes spin {
      to {
        transform: rotate(360deg)
      }
    }
  </style>
</head>

<body>
  <?php include __DIR__ . "/../includes/navbar.php"; ?>

  <!-- HERO SECTION -->
  <div class="hero-section">
    <div class="container hero-content">
      <div class="breadcrumb-custom">
        <a href="/index.php"><i class="bi bi-house-door"></i> Inicio</a>
        <span>/</span>
        <a href="list_obras.php">Registro de Obras</a>
        <span>/</span>
        <span>Detalles de Obra</span>
      </div>

      <div class="row align-items-end">
        <div class="col-lg-8">
          <h3 class="hero-title" style="font-size: 18px"><?= htmlspecialchars($obra['nombre_obra']) ?></h3>
          <div style="color: #ddd; font-size: 14px; margin-top: -5px;">
            <p>Periodo:
              <?= date('d/m/Y', strtotime($obra['fecha_inicio'])) ?>
              -
              <?= date('d/m/Y', strtotime($obra['fecha_fin'])) ?>
            </p>
            <p class="hero-subtitle">#<?= htmlspecialchars($obra['numero_obra']) ?>
              -
              <?= htmlspecialchars($obra['nombre_proyecto']) ?>
            </p>
          </div>
        </div>
        <!-- ACTION BUTTONS -->
        <div class="btn-group" style="gap:5px;">
          <button class="btn-ed" onclick="editarObra(<?= $obra_id ?>)"
            data-bs-toggle="tooltip" data-bs-placement="top" title="Editar Obra">
            <i class="bi bi-pencil"></i>
          </button>

          <button class="btn-inf" onclick="gestionarArchivos(<?= $obra_id ?>)"
            data-bs-toggle="tooltip" data-bs-placement="top" title="Archivos PDF">
            <i class="bi bi-paperclip"></i>
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- MAIN CONTENT -->
  <div class="content-wrapper">

    <!-- BUDGET DASHBOARD -->
    <div class="budget-dashboard">
      <div class="dashboard-header">
        <div class="dashboard-title">
          <div class="title-icon">
            <i class="bi bi-pie-chart"></i>
          </div>
          <h3>Control de Presupuesto</h3>
        </div>
      </div>

      <!-- Estadísticas de Presupuesto -->
      <div class="budget-stats">
        <div class="budget-stat">
          <div class="budget-stat-label">Costo Directo</div>
          <div class="budget-stat-value">$<?= number_format($obra['costo_directo'], 2) ?></div>
        </div>

        <div class="budget-stat">
          <div class="budget-stat-label">Utilizado</div>
          <div class="budget-stat-value">$<?= number_format($obra['costo_directo_utilizado'], 2) ?></div>
        </div>

        <div class="budget-stat">
          <div class="budget-stat-label">Disponible</div>
          <div class="budget-stat-value" style="color: <?= $costo_disponible < 0 ? '#dc3545' : '#198754' ?>">
            $<?= number_format($costo_disponible, 2) ?>
          </div>
        </div>
      </div>

      <!-- INFO PANELS -->
      <div class="info-grid gap-4">

        <!-- Información General -->
        <div class="info-panel">
          <div class="panel-header">
            <div class="panel-icon">
              <i class="bi bi-info-circle"></i>
            </div>
            <h4>Información General</h4>
          </div>

          <ul class="info-list">
            <li class="info-item">
              <span class="info-label">Número de Obra</span>
              <span class="info-value"><?= htmlspecialchars($obra['numero_obra']) ?></span>
            </li>
            <li class="info-item">
              <span class="info-label">Proyecto</span>
              <span class="info-value"><?= htmlspecialchars($obra['nombre_proyecto']) ?></span>
            </li>
            <li class="info-item">
              <span class="info-label">Licitación</span>
              <span class="info-value"><?= htmlspecialchars($obra['numero_licitacion']) ?></span>
            </li>
            <li class="info-item">
              <span class="info-label">Contrato</span>
              <span class="info-value"><?= htmlspecialchars($obra['numero_contrato']) ?></span>
            </li>
            <li class="info-item">
              <span class="info-label">Periodo</span>
              <span class="info-value"><?= date('d/m/Y', strtotime($obra['fecha_inicio'])) ?> - <?= date('d/m/Y', strtotime($obra['fecha_fin'])) ?></span>
            </li>
            <li class="info-item">
              <span class="info-label">Descripción</span>
              <span class="info-value"><?= htmlspecialchars($obra['descripcion']) ?></span>
            </li>
          </ul>
        </div>

        <!-- Información Financiera -->
        <div class="info-panel">
          <div class="panel-header">
            <div class="panel-icon">
              <i class="bi bi-cash-stack"></i>
            </div>
            <h4>Información Financiera</h4>
          </div>

          <ul class="info-list">
            <li class="info-item">
              <span class="info-label">Monto Desginado</span>
              <span class="info-value">$<?= number_format($obra['monto_designado'], 2) ?></span>
            </li>
            <li class="info-item">
              <span class="info-label">Costo Directo</span>
              <span class="info-value">$<?= number_format($obra['costo_directo'], 2) ?></span>
            </li>
          </ul>
        </div>
      </div>

      <!-- Catálogos -->
      <div class="works-section">

        <div class="section-header">
          <div class="section-title-group">
            <h4>Catálogo</h4>
          </div>
        </div>

        <div class="card-body">
          <?php if ($catalogos->num_rows > 0): ?>
            <div class="list-group">
              <?php while ($catalogo = $catalogos->fetch_assoc()): ?>
                <div class="list-group-item">
                  <div class="d-flex justify-content-between align-items-center">
                    <div class="flex-grow-1">
                      <strong><?= htmlspecialchars($catalogo['nombre_catalogo']) ?></strong>
                      <?php if ($catalogo['descripcion']): ?>
                        <br><small class="text-muted"><?= htmlspecialchars($catalogo['descripcion']) ?></small>
                      <?php endif; ?>
                      <br><small class="text-muted">Creado: <?= date('d/m/Y', strtotime($catalogo['fecha_creacion'])) ?></small>
                    </div>
                    <div class="btn-group" style="gap:5px;">
                      <a href="conceptos_view.php?catalogo_id=<?= $catalogo['id'] ?>&catalogo_nombre=<?= urlencode($catalogo['nombre_catalogo']) ?>&obra_id=<?= $obra_id ?>&obra_nombre=<?= urlencode($obra['nombre_obra']) ?>"
                        class="btn-inf"
                        data-bs-toggle="tooltip" data-bs-placement="top" title="Gestionar Conceptos">
                        <i class="bi bi-folder2-open"></i>
                      </a>
                      <button class="btn-ed"
                        onclick="editarCatalogo(<?= $catalogo['id'] ?>)"
                        data-bs-toggle="tooltip" data-bs-placement="top" title="Editar Catálogo">
                        <i class="bi bi-pencil"></i>
                      </button>
                      <button class="btn-del"
                        onclick="eliminarCatalogo(<?= $catalogo['id'] ?>, <?= $obra_id ?>, '<?= htmlspecialchars(addslashes($obra['nombre_obra'])) ?>')"
                        data-bs-toggle="tooltip" data-bs-placement="top" title="Eliminar Catálogo">
                        <i class="bi bi-trash3"></i>
                      </button>
                    </div>
                  </div>
                </div>
              <?php endwhile; ?>
            </div>
          <?php else: ?>
            <div class="text-center text-muted py-4">
              <i class="bi bi-folder" style="font-size: 3rem;"></i>
              <p class="mt-2">No hay catálogos registrados</p>
              <button class="btn btn-success"
                onclick="mostrarFormularioCatalogo(<?= $obra_id ?>, '<?= htmlspecialchars(addslashes($obra['nombre_obra'])) ?>')">
                <i class="bi bi-plus-circle"></i> Crear Primer Catálogo
              </button>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- SUBCONTRATOS -->
      <div class="works-section" style="margin-top:24px;">
        <div class="section-header">
          <div class="section-title-group">
            <h4>Subcontratos</h4>
          </div>
          <button class="btn-sc-p" onclick="scAbrirModal()"><i class="bi bi-plus-lg"></i> Nuevo subcontrato</button>
        </div>
        <div class="row g-2 mb-3" id="sc-stats" style="display:none!important">
          <div class="col-6 col-md-3">
            <div class="budget-stat">
              <div class="budget-stat-label">Subcontratos</div>
              <div class="budget-stat-value" id="sc-stat-total">0</div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="budget-stat">
              <div class="budget-stat-label">Total Monto Contratos</div>
              <div class="budget-stat-value" id="sc-stat-est">$0</div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="budget-stat">
              <div class="budget-stat-label">Total Anticipos</div>
              <div class="budget-stat-value" id="sc-stat-ant">$0</div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="budget-stat">
              <div class="budget-stat-label">Total Pagado</div>
              <div class="budget-stat-value" id="sc-stat-real">$0</div>
            </div>
          </div>
        </div>
        <div id="sc-grid">
          <div class="text-center text-muted py-4">
            <i class="bi bi-arrow-clockwise" style="font-size:1.6rem;display:block;margin-bottom:8px;animation:spin 1s linear infinite;"></i>
            Cargando subcontratos...
          </div>
        </div>
      </div>

    </div>
  </div>

  <!-- FLOATING ACTION BUTTONS -->
  <div class="fab-container-backbtn">
    <a onclick="history.back()" class="fab-button-backbtn">
      <i class="bi bi-arrow-left"></i>
      <span class="fab-tooltip-backbtn">Volver</span>
    </a>
  </div>

  <div id="sc-toast"></div>

  <!-- MODAL Subcontrato -->
  <div class="sc-modal-overlay" id="sc-modal">
    <div class="sc-modal-box">
      <button class="sc-mc" onclick="scCerrarModal()"><i class="bi bi-x-lg"></i></button>
      <div class="sc-modal-title" id="sc-modal-title">Nuevo subcontrato</div>
      <input type="hidden" id="sc-m-id">
      <div class="sc-fg">
        <div class="sc-fgi full">
          <div class="sc-lbl">Proveedor</div><select id="sc-m-proveedor" class="sc-inp">
            <option value="">Selecciona proveedor</option>
          </select>
        </div>
        <div class="sc-fgi">
          <div class="sc-lbl">Total monto de contrato</div><input type="number" id="sc-m-total" class="sc-inp" placeholder="0.00" step="0.01" min="0" oninput="scCalcAnticipo()">
        </div>
        <div class="sc-fgi">
          <div class="sc-lbl">Anticipo (%)</div><input type="number" id="sc-m-pct" class="sc-inp" placeholder="0" step="0.1" min="0" max="100" oninput="scCalcAnticipo()">
        </div>
        <div class="sc-fgi full">
          <div class="anticipo-preview" id="sc-ant-preview"><i class="bi bi-arrow-right-circle me-1"></i>Anticipo en pesos: <strong id="sc-ant-monto">$0.00</strong></div>
        </div>
        <div class="sc-fgi full">
          <div class="sc-lbl">Descripcion del subcontrato</div><textarea id="sc-m-desc" class="sc-inp" rows="2" placeholder="Descripcion general del alcance o trabajos..."></textarea>
        </div>
      </div>
      <div class="sc-fa"><button class="btn-sc-s" onclick="scCerrarModal()">Cancelar</button><button class="btn-sc-p" onclick="scGuardar()"><i class="bi bi-check2"></i> Guardar</button></div>
    </div>
  </div>

  <!-- OVERLAY Editor drag & drop -->
  <div class="sc-editor-overlay" id="sc-editor">
    <div class="sc-editor-box">
      <div class="sc-editor-head">
        <div>
          <div style="font-size:.98rem;font-weight:700;color:#0f172a;" id="sc-ed-title">Asignar conceptos</div>
          <div style="font-size:.76rem;color:#8fa3b8;margin-top:2px;" id="sc-ed-sub">Un concepto puede estar en varios subcontratos de la misma obra</div>
        </div>
        <div style="display:flex;gap:8px;align-items:center;">
          <span class="tag-cnt" id="sc-ed-cnt">0 asignados</span>
          <button class="btn-sc-p" onclick="scGuardarConceptos()" id="sc-btn-guardar-conc"><i class="bi bi-check2"></i> Guardar</button>
          <button class="sc-mc" style="position:relative;top:auto;right:auto;font-size:17px;" onclick="scCerrarEditor()"><i class="bi bi-x-lg"></i></button>
        </div>
      </div>
      <div class="sc-editor-body">
        <div class="sc-editor-col">
          <div class="dd-head"><i class="bi bi-grid-3x3-gap"></i> Conceptos del catalogo <span class="dd-cnt" id="sc-cnt-disp">0</span></div>
          <input class="dd-search" id="sc-search-disp" placeholder="Buscar..." oninput="scFiltrarDisp()">
          <div class="dd-zone" id="sc-zone-disp" ondragover="scOnDragOver(event)" ondrop="scOnDrop(event,'disp')" ondragleave="scOnDragLeave(event)"></div>
        </div>
        <div class="sc-editor-col">
          <div class="dd-head" style="color:#3f7555;"><i class="bi bi-person-check"></i> Asignados al proveedor <span class="dd-cnt green" id="sc-cnt-asig">0</span></div>
          <input class="dd-search" id="sc-search-asig" placeholder="Buscar..." oninput="scFiltrarAsig()">
          <div class="dd-zone" id="sc-zone-asig" ondragover="scOnDragOver(event)" ondrop="scOnDrop(event,'asig')" ondragleave="scOnDragLeave(event)">
            <div class="dd-empty"><i class="bi bi-inbox"></i>Arrastra conceptos aqui</div>
          </div>
        </div>
      </div>
      <div class="sc-editor-foot">
        <span><i class="bi bi-info-circle me-1"></i>Violeta = ya asignado a otro subcontrato de esta obra (puede repetirse)</span>
        <button class="btn-sc-s" onclick="scCerrarEditor()">Cancelar</button>
      </div>
    </div>
  </div>

  <!-- MODAL Extraordinarios -->
  <div class="ext-overlay" id="ext-modal">
    <div class="ext-box">
      <div class="ext-head">
        <div>
          <div class="ext-head-title" id="ext-modal-title">Valores extraordinarios</div>
          <div class="ext-head-sub" id="ext-modal-sub"></div>
        </div>
        <button class="sc-mc" style="position:relative;top:auto;right:auto;" onclick="extCerrar()"><i class="bi bi-x-lg"></i></button>
      </div>
      <div class="ext-body">
        <div class="ext-form">
          <div class="ext-form-title"><i class="bi bi-plus-circle me-1"></i>Agregar valor extraordinario</div>
          <div class="ext-fg">
            <div class="ext-fgi">
              <div class="sc-lbl">Monto</div><input type="number" id="ext-monto" class="sc-inp" placeholder="0.00" step="0.01">
            </div>
            <div class="ext-fgi">
              <div class="sc-lbl">Fecha</div><input type="date" id="ext-fecha" class="sc-inp">
            </div>
            <div class="ext-fgi full">
              <div class="sc-lbl">Descripcion</div><input type="text" id="ext-desc" class="sc-inp" placeholder="Ej: Trabajos adicionales...">
            </div>
          </div>
          <div style="display:flex;justify-content:flex-end;margin-top:10px;">
            <button class="btn-sc-p purple" onclick="extAgregar()"><i class="bi bi-plus-lg"></i> Agregar</button>
          </div>
        </div>
        <div class="ext-list-title">Registros</div>
        <div id="ext-lista">
          <div class="dd-empty"><i class="bi bi-inbox"></i>Sin registros extraordinarios</div>
        </div>
      </div>
      <div class="ext-foot">
        <div class="ext-total-bar">
          <span class="ext-total-lbl"><i class="bi bi-sigma me-1"></i>Total extraordinarios</span>
          <span class="ext-total-val" id="ext-total">$0.00</span>
        </div>
      </div>
    </div>
  </div>

  <!-- MODAL Exceso de costo directo -->
  <div class="sc-modal-overlay" id="sc-modal-exceso">
    <div class="sc-modal-box" style="max-width:520px;">
      <div class="sc-modal-title" style="border-color:#e8445a;color:#c0392b;">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>Subcontratos superan el costo directo
      </div>
      <div style="font-size:.85rem;color:#475569;margin-bottom:14px;" id="sc-exceso-detalle"></div>
      <div style="background:rgba(232,68,90,.06);border:1px solid rgba(232,68,90,.2);border-radius:8px;padding:12px 14px;font-size:.82rem;color:#c0392b;margin-bottom:16px;">
        <i class="bi bi-envelope me-1"></i>
        Se notificará a <strong>Subdirección</strong> por correo electrónico sobre este aumento en el valor de los subcontratos.
      </div>
      <div class="sc-fa">
        <button class="btn-sc-s" onclick="scCancelarExceso()">Cancelar</button>
        <button class="btn-sc-p" style="background:#e8445a;" onclick="scConfirmarExceso()">
          <i class="bi bi-send me-1"></i>Notificar y guardar
        </button>
      </div>
    </div>
  </div>

  <!-- MODAL Exceso de costo directo -->
  <script>
    var scExcesoCallback = null;

    function scMostrarModalExceso(nuevaSuma, costoDirecto, callback) {
      scExcesoCallback = callback;
      var exceso = nuevaSuma - costoDirecto;
      scEl('sc-exceso-detalle').innerHTML =
        '<p>La suma total de subcontratos (<strong>' + scFmt(nuevaSuma) + '</strong>) superará el costo directo de la obra (<strong>' + scFmt(costoDirecto) + '</strong>).</p>' +
        '<p>Exceso: <strong style="color:#c0392b">' + scFmt(exceso) + '</strong></p>';
      scEl('sc-modal-exceso').classList.add('open');
    }

    function scCancelarExceso() {
      scEl('sc-modal-exceso').classList.remove('open');
      scExcesoCallback = null;
    }

    function scConfirmarExceso() {
      scEl('sc-modal-exceso').classList.remove('open');
      // Enviar notificación por correo
      scAjax({
        action: 'notificar_exceso_subcontratos',
        obra_id: SC_OBRA_ID
      }, function(e, r) {
        if (!r.success) scToast('Aviso: no se pudo enviar el correo a subdirección', 'error');
        else scToast('Notificación enviada a subdirección', 'info');
      });
      // Ejecutar el guardado
      if (scExcesoCallback) scExcesoCallback();
      scExcesoCallback = null;
    }
  </script>

  <script>
    // Inicializar tooltips de Bootstrap
    document.addEventListener('DOMContentLoaded', function() {
      var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
      var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
      });
    });

    // Función para editar obra
    function editarObra(id) {
      fetch(`edit_obra.php?id=${id}`)
        .then(res => {
          if (!res.ok) throw new Error('Error al cargar datos de la obra');
          return res.json();
        })
        .then(data => {
          if (data.error) {
            Swal.fire("Error", data.error, "error");
            return;
          }

          Swal.fire({
            title: "Editar Obra",
            html: `
                  <form id="formEditarObra" class="swal-form">
                    <input type="hidden" name="id" value="${data.id}">

                    <div class="mb-2">
                      <label class="form-label">Proyecto</label>
                      <textarea class="form-control" style="height: auto; min-height: 38px; resize: none;" readonly 
                      >${data.nombre_proyecto || 'Proyecto no disponible'}</textarea>
                      <input type="hidden" name="proyecto_id" value="${data.proyecto_id}">
                    </div>

                    <div class="mb-2">
                      <label class="form-label">Número de Obra</label>
                      <input type="text" name="numero_obra" class="form-control" value="${data.numero_obra}" required>
                    </div>

                    <div class="mb-2">
                      <label class="form-label">Nombre de la Obra</label>
                      <input type="text" name="nombre_obra" class="form-control" value="${data.nombre_obra}" required>
                    </div>

                    <div class="mb-2">
                      <label class="form-label">Descripción</label>
                      <textarea name="descripcion" class="form-control" rows="3" placeholder="Describe los objetivos y características de la obra...">${data.descripcion || ''}</textarea>
                    </div>

                    <div class="row">
                      <div class="col-6 mb-2">
                        <label class="form-label">Fecha Inicio</label>
                        <input type="date" name="fecha_inicio" class="form-control" value="${data.fecha_inicio}" required>
                      </div>
                      <div class="col-6 mb-2">
                        <label class="form-label">Fecha Fin</label>
                        <input type="date" name="fecha_fin" class="form-control" value="${data.fecha_fin}" required>
                      </div>
                    </div>

                    <div class="mb-2">
                      <label class="form-label">Monto Designado</label>
                      <input type="number" step="0.01" name="monto_designado" class="form-control" value="${data.monto_designado}" required>
                    </div>

                    <div class="mb-2">
                      <label class="form-label">Costo Directo</label>
                      <input type="number" step="0.01" name="costo_directo" class="form-control" value="${data.costo_directo}" required>
                      <small class="text-muted">Presupuesto disponible para esta obra</small>
                    </div>
                  </form>
                `,
            width: 600,
            focusConfirm: false,
            showCancelButton: true,
            confirmButtonText: "Actualizar",
            cancelButtonText: "Cancelar",
            preConfirm: () => {
              const form = document.getElementById("formEditarObra");
              const formData = new FormData(form);

              return fetch("update_obra.php", {
                  method: "POST",
                  body: formData
                })
                .then(res => res.json())
                .then(resp => {
                  if (resp.status === "success") {
                    Swal.fire("¡Éxito!", "Obra actualizada correctamente", "success")
                      .then(() => location.reload());
                  } else {
                    Swal.showValidationMessage(resp.message || "Error al actualizar la obra");
                  }
                })
                .catch(() => Swal.showValidationMessage("Error de conexión"));
            }
          });
        })
        .catch(error => {
          console.error('Error al cargar datos de la obra:', error);
          Swal.fire('Error', 'No se pudieron cargar los datos de la obra', 'error');
        });
    }

    // Función para gestionar archivos de obra
    function gestionarArchivos(obraId) {
      fetch(`get_archivos_obra.php?obra_id=${obraId}`)
        .then(res => res.json())
        .then(data => {
          let archivosHtml = `
                <div class="mb-3">
                  <form id="formSubirArchivo" enctype="multipart/form-data">
                    <input type="hidden" name="obra_id" value="${obraId}">
                    <div class="mb-2">
                      <label class="form-label">Subir archivo PDF (Máximo 5 archivos)</label>
                      <input type="file" name="archivo" class="form-control" accept=".pdf" required>
                      <small class="text-muted">Tamaño máximo: 10MB</small>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" onclick="subirArchivoObra()">
                      <i class="bi bi-upload"></i> Subir PDF
                    </button>
                  </form>
                </div>
                <hr>
            `;

          if (data.archivos.length > 0) {
            archivosHtml += '<div class="list-group">';
            data.archivos.forEach(archivo => {
              archivosHtml += `
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                          <div>
                            <i class="bi bi-file-pdf text-danger"></i>
                            ${archivo.nombre_archivo}
                            <br>
                            <small class="text-muted">Subido: ${archivo.fecha_subida}</small>
                          </div>
                          <div>
                            <button class="btn btn-sm btn-info" onclick="verPDF('${archivo.ruta_archivo}')">
                              <i class="bi bi-eye"></i> Ver
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="eliminarArchivoObra(${archivo.id}, ${obraId})">
                              <i class="bi bi-trash"></i> Eliminar
                            </button>
                          </div>
                        </div>
                    `;
            });
            archivosHtml += '</div>';
          } else {
            archivosHtml += '<p class="text-muted">No hay archivos adjuntos</p>';
          }

          Swal.fire({
            title: 'Gestión de Archivos PDF',
            html: archivosHtml,
            width: 700,
            showCloseButton: true,
            showConfirmButton: false
          });
        })
        .catch(error => {
          console.error('Error al cargar archivos:', error);
          Swal.fire('Error', 'No se pudieron cargar los archivos', 'error');
        });
    }

    // Función para subir archivo de obra
    function subirArchivoObra() {
      const form = document.getElementById('formSubirArchivo');
      const formData = new FormData(form);

      fetch('upload_archivo_obra.php', {
          method: 'POST',
          body: formData
        })
        .then(res => res.json())
        .then(data => {
          if (data.status === 'success') {
            Swal.fire('¡Éxito!', data.message, 'success')
              .then(() => {
                const obraId = formData.get('obra_id');
                gestionarArchivos(obraId);
              });
          } else {
            Swal.fire('Error', data.message, 'error');
          }
        })
        .catch(error => {
          console.error('Error:', error);
          Swal.fire('Error', 'Error al subir el archivo', 'error');
        });
    }

    // Función para eliminar archivo de obra
    function eliminarArchivoObra(archivoId, obraId) {
      Swal.fire({
        title: '¿Eliminar archivo?',
        text: 'Esta acción no se puede deshacer',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545'
      }).then((result) => {
        if (result.isConfirmed) {
          fetch('delete_archivo_obra.php', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json'
              },
              body: JSON.stringify({
                id: archivoId
              })
            })
            .then(res => res.json())
            .then(data => {
              if (data.status === 'success') {
                Swal.fire('¡Eliminado!', data.message, 'success')
                  .then(() => gestionarArchivos(obraId));
              } else {
                Swal.fire('Error', data.message, 'error');
              }
            })
            .catch(error => {
              console.error('Error:', error);
              Swal.fire('Error', 'Error al eliminar el archivo', 'error');
            });
        }
      });
    }

    // Función para ver PDF
    function verPDF(rutaArchivo) {
      window.open(rutaArchivo, '_blank');
    }

    // Función para gestionar catálogos (necesaria para el botón)
    function gestionarCatalogos(obraId, obraNombre) {
      // Esta función debe estar definida en catalogo-obras.js
      if (typeof gestionarCatalogos === 'function') {
        gestionarCatalogos(obraId, obraNombre);
      } else {
        console.error('Función gestionarCatalogos no disponible');
        // Recargar la página como fallback
        location.reload();
      }
    }
  </script>

  <!-- Cargar subcontratos al cargar la página -->
  <script>
    var SC_OBRA_ID = <?= (int)$obra_id ?>;
    var scLista = [],
      scProveedores = [],
      scTodosConc = [],
      scEditorId = null,
      scDisp = [],
      scAsig = [],
      scDragId = null;
    var extScId = null,
      extLista = [];
    var scFmt = function(n) {
      return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN'
      }).format(n || 0);
    };
    var scEl = function(id) {
      return document.getElementById(id);
    };

    function scToast(msg, type) {
      type = type || 'success';
      var t = scEl('sc-toast');
      t.textContent = msg;
      t.className = 'show ' + type;
      clearTimeout(t._t);
      t._t = setTimeout(function() {
        t.classList.remove('show');
      }, 3200);
    }

    function scAjax(data, cb) {
      var fd = new FormData();
      Object.keys(data).forEach(function(k) {
        fd.append(k, data[k] != null ? data[k] : '');
      });
      fetch('details_obra.php?id=' + SC_OBRA_ID, {
          method: 'POST',
          body: fd
        })
        .then(function(r) {
          return r.json();
        }).then(function(r) {
          cb(null, r);
        })
        .catch(function() {
          cb(null, {
            success: false,
            error: 'Error de red'
          });
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
      if (!SC_OBRA_ID) return;
      scEl('ext-fecha').value = new Date().toISOString().split('T')[0];
      scAjax({
        action: 'obtener_proveedores'
      }, function(e, rP) {
        if (rP.success) {
          scProveedores = rP.proveedores;
          scPoblarSelect();
        }
        scAjax({
          action: 'obtener_conceptos_obra',
          obra_id: SC_OBRA_ID
        }, function(e2, rC) {
          if (rC.success) scTodosConc = rC.conceptos;
          scCargar();
        });
      });
    });

    function scPoblarSelect() {
      var sel = scEl('sc-m-proveedor');
      sel.innerHTML = '<option value="">Selecciona proveedor</option>';
      scProveedores.forEach(function(p) {
        var o = document.createElement('option');
        o.value = p.id;
        o.textContent = p.nombre;
        sel.appendChild(o);
      });
    }

    function scCargar() {
      scAjax({
        action: 'obtener_subcontratos',
        obra_id: SC_OBRA_ID
      }, function(e, r) {
        if (!r.success) {
          scToast('Error al cargar subcontratos', 'error');
          return;
        }
        scLista = r.subcontratos;
        scRender();
        scStats();
      });
    }

    function scRender() {
      var wrap = scEl('sc-grid');
      if (!scLista.length) {
        wrap.innerHTML = '<div class="text-center text-muted py-4"><i class="bi bi-people" style="font-size:2rem;display:block;margin-bottom:8px;opacity:.3"></i><p>Sin subcontratos registrados.</p></div>';
        return;
      }
      var html = '<div class="sc-grid">';
      scLista.forEach(function(sc) {
        var numExt = parseInt(sc.num_extraordinarios) || 0;
        var totalReal = parseFloat(sc.monto_real || 0);
        var anticipo = parseFloat(sc.anticipo_monto || 0);
        var totalEstimado = parseFloat(sc.total_estimado || 0);
        var extraordinarios = parseFloat(sc.total_extraordinarios || 0);
        // Importe Total = (Monto de contrato + total extraordinarios)
        var totalConExtraordinarios = (totalEstimado + extraordinarios) || 0;
        // Monto por pagar = Importe total - monto_real (OC)
        var porPagar = totalConExtraordinarios - totalReal;
        var descHtml = sc.descripcion ? '<div class="sc-desc-badge"><i class="bi bi-chat-left-text me-1" style="opacity:.5"></i>' + escHtml(sc.descripcion) + '</div>' : '';
        html += '<div class="sc-card" id="sc-card-' + sc.id + '">';
        html += '<div class="sc-card-head"><div class="sc-avatar"><i class="bi bi-person-gear"></i></div>';
        html += '<div style="flex:1;min-width:0"><div class="sc-name">' + (sc.proveedor_nombre || '<em style="opacity:.5">Sin proveedor</em>') + '</div>';
        html += '<div class="sc-sub" style="display:flex;gap:6px;flex-wrap:wrap;margin-top:4px;">';
        html += '<span class="tag-cnt"><i class="bi bi-list-check"></i> ' + sc.total_conceptos + ' conceptos</span>';
        if (numExt > 0) html += '<span class="tag-ext" onclick="scToggleExt(' + sc.id + ')"><i class="bi bi-star-fill"></i> ' + numExt + ' extraordinario' + (numExt > 1 ? 's' : '') + '</span>';
        html += '</div></div></div>';
        html += '<div class="sc-body">';
        if (descHtml) html += descHtml;
        html += '<div class="monto-grid" style="margin-top:8px;">';
        html += '<div class="monto-item"><div class="monto-lbl">Monto de contrato</div><div class="monto-val mv-blue">' + scFmt(totalEstimado) + '</div></div>';
        html += '<div class="monto-item"><div class="monto-lbl">Anticipo ' + sc.anticipo_pct + '%</div><div class="monto-val mv-amber">' + scFmt(anticipo) + '</div></div>';
        html += '<div class="monto-item"><div class="monto-lbl">Total extraordinarios</div><div class="ext-total-val">' + scFmt(extraordinarios) + '</div></div>';
        html += '<div class="monto-item"><div class="monto-lbl">Utilizado</div><div class="monto-val mv-red">' + scFmt(totalReal) + '</div></div>';
        html += '<div class="monto-item"><div class="monto-lbl">Importe total</div><div class="sc-total-real-val">' + scFmt(totalConExtraordinarios) + '</div></div>';
        html += '</div>';
        html += '<div class="sc-total-real"><span class="monto-lbl"><i class="bi bi-sigma me-1"></i>Por pagar</span><span>' + scFmt(porPagar) + '</span></div>';
        if (numExt > 0) {
          html += '<div class="sc-ext-panel" id="sc-ext-' + sc.id + '">';
          html += '<div class="ext-list-title" style="margin-bottom:6px;font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#8fa3b8;">Detalle extraordinarios</div>';
          html += '<div id="sc-ext-rows-' + sc.id + '"><div class="dd-empty" style="padding:12px;font-size:.75rem;"><i class="bi bi-arrow-clockwise"></i> Cargando...</div></div>';
          html += '</div>';
        }
        html += '</div>';
        html += '<div class="sc-foot">';
        html += '<button class="btn-sc-icon purple" onclick="extAbrir(' + sc.id + ')" title="Agregar/gestionar extraordinarios"><i class="bi bi-star"></i> Extraordinarios</button>';
        html += '<button class="btn-sc-icon" onclick="scAbrirEditor(' + sc.id + ')" title="Conceptos"><i class="bi bi-list-check"></i> Conceptos</button>';
        html += '<button class="btn-sc-icon" onclick="scEditar(' + sc.id + ')" title="Editar"><i class="bi bi-pencil"></i></button>';
        html += '<button class="btn-sc-icon danger" onclick="scEliminar(' + sc.id + ')" title="Eliminar"><i class="bi bi-trash3"></i></button>';
        html += '</div></div>';
      });
      html += '</div>';
      wrap.innerHTML = html;
      scLista.forEach(function(sc) {
        if (parseInt(sc.num_extraordinarios) > 0) scCargarExtInline(sc.id);
      });
    }

    function escHtml(s) {
      var d = document.createElement('div');
      d.textContent = s;
      return d.innerHTML;
    }

    function scToggleExt(scId) {
      var panel = scEl('sc-ext-' + scId);
      if (!panel) return;
      panel.classList.toggle('open');
    }

    function scCargarExtInline(scId) {
      scAjax({
        action: 'obtener_extraordinarios',
        subcontrato_id: scId
      }, function(e, r) {
        var rows = scEl('sc-ext-rows-' + scId);
        if (!rows) return;
        if (!r.success || !r.extraordinarios.length) {
          rows.innerHTML = '<div style="font-size:.75rem;color:#8fa3b8;padding:4px 0;">Sin registros aun</div>';
          return;
        }
        rows.innerHTML = r.extraordinarios.map(function(x) {
          var fd = x.fecha ? new Date(x.fecha + 'T00:00:00').toLocaleDateString('es-MX', {
            day: '2-digit',
            month: 'short',
            year: 'numeric'
          }) : '';
          return '<div class="sc-ext-row">' +
            '<div class="sc-ext-monto">+' + scFmt(x.monto) + '</div>' +
            '<div class="sc-ext-info"><div class="sc-ext-desc">' + escHtml(x.descripcion) + '</div><div class="sc-ext-fecha">' + fd + '</div></div>' +
            '<button class="sc-ext-del" onclick="extEliminarInline(' + x.id + ',' + scId + ')" title="Eliminar"><i class="bi bi-trash3"></i></button>' +
            '</div>';
        }).join('');
      });
    }

    function extEliminarInline(id, scId) {
      if (!confirm('Eliminar este extraordinario?')) return;
      scAjax({
        action: 'eliminar_extraordinario',
        id: id,
        subcontrato_id: scId
      }, function(e, r) {
        if (!r.success) {
          scToast(r.error || 'Error', 'error');
          return;
        }
        scToast('Extraordinario eliminado');
        var sc = scLista.find(function(x) {
          return x.id == scId;
        });
        if (sc) {
          sc.total_extraordinarios = r.total_extraordinarios;
          sc.num_extraordinarios = r.num_extraordinarios;
        }
        scRender();
      });
    }

    function scStats() {
      var wrap = scEl('sc-stats');
      if (!scLista.length) {
        wrap.style.setProperty('display', 'none', 'important');
        return;
      }
      wrap.style.setProperty('display', 'flex', 'important');
      scEl('sc-stat-total').textContent = scLista.length;
      scEl('sc-stat-est').textContent = scFmt(scLista.reduce(function(s, x) {
        return s + (parseFloat(x.total_estimado) || 0) + (parseFloat(x.total_extraordinarios) || 0);
      }, 0));
      scEl('sc-stat-ant').textContent = scFmt(scLista.reduce(function(s, x) {
        return s + parseFloat(x.anticipo_monto || 0);
      }, 0));
      scEl('sc-stat-real').textContent = scFmt(scLista.reduce(function(s, x) {
        return s + parseFloat(x.monto_real || 0);
      }, 0));
    }

    function scAbrirModal() {
      scEl('sc-m-id').value = '';
      scEl('sc-modal-title').textContent = 'Nuevo subcontrato';
      scEl('sc-m-proveedor').value = '';
      scEl('sc-m-total').value = '';
      scEl('sc-m-pct').value = '';
      scEl('sc-m-desc').value = '';
      scEl('sc-ant-preview').style.display = 'none';
      scEl('sc-modal').classList.add('open');
    }

    function scEditar(id) {
      var sc = scLista.find(function(x) {
        return x.id == id;
      });
      if (!sc) return;
      scEl('sc-m-id').value = sc.id;
      scEl('sc-modal-title').textContent = 'Editar subcontrato';
      scEl('sc-m-proveedor').value = sc.proveedor_id || '';
      scEl('sc-m-total').value = sc.total_estimado;
      scEl('sc-m-pct').value = sc.anticipo_pct;
      scEl('sc-m-desc').value = sc.descripcion || '';
      scCalcAnticipo();
      scEl('sc-modal').classList.add('open');
    }

    function scCerrarModal() {
      scEl('sc-modal').classList.remove('open');
    }

    function scCalcAnticipo() {
      var total = parseFloat(scEl('sc-m-total').value) || 0;
      var pct = parseFloat(scEl('sc-m-pct').value) || 0;
      var prev = scEl('sc-ant-preview');
      if (total > 0 && pct > 0) {
        prev.style.display = 'block';
        scEl('sc-ant-monto').textContent = scFmt(total * pct / 100);
      } else prev.style.display = 'none';
    }

    function scGuardar() {
      var id = scEl('sc-m-id').value;
      var proveedor_id = scEl('sc-m-proveedor').value;
      var total = parseFloat(scEl('sc-m-total').value) || 0;
      var pct = parseFloat(scEl('sc-m-pct').value) || 0;
      var desc = scEl('sc-m-desc').value;

      // Calcular suma total de subcontratos existentes (excluyendo el que se edita)
      var sumaActual = scLista.reduce(function(s, x) {
        if (id && x.id == id) return s; // excluir el que se está editando
        return s + parseFloat(x.total_estimado || 0) + parseFloat(x.total_extraordinarios || 0);
      }, 0);

      var nuevaSuma = sumaActual + total;
      var costoDirecto = <?= (float)$obra['costo_directo'] ?>;

      if (nuevaSuma > costoDirecto) {
        // Mostrar modal de notificación antes de guardar
        scMostrarModalExceso(nuevaSuma, costoDirecto, function() {
          // El usuario confirmó, continuar guardando
          scEjecutarGuardar(id, proveedor_id, total, pct, desc);
        });
      } else {
        scEjecutarGuardar(id, proveedor_id, total, pct, desc);
      }
    }

    function scEjecutarGuardar(id, proveedor_id, total, pct, desc) {
      var payload = id ?
        {
          action: 'actualizar_subcontrato',
          id: id,
          proveedor_id: proveedor_id,
          total_estimado: total,
          anticipo_pct: pct,
          descripcion: desc
        } :
        {
          action: 'crear_subcontrato',
          obra_id: SC_OBRA_ID,
          proveedor_id: proveedor_id,
          total_estimado: total,
          anticipo_pct: pct,
          descripcion: desc
        };
      scAjax(payload, function(e, r) {
        if (!r.success) {
          scToast(r.error || 'Error al guardar', 'error');
          return;
        }
        scToast(id ? 'Subcontrato actualizado' : 'Subcontrato creado');
        scCerrarModal();
        scCargar();
      });
    }

    function scEliminar(id) {
      var sc = scLista.find(function(x) {
        return x.id == id;
      });
      if (!sc) return;
      Swal.fire({
          title: 'Eliminar subcontrato?',
          text: 'Se eliminaran los conceptos asignados y los valores extraordinarios.',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Si, eliminar',
          cancelButtonText: 'Cancelar',
          confirmButtonColor: '#dc3545'
        })
        .then(function(conf) {
          if (!conf.isConfirmed) return;
          scAjax({
            action: 'eliminar_subcontrato',
            id: id
          }, function(e, r) {
            if (r.success) {
              scToast('Subcontrato eliminado');
              scCargar();
            } else scToast(r.error || 'Error al eliminar', 'error');
          });
        });
    }

    /* EDITOR */
    function scAbrirEditor(id) {
      var sc = scLista.find(function(x) {
        return x.id == id;
      });
      if (!sc) return;
      scEditorId = id;
      scEl('sc-ed-title').textContent = 'Conceptos - ' + (sc.proveedor_nombre || 'Sin proveedor');
      scAjax({
        action: 'obtener_conceptos_obra',
        obra_id: SC_OBRA_ID
      }, function(e, r) {
        if (!r.success) {
          scToast('Error al cargar conceptos', 'error');
          return;
        }
        scTodosConc = r.conceptos;
        scAsig = scTodosConc.filter(function(c) {
          if (!c.subcontratos_ids) return false;
          return c.subcontratos_ids.split(',').indexOf(String(id)) !== -1;
        });
        scDisp = scTodosConc.filter(function(c) {
          if (!c.subcontratos_ids) return true;
          return c.subcontratos_ids.split(',').indexOf(String(id)) === -1;
        });
        scEl('sc-search-disp').value = '';
        scEl('sc-search-asig').value = '';
        scRenderZonas();
        scEl('sc-editor').classList.add('open');
      });
    }

    function scCerrarEditor() {
      scEl('sc-editor').classList.remove('open');
      scEditorId = null;
    }

    function scRenderZonas() {
      scRenderZona('sc-zone-disp', scDisp, scEl('sc-search-disp').value, false);
      scRenderZona('sc-zone-asig', scAsig, scEl('sc-search-asig').value, true);
      scEl('sc-cnt-disp').textContent = scDisp.length;
      scEl('sc-cnt-asig').textContent = scAsig.length;
      scEl('sc-ed-cnt').textContent = scAsig.length + ' asignados';
    }

    function scRenderZona(zoneId, lista, filtro, esAsig) {
      var zone = scEl(zoneId);
      var q = filtro.toLowerCase().trim();
      var vis = q ? lista.filter(function(c) {
        return c.nombre_concepto.toLowerCase().indexOf(q) !== -1 || c.codigo_concepto.toLowerCase().indexOf(q) !== -1;
      }) : lista;
      if (!vis.length) {
        zone.innerHTML = '<div class="dd-empty"><i class="bi bi-' + (esAsig ? 'inbox' : 'check2-all') + '"></i>' + (esAsig ? 'Arrastra conceptos aqui' : 'Sin conceptos disponibles') + '</div>';
        return;
      }
      zone.innerHTML = vis.map(function(c) {
        var enOtro = !esAsig && c.subcontratos_ids && c.subcontratos_ids.split(',').some(function(sid) {
          return sid !== '' && parseInt(sid) !== scEditorId;
        });
        var alsoLabel = enOtro ? '<span class="chip-also"><i class="bi bi-diagram-2"></i> tambien en otro</span>' : '';
        return '<div class="concept-chip' + (enOtro ? ' already' : '') + '" draggable="true" data-id="' + c.id + '"' +
          ' ondragstart="scOnDragStart(event,' + c.id + ')" ondragend="scOnDragEnd(event)"' +
          ' ondblclick="scMoverConcepto(' + c.id + ',\'' + (esAsig ? 'disp' : 'asig') + '\')" title="Doble clic para mover">' +
          '<span class="chip-cod">' + c.codigo_concepto + '</span>' +
          '<span class="chip-um">' + (c.unidad_medida || '') + '</span>' +
          '<span class="chip-name">' + c.cantidad + '</span>' +
          alsoLabel + '</div>';
      }).join('');
    }

    function scOnDragStart(e, id) {
      scDragId = id;
      e.target.classList.add('dragging');
      e.dataTransfer.effectAllowed = 'move';
    }

    function scOnDragEnd(e) {
      e.target.classList.remove('dragging');
      scDragId = null;
    }

    function scOnDragOver(e) {
      e.preventDefault();
      e.currentTarget.classList.add('drag-over');
    }

    function scOnDragLeave(e) {
      e.currentTarget.classList.remove('drag-over');
    }

    function scOnDrop(e, destino) {
      e.preventDefault();
      e.currentTarget.classList.remove('drag-over');
      if (!scDragId) return;
      scMoverConcepto(scDragId, destino);
    }

    function scMoverConcepto(id, destino) {
      id = parseInt(id);
      if (destino === 'asig') {
        var idx = scDisp.findIndex(function(c) {
          return c.id === id;
        });
        if (idx === -1) return;
        var m = scDisp.splice(idx, 1)[0];
        scAsig.push(m);
      } else {
        var idx2 = scAsig.findIndex(function(c) {
          return c.id === id;
        });
        if (idx2 === -1) return;
        var m2 = scAsig.splice(idx2, 1)[0];
        scDisp.push(m2);
      }
      scRenderZonas();
    }

    function scFiltrarDisp() {
      scRenderZona('sc-zone-disp', scDisp, scEl('sc-search-disp').value, false);
    }

    function scFiltrarAsig() {
      scRenderZona('sc-zone-asig', scAsig, scEl('sc-search-asig').value, true);
    }

    function scGuardarConceptos() {
      if (!scEditorId) {
        scToast('Sin subcontrato activo', 'error');
        return;
      }
      var btn = scEl('sc-btn-guardar-conc');
      btn.disabled = true;
      scAjax({
          action: 'guardar_conceptos',
          subcontrato_id: scEditorId,
          concepto_ids: JSON.stringify(scAsig.map(function(c) {
            return c.id;
          }))
        },
        function(e, r) {
          btn.disabled = false;
          if (r.success) {
            scToast(r.total + ' conceptos guardados');
            scCerrarEditor();
            scCargar();
          } else scToast(r.error || 'Error al guardar', 'error');
        });
    }

    /* EXTRAORDINARIOS */
    function extAbrir(scId) {
      extScId = scId;
      var sc = scLista.find(function(x) {
        return x.id == scId;
      });
      scEl('ext-modal-title').textContent = 'Extraordinarios - ' + (sc ? (sc.proveedor_nombre || 'Sin proveedor') : '');
      scEl('ext-modal-sub').textContent = sc && sc.descripcion ? sc.descripcion : '';
      scEl('ext-monto').value = '';
      scEl('ext-desc').value = '';
      scEl('ext-fecha').value = new Date().toISOString().split('T')[0];
      extCargar();
      scEl('ext-modal').classList.add('open');
    }

    function extCerrar() {
      scEl('ext-modal').classList.remove('open');
      extScId = null;
    }

    function extCargar() {
      scAjax({
        action: 'obtener_extraordinarios',
        subcontrato_id: extScId
      }, function(e, r) {
        if (!r.success) {
          scToast('Error al cargar', 'error');
          return;
        }
        extLista = r.extraordinarios;
        extRender();
      });
    }

    function extRender() {
      var lista = scEl('ext-lista');
      var total = extLista.reduce(function(s, x) {
        return s + parseFloat(x.monto || 0);
      }, 0);
      scEl('ext-total').textContent = scFmt(total);
      if (!extLista.length) {
        lista.innerHTML = '<div class="dd-empty"><i class="bi bi-inbox"></i>Sin registros extraordinarios</div>';
        return;
      }
      lista.innerHTML = extLista.map(function(x) {
        var fd = x.fecha ? new Date(x.fecha + 'T00:00:00').toLocaleDateString('es-MX', {
          day: '2-digit',
          month: 'short',
          year: 'numeric'
        }) : '';
        return '<div class="ext-item">' +
          '<div class="ext-item-monto">' + (parseFloat(x.monto) >= 0 ? '+' : '') + scFmt(x.monto) + '</div>' +
          '<div class="ext-item-info"><div class="ext-item-desc">' + x.descripcion + '</div><div class="ext-item-fecha">' + fd + '</div></div>' +
          '<button class="ext-item-del" onclick="extEliminar(' + x.id + ')" title="Eliminar"><i class="bi bi-trash3"></i></button>' +
          '</div>';
      }).join('');
    }

    function extAgregar() {
      var monto = parseFloat(scEl('ext-monto').value) || 0;
      var desc = scEl('ext-desc').value.trim();
      var fecha = scEl('ext-fecha').value;
      if (!monto) {
        scToast('Ingresa un monto', 'error');
        return;
      }
      if (!desc) {
        scToast('Ingresa una descripcion', 'error');
        return;
      }
      if (!fecha) {
        scToast('Ingresa una fecha', 'error');
        return;
      }
      scAjax({
          action: 'agregar_extraordinario',
          subcontrato_id: extScId,
          monto: monto,
          descripcion: desc,
          fecha: fecha
        },
        function(e, r) {
          if (!r.success) {
            scToast(r.error || 'Error al agregar', 'error');
            return;
          }
          scToast('Extraordinario registrado', 'success');
          scEl('ext-monto').value = '';
          scEl('ext-desc').value = '';
          var sc = scLista.find(function(x) {
            return x.id == extScId;
          });
          if (sc) {
            sc.total_extraordinarios = r.total_extraordinarios;
            sc.num_extraordinarios = r.num_extraordinarios;
          }
          extCargar();
          scRender();
        });
    }

    function extEliminar(id) {
      Swal.fire({
          title: 'Eliminar este registro?',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Si, eliminar',
          confirmButtonColor: '#dc3545'
        })
        .then(function(conf) {
          if (!conf.isConfirmed) return;
          scAjax({
            action: 'eliminar_extraordinario',
            id: id,
            subcontrato_id: extScId
          }, function(e, r) {
            if (!r.success) {
              scToast(r.error || 'Error', 'error');
              return;
            }
            scToast('Registro eliminado');
            var sc = scLista.find(function(x) {
              return x.id == extScId;
            });
            if (sc) {
              sc.total_extraordinarios = r.total_extraordinarios;
              sc.num_extraordinarios = r.num_extraordinarios;
              scRender();
            }
            extCargar();
          });
        });
    }
  </script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
  <script src="/assets/scripts/catalogo-obras.js"></script>

  <?php include __DIR__ . "/../includes/footer.php"; ?>

</body>

</html>