// ====================================
// VARIABLES GLOBALES
// ====================================
const MAX_FILES = 5;
const MAX_SIZE = 10 * 1024 * 1024; // 10MB
let archivosAcumulados = [];

// Elementos del DOM
const proyectoSelect = document.getElementById('proyecto');
const obraSelect = document.getElementById('obra');
const catalogoSelect = document.getElementById('catalogo');

// ====================================
// CATÁLOGO DE PRODUCTOS
// ====================================
function mostrarCatalogoProductos() {
    const modal = new bootstrap.Modal(document.getElementById('modalCatalogo'));
    modal.show();
}

function seleccionarProducto(productoId, nombre) {
    
    const tableBody = document.querySelector('#itemsTable tbody');
    const lastRow = tableBody.lastElementChild;
    
    if (lastRow) {
        const producto = productosServiciosData.find(p => p.id == productoId);
        const tipoSelect = lastRow.querySelector('.tipo-select');
        const productoSelect = lastRow.querySelector('.producto-select');
        
        if (tipoSelect && productoSelect && producto) {
            tipoSelect.value = producto.tipo;
            productoSelect.value = productoId;
        }
    }
    
    const modal = bootstrap.Modal.getInstance(document.getElementById('modalCatalogo'));
    modal.hide();

    addItem();
}

// ====================================
// MANEJO DE ITEMS EN LA TABLA
// ====================================
function addItem() {
    const tableBody = document.querySelector('#itemsTable tbody');
    const rowCount = tableBody.rows.length;

    const tipos = [...new Set(productosServiciosData.map(i => i.tipo))];
    const tipoOptions = tipos.map(t => 
        `<option value="${t}">${t.charAt(0).toUpperCase() + t.slice(1)}</option>`
    ).join('');

    const productoOptions = productosServiciosData.map(i => 
        `<option value="${i.id}" data-tipo="${i.tipo}">${i.nombre}</option>`
    ).join('');

    const unidadOptions = unidadesData.map(u => 
      `<option value="${u.id}">${u.unidad}</option>`
    ).join('');

    // ✅ Obtener el estado actual del catálogo seleccionado
    const catalogoId = catalogoSelect.value;
    const conceptoPlaceholder = catalogoId ? 
        'Cargando conceptos...' : 
        'Primero seleccione un catálogo';

    const newRow = document.createElement('tr');
    newRow.innerHTML = `
        <td>${rowCount + 1}</td>
        <td>
            <select name="items[${rowCount}][tipo]" class="form-select tipo-select" required>
                <option value="">Seleccionar Tipo</option>${tipoOptions}
            </select>
        </td>
        <td>
            <select name="items[${rowCount}][producto_id]" class="form-select producto-select" required>
                <option value="">Seleccionar Producto/Servicio</option>${productoOptions}
            </select>
        </td>
        <td>
            <input type="number" name="items[${rowCount}][cantidad]" 
                   class="form-control cantidad" min="1" value="1" required>
        </td>
        <td>
            <select name="items[${rowCount}][unidad_id]" class="form-select" required>
                <option value="">Seleccionar Unidad</option>${unidadOptions}
            </select>
        </td>
        <td>
            <select name="items[${rowCount}][concepto_id]" class="form-select concepto-select" 
                    ${!catalogoId ? 'disabled' : ''}>
                <option value="">${conceptoPlaceholder}</option>
            </select>
        </td>
        <td>
            <button type="button" class="remove-item-btn btn btn-danger btn-sm">
                <i class="bi bi-trash"></i>
            </button>
        </td>
    `;
    
    tableBody.appendChild(newRow);

    // ✅ Si hay un catálogo seleccionado, cargar conceptos para esta nueva fila
    if (catalogoId) {
        cargarConceptosParaFila(newRow, catalogoId);
    }

    const removeBtn = newRow.querySelector('.remove-item-btn');
    removeBtn.addEventListener('click', function() { 
        removeItem(this); 
    });

    const tipoSelect = newRow.querySelector('.tipo-select');
    const productoSelect = newRow.querySelector('.producto-select');

    tipoSelect.addEventListener('change', function() {
        const selectedTipo = this.value;
        Array.from(productoSelect.options).forEach(opt => {
            if (opt.value !== "") {
                opt.style.display = (opt.dataset.tipo === selectedTipo) ? '' : 'none';
            }
        });
        productoSelect.value = "";
    });

    productoSelect.addEventListener('change', function() {
        const selectedProduct = productosServiciosData.find(i => i.id == this.value);
        if (selectedProduct) {
            tipoSelect.value = selectedProduct.tipo;
        }
    });
}

// ✅ Nueva función auxiliar para cargar conceptos en una fila específica
function cargarConceptosParaFila(row, catalogoId) {
    const conceptoSelect = row.querySelector('.concepto-select');
    
    conceptoSelect.disabled = true;
    conceptoSelect.innerHTML = '<option value="">Cargando conceptos...</option>';
    
    fetch(`get_conceptos_by_catalogo.php?catalogo_id=${catalogoId}`)
        .then(response => response.json())
        .then(conceptos => {
            if (conceptos.error) {
                conceptoSelect.innerHTML = `<option value="">Error: ${conceptos.error}</option>`;
                return;
            }
            
            const conceptosHTML = '<option value="">Seleccionar Concepto (Opcional)</option>' +
                conceptos.map(concepto => {
                    const displayText = concepto.numero_original ? 
                        `#${concepto.numero_original} - ${concepto.codigo_concepto}` : 
                        concepto.codigo_concepto;
                    return `<option value="${concepto.id}">${displayText}</option>`;
                }).join('');
            
            conceptoSelect.innerHTML = conceptosHTML;
            conceptoSelect.disabled = false;
        })
        .catch(error => {
            console.error('Error:', error);
            conceptoSelect.innerHTML = '<option value="">Error al cargar conceptos</option>';
        });
}

function removeItem(btn) {
    const row = btn.closest('tr');
    row.remove();
    updateItemNumbers();
}

function updateItemNumbers() {
    document.querySelectorAll('#itemsTable tbody tr').forEach((row, index) => {
        row.cells[0].textContent = index + 1;
        const inputs = row.querySelectorAll('input, select');
        inputs.forEach(input => {
            const name = input.getAttribute('name');
            if (name && name.includes('items')) {
                input.setAttribute('name', name.replace(/items\[\d+\]/, `items[${index}]`));
            }
        });
    });
}

// ====================================
// CASCADA: PROYECTO -> OBRA -> CATÁLOGO -> CONCEPTOS EN ITEMS
// ====================================
proyectoSelect.addEventListener('change', function() {
    const proyectoId = this.value;
    
    if (!proyectoId) {
        resetSelect(obraSelect, 'Primero seleccione un proyecto');
        resetSelect(catalogoSelect, 'Primero seleccione una obra');
        resetAllItemConceptos('Primero seleccione un catálogo');
        return;
    }
    
    cargarObras(proyectoId);
});

obraSelect.addEventListener('change', function() {
    const obraId = this.value;
    
    if (!obraId) {
        resetSelect(catalogoSelect, 'Primero seleccione una obra');
        resetAllItemConceptos('Primero seleccione un catálogo');
        return;
    }
    
    cargarCatalogos(obraId);
});

catalogoSelect.addEventListener('change', function() {
    const catalogoId = this.value;
    
    if (!catalogoId) {
        resetAllItemConceptos('Primero seleccione un catálogo');
        return;
    }
    
    cargarConceptosEnItems(catalogoId);
});

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

function cargarObras(proyectoId) {
    obraSelect.disabled = true;
    obraSelect.innerHTML = '<option value="">Cargando obras...</option>';
    fetch(`get_obras_by_proyecto.php?proyecto_id=${proyectoId}`)
        .then(response => response.json())
        .then(obras => {
            if (obras.error) {
                obraSelect.innerHTML = `<option value="">Error: ${obras.error}</option>`;
                return;
            }
            obraSelect.innerHTML = '<option value="">Seleccionar Obra</option>';
            obras.forEach(obra => {
                obraSelect.innerHTML += `<option value="${obra.id}">${obra.nombre_obra}</option>`;
            });
            obraSelect.disabled = false;
            // Reset catálogo y conceptos
            resetSelect(catalogoSelect, 'Primero seleccione una obra');
            resetAllItemConceptos('Primero seleccione un catálogo');
        })
        .catch(error => {
            console.error('Error:', error);
            obraSelect.innerHTML = '<option value="">Error al cargar obras</option>';
        });
}

function cargarCatalogos() {
    const obraId = obraSelect.value;
    if (!obraId) {
        resetSelect(catalogoSelect, 'Primero seleccione una obra');
        return;
    }
    catalogoSelect.disabled = true;
    catalogoSelect.innerHTML = '<option>Cargando catálogo...</option>';
    fetch(`get_catalogos_by_obra.php?obra_id=${obraId}`)
        .then(r => r.json())
        .then(catalogos => {
            if (!catalogos.length) {
                catalogoSelect.innerHTML = '<option>No existe catálogo</option>';
                return;
            }
            const catalogo = catalogos[0];
            catalogoSelect.innerHTML =
                `<option value="${catalogo.id}">${catalogo.nombre_catalogo}</option>`;
            catalogoSelect.value = catalogo.id;
            catalogoSelect.disabled = false;
            // cargar conceptos automáticamente
            cargarConceptosEnItems();
        })
        .catch(err => {
            console.error(err);
            catalogoSelect.innerHTML = '<option>Error al cargar catálogo</option>';
        });
}

function cargarConceptosEnItems(catalogoId) {
    const conceptoSelects = document.querySelectorAll('.concepto-select');
    
    conceptoSelects.forEach(select => {
        select.disabled = true;
        select.innerHTML = '<option value="">Cargando conceptos...</option>';
    });
    
    fetch(`get_conceptos_by_catalogo.php?catalogo_id=${catalogoId}`)
        .then(response => response.json())
        .then(conceptos => {
            if (conceptos.error) {
                conceptoSelects.forEach(select => {
                    select.innerHTML = `<option value="">Error: ${conceptos.error}</option>`;
                    select.disabled = true; // Mantener deshabilitado en caso de error
                });
                return;
            }
            
            const conceptosHTML = '<option value="">Seleccionar Concepto (Opcional)</option>' +
                conceptos.map(concepto => {
                    const displayText = concepto.numero_original ? 
                        `#${concepto.numero_original} - ${concepto.codigo_concepto}` : 
                        concepto.codigo_concepto;
                    return `<option value="${concepto.id}">${displayText}</option>`;
                }).join('');
            
            conceptoSelects.forEach(select => {
                select.innerHTML = conceptosHTML;
                select.disabled = false; 
            });
        })
        .catch(error => {
            console.error('Error:', error);
            conceptoSelects.forEach(select => {
                select.innerHTML = '<option value="">Error al cargar conceptos</option>';
                select.disabled = true;
            });
        });
}

// ====================================
// MANEJO DE ARCHIVOS
// ====================================
function agregarArchivo() {
    const singleFileInput = document.getElementById('singleFileInput');
    const file = singleFileInput.files[0];
    
    if (!file) {
        mostrarAlerta('Por favor seleccione un archivo primero.', 'warning');
        return;
    }
    
    if (archivosAcumulados.length >= MAX_FILES) {
        mostrarAlerta(`Ya alcanzó el límite de ${MAX_FILES} archivos.`, 'danger');
        singleFileInput.value = '';
        return;
    }
    
    if (file.size > MAX_SIZE) {
        mostrarAlerta(`El archivo "${file.name}" excede el tamaño máximo de 10MB.`, 'danger');
        singleFileInput.value = '';
        return;
    }
    
    const existe = archivosAcumulados.some(f => f.name === file.name && f.size === file.size);
    if (existe) {
        mostrarAlerta(`El archivo "${file.name}" ya fue agregado.`, 'warning');
        singleFileInput.value = '';
        return;
    }
    
    archivosAcumulados.push(file);
    actualizarListaArchivos();
    singleFileInput.value = '';
    mostrarAlerta(`Archivo "${file.name}" agregado correctamente.`, 'success');
}

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

function eliminarArchivo(index) {
    const archivo = archivosAcumulados[index];
    archivosAcumulados.splice(index, 1);
    actualizarListaArchivos();
    mostrarAlerta(`Archivo "${archivo.name}" eliminado.`, 'info');
}

function mostrarAlerta(msg, tipo = 'info') {
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
    }, 4000);
}

// ====================================
// BÚSQUEDA EN CATÁLOGO
// ====================================
document.getElementById('buscarCatalogo').addEventListener('input', function() {
    const termino = this.value.toLowerCase();
    const filas = document.getElementById('tbodyCatalogo').getElementsByTagName('tr');
    
    for (let fila of filas) {
        const texto = fila.textContent.toLowerCase();
        fila.style.display = texto.includes(termino) ? '' : 'none';
    }
});

// ====================================
// ENVÍO DEL FORMULARIO
// ====================================
function showOverlay() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) overlay.style.display = 'flex';
    document.querySelectorAll('button[type="submit"]').forEach(b => b.disabled = true);
}

function hideOverlay() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) overlay.style.display = 'none';
    document.querySelectorAll('button[type="submit"]').forEach(b => b.disabled = false);
}

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('ordenCompraForm');
    if (!form) return;

    form.addEventListener('submit', function(e) {
        e.preventDefault(); 
        showOverlay();

        // Validar campos obligatorios
        const proyecto = document.getElementById('proyecto').value;
        const obra = document.getElementById('obra').value;
        const catalogo = document.getElementById('catalogo').value;
        const entidad = document.getElementById('entidad').value;
        const solicitante = document.getElementById('solicitante').value;
        const categoria = document.getElementById('categoria').value;
        
        // Validar tabla de items
        const itemsTable = document.querySelector('#itemsTable tbody');
        const tieneItems = itemsTable && itemsTable.children.length > 0;
        
        // Mostrar errores
        const errores = [];
        
        if (!entidad) {errores.push("La entidad es obligatoria.");}
        if (!solicitante) {errores.push("El solicitante es obligatorio.");}
        if (!categoria) {errores.push("La categoría es obligatoria.");}
        if (!proyecto) {errores.push("El proyecto es obligatorio.");}
        if (!obra) {errores.push("La obra es obligatoria.");}
        if (!catalogo) {errores.push("El catálogo es obligatorio.");}
        if (!tieneItems) {errores.push("Debe agregar al menos un item a la requisición.");}
        
        // Si hay errores, mostrar alerta y evitar envío
        if (errores.length > 0) {
            hideOverlay();
            mostrarAlerta('<strong>Por favor corrija los siguientes errores:</strong><br>' + errores.join('<br>'), 'warning');
            return false;
        }
        
        // Preparar FormData
        const formData = new FormData(this);
        
        // Agregar archivos si existen
        if (archivosAcumulados.length > 0) {
            archivosAcumulados.forEach((file, index) => {
                formData.append('archivos[]', file);
            });
        }
        
        // Enviar datos vía AJAX
        fetch('save_requis.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (response.ok) return response.json();
            throw new Error('Error en el servidor: ' + response.status);
        })
        .then(data => {
            console.log('Respuesta del servidor:', data); // Para debugging
            if (data.status === 'success') {
                // Redirigir a la lista con mensaje de éxito
                window.location.href = 'list_requis.php?msg=success&folio=' + encodeURIComponent(data.folio);
            } else {
                hideOverlay();
                mostrarAlerta(data.message || 'Error desconocido al guardar', 'danger');
            }
        })
        .catch(error => {
            hideOverlay();
            console.error('Error en fetch:', error);
            mostrarAlerta('Error al guardar la requisición: ' + error.message, 'danger');
        });
        
        return false;
    });
});

// ====================================
// INICIALIZACIÓN
// ====================================
document.addEventListener('DOMContentLoaded', function() {
    // Fecha de solicitud
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
});