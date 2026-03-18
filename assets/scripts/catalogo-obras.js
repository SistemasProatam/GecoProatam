// ====================================
// CLASE PRINCIPAL - CATALOGOS MANAGER
// ====================================

class CatalogosManager {
    async makeRequest(formData) {
        try {
            const response = await fetch('catalogos_manager.php', {
                method: 'POST',
                body: formData
            });
            
            const text = await response.text();
            
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Respuesta no JSON:', text);
                throw new Error('Error del servidor: respuesta no válida');
            }
            
        } catch (error) {
            console.error('Error de red:', error);
            throw new Error('Error de conexión: ' + error.message);
        }
    }

    async obtenerDetalleConcepto(conceptoId) {
        const formData = new FormData();
        formData.append('action', 'obtener_detalle_concepto');
        formData.append('concepto_id', conceptoId);
        return await this.makeRequest(formData);
    }

    async crearCatalogo(obraId, nombre, descripcion = '') {
        const formData = new FormData();
        formData.append('action', 'crear_catalogo');
        formData.append('obra_id', obraId);
        formData.append('nombre_catalogo', nombre);
        formData.append('descripcion', descripcion);
        return await this.makeRequest(formData);
    }
    
    async obtenerCatalogos(obraId) {
        const formData = new FormData();
        formData.append('action', 'obtener_catalogos');
        formData.append('obra_id', obraId);
        return await this.makeRequest(formData);
    }
    
    async crearConcepto(catalogoId, codigo, nombre, descripcion = '', unidadMedida = '', categoria = '', subcategoria = '', numeroOriginal = '', cantidad = '', precioUnitario = '', importe = '', fechaInicio = '', fechaFin = '') {
        const formData = new FormData();
        formData.append('action', 'crear_concepto');
        formData.append('catalogo_id', catalogoId);
        formData.append('codigo_concepto', codigo);
        formData.append('nombre_concepto', nombre);
        formData.append('descripcion', descripcion);
        formData.append('unidad_medida', unidadMedida);
        formData.append('categoria', categoria);
        formData.append('subcategoria', subcategoria);
        formData.append('numero_original', numeroOriginal);
        formData.append('cantidad', cantidad);
        formData.append('precio_unitario', precioUnitario);
        formData.append('importe', importe);
        formData.append('fecha_inicio', fechaInicio);
        formData.append('fecha_fin', fechaFin);
        formData.append('permitir_duplicados', 'true');
        return await this.makeRequest(formData);
    }
    
    async obtenerConceptos(catalogoId) {
        const formData = new FormData();
        formData.append('action', 'obtener_conceptos');
        formData.append('catalogo_id', catalogoId);
        return await this.makeRequest(formData);
    }

    
    
async importarConceptosDesdeExcel(catalogoId, file) {
    try {
        const datosExcel = await this.procesarArchivoExcel(file);
         
        console.log(`Conceptos originales: ${datosExcel.length}`);
        
        const formData = new FormData();
        formData.append('action', 'importar_conceptos_excel');
        formData.append('catalogo_id', catalogoId);
        formData.append('datos_excel', JSON.stringify(datosExcel));
        formData.append('permitir_duplicados', 'true');
        
        return await this.makeRequest(formData);
    } catch (error) {
        console.error('Error en importarConceptosDesdeExcel:', error);
        throw error;
    }
}

async procesarArchivoExcel(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = function(e) {
            try {
                const data = new Uint8Array(e.target.result);
                const workbook = XLSX.read(data, { type: 'array' });
                const sheetName = workbook.SheetNames[0];
                const worksheet = workbook.Sheets[sheetName];
                const jsonData = XLSX.utils.sheet_to_json(worksheet, { 
                    header: 1,
                    defval: '',
                    blankrows: false
                });
                const conceptos = procesarDatosCatalogoFlexible(jsonData);
                resolve(conceptos);
            } catch (error) {
                reject(new Error('Error procesando archivo Excel: ' + error.message));
            }
        };
        reader.onerror = function() {
            reject(new Error('Error leyendo archivo'));
        };
        reader.readAsArrayBuffer(file);
    });
}
    
    async obtenerItemsConcepto(conceptoId) {
        const formData = new FormData();
        formData.append('action', 'obtener_items_concepto');
        formData.append('concepto_id', conceptoId);
        return await this.makeRequest(formData);
    }
    
    async eliminarCatalogo(catalogoId) {
        const formData = new FormData();
        formData.append('action', 'eliminar_catalogo');
        formData.append('catalogo_id', catalogoId);
        return await this.makeRequest(formData);
    }
    
    async eliminarConcepto(conceptoId) {
        const formData = new FormData();
        formData.append('action', 'eliminar_concepto');
        formData.append('concepto_id', conceptoId);
        return await this.makeRequest(formData);
    }
}

// Instancia global
const catalogosManager = new CatalogosManager();

// ====================================
// GESTIÓN DE CATÁLOGOS
// ====================================
function mostrarFormularioCatalogo(obraId, obraNombre) {
    Swal.fire({
        title: "Nuevo Catálogo",
        html: `
        <form id="formNuevoCatalogo" class="swal-form">
                    <div class="mb-3">
                        <label class="form-label text-start d-block">Nombre del Catálogo <span class="text-danger">*</span></label>
                        <input type="text" name="nombre_catalogo" class="form-control" placeholder="Ej: Catálogo Principal" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-start d-block">Descripción</label>
                        <textarea name="descripcion" class="form-control" rows="3" placeholder="Describe el propósito de este catálogo..."></textarea>
                    </div>
                </form>
        `,
        width: 500,
        showCancelButton: true,
        confirmButtonText: "Crear",
        cancelButtonText: "Cancelar",
        preConfirm: () => {
            const form = document.getElementById("formNuevoCatalogo");
            const nombre = form.nombre_catalogo.value;
            const descripcion = form.descripcion.value;
            
            if (!nombre.trim()) {
                Swal.showValidationMessage('El nombre del catálogo es obligatorio');
                return false;
            }
            
            return catalogosManager.crearCatalogo(obraId, nombre, descripcion);
        }
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            if (result.value.success) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Éxito!',
                    text: result.value.message,
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    // Recargar la página para ver la lista actualizada
                    location.reload();
                });
            } else {
                Swal.fire('Error', result.value.error, 'error');
            }
        }
    });
}

// ====================================
// GESTIÓN DE CONCEPTOS
// ====================================

function mostrarFormularioConcepto(catalogoId, catalogoNombre, obraId = null, obraNombre = null) {
    Swal.fire({
        title: "Nuevo Concepto",
        html: `
            <form id="formNuevoConcepto" class="swal-form text-start">
                <div class="row">
                    <div class="col-6 mb-2">
                        <label class="form-label">Código <span class="text-danger">*</span></label>
                        <input type="text" name="codigo_concepto" class="form-control" placeholder="Ej: CONC-001" required>
                    </div>
                    <div class="col-6 mb-2">
                        <label class="form-label">Unidad de Medida</label>
                        <input type="text" name="unidad_medida" class="form-control" placeholder="Ej: m³, kg, pza">
                    </div>
                </div>
                <div class="row">
                    <div class="col-6 mb-2">
                        <label class="form-label">Categoría</label>
                        <input type="text" name="categoria" class="form-control" placeholder="Ej: Cimentación">
                    </div>
                    <div class="col-6 mb-2">
                        <label class="form-label">Subcategoría</label>
                        <input type="text" name="subcategoria" class="form-control" placeholder="Ej: Zapata Aislada">
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label">Nombre <span class="text-danger">*</span></label>
                    <input type="text" name="nombre_concepto" class="form-control" placeholder="Ej: Excavación manual" required>
                </div>
                <div class="mb-2">
                    <label class="form-label">Descripción</label>
                    <textarea name="descripcion" class="form-control" rows="2" placeholder="Descripción del concepto..."></textarea>
                </div>
                <div class="mb-2">
                    <label class="form-label">Número Original</label>
                    <input type="text" name="numero_original" class="form-control" placeholder="Ej: 1, 2, 3">
                </div>
                <hr>
                <div class="row">
                    <div class="col-6 mb-2">
                        <label class="form-label">Precio Unitario</label>
                        <input type="number" step="0.01" min="0" name="precio_unitario" class="form-control" placeholder="0.00">
                    </div>
                    <div class="col-6 mb-2">
                        <label class="form-label">Importe</label>
                        <input type="number" step="0.01" min="0" name="importe" class="form-control" placeholder="0.00">
                    </div>
                </div>
                <div class="row">
                    <div class="col-6 mb-2">
                        <label class="form-label">Fecha Inicio</label>
                        <input type="text" name="fecha_inicio" class="form-control" placeholder="DD/MM/AAAA">
                    </div>
                    <div class="col-6 mb-2">
                        <label class="form-label">Fecha Fin</label>
                        <input type="date" name="fecha_fin" class="form-control">
                    </div>
                </div>
            </form>
        `,
        width: 700,
        showCancelButton: true,
        confirmButtonText: "Crear",
        cancelButtonText: "Cancelar",
        preConfirm: () => {
            const form = document.getElementById("formNuevoConcepto");
            return catalogosManager.crearConcepto(
                catalogoId,
                form.codigo_concepto.value.trim(),
                form.nombre_concepto.value.trim(),
                form.descripcion.value.trim(),
                form.unidad_medida.value.trim(),
                form.categoria.value.trim(),
                form.subcategoria.value.trim(),
                form.numero_original.value.trim(),
                form.precio_unitario.value.trim(),
                form.importe.value.trim(),
                form.fecha_inicio.value.trim(),
                form.fecha_fin.value.trim()
            );
        }
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            if (result.value.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Concepto creado',
                    text: 'El concepto se ha creado correctamente',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    // Recargar la página para ver la lista actualizada
                    location.reload();
                });
            } else {
                Swal.fire('Error', result.value.error, 'error');
            }
        }
    });
}

// ====================================
// IMPORTACIÓN DE EXCEL
// ====================================

function mostrarImportarExcelConceptos(catalogoId, catalogoNombre, obraId = null, obraNombre = null) {
    Swal.fire({
        title: "Importar Conceptos desde Excel",
        html: `
            <div class="alert alert-info text-start">
                <small><i class="bi bi-info-circle"></i> 
                Columnas requeridas: <strong>CLAVE, DESCRIPCIÓN</strong><br>
                Columnas opcionales: NUMERO, UNIDAD, PRECIO UNITARIO, IMPORTE, FECHA INICIO, FECHA FIN</small>
            </div>
            <div class="mb-3">
                <label class="form-label">Archivo Excel</label>
                <input type="file" id="archivoExcelConceptos" class="form-control" accept=".xlsx, .xls" required>
            </div>
            <div id="vistaPrevia" style="display: none;">
                <h6>Vista previa:</h6>
                <div id="listaPrevia" class="small" style="max-height: 200px; overflow-y: auto;"></div>
            </div>
        `,
        width: 800,
        showCancelButton: true,
        confirmButtonText: "Importar",
        cancelButtonText: "Cancelar",
        didOpen: () => {
            const fileInput = document.getElementById('archivoExcelConceptos');
            fileInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) mostrarVistaPrevia(file);
            });
        },
        preConfirm: () => {
            const fileInput = document.getElementById('archivoExcelConceptos');
            if (!fileInput.files[0]) {
                Swal.showValidationMessage('Selecciona un archivo');
                return false;
            }
        return catalogosManager.importarConceptosDesdeExcel(catalogoId, fileInput.files[0])
    .then(result => {
        console.log('RESPUESTA SERVIDOR:', JSON.stringify(result));
        return result;
    });            
        }
    }).then((result) => {
        if (result.isConfirmed && result.value && result.value.success) {
            let mensaje = `
                <div class="alert alert-success">
                    <h5><i class="bi bi-check-circle"></i> Importación completada</h5>
                    <p class="mb-0"><strong>${result.value.conceptos_importados}</strong> conceptos importados</p>
                </div>
            `;
            
            if (result.value.errores && result.value.errores.length > 0) {
                mensaje += `
                    <div class="alert alert-warning">
                        <h6><i class="bi bi-exclamation-triangle"></i> Errores (${result.value.errores.length})</h6>
                        <div class="text-start small" style="max-height: 200px; overflow-y: auto;">
                            <ul class="mb-0">${result.value.errores.map(e => `<li>${e}</li>`).join('')}</ul>
                        </div>
                    </div>
                `;
            }
            
            Swal.fire({
                title: 'Resultado',
                html: mensaje,
                icon: result.value.errores.length > 0 ? 'warning' : 'success',
                confirmButtonText: 'Cerrar'
            }).then(() => {
                // Recargar la página para ver la lista actualizada
                location.reload();
            });
        }
    });
}

function esCategoriaNivel1(clave) {
    if (!clave) return false;
    const valor = clave.toString().trim().toUpperCase();
    // Nivel 1: I, II, III, IV, V, etc. (solo números romanos)
    return /^[IVXLCDM]+$/.test(valor);
}

// Alias para compatibilidad con código existente
function esCategoria(clave) {
    return esCategoriaNivel1(clave);
}

function esCategoriaNivel2(clave) {
    if (!clave) return false;
    const valor = clave.toString().trim();
    // Nivel 2: 1.2, 1.3, 2.1, etc. (número.número - solo dos partes)
    return /^\d+\.\d+$/.test(valor);
}

function esCategoriaNivel3(clave) {
    if (!clave) return false;
    const valor = clave.toString().trim();
    // Nivel 3: 1.2.1, 1.2.3, etc. (número.número.número)
    return /^\d+\.\d+\.\d+$/.test(valor);
}

function esSubcategoria(clave) {
    if (!clave) return false;
    const valor = clave.toString().trim().toUpperCase();
    // Subcategoría estilo romano: I.1, I.2, II.1, III.4, etc.
    return /^[IVXLCDM]+\.\d+$/.test(valor);
}

async function mostrarVistaPrevia(file) {
    try {
        console.log('Iniciando vista previa del archivo:', file.name);
        
        const conceptos = await catalogosManager.procesarArchivoExcel(file);
        
        console.log('Total conceptos procesados:', conceptos.length);
        console.log('Muestra de conceptos:', conceptos.slice(0, 5));
        
        // Analizar categorías
        const categorias = {};
        conceptos.forEach(c => {
            const cat = c.categoria || 'Sin categoría';
            if (!categorias[cat]) categorias[cat] = 0;
            categorias[cat]++;
        });
        
        console.log('Distribución por categorías:', categorias);
        
        const vistaPrevia = document.getElementById('vistaPrevia');
        const listaPrevia = document.getElementById('listaPrevia');
        
        vistaPrevia.style.display = 'block';
        
        if (conceptos.length > 0) {
            let html = `
                <div class="alert alert-success mb-3">
                    <strong>${conceptos.length} conceptos encontrados</strong>
                </div>
                
                <!-- Resumen por categorías -->
                <div class="card mb-3">
                    <div class="card-header bg-primary text-white">
                        <strong>Distribución por Categorías</strong>
                    </div>
                    <div class="card-body">
                        ${Object.entries(categorias).map(([cat, count]) => `
                            <div class="d-flex justify-content-between mb-1">
                                <span>${cat}</span>
                                <span class="badge bg-info">${count} conceptos</span>
                            </div>
                        `).join('')}
                    </div>
                </div>
                
                <!-- Lista de conceptos -->
                <div class="card">
                    <div class="card-header">
                        <strong>Vista Previa (primeros 10)</strong>
                    </div>
                    <div class="list-group list-group-flush">
            `;
            
            conceptos.slice(0, 10).forEach((concepto, idx) => {
                html += `
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <h6 class="mb-1">
                                    <span class="badge bg-info">${concepto.codigo_concepto}</span>
                                    ${concepto.nombre_concepto}
                                </h6>
                                <small class="text-muted">
                                    ${concepto.categoria ? `📁 ${concepto.categoria}` : ''}
                                    ${concepto.subcategoria ? ` › 📂 ${concepto.subcategoria}` : ''}
                                    ${concepto.unidad_medida ? ` | ${concepto.unidad_medida}` : ''}
                                    ${concepto.numero_original ? ` | #${concepto.numero_original}` : ''}
                                </small>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            if (conceptos.length > 10) {
                html += `
                    <div class="list-group-item text-center text-muted">
                        ... y ${conceptos.length - 10} más
                    </div>
                `;
            }
            
            html += `
                    </div>
                </div>
            `;
            
            listaPrevia.innerHTML = html;
        } else {
            listaPrevia.innerHTML = `
                <div class="alert alert-warning">
                    <strong>⚠️ No se encontraron conceptos válidos</strong><br>
                    Verifica que el archivo tenga columnas CLAVE y DESCRIPCIÓN con datos.
                </div>
            `;
        }
    } catch (error) {
        console.error('Error en vista previa:', error);
        document.getElementById('listaPrevia').innerHTML = 
            `<div class="alert alert-danger">
                <strong>Error:</strong> ${error.message}
                <br><small>Revisa la consola (F12) para más detalles</small>
            </div>`;
    }
}

// ====================================
// FUNCIONES DE DETALLE Y ELIMINACIÓN
// ====================================

function verDetalleConcepto(conceptoId, codigoClave, catalogoId, catalogoNombre, obraId = null, obraNombre = null) {
    catalogosManager.obtenerDetalleConcepto(conceptoId)
        .then(resp => {
            // Normalizar respuesta
            let concepto = resp;
            if (resp && typeof resp === 'object' && ('success' in resp)) {
                if (resp.success === false) {
                    Swal.fire('Error', resp.error || resp.message || 'Error al cargar concepto', 'error');
                    return;
                }
                if (resp.concepto) {
                    concepto = resp.concepto;
                }
            }

            const montoTotal = parseFloat(concepto.monto_total) || 0;
            const totalItems = parseInt(concepto.total_items) || 0;
            
            // Formatear fechas correctamente (YYYY-MM-DD a DD/MM/YYYY)
            const formatearFecha = (fecha) => {
                if (!fecha || fecha === '0000-00-00') return 'N/A';
                const partes = fecha.split('-');
                if (partes.length === 3) {
                    return `${partes[2]}/${partes[1]}/${partes[0]}`;
                }
                return fecha;
            };
            
            // Formatear números
            const formatearNumero = (valor, decimales = 2, moneda = false) => {
                if (!valor || isNaN(valor)) return 'N/A';
                const num = parseFloat(valor);
                if (moneda) {
                    return '$' + num.toLocaleString('es-MX', {minimumFractionDigits: decimales, maximumFractionDigits: decimales});
                }
                return num.toLocaleString('es-MX', {minimumFractionDigits: decimales, maximumFractionDigits: 3});
            };
            
            // Escapar correctamente los valores
            const nombreConcepto = String(concepto.nombre_concepto || '').replace(/'/g, "\\'");
            const catalogoNombreEscaped = String(catalogoNombre || '').replace(/'/g, "\\'");
            
            let detalleHtml = `
                <div class="concepto-simple">
                    <!-- Información principal -->
                    <div class="mb-3 text-center">
                        <h5 class="text-primary">${concepto.codigo_concepto || 'N/A'}</h5>
                    </div>

                    <!-- Datos básicos -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between border-bottom py-1">
                            <span>Unidad:</span>
                            <strong>${concepto.unidad_medida || 'N/A'}</strong>
                        </div>
                        <div class="d-flex justify-content-between border-bottom py-1">
                            <span>Categoría:</span>
                            <strong>${concepto.categoria || 'N/A'}</strong>
                        </div>
                        <div class="d-flex justify-content-between border-bottom py-1">
                            <span>Subcategoría:</span>
                            <strong>${concepto.subcategoria || 'N/A'}</strong>
                        </div>
                        ${concepto.numero_original ? `
                        <div class="d-flex justify-content-between border-bottom py-1">
                            <span>Número:</span>
                            <strong>${concepto.numero_original}</strong>
                        </div>
                        ` : ''}
                    </div>

                    <!-- Descripción si existe -->
                    ${concepto.descripcion ? `
                    <div class="mb-3">
                        <strong class="text-muted d-block">Descripción:</strong>
                        <div class="bg-light p-2 rounded small">${concepto.descripcion}</div>
                    </div>
                    ` : ''}

                    <!-- ===== NUEVA SECCIÓN: CAMPOS ADICIONALES ===== -->
                    <div class="mb-3">
                        <h6 class="text-primary border-bottom pb-2">Detalles del Concepto</h6>
                        
                        <!-- Cantidad (NUEVO) -->
                        <div class="d-flex justify-content-between border-bottom py-1">
                            <span><i class="bi bi-sort-numeric-up me-1"></i>Cantidad:</span>
                            <strong class="text-dark">${formatearNumero(concepto.cantidad, 3)}</strong>
                        </div>
                        
                        <!-- Precio Unitario -->
                        <div class="d-flex justify-content-between border-bottom py-1">
                            <span><i class="bi bi-tag me-1"></i>Precio Unitario:</span>
                            <strong class="text-success">${formatearNumero(concepto.precio_unitario, 2, true)}</strong>
                        </div>
                        
                        <!-- Importe -->
                        <div class="d-flex justify-content-between border-bottom py-1">
                            <span><i class="bi bi-currency-dollar me-1"></i>Importe:</span>
                            <strong class="text-success">${formatearNumero(concepto.importe, 2, true)}</strong>
                        </div>
                        
                        <!-- Fecha Inicio -->
                        <div class="d-flex justify-content-between border-bottom py-1">
                            <span><i class="bi bi-calendar me-1"></i>Fecha Inicio:</span>
                            <strong>${formatearFecha(concepto.fecha_inicio)}</strong>
                        </div>
                        
                        <!-- Fecha Fin -->
                        <div class="d-flex justify-content-between border-bottom py-1">
                            <span><i class="bi bi-calendar me-1"></i>Fecha Fin:</span>
                            <strong>${formatearFecha(concepto.fecha_fin)}</strong>
                        </div>
                        
                    </div>

                    <!-- Estadísticas de items -->
                    <div class="row text-center mb-3">
                        <div class="col-6">
                            <div class="border rounded p-2">
                                <div class="text-primary fw-bold">${totalItems}</div>
                                <small class="text-muted">Items</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded p-2">
                                <div class="text-success fw-bold">$${montoTotal.toLocaleString('es-MX', {minimumFractionDigits: 2})}</div>
                                <small class="text-muted">Total en items</small>
                            </div>
                        </div>
                    </div>

                    <!-- Botones -->
                    <div class="gap-2 d-flex justify-content-center">
                        <button class="btn btn-sm" style="background-color:#17a2b8;color:white;border:none;"
                            onclick="verItemsConcepto(${conceptoId}, '${nombreConcepto}', ${catalogoId}, '${catalogoNombreEscaped}')">
                            <i class="bi bi-list-ul me-1"></i>Ver Items
                        </button>
                        <button class="btn btn-outline-secondary btn-sm" onclick="Swal.close()">
                            <i class="bi bi-x-circle me-1"></i>Cerrar
                        </button>
                    </div>
                </div>
            `;
            
            Swal.fire({
                title: 'Detalle del Concepto',
                html: detalleHtml,
                width: '90%',
                maxWidth: '450px',
                showCloseButton: true,
                showConfirmButton: false
            });
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire('Error', 'No se pudo cargar el detalle del concepto', 'error');
        });
}

// Función para eliminar catálogo con confirmación
function eliminarCatalogo(catalogoId, obraId, obraNombre) {
    Swal.fire({
        title: '¿Eliminar catálogo?',
        html: `
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i>
                <strong>Esta acción no se puede deshacer</strong><br>
                Se eliminarán todos los conceptos y items asociados a este catálogo.
            </div>
            <p class="text-muted small">¿Estás seguro de que deseas continuar?</p>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6'
    }).then((result) => {
        if (result.isConfirmed) {
            // Mostrar loading
            Swal.fire({
                title: 'Eliminando catálogo...',
                text: 'Por favor espere',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            catalogosManager.eliminarCatalogo(catalogoId)
                .then(result => {
                    if (result.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Catálogo eliminado',
                            text: result.message,
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            // Recargar la página para ver la lista actualizada
                            location.reload();
                        });
                    } else {
                        Swal.fire('Error', result.error, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire('Error', 'Error al eliminar el catálogo: ' + error.message, 'error');
                });
        }
    });
}

// Función para eliminar concepto con confirmación
function eliminarConcepto(conceptoId, catalogoId, catalogoNombre, obraId = null, obraNombre = null) {
    // Primero obtener información del concepto para mostrar en la confirmación
    catalogosManager.obtenerDetalleConcepto(conceptoId)
        .then(resp => {
            // Normalizar respuesta: manejar { success, concepto } o el objeto directamente
            let concepto = resp;
            if (resp && typeof resp === 'object' && ('success' in resp)) {
                if (resp.success === false) {
                    Swal.fire('Error', resp.error || resp.message || 'Error al cargar concepto', 'error');
                    return;
                }
                if (resp.concepto) concepto = resp.concepto;
            }

            const totalItems = parseInt(concepto.total_items) || 0;
            const tieneItems = totalItems > 0;
            
            Swal.fire({
                title: '¿Eliminar concepto?',
                html: `
                    <div class="text-start">
                        <p><strong>${concepto.codigo_concepto}</strong> - ${concepto.nombre_concepto}</p>
                        ${tieneItems ? `
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle"></i>
                            <strong>Este concepto tiene ${totalItems} items vinculados</strong><br>
                            Todos los items asociados también serán eliminados.
                        </div>
                        ` : `
                        <div class="alert alert-warning">
                            <i class="bi bi-info-circle"></i>
                            Este concepto no tiene items vinculados.
                        </div>
                        `}
                        <p class="text-muted small">¿Estás seguro de que deseas continuar?</p>
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Mostrar loading
                    Swal.fire({
                        title: 'Eliminando concepto...',
                        text: 'Por favor espere',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    catalogosManager.eliminarConcepto(conceptoId)
                        .then(result => {
                            if (result.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Concepto eliminado',
                                    text: result.message,
                                    timer: 2000,
                                    showConfirmButton: false
                                }).then(() => {
                                    // Recargar la página para ver la lista actualizada
                                    location.reload();
                                });
                            } else {
                                Swal.fire('Error', result.error, 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            Swal.fire('Error', 'Error al eliminar el concepto: ' + error.message, 'error');
                        });
                }
            });
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire('Error', 'No se pudo cargar la información del concepto', 'error');
        });
}


// Función para editar concepto (placeholder)
function editarConcepto(conceptoId) {
    Swal.fire({
        title: 'Editar Concepto',
        html: `
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i>
                La funcionalidad de edición de conceptos estará disponible en la próxima actualización.
            </div>
            <p class="text-muted">Mientras tanto, puedes eliminar y crear nuevamente el concepto con la información correcta.</p>
        `,
        icon: 'info',
        confirmButtonText: 'Entendido'
    });
}

// Función para editar catálogo (placeholder)
function editarCatalogo(catalogoId) {
    Swal.fire({
        title: 'Editar Catálogo',
        html: `
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i>
                La funcionalidad de edición de catálogos estará disponible en la próxima actualización.
            </div>
            <p class="text-muted">Actualmente solo puedes eliminar y crear nuevamente el catálogo.</p>
        `,
        icon: 'info',
        confirmButtonText: 'Entendido'
    });
}

// Función para abrir vista completa de conceptos
function abrirVistaConceptos(catalogoId, catalogoNombre, obraId = null, obraNombre = null) {
    let url = `conceptos_view.php?catalogo_id=${catalogoId}&catalogo_nombre=${encodeURIComponent(catalogoNombre)}`;
    
    if (obraId) {
        url += `&obra_id=${obraId}&obra_nombre=${encodeURIComponent(obraNombre)}`;
    }
    
    window.location.href = url;
}

// ====================================
// PROCESAMIENTO DE DATOS EXCEL
// ====================================

function procesarDatosCatalogoFlexible(jsonData) {
    const conceptos = [];
    
    // Paso 1: Buscar la fila de encabezados
    let filaEncabezados = -1;
    let mapeoColumnas = {};
    
    for (let i = 0; i < Math.min(jsonData.length, 20); i++) {
        const fila = jsonData[i];
        if (!fila || fila.length === 0) continue;
        
        const encabezadosEncontrados = detectarEncabezados(fila);
        
        if (encabezadosEncontrados.valido) {
            filaEncabezados = i;
            mapeoColumnas = encabezadosEncontrados.mapeo;
            console.log('Encabezados encontrados en fila', i, mapeoColumnas);
            break;
        }
    }
    
    if (filaEncabezados === -1) {
        throw new Error('No se encontraron los encabezados requeridos. Mínimo necesario: CLAVE y DESCRIPCIÓN');
    }
    
    // Paso 2: Procesar los datos
    let categoriaActual = '';
    let subcategoriaActual = '';
    let numeroSecuencial = 1;

    const categoriasDetectadas = [];
    const subcategoriasDetectadas = [];
    
    for (let i = filaEncabezados + 1; i < jsonData.length; i++) {
        const fila = jsonData[i];
        if (!fila || fila.length === 0) continue;
        
        // Extraer valores según el mapeo de columnas
        const numero = obtenerValorColumna(fila, mapeoColumnas.numero);
        const clave = obtenerValorColumna(fila, mapeoColumnas.clave);
        const descripcion = obtenerValorColumna(fila, mapeoColumnas.descripcion);
        const unidad = obtenerValorColumna(fila, mapeoColumnas.unidad);
        const cantidad = obtenerValorColumna(fila, mapeoColumnas.cantidad);
        const precioUnitario = obtenerValorColumna(fila, mapeoColumnas.precio_unitario);
        const importe = obtenerValorColumna(fila, mapeoColumnas.importe);
        const fechaInicio = obtenerValorColumna(fila, mapeoColumnas.fecha_inicio);
        const fechaFin = obtenerValorColumna(fila, mapeoColumnas.fecha_fin);
        
        // Saltar filas completamente vacías
        if (!numero && !clave && !descripcion) continue;
        
        // Saltar filas de totales
        const descripcionUpper = descripcion.toUpperCase();
        if (descripcionUpper.includes('IMPORTE TOTAL') || 
            descripcionUpper.includes('TOTAL GENERAL') ||
            descripcionUpper.includes('SUBTOTAL') ||
            descripcionUpper === 'TOTAL') continue;
        
        // Normalizar clave para detectar categorías y subcategorías
        const claveStr = clave.toString().trim();
        // Versión solo romanos (para nivel 1)
        const claveRomana = claveStr.toUpperCase().replace(/[^IVXLCDM.]/g, '');

        // 1. CATEGORÍA NIVEL 1 (I, II, III...)
        if (esCategoriaNivel1(claveRomana) && descripcion) {
            categoriaActual = descripcion.trim();
            subcategoriaActual = null;
            categoriasDetectadas.push({ clave: claveRomana, descripcion: categoriaActual });
            console.log(`📁 CATEGORÍA N1: ${claveRomana} - ${categoriaActual}`);
            continue;
        }

        // 2. SUBCATEGORÍA ESTILO ROMANO (I.1, I.2...)
        if (esSubcategoria(claveRomana) && descripcion) {
            subcategoriaActual = descripcion.trim();
            subcategoriasDetectadas.push({ clave: claveRomana, descripcion: subcategoriaActual, categoria: categoriaActual });
            console.log(`📂 SUBCATEGORÍA ROMANO: ${claveRomana} - ${subcategoriaActual}`);
            continue;
        }

        // 3. CATEGORÍA NIVEL 2 (1.2, 1.3...)
        if (esCategoriaNivel2(claveStr) && descripcion) {
            subcategoriaActual = descripcion.trim();
            subcategoriasDetectadas.push({ clave: claveStr, descripcion: subcategoriaActual, categoria: categoriaActual });
            console.log(`📂 CATEGORÍA N2: ${claveStr} - ${subcategoriaActual}`);
            continue;
        }

        // 4. CATEGORÍA NIVEL 3 (1.2.1, 1.2.3...)
        if (esCategoriaNivel3(claveStr) && descripcion) {
            // El nivel 3 actúa como una subcategoría más específica dentro del nivel 2
            subcategoriaActual = descripcion.trim();
            subcategoriasDetectadas.push({ clave: claveStr, descripcion: subcategoriaActual, categoria: categoriaActual });
            console.log(`📄 CATEGORÍA N3: ${claveStr} - ${subcategoriaActual}`);
            continue;
        }

        const claveNormalizada = claveStr;
        
        // 3. Si la fila tiene CLAVE y DESCRIPCIÓN, es un concepto válido
        if (clave && descripcion) {
            const concepto = {
                codigo_concepto: clave.trim(),
                nombre_concepto: generarNombreConcepto(descripcion),
                descripcion: descripcion.trim(),
                unidad_medida: unidad || obtenerUnidadDesdeDescripcion(descripcion),
                categoria: categoriaActual || '',
                subcategoria: subcategoriaActual || '',
                numero_original: numero || String(numeroSecuencial),
                cantidad: cantidad || '',
                precio_unitario: precioUnitario || '',
                importe: importe || '',
                fecha_inicio: fechaInicio ? normalizarFecha(fechaInicio) : '',
                fecha_fin: fechaFin ? normalizarFecha(fechaFin) : ''
            };
            
            conceptos.push(concepto);
            numeroSecuencial++;
            
            console.log(`📋 Concepto ${conceptos.length} agregado:`, {
                clave: concepto.codigo_concepto,
                nombre: concepto.nombre_concepto.substring(0, 50),
                categoria: concepto.categoria,
                subcategoria: concepto.subcategoria
            });
        }
    }
    
    console.log(`✅ Total de conceptos procesados: ${conceptos.length}`);
    
    if (conceptos.length === 0) {
        throw new Error('No se encontraron conceptos válidos. Verifica que las columnas CLAVE y DESCRIPCIÓN tengan datos.');
    }
    
    return conceptos;
}

function detectarEncabezados(fila) {
    const mapeo = {
        numero: -1,
        clave: -1,
        descripcion: -1,
        unidad: -1,
        cantidad: -1,
        precio_unitario: -1,
        importe: -1,
        fecha_inicio: -1,
        fecha_fin: -1,
    };
    
    fila.forEach((celda, index) => {
        if (!celda) return;
        
        const valorNormalizado = String(celda).toUpperCase().trim().toString()
            .normalize("NFD").replace(/[\u0300-\u036f]/g, ""); // Remover acentos
        
        // Detectar NUMERO (OPCIONAL)
        if (valorNormalizado.includes('NUMERO') || 
            valorNormalizado === 'NO.' ||
            valorNormalizado === 'NUM' ||
            valorNormalizado === '#') {
            mapeo.numero = index;
        }
        
        // Detectar CLAVE (REQUERIDO)
        if (valorNormalizado.includes('CLAVE') ||
            valorNormalizado.includes('CODIGO') ||
            valorNormalizado === 'CVE' ||
            valorNormalizado === 'COD' ||
            valorNormalizado === 'KEY') {
            mapeo.clave = index;
        }
        
        // Detectar DESCRIPCIÓN (REQUERIDO)
        if (valorNormalizado.includes('DESCRIPCION') ||
            valorNormalizado.includes('CONCEPTO') ||
            valorNormalizado.includes('NOMBRE') ||
            valorNormalizado === 'DESC') {
            mapeo.descripcion = index;
        }
        
        // Detectar UNIDAD (OPCIONAL)
        if (valorNormalizado.includes('UNIDAD') ||
            valorNormalizado.includes('U.M') ||
            valorNormalizado === 'UM' ||
            valorNormalizado === 'UNI' ||
            valorNormalizado === 'MEDIDA') {
            mapeo.unidad = index;
        }

        // Detectar CANTIDAD (OPCIONAL)
        if (valorNormalizado === 'CANTIDAD' ||
            valorNormalizado === 'CANT' ||
            valorNormalizado === 'QTY' ||
            valorNormalizado === 'QUANTITY') {
            mapeo.cantidad = index;
        }

        // Detectar PRECIO UNITARIO (OPCIONAL) — soporta P.U., P.U, PRECIO UNITARIO
        if (valorNormalizado === 'P.U.' ||
            valorNormalizado === 'P.U' ||
            valorNormalizado === 'PU' ||
            valorNormalizado.includes('PRECIO UNITARIO') ||
            valorNormalizado === 'P.UNITARIO' ||
            valorNormalizado === 'PRECIO' ||
            valorNormalizado === 'UNIT PRICE') {
            mapeo.precio_unitario = index;
        }

        // Detectar IMPORTE (OPCIONAL)
        if (valorNormalizado === 'IMPORTE' ||
            valorNormalizado.includes('MONTO') ||
            valorNormalizado === 'AMOUNT') {
            mapeo.importe = index;
        }

        // Detectar FECHA INICIO (OPCIONAL)
        if (valorNormalizado.includes('FECHA DE INICIO') ||
            valorNormalizado.includes('FECHA INICIO') ||
            valorNormalizado.includes('F.INICIO') ||
            valorNormalizado === 'INICIO' ||
            valorNormalizado === 'START DATE') {
            mapeo.fecha_inicio = index;
        }

        // Detectar FECHA FIN (OPCIONAL)
        if (valorNormalizado.includes('FECHA DE FINALIZACION') ||
            valorNormalizado.includes('FECHA FINALIZACION') ||
            valorNormalizado.includes('FECHA FIN') ||
            valorNormalizado.includes('FECHA TERMINO') ||
            valorNormalizado.includes('F.FIN') ||
            valorNormalizado === 'FIN' ||
            valorNormalizado === 'TERMINO' ||
            valorNormalizado === 'END DATE') {
            mapeo.fecha_fin = index;
        }

    });
    
    // SOLO requiere CLAVE y DESCRIPCIÓN
    // El resto son opcionales
    const valido = mapeo.clave !== -1 && mapeo.descripcion !== -1;
    
    if (valido) {
        console.log('Encabezados detectados:', {
            NUMERO:          mapeo.numero          !== -1 ? `Columna ${mapeo.numero}` : 'No encontrado (opcional)',
            CLAVE:           `Columna ${mapeo.clave} ✓`,
            DESCRIPCION:     `Columna ${mapeo.descripcion} ✓`,
            UNIDAD:          mapeo.unidad          !== -1 ? `Columna ${mapeo.unidad}` : 'No encontrado (opcional)',
            CANTIDAD:        mapeo.cantidad        !== -1 ? `Columna ${mapeo.cantidad}` : 'No encontrado (opcional)',
            PRECIO_UNITARIO: mapeo.precio_unitario !== -1 ? `Columna ${mapeo.precio_unitario}` : 'No encontrado (opcional)',
            IMPORTE:         mapeo.importe         !== -1 ? `Columna ${mapeo.importe}` : 'No encontrado (opcional)',
            FECHA_INICIO:    mapeo.fecha_inicio    !== -1 ? `Columna ${mapeo.fecha_inicio}` : 'No encontrado (opcional)',
            FECHA_FIN:       mapeo.fecha_fin       !== -1 ? `Columna ${mapeo.fecha_fin}` : 'No encontrado (opcional)',
        });
    }
    
    return {
        valido: valido,
        mapeo: mapeo
    };
}

function obtenerValorColumna(fila, indice) {
    if (indice === -1 || indice >= fila.length) return '';
    const valor = fila[indice];
    return valor ? String(valor).trim() : '';
}

function generarNombreConcepto(descripcion) {
    // Extraer el nombre principal de la descripción
    if (!descripcion) return 'Concepto sin nombre';
    
    // Limitar a 200 caracteres máximo
    let nombre = descripcion.substring(0, 200);
    
    // Si es muy largo, tomar solo la primera parte
    if (nombre.length > 100) {
        const primeraOracion = nombre.split('.')[0];
        if (primeraOracion.length > 50) {
            nombre = primeraOracion.substring(0, 100) + '...';
        } else {
            nombre = primeraOracion;
        }
    }
    
    return nombre.trim();
}

function obtenerUnidadDesdeDescripcion(descripcion) {
    // Intentar extraer unidad de la descripción
    if (!descripcion) return '';
    
    const unidades = ['m³', 'm2', 'kg', 'pza', 'm', 'lts', 'hr', 'día', 'mes'];
    for (const unidad of unidades) {
        if (descripcion.toLowerCase().includes(unidad.toLowerCase())) {
            return unidad;
        }
    }
    
    return '';
}

/**
 * Normaliza fechas que pueden venir como serial de Excel o como string
 * Retorna 'YYYY-MM-DD' que es lo que acepta MySQL DATE
 */
function normalizarFecha(valor) {
    if (!valor) return '';

    // Si es número, es un serial de Excel (días desde 1900-01-01)
    const num = parseFloat(valor);
    if (!isNaN(num) && num > 1000) {
        // Excel serial: epoch es 1899-12-30
        const fecha = new Date(Math.round((num - 25569) * 86400 * 1000));
        if (!isNaN(fecha.getTime())) {
            return fecha.toISOString().split('T')[0];
        }
    }

    // Si ya es string con formato reconocible, intentar parsearlo
    const str = String(valor).trim();

    // dd/mm/yyyy o dd-mm-yyyy
    const matchDMY = str.match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/);
    if (matchDMY) {
        return `${matchDMY[3]}-${matchDMY[2].padStart(2,'0')}-${matchDMY[1].padStart(2,'0')}`;
    }

    // yyyy-mm-dd (ya en formato correcto)
    const matchYMD = str.match(/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/);
    if (matchYMD) {
        return `${matchYMD[1]}-${matchYMD[2].padStart(2,'0')}-${matchYMD[3].padStart(2,'0')}`;
    }

    // Intentar con Date nativo como último recurso
    const d = new Date(str);
    if (!isNaN(d.getTime())) {
        return d.toISOString().split('T')[0];
    }

    return '';
}

// ====================================
// FUNCIONES PARA VER DETALLES, ITEMS Y ELIMINAR CONCEPTOS
// ====================================

function verDetalleConcepto(conceptoId, codigoClave, catalogoId, catalogoNombre, obraId = null, obraNombre = null) {
    catalogosManager.obtenerDetalleConcepto(conceptoId)
        .then(resp => {
            // Normalizar respuesta
            let concepto = resp;
            if (resp && typeof resp === 'object' && ('success' in resp)) {
                if (resp.success === false) {
                    Swal.fire('Error', resp.error || resp.message || 'Error al cargar concepto', 'error');
                    return;
                }
                if (resp.concepto) {
                    concepto = resp.concepto;
                }
            }

            const montoTotal = parseFloat(concepto.monto_total) || 0;
            const totalItems = parseInt(concepto.total_items) || 0;
            
            // Formatear fechas correctamente (YYYY-MM-DD a DD/MM/YYYY)
            const formatearFecha = (fecha) => {
                if (!fecha || fecha === '0000-00-00') return 'N/A';
                const partes = fecha.split('-');
                if (partes.length === 3) {
                    return `${partes[2]}/${partes[1]}/${partes[0]}`;
                }
                return fecha;
            };
            
            // Formatear números
            const formatearNumero = (valor, decimales = 2, moneda = false) => {
                if (!valor || isNaN(valor)) return 'N/A';
                const num = parseFloat(valor);
                if (moneda) {
                    return '$' + num.toLocaleString('es-MX', {minimumFractionDigits: decimales, maximumFractionDigits: decimales});
                }
                return num.toLocaleString('es-MX', {minimumFractionDigits: decimales, maximumFractionDigits: 3});
            };
            
            // Escapar correctamente los valores
            const nombreConcepto = String(concepto.nombre_concepto || '').replace(/'/g, "\\'");
            const catalogoNombreEscaped = String(catalogoNombre || '').replace(/'/g, "\\'");
            
            let detalleHtml = `
                <div class="concepto-simple">
                    <!-- Información principal -->
                    <div class="mb-3 text-center">
                        <h5 class="text-primary">${concepto.codigo_concepto || 'N/A'}</h5>
                    </div>

                    <!-- Datos básicos -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between border-bottom py-1">
                            <span>Unidad:</span>
                            <strong>${concepto.unidad_medida || 'N/A'}</strong>
                        </div>
                        <div class="d-flex justify-content-between border-bottom py-1">
                            <span>Categoría:</span>
                            <strong>${concepto.categoria || 'N/A'}</strong>
                        </div>
                        <div class="d-flex justify-content-between border-bottom py-1">
                            <span>Subcategoría:</span>
                            <strong>${concepto.subcategoria || 'N/A'}</strong>
                        </div>
                        ${concepto.numero_original ? `
                        <div class="d-flex justify-content-between border-bottom py-1">
                            <span>Número:</span>
                            <strong>${concepto.numero_original}</strong>
                        </div>
                        ` : ''}
                    </div>

                    <!-- Descripción si existe -->
                    ${concepto.descripcion ? `
                    <div class="mb-3">
                        <strong class="text-muted d-block">Descripción:</strong>
                        <div class="bg-light p-2 rounded small">${concepto.descripcion}</div>
                    </div>
                    ` : ''}

                    <!-- ===== NUEVA SECCIÓN: CAMPOS ADICIONALES ===== -->
                    <div class="mb-3">
                        <h6 class="text-primary border-bottom pb-2">Detalles del Concepto</h6>
                        
                        <!-- Cantidad (NUEVO) -->
                        <div class="d-flex justify-content-between border-bottom py-1">
                            <span><i class="bi bi-sort-numeric-up me-1"></i>Cantidad:</span>
                            <strong class="text-dark">${formatearNumero(concepto.cantidad, 3)}</strong>
                        </div>
                        
                        <!-- Precio Unitario -->
                        <div class="d-flex justify-content-between border-bottom py-1">
                            <span><i class="bi bi-tag me-1"></i>Precio Unitario:</span>
                            <strong class="text-success">${formatearNumero(concepto.precio_unitario, 2, true)}</strong>
                        </div>
                        
                        <!-- Importe -->
                        <div class="d-flex justify-content-between border-bottom py-1">
                            <span><i class="bi bi-currency-dollar me-1"></i>Importe:</span>
                            <strong class="text-success">${formatearNumero(concepto.importe, 2, true)}</strong>
                        </div>
                        
                        <!-- Fecha Inicio -->
                        <div class="d-flex justify-content-between border-bottom py-1">
                            <span><i class="bi bi-calendar me-1"></i>Fecha Inicio:</span>
                            <strong>${formatearFecha(concepto.fecha_inicio)}</strong>
                        </div>
                        
                        <!-- Fecha Fin -->
                        <div class="d-flex justify-content-between border-bottom py-1">
                            <span><i class="bi bi-calendar me-1"></i>Fecha Fin:</span>
                            <strong>${formatearFecha(concepto.fecha_fin)}</strong>
                        </div>
                        
                    </div>

                    <!-- Estadísticas de items -->
                    <div class="row text-center mb-3">
                        <div class="col-6">
                            <div class="border rounded p-2">
                                <div class="text-primary fw-bold">${totalItems}</div>
                                <small class="text-muted">Items</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded p-2">
                                <div class="text-success fw-bold">$${montoTotal.toLocaleString('es-MX', {minimumFractionDigits: 2})}</div>
                                <small class="text-muted">Total en items</small>
                            </div>
                        </div>
                    </div>

                    <!-- Botones -->
                    <div class="gap-2 d-flex justify-content-center">
                        <button class="btn btn-sm" style="background-color:#17a2b8;color:white;border:none;" 
                            onclick="verItemsConcepto(${conceptoId}, '${nombreConcepto}', ${catalogoId}, '${catalogoNombreEscaped}')">
                            <i class="bi bi-list-ul me-1"></i>Ver Items
                        </button>
                        <button class="btn btn-outline-secondary btn-sm" onclick="Swal.close()">
                            <i class="bi bi-x-circle me-1"></i>Cerrar
                        </button>
                    </div>
                </div>
            `;
            
            Swal.fire({
                title: 'Detalle del Concepto',
                html: detalleHtml,
                width: '90%',
                maxWidth: '450px',
                showCloseButton: true,
                showConfirmButton: false
            });
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire('Error', 'No se pudo cargar el detalle del concepto', 'error');
        });
}

/**
 * Mostrar items del concepto desde orden_compra_items
 */
// ====================================
// FUNCIÓN CORREGIDA - VER ITEMS
// ====================================
function verItemsConcepto(conceptoId, conceptoNombre, catalogoId, catalogoNombre) {
    // Mostrar loading
    Swal.fire({
        title: 'Cargando items...',
        html: '<div class="text-center"><div class="spinner-border text-primary"></div><p class="mt-2">Por favor espere</p></div>',
        allowOutsideClick: false,
        showConfirmButton: false
    });
    
    // CORRECCIÓN: Ruta absoluta desde la raíz
    fetch(`/api/get_concepto_items.php?concepto_id=${conceptoId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            Swal.close();
            
            if (data.success && data.items && data.items.length > 0) {
                let itemsHtml = `
                    <div class="table-responsive">
                        <table class="table table-hover table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>Descripción</th>
                                    <th class="text-center">Cantidad</th>
                                    <th class="text-center">Unidad</th>
                                    <th class="text-end">Precio Unitario</th>
                                    <th class="text-end">Subtotal</th>
                                    <th class="text-center">Orden</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                
                let totalGeneral = 0;
                
                data.items.forEach(item => {
                    const cantidad = parseFloat(item.cantidad) || 0;
                    const precio = parseFloat(item.precio_unitario) || 0;
                    const subtotal = cantidad * precio;
                    totalGeneral += subtotal;
                    
                    itemsHtml += `
                        <tr>
                            <td>${escapeHtml(item.descripcion || '')}</td>
                            <td class="text-center">${cantidad.toFixed(3)}</td>
                            <td class="text-center">${escapeHtml(item.unidad_medida || 'N/A')}</td>
                            <td class="text-end">$${precio.toFixed(2)}</td>
                            <td class="text-end fw-bold">$${subtotal.toFixed(2)}</td>
                            <td class="text-center">
                                <span class="badge bg-success">${item.folio_oc || 'OC-' + item.orden_compra_id}</span>
                            </td>
                        </tr>
                    `;
                });
                
                itemsHtml += `
                            </tbody>
                            <tfoot class="table-light fw-bold">
                                <tr>
                                    <td colspan="4" class="text-end">Total:</td>
                                    <td class="text-end">$${totalGeneral.toFixed(2)}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                `;
                
                Swal.fire({
                    title: `Items de: ${conceptoNombre}`,
                    html: itemsHtml,
                    width: '90%',
                    maxWidth: '1000px',
                    confirmButtonText: 'Cerrar',
                    confirmButtonColor: '#0d6efd'
                });
            } else {
                Swal.fire({
                    title: 'Sin items',
                    html: `
                        <div class="text-center py-4">
                            <i class="bi bi-inbox display-1 text-muted"></i>
                            <p class="mt-3">No hay items asignados a este concepto</p>
                            <small class="text-muted">Los items aparecen cuando se aprueban órdenes de compra</small>
                        </div>
                    `,
                    icon: 'info',
                    confirmButtonText: 'Cerrar'
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.close();
            Swal.fire({
                icon: 'error',
                title: 'Error',
                html: `
                    <p>No se pudieron cargar los items</p>
                    <small class="text-muted">${error.message}</small>
                    <p class="mt-3"><strong>Verifica:</strong> El archivo API debe existir en la lista de conceptos.</p>
                `,
                confirmButtonText: 'Cerrar'
            });
        });
}


/**
 * Función helper para escapar HTML
 */
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text || '').replace(/[&<>"']/g, m => map[m]);
}

function renderizarConceptosAgrupados(conceptos, containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;

    // Agrupar por categoría y subcategoría
    const grupos = {};
    let sinCategoria = [];
    
    conceptos.forEach(c => {
        const cat = c.categoria?.trim() || '';
        const sub = c.subcategoria?.trim() || '';
        
        if (!cat) {
            sinCategoria.push(c);
            return;
        }
        
        if (!grupos[cat]) grupos[cat] = { nombre: cat, subs: {}, directos: [] };
        
        if (sub) {
            if (!grupos[cat].subs[sub]) grupos[cat].subs[sub] = [];
            grupos[cat].subs[sub].push(c);
        } else {
            grupos[cat].directos.push(c);
        }
    });
    
    // Renderizar HTML
    let html = '';
    
    // Ordenar categorías (I, II, III, etc.)
    const ordenRomano = {'I':1,'II':2,'III':3,'IV':4,'V':5,'VI':6,'VII':7,'VIII':8,'IX':9,'X':10};
    const catsOrdenadas = Object.keys(grupos).sort((a,b) => (ordenRomano[a]||999) - (ordenRomano[b]||999));
    
    catsOrdenadas.forEach(catKey => {
        const cat = grupos[catKey];
        html += `
            <div class="mb-4 border rounded">
                <div class="bg-primary text-white p-3 fw-bold">
                    📁 ${cat.nombre}
                </div>
                <div class="p-3">
        `;
        
        // Subcategorías
        const subsOrdenadas = Object.keys(cat.subs).sort((a,b) => {
            const [,numA] = a.split('.');
            const [,numB] = b.split('.');
            return (parseInt(numA)||0) - (parseInt(numB)||0);
        });
        
        subsOrdenadas.forEach(subKey => {
            html += `
                <div class="mb-3 ms-3">
                    <div class="bg-light p-2 rounded border-start border-3 border-secondary">
                        📂 <strong>${subKey}</strong>
                    </div>
                    <div class="ms-4 mt-2">
                        ${renderConceptosList(cat.subs[subKey])}
                    </div>
                </div>
            `;
        });
        
        // Conceptos directos
        if (cat.directos.length > 0) {
            html += renderConceptosList(cat.directos);
        }
        
        html += '</div></div>';
    });
    
    // Sin categoría
    if (sinCategoria.length > 0) {
        html += `
            <div class="mb-4 border rounded">
                <div class="bg-secondary text-white p-3 fw-bold">
                    Sin Categoría
                </div>
                <div class="p-3">
                    ${renderConceptosList(sinCategoria)}
                </div>
            </div>
        `;
    }
    
    container.innerHTML = html;
}

function renderConceptosList(conceptos) {
    if (!conceptos || conceptos.length === 0) return '<p class="text-muted">Sin conceptos</p>';
    
    return conceptos.map(c => {
        const monto = parseFloat(c.monto_total || 0);
        const items = parseInt(c.total_items || 0);
        
        return `
            <div class="card mb-2 shadow-sm">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <h6 class="mb-1">
                                <span class="badge bg-info">${c.codigo_concepto}</span>
                                ${c.nombre_concepto}
                            </h6>
                            ${c.descripcion ? `<p class="mb-1 small text-muted">${c.descripcion.substring(0,80)}...</p>` : ''}
                            <small class="text-muted">
                                ${c.unidad_medida ? ` ${c.unidad_medida}` : ''}
                                ${c.numero_original ? ` | #${c.numero_original}` : ''}
                            </small>
                        </div>
                        <div class="text-end">
                            <div class="badge bg-success mb-2">$${monto.toLocaleString('es-MX')}</div>
                            <br>
                            <small class="text-muted">${items} items</small>
                            <div class="btn-group btn-group-sm mt-2">
                                <button class="btn btn-sm btn-outline-primary" 
                                    onclick="verDetalleConcepto(${c.id}, '${c.codigo_concepto}', ${c.catalogo_id}, '')">
                                    
                                </button>
                                <button class="btn btn-sm btn-outline-danger" 
                                    onclick="eliminarConcepto(${c.id}, ${c.catalogo_id}, '')">
                                    
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}