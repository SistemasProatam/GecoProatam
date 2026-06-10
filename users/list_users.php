<?php
// Incluir el gestor de sesiones UNA sola vez
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

// Verificar sesión y prevenir caching
checkSession();
preventCaching();

require_once __DIR__ . "/../conexion.php";

// ==== Filtros ====
$busqueda = $_GET['q'] ?? '';
$departamento_id = $_GET['departamento'] ?? '';

$pagina = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$por_pagina = 10;
$offset = ($pagina - 1) * $por_pagina;

// ====== Query base ======
$sqlBase = "FROM usuarios u
            LEFT JOIN departamentos d ON u.departamento_id = d.id
            WHERE 1=1";

$params = [];
$types = "";

// Busqueda
if (!empty($busqueda)) {
  $sqlBase .= " AND (u.nombres LIKE ? OR u.apellidos LIKE ? OR u.correo_corporativo LIKE ?)";
  $like = "%$busqueda%";
  $params[] = $like;
  $params[] = $like;
  $params[] = $like;
  $types .= "sss";
}

// Filtro departamento
if (!empty($departamento_id)) {
  $sqlBase .= " AND u.departamento_id = ?";
  $params[] = $departamento_id;
  $types .= "i";
}

// ====== Total registros ======
$stmtTotal = $conn->prepare("SELECT COUNT(*) AS total $sqlBase");
if ($types) $stmtTotal->bind_param($types, ...$params);
$stmtTotal->execute();
$totalRegistros = $stmtTotal->get_result()->fetch_assoc()['total'] ?? 0;

// ====== Datos paginados ======
$stmt = $conn->prepare("SELECT u.id, u.nombres, u.apellidos, u.correo_corporativo, u.foto_jpg, d.nombre AS departamento
                        $sqlBase
                        ORDER BY u.nombres ASC
                        LIMIT ? OFFSET ?");
$paramsPag = $params;
$typesPag = $types . "ii";
$paramsPag[] = $por_pagina;
$paramsPag[] = $offset;
$stmt->bind_param($typesPag, ...$paramsPag);
$stmt->execute();
$result = $stmt->get_result();

// ====== Departamentos para filtros ======
$departamentos = $conn->query("SELECT id, nombre FROM departamentos ORDER BY nombre ASC");
$departamentosOptions = "";
while ($dep = $departamentos->fetch_assoc()) {
  $selected = $departamento_id == $dep['id'] ? "selected" : "";
  $departamentosOptions .= "<option value='{$dep['id']}' $selected>{$dep['nombre']}</option>";
}

// Total páginas
$totalPaginas = ceil($totalRegistros / $por_pagina);
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/core/modules.css?v=2.0">
<?php include __DIR__ . "/../includes/navbar.php"; ?>

<div class="orders-page-container">

  <!-- Page Header -->
  <div class="orders-page-header mb-4">
    <div class="orders-page-header-info">
      <nav class="orders-breadcrumb">
        <a href="<?= BASE_URL ?>/index.php">Inicio</a>
        <span class="separator">›</span>
        <span>Usuarios</span>
      </nav>
      <h1 class="orders-page-title">Usuarios</h1>
    </div>
    <a href="add_user.php" class="btn-geco-primary">
      <i class="fa-solid fa-plus"></i> Agregar
    </a>
  </div>

  <!-- ===== CARD 1: FILTROS ===== -->
  <div class="orders-card mb-3">
    <!-- Filter Bar -->
    <form id="filter-search-form" method="GET">
      <div class="orders-filter-bar" style="margin-bottom: 0;">

        <!-- Left: Search -->
        <div class="orders-filter-search" style="margin-right: auto; flex: 1; max-width: 400px;">
          <div class="search-input-wrap">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" name="q" placeholder="Buscar usuario..." value="<?= htmlspecialchars($busqueda) ?>">
          </div>
        </div>

        <!-- Right: Filters (Selects) -->
        <div class="orders-filter-selects">
          <select name="departamento" class="form-select" style="min-width: 250px;">
            <option value="">-- Todos los departamentos --</option>
            <?= $departamentosOptions ?>
          </select>
        </div>

      </div>
    </form>
  </div>

  <!-- ===== CARD 2: TABLA ===== -->
  <div class="orders-card">
    <!-- Table Wrapper -->
    <div id="table-container-wrapper">

      <?php if ($result && $result->num_rows > 0): ?>
        <div class="orders-table-wrap">
          <table class="orders-table">
            <thead>
              <tr>
                <th>Usuario</th>
                <th>Correo Corporativo</th>
                <th>Departamento</th>
                <th class="text-end">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                  <td>
                    <div class="d-flex align-items-center gap-3">
                      <?php if (!empty($row['foto_jpg'])): ?>
                        <img src="../uploads/usuarios/<?= htmlspecialchars($row['foto_jpg']) ?>" alt="Foto" class="rounded-circle" style="width: 38px; height: 38px; object-fit: cover; border: 1px solid var(--gray-200,#e5e7eb);">
                      <?php else: ?>
                        <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold" style="width: 38px; height: 38px; background: var(--gray-600, #4b5563); font-size: 0.82rem; letter-spacing: 0.5px;">
                          <?= getInitials($row['nombres'], $row['apellidos']) ?>
                        </div>
                      <?php endif; ?>
                      <div>
                        <span class="fw-bold text-dark d-block"><?= htmlspecialchars($row['nombres'] . ' ' . $row['apellidos']) ?></span>
                      </div>
                    </div>
                  </td>
                  <td>
                    <span style="font-size: 0.82rem; font-family: inherit; color: var(--gray-500,#6b7280);"><?= htmlspecialchars($row['correo_corporativo']) ?></span>
                  </td>
                  <td>
                    <span class="fw-semibold text-secondary" style="font-size: 0.85rem;"><?= htmlspecialchars($row['departamento'] ?? "Sin departamento") ?></span>
                  </td>
                  <td>
                    <div class="actions-group justify-content-end">
                      <a href="details_user.php?id=<?= $row['id'] ?>" class="btn-action btn-action--view">
                        <i class="fa-regular fa-eye"></i>
                      </a>
                      <a href="edit_user.php?id=<?= $row['id'] ?>" class="btn-action btn-action--edit">
                        <i class="fa-solid fa-pen-to-square"></i>
                      </a>
                      <button class="btn-action btn-action--delete" onclick="eliminarUsuario(<?= $row['id'] ?>)">
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
        <div class="orders-pagination-bar mt-3">
          <div class="orders-pagination-left">
            <span class="orders-pagination-info">
              <?php
              $inicio_registro = $totalRegistros > 0 ? $offset + 1 : 0;
              $fin_registro = min($offset + $por_pagina, $totalRegistros);
              ?>
              Mostrando <strong><?= $inicio_registro ?>-<?= $fin_registro ?></strong> de <strong><?= $totalRegistros ?></strong> usuarios registrados
            </span>
          </div>
          <div class="orders-pagination-controls">
            <?php if ($totalPaginas > 1): ?>
              <nav class="orders-pagination-nav" aria-label="Paginación">
                <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                  <a href="?q=<?= urlencode($busqueda) ?>&departamento=<?= urlencode($departamento_id) ?>&page=<?= $i ?>"
                    class="page-btn <?= $i == $pagina ? 'active' : '' ?>">
                    <?= $i ?>
                  </a>
                <?php endfor; ?>
              </nav>
            <?php endif; ?>
          </div>
        </div>

      <?php else: ?>
        <div class="orders-empty-state">
          <i class="fa-solid fa-users"></i>
          <p>No se encontraron usuarios registrados. Prueba ajustando los filtros o criterios de búsqueda.</p>
        </div>
      <?php endif; ?>
    </div> <!-- /table-container-wrapper -->
  </div> <!-- /orders-card -->
</div> <!-- /orders-page-container -->


<script>
  function eliminarUsuario(id) {
    UI.confirm({
      title: '¿Eliminar este usuario?',
      message: 'Esta acción no se puede deshacer.',
      danger: true,
      confirmText: 'Sí, eliminar',
    }).then(ok => {
      if (!ok) return;
      fetch(`delete_user.php?id=${id}`)
        .then(res => {
          if (!res.ok) throw new Error('Error en la respuesta del servidor: ' + res.status);
          return res.json();
        })
        .then(data => {
          if (data.status === 'success') {
            UI.toast.success(data.message);
            setTimeout(() => {
              if (typeof updateList === 'function') updateList(window.location.href);
              else location.reload();
            }, 1200);
          } else {
            UI.modal({
              title: 'No se puede eliminar',
              icon: 'error',
              html: `<p><strong>Razón:</strong> ${data.message}</p>
                   <p class="text-muted small"><i class="fa-solid fa-circle-info"></i>
                   Para eliminar este usuario, primero debe eliminar o reasignar los registros relacionados.</p>`,
            });
          }
        })
        .catch(error => {
          console.error('Error:', error);
          UI.toast.error(`No se pudo conectar con el servidor. Detalle: ${error.message}`);
        });
    });
  }

  // Función para actualizar la lista vía AJAX
  function initAJAX() {
    const form = document.getElementById('filter-search-form');
    const container = document.getElementById('table-container-wrapper');

    if (!form || !container) return;

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

          const newForm = doc.getElementById('filter-search-form');
          if (newForm) syncForm(form, newForm);

          container.style.opacity = '1';
          container.style.pointerEvents = 'auto';

          if (pushState) window.history.pushState({}, '', url);

          // Reinicializar tooltips
          var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
          tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
          });
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
      const pageLink = e.target.closest('.page-btn');
      if (pageLink) {
        e.preventDefault();
        updateList(pageLink.href);
        window.scrollTo({
          top: 0,
          behavior: 'smooth'
        });
      }
    });

    form.addEventListener('submit', function(e) {
      e.preventDefault();
      const params = new URLSearchParams(new FormData(form));
      params.set('page', '1');
      updateList('?' + params.toString());
    });

    form.querySelectorAll('select').forEach(select => {
      select.addEventListener('change', () => form.requestSubmit());
    });

    // Búsqueda en tiempo real con debounce
    let timeout = null;
    const searchInput = form.querySelector('input[name="q"]');
    if (searchInput) {
      searchInput.addEventListener('input', () => {
        clearTimeout(timeout);
        timeout = setTimeout(() => {
          form.requestSubmit();
        }, 400);
      });
    }
  }

  document.addEventListener('DOMContentLoaded', initAJAX);
</script>

<?php include __DIR__ . "/../includes/footer.php"; ?>