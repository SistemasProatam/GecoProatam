<?php
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";
checkSession();
preventCaching();

require_once __DIR__ . "/../conexion.php";

$id = intval($_GET['id']);

// Consulta con JOIN para obtener nombre del departamento
$sql = "SELECT u.*, d.nombre AS departamento
        FROM usuarios u
        LEFT JOIN departamentos d ON u.departamento_id = d.id
        WHERE u.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();

if (!$user) {
    header("Location: list_users.php?error=Usuario no encontrado");
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Detalles del Usuario - <?= htmlspecialchars($user['nombres'] . ' ' . $user['apellidos']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="icon" href="<?= BASE_URL ?>/assets/img/LogoCuadro.ico" type="image/x-icon">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/details.css">
    <style>
        .user-profile-header {
            display: flex;
            align-items: center;
            gap: 20px;
            color: var(--primary);
            background: var(--card);
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border);
            margin-bottom: 2rem;
            border-radius: var(--radius-lg);
            padding: 20px;
        }

        .user-avatar {
            flex-shrink: 0;
        }

        .user-avatar img {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border: 4px solid white;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .user-avatar .avatar-placeholder {
            width: 120px;
            height: 120px;
            border: 4px solid white;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .user-info h1 {
            margin: 0;
            font-size: 1.8rem;
            font-weight: 600;
        }

        .user-info .department {
            font-size: 1.1rem;
            opacity: 0.9;
            margin: 5px 0;
        }

        .user-info .email {
            font-size: 0.95rem;
            opacity: 0.8;
        }

        .info-grid {
            margin-bottom: 0;

        }

        .info-panel {
            margin-bottom: 2rem;
            gap: 1rem;
        }

        .info-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .info-item {
            display: flex;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--border);
        }

        .functions-content {
            padding: 20px;
            min-height: 120px;
        }

        .functions-content p {
            margin: 0;
            line-height: 1.6;
        }

        .documents-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            padding: 20px;
        }

        .document-card {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .document-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .document-icon {
            font-size: 2rem;
            color: #3f7555;
            margin-bottom: 10px;
        }

        .document-card h6 {
            margin: 0 0 10px 0;
            font-size: 0.9rem;
            color: #333;
            font-weight: 600;
            line-height: 1.3;
        }

        .document-actions {
            margin-top: 10px;
        }

        .btn-download {
            background: #667eea;
            border: none;
            padding: 5px 12px;
            font-size: 0.8rem;
        }

        .btn-download:hover {
            background: #5a6fd8;
        }

        .btn-view {
            background: #28a745;
            border: none;
            padding: 5px 12px;
            font-size: 0.8rem;
        }

        .btn-view:hover {
            background: #218838;
        }

        .no-document {
            color: #6c757d;
            font-size: 0.8rem;
        }

        .action-buttons {
            text-align: center;
            margin-top: 30px;
            padding: 20px 0;
        }

        @media (max-width: 768px) {
            .user-profile-header {
                flex-direction: column;
                text-align: center;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .info-item {
                flex-direction: column;
                align-items: flex-start;
            }

            .info-item span:last-child {
                text-align: left;
                margin-top: 5px;
            }

            .documents-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
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
                <a href="list_users.php"> Registro de Usuarios</a>
                <span>/</span>
                <span>Detalles del usuario</span>
            </div>

            <div class="row align-items-end">
                <div class="col-lg-8">
                    <h1 class="hero-title">Detalles del usuario</h1>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="content-wrapper">
        <div class="container">

            <!-- User Profile Header -->
            <div class="user-profile-header">
                <div class="user-avatar">
                    <?php
if ($user['foto_jpg']): ?>
                        <img src="../uploads/usuarios/<?= htmlspecialchars($user['foto_jpg']) ?>"
                            alt="Foto de <?= htmlspecialchars($user['nombres'] . ' ' . $user['apellidos']) ?>"
                            class="rounded-circle">
                    <?php
else: ?>
                        <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center avatar-placeholder">
                            <i class="bi bi-person" style="font-size: 3rem; color: #6c757d;"></i>
                        </div>
                    <?php
endif; ?>
                </div>
                <div class="user-info">
                    <h1><?= htmlspecialchars($user['nombres'] . ' ' . $user['apellidos']) ?></h1>
                </div>
            </div>

            <!-- INFO GRID -->
            <div class="info-grid">

                <!-- InformaciÃ³n Personal -->
                <div class="info-panel mx-1">
                    <div class="panel-header">
                        <div class="panel-icon">
                            <i class="bi bi-person-vcard"></i>
                        </div>
                        <h4>InformaciÃ³n Personal</h4>
                    </div>
                    <ul class="info-list">
                        <li class="info-item">
                            <strong>Nombres:</strong>
                            <span><?= htmlspecialchars($user['nombres'] ?? '-') ?></span>
                        </li>
                        <li class="info-item">
                            <strong>Apellidos:</strong>
                            <span><?= htmlspecialchars($user['apellidos'] ?? '-') ?></span>
                        </li>
                        <li class="info-item">
                            <strong>Correo Corporativo:</strong>
                            <span><?= htmlspecialchars($user['correo_corporativo'] ?? '-') ?></span>
                        </li>
                        <li class="info-item">
                            <strong>Correo Personal:</strong>
                            <span><?= htmlspecialchars($user['correo_personal'] ?? '-') ?></span>
                        </li>
                        <li class="info-item">
                            <strong>TelÃ©fono Personal:</strong>
                            <span><?= htmlspecialchars($user['telefono_personal'] ?? '-') ?></span>
                        </li>
                        <li class="info-item">
                            <strong>Departamento:</strong>
                            <span><?= htmlspecialchars($user['departamento'] ?? '-') ?></span>
                        </li>
                        <li class="info-item">
                            <strong>Fecha de ingreso: </strong>
                            <span><?= htmlspecialchars($user['fecha_ingreso'] ?? '-') ?></span>
                        </li>
                    </ul>
                </div>

                <!-- Contacto de Emergencia -->
                <div class="info-panel mx-1">
                    <div class="panel-header">
                        <div class="panel-icon">
                            <i class="bi bi-telephone"></i>
                        </div>
                        <h4>Contacto de Emergencia</h4>
                    </div>
                    <ul class="info-list">
                        <li class="info-item">
                            <strong>Nombre Completo:</strong>
                            <span><?= htmlspecialchars($user['contacto_emergencia_nombre'] ?? '-') ?></span>
                        </li>
                        <li class="info-item">
                            <strong>Parentesco:</strong>
                            <span><?= htmlspecialchars($user['contacto_emergencia_parentesco'] ?? '-') ?></span>
                        </li>
                        <li class="info-item">
                            <strong>NÃºmero de Celular:</strong>
                            <span><?= htmlspecialchars($user['contacto_emergencia_telefono'] ?? '-') ?></span>
                        </li>
                    </ul>
                </div>

            </div>

            <!-- Funciones y Actividades -->
            <div class="info-panel">
                <div class="panel-header">
                    <div class="panel-icon">
                        <i class="bi bi-list-task"></i>
                    </div>
                    <h4>Funciones y Actividades</h4>
                </div>
                <div class="functions-content">
                    <?php
if (!empty($user['funciones_actividades'])): ?>
                        <p><?= nl2br(htmlspecialchars($user['funciones_actividades'])) ?></p>
                    <?php
else: ?>
                        <p class="text-muted">No se han especificado funciones y actividades.</p>
                    <?php
endif; ?>
                </div>
            </div>

            <!-- Documentos del Expediente -->
            <div class="info-panel">
                <div class="panel-header">
                    <div class="panel-icon">
                        <i class="bi bi-folder"></i>
                    </div>
                    <h4>Documentos del Expediente</h4>
                </div>
                <div class="documents-grid">
                    <?php
$documentos = [
                        'curriculum_pdf' => ['icon' => 'bi-file-earmark-person', 'title' => 'Curriculum Vitae'],
                        'identificacion_pdf' => ['icon' => 'bi-card-checklist', 'title' => 'IdentificaciÃ³n Oficial'],
                        'acta_nacimiento_pdf' => ['icon' => 'bi-file-earmark-text', 'title' => 'Acta de Nacimiento'],
                        'curp_pdf' => ['icon' => 'bi-file-earmark-richtext', 'title' => 'CURP'],
                        'situacion_fiscal_pdf' => ['icon' => 'bi-cash-coin', 'title' => 'Constancia de SituaciÃ³n Fiscal'],
                        'nss_pdf' => ['icon' => 'bi-shield-check', 'title' => 'NÃºmero de Seguro Social'],
                        'comprobante_domicilio_pdf' => ['icon' => 'bi-house', 'title' => 'Comprobante de Domicilio'],
                        'foto_jpg' => ['icon' => 'bi-camera', 'title' => 'Foto'],
                        'comprobante_estudios_pdf' => ['icon' => 'bi-mortarboard', 'title' => 'Ãšltimo Comprobante de Estudios'],
                        'credencial_pdf' => ['icon' => 'bi-person-badge', 'title' => 'Credencial Corporativa'],
                        'acuerdo_confidencialidad_pdf' => ['icon' => 'bi-file-lock', 'title' => 'Acuerdo de Confidencialidad']
                    ];

                    foreach ($documentos as $campo => $info):
                        $archivo = $user[$campo];
                    ?>
                        <div class="document-card">
                            <i class="bi <?= $info['icon'] ?> document-icon"></i>
                            <h6><?= $info['title'] ?></h6>
                            <div class="document-actions">
                                <?php
if ($archivo): ?>
                                    <?php
if ($campo === 'foto_jpg'): ?>
                                        <a href="../uploads/usuarios/<?= htmlspecialchars($archivo) ?>"
                                            target="_blank" class="btn btn-sm btn-view">
                                            <i class="bi bi-eye"></i> Ver
                                        </a>
                                    <?php
else: ?>
                                        <a href="../uploads/usuarios/<?= htmlspecialchars($archivo) ?>"
                                            target="_blank" class="btn btn-sm btn-download">
                                            <i class="bi bi-download"></i> Descargar
                                        </a>
                                    <?php
endif; ?>
                                <?php
else: ?>
                                    <span class="badge bg-secondary no-document">No subido</span>
                                <?php
endif; ?>
                            </div>
                        </div>
                    <?php
endforeach; ?>
                </div>
            </div>

            <!-- Contratos -->
            <div class="info-panel">
                <div class="panel-header">
                    <div class="panel-icon">
                        <i class="bi bi-file-earmark-text"></i>
                    </div>
                    <h4>Contratos</h4>
                </div>
                <div class="documents-grid">
                    <?php
// Obtener contratos del usuario
                    $sql_contratos = "SELECT * FROM contratos_usuario WHERE usuario_id = ? ORDER BY tipo_contrato DESC";
                    $stmt_contratos = $conn->prepare($sql_contratos);
                    $stmt_contratos->bind_param("i", $id);
                    $stmt_contratos->execute();
                    $contratos = $stmt_contratos->get_result();

                    if ($contratos->num_rows > 0):
                        while ($contrato = $contratos->fetch_assoc()):
                    ?>
                            <div class="document-card">
                                <i class="bi bi-file-earmark-pdf document-icon"></i>
                                <h6>Contrato <?= $contrato['tipo_contrato'] ?? 'Sin tipo' ?></h6>
                                <div class="document-actions">
                                    <a href="<?= htmlspecialchars($contrato['ruta_archivo']) ?>"
                                        target="_blank" class="btn btn-sm btn-download">
                                        <i class="bi bi-download"></i> Descargar
                                    </a>
                                </div>
                            </div>
                        <?php
endwhile;
                    else: ?>
                        <div class="col-12">
                            <p class="text-muted">No hay contratos registrados.</p>
                        </div>
                    <?php
endif; ?>
                    <?php
$stmt_contratos->close(); ?>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <a href="list_users.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Volver al Listado
                </a>
            </div>

        </div>
    </div>

    <?php
include __DIR__ . "/../includes/footer.php"; ?>
</body>

</html>



