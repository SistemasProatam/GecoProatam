<?php
require_once __DIR__ . '/config.php';

session_start();
$page_title = "Aviso de Privacidad";
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - PROATAM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/new_order.css">
    <link rel="icon" href="<?= BASE_URL ?>/assets/img/chinior.ico" type="image/x-icon">


    <style>
        :root {
            --primary-green: #3f7555;
            --light-green: #5a9770;
            --bg-light: #f8f9fa;
            --border-color: #e0e0e0;
        }

        .form-body {
            padding: 0 2.5rem 2rem;
            font-family: "Montserrat", sans-serif;
        }

        /* Tarjetas de sección */
        .privacy-card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }

        .privacy-card:hover {
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
        }

        .section-title {
            color: var(--primary-green);
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 1.2rem;
            display: flex;
            align-items: center;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--border-color);
        }

        .policy-icon {
            font-size: 1.5rem;
            color: var(--primary-green);
            margin-right: 12px;
            background: rgba(63, 117, 85, 0.1);
            padding: 10px;
            border-radius: 10px;
        }

        /* Items de contenido */
        .content-item {
            padding: 1rem;
            margin-bottom: 0.8rem;
            background: var(--bg-light);
            border-radius: 8px;
            border-left: 3px solid var(--primary-green);
        }

        .content-item strong {
            color: var(--primary-green);
            display: block;
            margin-bottom: 0.3rem;
        }

        /* Listas */
        .info-list {
            background: var(--bg-light);
            border-radius: 8px;
            padding: 1.2rem;
        }

        .info-list h6 {
            color: var(--primary-green);
            font-weight: 600;
            margin-bottom: 0.8rem;
        }

        .info-list ul {
            list-style: none;
            padding-left: 0;
            margin-bottom: 0;
        }

        .info-list li {
            padding: 0.4rem 0;
            padding-left: 1.5rem;
            position: relative;
        }

        .info-list li:before {
            content: "•";
            position: absolute;
            left: 0.5rem;
            color: var(--primary-green);
            font-weight: bold;
            font-size: 1.2rem;
        }

        /* Derechos ARCO */
        .arco-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .arco-card {
            background: var(--bg-light);
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 1.2rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .arco-card:hover {
            border-color: var(--primary-green);
            background: white;
            box-shadow: 0 4px 12px rgba(63, 117, 85, 0.15);
        }

        .arco-icon {
            font-size: 2rem;
            color: var(--primary-green);
            margin-bottom: 0.8rem;
        }

        .arco-title {
            font-weight: 600;
            color: var(--primary-green);
            margin-bottom: 0.5rem;
            font-size: 1rem;
        }

        /* Contacto */
        .contact-box {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--light-green) 100%);
            color: white;
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            margin-top: 2rem;
        }

        .contact-box i {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .contact-box a {
            color: white;
            text-decoration: none;
            font-weight: 600;
            font-size: 1.1rem;
            border-bottom: 2px solid rgba(255, 255, 255, 0.4);
            transition: all 0.3s ease;
        }

        .contact-box a:hover {
            border-bottom-color: white;
        }

        /* Badge de actualización */
        .update-badge {
            padding: 0.4rem 1rem;
            color: white;
            border-radius: 20px;
            display: inline-block;
            margin-top: 0.5rem;
            font-size: 0.95rem;
        }

        /* Responsivo */
        @media (max-width: 768px) {
            .form-body {
                padding: 0 1rem 1rem;
            }

            .section-title {
                font-size: 1.1rem;
            }

            .privacy-card {
                padding: 1.5rem;
            }
        }
    </style>
</head>

<body>

    <?php include $_SERVER['DOCUMENT_ROOT'] . "/includes/navbar.php"; ?>

    <!-- HERO SECTION -->
    <div class="hero-section">
        <div class="container hero-content">
            <div class="breadcrumb-custom">
                <a href="index.php"><i class="bi bi-house-door"></i> Inicio</a>
                <span>/</span>
                <a href="#"> Aviso de Privacidad</a>
            </div>

            <div class="row align-items-end">
                <div class="col-lg-8">
                    <h1 class="hero-title">Aviso de Privacidad</h1>
                    <div class="update-badge">
                        Última actualización: 30/01/2026
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="content-wrapper">
        <div class="form-body">

            <!-- Identificación del Responsable -->
            <div class="privacy-card">
                <div class="section-title">
                    <i class="bi bi-building policy-icon"></i>
                    <span>Responsable del Tratamiento de Datos</span>
                </div>
                <p style="font-size: 1.05rem; line-height: 1.7;">
                    <strong>PROATAM S.A. DE C.V.</strong>, con domicilio en Reynosa, Tamaulipas, México,
                    es responsable del tratamiento y protección de los datos personales que usted proporcione
                    a través del Sistema de Gestión de Órdenes de Compra, en cumplimiento con la Ley Federal de
                    Protección de Datos Personales en Posesión de los Particulares.
                </p>
            </div>

            <!-- Finalidades del Tratamiento -->
            <div class="privacy-card">
                <div class="section-title">
                    <i class="bi bi-clipboard-check policy-icon"></i>
                    <span>¿Para qué utilizamos sus datos personales?</span>
                </div>

                <p class="mb-3">Sus datos personales serán utilizados para las siguientes finalidades:</p>

                <div class="content-item">
                    <strong>Gestión de Acceso al Sistema</strong>
                    Crear y administrar su cuenta de usuario, verificar su identidad y controlar el acceso a las funcionalidades del sistema.
                </div>

                <div class="content-item">
                    <strong>Operación del Sistema</strong>
                    Procesar requisiciones, órdenes de compra, proyectos y todas las operaciones relacionadas con su función laboral.
                </div>

                <div class="content-item">
                    <strong>Comunicación</strong>
                    Enviarle notificaciones sobre el estado de sus solicitudes, cambios en el sistema y comunicaciones operativas necesarias.
                </div>

                <div class="content-item">
                    <strong>Seguridad y Auditoría</strong>
                    Mantener registros de las operaciones realizadas para garantizar la trazabilidad y seguridad del sistema.
                </div>
            </div>

            <!-- Datos Personales Recabados -->
            <div class="privacy-card">
                <div class="section-title">
                    <i class="bi bi-person-vcard policy-icon"></i>
                    <span>¿Qué datos personales recabamos?</span>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="info-list">
                            <h6><i class="bi bi-person-badge me-2"></i>Datos de Identificación</h6>
                            <ul>
                                <li>Nombre completo</li>
                                <li>Correo electrónico</li>
                                <li>Teléfono</li>
                                <li>Contacto de emergencia</li>
                                <li>Documentación (Acta de nacimiento, CURP, NSS, etc.)</li>

                            </ul>
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <div class="info-list">
                            <h6><i class="bi bi-briefcase me-2"></i>Datos Laborales</h6>
                            <ul>
                                <li>Departamento</li>
                                <li>Correo electrónico corporativo</li>
                                <li>Credencial corporativa</li>
                                <li>Fecha de ingreso</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Medidas de Seguridad -->
            <div class="privacy-card">
                <div class="section-title">
                    <i class="bi bi-shield-lock policy-icon"></i>
                    <span>Medidas de Seguridad</span>
                </div>

                <p class="mb-3">Para proteger sus datos personales, hemos implementado medidas de seguridad físicas, técnicas y administrativas:</p>

                <div class="row">
                    <div class="col-md-6">
                        <div class="content-item">
                            <strong>Encriptación de Contraseñas</strong>
                            Sus credenciales se almacenan utilizando algoritmos de encriptación avanzados.
                        </div>

                        <div class="content-item">
                            <strong>Conexión Segura</strong>
                            Todas las comunicaciones están protegidas mediante protocolos SSL/TLS.
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="content-item">
                            <strong>Control de Acceso</strong>
                            Sistema de permisos basado en roles para proteger la información.
                        </div>

                        <div class="content-item">
                            <strong>Respaldos Seguros</strong>
                            Copias de seguridad periódicas almacenadas de forma segura.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Derechos ARCO -->
            <div class="privacy-card">
                <div class="section-title">
                    <i class="bi bi-hand-index policy-icon"></i>
                    <span>Sus Derechos (ARCO)</span>
                </div>

                <p class="mb-3">Usted tiene derecho a conocer, acceder, rectificar, cancelar u oponerse al tratamiento de sus datos personales:</p>

                <div class="arco-grid">
                    <div class="arco-card">
                        <div class="arco-icon">
                            <i class="bi bi-search"></i>
                        </div>
                        <div class="arco-title">Acceso</div>
                        <p style="font-size: 0.9rem; margin-bottom: 0;">Conocer qué datos tenemos de usted</p>
                    </div>

                    <div class="arco-card">
                        <div class="arco-icon">
                            <i class="bi bi-pencil"></i>
                        </div>
                        <div class="arco-title">Rectificación</div>
                        <p style="font-size: 0.9rem; margin-bottom: 0;">Corregir datos inexactos</p>
                    </div>

                    <div class="arco-card">
                        <div class="arco-icon">
                            <i class="bi bi-x-circle"></i>
                        </div>
                        <div class="arco-title">Cancelación</div>
                        <p style="font-size: 0.9rem; margin-bottom: 0;">Solicitar eliminación de datos</p>
                    </div>

                    <div class="arco-card">
                        <div class="arco-icon">
                            <i class="bi bi-shield-x"></i>
                        </div>
                        <div class="arco-title">Oposición</div>
                        <p style="font-size: 0.9rem; margin-bottom: 0;">Oponerse a ciertos usos</p>
                    </div>
                </div>
            </div>

            <!-- Transferencias de Datos -->
            <div class="privacy-card">
                <div class="section-title">
                    <i class="bi bi-share policy-icon"></i>
                    <span>¿Con quién compartimos sus datos?</span>
                </div>

                <p class="mb-3">Sus datos personales pueden ser compartidos únicamente en los siguientes casos:</p>

                <div class="content-item">
                    <strong>Autoridades Legales</strong>
                    Cuando sea requerido por ley o autoridad competente.
                </div>

                <div class="content-item">
                    <strong>Proveedores de Servicios</strong>
                    Empresas que nos proporcionan servicios tecnológicos, siempre bajo acuerdos de confidencialidad.
                </div>
            </div>

            <!-- Conservación -->
            <div class="privacy-card">
                <div class="section-title">
                    <i class="bi bi-clock-history policy-icon"></i>
                    <span>¿Por cuánto tiempo conservamos sus datos?</span>
                </div>

                <p style="font-size: 1.05rem; line-height: 1.7;">
                    Sus datos personales serán conservados mientras sea necesario para cumplir con las finalidades
                    descritas en este aviso y durante el tiempo que exijan las disposiciones legales aplicables.
                    Una vez cumplidos estos plazos, los datos serán eliminados de forma segura.
                </p>
            </div>

            <!-- Cambios -->
            <div class="privacy-card">
                <div class="section-title">
                    <i class="bi bi-arrow-repeat policy-icon"></i>
                    <span>Cambios al Aviso de Privacidad</span>
                </div>

                <p style="font-size: 1.05rem; line-height: 1.7;">
                    Nos reservamos el derecho de modificar este aviso de privacidad. Cualquier cambio será
                    notificado a través del sistema y por correo electrónico. Le recomendamos consultar
                    periódicamente este aviso para estar informado sobre cómo protegemos sus datos.
                </p>
            </div>

            <!-- Contacto -->
            <div class="contact-box">
                <i class="bi bi-envelope-at"></i>
                <h4 class="mb-3">Contacto</h4>
                <p class="mb-3">
                    Para ejercer sus derechos ARCO o resolver dudas sobre el tratamiento de sus datos personales,
                    puede contactar al Departamento de Sistemas:
                </p>
                <a href="mailto:sistemas@proatam.com">sistemas@proatam.com</a>
            </div>

        </div>
    </div>

    <?php include __DIR__ . "/includes/footer.php"; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/scripts/session_timeout.js"></script>

</body>

</html>

