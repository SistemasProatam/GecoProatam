// ===== VARIABLES GLOBALES =====
let archivosEliminados = [];
let presupuestoActual = {
  proyecto: { disponible: 0, utilizado: 0, total: 0 },
  obra: { disponible: 0, utilizado: 0, total: 0 }
};
let productosCatalogo = []; 

// ===== CONSTANTES PARA ARCHIVOS =====
const MAX_FILES = 5;
const MAX_SIZE = 10 * 1024 * 1024; // 10MB
let archivosAcumulados = []; 

// ===== FUNCIONES PARA ADJUNTAR ARCHIVOS =====

// Función para agregar archivo a la lista
function agregarArchivo() {
  const singleFileInput = document.getElementById('singleFileInput');
  const file = singleFileInput.files[0];
  
  if (!file) {
    mostrarAlertaArchivos('Por favor seleccione un archivo primero.', 'warning');
    return;
  }
  
  // Validar número máximo de archivos
  if (archivosAcumulados.length >= MAX_FILES) {
    mostrarAlertaArchivos(`Ya alcanzó el límite de ${MAX_FILES} archivos.`, 'danger');
    singleFileInput.value = '';
    return;
  }
  
  // Validar tamaño del archivo
  if (file.size > MAX_SIZE) {
    mostrarAlertaArchivos(`El archivo "${file.name}" excede el tamaño máximo de 10MB.`, 'danger');
    singleFileInput.value = '';
    return;
  }
  
  // Validar que no sea duplicado
  const existe = archivosAcumulados.some(f => f.name === file.name && f.size === file.size);
  if (existe) {
    mostrarAlertaArchivos(`El archivo "${file.name}" ya fue agregado.`, 'warning');
    singleFileInput.value = '';
    return;
  }
  
  // Agregar archivo al array
  archivosAcumulados.push(file);
  
  // Actualizar vista
  actualizarListaArchivos();
  
  // Limpiar input
  singleFileInput.value = '';
  
  // Mensaje de éxito
  mostrarAlertaArchivos(`Archivo "${file.name}" agregado correctamente.`, 'success');
}

// Función para actualizar la lista visual de archivos nuevos
function actualizarListaArchivos() {
  const fileList = document.getElementById('fileList');
  const contador = document.getElementById('contadorArchivos');
  
  fileList.innerHTML = '';
  contador.textContent = archivosAcumulados.length;
  
  if (archivosAcumulados.length === 0) {
    fileList.innerHTML = `
      <li class="list-group-item text-center text-muted">
        <i class="bi bi-inbox"></i> No hay archivos agregados
      </li>
    `;
    return;
  }
  
  archivosAcumulados.forEach((file, index) => {
    const li = document.createElement('li');
    li.className = 'list-group-item d-flex justify-content-between align-items-center';
    
    // Determinar icono y color según extensión
    const extension = file.name.split('.').pop().toLowerCase();
    let icono = 'file-earmark';
    let colorClass = 'text-secondary';
    
    if (extension === 'pdf') {
      icono = 'file-earmark-pdf';
      colorClass = 'text-danger';
    } else if (['doc', 'docx'].includes(extension)) {
      icono = 'file-earmark-word';
      colorClass = 'text-primary';
    } else if (['xls', 'xlsx'].includes(extension)) {
      icono = 'file-earmark-excel';
      colorClass = 'text-success';
    } else if (['jpg', 'jpeg', 'png', 'gif'].includes(extension)) {
      icono = 'file-earmark-image';
      colorClass = 'text-warning';
    }
    
    const fileSize = (file.size / 1024).toFixed(2);
    
    li.innerHTML = `
      <div>
        <span class="badge bg-secondary me-2">${index + 1}</span>
        <i class="bi bi-${icono} ${colorClass} me-2"></i>
        <strong>${file.name}</strong>
        <span class="badge bg-light text-dark ms-2">${fileSize} KB</span>
      </div>
      <button type="button" class="btn btn-sm btn-danger" onclick="eliminarArchivo(${index})">
        <i class="bi bi-trash"></i> Quitar
      </button>
    `;
    
    fileList.appendChild(li);
  });
}

// Función para eliminar un archivo nuevo específico
function eliminarArchivo(index) {
  const archivo = archivosAcumulados[index];
  archivosAcumulados.splice(index, 1);
  actualizarListaArchivos();
  mostrarAlertaArchivos(`Archivo "${archivo.name}" eliminado.`, 'info');
}

// Función para mostrar alertas de archivos
function mostrarAlertaArchivos(msg, tipo = 'info') {
  const alertContainer = document.getElementById('alertContainer');
  const alert = document.createElement('div');
  alert.className = `alert alert-${tipo} alert-dismissible fade show`;
  alert.role = 'alert';
  alert.innerHTML = `
    ${msg}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  `;
  
  alertContainer.innerHTML = '';
  alertContainer.appendChild(alert);
  
  setTimeout(() => {
    if (alert.parentElement) {
      alert.remove();
    }
  }, 5000);
}

// ===== FUNCIONES DE VALIDACIÓN =====
function validarFormulario() {
  const folio = document.getElementById('numeroOrden').value;
  const entidad = document.getElementById('entidad').value;
  const proveedor = document.getElementById('proveedor').value;
  const proyecto = document.getElementById('proyecto').value;
  const categoria = document.getElementById('categoria').value;
  
  console.log('Validando formulario:');
  console.log('Folio:', folio);
  console.log('Entidad:', entidad);
  console.log('Proveedor:', proveedor);
  console.log('Proyecto:', proyecto);
  console.log('Categoría:', categoria);
  
  // Validar campos obligatorios
  if (!folio || !folio.trim()) {
    mostrarAlertaArchivos('El número de orden es obligatorio', 'danger');
    document.getElementById('numeroOrden').focus();
    return false;
  }
  
  if (!entidad) {
    mostrarAlertaArchivos('La entidad es obligatoria', 'danger');
    document.getElementById('entidad').focus();
    return false;
  }
  
  if (!proveedor) {
    mostrarAlertaArchivos('El proveedor es obligatorio', 'danger');
    document.getElementById('proveedor').focus();
    return false;
  }
  
  if (!proyecto) {
    mostrarAlertaArchivos('El proyecto es obligatorio', 'danger');
    document.getElementById('proyecto').focus();
    return false;
  }
  
  if (!categoria) {
    mostrarAlertaArchivos('La categoría es obligatoria', 'danger');
    document.getElementById('categoria').focus();
    return false;
  }
  
  // Validar que haya al menos un item con datos completos
  const itemsTable = document.getElementById('itemsTable').getElementsByTagName('tbody')[0];
  const filasValidas = Array.from(itemsTable.rows).filter(row => {
    const descripcion = row.querySelector('.descripcion')?.value;
    const cantidad = row.querySelector('.cantidad')?.value;
    const precio = row.querySelector('.precio')?.value;
    const unidad = row.querySelector('select[name="unidad_id[]"]')?.value;
    
    return descripcion && descripcion.trim() && cantidad && precio && unidad;
  });
  
  if (filasValidas.length === 0) {
    mostrarAlertaArchivos('Debe agregar al menos un item completo a la orden', 'danger');
    return false;
  }
  
  return true;
}

// ===== FUNCIONES DE PRESUPUESTO =====
function cargarObrasYPresupuesto() {
  const proyectoId = document.getElementById('proyecto').value;
  const obraSelect = document.getElementById('obra');
  const catalogoSelect = document.getElementById('catalogo');
  const infoProyecto = document.getElementById('infoProyecto');
  const infoObra = document.getElementById('infoObra');
  const alertPresupuesto = document.getElementById('alertPresupuesto');

  console.log('Proyecto seleccionado:', proyectoId);

  // Resetear obras y catálogos
  obraSelect.innerHTML = '<option value="">-- Sin obra específica --</option>';
  if (infoObra) infoObra.style.display = 'none';
  if (infoProyecto) infoProyecto.style.display = 'none';
  if (alertPresupuesto) alertPresupuesto.style.display = 'none';

  if (!proyectoId) {
    if (catalogoSelect) resetSelect(catalogoSelect, '-- Sin catálogo específico --');
    resetAllItemConceptos('Primero seleccione un catálogo');
    return;
  }

  obraSelect.disabled = true;
  obraSelect.innerHTML = '<option value="">Cargando obras...</option>';

  // Cargar información del presupuesto del proyecto
  fetch(`get_presupuesto_proyecto.php?proyecto_id=${proyectoId}`)
    .then(response => {
      if (!response.ok) {
        throw new Error('Error en la respuesta del servidor');
      }
      return response.json();
    })
    .then(data => {
      console.log('Datos COMPLETOS recibidos del servidor:', data);
      
      if (data.error) {
        mostrarAlerta(data.error, 'danger');
        obraSelect.innerHTML = '<option value="">Error al cargar</option>';
        obraSelect.disabled = true;
        return;
      }

      // Mostrar información básica del proyecto (solo informativa)
      if (infoProyecto && data.proyecto) {
        const montoProyectoEl = document.getElementById('montoProyecto');
        if (montoProyectoEl) {
          montoProyectoEl.textContent = data.proyecto.total.toLocaleString('es-MX', {minimumFractionDigits: 2});
        }
        infoProyecto.style.display = 'block';
      }

      // Resetear el select de obras
      obraSelect.innerHTML = '<option value="">-- Sin obra específica --</option>';

      // Cargar obras del proyecto (COSTO DIRECTO)
      if (data.obras && data.obras.length > 0) {
        console.log('OBRAS RECIBIDAS:', data.obras);
        
        data.obras.forEach(obra => {
          const option = document.createElement('option');
          option.value = obra.id;
          option.textContent = `${obra.numero_obra} - ${obra.nombre_obra}`;
          
          // VERIFICAR QUÉ VALORES TIENE CADA OBRA
          console.log(`OBRA ${obra.id}:`, {
            total: obra.total,
            disponible: obra.disponible,
            utilizado: obra.utilizado
          });
          
          option.setAttribute('data-total', obra.total || 0);
          option.setAttribute('data-disponible', obra.disponible || 0);
          option.setAttribute('data-utilizado', obra.utilizado || 0);
          
          obraSelect.appendChild(option);
        });

        obraSelect.disabled = false;
      } else {
        console.log('No hay obras para este proyecto');
        obraSelect.disabled = false;
      }

      if (catalogoSelect) resetSelect(catalogoSelect, '-- Sin catálogo específico --');
      resetAllItemConceptos('Primero seleccione un catálogo');
      actualizarPresupuesto();
    })
    .catch(error => {
      console.error('Error en fetch:', error);
      mostrarAlerta('Error al cargar la información del proyecto: ' + error.message, 'danger');
      obraSelect.innerHTML = '<option value="">Error al cargar</option>';
      obraSelect.disabled = true;
    });
}

// ===== HANDLER PARA CAMBIO DE OBRA (SE CONFIGURA UNA SOLA VEZ) =====
function handleObraChange() {
  const obraSelect = document.getElementById('obra');
  const selectedOption = obraSelect.options[obraSelect.selectedIndex];
  const infoObra = document.getElementById('infoObra');
  const catalogoSelect = document.getElementById('catalogo');
  
  console.log('OBRA CAMBIADA - Option seleccionada:', selectedOption);
  
  if (obraSelect.value) {
    // USAR SOLO EL COSTO DIRECTO DE LA OBRA
    const total = parseFloat(selectedOption.getAttribute('data-total')) || 0;
    const disponible = parseFloat(selectedOption.getAttribute('data-disponible')) || 0;
    const utilizado = parseFloat(selectedOption.getAttribute('data-utilizado')) || 0;
    
    console.log('VALORES EXTRAÍDOS:', { total, disponible, utilizado });
    
    presupuestoActual.obra = {
      total: total,
      disponible: disponible,
      utilizado: utilizado
    };
    
    console.log('PRESUPUESTO ACTUAL OBRA:', presupuestoActual.obra);
    
    // Mostrar información de la OBRA (costo directo)
    const montoObraEl = document.getElementById('montoObra');
    const disponibleObraEl = document.getElementById('disponibleObra');
    const progressObraEl = document.getElementById('progressObra');
    
    if (montoObraEl) {
      montoObraEl.textContent = presupuestoActual.obra.total.toLocaleString('es-MX', {minimumFractionDigits: 2});
    }
    if (disponibleObraEl) {
      disponibleObraEl.textContent = presupuestoActual.obra.disponible.toLocaleString('es-MX', {minimumFractionDigits: 2});
    }
    
    if (progressObraEl) {
      const porcentajeObra = presupuestoActual.obra.total > 0 ? 
        (presupuestoActual.obra.utilizado / presupuestoActual.obra.total) * 100 : 0;
      progressObraEl.style.width = `${Math.min(porcentajeObra, 100)}%`;
      progressObraEl.className = `progress-bar ${porcentajeObra > 90 ? 'bg-danger' : porcentajeObra > 70 ? 'bg-warning' : 'bg-success'}`;
    }
    
    if (infoObra) infoObra.style.display = 'block';
    
    // Limpiar presupuesto de proyecto para forzar uso de obra
    presupuestoActual.proyecto = null;
    
    // Cargar catálogos de la obra
    cargarCatalogos();
  } else {
    presupuestoActual.obra = null;
    if (infoObra) infoObra.style.display = 'none';
    if (catalogoSelect) resetSelect(catalogoSelect, '-- Sin catálogo específico --');
    resetAllItemConceptos('Primero seleccione un catálogo');
  }
  
  actualizarPresupuesto();
}

function actualizarPresupuesto() {
  const obraId = document.getElementById('obra')?.value;
  const alertPresupuesto = document.getElementById('alertPresupuesto');
  const infoObra = document.getElementById('infoObra');
  const btnEnviar = document.getElementById('btnEnviar');

  // Resetear alerta
  if (alertPresupuesto) alertPresupuesto.style.display = 'none';

  let presupuestoSeleccionado = null;
  let tipoPresupuesto = '';

  // USAR SOLO LA OBRA SI ESTÁ SELECCIONADA
  if (obraId && presupuestoActual.obra) {
    presupuestoSeleccionado = presupuestoActual.obra;
    tipoPresupuesto = 'obra';

    console.log('Costo directo de obra seleccionado:', presupuestoSeleccionado);

    // Mostrar información de la obra (ya se mostró en handleObraChange, pero por si acaso)
    if (infoObra) infoObra.style.display = 'block';
  } else {
    // Si no hay obra seleccionada, mostrar mensaje
    if (alertPresupuesto) {
      alertPresupuesto.innerHTML = `
        <div class="alert alert-info">
          <i class="bi bi-info-circle"></i> 
          <strong>Seleccione una obra</strong><br>
          Para validar el presupuesto, debe seleccionar una obra específica.
        </div>
      `;
      alertPresupuesto.style.display = 'block';
    }
    if (btnEnviar) btnEnviar.disabled = true;
    return;
  }

  const totalOrden = parseFloat(document.getElementById('totalGeneral')?.textContent.replace('$', '').replace(/,/g, '')) || 0;
  console.log('Total orden:', totalOrden, 'Costo directo disponible:', presupuestoSeleccionado.disponible);
  validarPresupuesto(totalOrden, presupuestoSeleccionado, tipoPresupuesto);
}

function validarPresupuesto(totalOrden, presupuesto, tipo) {
  const alertPresupuesto = document.getElementById('alertPresupuesto');
  const btnEnviar = document.getElementById('btnEnviar');

  if (!alertPresupuesto || !btnEnviar) return;

  if (totalOrden === 0) {
    alertPresupuesto.style.display = 'none';
    btnEnviar.disabled = false;
    return;
  }

  if (totalOrden > presupuesto.disponible) {
    const faltante = totalOrden - presupuesto.disponible;
    alertPresupuesto.innerHTML = `
      <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle"></i> 
        <strong>Presupuesto insuficiente</strong><br>
        El total de la orden ($${totalOrden.toLocaleString('es-MX', {minimumFractionDigits: 2})}) 
        excede el presupuesto disponible del ${tipo} ($${presupuesto.disponible.toLocaleString('es-MX', {minimumFractionDigits: 2})})<br>
        <strong>Faltante:</strong> $${faltante.toLocaleString('es-MX', {minimumFractionDigits: 2})}
      </div>
    `;
    alertPresupuesto.style.display = 'block';
    btnEnviar.disabled = true;
  } else {
    const porcentajeUtilizado = ((presupuesto.utilizado + totalOrden) / presupuesto.total) * 100;
    let alertClass = 'alert-success';
    let icon = 'bi-check-circle';
    let mensaje = 'Presupuesto suficiente';

    if (porcentajeUtilizado > 90) {
      alertClass = 'alert-danger';
      icon = 'bi-exclamation-triangle';
      mensaje = 'Presupuesto casi agotado';
    } else if (porcentajeUtilizado > 70) {
      alertClass = 'alert-warning';
      icon = 'bi-exclamation-circle';
      mensaje = 'Presupuesto en advertencia';
    }

    alertPresupuesto.innerHTML = `
      <div class="alert ${alertClass}">
        <i class="bi ${icon}"></i> 
        <strong>${mensaje}</strong><br>
        Total orden: $${totalOrden.toLocaleString('es-MX', {minimumFractionDigits: 2})} | 
        Disponible: $${presupuesto.disponible.toLocaleString('es-MX', {minimumFractionDigits: 2})}
      </div>
    `;
    alertPresupuesto.style.display = 'block';
    btnEnviar.disabled = false;
  }
}

function mostrarAlerta(mensaje, tipo) {
  const alertPresupuesto = document.getElementById('alertPresupuesto');
  if (alertPresupuesto) {
    alertPresupuesto.innerHTML = `
      <div class="alert alert-${tipo}">
        <i class="bi bi-exclamation-triangle"></i> ${mensaje}
      </div>
    `;
    alertPresupuesto.style.display = 'block';
  }
}

// ===== ELIMINAR LA FUNCIÓN cargarObras() ANTIGUA Y REEMPLAZAR CON ESTO =====
function cargarObras() {
  // Esta función ahora solo llama a cargarObrasYPresupuesto
  cargarObrasYPresupuesto();
}

// ===== FUNCIONES DE PRODUCTOS/SERVICIOS =====
function buscarProductos(input) {
  const termino = input.value.toLowerCase();
  const row = input.closest('tr');
  const autocompleteList = row.querySelector('.autocomplete-list');
  
  if (termino.length < 2) {
    autocompleteList.style.display = 'none';
    return;
  }

  const resultados = productosCatalogo.filter(producto => 
    producto.nombre.toLowerCase().includes(termino) ||
    (producto.descripcion && producto.descripcion.toLowerCase().includes(termino))
  );

  if (resultados.length === 0) {
    autocompleteList.innerHTML = '<div class="autocomplete-item">No se encontraron productos</div>';
  } else {
    autocompleteList.innerHTML = resultados.map(producto => 
      `<div class="autocomplete-item" onclick="seleccionarProductoEnLista(${producto.id}, '${producto.nombre.replace(/'/g, "\\'")}', this)">
        <strong>${producto.nombre}</strong><br>
        <small class="text-muted">${producto.descripcion || 'Sin descripción'} - ${producto.tipo}</small>
      </div>`
    ).join('');
  }
  
  autocompleteList.style.display = 'block';
}

function seleccionarProductoEnLista(productoId, nombre, elemento) {
  const row = elemento.closest('tr');
  const descripcionInput = row.querySelector('.descripcion');
  const productoIdInput = row.querySelector('input[name="producto_id[]"]');
  const tipoInput = row.querySelector('input[name="tipo[]"]');
  
  // Buscar el producto en el catálogo para obtener el tipo
  const producto = productosCatalogo.find(p => p.id == productoId);
  
  descripcionInput.value = nombre;
  productoIdInput.value = productoId;
  tipoInput.value = producto ? producto.tipo : '';
  
  // Ocultar autocomplete
  const autocompleteList = row.querySelector('.autocomplete-list');
  autocompleteList.style.display = 'none';
}

function mostrarCatalogoProductos() {
  const modal = new bootstrap.Modal(document.getElementById('modalCatalogo'));
  modal.show();
}

function seleccionarProducto(productoId, nombre) {
  // Agregar a la primera fila vacía o crear nueva
  const table = document.getElementById('itemsTable').getElementsByTagName('tbody')[0];
  let filaVacia = null;
  
  // Buscar primera fila vacía
  for (let i = 0; i < table.rows.length; i++) {
    const descInput = table.rows[i].querySelector('.descripcion');
    if (!descInput.value.trim()) {
      filaVacia = table.rows[i];
      break;
    }
  }
  
  if (!filaVacia) {
    addItem();
    filaVacia = table.rows[table.rows.length - 1];
  }
  
  const descripcionInput = filaVacia.querySelector('.descripcion');
  const productoIdInput = filaVacia.querySelector('input[name="producto_id[]"]');
  const tipoInput = filaVacia.querySelector('input[name="tipo[]"]');
  
  // Buscar el producto en el catálogo para obtener el tipo
  const producto = productosCatalogo.find(p => p.id == productoId);
  
  descripcionInput.value = nombre;
  productoIdInput.value = productoId;
  tipoInput.value = producto ? producto.tipo : '';
  
  // Cerrar modal
  const modal = bootstrap.Modal.getInstance(document.getElementById('modalCatalogo'));
  modal.hide();
}

function addItem(itemData = null) {
  const table = document.getElementById('itemsTable').getElementsByTagName('tbody')[0];
  const newRow = table.insertRow();
  const rowCount = table.rows.length;

  const catalogoId = document.getElementById('catalogo').value;
  const conceptoPlaceholder = catalogoId ? 'Cargando conceptos...' : 'Primero seleccione un catálogo';
  const conceptoDisabled = !catalogoId;

  newRow.innerHTML = `
    <td>${rowCount}</td>
    <td>
      <div class="producto-autocomplete">
        <input type="text" class="form-control descripcion" name="descripcion[]" placeholder="Descripción" 
               oninput="buscarProductos(this)" required>
        <div class="autocomplete-list" id="autocomplete-${rowCount}"></div>
      </div>
      <input type="hidden" name="producto_id[]" value="">
      <input type="hidden" name="tipo[]" value="">
    </td>
    <td>
      <input type="number" name="cantidad[]" class="form-control cantidad" 
       min="0.001" step="0.001" value="1" 
       onchange="calcularSubtotal(this)" required>
    </td>
    <td>
      <select name="unidad_id[]" class="form-select" required>
        <option value="">Seleccionar Unidad</option>${window.unidadOptions}
      </select>
    </td>
    <td>
      <select name="concepto_id[]" class="form-select concepto-select" ${conceptoDisabled ? 'disabled' : ''}>
        <option value="">${conceptoPlaceholder}</option>
      </select>
    </td>
    <td>
      <input type="number"  name="precio_unitario[]" class="form-control precio" min="0" step="0.01" placeholder="0.00" onchange="calcularSubtotal(this)" required>
    </td>
    <td class="subtotal">$0.00</td>
    <td>
      <button type="button" class="btn btn-sm btn-danger remove-item-btn" onclick="removeItem(this)">
        <i class="bi bi-trash"></i>
      </button>
    </td>
  `;

  // Llenar datos si se proporcionan
  if (itemData) {
    const descripcionInput = newRow.querySelector('.descripcion');
    const productoIdInput = newRow.querySelector('input[name="producto_id[]"]');
    const tipoInput = newRow.querySelector('input[name="tipo[]"]');
    const cantidadInput = newRow.querySelector('.cantidad');
    const unidadSelect = newRow.querySelector('select[name="unidad_id[]"]');
    const precioInput = newRow.querySelector('.precio');
    const conceptoSelect = newRow.querySelector('.concepto-select');

    if (descripcionInput) descripcionInput.value = itemData.producto_nombre || '';
    if (productoIdInput) productoIdInput.value = itemData.producto_id || '';
    if (tipoInput) tipoInput.value = itemData.producto_tipo || '';
    if (cantidadInput) cantidadInput.value = itemData.cantidad || 1;
    if (unidadSelect) unidadSelect.value = itemData.unidad_id || '';
    if (precioInput) precioInput.value = itemData.precio_unitario || 0;

    // Guardar concepto_id para cargarlo después cuando estén disponibles los conceptos
    if (itemData.concepto_id) {
      newRow.dataset.conceptoId = itemData.concepto_id;
      
      const conceptoHidden = document.createElement('input');
      conceptoHidden.type = 'hidden';
      conceptoHidden.name = 'concepto_id[]';
      conceptoHidden.value = itemData.concepto_id;
      conceptoHidden.className = 'concepto-hidden-input';
      newRow.appendChild(conceptoHidden);
      
      console.log('Campo oculto creado para concepto_id:', itemData.concepto_id);
    }

    // Si ya hay catálogo seleccionado, cargar conceptos para esta fila
    if (catalogoId) {
      setTimeout(() => {
        cargarConceptosParaFila(newRow, catalogoId, itemData.concepto_id || null);
      }, 100);
    }
  }

  // Si hay catálogo pero no hay concepto específico, cargar lista de conceptos
  if (catalogoId && (!itemData || !itemData.concepto_id)) {
    cargarConceptosParaFila(newRow, catalogoId, null);
  }
}

function removeItem(button) {
  const row = button.closest('tr');
  row.remove();
  renumberRows();
  calcularTotales();
  actualizarPresupuesto();
}

function renumberRows() {
  const table = document.getElementById('itemsTable').getElementsByTagName('tbody')[0];
  for (let i = 0; i < table.rows.length; i++) {
    table.rows[i].cells[0].textContent = i + 1;
  }
}

// ===== CARGAR ITEMS DE LA REQUISICIÓN =====
function cargarItemsRequisicion() {
  if (window.requisicionItems && window.requisicionItems.length > 0) {
    console.log('Cargando items de requisición:', window.requisicionItems);
    
    window.requisicionItems.forEach((item, index) => {
      console.log('Procesando item:', item);
      
      // Agregar nueva fila con todos los datos
      addItem({
        producto_nombre: item.producto_nombre || '',
        producto_id: item.producto_id || '',
        producto_tipo: item.producto_tipo || '',
        cantidad: item.cantidad || 1,
        unidad_id: item.unidad_id || '',
        concepto_id: item.concepto_id || '',
        precio_unitario: item.precio_unitario || 0
      });

      // Obtener la fila recién creada
      const tableBody = document.querySelector('#itemsTable tbody');
      const lastRow = tableBody.lastElementChild;

      if (lastRow) {
        console.log('Fila creada, configurando concepto:', item.concepto_id);
        
        // Guardar el concepto_id en un data attribute para usarlo después
        if (item.concepto_id) {
          lastRow.dataset.conceptoId = item.concepto_id;
          console.log('Concepto guardado en data attribute:', lastRow.dataset.conceptoId);
        }

        // Si ya hay un catálogo seleccionado, cargar conceptos para esta fila
        const catalogoId = document.getElementById('catalogo').value;
        if (catalogoId && item.concepto_id) {
          console.log('Catálogo disponible, cargando conceptos para fila');
          // Usar setTimeout para asegurar que el DOM esté listo
          setTimeout(() => {
            cargarConceptosParaFila(lastRow, catalogoId, item.concepto_id);
          }, 100);
        }
      }
    });

    // Recalcular totales después de cargar todos los items
    setTimeout(() => {
      calcularTotales();
      actualizarPresupuesto();
      console.log('Items de requisición cargados completamente');
    }, 500);
  } else {
    console.log('No hay items de requisición para cargar');
  }
}

function cargarConceptosParaTodosLosItems(catalogoId) {
    const table = document.getElementById('itemsTable').getElementsByTagName('tbody')[0];
    const rows = table.rows;
    
    console.log(`Cargando conceptos para ${rows.length} filas con catálogo:`, catalogoId);
    
    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const conceptoId = row.dataset.conceptoId;
        
        if (conceptoId) {
            console.log(`Cargando conceptos para fila ${i} con concepto_id:`, conceptoId);
            cargarConceptosParaFila(row, catalogoId, conceptoId);
        } else {
            console.log(`Fila ${i} no tiene concepto_id guardado`);
        }
    }
}

// ===== FUNCIONES DE CÁLCULO =====
function calcularSubtotal(input) {
  const row = input.closest('tr');
  const cantidadInput = row.querySelector('.cantidad');
  const precioInput = row.querySelector('.precio');
  
  // Obtener y limpiar valores
  const cantidad = parseFloat(cantidadInput.value) || 0;
  
  // Limpiar el valor del precio - remover cualquier caracter no numérico excepto punto decimal
  const precioRaw = precioInput.value.replace(/[^\d.]/g, '');
  const precio = parseFloat(precioRaw) || 0;
  
  const subtotal = cantidad * precio;
  
  // Redondear a dos decimales
  const subtotalRedondeado = Math.round(subtotal * 100) / 100;
  
  // Actualizar el subtotal en la tabla
  row.querySelector('.subtotal').textContent = '$' + subtotalRedondeado.toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2});
  
  // Recalcular todos los totales
  calcularTotales();
}

function calcularTotales() {
  let subtotalGeneral = 0;
  
  document.querySelectorAll('.subtotal').forEach(cell => {
    // Limpiar el texto del subtotal - remover $ y cualquier otro caracter no numérico excepto punto decimal
    const valorTexto = cell.textContent.replace(/[^\d.]/g, '');
    const valor = parseFloat(valorTexto) || 0;
    subtotalGeneral += valor;
  });

  // Redondear a dos decimales
  subtotalGeneral = Math.round(subtotalGeneral * 100) / 100;

  // Actualizar el subtotal general
  document.getElementById('subtotalGeneral').textContent = '$' + subtotalGeneral.toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2});
  
  // Recalcular IVA y total general
  calcularIVA();
}

function calcularIVA() {
  // Limpiar el subtotal general
  const subtotalTexto = document.getElementById('subtotalGeneral').textContent.replace(/[^\d.]/g, '');
  const subtotal = parseFloat(subtotalTexto) || 0;
  
  const ivaPorcentaje = parseFloat(document.getElementById('iva').value) || 0;
  const ivaTotal = subtotal * (ivaPorcentaje / 100);
  const totalGeneral = subtotal + ivaTotal;

  // Redondear a dos decimales
  const ivaTotalRedondeado = Math.round(ivaTotal * 100) / 100;
  const totalGeneralRedondeado = Math.round(totalGeneral * 100) / 100;

  document.getElementById('ivaTotal').textContent = '$' + ivaTotalRedondeado.toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2});
  document.getElementById('totalGeneral').textContent = '$' + totalGeneralRedondeado.toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2});

  actualizarPresupuesto();
}

// Funciones para archivos de requisición
function eliminarArchivoTemporal(archivoId, btn) {
  if(!confirm('¿Está seguro de que desea eliminar este archivo de la orden de compra?\n\nNota: El archivo permanecerá en la requisición original.')) {
    return;
  }
  
  archivosEliminados.push(archivoId);
  const fila = btn.closest('tr');
  fila.style.transition = 'opacity 0.3s';
  fila.style.opacity = '0';
  setTimeout(() => {
    fila.remove();
    const tbody = document.getElementById('tablaArchivos');
    if(tbody.children.length === 0) {
      tbody.innerHTML = `
        <tr>
          <td colspan="5" class="text-center text-muted">
            <i class="bi bi-inbox"></i> No hay archivos seleccionados
          </td>
        </tr>
      `;
    } else {
      Array.from(tbody.children).forEach((row, index) => {
        row.children[0].textContent = index + 1;
      });
    }
  }, 300);
}

function descargarArchivo(archivoId) {
  window.open('/orders/download_archivo.php?id=' + archivoId, '_blank');
}

// Función para sincronizar selects con campos ocultos
function sincronizarConceptosConFormulario() {
    console.log('=== SINCRONIZANDO CONCEPTOS CON FORMULARIO ===');
    const table = document.getElementById('itemsTable').getElementsByTagName('tbody')[0];
    const rows = table.rows;
    
    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const conceptoSelect = row.querySelector('select[name="concepto_id[]"]');
        const hiddenInput = row.querySelector('input[name="concepto_id[]"]');
        
        if (conceptoSelect && hiddenInput) {
            // Si el select cambió, actualizar el campo oculto
            if (conceptoSelect.value !== hiddenInput.value) {
                hiddenInput.value = conceptoSelect.value;
                console.log(`✅ Campo oculto actualizado para fila ${i}: ${hiddenInput.value}`);
            }
        } else if (conceptoSelect && !hiddenInput && conceptoSelect.value) {
            // Crear campo oculto si no existe
            const conceptoHidden = document.createElement('input');
            conceptoHidden.type = 'hidden';
            conceptoHidden.name = 'concepto_id[]';
            conceptoHidden.value = conceptoSelect.value;
            conceptoHidden.className = 'concepto-hidden-input';
            row.appendChild(conceptoHidden);
            console.log(`✅ Campo oculto creado para fila ${i}: ${conceptoSelect.value}`);
        }
    }
}

// ===== MANEJO DEL ENVÍO DEL FORMULARIO =====
function setupFormSubmit() {
  document.getElementById('ordenCompraForm').addEventListener('submit', function(e) {
    // Prevenir envío por defecto
    e.preventDefault();
    
    console.log('=== INICIANDO ENVÍO DEL FORMULARIO ===');

     sincronizarConceptosConFormulario();
    
    // Validar formulario antes de continuar
    if (!validarFormulario()) {
      console.log('Validación falló');
      return;
    }
    
    console.log('Validación exitosa, preparando envío...');
    
    // ===========================================
    // CORRECCIÓN: AGREGAR CAMPOS OCULTOS CON VALORES LIMPIOS
    // ===========================================
    const subtotalElement = document.getElementById('subtotalGeneral');
    const totalElement = document.getElementById('totalGeneral');
    
    // Limpiar valores (remover $, comas y espacios)
    const subtotalLimpio = subtotalElement.textContent.replace(/[^\d.]/g, '');
    const totalLimpio = totalElement.textContent.replace(/[^\d.]/g, '');
    
    console.log('Subtotal limpio:', subtotalLimpio);
    console.log('Total limpio:', totalLimpio);
    
    // Eliminar campos ocultos anteriores si existen
    const existingSubtotalHidden = document.getElementById('subtotalHidden');
    const existingTotalHidden = document.getElementById('totalHidden');
    const existingProyectoId = document.getElementById('proyecto_id');
    const existingCategoriaId = document.getElementById('categoria_id');
    
    if (existingSubtotalHidden) existingSubtotalHidden.remove();
    if (existingTotalHidden) existingTotalHidden.remove();
    if (existingProyectoId) existingProyectoId.remove();
    if (existingCategoriaId) existingCategoriaId.remove();
    
    // Crear nuevos campos ocultos
    const subtotalHidden = document.createElement('input');
    subtotalHidden.type = 'hidden';
    subtotalHidden.name = 'subtotal';
    subtotalHidden.id = 'subtotalHidden';
    subtotalHidden.value = subtotalLimpio;
    
    const totalHidden = document.createElement('input');
    totalHidden.type = 'hidden';
    totalHidden.name = 'total';
    totalHidden.id = 'totalHidden';
    totalHidden.value = totalLimpio;
    
    // CORRECCIÓN: Campos con nombres que espera el backend
    const proyectoId = document.createElement('input');
    proyectoId.type = 'hidden';
    proyectoId.name = 'proyecto_id';
    proyectoId.id = 'proyecto_id';
    proyectoId.value = document.getElementById('proyecto').value;
    
    const categoriaId = document.createElement('input');
    categoriaId.type = 'hidden';
    categoriaId.name = 'categoria_id';
    categoriaId.id = 'categoria_id';
    categoriaId.value = document.getElementById('categoria').value;
    
    // Agregar campos al formulario
    this.appendChild(subtotalHidden);
    this.appendChild(totalHidden);
    this.appendChild(proyectoId);
    this.appendChild(categoriaId);
    
    // ===========================================
    // VERIFICAR SI EL TOTAL ES CERO
    // ===========================================
    const totalOrden = parseFloat(totalLimpio) || 0;
    if (totalOrden === 0) {
      if(!confirm('El total de la orden es $0.00. ¿Desea continuar?')) {
        return;
      }
    }
    
    // Deshabilitar botón para prevenir doble envío
    const btnEnviar = document.getElementById('btnEnviar');
    const originalText = btnEnviar.innerHTML;
    btnEnviar.innerHTML = '<i class="bi bi-hourglass-split"></i> Guardando...';
    btnEnviar.disabled = true;
    
    // CREAR FORMDATA PRIMERO (antes de deshabilitar campos)
    const formData = new FormData(this);
    
    // AHORA SÍ deshabilitar campos para prevenir edición durante envío
    const formElements = this.elements;
    for (let i = 0; i < formElements.length; i++) {
      formElements[i].disabled = true;
    }
    
    // DEBUG: Mostrar datos que se enviarán
    console.log('Datos del formulario:');
    for (let pair of formData.entries()) {
      console.log(pair[0] + ': ' + pair[1]);
    }
    
    // Agregar archivos eliminados de la requisición
    archivosEliminados.forEach(id => {
      formData.append('archivos_eliminados[]', id);
    });
    
    // Agregar cada archivo nuevo acumulado al FormData
    archivosAcumulados.forEach((file, index) => {
      formData.append('archivos_nuevos[]', file);
    });
    
    // Agregar indicador de que estamos usando el nuevo sistema de archivos
    formData.append('nuevo_sistema_archivos', '1');
    
    console.log('Enviando datos al servidor...');
    
    // Enviar con fetch
    fetch('save_orden.php', {
      method: 'POST',
      body: formData
    })
    .then(response => {
      console.log('Respuesta recibida, status:', response.status);
      const contentType = response.headers.get('content-type');
      if (contentType && contentType.includes('application/json')) {
        return response.json();
      } else {
        return response.text().then(text => {
          console.log('Respuesta no JSON:', text);
          throw new Error('Respuesta del servidor no es JSON. Posible error PHP.');
        });
      }
    })
    .then(data => {
      console.log('Datos JSON recibidos:', data);
      if (data.success) {
        console.log('Guardado exitoso, redirigiendo...');
        if (data.redirect) {
          window.location.href = data.redirect;
        } else {
          window.location.href = 'list_oc.php?msg=success';
        }
      } else {
        throw new Error(data.message || 'Error desconocido del servidor');
      }
    })
    .catch(error => {
      console.error('Error completo:', error);
      mostrarAlertaArchivos('Error al guardar la orden de compra: ' + error.message, 'danger');
      
      // Restaurar botón y formulario
      habilitarFormulario(btnEnviar, originalText, formElements);
    });
  });
}

// Función para habilitar el formulario
function habilitarFormulario(btnEnviar, originalText, formElements) {
  btnEnviar.innerHTML = originalText;
  btnEnviar.disabled = false;
  for (let i = 0; i < formElements.length; i++) {
    formElements[i].readOnly = false;
    formElements[i].disabled = false;
  }
}

// Fecha automática
function establecerFechaAutomatica() {
  const fechaInput = document.getElementById('fecha_solicitud');
  const ahora = new Date();
    const opciones = {
        timeZone: "America/Matamoros",
        year: "numeric",
        month: "2-digit",
        day: "2-digit",
        hour: "2-digit",
        minute: "2-digit",
        hour12: false
    };
    const formateado = new Intl.DateTimeFormat('sv-SE', opciones).format(ahora);
    fechaInput.value = formateado.replace(" ", "T");
    
    // Inicializar lista de archivos vacía
    actualizarListaArchivos();
}

// Generar número de orden según entidad
function setupEntidadChange() {
  document.getElementById('entidad').addEventListener('change', function() {
    const entidadId = this.value;
    
    if(!entidadId) {
      document.getElementById('numeroOrden').value = '';
      return;
    }

    // Mostrar loading
    const numeroOrdenInput = document.getElementById('numeroOrden');
    numeroOrdenInput.value = 'Generando...';

    fetch('/orders/get_next_folio_oc.php?entidad_id=' + entidadId)
      .then(response => {
        if (!response.ok) {
          throw new Error('Error en la respuesta del servidor');
        }
        return response.json();
      })
      .then(data => {
        if(data.success) {
          document.getElementById('numeroOrden').value = data.folio;
          console.log('Folio generado:', data.folio);
        } else {
          document.getElementById('numeroOrden').value = '';
          console.error('Error al generar folio:', data.message);
          mostrarAlertaArchivos('Error al generar el número de orden: ' + data.message, 'warning');
        }
      })
      .catch(error => {
        console.error('Error:', error);
        document.getElementById('numeroOrden').value = '';
        mostrarAlertaArchivos('Error al conectar con el servidor para generar el número de orden', 'danger');
      });
  });
}

// Filtrar catálogo en modal
function setupBuscarCatalogo() {
  document.getElementById('buscarCatalogo').addEventListener('input', function() {
    const termino = this.value.toLowerCase();
    const filas = document.getElementById('tbodyCatalogo').getElementsByTagName('tr');
    
    for (let fila of filas) {
      const texto = fila.textContent.toLowerCase();
      fila.style.display = texto.includes(termino) ? '' : 'none';
    }
  });
}

// Cerrar autocomplete al hacer clic fuera
function setupCloseAutocomplete() {
  document.addEventListener('click', function(e) {
    if (!e.target.closest('.producto-autocomplete')) {
      document.querySelectorAll('.autocomplete-list').forEach(list => {
        list.style.display = 'none';
      });
    }
  });
}

// ===== FUNCIONES PARA CASCADA PROYECTO -> OBRA -> CATÁLOGO -> CONCEPTOS =====
function cargarCatalogos() {
  const obraId = document.getElementById('obra').value;
  const catalogoSelect = document.getElementById('catalogo');
  
  if (!obraId) {
    resetSelect(catalogoSelect, '-- Sin catálogo específico --');
    resetAllItemConceptos('Primero seleccione un catálogo');
    return;
  }
  
  catalogoSelect.disabled = true;
  catalogoSelect.innerHTML = '<option value="">Cargando catálogos...</option>';
  
  fetch(`get_catalogos_by_obra.php?obra_id=${obraId}`)
    .then(response => response.json())
    .then(catalogos => {
      if (catalogos.error) {
        catalogoSelect.innerHTML = `<option value="">Error: ${catalogos.error}</option>`;
        return;
      }
      
      catalogoSelect.innerHTML = '<option value="">-- Sin catálogo específico --</option>';
      catalogos.forEach(catalogo => {
        catalogoSelect.innerHTML += `<option value="${catalogo.id}">${catalogo.nombre_catalogo}</option>`;
      });
      catalogoSelect.disabled = false;
      
      resetAllItemConceptos('Primero seleccione un catálogo');
    })
    .catch(error => {
      console.error('Error:', error);
      catalogoSelect.innerHTML = '<option value="">Error al cargar catálogos</option>';
    });
}

function cargarConceptosEnItems() {
  const catalogoId = document.getElementById('catalogo').value;
  const conceptoSelects = document.querySelectorAll('.concepto-select');
  
  if (!catalogoId) {
    resetAllItemConceptos('Primero seleccione un catálogo');
    return;
  }

  // Bloquear selects mientras se carga
  conceptoSelects.forEach(select => {
    select.disabled = true;
    select.innerHTML = '<option value="">Cargando conceptos...</option>';
  });

  // Cargar conceptos del catálogo
  fetch(`get_conceptos_by_catalogo.php?catalogo_id=${catalogoId}`)
    .then(response => response.json())
    .then(conceptos => {
      if (conceptos.error) {
        conceptoSelects.forEach(select => {
          select.innerHTML = `<option value="">Error: ${conceptos.error}</option>`;
        });
        return;
      }

      const conceptosHTML = '<option value="">Seleccionar Concepto</option>' +
        conceptos.map(concepto => {
          const displayText = concepto.numero_original
            ? `#${concepto.numero_original} - ${concepto.codigo_concepto}`
            : concepto.codigo_concepto;
          return `<option value="${concepto.id}">${displayText}</option>`;
        }).join('');

      // Aplicar a todos los selects
      conceptoSelects.forEach(select => {
        select.innerHTML = conceptosHTML;
        select.disabled = false;
      });

      // Si existe una requisición, asignar los conceptos correctos a cada fila
      const requisicionId = document.getElementById('requisicion_id')?.value || null;
      if (requisicionId) {
        fetch(`get_conceptos_requisicion.php?requisicion_id=${requisicionId}`)
          .then(resp => resp.json())
          .then(items => {
            if (items && Array.isArray(items)) {
              items.forEach(item => {
                // Buscar la fila correspondiente por data-item-id
                const select = document.querySelector(`.concepto-select[data-item-id="${item.id}"]`);
                if (select && item.concepto_id) {
                  select.value = item.concepto_id;
                }
              });
            }
          })
          .catch(error => console.error('Error al cargar conceptos de la requisición:', error));
      }
    })
    .catch(error => {
      console.error('Error al cargar conceptos:', error);
      conceptoSelects.forEach(select => {
        select.innerHTML = '<option value="">Error al cargar conceptos</option>';
      });
    });
}

function resetSelect(selectElement, placeholder) {
  selectElement.innerHTML = `<option value="">${placeholder}</option>`;
  selectElement.disabled = true;
}

function resetAllItemConceptos(placeholder) {
  document.querySelectorAll('.concepto-select').forEach(select => {
    select.innerHTML = `<option value="">${placeholder}</option>`;
    select.disabled = true;
  });
}

// Función auxiliar: carga los conceptos para una fila específica
function cargarConceptosParaFila(row, catalogoId, selectedConceptoId = null) {
    const conceptoSelect = row.querySelector('.concepto-select');
    if (!conceptoSelect) {
        console.error('No se encontró el select de concepto en la fila');
        return;
    }

    console.log('cargarConceptosParaFila - catalogoId:', catalogoId, 'selectedConceptoId:', selectedConceptoId);
    
    // Si no hay catálogo seleccionado, no hacer nada
    if (!catalogoId) {
        conceptoSelect.innerHTML = '<option value="">Primero seleccione un catálogo</option>';
        conceptoSelect.disabled = true;
        return;
    }

    conceptoSelect.disabled = true;
    conceptoSelect.innerHTML = '<option value="">Cargando conceptos...</option>';

    fetch(`get_conceptos_by_catalogo.php?catalogo_id=${catalogoId}`)
    .then(response => {
        if (!response.ok) {
            throw new Error('Error en la respuesta del servidor');
        }
        return response.json();
    })
    .then(conceptos => {
        if (conceptos.error) {
            conceptoSelect.innerHTML = `<option value="">Error: ${conceptos.error}</option>`;
            return;
        }

        let conceptosHTML = '<option value="">Seleccionar Concepto (Opcional)</option>';
        let conceptoEncontrado = false;
        
        conceptos.forEach(concepto => {
            const displayText = concepto.numero_original
                ? `#${concepto.numero_original} - ${concepto.codigo_concepto}`
                : concepto.codigo_concepto;
            
            // Usar el selectedConceptoId proporcionado
            const finalConceptoId = selectedConceptoId || row.dataset.conceptoId;
            const selected = (finalConceptoId && finalConceptoId == concepto.id) ? 'selected' : '';
            
            if (selected) {
                conceptoEncontrado = true;
                console.log('Concepto encontrado y seleccionado:', concepto.id, displayText);
            }
            
            conceptosHTML += `<option value="${concepto.id}" ${selected}>${displayText}</option>`;
        });

        conceptoSelect.innerHTML = conceptosHTML;
        conceptoSelect.disabled = false;
        
        // ✅ FORZAR LA SELECCIÓN después de cargar las opciones
        if (selectedConceptoId) {
            setTimeout(() => {
                conceptoSelect.value = selectedConceptoId;
                console.log('✅ Select value forzado a:', selectedConceptoId, 'Actual:', conceptoSelect.value);
                
                // Verificar que realmente se seleccionó
                if (conceptoSelect.value != selectedConceptoId) {
                    console.warn('⚠️ El concepto no se pudo seleccionar automáticamente');
                }
            }, 100);
        }
        
        if (conceptoEncontrado) {
            console.log('✅ Concepto seleccionado correctamente en el select');
        } else if (selectedConceptoId) {
            console.warn('⚠️ Concepto ID', selectedConceptoId, 'no encontrado en la lista de conceptos');
        }
    })
    .catch(error => {
        console.error('❌ Error al cargar conceptos:', error);
        conceptoSelect.innerHTML = '<option value="">Error al cargar conceptos</option>';
    });
}

function verificarConceptosSeleccionados() {
    console.log('=== VERIFICANDO CONCEPTOS SELECCIONADOS ===');
    const table = document.getElementById('itemsTable').getElementsByTagName('tbody')[0];
    const rows = table.rows;
    
    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const conceptoId = row.dataset.conceptoId;
        const conceptoSelect = row.querySelector('.concepto-select');
        
        if (conceptoId && conceptoSelect) {
            console.log(`Fila ${i} - Concepto esperado: ${conceptoId}, Select actual: ${conceptoSelect.value}`);
            
            if (conceptoSelect.value != conceptoId) {
                console.log(`⚠️ Corrigiendo concepto en fila ${i}: ${conceptoSelect.value} -> ${conceptoId}`);
                conceptoSelect.value = conceptoId;
            }
        }
    }
}

// ===== INICIALIZACIÓN =====
function initNewOrder(config) {
  console.log('=== INICIALIZANDO FORMULARIO ===');
  
  // Guardar configuración global
  if (config.productosCatalogo) {
    productosCatalogo = config.productosCatalogo;
  }
  if (config.unidadOptions) {
    window.unidadOptions = config.unidadOptions;
  }
  if (config.requisicionItems) {
    window.requisicionItems = config.requisicionItems;
  }
  
  // Establecer fecha automática
  establecerFechaAutomatica();
  
  // ✅ CONFIGURAR EVENT LISTENER DE OBRA UNA SOLA VEZ
  const obraSelect = document.getElementById('obra');
  if (obraSelect) {
    obraSelect.addEventListener('change', handleObraChange);
  }
  
  // Cargar items de la requisición si existe
  cargarItemsRequisicion();
  
  // Inicializar cálculos
  calcularTotales();
  
  // Inicializar lista de archivos
  actualizarListaArchivos();
  
  // Setup event listeners
  setupFormSubmit();
  setupEntidadChange();
  setupBuscarCatalogo();
  setupCloseAutocomplete();
  
  // Si viene de una requisición, generar folio automáticamente
  if (config.requisicionId && config.entidadId) {
    setTimeout(() => {
      const entidadSelect = document.getElementById('entidad');
      if (entidadSelect.value) {
        console.log('Generando folio automático para requisición...');
        const event = new Event('change');
        entidadSelect.dispatchEvent(event);
      }
    }, 500);
  }
  
  // Cargar obras y catálogos si viene de requisición con datos
  if (config.requisicion) {
    setTimeout(() => {
        const proyectoSelect = document.getElementById('proyecto');
        if (proyectoSelect && config.requisicion.proyecto_id) {
            proyectoSelect.value = config.requisicion.proyecto_id;
            cargarObrasYPresupuesto(); 
            
            // Esperar a que carguen las obras y seleccionar la correcta
            setTimeout(() => {
                const obraSelect = document.getElementById('obra');
                if (obraSelect && config.requisicion.obra_id) {
                    obraSelect.value = config.requisicion.obra_id;
                    // Disparar el evento change manualmente
                    const event = new Event('change');
                    obraSelect.dispatchEvent(event);
                    
                    // Esperar a que carguen los catálogos
                    setTimeout(() => {
                        const catalogoSelect = document.getElementById('catalogo');
                        if (catalogoSelect && config.requisicion.catalogo_id) {
                            catalogoSelect.value = config.requisicion.catalogo_id;
                            console.log('✅ Catálogo seleccionado, cargando conceptos...');
                            
                            // Forzar carga de conceptos para todos los items
                            setTimeout(() => {
                                cargarConceptosParaTodosLosItems(config.requisicion.catalogo_id);
                                
                                // Verificar y corregir después de un tiempo
                                setTimeout(() => {
                                    verificarConceptosSeleccionados();
                                }, 1000);
                            }, 300);
                        }
                    }, 800);
                }
            }, 800);
        }
    }, 1000);
  }
}

// Exponer funciones globales necesarias
window.agregarArchivo = agregarArchivo;
window.eliminarArchivo = eliminarArchivo;
window.eliminarArchivoTemporal = eliminarArchivoTemporal;
window.descargarArchivo = descargarArchivo;
window.addItem = addItem;
window.removeItem = removeItem;
window.calcularSubtotal = calcularSubtotal;
window.calcularIVA = calcularIVA;
window.buscarProductos = buscarProductos;
window.seleccionarProductoEnLista = seleccionarProductoEnLista;
window.seleccionarProducto = seleccionarProducto;
window.mostrarCatalogoProductos = mostrarCatalogoProductos;
window.guardarBorrador = guardarBorrador;
window.cargarObras = cargarObras;
window.cargarCatalogos = cargarCatalogos;
window.cargarConceptosEnItems = cargarConceptosEnItems;
window.initNewOrder = initNewOrder;