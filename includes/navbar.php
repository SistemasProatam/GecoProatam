<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";
require_once __DIR__ . "/../includes/permissions.php";
require_once __DIR__ . "/auth_guard.php";

checkRoutePermission();
checkSession();
preventCaching();

// Sesión
$departamento_sesion = $_SESSION['departamento'] ?? 'Sin departamento';
$nombre_usuario = $_SESSION['nombres'] ?? 'Usuario';
$apellidos_usuario = $_SESSION['apellidos'] ?? '';
$primerNombre = explode(' ', trim($nombre_usuario))[0] ?? 'U';
$inicial = getInitials($nombre_usuario, $apellidos_usuario);
$rol_usuario = $_SESSION['departamento'] ?? 'General';

// Permisos
$ver_proyectos = hasPermission('proyectos');
$ver_plan_obra = hasPermission('plan_obra');
$ver_catalogo = hasPermission('catalog');
$ver_obras = hasPermission('obras');
$ver_activos = hasPermission('activos');
$ver_ordenes = hasPermission('ordenes');
$ver_requisiciones = hasPermission('requisiciones');
$ver_cotizaciones = hasPermission('cotizaciones');
$ver_admin_usuarios = hasPermission('usuarios');
$ver_dash = hasPermission('dashboard');

function is_active($path)
{
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet" />
    <link rel="icon" href="<?= BASE_URL ?>/assets/img/GECO_ISOLOGO.png" type="image/x-icon">

    <!-- Hojas de Estilo Base -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/core/tokens.css?v=1.2" />
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/core/layout.css?v=1.4" />
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/core/components.css?v=1.0" />
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/ui.css?v=1.2" />
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/list.css?v=1.2" />

    <script>
        window.BASE_URL = '<?= BASE_URL ?>';
    </script>
    <script src="<?= BASE_URL ?>/assets/scripts/ui.js?v=1.5"></script>
</head>

<body>
    <div class="app-layout">

        <!-- ─── SIDEBAR ──────────────────────────────────────────────────────────── -->
        <aside class="app-sidebar collapsed" id="appSidebar">
            <div class="sidebar-brand">
                <a href="<?= BASE_URL ?>/index.php" class="brand-logo-link">
                    <img src="<?= BASE_URL ?>/assets/img/GECO.png" alt="Logo">
                </a>
                <button class="desktop-toggle btn-icon" onclick="toggleSidebar()">
                    <span class="material-symbols-rounded">menu</span>
                </button>
            </div>

            <nav class="sidebar-menu">
                <!-- INICIO -->
                <a href="<?= BASE_URL ?>/index.php" class="menu-item <?= is_active('index.php') ?>" data-tooltip="Inicio">
                    <span class="material-symbols-rounded">home</span>
                    <span class="menu-text">Inicio</span>
                </a>

                <!-- OPERACIÓN -->
                <div class="sidebar-label">Operación</div>

                <!-- Proyectos -->
                <?php if ($ver_proyectos): ?>
                    <div class="has-submenu <?= is_active(['list_project.php', 'plan_obra.php', 'add_project.php']) ? 'expanded' : '' ?>" id="menuProyectos" data-label="Proyectos">
                        <a href="#" class="menu-item <?= is_active(['list_project.php', 'plan_obra.php', 'add_project.php']) ?>" onclick="toggleSubmenu(event, 'menuProyectos')">
                            <span class="material-symbols-rounded">business_center</span>
                            <span class="menu-text">Proyectos</span>
                            <span class="material-symbols-rounded chevron" style="margin-left:auto;font-size:1.1rem;">expand_more</span>
                        </a>
                        <div class="submenu-container">
                            <span class="flyout-header">Proyectos</span>
                            <?php if ($ver_plan_obra): ?>
                                <a href="<?= BASE_URL ?>/projects/plan_obra.php" class="submenu-item <?= is_active('plan_obra.php') ?>">Plan de Obra</a>
                            <?php endif; ?>
                            <a href="<?= BASE_URL ?>/projects/list_project.php" class="submenu-item <?= is_active(['list_project.php', 'add_project.php']) ?>">Proyectos</a>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Ordenes de compra -->
                <?php if ($ver_ordenes): ?>
                    <a href="<?= BASE_URL ?>/orders/list_oc.php" class="menu-item <?= is_active('list_oc.php') ?>" data-tooltip="Órdenes de Compra">
                        <span class="material-symbols-rounded">receipt_long</span>
                        <span class="menu-text">Órdenes de Compra</span>
                    </a>
                <?php endif; ?>

                <!-- Requisiciones -->
                <?php if ($ver_requisiciones): ?>
                    <a href="<?= BASE_URL ?>/orders/list_requis.php" class="menu-item <?= is_active('list_requis.php') ?>" data-tooltip="Requisiciones">
                        <span class="material-symbols-rounded">assignment</span>
                        <span class="menu-text">Requisiciones</span>
                    </a>
                <?php endif; ?>

                <!-- Cotizaciones -->
                <?php if ($ver_cotizaciones): ?>
                    <a href="<?= BASE_URL ?>/cotizaciones/list_cotizaciones.php" class="menu-item <?= is_active('lista_cotizaciones.php') ?>" data-tooltip="Cotizaciones">
                        <span class="material-symbols-rounded">request_quote</span>
                        <span class="menu-text">Cotizaciones</span>
                    </a>
                <?php endif; ?>

                <!-- RECURSOS -->
                <div class="sidebar-label">Recursos</div>

                <!-- Catálogo -->
                <?php if ($ver_catalogo): ?>
                    <a href="<?= BASE_URL ?>/catalog/list_catalog.php" class="menu-item <?= is_active('list_catalog.php') ?>" data-tooltip="Catálogo">
                        <span class="material-symbols-rounded">grid_view</span>
                        <span class="menu-text">Catálogo</span>
                    </a>
                <?php endif; ?>

                <!-- Activos -->
                <?php if ($ver_activos): ?>
                    <a href="<?= BASE_URL ?>/activos/list_activos.php" class="menu-item <?= is_active(['list_activos.php', 'add_activo.php', 'edit_activo.php']) ?>" data-tooltip="Activos">
                        <span class="material-symbols-rounded">fact_check</span>
                        <span class="menu-text">Activos</span>
                    </a>
                <?php endif; ?>

                <!-- ADMINISTRACIÓN -->
                <div class="sidebar-label">Administración</div>

                <!-- Usuarios -->
                <?php if ($ver_admin_usuarios): ?>
                    <a href="<?= BASE_URL ?>/users/list_users.php" class="menu-item <?= is_active('list_users.php') ?>" data-tooltip="Usuarios">
                        <span class="material-symbols-rounded">person</span>
                        <span class="menu-text">Usuarios</span>
                    </a>
                <?php endif; ?>

                <!-- Panel de Control -->
                <?php if ($ver_dash): ?>
                    <a href="<?= BASE_URL ?>/dashboard.php" class="menu-item <?= is_active('dashboard.php') ?>" data-tooltip="Panel de Control">
                        <span class="material-symbols-rounded">analytics</span>
                        <span class="menu-text">Panel de Control</span>
                    </a>
                <?php endif; ?>

                <!-- MANTENIMIENTO -->
                <div class="sidebar-label">Mantenimiento</div>
                <a href="<?= BASE_URL ?>/solicitud_soporte.php" class="menu-item <?= is_active('solicitud_soporte.php') ?>" data-tooltip="Solicitud de Mantenimiento">
                    <span class="material-symbols-rounded">engineering</span>
                    <span class="menu-text">Solicitud de Mantenimiento</span>
                </a>

                <!-- LOGOUT -->
                <div style="margin-top: auto; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.05);">
                    <a href="#" class="menu-item logout-item" onclick="UI.logout()" data-tooltip="Cerrar Sesión" style="color: #ff5f5f;">
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

                    <div class="search-input-wrap header-search-wrap">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" id="globalSearchInput" placeholder="Buscar módulos..." autocomplete="off">

                        <!-- Dropdown de resultados -->
                        <div id="search-results-dropdown" class="search-dropdown"></div>
                    </div>
                </div>

                <!-- ISOLOGO DECORATIVO CENTRAL -->
                <div class="header-center-decoration">
                    <img src="<?= BASE_URL ?>/assets/img/GECO_ISOLOGO.png"
                        alt="GECO Isologo"
                        class="isologo-spin"
                        onclick="this.classList.toggle('rotating')"
                        style="height: 35px; opacity: 0.6; cursor: pointer; transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);">
                </div>

                <div class="header-actions">
                    <div class="user-profile">
                        <div class="user-info" style="text-align: right;">
                            <span class="user-name"><?= htmlspecialchars($primerNombre) ?></span>
                            <span class="user-role"><?= htmlspecialchars($rol_usuario) ?></span>
                        </div>
                        <div class="avatar"><?= $inicial ?></div>
                    </div>


                </div>
            </header>

            <!-- Lógica de Búsqueda Global -->
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    const searchInput = document.getElementById('globalSearchInput');
                    const resultsDropdown = document.getElementById('search-results-dropdown');

                    // Lista exhaustiva de módulos y submenús basada en la navegación real
                    const searchableModules = [{
                            name: 'Inicio',
                            parent: '',
                            url: '<?= BASE_URL ?>/index.php',
                            icon: 'home',
                            visible: true
                        },

                        // Proyectos
                        {
                            name: 'Plan de Obra',
                            parent: 'Proyectos',
                            url: '<?= BASE_URL ?>/projects/plan_obra.php',
                            icon: 'business_center',
                            visible: <?= $ver_plan_obra ? 'true' : 'false' ?>
                        },
                        {
                            name: 'Ver Proyectos',
                            parent: 'Proyectos',
                            url: '<?= BASE_URL ?>/projects/list_project.php',
                            icon: 'business_center',
                            visible: <?= $ver_proyectos ? 'true' : 'false' ?>
                        },

                        // Órdenes
                        {
                            name: 'Ver Registros Órdenes',
                            parent: 'Órdenes Compra',
                            url: '<?= BASE_URL ?>/orders/list_oc.php',
                            icon: 'receipt_long',
                            visible: <?= $ver_ordenes ? 'true' : 'false' ?>
                        },

                        // Requisiciones
                        {
                            name: 'Ver Registros Requisiciones',
                            parent: 'Requisiciones',
                            url: '<?= BASE_URL ?>/orders/list_requis.php',
                            icon: 'assignment',
                            visible: <?= $ver_requisiciones ? 'true' : 'false' ?>
                        },

                        // Cotizaciones
                        {
                            name: 'Ver Registros Cotizaciones',
                            parent: 'Cotizaciones',
                            url: '<?= BASE_URL ?>/cotizaciones/list_cotizaciones.php',
                            icon: 'request_quote',
                            visible: <?= $ver_cotizaciones ? 'true' : 'false' ?>
                        },

                        // Recursos
                        {
                            name: 'Catálogo',
                            parent: '',
                            url: '<?= BASE_URL ?>/catalog/list_catalog.php',
                            icon: 'grid_view',
                            visible: <?= $ver_catalogo ? 'true' : 'false' ?>
                        },
                        {
                            name: 'Activos',
                            parent: '',
                            url: '<?= BASE_URL ?>/activos/list_activos.php',
                            icon: 'fact_check',
                            visible: <?= $ver_activos ? 'true' : 'false' ?>
                        },

                        // Admin
                        {
                            name: 'Usuarios',
                            parent: '',
                            url: '<?= BASE_URL ?>/users/list_users.php',
                            icon: 'person',
                            visible: <?= $ver_admin_usuarios ? 'true' : 'false' ?>
                        },
                        {
                            name: 'Panel de Control',
                            parent: '',
                            url: '<?= BASE_URL ?>/dashboard.php',
                            icon: 'analytics',
                            visible: <?= $ver_dash ? 'true' : 'false' ?>
                        },

                        // Otros
                        {
                            name: 'Solicitud de Mantenimiento',
                            parent: '',
                            url: '<?= BASE_URL ?>/solicitud_soporte.php',
                            icon: 'engineering',
                            visible: true
                        }
                    ].filter(m => m.visible);

                    searchInput.addEventListener('input', (e) => {
                        const term = e.target.value.toLowerCase().trim();
                        resultsDropdown.innerHTML = '';

                        if (term.length === 0) {
                            resultsDropdown.style.display = 'none';
                            return;
                        }

                        // Función para normalizar y quitar acentos
                        const normalize = (str) => str.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase();
                        const normalizedTerm = normalize(term);

                        const matches = searchableModules.filter(m => {
                            const nameNorm = normalize(m.name);
                            const parentNorm = m.parent ? normalize(m.parent) : '';
                            return nameNorm.includes(normalizedTerm) || parentNorm.includes(normalizedTerm);
                        });

                        if (matches.length > 0) {
                            matches.forEach(m => {
                                const item = document.createElement('a');
                                item.href = m.url;
                                item.className = 'search-result-item';
                                const parentTag = m.parent ? `<span class="res-parent">${m.parent}</span>` : '';
                                item.innerHTML = `
                            <div class="res-icon-box">
                                <span class="material-symbols-rounded">${m.icon}</span>
                            </div>
                            <div class="res-info">
                                <span class="res-name">${m.name}</span>
                                ${parentTag}
                            </div>
                        `;
                                resultsDropdown.appendChild(item);
                            });
                            resultsDropdown.style.display = 'block';
                        } else {
                            resultsDropdown.innerHTML = '<div class="search-no-results">No se encontraron coincidencias</div>';
                            resultsDropdown.style.display = 'block';
                        }
                    });

                    // Cerrar dropdown al hacer clic fuera
                    document.addEventListener('click', (e) => {
                        if (!searchInput.contains(e.target) && !resultsDropdown.contains(e.target)) {
                            resultsDropdown.style.display = 'none';
                        }
                    });
                });
            </script>

            <!-- MAIN CONTENT AREA -->
            <main class="app-content">
                <!-- El contenido de la página empieza aquí y se cierra en footer.php -->