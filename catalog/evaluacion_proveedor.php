<?php
// Incluir el gestor de sesiones UNA sola vez
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

// Verificar sesión y prevenir caching
checkSession();
preventCaching();

require_once __DIR__ . "/../conexion.php";

// Obtener ID del proveedor desde la URL
$proveedor_id = $_GET['id'] ?? 0;

// Validar que el proveedor exista
if ($proveedor_id <= 0) {
    header("Location: list_catalog.php?entidad=proveedores");
    exit();
}

// Obtener información del proveedor
$sql_proveedor = "SELECT id, razon_social, rfc, nombre, telefono, email, direccion, contacto 
                  FROM proveedores 
                  WHERE id = ? AND activo = 1";
$stmt_proveedor = $conn->prepare($sql_proveedor);
$stmt_proveedor->bind_param("i", $proveedor_id);
$stmt_proveedor->execute();
$result_proveedor = $stmt_proveedor->get_result();

if ($result_proveedor->num_rows === 0) {
    header("Location: list_catalog.php?entidad=proveedores");
    exit();
}

$proveedor = $result_proveedor->fetch_assoc();

// Obtener información del usuario actual
$usuario_id = $_SESSION['user_id'] ?? 0;

// Procesar el formulario si se envió
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $razon_social = $proveedor['razon_social'];
    $rfc = $_POST['supplierRFC'] ?? '';
    $lugar_fecha = $_POST['evaluationDate'] ?? '';
    $contrato_numero = $_POST['contractNumber'] ?? '';
    
    // Calificaciones
    $calidad = intval($_POST['qualityRating'] ?? 0);
    $cumplimiento_entregas = intval($_POST['deliveryRating'] ?? 0);
    $precio_condiciones = intval($_POST['priceRating'] ?? 0);
    $cumplimiento_legal = intval($_POST['legalRating'] ?? 0);
    $atencion_servicio = intval($_POST['serviceRating'] ?? 0);
    
    // Calcular resultados
    $calidad_resultado = $calidad * 30;
    $cumplimiento_entregas_resultado = $cumplimiento_entregas * 25;
    $precio_condiciones_resultado = $precio_condiciones * 20;
    $cumplimiento_legal_resultado = $cumplimiento_legal * 15;
    $atencion_servicio_resultado = $atencion_servicio * 10;
    
    $total_puntuacion = $calidad_resultado + $cumplimiento_entregas_resultado + 
                       $precio_condiciones_resultado + $cumplimiento_legal_resultado + 
                       $atencion_servicio_resultado;
    
    // Determinar resultado final
    if ($total_puntuacion >= 450) {
        $resultado_final = 'excelente';
    } elseif ($total_puntuacion >= 400) {
        $resultado_final = 'bueno';
    } elseif ($total_puntuacion >= 350) {
        $resultado_final = 'regular';
    } else {
        $resultado_final = 'no_aprobado';
    }
    
    $observaciones = $_POST['observations'] ?? '';
    $responsables = $_POST['responsibles'] ?? '';
    
    // Insertar en la base de datos
    $sql_insert = "INSERT INTO evaluaciones_proveedores (
        proveedor_id, razon_social, rfc, lugar_fecha, contrato_numero,
        calidad_calificacion, cumplimiento_entregas_calificacion, 
        precio_condiciones_calificacion, cumplimiento_legal_calificacion, 
        atencion_servicio_calificacion,
        calidad_resultado, cumplimiento_entregas_resultado, 
        precio_condiciones_resultado, cumplimiento_legal_resultado, 
        atencion_servicio_resultado, total_puntuacion, resultado_final,
        observaciones, responsables, usuario_creador_id
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt_insert = $conn->prepare($sql_insert);
    $stmt_insert->bind_param(
        "issssiiiiiddddddsssi",
        $proveedor_id, $razon_social, $rfc, $lugar_fecha, $contrato_numero,
        $calidad, $cumplimiento_entregas, $precio_condiciones, $cumplimiento_legal, $atencion_servicio,
        $calidad_resultado, $cumplimiento_entregas_resultado, $precio_condiciones_resultado,
        $cumplimiento_legal_resultado, $atencion_servicio_resultado, $total_puntuacion, $resultado_final,
        $observaciones, $responsables, $usuario_id
    );
    
    if ($stmt_insert->execute()) {
        $mensaje_exito = "Evaluación guardada correctamente";
    } else {
        $mensaje_error = "Error al guardar la evaluación: " . $conn->error;
    }
}
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/core/modules.css?v=2.0">



<title>Evaluación de Proveedor | GECO PROATAM</title>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="orders-page-container">

  <!-- Page Header -->
  <div class="orders-page-header">
    <div class="orders-page-header-info">
      <nav class="orders-breadcrumb">
        <a href="<?= BASE_URL ?>/index.php">Inicio</a>
        <span>›</span>
        <a href="<?= BASE_URL ?>/catalog/list_catalog.php?entidad=proveedores">Proveedores</a>
        <span>›</span>
        <span>Evaluación de Proveedor</span>
      </nav>
      <h1 class="orders-page-title">Evaluación de Proveedor</h1>
    </div>
    <div class="ms-auto">
      <button class="btn-geco-secondary" onclick="verHistorial()" title="Historial de evaluaciones">
        <i class="fa-solid fa-clock-rotate-left"></i> Historial
      </button>
    </div>
  </div>

  <!-- Alerts -->
  <?php if (isset($mensaje_exito)): ?>
    <script>
      document.addEventListener('DOMContentLoaded', () => {
        UI.toast.success(<?= json_encode($mensaje_exito) ?>);
      });
    </script>
  <?php endif; ?>

  <?php if (isset($mensaje_error)): ?>
    <script>
      document.addEventListener('DOMContentLoaded', () => {
        UI.toast.error(<?= json_encode($mensaje_error) ?>);
      });
    </script>
  <?php endif; ?>

  <!-- Main Form Container -->
  <div class="mt-4">
    <form method="POST" id="evaluationForm" onsubmit="return confirmarGuardado(this, event)">
      
      <!-- Section 1: Información General (Todo Width) -->
      <div class="oc-card mb-4">
        <div class="oc-card-header">
          <span class="oc-card-header__title"><i class="fa-solid fa-circle-info"></i> Información General</span>
        </div>
        <div class="oc-card-body">
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">Nombre o Razón Social del Proveedor:</label>
                <input type="text" class="form-control"
                       value="<?= htmlspecialchars($proveedor['razon_social']) ?>"
                       readonly />
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">CIF. o R.F.C.:</label>
                <input type="text" class="form-control" name="supplierRFC"
                       value="<?= htmlspecialchars($proveedor['rfc'] ?? '') ?>" required>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">Lugar y Fecha de Elaboración:</label>
                <input type="text" class="form-control" name="evaluationDate"
                       value="PROATAM S.A. DE C.V. - <?= date('d/m/Y') ?>" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">Contrato No.:</label>
                <input type="text" class="form-control" name="contractNumber" required>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="oc-form-layout">
        <!-- ─── MAIN COLUMN (Left) ──────────────────────────────────────── -->
        <div class="oc-form-layout-main">
          
          <!-- Section 3: Evaluación del Proveedor con Escala Adentro -->
          <div class="oc-card mb-4">
            <div class="oc-card-header">
              <span class="oc-card-header__title"><i class="fa-solid fa-star"></i> Evaluación del Proveedor</span>
            </div>
            <div class="oc-card-body">
              
              <!-- Escala de Calificación dentro de la card de evaluación, arriba de la tabla -->
              <div class="mb-4">
                <label class="form-label" style="font-weight: 600; font-size: 0.85rem; color: var(--s-800); margin-bottom: 0.5rem;">
                  <i class="fa-solid fa-gauge-high"></i> Escala de Calificación
                </label>
                <div class="rating-options">
                  <div data-value="1"><strong>1</strong><br>Muy Deficiente</div>
                  <div data-value="2"><strong>2</strong><br>Deficiente</div>
                  <div data-value="3"><strong>3</strong><br>Regular</div>
                  <div data-value="4"><strong>4</strong><br>Bueno</div>
                  <div data-value="5"><strong>5</strong><br>Excelente</div>
                </div>
              </div>

              <div class="orders-table-wrap">
                <table class="orders-table">
                  <thead>
                    <tr>
                      <th>Criterio</th>
                      <th>Descripción</th>
                      <th>Ponderación</th>
                      <th>Calificación</th>
                      <th>Resultado</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td>Calidad</td>
                      <td>Cumplimiento con especificaciones técnicas, ausencia de defectos.</td>
                      <td>30%</td>
                      <td>
                        <select class="form-select rating-select" name="qualityRating" data-weight="30" id="qualityRating" required>
                          <option value="0">Seleccionar</option>
                          <option value="1">1</option>
                          <option value="2">2</option>
                          <option value="3">3</option>
                          <option value="4">4</option>
                          <option value="5">5</option>
                        </select>
                      </td>
                      <td id="qualityResult">0</td>
                    </tr>
                    <tr>
                      <td>Cumplimiento en Entregas</td>
                      <td>Puntualidad, cumplimiento de plazos acordados.</td>
                      <td>25%</td>
                      <td>
                        <select class="form-select rating-select" name="deliveryRating" data-weight="25" id="deliveryRating" required>
                          <option value="0">Seleccionar</option>
                          <option value="1">1</option>
                          <option value="2">2</option>
                          <option value="3">3</option>
                          <option value="4">4</option>
                          <option value="5">5</option>
                        </select>
                      </td>
                      <td id="deliveryResult">0</td>
                    </tr>
                    <tr>
                      <td>Precio y Condiciones Comerciales</td>
                      <td>Competitividad de precios, claridad en pagos y facturación.</td>
                      <td>20%</td>
                      <td>
                        <select class="form-select rating-select" name="priceRating" data-weight="20" id="priceRating" required>
                          <option value="0">Seleccionar</option>
                          <option value="1">1</option>
                          <option value="2">2</option>
                          <option value="3">3</option>
                          <option value="4">4</option>
                          <option value="5">5</option>
                        </select>
                      </td>
                      <td id="priceResult">0</td>
                    </tr>
                    <tr>
                      <td>Cumplimiento Legal y Normativo</td>
                      <td>Documentación vigente (fiscal, laboral, seguridad, ambiental).</td>
                      <td>15%</td>
                      <td>
                        <select class="form-select rating-select" name="legalRating" data-weight="15" id="legalRating" required>
                          <option value="0">Seleccionar</option>
                          <option value="1">1</option>
                          <option value="2">2</option>
                          <option value="3">3</option>
                          <option value="4">4</option>
                          <option value="5">5</option>
                        </select>
                      </td>
                      <td id="legalResult">0</td>
                    </tr>
                    <tr>
                      <td>Atención y Servicio Postventa</td>
                      <td>Respuesta a incidencias, comunicación y soporte.</td>
                      <td>10%</td>
                      <td>
                        <select class="form-select rating-select" name="serviceRating" data-weight="10" id="serviceRating" required>
                          <option value="0">Seleccionar</option>
                          <option value="1">1</option>
                          <option value="2">2</option>
                          <option value="3">3</option>
                          <option value="4">4</option>
                          <option value="5">5</option>
                        </select>
                      </td>
                      <td id="serviceResult">0</td>
                    </tr>
                    <tr class="table-secondary">
                      <td colspan="3" class="text-end"><strong>TOTAL</strong></td>
                      <td></td>
                      <td id="totalResult" style="color: var(--s-700);"><strong>0</strong></td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          
        </div>

        <!-- ─── SIDE COLUMN (Right) ────────────────────────────────────────── -->
        <div class="oc-form-layout-side">
          
          <!-- Section 4: Resultado Final -->
          <div class="oc-card mb-4">
            <div class="oc-card-header">
              <span class="oc-card-header__title"><i class="fa-solid fa-award"></i> Resultado Final</span>
            </div>
            <div class="oc-card-body p-0">
              <table class="orders-table m-0" style="border-radius: 0; min-width: auto;">
                <thead>
                  <tr>
                    <th>PUNTAJE</th>
                    <th>RESULTADO</th>
                  </tr>
                </thead>
                <tbody>
                  <tr class="eval-excellent"><td>450 – 500</td><td>Excelente</td></tr>
                  <tr class="eval-good"><td>400 – 449</td><td>Bueno</td></tr>
                  <tr class="eval-conditional"><td>350 – 399</td><td>Regular (Seguimiento)</td></tr>
                  <tr class="eval-not-approved"><td>&lt; 350</td><td>No Aprobado</td></tr>
                </tbody>
              </table>
              <div class="p-3">
                <div class="result-card">
                  <h4 id="finalScore" class="m-0 fs-5 fw-bold" style="color: var(--s-700);">Puntuación: 0</h4>
                  <div id="finalResult" class="mt-3 p-2 rounded">
                    <h5 id="resultText" class="m-0 fs-6 fw-bold">-</h5>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Section 5: Observaciones y Responsable -->
          <div class="oc-card mb-4">
            <div class="oc-card-header">
              <span class="oc-card-header__title"><i class="fa-solid fa-message"></i> Observaciones</span>
            </div>
            <div class="oc-card-body">
              <div class="mb-3">
                <textarea class="form-control" name="observations" rows="3" placeholder="Observaciones adicionales..."></textarea>
              </div>
              <div class="mb-3">
                <label class="form-label" style="font-size: 0.85rem;"><i class="fa-solid fa-user-check"></i> Responsable:</label>
                <input type="text" class="form-control form-control-sm" name="responsibles"
                       value="<?php echo htmlspecialchars($_SESSION['nombres'] . ' ' . $_SESSION['apellidos']); ?>"
                       readonly />
              </div>
            </div>
          </div>

          <!-- Submit -->
          <div class="oc-form-submit-actions mb-4">
            <button type="submit" class="btn-geco-primary w-100" style="padding: 0.75rem; border-radius: 10px;">
              <i class="fa-solid fa-floppy-disk"></i> Guardar Evaluación
            </button>
          </div>

        </div>

      </div>
    </form>
  </div>

</div><!-- /.orders-page-container -->

<script>
  document.addEventListener('DOMContentLoaded', function() {
    // Manejar selección de calificaciones
    const ratingSelects = document.querySelectorAll('.rating-select');
    ratingSelects.forEach(select => {
      select.addEventListener('change', calculateResults);
    });

    // Calcular resultados iniciales
    calculateResults();
  });

  function calculateResults() {
    let totalScore = 0;
    
    // Calcular resultados para cada criterio
    const criteria = [
      { id: 'qualityRating', resultId: 'qualityResult', weight: 30 },
      { id: 'deliveryRating', resultId: 'deliveryResult', weight: 25 },
      { id: 'priceRating', resultId: 'priceResult', weight: 20 },
      { id: 'legalRating', resultId: 'legalResult', weight: 15 },
      { id: 'serviceRating', resultId: 'serviceResult', weight: 10 }
    ];
    
    criteria.forEach(criterion => {
      const rating = parseInt(document.getElementById(criterion.id).value) || 0;
      const result = rating * criterion.weight;
      document.getElementById(criterion.resultId).textContent = result;
      totalScore += result;
    });
    
    // Actualizar total
    document.getElementById('totalResult').textContent = totalScore.toFixed(1);
    document.getElementById('finalScore').textContent = `Puntuación: ${totalScore.toFixed(1)}`;
    
    // Determinar resultado final
    const resultElement = document.getElementById('finalResult');
    const resultText = document.getElementById('resultText');
    
    if (totalScore >= 450) {
      resultElement.className = 'mt-3 p-2 rounded eval-excellent';
      resultText.textContent = 'EXCELENTE (PROVEEDOR CONFIABLE)';
    } else if (totalScore >= 400) {
      resultElement.className = 'mt-3 p-2 rounded eval-good';
      resultText.textContent = 'BUENO';
    } else if (totalScore >= 350) {
      resultElement.className = 'mt-3 p-2 rounded eval-conditional';
      resultText.textContent = 'REGULAR (REQUIERE SEGUIMIENTO)';
    } else {
      resultElement.className = 'mt-3 p-2 rounded eval-not-approved';
      resultText.textContent = 'NO APROBADO';
    }
  }

  function verHistorial() {
    window.location.href = `historial_evaluaciones.php?proveedor_id=<?= $proveedor_id ?>`;
  }

  function confirmarGuardado(form, event) {
    if (event) event.preventDefault();
    UI.confirm({
      title: '¿Guardar Evaluación?',
      message: 'Una vez guardada, la evaluación quedará registrada en el historial del proveedor.',
      confirmText: 'Sí, guardar',
      cancelText: 'Cancelar'
    }).then(confirmado => {
      if (confirmado) {
        UI.loading('Guardando...');
        form.submit();
      }
    });
    return false;
  }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

