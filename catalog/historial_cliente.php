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
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/orders-common.css?v=1.5">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
  /* CSAT metric block */
  .csat-metric { display:flex; align-items:flex-end; gap:0.5rem; margin-bottom:0.5rem; }
  .csat-score  { font-size:2.8rem; font-weight:800; color:var(--s-800,#0f172a); line-height:1; }
  .csat-label  { font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; color:var(--gray-400,#9ca3af); margin-bottom:0.25rem; }
  .csat-subtitle { font-size:0.9rem; color:var(--gray-500,#6b7280); margin-bottom:0.75rem; }
  .csat-hint   { font-size:0.8rem; color:var(--gray-400,#9ca3af); padding-top:0.75rem; border-top:1px solid var(--gray-100,#f3f4f6); }

  /* Back button */
  .btn-geco-outline {
    display:inline-flex; align-items:center; gap:0.4rem;
    padding:0.5rem 1rem;
    border:1.5px solid var(--gray-200,#e5e7eb);
    border-radius:10px; background:#fff;
    color:var(--s-700,#113557); font-size:0.82rem; font-weight:600;
    cursor:pointer; text-decoration:none; transition:all 0.2s;
  }
  .btn-geco-outline:hover { background:var(--gray-50,#f9fafb); color:var(--s-700,#113557); text-decoration:none; }

  /* Result badges */
  .badge-excelente   { background-color:#28a745; color:#fff; }
  .badge-bueno       { background-color:#17a2b8; color:#fff; }
  .badge-regular     { background-color:#ffc107; color:#000; }
  .badge-no_aprobado { background-color:#dc3545; color:#fff; }
</style>

<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="orders-page-container">

  <!-- Page Header -->
  <div class="orders-page-header mb-4">
    <div class="orders-page-header-info">
      <nav class="orders-breadcrumb">
        <a href="<?= BASE_URL ?>/index.php">Inicio</a>
        <span class="separator">›</span>
        <a href="<?= BASE_URL ?>/catalog/list_catalog.php?entidad=clientes">Clientes</a>
        <span class="separator">›</span>
        <span>Historial de Evaluaciones</span>
      </nav>
      <h1 class="orders-page-title">Historial de Evaluaciones de Clientes</h1>
    </div>
    <a href="list_catalog.php?entidad=clientes" class="btn-geco-outline">← Volver</a>
  </div>

  <!-- CSAT Card -->
  <div class="orders-card orders-card--padded mb-4">
    <div class="csat-label">CSAT del historial</div>
    <div class="csat-metric">
      <span class="csat-score"><?= $csat_score ?>%</span>
    </div>
    <p class="csat-subtitle">
      <?= $csat_data['satisfechos'] ?> satisfechos de <?= $csat_data['total_contestadas'] ?> respuestas contestadas
    </p>
    <div class="csat-hint">
      <strong style="display:block; margin-bottom:4px; color:var(--s-800,#0f172a); font-weight:700;">¿Cómo se calcula?</strong>
      Se considera satisfecho a todo cliente con promedio de calificación mayor o igual a 4.
      <code style="display:inline-block; margin-top:6px; padding:4px 7px; background:var(--gray-100,#f3f4f6); color:var(--s-800,#0f172a); border-radius:6px; white-space:normal;">CSAT = satisfechos / respuestas × 100</code>
    </div>
  </div>

  <!-- Filters + Table Card -->
  <div class="orders-card">

    <!-- Filter Bar -->
    <div class="orders-filter-bar">
      <!-- Search -->
      <form id="search-form" method="GET" class="search-input-wrap" style="flex:1; min-width:220px;">
        <input type="hidden" name="cliente_id" value="<?= $cliente_id ?>">
        <input type="hidden" name="proyecto_id" value="<?= $proyecto_id ?>">
        <i class="bi bi-search search-icon" style="position:absolute; left:0.75rem; top:50%; transform:translateY(-50%); color:var(--gray-400,#9ca3af); pointer-events:none;"></i>
        <input
          class="form-control js-auto-search"
          type="search"
          name="q"
          placeholder="Buscar cliente, proyecto o contrato..."
          value="<?= htmlspecialchars($busqueda) ?>"
          style="padding-left:2.2rem;"
        >
      </form>

      <!-- Dropdowns -->
      <form id="filter-form" method="GET" class="d-flex flex-wrap align-items-center gap-2">
        <input type="hidden" name="page" value="1">
        <input type="hidden" name="q" value="<?= htmlspecialchars($busqueda) ?>">

        <select name="cliente_id" class="form-select js-auto-filter" style="min-width:170px; font-size:0.85rem;">
          <option value="0">Todos los clientes</option>
          <?php while ($cliente_option = $clientes_result->fetch_assoc()): ?>
            <option value="<?= $cliente_option['id'] ?>" <?= $cliente_id === intval($cliente_option['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($cliente_option['nombre']) ?>
            </option>
          <?php endwhile; ?>
        </select>

        <select name="proyecto_id" class="form-select js-auto-filter" style="min-width:170px; font-size:0.85rem;">
          <option value="0">Todos los proyectos</option>
          <?php while ($proyecto_option = $proyectos_result->fetch_assoc()): ?>
            <option value="<?= $proyecto_option['id'] ?>" <?= $proyecto_id === intval($proyecto_option['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($proyecto_option['nombre_proyecto']) ?>
              <?php if (!empty($proyecto_option['numero_contrato'])): ?>
                - <?= htmlspecialchars($proyecto_option['numero_contrato']) ?>
              <?php endif; ?>
            </option>
          <?php endwhile; ?>
        </select>
      </form>
    </div>

    <!-- Record count -->
    <div class="d-flex align-items-center px-3 pb-2" style="font-size:0.82rem; color:var(--gray-500,#6b7280);">
      <span><?= $totalRegistros ?> registro<?= $totalRegistros !== 1 ? 's' : '' ?></span>
    </div>

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
                  <div class="actions-group">
                    <!-- Botón enviar: solo si está pendiente y hay email -->
                    <?php if ($row['estado'] === 'pendiente'): ?>
                      <?php if (!empty($row['email_cliente'])): ?>
                        <button class="btn btn-sm btn-primary"
                          onclick="enviarFormulario(<?= $row['historial_id'] ?>, <?= htmlspecialchars(json_encode($row['email_cliente']), ENT_QUOTES, 'UTF-8') ?>)"
                          title="Enviar encuesta al cliente"
                          style="font-size:0.75rem;">
                          <i class="fas fa-paper-plane"></i> Enviar
                        </button>
                      <?php else: ?>
                        <span class="badge bg-warning text-dark" title="El cliente no tiene email registrado">
                          Sin email
                        </span>
                      <?php endif; ?>
                    <?php elseif ($row['estado'] === 'enviado'): ?>
                      <button class="btn btn-sm btn-outline-secondary" disabled
                        title="Esperando respuesta del cliente"
                        style="font-size:0.75rem;">
                        <i class="fas fa-clock"></i> Pendiente
                      </button>
                    <?php endif; ?>

                    <!-- Botón ver respuesta: solo si está contestado -->
                    <?php if ($row['estado'] === 'contestado'): ?>
                      <button class="btn btn-sm btn-info text-white"
                        onclick="verRespuesta(<?= htmlspecialchars(json_encode($row)) ?>)"
                        title="Ver respuestas del cliente"
                        style="font-size:0.75rem;">
                        <i class="fas fa-eye"></i> Ver respuesta
                      </button>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="7">
                <div class="orders-empty-state">
                  <i class="fas fa-inbox fa-2x mb-2"></i>
                  <p>No hay proyectos terminados registrados</p>
                </div>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPaginas > 1): ?>
      <?php
        $queryBase = [
          'cliente_id' => $cliente_id,
          'proyecto_id' => $proyecto_id,
          'q' => $busqueda,
        ];
      ?>
      <div class="orders-pagination-bar">
        <span class="pagination-info">Página <?= $pagina ?> de <?= $totalPaginas ?></span>
        <div class="pagination-controls">
          <a class="page-btn <?= $pagina <= 1 ? 'disabled' : '' ?>"
            href="?<?= http_build_query(array_merge($queryBase, ['page' => $pagina - 1])) ?>">
            &laquo;
          </a>
          <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
            <a class="page-btn <?= $i === $pagina ? 'active' : '' ?>"
              href="?<?= http_build_query(array_merge($queryBase, ['page' => $i])) ?>">
              <?= $i ?>
            </a>
          <?php endfor; ?>
          <a class="page-btn <?= $pagina >= $totalPaginas ? 'disabled' : '' ?>"
            href="?<?= http_build_query(array_merge($queryBase, ['page' => $pagina + 1])) ?>">
            &raquo;
          </a>
        </div>
      </div>
    <?php endif; ?>

  </div><!-- /.orders-card -->

</div><!-- /.orders-page-container -->

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
<?php include __DIR__ . "/../includes/footer.php"; ?>
