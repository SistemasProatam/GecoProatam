<?php
require_once "config.php";
require_once "includes/session_manager.php";
require_once "includes/check_session.php";

checkSession();
preventCaching();

include 'includes/navbar.php';
?>

<div class="orders-page-container">

  <!-- Page Header -->
  <div class="orders-page-header mb-4">
    <div class="orders-page-header-info">
      <nav class="orders-breadcrumb">
        <a href="<?= BASE_URL ?>/index.php">Inicio</a>
        <span class="separator">›</span>
        <span>Aviso de Privacidad</span>
      </nav>
      <h1 class="orders-page-title">Aviso de Privacidad</h1>
    </div>
  </div>

  <!-- 1. Identidad -->
  <div class="oc-card orders-card--no-hover mb-4">
    <div class="oc-card-header">
      <span class="oc-card-header__title">
        <i class="fa-solid fa-building"></i>
        1. Identidad y Domicilio del Responsable
      </span>
    </div>
    <div class="oc-card-body">
      <p class="oc-card-intro">
        <strong>PROATAM S.A. DE C.V.</strong> (en adelante, "PROATAM"), con domicilio en Carretera
        Villahermosa-Cárdenas Km 6.5, Col. Anacleto Canabal 2da Sección, Villahermosa, Tabasco, México, es el
        responsable del uso y protección de sus datos personales.
      </p>
    </div>
  </div>

  <!-- 2. Finalidades -->
  <div class="oc-card orders-card--no-hover mb-4">
    <div class="oc-card-header">
      <span class="oc-card-header__title">
        <i class="fa-solid fa-bullseye"></i>
        2. Finalidades del Tratamiento
      </span>
    </div>
    <div class="oc-card-body">
      <p class="oc-card-intro">
        Los datos personales que recabamos de usted, los utilizaremos para las siguientes finalidades que son
        necesarias para el servicio que solicita:
      </p>
      <div class="row g-3">
        <div class="col-md-6">
          <div class="oc-form-subsection" style="margin-top: 0; padding-top: 0; border-top: none;">
            <p class="oc-form-subsection__title"><i class="fa-solid fa-briefcase"></i> Operación Administrativa</p>
            <p class="oc-card-intro mb-0">Gestión de activos, órdenes de compra, requisiciones y control de proyectos internos.</p>
          </div>
        </div>
        <div class="col-md-6">
          <div class="oc-form-subsection" style="margin-top: 0; padding-top: 0; border-top: none;">
            <p class="oc-form-subsection__title"><i class="fa-solid fa-users"></i> Gestión de RRHH</p>
            <p class="oc-card-intro mb-0">Administración de expedientes de personal, nóminas, capacitaciones y seguridad industrial.</p>
          </div>
        </div>
        <div class="col-md-6">
          <div class="oc-form-subsection" style="margin-top: 0; padding-top: 0; border-top: none;">
            <p class="oc-form-subsection__title"><i class="fa-solid fa-shield-halved"></i> Seguridad</p>
            <p class="oc-card-intro mb-0">Control de acceso a las instalaciones y monitoreo de seguridad en sitio.</p>
          </div>
        </div>
        <div class="col-md-6">
          <div class="oc-form-subsection" style="margin-top: 0; padding-top: 0; border-top: none;">
            <p class="oc-form-subsection__title"><i class="fa-solid fa-truck"></i> Proveeduría</p>
            <p class="oc-card-intro mb-0">Evaluación, alta y gestión de pagos a proveedores y contratistas.</p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- 3. Datos Recabados -->
  <div class="oc-card orders-card--no-hover mb-4">
    <div class="oc-card-header">
      <span class="oc-card-header__title">
        <i class="fa-solid fa-clipboard-list"></i>
        3. Datos Personales Recabados
      </span>
    </div>
    <div class="oc-card-body">
      <p class="oc-card-intro">
        Para llevar a cabo las finalidades descritas en el presente aviso de privacidad, utilizaremos los
        siguientes datos personales:
      </p>
      <div class="row g-4">
        <div class="col-md-6">
          <p class="oc-form-subsection__title"><i class="fa-solid fa-id-card"></i> Datos de Identificación</p>
          <ul class="list-unstyled mb-0" style="display: flex; flex-direction: column; gap: 0.4rem;">
            <li style="display: flex; align-items: center; gap: 0.5rem; font-size: var(--fs-sm); color: var(--s-800);">
              <i class="fa-solid fa-circle-check" style="color: var(--p-500); font-size: 0.8rem;"></i>
              Nombre completo
            </li>
            <li style="display: flex; align-items: center; gap: 0.5rem; font-size: var(--fs-sm); color: var(--s-800);">
              <i class="fa-solid fa-circle-check" style="color: var(--p-500); font-size: 0.8rem;"></i>
              Firma autógrafa
            </li>
            <li style="display: flex; align-items: center; gap: 0.5rem; font-size: var(--fs-sm); color: var(--s-800);">
              <i class="fa-solid fa-circle-check" style="color: var(--p-500); font-size: 0.8rem;"></i>
              Identificación oficial
            </li>
            <li style="display: flex; align-items: center; gap: 0.5rem; font-size: var(--fs-sm); color: var(--s-800);">
              <i class="fa-solid fa-circle-check" style="color: var(--p-500); font-size: 0.8rem;"></i>
              RFC / CURP
            </li>
          </ul>
        </div>
        <div class="col-md-6">
          <p class="oc-form-subsection__title"><i class="fa-solid fa-address-book"></i> Datos de Contacto</p>
          <ul class="list-unstyled mb-0" style="display: flex; flex-direction: column; gap: 0.4rem;">
            <li style="display: flex; align-items: center; gap: 0.5rem; font-size: var(--fs-sm); color: var(--s-800);">
              <i class="fa-solid fa-circle-check" style="color: var(--p-500); font-size: 0.8rem;"></i>
              Correo electrónico
            </li>
            <li style="display: flex; align-items: center; gap: 0.5rem; font-size: var(--fs-sm); color: var(--s-800);">
              <i class="fa-solid fa-circle-check" style="color: var(--p-500); font-size: 0.8rem;"></i>
              Teléfono
            </li>
            <li style="display: flex; align-items: center; gap: 0.5rem; font-size: var(--fs-sm); color: var(--s-800);">
              <i class="fa-solid fa-circle-check" style="color: var(--p-500); font-size: 0.8rem;"></i>
              Domicilio particular
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>

  <!-- 4. Derechos ARCO -->
  <div class="oc-card orders-card--no-hover mb-4">
    <div class="oc-card-header">
      <span class="oc-card-header__title">
        <i class="fa-solid fa-scale-balanced"></i>
        4. Derechos ARCO
      </span>
    </div>
    <div class="oc-card-body">
      <p class="oc-card-intro mb-0">
        Usted tiene derecho a conocer qué datos personales tenemos de usted, para qué los utilizamos y las
        condiciones del uso que les damos (<strong>Acceso</strong>). Asimismo, es su derecho solicitar la corrección de su
        información personal en caso de que esté desactualizada, sea inexacta o incompleta (<strong>Rectificación</strong>);
        que la eliminemos de nuestros registros o bases de datos cuando considere que la misma no está
        siendo utilizada conforme a los principios, deberes y obligaciones previstos en la normativa
        (<strong>Cancelación</strong>); así como oponerse al uso de sus datos personales para fines específicos (<strong>Oposición</strong>).
        Estos derechos se conocen como derechos ARCO.
      </p>
    </div>
  </div>

  <!-- Contacto -->
  <div class="orders-info-banner orders-info-banner--green mb-5">
    <i class="fa-solid fa-envelope-open-text" style="font-size: 1.2rem; margin-top: 0.1rem;"></i>
    <div>
      <p class="mb-1" style="font-weight: 700; font-size: var(--fs-sm); color: var(--s-800);">¿Dudas sobre su privacidad?</p>
      <p class="mb-1" style="font-size: var(--fs-sm); color: var(--gray-600);">Para ejercer sus derechos ARCO o resolver dudas sobre el tratamiento de sus datos personales, puede contactar al Departamento de Sistemas:</p>
      <a href="mailto:sistemas@proatam.com" style="font-size: var(--fs-sm); color: var(--p-600); font-weight: 700; text-decoration: none;">sistemas@proatam.com</a>
    </div>
  </div>

</div>

<?php include 'includes/footer.php'; ?>