<?php
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";
checkSession();
preventCaching();
require_once __DIR__ . "/../conexion.php";

$entidades = [
  'productos_servicios' => [ 'nombre' => 'Productos y Servicios', 'tabla' => 'productos_servicios', 'campo_nombre' => 'nombre', 'icono' => 'bi-box-seam', 'color' => 'primary' ],
  'unidades' => [ 'nombre' => 'Unidades', 'tabla' => 'unidades', 'campo_nombre' => 'nombre', 'icono' => 'bi-rulers', 'color' => 'success' ],
  'categorias' => [ 'nombre' => 'Categorías', 'tabla' => 'categorias', 'campo_nombre' => 'nombre', 'icono' => 'bi-tags', 'color' => 'info' ],
  'entidades' => [ 'nombre' => 'Entidades', 'tabla' => 'entidades', 'campo_nombre' => 'nombre', 'icono' => 'bi-building', 'color' => 'warning' ],
  'proveedores' => [ 'nombre' => 'Proveedores', 'tabla' => 'proveedores', 'campo_nombre' => 'razon_social', 'icono' => 'bi-truck', 'color' => 'danger' ],
  'clientes' => [ 'nombre' => 'Clientes', 'tabla' => 'clientes', 'campo_nombre' => 'nombre', 'icono' => 'bi-people', 'color' => 'secondary' ]
];

$entidad_seleccionada = $_GET['entidad'] ?? 'productos_servicios';
$entidad_config = $entidades[$entidad_seleccionada] ?? $entidades['productos_servicios'];

$busqueda = $_GET['q'] ?? '';
$tipo = $_GET['tipo'] ?? '';
$activo = $_GET['activo'] ?? '1';
$pagina = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$por_pagina = 10;
$offset = ($pagina - 1) * $por_pagina;

$sqlBase = "FROM {$entidad_config['tabla']} e WHERE 1=1";
$params = []; $types = "";
if (!empty($busqueda)) { $sqlBase .= " AND e.{$entidad_config['campo_nombre']} LIKE ?"; $params[] = "%$busqueda%"; $types .= "s"; }
if (!empty($tipo) && $entidad_seleccionada === 'productos_servicios') { $sqlBase .= " AND e.tipo = ?"; $params[] = $tipo; $types .= "s"; }
if (in_array($entidad_seleccionada, ['productos_servicios', 'unidades', 'categorias', 'entidades', 'clientes'])) { $sqlBase .= " AND e.activo = ?"; $params[] = $activo; $types .= "s"; }

$stmtTotal = $conn->prepare("SELECT COUNT(*) AS total $sqlBase");
if ($types) $stmtTotal->bind_param($types, ...$params);
$stmtTotal->execute();
$totalRegistros = $stmtTotal->get_result()->fetch_assoc()['total'] ?? 0;

$campos_select = "e.id, e.{$entidad_config['campo_nombre']} as nombre";
if ($entidad_seleccionada === 'productos_servicios') $campos_select .= ", e.tipo, e.descripcion";
elseif ($entidad_seleccionada === 'clientes') $campos_select .= ", e.nombre_abreviado";

$sqlDatos = "SELECT $campos_select $sqlBase ORDER BY e.{$entidad_config['campo_nombre']} ASC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sqlDatos);
$paramsPag = $params; $typesPag = $types . "ii"; $paramsPag[] = $por_pagina; $paramsPag[] = $offset;
$stmt->bind_param($typesPag, ...$paramsPag);
$stmt->execute();
$result = $stmt->get_result();
$totalPaginas = ceil($totalRegistros / $por_pagina);
?>

<style>
    .catalog-card { transition: all 0.3s ease; cursor: pointer; border: 2px solid transparent; height: 100%; border-radius: 12px; }
    .catalog-card:hover { transform: translateY(-5px); border-color: #1a3a5c; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
    .catalog-card.active { border-color: #1a3a5c; background-color: #f8fafc; box-shadow: inset 0 0 0 1px #1a3a5c; }
    .card-icon { font-size: 2rem; margin-bottom: 0.5rem; }
    .badge-num { background: #1a3a5c; color: white; padding: 4px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; }
</style>

<?php include __DIR__ . "/../includes/navbar.php"; ?>

<div class="hero-section">
  <div class="container hero-content">
    <div class="breadcrumb-custom">
      <a href="<?= BASE_URL ?>/index.php"><i class="bi bi-house-door"></i> Inicio</a>
      <span>/</span>
      <span>Catálogo del Sistema</span>
    </div>
    <h1 class="hero-title">Catálogo del Sistema</h1>
  </div>
</div>

<div class="content-wrapper">
  <div class="container">
    <div class="row mb-4 g-3">
      <?php foreach ($entidades as $key => $entidad): ?>
        <div class="col-6 col-md-4 col-lg-2">
          <div class="card catalog-card <?= $entidad_seleccionada === $key ? 'active' : '' ?>" onclick="seleccionarEntidad('<?= $key ?>')" data-entidad="<?= $key ?>">
            <div class="card-body text-center p-3">
              <div class="card-icon text-<?= $entidad['color'] ?>"><i class="bi <?= $entidad['icono'] ?>"></i></div>
              <h6 class="card-title mb-0" style="font-size: 0.75rem;"><?= $entidad['nombre'] ?></h6>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
      <div class="card-body p-4">
        <div id="contenido-lista" data-entidad-actual="<?= $entidad_seleccionada ?>">
          <div class="row mb-4 align-items-center">
            <div class="col-md-6">
              <form id="search-form" class="d-flex gap-2">
                <input type="hidden" name="entidad" value="<?= $entidad_seleccionada ?>">
                <input class="form-control" type="search" name="q" placeholder="Buscar en <?= strtolower($entidad_config['nombre']) ?>..." value="<?= htmlspecialchars($busqueda) ?>">
                <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button>
              </form>
            </div>
            <div class="col-md-6 text-end mt-3 mt-md-0">
              <span class="badge-num me-2"><?= $totalRegistros ?> registros</span>
              <button class="btn btn-success" onclick="agregarItem()"><i class="bi bi-plus-circle me-1"></i> Agregar</button>
            </div>
          </div>

          <div id="table-container-wrapper">
            <?php if ($result && $result->num_rows > 0): ?>
              <div class="list-group list-group-flush border-top">
                <?php while ($row = $result->fetch_assoc()): ?>
                  <div class="list-group-item d-flex justify-content-between align-items-center py-3">
                    <div>
                      <h6 class="mb-0 fw-bold"><?= htmlspecialchars($row['nombre_abreviado'] ?? $row['nombre']) ?></h6>
                      <?php if (isset($row['tipo'])): ?><small class="text-muted"><?= ucfirst($row['tipo']) ?></small><?php endif; ?>
                    </div>
                    <div class="btn-group gap-1">
                      <button class="btn btn-sm btn-outline-info" onclick="mostrarItem(<?= $row['id'] ?>)"><i class="bi bi-info-circle"></i></button>
                      <?php if ($entidad_seleccionada === 'proveedores'): ?><button class="btn btn-sm btn-outline-warning" onclick="location.href='evaluacion_proveedor.php?id=<?= $row['id'] ?>'"><i class="bi bi-star"></i></button><?php endif; ?>
                      <button class="btn btn-sm btn-outline-primary" onclick="editarItem(<?= $row['id'] ?>)"><i class="bi bi-pencil"></i></button>
                      <button class="btn btn-sm btn-outline-danger" onclick="eliminarItem(<?= $row['id'] ?>)"><i class="bi bi-trash"></i></button>
                    </div>
                  </div>
                <?php endwhile; ?>
              </div>

              <?php if ($totalPaginas > 1): ?>
                <nav class="mt-4"><ul class="pagination justify-content-center">
                  <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="?entidad=<?= $entidad_seleccionada ?>&q=<?= urlencode($busqueda) ?>&page=<?= $pagina - 1 ?>">&laquo;</a></li>
                  <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                    <?php if ($i == 1 || $i == $totalPaginas || ($i >= $pagina - 2 && $i <= $pagina + 2)): ?>
                      <li class="page-item <?= $i == $pagina ? 'active' : '' ?>"><a class="page-link" href="?entidad=<?= $entidad_seleccionada ?>&q=<?= urlencode($busqueda) ?>&page=<?= $i ?>"><?= $i ?></a></li>
                    <?php elseif ($i == $pagina - 3 || $i == $pagina + 3): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
                  <?php endfor; ?>
                  <li class="page-item <?= $pagina >= $totalPaginas ? 'disabled' : '' ?>"><a class="page-link" href="?entidad=<?= $entidad_seleccionada ?>&q=<?= urlencode($busqueda) ?>&page=<?= $pagina + 1 ?>">&raquo;</a></li>
                </ul></nav>
              <?php endif; ?>
            <?php else: ?>
              <div class="text-center py-5 text-muted"><i class="bi bi-inbox display-1 d-block mb-3"></i>No se encontraron registros.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>

<script>
  let entidadActual = '<?= $entidad_seleccionada ?>';

  function seleccionarEntidad(entidad) {
    const url = new URL(window.location);
    url.searchParams.set('entidad', entidad);
    url.searchParams.delete('page');
    url.searchParams.delete('q');
    updateList(url.toString());
    document.querySelectorAll('.catalog-card').forEach(c => c.classList.toggle('active', c.dataset.entidad === entidad));
  }

  function mostrarItem(id) {
    UI.loading("Cargando...");
    fetch(`details_${entidadActual}.php?id=${id}`).then(r => r.text()).then(html => {
      UI.loading.hide();
      UI.modal({ title: "Detalle del Registro", html: `<div class="p-2">${html}</div>` });
    });
  }

  function agregarItem() {
    UI.loading("Cargando formulario...");
    fetch(`edit_${entidadActual}.php`).then(r => {
        return fetch(`insert_${entidadActual}.php?form_only=1`).catch(() => null);
    }).then(() => {
        scGenerarModalForm();
    });
  }

  function scGenerarModalForm(id = 0) {
    const isEdit = id > 0;
    if (isEdit) UI.loading("Cargando datos...");
    
    const url = isEdit ? `edit_${entidadActual}.php?id=${id}` : null;
    const promise = url ? fetch(url).then(r => r.json()) : Promise.resolve({status:'success', data:{}});

    promise.then(resp => {
        UI.loading.hide();
        if (resp.status !== 'success') { UI.toast.error("Error al cargar"); return; }
        const data = resp.data;
        let formHtml = `<form id="formItem"><input type="hidden" name="id" value="${data.id || ''}">`;
        
        if (entidadActual === 'productos_servicios') {
            formHtml += `
                <div class="mb-3"><label class="form-label">Tipo</label><select class="form-select" name="tipo" required><option value="producto" ${data.tipo==='producto'?'selected':''}>Producto</option><option value="servicio" ${data.tipo==='servicio'?'selected':''}>Servicio</option></select></div>
                <div class="mb-3"><label class="form-label">Nombre</label><input type="text" class="form-control" name="nombre" value="${data.nombre || ''}" required></div>
                <div class="mb-3"><label class="form-label">Descripción</label><textarea class="form-control" name="descripcion">${data.descripcion || ''}</textarea></div>`;
        } else if (entidadActual === 'proveedores') {
            formHtml += `
                <div class="mb-3"><label class="form-label">Razón Social</label><input type="text" class="form-control" name="razon_social" value="${data.razon_social || ''}" required></div>
                <div class="mb-3"><label class="form-label">RFC</label><input type="text" class="form-control" name="rfc" value="${data.rfc || ''}"></div>
                <div class="mb-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email" value="${data.email || ''}"></div>
                <div class="mb-3"><label class="form-label">Teléfono</label><input type="text" class="form-control" name="telefono" value="${data.telefono || ''}"></div>`;
        } else if (entidadActual === 'clientes') {
            formHtml += `
                <div class="mb-3"><label class="form-label">Nombre Completo</label><input type="text" class="form-control" name="nombre" value="${data.nombre || ''}" required></div>
                <div class="mb-3"><label class="form-label">Nombre Abreviado</label><input type="text" class="form-control" name="nombre_abreviado" value="${data.nombre_abreviado || ''}"></div>
                <div class="mb-3"><label class="form-label">RFC</label><input type="text" class="form-control" name="rfc" value="${data.rfc || ''}"></div>`;
        } else {
            formHtml += `
                <div class="mb-3"><label class="form-label">Nombre</label><input type="text" class="form-control" name="nombre" value="${data.nombre || ''}" required></div>
                <div class="mb-3"><label class="form-label">Descripción</label><textarea class="form-control" name="descripcion">${data.descripcion || ''}</textarea></div>`;
        }
        
        formHtml += `<div class="text-end mt-4"><button type="button" class="btn btn-secondary me-2" onclick="UI.modal.close()">Cancelar</button><button type="submit" class="btn btn-primary">${isEdit?'Actualizar':'Guardar'}</button></div></form>`;

        UI.modal({ title: (isEdit ? "Editar " : "Agregar ") + entidadActual.replace('_', ' '), html: formHtml });

        document.getElementById('formItem').addEventListener('submit', function(e) {
            e.preventDefault();
            UI.loading("Guardando...");
            const action = isEdit ? 'update' : 'insert';
            fetch(`${action}_${entidadActual}.php`, { method: 'POST', body: new FormData(this) }).then(r => r.json()).then(r => {
                UI.loading.hide();
                if (r.status === 'success') { UI.modal.close(); UI.toast.success("Registro guardado"); updateList(window.location.href); }
                else UI.toast.error(r.message);
            });
        });
    });
  }

  function editarItem(id) { scGenerarModalForm(id); }

  function eliminarItem(id) {
    UI.confirm({ title: "¿Eliminar registro?", message: "Esta acción no se puede deshacer.", danger: true }).then(conf => {
        if (!conf) return;
        UI.loading("Eliminando...");
        fetch(`delete_${entidadActual}.php?id=${id}`).then(r => r.json()).then(r => {
            UI.loading.hide();
            if (r.status === 'success') { UI.toast.success("Eliminado"); updateList(window.location.href); }
            else UI.toast.error(r.message);
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
        entidadActual = doc.getElementById('contenido-lista').dataset.entidadActual;
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