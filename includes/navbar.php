<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

checkSession();
preventCaching();

$departamento_sesion = $_SESSION['departamento'] ?? 'Sin departamento';
$nombre_usuario = $_SESSION['nombres'] ?? 'Usuario';
$primerNombre = explode(' ', trim($nombre_usuario))[0] ?? 'U';
$inicial = strtoupper(substr($primerNombre, 0, 1));
$rol_usuario = $_SESSION['departamento'] ?? 'General';

// --- PERMISOS ---
$ver_activos = in_array($departamento_sesion, ['Director General', 'Subdirector General', 'Gerente de Operaciones', 'Coordinador de Control de Documentos y Facturación', 'Gerente de Seguridad Salud y Medio Ambiente', 'Tecnico de Sistemas', 'Procura']);
$ver_catalogo = in_array($departamento_sesion, ['Director General', 'Subdirector General', 'Gerente de Operaciones', 'Supervisor de Proyecto', 'Tecnico de Sistemas', 'Procura']);
$ver_proyectos = in_array($departamento_sesion, ['Director General', 'Subdirector General', 'Gerente de Operaciones', 'Supervisor de Proyecto', 'Tecnico de Sistemas', 'Procura']);
$ver_plan_obra = in_array($departamento_sesion, ['Director General', 'Subdirector General', 'Gerente de Operaciones', 'Tecnico de Sistemas', 'Procura']);
$ver_ordenes = in_array($departamento_sesion, ['Director General', 'Subdirector General', 'Gerente de Operaciones', 'Supervisor de Proyecto', 'Tecnico de Sistemas', 'Gerente de Recursos Humanos', 'Procura']);
$crear_orden = in_array($departamento_sesion, ['Director General', 'Subdirector General', 'Gerente de Operaciones', 'Supervisor de Proyecto', 'Tecnico de Sistemas', 'Procura']);
$ver_requisiciones = in_array($departamento_sesion, ['Director General', 'Subdirector General', 'Gerente de Operaciones', 'Supervisor de Proyecto', 'Tecnico de Sistemas', 'Coordinador de Control de Documentos y Facturación', 'Gerente de Seguridad Salud y Medio Ambiente', 'Procura', 'Supervisor del sistema de Administración', 'Supervisor de Calidad', 'Residente de Obra']);
$ver_admin_usuarios = in_array($departamento_sesion, ['Director General', 'Subdirector General', 'Gerente de Recursos Humanos', 'Tecnico de Sistemas']);
$ver_dash = in_array($departamento_sesion, ['Director General', 'Subdirector General', 'Gerente de Operaciones', 'Tecnico de Sistemas']);

function is_active($path) {
    $current_file = basename($_SERVER['PHP_SELF']);
    if (is_array($path)) {
        return in_array($current_file, $path) ? 'active' : '';
    }
    return $current_file === $path ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" />
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet" />
  
  <!-- Estilos SaaS -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/core/tokens.css?v=1.2" />
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/core/layout.css?v=1.2" />
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/ui.css?v=1.2" />
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/list.css?v=1.2" />
  
  <script>window.BASE_URL = '<?= BASE_URL ?>';</script>
</head>

<body>
  <div class="app-layout">
    
    <!-- ─── SIDEBAR ──────────────────────────────────────────────────────────── -->
    <aside class="app-sidebar" id="appSidebar">
        <div class="sidebar-brand">
            <button class="desktop-toggle btn-icon" onclick="toggleSidebar()">
                <span class="material-symbols-rounded">menu</span>
            </button>
            <a href="<?= BASE_URL ?>/index.php" class="brand-logo-link">
                <img src="<?= BASE_URL ?>/assets/img/proatam.png" alt="Logo">
            </a>
        </div>

        <nav class="sidebar-menu">
            <!-- INICIO -->
            <a href="<?= BASE_URL ?>/index.php" class="menu-item <?= is_active('index.php') ?>" title="Inicio">
                <span class="material-symbols-rounded">home</span>
                <span class="menu-text">Inicio</span>
            </a>

            <!-- OPERACIÓN -->
            <div class="sidebar-label">Operación</div>
            
            <?php if ($ver_proyectos): ?>
            <div class="has-submenu <?= is_active(['list_project.php', 'plan_obra.php', 'add_project.php']) ? 'expanded' : '' ?>" id="menuProyectos">
                <a href="#" class="menu-item <?= is_active(['list_project.php', 'plan_obra.php', 'add_project.php']) ?>" onclick="toggleSubmenu(event, 'menuProyectos')" title="Proyectos">
                    <span class="material-symbols-rounded">business_center</span>
                    <span class="menu-text">Proyectos</span>
                    <span class="material-symbols-rounded chevron" style="margin-left:auto;font-size:1.1rem;">expand_more</span>
                </a>
                <div class="submenu-container">
                    <?php if ($ver_plan_obra): ?>
                    <a href="<?= BASE_URL ?>/projects/plan_obra.php" class="submenu-item <?= is_active('plan_obra.php') ?>">Plan de Obra</a>
                    <?php endif; ?>
                    <a href="<?= BASE_URL ?>/projects/list_project.php" class="submenu-item <?= is_active(['list_project.php', 'add_project.php']) ?>">Ver Proyectos</a>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($ver_ordenes): ?>
            <div class="has-submenu <?= is_active(['list_oc.php', 'new_order.php']) ? 'expanded' : '' ?>" id="menuOrdenes">
                <a href="#" class="menu-item <?= is_active(['list_oc.php', 'new_order.php']) ?>" onclick="toggleSubmenu(event, 'menuOrdenes')" title="Órdenes de Compra">
                    <span class="material-symbols-rounded">receipt_long</span>
                    <span class="menu-text">Órdenes Compra</span>
                    <span class="material-symbols-rounded chevron" style="margin-left:auto;font-size:1.1rem;">expand_more</span>
                </a>
                <div class="submenu-container">
                    <?php if ($crear_orden): ?>
                    <a href="<?= BASE_URL ?>/orders/new_order.php" class="submenu-item <?= is_active('new_order.php') ?>">Crear Nueva</a>
                    <?php endif; ?>
                    <a href="<?= BASE_URL ?>/orders/list_oc.php" class="submenu-item <?= is_active('list_oc.php') ?>">Ver Registros</a>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($ver_requisiciones): ?>
            <div class="has-submenu <?= is_active(['list_requis.php', 'new_requis.php']) ? 'expanded' : '' ?>" id="menuRequis">
                <a href="#" class="menu-item <?= is_active(['list_requis.php', 'new_requis.php']) ?>" onclick="toggleSubmenu(event, 'menuRequis')" title="Requisiciones">
                    <span class="material-symbols-rounded">assignment</span>
                    <span class="menu-text">Requisiciones</span>
                    <span class="material-symbols-rounded chevron" style="margin-left:auto;font-size:1.1rem;">expand_more</span>
                </a>
                <div class="submenu-container">
                    <?php if ($crear_orden): ?>
                    <a href="<?= BASE_URL ?>/orders/new_requis.php" class="submenu-item <?= is_active('new_requis.php') ?>">Crear Requisición</a>
                    <?php endif; ?>
                    <a href="<?= BASE_URL ?>/orders/list_requis.php" class="submenu-item <?= is_active('list_requis.php') ?>">Ver Registros</a>
                </div>
            </div>
            <?php endif; ?>

            <!-- RECURSOS -->
            <div class="sidebar-label">Recursos</div>

            <?php if ($ver_catalogo): ?>
            <a href="<?= BASE_URL ?>/catalog/list_catalog.php" class="menu-item <?= is_active('list_catalog.php') ?>" title="Catálogo">
                <span class="material-symbols-rounded">grid_view</span>
                <span class="menu-text">Catálogo</span>
            </a>
            <?php endif; ?>

            <?php if ($ver_activos): ?>
            <a href="<?= BASE_URL ?>/activos/list_activos.php" class="menu-item <?= is_active(['list_activos.php', 'add_activo.php', 'edit_activo.php']) ?>" title="Activos">
                <span class="material-symbols-rounded">fact_check</span>
                <span class="menu-text">Activos</span>
            </a>
            <?php endif; ?>

            <!-- ADMINISTRACIÓN -->
            <div class="sidebar-label">Administración</div>

            <?php if ($ver_admin_usuarios): ?>
            <a href="<?= BASE_URL ?>/users/list_users.php" class="menu-item <?= is_active(['list_users.php', 'add_user.php', 'edit_user.php']) ?>" title="Usuarios">
                <span class="material-symbols-rounded">badge</span>
                <span class="menu-text">Usuarios</span>
            </a>
            <?php endif; ?>

            <?php if ($ver_dash): ?>
            <a href="<?= BASE_URL ?>/dashboard.php" class="menu-item <?= is_active('dashboard.php') ?>" title="Panel de Control">
                <span class="material-symbols-rounded">analytics</span>
                <span class="menu-text">Panel de Control</span>
            </a>
            <?php endif; ?>

            <!-- SOPORTE -->
            <div class="sidebar-label">Soporte</div>
            <a href="<?= BASE_URL ?>/solicitud_soporte.php" class="menu-item <?= is_active('solicitud_soporte.php') ?>" title="Soporte TI">
                <span class="material-symbols-rounded">person</span>
                <span class="menu-text">Soporte TI</span>
            </a>

            <!-- LOGOUT -->
            <div style="margin-top: auto; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.05);">
                <a href="#" class="menu-item logout-item" onclick="UI.logout()" title="Cerrar Sesión" style="color: #ff5f5f;">
                    <span class="material-symbols-rounded">logout</span>
                    <span class="menu-text">Cerrar Sesión</span>
                </a>
            </div>

        </nav>
    </aside>

    <!-- ─── MAIN WRAPPER ─────────────────────────────────────────────────────── -->
    <div class="app-main">
        
        <!-- TOP HEADER -->
        <header class="app-header">
            <div style="display:flex; align-items:center; gap: 1.5rem;">
                <!-- Solo en movil se muestra el boton aca -->
                <button class="mobile-toggle btn-icon" onclick="toggleMobileSidebar()">
                    <span class="material-symbols-rounded">menu</span>
                </button>
                
                <div class="header-search">
                    <span class="material-symbols-rounded">search</span>
                    <input type="text" placeholder="Buscar módulos o registros...">
                </div>
            </div>

            <div class="header-actions">
                <div class="user-profile">
                    <div class="user-info" style="text-align: right;">
                        <span class="user-name"><?= htmlspecialchars($primerNombre) ?></span>
                        <span class="user-role"><?= htmlspecialchars($rol_usuario) ?></span>
                    </div>
                    <div class="avatar"><?= $inicial ?></div>
                </div>

                <button class="btn-icon" onclick="UI.logout()" title="Cerrar Sesión">
                    <span class="material-symbols-rounded">logout</span>
                </button>
            </div>
        </header>

        <!-- MAIN CONTENT AREA -->
        <main class="app-content">
            <!-- El contenido de la página empieza aquí y se cierra en footer.php -->
