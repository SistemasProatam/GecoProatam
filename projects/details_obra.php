<?php
// details_obra.php
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";
require_once __DIR__ . "/../EmailHandler.php";
$emailHandler = new EmailHandler();
checkSession();
preventCaching();

require_once __DIR__ . "/../conexion.php";

$obra_id = $_GET['id'] ?? 0;

if ($obra_id <= 0) {
  header("Location: list_obras.php");
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
  header("Location: list_obras.php");
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

// AJAX handler
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
        $oid = $obra_id;
        $proveedor_id = (int)($_POST['proveedor_id'] ?? 0) ?: null;
        $total = (float)($_POST['total_estimado'] ?? 0);
        $pct = (float)($_POST['anticipo_pct'] ?? 0);
        $ant = round($total * $pct / 100, 2);
        $desc = trim($_POST['descripcion'] ?? '');
        $stmt = $conn->prepare("INSERT INTO subcontratos (obra_id,proveedor_id,total_estimado,anticipo_pct,anticipo_monto,descripcion) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param("iiddds", $oid, $proveedor_id, $total, $pct, $ant, $desc);
        if ($stmt->execute())
          echo json_encode(['success' => true, 'id' => $conn->insert_id, 'anticipo_monto' => $ant]);
        else
          echo json_encode(['success' => false, 'error' => $stmt->error]);
        break;

      case 'actualizar_subcontrato':
        $id = (int)($_POST['id'] ?? 0);
        $proveedor_id = (int)($_POST['proveedor_id'] ?? 0) ?: null;
        $total = (float)($_POST['total_estimado'] ?? 0);
        $pct = (float)($_POST['anticipo_pct'] ?? 0);
        $ant = round($total * $pct / 100, 2);
        $desc = trim($_POST['descripcion'] ?? '');
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
        $stmt = $conn->prepare("SELECT o.id, o.nombre_obra, o.costo_directo,
        COALESCE(SUM(sc.total_estimado), 0) + COALESCE(SUM(ext.total_ext), 0) AS suma_subcontratos
        FROM obras o
        LEFT JOIN subcontratos sc ON sc.obra_id = o.id
        LEFT JOIN (SELECT subcontrato_id, SUM(monto) AS total_ext FROM subcontrato_extraordinarios GROUP BY subcontrato_id) ext ON ext.subcontrato_id = sc.id
        WHERE o.id = ? GROUP BY o.id");
        $stmt->bind_param("i", $oid);
        $stmt->execute();
        $info = $stmt->get_result()->fetch_assoc();
        if (!$info) { echo json_encode(['success' => false, 'error' => 'Obra no encontrada']); break; }
        $usuarioNombre = $_SESSION['nombre_completo'] ?? ($_SESSION['nombres'] ?? 'Usuario del sistema');
        $datosAlerta = [
          'obra_id' => $oid, 'obra_nombre' => $info['nombre_obra'], 'costo_directo' => number_format($info['costo_directo'], 2),
          'suma_subcontratos' => number_format($info['suma_subcontratos'], 2), 'exceso' => number_format($info['suma_subcontratos'] - $info['costo_directo'], 2),
          'usuario' => $usuarioNombre
        ];
        $enviado = false;
        try {
          $sql_subdirector = "SELECT u.correo_corporativo, CONCAT(u.nombres, ' ', u.apellidos) AS nombre_completo FROM usuarios u INNER JOIN departamentos d ON u.departamento_id = d.id WHERE d.nombre LIKE '%Subdirec%' AND u.activo = 1 AND u.correo_corporativo IS NOT NULL AND u.correo_corporativo != ''";
          $result_subdirector = $conn->query($sql_subdirector);
          if ($result_subdirector && $result_subdirector->num_rows > 0) {
            $emailHandler = new EmailHandler();
            while ($subdirector = $result_subdirector->fetch_assoc()) {
              $emailHandler->enviarAlertaExcesoSubcontratos($subdirector['correo_corporativo'], $subdirector['nombre_completo'], $datosAlerta);
            }
            $enviado = true;
          }
        } catch (Exception $e) {}
        echo json_encode(['success' => true, 'enviado' => $enviado, 'message' => $enviado ? 'Notificación enviada' : 'No se encontró subdirector']);
        break;

      default:
        echo json_encode(['success' => false, 'error' => 'Acción no válida']);
    }
  } catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
  }
  $conn->close();
  exit;
}
?>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/orders-common.css?v=1.5">
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

<style>
  /* Estilos específicos para subcontratos en esta página */
  .sc-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 16px; }
  .sc-card { border: 1px solid rgba(0,0,0,.09); border-radius: 12px; overflow: hidden; background: #fff; transition: box-shadow .2s, transform .2s; box-shadow: 0 2px 6px rgba(0,0,0,.05); }
  .sc-card:hover { box-shadow: 0 6px 20px rgba(0,0,0,.1); transform: translateY(-2px); }
  .sc-card-head { padding: 14px 16px 12px; border-bottom: 1px solid rgba(0,0,0,.06); display: flex; align-items: flex-start; gap: 10px; }
  .sc-avatar { width: 38px; height: 38px; border-radius: 9px; background: var(--gray-600, #4b5563); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 16px; flex-shrink: 0; }
  .sc-name { font-size: .88rem; font-weight: 700; color: #0f172a; line-height: 1.3; }
  .sc-sub { font-size: .72rem; color: #8fa3b8; margin-top: 2px; }
  .sc-body { padding: 12px 16px; }
  .sc-foot { padding: 9px 16px; background: rgba(0,0,0,.02); border-top: 1px solid rgba(0,0,0,.05); display: flex; gap: 6px; justify-content: flex-end; flex-wrap: wrap; }
  .monto-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; margin-bottom: 10px; }
  .monto-item { text-align: center; }
  .monto-lbl { font-size: .62rem; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: #0f172a; margin-bottom: 2px; }
  .monto-val { font-family: 'Outfit', sans-serif; font-size: .88rem; font-weight: 700; color: #0f172a; }
  .mv-blue { color: #1a60a8; } .mv-amber { color: #b47800; } .mv-green { color: #1a7555; } .mv-red { color: #c0392b; }
  .mv-purple { color: #6d28d9; }
  .tag-cnt { display: inline-flex; align-items: center; gap: 4px; font-size: .68rem; font-weight: 600; padding: 2px 8px; border-radius: 20px; background: rgba(63,117,85,.1); color: #3f7555; border: 1px solid rgba(63,117,85,.2); }
  .tag-ext { display: inline-flex; align-items: center; gap: 4px; font-size: .68rem; font-weight: 600; padding: 2px 8px; border-radius: 20px; background: rgba(109,40,217,.08); color: #6d28d9; border: 1px solid rgba(109,40,217,.2); cursor: pointer; transition: background .15s; }
  .tag-ext:hover { background: rgba(109,40,217,.15); }
  .sc-total-real { display: flex; justify-content: space-between; align-items: center; padding: 8px; background: rgba(63,117,85,.05); border-radius: 8px; margin-top: 4px; font-size: .85rem; font-weight: 700; color: #3f7555; }
  .sc-ext-panel { display: none; margin-top: 10px; padding-top: 10px; border-top: 1px dashed rgba(0,0,0,.1); }
  .sc-ext-panel.open { display: block; }
  .ext-item { display: flex; align-items: center; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid rgba(0,0,0,.03); }
  .ext-item:last-child { border-bottom: none; }
  .ext-item-monto { font-weight: 700; font-size: .8rem; color: #6d28d9; }
  .ext-item-info { flex: 1; margin-left: 10px; }
  .ext-item-desc { font-size: .72rem; color: #475569; }
  .ext-item-fecha { font-size: .62rem; color: #94a3b8; }
  .ext-item-del { background: none; border: none; color: #94a3b8; cursor: pointer; padding: 2px 6px; }
  .ext-item-del:hover { color: #e8445a; }
  
  /* Drag & Drop Editor */
  .sc-editor-col { display: flex; flex-direction: column; height: 100%; min-height: 400px; padding: 10px; border: 1px solid #eee; border-radius: 8px; background: #fafafa; }
  .dd-head { font-size: .75rem; font-weight: 700; text-transform: uppercase; margin-bottom: 10px; color: #64748b; display: flex; align-items: center; justify-content: space-between; }
  .dd-zone { flex: 1; overflow-y: auto; background: #fff; border-radius: 6px; padding: 8px; min-height: 300px; }
  .dd-zone.drag-over { background: rgba(63,117,85,.05); border: 2px dashed #3f7555; }
  .concept-chip { padding: 8px; border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 6px; background: #fff; cursor: grab; font-size: .8rem; display: flex; align-items: center; gap: 8px; transition: transform .1s; }
  .concept-chip:hover { border-color: #3f7555; transform: scale(1.02); }
  .chip-cod { font-family: monospace; font-weight: 700; color: #334155; }
  .chip-um { font-size: .7rem; color: #94a3b8; }
  .chip-name { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .chip-also { font-size: .65rem; color: #6d28d9; font-style: italic; }
  .already { background: rgba(109,40,217,.05); border-color: rgba(109,40,217,.2); }
  .dd-empty { text-align: center; padding: 40px 10px; color: #94a3b8; font-size: .8rem; }
  .dd-search { margin-bottom: 8px; }

  /* KPI Cards for Obra Details - Modern Soft-Pastel Standard */
  .budget-kpi-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
  }
  .kpi-card {
    background: #fff;
    border-radius: var(--radius-lg, 16px);
    padding: 1.5rem;
    border: 1.5px solid var(--gray-100, #f3f4f6);
    box-shadow: var(--shadow-sm, 0 1px 2px 0 rgba(0,0,0,0.03));
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    position: relative;
    overflow: hidden;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
  }
  .kpi-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-md, 0 8px 20px -3px rgba(0,0,0,0.06));
    border-color: var(--card-hover-border) !important;
  }

  .kpi-label {
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--gray-500, #6b7280);
    text-transform: uppercase;
    letter-spacing: 0.06em;
  }
  .kpi-value {
    font-size: 1.75rem;
    font-weight: 800;
    color: var(--s-800, #0f172a);
    font-family: var(--font-heading, 'Outfit', sans-serif);
    line-height: 1.2;
  }
  .kpi-subtitle {
    font-size: 0.75rem;
    color: var(--gray-500, #6b7280);
  }
  .progress { height: 6px; margin-top: 5px; }

  /* Soft Pastel theme variations (no left borders, full color cards) */
  .kpi-card--cd {
    background: rgba(64, 118, 86, 0.04);
    border-color: rgba(64, 118, 86, 0.12);
    --card-hover-border: rgba(64, 118, 86, 0.3);
  }
  .kpi-card--cd .kpi-value { color: var(--p-500, #407656) !important; }

  .kpi-card--utilizado {
    background: rgba(245, 158, 11, 0.04);
    border-color: rgba(245, 158, 11, 0.12);
    --card-hover-border: rgba(245, 158, 11, 0.3);
  }
  .kpi-card--utilizado .kpi-value { color: #d97706 !important; }

  .kpi-card--disponible.success {
    background: rgba(34, 197, 94, 0.04);
    border-color: rgba(34, 197, 94, 0.12);
    --card-hover-border: rgba(34, 197, 94, 0.3);
  }
  .kpi-card--disponible.success .kpi-value { color: #16a34a !important; }

  .kpi-card--disponible.danger {
    background: rgba(239, 68, 68, 0.04);
    border-color: rgba(239, 68, 68, 0.12);
    --card-hover-border: rgba(239, 68, 68, 0.3);
  }
  .kpi-card--disponible.danger .kpi-value { color: #dc2626 !important; }

  .kpi-card--progreso {
    background: rgba(17, 53, 87, 0.04);
    border-color: rgba(17, 53, 87, 0.12);
    --card-hover-border: rgba(17, 53, 87, 0.3);
  }
  .kpi-card--progreso .kpi-value { color: var(--s-700, #113557) !important; }
</style>

<?php include __DIR__ . "/../includes/navbar.php"; ?>

<div class="orders-page-container">

  <!-- Page Header -->
  <div class="orders-page-header mb-4">
    <div class="orders-page-header-info">
      <nav class="orders-breadcrumb">
        <a href="<?= BASE_URL ?>/index.php">Inicio</a>
        <span class="separator">›</span>
        <a href="list_project.php">Proyectos</a>
        <span class="separator">›</span>
        <a href="details_project.php?id=<?= $obra['proyecto_id'] ?>">Detalles del Proyecto</a>
        <span class="separator">›</span>
        <span>Detalles de Obra</span>
      </nav>
      <h1 class="orders-page-title" style="font-size: 1.5rem;"><?= htmlspecialchars($obra['nombre_obra']) ?></h1>
      <div class="text-muted small mt-1">
        Proyecto: <?= htmlspecialchars($obra['nombre_proyecto']) ?> | Obra Nº: <?= htmlspecialchars($obra['numero_obra']) ?>
      </div>
    </div>
    
    <!-- Action Buttons -->
    <div class="actions-group" style="gap: 8px;">
      <a href="details_project.php?id=<?= $obra['proyecto_id'] ?>" class="btn-geco-outline">
        <i class="bi bi-arrow-left"></i> Volver
      </a>
      <button class="btn-geco-secondary" onclick="editarObra(<?= $obra_id ?>)" title="Editar Obra">
        <i class="bi bi-pencil-square"></i> Editar
      </button>
      <button class="btn-geco-secondary" onclick="gestionarArchivos(<?= $obra_id ?>)" title="Archivos PDF">
        <i class="bi bi-paperclip"></i> Archivos
      </button>
      <button class="btn-geco-primary" onclick="window.location.reload()" title="Recargar">
        <i class="bi bi-arrow-clockwise"></i> Recargar
      </button>
    </div>
  </div>

  <!-- KPI Budget Dashboard -->
  <div class="budget-kpi-container">
    <div class="kpi-card kpi-card--cd">
      <span class="kpi-label">Costo Directo</span>
      <span class="kpi-value">$<?= number_format($obra['costo_directo'], 2) ?></span>
      <span class="kpi-subtitle">Presupuesto contratado</span>
    </div>
    
    <div class="kpi-card kpi-card--utilizado">
      <span class="kpi-label">Monto Utilizado</span>
      <span class="kpi-value">$<?= number_format($obra['costo_directo_utilizado'], 2) ?></span>
      <span class="kpi-subtitle">En órdenes de compra</span>
    </div>
    
    <div class="kpi-card kpi-card--disponible <?= $costo_disponible < 0 ? 'danger' : 'success' ?>">
      <span class="kpi-label">Monto Disponible</span>
      <span class="kpi-value">$<?= number_format($costo_disponible, 2) ?></span>
      <span class="kpi-subtitle">Presupuesto libre</span>
    </div>

    <div class="kpi-card kpi-card--progreso">
      <span class="kpi-label">Progreso Financiero</span>
      <span class="kpi-value"><?= number_format($porcentaje_utilizado, 1) ?>%</span>
      <div class="progress flex-grow-1" style="height: 6px; width: 100%;">
        <div class="progress-bar <?= $porcentaje_utilizado > 90 ? 'bg-danger' : ($porcentaje_utilizado > 70 ? 'bg-warning' : 'bg-success') ?>" 
             role="progressbar" style="width: <?= min($porcentaje_utilizado, 100) ?>%"></div>
      </div>
    </div>
  </div>

  <!-- Tab Navigation -->
  <ul class="nav nav-pills mb-4 gap-2" id="obraTabs" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" id="catalogos-tab" data-bs-toggle="pill" data-bs-target="#catalogos-pane" type="button" role="tab">
        <i class="bi bi-journal-text me-1"></i> Catálogos (<?= $obra['total_catalogos'] ?>)
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="subcontratos-tab" data-bs-toggle="pill" data-bs-target="#subcontratos-pane" type="button" role="tab">
        <i class="bi bi-people me-1"></i> Subcontratos
      </button>
    </li>
  </ul>

  <!-- Tab Contents -->
  <div class="tab-content" id="obraTabsContent">
    <!-- TAB CATALOGOS -->
    <div class="tab-pane fade show active" id="catalogos-pane" role="tabpanel" tabindex="0">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold text-dark mb-0">Catálogos de Conceptos</h5>
        <button class="btn-geco-primary btn-sm" onclick="mostrarFormularioCatalogo(<?= $obra_id ?>, '<?= addslashes($obra['nombre_obra']) ?>')">
          <i class="bi bi-plus-circle me-1"></i> Nuevo Catálogo
        </button>
      </div>
      <?php if ($catalogos->num_rows > 0): ?>
        <div class="row g-3">
          <?php while ($cat = $catalogos->fetch_assoc()): ?>
            <div class="col-md-6 col-lg-4">
              <div class="orders-card p-3 h-100 d-flex flex-column">
                <h6 class="fw-bold text-dark mb-1"><?= htmlspecialchars($cat['nombre_catalogo']) ?></h6>
                <p class="text-muted small mb-3"><?= htmlspecialchars($cat['descripcion'] ?: 'Sin descripción') ?></p>
                <div class="d-flex justify-content-between align-items-center mt-auto border-top pt-2">
                  <span class="status-badge text-muted" style="background:#f3f4f6; border-color:#e5e7eb;">
                    Creado: <?= date('d/m/Y', strtotime($cat['fecha_creacion'])) ?>
                  </span>
                  <div class="actions-group">
                    <button class="btn-action" style="color: var(--p-600);" onclick="abrirVistaConceptos(<?= $cat['id'] ?>, '<?= addslashes($cat['nombre_catalogo']) ?>', <?= $obra_id ?>, '<?= addslashes($obra['nombre_obra']) ?>')" title="Ver Conceptos">
                      <i class="bi bi-eye"></i>
                    </button>
                    <button class="btn-action" style="color: #d97706;" onclick="editarCatalogo(<?= $cat['id'] ?>, '<?= addslashes($cat['nombre_catalogo']) ?>', '<?= addslashes($cat['descripcion']) ?>')" title="Editar Catálogo">
                      <i class="bi bi-pencil-square"></i>
                    </button>
                    <button class="btn-action" style="color: #ef4444;" onclick="eliminarCatalogo(<?= $cat['id'] ?>)" title="Eliminar Catálogo">
                      <i class="bi bi-trash3"></i>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          <?php endwhile; ?>
        </div>
      <?php else: ?>
        <div class="orders-empty-state">
          <i class="bi bi-folder2-open" style="font-size: 3rem;"></i>
          <p class="mt-2">No hay catálogos registrados para esta obra.</p>
        </div>
      <?php endif; ?>
    </div>

    <!-- TAB SUBCONTRATOS -->
    <div class="tab-pane fade" id="subcontratos-pane" role="tabpanel" tabindex="0">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div id="sc-stats" class="d-flex gap-3 align-items-center small text-muted">
          <span><i class="bi bi-people me-1"></i>Subcontratos: <strong id="sc-stat-total">0</strong></span>
          <span><i class="bi bi-cash me-1"></i>Estimado: <strong id="sc-stat-est">$0</strong></span>
          <span><i class="bi bi-check2-circle me-1"></i>Real: <strong id="sc-stat-real">$0</strong></span>
        </div>
        <button class="btn-geco-primary btn-sm" onclick="scAbrirNuevoModal()">
          <i class="bi bi-plus-circle me-1"></i> Nuevo Subcontrato
        </button>
      </div>
      <div id="sc-grid">
        <div class="text-center py-5">
          <div class="spinner-border text-success"></div>
          <p class="mt-2">Cargando subcontratos...</p>
        </div>
      </div>
    </div>
  </div>

</div>



<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/scripts/catalogo-obras.js"></script>

<script>
  var SC_OBRA_ID = <?= (int)$obra_id ?>;
  var COSTO_DIRECTO_OBRA = <?= (float)$obra['costo_directo'] ?>;
  var scLista = [], scProveedores = [], scTodosConc = [], scEditorId = null, scDisp = [], scAsig = [], scDragId = null;
  var extScId = null, extLista = [];

  const scFmt = n => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(n || 0);
  const scEl = id => document.getElementById(id);

  function scAjax(data, cb) {
    const fd = new FormData();
    Object.keys(data).forEach(k => fd.append(k, data[k] ?? ''));
    fetch('details_obra.php?id=' + SC_OBRA_ID, { method: 'POST', body: fd })
      .then(r => r.json()).then(r => cb(null, r)).catch(() => cb(null, { success: false, error: 'Error de red' }));
  }

  document.addEventListener('DOMContentLoaded', function() {
    scAjax({ action: 'obtener_proveedores' }, (e, rP) => {
      if (rP.success) scProveedores = rP.proveedores;
      scCargar();
    });
  });

  function scCargar() {
    scAjax({ action: 'obtener_subcontratos', obra_id: SC_OBRA_ID }, (e, r) => {
      if (r.success) { scLista = r.subcontratos; scRender(); scStats(); }
      else UI.toast.error("Error al cargar subcontratos");
    });
  }

  function scStats() {
    scEl('sc-stat-total').textContent = scLista.length;
    scEl('sc-stat-est').textContent = scFmt(scLista.reduce((s, x) => s + (parseFloat(x.total_estimado) || 0) + (parseFloat(x.total_extraordinarios) || 0), 0));
    scEl('sc-stat-real').textContent = scFmt(scLista.reduce((s, x) => s + parseFloat(x.monto_real || 0), 0));
  }

  function scRender() {
    const wrap = scEl('sc-grid');
    if (!scLista.length) { wrap.innerHTML = '<div class="orders-empty-state"><i class="bi bi-people" style="font-size:3rem;"></i><p class="mt-2">Sin subcontratos registrados.</p></div>'; return; }
    let html = '<div class="sc-grid">';
    scLista.forEach(sc => {
      const numExt = parseInt(sc.num_extraordinarios) || 0;
      const totalReal = parseFloat(sc.monto_real || 0);
      const estimado = parseFloat(sc.total_estimado || 0);
      const ext = parseFloat(sc.total_extraordinarios || 0);
      const totalConExt = estimado + ext;
      const porPagar = totalConExt - totalReal;
      html += `
        <div class="sc-card">
          <div class="sc-card-head"><div class="sc-avatar"><i class="bi bi-person-gear"></i></div>
            <div style="flex:1;min-width:0"><div class="sc-name">${sc.proveedor_nombre || 'Sin proveedor'}</div>
            <div class="sc-sub"><span class="tag-cnt"><i class="bi bi-list-check"></i> ${sc.total_conceptos} conceptos</span>
            ${numExt > 0 ? `<span class="tag-ext" onclick="scToggleExt(${sc.id})"><i class="bi bi-star-fill"></i> ${numExt} extra</span>` : ''}</div></div></div>
          <div class="sc-body">
            <div class="monto-grid">
              <div class="monto-item"><div class="monto-lbl">Contrato</div><div class="monto-val mv-blue">${scFmt(estimado)}</div></div>
              <div class="monto-item"><div class="monto-lbl">Extra</div><div class="monto-val mv-purple">${scFmt(ext)}</div></div>
              <div class="monto-item"><div class="monto-lbl">Utilizado</div><div class="monto-val mv-red">${scFmt(totalReal)}</div></div>
              <div class="monto-item"><div class="monto-lbl">Importe Total</div><div class="monto-val mv-green">${scFmt(totalConExt)}</div></div>
            </div>
            <div class="sc-total-real"><span><i class="bi bi-sigma me-1"></i>Por pagar</span><span>${scFmt(porPagar)}</span></div>
            <div class="sc-ext-panel" id="sc-ext-${sc.id}"><div class="small fw-bold text-muted mb-2">Detalle Extraordinarios</div><div id="sc-ext-rows-${sc.id}"></div></div>
          </div>
          <div class="sc-foot">
            <button class="btn btn-sm btn-outline-primary" onclick="extAbrir(${sc.id})" title="Extraordinarios"><i class="bi bi-star"></i></button>
            <button class="btn btn-sm btn-outline-info" onclick="scAbrirEditor(${sc.id})" title="Asignar Conceptos"><i class="bi bi-list-check"></i></button>
            <button class="btn btn-sm btn-outline-warning" onclick="scEditar(${sc.id})" title="Editar"><i class="bi bi-pencil"></i></button>
            <button class="btn btn-sm btn-outline-danger" onclick="scEliminar(${sc.id})" title="Eliminar"><i class="bi bi-trash"></i></button>
          </div>
        </div>`;
    });
    wrap.innerHTML = html + '</div>';
    scLista.forEach(sc => { if (parseInt(sc.num_extraordinarios) > 0) scCargarExtInline(sc.id); });
  }

  function scToggleExt(id) { scEl('sc-ext-' + id).classList.toggle('open'); }
  function scCargarExtInline(scId) {
    scAjax({ action: 'obtener_extraordinarios', subcontrato_id: scId }, (e, r) => {
      const rows = scEl('sc-ext-rows-' + scId); if (!rows) return;
      if (!r.success || !r.extraordinarios.length) { rows.innerHTML = '<div class="small text-muted">Sin registros</div>'; return; }
      rows.innerHTML = r.extraordinarios.map(x => `
        <div class="ext-item">
          <div class="ext-item-monto">${scFmt(x.monto)}</div>
          <div class="ext-item-info"><div class="ext-item-desc text-truncate" style="max-width:140px;">${x.descripcion}</div><div class="ext-item-fecha">${x.fecha}</div></div>
          <button class="ext-item-del" onclick="extEliminarInline(${x.id},${scId})"><i class="bi bi-trash3"></i></button>
        </div>`).join('');
    });
  }

  function extEliminarInline(id, scId) {
    UI.confirm({ title: '¿Eliminar extraordinario?', danger: true }).then(conf => {
      if (!conf) return;
      scAjax({ action: 'eliminar_extraordinario', id: id, subcontrato_id: scId }, (e, r) => {
        if (r.success) { UI.toast.success("Eliminado"); scCargar(); } else UI.toast.error(r.error);
      });
    });
  }

  // Modales de Subcontrato centralizados
  function scAbrirNuevoModal() { scFormSubcontrato(); }
  function scEditar(id) {
    const sc = scLista.find(x => x.id == id); if (!sc) return;
    scFormSubcontrato(sc);
  }

  function scFormSubcontrato(sc = null) {
    let proveedoresOptions = '<option value="">Selecciona proveedor</option>';
    scProveedores.forEach(p => { proveedoresOptions += `<option value="${p.id}" ${sc && sc.proveedor_id == p.id ? 'selected' : ''}>${p.nombre}</option>`; });

    UI.modal({
      title: sc ? "Editar Subcontrato" : "Nuevo Subcontrato",
      html: `
        <form id="formSC" class="p-2">
          <input type="hidden" name="id" value="${sc ? sc.id : ''}">
          <div class="mb-3">
            <label class="form-label fw-semibold">Proveedor *</label>
            <select name="proveedor_id" class="form-select" required>${proveedoresOptions}</select>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label fw-semibold">Monto Contrato *</label>
              <input type="number" step="0.01" name="total_estimado" class="form-control" value="${sc ? sc.total_estimado : ''}" required oninput="scModalCalcAnt()">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label fw-semibold">Anticipo (%)</label>
              <input type="number" step="0.1" name="anticipo_pct" class="form-control" value="${sc ? sc.anticipo_pct : ''}" oninput="scModalCalcAnt()">
            </div>
          </div>
          <div class="mb-3 text-muted small" id="scAntMontoPreview"></div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Descripción</label>
            <textarea name="descripcion" class="form-control" rows="2" placeholder="Detalles u observaciones del subcontrato...">${sc ? sc.descripcion : ''}</textarea>
          </div>
          <div class="d-flex justify-content-end gap-2 mt-4">
            <button type="button" class="btn btn-secondary" onclick="UI.modal.close()">Cancelar</button>
            <button type="submit" class="btn btn-success"><i class="bi bi-floppy me-1"></i>Guardar Subcontrato</button>
          </div>
        </form>`
    });
    if (sc) scModalCalcAnt();

    document.getElementById('formSC').addEventListener('submit', function(e) {
      e.preventDefault();
      const fd = new FormData(this);
      const total = parseFloat(fd.get('total_estimado')) || 0;
      const sumaActual = scLista.reduce((s, x) => (sc && x.id == sc.id) ? s : s + (parseFloat(x.total_estimado) || 0) + (parseFloat(x.total_extraordinarios) || 0), 0);
      
      if (sumaActual + total > COSTO_DIRECTO_OBRA) {
        UI.confirm({
          title: 'Exceso de Costo Directo',
          message: `La suma de subcontratos (${scFmt(sumaActual + total)}) supera el costo directo (${scFmt(COSTO_DIRECTO_OBRA)}). ¿Deseas notificar a Subdirección y continuar?`,
          danger: true
        }).then(conf => {
          if (conf) {
            UI.loading("Notificando y guardando...");
            scAjax({ action: 'notificar_exceso_subcontratos', obra_id: SC_OBRA_ID }, () => {
              scEjecutarGuardar(fd);
            });
          }
        });
      } else {
        scEjecutarGuardar(fd);
      }
    });
  }

  function scModalCalcAnt() {
    const f = document.getElementById('formSC');
    const t = parseFloat(f.total_estimado.value) || 0;
    const p = parseFloat(f.anticipo_pct.value) || 0;
    const pre = document.getElementById('scAntMontoPreview');
    if (t > 0 && p > 0) pre.innerHTML = `Anticipo en pesos: <strong>${scFmt(t * p / 100)}</strong>`;
    else pre.innerHTML = '';
  }

  function scEjecutarGuardar(fd) {
    const data = {}; fd.forEach((v, k) => data[k] = v);
    data.action = data.id ? 'actualizar_subcontrato' : 'crear_subcontrato';
    if (!data.id) data.obra_id = SC_OBRA_ID;
    UI.loading("Guardando...");
    scAjax(data, (e, r) => {
      UI.loading.hide();
      if (r.success) { UI.modal.close(); UI.toast.success("Subcontrato guardado"); scCargar(); }
      else UI.toast.error(r.error);
    });
  }

  function scEliminar(id) {
    UI.confirm({ title: '¿Eliminar subcontrato?', message: 'Se borrarán conceptos y extraordinarios asociados.', danger: true }).then(conf => {
      if (!conf) return;
      UI.loading("Eliminando...");
      scAjax({ action: 'eliminar_subcontrato', id: id }, (e, r) => {
        UI.loading.hide();
        if (r.success) { UI.toast.success("Subcontrato eliminado"); scCargar(); } else UI.toast.error("Error al eliminar");
      });
    });
  }

  // Editor de Conceptos centralizado
  function scAbrirEditor(id) {
    const sc = scLista.find(x => x.id == id); if (!sc) return;
    scEditorId = id;
    UI.loading("Cargando conceptos...");
    scAjax({ action: 'obtener_conceptos_obra', obra_id: SC_OBRA_ID }, (e, r) => {
      UI.loading.hide();
      if (!r.success) { UI.toast.error("Error al cargar conceptos"); return; }
      scTodosConc = r.conceptos;
      scAsig = scTodosConc.filter(c => c.subcontratos_ids && c.subcontratos_ids.split(',').includes(String(id)));
      scDisp = scTodosConc.filter(c => !c.subcontratos_ids || !c.subcontratos_ids.split(',').includes(String(id)));
      
      UI.modal({
        title: "Asignar Conceptos - " + (sc.proveedor_nombre || 'Sin proveedor'),
        size: "xl",
        html: `
          <div class="row g-3 p-2">
            <div class="col-md-6">
              <div class="sc-editor-col">
                <div class="dd-head">Disponibles <span class="badge bg-secondary animate-pulse" id="sc-cnt-disp">0</span></div>
                <input class="form-control form-control-sm mb-2" id="sc-search-disp" placeholder="Buscar..." oninput="scFiltrarZonas()">
                <div class="dd-zone" id="sc-zone-disp" ondragover="scOnDragOver(event)" ondrop="scOnDrop(event,'disp')" ondragleave="scOnDragLeave(event)"></div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="sc-editor-col">
                <div class="dd-head" style="color:#3f7555;">Asignados <span class="badge bg-success" id="sc-cnt-asig">0</span></div>
                <input class="form-control form-control-sm mb-2" id="sc-search-asig" placeholder="Buscar..." oninput="scFiltrarZonas()">
                <div class="dd-zone" id="sc-zone-asig" ondragover="scOnDragOver(event)" ondrop="scOnDrop(event,'asig')" ondragleave="scOnDragLeave(event)"></div>
              </div>
            </div>
          </div>
          <div class="d-flex justify-content-between align-items-center mt-4 p-2">
            <small class="text-muted"><i class="bi bi-info-circle me-1"></i>Doble clic para mover o arrastra los conceptos.</small>
            <div class="gap-2 d-flex">
              <button class="btn btn-secondary" onclick="UI.modal.close()">Cancelar</button>
              <button class="btn btn-success" onclick="scGuardarConceptos()"><i class="bi bi-floppy me-1"></i>Guardar Cambios</button>
            </div>
          </div>`
      });
      scRenderZonas();
    });
  }

  function scRenderZonas() {
    scRenderZona('sc-zone-disp', scDisp, scEl('sc-search-disp').value, false);
    scRenderZona('sc-zone-asig', scAsig, scEl('sc-search-asig').value, true);
    scEl('sc-cnt-disp').textContent = scDisp.length;
    scEl('sc-cnt-asig').textContent = scAsig.length;
  }

  function scRenderZona(zoneId, lista, filtro, esAsig) {
    const zone = scEl(zoneId);
    const q = filtro.toLowerCase().trim();
    const vis = q ? lista.filter(c => c.nombre_concepto.toLowerCase().includes(q) || c.codigo_concepto.toLowerCase().includes(q)) : lista;
    if (!vis.length) { zone.innerHTML = `<div class="dd-empty"><i class="bi bi-${esAsig ? 'inbox' : 'check2-all'}"></i> ${esAsig ? 'Vacío' : 'Sin disponibles'}</div>`; return; }
    zone.innerHTML = vis.map(c => {
      const enOtro = !esAsig && c.subcontratos_ids && c.subcontratos_ids.split(',').some(sid => sid !== '' && parseInt(sid) !== scEditorId);
      return `
        <div class="concept-chip ${enOtro ? 'already' : ''}" draggable="true" ondragstart="scDragId=${c.id}" ondragend="scDragId=null" ondblclick="scMoverConcepto(${c.id},'${esAsig ? 'disp' : 'asig'}')">
          <span class="chip-cod">${c.codigo_concepto}</span>
          <span class="chip-um">${c.unidad_medida || ''}</span>
          <span class="chip-name" title="${c.nombre_concepto}">${c.nombre_concepto}</span>
          ${enOtro ? '<span class="chip-also">otro</span>' : ''}
        </div>`;
    }).join('');
  }

  function scOnDragOver(e) { e.preventDefault(); e.currentTarget.classList.add('drag-over'); }
  function scOnDragLeave(e) { e.currentTarget.classList.remove('drag-over'); }
  function scOnDrop(e, dest) { e.preventDefault(); e.currentTarget.classList.remove('drag-over'); if (scDragId) scMoverConcepto(scDragId, dest); }
  function scMoverConcepto(id, dest) {
    if (dest === 'asig') { const i = scDisp.findIndex(c => c.id == id); if (i !== -1) scAsig.push(scDisp.splice(i, 1)[0]); }
    else { const i = scAsig.findIndex(c => c.id == id); if (i !== -1) scDisp.push(scAsig.splice(i, 1)[0]); }
    scRenderZonas();
  }
  function scFiltrarZonas() { scRenderZonas(); }

  function scGuardarConceptos() {
    UI.loading("Guardando...");
    scAjax({ action: 'guardar_conceptos', subcontrato_id: scEditorId, concepto_ids: JSON.stringify(scAsig.map(c => c.id)) }, (e, r) => {
      UI.loading.hide();
      if (r.success) { UI.modal.close(); UI.toast.success("Conceptos asignados"); scCargar(); } else UI.toast.error("Error al guardar");
    });
  }

  function extAbrir(scId) {
    extScId = scId;
    UI.loading("Cargando extraordinarios...");
    scAjax({ action: 'obtener_extraordinarios', subcontrato_id: scId }, (e, r) => {
      UI.loading.hide();
      if (!r.success) { UI.toast.error("Error"); return; }
      extLista = r.extraordinarios;
      UI.modal({
        title: "Gestión de Extraordinarios",
        size: "lg",
        html: `
          <div class="row p-2">
            <div class="col-md-5">
              <div class="card p-3 bg-light border-0 mb-3 rounded">
                <h6 class="fw-bold small mb-3">Agregar Nuevo</h6>
                <div class="mb-2">
                  <label class="small text-muted fw-semibold">Monto *</label>
                  <input type="number" step="0.01" id="ext-m-monto" class="form-control form-control-sm" required placeholder="0.00">
                </div>
                <div class="mb-2">
                  <label class="small text-muted fw-semibold">Fecha *</label>
                  <input type="date" id="ext-m-fecha" class="form-control form-control-sm" required>
                </div>
                <div class="mb-3">
                  <label class="small text-muted fw-semibold">Descripción *</label>
                  <input type="text" id="ext-m-desc" class="form-control form-control-sm" required placeholder="Ej: Adicional de obra">
                </div>
                <button class="btn btn-success btn-sm w-100" onclick="extAgregar()"><i class="bi bi-plus-lg me-1"></i>Agregar</button>
              </div>
            </div>
            <div class="col-md-7">
              <div id="ext-m-lista" style="max-height:300px;overflow-y:auto;"></div>
              <div class="mt-3 text-end fw-bold text-dark border-top pt-2" id="ext-m-total">Total: $0.00</div>
            </div>
          </div>`
      });
      scEl('ext-m-fecha').value = new Date().toISOString().split('T')[0];
      extRender();
    });
  }

  function extRender() {
    const list = scEl('ext-m-lista');
    const total = extLista.reduce((s, x) => s + parseFloat(x.monto || 0), 0);
    scEl('ext-m-total').textContent = 'Total: ' + scFmt(total);
    if (!extLista.length) { list.innerHTML = '<div class="text-center py-4 text-muted">Sin registros</div>'; return; }
    list.innerHTML = extLista.map(x => `
      <div class="d-flex justify-content-between align-items-center p-2 border-bottom">
        <div><div class="small fw-bold">${scFmt(x.monto)}</div><div class="text-muted" style="font-size:10px;">${x.descripcion} - ${x.fecha}</div></div>
        <button class="btn btn-sm text-danger" onclick="extEliminar(${x.id})"><i class="bi bi-trash"></i></button>
      </div>`).join('');
  }

  function extAgregar() {
    const m = parseFloat(scEl('ext-m-monto').value) || 0;
    const d = scEl('ext-m-desc').value.trim();
    const f = scEl('ext-m-fecha').value;
    if (!m || !d) { UI.toast.error("Datos incompletos"); return; }
    scAjax({ action: 'agregar_extraordinario', subcontrato_id: extScId, monto: m, descripcion: d, fecha: f }, (e, r) => {
      if (r.success) { UI.toast.success("Agregado"); extLista.unshift({ id: r.id, monto: m, descripcion: d, fecha: f }); extRender(); scCargar(); }
      else UI.toast.error(r.error);
    });
  }

  function extEliminar(id) {
    UI.confirm({ title: '¿Eliminar?', danger: true }).then(conf => {
      if (!conf) return;
      scAjax({ action: 'eliminar_extraordinario', id: id, subcontrato_id: extScId }, (e, r) => {
        if (r.success) { UI.toast.success("Eliminado"); extLista = extLista.filter(x => x.id != id); extRender(); scCargar(); }
      });
    });
  }

  function editarObra(id) {
    UI.loading("Cargando...");
    fetch(`edit_obra.php?id=${id}`).then(r => r.json()).then(data => {
      UI.loading.hide();
      UI.modal({
        title: "Editar Obra",
        size: "lg",
        html: `
          <form id="formEditObra" class="p-2">
            <input type="hidden" name="id" value="${data.id}"><input type="hidden" name="proyecto_id" value="${data.proyecto_id}">
            <div class="mb-3">
              <label class="form-label fw-semibold">Nombre de la Obra *</label>
              <input type="text" name="nombre_obra" class="form-control" value="${data.nombre_obra}" required>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Número de Obra *</label>
              <input type="text" name="numero_obra" class="form-control" value="${data.numero_obra}" required>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Descripción</label>
              <textarea name="descripcion" class="form-control" rows="3" placeholder="Describe los detalles de la obra...">${data.descripcion || ''}</textarea>
            </div>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label fw-semibold">Costo Directo *</label>
                <input type="number" step="0.01" name="costo_directo" class="form-control" value="${data.costo_directo}" required>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label fw-semibold">Monto Designado *</label>
                <input type="number" step="0.01" name="monto_designado" class="form-control" value="${data.monto_designado}" required>
              </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-4">
              <button type="button" class="btn btn-secondary" onclick="UI.modal.close()">Cancelar</button>
              <button type="submit" class="btn btn-success"><i class="bi bi-floppy me-1"></i>Actualizar</button>
            </div>
          </form>`
      });
      document.getElementById('formEditObra').addEventListener('submit', function(e) {
        e.preventDefault();
        UI.loading("Actualizando...");
        fetch('update_obra.php', { method: 'POST', body: new FormData(this) }).then(r => r.json()).then(r => {
          UI.loading.hide();
          if (r.status === 'success') { UI.toast.success("Obra actualizada"); setTimeout(() => location.reload(), 1200); }
          else UI.toast.error(r.message);
        });
      });
    });
  }

  function gestionarArchivos(obraId) {
    UI.loading("Cargando archivos...");
    fetch(`get_archivos_obra.php?obra_id=${obraId}`).then(r => r.json()).then(data => {
      UI.loading.hide();
      let html = `
        <div class="mb-4 p-2">
          <form id="formFiles" enctype="multipart/form-data">
            <input type="hidden" name="obra_id" value="${obraId}">
            <div class="mb-3">
              <label class="form-label fw-semibold">Subir archivo PDF</label>
              <input type="file" name="archivo" class="form-control" accept=".pdf" required>
            </div>
            <button type="button" class="btn btn-primary w-100" onclick="subirArchivoObra()"><i class="bi bi-upload me-1"></i>Subir Archivo</button>
          </form>
        </div>
        <hr><div class="list-group mt-3 p-2" id="fileList"></div>`;
      UI.modal({ title: "Gestión de Archivos PDF", html: html });
      renderFiles(data.archivos);
    });
  }

  function renderFiles(files) {
    const list = scEl('fileList');
    if (!files || !files.length) { list.innerHTML = '<div class="text-center text-muted py-3"><i class="bi bi-folder2-open d-block mb-2" style="font-size:2rem;"></i>Sin archivos</div>'; return; }
    list.innerHTML = files.map(f => `
      <div class="list-group-item d-flex justify-content-between align-items-center rounded mb-2 border">
        <div class="text-truncate" style="max-width:250px;"><i class="bi bi-file-pdf text-danger me-2" style="font-size:1.2rem;"></i><strong>${f.nombre_archivo}</strong></div>
        <div class="btn-group gap-1">
          <button class="btn btn-sm btn-outline-info" onclick="window.open('${f.ruta_archivo}','_blank')" title="Ver PDF"><i class="bi bi-eye"></i></button>
          <button class="btn btn-sm btn-outline-danger" onclick="eliminarArchivoObra(${f.id})" title="Eliminar"><i class="bi bi-trash"></i></button>
        </div>
      </div>`).join('');
  }

  function subirArchivoObra() {
    const fd = new FormData(document.getElementById('formFiles'));
    UI.loading("Subiendo...");
    fetch('upload_archivo_obra.php', { method: 'POST', body: fd }).then(r => r.json()).then(r => {
      UI.loading.hide();
      if (r.status === 'success') { UI.toast.success("Archivo subido"); gestionarArchivos(fd.get('obra_id')); }
      else UI.toast.error(r.message);
    });
  }

  function eliminarArchivoObra(id) {
    UI.confirm({ title: '¿Eliminar archivo?', danger: true }).then(conf => {
      if (!conf) return;
      fetch('delete_archivo_obra.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: id }) })
        .then(r => r.json()).then(r => { if (r.status === 'success') { UI.toast.success("Eliminado"); UI.modal.close(); } });
    });
  }

  function abrirVistaConceptos(catalogoId, catalogoNombre, obraId, obraNombre) {
    window.location.href = `conceptos_view.php?catalogo_id=${catalogoId}&obra_id=${obraId}`;
  }

  function escapeHtml(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
</script>

<?php include __DIR__ . "/../includes/footer.php"; ?>

