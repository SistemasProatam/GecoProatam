// ===== VARIABLES GLOBALES =====
let archivosEliminados = [];
let presupuestoActual = {
    obra: {
        total: 0,           // costo_directo
        totalContratos: 0,  // suma total subcontratos (incl. extraordinarios)
        utilizado: 0,       // pagado real
        comprometido: 0,    // (OC activas no pagadas)
        disponible: 0       // costo_directo - totalContratos
    }
};
let productosCatalogo = []; 

// ===== CONSTANTES PARA ARCHIVOS =====
// Helper de formato — agregar junto a las variables globales
const fmt = n => (parseFloat(n) || 0).toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });
const MAX_FILES = 5;
const MAX_SIZE = 10 * 1024 * 1024; // 10MB
let archivosAcumulados = []; 

// ===== CONSTANTES PARA SUBCONTRATOS =====
const CATEGORIAS_SUBCONTRATO = ['2', '5'];
// Función para verificar si es categoría de subcontrato
function esCategoriaSubcontrato(categoriaId) {
    return CATEGORIAS_SUBCONTRATO.includes(categoriaId);
}

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
  if (tipo === 'danger' || tipo === 'error') UI.toast.error(msg);
  else if (tipo === 'warning') UI.toast.warning(msg);
  else if (tipo === 'success') UI.toast.success(msg);
  else UI.toast.info(msg);
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

// ===== FUNCIONES DE SUBCONTRATO =====

// Variable para almacenar subcontratos de la obra
let subcontratosDisponibles = [];
let subcontratoActual = {
    id: null,
    total: 0,               // total_estimado
    extraordinarios: 0,
    totalContrato: 0,       // total_estimado + extraordinarios
    utilizado: 0,           // pagado real
    comprometido: 0,        // tentativo
    disponible: 0           // total_contrato - utilizado - comprometido
};

// Función para manejar cambio de categoría
function handleCategoriaChange() {
    console.log('=== handleCategoriaChange ===');
    const categoriaId = document.getElementById('categoria').value;
    const subcontratoContainer = document.getElementById('subcontratoContainer');
    
    const esSubcontrato = esCategoriaSubcontrato(categoriaId);
    
    console.log('Categoría seleccionada:', categoriaId);
    console.log('Es categoría de subcontrato:', esSubcontrato);
    
    if (esSubcontrato) {
        console.log('Mostrando contenedor de subcontrato');
        subcontratoContainer.style.display = 'block';
        
        // Si ya hay una obra seleccionada, cargar subcontratos
        const obraId = document.getElementById('obra').value;
        if (obraId) {
            console.log('Cargando subcontratos para obra:', obraId);
            cargarSubcontratos();
        } else {
            console.log('No hay obra seleccionada, no se cargan subcontratos');
        }
    } else {
        console.log('Ocultando contenedor de subcontrato');
        if (subcontratoContainer) subcontratoContainer.style.display = 'none';
        
        // Restaurar proveedores y conceptos normales
        restaurarListaProveedores();
        
        // Recargar conceptos normales del catálogo
        const catalogoId = document.getElementById('catalogo').value;
        if (catalogoId) {
            cargarConceptosEnItems();
        }
    }
    
    actualizarPresupuesto();
}

// Función para cargar subcontratos según obra
function cargarSubcontratos() {
    const obraId = document.getElementById('obra').value;
    const subcontratoSelect = document.getElementById('subcontrato');
    const infoSubcontrato = document.getElementById('infoSubcontrato');
    const catalogoSelect = document.getElementById('catalogo');
    
    console.log('cargarSubcontratos - obraId:', obraId);
    
    if (!obraId) {
        console.log('No hay obra seleccionada');
        subcontratoSelect.innerHTML = '<option value="">-- Primero seleccione una obra --</option>';
        subcontratoSelect.disabled = true;
        infoSubcontrato.style.display = 'none';
        return;
    }
    
    subcontratoSelect.disabled = true;
    subcontratoSelect.innerHTML = '<option value="">Cargando subcontratos...</option>';
    
    fetch(`get_subcontratos_by_obra.php?obra_id=${obraId}`)
        .then(response => {
            console.log('Respuesta fetch status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Subcontratos recibidos:', data);
            
            if (data.error) {
                console.error('Error en respuesta:', data.error);
                subcontratoSelect.innerHTML = `<option value="">Error: ${data.error}</option>`;
                subcontratoSelect.disabled = true;
                return;
            }
            
            subcontratosDisponibles = data.subcontratos || [];
            subcontratoSelect.innerHTML = '<option value="">-- Seleccionar subcontrato --</option>';
            subcontratoSelect.disabled = false;
            
            if (subcontratosDisponibles.length === 0) {
                console.log('No hay subcontratos para esta obra');
                subcontratoSelect.innerHTML = '<option value="">No hay subcontratos registrados</option>';
                subcontratoSelect.disabled = true;
                infoSubcontrato.style.display = 'none';
                resetAllItemConceptos('No hay subcontratos para esta obra');
            } else {
                console.log(`Cargando ${subcontratosDisponibles.length} subcontratos`);
                subcontratosDisponibles.forEach(sub => {
                  const option = document.createElement('option');
                  option.value = sub.id;
                  option.textContent = sub.proveedor_nombre || 'Sin proveedor';
                  option.setAttribute('data-total',           parseFloat(sub.total_estimado       || 0));
                  option.setAttribute('data-total-contrato',  parseFloat(sub.total_contrato       || 0));
                  option.setAttribute('data-utilizado',       parseFloat(sub.utilizado_pagado     || 0));
                  option.setAttribute('data-comprometido',    parseFloat(sub.comprometido_tentativo || 0));
                  option.setAttribute('data-disponible',      parseFloat(sub.disponible_subcontrato || 0));
                  option.setAttribute('data-proveedor-id',    sub.proveedor_id || '');
                  subcontratoSelect.appendChild(option);
                });
                
                // Si ya hay catálogo, mostrar mensaje para seleccionar subcontrato
                if (catalogoSelect && catalogoSelect.value) {
                    resetAllItemConceptos('Seleccione un subcontrato para ver conceptos');
                }
            }
        })
        .catch(error => {
            console.error('Error al cargar subcontratos:', error);
            subcontratoSelect.innerHTML = '<option value="">Error al cargar subcontratos</option>';
            subcontratoSelect.disabled = true;
            mostrarAlertaArchivos('Error al cargar subcontratos: ' + error.message, 'danger');
        });
}

// Función para manejar cambio de subcontrato
function handleSubcontratoChange() {
    console.log('=== handleSubcontratoChange ===');
    const subcontratoSelect = document.getElementById('subcontrato');
    const selectedOption    = subcontratoSelect.options[subcontratoSelect.selectedIndex];
    const infoSubcontrato   = document.getElementById('infoSubcontrato');
    const catalogoSelect    = document.getElementById('catalogo');
    const proveedorSelect   = document.getElementById('proveedor');

    if (subcontratoSelect.value && selectedOption) {

        // ── Leer atributos ────────────────────────────────────────────────────
        const totalBase     = parseFloat(selectedOption.getAttribute('data-total'))          || 0;
        const totalContrato = parseFloat(selectedOption.getAttribute('data-total-contrato')) || 0;
        const utilizado     = parseFloat(selectedOption.getAttribute('data-utilizado'))      || 0;
        const comprometido  = parseFloat(selectedOption.getAttribute('data-comprometido'))   || 0;
        const disponible    = parseFloat(selectedOption.getAttribute('data-disponible'))     || 0;
        const proveedorId   = selectedOption.getAttribute('data-proveedor-id');

        console.log('Datos del subcontrato:', {
            id: subcontratoSelect.value,
            totalBase, totalContrato, utilizado, comprometido, disponible
        });

        // ── Actualizar estado global ──────────────────────────────────────────
        subcontratoActual = {
            id:              subcontratoSelect.value,
            total:           totalBase,
            totalContrato:   totalContrato,
            extraordinarios: totalContrato - totalBase,
            utilizado:       utilizado,
            comprometido:    comprometido,
            disponible:      disponible
        };

        const subcontratoHidden = document.createElement('input');
        subcontratoHidden.type = 'hidden';
        subcontratoHidden.id = 'subcontrato_id_hidden';
        subcontratoHidden.name = 'subcontrato_id';  // <- Importante: nombre que espera el servidor
        subcontratoHidden.value = subcontratoSelect.value;
        document.getElementById('ordenCompraForm').appendChild(subcontratoHidden);
        console.log('Campo oculto creado para subcontrato_id:', subcontratoSelect.value);

        // ── 1. Seleccionar y bloquear proveedor ───────────────────────────────
        if (proveedorId && proveedorSelect) {
            let found = false;
            for (let i = 0; i < proveedorSelect.options.length; i++) {
                if (proveedorSelect.options[i].value == proveedorId) {
                    proveedorSelect.selectedIndex = i;
                    found = true;
                    break;
                }
            }

            if (found) {
                proveedorSelect.disabled = true;
                let hiddenField = document.getElementById('proveedor_from_subcontrato');
                if (!hiddenField) {
                    hiddenField = document.createElement('input');
                    hiddenField.type  = 'hidden';
                    hiddenField.id    = 'proveedor_from_subcontrato';
                    hiddenField.name  = 'proveedor_from_subcontrato';
                    const form = document.getElementById('ordenCompraForm');
                    if (form) form.appendChild(hiddenField);
                }
                hiddenField.value = proveedorId;
            } else {
                proveedorSelect.disabled = true;
                proveedorSelect.value    = '';
                mostrarAlertaArchivos('El proveedor asociado al subcontrato no está disponible en la lista.', 'warning');
            }
        } else if (!proveedorId) {
            proveedorSelect.disabled = true;
            proveedorSelect.value    = '';
            mostrarAlertaArchivos('Este subcontrato no tiene un proveedor asignado.', 'warning');
        }

        // ── 2. Mostrar panel informativo ──────────────────────────────────────
        const el = id => document.getElementById(id);

        if (el('subcontratoTotal'))        el('subcontratoTotal').textContent        = fmt(totalContrato);
        if (el('subcontratoUtilizado'))    el('subcontratoUtilizado').textContent    = fmt(utilizado);
        if (el('subcontratoComprometido')) el('subcontratoComprometido').textContent = fmt(comprometido);

        if (el('subcontratoDisponible')) {
            el('subcontratoDisponible').innerHTML = disponible >= 0
                ? `<span class="text-success">${fmt(disponible)}</span>`
                : `<span class="text-danger">${fmt(disponible)}</span>`;
        }

        infoSubcontrato.style.display = 'block';

        // ── 3. Cargar conceptos ───────────────────────────────────────────────
        if (catalogoSelect && catalogoSelect.value) {
            setTimeout(() => cargarConceptosPorSubcontrato(), 100);
        } else {
            resetAllItemConceptos('Primero debe seleccionarse el catálogo');
        }

    } else {
        // ── Sin subcontrato seleccionado ──────────────────────────────────────
        subcontratoActual = {
            id: null, total: 0, totalContrato: 0, extraordinarios: 0,
            utilizado: 0, comprometido: 0, disponible: 0
        };
        infoSubcontrato.style.display = 'none';

        if (proveedorSelect) proveedorSelect.disabled = false;

        // Eliminar campo oculto del subcontrato
        const hiddenSub = document.getElementById('subcontrato_id_hidden');
        if (hiddenSub) hiddenSub.remove();

        const hiddenField = document.getElementById('proveedor_from_subcontrato');
        if (hiddenField) hiddenField.remove();

        const categoriaId = document.getElementById('categoria').value;
        if (!esCategoriaSubcontrato(categoriaId) && catalogoSelect?.value) {
            cargarConceptosEnItems();
        } else {
            resetAllItemConceptos('Seleccione un subcontrato para ver conceptos');
        }
    }

    actualizarPresupuesto();
}

// Función para cargar conceptos según subcontrato
function cargarConceptosPorSubcontrato() {
    const subcontratoId = document.getElementById('subcontrato').value;
    const catalogoId = document.getElementById('catalogo').value;
    const conceptoSelects = document.querySelectorAll('.concepto-select');
    
    console.log('cargarConceptosPorSubcontrato - subcontratoId:', subcontratoId, 'catalogoId:', catalogoId);
    
    if (!subcontratoId) {
        console.log('No hay subcontrato seleccionado');
        resetAllItemConceptos('Seleccione un subcontrato');
        return;
    }
    
    if (!catalogoId) {
        console.log('No hay catálogo seleccionado');
        resetAllItemConceptos('Primero seleccione un catálogo');
        return;
    }
    
    // Bloquear selects mientras se cargan
    conceptoSelects.forEach(select => {
        select.disabled = true;
        select.innerHTML = '<option value="">Cargando conceptos del subcontrato...</option>';
    });
    
    // Cargar solo los conceptos asignados a este subcontrato
    fetch(`get_conceptos_by_subcontrato.php?subcontrato_id=${subcontratoId}&catalogo_id=${catalogoId}`)
        .then(response => {
            console.log('Respuesta fetch status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(conceptos => {
            console.log('Conceptos del subcontrato recibidos:', conceptos);
            
            if (conceptos.error) {
                console.error('Error en conceptos:', conceptos.error);
                conceptoSelects.forEach(select => {
                    select.innerHTML = `<option value="">Error: ${conceptos.error}</option>`;
                    select.disabled = true;
                });
                return;
            }
            
            if (!conceptos || conceptos.length === 0) {
                console.log('No hay conceptos asignados a este subcontrato');
                conceptoSelects.forEach(select => {
                    select.innerHTML = '<option value="">No hay conceptos asignados a este subcontrato</option>';
                    select.disabled = true;
                });
                mostrarAlertaArchivos('Este subcontrato no tiene conceptos asignados', 'warning');
                return;
            }
            
            // Mismo formato que get_conceptos_by_catalogo.php
            const conceptosHTML = '<option value="">Seleccionar Concepto (Opcional)</option>' +
                conceptos.map(concepto => {
                    let displayText = '';
                    if (concepto.numero_original) {
                        displayText = `#${concepto.numero_original} - ${concepto.codigo_concepto}`;
                    } else {
                        displayText = concepto.codigo_concepto;
                    }
                    if (concepto.nombre_concepto) {
                        displayText += ` - ${concepto.nombre_concepto.substring(0, 50)}`;
                    }
                    return `<option value="${concepto.id}">${displayText}</option>`;
                }).join('');
            
            console.log('Conceptos HTML generado:', conceptosHTML.substring(0, 200) + '...');
            
            conceptoSelects.forEach((select, idx) => {
                select.innerHTML = conceptosHTML;
                select.disabled = false;
                console.log(`Select ${idx} actualizado con ${conceptos.length} conceptos`);
            });
            
            console.log(`${conceptos.length} conceptos cargados para el subcontrato ${subcontratoId}`);
            
            // Mostrar mensaje de éxito
            mostrarAlertaArchivos(`Se cargaron ${conceptos.length} conceptos del subcontrato`, 'success');
        })
        .catch(error => {
            console.error('Error al cargar conceptos por subcontrato:', error);
            conceptoSelects.forEach(select => {
                select.innerHTML = '<option value="">Error al cargar conceptos del subcontrato</option>';
                select.disabled = true;
            });
            mostrarAlertaArchivos('Error al cargar conceptos del subcontrato: ' + error.message, 'danger');
        });
}

// Función para restaurar la lista de proveedores
function restaurarListaProveedores() {
    const proveedorSelect = document.getElementById('proveedor');
    proveedorSelect.disabled = false;
    console.log('Proveedor habilitado');
}

// Modificar la función handleObraChange para cargar subcontratos cuando corresponda
function handleObraChange() {
    const obraSelect = document.getElementById('obra');
    const selectedOption = obraSelect.options[obraSelect.selectedIndex];
    const infoObra = document.getElementById('infoObra');
    const catalogoSelect = document.getElementById('catalogo');
    
    console.log('handleObraChange - Obra seleccionada:', obraSelect.value);
    
    if (obraSelect.value) {
    presupuestoActual.obra = {
        total:          parseFloat(selectedOption.getAttribute('data-total'))           || 0,
        totalContratos: parseFloat(selectedOption.getAttribute('data-total-contratos')) || 0,
        utilizado:      parseFloat(selectedOption.getAttribute('data-utilizado'))       || 0,
        comprometido:   parseFloat(selectedOption.getAttribute('data-comprometido'))    || 0,
        disponible:     parseFloat(selectedOption.getAttribute('data-disponible'))      || 0,
    };

    // Panel informativo de la obra
    const el = id => document.getElementById(id);
    if (el('montoObra'))        el('montoObra').textContent        = fmt(presupuestoActual.obra.total);
    if (el('contratosObra'))    el('contratosObra').textContent    = fmt(presupuestoActual.obra.totalContratos);
    if (el('comprometidoObra')) el('comprometidoObra').textContent = fmt(presupuestoActual.obra.comprometido);
    if (el('disponibleObra'))   el('disponibleObra').textContent   = fmt(presupuestoActual.obra.disponible);

    if (el('progressObra') && presupuestoActual.obra.total > 0) {
        const pct = (presupuestoActual.obra.totalContratos / presupuestoActual.obra.total) * 100;
        el('progressObra').style.width = `${Math.min(pct, 100)}%`;
        el('progressObra').className = `progress-bar ${pct > 90 ? 'bg-danger' : pct > 70 ? 'bg-warning' : 'bg-success'}`;
    }

    if (el('infoObra')) el('infoObra').style.display = 'block';
        
        // Limpiar presupuesto de proyecto
        presupuestoActual.proyecto = null;
        
        // Cargar catálogos de la obra
        cargarCatalogos();
        
        // Cargar subcontratos si la categoría lo requiere
        const categoriaId = document.getElementById('categoria').value;
        const esSubcontratoCategoria = esCategoriaSubcontrato(categoriaId);
        
        if (esSubcontratoCategoria) {
            console.log('Categoría de subcontrato, cargando subcontratos...');
            cargarSubcontratos();
        }
    } else {
        presupuestoActual.obra = null;
        if (infoObra) infoObra.style.display = 'none';
        if (catalogoSelect) resetSelect(catalogoSelect, '-- Sin catálogo específico --');
        resetAllItemConceptos('Primero seleccione un catálogo');
    }
    
    actualizarPresupuesto();
}

// ===== FUNCIÓN PARA MOSTRAR MODAL DE FONDOS INSUFICIENTES =====
function mostrarModalFondosInsuficientes(tipo, totalOrden, disponible) {
    const faltante = totalOrden - disponible;
    UI.modal({
        title: 'Fondos Insuficientes',
        icon: 'error',
        html: `
            <div style="text-align: left;">
                <p>El total de la orden supera el presupuesto disponible del ${tipo}.</p>
                <div style="background: #fff5f5; border: 1px solid #feb2b2; border-radius: 8px; padding: 15px; margin-bottom: 15px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <span>Total de la orden:</span>
                        <strong>${fmt(totalOrden)}</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px; color: #c53030;">
                        <span>Presupuesto disponible:</span>
                        <strong>${fmt(disponible)}</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding-top: 5px; border-top: 1px solid #feb2b2; color: #c53030; font-weight: bold;">
                        <span>Faltante:</span>
                        <span>${fmt(faltante)}</span>
                    </div>
                </div>
                <p style="font-size: 0.85rem; color: #718096;">
                    <i class="bi bi-info-circle"></i> Por favor, ajuste los montos o seleccione otra fuente de financiamiento.
                </p>
            </div>
        `
    });
}

// ===== FUNCIÓN PARA MOSTRAR ALERTA EN EL CONTENEDOR =====
function mostrarAlertaPresupuesto(tipo, mensaje, detalles = {}) {
    const alertEl = document.getElementById('alertPresupuesto');
    if (!alertEl) return;
    
    let alertClass = 'alert-success';
    let icon = 'bi-check-circle';
    
    switch (tipo) {
        case 'danger':
            alertClass = 'alert-danger';
            icon = 'bi-exclamation-octagon-fill';
            break;
        case 'warning':
            alertClass = 'alert-warning';
            icon = 'bi-exclamation-triangle-fill';
            break;
        case 'info':
            alertClass = 'alert-info';
            icon = 'bi-info-circle-fill';
            break;
        default:
            alertClass = 'alert-success';
            icon = 'bi-check-circle-fill';
    }
    
    let detallesHtml = '';
    if (detalles.totalOrden) {
        detallesHtml = `
            <small class="d-block mt-1">
                Total orden: <strong>${fmt(detalles.totalOrden)}</strong>
                ${detalles.disponible ? `| Disponible: <strong>${fmt(detalles.disponible)}</strong>` : ''}
                ${detalles.porcentaje ? `| Uso: <strong>${detalles.porcentaje.toFixed(1)}%</strong>` : ''}
            </small>
        `;
    }
    
    alertEl.innerHTML = `
        <div class="alert ${alertClass} mb-0 alert-dismissible fade show" role="alert">
            <i class="bi ${icon} me-2"></i>
            <strong>${mensaje}</strong>
            ${detallesHtml}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    alertEl.style.display = 'block';
}

// ===== VERSIÓN MEJORADA DE actualizarPresupuesto =====
function actualizarPresupuesto() {
    const obraId        = document.getElementById('obra')?.value;
    const categoriaId   = document.getElementById('categoria')?.value;
    const subcontratoId = document.getElementById('subcontrato')?.value;
    const alertEl       = document.getElementById('alertPresupuesto');
    const btnEnviar     = document.getElementById('btnEnviar');
    const esSubcontrato = esCategoriaSubcontrato(categoriaId);

    // Ocultar alerta anterior
    if (alertEl) alertEl.style.display = 'none';

    // Obtener total de la orden
    const totalOrden = parseFloat(
        document.getElementById('totalGeneral')?.textContent.replace(/[^0-9.-]/g, '')
    ) || 0;

    // Si no hay total, habilitar botón y salir
    if (totalOrden === 0) {
        if (btnEnviar) btnEnviar.disabled = false;
        return;
    }

    // ── MODO SUBCONTRATO ──────────────────────────────────────────────────────
    if (esSubcontrato && subcontratoId && subcontratoActual.id) {
        const { totalContrato, utilizado, comprometido, disponible } = subcontratoActual;

        // Validación de fondos insuficientes
        if (totalOrden > disponible) {
            mostrarModalFondosInsuficientes('subcontrato', totalOrden, disponible);
            mostrarAlertaPresupuesto('danger', 
                `Fondos insuficientes en subcontrato. Faltante: ${fmt(totalOrden - disponible)}`,
                { totalOrden, disponible }
            );
            if (btnEnviar) btnEnviar.disabled = true;
            return;
        }

        // Alertas por porcentaje de uso
        const usadoActual    = utilizado + comprometido;
        const totalConPedido = usadoActual + totalOrden;
        const pct            = (totalConPedido / totalContrato) * 100;
        
        if (pct > 80) {
            mostrarAlertaPresupuesto('danger', 
                `¡ALERTA! El subcontrato supera el 80% de uso (${pct.toFixed(1)}%).`,
                { totalOrden, disponible, porcentaje: pct }
            );
        } else if (pct > 50) {
            mostrarAlertaPresupuesto('warning', 
                `Precaución: El subcontrato supera el 50% de uso (${pct.toFixed(1)}%).`,
                { totalOrden, disponible, porcentaje: pct }
            );
        } else {
            mostrarAlertaPresupuesto('success', 
                `Fondos disponibles en subcontrato. Uso actual: ${pct.toFixed(1)}%`,
                { totalOrden, disponible, porcentaje: pct }
            );
        }
        
        if (btnEnviar) btnEnviar.disabled = false;
        return;
    }

    // ── MODO OBRA (no subcontrato) ────────────────────────────────────────────
    if (obraId && presupuestoActual.obra && totalOrden > 0) {
        const { total, totalContratos, disponible } = presupuestoActual.obra;

        // Validación de fondos insuficientes
        if (totalOrden > disponible) {
            mostrarModalFondosInsuficientes('obra', totalOrden, disponible);
            mostrarAlertaPresupuesto('danger', 
                `Fondos insuficientes en obra. Faltante: ${fmt(totalOrden - disponible)}`,
                { totalOrden, disponible }
            );
            if (btnEnviar) btnEnviar.disabled = true;
            return;
        }

        // Alertas por porcentaje de uso
        const totalConPedido = totalContratos + totalOrden;
        const pct            = total > 0 ? (totalConPedido / total) * 100 : 0;
        
        if (pct > 80) {
            mostrarAlertaPresupuesto('danger', 
                `¡ALERTA! El presupuesto de obra supera el 80% de uso (${pct.toFixed(1)}%). Se recomienda no generar más órdenes.`,
                { totalOrden, disponible, porcentaje: pct }
            );
        } else if (pct > 50) {
            mostrarAlertaPresupuesto('warning', 
                `Precaución: El presupuesto de obra supera el 50% de uso (${pct.toFixed(1)}%).`,
                { totalOrden, disponible, porcentaje: pct }
            );
        } else {
            mostrarAlertaPresupuesto('success', 
                `Fondos disponibles en obra. Uso actual: ${pct.toFixed(1)}%`,
                { totalOrden, disponible, porcentaje: pct }
            );
        }
        
        if (btnEnviar) btnEnviar.disabled = false;
        return;
    }
    
    // Si no hay obra seleccionada o no hay presupuesto
    if (btnEnviar) btnEnviar.disabled = false;
}

// Modificar la función initNewOrder para agregar los nuevos event listeners
function initNewOrder(config) {
  console.log('=== INICIALIZANDO FORMULARIO CON SUBCONTRATOS ===');
  
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
  
  // Configurar event listeners
  const obraSelect = document.getElementById('obra');
  if (obraSelect) {
    obraSelect.removeEventListener('change', handleObraChange);
    obraSelect.addEventListener('change', handleObraChange);
    console.log('Event listener agregado a obra');
  }
  
  const categoriaSelect = document.getElementById('categoria');
  if (categoriaSelect) {
    categoriaSelect.removeEventListener('change', handleCategoriaChange);
    categoriaSelect.addEventListener('change', handleCategoriaChange);
    console.log('Event listener agregado a categoría');
    
    // Disparar el evento inicial para mostrar/ocultar según categoría actual
    setTimeout(() => {
        handleCategoriaChange();
    }, 100);
  }
  
  const subcontratoSelect = document.getElementById('subcontrato');
  if (subcontratoSelect) {
    subcontratoSelect.removeEventListener('change', handleSubcontratoChange);
    subcontratoSelect.addEventListener('change', handleSubcontratoChange);
    console.log('Event listener agregado a subcontrato');
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
            
            setTimeout(() => {
                const obraSelect = document.getElementById('obra');
                if (obraSelect && config.requisicion.obra_id) {
                    obraSelect.value = config.requisicion.obra_id;
                    const event = new Event('change');
                    obraSelect.dispatchEvent(event);
                    
                    setTimeout(() => {
                        const catalogoSelect = document.getElementById('catalogo');
                        if (catalogoSelect && config.requisicion.catalogo_id) {
                            catalogoSelect.value = config.requisicion.catalogo_id;
                            console.log('Catálogo seleccionado, cargando conceptos...');
                            
                            // Si hay subcontrato en la requisición, seleccionarlo
                            if (config.requisicion.subcontrato_id) {
                                setTimeout(() => {
                                    const subcontratoSelect = document.getElementById('subcontrato');
                                    if (subcontratoSelect) {
                                        subcontratoSelect.value = config.requisicion.subcontrato_id;
                                        const subEvent = new Event('change');
                                        subcontratoSelect.dispatchEvent(subEvent);
                                    }
                                }, 500);
                            }
                            
                            setTimeout(() => {
                                cargarConceptosParaTodosLosItems(config.requisicion.catalogo_id);
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
  
  console.log('=== INICIALIZACIÓN COMPLETA ===');
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

    console.log(`OBRA ${obra.id}:`, {
        total:          obra.total,
        totalContratos: obra.total_contratos,
        utilizado:      obra.utilizado,
        comprometido:   obra.comprometido,
        disponible:     obra.disponible
    });

    // Nombres de atributos alineados con handleObraChange()
    option.setAttribute('data-total',           obra.total           || 0);
    option.setAttribute('data-total-contratos', obra.total_contratos || 0);
    option.setAttribute('data-utilizado',       obra.utilizado       || 0);
    option.setAttribute('data-comprometido',    obra.comprometido    || 0);
    option.setAttribute('data-disponible',      obra.disponible      || 0);

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
  if (tipo === 'danger') UI.toast.error(mensaje);
  else if (tipo === 'warning') UI.toast.warning(mensaje);
  else UI.toast.info(mensaje);
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
        <small class="text-muted">${(producto.descripcion || 'Sin descripción').substring(0, 100)} - ${producto.tipo}</small>
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
  const rows = productosCatalogo.map(p => `
    <tr>
      <td>${p.nombre}</td>
      <td><small>${p.descripcion || ''}</small></td>
      <td><span class="badge bg-${p.tipo === 'producto' ? 'primary' : 'success'}">${p.tipo}</span></td>
      <td>
        <button type="button" class="btn btn-sm btn-primary" onclick="seleccionarProducto(${p.id}, '${p.nombre.replace(/'/g, "\\'")}')">
          <i class="bi bi-plus"></i> Seleccionar
        </button>
      </td>
    </tr>
  `).join('');

  UI.modal({
    title: 'Catálogo de Productos y Servicios',
    size: 'lg',
    html: `
      <div class="mb-3">
        <input type="text" class="form-control" id="buscarCatalogoModal" placeholder="Buscar producto o servicio..." onkeyup="filtrarCatalogoModal(this.value)">
      </div>
      <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
        <table class="table table-hover align-middle" id="tablaCatalogoModal">
          <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
            <tr>
              <th>Nombre</th>
              <th>Descripción</th>
              <th>Tipo</th>
              <th>Acción</th>
            </tr>
          </thead>
          <tbody>
            ${rows || '<tr><td colspan="4" class="text-center">No hay productos disponibles</td></tr>'}
          </tbody>
        </table>
      </div>
    `
  });
}

window.filtrarCatalogoModal = function(val) {
  const q = val.toLowerCase();
  const rows = document.querySelectorAll('#tablaCatalogoModal tbody tr');
  rows.forEach(row => {
    const text = row.innerText.toLowerCase();
    row.style.display = text.includes(q) ? '' : 'none';
  });
};

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
  UI.modal.hide();
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
      <div class="producto-autocomplete w-100">
        <input type="text" class="form-control descripcion w-100" name="descripcion[]" placeholder="Descripción" 
               oninput="buscarProductos(this)" required>
        <div class="autocomplete-list" id="autocomplete-${rowCount}"></div>
      </div>
      <input type="hidden" name="producto_id[]" value="">
      <input type="hidden" name="tipo[]" value="">
    </td>
    <td>
      <input type="number" name="cantidad[]" class="form-control cantidad" min="0.001" value="1.000" step="0.001" onchange="calcularSubtotal(this)" required>
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
      <input type="number"  name="precio_unitario[]" class="form-control precio" min="0.001" value="1.000" step="0.001" onchange="calcularSubtotal(this)" required>
    </td>
    <td>
      <input type="text" class="form-control subtotal" value="$0.000" readonly>
    </td>
    <td class="cell-actions">
      <button type="button" class="btn-action btn-action--delete remove-item-btn" onclick="removeItem(this)" title="Eliminar ítem">
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
    const categoriaId = document.getElementById('categoria').value;
    const subcontratoId = document.getElementById('subcontrato')?.value;
    if (esCategoriaSubcontrato(categoriaId) && subcontratoId) {
      // Cargar solo los conceptos del subcontrato para esta fila
      fetch(`get_conceptos_by_subcontrato.php?subcontrato_id=${subcontratoId}&catalogo_id=${catalogoId}`)
        .then(r => r.json())
        .then(conceptos => {
          if (!conceptos || conceptos.error || conceptos.length === 0) return;
          const conceptoSelect = newRow.querySelector('.concepto-select');
          if (!conceptoSelect) return;
          conceptoSelect.innerHTML = '<option value="">Seleccionar Concepto (Opcional)</option>' +
            conceptos.map(c => {
              const txt = c.numero_original ? `#${c.numero_original} - ${c.codigo_concepto}` : c.codigo_concepto;
              return `<option value="${c.id}">${txt}${c.nombre_concepto ? ' - ' + c.nombre_concepto.substring(0, 50) : ''}</option>`;
            }).join('');
          conceptoSelect.disabled = false;
        })
        .catch(() => {});
    } else {
      cargarConceptosParaFila(newRow, catalogoId, null);
    }
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
  
  // Actualizar el subtotal en la tabla
  const subtotalEl = row.querySelector('.subtotal');
  if (subtotalEl.tagName === 'INPUT') {
    subtotalEl.value = '$' + subtotal.toLocaleString('es-MX', {minimumFractionDigits: 3, maximumFractionDigits: 3});
  } else {
    subtotalEl.textContent = '$' + subtotal.toLocaleString('es-MX', {minimumFractionDigits: 3, maximumFractionDigits: 3});
  }
  
  // Recalcular todos los totales
  calcularTotales();
}

function calcularTotales() {
  let subtotalGeneral = 0;
  
  document.querySelectorAll('.subtotal').forEach(cell => {
    // Limpiar el texto del subtotal - funciona tanto para input (value) como para td (textContent)
    const val = cell.tagName === 'INPUT' ? cell.value : cell.textContent;
    const valorTexto = val.replace(/[^\d.]/g, '');
    const valor = parseFloat(valorTexto) || 0;
    subtotalGeneral += valor;
  });

  // Actualizar el subtotal general
  document.getElementById('subtotalGeneral').textContent = '$' + subtotalGeneral.toLocaleString('es-MX', {minimumFractionDigits: 3, maximumFractionDigits: 3});
  
  // Recalcular IVA y total general
  calcularIVA();
}

function calcularIVA() {
  // Limpiar el subtotal general
  const subtotalTexto = document.getElementById('subtotalGeneral').textContent.replace(/[^\d.]/g, '');
  const subtotal = parseFloat(subtotalTexto) || 0;
  
  const ivaPorcentaje = parseFloat(document.getElementById('iva').value) || 0;
  const ivaTotal = parseFloat((subtotal * (ivaPorcentaje / 100)).toFixed(3));
  const totalGeneral = parseFloat((subtotal + ivaTotal).toFixed(2));

  document.getElementById('ivaTotal').textContent = '$' + ivaTotal.toLocaleString('es-MX', {minimumFractionDigits: 3, maximumFractionDigits: 3});
  document.getElementById('totalGeneral').textContent = '$' + totalGeneral.toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2});

  actualizarPresupuesto();
}

// Funciones para archivos de requisición
function eliminarArchivoTemporal(archivoId, btn) {
  UI.confirm({
    title: 'Eliminar Archivo',
    message: '¿Está seguro de que desea eliminar este archivo de la orden de compra?\n\nNota: El archivo permanecerá en la requisición original.',
    danger: true
  }).then(confirmed => {
    if (!confirmed) return;
    
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
  });
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
                console.log(`Campo oculto actualizado para fila ${i}: ${hiddenInput.value}`);
            }
        } else if (conceptoSelect && !hiddenInput && conceptoSelect.value) {
            // Crear campo oculto si no existe
            const conceptoHidden = document.createElement('input');
            conceptoHidden.type = 'hidden';
            conceptoHidden.name = 'concepto_id[]';
            conceptoHidden.value = conceptoSelect.value;
            conceptoHidden.className = 'concepto-hidden-input';
            row.appendChild(conceptoHidden);
            console.log(`Campo oculto creado para fila ${i}: ${conceptoSelect.value}`);
        }
    }
}

// ===== MANEJO DEL ENVÍO DEL FORMULARIO =====
function setupFormSubmit() {
  document.getElementById('ordenCompraForm').addEventListener('submit', async function(e) {
    // Prevenir envío por defecto
    e.preventDefault();
    const form = this;
    
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
      const confirmed = await UI.confirm({
        title: 'Monto en Cero',
        message: 'El total de la orden es $0.00. ¿Desea continuar?',
        danger: true
      });
      if (!confirmed) return;
    }
    
    // Deshabilitar botón para prevenir doble envío
    const btnEnviar = document.getElementById('btnEnviar');
    const originalText = btnEnviar.innerHTML;
    btnEnviar.innerHTML = '<i class="bi bi-hourglass-split"></i> Guardando...';
    btnEnviar.disabled = true;
    
    // CREAR FORMDATA PRIMERO (antes de deshabilitar campos)
    const formData = new FormData(form);
    
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

    fetch(window.BASE_URL + '/orders/get_next_folio_oc.php?entidad_id=' + entidadId)
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
  const categoriaId = document.getElementById('categoria').value;
  const esSubcontrato = esCategoriaSubcontrato(categoriaId);
  
  console.log('cargarCatalogos - obraId:', obraId);
  
  if (!obraId) {
    // Si no hay obra, limpiar y deshabilitar
    catalogoSelect.innerHTML = '<option value="">-- Sin catálogo --</option>';
    catalogoSelect.disabled = true;
    resetAllItemConceptos('Primero seleccione una obra');
    return;
  }
  
  catalogoSelect.disabled = true;
  catalogoSelect.innerHTML = '<option value="">Cargando catálogo...</option>';
  
  fetch(`get_catalogos_by_obra.php?obra_id=${obraId}`)
    .then(response => {
      if (!response.ok) throw new Error('Error en la respuesta');
      return response.json();
    })
    .then(catalogos => {
      console.log('Catálogo recibido:', catalogos);
      
      if (catalogos.error) {
        catalogoSelect.innerHTML = `<option value="">Error: ${catalogos.error}</option>`;
        catalogoSelect.disabled = true;
        return;
      }
      
      // Limpiar select
      catalogoSelect.innerHTML = '';
      
      if (!catalogos || catalogos.length === 0) {
        catalogoSelect.innerHTML = '<option value="">-- No hay catálogo para esta obra --</option>';
        catalogoSelect.disabled = true;
        resetAllItemConceptos('No hay catálogo para esta obra');
        return;
      }
      
      // Como solo hay un catálogo por obra, tomamos el primero
      const catalogo = catalogos[0];
      const option = document.createElement('option');
      option.value = catalogo.id;
      option.textContent = catalogo.nombre_catalogo;
      option.selected = true;
      catalogoSelect.appendChild(option);
      catalogoSelect.disabled = false;
      
      console.log('Catálogo seleccionado automáticamente:', catalogo.id, catalogo.nombre_catalogo);
      
      // Guardar el ID del catálogo para referencia
      window.catalogoActualId = catalogo.id;
      
      // Cargar conceptos según el tipo (subcontrato o normal)
      if (esSubcontrato) {
        // Si es subcontrato, esperar a que se seleccione uno
        const subcontratoId = document.getElementById('subcontrato').value;
        if (subcontratoId) {
          console.log('Cargando conceptos por subcontrato');
          cargarConceptosPorSubcontrato();
        } else {
          resetAllItemConceptos('Seleccione un subcontrato para ver conceptos');
        }
      } else {
        // Si no es subcontrato, cargar conceptos normales
        console.log('Cargando conceptos normales del catálogo');
        cargarConceptosEnItems();
      }
    })
    .catch(error => {
      console.error('Error al cargar catálogo:', error);
      catalogoSelect.innerHTML = '<option value="">Error al cargar catálogo</option>';
      catalogoSelect.disabled = true;
      mostrarAlertaArchivos('Error al cargar el catálogo: ' + error.message, 'danger');
    });
}

function cargarConceptosEnItems() {
  const catalogoId = document.getElementById('catalogo').value;
  const categoriaId = document.getElementById('categoria').value;
  const esSubcontrato = esCategoriaSubcontrato(categoriaId);
  const subcontratoId = document.getElementById('subcontrato')?.value;
  
  console.log('cargarConceptosEnItems - catalogoId:', catalogoId);
  
  if (!catalogoId) {
    resetAllItemConceptos('Primero seleccione un catálogo');
    return;
  }
  
  // Si es categoría subcontrato y hay subcontrato, usar esa función
  if (esSubcontrato && subcontratoId) {
    cargarConceptosPorSubcontrato();
    return;
  }
  
  // Si no es subcontrato, cargar conceptos normales del catálogo
  const conceptoSelects = document.querySelectorAll('.concepto-select');
  
  conceptoSelects.forEach(select => {
    select.disabled = true;
    select.innerHTML = '<option value="">Cargando conceptos...</option>';
  });
  
  // Usar la ruta correcta
  fetch(`get_conceptos_by_catalogo.php?catalogo_id=${catalogoId}`)
    .then(response => {
      if (!response.ok) {
        throw new Error(`Error en la respuesta del servidor: ${response.status}`);
      }
      return response.json();
    })
    .then(conceptos => {
      if (conceptos.error) {
        throw new Error(conceptos.error);
      }
      
      const conceptosHTML = '<option value="">Seleccionar Concepto (Opcional)</option>' +
        conceptos.map(concepto => {
          let displayText = concepto.codigo_concepto;
          if (concepto.numero_original) {
            displayText = `#${concepto.numero_original} - ${concepto.codigo_concepto}`;
          }
          if (concepto.nombre_concepto) {
            displayText += ` - ${concepto.nombre_concepto.substring(0, 50)}`;
          }
          return `<option value="${concepto.id}">${displayText}</option>`;
        }).join('');
      
      conceptoSelects.forEach(select => {
        select.innerHTML = conceptosHTML;
        select.disabled = false;
      });
      
      console.log(` ${conceptos.length} conceptos cargados del catálogo`);
    })
    .catch(error => {
      console.error('Error al cargar conceptos:', error);
      conceptoSelects.forEach(select => {
        select.innerHTML = '<option value="">Error al cargar conceptos</option>';
        select.disabled = true;
      });
      mostrarAlertaArchivos('Error al cargar conceptos: ' + error.message, 'danger');
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
        
        // FORZAR LA SELECCIÓN después de cargar las opciones
        if (selectedConceptoId) {
            setTimeout(() => {
                conceptoSelect.value = selectedConceptoId;
                console.log('Select value forzado a:', selectedConceptoId, 'Actual:', conceptoSelect.value);
                
                // Verificar que realmente se seleccionó
                if (conceptoSelect.value != selectedConceptoId) {
                    console.warn('El concepto no se pudo seleccionar automáticamente');
                }
            }, 100);
        }
        
        if (conceptoEncontrado) {
            console.log('Concepto seleccionado correctamente en el select');
        } else if (selectedConceptoId) {
            console.warn('Concepto ID', selectedConceptoId, 'no encontrado en la lista de conceptos');
        }
    })
    .catch(error => {
        console.error('Error al cargar conceptos:', error);
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
                console.log(`Corrigiendo concepto en fila ${i}: ${conceptoSelect.value} -> ${conceptoId}`);
                conceptoSelect.value = conceptoId;
            }
        }
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
window.cargarObras = cargarObras;
window.cargarCatalogos = cargarCatalogos;
window.cargarConceptosEnItems = cargarConceptosEnItems;
window.initNewOrder = initNewOrder;