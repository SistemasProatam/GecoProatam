<?php
require_once "config.php";
require_once "includes/session_manager.php";
require_once "includes/check_session.php";

// No siempre es necesario estar logueado para ver políticas, 
// pero en este sistema parece ser todo interno.
// Si se desea acceso público, se debería quitar checkSession().
checkSession();
preventCaching();

include 'includes/navbar.php'; 
?>

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
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .info-list li i {
        color: var(--primary-green);
        font-size: 0.5rem;
    }

    /* Contacto */
    .contact-box {
        text-align: center;
        padding: 3rem;
        background: linear-gradient(135deg, var(--primary-green), var(--light-green));
        color: white;
        border-radius: 16px;
        margin-top: 2rem;
    }

    .contact-box i {
        font-size: 2.5rem;
        margin-bottom: 1rem;
        display: block;
    }

    .contact-box a {
        color: white;
        text-decoration: underline;
        font-weight: 600;
        font-size: 1.2rem;
    }
</style>

<!-- HERO SECTION -->
<div class="hero-section">
    <div class="container hero-content">
        <div class="breadcrumb-custom">
            <a href="<?= BASE_URL ?>/index.php"><i class="bi bi-house-door"></i> Inicio</a>
            <span>/</span>
            <span>Aviso de Privacidad</span>
        </div>
        <h1 class="hero-title">Aviso de Privacidad</h1>
    </div>
</div>

<div class="content-wrapper">
    <div class="form-container">
        <div class="form-body py-5">

            <!-- 1. Identidad -->
            <div class="privacy-card">
                <div class="section-title">
                    <i class="bi bi-building policy-icon"></i>
                    1. Identidad y Domicilio del Responsable
                </div>
                <p>
                    <strong>PROATAM S.A. DE C.V.</strong> (en adelante, "PROATAM"), con domicilio en Carretera
                    Villahermosa-Cárdenas Km 6.5, Col. Anacleto Canabal 2da Sección, Villahermosa, Tabasco, México, es el
                    responsable del uso y protección de sus datos personales.
                </p>
            </div>

            <!-- 2. Finalidades -->
            <div class="privacy-card">
                <div class="section-title">
                    <i class="bi bi-bullseye policy-icon"></i>
                    2. Finalidades del Tratamiento
                </div>
                <p>Los datos personales que recabamos de usted, los utilizaremos para las siguientes finalidades que son
                    necesarias para el servicio que solicita:</p>

                <div class="row g-3 mt-2">
                    <div class="col-md-6">
                        <div class="content-item">
                            <strong>Operación Administrativa</strong>
                            Gestión de activos, órdenes de compra, requisiciones y control de proyectos internos.
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="content-item">
                            <strong>Gestión de RRHH</strong>
                            Administración de expedientes de personal, nóminas, capacitaciones y seguridad industrial.
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="content-item">
                            <strong>Seguridad</strong>
                            Control de acceso a las instalaciones y monitoreo de seguridad en sitio.
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="content-item">
                            <strong>Proveeduría</strong>
                            Evaluación, alta y gestión de pagos a proveedores y contratistas.
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. Datos Recabados -->
            <div class="privacy-card">
                <div class="section-title">
                    <i class="bi bi-clipboard-data policy-icon"></i>
                    3. Datos Personales Recabados
                </div>
                <p>Para llevar a cabo las finalidades descritas en el presente aviso de privacidad, utilizaremos los
                    siguientes datos personales:</p>

                <div class="info-list">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Datos de Identificación</h6>
                            <ul>
                                <li><i class="bi bi-circle-fill"></i> Nombre completo</li>
                                <li><i class="bi bi-circle-fill"></i> Firma autógrafa</li>
                                <li><i class="bi bi-circle-fill"></i> Identificación oficial</li>
                                <li><i class="bi bi-circle-fill"></i> RFC / CURP</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>Datos de Contacto</h6>
                            <ul>
                                <li><i class="bi bi-circle-fill"></i> Correo electrónico</li>
                                <li><i class="bi bi-circle-fill"></i> Teléfono</li>
                                <li><i class="bi bi-circle-fill"></i> Domicilio particular</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. Derechos ARCO -->
            <div class="privacy-card">
                <div class="section-title">
                    <i class="bi bi-shield-check policy-icon"></i>
                    4. Derechos ARCO
                </div>
                <p>
                    Usted tiene derecho a conocer qué datos personales tenemos de usted, para qué los utilizamos y las
                    condiciones del uso que les damos (Acceso). Asimismo, es su derecho solicitar la corrección de su
                    información personal en caso de que esté desactualizada, sea inexacta o incompleta (Rectificación);
                    que la eliminemos de nuestros registros o bases de datos cuando considere que la misma no está
                    siendo utilizada conforme a los principios, deberes y obligaciones previstos en la normativa
                    (Cancelación); así como oponerse al uso de sus datos personales para fines específicos (Oposición).
                    Estos derechos se conocen como derechos ARCO.
                </p>
            </div>

            <!-- Contacto -->
            <div class="contact-box">
                <i class="bi bi-envelope-at"></i>
                <h4 class="mb-3">¿Dudas sobre su privacidad?</h4>
                <p class="mb-3">
                    Para ejercer sus derechos ARCO o resolver dudas sobre el tratamiento de sus datos personales,
                    puede contactar al Departamento de Sistemas:
                </p>
                <a href="mailto:sistemas@proatam.com">sistemas@proatam.com</a>
            </div>

        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>