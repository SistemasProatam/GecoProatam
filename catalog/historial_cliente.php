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
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/core/modules.css?v=2.0">

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
        <span>Historial de Evaluaciones de Clientes</span>
      </nav>
      <h1 class="orders-page-title">Historial de Evaluaciones de Clientes</h1>
    </div>
    <a href="list_catalog.php?entidad=clientes" class="btn-geco-outline"><i class="fa-solid fa-arrow-left"></i> Volver</a>
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

  <!-- Filters Card -->
  <div class="orders-card mb-3">
    <div class="orders-filter-bar">
      <form id="filter-form" method="GET" class="d-flex align-items-center gap-3 w-100 flex-wrap">
        <input type="hidden" name="page" value="1">

        <!-- Search -->
        <div class="search-input-wrap" style="flex: 1; max-width: 400px; min-width: 250px;">
          <i class="fa-solid fa-magnifying-glass"></i>
          <input class="js-auto-search" type="search" name="q" placeholder="Buscar cliente, proyecto o contrato..." value="<?= htmlspecialchars($busqueda) ?>">
        </div>

        <!-- Selects -->
        <div class="d-flex align-items-center gap-2">
          <select name="cliente_id" class="form-select js-auto-filter" style="width: 220px; font-size: 0.85rem;">
            <option value="0">Todos los clientes</option>
            <?php while ($cliente_option = $clientes_result->fetch_assoc()): ?>
              <option value="<?= $cliente_option['id'] ?>" <?= $cliente_id === intval($cliente_option['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($cliente_option['nombre']) ?>
              </option>
            <?php endwhile; ?>
          </select>

          <select name="proyecto_id" class="form-select js-auto-filter" style="width: 220px; font-size: 0.85rem;">
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
        </div>
      </form>
    </div>
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
                  $estadoClass = match ($row['estado']) {
                    'pendiente'    => 'status-badge--pendiente',
                    'enviado'      => 'status-badge--revisado',
                    'contestado'   => 'status-badge--aprobado',
                    'sin_respuesta' => 'status-badge--cancelado',
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
                      'excelente'   => 'eval-excellent',
                      'bueno'       => 'eval-good',
                      'regular'     => 'eval-conditional',
                      'no_aprobado' => 'eval-not-approved',
                    ];
                    ?>
                    <span class="<?= $badges[$row['resultado_final']] ?? 'bg-secondary' ?> px-2 py-1 rounded" style="font-size:0.75rem; font-weight:600; border:1px solid currentColor;">
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
                        <button class="btn-geco-primary"
                          onclick="enviarFormulario(<?= $row['historial_id'] ?>, <?= htmlspecialchars(json_encode($row['email_cliente']), ENT_QUOTES, 'UTF-8') ?>)"
                          title="Enviar encuesta al cliente"
                          style="font-size:0.75rem; padding: 0.35rem 0.65rem;">
                          <i class="fa-solid fa-paper-plane"></i> Enviar
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
                        <i class="fa-regular fa-clock"></i> Pendiente
                      </button>
                    <?php endif; ?>

                    <!-- Botón ver respuesta: solo si está contestado -->
                    <?php if ($row['estado'] === 'contestado'): ?>
                      <button class="btn-geco-secondary"
                        onclick="verRespuesta(<?= htmlspecialchars(json_encode($row)) ?>)"
                        title="Ver respuestas del cliente"
                        style="font-size:0.75rem; padding: 0.35rem 0.65rem;">
                        <i class="fa-regular fa-eye"></i> Ver respuesta
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
                  <i class="fa-solid fa-inbox fa-2x mb-2"></i>
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
        <div class="orders-pagination-left">
          <span class="orders-pagination-info">
            <?php
            $ini = $totalRegistros > 0 ? $offset + 1 : 0;
            $fin = min($offset + $por_pagina, $totalRegistros);
            ?>
            Mostrando <strong><?= $ini ?>–<?= $fin ?></strong> de <strong><?= $totalRegistros ?></strong>
          </span>
        </div>
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

  async function enviarFormulario(historialId, emailCliente) {
    const confirmado = await UI.confirm({
      title: '¿Enviar encuesta?',
      message: `Se enviará el formulario de satisfacción a <strong>${emailCliente}</strong>`,
      confirmText: 'Sí, enviar',
      cancelText: 'Cancelar',
      icon: 'question'
    });

    if (!confirmado) return;

    UI.loading('Enviando...');

    fetch('enviar_formulario_satisfaccion.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `historial_id=${historialId}`
      })
      .then(r => r.json())
      .then(data => {
        UI.loading.hide();
        if (data.success) {
          UI.toast.success(data.message);
          setTimeout(() => location.reload(), 1000);
        } else {
          UI.toast.error(data.message);
        }
      })
      .catch(() => {
        UI.loading.hide();
        UI.toast.error('Error de conexión');
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

    UI.modal({
      title: 'Respuesta del cliente',
      size: 'md',
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
                </div>`
    });
  }
</script>
<?php include __DIR__ . "/../includes/footer.php"; ?>