<?php
require_once "config.php";
require_once "includes/session_manager.php";
require_once "includes/check_session.php";

checkSession();
preventCaching();

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

<style>
/* Estilos específicos para el Index Dashboard */
.welcome-hero {
    position: relative;
    border-radius: 24px;
    overflow: hidden;
    padding: 3rem 2.5rem;
    color: white;
    margin-bottom: 2rem;
    background: linear-gradient(135deg, rgba(17,53,87,0.95) 0%, rgba(63,117,85,0.85) 100%), url('<?= BASE_URL ?>/assets/img/background.png') center/cover;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.welcome-hero::after {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: url('https://www.transparenttextures.com/patterns/cubes.png');
    opacity: 0.1;
    pointer-events: none;
}

.welcome-text {
    position: relative;
    z-index: 2;
    max-width: 600px;
}

.welcome-greeting {
    font-size: 2.5rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    letter-spacing: -0.02em;
}

.welcome-subtitle {
    font-size: 1.1rem;
    opacity: 0.9;
    font-weight: 300;
    line-height: 1.5;
}

.welcome-clock {
    position: relative;
    z-index: 2;
    text-align: right;
    background: rgba(255,255,255,0.1);
    backdrop-filter: blur(10px);
    padding: 1.5rem 2rem;
    border-radius: 20px;
    border: 1px solid rgba(255,255,255,0.2);
}

.clock-time {
    font-size: 3rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    line-height: 1;
    margin-bottom: 0.2rem;
}

.clock-date {
    font-size: 0.95rem;
    opacity: 0.9;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 500;
}

/* Quick Actions */
.section-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--s-800);
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 8px;
}

.shortcuts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.shortcut-card {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    text-decoration: none;
    color: inherit;
    border: 1px solid var(--gray-200);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 2px 10px rgba(0,0,0,0.02);
}

.shortcut-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px rgba(0,0,0,0.06);
    border-color: var(--gray-300);
    color: inherit;
}

.shortcut-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.shortcut-card.blue .shortcut-icon { background: rgba(77, 184, 255, 0.1); color: #2096e0; }
.shortcut-card.green .shortcut-icon { background: rgba(0, 201, 167, 0.1); color: #00a383; }
.shortcut-card.purple .shortcut-icon { background: rgba(167, 139, 250, 0.1); color: #7c5fe0; }
.shortcut-card.gold .shortcut-icon { background: rgba(244, 185, 66, 0.1); color: #d49520; }

.shortcut-info h3 {
    font-size: 1.05rem;
    font-weight: 700;
    margin: 0 0 4px 0;
    color: var(--s-800);
}

.shortcut-info p {
    font-size: 0.85rem;
    color: var(--gray-500);
    margin: 0;
    line-height: 1.4;
}

/* Info Panel */
.info-panel {
    background: white;
    border-radius: 16px;
    padding: 2rem;
    border: 1px solid var(--gray-200);
    display: flex;
    align-items: center;
    gap: 2rem;
}

.info-panel img {
    max-width: 150px;
    opacity: 0.8;
}

.info-text h4 {
    font-size: 1.1rem;
    font-weight: 700;
    margin: 0 0 8px 0;
}

.info-text p {
    color: var(--gray-500);
    font-size: 0.9rem;
    margin: 0;
}

@media (max-width: 768px) {
    .welcome-hero {
        flex-direction: column;
        align-items: flex-start;
        gap: 2rem;
        padding: 2rem 1.5rem;
    }
    .welcome-clock {
        text-align: left;
        width: 100%;
    }
    .info-panel {
        flex-direction: column;
        text-align: center;
    }
}
</style>

<!-- Hero Section -->
<div class="welcome-hero">
    <div class="welcome-text">
        <h1 class="welcome-greeting"><?= $saludo ?>, <?= htmlspecialchars($primerNombre) ?>.</h1>
        <p class="welcome-subtitle">Bienvenido al Sistema de Gestión Integral PROATAM. Selecciona un módulo para comenzar tu jornada.</p>
    </div>
    
    <div class="welcome-clock">
        <div class="clock-time" id="realTimeClock">00:00</div>
        <div class="clock-date" id="realTimeDate">Cargando fecha...</div>
    </div>
</div>

<!-- Accesos Rápidos -->
<h2 class="section-title">
    <span class="material-symbols-rounded" style="color: var(--p-500)">bolt</span>
    Accesos Rápidos
</h2>

<div class="shortcuts-grid">
    <?php if ($ver_dash): ?>
    <a href="<?= BASE_URL ?>/dashboard.php" class="shortcut-card blue">
        <div class="shortcut-icon"><span class="material-symbols-rounded">space_dashboard</span></div>
        <div class="shortcut-info">
            <h3>Panel de Control</h3>
            <p>Visualiza el rendimiento y métricas principales de la empresa.</p>
        </div>
    </a>
    <?php endif; ?>

    <a href="<?= BASE_URL ?>/solicitud_soporte.php" class="shortcut-card purple">
        <div class="shortcut-icon"><span class="material-symbols-rounded">support_agent</span></div>
        <div class="shortcut-info">
            <h3>Soporte TI</h3>
            <p>¿Tienes problemas? Crea un ticket de mantenimiento o soporte.</p>
        </div>
    </a>

    <?php if ($ver_activos): ?>
    <a href="<?= BASE_URL ?>/activos/list_activos.php" class="shortcut-card green">
        <div class="shortcut-icon"><span class="material-symbols-rounded">inventory_2</span></div>
        <div class="shortcut-info">
            <h3>Inventario</h3>
            <p>Gestiona y revisa el estado de los activos físicos.</p>
        </div>
    </a>
    <?php endif; ?>

    <a href="<?= BASE_URL ?>/change_password.php" class="shortcut-card gold">
        <div class="shortcut-icon"><span class="material-symbols-rounded">lock_reset</span></div>
        <div class="shortcut-info">
            <h3>Seguridad</h3>
            <p>Actualiza tu contraseña corporativa regularmente.</p>
        </div>
    </a>
</div>

<!-- Panel de Información -->
<div class="info-panel">
    <img src="<?= BASE_URL ?>/assets/img/proatam.png" alt="PROATAM">
    <div class="info-text">
        <h4>SGI PROATAM v1.2</h4>
        <p>Sistema centralizado para la administración de activos, recursos humanos, control de proyectos y procuración. Si necesitas acceso a un módulo específico, contacta a tu administrador.</p>
    </div>
</div>

<script>
function updateClock() {
    const now = new Date();
    
    // Formato de hora (14:30)
    document.getElementById('realTimeClock').textContent = now.toLocaleTimeString('es-MX', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false
    });
    
    // Formato de fecha (Lunes, 5 de Mayo de 2026)
    const opcionesFecha = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    document.getElementById('realTimeDate').textContent = now.toLocaleDateString('es-MX', opcionesFecha);
}

setInterval(updateClock, 1000);
updateClock(); // Iniciar inmediatamente
</script>

<?php
// Cerrar el layout (Esto renderiza el footer y cierra div.app-main)
include 'includes/footer.php'; 
?>
