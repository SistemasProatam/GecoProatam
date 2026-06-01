<?php
// Incluir el gestor de sesiones UNA sola vez
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";
// Verificar sesión y prevenir caching
checkSession();
preventCaching();

require_once __DIR__ . "/../conexion.php";

// ==== Configuración de entidades ====
$entidades = [
  'productos_servicios' => [
    'nombre' => 'Productos y Servicios',
    'tabla' => 'productos_servicios',
    'campo_nombre' => 'nombre',
    'icono' => 'fa-solid fa-box-open',
    'color' => 'primary'
  ],
  'unidades' => [
    'nombre' => 'Unidades',
    'tabla' => 'unidades',
    'campo_nombre' => 'nombre',
    'icono' => 'fa-solid fa-ruler-combined',
    'color' => 'success'
  ],
  'categorias' => [
    'nombre' => 'Categorías',
    'tabla' => 'categorias',
    'campo_nombre' => 'nombre',
    'icono' => 'fa-solid fa-tags',
    'color' => 'info'
  ],
  'entidades' => [
    'nombre' => 'Entidades',
    'tabla' => 'entidades',
    'campo_nombre' => 'nombre',
    'icono' => 'fa-solid fa-building',
    'color' => 'warning'
  ],
  'proveedores' => [
    'nombre' => 'Proveedores',
    'tabla' => 'proveedores',
    'campo_nombre' => 'razon_social',
    'icono' => 'fa-solid fa-truck',
    'color' => 'danger'
  ],
  'clientes' => [
    'nombre' => 'Clientes',
    'tabla' => 'clientes',
    'campo_nombre' => 'nombre',
    'icono' => 'fa-solid fa-users',
    'color' => 'secondary'
  ]
];

// ==== Entidad seleccionada ====
$entidad_seleccionada = $_GET['entidad'] ?? 'productos_servicios';
$entidad_config = $entidades[$entidad_seleccionada] ?? $entidades['productos_servicios'];

// ==== Filtros ====
$busqueda = $_GET['q'] ?? '';
$tipo = $_GET['tipo'] ?? '';
$proveedor_id = $_GET['proveedor'] ?? '';
$activo = $_GET['activo'] ?? '1';

$pagina = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$por_pagina = 10;
$offset = ($pagina - 1) * $por_pagina;

// ====== Query base dinámica ======
$sqlBase = "FROM {$entidad_config['tabla']} e";
$params = [];
$types = "";

// Condición base
$sqlBase .= " WHERE 1=1";

// Busqueda
if (!empty($busqueda)) {
  $sqlBase .= " AND e.{$entidad_config['campo_nombre']} LIKE ?";
  $like = "%$busqueda%";
  $params[] = $like;
  $types .= "s";
}

// Filtro tipo (solo para productos_servicios)
if (!empty($tipo) && $entidad_seleccionada === 'productos_servicios') {
  $sqlBase .= " AND e.tipo = ?";
  $params[] = $tipo;
  $types .= "s";
}

// Filtro activo (para todas las entidades que tengan el campo)
if (in_array($entidad_seleccionada, ['productos_servicios', 'unidades', 'categorias', 'entidades', 'clientes'])) {
  $sqlBase .= " AND e.activo = ?";
  $params[] = $activo;
  $types .= "s";
}

// ====== Total registros ======
$stmtTotal = $conn->prepare("SELECT COUNT(*) AS total $sqlBase");
if ($types) $stmtTotal->bind_param($types, ...$params);
$stmtTotal->execute();
$totalRegistros = $stmtTotal->get_result()->fetch_assoc()['total'] ?? 0;

// ====== Datos paginados ======
$campos_select = "e.id, e.{$entidad_config['campo_nombre']} as nombre";
if ($entidad_seleccionada === 'productos_servicios') {
  $campos_select .= ", e.tipo, e.descripcion";
} elseif ($entidad_seleccionada === 'clientes') {
  $campos_select .= ", e.nombre_abreviado";
}

$sqlDatos = "SELECT $campos_select $sqlBase ORDER BY e.{$entidad_config['campo_nombre']} ASC LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sqlDatos);
$paramsPag = $params;
$typesPag = $types . "ii";
$paramsPag[] = $por_pagina;
$paramsPag[] = $offset;
$stmt->bind_param($typesPag, ...$paramsPag);
$stmt->execute();
$result = $stmt->get_result();

// ====== Datos para filtros ======
$proveedoresOptions = "";
if ($entidad_seleccionada === 'productos_servicios') {
  $proveedores = $conn->query("SELECT id, razon_social FROM proveedores WHERE activo=1 ORDER BY razon_social ASC");
  while ($prov = $proveedores->fetch_assoc()) {
    $selected = $proveedor_id == $prov['id'] ? "selected" : "";
    $proveedoresOptions .= "<option value='{$prov['id']}' $selected>{$prov['razon_social']}</option>";
  }
}

// Total páginas
$totalPaginas = ceil($totalRegistros / $por_pagina);

// Colores de icono por entidad
$iconColors = [
  'productos_servicios' => '#3b82f6',
  'unidades'            => '#22c55e',
  'categorias'          => '#06b6d4',
  'entidades'           => '#f59e0b',
  'proveedores'         => '#ef4444',
  'clientes'            => '#8b5cf6',
];
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/core/modules.css?v=2.0">
<link rel="icon" href="<?= BASE_URL ?>/assets/img/LogoCuadro.ico" type="image/x-icon">



<?php include __DIR__ . "/../includes/navbar.php"; ?>

<div class="orders-page-container">

  <!-- ─── PAGE HEADER ──────────────────────────────────────────── -->
  <div class="orders-page-header mb-4">
    <div class="orders-page-header-info">
      <nav class="orders-breadcrumb">
        <a href="<?= BASE_URL ?>/index.php">Inicio</a>
        <span class="separator">›</span>
        <span>Catálogo del Sistema</span>
      </nav>
      <h1 class="orders-page-title">Catálogo del Sistema</h1>
    </div>
  </div>

  <!-- ─── CONTENIDO LISTA ────────────────────────────────────── -->
  <div id="contenido-lista" data-entidad-actual="<?= $entidad_seleccionada ?>">

    <!-- CARD 1: SECCIONES DEL CATÁLOGO -->
    <div class="orders-card mb-3">
      <!-- ─── ENTITY SELECTOR ──────────────────────────────────────── -->
      <div class="entity-grid" style="margin-bottom: 0;">
        <?php foreach ($entidades as $key => $entidad): ?>
          <div class="entity-card <?= $entidad_seleccionada === $key ? 'active' : '' ?>"
               onclick="seleccionarEntidad('<?= $key ?>')"
               data-entidad="<?= $key ?>">
            <div class="entity-card-icon" style="color:<?= $iconColors[$key] ?? '#6b7280' ?>">
              <i class="<?= $entidad['icono'] ?>"></i>
            </div>
            <div class="entity-card-label"><?= $entidad['nombre'] ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- CARD 2: FILTROS DE BÚSQUEDA -->
    <div class="orders-card mb-3">
      <!-- Hidden search form -->
      <form id="search-form" method="GET" style="display:none;">
        <input type="hidden" name="entidad" value="<?= $entidad_seleccionada ?>">
        <input type="hidden" name="tipo"    value="<?= htmlspecialchars($tipo) ?>">
        <input type="search" name="q"       value="<?= htmlspecialchars($busqueda) ?>">
      </form>

      <!-- Filter form -->
      <form id="filter-form" method="GET">
        <input type="hidden" name="entidad" value="<?= $entidad_seleccionada ?>">
        <input type="hidden" name="q"       value="<?= htmlspecialchars($busqueda) ?>">

        <div class="orders-filter-bar">

          <!-- Tabs de tipo (solo productos_servicios) -->
          <?php if ($entidad_seleccionada === 'productos_servicios'): ?>
            <div class="orders-filter-tabs" id="tipoTabs">
              <?php foreach (['' => 'Todos', 'producto' => 'Productos', 'servicio' => 'Servicios'] as $val => $lbl): ?>
                <button type="button" class="tab-btn <?= $tipo === $val ? 'active' : '' ?>" data-tipo="<?= $val ?>"><?= $lbl ?></button>
              <?php endforeach; ?>
            </div>
            <select name="tipo" id="tipoSelect" style="display:none;">
              <option value="">Todos</option>
              <option value="producto" <?= $tipo === 'producto' ? 'selected' : '' ?>>Productos</option>
              <option value="servicio" <?= $tipo === 'servicio' ? 'selected' : '' ?>>Servicios</option>
            </select>
          <?php endif; ?>

          <!-- Cliente: historial -->
          <?php if ($entidad_seleccionada === 'clientes'): ?>
            <a href="historial_cliente.php" class="btn-geco-outline">
              <i class="fa-solid fa-clock-rotate-left"></i> Historial evaluaciones
            </a>
          <?php endif; ?>

          <!-- Search + Agregar -->
          <div class="orders-filter-search" style="margin-left:auto; display:flex; align-items:center; gap:0.75rem;">
            <div class="search-input-wrap">
              <i class="fa-solid fa-magnifying-glass"></i>
              <input type="text" id="visibleSearchInput"
                     placeholder="Buscar <?= strtolower($entidad_config['nombre']) ?>..."
                     value="<?= htmlspecialchars($busqueda) ?>">
            </div>
            <button id="btnAgregarItem" class="btn-geco-primary" type="button" onclick="agregarItem()">
              <i class="fa-solid fa-plus"></i> Agregar
            </button>
          </div>

        </div>
      </form>
    </div>

    <!-- CARD 3: TABLA DE DATOS -->
    <div class="orders-card">
      <!-- ─── TABLE ──────────────────────────────────────────────── -->
      <div id="table-container-wrapper">

      <?php if ($result && $result->num_rows > 0): ?>
        <div class="orders-table-wrap">
          <table class="orders-table">
            <thead>
              <tr>
                <th>Nombre</th>
                <?php if ($entidad_seleccionada === 'productos_servicios'): ?>
                  <th>Tipo</th>
                <?php endif; ?>
                <?php if ($entidad_seleccionada === 'clientes'): ?>
                  <th>Abreviado</th>
                <?php endif; ?>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                  <td>
                    <span class="cell-folio">
                      <?php
                        if ($entidad_seleccionada === 'clientes' && !empty($row['nombre_abreviado'])):
                          echo htmlspecialchars($row['nombre_abreviado']);
                        else:
                          echo htmlspecialchars($row['nombre']);
                        endif;
                      ?>
                    </span>
                  </td>
                  <?php if ($entidad_seleccionada === 'productos_servicios'): ?>
                    <td>
                      <span style="font-weight: 600; font-size: 0.85rem; color: <?= $row['tipo'] == 'producto' ? 'var(--p-600)' : 'var(--s-600)' ?>;">
                        <?= ucfirst(htmlspecialchars($row['tipo'])) ?>
                      </span>
                    </td>
                  <?php endif; ?>
                  <?php if ($entidad_seleccionada === 'clientes'): ?>
                    <td class="cell-muted"><?= htmlspecialchars($row['nombre'] ?? '') ?></td>
                  <?php endif; ?>
                  <td>
                    <div class="actions-group">
                      <button class="btn-action btn-action--view" onclick="mostrarItem(<?= $row['id'] ?>)">
                        <i class="fa-regular fa-eye"></i>
                      </button>
                      <?php if ($entidad_seleccionada === 'proveedores'): ?>
                        <button class="btn-action btn-action--edit" onclick="evaluarProveedor(<?= $row['id'] ?>)"
                                style="color:#f59e0b; border-color:rgba(245,158,11,0.3);">
                          <i class="fa-solid fa-star"></i>
                        </button>
                      <?php endif; ?>
                      <button class="btn-action btn-action--edit" onclick="editarItem(<?= $row['id'] ?>)">
                        <i class="fa-solid fa-pen-to-square"></i>
                      </button>
                      <button class="btn-action btn-action--delete" onclick="eliminarItem(<?= $row['id'] ?>)">
                        <i class="fa-solid fa-trash-can"></i>
                      </button>
                    </div>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
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
          <?php if ($totalPaginas > 1): ?>
            <div class="orders-pagination-controls">
              <nav class="orders-pagination-nav">
                <?php
                  $maxVisible = 10;
                  $bloqueActual = ceil($pagina / $maxVisible);
                  $inicio = (($bloqueActual - 1) * $maxVisible) + 1;
                  $finPag = min($inicio + $maxVisible - 1, $totalPaginas);
                ?>
                <a class="page-btn page-link <?= $pagina <= 1 ? 'disabled' : '' ?>"
                   href="?entidad=<?= $entidad_seleccionada ?>&q=<?= urlencode($busqueda) ?>&tipo=<?= urlencode($tipo) ?>&page=1">«</a>
                <a class="page-btn page-link <?= $pagina <= 1 ? 'disabled' : '' ?>"
                   href="?entidad=<?= $entidad_seleccionada ?>&q=<?= urlencode($busqueda) ?>&tipo=<?= urlencode($tipo) ?>&page=<?= $pagina - 1 ?>">‹</a>
                <?php for ($i = $inicio; $i <= $finPag; $i++): ?>
                  <a class="page-btn page-link <?= $i == $pagina ? 'active' : '' ?>"
                     href="?entidad=<?= $entidad_seleccionada ?>&q=<?= urlencode($busqueda) ?>&tipo=<?= urlencode($tipo) ?>&page=<?= $i ?>"><?= $i ?></a>
                <?php endfor; ?>
                <a class="page-btn page-link <?= $pagina >= $totalPaginas ? 'disabled' : '' ?>"
                   href="?entidad=<?= $entidad_seleccionada ?>&q=<?= urlencode($busqueda) ?>&tipo=<?= urlencode($tipo) ?>&page=<?= $pagina + 1 ?>">›</a>
                <a class="page-btn page-link <?= $pagina >= $totalPaginas ? 'disabled' : '' ?>"
                   href="?entidad=<?= $entidad_seleccionada ?>&q=<?= urlencode($busqueda) ?>&tipo=<?= urlencode($tipo) ?>&page=<?= $totalPaginas ?>">»</a>
              </nav>
              <div class="orders-pagination-divider"></div>
              <div class="orders-pagination-goto">
                <span>Ir a</span>
                <input type="number" class="goto-page-input" min="1" max="<?= $totalPaginas ?>" value="<?= $pagina ?>">
              </div>
            </div>
          <?php endif; ?>
        </div>

      <?php else: ?>
        <div class="orders-empty-state">
          <i class="fa-solid fa-inbox"></i>
          <p>No hay <?= strtolower($entidad_config['nombre']) ?> registrados</p>
        </div>
      <?php endif; ?>

      </div><!-- /table-container-wrapper -->
    </div><!-- /orders-card -->

  </div><!-- /contenido-lista -->

  <!-- ─── SCRIPTS ──────────────────────────────────────────────── -->
  <script>
    let entidadActual = '<?= $entidad_seleccionada ?>';
    const proveedoresOptions = `<?= $proveedoresOptions ?>`;

    document.addEventListener('DOMContentLoaded', function() {
      const visibleSearch = document.getElementById('visibleSearchInput');
      const filterForm   = document.getElementById('filter-form');
      const searchForm   = document.getElementById('search-form');

      // Sync visible search
      if (visibleSearch) {
        visibleSearch.addEventListener('input', function() {
          filterForm.querySelector('input[name="q"]').value = this.value;
          searchForm.querySelector('input[name="q"]').value = this.value;
        });
        visibleSearch.addEventListener('keydown', function(e) {
          if (e.key === 'Enter') { e.preventDefault(); filterForm.requestSubmit(); }
        });
      }

      // Tipo tabs
      document.querySelectorAll('#tipoTabs .tab-btn').forEach(function(tab) {
        tab.addEventListener('click', function() {
          document.getElementById('tipoSelect').value = this.dataset.tipo;
          document.querySelectorAll('#tipoTabs .tab-btn').forEach(t => t.classList.remove('active'));
          this.classList.add('active');
          filterForm.requestSubmit();
        });
      });

      // Filter selects auto submit
      filterForm.querySelectorAll('select').forEach(function(sel) {
        sel.addEventListener('change', function() { filterForm.requestSubmit(); });
      });

      // AJAX for pagination & search (updates only #table-container-wrapper)
      const container = document.getElementById('table-container-wrapper');
      const catalogCard = document.getElementById('contenido-lista');

      window.updateList = function(url, pushState = true) {
        container.style.opacity = '0.5';
        container.style.pointerEvents = 'none';
        fetch(url)
          .then(r => r.text())
          .then(html => {
            const doc = new DOMParser().parseFromString(html, 'text/html');
            const newContent = doc.getElementById('table-container-wrapper');
            if (newContent) container.innerHTML = newContent.innerHTML;
            container.style.opacity = '1';
            container.style.pointerEvents = 'auto';
            if (pushState) window.history.pushState({}, '', url);
          })
          .catch(() => {
            container.style.opacity = '1';
            container.style.pointerEvents = 'auto';
          });
      };

      // AJAX for entity switch (updates entire #contenido-lista card)
      window.updateCatalogCard = function(url, pushState = true) {
        catalogCard.style.opacity = '0.5';
        catalogCard.style.pointerEvents = 'none';
        fetch(url)
          .then(r => r.text())
          .then(html => {
            const doc = new DOMParser().parseFromString(html, 'text/html');
            const newCard = doc.getElementById('contenido-lista');
            if (newCard) {
              catalogCard.innerHTML = newCard.innerHTML;
              catalogCard.dataset.entidadActual = newCard.dataset.entidadActual;
              // Re-bind filter form events after innerHTML replace
              initFilterEvents();
            }
            catalogCard.style.opacity = '1';
            catalogCard.style.pointerEvents = 'auto';
            if (pushState) window.history.pushState({}, '', url);
          })
          .catch(() => {
            catalogCard.style.opacity = '1';
            catalogCard.style.pointerEvents = 'auto';
          });
      };

      document.addEventListener('click', function(e) {
        const pl = e.target.closest('.page-link');
        if (pl) { e.preventDefault(); updateList(pl.href); window.scrollTo({ top: 0, behavior: 'smooth' }); }
      });

      document.addEventListener('change', function(e) {
        if (e.target.classList.contains('goto-page-input')) handleGoToPage(e.target);
      });
      document.addEventListener('keydown', function(e) {
        if (e.target.classList.contains('goto-page-input') && e.key === 'Enter') {
          e.preventDefault(); handleGoToPage(e.target);
        }
      });

      function handleGoToPage(input) {
        let page = parseInt(input.value);
        const max = parseInt(input.getAttribute('max'));
        if (isNaN(page) || page < 1) page = 1;
        else if (page > max) page = max;
        const params = new URLSearchParams(new FormData(filterForm));
        params.set('q', searchForm.querySelector('input[name="q"]').value || '');
        params.set('page', page);
        updateList('?' + params.toString());
        window.scrollTo({ top: 0, behavior: 'smooth' });
      }

      initFilterEvents();
    });

    function initFilterEvents() {
      const visibleSearch = document.getElementById('visibleSearchInput');
      const filterForm   = document.getElementById('filter-form');
      const searchForm   = document.getElementById('search-form');
      if (!filterForm || !searchForm) return;

      // Sync visible search
      if (visibleSearch) {
        visibleSearch.oninput = function() {
          filterForm.querySelector('input[name="q"]').value = this.value;
          searchForm.querySelector('input[name="q"]').value = this.value;
        };
        visibleSearch.onkeydown = function(e) {
          if (e.key === 'Enter') { e.preventDefault(); filterForm.requestSubmit(); }
        };
      }

      // Tipo tabs
      document.querySelectorAll('#tipoTabs .tab-btn').forEach(function(tab) {
        tab.onclick = function() {
          const sel = document.getElementById('tipoSelect');
          if (sel) sel.value = this.dataset.tipo;
          document.querySelectorAll('#tipoTabs .tab-btn').forEach(t => t.classList.remove('active'));
          this.classList.add('active');
          filterForm.requestSubmit();
        };
      });

      // Filter selects auto submit
      filterForm.querySelectorAll('select').forEach(function(sel) {
        sel.onchange = function() { filterForm.requestSubmit(); };
      });

      // Form submit => AJAX
      [searchForm, filterForm].forEach(function(form) {
        form.onsubmit = function(e) {
          e.preventDefault();
          const params = new URLSearchParams(new FormData(filterForm));
          params.set('q', (searchForm.querySelector('input[name="q"]') || {}).value || '');
          params.set('page', '1');
          updateList('?' + params.toString());
        };
      });
    }

    function seleccionarEntidad(entidad) {
      entidadActual = entidad;
      const url = new URL(window.location);
      url.searchParams.set('entidad', entidad);
      url.searchParams.delete('page');
      url.searchParams.delete('q');
      url.searchParams.delete('tipo');
      // Update entity card highlight immediately
      document.querySelectorAll('.entity-card').forEach(c => c.classList.remove('active'));
      const activeCard = document.querySelector(`.entity-card[data-entidad="${entidad}"]`);
      if (activeCard) activeCard.classList.add('active');
      // Update entire catalog card via AJAX (filter bar + table)
      updateCatalogCard(url.toString());
    }

    function mostrarItem(id) {
      UI.loading('Cargando detalle...');
      fetch(`details_${entidadActual}.php?id=${id}`)
        .then(res => res.text())
        .then(data => {
          UI.loading.hide();
          UI.modal({
            title: `Detalle`,
            html: `<div style="text-align:left;">${data}</div>`,
            size: 'md'
          });
        })
        .catch(() => {
          UI.loading.hide();
          UI.toast.error('No se pudo cargar la información');
        });
    }

    function agregarItem() {
      let formHtml = '';
      switch (entidadActual) {
        case 'productos_servicios':
          formHtml = `<form id="formAgregarItem">
            <div class="mb-2"><label class="form-label">Tipo</label>
              <select class="form-select" name="tipo" required>
                <option value="producto">Producto</option>
                <option value="servicio">Servicio</option>
              </select></div>
            <div class="mb-2"><label class="form-label">Nombre</label>
              <input type="text" class="form-control" name="nombre" required></div>
            <div class="mb-2"><label class="form-label">Descripción</label>
              <textarea class="form-control" name="descripcion"></textarea></div>
          </form>`;
          break;
        case 'proveedores':
          formHtml = `<form id="formAgregarItem">
            <div class="mb-2"><label class="form-label">Razón Social <span class="text-danger">*</span></label>
              <input type="text" class="form-control" name="razon_social" required></div>
            <div class="mb-2"><label class="form-label">Nombre Comercial</label>
              <input type="text" class="form-control" name="nombre"></div>
            <div class="mb-2"><label class="form-label">RFC</label>
              <input type="text" class="form-control" name="rfc" maxlength="15"></div>
            <div class="mb-2"><label class="form-label">Teléfono</label>
              <input type="text" class="form-control" name="telefono"></div>
            <div class="mb-2"><label class="form-label">Email</label>
              <input type="email" class="form-control" name="email"></div>
            <div class="mb-2"><label class="form-label">Dirección</label>
              <textarea class="form-control" name="direccion" rows="3"></textarea></div>
            <div class="mb-2"><label class="form-label">Persona de Contacto</label>
              <input type="text" class="form-control" name="contacto"></div>
          </form>`;
          break;
        case 'clientes':
          formHtml = `<form id="formAgregarItem" class="swal-form">
            <div class="mb-2"><label class="form-label">Nombre <span class="text-danger">*</span></label>
              <input type="text" class="form-control" name="nombre" required></div>
            <div class="mb-2"><label class="form-label">Nombre Abreviado</label>
              <input type="text" class="form-control" name="nombre_abreviado"></div>
            <div class="mb-2"><label class="form-label">Email</label>
              <input type="email" class="form-control" name="email"></div>
            <div class="mb-2"><label class="form-label">RFC</label>
              <input type="text" class="form-control" name="rfc" maxlength="15"></div>
            <div class="mb-2"><label class="form-label">Dirección</label>
              <textarea class="form-control" name="direccion" rows="3"></textarea></div>
          </form>`;
          break;
        case 'unidades':
          formHtml = `<form id="formAgregarItem">
            <div class="mb-2"><label class="form-label">Nombre</label>
              <input type="text" class="form-control" name="nombre" required></div>
          </form>`;
          break;
        default:
          formHtml = `<form id="formAgregarItem">
            <div class="mb-2"><label class="form-label">Nombre</label>
              <input type="text" class="form-control" name="nombre" required></div>
            <div class="mb-2"><label class="form-label">Descripción</label>
              <textarea class="form-control" name="descripcion"></textarea></div>
          </form>`;
      }

      const m = UI.modal({
        title: `Agregar ${entidadActual.replace('_', ' ').toUpperCase()}`,
        html: formHtml,
        size: 'md',
        footer: `
          <button type="button" class="btn-geco-outline" onclick="UI.modal.close()">Cancelar</button>
          <button type="button" class="btn-geco-primary" id="btn-save-item">Guardar</button>
        `
      });

      document.getElementById('btn-save-item').onclick = () => {
        const form = document.getElementById('formAgregarItem');
        if (!form.checkValidity()) { form.reportValidity(); return; }
        
        UI.loading('Guardando...');
        fetch(`insert_${entidadActual}.php`, { method: 'POST', body: new FormData(form) })
          .then(r => r.json())
          .then(data => {
            UI.loading.hide();
            if (data.status === 'success') {
              UI.modal.close();
              UI.toast.success(data.message);
              setTimeout(() => location.reload(), 1000);
            } else { 
              UI.toast.error(data.message || 'Error al guardar'); 
            }
          })
          .catch(() => {
            UI.loading.hide();
            UI.toast.error('Error de conexión');
          });
      };
    }

    function editarItem(id) {
      UI.loading('Cargando registro...');
      fetch(`edit_${entidadActual}.php?id=${id}`)
        .then(r => r.json())
        .then(resp => {
          UI.loading.hide();
          if (resp.status !== 'success') { UI.toast.error(resp.message || 'No se pudo cargar el registro'); return; }
          const data = resp.data;
          let formHtml = '';
          switch (entidadActual) {
            case 'productos_servicios':
              formHtml = `<form id="formEditarItem" class="swal-form">
                <input type="hidden" name="id" value="${data.id}">
                <div class="mb-2"><label class="form-label">Tipo</label>
                  <select class="form-select" name="tipo" required>
                    <option value="producto" ${data.tipo === 'producto' ? 'selected' : ''}>Producto</option>
                    <option value="servicio" ${data.tipo === 'servicio' ? 'selected' : ''}>Servicio</option>
                  </select></div>
                <div class="mb-2"><label class="form-label">Nombre</label>
                  <input type="text" class="form-control" name="nombre" value="${data.nombre || ''}" required></div>
                <div class="mb-2"><label class="form-label">Descripción</label>
                  <textarea class="form-control" name="descripcion">${data.descripcion || ''}</textarea></div>
              </form>`;
              break;
            case 'proveedores':
              formHtml = `<form id="formEditarItem" class="swal-form">
                <input type="hidden" name="id" value="${data.id ?? ''}">
                <div class="mb-2"><label class="form-label">Razón Social <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" name="razon_social" value="${data.razon_social ?? ''}" required></div>
                <div class="mb-2"><label class="form-label">Nombre Comercial</label>
                  <input type="text" class="form-control" name="nombre" value="${data.nombre ?? ''}"></div>
                <div class="mb-2"><label class="form-label">RFC</label>
                  <input type="text" class="form-control" name="rfc" value="${data.rfc ?? ''}" maxlength="15"></div>
                <div class="mb-2"><label class="form-label">Teléfono</label>
                  <input type="text" class="form-control" name="telefono" value="${data.telefono ?? ''}"></div>
                <div class="mb-2"><label class="form-label">Email</label>
                  <input type="email" class="form-control" name="email" value="${data.email ?? ''}"></div>
                <div class="mb-2"><label class="form-label">Dirección</label>
                  <textarea class="form-control" name="direccion" rows="3">${data.direccion ?? ''}</textarea></div>
                <div class="mb-2"><label class="form-label">Persona de Contacto</label>
                  <input type="text" class="form-control" name="contacto" value="${data.contacto ?? ''}"></div>
              </form>`;
              break;
            case 'unidades':
              formHtml = `<form id="formEditarItem" class="swal-form">
                <input type="hidden" name="id" value="${data.id}">
                <div class="mb-2"><label class="form-label">Nombre</label>
                  <input type="text" class="form-control" name="nombre" value="${data.nombre || ''}" required></div>
              </form>`;
              break;
            case 'categorias':
              formHtml = `<form id="formEditarItem" class="swal-form">
                <input type="hidden" name="id" value="${data.id}">
                <div class="mb-2"><label class="form-label">Nombre</label>
                  <input type="text" class="form-control" name="nombre" value="${data.nombre || ''}" required></div>
                <div class="mb-2"><label class="form-label">Descripción</label>
                  <textarea class="form-control" name="descripcion">${data.descripcion || ''}</textarea></div>
              </form>`;
              break;
            case 'entidades':
              formHtml = `<form id="formEditarItem" class="swal-form">
                <input type="hidden" name="id" value="${data.id}">
                <div class="mb-2"><label class="form-label">Nombre</label>
                  <input type="text" class="form-control" name="nombre" value="${data.nombre || ''}" required></div>
                <div class="mb-2"><label class="form-label">Descripción</label>
                  <textarea class="form-control" name="descripcion">${data.descripcion || ''}</textarea></div>
              </form>`;
              break;
            case 'clientes':
              formHtml = `<form id="formEditarItem" class="swal-form">
                <input type="hidden" name="id" value="${data.id}">
                <div class="mb-2"><label class="form-label">Nombre <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" name="nombre" value="${data.nombre || ''}" required></div>
                <div class="mb-2"><label class="form-label">Nombre Abreviado</label>
                  <input type="text" class="form-control" name="nombre_abreviado" value="${data.nombre_abreviado || ''}"></div>
                <div class="mb-2"><label class="form-label">Email</label>
                  <input type="email" class="form-control" name="email" value="${data.email || ''}"></div>
                <div class="mb-2"><label class="form-label">RFC</label>
                  <input type="text" class="form-control" name="rfc" value="${data.rfc || ''}" maxlength="15"></div>
                <div class="mb-2"><label class="form-label">Dirección</label>
                  <textarea class="form-control" name="direccion" rows="3">${data.direccion || ''}</textarea></div>
              </form>`;
              break;
            default:
              formHtml = `<form id="formEditarItem" class="swal-form">
                <input type="hidden" name="id" value="${data.id}">
                <div class="mb-2"><label class="form-label">Nombre</label>
                  <input type="text" class="form-control" name="nombre" value="${data.nombre || ''}" required></div>
                <div class="mb-2"><label class="form-label">Descripción</label>
                  <textarea class="form-control" name="descripcion">${data.descripcion || ''}</textarea></div>
              </form>`;
          }

          const m = UI.modal({
            title: `Editar ${entidadActual.replace('_', ' ').toUpperCase()}`,
            html: formHtml,
            size: 'md',
            footer: `
              <button type="button" class="btn-geco-outline" onclick="UI.modal.close()">Cancelar</button>
              <button type="button" class="btn-geco-primary" id="btn-update-item">Actualizar</button>
            `
          });

          document.getElementById('btn-update-item').onclick = () => {
            const form = document.getElementById('formEditarItem');
            if (!form.checkValidity()) { form.reportValidity(); return; }
            
            UI.loading('Actualizando...');
            fetch(`update_${entidadActual}.php`, { method: 'POST', body: new FormData(form) })
              .then(r => r.json())
              .then(resp => {
                UI.loading.hide();
                if (resp.status === 'success') {
                  UI.modal.close();
                  UI.toast.success(resp.message);
                  setTimeout(() => location.reload(), 1000);
                } else { 
                  UI.toast.error(resp.message || 'Error al actualizar'); 
                }
              })
              .catch(err => {
                UI.loading.hide();
                UI.toast.error('Error de conexión: ' + err.message);
              });
          };
        })
        .catch(() => {
          UI.loading.hide();
          UI.toast.error('No se pudo cargar la información');
        });
    }

    async function eliminarItem(id) {
      const confirmado = await UI.confirm({
        title: '¿Eliminar este registro?',
        message: 'Esta acción no se puede deshacer',
        danger: true,
        confirmText: 'Sí, eliminar',
        cancelText: 'Cancelar'
      });

      if (confirmado) {
        UI.loading('Eliminando...');
        fetch(`delete_${entidadActual}.php?id=${id}`)
          .then(r => r.json())
          .then(data => {
            UI.loading.hide();
            if (data.status === 'success') {
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
    }

    function evaluarProveedor(id) {
      window.location.href = `evaluacion_proveedor.php?id=${id}`;
    }

    function historialCliente() {
      window.location.href = 'historial_cliente.php';
    }
  </script>

</div><!-- /orders-page-container -->

<?php include __DIR__ . "/../includes/footer.php"; ?>
