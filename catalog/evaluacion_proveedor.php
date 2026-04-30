<?php
// Incluir el gestor de sesiones UNA sola vez
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

// Verificar sesiÃ³n y prevenir caching
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

// Obtener informaciÃ³n del proveedor
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

// Obtener informaciÃ³n del usuario actual
$usuario_id = $_SESSION['user_id'] ?? 0;

// Procesar el formulario si se enviÃ³
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
        $mensaje_exito = "EvaluaciÃ³n guardada correctamente";
    } else {
        $mensaje_error = "Error al guardar la evaluaciÃ³n: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EvaluaciÃ³n de Proveedores - PROATAM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/evaluacion.css" />
    <style>
        .rating-options > div {
            padding: 10px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            margin: 2px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .rating-options > div:hover {
            background-color: #f8f9fa;
        }
        .rating-options > div.selected {
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        .excellent { background-color: #d4edda; color: #155724; }
        .good { background-color: #d1ecf1; color: #0c5460; }
        .conditional { background-color: #fff3cd; color: #856404; }
        .not-approved { background-color: #f8d7da; color: #721c24; }
    </style>
</head>
<body>

<?php
include __DIR__ . "/../includes/navbar.php"; ?>

<!-- HERO SECTION -->
<div class="hero-section">
  <div class="container hero-content">
    <div class="breadcrumb-custom">
      <a href="<?= BASE_URL ?>/index.php"><i class="bi bi-house-door"></i> Inicio</a>
      <span>/</span>
      <a href="<?= BASE_URL ?>/catalog/list_catalog.php?entidad=proveedores">Proveedores</a>
      <span>/</span>
      <span>EvaluaciÃ³n de Proveedor</span>
    </div>
    
    <div class="row align-items-end">
      <div class="col-lg-8">
        <h1 class="hero-title">EvaluaciÃ³n de Proveedor</h1>
      </div>
      <div class="col-lg-4 text-end">
        <div class="btn-group">
          <button class="btn-inf" onclick="verHistorial()"  
            data-bs-toggle="tooltip" data-bs-placement="top" title="Historial de evaluaciones">
            <i class="bi bi-clock-history"></i>
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- MAIN CONTENT -->
<div class="content-wrapper">
  <div class="form-container">
    <div class="form-body">
      
      <?php
if (isset($mensaje_exito)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
          <?= $mensaje_exito ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php
endif; ?>
      
      <?php
if (isset($mensaje_error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
          <?= $mensaje_error ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php
endif; ?>
      
      <form method="POST" id="evaluationForm">
        <div class="section-title">
          <i class="bi bi-info-circle"></i>
          InformaciÃ³n General
        </div>
        
        <!-- Nombre o razÃ³n social del proveedor -->
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label class="form-label">Nombre o RazÃ³n Social del Proveedor:</label>
                    <input type="text" class="form-control" 
                     value="<?= htmlspecialchars($proveedor['razon_social']) ?>" 
                     readonly />
                </div>
            </div>

            <div class="col-md-6">
                <div class="form-group">
                    <label class="form-label">CIF. o R.F.C.:</label>
                    <input type="text" class="form-control" name="supplierRFC" 
                           value="<?= htmlspecialchars($proveedor['rfc'] ?? '') ?>" required>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label class="form-label">Lugar y Fecha de ElaboraciÃ³n:</label>
                    <input type="text" class="form-control" name="evaluationDate" 
                           value="PROATAM S.A. DE C.V. - <?= date('d/m/Y') ?>" required>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label class="form-label">Contrato No.:</label>
                    <input type="text" class="form-control" name="contractNumber" required>
                </div>
            </div>
        </div>

        <div class="section-title">
          <i class="bi bi-speedometer2"></i>
          Escala de CalificaciÃ³n:
        </div>
        <div class="row text-center rating-options">
            <div class="col" data-value="1">
                <strong>1</strong><br>Muy Deficiente
            </div>
            <div class="col" data-value="2">
                <strong>2</strong><br>Deficiente
            </div>
            <div class="col" data-value="3">
                <strong>3</strong><br>Regular
            </div>
            <div class="col" data-value="4">
                <strong>4</strong><br>Bueno
            </div>
            <div class="col" data-value="5">
                <strong>5</strong><br>Excelente
            </div>
        </div>

        <div class="section-title">
          <i class="bi bi-star"></i>
          EvaluaciÃ³n del Proveedor
        </div>
        <div class="table-container">
          <table class="table table-bordered">
            <thead>
              <tr>
                <th>Criterio</th>
                <th>DescripciÃ³n</th>
                <th>PonderaciÃ³n</th>
                <th>CalificaciÃ³n</th>
                <th>Resultado</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>Calidad</td>
                <td>Cumplimiento con especificaciones tÃ©cnicas, ausencia de defectos.</td>
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
                <td>Competitividad de precios, claridad en pagos y facturaciÃ³n.</td>
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
                <td>DocumentaciÃ³n vigente (fiscal, laboral, seguridad, ambiental).</td>
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
                <td>AtenciÃ³n y Servicio Postventa</td>
                <td>Respuesta a incidencias, comunicaciÃ³n y soporte.</td>
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
                <td id="totalResult"><strong>0</strong></td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="section-title">
          <i class="bi bi-speedometer2"></i>
          Resultado Final:
        </div>
        <div class="result-section">
          <div class="row">
            <div class="col-md-6">
              <table class="table table-bordered">
                <thead class="table-light">
                  <tr>
                    <th>PUNTAJE</th>
                    <th>RESULTADO</th>
                  </tr>
                </thead>
                <tbody>
                  <tr class="excellent">
                    <td>450 â€“ 500</td>
                    <td>Excelente</td>
                  </tr>
                  <tr class="good">
                    <td>400 â€“ 449</td>
                    <td>Bueno</td>
                  </tr>
                  <tr class="conditional">
                    <td>350 â€“ 399</td>
                    <td>Regular (Requiere seguimiento)</td>
                  </tr>
                  <tr class="not-approved">
                    <td>&lt; 350</td>
                    <td>No Aprobado</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div class="col-md-6">
              <div class="text-center p-4">
                <h4 id="finalScore">PuntuaciÃ³n: 0</h4>
                <div id="finalResult" class="mt-3 p-3 rounded">
                  <h5 id="resultText">-</h5>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="section-title">
          <i class="bi bi-chat-text"></i>
          Observaciones:
        </div>
        <textarea class="form-control" name="observations" rows="3"></textarea>

        <div class="section-title">
          <i class="bi bi-person-check"></i>
          Responsable:
        </div>
        <input type="text" class="form-control" name="responsibles" 
               value="<?php
echo htmlspecialchars($_SESSION['nombres'] . ' ' . $_SESSION['apellidos']); ?>"
                readonly />

        <!-- Guardar -->
        <div class="form-actions mt-3">
          <div class="send-otxt">
            Esta evaluaciÃ³n se guardarÃ¡ automÃ¡ticamente en el registro de evaluaciones de este proveedor.
          </div>
          <button type="submit" class="button-56">
            <i class="bi bi-floppy"></i> Guardar EvaluaciÃ³n
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    // Manejar selecciÃ³n de calificaciones
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
    document.getElementById('finalScore').textContent = `PuntuaciÃ³n: ${totalScore.toFixed(1)}`;
    
    // Determinar resultado final
    const resultElement = document.getElementById('finalResult');
    const resultText = document.getElementById('resultText');
    
    if (totalScore >= 450) {
      resultElement.className = 'mt-3 p-3 rounded excellent';
      resultText.textContent = 'EXCELENTE (PROVEEDOR CONFIABLE)';
    } else if (totalScore >= 400) {
      resultElement.className = 'mt-3 p-3 rounded good';
      resultText.textContent = 'BUENO';
    } else if (totalScore >= 350) {
      resultElement.className = 'mt-3 p-3 rounded conditional';
      resultText.textContent = 'REGULAR (REQUIERE SEGUIMIENTO)';
    } else {
      resultElement.className = 'mt-3 p-3 rounded not-approved';
      resultText.textContent = 'NO APROBADO';
    }
  }

  function verHistorial() {
    window.location.href = `historial_evaluaciones.php?proveedor_id=<?= $proveedor_id ?>`;
  }
</script>

<?php
include __DIR__ . "/../includes/footer.php"; ?>

</body>
</html>



