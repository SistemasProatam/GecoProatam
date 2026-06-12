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
      <p class="text-muted" style="font-size: var(--fs-sm); color: var(--s-600); margin-top: 0.2rem; margin-bottom: 0;">
        <i class="fa-regular fa-calendar-days"></i> Última actualización: 30/01/2026
      </p>
    </div>
  </div>

  <!-- Responsable del Tratamiento de Datos -->
  <div class="oc-card orders-card--no-hover mb-4">
    <div class="oc-card-header">
      <span class="oc-card-header__title">
        <i class="fa-solid fa-building-shield"></i>
        Responsable del Tratamiento de Datos
      </span>
    </div>
    <div class="oc-card-body">
      <p class="oc-card-intro mb-0">
        <strong>PROATAM S.A. DE C.V.</strong>, con domicilio en Reynosa, Tamaulipas, México, es responsable del tratamiento y protección de los datos personales que usted proporcione a través del Sistema de Gestión de Órdenes de Compra, en cumplimiento con la Ley Federal de Protección de Datos Personales en Posesión de los Particulares.
      </p>
    </div>
  </div>

  <!-- Finalidades -->
  <div class="oc-card orders-card--no-hover mb-4">
    <div class="oc-card-header">
      <span class="oc-card-header__title">
        <i class="fa-solid fa-bullseye"></i>
        ¿Para qué utilizamos sus datos personales?
      </span>
    </div>
    <div class="oc-card-body">
      <p class="oc-card-intro">
        Sus datos personales serán utilizados para las siguientes finalidades:
      </p>
      <div class="row g-3">
        <div class="col-md-6">
          <div class="oc-form-subsection" style="margin-top: 0; padding-top: 0; border-top: none; height: 100%;">
            <p class="oc-form-subsection__title"><i class="fa-solid fa-user-lock"></i> Gestión de Acceso al Sistema</p>
            <p class="oc-card-intro mb-0">Crear y administrar su cuenta de usuario, verificar su identidad y controlar el acceso a las funcionalidades del sistema.</p>
          </div>
        </div>
        <div class="col-md-6">
          <div class="oc-form-subsection" style="margin-top: 0; padding-top: 0; border-top: none; height: 100%;">
            <p class="oc-form-subsection__title"><i class="fa-solid fa-gears"></i> Operación del Sistema</p>
            <p class="oc-card-intro mb-0">Procesar requisiciones, órdenes de compra, proyectos y todas las operaciones relacionadas con su función laboral.</p>
          </div>
        </div>
        <div class="col-md-6">
          <div class="oc-form-subsection" style="margin-top: 0; padding-top: 0; border-top: none; height: 100%;">
            <p class="oc-form-subsection__title"><i class="fa-solid fa-envelope"></i> Comunicación</p>
            <p class="oc-card-intro mb-0">Enviarle notificaciones sobre el estado de sus solicitudes, cambios en el sistema y comunicaciones operativas necesarias.</p>
          </div>
        </div>
        <div class="col-md-6">
          <div class="oc-form-subsection" style="margin-top: 0; padding-top: 0; border-top: none; height: 100%;">
            <p class="oc-form-subsection__title"><i class="fa-solid fa-shield-halved"></i> Seguridad y Auditoría</p>
            <p class="oc-card-intro mb-0">Mantener registros de las operaciones realizadas para garantizar la trazabilidad y seguridad del sistema.</p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Datos Recabados -->
  <div class="oc-card orders-card--no-hover mb-4">
    <div class="oc-card-header">
      <span class="oc-card-header__title">
        <i class="fa-solid fa-clipboard-list"></i>
        ¿Qué datos personales recabamos?
      </span>
    </div>
    <div class="oc-card-body">
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
              Correo electrónico
            </li>
            <li style="display: flex; align-items: center; gap: 0.5rem; font-size: var(--fs-sm); color: var(--s-800);">
              <i class="fa-solid fa-circle-check" style="color: var(--p-500); font-size: 0.8rem;"></i>
              Teléfono
            </li>
            <li style="display: flex; align-items: center; gap: 0.5rem; font-size: var(--fs-sm); color: var(--s-800);">
              <i class="fa-solid fa-circle-check" style="color: var(--p-500); font-size: 0.8rem;"></i>
              Contacto de emergencia
            </li>
            <li style="display: flex; align-items: center; gap: 0.5rem; font-size: var(--fs-sm); color: var(--s-800);">
              <i class="fa-solid fa-circle-check" style="color: var(--p-500); font-size: 0.8rem;"></i>
              Documentación (Acta de nacimiento, CURP, NSS, etc.)
            </li>
          </ul>
        </div>
        <div class="col-md-6">
          <p class="oc-form-subsection__title"><i class="fa-solid fa-briefcase"></i> Datos Laborales</p>
          <ul class="list-unstyled mb-0" style="display: flex; flex-direction: column; gap: 0.4rem;">
            <li style="display: flex; align-items: center; gap: 0.5rem; font-size: var(--fs-sm); color: var(--s-800);">
              <i class="fa-solid fa-circle-check" style="color: var(--p-500); font-size: 0.8rem;"></i>
              Departamento
            </li>
            <li style="display: flex; align-items: center; gap: 0.5rem; font-size: var(--fs-sm); color: var(--s-800);">
              <i class="fa-solid fa-circle-check" style="color: var(--p-500); font-size: 0.8rem;"></i>
              Correo electrónico corporativo
            </li>
            <li style="display: flex; align-items: center; gap: 0.5rem; font-size: var(--fs-sm); color: var(--s-800);">
              <i class="fa-solid fa-circle-check" style="color: var(--p-500); font-size: 0.8rem;"></i>
              Credencial corporativa
            </li>
            <li style="display: flex; align-items: center; gap: 0.5rem; font-size: var(--fs-sm); color: var(--s-800);">
              <i class="fa-solid fa-circle-check" style="color: var(--p-500); font-size: 0.8rem;"></i>
              Fecha de ingreso
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>

  <!-- Medidas de Seguridad -->
  <div class="oc-card orders-card--no-hover mb-4">
    <div class="oc-card-header">
      <span class="oc-card-header__title">
        <i class="fa-solid fa-lock-open"></i>
        Medidas de Seguridad
      </span>
    </div>
    <div class="oc-card-body">
      <p class="oc-card-intro">
        Para proteger sus datos personales, hemos implementado medidas de seguridad físicas, técnicas y administrativas:
      </p>
      <div class="row g-3">
        <div class="col-md-6 col-lg-3">
          <div class="oc-form-subsection" style="margin-top: 0; padding-top: 0; border-top: none; height: 100%;">
            <p class="oc-form-subsection__title"><i class="fa-solid fa-key"></i> Encriptación de Contraseñas</p>
            <p class="oc-card-intro mb-0">Sus credenciales se almacenan utilizando algoritmos de encriptación avanzados.</p>
          </div>
        </div>
        <div class="col-md-6 col-lg-3">
          <div class="oc-form-subsection" style="margin-top: 0; padding-top: 0; border-top: none; height: 100%;">
            <p class="oc-form-subsection__title"><i class="fa-solid fa-shield-halved"></i> Conexión Segura</p>
            <p class="oc-card-intro mb-0">Todas las comunicaciones están protegidas mediante protocolos SSL/TLS.</p>
          </div>
        </div>
        <div class="col-md-6 col-lg-3">
          <div class="oc-form-subsection" style="margin-top: 0; padding-top: 0; border-top: none; height: 100%;">
            <p class="oc-form-subsection__title"><i class="fa-solid fa-user-shield"></i> Control de Acceso</p>
            <p class="oc-card-intro mb-0">Sistema de permisos basado en roles para proteger la información.</p>
          </div>
        </div>
        <div class="col-md-6 col-lg-3">
          <div class="oc-form-subsection" style="margin-top: 0; padding-top: 0; border-top: none; height: 100%;">
            <p class="oc-form-subsection__title"><i class="fa-solid fa-database"></i> Respaldos Seguros</p>
            <p class="oc-card-intro mb-0">Copias de seguridad periódicas almacenadas de forma segura.</p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Derechos ARCO -->
  <div class="oc-card orders-card--no-hover mb-4">
    <div class="oc-card-header">
      <span class="oc-card-header__title">
        <i class="fa-solid fa-scale-balanced"></i>
        Sus Derechos (ARCO)
      </span>
    </div>
    <div class="oc-card-body">
      <p class="oc-card-intro">
        Usted tiene derecho a conocer, acceder, rectificar, cancelar u oponerse al tratamiento de sus datos personales:
      </p>
      <div class="row g-3">
        <div class="col-md-6 col-lg-3">
          <div class="oc-form-subsection" style="margin-top: 0; padding-top: 0; border-top: none; height: 100%;">
            <p class="oc-form-subsection__title"><i class="fa-solid fa-eye"></i> Acceso</p>
            <p class="oc-card-intro mb-0">Conocer qué datos tenemos de usted</p>
          </div>
        </div>
        <div class="col-md-6 col-lg-3">
          <div class="oc-form-subsection" style="margin-top: 0; padding-top: 0; border-top: none; height: 100%;">
            <p class="oc-form-subsection__title"><i class="fa-solid fa-pen-to-square"></i> Rectificación</p>
            <p class="oc-card-intro mb-0">Corregir datos inexactos</p>
          </div>
        </div>
        <div class="col-md-6 col-lg-3">
          <div class="oc-form-subsection" style="margin-top: 0; padding-top: 0; border-top: none; height: 100%;">
            <p class="oc-form-subsection__title"><i class="fa-solid fa-trash-can"></i> Cancelación</p>
            <p class="oc-card-intro mb-0">Solicitar eliminación de datos</p>
          </div>
        </div>
        <div class="col-md-6 col-lg-3">
          <div class="oc-form-subsection" style="margin-top: 0; padding-top: 0; border-top: none; height: 100%;">
            <p class="oc-form-subsection__title"><i class="fa-solid fa-hand"></i> Oposición</p>
            <p class="oc-card-intro mb-0">Oponerse a ciertos usos</p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Compartido -->
  <div class="oc-card orders-card--no-hover mb-4">
    <div class="oc-card-header">
      <span class="oc-card-header__title">
        <i class="fa-solid fa-share-nodes"></i>
        ¿Con quién compartimos sus datos?
      </span>
    </div>
    <div class="oc-card-body">
      <p class="oc-card-intro">
        Sus datos personales pueden ser compartidos únicamente en los siguientes casos:
      </p>
      <div class="row g-3">
        <div class="col-md-6">
          <div class="oc-form-subsection" style="margin-top: 0; padding-top: 0; border-top: none; height: 100%;">
            <p class="oc-form-subsection__title"><i class="fa-solid fa-gavel"></i> Autoridades Legales</p>
            <p class="oc-card-intro mb-0">Cuando sea requerido por ley o autoridad competente.</p>
          </div>
        </div>
        <div class="col-md-6">
          <div class="oc-form-subsection" style="margin-top: 0; padding-top: 0; border-top: none; height: 100%;">
            <p class="oc-form-subsection__title"><i class="fa-solid fa-handshake-simple"></i> Proveedores de Servicios</p>
            <p class="oc-card-intro mb-0">Empresas que nos proporcionan servicios tecnológicos, siempre bajo acuerdos de confidencialidad.</p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Conservación y Cambios -->
  <div class="row g-4 mb-4">
    <div class="col-md-6">
      <div class="oc-card orders-card--no-hover h-100 mb-0">
        <div class="oc-card-header">
          <span class="oc-card-header__title">
            <i class="fa-solid fa-hourglass-half"></i>
            ¿Por cuánto tiempo conservamos sus datos?
          </span>
        </div>
        <div class="oc-card-body">
          <p class="oc-card-intro mb-0">
            Sus datos personales serán conservados mientras sea necesario para cumplir con las finalidades descritas en este aviso y durante el tiempo que exijan las disposiciones legales aplicables. Una vez cumplidos estos plazos, los datos serán eliminados de forma segura.
          </p>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="oc-card orders-card--no-hover h-100 mb-0">
        <div class="oc-card-header">
          <span class="oc-card-header__title">
            <i class="fa-solid fa-rotate"></i>
            Cambios al Aviso de Privacidad
          </span>
        </div>
        <div class="oc-card-body">
          <p class="oc-card-intro mb-0">
            Nos reservamos el derecho de modificar este aviso de privacidad. Cualquier cambio será notificado a través del sistema y por correo electrónico. Le recomendamos consultar periódicamente este aviso para estar informado sobre cómo protegemos sus datos.
          </p>
        </div>
      </div>
    </div>
  </div>

  <!-- Contacto -->
  <div class="orders-info-banner orders-info-banner--green mb-5">
    <i class="fa-solid fa-envelope-open-text" style="font-size: 1.2rem; margin-top: 0.1rem;"></i>
    <div>
      <p class="mb-1" style="font-weight: 700; font-size: var(--fs-sm); color: var(--s-800);">Contacto</p>
      <p class="mb-1" style="font-size: var(--fs-sm); color: var(--gray-600);">Para ejercer sus derechos ARCO o resolver dudas sobre el tratamiento de sus datos personales, puede contactar al Departamento de Sistemas:</p>
      <a href="mailto:sistemas@proatam.com" style="font-size: var(--fs-sm); color: var(--p-600); font-weight: 700; text-decoration: none;">sistemas@proatam.com</a>
    </div>
  </div>

</div>

<?php include 'includes/footer.php'; ?>