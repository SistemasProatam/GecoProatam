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
    'icono' => 'bi-box-seam',
    'color' => 'primary'
  ],
  'unidades' => [
    'nombre' => 'Unidades',
    'tabla' => 'unidades',
    'campo_nombre' => 'nombre',
    'icono' => 'bi-rulers',
    'color' => 'success'
  ],
  'categorias' => [
    'nombre' => 'Categorías',
    'tabla' => 'categorias',
    'campo_nombre' => 'nombre',
    'icono' => 'bi-tags',
    'color' => 'info'
  ],
  'entidades' => [
    'nombre' => 'Entidades',
    'tabla' => 'entidades',
    'campo_nombre' => 'nombre',
    'icono' => 'bi-building',
    'color' => 'warning'
  ],
  'proveedores' => [
    'nombre' => 'Proveedores',
    'tabla' => 'proveedores',
    'campo_nombre' => 'razon_social',
    'icono' => 'bi-truck',
    'color' => 'danger'
  ],
  'clientes' => [
    'nombre' => 'Clientes',
    'tabla' => 'clientes',
    'campo_nombre' => 'nombre',
    'icono' => 'bi-people',
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
?>

<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <title>Catálogo</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/list.css">
  <link rel="icon" href="<?= BASE_URL ?>/assets/img/LogoCuadro.ico" type="image/x-icon">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    .catalog-card {
      transition: all 0.3s ease;
      cursor: pointer;
      border: 2px solid transparent;
      height: 100%;
    }

    .card-title {
      font-size: 0.8rem;
    }

    .catalog-card:hover {
      transform: translateY(-5px);
      border-color: #113456;
    }

    .catalog-card.active {
      border-color: #113456;
      background-color: var(--bs-light);
    }

    .card-icon {
      font-size: 2rem;
      margin-bottom: 1rem;
    }
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
        <a href="<?= BASE_URL ?>/projects/list_project.php"> Cátalogo del sistema</a>
      </div>

      <div class="row align-items-end">
        <div class="col-lg-8">
          <h1 class="hero-title">Cátalogo del sistema</h1>
        </div>
      </div>
    </div>
  </div>
  </div>

  <!-- MAIN CONTENT -->
  <div class="content-wrapper">

    <div class="form-container">

      <div class="form-body">
        <!-- Cards de Selección de Entidades -->
        <div class="row mb-5">
          <?php

          foreach ($entidades as $key => $entidad): ?>
            <div class="col-md-4 col-lg-2 mb-3">
              <div class="card catalog-card <?= $entidad_seleccionada === $key ? 'active' : '' ?>"
                onclick="seleccionarEntidad('<?= $key ?>')"
                data-entidad="<?= $key ?>">
                <div class="card-body text-center">
                  <div class="card-icon text-<?= $entidad['color'] ?>">
                    <i class="bi <?= $entidad['icono'] ?>"></i>
                  </div>
                  <h6 class="card-title"><?= $entidad['nombre'] ?></h6>
                </div>
              </div>
            </div>
          <?php
          endforeach; ?>
        </div>

        <div id="contenido-lista" data-entidad-actual="<?= $entidad_seleccionada ?>">

          <!-- Buscador -->
          <form id="search-form" class="form-search d-flex justify-content-center w-100 mb-4" method="GET">
            <input type="hidden" name="entidad" value="<?= $entidad_seleccionada ?>">
            <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo) ?>">
            <input class="form-control w-100" type="search" name="q"
              placeholder="Buscar <?= strtolower($entidad_config['nombre']) ?>..."
              value="<?= htmlspecialchars($busqueda) ?>">
            <button class="btn btn-outline-success" type="submit">
              <i class="bi bi-search"></i>
            </button>
          </form>

          <div id="table-container-wrapper">

            <!-- Filtros Específicos por Entidad -->
            <?php
            if ($entidad_seleccionada === 'productos_servicios'): ?>
              <div class="mb-2">
                <h5 class="text-muted" style="font-size: 1rem; font-weight: 600;">
                  <i class="bi bi-funnel"></i> Filtros
                </h5>
              </div>
              <form id="filter-form" method="GET" class="d-flex flex-wrap align-items-center gap-2 mb-4">
                <input type="hidden" name="entidad" value="<?= $entidad_seleccionada ?>">
                <input type="hidden" name="q" value="<?= htmlspecialchars($busqueda) ?>">

                <div style="flex: 0 0 auto; min-width: 150px;">
                  <select name="tipo" class="form-select">
                    <option value="">-- Todos --</option>
                    <option value="producto" <?= $tipo === 'producto' ? 'selected' : '' ?>>Productos</option>
                    <option value="servicio" <?= $tipo === 'servicio' ? 'selected' : '' ?>>Servicios</option>
                  </select>
                </div>
              </form>
            <?php
            endif; ?>

            <!-- Botón de agregar -->
            <div class="d-flex justify-content-between mb-3">
              <span class="badge-num"><?= $totalRegistros ?> <?= strtolower($entidad_config['nombre']) ?></span>
              <button class="button-56" type="button" onclick="agregarItem()">
                <i class="bi bi-plus-circle"></i> Agregar <?= $entidad_config['nombre'] ?>
              </button>
            </div>

            <!-- Lista -->
            <?php
            if ($result && $result->num_rows > 0): ?>
              <ul class="list-group">
                <?php
                while ($row = $result->fetch_assoc()): ?>
                  <li class="list-group-item text-nowrap d-flex justify-content-between align-items-center">
                    <div>
                      <strong>
                        <?php
                        if ($entidad_seleccionada === 'clientes' && !empty($row['nombre_abreviado'])): ?>
                          <?= htmlspecialchars($row['nombre_abreviado']) ?>
                        <?php
                        else: ?>
                          <?= htmlspecialchars($row['nombre']) ?>
                        <?php
                        endif; ?>
                      </strong>

                      <?php
                      if ($entidad_seleccionada === 'productos_servicios'): ?>
                        <br>
                        <small class="text-muted">
                          Tipo: <?= ucfirst($row['tipo']) ?>
                        </small>
                      <?php
                      endif; ?>
                    </div>
                    <div class="btn-group" style="gap:5px;">
                      <button class="btn-inf" onclick="mostrarItem(<?= $row['id'] ?>)">
                        <i class="bi bi-info-circle"></i>
                      </button>
                      <?php
                      if ($entidad_seleccionada === 'proveedores'): ?>
                        <button class="btn-eva" onclick="evaluarProveedor(<?= $row['id'] ?>)">
                          <i class="bi bi-star"></i>
                        </button>
                      <?php
                      endif; ?>
                      <button class="btn-ed" onclick="editarItem(<?= $row['id'] ?>)">
                        <i class="bi bi-pencil"></i>
                      </button>
                      <button class="btn-del" onclick="eliminarItem(<?= $row['id'] ?>)">
                        <i class="bi bi-trash3"></i>
                      </button>
                    </div>
                  </li>
                <?php
                endwhile; ?>
              </ul>

              <!-- Paginación -->
              <?php
              if ($totalPaginas > 1): ?>

                <?php
                $maxVisible = 10; // cantidad máxima de números visibles

                // Calcular bloque actual de 10 páginas
                $bloqueActual = ceil($pagina / $maxVisible);
                $inicio = (($bloqueActual - 1) * $maxVisible) + 1;
                $fin = min($inicio + $maxVisible - 1, $totalPaginas);
                ?>

                <nav aria-label="Paginación">
                  <ul class="pagination justify-content-center mt-3">

                    <!-- Botón Anterior -->
                    <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
                      <a class="page-link"
                        href="?entidad=<?= $entidad_seleccionada ?>&q=<?= urlencode($busqueda) ?>&proveedor=<?= urlencode($proveedor_id) ?>&tipo=<?= urlencode($tipo) ?>&page=<?= $pagina - 1 ?>"
                        aria-label="Previous">
                        <span aria-hidden="true">&laquo;</span>
                      </a>
                    </li>

                    <!-- Números de página (solo 10 visibles) -->
                    <?php
                    for ($i = $inicio; $i <= $fin; $i++): ?>
                      <li class="page-item <?= $i == $pagina ? 'active' : '' ?>">
                        <a class="page-link"
                          href="?entidad=<?= $entidad_seleccionada ?>&q=<?= urlencode($busqueda) ?>&proveedor=<?= urlencode($proveedor_id) ?>&tipo=<?= urlencode($tipo) ?>&page=<?= $i ?>">
                          <?= $i ?>
                        </a>
                      </li>
                    <?php
                    endfor; ?>

                    <!-- Botón Siguiente -->
                    <li class="page-item <?= $pagina >= $totalPaginas ? 'disabled' : '' ?>">
                      <a class="page-link"
                        href="?entidad=<?= $entidad_seleccionada ?>&q=<?= urlencode($busqueda) ?>&proveedor=<?= urlencode($proveedor_id) ?>&tipo=<?= urlencode($tipo) ?>&page=<?= $pagina + 1 ?>"
                        aria-label="Next">
                        <span aria-hidden="true">&raquo;</span>
                      </a>
                    </li>

                  </ul>
                </nav>

              <?php
              endif; ?>
            <?php
            else: ?>
              <div class="text-center text-muted py-4">
                <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                <p class="mt-2">No hay <?= strtolower($entidad_config['nombre']) ?> registrados</p>
              </div>
            <?php
            endif; ?>
          </div> <!-- /table-container-wrapper -->
        </div>
      </div>
    </div>
  </div>


  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    let entidadActual = '<?= $entidad_seleccionada ?>';
    const proveedoresOptions = `<?= $proveedoresOptions ?>`;

    // Inicializar al cargar la página
    document.addEventListener('DOMContentLoaded', function() {
      const contenidoPlegable = document.getElementById('contenido-plegable');
      const localEntidad = contenidoPlegable?.getAttribute('data-entidad-actual');

      if (localEntidad && seccionesEstado[localEntidad] !== false) {
        contenidoPlegable?.classList.add('mostrado');
        actualizarIcono(localEntidad, true);
      }
    });

    function seleccionarEntidad(entidad) {
      const url = new URL(window.location);
      url.searchParams.set('entidad', entidad);
      url.searchParams.delete('page');
      url.searchParams.delete('q');
      url.searchParams.delete('proveedor');
      url.searchParams.delete('tipo');

      // Si estamos en AJAX, podemos actualizar el contenedor
      if (typeof updateList === 'function') {
        updateList(url.toString());
        // Actualizar UI de las cards
        document.querySelectorAll('.catalog-card').forEach(c => c.classList.remove('active'));
        document.querySelector(`.catalog-card[data-entidad="${entidad}"]`).classList.add('active');
      } else {
        window.location.href = url.toString();
      }
    }

    function mostrarItem(id) {
      fetch(`details_${entidadActual}.php?id=${id}`)
        .then(res => res.text())
        .then(data => {
          Swal.fire({
            title: `Información del ${document.title.split(' ')[0]}`,
            html: `<div class="swal-info-card">${data}</div>`,
            width: 600,
            showCloseButton: true,
            focusConfirm: false
          });
        })
        .catch(error => {
          console.error('Error:', error);
          Swal.fire('Error', 'No se pudo cargar la información', 'error');
        });
    }

    function agregarItem() {
      let formHtml = '';

      // Formulario dinámico según la entidad
      switch (entidadActual) {
        case 'productos_servicios':
          formHtml = `
                <form id="formAgregarItem">
                    <div class="mb-2">
                        <label class="form-label">Tipo</label>
                        <select class="form-select" name="tipo" required>
                            <option value="producto">Producto</option>
                            <option value="servicio">Servicio</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Nombre</label>
                        <input type="text" class="form-control" name="nombre" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Descripción</label>
                        <textarea class="form-control" name="descripcion"></textarea>
                    </div>
                </form>
            `;
          break;
        case 'proveedores':
          formHtml = `
                <form id="formAgregarItem">
                    <div class="mb-2">
                        <label class="form-label">Razón Social <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="razon_social" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Nombre Comercial</label>
                        <input type="text" class="form-control" name="nombre">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">RFC</label>
                        <input type="text" class="form-control" name="rfc" maxlength="15">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Teléfono</label>
                        <input type="text" class="form-control" name="telefono">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Dirección</label>
                        <textarea class="form-control" name="direccion" rows="3"></textarea>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Persona de Contacto</label>
                        <input type="text" class="form-control" name="contacto">
                    </div>
                </form>
            `;
          break;
        case 'clientes':
          formHtml = `
                <form id="formAgregarItem" class="swal-form">
                    <div class="mb-2">
                        <label class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nombre" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Nombre Abreviado</label>
                        <input type="text" class="form-control" name="nombre_abreviado">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">RFC</label>
                        <input type="text" class="form-control" name="rfc" maxlength="15">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Dirección</label>
                        <textarea class="form-control" name="direccion" rows="3"></textarea>
                    </div>
                </form>
            `;
          break;
        case 'unidades':
          formHtml = `
                <form id="formAgregarItem">
                    <div class="mb-2">
                        <label class="form-label">Nombre</label>
                        <input type="text" class="form-control" name="nombre" required>
                    </div>
                </form>
            `;
          break;
        default:
          formHtml = `
                <form id="formAgregarItem">
                    <div class="mb-2">
                        <label class="form-label">Nombre</label>
                        <input type="text" class="form-control" name="nombre" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Descripción</label>
                        <textarea class="form-control" name="descripcion"></textarea>
                    </div>
                </form>
            `;
      }

      Swal.fire({
        title: `Agregar ${entidadActual.replace('_', ' ').toUpperCase()}`,
        html: formHtml,
        showCancelButton: true,
        confirmButtonText: "Guardar",
        preConfirm: () => {
          const form = document.getElementById("formAgregarItem");
          const formData = new FormData(form);
          return fetch(`insert_${entidadActual}.php`, {
              method: "POST",
              body: formData
            })
            .then(res => res.json())
            .then(data => {
              if (data.status === 'success') {
                Swal.fire("¡Éxito!", data.message, "success")
                  .then(() => location.reload());
              } else {
                Swal.showValidationMessage(data.message || "Error al guardar");
              }
            })
            .catch(error => {
              console.error('Error:', error);
              Swal.showValidationMessage("Error de conexión");
            });
        }
      });
    }

    function editarItem(id) {
      fetch(`edit_${entidadActual}.php?id=${id}`)
        .then(res => res.json())
        .then(resp => {

          if (resp.status !== 'success') {
            Swal.fire('Error', resp.message || 'No se pudo cargar el registro', 'error');
            return;
          }

          const data = resp.data;

          let formHtml = '';

          // Formularios dinámicos según la entidad
          switch (entidadActual) {
            case 'productos_servicios':
              formHtml = `
                        <form id="formEditarItem" class="swal-form">
                            <input type="hidden" name="id" value="${data.id}">
                            <div class="mb-2">
                                <label class="form-label">Tipo</label>
                                <select class="form-select" name="tipo" required>
                                    <option value="producto" ${data.tipo === 'producto' ? 'selected' : ''}>Producto</option>
                                    <option value="servicio" ${data.tipo === 'servicio' ? 'selected' : ''}>Servicio</option>
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Nombre</label>
                                <input type="text" class="form-control" name="nombre" value="${data.nombre || ''}" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Descripción</label>
                                <textarea class="form-control" name="descripcion">${data.descripcion || ''}</textarea>
                            </div>
                        </form>
                    `;
              break;

            case 'proveedores':
              formHtml = `
        <form id="formEditarItem" class="swal-form">
            <input type="hidden" name="id" value="${data.id ?? ''}">

            <div class="mb-2">
                <label class="form-label">Razón Social <span class="text-danger">*</span></label>
                <input type="text" class="form-control"
                       name="razon_social"
                       value="${data.razon_social ?? ''}"
                       required>
            </div>

            <div class="mb-2">
                <label class="form-label">Nombre Comercial</label>
                <input type="text" class="form-control"
                       name="nombre"
                       value="${data.nombre ?? ''}">
            </div>

            <div class="mb-2">
                <label class="form-label">RFC</label>
                <input type="text" class="form-control"
                       name="rfc"
                       value="${data.rfc ?? ''}"
                       maxlength="15">
            </div>

            <div class="mb-2">
                <label class="form-label">Teléfono</label>
                <input type="text" class="form-control"
                       name="telefono"
                       value="${data.telefono ?? ''}">
            </div>

            <div class="mb-2">
                <label class="form-label">Email</label>
                <input type="email" class="form-control"
                       name="email"
                       value="${data.email ?? ''}">
            </div>

            <div class="mb-2">
                <label class="form-label">Dirección</label>
                <textarea class="form-control"
                          name="direccion"
                          rows="3">${data.direccion ?? ''}</textarea>
            </div>

            <div class="mb-2">
                <label class="form-label">Persona de Contacto</label>
                <input type="text" class="form-control"
                       name="contacto"
                       value="${data.contacto ?? ''}">
            </div>
        </form>
    `;
              break;

            case 'unidades':
              formHtml = `
                        <form id="formEditarItem" class="swal-form">
                            <input type="hidden" name="id" value="${data.id}">
                            <div class="mb-2">
                                <label class="form-label">Nombre</label>
                                <input type="text" class="form-control" name="nombre" value="${data.nombre || ''}" required>
                            </div>
                        </form>
                    `;
              break;

            case 'categorias':
              formHtml = `
                        <form id="formEditarItem" class="swal-form">
                            <input type="hidden" name="id" value="${data.id}">
                            <div class="mb-2">
                                <label class="form-label">Nombre</label>
                                <input type="text" class="form-control" name="nombre" value="${data.nombre || ''}" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Descripción</label>
                                <textarea class="form-control" name="descripcion">${data.descripcion || ''}</textarea>
                            </div>
                        </form>
                    `;
              break;

            case 'entidades':
              formHtml = `
                        <form id="formEditarItem" class="swal-form">
                            <input type="hidden" name="id" value="${data.id}">
                            <div class="mb-2">
                                <label class="form-label">Nombre</label>
                                <input type="text" class="form-control" name="nombre" value="${data.nombre || ''}" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Descripción</label>
                                <textarea class="form-control" name="descripcion">${data.descripcion || ''}</textarea>
                            </div>
                        </form>
                    `;
              break;

            case 'clientes':
              formHtml = `
                        <form id="formEditarItem" class="swal-form">
                            <input type="hidden" name="id" value="${data.id}">
                            <div class="mb-2">
                                <label class="form-label">Nombre <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="nombre" value="${data.nombre || ''}" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Nombre Abreviado</label>
                                <input type="text" class="form-control" name="nombre_abreviado" value="${data.nombre_abreviado || ''}">
                            </div>
                            <div class="mb-2">
                                <label class="form-label">RFC</label>
                                <input type="text" class="form-control" name="rfc" value="${data.rfc || ''}" maxlength="15">
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Dirección</label>
                                <textarea class="form-control" name="direccion" rows="3">${data.direccion || ''}</textarea>
                            </div>
                        </form>
                    `;
              break;

            default:
              formHtml = `
                        <form id="formEditarItem" class="swal-form">
                            <input type="hidden" name="id" value="${data.id}">
                            <div class="mb-2">
                                <label class="form-label">Nombre</label>
                                <input type="text" class="form-control" name="nombre" value="${data.nombre || ''}" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Descripción</label>
                                <textarea class="form-control" name="descripcion">${data.descripcion || ''}</textarea>
                            </div>
                        </form>
                    `;
          }

          const configurarSelectProveedor = () => {
            if (entidadActual === 'productos_servicios' && data.proveedor_id) {
              const proveedorSelect = document.querySelector('select[name="proveedor_id"]');
              if (proveedorSelect) {
                proveedorSelect.value = data.proveedor_id;
                console.log('Proveedor seleccionado automáticamente:', data.proveedor_id);
              } else {
                setTimeout(configurarSelectProveedor, 50);
              }
            }
          };

          Swal.fire({
            title: `Editar ${entidadActual.replace('_', ' ').toUpperCase()}`,
            html: formHtml,
            width: 600,
            showCancelButton: true,
            confirmButtonText: "Actualizar",
            cancelButtonText: "Cancelar",
            focusConfirm: false,
            didOpen: () => {
              configurarSelectProveedor();
            },
            preConfirm: () => {
              const form = document.getElementById("formEditarItem");
              const formData = new FormData(form);

              return fetch(`update_${entidadActual}.php`, {
                  method: "POST",
                  body: formData
                })
                .then(res => res.json())
                .then(resp => {
                  if (resp.status === "success") {
                    Swal.fire("¡Éxito!", resp.message, "success")
                      .then(() => location.reload());
                  } else {
                    Swal.showValidationMessage(resp.message || "Error al actualizar");
                  }
                })
                .catch(error => {
                  console.error('Error:', error);
                  Swal.showValidationMessage("Error de conexión: " + error.message);
                });
            }
          });

        })
        .catch(error => {
          console.error('Error:', error);
          Swal.fire('Error', 'No se pudo cargar la información', 'error');
        });
    }

    function eliminarItem(id) {
      Swal.fire({
        title: '¿Seguro que deseas eliminar este registro?',
        text: "Esta acción no se puede deshacer",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#525252',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
      }).then((result) => {
        if (result.isConfirmed) {
          fetch(`delete_${entidadActual}.php?id=${id}`)
            .then(res => res.json())
            .then(data => {
              if (data.status === 'success') {
                Swal.fire('Eliminado!', data.message, 'success')
                  .then(() => location.reload());
              } else {
                Swal.fire('Error', data.message, 'error');
              }
            })
            .catch(error => {
              console.error('Error:', error);
              Swal.fire('Error', 'Error de conexión', 'error');
            });
        }
      });
    }
  </script>

  <script>
    function evaluarProveedor(id) {
      window.location.href = `evaluacion_proveedor.php?id=${id}`;
    }
  </script>

  <?php
  include __DIR__ . "/../includes/footer.php"; ?>

</body>
<script>
  // Lógica AJAX para list_catalog
  function initAJAX() {
    const searchForm = document.getElementById('search-form');
    const filterForm = document.getElementById('filter-form');
    const container = document.getElementById('table-container-wrapper');

    if (!searchForm || !container) return;

    window.updateList = function(url, pushState = true) {
      container.style.opacity = '0.5';
      container.style.pointerEvents = 'none';

      fetch(url)
        .then(response => response.text())
        .then(html => {
          const parser = new DOMParser();
          const doc = parser.parseFromString(html, 'text/html');
          const newContent = doc.getElementById('table-container-wrapper');

          if (newContent) {
            container.innerHTML = newContent.innerHTML;
          }

          const newEntidadContainer = doc.getElementById('contenido-lista');
          if (newEntidadContainer) {
            entidadActual = newEntidadContainer.getAttribute('data-entidad-actual');
          }

          const newSearch = doc.getElementById('search-form');
          const newFilter = doc.getElementById('filter-form');

          if (newSearch) syncForm(searchForm, newSearch);
          if (newFilter && filterForm) syncForm(filterForm, newFilter);

          container.style.opacity = '1';
          container.style.pointerEvents = 'auto';

          if (pushState) window.history.pushState({}, '', url);
        })
        .catch(err => {
          console.error('Error:', err);
          container.style.opacity = '1';
          container.style.pointerEvents = 'auto';
        });
    }

    function syncForm(current, source) {
      source.querySelectorAll('input, select').forEach(input => {
        const target = current.querySelector(`[name="${input.name}"]`);
        if (target) target.value = input.value;
      });
    }

    document.addEventListener('click', function(e) {
      const pageLink = e.target.closest('.page-link');
      if (pageLink) {
        e.preventDefault();
        updateList(pageLink.href);
        window.scrollTo({
          top: 0,
          behavior: 'smooth'
        });
      }
    });

    [searchForm, filterForm].forEach(form => {
      if (!form) return;
      form.addEventListener('submit', function(e) {
        e.preventDefault();
        const params = new URLSearchParams(new FormData(filterForm));
        const searchData = new FormData(searchForm);
        params.set('q', searchData.get('q') || "");

        params.set('page', '1');
        updateList('?' + params.toString());
      });
    });

    if (filterForm) {
      filterForm.querySelectorAll('select').forEach(select => {
        select.addEventListener('change', () => filterForm.requestSubmit());
      });
    }
  }
  document.addEventListener('DOMContentLoaded', initAJAX);
</script>

<script src="<?= BASE_URL ?>/assets/scripts/session_timeout.js"></script>
</body>

</html>

