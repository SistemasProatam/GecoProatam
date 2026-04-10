<?php
require_once __DIR__ . '/../config.php';

// Incluir el gestor de sesiones UNA sola vez
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

// Verificar sesión y prevenir caching
checkSession();
preventCaching();

include(__DIR__ . "/../conexion.php");

// ==== Filtros ====
$busqueda = $_GET['q'] ?? '';
$departamento_id = $_GET['departamento'] ?? '';

$pagina = isset($_GET['page']) ? max(1,intval($_GET['page'])) : 1;
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
$stmt = $conn->prepare("SELECT u.id, u.nombres, u.apellidos, u.correo_corporativo, d.nombre AS departamento
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
while($dep = $departamentos->fetch_assoc()){
    $selected = $departamento_id == $dep['id'] ? "selected" : "";
    $departamentosOptions .= "<option value='{$dep['id']}' $selected>{$dep['nombre']}</option>";
}

// Total páginas
$totalPaginas = ceil($totalRegistros / $por_pagina);
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Usuarios</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/list.css">
  <link rel="icon" href="<?= BASE_URL ?>/assets/img/LogoCuadro.ico" type="image/x-icon">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<?php include __DIR__ . "/../includes/navbar.php"; ?>

<!-- HERO SECTION -->
<div class="hero-section">
  <div class="container hero-content">
    <div class="breadcrumb-custom">
        <a href="<?= BASE_URL ?>/index.php"><i class="bi bi-house-door"></i> Inicio</a>
      <span>/</span>
      <a href="list_project.php"> Registro de Usuarios</a>
    </div>
    
    <div class="row align-items-end">
      <div class="col-lg-8">
        <h1 class="hero-title">Registro de Usuarios</h1>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- MAIN CONTENT -->
<div class="content-wrapper">
  
<div class="form-container">

    <div class="form-body">
  <!-- Buscador -->
  <form class="form-search d-flex justify-content-center w-100 mb-4" method="GET">
        <input class="form-control w-100" type="search" name="q" placeholder="Buscar usuario..." value="<?= htmlspecialchars($busqueda) ?>">
        <button class="btn btn-outline-success" type="submit"> <i class="bi bi-search"></i> </button>
      </form>

      <form method="GET" class="d-flex flex-wrap align-items-center gap-2 mb-4">
    <!-- Filtro por departamento -->
  <div style="flex: 0 0 auto; min-width: 150px;">
    <select name="departamento" class="form-select">
      <option value="">-- Todos los departamentos --</option>
      <?= $departamentosOptions ?>
    </select>
  </div>

  <!-- Botón de filtrar -->
  <div style="flex: 0 0 auto;">
    <button type="submit" class="btn btn-success">
      <i class="bi bi-funnel"></i> Filtrar
    </button>
  </div>
</form>

  <!-- Botón de agregar usuario -->
  <div class="d-flex justify-content-between mb-3">
        <span class="badge-num"><?= $totalRegistros ?> usuarios</span>
        <a href="add_user.php" class="button-56" style="text-decoration: none;">
          <i class="bi bi-plus-circle"></i> Agregar
        </a>
      </div>

      <!-- Lista -->
      <?php if($result && $result->num_rows>0): ?>
      <ul class="list-group">
        <?php while($row = $result->fetch_assoc()): ?>
        <li class="list-group-item d-flex justify-content-between align-items-center text-nowrap">
          <div>
            <strong><?= htmlspecialchars($row['nombres'].' '.$row['apellidos']) ?></strong>
            <br>
            <small class="text-muted">
              <?= $row['departamento'] ?? "Sin departamento" ?>
            </small>
          </div>
          <div class="btn-group">
            <a href="details_user.php?id=<?= $row['id'] ?>" class="btn-inf" 
              data-bs-toggle="tooltip" data-bs-placement="top" title="Ver Detalles del Usuario">
              <i class="bi bi-info-circle"></i>
            </a>
            <a href="edit_user.php?id=<?= $row['id'] ?>" class="btn-ed"
              data-bs-toggle="tooltip" data-bs-placement="top" title="Editar Usuario">
              <i class="bi bi-pencil"></i>
              </a>
            <button class="btn-del" onclick="eliminarUsuario(<?= $row['id'] ?>)">
              <i class="bi bi-trash3"></i>
            </button>
          </div>

        </li>
        <?php endwhile; ?>
      </ul>

      <!-- Paginación -->
      <?php if($totalPaginas>1): ?>
      <nav aria-label="Paginación">
        <ul class="pagination justify-content-center mt-3">
          <?php for($i=1;$i<=$totalPaginas;$i++): ?>
          <li class="page-item <?= $i==$pagina?'active':'' ?>">
            <a class="page-link" href="?q=<?= urlencode($busqueda) ?>&departamento=<?= urlencode($departamento_id) ?>&page=<?= $i ?>">
              <?= $i ?>
            </a>
          </li>
          <?php endfor; ?>
        </ul>
      </nav>
      <?php endif; ?>
      <?php else: ?>
       <tr>
          <td colspan="9" class="text-center text-muted py-4">
            <i class="bi bi-inbox" style="font-size: 3rem;"></i>
            <p class="mt-2">No hay usuarios registrados</p>
          </td>
        </tr>
      <?php endif; ?>
    </div>
  </div>
</div>
      </div>

<?php include __DIR__ . "/../includes/footer.php"; ?>

<script>
function eliminarUsuario(id) {
  Swal.fire({
    title: '¿Seguro que deseas eliminar este usuario?',
    text: "Esta acción no se puede deshacer",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#525252',
    confirmButtonText: 'Sí, eliminar',
    cancelButtonText: 'Cancelar'
  }).then((result) => {
    if(result.isConfirmed){
      fetch(`delete_user.php?id=${id}`)
        .then(res => {
          if (!res.ok) {
            throw new Error('Error en la respuesta del servidor: ' + res.status);
          }
          return res.json();
        })
        .then(data => {
          if(data.status === 'success'){
            Swal.fire({
              icon: 'success',
              title: '¡Eliminado!',
              text: data.message,
              confirmButtonText: 'Aceptar'
            }).then(() => location.reload());
          } else {
            Swal.fire({
              icon: 'error',
              title: 'No se puede eliminar',
              html: `<div style="text-align: left;">
                       <p><strong>Razón:</strong> ${data.message}</p>
                       <hr>
                       <small class="text-muted">
                         <i class="bi bi-info-circle"></i> 
                         Para eliminar este usuario, primero debe eliminar o reasignar los registros relacionados.
                       </small>
                     </div>`,
              confirmButtonText: 'Entendido'
            });
          }
        })
        .catch(error => {
          console.error('Error:', error);
          Swal.fire({
            icon: 'error',
            title: 'Error de conexión',
            html: `No se pudo conectar con el servidor.<br>
                   <small>Detalle: ${error.message}</small>`,
            confirmButtonText: 'Entendido'
          });
        });
    }
  });
}
</script>

<script src="<?= BASE_URL ?>/assets/scripts/session_timeout.js"></script>

</body>
</html>

