<?php
require_once "config.php";
require_once "includes/session_manager.php";
require_once "includes/check_session.php";

checkSession();
preventCaching();

// Ajustar zona horaria para saludos precisos (Reynosa/Matamoros)
date_default_timezone_set('America/Matamoros');

// Incluir navbar (Esto abre el app-layout y app-content)
include 'includes/navbar.php';

$hora_actual = date('G');
$saludo = "Hola";
if ($hora_actual >= 5 && $hora_actual < 12) {
    $saludo = "Buenos días";
} elseif ($hora_actual >= 12 && $hora_actual < 19) {
    $saludo = "Buenas tardes";
} else {
    $saludo = "Buenas noches";
}

$primerNombre = explode(' ', trim($_SESSION['nombres']))[0] ?? 'Usuario';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/index.css?v=2.0">
<title>Inicio | GECO PROATAM</title>

<div class="page-content-inner index-full-wrap">

    <!-- Hero Section: Full Visual Background -->
    <div class="hero-overlay-card">
        <!-- Visual Layer -->
        <div class="hero-bg-wrapper">
            <video autoplay muted loop playsinline poster="<?= BASE_URL ?>/construction_machinery_artistic_3d_1778092529865.png">
                <source src="<?= BASE_URL ?>/assets/video/hero_bg.mp4" type="video/mp4">
                <img src="<?= BASE_URL ?>/construction_machinery_artistic_3d_1778092529865.png" alt="Background">
            </video>
            <div class="hero-darkener"></div>
        </div>

        <!-- Content Layer -->
        <div class="hero-main-content">
            <!-- Integrated Page Header -->
            <div class="orders-page-header mb-4">
                <div class="orders-page-header-info">
                    <nav class="orders-breadcrumb">
                        <span>Inicio</span>
                    </nav>
                    <h1 class="orders-page-title">Bienvenido</h1>
                </div>
            </div>

            <div class="hero-top-info">
                <span class="hero-date" id="realTimeDisplay"><?= mb_strtoupper(date('d M Y • h:i A')) ?></span>
            </div>

            <div class="hero-greeting-box">
                <h2 class="h-greeting"><?= $saludo ?>,</h2>
                <h2 class="h-name"><?= htmlspecialchars($primerNombre) ?></h2>
                <p class="h-welcome-text">Bienvenido a <strong>GECO Proatam</strong>, tu sistema de Gestión y Control Operativo.</p>

            </div>

            <!-- Floating Widgets Container -->
            <div class="hero-widgets-container">
                <!-- Floating Novedades Card -->
                <div class="floating-card quote-card">
                    <div class="news-header" style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.4rem;">
                        <i class="material-symbols-rounded" style="color: var(--p-400, #5db076); font-size: 0.95rem;">info</i>
                        <span style="font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: var(--gray-500, #6b7280);">Novedades</span>
                    </div>
                    <h4 class="quote-text" style="font-size: 0.85rem; font-weight: 800; margin: 0 0 0.2rem 0; color: var(--s-800, #0f172a);">GECO 2.0 • Actualización de interfaz de usuario</h4>
                    <p class="quote-text" style="font-size: 0.72rem; font-weight: 500; opacity: 0.85; margin: 0; line-height: 1.3;">Nueva interfaz unificada y sistema visual optimizado para alto rendimiento.
                    </p>
                    <small style="font-size: 0.62rem; font-weight: 500;  margin: 0; line-height: 1.3;">06/06/2026</small>
                </div>

                <!-- Floating Status Card -->
                <div class="floating-card status-card">
                    <div class="status-info">
                        <i class="material-symbols-rounded status-icon">light_mode</i>
                        <div class="status-details">
                            <span class="st-temp">26°C</span>
                            <span class="st-desc">Despejado</span>
                        </div>
                    </div>
                    <div class="status-location">
                        <i class="material-symbols-rounded loc-icon">location_on</i>
                        <span class="loc-text">Reynosa, MX</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Access Section -->
    <div class="quick-access-section">
        <div class="qa-header">
            <div class="qa-title-box">
                <i class="material-symbols-rounded">grid_view</i>
                <div class="qa-titles">
                    <h3>Accesos Rápidos</h3>
                    <p>Accede rápidamente a las áreas principales del sistema.</p>
                </div>
            </div>

        </div>

        <div class="qa-grid">
            <?php if ($ver_dash): ?>
                <a href="<?= BASE_URL ?>/dashboard.php" class="qa-card">
                    <div class="qa-icon-box blue"><i class="material-symbols-rounded">analytics</i></div>
                    <div class="qa-content">
                        <h4>Panel de Control</h4>
                        <p>Métricas y KPIs del sistema</p>
                    </div>
                    <i class="material-symbols-rounded qa-arrow">arrow_forward</i>
                </a>
            <?php endif; ?>

            <?php if ($ver_proyectos): ?>
                <a href="<?= BASE_URL ?>/projects/list_project.php" class="qa-card">
                    <div class="qa-icon-box purple"><i class="material-symbols-rounded">business_center</i></div>
                    <div class="qa-content">
                        <h4>Proyectos</h4>
                        <p>Gestión de obras y contratos</p>
                    </div>
                    <i class="material-symbols-rounded qa-arrow">arrow_forward</i>
                </a>
            <?php endif; ?>

            <?php if ($ver_ordenes): ?>
                <a href="<?= BASE_URL ?>/orders/list_oc.php" class="qa-card">
                    <div class="qa-icon-box green"><i class="material-symbols-rounded">receipt_long</i></div>
                    <div class="qa-content">
                        <h4>Compras</h4>
                        <p>Órdenes y requisiciones</p>
                    </div>
                    <i class="material-symbols-rounded qa-arrow">arrow_forward</i>
                </a>
            <?php endif; ?>

            <a href="<?= BASE_URL ?>/solicitud_soporte.php" class="qa-card">
                <div class="qa-icon-box orange"><i class="material-symbols-rounded">engineering</i></div>
                <div class="qa-content">
                    <h4>Solicitud de Mantenimiento</h4>
                    <p>Solicitudes y reportes técnicos</p>
                </div>
                <i class="material-symbols-rounded qa-arrow">arrow_forward</i>
            </a>
        </div>
    </div>
</div>



<script>
    function updateClock() {
        const now = new Date();
        const options = {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: true
        };
        const dateStr = now.toLocaleDateString('en-US', options).replace(',', '');
        document.getElementById('realTimeDisplay').textContent = dateStr.toUpperCase();
    }

    async function fetchWeather() {
        try {
            const response = await fetch('https://wttr.in/Reynosa?format=j1');
            const data = await response.json();
            const current = data.current_condition[0];

            document.querySelector('.st-temp').textContent = current.temp_C + '°C';

            const desc = current.weatherDesc[0].value.toLowerCase();
            let iconText = 'light_mode';
            let descSp = 'Despejado';

            if (desc.includes('cloud')) {
                iconText = 'partly_cloudy_day';
                descSp = 'Parcialmente nublado';
            }
            if (desc.includes('rain')) {
                iconText = 'rainy';
                descSp = 'Lluvioso';
            }
            if (desc.includes('clear')) {
                iconText = 'light_mode';
                descSp = 'Despejado';
            }
            if (desc.includes('overcast')) {
                iconText = 'cloud';
                descSp = 'Cubierto';
            }

            const statusIcon = document.querySelector('.status-icon');
            if (statusIcon) {
                statusIcon.textContent = iconText;
            }
            document.querySelector('.st-desc').textContent = descSp;
        } catch (e) {
            console.error("Error fetching weather", e);
            document.querySelector('.st-temp').textContent = '--';
            document.querySelector('.st-desc').textContent = 'Clima no disponible';
            const statusIcon = document.querySelector('.status-icon');
            if (statusIcon) {
                statusIcon.textContent = 'device_thermostat';
            }
        }
    }

    setInterval(updateClock, 1000);
    updateClock();
    fetchWeather();
</script>

<?php
// Cerrar el layout (Esto renderiza el footer y cierra div.app-main)
include 'includes/footer.php';
?>