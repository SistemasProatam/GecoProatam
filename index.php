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

<div class="page-content-inner" style="gap: 0; margin-top: -1.5rem; margin-left: -1.5rem; margin-right: -1.5rem;">
    
    <!-- Hero Section: Full Visual Background -->
    <div class="hero-overlay-card" style="border-radius: 0 0 28px 28px;">
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
            <div class="page-header" style="margin-bottom: auto;">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item active" style="color: rgba(255,255,255,0.6);">Inicio</li>
                    </ol>
                </nav>
                <h1 class="page-title" style="color: #ffffff; font-size: 1.2rem;">Bienvenido</h1>
            </div>

            <div class="hero-top-info">
                <span class="hero-date" id="realTimeDisplay"><?= mb_strtoupper(date('d M Y • h:i A')) ?></span>
            </div>

            <div class="hero-greeting-box">
                <h2 class="h-greeting"><?= $saludo ?>,</h2>
                <h2 class="h-name"><?= htmlspecialchars($primerNombre) ?></h2>
                <p class="h-welcome-text">Bienvenido a <strong>GECO Proatam</strong>, tu Sistema de Gestión Integral.</p>

            </div>

            <!-- Floating Quote Card -->
            <div class="floating-card quote-card">
                <span class="material-symbols-rounded quote-icon">format_quote</span>
                <p class="quote-text">Cada proyecto es una oportunidad para construir un futuro mejor.</p>
            </div>

            <!-- Floating Status Card -->
            <div class="floating-card status-card">
                <div class="status-info">
                    <span class="material-symbols-rounded status-icon">wb_sunny</span>
                    <div class="status-details">
                        <span class="st-temp">26°C</span>
                        <span class="st-desc">Despejado</span>
                    </div>
                </div>
                <div class="status-location">
                    <span class="material-symbols-rounded loc-icon">location_on</span>
                    <span class="loc-text">Reynosa, MX</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Access Section -->
    <div class="quick-access-section">
        <div class="qa-header">
            <div class="qa-title-box">
                <span class="material-symbols-rounded">apps</span>
                <div class="qa-titles">
                    <h3>Accesos Rápidos</h3>
                    <p>Accede rápidamente a las áreas principales del sistema.</p>
                </div>
            </div>

        </div>

        <div class="qa-grid">
            <?php if ($ver_dash): ?>
            <a href="<?= BASE_URL ?>/dashboard.php" class="qa-card">
                <div class="qa-icon-box blue"><span class="material-symbols-rounded">analytics</span></div>
                <div class="qa-content">
                    <h4>Panel de Control</h4>
                    <p>Métricas y KPIs del sistema</p>
                </div>
                <span class="material-symbols-rounded qa-arrow">arrow_forward</span>
            </a>
            <?php endif; ?>

            <?php if ($ver_proyectos): ?>
            <a href="<?= BASE_URL ?>/projects/list_project.php" class="qa-card">
                <div class="qa-icon-box purple"><span class="material-symbols-rounded">business_center</span></div>
                <div class="qa-content">
                    <h4>Proyectos</h4>
                    <p>Gestión de obras y contratos</p>
                </div>
                <span class="material-symbols-rounded qa-arrow">arrow_forward</span>
            </a>
            <?php endif; ?>

            <?php if ($ver_ordenes): ?>
            <a href="<?= BASE_URL ?>/orders/list_oc.php" class="qa-card">
                <div class="qa-icon-box green"><span class="material-symbols-rounded">receipt_long</span></div>
                <div class="qa-content">
                    <h4>Compras</h4>
                    <p>Órdenes y requisiciones</p>
                </div>
                <span class="material-symbols-rounded qa-arrow">arrow_forward</span>
            </a>
            <?php endif; ?>

            <a href="<?= BASE_URL ?>/solicitud_soporte.php" class="qa-card">
                <div class="qa-icon-box orange"><span class="material-symbols-rounded">engineering</span></div>
                <div class="qa-content">
                    <h4>Solicitud de Mantenimiento</h4>
                    <p>Solicitudes y reportes técnicos</p>
                </div>
                <span class="material-symbols-rounded qa-arrow">arrow_forward</span>
            </a>
        </div>
    </div>
</div>

<style>
/* ─── HERO OVERLAY DESIGN ───────────────────────────────────── */
.hero-overlay-card {
    position: relative;
    height: 480px;
    border-radius: 28px;
    overflow: hidden;
    background: #1e293b;
    box-shadow: 0 20px 50px rgba(0,0,0,0.15);
}

.hero-bg-wrapper {
    position: absolute;
    inset: 0;
    z-index: 1;
}

.hero-bg-wrapper video,
.hero-bg-wrapper img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.hero-darkener {
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(30,41,59,0.95) 0%, rgba(30,41,59,0.4) 60%, transparent 100%);
}

.hero-main-content {
    position: relative;
    z-index: 2;
    height: 100%;
    padding: 3rem;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.hero-date {
    font-size: 0.8rem;
    font-weight: 700;
    color: rgba(255,255,255,0.7);
    letter-spacing: 0.1em;
}

.hero-greeting-box {
    max-width: 450px;
}

.h-greeting {
    font-size: 2.8rem;
    font-weight: 800;
    color: #ffffff;
    margin: 0;
    line-height: 1;
}

.h-name {
    font-size: 3.2rem;
    font-weight: 800;
    color: var(--p-400);
    margin: 0 0 1rem 0;
    line-height: 1;
}

.h-welcome-text {
    font-size: 1.1rem;
    color: rgba(255,255,255,0.8);
    margin-bottom: 2rem;
}

.h-divider {
    width: 40px;
    height: 4px;
    background: var(--p-400);
    border-radius: 2px;
}

/* Floating Cards (Glassmorphism) */
.floating-card {
    position: absolute;
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(12px);
    border-radius: 20px;
    padding: 1.25rem;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    border: 1px solid rgba(255,255,255,0.3);
}

.quote-card {
    right: 3rem;
    bottom: 12rem;
    width: 280px;
}

.quote-icon {
    font-size: 2rem;
    color: var(--p-500);
    margin-bottom: 0.5rem;
    display: block;
}

.quote-text {
    font-size: 0.95rem;
    color: var(--s-800);
    font-weight: 600;
    line-height: 1.4;
    margin: 0;
}

.status-card {
    right: 3rem;
    bottom: 3rem;
    min-width: 240px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 2rem;
}

.status-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.status-icon {
    font-size: 2rem;
    color: #f59e0b;
}

.status-details {
    display: flex;
    flex-direction: column;
}

.st-temp {
    font-size: 1.2rem;
    font-weight: 800;
    color: var(--s-900);
}

.st-desc {
    font-size: 0.75rem;
    color: var(--gray-500);
    font-weight: 600;
}

.status-location {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding-left: 1rem;
    border-left: 1px solid var(--gray-200);
}

.loc-icon { font-size: 1rem; color: var(--gray-400); }
.loc-text { font-size: 0.8rem; font-weight: 700; color: var(--s-700); }

/* ─── QUICK ACCESS SECTION ──────────────────────────────────── */
.quick-access-section {
    margin-top: 2.5rem;
    padding: 0 1.5rem 2.5rem 1.5rem;
}

.qa-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.qa-title-box {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.qa-title-box > span {
    font-size: 1.8rem;
    color: var(--p-500);
}

.qa-titles h3 { font-size: 1.1rem; font-weight: 800; color: var(--s-900); margin: 0; }
.qa-titles p { font-size: 0.8rem; color: var(--gray-500); margin: 2px 0 0; }

.btn-outline-sm {
    background: transparent;
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    padding: 6px 12px;
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--s-700);
    display: flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-outline-sm:hover {
    background: var(--gray-50);
    border-color: var(--p-300);
    color: var(--p-600);
}

.qa-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.5rem;
}

.qa-card {
    background: #ffffff;
    border: 1px solid var(--gray-100);
    border-radius: 18px;
    padding: 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    text-decoration: none;
    transition: all 0.3s;
}

.qa-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.04);
    border-color: var(--p-200);
}

.qa-icon-box {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    flex-shrink: 0;
}

.qa-icon-box.blue { background: #eff6ff; color: #2563eb; }
.qa-icon-box.purple { background: #f5f3ff; color: #7c3aed; }
.qa-icon-box.green { background: #f0fdf4; color: #16a34a; }

.qa-content h4 { font-size: 0.95rem; font-weight: 700; color: var(--s-800); margin: 0; }
.qa-content p { font-size: 0.8rem; color: var(--gray-500); margin: 2px 0 0; }

.qa-arrow { margin-left: auto; color: var(--gray-300); font-size: 1.2rem; }
.qa-card:hover .qa-arrow { color: var(--p-500); transform: translateX(3px); }
</style>

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
        
        // Mapeo simple de descripción a iconos
        const desc = current.weatherDesc[0].value.toLowerCase();
        let icon = 'wb_sunny';
        let descSp = 'Despejado';

        if (desc.includes('cloud')) { icon = 'partly_cloudy_day'; descSp = 'Nublado'; }
        if (desc.includes('rain')) { icon = 'rainy'; descSp = 'Lluvia'; }
        if (desc.includes('clear')) { icon = 'wb_sunny'; descSp = 'Despejado'; }
        if (desc.includes('overcast')) { icon = 'cloud'; descSp = 'Cubierto'; }
        
        document.querySelector('.status-icon').textContent = icon;
        document.querySelector('.st-desc').textContent = descSp;
    } catch (e) {
        console.error("Error fetching weather", e);
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
