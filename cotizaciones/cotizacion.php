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
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/core/modules.css?v=2.0">

<?php include __DIR__ . "/../includes/navbar.php"; ?>

<div class="orders-page-container">

    <!-- ─── PAGE HEADER ──────────────────────────────────────────── -->
    <div class="orders-page-header mb-3">
        <div class="orders-page-header-info">
            <nav class="orders-breadcrumb">
                <a href="<?= BASE_URL ?>/index.php">Inicio</a>
                <span class="separator">›</span>
                <a href="list_cotizaciones.php">Cotizaciones</a>
                <span class="separator">›</span>
                <span>Nueva Cotización</span>
            </nav>
            <h1 class="orders-page-title">Generar Cotización</h1>
            <p class="mt-1 mb-0 text-muted" style="font-size: 0.9rem;">
                Folio actual: <strong style="color: var(--s-700);" id="folioDisplay">...</strong>
            </p>
        </div>
        <button type="button" class="btn-geco-outline" onclick="location.href='list_cotizaciones.php'">
            <i class="fa-solid fa-arrow-left"></i> Volver
        </button>
    </div>

    <form id="formCotizacion">
        <!-- Hidden inputs preserving the backend requirements -->
        <input type="hidden" name="folio" id="folioInput">
        <input type="hidden" name="subtotal" id="hSubtotal">
        <input type="hidden" name="iva" id="hIva">
        <input type="hidden" name="total" id="hTotal">
        <input type="hidden" name="tasa_iva" id="hTasaIva">
        <input type="hidden" name="emisor_nombre" value="<?= htmlspecialchars($emisorNombre) ?>">
        <input type="hidden" name="emisor_depto" value="<?= htmlspecialchars($emisorDepto) ?>">

        <!-- CARD 1: INFORMACIÓN GENERAL -->
        <div class="oc-card mb-4">
            <div class="oc-card-header">
                <span class="oc-card-header__title"><i class="fa-solid fa-circle-info"></i> Información General de la Cotización</span>
            </div>
            <div class="oc-card-body">
                <p class="oc-card-intro" style="margin-top: -8px; margin-bottom: 20px;">Complete los datos generales del receptor y emisor de esta cotización. Los campos con <span class="required">*</span> son requeridos.</p>
                
                <div class="row g-3">
                    <div class="col-md-6 col-lg-4">
                        <label class="oc-form-label">Empresa Emisora <span class="required">*</span></label>
                        <select name="entidad" id="entidadSelect" class="form-select" onchange="actualizarFolio()">
                          <?php while ($row = $result_entidades->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($row['nombre']) ?>" <?= $row['nombre'] === $entidadSel ? 'selected' : '' ?>><?= htmlspecialchars($row['nombre']) ?></option>
                          <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <label class="oc-form-label">Atención a <span class="required">*</span></label>
                        <input type="text" name="atencion" class="form-control" placeholder="Nombre del contacto" required>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <label class="oc-form-label">Compañía / Cliente</label>
                        <input type="text" name="compania" class="form-control" placeholder="Nombre de la empresa">
                    </div>
                    <div class="col-md-6 col-lg-6">
                        <label class="oc-form-label">Fecha de Emisión <span class="required">*</span></label>
                        <input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-6 col-lg-6">
                        <label class="oc-form-label">Lugar de Emisión</label>
                        <input type="text" name="lugar" class="form-control" placeholder="Ciudad, Estado">
                    </div>
                </div>
            </div>
        </div>

        <!-- CARD 2: CONCEPTOS Y PARTIDAS -->
        <div class="oc-card mb-4">
            <div class="oc-card-header">
                <span class="oc-card-header__title"><i class="fa-solid fa-list-check"></i> Conceptos y Partidas</span>
            </div>
            <div class="oc-card-body">
                <p class="oc-card-intro" style="margin-top: -8px; margin-bottom: 20px;">Desglose las partidas o servicios a cotizar en la siguiente tabla. Todos los importes se recalcularán automáticamente.</p>
                
                <div class="orders-table-wrap mb-3">
                    <table class="orders-table align-middle" id="tablaConceptos">
                        <thead>
                            <tr>
                                <th style="width: 5%">#</th>
                                <th style="width: 45%">Descripción del Concepto</th>
                                <th style="width: 12%">Unidad</th>
                                <th style="width: 10%">Cant.</th>
                                <th style="width: 14%">P. Unitario</th>
                                <th style="width: 14%">Importe</th>
                                <th style="width: 5%" class="text-center"></th>
                            </tr>
                        </thead>
                        <tbody id="tbodyConceptos"></tbody>
                    </table>
                </div>

                <button type="button" class="btn-geco-secondary btn-sm" onclick="agregarFila()">
                    <i class="fa-solid fa-circle-plus"></i> Agregar Concepto
                </button>


            </div>
        </div>

        <!-- CARD 3: ALCANCES Y TÉRMINOS COMERCIALES -->
        <div class="oc-form-layout mb-4">
            <div class="oc-form-layout-main">
                <div class="oc-card h-100">
                    <div class="oc-card-header">
                        <span class="oc-card-header__title"><i class="fa-solid fa-square-check"></i> Alcances Incluidos</span>
                    </div>
                    <div class="oc-card-body">
                        <p class="oc-card-hint--block">Seleccione los alcances generales cubiertos por esta cotización.</p>
                        <div class="row g-3">
                            <?php 
                            $alcancesOpc = [
                                'ejecucion'   => 'Ejecución de obra',
                                'materiales'  => 'Suministro de materiales',
                                'supervision' => 'Supervisión técnica',
                                'limpieza'    => 'Limpieza final',
                                'garantia'    => 'Garantía de vicios ocultos',
                                'herramienta' => 'Herramienta y equipo',
                                'seguridad'   => 'Seguridad industrial',
                                'entrega'     => 'Memoria fotográfica'
                            ];
                            foreach ($alcancesOpc as $k => $v): 
                            ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="alcances[]" value="<?= $k ?>" id="chk_<?= $k ?>" checked>
                                        <label class="form-check-label" for="chk_<?= $k ?>" style="font-size: 0.9rem; color: var(--gray-700); cursor: pointer;">
                                            <?= $v ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="mt-4">
                            <label class="oc-form-label small" style="font-size: 0.8rem; color: var(--gray-500);">Alcances Adicionales o Exclusiones</label>
                            <textarea name="alcances_extra" class="form-control" rows="3" placeholder="Describa otros alcances, aclaraciones o exclusiones de forma detallada..."></textarea>
                        </div>
                    </div>
                </div>
            </div>
            <div class="oc-form-layout-side">
                <div class="oc-card mb-4">
                    <div class="oc-card-header">
                        <span class="oc-card-header__title"><i class="fa-solid fa-file-contract"></i> Términos Comerciales</span>
                    </div>
                    <div class="oc-card-body">
                        <div class="d-flex flex-column gap-3">
                            <div>
                                <label class="oc-form-label">Tiempo de Ejecución</label>
                                <input type="text" name="tiempo" class="form-control" placeholder="Ej. 15 días hábiles">
                            </div>
                            <div>
                                <label class="oc-form-label">Forma de Pago</label>
                                <input type="text" name="forma_pago" class="form-control" placeholder="Ej. 50% anticipo, 50% entrega">
                            </div>
                            <div>
                                <label class="oc-form-label">Vigencia de la Oferta</label>
                                <input type="text" name="vigencia" class="form-control" value="30 días naturales">
                            </div>
                            <div>
                                <label class="oc-form-label">Notas Adicionales</label>
                                <textarea name="notas" class="form-control" rows="3" placeholder="Comentarios comerciales o notas adicionales..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="oc-finance">
                    <div class="oc-finance-title"><i class="fa-solid fa-calculator"></i> Resumen Financiero</div>
                    <div class="oc-finance-row">
                        <span>Subtotal:</span>
                        <span id="dispSubtotal">$0.00</span>
                    </div>
                    <div class="oc-finance-row">
                        <span class="d-flex align-items-center gap-2">
                            IVA:
                            <select id="selectIva" class="form-select form-select-sm oc-finance-iva-select text-white bg-transparent border-0" style="color: white !important;" onchange="recalcular()">
                                <option value="16" class="text-dark">16%</option>
                                <option value="8" class="text-dark">8%</option>
                                <option value="0" class="text-dark">0%</option>
                            </select>
                        </span>
                        <span id="dispIva">$0.00</span>
                    </div>
                    <div class="oc-finance-total">
                        <span class="lbl">TOTAL:</span>
                        <span class="amt" id="dispTotal">$0.00</span>
                    </div>
                </div>

                <div class="oc-form-submit-actions mt-4">
                    <button type="button" class="btn-geco-primary px-5 py-2.5" onclick="guardarYDescargar()" style="width: 100%; border-radius: 10px; font-weight: 600;">
                        <i class="fa-solid fa-download me-2"></i> Generar Cotización
                    </button>
                </div>
            </div>
        </div>
    </form>

    <!-- ─── SCRIPTS ──────────────────────────────────────────────── -->
    <script>
      let filaCount = 0;
      const fmt = n => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(n || 0);

      function agregarFila() {
        filaCount++;
        const tr = document.createElement('tr');
        tr.dataset.idx = filaCount;
        tr.innerHTML = `
          <td class="text-center small fw-bold text-muted" style="vertical-align: middle;">${filaCount}</td>
          <td><textarea name="desc[]" class="form-control form-control-sm" rows="2" placeholder="Descripción..."></textarea></td>
          <td><input type="text" name="unidad[]" class="form-control form-control-sm" placeholder="pza, m, etc"></td>
          <td><input type="number" name="cantidad[]" class="form-control form-control-sm text-center" value="1" oninput="recalcular()"></td>
          <td><input type="number" name="precio[]" class="form-control form-control-sm text-end" value="0" step="0.01" oninput="recalcular()"></td>
          <td class="text-end fw-bold" style="color: var(--s-700); vertical-align: middle;" id="imp-${filaCount}">$0.00</td>
          <td class="text-center" style="vertical-align: middle;">
              <button type="button" class="btn-action btn-action--delete" onclick="this.closest('tr').remove(); recalcular()">
                  <i class="fa-solid fa-trash-can"></i>
              </button>
          </td>`;
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

      document.addEventListener('DOMContentLoaded', function() {
        agregarFila();
        actualizarFolio();
      });
    </script>
</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>

