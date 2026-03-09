<?php
require_once __DIR__ . "/../includes/session_manager.php";
require_once __DIR__ . "/../includes/check_session.php";

checkSession();
preventCaching();

include(__DIR__ . "/../conexion.php");

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

<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Nuevo Activo</title>
    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
      rel="stylesheet"
      integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
      crossorigin="anonymous"
    />
    <link rel="icon" href="/assets/img/LogoCuadro.ico" type="image/x-icon">
    <link
      rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css"
    />
    <link rel="stylesheet" href="/assets/styles/new_order.css" />
    <style>
      .section-detalle {
        display: none;
        animation: fadeIn 0.3s ease;
      }
      .section-detalle.visible {
        display: block;
      }
      @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-8px); }
        to   { opacity: 1; transform: translateY(0); }
      }
      .codigo-preview {
        background: #f0f4f8;
        border: 1px dashed #adb5bd;
        border-radius: 6px;
        padding: 8px 14px;
        font-family: monospace;
        font-size: 1rem;
        color: #495057;
        letter-spacing: 1px;
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
          <a href="/activos/list_activos.php">Registro de Activos</a>
          <span>/</span>
          <span>Nuevo Activo</span>
        </div>
        <div class="row align-items-end">
          <div class="col-lg-8">
            <h1 class="hero-title">Registro de Nuevo Activo</h1>
          </div>
        </div>
      </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="content-wrapper">
      <div class="form-container">
        <div class="form-body">

          <form id="activoForm" method="POST" action="save_activo.php" enctype="multipart/form-data">

            <div>
              <p>
                Complete este formulario para registrar un nuevo activo en el sistema.
                Seleccione primero el Tipo de Activo para que aparezcan los campos
                específicos correspondientes. <br />
                <b>Importante:</b> El código de identificación se generará automáticamente
                al guardar el registro.
              </p>
            </div>

            <!-- ===== INFORMACIÓN GENERAL ===== -->
            <div class="section-title">
              <i class="bi bi-info-circle"></i> Información General
            </div>

            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label class="form-label">Tipo de Activo <span class="required">*</span></label>
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
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label class="form-label">Código de Activo</label>
                  <div class="codigo-preview" id="codigoPreview">
                    <i class="bi bi-upc-scan"></i> Se asignará al guardar
                  </div>
                </div>
              </div>
            </div>

            <div class="col-md-8 doc-item mb-3">
              <label class="form-label">Foto Principal</label>
              <input type="file" class="form-control" name="img_foto_principal"
              accept=".jpg,.jpeg,.png,.gif,.webp" />
              <small class="text-muted">JPG, PNG, GIF o WebP</small>
            </div>

            <div class="row">
              <div class="col-md-8">
                <div class="form-group">
                  <label class="form-label">Nombre del Activo <span class="required">*</span></label>
                  <input type="text" class="form-control" name="nombre"
                    placeholder="Ej. Camioneta Ford F-150, Laptop Dell Inspiron..." required />
                </div>
              </div>

              <div class="col-md-4">
                <div class="form-group">
                  <label class="form-label">Condición <span class="required">*</span></label>
                  <select class="form-select" name="condicion" required>
                    <option value="">Seleccionar</option>
                    <option value="bueno">Bueno</option>
                    <option value="regular">Regular</option>
                    <option value="malo">Malo</option>
                  </select>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label class="form-label">Responsable</label>
                   <select class="form-select" name="responsable_id" id="responsable">
                    <option value="">Sin responsable asignado</option>
                    <?php
                    if ($result_usuarios && $result_usuarios->num_rows > 0) {
                      while ($row = $result_usuarios->fetch_assoc()) {
                        echo '<option value="' . htmlspecialchars($row['id']) . '"
                           data-departamento="' . htmlspecialchars($row['departamento_id']) . '">'
                           . htmlspecialchars($row['nombres'] . ' ' . $row['apellidos']) . '</option>';
                      }
                    }
                    ?>
                  </select>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label class="form-label">Departamento</label>
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
              </div>
            </div>

            <div class="row">
              <div class="col-md-4">
                <div class="form-group">
                  <label class="form-label">Fecha de Adquisición</label>
                  <input type="date" class="form-control" name="fecha_adquisicion" />
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label class="form-label">Valor Factura <span class="comentario">(Valor en MXN)</span></label>
                  <input type="number" class="form-control" name="valor_factura"
                    placeholder="0.00" step="0.01" min="0" />
                </div>
              </div>
              <div class="col-md-2">
                <div class="form-group">
                  <label class="form-label">Vida Útil <span class="comentario">(años)</span></label>
                  <input type="number" class="form-control" name="vida_util"
                    placeholder="0" min="0" />
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-8">
                <div class="form-group">
                  <label class="form-label">Ubicación</label>
                  <input type="text" class="form-control" name="ubicacion"
                    placeholder="Ej. Oficina Ribereña, Almaguer, Obra..." />
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label class="form-label">Estatus <span class="required">*</span></label>
                  <select class="form-select" name="estatus" required>
                    <option value="activo">Activo</option>
                    <option value="inactivo">Inactivo</option>
                  </select>
                </div>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Notas Generales</label>
              <textarea class="form-control" name="notas" rows="2"
                placeholder="Observaciones, historial de mantenimiento, características adicionales..."></textarea>
            </div>

            <!-- ============================================================ -->
            <!-- VEHÍCULOS                                                     -->
            <!-- ============================================================ -->
            <div id="seccion-vehiculos" class="section-detalle">
              <div class="section-title"><i class="bi bi-truck"></i> Detalles del Vehículo</div>

              <small class="text-muted d-block mb-3">
                <i class="bi bi-info-circle"></i>
                Tipo de Gravamen: <br>
                -Libre: Propiedad plena. <br />
                -Limitado: Propiedad compartida o en proceso de pago. <br />
                -Con gravamen: Restricción legal o judicial activa.
              </small>

              <div class="row">
                <div class="col-md-4">
                  <div class="form-group">
                    <label class="form-label">Marca</label>
                    <input type="text" class="form-control" name="v_marca" placeholder="Ford, Toyota, Nissan..." />
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group">
                    <label class="form-label">Modelo</label>
                    <input type="text" class="form-control" name="v_modelo" placeholder="F-150, Hilux..." />
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group">
                    <label class="form-label">Año</label>
                    <input type="number" class="form-control" name="v_anio" placeholder="2024" min="1900" max="2099" />
                  </div>
                </div>
              </div>

              <div class="row">
                <div class="col-md-3">
                  <div class="form-group">
                    <label class="form-label">Color</label>
                    <input type="text" class="form-control" name="v_color" placeholder="Blanco, Rojo..." />
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="form-group">
                    <label class="form-label">Placa</label>
                    <input type="text" class="form-control" name="v_placa" placeholder="ABC-123-D" />
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="form-group">
                    <label class="form-label">VIN / Número de Serie</label>
                    <input type="text" class="form-control" name="v_vin" placeholder="17 caracteres..." />
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="form-group">
                    <label class="form-label">Número de Motor</label>
                    <input type="text" class="form-control" name="v_numero_motor" />
                  </div>
                </div>
              </div>

              <div class="row">
                <div class="col-md-4">
                  <div class="form-group">
                    <label class="form-label">Entidad Federativa</label>
                    <input type="text" class="form-control" name="v_entidad_federativa" placeholder="Tamaulipas, Nuevo León..." />
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group">
                    <label class="form-label">Número de Pedimento</label>
                    <input type="text" class="form-control" name="v_numero_pedimento" />
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group">
                    <label class="form-label">Origen</label>
                    <select class="form-select" name="v_origen">
                      <option value="">Seleccionar</option>
                      <option value="nacional">Nacional</option>
                      <option value="importado">Importado</option>
                    </select>
                  </div>
                </div>
              </div>

              <div class="row">
                <div class="col-md-4">
                  <div class="form-group">
                    <label class="form-label">Gravamen</label>
                    <select class="form-select" name="v_gravamen">
                      <option value="">Seleccionar</option>
                      <option value="libre">Libre</option>
                      <option value="limitado">Limitado</option>
                      <option value="gravado">Con gravamen</option>
                    </select>
                  </div>
                </div>
                <div class="col-md-8">
                  <div class="form-group">
                    <label class="form-label">Nombre del Propietario</label>
                    <input type="text" class="form-control" name="v_nombre_propietario" />
                  </div>
                </div>
              </div>

              <!-- SEGURO MÉXICO -->
              <div class="row">
                <div class="col-md-4">
                  <div class="form-group">
                    <!-- ✔ name corregido: v_nombre_aseguradora_mx -->
                    <label class="form-label">Nombre de Aseguradora <span class="comentario">(México)</span></label>
                    <input type="text" class="form-control" name="v_nombre_aseguradora_mx" placeholder="Qualitas, Inbursa..." />
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group">
                    <!-- ✔ name corregido: v_telefono_aseguradora_mx -->
                    <label class="form-label">Teléfono Aseguradora <span class="comentario">(México)</span></label>
                    <input type="text" class="form-control" name="v_telefono_aseguradora_mx" placeholder="800-XXX-XXXX" />
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group">
                    <!-- ✔ name corregido: v_fecha_vencimiento_seguro_mx -->
                    <label class="form-label">Fecha Vencimiento Seguro <span class="comentario">(México)</span></label>
                    <input type="date" class="form-control" name="v_fecha_vencimiento_seguro_mx" />
                  </div>
                </div>
              </div>

              <!-- SEGURO USA -->
              <div class="row">
                <div class="col-md-4">
                  <div class="form-group">
                    <!-- ✔ name corregido: v_nombre_aseguradora_usa -->
                    <label class="form-label">Nombre de Aseguradora <span class="comentario">(USA)</span></label>
                    <input type="text" class="form-control" name="v_nombre_aseguradora_usa" placeholder="GEICO, State Farm..." />
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group">
                    <!-- ✔ name corregido: v_telefono_aseguradora_usa -->
                    <label class="form-label">Teléfono Aseguradora <span class="comentario">(USA)</span></label>
                    <input type="text" class="form-control" name="v_telefono_aseguradora_usa" placeholder="800-XXX-XXXX" />
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group">
                    <!-- ✔ name corregido: v_fecha_vencimiento_seguro_usa -->
                    <label class="form-label">Fecha Vencimiento Seguro <span class="comentario">(USA)</span></label>
                    <input type="date" class="form-control" name="v_fecha_vencimiento_seguro_usa" />
                  </div>
                </div>
              </div>

            </div><!-- /seccion-vehiculos -->

            <!-- ============================================================ -->
            <!-- MAQUINARIA                                                    -->
            <!-- ============================================================ -->
            <div id="seccion-maquinaria" class="section-detalle">
              <div class="section-title"><i class="bi bi-gear-wide-connected"></i> Detalles de Maquinaria</div>

              <div class="row">
                <div class="col-md-4">
                  <div class="form-group">
                    <label class="form-label">Marca</label>
                    <input type="text" class="form-control" name="m_marca" placeholder="Caterpillar, Komatsu..." />
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group">
                    <label class="form-label">Modelo</label>
                    <input type="text" class="form-control" name="m_modelo" placeholder="D6T, PC200..." />
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group">
                    <label class="form-label">Número de Serie</label>
                    <input type="text" class="form-control" name="m_numero_serie" />
                  </div>
                </div>
              </div>

              <div class="row">
                <div class="col-md-4">
                  <div class="form-group">
                    <label class="form-label">Kilometraje / Horómetro</label>
                    <input type="number" class="form-control" name="m_kilometraje" placeholder="0" min="0" />
                  </div>
                </div>
                <div class="col-md-8">
                  <div class="form-group">
                    <label class="form-label">Foto Motor</label>
                    <input type="file" class="form-control" name="m_foto_motor" accept="image/*" />
                    <small class="text-muted">Formatos: JPG, PNG, GIF. Máx. 5 MB.</small>
                  </div>
                </div>
              </div>
            </div><!-- /seccion-maquinaria -->

            <!-- ============================================================ -->
            <!-- MOBILIARIO                                                    -->
            <!-- ============================================================ -->
            <div id="seccion-mobiliario" class="section-detalle">
              <div class="section-title"><i class="bi bi-archive"></i> Detalles de Mobiliario</div>

              <div class="row">
                <div class="col-md-4">
                  <div class="form-group">
                    <label class="form-label">Marca</label>
                    <input type="text" class="form-control" name="mob_marca" placeholder="Ikea, Steelcase..." />
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group">
                    <label class="form-label">Modelo</label>
                    <input type="text" class="form-control" name="mob_modelo" />
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group">
                    <label class="form-label">Número de Items</label>
                    <input type="number" class="form-control" name="mob_numero_items" placeholder="1" min="1" />
                  </div>
                </div>
              </div>

              <div class="row">
                <div class="col-md-4">
                  <div class="form-group">
                    <label class="form-label">Medida Aproximada</label>
                    <input type="text" class="form-control" name="mob_medida_aprox" placeholder="1.80 x 0.80 m" />
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group">
                    <label class="form-label">Edificio</label>
                    <input type="text" class="form-control" name="mob_edificio" placeholder="Edificio A, Torre Norte..." />
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group">
                    <label class="form-label">Área / Departamento</label>
                    <input type="text" class="form-control" name="mob_area_departamento" placeholder="Recursos Humanos, Gerencia..." />
                  </div>
                </div>
              </div>

              <div class="row">
                <div class="col-md-6">
                  <div class="form-group">
                    <label class="form-label">Dirección</label>
                    <input type="text" class="form-control" name="mob_direccion" placeholder="Calle, número, colonia..." />
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label class="form-label">Descripción</label>
                    <textarea class="form-control" name="mob_descripcion" rows="2"
                      placeholder="Características adicionales del mobiliario..."></textarea>
                  </div>
                </div>
              </div>
            </div><!-- /seccion-mobiliario -->

            <!-- ============================================================ -->
            <!-- INMUEBLES                                                     -->
            <!-- ============================================================ -->
            <div id="seccion-inmuebles" class="section-detalle">
              <div class="section-title"><i class="bi bi-building"></i> Detalles del Inmueble</div>

              <div class="row">
                <div class="col-md-4">
                  <div class="form-group">
                    <label class="form-label">Tipo de Inmueble</label>
                    <input type="text" class="form-control" name="inm_tipo" placeholder="Oficina, Bodega, Terreno..." />
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group">
                    <label class="form-label">Tipo de Posesión</label>
                    <input type="text" class="form-control" name="inm_tipo_posesion" placeholder="Propio, Arrendado, Comodato..." />
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group">
                    <label class="form-label">Uso</label>
                    <input type="text" class="form-control" name="inm_uso" placeholder="Habitacional, Comercial, Industrial..." />
                  </div>
                </div>
              </div>

              <div class="row">
                <div class="col-md-6">
                  <div class="form-group">
                    <label class="form-label">Dirección</label>
                    <input type="text" class="form-control" name="inm_direccion" placeholder="Calle, número, colonia, municipio..." />
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label class="form-label">Coordenadas GPS</label>
                    <input type="text" class="form-control" name="inm_coordenadas" placeholder="29.0729° N, 110.9559° W" />
                  </div>
                </div>
              </div>

              <div class="row">
                <div class="col-md-3">
                  <div class="form-group">
                    <label class="form-label">Superficie Terreno (m²)</label>
                    <input type="number" class="form-control" name="inm_superficie_terreno" placeholder="0.00" step="0.01" min="0" />
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="form-group">
                    <label class="form-label">Superficie Construida (m²)</label>
                    <input type="number" class="form-control" name="inm_superficie_construida" placeholder="0.00" step="0.01" min="0" />
                  </div>
                </div>
                <div class="col-md-2">
                  <div class="form-group">
                    <label class="form-label">Niveles</label>
                    <input type="number" class="form-control" name="inm_niveles" placeholder="1" min="0" />
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group">
                    <label class="form-label">Valor Terreno</label>
                    <input type="number" class="form-control" name="inm_valor_terreno" placeholder="0.00" step="0.01" min="0" />
                  </div>
                </div>
              </div>

              <div class="row">
                <div class="col-md-4">
                  <div class="form-group">
                    <label class="form-label">Folio RPP</label>
                    <input type="text" class="form-control" name="inm_folio_rpp" placeholder="Registro Público de la Propiedad" />
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group">
                    <label class="form-label">Predial</label>
                    <input type="text" class="form-control" name="inm_predial" placeholder="Número de cuenta predial" />
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group">
                    <label class="form-label">Estatus Legal</label>
                    <input type="text" class="form-control" name="inm_estatus_legal" placeholder="Regular, En litigio, Escriturado..." />
                  </div>
                </div>
              </div>

              <div class="row">
                <div class="col-md-6">
                  <div class="form-group">
                    <label class="form-label">Responsable Administrativo</label>
                    <input type="text" class="form-control" name="inm_responsable_administrativo" />
                  </div>
                </div>
              </div>
            </div><!-- /seccion-inmuebles -->

            <!-- ============================================================ -->
            <!-- HERRAMIENTAS                                                  -->
            <!-- ============================================================ -->
            <div id="seccion-herramientas" class="section-detalle">
              <div class="section-title"><i class="bi bi-tools"></i> Detalles de Herramienta</div>

              <div class="row">
                <div class="col-md-4">
                  <div class="form-group">
                    <label class="form-label">Marca</label>
                    <input type="text" class="form-control" name="h_marca" placeholder="Dewalt, Bosch, Stanley..." />
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group">
                    <label class="form-label">Modelo</label>
                    <input type="text" class="form-control" name="h_modelo" />
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group">
                    <label class="form-label">Número de Serie</label>
                    <input type="text" class="form-control" name="h_numero_serie" />
                  </div>
                </div>
              </div>

              <div class="row">
                <div class="col-md-6">
                  <div class="form-group">
                    <label class="form-label">Asignación</label>
                    <input type="text" class="form-control" name="h_asignacion" placeholder="Nombre de quien la usa o resguarda" />
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label class="form-label">Ubicación <span class="comentario">(Departamento/área)</span></label>
                    <input type="text" class="form-control" name="h_ubicacion_fisica" placeholder="Oficina RRHH, Almacén, Obra..." />
                  </div>
                </div>
              </div>

              <div class="form-group">
                <label class="form-label">Descripción</label>
                <textarea class="form-control" name="h_descripcion" rows="2"
                  placeholder="Características, accesorios incluidos..."></textarea>
              </div>
            </div><!-- /seccion-herramientas -->

            <!-- ============================================================ -->
            <!-- TICs                                                          -->
            <!-- ============================================================ -->
            <div id="seccion-tics" class="section-detalle">
              <div class="section-title"><i class="bi bi-laptop"></i> Detalles de TICs</div>

              <div class="row">
                <div class="col-md-4">
                  <div class="form-group">
                    <label class="form-label">Marca</label>
                    <input type="text" class="form-control" name="t_marca" placeholder="Dell, HP, Apple..." />
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group">
                    <label class="form-label">Modelo</label>
                    <input type="text" class="form-control" name="t_modelo" placeholder="Inspiron 15, MacBook Pro..." />
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group">
                    <label class="form-label">Número de Serie</label>
                    <input type="text" class="form-control" name="t_numero_serie" />
                  </div>
                </div>
              </div>

              <div class="row">
                <div class="col-md-4">
                  <div class="form-group">
                    <label class="form-label">Sistema Operativo</label>
                    <input type="text" class="form-control" name="t_sistema_operativo" placeholder="Windows 11, macOS 14..." />
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group">
                    <label class="form-label">Procesador</label>
                    <input type="text" class="form-control" name="t_procesador" placeholder="Intel Core i7, AMD Ryzen 5..." />
                  </div>
                </div>
                <div class="col-md-2">
                  <div class="form-group">
                    <label class="form-label">RAM</label>
                    <input type="text" class="form-control" name="t_ram" placeholder="16 GB" />
                  </div>
                </div>
                <div class="col-md-2">
                  <div class="form-group">
                    <label class="form-label">Almacenamiento</label>
                    <input type="text" class="form-control" name="t_almacenamiento" placeholder="512 GB SSD" />
                  </div>
                </div>
              </div>

              <div class="row">
                <div class="col-md-4">
                  <div class="form-group">
                    <label class="form-label">Office / Suite Ofimática</label>
                    <input type="text" class="form-control" name="t_office" placeholder="Microsoft 365, LibreOffice..." />
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group">
                    <label class="form-label">Correo Electrónico Asignado</label>
                    <input type="email" class="form-control" name="t_correo" placeholder="usuario@empresa.com" />
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group">
                    <label class="form-label">Ubicación Física</label>
                    <input type="text" class="form-control" name="t_ubicacion_fisica" placeholder="Oficina 3, Planta alta..." />
                  </div>
                </div>
              </div>

              <div class="row">
                <div class="col-md-6">
                  <div class="form-group">
                    <label class="form-label">Programas Instalados</label>
                    <textarea class="form-control" name="t_programas_instalados" rows="2"
                      placeholder="AutoCAD, Adobe Acrobat, Antivirus..."></textarea>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label class="form-label">Complementos / Accesorios</label>
                    <textarea class="form-control" name="t_complementos" rows="2"
                      placeholder="Mouse, teclado, monitor extra, docking station..."></textarea>
                  </div>
                </div>
              </div>
            </div><!-- /seccion-tics -->

            <!-- ============================================================ -->
            <!-- DOCUMENTOS                                                    -->
            <!-- ============================================================ -->
            <div class="section-title">
              <i class="bi bi-paperclip"></i> Documentos
            </div>

            <small class="text-muted d-block mb-4">
              <i class="bi bi-info-circle"></i>
              Todos los campos son opcionales. Máximo 10 MB por archivo (catálogo de refacciones hasta 1 GB).
            </small>

            <div class="row">
              <div class="col-md-6 doc-item mb-3">
                <label class="form-label">Factura / Comprobante de Compra</label>
                <input type="file" class="form-control" name="doc_factura"
                  accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" />
                <small class="text-muted">PDF, Word o imagen</small>
              </div>

              <div class="col-md-6 doc-item mb-3">
                <label class="form-label">Pedimento</label>
                <input type="file" class="form-control" name="doc_pedimento"
                  accept=".pdf,.doc,.docx" />
                <small class="text-muted">PDF o Word</small>
              </div>

              <div class="col-md-6 doc-item mb-3">
                <label class="form-label">Póliza de Seguro <span class="comentario">(México)</span></label>
                <input type="file" class="form-control" name="doc_poliza_seguro"
                  accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" />
                <small class="text-muted">PDF, Word o imagen</small>
              </div>

              <div class="col-md-6 doc-item mb-3">
                <label class="form-label">Póliza de Seguro <span class="comentario">(USA)</span></label>
                <input type="file" class="form-control" name="doc_poliza_seguro_usa"
                  accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" />
                <small class="text-muted">PDF, Word o imagen</small>
              </div>

              <div class="col-md-6 doc-item mb-3">
                <label class="form-label">Manual de Usuario / Operación</label>
                <input type="file" class="form-control" name="doc_manual"
                  accept=".pdf,.doc,.docx" />
                <small class="text-muted">PDF o Word</small>
              </div>

              <div class="col-md-6 doc-item mb-3">
                <label class="form-label">Manual de Mantenimiento</label>
                <input type="file" class="form-control" name="doc_manual_mantenimiento"
                  accept=".pdf,.doc,.docx" />
                <small class="text-muted">PDF o Word</small>
              </div>

              <div class="col-md-6 doc-item mb-3">
                <label class="form-label">Catálogo de Refacciones <span class="comentario">(máx. 1 GB)</span></label>
                <input type="file" class="form-control" name="doc_catalogo_refacciones"
                  accept=".pdf,.doc,.docx" />
                <small class="text-muted">PDF o Word</small>
              </div>

              <div class="col-md-6 doc-item mb-3">
                <label class="form-label">Contrato / Escritura</label>
                <input type="file" class="form-control" name="doc_contrato"
                  accept=".pdf,.doc,.docx" />
                <small class="text-muted">PDF o Word</small>
              </div>
            </div>

            <!-- ============================================================ -->
            <!-- IMÁGENES                                                      -->
            <!-- ============================================================ -->
            <div class="section-title">
              <i class="bi bi-card-image"></i> Imágenes
            </div>

            <div class="row">
              <div class="col-md-4 doc-item mb-3">
                <label class="form-label">Fotos <span class="comentario">(General, detalles extra, etc.)</span></label>
                <input type="file" class="form-control" name="img_foto_general[]"
                  accept=".jpg,.jpeg,.png,.gif,.webp" multiple />
                <small class="text-muted">Puedes seleccionar varias imágenes (JPG, PNG, GIF o WebP)</small>
              </div>

              <div class="col-md-4 doc-item mb-3">
                <label class="form-label">Foto de Placa</label>
                <input type="file" class="form-control" name="img_foto_placa"
                  accept=".jpg,.jpeg,.png,.gif,.webp" />
                <small class="text-muted">JPG, PNG, GIF o WebP</small>
              </div>

              <div class="col-md-4 doc-item mb-3">
                <label class="form-label">Foto de Número de Serie</label>
                <input type="file" class="form-control" name="img_foto_numero_serie"
                  accept=".jpg,.jpeg,.png,.gif,.webp" />
                <small class="text-muted">JPG, PNG, GIF o WebP</small>
              </div>
            </div>

            <!-- ============================================================ -->
            <!-- EXPEDIENTE CONTROL FISCAL / TENENCIA / PREDIAL               -->
            <!-- ============================================================ -->
            <div class="section-title">
              <i class="bi bi-paperclip"></i> Expediente de Control Fiscal y Tenencia / Predial
            </div>

            <div class="form-group">
              <small class="text-muted d-block mb-3">
                <i class="bi bi-info-circle"></i>
                Puedes adjuntar imágenes, documentos o capturas de pantalla. Máx. 10 archivos, 10 MB c/u.
              </small>
              <div class="input-group">
                <input type="file" class="form-control" id="singleFileInputFiscal"
                       accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx,.txt">
                <button class="btn btn-primary" type="button" onclick="agregarAdjunto('fiscal')"
                        style="background:#113456; transform:none;">
                  <i class="bi bi-plus-circle"></i> Agregar
                </button>
              </div>
            </div>

            <div id="adjuntosContainerFiscal" class="mt-2 mb-3">
              <h6 class="mb-2">Archivos seleccionados: <span id="contadorFiscal">0</span></h6>
              <ul id="adjuntosListFiscal" class="list-group">
                <li class="list-group-item text-center text-muted">
                  <i class="bi bi-inbox"></i> No hay archivos agregados
                </li>
              </ul>
            </div>

            <!-- ============================================================ -->
            <!-- DOCUMENTACIÓN EXTRA                                           -->
            <!-- ============================================================ -->
            <div class="section-title">
              <i class="bi bi-paperclip"></i> Documentación Extra
            </div>

            <div class="form-group">
              <small class="text-muted d-block mb-3">
                <i class="bi bi-info-circle"></i>
                Puedes adjuntar imágenes, documentos o capturas de pantalla. Máx. 10 archivos, 10 MB c/u.
              </small>
              <div class="input-group">
                <input type="file" class="form-control" id="singleFileInputExtra"
                       accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx,.txt">
                <button class="btn btn-primary" type="button" onclick="agregarAdjunto('extra')"
                        style="background:#113456; transform:none;">
                  <i class="bi bi-plus-circle"></i> Agregar
                </button>
              </div>
            </div>

            <div id="adjuntosContainerExtra" class="mt-2 mb-3">
              <h6 class="mb-2">Archivos seleccionados: <span id="contadorExtra">0</span></h6>
              <ul id="adjuntosListExtra" class="list-group">
                <li class="list-group-item text-center text-muted">
                  <i class="bi bi-inbox"></i> No hay archivos agregados
                </li>
              </ul>
            </div>

            <!-- ===== GUARDAR ===== -->
            <div class="form-actions mt-3">
              <div id="alertContainer" class="mt-2"></div>
              <div class="send-otxt">
                Verifique que toda la información sea correcta antes de guardar el registro.
              </div>
              <div class="container overflow-hidden text-center">
                <div class="row gx-5">
                  <div class="col">
                    <div class="p-3">
                      <button type="submit" class="button-57" id="btnGuardar">
                        <i class="bi bi-floppy"></i> Guardar Activo
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>

          </form>
        </div>
      </div>
    </div>

    <!-- Botón de regreso -->
    <div class="fab-container-backbtn">
      <a onclick="history.back()" class="fab-button-backbtn gray">
        <i class="bi bi-arrow-left"></i>
        <span class="fab-tooltip-backbtn">Volver</span>
      </a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
      integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
      crossorigin="anonymous"></script>

    <script>
    // ── Mapa tipo → sección dinámica ─────────────────────────────────────────
    const secciones = {
      'vehiculos'   : 'seccion-vehiculos',
      'vehículos'   : 'seccion-vehiculos',
      'maquinaria'  : 'seccion-maquinaria',
      'mobiliario'  : 'seccion-mobiliario',
      'inmuebles'   : 'seccion-inmuebles',
      'herramientas': 'seccion-herramientas',
      'tics'        : 'seccion-tics',
      'tic'         : 'seccion-tics',
    };

    function normalizarTexto(str) {
      return str.toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .trim();
    }

    function mostrarSeccionDetalle() {
      document.querySelectorAll('.section-detalle').forEach(el => el.classList.remove('visible'));

      const select = document.getElementById('tipo_id');
      const option = select.options[select.selectedIndex];
      if (!option || !option.value) return;

      const nombreTipo = normalizarTexto(option.text);
      const prefijo    = option.getAttribute('data-prefijo') || '';

      document.getElementById('codigoPreview').innerHTML =
        '<i class="bi bi-upc-scan"></i> ' + prefijo + '-XXXX (se asignará al guardar)';

      for (const [clave, idSeccion] of Object.entries(secciones)) {
        if (nombreTipo.includes(normalizarTexto(clave))) {
          const sec = document.getElementById(idSeccion);
          if (sec) sec.classList.add('visible');
          break;
        }
      }
    }

    // ── Adjuntos dinámicos (fiscal + extra) ──────────────────────────────────
    const pools = { fiscal: [], extra: [] };

    function agregarAdjunto(tipo) {
      const inputId = tipo === 'fiscal' ? 'singleFileInputFiscal' : 'singleFileInputExtra';
      const input   = document.getElementById(inputId);
      if (!input.files.length) { alert('Por favor seleccione un archivo primero.'); return; }
      const file = input.files[0];
      if (pools[tipo].length >= 10) { alert('Solo puede agregar hasta 10 archivos por sección.'); return; }
      if (file.size > 10 * 1024 * 1024) { alert('El archivo "' + file.name + '" supera el límite de 10 MB.'); return; }
      pools[tipo].push(file);
      renderLista(tipo);
      input.value = '';
    }

    function eliminarAdjunto(tipo, index) {
      pools[tipo].splice(index, 1);
      renderLista(tipo);
    }

    function renderLista(tipo) {
      const listId  = tipo === 'fiscal' ? 'adjuntosListFiscal' : 'adjuntosListExtra';
      const countId = tipo === 'fiscal' ? 'contadorFiscal'     : 'contadorExtra';
      const lista   = document.getElementById(listId);
      document.getElementById(countId).textContent = pools[tipo].length;
      if (!pools[tipo].length) {
        lista.innerHTML = '<li class="list-group-item text-center text-muted">'
          + '<i class="bi bi-inbox"></i> No hay archivos agregados</li>';
        return;
      }
      lista.innerHTML = pools[tipo].map((f, i) =>
        `<li class="list-group-item d-flex justify-content-between align-items-center">
          <span><i class="bi bi-file-earmark"></i> ${f.name}
            <small class="text-muted ms-2">(${(f.size/1024).toFixed(1)} KB)</small>
          </span>
          <button type="button" class="btn btn-sm btn-outline-danger"
                  onclick="eliminarAdjunto('${tipo}',${i})">
            <i class="bi bi-trash"></i>
          </button>
        </li>`
      ).join('');
    }

    // ── Submit via FormData + fetch (único método que envía files de pools JS) ─
    document.getElementById('activoForm').addEventListener('submit', function(e) {
      e.preventDefault();

      const btn = document.getElementById('btnGuardar');
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Guardando...';

      const fd = new FormData(this);

      // Agregar archivos de ambos pools
      pools.fiscal.forEach(f => {
  fd.append('documentos[]', f, f.name);
  fd.append('documentos_tipo[]', 'expediente_predial');
});
pools.extra.forEach(f => {
  fd.append('documentos[]', f, f.name);
  fd.append('documentos_tipo[]', 'extra');
});

      fetch(this.action, { method: 'POST', body: fd })
        .then(res => {
          // save_activo.php termina con header(Location:...) → fetch recibe la redirección
          // Tomamos la URL final y navegamos a ella
          if (res.redirected) { window.location.href = res.url; return; }
          // Si no hubo redirect, revisar si hay error en el body
          return res.text().then(html => {
            // Buscar error en la respuesta
            if (html.includes('error') || res.status >= 400) {
              btn.disabled = false;
              btn.innerHTML = '<i class="bi bi-floppy"></i> Guardar Activo';
              alert('Ocurrió un error al guardar. Revisa el log del servidor.');
            } else {
              window.location.href = res.url || 'list_activos.php';
            }
          });
        })
        .catch(err => {
          btn.disabled = false;
          btn.innerHTML = '<i class="bi bi-floppy"></i> Guardar Activo';
          alert('Error de red: ' + err.message);
        });
    });
    </script>

        <script>
    // ── Sincronizar departamento al seleccionar responsable ───────────────────────
    document.getElementById('responsable').addEventListener('change', function() {
      const selectedOption = this.options[this.selectedIndex];
      const departamentoId = selectedOption.getAttribute('data-departamento');
      const departamentoSelect = document.getElementById('departamento');
      if (departamentoSelect) {
        departamentoSelect.value = departamentoId || '';
      }
    });
    </script>

    <?php include __DIR__ . "/../includes/footer.php"; ?>
  </body>
</html>