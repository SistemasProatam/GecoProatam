<?php
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";
checkSession();
preventCaching();

$dep_id_sesion  = $_SESSION['departamento_id'] ?? null;
$es_super_admin = ($_SESSION['departamento']   ?? '') === 'SUPER_ADMIN';
if (!$es_super_admin && !in_array($dep_id_sesion, [1, 2, 10, 16])) {
  header("Location: " . BASE_URL . "/index.php?acceso=denegado");
  exit;
}

require_once __DIR__ . "/../conexion.php";

$entidadSel    = $_GET['entidad'] ?? 'PROATAM';
$emisorNombre  = trim(($_SESSION['nombres'] ?? '') . ' ' . ($_SESSION['apellidos'] ?? ''));
$emisorDepto   = $_SESSION['departamento'] ?? '';

$unidades = [];
$resU = $conn->query("SELECT id, nombre FROM unidades WHERE activo = 1 ORDER BY nombre ASC");
if ($resU) while ($rowU = $resU->fetch_assoc()) $unidades[] = $rowU;

$sql_entidades = "SELECT id, nombre FROM entidades ORDER BY nombre ASC";
$result_entidades = $conn->query($sql_entidades);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Nueva Cotización</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/cotizaciones.css">
</head>
<body>
<?php include __DIR__ . "/../includes/navbar.php"; ?>

<div class="hero-section">
  <div class="container hero-content">
    <div class="breadcrumb-custom">
      <a href="<?= BASE_URL ?>/index.php"><i class="bi bi-house-door"></i> Inicio</a>
      <span>/</span><a href="list_cotizaciones.php">Cotizaciones</a>
      <span>/</span><span>Nueva</span>
    </div>
    <h1 class="hero-title">Generar Cotización</h1>
    <p class="mt-2 text-white-50">Folio actual: <strong class="text-white" id="folioDisplay">...</strong></p>
  </div>
</div>

<div class="content-wrapper">
  <div class="container">
    <div class="card shadow-sm border-0 rounded-4">
      <div class="card-body p-4">
        <form id="formCotizacion">
          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <label class="form-label fw-bold">Empresa emisora</label>
              <select name="entidad" id="entidadSelect" class="form-select" onchange="actualizarFolio()">
                <?php while ($row = $result_entidades->fetch_assoc()): ?>
                  <option value="<?= htmlspecialchars($row['nombre']) ?>" <?= $row['nombre'] === $entidadSel ? 'selected' : '' ?>><?= htmlspecialchars($row['nombre']) ?></option>
                <?php endwhile; ?>
              </select>
            </div>
          </div>

          <div class="row g-3 mb-4">
            <div class="col-md-6"><label class="form-label fw-bold">Atención a</label><input type="text" name="atencion" class="form-control" placeholder="Nombre del contacto" required></div>
            <div class="col-md-6"><label class="form-label fw-bold">Compañía</label><input type="text" name="compania" class="form-control" placeholder="Empresa"></div>
            <div class="col-md-6"><label class="form-label fw-bold">Fecha</label><input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
            <div class="col-md-6"><label class="form-label fw-bold">Lugar</label><input type="text" name="lugar" class="form-control" placeholder="Ciudad, Estado"></div>
          </div>

          <div class="table-responsive mb-4">
            <table class="table table-bordered align-middle" id="tablaConceptos">
              <thead class="bg-light">
                <tr><th style="width: 5%">#</th><th style="width: 45%">Descripción</th><th style="width: 10%">Unidad</th><th style="width: 10%">Cant.</th><th style="width: 15%">P. Unitario</th><th style="width: 15%">Importe</th><th style="width: 5%"></th></tr>
              </thead>
              <tbody id="tbodyConceptos"></tbody>
            </table>
            <button type="button" class="btn btn-outline-primary btn-sm" onclick="agregarFila()"><i class="bi bi-plus-lg me-1"></i> Agregar concepto</button>
          </div>

          <div class="row justify-content-end mb-4">
            <div class="col-md-4">
              <div class="d-flex justify-content-between mb-2"><span>Subtotal:</span><span id="dispSubtotal" class="fw-bold">$0.00</span></div>
              <div class="d-flex justify-content-between align-items-center mb-2">
                <span>IVA:</span>
                <div class="d-flex gap-2 align-items-center">
                  <select id="selectIva" class="form-select form-select-sm" style="width: 70px;" onchange="recalcular()"><option value="16">16%</option><option value="8">8%</option><option value="0">0%</option></select>
                  <span id="dispIva" class="fw-bold">$0.00</span>
                </div>
              </div>
              <div class="d-flex justify-content-between fs-4 text-primary pt-2 border-top"><span>TOTAL:</span><span id="dispTotal" class="fw-bold">$0.00</span></div>
            </div>
          </div>

          <input type="hidden" name="folio" id="folioInput">
          <input type="hidden" name="subtotal" id="hSubtotal"><input type="hidden" name="iva" id="hIva"><input type="hidden" name="total" id="hTotal"><input type="hidden" name="tasa_iva" id="hTasaIva">
          <input type="hidden" name="emisor_nombre" value="<?= htmlspecialchars($emisorNombre) ?>"><input type="hidden" name="emisor_depto" value="<?= htmlspecialchars($emisorDepto) ?>">

          <div class="mb-4">
            <label class="form-label fw-bold">Alcances Incluidos</label>
            <div class="row g-2">
              <?php $alcancesOpc = ['ejecucion'=>'Ejecución','materiales'=>'Materiales','supervision'=>'Supervisión','limpieza'=>'Limpieza','garantia'=>'Garantía','herramienta'=>'Herramienta','seguridad'=>'Seguridad','entrega'=>'Memoria fotog.'];
              foreach ($alcancesOpc as $k => $v): ?>
                <div class="col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="alcances[]" value="<?= $k ?>" id="chk_<?= $k ?>" checked><label class="form-check-label small" for="chk_<?= $k ?>"><?= $v ?></label></div></div>
              <?php endforeach; ?>
            </div>
            <textarea name="alcances_extra" class="form-control mt-3" rows="2" placeholder="Otros alcances..."></textarea>
          </div>

          <div class="row g-3 mb-5">
            <div class="col-md-4"><label class="form-label small fw-bold">Ejecución</label><input type="text" name="tiempo" class="form-control form-control-sm" placeholder="15 días"></div>
            <div class="col-md-4"><label class="form-label small fw-bold">Forma de pago</label><input type="text" name="forma_pago" class="form-control form-control-sm" placeholder="50% anticipo"></div>
            <div class="col-md-4"><label class="form-label small fw-bold">Vigencia</label><input type="text" name="vigencia" class="form-control form-control-sm" value="30 días naturales"></div>
            <div class="col-12"><label class="form-label small fw-bold">Notas</label><textarea name="notas" class="form-control" rows="2"></textarea></div>
          </div>

          <div class="text-center gap-3 d-flex justify-content-center">
            <a href="list_cotizaciones.php" class="btn btn-lg btn-outline-secondary px-5">Cancelar</a>
            <button type="button" class="btn btn-lg btn-primary px-5" onclick="guardarYDescargar()"><i class="bi bi-file-earmark-pdf me-2"></i> Generar Cotización</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  const BASE_URL = '<?= BASE_URL ?>';
  let filaCount = 0;
  const fmt = n => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(n || 0);

  function agregarFila() {
    filaCount++;
    const tr = document.createElement('tr');
    tr.dataset.idx = filaCount;
    tr.innerHTML = `
      <td class="text-center small fw-bold text-muted">${filaCount}</td>
      <td><textarea name="desc[]" class="form-control form-control-sm" rows="2" placeholder="Descripción..."></textarea></td>
      <td><input type="text" name="unidad[]" class="form-control form-control-sm" placeholder="pza, m, etc"></td>
      <td><input type="number" name="cantidad[]" class="form-control form-control-sm text-center" value="1" oninput="recalcular()"></td>
      <td><input type="number" name="precio[]" class="form-control form-control-sm text-end" value="0" step="0.01" oninput="recalcular()"></td>
      <td class="text-end fw-bold text-primary" id="imp-${filaCount}">$0.00</td>
      <td class="text-center"><button type="button" class="btn btn-sm text-danger" onclick="this.closest('tr').remove(); recalcular()"><i class="bi bi-trash"></i></button></td>`;
    document.getElementById('tbodyConceptos').appendChild(tr);
    recalcular();
  }

  function recalcular() {
    let subtotal = 0;
    document.querySelectorAll('#tbodyConceptos tr').forEach(tr => {
      const q = parseFloat(tr.querySelector('input[name="cantidad[]"]').value) || 0;
      const p = parseFloat(tr.querySelector('input[name="precio[]"]').value) || 0;
      const imp = q * p; subtotal += imp;
      document.getElementById('imp-' + tr.dataset.idx).textContent = fmt(imp);
    });
    const ivaPct = parseFloat(document.getElementById('selectIva').value) || 0;
    const iva = subtotal * (ivaPct / 100);
    const total = subtotal + iva;
    document.getElementById('dispSubtotal').textContent = fmt(subtotal);
    document.getElementById('dispIva').textContent = fmt(iva);
    document.getElementById('dispTotal').textContent = fmt(total);
    document.getElementById('hSubtotal').value = subtotal.toFixed(2);
    document.getElementById('hIva').value = iva.toFixed(2);
    document.getElementById('hTotal').value = total.toFixed(2);
    document.getElementById('hTasaIva').value = ivaPct;
  }

  function actualizarFolio() {
    const ent = document.getElementById('entidadSelect').value;
    fetch('generar_folio.php?entidad=' + encodeURIComponent(ent)).then(r => r.json()).then(d => {
      document.getElementById('folioDisplay').textContent = d.folio;
      document.getElementById('folioInput').value = d.folio;
    });
  }

  function guardarYDescargar() {
    const f = document.getElementById('formCotizacion');
    if (!f.atencion.value.trim()) { UI.toast.error("Atención a es obligatorio"); return; }
    
    UI.loading("Generando cotización...");
    fetch('save_cotizacion.php', { method: 'POST', body: new FormData(f) }).then(r => r.json()).then(r => {
      UI.loading.hide();
      if (r.status === 'success') {
        UI.toast.success("Cotización generada");
        window.location.href = 'descargar_cotizacion.php?id=' + r.id;
        setTimeout(() => location.href = 'list_cotizaciones.php', 2000);
      } else UI.toast.error(r.message);
    });
  }

  agregarFila();
  actualizarFolio();
</script>
</body>
</html>
