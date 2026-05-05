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

$busqueda  = trim($_GET['q']    ?? '');
$pagina    = max(1, (int)($_GET['page'] ?? 1));
$porPagina = 10;
$offset    = ($pagina - 1) * $porPagina;

$where  = "WHERE 1=1";
$params = [];
$types  = "";

if ($busqueda) {
  $where  .= " AND (c.folio LIKE ? OR c.atencion LIKE ? OR c.compania LIKE ?)";
  $like    = "%$busqueda%";
  $params  = [$like, $like, $like];
  $types   = "sss";
}

$stmtTotal = $conn->prepare("SELECT COUNT(*) AS total FROM cotizaciones c $where");
if ($types) $stmtTotal->bind_param($types, ...$params);
$stmtTotal->execute();
$totalRegistros = $stmtTotal->get_result()->fetch_assoc()['total'] ?? 0;
$totalPags = (int)ceil($totalRegistros / $porPagina);

$paramsPag = array_merge($params, [$porPagina, $offset]);
$typesPag  = $types . "ii";
$stmt = $conn->prepare("
    SELECT c.id, c.folio, c.fecha_emision, c.atencion, c.compania,
           c.total, c.moneda, c.emisor_nombre, c.emisor_depto, c.tasa_iva,
           e.nombre AS entidad
    FROM cotizaciones c
    LEFT JOIN entidades e ON c.entidades_id = e.id
    $where
    ORDER BY c.fecha_creacion DESC
    LIMIT ? OFFSET ?
");
$stmt->bind_param($typesPag, ...$paramsPag);
$stmt->execute();
$result = $stmt->get_result();

$entidadColores = [
  'PROATAM'     => '#113456',
  'INGETAM'     => '#efa336',
  'LUBYCOMP'    => '#243944',
  'DAVID GOMEZ' => '#fbae17',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Cotizaciones - Historial</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/list.css">
  <link rel="icon" href="<?= BASE_URL ?>/assets/img/chinior.ico" type="image/x-icon">
  <style>
    .cot-card { transition: all 0.2s ease; border-radius: 12px; margin-bottom: 12px; border: 1px solid rgba(0,0,0,0.05); box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
    .cot-card:hover { transform: translateX(5px); box-shadow: 0 5px 15px rgba(0,0,0,0.08); border-color: #113456; }
    .badge-entidad { padding: 4px 10px; border-radius: 6px; color: white; font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
  </style>
</head>
<body>
<?php include __DIR__ . "/../includes/navbar.php"; ?>

<div class="hero-section">
  <div class="container hero-content">
    <div class="breadcrumb-custom">
      <a href="<?= BASE_URL ?>/index.php"><i class="bi bi-house-door"></i> Inicio</a>
      <span>/</span><span>Cotizaciones</span>
    </div>
    <h1 class="hero-title">Historial de Cotizaciones</h1>
  </div>
</div>

<div class="content-wrapper">
  <div class="container">
    <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
      <div class="card-body p-4">
        <div class="row mb-4 align-items-center">
          <div class="col-md-6">
            <form id="search-form" class="d-flex gap-2">
              <input class="form-control" type="search" name="q" placeholder="Buscar cotización..." value="<?= htmlspecialchars($busqueda) ?>">
              <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button>
            </form>
          </div>
          <div class="col-md-6 text-end mt-3 mt-md-0">
            <span class="badge bg-light text-dark border me-2 py-2 px-3 rounded-pill"><?= $totalRegistros ?> cotizaciones</span>
            <button class="btn btn-success" onclick="location.href='cotizacion.php'"><i class="bi bi-plus-circle me-1"></i> Nueva Cotización</button>
          </div>
        </div>

        <div id="table-container-wrapper">
          <?php if ($result && $result->num_rows > 0): ?>
            <div class="list-group list-group-flush border-top pt-3">
              <?php while ($row = $result->fetch_assoc()): 
                $id = $row['id']; $folio = $row['folio']; $ent = strtoupper($row['entidad'] ?? 'PROATAM');
                $entColor = $entidadColores[$ent] ?? '#1a3a5c';
              ?>
                <div class="list-group-item d-flex justify-content-between align-items-center py-3 cot-card bg-white" id="item-<?= $id ?>">
                  <div class="d-flex align-items-center gap-3">
                    <div class="badge-entidad" style="background: <?= $entColor ?>;"><?= htmlspecialchars($ent) ?></div>
                    <div>
                      <h6 class="mb-0 fw-bold"><?= htmlspecialchars($row['atencion']) ?></h6>
                      <div class="text-muted small">
                        <span class="me-2"><i class="bi bi-building me-1"></i><?= htmlspecialchars($row['compania'] ?: 'Sin empresa') ?></span>
                        <span class="me-2"><i class="bi bi-hash me-1"></i><?= htmlspecialchars($folio) ?></span>
                        <span><i class="bi bi-calendar3 me-1"></i><?= date('d/m/Y', strtotime($row['fecha_emision'])) ?></span>
                      </div>
                    </div>
                  </div>
                  <div class="d-flex align-items-center gap-4">
                    <div class="text-end">
                      <div class="text-muted small fw-bold text-uppercase" style="font-size: 0.6rem;">Total</div>
                      <div class="fw-bold text-primary fs-5">$<?= number_format($row['total'], 2) ?> <small class="text-muted" style="font-size: 0.6rem;"><?= $row['moneda'] ?></small></div>
                    </div>
                    <div class="btn-group gap-1">
                      <a href="descargar_cotizacion.php?id=<?= $id ?>" class="btn btn-sm btn-outline-danger" title="PDF"><i class="bi bi-file-earmark-pdf"></i></a>
                      <button class="btn btn-sm btn-outline-info" onclick="verCotizacion(<?= $id ?>, '<?= $folio ?>')" title="Ver"><i class="bi bi-eye"></i></button>
                      <button class="btn btn-sm btn-outline-primary" onclick="editarCotizacion(<?= $id ?>)" title="Editar"><i class="bi bi-pencil-square"></i></button>
                      <button class="btn btn-sm btn-outline-danger" onclick="eliminarCotizacion(<?= $id ?>, '<?= $folio ?>')" title="Eliminar"><i class="bi bi-trash3"></i></button>
                    </div>
                  </div>
                </div>
              <?php endwhile; ?>
            </div>

            <?php if ($totalPags > 1): ?>
              <nav class="mt-4"><ul class="pagination justify-content-center">
                <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="?q=<?= urlencode($busqueda) ?>&page=<?= $pagina - 1 ?>">&laquo;</a></li>
                <?php foreach (range(max(1, $pagina - 2), min($totalPags, $pagina + 2)) as $i): ?>
                  <li class="page-item <?= $i == $pagina ? 'active' : '' ?>"><a class="page-link" href="?q=<?= urlencode($busqueda) ?>&page=<?= $i ?>"><?= $i ?></a></li>
                <?php endforeach; ?>
                <li class="page-item <?= $pagina >= $totalPags ? 'disabled' : '' ?>"><a class="page-link" href="?q=<?= urlencode($busqueda) ?>&page=<?= $pagina + 1 ?>">&raquo;</a></li>
              </ul></nav>
            <?php endif; ?>
          <?php else: ?>
            <div class="text-center py-5 text-muted"><i class="bi bi-file-earmark-x display-1 d-block mb-3"></i>No se encontraron cotizaciones.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  function verCotizacion(id, folio) {
    UI.modal({
      title: `<i class="bi bi-file-earmark-pdf me-2"></i> ${folio}`,
      size: "xl",
      html: `<iframe src="descargar_cotizacion.php?id=${id}&inline=1" style="width:100%;height:75vh;border:none;background:#fff;"></iframe>`,
      footer: `<a href="descargar_cotizacion.php?id=${id}" class="btn btn-success"><i class="bi bi-download me-1"></i> Descargar</a>`
    });
  }

  function editarCotizacion(id) {
    UI.loading("Cargando datos...");
    fetch('get_cotizacion.php?id=' + id).then(r => r.json()).then(d => {
      UI.loading.hide();
      if (!d.success) { UI.toast.error(d.message); return; }
      const c = d.cotizacion;
      UI.modal({
        title: "Editar Cotización - " + c.folio,
        size: "lg",
        html: `
          <form id="formEditCot">
            <input type="hidden" name="id" value="${c.id}">
            <div class="row g-3">
              <div class="col-md-6"><label class="form-label">Atención a</label><input type="text" name="atencion" class="form-control" value="${c.atencion || ''}" required></div>
              <div class="col-md-6"><label class="form-label">Compañía</label><input type="text" name="compania" class="form-control" value="${c.compania || ''}"></div>
              <div class="col-md-4"><label class="form-label">Moneda</label><select name="moneda" class="form-select"><option value="MXN" ${c.moneda==='MXN'?'selected':''}>MXN</option><option value="USD" ${c.moneda==='USD'?'selected':''}>USD</option></select></div>
              <div class="col-md-4"><label class="form-label">Fecha</label><input type="date" name="fecha_emision" class="form-control" value="${c.fecha_emision || ''}"></div>
              <div class="col-md-4"><label class="form-label">Vigencia</label><input type="text" name="vigencia" class="form-control" value="${c.vigencia || ''}"></div>
              <div class="col-12"><label class="form-label">Notas</label><textarea name="notas" class="form-control" rows="3">${c.notas || ''}</textarea></div>
            </div>
            <div class="text-end mt-4"><button type="button" class="btn btn-secondary me-2" onclick="UI.modal.close()">Cancelar</button><button type="submit" class="btn btn-primary">Guardar Cambios</button></div>
          </form>`
      });
      document.getElementById('formEditCot').addEventListener('submit', function(e) {
        e.preventDefault();
        UI.loading("Guardando...");
        fetch('update_cotizacion.php', { method: 'POST', body: new FormData(this) }).then(r => r.json()).then(resp => {
          UI.loading.hide();
          if (resp.status === 'success') { UI.modal.close(); UI.toast.success("Actualizado"); updateList(window.location.href); }
          else UI.toast.error(resp.message);
        });
      });
    });
  }

  function eliminarCotizacion(id, folio) {
    UI.confirm({ title: "¿Eliminar permanentemente?", message: `La cotización <b>${folio}</b> será eliminada.`, danger: true }).then(conf => {
      if (!conf) return;
      UI.loading("Eliminando...");
      fetch('delete_cotizacion.php?id=' + id).then(r => r.json()).then(data => {
        UI.loading.hide();
        if (data.status === 'success') { UI.toast.success("Eliminada"); updateList(window.location.href); }
        else UI.toast.error(data.message);
      });
    });
  }

  function updateList(url, pushState = true) {
    const container = document.getElementById('table-container-wrapper');
    UI.loading("Actualizando...");
    fetch(url).then(r => r.text()).then(html => {
        UI.loading.hide();
        const doc = new DOMParser().parseFromString(html, 'text/html');
        container.innerHTML = doc.getElementById('table-container-wrapper').innerHTML;
        if (pushState) window.history.pushState({}, '', url);
    });
  }

  document.getElementById('search-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const url = new URL(window.location);
    url.searchParams.set('q', this.q.value);
    url.searchParams.delete('page');
    updateList(url.toString());
  });

  document.addEventListener('click', e => {
    const link = e.target.closest('.page-link');
    if (link) { e.preventDefault(); updateList(link.href); window.scrollTo({top:0, behavior:'smooth'}); }
  });
</script>
</body>
</html>
