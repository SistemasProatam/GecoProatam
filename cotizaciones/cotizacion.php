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

// Generar folio por entidad
function generarFolio(string $entidad = 'PROATAM'): string
{
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

require_once __DIR__ . "/../conexion.php";
$unidades = [];
$resU = $conn->query("SELECT id, nombre FROM unidades WHERE activo = 1 ORDER BY nombre ASC");
if ($resU) while ($rowU = $resU->fetch_assoc()) $unidades[] = $rowU;

// Obtener datos de entidad
$sql_entidades = "SELECT id, nombre FROM entidades ORDER BY nombre ASC";
$result_entidades = $conn->query($sql_entidades);
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Nueva Cotización — <?= htmlspecialchars($folioInicial) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"
    integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/navbar.css" />
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/cotizaciones.css" />
</head>

<body>
  <?php
require_once __DIR__ . "/../includes/navbar.php"; ?>

  <!-- HERO SECTION -->
  <div class="hero-section">
    <div class="container hero-content">
      <div class="breadcrumb-custom">
        <a href="<?= BASE_URL ?>/index.php"><i class="bi bi-house-door"></i> Inicio</a>
        <span>/</span>
        <a href="<?= BASE_URL ?>/cotizaciones/list_cotizaciones.php">Cotizaciones</a>
        <span>/</span><span>Nueva</span>
      </div>
      <div class="row align-items-center">
        <div class="col-lg-8">
          <h1 class="hero-title">Generar Cotización</h1>
          <p class="mt-2 text-white-50">Folio actual: <strong class="text-white" id="folioDisplay"><?= htmlspecialchars($folioInicial) ?></strong></p>
        </div>
        <div class="col-lg-4 text-lg-end d-none d-lg-block">
          <img src="<?= BASE_URL ?>/assets/img/logo_proat.png" alt="Logo" style="max-height: 80px; filter: brightness(0) invert(1); opacity: 0.8;">
        </div>
      </div>
    </div>
  </div>

  <!-- MAIN CONTENT -->
  <div class="content-wrapper">
    <div class="form-container">
      <div class="form-body">

        <form id="formCotizacion">
          <!-- ENTIDAD EMISORA -->
          <div class="section-title">
            <i class="bi bi-building"></i> Entidad Emisora
          </div>
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">Empresa emisora <span class="text-danger">*</span></label>
                <select name="entidad" id="entidadSelect" class="form-select" onchange="actualizarFolio()">
                  <?php
while ($row = $result_entidades->fetch_assoc()): ?>
                    <option value="<?= htmlspecialchars($row['id']) ?>" <?= $row['id'] === $entidadSel ? 'selected' : '' ?>>
                      <?= htmlspecialchars($row['nombre']) ?>
                    </option>
                  <?php
endwhile; ?>
                </select>
              </div>
            </div>
          </div>

          <!-- DATOS DEL CLIENTE -->
          <div class="section-title">
            <i class="bi bi-person-lines-fill"></i> Datos del Cliente
          </div>
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">Atención a <span class="text-danger">*</span></label>
                <input type="text" name="atencion" class="form-control" placeholder="Nombre del contacto" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">Compañía / Empresa</label>
                <input type="text" name="compania" class="form-control" placeholder="Razón social o nombre">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">Fecha</label>
                <input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">Lugar / Ciudad</label>
                <input type="text" name="lugar" class="form-control" placeholder="Ciudad, Estado">
              </div>
            </div>
          </div>

          <!-- CONCEPTOS -->
          <div class="section-title">
            <i class="bi bi-table"></i> Conceptos de Obra / Servicios
          </div>
          <div class="table-responsive-custom">
            <table class="table" id="tablaConceptos">
              <thead>
                <tr>
                  <th style="width: 5%">#</th>
                  <th style="width: 40%">Descripción</th>
                  <th style="width: 10%">Unidad</th>
                  <th style="width: 10%">Cant.</th>
                  <th style="width: 15%">P. Unitario</th>
                  <th style="width: 15%">Importe</th>
                  <th style="width: 5%"></th>
                </tr>
              </thead>
              <tbody id="tbodyConceptos"></tbody>
            </table>
          </div>

          <div class="text-end mb-4">
            <button type="button" class="button-56" onclick="agregarFila()">
              <i class="bi bi-plus-lg"></i> Agregar concepto
            </button>
          </div>

          <div class="total-section">
            <div class="total-row">
              <span>Subtotal:</span>
              <span id="dispSubtotal">$0.00</span>
            </div>
            <div class="total-row align-items-center">
              <span>IVA:</span>
              <div class="d-flex align-items-center gap-2">
                <select id="selectIva" class="form-select py-1" style="width: 80px;" onchange="recalcular()">
                  <option value="16">16%</option>
                  <option value="8">8%</option>
                  <option value="0">0%</option>
                </select>
                <span id="dispIva">$0.00</span>
              </div>
            </div>
            <div class="total-row final">
              <span>TOTAL:</span>
              <span id="dispTotal">$0.00</span>
            </div>
          </div>

          <!-- Hidden fields -->
          <input type="hidden" name="folio" id="folioInput" value="<?= htmlspecialchars($folioInicial) ?>">
          <input type="hidden" name="folio_num" id="folioNum" value="">
          <input type="hidden" name="subtotal" id="hSubtotal">
          <input type="hidden" name="iva" id="hIva">
          <input type="hidden" name="total" id="hTotal">
          <input type="hidden" name="tasa_iva" id="hTasaIva">
          <input type="hidden" name="emisor_nombre" value="<?= htmlspecialchars($emisorNombre) ?>">
          <input type="hidden" name="emisor_depto" value="<?= htmlspecialchars($emisorDepto) ?>">

          <!-- ALCANCES -->
          <div class="section-title">
            <i class="bi bi-check2-square"></i> Alcances Incluidos
          </div>
          <div class="checks-grid">
            <?php
$alcancesOpc = [
              'ejecucion'   => 'Ejecución de trabajos',
              'materiales'  => 'Materiales y mano de obra',
              'supervision' => 'Supervisión técnica',
              'limpieza'    => 'Limpieza final',
              'garantia'    => 'Garantía de calidad',
              'herramienta' => 'Herramienta y equipo',
              'seguridad'   => 'Seguridad e higiene',
              'entrega'     => 'Memoria fotográfica',
            ];
            foreach ($alcancesOpc as $key => $label): ?>
              <label class="check-item">
                <input type="checkbox" name="alcances[]" value="<?= $key ?>" checked>
                <span><?= htmlspecialchars($label) ?></span>
              </label>
            <?php
endforeach; ?>
          </div>
          <div class="form-group mt-3">
            <label class="form-label">Otros alcances o precisiones</label>
            <textarea name="alcances_extra" class="form-control" rows="3" placeholder="Opcional..."></textarea>
          </div>

          <!-- CONDICIONES -->
          <div class="section-title">
            <i class="bi bi-file-text"></i> Condiciones Generales
          </div>
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">Tiempo de ejecución</label>
                <input type="text" name="tiempo" class="form-control" placeholder="Ej: 15 días">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">Forma de pago</label>
                <input type="text" name="forma_pago" class="form-control" placeholder="Ej: 50% anticipo">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">Moneda</label>
                <select name="moneda" class="form-select">
                  <option value="MXN">MXN — Pesos Mexicanos</option>
                  <option value="USD">USD — Dólares Americanos</option>
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">Vigencia</label>
                <input type="text" name="vigencia" class="form-control" value="30 días naturales">
              </div>
            </div>
            <div class="col-12">
              <div class="form-group">
                <label class="form-label">Notas adicionales</label>
                <textarea name="notas" class="form-control" rows="3" placeholder="Observaciones..."></textarea>
              </div>
            </div>
          </div>

          <!-- BOTONES -->
          <div class="d-flex justify-content-center gap-4 mt-5">
            <a href="list_cotizaciones.php" class="button-cancel">
              <i class="bi bi-arrow-left"></i> Volver
            </a>
            <button type="button" class="button-57" onclick="guardarYDescargar()">
              <i class="bi bi-file-earmark-pdf"></i> Generar & Descargar PDF
            </button>
          </div>

        </form>
      </div>
    </div>
  </div>

  <script>
    const BASE_URL = '<?= BASE_URL ?>';
    const opcionesUnidad = `<option value="">-- Unidad --</option><?php
foreach ($unidades as $u)
                                                                    echo '<option value="' . htmlspecialchars($u['nombre'], ENT_QUOTES) . '">' . htmlspecialchars($u['nombre']) . '</option>';
                                                                  ?>`;
    let filaCount = 0;

    function fmt(n) {
      return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN'
      }).format(n);
    }

    function agregarFila() {
      filaCount++;
      const tbody = document.getElementById('tbodyConceptos');
      const tr = document.createElement('tr');
      tr.dataset.idx = filaCount;
      tr.innerHTML = `
    <td class="text-muted fw-bold">${filaCount}</td>
    <td><textarea name="desc[]" class="form-control" rows="2" placeholder="Descripción del concepto..."></textarea></td>
    <td><select name="unidad[]" class="form-select">${opcionesUnidad}</select></td>
    <td><input type="number" name="cantidad[]" class="form-control text-center" min="0" step="any" value="1" oninput="recalcular()"></td>
    <td><input type="number" name="precio[]" class="form-control text-end" min="0" step="any" value="0" oninput="recalcular()"></td>
    <td class="fw-bold text-primary text-end" id="imp-${filaCount}">$0.00</td>
    <td class="text-center">
      <button type="button" class="btn btn-link text-danger p-0" onclick="eliminarFila(this)" title="Eliminar">
        <i class="bi bi-trash3 fs-5"></i>
      </button>
    </td>
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
        const cant = parseFloat(tr.querySelector('input[name="cantidad[]"]')?.value) || 0;
        const precio = parseFloat(tr.querySelector('input[name="precio[]"]')?.value) || 0;
        const imp = cant * precio;
        subtotal += imp;
        const cell = document.getElementById('imp-' + tr.dataset.idx);
        if (cell) cell.textContent = fmt(imp);
      });
      const tasaPct = parseFloat(document.getElementById('selectIva').value) || 0;
      const iva = subtotal * (tasaPct / 100);
      const total = subtotal + iva;
      document.getElementById('dispSubtotal').textContent = fmt(subtotal);
      document.getElementById('dispIva').textContent = fmt(iva);
      document.getElementById('dispTotal').textContent = fmt(total);
      // Actualizar hiddens
      document.getElementById('hSubtotal').value = subtotal.toFixed(2);
      document.getElementById('hIva').value = iva.toFixed(2);
      document.getElementById('hTotal').value = total.toFixed(2);
      document.getElementById('hTasaIva').value = tasaPct;
    }

    function validarFormulario() {
      const atencion = document.querySelector('input[name="atencion"]').value.trim();
      if (!atencion) {
        Swal.fire({
          icon: 'warning',
          title: 'Campo obligatorio',
          text: 'Debes especificar a quién va dirigida la cotización.'
        });
        return false;
      }
      let tieneConcepto = false;
      document.querySelectorAll('textarea[name="desc[]"]').forEach(t => {
        if (t.value.trim()) tieneConcepto = true;
      });
      if (!tieneConcepto) {
        Swal.fire({
          icon: 'warning',
          title: 'Conceptos requeridos',
          text: 'Agrega al menos un concepto con descripción.'
        });
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
        const resp = await fetch(BASE_URL + '/cotizaciones/save_cotizacion.php', {
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
          window.location.href = BASE_URL + '/cotizaciones/descargar_cotizacion.php?id=' + result.id;
          // Redirigir a la lista tras 2s
          setTimeout(() => {
            window.location.href = BASE_URL + '/cotizaciones/list_cotizaciones.php';
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
      fetch(BASE_URL + '/cotizaciones/generar_folio.php?entidad=' + encodeURIComponent(entidad))
        .then(r => r.json())
        .then(data => {
          document.getElementById('folioDisplay').textContent = data.folio;
          document.getElementById('folioInput').value = data.folio;
          document.getElementById('folioNum').value = data.numero;
        })
        .catch(e => console.error('Error folio:', e));
    }

    // Inicializar
    agregarFila();
    recalcular();
  </script>

  <?php
include __DIR__ . "/../includes/footer.php"; ?>
  <script src="<?= BASE_URL ?>/assets/scripts/session_timeout.js"></script>
</body>

</html>


