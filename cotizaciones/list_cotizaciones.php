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
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/core/modules.css?v=2.0">

<?php include __DIR__ . "/../includes/navbar.php"; ?>

<div class="orders-page-container">

    <!-- ─── PAGE HEADER ──────────────────────────────────────────── -->
    <div class="orders-page-header mb-4">
        <div class="orders-page-header-info">
            <nav class="orders-breadcrumb">
                <a href="<?= BASE_URL ?>/index.php">Inicio</a>
                <span class="separator">›</span>
                <span>Cotizaciones</span>
            </nav>
            <h1 class="orders-page-title">Historial de Cotizaciones</h1>
        </div>
        <a href="cotizacion.php" class="btn-geco-primary">
            <i class="fa-solid fa-circle-plus"></i> Nueva Cotización
        </a>
    </div>

    <!-- ─── FILTERS + SEARCH ─────────────────────────────────────── -->
    <div class="orders-card mb-4">
        <form id="search-form" method="GET">
            <div class="orders-filter-bar">


                <!-- Left: Search Input -->
                <div class="orders-filter-search">
                    <div class="search-input-wrap">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" name="q" id="visibleSearchInput"
                               placeholder="Buscar folio, atención, compañía..."
                               value="<?= htmlspecialchars($busqueda) ?>">
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- ─── TABLE ────────────────────────────────────────────────── -->
    <div class="orders-card orders-ajax-fade" id="table-container-wrapper">
        <div class="orders-table-wrap">
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>Entidad</th>
                        <th>Folio</th>
                        <th>Atención a</th>
                        <th>Compañía</th>
                        <th>Fecha Emisión</th>
                        <th>Total</th>
                        <th style="width: 130px; text-align: center;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): 
                            $id = $row['id']; 
                            $folio = $row['folio']; 
                            $ent = strtoupper($row['entidad'] ?? 'PROATAM');
                            $entColor = $entidadColores[$ent] ?? '#1a3a5c';
                        ?>
                            <tr id="item-<?= $id ?>">
                                <td>
                                    <strong><?= htmlspecialchars($ent) ?></strong>
                                </td>
                                <td class="cell-folio"><?= htmlspecialchars($folio) ?></td>
                                <td><strong><?= htmlspecialchars($row['atencion']) ?></strong></td>
                                <td><?= htmlspecialchars($row['compania'] ?: 'Sin empresa') ?></td>
                                <td class="cell-date"><?= date('d/m/Y', strtotime($row['fecha_emision'])) ?></td>
                                <td>
                                    <span class="fw-bold" style="color: var(--s-700);">
                                        $<?= number_format($row['total'], 2) ?>
                                    </span>
                                    <small class="text-muted" style="font-size: 0.75rem;"><?= $row['moneda'] ?></small>
                                </td>
                                <td>
                                    <div class="actions-group" style="justify-content: center;">
                                        <a href="descargar_cotizacion.php?id=<?= $id ?>" 
                                           class="btn-action btn-action--download" 
                                           title="Descargar PDF">
                                            <i class="fa-solid fa-download"></i>
                                        </a>
                                        <button class="btn-action btn-action--view" 
                                                onclick="verCotizacion(<?= $id ?>, '<?= $folio ?>')" 
                                                title="Ver Vista Previa">
                                            <i class="fa-regular fa-eye"></i>
                                        </button>
                                        <button class="btn-action btn-action--edit" 
                                                onclick="editarCotizacion(<?= $id ?>)" 
                                                title="Editar Cotización">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <button class="btn-action btn-action--delete" 
                                                onclick="eliminarCotizacion(<?= $id ?>, '<?= $folio ?>')" 
                                                title="Eliminar Cotización">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7">
                                <div class="orders-empty-state">
                                    <i class="fa-solid fa-inbox" style="font-size: 2.5rem; color: var(--gray-400);"></i>
                                    <p>No se encontraron cotizaciones registradas</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="orders-pagination-bar">
            <!-- Left Info -->
            <div class="orders-pagination-left">
                <span class="orders-pagination-info">
                    <?php
                    $inicio_registro = $totalRegistros > 0 ? $offset + 1 : 0;
                    $fin_registro = min($offset + $porPagina, $totalRegistros);
                    ?>
                    Mostrando <strong><?= $inicio_registro ?>-<?= $fin_registro ?></strong> de <strong><?= $totalRegistros ?></strong> resultados
                </span>
            </div>

            <!-- Center Nav and Right Controls -->
            <div class="orders-pagination-controls">
                <?php if ($totalPags > 1): ?>
                    <nav class="orders-pagination-nav" aria-label="Paginación">
                        <!-- Ir al primero -->
                        <a class="page-btn page-link <?= $pagina <= 1 ? 'disabled' : '' ?>" 
                           href="?q=<?= urlencode($busqueda) ?>&page=1"
                           aria-label="Primera página">
                           &laquo;
                        </a>

                        <!-- Anterior -->
                        <a class="page-btn page-link <?= $pagina <= 1 ? 'disabled' : '' ?>" 
                           href="?q=<?= urlencode($busqueda) ?>&page=<?= max(1, $pagina - 1) ?>"
                           aria-label="Página anterior">
                           &lsaquo;
                        </a>

                        <!-- Números de página -->
                        <?php 
                        $rango_inicio = max(1, $pagina - 2);
                        $rango_fin = min($totalPags, $pagina + 2);
                        for ($i = $rango_inicio; $i <= $rango_fin; $i++): 
                        ?>
                            <a class="page-btn page-link <?= $i == $pagina ? 'active' : '' ?>"
                               href="?q=<?= urlencode($busqueda) ?>&page=<?= $i ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <!-- Siguiente -->
                        <a class="page-btn page-link <?= $pagina >= $totalPags ? 'disabled' : '' ?>" 
                           href="?q=<?= urlencode($busqueda) ?>&page=<?= min($totalPags, $pagina + 1) ?>"
                           aria-label="Página siguiente">
                           &rsaquo;
                        </a>

                        <!-- Ir al último -->
                        <a class="page-btn page-link <?= $pagina >= $totalPags ? 'disabled' : '' ?>" 
                           href="?q=<?= urlencode($busqueda) ?>&page=<?= $totalPags ?>"
                           aria-label="Última página">
                           &raquo;
                        </a>
                    </nav>

                    <!-- Divider -->
                    <div class="orders-pagination-divider"></div>

                    <!-- Go to page -->
                    <div class="orders-pagination-goto">
                        <span>Ir a</span>
                        <input type="number" 
                               class="goto-page-input" 
                               min="1" 
                               max="<?= $totalPags ?>" 
                               value="<?= $pagina ?>"
                               aria-label="Ir a la página">
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ─── SCRIPTS ──────────────────────────────────────────────── -->
    <script>
      function verCotizacion(id, folio) {
        UI.modal({
          title: `<i class="fa-solid fa-file-pdf me-2"></i> ${folio}`,
          size: "xl",
          html: `<iframe src="descargar_cotizacion.php?id=${id}&inline=1" style="width:100%;height:75vh;border:none;background:#fff;"></iframe>`,
          footer: `<a href="descargar_cotizacion.php?id=${id}" class="btn btn-success"><i class="fa-solid fa-download me-1"></i> Descargar</a>`
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

      document.addEventListener('DOMContentLoaded', function() {
        const searchForm = document.getElementById('search-form');

        if (searchForm) {
          searchForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const url = new URL(window.location);
            url.searchParams.set('q', this.q.value);
            url.searchParams.delete('page');
            updateList(url.toString());
          });
        }

        document.addEventListener('keydown', function(e) {
          if (e.target.classList.contains('goto-page-input') && e.key === 'Enter') {
            e.preventDefault();
            handleGoToPage(e.target);
          }
        });

        function handleGoToPage(input) {
          var page = parseInt(input.value);
          var maxPage = parseInt(input.getAttribute('max'));
          if (isNaN(page) || page < 1) {
            page = 1;
          } else if (page > maxPage) {
            page = maxPage;
          }

          var url = new URL(window.location);
          const qInput = document.querySelector('#search-form input[name="q"]');
          url.searchParams.set('q', qInput ? qInput.value : "");
          url.searchParams.set('page', page);
          updateList(url.toString());
          window.scrollTo({ top: 0, behavior: 'smooth' });
        }
      });

      document.addEventListener('click', e => {
        const link = e.target.closest('.page-link');
        if (link) { e.preventDefault(); updateList(link.href); window.scrollTo({top:0, behavior:'smooth'}); }
      });
    </script>
</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>

