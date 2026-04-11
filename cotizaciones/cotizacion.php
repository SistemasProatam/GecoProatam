<?php
// ============================================================
// cotizacion.php — Formulario de nueva cotización
// Flujo: AJAX → save_cotizacion.php (guarda BD + devuelve id)
//         → descargar_cotizacion.php?id=X (genera y descarga PDF)
// ============================================================
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";
checkSession();
preventCaching();

$dep_id_sesion  = $_SESSION['departamento_id'] ?? null;
$es_super_admin = ($_SESSION['departamento']   ?? '') === 'SUPER_ADMIN';
if (!$es_super_admin && !in_array($dep_id_sesion, [1, 2, 10, 16])) {
    header("Location: /PROATAM/index.php?acceso=denegado");
    exit;
}

// Generar folio por entidad
function generarFolio(string $entidad = 'PROATAM'): string {
    $prefijos = [
        'PROATAM'     => 'CO-PRO',
        'INGETAM'     => 'CO-ING',
        'LUBYCOMP'    => 'CO-LUB',
        'DAVID GOMEZ' => 'CO-DAG',
    ];
    $prefijo   = $prefijos[$entidad] ?? 'CO-PRO';
    $dir       = __DIR__ . '/data';
    $folioFile = "$dir/folio_{$entidad}.txt";
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    if (!file_exists($folioFile)) file_put_contents($folioFile, '1');
    $num = (int)file_get_contents($folioFile);
    return $prefijo . '-' . str_pad($num, 4, '0', STR_PAD_LEFT);
}

$entidadSel    = $_GET['entidad'] ?? 'PROATAM';
$folioInicial  = generarFolio($entidadSel);
$emisorNombre  = trim(($_SESSION['nombres'] ?? '') . ' ' . ($_SESSION['apellidos'] ?? ''));
$emisorDepto   = $_SESSION['departamento'] ?? '';

include_once __DIR__ . '/../conexion.php';
$unidades = [];
$resU = $conn->query("SELECT id, nombre FROM unidades WHERE activo = 1 ORDER BY nombre ASC");
if ($resU) while ($rowU = $resU->fetch_assoc()) $unidades[] = $rowU;

$entidades = [
    'PROATAM'     => 'PROATAM S.A. DE C.V.',
    'INGETAM'     => 'INGETAM S.A. DE C.V.',
    'LUBYCOMP'    => 'LUBYCOMP',
    'DAVID GOMEZ' => 'DAVID GOMEZ',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Nueva Cotización — <?= htmlspecialchars($folioInicial) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous"/>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css"/>
  <link rel="stylesheet" href="/PROATAM/assets/styles/navbar.css"/>
  <link rel="stylesheet" href="/PROATAM/assets/styles/list.css"/>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    :root {
      --verde:    #2e7d5e; --verde-cl: #4caf85;
      --azul:     #1a3a5c; --oro:      #c8a84b;
      --fondo:    #f4f6f9; --blanco:   #ffffff;
      --texto:    #1c1c1c; --gris:     #6b7280; --borde: #e5e7eb;
      --sombra:   0 2px 16px rgba(26,58,92,.08);
    }
    .hero-section {
      background: linear-gradient(135deg, var(--azul) 0%, #0f2744 60%, #1a4a3a 100%);
      position:relative; overflow:hidden;
    }
    .hero-section::before {
      content:''; position:absolute; top:-40px; right:-40px;
      width:300px; height:300px; background:rgba(46,125,94,.15); border-radius:50%;
    }
    .hero-empresa { display:flex; align-items:center; gap:14px; margin-bottom:18px; }
    .hero-empresa img { height:48px; width:auto; filter:brightness(0) invert(1); opacity:.92; }
    .hero-empresa-nombre { color:#fff; font-size:1rem; font-weight:700; }
    .hero-empresa-sub { color:var(--oro); font-size:.75rem; font-weight:500; letter-spacing:1.5px; text-transform:uppercase; }
    .hero-title { font-size:1.75rem; font-weight:700; }
    .folio-hero {
      display:inline-flex; align-items:center; gap:8px;
      background:rgba(255,255,255,.1); border:1px solid rgba(200,168,75,.4);
      color:var(--oro); font-size:.8rem; font-weight:700; letter-spacing:2px;
      padding:6px 16px; border-radius:20px; margin-top:10px;
    }
    /* Cards */
    .cot-card { background:var(--blanco); border-radius:12px; box-shadow:var(--sombra); border:1px solid var(--borde); margin-bottom:20px; overflow:hidden; }
    .cot-card-header { display:flex; align-items:center; gap:10px; padding:14px 22px; background:linear-gradient(90deg,#f8fafc,#fff); border-bottom:2px solid var(--borde); }
    .cot-card-header .icon-box { width:32px; height:32px; background:var(--verde); border-radius:8px; display:flex; align-items:center; justify-content:center; color:#fff; font-size:.9rem; flex-shrink:0; }
    .cot-card-header h6 { margin:0; font-size:.9rem; font-weight:700; color:var(--azul); }
    .cot-card-body { padding:22px; }
    /* Campos */
    .field { display:flex; flex-direction:column; gap:5px; margin-bottom:14px; }
    .field label { font-size:.73rem; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:var(--gris); }
    .field input, .field textarea, .field select {
      border:1.5px solid var(--borde); border-radius:8px; padding:9px 13px;
      font-size:.92rem; color:var(--texto); background:#fafafa;
      transition:border-color .2s, box-shadow .2s; outline:none; width:100%;
    }
    .field input:focus, .field textarea:focus, .field select:focus {
      border-color:var(--verde); box-shadow:0 0 0 3px rgba(46,125,94,.1); background:#fff;
    }
    .field textarea { resize:vertical; min-height:80px; }
    /* Tabla conceptos */
    table.conceptos { width:100%; border-collapse:collapse; font-size:.875rem; }
    table.conceptos thead tr { background:var(--azul); }
    table.conceptos thead th { padding:10px; color:#fff; font-weight:600; font-size:.72rem; letter-spacing:.8px; text-transform:uppercase; }
    table.conceptos thead th:nth-child(1) { width:40px; text-align:center; }
    table.conceptos thead th:nth-child(3) { width:90px; }
    table.conceptos thead th:nth-child(4) { width:90px; }
    table.conceptos thead th:nth-child(5) { width:110px; }
    table.conceptos thead th:nth-child(6) { width:115px; }
    table.conceptos thead th:nth-child(7) { width:38px; }
    table.conceptos tbody tr { border-bottom:1px solid var(--borde); transition:background .15s; }
    table.conceptos tbody tr:hover { background:#f0faf5; }
    table.conceptos tbody tr:nth-child(even) { background:#fafafa; }
    table.conceptos tbody td { padding:7px 5px; vertical-align:top; }
    table.conceptos tbody td:first-child { text-align:center; color:var(--gris); font-size:.8rem; padding-top:14px; }
    table.conceptos input, table.conceptos select, table.conceptos textarea {
      width:100%; border:1px solid var(--borde); border-radius:6px;
      padding:6px 8px; font-size:.85rem; background:transparent; outline:none; transition:border-color .2s;
    }
    table.conceptos input:focus, table.conceptos select:focus, table.conceptos textarea:focus { border-color:var(--verde); background:#fff; }
    table.conceptos textarea { resize:vertical; min-height:52px; }
    .importe-cell { color:var(--azul); font-weight:700; text-align:right; padding-top:14px !important; padding-right:10px !important; white-space:nowrap; }
    .btn-del-row { background:none; border:none; cursor:pointer; color:#dc2626; font-size:.95rem; padding:5px 6px; border-radius:6px; transition:background .15s; }
    .btn-del-row:hover { background:#fef2f2; }
    .add-row-btn {
      margin-top:12px; background:none; border:2px dashed var(--verde-cl); color:var(--verde);
      font-weight:600; font-size:.85rem; padding:8px 18px; border-radius:8px;
      cursor:pointer; transition:background .2s; display:inline-flex; align-items:center; gap:6px;
    }
    .add-row-btn:hover { background:rgba(46,125,94,.07); }
    /* Totales */
    .totales-wrap { display:flex; justify-content:flex-end; margin-top:18px; }
    .totales-box { min-width:260px; border:1.5px solid var(--borde); border-radius:10px; overflow:hidden; }
    .totales-row { display:flex; justify-content:space-between; align-items:center; padding:9px 16px; font-size:.9rem; border-bottom:1px solid var(--borde); background:#fafafa; }
    .totales-row:last-child { border-bottom:none; }
    .totales-row.total-final { background:var(--azul); color:#fff; font-weight:700; font-size:1rem; }
    .totales-row.total-final span:last-child { color:var(--oro); font-size:1.05rem; }
    /* Alcances */
    .checks-grid { display:grid; grid-template-columns:1fr 1fr; gap:8px 18px; margin-bottom:14px; }
    .check-item {
      display:flex; align-items:flex-start; gap:9px; font-size:.87rem;
      cursor:pointer; padding:8px 12px; border-radius:8px;
      border:1.5px solid var(--borde); transition:border-color .2s, background .2s; line-height:1.4;
    }
    .check-item:has(input:checked) { border-color:var(--verde); background:rgba(46,125,94,.05); }
    .check-item input { accent-color:var(--verde); width:15px; height:15px; flex-shrink:0; margin-top:2px; }
    /* Acciones */
    .acciones-bar { display:flex; gap:10px; justify-content:flex-end; padding:18px 0 4px; }
    .btn-cot { font-weight:600; font-size:.9rem; padding:10px 24px; border-radius:8px; border:none; cursor:pointer; display:inline-flex; align-items:center; gap:7px; transition:transform .12s, background .15s; }
    .btn-cot:active { transform:translateY(1px); }
    .btn-cot.secundario { background:var(--blanco); color:var(--azul); border:2px solid var(--azul); }
    .btn-cot.secundario:hover { background:#f0f4f8; }
    .btn-cot.primario { background:var(--verde); color:#fff; box-shadow:0 3px 12px rgba(46,125,94,.30); }
    .btn-cot.primario:hover { background:#246b4e; }
    .grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:0 20px; }
    .full { grid-column:1 / -1; }
    @media(max-width:640px) {
      .grid-2, .checks-grid { grid-template-columns:1fr; }
      .cot-card-body { padding:16px; }
    }
  </style>
</head>
<body>
<?php require_once __DIR__ . "/../includes/navbar.php"; ?>

<div class="hero-section">
  <div class="container hero-content">
    <div class="breadcrumb-custom">
      <a href="/PROATAM/index.php"><i class="bi bi-house-door"></i> Inicio</a>
      <span>/</span>
      <a href="/PROATAM/cotizaciones/list_cotizaciones.php">Cotizaciones</a>
      <span>/</span><span>Nueva</span>
    </div>
    <div class="hero-empresa">
      <img src="/PROATAM/assets/img/logo_proat.png" alt="Logo">
      <div>
        <div class="hero-empresa-nombre">PROATAM S.A. DE C.V.</div>
        <div class="hero-empresa-sub">Nueva Cotización</div>
      </div>
    </div>
    <h1 class="hero-title text-white">Generar Cotización</h1>
    <div class="folio-hero">
      <i class="bi bi-hash"></i>
      <span id="folioDisplay"><?= htmlspecialchars($folioInicial) ?></span>
    </div>
  </div>
</div>

<div class="content-wrapper">
  <div class="form-container">
    <div class="form-body">

      <!-- El form ya NO hace submit directo — todo va por JS -->
      <form id="formCotizacion">

        <!-- ENTIDAD EMISORA -->
        <div class="cot-card">
          <div class="cot-card-header">
            <div class="icon-box"><i class="bi bi-building"></i></div>
            <h6>Entidad Emisora</h6>
          </div>
          <div class="cot-card-body">
            <p style="font-size:.82rem;color:var(--gris);margin-bottom:14px;">
              Define el logo, colores y nombre que aparecerán en el PDF.
            </p>
            <div class="field" style="max-width:360px;">
              <label>Empresa emisora</label>
              <select name="entidad" id="entidadSelect" onchange="actualizarFolio()">
                <?php foreach ($entidades as $clave => $nombre): ?>
                  <option value="<?= htmlspecialchars($clave) ?>"
                    <?= $clave === $entidadSel ? 'selected' : '' ?>>
                    <?= htmlspecialchars($nombre) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>

        <!-- DATOS DEL CLIENTE -->
        <div class="cot-card">
          <div class="cot-card-header">
            <div class="icon-box"><i class="bi bi-person-lines-fill"></i></div>
            <h6>Datos del Cliente</h6>
          </div>
          <div class="cot-card-body">
            <div class="grid-2">
              <div class="field">
                <label>Atención a <span class="text-danger">*</span></label>
                <input type="text" name="atencion" placeholder="Nombre del contacto" required>
              </div>
              <div class="field">
                <label>Compañía / Empresa</label>
                <input type="text" name="compania" placeholder="Razón social o nombre">
              </div>
              <div class="field">
                <label>Fecha</label>
                <input type="date" name="fecha" value="<?= date('Y-m-d') ?>" required>
              </div>
              <div class="field">
                <label>Lugar / Ciudad</label>
                <input type="text" name="lugar" placeholder="Ciudad, Estado">
              </div>
            </div>
          </div>
        </div>

        <!-- CONCEPTOS -->
        <div class="cot-card">
          <div class="cot-card-header">
            <div class="icon-box"><i class="bi bi-table"></i></div>
            <h6>Conceptos de Obra / Servicios <span class="text-danger">*</span></h6>
          </div>
          <div class="cot-card-body">
            <div style="overflow-x:auto;">
              <table class="conceptos" id="tablaConceptos">
                <thead>
                  <tr>
                    <th>No.</th>
                    <th>Descripción del Concepto</th>
                    <th>Unidad</th>
                    <th>Cantidad</th>
                    <th>Precio Unit.</th>
                    <th>Importe</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody id="tbodyConceptos"></tbody>
              </table>
            </div>
            <button type="button" class="add-row-btn" onclick="agregarFila()">
              <i class="bi bi-plus-circle"></i> Agregar concepto
            </button>
            <div class="totales-wrap">
              <div class="totales-box">
                <div class="totales-row">
                  <span>Subtotal</span><span id="dispSubtotal">$0.00</span>
                </div>
                <div class="totales-row">
                  <span style="display:flex;align-items:center;gap:8px;">
                    IVA
                    <select id="selectIva" onchange="recalcular()"
                      style="border:1.5px solid var(--borde);border-radius:5px;padding:2px 6px;font-size:.82rem;background:var(--fondo);outline:none;">
                      <option value="16">16%</option>
                      <option value="8">8%</option>
                      <option value="0">0%</option>
                    </select>
                  </span>
                  <span id="dispIva">$0.00</span>
                </div>
                <div class="totales-row total-final">
                  <span>TOTAL</span><span id="dispTotal">$0.00</span>
                </div>
              </div>
            </div>
            <!-- Campos hidden para totales y emisor -->
            <input type="hidden" name="folio"         id="folioInput" value="<?= htmlspecialchars($folioInicial) ?>">
            <input type="hidden" name="folio_num"     id="folioNum"   value="">
            <input type="hidden" name="subtotal"      id="hSubtotal">
            <input type="hidden" name="iva"           id="hIva">
            <input type="hidden" name="total"         id="hTotal">
            <input type="hidden" name="tasa_iva"      id="hTasaIva">
            <input type="hidden" name="emisor_nombre" value="<?= htmlspecialchars($emisorNombre) ?>">
            <input type="hidden" name="emisor_depto"  value="<?= htmlspecialchars($emisorDepto) ?>">
          </div>
        </div>

        <!-- ALCANCES -->
        <div class="cot-card">
          <div class="cot-card-header">
            <div class="icon-box"><i class="bi bi-check2-square"></i></div>
            <h6>Alcances Incluidos</h6>
          </div>
          <div class="cot-card-body">
            <div class="checks-grid">
              <?php
              $alcancesOpc = [
                'ejecucion'   => 'Ejecución de los trabajos solicitados conforme a lo indicado por el cliente',
                'materiales'  => 'Suministro de materiales y mano de obra especializada',
                'supervision' => 'Supervisión técnica durante toda la obra',
                'limpieza'    => 'Limpieza general al finalizar los trabajos',
                'garantia'    => 'Garantía sobre los trabajos realizados',
                'herramienta' => 'Herramienta y equipo necesario para la ejecución',
                'seguridad'   => 'Medidas de seguridad e higiene en obra',
                'entrega'     => 'Entrega de memoria fotográfica al concluir',
              ];
              foreach ($alcancesOpc as $key => $label): ?>
                <label class="check-item">
                  <input type="checkbox" name="alcances[]" value="<?= $key ?>" checked>
                  <?= htmlspecialchars($label) ?>
                </label>
              <?php endforeach; ?>
            </div>
            <div class="field">
              <label>Alcances adicionales (opcional)</label>
              <textarea name="alcances_extra" placeholder="Describe aquí cualquier alcance especial no listado arriba..."></textarea>
            </div>
          </div>
        </div>

        <!-- CONDICIONES -->
        <div class="cot-card">
          <div class="cot-card-header">
            <div class="icon-box"><i class="bi bi-file-text"></i></div>
            <h6>Condiciones Generales</h6>
          </div>
          <div class="cot-card-body">
            <div class="grid-2">
              <div class="field">
                <label>Tiempo de ejecución</label>
                <input type="text" name="tiempo" placeholder="Ej: 15 días hábiles">
              </div>
              <div class="field">
                <label>Forma de pago</label>
                <input type="text" name="forma_pago" placeholder="Ej: 50% anticipo, 50% al término">
              </div>
              <div class="field">
                <label>Vigencia de la cotización</label>
                <input type="text" name="vigencia" value="30 días naturales">
              </div>
              <div class="field">
                <label>Moneda</label>
                <select name="moneda">
                  <option value="MXN">MXN — Pesos Mexicanos</option>
                  <option value="USD">USD — Dólares Americanos</option>
                </select>
              </div>
              <div class="field full">
                <label>Notas / Observaciones</label>
                <textarea name="notas" placeholder="Cualquier nota relevante para el cliente..."></textarea>
              </div>
            </div>
          </div>
        </div>

        <!-- BOTONES -->
        <div class="acciones-bar">
          <button type="button" class="btn-cot secundario" onclick="history.back()">
            <i class="bi bi-arrow-left"></i> Cancelar
          </button>
          <button type="button" class="btn-cot primario" onclick="guardarYDescargar()">
            <i class="bi bi-file-earmark-arrow-down"></i> Generar & Descargar PDF
          </button>
        </div>

      </form>
    </div>
  </div>
</div>

<script>
const opcionesUnidad = `<option value="">-- Unidad --</option><?php
  foreach ($unidades as $u)
    echo '<option value="' . htmlspecialchars($u['nombre'], ENT_QUOTES) . '">' . htmlspecialchars($u['nombre']) . '</option>';
?>`;
let filaCount = 0;

function fmt(n) {
  return new Intl.NumberFormat('es-MX', { style:'currency', currency:'MXN' }).format(n);
}

function agregarFila() {
  filaCount++;
  const tbody = document.getElementById('tbodyConceptos');
  const tr    = document.createElement('tr');
  tr.dataset.idx = filaCount;
  tr.innerHTML = `
    <td>${filaCount}</td>
    <td><textarea name="desc[]" rows="2" placeholder="Descripción del concepto..."></textarea></td>
    <td><select name="unidad[]">${opcionesUnidad}</select></td>
    <td><input type="number" name="cantidad[]" min="0" step="any" value="1" oninput="recalcular()"></td>
    <td><input type="number" name="precio[]"   min="0" step="any" value="0" oninput="recalcular()"></td>
    <td class="importe-cell" id="imp-${filaCount}">$0.00</td>
    <td><button type="button" class="btn-del-row" onclick="eliminarFila(this)" title="Eliminar">
      <i class="bi bi-trash3"></i>
    </button></td>
  `;
  tbody.appendChild(tr);
  recalcular();
}

function eliminarFila(btn) {
  btn.closest('tr').remove();
  document.querySelectorAll('#tbodyConceptos tr').forEach((tr, i) => {
    tr.cells[0].textContent = i + 1;
  });
  recalcular();
}

function recalcular() {
  let subtotal = 0;
  document.querySelectorAll('#tbodyConceptos tr').forEach(tr => {
    const cant   = parseFloat(tr.querySelector('input[name="cantidad[]"]')?.value) || 0;
    const precio = parseFloat(tr.querySelector('input[name="precio[]"]')?.value)   || 0;
    const imp    = cant * precio;
    subtotal += imp;
    const cell = document.getElementById('imp-' + tr.dataset.idx);
    if (cell) cell.textContent = fmt(imp);
  });
  const tasaPct = parseFloat(document.getElementById('selectIva').value) || 0;
  const iva   = subtotal * (tasaPct / 100);
  const total = subtotal + iva;
  document.getElementById('dispSubtotal').textContent = fmt(subtotal);
  document.getElementById('dispIva').textContent      = fmt(iva);
  document.getElementById('dispTotal').textContent    = fmt(total);
  // Actualizar hiddens
  document.getElementById('hSubtotal').value = subtotal.toFixed(2);
  document.getElementById('hIva').value      = iva.toFixed(2);
  document.getElementById('hTotal').value    = total.toFixed(2);
  document.getElementById('hTasaIva').value  = tasaPct;
}

function validarFormulario() {
  const atencion = document.querySelector('input[name="atencion"]').value.trim();
  if (!atencion) {
    Swal.fire({ icon:'warning', title:'Campo obligatorio', text:'Debes especificar a quién va dirigida la cotización.' });
    return false;
  }
  let tieneConcepto = false;
  document.querySelectorAll('textarea[name="desc[]"]').forEach(t => {
    if (t.value.trim()) tieneConcepto = true;
  });
  if (!tieneConcepto) {
    Swal.fire({ icon:'warning', title:'Conceptos requeridos', text:'Agrega al menos un concepto con descripción.' });
    return false;
  }
  return true;
}

async function guardarYDescargar() {
  if (!validarFormulario()) return;

  // Mostrar loading
  Swal.fire({
    title: 'Guardando cotización...',
    text: 'Por favor espera',
    allowOutsideClick: false,
    didOpen: () => Swal.showLoading()
  });

  const data = new FormData(document.getElementById('formCotizacion'));

  try {
    const resp = await fetch('/PROATAM/cotizaciones/save_cotizacion.php', {
      method: 'POST',
      body: data
    });

    // Verificar que la respuesta sea JSON
    const text = await resp.text();
    let result;
    try {
      result = JSON.parse(text);
    } catch (e) {
      console.error('Respuesta no-JSON:', text);
      Swal.fire('Error', 'Respuesta inesperada del servidor. Revisa la consola.', 'error');
      return;
    }

    if (result.status === 'success') {
      Swal.close();
      // Descargar PDF usando el ID guardado en BD
      window.location.href = '/PROATAM/cotizaciones/descargar_cotizacion.php?id=' + result.id;
      // Redirigir a la lista tras 2s
      setTimeout(() => {
        window.location.href = '/PROATAM/cotizaciones/list_cotizaciones.php';
      }, 2500);
    } else {
      Swal.fire('Error al guardar', result.message || 'Error desconocido.', 'error');
    }
  } catch (err) {
    console.error(err);
    Swal.fire('Error de red', 'No se pudo conectar con el servidor.', 'error');
  }
}

function actualizarFolio() {
  const entidad = document.getElementById('entidadSelect').value;
  fetch('/PROATAM/cotizaciones/generar_folio.php?entidad=' + encodeURIComponent(entidad))
    .then(r => r.json())
    .then(data => {
      document.getElementById('folioDisplay').textContent = data.folio;
      document.getElementById('folioInput').value = data.folio;
      document.getElementById('folioNum').value   = data.numero;
    })
    .catch(e => console.error('Error folio:', e));
}

// Inicializar
agregarFila();
recalcular();
</script>

<?php include __DIR__ . "/../includes/footer.php"; ?>
<script src="/PROATAM/assets/scripts/session_timeout.js"></script>
</body>
</html>