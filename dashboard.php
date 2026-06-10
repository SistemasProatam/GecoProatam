<?php
require_once __DIR__ . "/includes/session_manager.php";
require_once __DIR__ . "/includes/check_session.php";

checkSession();
preventCaching();

require_once __DIR__ . "/conexion.php";

$hoy = date('Y-m-d');

$r = $conn->query("SELECT COUNT(*) AS total FROM proyectos WHERE fecha_fin >= '$hoy'");
$proyectos_vigentes = $r->fetch_assoc()['total'];
$r = $conn->query("SELECT COUNT(*) AS total FROM proyectos WHERE fecha_fin < '$hoy'");
$proyectos_terminados = $r->fetch_assoc()['total'];
$r = $conn->query("SELECT COUNT(*) AS total FROM proyectos");
$proyectos_total = $r->fetch_assoc()['total'];

$r = $conn->query("SELECT COUNT(*) AS total FROM ordenes_compra");
$oc_total = $r->fetch_assoc()['total'];
$r = $conn->query("SELECT COUNT(*) AS total FROM ordenes_compra WHERE estado = 'pendiente'");
$oc_pendientes = $r->fetch_assoc()['total'];
$r = $conn->query("SELECT COUNT(*) AS total FROM ordenes_compra WHERE estado = 'revisado'");
$oc_revisadas = $r->fetch_assoc()['total'];
$r = $conn->query("SELECT COUNT(*) AS total FROM ordenes_compra WHERE estado = 'aprobado'");
$oc_aprobadas = $r->fetch_assoc()['total'];
$r = $conn->query("SELECT COUNT(*) AS total FROM ordenes_compra WHERE estado = 'rechazado'");
$oc_rechazadas = $r->fetch_assoc()['total'];
$r = $conn->query("SELECT COUNT(*) AS total FROM ordenes_compra WHERE estado = 'pagado'");
$oc_pagadas = $r->fetch_assoc()['total'];
$r = $conn->query("SELECT COUNT(*) AS total FROM ordenes_compra WHERE estado = 'devuelto'");
$oc_devueltas = $r->fetch_assoc()['total'];
$r = $conn->query("SELECT COALESCE(SUM(total),0) AS monto FROM ordenes_compra WHERE estado NOT IN ('rechazado')");
$oc_monto = $r->fetch_assoc()['monto'];

$r = $conn->query("SELECT COUNT(*) AS total FROM activos WHERE estatus = 'activo'");
$activos_activos = $r->fetch_assoc()['total'];
$r = $conn->query("SELECT COUNT(*) AS total FROM activos WHERE estatus = 'inactivo'");
$activos_inactivos = $r->fetch_assoc()['total'];
$r = $conn->query("SELECT COUNT(*) AS total FROM activos");
$activos_total = $r->fetch_assoc()['total'];
$r = $conn->query("SELECT COALESCE(SUM(valor_factura),0) AS total FROM activos WHERE estatus = 'activo'");
$activos_valor_total = $r->fetch_assoc()['total'];

// --- NUEVAS MÉTRICAS ---
$r = $conn->query("SELECT COUNT(*) AS total FROM obras");
$obras_total = $r->fetch_assoc()['total'];

$r = $conn->query("SELECT COUNT(*) AS total FROM subcontratos");
$subcontratos_total = $r->fetch_assoc()['total'];

$r = $conn->query("SELECT COUNT(*) AS total FROM requisiciones");
$req_total = $r->fetch_assoc()['total'];
$r = $conn->query("SELECT COUNT(*) AS total FROM requisiciones WHERE estado = 'pendiente'");
$req_pendientes = $r->fetch_assoc()['total'];

$r = $conn->query("
    SELECT t.nombre AS tipo, t.prefijo,
           COUNT(a.id) AS cantidad,
           COALESCE(SUM(a.valor_factura), 0) AS valor_total
    FROM activo_tipos t
    LEFT JOIN activos a ON a.tipo_id = t.id AND a.estatus = 'activo'
    GROUP BY t.id, t.nombre, t.prefijo
    ORDER BY valor_total DESC
");
$activos_por_tipo = [];
while ($row = $r->fetch_assoc()) $activos_por_tipo[] = $row;

$r = $conn->query("SELECT COUNT(*) AS total FROM usuarios WHERE activo = 1");
$usuarios_activos = $r->fetch_assoc()['total'];
$r = $conn->query("SELECT COUNT(*) AS total FROM usuarios WHERE activo = 0");
$usuarios_inactivos = $r->fetch_assoc()['total'];

$r = $conn->query("
    SELECT MONTH(fecha_solicitud) AS mes_n,
           YEAR(fecha_solicitud) AS anio,
           DATE_FORMAT(fecha_solicitud, '%Y-%m') AS mes_ord,
           COUNT(*) AS total,
           COALESCE(SUM(total),0) AS monto
    FROM ordenes_compra
    WHERE fecha_solicitud >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY anio, mes_n, mes_ord
    ORDER BY mes_ord ASC
");
$meses_es = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
$oc_por_mes = [];
while ($row = $r->fetch_assoc()) {
    $row['mes'] = $meses_es[$row['mes_n']] . ' ' . $row['anio'];
    $oc_por_mes[] = $row;
}

$r = $conn->query("
    SELECT oc.folio, oc.estado, oc.total,
           oc.fecha_solicitud,
           CONCAT(u.nombres,' ',u.apellidos) AS solicitante,
           p.nombre AS proveedor
    FROM ordenes_compra oc
    JOIN usuarios u ON oc.solicitante_id = u.id
    JOIN proveedores p ON oc.proveedor_id = p.id
    ORDER BY oc.fecha_creacion DESC
    LIMIT 5
");
$ultimas_oc = [];
while ($row = $r->fetch_assoc()) $ultimas_oc[] = $row;

$r = $conn->query("
    SELECT nombre_proyecto, fecha_fin,
           DATEDIFF(fecha_fin, '$hoy') AS dias_restantes,
           c.nombre_abreviado AS cliente
    FROM proyectos p
    LEFT JOIN clientes c ON p.cliente_id = c.id
    WHERE fecha_fin BETWEEN '$hoy' AND DATE_ADD('$hoy', INTERVAL 30 DAY)
    ORDER BY fecha_fin ASC
    LIMIT 5
");
$proximos_vencer = [];
while ($row = $r->fetch_assoc()) $proximos_vencer[] = $row;

$r = $conn->query("
    SELECT condicion, COUNT(*) AS cantidad
    FROM activos WHERE estatus='activo'
    GROUP BY condicion
");
$condicion_activos = ['bueno' => 0, 'regular' => 0, 'malo' => 0];
while ($row = $r->fetch_assoc()) $condicion_activos[$row['condicion']] = $row['cantidad'];

function fmt($n)
{
    return '$' . number_format($n, 0, '.', ',');
}
function pct($part, $total)
{
    return $total > 0 ? round($part / $total * 100) : 0;
}

$tipo_colores = [
    ['#16a34a', '#15803d'], // GECO Green
    ['#2563eb', '#1d4ed8'], // GECO Blue
    ['#d97706', '#b45309'], // GECO Gold
    ['#7c3aed', '#6d28d9'], // GECO Purple
    ['#e11d48', '#be123c'], // GECO Rose
    ['#ea580c', '#c2410c'], // GECO Orange
];
?>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/core/modules.css?v=2.0">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/dashboard.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<title>Panel de Control | GECO PROATAM</title>

<?php include __DIR__ . "/includes/navbar.php"; ?>

<div class="orders-page-container">

    <!-- Page Header -->
    <div class="orders-page-header mb-4">
        <div class="orders-page-header-info">
            <nav class="orders-breadcrumb">
                <a href="<?= BASE_URL ?>/index.php">Inicio</a>
                <span class="separator">›</span>
                <span>Panel de Control</span>
            </nav>
            <h1 class="orders-page-title">Panel de Control</h1>
        </div>
    </div>


    <!-- KPIs PRINCIPALES -->
    <div class="oc-card mb-section fade-up delay-1">
        <div class="oc-card-header">
            <span class="oc-card-header__title"><i class="fa-solid fa-grip"></i> Resumen general</span>
        </div>
        <div class="oc-card-body" style="padding: 24px;">
            <div class="grid-stats">
                <a href="<?= BASE_URL ?>/projects/list_project.php" class="kpi-card-link">
                    <div class="kpi-card kpi-card--green">
                        <i class="material-symbols-rounded kpi-icon">business_center</i>
                        <div class="kpi-card__label">Proyectos</div>
                        <div class="kpi-card__value"><?= $proyectos_vigentes ?></div>
                        <div class="kpi-card__sub">
                            <?= $proyectos_total ?> totales &nbsp;·&nbsp;
                            <span><?= $proyectos_terminados ?> terminados</span>
                        </div>
                    </div>
                </a>

                <a href="<?= BASE_URL ?>/projects/list_obras.php" class="kpi-card-link">
                    <div class="kpi-card kpi-card--amber">
                        <i class="material-symbols-rounded kpi-icon">apartment</i>
                        <div class="kpi-card__label">Obras Registradas</div>
                        <div class="kpi-card__value"><?= $obras_total ?></div>
                        <div class="kpi-card__sub">Frentes de trabajo activos</div>
                    </div>
                </a>

                <a href="<?= BASE_URL ?>/orders/list_oc.php" class="kpi-card-link">
                    <div class="kpi-card kpi-card--blue">
                        <i class="material-symbols-rounded kpi-icon">receipt_long</i>
                        <div class="kpi-card__label">Órdenes de Compra</div>
                        <div class="kpi-card__value"><?= $oc_total ?></div>
                        <div class="kpi-card__sub">Monto: <?= fmt($oc_monto) ?></div>
                    </div>
                </a>

                <a href="<?= BASE_URL ?>/orders/list_requis.php" class="kpi-card-link">
                    <div class="kpi-card kpi-card--purple">
                        <i class="material-symbols-rounded kpi-icon">assignment</i>
                        <div class="kpi-card__label">Requisiciones</div>
                        <div class="kpi-card__value"><?= $req_total ?></div>
                        <div class="kpi-card__sub"><?= $req_pendientes ?> pendientes de revisión</div>
                    </div>
                </a>

                <div class="kpi-card kpi-card--red">
                    <i class="material-symbols-rounded kpi-icon">handshake</i>
                    <div class="kpi-card__label">Subcontratos</div>
                    <div class="kpi-card__value"><?= $subcontratos_total ?></div>
                    <div class="kpi-card__sub">Contratos externos vigentes</div>
                </div>

                <a href="<?= BASE_URL ?>/activos/list_activos.php" class="kpi-card-link">
                    <div class="kpi-card kpi-card--amber">
                        <i class="material-symbols-rounded kpi-icon">fact_check</i>
                        <div class="kpi-card__label">Activos</div>
                        <div class="kpi-card__value"><?= $activos_activos ?></div>
                        <div class="kpi-card__sub">Valor: <?= fmt($activos_valor_total) ?></div>
                    </div>
                </a>

                <a href="<?= BASE_URL ?>/users/list_users.php" class="kpi-card-link">
                    <div class="kpi-card kpi-card--purple">
                        <i class="material-symbols-rounded kpi-icon">person</i>
                        <div class="kpi-card__label">Usuarios</div>
                        <div class="kpi-card__value"><?= $usuarios_activos ?></div>
                        <div class="kpi-card__sub">Personal en el sistema</div>
                    </div>
                </a>
            </div>
        </div>
    </div>

    <!-- OC ESTADOS -->
    <div class="oc-card mb-section fade-up delay-2">
        <div class="oc-card-header">
            <span class="oc-card-header__title"><i class="fa-solid fa-cart-arrow-down"></i> Órdenes de compra · estado actual</span>
        </div>
        <div class="oc-card-body" style="padding: 24px;">
            <div class="status-metric-group">

                <div class="status-metric-item">
                    <span class="status-badge status-badge--pendiente">
                        <i class="fa-regular fa-clock"></i> Pendientes
                    </span>
                    <span class="status-metric-count"><?= $oc_pendientes ?></span>
                </div>

                <div class="status-metric-item">
                    <span class="status-badge status-badge--revisado">
                        <i class="fa-solid fa-circle-check"></i> Revisadas
                    </span>
                    <span class="status-metric-count"><?= $oc_revisadas ?></span>
                </div>

                <div class="status-metric-item">
                    <span class="status-badge status-badge--aprobado">
                        <i class="fa-solid fa-circle-check"></i> Aprobadas
                    </span>
                    <span class="status-metric-count"><?= $oc_aprobadas ?></span>
                </div>

                <div class="status-metric-item">
                    <span class="status-badge status-badge--rechazado">
                        <i class="fa-solid fa-circle-xmark"></i> Rechazadas
                    </span>
                    <span class="status-metric-count"><?= $oc_rechazadas ?></span>
                </div>

                <div class="status-metric-item">
                    <span class="status-badge status-badge--pagado">
                        <i class="fa-solid fa-circle-dollar-to-slot"></i> Pagadas
                    </span>
                    <span class="status-metric-count"><?= $oc_pagadas ?></span>
                </div>

                <div class="status-metric-item">
                    <span class="status-badge status-badge--devuelto">
                        <i class="fa-solid fa-rotate-left"></i> Devueltas
                    </span>
                    <span class="status-metric-count"><?= $oc_devueltas ?></span>
                </div>

            </div>
        </div>
    </div>

    <!-- GRÁFICO OC ANCHO COMPLETO -->
    <div class="mb-section">
        <div class="panel fade-up delay-2">
            <div class="panel-head">
                <div class="panel-title">
                    <i class="fa-solid fa-chart-bar" style="margin-right:8px;"></i>
                    Órdenes de Compra · últimos 6 meses
                </div>
            </div>
            <div class="panel-body">
                <canvas id="chartOC" height="90"></canvas>
            </div>
        </div>
    </div>

    <!-- DONA OC + ÚLTIMAS OC + PROYECTOS A VENCER -->
    <div style="display:grid;grid-template-columns:1fr 2fr 1fr;gap:16px;" class="mb-section grid-triple">

        <div class="panel fade-up delay-2">
            <div class="panel-head">
                <div class="panel-title"><i class="fa-solid fa-chart-pie" style="margin-right:8px;"></i>Estado OC</div>
            </div>
            <div class="panel-body" style="display:flex;flex-direction:column;align-items:center;gap:20px;">
                <div class="donut-wrap" style="width:160px;height:160px;">
                    <canvas id="chartDonut" width="160" height="160"></canvas>
                    <div class="donut-center">
                        <div class="donut-center-val"><?= $oc_total ?></div>
                        <div class="donut-center-lbl">total OC</div>
                    </div>
                </div>
                <div style="width:100%">
                    <?php

                    $oc_legend = [
                        ['Pendientes', $oc_pendientes, 'var(--gold)'],
                        ['Aprobadas',  $oc_aprobadas,  'var(--accent)'],
                        ['Rechazadas', $oc_rechazadas, 'var(--rose)'],
                        ['Pagadas',    $oc_pagadas,    'var(--sky)'],
                    ];
                    foreach ($oc_legend as [$lbl, $val, $col]):
                    ?>
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                            <div style="display:flex;align-items:center;gap:8px;">
                                <span style="width:8px;height:8px;border-radius:50%;background:<?= $col ?>;display:inline-block;flex-shrink:0;"></span>
                                <span style="font-size:.78rem;"><?= $lbl ?></span>
                            </div>
                            <span style="font-size:.8rem;font-weight:600;"><?= $val ?></span>
                        </div>
                    <?php

                    endforeach; ?>
                </div>
            </div>
        </div>

        <div class="panel fade-up delay-3">
            <div class="panel-head">
                <div class="panel-title"><i class="fa-solid fa-receipt" style="color:var(--sky);margin-right:8px;"></i>Últimas Órdenes de Compra</div>
                <a href="<?= BASE_URL ?>/orders/list_oc.php" style="font-size:.74rem;color:var(--accent);text-decoration:none;">Ver todas →</a>
            </div>
            <div class="panel-body" style="padding:0;">
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th>Folio</th>
                            <th>Solicitante</th>
                            <th>Proveedor</th>
                            <th>Total</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php

                        foreach ($ultimas_oc as $oc):
                            $estado = $oc['estado'];
                            $pill_class = match ($estado) {
                                'pendiente'          => 'pill-gold',
                                'aprobado'           => 'pill-green',
                                'rechazado'          => 'pill-rose',
                                'pagado'             => 'pill-sky',
                                'comprobante_subido' => 'pill-sky',
                                default              => 'pill-purple',
                            };
                            $estado_label = str_replace('_', ' ', ucfirst($estado));
                        ?>
                            <tr>
                                <td><span style="font-size:.78rem;"><?= htmlspecialchars($oc['folio']) ?></span></td>
                                <td style="max-width:120px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($oc['solicitante']) ?></td>
                                <td style="max-width:120px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($oc['proveedor']) ?></td>
                                <td style="font-weight:600;"><?= fmt($oc['total']) ?></td>
                                <td><span class="pill <?= $pill_class ?>"><?= $estado_label ?></span></td>
                            </tr>
                        <?php

                        endforeach; ?>
                        <?php

                        if (empty($ultimas_oc)): ?>
                            <tr>
                                <td colspan="5" style="text-align:center;padding:24px;">Sin registros</td>
                            </tr>
                        <?php

                        endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel fade-up delay-4">
            <div class="panel-head">
                <div class="panel-title"><i class="fa-solid fa-bell" style="color:var(--rose);margin-right:8px;"></i>Vencen en 30 días</div>
            </div>
            <div class="panel-body">
                <?php

                if (empty($proximos_vencer)): ?>
                    <div style="text-align:center;color:var(--ink2);padding:30px 0;font-size:.83rem;">
                        <i class="fa-solid fa-circle-check" style="font-size:1.8rem;color:var(--accent);display:block;margin-bottom:8px;"></i>
                        Sin proyectos próximos a vencer
                    </div>
                <?php

                else: ?>
                    <?php

                    foreach ($proximos_vencer as $p):
                        $dias = $p['dias_restantes'];
                        $color = $dias <= 7 ? 'var(--rose)' : ($dias <= 15 ? 'var(--gold)' : 'var(--sky)');
                    ?>
                        <div class="alert-row">
                            <div class="alert-days" style="color:<?= $color ?>">
                                <?= $dias ?>
                                <small>días</small>
                            </div>
                            <div class="alert-info">
                                <div class="alert-name"><?= htmlspecialchars($p['nombre_proyecto']) ?></div>
                                <div class="alert-client"><?= htmlspecialchars($p['cliente'] ?? 'Sin cliente') ?> · <?= date('d/m/Y', strtotime($p['fecha_fin'])) ?></div>
                            </div>
                        </div>
                    <?php

                    endforeach; ?>
                <?php

                endif; ?>
            </div>
        </div>

    </div>

    <!-- CONDICIÓN ACTIVOS + DESGLOSE ACTIVOS -->
    <div class="grid-2 mb-section">

        <div class="panel fade-up delay-2">
            <div class="panel-head">
                <div class="panel-title"><i class="fa-solid fa-wave-square" style="color:var(--accent);margin-right:8px;"></i>Condición de Activos</div>
                <div style="font-size:.75rem;"><?= $activos_activos ?> activos totales</div>
            </div>
            <div class="panel-body">
                <canvas id="chartCondicion" height="180"></canvas>
            </div>
        </div>

        <div class="panel fade-up delay-3">
            <div class="panel-head">
                <div class="panel-title"><i class="fa-solid fa-table-list" style="color:var(--gold);margin-right:8px;"></i>Resumen de Activos por Tipo</div>
                <a href="<?= BASE_URL ?>/activos/list_activos.php" style="font-size:.74rem;color:var(--accent);text-decoration:none;">Ver todos →</a>
            </div>
            <div class="panel-body" style="padding:0;">
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th>Código</th>
                            <th style="text-align:right;">Cantidad</th>
                            <th style="text-align:right;">Valor Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php

                        foreach ($activos_por_tipo as $i => $tipo):
                            $c = $tipo_colores[$i % count($tipo_colores)];
                        ?>
                            <tr>
                                <td>
                                    <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:<?= $c[0] ?>;margin-right:8px;"></span>
                                    <?= htmlspecialchars($tipo['tipo']) ?>
                                </td>
                                <td style="font-size:.78rem;"><?= htmlspecialchars($tipo['prefijo']) ?></td>
                                <td style="text-align:right;font-weight:600;"><?= $tipo['cantidad'] ?></td>
                                <td style="text-align:right;font-weight:600;"><?= fmt($tipo['valor_total']) ?></td>
                            </tr>
                        <?php

                        endforeach; ?>
                        <?php

                        if (empty($activos_por_tipo)): ?>
                            <tr>
                                <td colspan="4" style="text-align:center;padding:24px;">Sin registros</td>
                            </tr>
                        <?php

                        endif; ?>
                    </tbody>
                    <?php

                    if (!empty($activos_por_tipo)): ?>
                        <tfoot>
                            <tr>
                                <td colspan="2" style="font-weight:600;font-size:.78rem;padding:10px 12px;color:var(--secondary-color);">TOTAL</td>
                                <td style="text-align:right;font-weight:700;color:var(--secondary-color);"><?= array_sum(array_column($activos_por_tipo, 'cantidad')) ?></td>
                                <td style="text-align:right;font-weight:700;color:var(--secondary-color);"><?= fmt($activos_valor_total) ?></td>
                            </tr>
                        </tfoot>
                    <?php

                    endif; ?>
                </table>
            </div>
        </div>
    </div> <!-- /grid-triple -->
</div> <!-- /orders-page-container -->

<script>
    (function tick() {
        const el = document.getElementById('reloj');
        if (el) el.textContent = new Date().toLocaleTimeString('es-MX', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
        setTimeout(tick, 1000);
    })();

    const C = {
        accent: '#407656', // GECO Green
        gold: '#d97706', // Amber-600
        rose: '#e11d48', // Rose-600
        sky: '#2563eb', // Blue-600
        purple: '#7c3aed', // Purple-600
        ink2: '#6b7280', // Gray-500
        grid: 'rgba(0,0,0,.04)',
        navy: '#374151' // Gray-700
    };

    Chart.defaults.color = C.navy;
    Chart.defaults.font.family = "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";

    // 1. OC por mes
    const ocData = <?= json_encode($oc_por_mes) ?>;
    const ctxOC = document.getElementById('chartOC').getContext('2d');

    const gradientBar = ctxOC.createLinearGradient(0, 0, 0, 400);
    gradientBar.addColorStop(0, 'rgba(37, 99, 235, 0.4)');
    gradientBar.addColorStop(1, 'rgba(37, 99, 235, 0.02)');

    const gradientLine = ctxOC.createLinearGradient(0, 0, 0, 400);
    gradientLine.addColorStop(0, 'rgba(64, 118, 86, 0.2)');
    gradientLine.addColorStop(1, 'rgba(64, 118, 86, 0)');

    new Chart(ctxOC, {
        type: 'bar',
        data: {
            labels: ocData.map(d => d.mes),
            datasets: [{
                label: 'Cantidad OC',
                data: ocData.map(d => d.total),
                backgroundColor: gradientBar,
                borderColor: C.sky,
                borderWidth: 2,
                borderRadius: 8,
                yAxisID: 'y',
            }, {
                label: 'Monto ($)',
                data: ocData.map(d => parseFloat(d.monto)),
                type: 'line',
                borderColor: C.accent,
                backgroundColor: gradientLine,
                borderWidth: 3,
                pointRadius: 5,
                pointBackgroundColor: C.accent,
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                tension: 0.4,
                fill: true,
                yAxisID: 'y1',
            }]
        },
        options: {
            responsive: true,
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        boxWidth: 12,
                        usePointStyle: true,
                        padding: 20
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(255, 255, 255, 0.9)',
                    titleColor: '#1a1a1a',
                    bodyColor: '#666',
                    borderColor: '#e1e1e1',
                    borderWidth: 1,
                    padding: 12,
                    displayColors: true,
                    callbacks: {
                        label: ctx => ctx.datasetIndex === 1 ?
                            ' Monto: $' + Number(ctx.raw).toLocaleString() : ' Cantidad: ' + ctx.raw + ' OC'
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        font: {
                            weight: '500'
                        }
                    }
                },
                y: {
                    grid: {
                        color: C.grid
                    },
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    },
                    title: {
                        display: true,
                        text: 'Cantidad'
                    }
                },
                y1: {
                    position: 'right',
                    grid: {
                        drawOnChartArea: false
                    },
                    beginAtZero: true,
                    ticks: {
                        callback: v => '$' + Number(v / 1000).toLocaleString() + 'k'
                    },
                    title: {
                        display: true,
                        text: 'Monto (MXN)'
                    }
                }
            }
        }
    });

    // 2. Donut OC
    new Chart(document.getElementById('chartDonut'), {
        type: 'doughnut',
        data: {
            labels: ['Pendientes', 'Aprobadas', 'Rechazadas', 'Pagadas'],
            datasets: [{
                data: [
                    <?= $oc_pendientes ?>,
                    <?= $oc_aprobadas ?>,
                    <?= $oc_rechazadas ?>,
                    <?= $oc_pagadas ?>
                ],
                backgroundColor: [C.gold, C.accent, C.rose, C.sky],
                borderWidth: 0,
                hoverOffset: 12,
                borderRadius: 4
            }]
        },
        options: {
            cutout: '75%',
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(255, 255, 255, 0.9)',
                    titleColor: '#1a1a1a',
                    bodyColor: '#666',
                    borderWidth: 1,
                    borderColor: '#eee',
                    padding: 10
                }
            },
            responsive: true,
        }
    });

    // 3. Condición activos
    new Chart(document.getElementById('chartCondicion'), {
        type: 'bar',
        data: {
            labels: ['Bueno', 'Regular', 'Malo'],
            datasets: [{
                data: [
                    <?= $condicion_activos['bueno'] ?>,
                    <?= $condicion_activos['regular'] ?>,
                    <?= $condicion_activos['malo'] ?>
                ],
                backgroundColor: [
                    'rgba(22, 163, 74, 0.8)',
                    'rgba(217, 119, 6, 0.8)',
                    'rgba(225, 29, 72, 0.8)'
                ],
                borderColor: [
                    '#16a34a',
                    '#d97706',
                    '#e11d48'
                ],
                borderWidth: 2,
                borderRadius: 10,
                barThickness: 25
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(255, 255, 255, 0.9)',
                    titleColor: '#1a1a1a',
                    bodyColor: '#666',
                    borderWidth: 1,
                    borderColor: '#eee'
                }
            },
            scales: {
                x: {
                    grid: {
                        color: C.grid
                    },
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                },
                y: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        font: {
                            weight: '600'
                        }
                    }
                }
            }
        }
    });
</script>

<?php include __DIR__ . "/includes/footer.php"; ?>