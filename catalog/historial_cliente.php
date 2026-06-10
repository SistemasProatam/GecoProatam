<?php
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";
checkSession();
preventCaching();

require_once __DIR__ . "/../conexion.php";
require_once __DIR__ . "/registrar_historial.php";

// Registrar automáticamente proyectos nuevos terminados
registrarProyectosTerminados($conn);

$cliente_id = intval($_GET['cliente_id'] ?? ($_GET['id'] ?? 0));
$proyecto_id = intval($_GET['proyecto_id'] ?? 0);

// ==== Filtros ====
$busqueda = trim($_GET['q'] ?? '');
$pagina = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$por_pagina = 10;
$offset = ($pagina - 1) * $por_pagina;

$clientes_result = $conn->query("SELECT id, nombre FROM clientes WHERE activo = 1 ORDER BY nombre ASC");
$proyectos_result = $conn->query("
    SELECT id, nombre_proyecto, numero_contrato
    FROM proyectos
    WHERE fecha_fin <= CURDATE()
      AND cliente_id IS NOT NULL
    ORDER BY nombre_proyecto ASC
");

// ====== Query base dinámica ======
$sqlBase = "
    FROM historial_proyectos_cliente h
    INNER JOIN proyectos p ON h.proyecto_id = p.id
    INNER JOIN clientes c ON h.cliente_id = c.id
    WHERE 1=1
";

$params = [];
$types = "";

if ($cliente_id > 0) {
  $sqlBase .= " AND h.cliente_id = ?";
  $params[] = $cliente_id;
  $types .= "i";
}

if ($proyecto_id > 0) {
  $sqlBase .= " AND h.proyecto_id = ?";
  $params[] = $proyecto_id;
  $types .= "i";
}

if ($busqueda !== '') {
  $sqlBase .= " AND (c.nombre LIKE ? OR p.nombre_proyecto LIKE ? OR p.numero_contrato LIKE ?)";
  $like = "%{$busqueda}%";
  $params[] = $like;
  $params[] = $like;
  $params[] = $like;
  $types .= "sss";
}

// ====== Total registros ======
$stmtTotal = $conn->prepare("SELECT COUNT(*) AS total $sqlBase");
if ($types) {
  $stmtTotal->bind_param($types, ...$params);
}
$stmtTotal->execute();
$totalRegistros = intval($stmtTotal->get_result()->fetch_assoc()['total'] ?? 0);
$totalPaginas = max(1, (int)ceil($totalRegistros / $por_pagina));

// ====== Datos paginados ======
$sql_historial = "
    SELECT
        h.id AS historial_id,
        h.cliente_id,
        h.proyecto_id,
        h.estado,
        h.fecha_envio_formulario,
        h.fecha_respuesta,
        h.p1, h.p2, h.p3, h.p4, h.p5,
        h.observaciones,
        h.promedio_puntuacion,
        h.resultado_final,
        p.nombre_proyecto,
        p.numero_contrato,
        DATE_FORMAT(p.fecha_fin, '%d/%m/%Y') AS fecha_fin,
        c.nombre AS nombre_cliente,
        c.email AS email_cliente
    $sqlBase
    ORDER BY p.fecha_fin DESC
    LIMIT ? OFFSET ?
";
$stmt_h = $conn->prepare($sql_historial);
$paramsPag = $params;
$typesPag = $types . "ii";
$paramsPag[] = $por_pagina;
$paramsPag[] = $offset;
$stmt_h->bind_param($typesPag, ...$paramsPag);
$stmt_h->execute();
$historial = $stmt_h->get_result();

// CSAT global: (respuestas con promedio >= 4) / total contestadas * 100
$sql_csat = "
    SELECT
        COUNT(*) AS total_contestadas,
        SUM(promedio_puntuacion >= 4) AS satisfechos
    $sqlBase
      AND h.estado = 'contestado'
";
$stmt_csat = $conn->prepare($sql_csat);
if ($types) {
  $stmt_csat->bind_param($types, ...$params);
}
$stmt_csat->execute();
$csat_data = $stmt_csat->get_result()->fetch_assoc();
$csat_data['total_contestadas'] = intval($csat_data['total_contestadas'] ?? 0);
$csat_data['satisfechos'] = intval($csat_data['satisfechos'] ?? 0);

$csat_score = 0;
if ($csat_data['total_contestadas'] > 0) {
  $csat_score = round(($csat_data['satisfechos'] / $csat_data['total_contestadas']) * 100, 1);
}

// Etiquetas de las preguntas del form (actualiza aquí si cambian)
$preguntas = [
  'p1' => 'Canales de comunicación',
  'p2' => 'Cumplimiento del cronograma',
  'p3' => 'Gestión del gerente de proyecto',
  'p4' => 'Calidad del resultado final',
  'p5' => 'Manejo de imprevistos',
];
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <title>Historial de Evaluaciones</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/list.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    .badge-excelente {
      background-color: #28a745;
    }

    .badge-bueno {
      background-color: #17a2b8;
    }

    .badge-regular {
      background-color: #ffc107;
      color: #000;
    }

    .badge-no_aprobado {
      background-color: #dc3545;
    }

    .estado-pendiente {
      background-color: #6c757d;
      color: #fff;
    }

    .estado-enviado {
      background-color: #0d6efd;
      color: #fff;
    }

    .estado-contestado {
      background-color: #198754;
      color: #fff;
    }

    .estado-sin_respuesta {
      background-color: #dc3545;
      color: #fff;
    }

    .csat-card {
      width: 100%;
      background: #fff;
      color: #111827;
      border: 1px solid #e5e7eb;
      border-radius: 8px;
      padding: 22px 24px;
      box-shadow: 0 6px 18px rgba(17, 24, 39, 0.08);
    }

    .csat-label {
      margin-bottom: 8px;
      font-size: 0.78rem;
      font-weight: 700;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      color: #111827;
    }

    .csat-score {
      color: #111827;
      font-size: 2.9rem;
      font-weight: 700;
      line-height: 1;
    }

    .csat-summary {
      margin-top: 8px;
      color: #4b5563;
      font-size: 0.92rem;
    }

    .csat-help {
      margin-top: 14px;
      padding-top: 14px;
      border-top: 1px solid #e5e7eb;
      color: #374151;
      font-size: 0.84rem;
      line-height: 1.45;
    }

    .csat-help strong {
      display: block;
      margin-bottom: 4px;
      color: #111827;
    }

    .csat-help code {
      display: inline-block;
      margin-top: 6px;
      padding: 4px 7px;
      background: #f3f4f6;
      color: #111827;
      border-radius: 6px;
      white-space: normal;
    }
  </style>
</head>

<body>
  <?php include __DIR__ . "/../includes/navbar.php"; ?>

  <div class="hero-section">
    <div class="container hero-content">
      <div class="breadcrumb-custom">
        <a href="<?= BASE_URL ?>/index.php"><i class="bi bi-house-door"></i> Inicio</a>
        <span>/</span>
        <span>Historial de Evaluaciones</span>
      </div>
      <h1 class="hero-title">
        Historial de Evaluaciones
      </h1>
    </div>
  </div>

  <div class="content-wrapper">
    <div class="form-container">
      <div class="form-body">

        <!-- Tarjeta CSAT global -->
        <div class="row mb-4">
          <div class="col-12">
            <div class="csat-card">
              <div class="csat-card-content">
                <div class="csat-label">
                  CSAT del historial
                </div>
                <div class="csat-score"><?= $csat_score ?>%</div>
                <div class="csat-summary">
                  <?= $csat_data['satisfechos'] ?> satisfechos de
                  <?= $csat_data['total_contestadas'] ?> respuestas contestadas
                </div>
                <div class="csat-help">
                  <strong>¿Cómo se calcula?</strong>
                  Se considera satisfecho a todo cliente con promedio de calificación mayor o igual a 4.
                  <code>CSAT = satisfechos / respuestas × 100</code>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Buscador -->
        <form id="search-form" class="form-search d-flex justify-content-center w-100 mb-4" method="GET">
          <input type="hidden" name="q" value="<?= htmlspecialchars($busqueda) ?>">
          <input class="form-control w-100" type="search" name="q"
            placeholder="Buscar cliente, proyecto o contrato..." value="<?= htmlspecialchars($busqueda) ?>">
          <button class="btn btn-outline-success" type="submit">
            <i class="bi bi-search"></i>
          </button>
        </form>

        <!-- Filtros Específicos por Proyecto o Cliente -->
        <div class="mb-2">
          <h5 class="text-muted" style="font-size: 1rem; font-weight: 600;">
            <i class="bi bi-funnel"></i> Filtros
          </h5>
        </div>

  <!-- Table Card -->
  <div class="orders-card">

    <!-- Table -->
    <div class="orders-table-wrap">
      <table class="orders-table">
        <thead>
          <tr>
            <th>Cliente</th>
            <th>Proyecto</th>
            <th>No. Contrato</th>
            <th>Fecha término</th>
            <th>Estado</th>
            <th>Resultado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($historial->num_rows > 0): ?>
            <?php while ($row = $historial->fetch_assoc()): ?>
              <tr>
                <td><?= htmlspecialchars($row['nombre_cliente']) ?></td>
                <td><?= htmlspecialchars($row['nombre_proyecto']) ?></td>
                <td><?= htmlspecialchars($row['numero_contrato']) ?></td>
                <td><?= $row['fecha_fin'] ?></td>
                <td>
                  <?php
                    $estadoClass = match($row['estado']) {
                      'pendiente'    => 'status-badge--pendiente',
                      'enviado'      => 'status-badge--revisado',
                      'contestado'   => 'status-badge--aprobado',
                      'sin_respuesta'=> 'status-badge--cancelado',
                      default        => 'status-badge--pendiente',
                    };
                  ?>
                  <span class="status-badge <?= $estadoClass ?>">
                    <?= ucfirst(str_replace('_', ' ', $row['estado'])) ?>
                  </span>
                </td>
                <td>
                  <?php if ($row['estado'] === 'contestado'): ?>
                    <?php
                      $badgeClass = match($row['resultado_final']) {
                        'excelente'   => 'status-badge--aprobado',
                        'bueno'       => 'status-badge--revisado',
                        'regular'     => 'status-badge--pendiente',
                        'no_aprobado' => 'status-badge--rechazado',
                        default       => 'status-badge--pendiente',
                      };
                    ?>
                    <span class="status-badge <?= $badgeClass ?>">
                      <?= number_format($row['promedio_puntuacion'], 1) ?> • <?= ucfirst(str_replace('_', ' ', $row['resultado_final'])) ?>
                    </span>
                  <?php else: ?>
                    <span class="text-muted">—</span>
                  <?php endif; ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>

        </form>

        <!-- Tabla historial -->
        <div class="table-container">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <span class="badge-num"><?= $totalRegistros ?> registros</span>
          </div>

          <table class="table table-bordered table-striped">
            <thead>
              <tr>
                <th>Cliente</th>
                <th>Proyecto</th>
                <th>No. Contrato</th>
                <th>Fecha término</th>
                <th>Estado</th>
                <th>Resultado</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($historial->num_rows > 0): ?>
                <?php while ($row = $historial->fetch_assoc()): ?>
                  <tr>
                    <td><?= htmlspecialchars($row['nombre_cliente']) ?></td>
                    <td><?= htmlspecialchars($row['nombre_proyecto']) ?></td>
                    <td><?= htmlspecialchars($row['numero_contrato']) ?></td>
                    <td><?= $row['fecha_fin'] ?></td>
                    <td>
                      <span class="badge estado-<?= $row['estado'] ?>">
                        <?= ucfirst(str_replace('_', ' ', $row['estado'])) ?>
                      </span>
                    </td>
                    <td>
                      <?php if ($row['estado'] === 'contestado'): ?>
                        <?php
                        $badges = [
                          'excelente'   => 'badge-excelente',
                          'bueno'       => 'badge-bueno',
                          'regular'     => 'badge-regular',
                          'no_aprobado' => 'badge-no_aprobado',
                        ];
                        ?>
                        <span class="badge <?= $badges[$row['resultado_final']] ?? 'bg-secondary' ?>">
                          <?= number_format($row['promedio_puntuacion'], 1) ?> —
                          <?= ucfirst(str_replace('_', ' ', $row['resultado_final'])) ?>
                        </span>
                      <?php else: ?>
                        <span class="text-muted">—</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <div class="d-flex gap-1">
                        <!-- Botón enviar: solo si está pendiente y hay email -->
                        <?php if ($row['estado'] === 'pendiente'): ?>
                          <?php if (!empty($row['email_cliente'])): ?>
                            <button class="btn btn-sm btn-primary"
                              onclick="enviarFormulario(<?= $row['historial_id'] ?>, <?= htmlspecialchars(json_encode($row['email_cliente']), ENT_QUOTES, 'UTF-8') ?>)"
                              title="Enviar encuesta al cliente">
                              <i class="fas fa-paper-plane"></i> Enviar
                            </button>
                          <?php else: ?>
                            <span class="badge bg-warning text-dark"
                              title="El cliente no tiene email registrado">
                              Sin email
                            </span>
                          <?php endif; ?>
                        <?php elseif ($row['estado'] === 'enviado'): ?>
                          <button class="btn btn-sm btn-outline-secondary" disabled
                            title="Esperando respuesta del cliente">
                            <i class="fas fa-clock"></i> Pendiente
                          </button>
                        <?php endif; ?>

                        <!-- Botón ver respuesta: solo si está contestado -->
                        <?php if ($row['estado'] === 'contestado'): ?>
                          <button class="btn btn-sm btn-info text-white"
                            onclick="verRespuesta(<?= htmlspecialchars(json_encode($row)) ?>)"
                            title="Ver respuestas del cliente">
                            <i class="fas fa-eye"></i> Ver respuesta
                          </button>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr>
                  <td colspan="7" class="text-center text-muted py-4">
                    <i class="fas fa-inbox fa-3x mb-2 d-block"></i>
                    No hay proyectos terminados registrados
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>

          <?php if ($totalPaginas > 1): ?>
            <nav aria-label="Paginacion historial">
              <ul class="pagination justify-content-center mt-3">
                <?php
                $queryBase = [
                  'cliente_id' => $cliente_id,
                  'proyecto_id' => $proyecto_id,
                  'q' => $busqueda,
                ];
                ?>

                <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
                  <a class="page-link" href="?<?= http_build_query(array_merge($queryBase, ['page' => $pagina - 1])) ?>">
                    &laquo;
                  </a>
                </li>

                <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                  <li class="page-item <?= $i === $pagina ? 'active' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($queryBase, ['page' => $i])) ?>">
                      <?= $i ?>
                    </a>
                  </li>
                <?php endfor; ?>

                <li class="page-item <?= $pagina >= $totalPaginas ? 'disabled' : '' ?>">
                  <a class="page-link" href="?<?= http_build_query(array_merge($queryBase, ['page' => $pagina + 1])) ?>">
                    &raquo;
                  </a>
                </li>
              </ul>
            </nav>
          <?php endif; ?>
        </div>

      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    const PREGUNTAS = <?= json_encode($preguntas) ?>;
    const filterForm = document.getElementById('filter-form');
    let searchTimer;

    document.querySelectorAll('.js-auto-filter').forEach(select => {
      select.addEventListener('change', () => filterForm.submit());
    });

    document.querySelectorAll('.js-auto-search').forEach(input => {
      input.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => filterForm.submit(), 500);
      });
    });

    function enviarFormulario(historialId, emailCliente) {
      Swal.fire({
        title: '¿Enviar encuesta?',
        html: `Se enviará el formulario de satisfacción a <strong>${emailCliente}</strong>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#0f6e56',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, enviar',
        cancelButtonText: 'Cancelar'
      }).then(result => {
        if (!result.isConfirmed) return;

        Swal.fire({
          title: 'Enviando...',
          allowOutsideClick: false,
          didOpen: () => Swal.showLoading()
        });

        fetch('enviar_formulario_satisfaccion.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `historial_id=${historialId}`
          })
          .then(r => r.json())
          .then(data => {
            if (data.success) {
              Swal.fire({
                  icon: 'success',
                  title: 'Enviado',
                  text: data.message
                })
                .then(() => location.reload());
            } else {
              Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message
              });
            }
          })
          .catch(() => Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Error de conexión'
          }));
      });
    }

    function verRespuesta(row) {
      const etiquetas = {
        p1: PREGUNTAS.p1,
        p2: PREGUNTAS.p2,
        p3: PREGUNTAS.p3,
        p4: PREGUNTAS.p4,
        p5: PREGUNTAS.p5
      };

      const estrellas = n => '★'.repeat(n) + '☆'.repeat(5 - n);

      let filasCalif = Object.entries(etiquetas).map(([key, label]) => `
            <tr>
                <td style="padding:6px 8px; color:#555;">${label}</td>
                <td style="padding:6px 8px; color:#f59e0b; letter-spacing:2px;">
                    ${estrellas(row[key] || 0)}
                    <small style="color:#888; margin-left:4px;">(${row[key] || '—'})</small>
                </td>
            </tr>`).join('');

      const observaciones = row.observaciones ?
        `<div style="margin-top:12px; padding:10px; background:#f8f9fa; border-radius:6px; text-align:left;">
                <strong>Observaciones:</strong><br>${row.observaciones}
               </div>` :
        '';

      Swal.fire({
        title: 'Respuesta del cliente',
        width: 560,
        html: `
                <div style="text-align:left;">
                    <p style="margin:0 0 8px; color:#555;">
                        <strong>Proyecto:</strong> ${row.nombre_proyecto}
                    </p>
                    <p style="margin:0 0 16px; color:#555;">
                        <strong>Fecha respuesta:</strong> ${row.fecha_respuesta ?? '—'}
                    </p>
                    <table style="width:100%; border-collapse:collapse;">
                        <thead>
                            <tr style="background:#f0f0f0;">
                                <th style="padding:6px 8px; text-align:left;">Criterio</th>
                                <th style="padding:6px 8px; text-align:left;">Calificación</th>
                            </tr>
                        </thead>
                        <tbody>${filasCalif}</tbody>
                        <tfoot>
                            <tr style="background:#e8f5e9; font-weight:bold;">
                                <td style="padding:8px;">Promedio</td>
                                <td style="padding:8px; color:#0f6e56;">
                                    ${parseFloat(row.promedio_puntuacion || 0).toFixed(1)} / 5
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                    ${observaciones}
                </div>`,
        showCloseButton: true,
        showConfirmButton: false,
      });
    }
  </script>
</body>

</html>