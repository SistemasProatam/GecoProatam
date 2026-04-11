<?php
require_once __DIR__ . '/../config.php';

// Incluir el gestor de sesiones UNA sola vez
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

// Verificar sesión y prevenir caching
checkSession();
preventCaching();

// Obtener departamento del usuario en sesión
$departamento_sesion = $_SESSION['departamento'] ?? 'Sin departamento';
?>

<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
    rel="stylesheet"
    integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
    crossorigin="anonymous" />
  <link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/navbar.css" />

</head>

<body>
  <nav class="navbar navbar-expand-lg ">
    <div class="container-fluid d-flex align-items-center">
      <button
        class="uiverse-btn"
        type="button"
        data-bs-toggle="offcanvas"
        data-bs-target="#staticBackdrop"
        aria-controls="staticBackdrop">
        <i class="bi bi-list menu-icon">
          <span class="menu_text"><b>Menú</b></span>
        </i>
      </button>

      <!-- Logo -->
      <a class="navbar-brand" href="<?= BASE_URL ?>/index.php">
        <img
          src="<?= BASE_URL ?>/assets/img/proatam.png"
          alt="Logo PROATAM"
          width="200"
          height="auto"
          class="d-inline-block align-text-top" />
      </a>
    </div>
  </nav>

  <!-- Offcanvas -->
  <div
    class="offcanvas offcanvas-start"
    data-bs-backdrop="static"
    tabindex="-1"
    id="staticBackdrop"
    aria-labelledby="staticBackdropLabel">
    <div class="offcanvas-header">
      <img
        src="<?= BASE_URL ?>/assets/img/logo_proat.png"
        alt="Logo PROATAM"
        width="20"
        height="auto"
        class="d-inline-block align-text-top" />
      <h5 class="offcanvas-title mx-2" id="staticBackdropLabel"> Menú</h5>
      <button
        type="button"
        class="btn-close"
        data-bs-dismiss="offcanvas"
        aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
      <ul class="menu-list">

        <!-- DEPARTAMENTO EN SESIÓN -->
        <p class="dept-label"><?php
        echo htmlspecialchars($departamento_sesion); ?></p>
        <div class="separator"></div>

        <!-- ACTIVOS - Elemento simple -->
<?php
$ver_activos = in_array($departamento_sesion, [
  'Director General',
  'Subdirector General',
  'Gerente de Operaciones',
  'Coordinador de Control de Documentos y Facturación',
  'Gerente de Seguridad Salud y Medio Ambiente',
  'Tecnico de Sistemas',
  'Procura'
]);
?>

<?php
 if ($ver_activos): ?>
  <a href="<?= BASE_URL ?>/activos/list_activos.php" class="text-decoration-none">
    <li class="simple-menu-item">
      <i class="bi bi-clipboard2-check"></i>
      <p class="menu-label">Activos</p>
    </li>
  </a>
<?php
 endif; ?>

<!-- CATÁLOGO - Elemento simple -->
<?php
$ver_catalogo = in_array($departamento_sesion, [
  'Director General',
  'Subdirector General',
  'Gerente de Operaciones',
  'Supervisor de Proyecto',
  'Tecnico de Sistemas',
  'Procura'
]);
?>
<?php
 if ($ver_catalogo): ?>
  <a href="<?= BASE_URL ?>/catalog/list_catalog.php" class="text-decoration-none">
    <li class="simple-menu-item">
      <i class="bi bi-grid"></i>
      <p class="menu-label">Catálogo</p>
    </li>
  </a>
<?php
 endif; ?>

<!-- PROYECTOS - Con submenu -->
<?php

$ver_proyectos = in_array($departamento_sesion, [
  'Director General',
  'Subdirector General',
  'Gerente de Operaciones',
  'Supervisor de Proyecto',
  'Tecnico de Sistemas',
  'Procura'
]);
?>
<?php
 if ($ver_proyectos): ?>
  <li class="menu-item" id="ProyectosMenu">
    <button class="menu-header" onclick="toggleSubmenu('ProyectosMenu')">
      <i class="bi bi-building-gear"></i>
      <p class="menu-label">Control de Proyectos</p>
      <i class="bi bi-chevron-down expand-arrow"></i>
    </button>
    <div class="submenu">
      <?php

      $ver_plan_obra = in_array($departamento_sesion, [
        'Director General',
        'Subdirector General',
        'Gerente de Operaciones',
        'Tecnico de Sistemas',
        'Procura'
      ]);
      ?>
      <?php
 if ($ver_plan_obra): ?>
        <a href="<?= BASE_URL ?>/projects/plan_obra.php" class="submenu-item">
          <i class="bi bi-calendar4-range"></i> Plan de Obra
        </a>
      <?php
 endif; ?>
      <a href="<?= BASE_URL ?>/projects/list_project.php" class="submenu-item">
        <i class="bi bi-building"></i> Ver Proyectos
      </a>
    </div>
  </li>
<?php
 endif; ?>

<!-- COTIZACIONES -->
        <?php
        $ver_cotizaciones = in_array($dep_id_sesion, [
        'Director General',
        'Subdirector General',
        'Gerente de Operaciones',
        'Supervisor de Proyecto',
        'Tecnico de Sistemas',
        'Coordinador de Control de Documentos y Facturación',
        'Gerente de Seguridad Salud y Medio Ambiente',
        'Procura',
        'Supervisor del sistema de Administración',
        'Supervisor de Calidad',
        'Residente de Obra'
        ]);
        ?>
        <?php if ($ver_cotizaciones): ?>
          <li class="menu-item" id="cotizMenu">
            <button class="menu-header" onclick="toggleSubmenu('cotizMenu')">
              <i class="bi bi-file-earmark-text"></i>
              <p class="menu-label">Cotizaciones</p>
              <i class="bi bi-chevron-down expand-arrow"></i>
            </button>
            <div class="submenu">
              <a href="<?= BASE_URL ?>/cotizaciones/cotizacion.php" class="submenu-item">
                <i class="bi bi-plus-circle"></i> Nueva Cotización
              </a>
              <a href="<?= BASE_URL ?>/cotizaciones/list_cotizaciones.php" class="submenu-item">
                <i class="bi bi-list-ul"></i> Ver Registros
              </a>
            </div>
          </li>
        <?php endif; ?>


<!-- ÓRDENES DE COMPRA - Con submenu -->
<?php

$ver_ordenes = in_array($departamento_sesion, [
  'Director General',
  'Subdirector General',
  'Gerente de Operaciones',
  'Supervisor de Proyecto',
  'Tecnico de Sistemas',
  'Gerente de Recursos Humanos',
  'Procura'
]);
?>

<?php
 if ($ver_ordenes): ?>
  <li class="menu-item" id="ordenesMenu">
    <button class="menu-header" onclick="toggleSubmenu('ordenesMenu')">
      <i class="bi bi-journal-bookmark"></i>
      <p class="menu-label">Órdenes de compra</p>
      <i class="bi bi-chevron-down expand-arrow"></i>
    </button>
    <div class="submenu">
      <?php
      $crear_orden = in_array($departamento_sesion, [
        'Director General',
        'Subdirector General',
        'Gerente de Operaciones',
        'Supervisor de Proyecto',
        'Tecnico de Sistemas',
        'Procura'
      ]);
      ?>
      <?php
 if ($crear_orden): ?>
        <a href="<?= BASE_URL ?>/orders/new_order.php" class="submenu-item">
          <i class="bi bi-plus-circle"></i> Crear Nueva Orden
        </a>
      <?php
 endif; ?>
      <a href="<?= BASE_URL ?>/orders/list_oc.php" class="submenu-item">
        <i class="bi bi-list-ul"></i> Ver Registros
      </a>
    </div>
  </li>
<?php
 endif; ?>

<!-- REQUISICIONES - Con submenu -->
<?php
$ver_requisiciones = in_array($departamento_sesion, [
  'Director General',
  'Subdirector General',
  'Gerente de Operaciones',
  'Supervisor de Proyecto',
  'Tecnico de Sistemas',
  'Coordinador de Control de Documentos y Facturación',
  'Gerente de Seguridad Salud y Medio Ambiente',
  'Procura',
  'Supervisor del sistema de Administración',
  'Supervisor de Calidad',
  'Residente de Obra'
]);
?>
<?php
 if ($ver_requisiciones): ?>
  <li class="menu-item" id="requisMenu">
    <button class="menu-header" onclick="toggleSubmenu('requisMenu')">
      <i class="bi bi-journal-arrow-up"></i>
      <p class="menu-label">Requisiciones</p>
      <i class="bi bi-chevron-down expand-arrow"></i>
    </button>
    <div class="submenu">
      <?php
      $crear_requisicion = in_array($departamento_sesion, [
        'Director General',
        'Subdirector General',
        'Gerente de Operaciones',
        'Supervisor de Proyecto',
        'Tecnico de Sistemas',
        'Coordinador de Control de Documentos y Facturación',
        'Gerente de Seguridad Salud y Medio Ambiente',
        'Procura',
        'Supervisor del sistema de Administración',
        'Supervisor de Calidad',
        'Residente de Obra'
      ]);
      ?>
      <?php
 if ($crear_requisicion): ?>
        <a href="<?= BASE_URL ?>/orders/new_requis.php" class="submenu-item">
          <i class="bi bi-plus-circle"></i> Crear Requisición
        </a>
      <?php
 endif; ?>
      <a href="<?= BASE_URL ?>/orders/list_requis.php" class="submenu-item">
        <i class="bi bi-list-ul"></i> Ver Registros
      </a>
    </div>
  </li>
<?php
 endif; ?>
        </ul>

<div class="separator"></div>

<!-- ADMINISTRACIÓN DE USUARIOS - Solo para autorizados -->
<?php
$ver_admin_usuarios = in_array($departamento_sesion, [
  'Director General',
  'Subdirector General',
  'Gerente de Recursos Humanos',
  'Tecnico de Sistemas'
]);
?>
<?php
 if ($ver_admin_usuarios): ?>
  <ul class="menu-list">
    <a href="<?= BASE_URL ?>/users/list_users.php" class="text-decoration-none">
      <li class="simple-menu-item">
        <i class="bi bi-person-vcard"></i>
        <p class="menu-label">Administrar Usuarios</p>
      </li>
    </a>
<?php
 endif; ?>

<!-- Solicitud de mantenimiento - Elemento simple -->
<a href="<?= BASE_URL ?>/solicitud_soporte.php" class="text-decoration-none">
  <li class="simple-menu-item">
    <i class="bi bi-person-raised-hand"></i>
    <p class="menu-label">Solicitud de Mantenimiento</p>
  </li>
</a>

<!-- Dashboard -->
<?php
$ver_dash = in_array($departamento_sesion, [
  'Director General',
  'Subdirector General',
  'Gerente de Operaciones',
  'Tecnico de Sistemas'
]);
?>
<?php
 if ($ver_dash): ?>
  <a href="<?= BASE_URL ?>/dashboard.php" class="text-decoration-none">
    <li class="simple-menu-item">
      <i class="bi bi-bar-chart-line"></i>
      <p class="menu-label">Panel de Control</p>
    </li>
  </a>
<?php
 endif; ?>
</ul>

<div class="separator"></div>

      <ul class="menu-list">
        <a href="<?= BASE_URL ?>/logout.php" class="text-decoration-none">
          <li class="simple-menu-item logout">
            <i class="bi bi-box-arrow-left"></i>
            <p class="menu-label">Cerrar Sesión</p>
          </li>
        </a>
      </ul>
    </div>
  </div>

  <script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
    crossorigin="anonymous"></script>

  <script>
    function toggleSubmenu(menuId) {
      const menuItem = document.getElementById(menuId);
      const isExpanded = menuItem.classList.contains("expanded");

      // Cerrar todos los submenus
      document.querySelectorAll(".menu-item").forEach((item) => {
        item.classList.remove("expanded");
      });

      // Si no estaba expandido, expandir este
      if (!isExpanded) {
        menuItem.classList.add("expanded");
      }
    }

    // Cerrar submenus al hacer click fuera
    document.addEventListener("click", function(event) {
      if (!event.target.closest(".menu-item")) {
        document.querySelectorAll(".menu-item").forEach((item) => {
          item.classList.remove("expanded");
        });
      }
    });
  </script>

  <!-- Session Timeout Script -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="<?= BASE_URL ?>/assets/scripts/session_timeout.js"></script>

</body>

</html>

