<?php
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

checkSession();
preventCaching();

require_once __DIR__ . "/../conexion.php";

// Obtener tipos de activo
$sql_tipos = "SELECT id, nombre, prefijo FROM activo_tipos WHERE activo = 1 ORDER BY nombre ASC";
$result_tipos = $conn->query($sql_tipos);

// Obtener usuarios (responsables)
$sql_usuarios = "SELECT id, nombres, apellidos, departamento_id FROM usuarios WHERE activo = 1 ORDER BY nombres ASC";
$result = $conn->query($sql_usuarios);
$result_usuarios = $result;

// Obtener departamentos
$sql_departamentos = "SELECT id, nombre FROM departamentos WHERE activo = 1 ORDER BY nombre ASC";
$result = $conn->query($sql_departamentos);
$result_departamentos = $result;
?>

<?php include __DIR__ . "/../includes/navbar.php"; ?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/core/modules.css?v=2.0">
<title>Nuevo Activo | GECO Proatam</title>

<div class="orders-page-container">

  <!-- Page Header -->
  <div class="orders-page-header mb-4">
    <div class="orders-page-header-info">
      <nav class="orders-breadcrumb">
        <a href="<?= BASE_URL ?>/index.php">Inicio</a>
        <span class="separator">›</span>
        <a href="<?= BASE_URL ?>/activos/list_activos.php">Activos</a>
        <span class="separator">›</span>
        <span>Nuevo Activo</span>
      </nav>
      <h1 class="orders-page-title">Nuevo Activo</h1>
    </div>
    <a href="list_activos.php" class="btn-geco-outline"><i class="fa-solid fa-arrow-left"></i>Volver al Listado
    </a>
  </div>

  <form id="activoForm" method="POST" action="save_activo.php" enctype="multipart/form-data">

    <!-- ===== CARD 1: INFORMACIÓN GENERAL ===== -->
    <div class="oc-card">
      <div class="oc-card-header">
        <span class="oc-card-header__title"><i class="fa-solid fa-circle-info"></i> Información General del Activo</span>
      </div>
      <div class="oc-card-body">
        <p class="oc-card-intro">Complete los datos básicos del activo físico. El código de identificación se generará automáticamente al guardar.</p>

        <div class="orders-alert orders-alert--info mb-4">
          <i class="fa-solid fa-circle-info"></i>
          <div class="orders-alert__body">
            <p>Seleccione primero el <strong>Tipo de Activo</strong> para desplegar la sección de detalles técnicos específicos correspondientes.</p>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-md-6 col-lg-3">
            <label class="oc-form-label">Tipo de Activo <span class="required">*</span></label>
            <select class="form-select" id="tipo_id" name="tipo_id" required onchange="mostrarSeccionDetalle()">
              <option value="">Seleccionar Tipo</option>
              <?php
              if ($result_tipos && $result_tipos->num_rows > 0) {
                while ($row = $result_tipos->fetch_assoc()) {
                  echo '<option value="' . htmlspecialchars($row['id']) . '" '
                    . 'data-prefijo="' . htmlspecialchars($row['prefijo']) . '">'
                    . htmlspecialchars($row['nombre']) . '</option>';
                }
              }
              ?>
            </select>
          </div>

          <div class="col-md-6 col-lg-3">
            <label class="oc-form-label">Código de Activo</label>
            <div class="codigo-preview" id="codigoPreview">
              <i class="fa-solid fa-barcode"></i> Se asignará al guardar
            </div>
          </div>

          <div class="col-md-6 col-lg-3">
            <label class="oc-form-label">Nombre del Activo <span class="required">*</span></label>
            <input type="text" class="form-control" name="nombre"
              placeholder="Ej. Camioneta Ford F-150, Laptop Dell Inspiron..." required />
          </div>

          <div class="col-md-6 col-lg-3">
            <label class="oc-form-label">Condición <span class="required">*</span></label>
            <select class="form-select" name="condicion" required>
              <option value="">Seleccionar</option>
              <option value="bueno">Bueno</option>
              <option value="regular">Regular</option>
              <option value="malo">Malo</option>
            </select>
          </div>

          <div class="col-md-6 col-lg-4">
            <label class="oc-form-label">Responsable</label>
            <select class="form-select" name="responsable_id" id="responsable">
              <option value="">Sin responsable asignado</option>
              <?php
              if ($result_usuarios && $result_usuarios->num_rows > 0) {
                while ($row = $result_usuarios->fetch_assoc()) {
                  echo '<option value="' . htmlspecialchars($row['id']) . '"'
                    . ' data-departamento="' . htmlspecialchars($row['departamento_id']) . '">'
                    . htmlspecialchars($row['nombres'] . ' ' . $row['apellidos']) . '</option>';
                }
              }
              ?>
            </select>
          </div>

          <div class="col-md-6 col-lg-4">
            <label class="oc-form-label">Departamento</label>
            <select class="form-select" name="departamento_id" id="departamento">
              <option value="">Sin departamento asignado</option>
              <?php
              if ($result_departamentos && $result_departamentos->num_rows > 0) {
                while ($row = $result_departamentos->fetch_assoc()) {
                  echo '<option value="' . htmlspecialchars($row['id']) . '">'
                    . htmlspecialchars($row['nombre']) . '</option>';
                }
              }
              ?>
            </select>
          </div>

          <div class="col-md-12 col-lg-4">
            <label class="oc-form-label">Estatus <span class="required">*</span></label>
            <select class="form-select" name="estatus" required>
              <option value="activo">Activo</option>
              <option value="inactivo">Inactivo</option>
            </select>
          </div>

          <div class="col-md-12">
            <label class="oc-form-label">Notas Generales</label>
            <textarea class="form-control" name="notas" rows="2"
              placeholder="Observaciones, historial de mantenimiento, características adicionales..."></textarea>
          </div>
        </div>
      </div>
    </div>

    <!-- ============================================================ -->
    <!-- VEHÍCULOS                                                     -->
    <!-- ============================================================ -->
    <div id="seccion-vehiculos" class="section-detalle oc-card">
      <div class="oc-card-header">
        <span class="oc-card-header__title"><i class="fa-solid fa-truck"></i> Detalles del Vehículo</span>
      </div>
      <div class="oc-card-body">
        <div class="orders-alert orders-alert--info mb-4">
          <i class="fa-solid fa-circle-info"></i>
          <span><strong>Gravamen:</strong> -Libre: Propiedad plena. -Limitado: Propiedad compartida/proceso de pago. -Con gravamen: Restricción legal activa.</span>
        </div>
        <div class="row g-3">
          <div class="col-md-4">
            <label class="oc-form-label">Marca</label>
            <input type="text" class="form-control" name="v_marca" placeholder="Ford, Toyota, Nissan..." />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Modelo</label>
            <input type="text" class="form-control" name="v_modelo" placeholder="F-150, Hilux..." />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Año</label>
            <input type="number" class="form-control" name="v_anio" placeholder="2024" min="1900" max="2099" />
          </div>
          <div class="col-md-3">
            <label class="oc-form-label">Color</label>
            <input type="text" class="form-control" name="v_color" placeholder="Blanco, Rojo..." />
          </div>
          <div class="col-md-3">
            <label class="oc-form-label">Placa</label>
            <input type="text" class="form-control" name="v_placa" placeholder="ABC-123-D" />
          </div>
          <div class="col-md-3">
            <label class="oc-form-label">VIN / Número de Serie</label>
            <input type="text" class="form-control" name="v_vin" placeholder="17 caracteres..." />
          </div>
          <div class="col-md-3">
            <label class="oc-form-label">Número de Motor</label>
            <input type="text" class="form-control" name="v_numero_motor" />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Entidad Federativa</label>
            <input type="text" class="form-control" name="v_entidad_federativa" placeholder="Tamaulipas, Nuevo León..." />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Número de Pedimento</label>
            <input type="text" class="form-control" name="v_numero_pedimento" />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Origen</label>
            <select class="form-select" name="v_origen">
              <option value="">Seleccionar</option>
              <option value="nacional">Nacional</option>
              <option value="importado">Importado</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Gravamen</label>
            <select class="form-select" name="v_gravamen">
              <option value="">Seleccionar</option>
              <option value="libre">Libre</option>
              <option value="limitado">Limitado</option>
              <option value="gravado">Con gravamen</option>
            </select>
          </div>
          <div class="col-md-8">
            <label class="oc-form-label">Nombre del Propietario</label>
            <input type="text" class="form-control" name="v_nombre_propietario" />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Aseguradora <span class="comentario">(México)</span></label>
            <input type="text" class="form-control" name="v_nombre_aseguradora_mx" placeholder="Qualitas, Inbursa..." />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Teléfono Aseguradora <span class="comentario">(México)</span></label>
            <input type="text" class="form-control" name="v_telefono_aseguradora_mx" placeholder="800-XXX-XXXX" />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Vto. Seguro <span class="comentario">(México)</span></label>
            <input type="date" class="form-control" name="v_fecha_vencimiento_seguro_mx" />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Aseguradora <span class="comentario">(USA)</span></label>
            <input type="text" class="form-control" name="v_nombre_aseguradora_usa" placeholder="GEICO, State Farm..." />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Teléfono Aseguradora <span class="comentario">(USA)</span></label>
            <input type="text" class="form-control" name="v_telefono_aseguradora_usa" placeholder="800-XXX-XXXX" />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Vto. Seguro <span class="comentario">(USA)</span></label>
            <input type="date" class="form-control" name="v_fecha_vencimiento_seguro_usa" />
          </div>
        </div>
      </div>
    </div>

    <!-- ============================================================ -->
    <!-- MAQUINARIA                                                    -->
    <!-- ============================================================ -->
    <div id="seccion-maquinaria" class="section-detalle oc-card">
      <div class="oc-card-header">
        <span class="oc-card-header__title"><i class="fa-solid fa-gears"></i> Detalles de Maquinaria</span>
      </div>
      <div class="oc-card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="oc-form-label">Marca</label>
            <input type="text" class="form-control" name="m_marca" placeholder="Caterpillar, Komatsu..." />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Modelo</label>
            <input type="text" class="form-control" name="m_modelo" placeholder="D6T, PC200..." />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Número de Serie</label>
            <input type="text" class="form-control" name="m_numero_serie" />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Kilometraje / Horómetro</label>
            <input type="number" class="form-control" name="m_kilometraje" placeholder="0" min="0" />
          </div>
          <div class="col-md-8">
            <label class="oc-form-label">Foto Motor</label>
            <div class="file-drop-zone" id="zone_m_foto_motor">
              <input type="file" id="input_m_foto_motor"
                accept="image/*"
                onchange="handleFile(this,'m_foto_motor','imagen',false)" />
              <div class="file-drop-label">
                <i class="fa-solid fa-camera"></i>
                <span>Seleccionar imagen del motor (máx. 10 MB)</span>
              </div>
            </div>
            <div class="file-chips" id="chips_m_foto_motor"></div>
          </div>
        </div>
      </div>
    </div>

    <!-- ============================================================ -->
    <!-- MOBILIARIO                                                    -->
    <!-- ============================================================ -->
    <div id="seccion-mobiliario" class="section-detalle oc-card">
      <div class="oc-card-header">
        <span class="oc-card-header__title"><i class="fa-solid fa-couch"></i> Detalles de Mobiliario</span>
      </div>
      <div class="oc-card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="oc-form-label">Marca</label>
            <input type="text" class="form-control" name="mob_marca" placeholder="Ikea, Steelcase..." />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Modelo</label>
            <input type="text" class="form-control" name="mob_modelo" />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Número de Items</label>
            <input type="number" class="form-control" name="mob_numero_items" placeholder="1" min="1" />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Medida Aproximada</label>
            <input type="text" class="form-control" name="mob_medida_aprox" placeholder="1.80 x 0.80 m" />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Edificio</label>
            <input type="text" class="form-control" name="mob_edificio" placeholder="Edificio A, Torre Norte..." />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Área / Departamento</label>
            <input type="text" class="form-control" name="mob_area_departamento" placeholder="Recursos Humanos, Gerencia..." />
          </div>
          <div class="col-md-6">
            <label class="oc-form-label">Dirección</label>
            <input type="text" class="form-control" name="mob_direccion" placeholder="Calle, número, colonia..." />
          </div>
          <div class="col-md-6">
            <label class="oc-form-label">Descripción</label>
            <textarea class="form-control" name="mob_descripcion" rows="2"></textarea>
          </div>
        </div>
      </div>
    </div>

    <!-- ============================================================ -->
    <!-- INMUEBLES                                                     -->
    <!-- ============================================================ -->
    <div id="seccion-inmuebles" class="section-detalle oc-card">
      <div class="oc-card-header">
        <span class="oc-card-header__title"><i class="fa-solid fa-building"></i> Detalles del Inmueble</span>
      </div>
      <div class="oc-card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="oc-form-label">Tipo de Inmueble</label>
            <input type="text" class="form-control" name="inm_tipo" placeholder="Oficina, Bodega, Terreno..." />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Tipo de Posesión</label>
            <input type="text" class="form-control" name="inm_tipo_posesion" placeholder="Propio, Arrendado, Comodato..." />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Uso</label>
            <input type="text" class="form-control" name="inm_uso" placeholder="Habitacional, Comercial, Industrial..." />
          </div>
          <div class="col-md-6">
            <label class="oc-form-label">Dirección</label>
            <input type="text" class="form-control" name="inm_direccion" />
          </div>
          <div class="col-md-6">
            <label class="oc-form-label">Coordenadas GPS</label>
            <input type="text" class="form-control" name="inm_coordenadas" placeholder="29.0729° N, 110.9559° W" />
          </div>
          <div class="col-md-3">
            <label class="oc-form-label">Superficie Terreno (m²)</label>
            <input type="number" class="form-control" name="inm_superficie_terreno" placeholder="0.00" step="0.01" min="0" />
          </div>
          <div class="col-md-3">
            <label class="oc-form-label">Superficie Construida (m²)</label>
            <input type="number" class="form-control" name="inm_superficie_construida" placeholder="0.00" step="0.01" min="0" />
          </div>
          <div class="col-md-2">
            <label class="oc-form-label">Niveles</label>
            <input type="number" class="form-control" name="inm_niveles" placeholder="1" min="0" />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Valor Terreno</label>
            <input type="number" class="form-control" name="inm_valor_terreno" placeholder="0.00" step="0.01" min="0" />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Folio RPP</label>
            <input type="text" class="form-control" name="inm_folio_rpp" />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Predial</label>
            <input type="text" class="form-control" name="inm_predial" />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Estatus Legal</label>
            <input type="text" class="form-control" name="inm_estatus_legal" />
          </div>
          <div class="col-md-6">
            <label class="oc-form-label">Responsable Administrativo</label>
            <input type="text" class="form-control" name="inm_responsable_administrativo" />
          </div>
        </div>
      </div>
    </div>

    <!-- ============================================================ -->
    <!-- HERRAMIENTAS                                                  -->
    <!-- ============================================================ -->
    <div id="seccion-herramientas" class="section-detalle oc-card">
      <div class="oc-card-header">
        <span class="oc-card-header__title"><i class="fa-solid fa-screwdriver-wrench"></i> Detalles de Herramienta</span>
      </div>
      <div class="oc-card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="oc-form-label">Marca</label>
            <input type="text" class="form-control" name="h_marca" placeholder="Dewalt, Bosch, Stanley..." />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Modelo</label>
            <input type="text" class="form-control" name="h_modelo" />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Número de Serie</label>
            <input type="text" class="form-control" name="h_numero_serie" />
          </div>
          <div class="col-md-6">
            <label class="oc-form-label">Asignación</label>
            <input type="text" class="form-control" name="h_asignacion" />
          </div>
          <div class="col-md-6">
            <label class="oc-form-label">Ubicación</label>
            <input type="text" class="form-control" name="h_ubicacion_fisica" />
          </div>
          <div class="col-12 mt-3">
            <label class="oc-form-label">Descripción</label>
            <textarea class="form-control" name="h_descripcion" rows="2"></textarea>
          </div>
        </div>
      </div>
    </div>

    <!-- ============================================================ -->
    <!-- TICs                                                          -->
    <!-- ============================================================ -->
    <div id="seccion-tics" class="section-detalle oc-card">
      <div class="oc-card-header">
        <span class="oc-card-header__title"><i class="fa-solid fa-laptop"></i> Detalles de TICs</span>
      </div>
      <div class="oc-card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="oc-form-label">Marca</label>
            <input type="text" class="form-control" name="t_marca" placeholder="Dell, HP, Apple..." />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Modelo</label>
            <input type="text" class="form-control" name="t_modelo" />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Número de Serie</label>
            <input type="text" class="form-control" name="t_numero_serie" />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Sistema Operativo</label>
            <input type="text" class="form-control" name="t_sistema_operativo" placeholder="Windows 11, macOS 14..." />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Procesador</label>
            <input type="text" class="form-control" name="t_procesador" />
          </div>
          <div class="col-md-2">
            <label class="oc-form-label">RAM</label>
            <input type="text" class="form-control" name="t_ram" placeholder="16 GB" />
          </div>
          <div class="col-md-2">
            <label class="oc-form-label">Almacenamiento</label>
            <input type="text" class="form-control" name="t_almacenamiento" placeholder="512 GB SSD" />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Office / Suite</label>
            <input type="text" class="form-control" name="t_office" />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Correo Asignado</label>
            <input type="email" class="form-control" name="t_correo" />
          </div>
          <div class="col-md-4">
            <label class="oc-form-label">Ubicación Física</label>
            <input type="text" class="form-control" name="t_ubicacion_fisica" />
          </div>
          <div class="col-md-6 mt-3">
            <label class="oc-form-label">Programas Instalados</label>
            <textarea class="form-control" name="t_programas_instalados" rows="2"></textarea>
          </div>
          <div class="col-md-6 mt-3">
            <label class="oc-form-label">Complementos / Accesorios</label>
            <textarea class="form-control" name="t_complementos" rows="2"></textarea>
          </div>
        </div>
      </div>
    </div>

    <!-- DOUBLE COLUMN FORM LAYOUT -->
    <div class="oc-form-layout">
      <div class="oc-form-layout-main">

        <!-- CARD: IMÁGENES -->
        <div class="oc-card">
          <div class="oc-card-header">
            <span class="oc-card-header__title"><i class="fa-regular fa-images"></i> Imágenes del Activo</span>
          </div>
          <div class="oc-card-body">
            <div class="row g-3">
              <div class="col-12 mb-3">
                <label class="oc-form-label">Foto Principal</label>
                <div class="file-drop-zone" id="zone_img_foto_principal">
                  <input type="file" id="input_img_foto_principal"
                    accept=".jpg,.jpeg,.png,.gif,.webp"
                    onchange="handleFile(this,'img_foto_principal','imagen',false)" />
                  <div class="file-drop-label">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                    <span>Haz clic para seleccionar o arrastra la foto principal (máx. 10 MB)</span>
                  </div>
                </div>
                <div class="file-chips" id="chips_img_foto_principal"></div>
              </div>

              <div class="col-md-4">
                <label class="oc-form-label">Fotos Generales</label>
                <div class="file-drop-zone">
                  <input type="file" id="input_img_foto_general" accept=".jpg,.jpeg,.png,.gif,.webp" multiple onchange="handleFile(this,'img_foto_general','imagen',true)" />
                  <div class="file-drop-label"><i class="fa-regular fa-images"></i><span>Fotos generales</span></div>
                </div>
                <div class="file-chips" id="chips_img_foto_general"></div>
              </div>

              <div class="col-md-4">
                <label class="oc-form-label">Foto de Placa</label>
                <div class="file-drop-zone">
                  <input type="file" id="input_img_foto_placa" accept=".jpg,.jpeg,.png,.gif,.webp" onchange="handleFile(this,'img_foto_placa','imagen',false)" />
                  <div class="file-drop-label"><i class="fa-solid fa-camera"></i><span>Foto placa</span></div>
                </div>
                <div class="file-chips" id="chips_img_foto_placa"></div>
              </div>

              <div class="col-md-4">
                <label class="oc-form-label">Foto de Número de Serie</label>
                <div class="file-drop-zone">
                  <input type="file" id="input_img_foto_numero_serie" accept=".jpg,.jpeg,.png,.gif,.webp" onchange="handleFile(this,'img_foto_numero_serie','imagen',false)" />
                  <div class="file-drop-label"><i class="fa-solid fa-camera"></i><span>Foto serie</span></div>
                </div>
                <div class="file-chips" id="chips_img_foto_numero_serie"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- CARD: EXPEDIENTE DIGITAL -->
        <div class="oc-card">
          <div class="oc-card-header">
            <span class="oc-card-header__title"><i class="fa-regular fa-file-pdf"></i> Expediente Digital (Documentos)</span>
          </div>
          <div class="oc-card-body">
            <div class="orders-alert orders-alert--info mb-3">
              <i class="fa-solid fa-circle-info"></i>
              <span>Cargue documentos PDF, Word o imagen. Máx. 10 MB por archivo (Catálogo hasta 1 GB).</span>
            </div>

            <div class="row g-3">
              <div class="col-md-6 col-lg-3">
                <label class="oc-form-label">Factura / Comprobante de Compra</label>
                <div class="file-drop-zone"><input type="file" id="input_doc_factura" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" onchange="handleFile(this,'doc_factura','normal',false)" />
                  <div class="file-drop-label"><i class="fa-solid fa-file-arrow-up"></i><span>PDF, Word o imagen</span></div>
                </div>
                <div class="file-chips" id="chips_doc_factura"></div>
              </div>

              <div class="col-md-6 col-lg-3">
                <label class="oc-form-label">Pedimento de Importación</label>
                <div class="file-drop-zone"><input type="file" id="input_doc_pedimento" accept=".pdf,.doc,.docx" onchange="handleFile(this,'doc_pedimento','normal',false)" />
                  <div class="file-drop-label"><i class="fa-solid fa-file-arrow-up"></i><span>PDF o Word</span></div>
                </div>
                <div class="file-chips" id="chips_doc_pedimento"></div>
              </div>

              <div class="col-md-6 col-lg-3">
                <label class="oc-form-label">Póliza de Seguro <span class="comentario">(México)</span></label>
                <div class="file-drop-zone"><input type="file" id="input_doc_poliza_seguro" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" onchange="handleFile(this,'doc_poliza_seguro','normal',false)" />
                  <div class="file-drop-label"><i class="fa-solid fa-file-arrow-up"></i><span>PDF, Word o imagen</span></div>
                </div>
                <div class="file-chips" id="chips_doc_poliza_seguro"></div>
              </div>

              <div class="col-md-6 col-lg-3">
                <label class="oc-form-label">Póliza de Seguro <span class="comentario">(USA)</span></label>
                <div class="file-drop-zone"><input type="file" id="input_doc_poliza_seguro_usa" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" onchange="handleFile(this,'doc_poliza_seguro_usa','normal',false)" />
                  <div class="file-drop-label"><i class="fa-solid fa-file-arrow-up"></i><span>PDF, Word o imagen</span></div>
                </div>
                <div class="file-chips" id="chips_doc_poliza_seguro_usa"></div>
              </div>

              <div class="col-md-6 col-lg-3">
                <label class="oc-form-label">Manual de Usuario / Operación</label>
                <div class="file-drop-zone"><input type="file" id="input_doc_manual" accept=".pdf,.doc,.docx" onchange="handleFile(this,'doc_manual','normal',false)" />
                  <div class="file-drop-label"><i class="fa-solid fa-file-arrow-up"></i><span>PDF o Word</span></div>
                </div>
                <div class="file-chips" id="chips_doc_manual"></div>
              </div>

              <div class="col-md-6 col-lg-3">
                <label class="oc-form-label">Manual de Mantenimiento</label>
                <div class="file-drop-zone"><input type="file" id="input_doc_manual_mantenimiento" accept=".pdf,.doc,.docx" onchange="handleFile(this,'doc_manual_mantenimiento','normal',false)" />
                  <div class="file-drop-label"><i class="fa-solid fa-file-arrow-up"></i><span>PDF o Word</span></div>
                </div>
                <div class="file-chips" id="chips_doc_manual_mantenimiento"></div>
              </div>

              <div class="col-md-6 col-lg-3">
                <label class="oc-form-label">Catálogo de Refacciones <span class="comentario">(máx. 1 GB)</span></label>
                <div class="file-drop-zone"><input type="file" id="input_doc_catalogo_refacciones" accept=".pdf,.doc,.docx" onchange="handleFile(this,'doc_catalogo_refacciones','catalogo',false)" />
                  <div class="file-drop-label"><i class="fa-solid fa-file-arrow-up"></i><span>PDF o Word</span></div>
                </div>
                <div class="file-chips" id="chips_doc_catalogo_refacciones"></div>
              </div>

              <div class="col-md-6 col-lg-3">
                <label class="oc-form-label">Contrato / Escritura</label>
                <div class="file-drop-zone"><input type="file" id="input_doc_contrato" accept=".pdf,.doc,.docx" onchange="handleFile(this,'doc_contrato','normal',false)" />
                  <div class="file-drop-label"><i class="fa-solid fa-file-arrow-up"></i><span>PDF o Word</span></div>
                </div>
                <div class="file-chips" id="chips_doc_contrato"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- CARD: EXPEDIENTE FISCAL Y EXTRA -->
        <div class="oc-card">
          <div class="oc-card-header">
            <span class="oc-card-header__title"><i class="fa-solid fa-folder-plus"></i> Carpetas de Control Dinámico</span>
          </div>
          <div class="oc-card-body">
            <div class="mb-4">
              <label class="oc-form-label"><i class="fa-solid fa-hashtag"></i> Control Fiscal / Tenencias / Predial</label>
              <small class="text-muted d-block mb-2">Máx. 10 archivos, 10 MB c/u.</small>
              <div class="oc-files-input-group mb-3">
                <input type="file" class="form-control" id="singleFileInputFiscal" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx,.txt">
                <button class="btn-geco-secondary" type="button" onclick="agregarAdjunto('fiscal')">
                  <i class="fa-solid fa-upload"></i> Subir
                </button>
              </div>
              <div id="adjuntosContainerFiscal" class="oc-files-dropzone">
                <h6 class="oc-files-dropzone__title">Archivos agregados: <span id="contadorFiscal" class="badge bg-secondary">0</span></h6>
                <ul id="adjuntosListFiscal" class="list-group list-group-flush mt-2">
                  <li class="list-group-item py-3 border-0 bg-transparent">
                    <div class="orders-empty-state">
                      <i class="fa-solid fa-circle-info"></i>
                      <p>No hay archivos agregados</p>
                    </div>
                  </li>
                </ul>
              </div>
            </div>

            <div>
              <label class="oc-form-label"><i class="fa-regular fa-square-plus"></i> Documentación Extra / Adicional</label>
              <small class="text-muted d-block mb-2">Máx. 10 archivos, 10 MB c/u.</small>
              <div class="oc-files-input-group mb-3">
                <input type="file" class="form-control" id="singleFileInputExtra" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx,.txt">
                <button class="btn-geco-secondary" type="button" onclick="agregarAdjunto('extra')">
                  <i class="fa-solid fa-upload"></i> Subir
                </button>
              </div>
              <div id="adjuntosContainerExtra" class="oc-files-dropzone">
                <h6 class="oc-files-dropzone__title">Archivos agregados: <span id="contadorExtra" class="badge bg-secondary">0</span></h6>
                <ul id="adjuntosListExtra" class="list-group list-group-flush mt-2">
                  <li class="list-group-item py-3 border-0 bg-transparent">
                    <div class="orders-empty-state">
                      <i class="fa-solid fa-circle-info"></i>
                      <p>No hay archivos agregados</p>
                    </div>
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </div>

      </div>

      <div class="oc-form-layout-side">

        <!-- CARD: INVERSIÓN Y FECHAS -->
        <div class="oc-card">
          <div class="oc-card-header">
            <span class="oc-card-header__title"><i class="fa-solid fa-wallet"></i> Inversión y Ubicación</span>
          </div>
          <div class="oc-card-body">
            <div class="row g-3">
              <div class="col-12">
                <label class="oc-form-label">Fecha de Adquisición</label>
                <input type="date" class="form-control" name="fecha_adquisicion" />
              </div>
              <div class="col-12">
                <label class="oc-form-label">Valor Factura <span class="comentario">(MXN)</span></label>
                <input type="number" class="form-control" name="valor_factura" placeholder="0.00" step="0.01" min="0" />
              </div>
              <div class="col-12">
                <label class="oc-form-label">Vida Útil <span class="comentario">(años)</span></label>
                <input type="number" class="form-control" name="vida_util" placeholder="0" min="0" />
              </div>
              <div class="col-12">
                <label class="oc-form-label">Ubicación Física</label>
                <input type="text" class="form-control" name="ubicacion" placeholder="Ej. Oficina Ribereña, Almaguer, Obra..." />
              </div>
            </div>
          </div>
        </div>

        <!-- ACCIONES DE ENVÍO -->
        <div class="oc-card">
          <div class="oc-card-body">
            <p class="oc-form-submit-note mb-3"><i class="fa-solid fa-circle-info"></i> Esta ficha será almacenada en la base de datos de activos de GECO.</p>
            <div class="oc-form-submit-actions">
              <button type="submit" class="btn-geco-primary w-100" id="btnGuardar">
                <i class="fa-solid fa-floppy-disk"></i> Guardar Activo
              </button>
            </div>
          </div>
        </div>

      </div>
    </div>

  </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
  integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
  crossorigin="anonymous"></script>

<script>
  // ═══════════════════════════════════════════════════════════════
  // LÍMITES POR TIPO
  // ═══════════════════════════════════════════════════════════════
  const LIMITES_MB = {
    normal: 10,
    imagen: 10,
    catalogo: 1024,
    adjunto: 10
  };

  // ═══════════════════════════════════════════════════════════════
  // STORE DE ARCHIVOS  { campo: [{ file, ok }] }
  // ═══════════════════════════════════════════════════════════════
  const fileStore = {};

  // ═══════════════════════════════════════════════════════════════
  // TOASTS
  // ═══════════════════════════════════════════════════════════════
  const TCFG = {
    danger: {
      title: 'Error',
      icon: 'fa-solid fa-circle-xmark'
    },
    success: {
      title: 'Listo',
      icon: 'fa-solid fa-circle-check'
    },
    warning: {
      title: 'Aviso',
      icon: 'fa-solid fa-triangle-exclamation'
    },
    info: {
      title: 'Información',
      icon: 'fa-solid fa-circle-info'
    },
  };

  function mostrarAlerta(msg, tipo = 'danger') {
    if (tipo === 'success') UI.toast.success(msg);
    else if (tipo === 'warning') UI.toast.warning(msg);
    else if (tipo === 'info') UI.toast.info(msg);
    else UI.toast.error(msg);
  }

  // ═══════════════════════════════════════════════════════════════
  // MODAL DE CONFIRMACIÓN  (reemplaza confirm() nativo)
  // ═══════════════════════════════════════════════════════════════
  function mostrarConfirmacion({
    archivos,
    totalMB
  }) {
    return UI.confirm({
      title: '¿Confirmar subida?',
      message: `Se guardarán los datos y se subirán <b>${archivos}</b> archivo${archivos !== 1 ? 's' : ''} (${totalMB} MB total).`,
      confirmText: 'Guardar Activo',
      icon: 'question'
    });
  }

  // ═══════════════════════════════════════════════════════════════
  // MANEJO DE ARCHIVOS CON CHIPS
  // ═══════════════════════════════════════════════════════════════
  function handleFile(input, campo, tipo, multiple) {
    if (!fileStore[campo]) fileStore[campo] = [];

    const limiteMB = LIMITES_MB[tipo] || 10;
    const files = Array.from(input.files);

    if (!multiple) {
      // Reemplazar archivo existente
      fileStore[campo] = [];
    }

    files.forEach(file => {
      const sizeMB = file.size / 1024 / 1024;
      const ok = sizeMB <= limiteMB;
      fileStore[campo].push({
        file,
        ok,
        tipo
      });
      if (ok) {
        mostrarAlerta(`"${file.name}" (${sizeMB.toFixed(2)} MB) listo para subir.`, 'success');
      } else {
        mostrarAlerta(`"${file.name}" pesa ${sizeMB.toFixed(2)} MB y supera el límite de ${limiteMB} MB. Retíralo antes de guardar.`, 'danger');
      }
    });

    renderChips(campo);
    // Reset input para poder re-seleccionar si se borra
    input.value = '';
  }

  function renderChips(campo) {
    const container = document.getElementById('chips_' + campo);
    if (!container) return;
    const entries = fileStore[campo] || [];
    if (!entries.length) {
      container.innerHTML = '';
      return;
    }

    container.innerHTML = entries.map((e, i) => {
      const sizeMB = (e.file.size / 1024 / 1024).toFixed(2);
      const cls = e.ok ? 'ok' : 'error';
      const icon = e.ok ? 'fa-solid fa-circle-check' : 'fa-solid fa-circle-exclamation';
      return `
          <div class="file-chip ${cls}">
            <i class="${icon}" style="font-size:.85rem;flex-shrink:0;"></i>
            <span class="chip-name" title="${e.file.name}">${e.file.name}</span>
            <span class="chip-size">${sizeMB} MB</span>
            <button type="button" class="chip-remove" onclick="removeChip('${campo}',${i})" title="Quitar archivo">
              <i class="fa-solid fa-xmark"></i>
            </button>
          </div>`;
    }).join('');
  }

  function removeChip(campo, index) {
    if (!fileStore[campo]) return;
    const removed = fileStore[campo].splice(index, 1)[0];
    mostrarAlerta(`"${removed.file.name}" eliminado de la lista.`, 'info');
    renderChips(campo);
  }

  // Verificar si hay archivos con error en el store
  function hayArchivosInvalidos() {
    for (const campo in fileStore) {
      if (fileStore[campo].some(e => !e.ok)) return true;
    }
    // Revisar pools de adjuntos
    for (const tipo of ['fiscal', 'extra']) {
      if (pools[tipo] && pools[tipo].some(e => !e.ok)) return true;
    }
    return false;
  }

  function contarArchivosValidos() {
    let count = 0,
      totalBytes = 0;
    for (const campo in fileStore) {
      fileStore[campo].forEach(e => {
        if (e.ok) {
          count++;
          totalBytes += e.file.size;
        }
      });
    }
    for (const tipo of ['fiscal', 'extra']) {
      if (pools[tipo]) pools[tipo].forEach(e => {
        if (e.ok) {
          count++;
          totalBytes += e.file.size;
        }
      });
    }
    return {
      count,
      totalMB: (totalBytes / 1024 / 1024).toFixed(2)
    };
  }

  // ═══════════════════════════════════════════════════════════════
  // ADJUNTOS DINÁMICOS (fiscal / extra)
  // ═══════════════════════════════════════════════════════════════
  const pools = {
    fiscal: [],
    extra: []
  };

  function agregarAdjunto(tipo) {
    const inputId = tipo === 'fiscal' ? 'singleFileInputFiscal' : 'singleFileInputExtra';
    const input = document.getElementById(inputId);
    if (!input.files.length) {
      mostrarAlerta('Seleccione un archivo primero.', 'warning');
      return;
    }
    const file = input.files[0];

    if (pools[tipo].length >= 10) {
      mostrarAlerta('Máximo 10 archivos por sección.', 'warning');
      return;
    }

    const sizeMB = file.size / 1024 / 1024;
    const ok = sizeMB <= LIMITES_MB.adjunto;
    pools[tipo].push({
      file,
      ok
    });

    if (ok) {
      mostrarAlerta(`"${file.name}" (${sizeMB.toFixed(2)} MB) agregado.`, 'success');
    } else {
      mostrarAlerta(`"${file.name}" pesa ${sizeMB.toFixed(2)} MB y supera 10 MB. Retíralo antes de guardar.`, 'danger');
    }

    renderListaAdj(tipo);
    input.value = '';
  }

  function eliminarAdjunto(tipo, index) {
    const removed = pools[tipo].splice(index, 1)[0];
    mostrarAlerta(`"${removed.file.name}" eliminado.`, 'info');
    renderListaAdj(tipo);
  }

  function renderListaAdj(tipo) {
    const listId = tipo === 'fiscal' ? 'adjuntosListFiscal' : 'adjuntosListExtra';
    const countId = tipo === 'fiscal' ? 'contadorFiscal' : 'contadorExtra';
    const lista = document.getElementById(listId);
    document.getElementById(countId).textContent = pools[tipo].length;

    if (!pools[tipo].length) {
      lista.innerHTML = `
          <li class="list-group-item py-3 border-0 bg-transparent">
            <div class="orders-empty-state">
              <i class="fa-solid fa-circle-info"></i>
              <p>No hay archivos agregados</p>
            </div>
          </li>
        `;
      return;
    }

    lista.innerHTML = pools[tipo].map((e, i) => {
      const sizeMB = (e.file.size / 1024 / 1024).toFixed(2);
      const cls = e.ok ? 'ok' : 'error';
      const icon = e.ok ? 'fa-regular fa-file' : 'fa-solid fa-circle-xmark';
      const meta = e.ok ? `${sizeMB} MB` : `${sizeMB} MB — excede 10 MB`;
      return `
          <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3 ${cls}" style="${!e.ok ? 'background: rgba(232, 68, 90, 0.05); border-color: var(--error);' : ''}">
            <span>
              <i class="${icon} me-2 ${!e.ok ? 'text-danger' : 'text-secondary'}"></i>
              <strong>${e.file.name}</strong>
              <small class="text-muted ms-2">(${meta})</small>
            </span>
            <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="eliminarAdjunto('${tipo}',${i})" style="text-decoration:none;">
              <i class="fa-solid fa-trash-can"></i>
            </button>
          </li>`;
    }).join('');
  }

  // ═══════════════════════════════════════════════════════════════
  // SECCIONES DINÁMICAS POR TIPO
  // ═══════════════════════════════════════════════════════════════
  const secciones = {
    'vehiculos': 'seccion-vehiculos',
    'vehículos': 'seccion-vehiculos',
    'maquinaria': 'seccion-maquinaria',
    'mobiliario': 'seccion-mobiliario',
    'inmuebles': 'seccion-inmuebles',
    'herramientas': 'seccion-herramientas',
    'tics': 'seccion-tics',
    'tic': 'seccion-tics',
  };

  function normalizarTexto(str) {
    return str.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
  }

  function mostrarSeccionDetalle() {
    document.querySelectorAll('.section-detalle').forEach(el => el.classList.remove('visible'));
    const select = document.getElementById('tipo_id');
    const option = select.options[select.selectedIndex];
    if (!option || !option.value) return;
    const nombreTipo = normalizarTexto(option.text);
    const prefijo = option.getAttribute('data-prefijo') || '';
    document.getElementById('codigoPreview').innerHTML =
      '<i class="fa-solid fa-barcode"></i> ' + prefijo + '-XXXX (se asignará al guardar)';
    for (const [clave, idSec] of Object.entries(secciones)) {
      if (nombreTipo.includes(normalizarTexto(clave))) {
        const sec = document.getElementById(idSec);
        if (sec) sec.classList.add('visible');
        break;
      }
    }
  }

  // ═══════════════════════════════════════════════════════════════
  // ENVÍO DEL FORMULARIO
  // ═══════════════════════════════════════════════════════════════
  document.getElementById('activoForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    // 1. Bloquear si hay archivos inválidos
    if (hayArchivosInvalidos()) {
      mostrarAlerta(
        'No se puede guardar. Hay archivos que superan el límite permitido. ' +
        'Retira los archivos marcados en rojo e inténtalo de nuevo.',
        'danger'
      );
      // Hacer scroll al primer chip en error
      const primerError = document.querySelector('.file-chip.error, .adj-item.error');
      if (primerError) primerError.scrollIntoView({
        behavior: 'smooth',
        block: 'center'
      });
      return;
    }

    // 2. Confirmación si hay archivos válidos
    const {
      count,
      totalMB
    } = contarArchivosValidos();
    if (count > 0) {
      const confirmar = await mostrarConfirmacion({
        archivos: count,
        totalMB
      });
      if (!confirmar) return;
    }

    // 3. Bloqueo de UI
    UI.loading('Guardando activo...');

    // 4. Construir FormData
    const fd = new FormData(this);

    // Agregar archivos del store
    for (const [campo, entries] of Object.entries(fileStore)) {
      entries.forEach(e => {
        if (!e.ok) return;
        if (campo === 'img_foto_general') {
          fd.append('img_foto_general[]', e.file, e.file.name);
        } else {
          fd.append(campo, e.file, e.file.name);
        }
      });
    }

    // Agregar adjuntos
    pools.fiscal.forEach(e => {
      if (!e.ok) return;
      fd.append('documentos[]', e.file, e.file.name);
      fd.append('documentos_tipo[]', 'expediente_predial');
    });
    pools.extra.forEach(e => {
      if (!e.ok) return;
      fd.append('documentos[]', e.file, e.file.name);
      fd.append('documentos_tipo[]', 'extra');
    });

    // 5. Enviar
    fetch(this.action, {
        method: 'POST',
        body: fd
      })
      .then(res => {
        if (res.redirected) {
          window.location.href = res.url;
          return;
        }
        return res.text().then(() => {
          if (res.status >= 400) {
            UI.loading.hide();
            mostrarAlerta('Ocurrió un error al guardar. Revisa el log del servidor.', 'danger');
          } else {
            window.location.href = res.url || 'list_activos.php';
          }
        });
      })
      .catch(err => {
        UI.loading.hide();
        mostrarAlerta('Error de red: ' + err.message, 'danger');
      });
  });

  // ═══════════════════════════════════════════════════════════════
  // SINCRONIZAR DEPARTAMENTO AL SELECCIONAR RESPONSABLE
  // ═══════════════════════════════════════════════════════════════
  document.getElementById('responsable').addEventListener('change', function() {
    const deptId = this.options[this.selectedIndex].getAttribute('data-departamento');
    const sel = document.getElementById('departamento');
    if (sel) sel.value = deptId || '';
  });
</script>

<?php include __DIR__ . "/../includes/footer.php"; ?>