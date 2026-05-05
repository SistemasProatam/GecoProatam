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

    async crearConcepto(catalogoId, codigo, nombre, descripcion = '', unidadMedida = '', nodoClave = '', numeroOriginal = '', cantidad = '', precioUnitario = '', importe = '', fechaInicio = '', fechaFin = '') {
        const formData = new FormData();
        formData.append('action', 'crear_concepto');
        formData.append('catalogo_id', catalogoId);
        formData.append('codigo_concepto', codigo);
        formData.append('nombre_concepto', nombre);
        formData.append('descripcion', descripcion);
        formData.append('unidad_medida', unidadMedida);
        formData.append('nodo_clave', nodoClave);
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
            reader.onload = function (e) {
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
            reader.onerror = function () {
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

const catalogosManager = new CatalogosManager();

// ====================================
// GESTIÓN DE CATÁLOGOS
// ====================================
function mostrarFormularioCatalogo(obraId, obraNombre) {
    UI.modal({
        title: "Nuevo Catálogo",
        html: `
            <form id="formNuevoCatalogo">
                <div class="mb-3">
                    <label class="form-label">Nombre del Catálogo <span class="text-danger">*</span></label>
                    <input type="text" name="nombre_catalogo" class="form-control" placeholder="Ej: Catálogo Principal" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Descripción</label>
                    <textarea name="descripcion" class="form-control" rows="3" placeholder="Describe el propósito de este catálogo..."></textarea>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button type="button" class="btn btn-secondary" onclick="UI.modal.close()">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Crear Catálogo</button>
                </div>
            </form>
        `
    });

    document.getElementById("formNuevoCatalogo").addEventListener("submit", function(e) {
        e.preventDefault();
        const nombre = this.nombre_catalogo.value.trim();
        const descripcion = this.descripcion.value.trim();

        if (!nombre) {
            UI.toast.error('El nombre es obligatorio');
            return;
        }

        UI.loading("Creando catálogo...");
        catalogosManager.crearCatalogo(obraId, nombre, descripcion)
            .then(result => {
                UI.loading.hide();
                if (result.success) {
                    UI.modal.close();
                    UI.toast.success('Catálogo creado correctamente');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    UI.toast.error(result.error || 'Error al crear catálogo');
                }
            })
            .catch(err => {
                UI.loading.hide();
                UI.toast.error(err.message);
            });
    });
}

// ====================================
// GESTIÓN DE CONCEPTOS
// ====================================

function mostrarFormularioConcepto(catalogoId, catalogoNombre, obraId = null, obraNombre = null) {
    UI.modal({
        title: "Nuevo Concepto",
        size: "lg",
        html: `
            <form id="formNuevoConcepto" class="text-start">
                <div class="row">
                    <div class="col-6 mb-3">
                        <label class="form-label">Código <span class="text-danger">*</span></label>
                        <input type="text" name="codigo_concepto" class="form-control" placeholder="Ej: CONC-001" required>
                    </div>
                    <div class="col-6 mb-3">
                        <label class="form-label">Núm. Original</label>
                        <input type="text" name="numero_original" class="form-control" placeholder="Ej: 1, 2, 3">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Nombre <span class="text-danger">*</span></label>
                    <input type="text" name="nombre_concepto" class="form-control" placeholder="Ej: Excavación manual" required>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold"><i class="bi bi-diagram-3 me-1"></i>Jerarquía (Clave del nodo)</label>
                    <input type="text" name="nodo_clave" id="swalNodoClave" class="form-control font-monospace" placeholder="Ej: I.1.1 o CIMENTACION">
                    <div class="form-text small">Nivel detectado: <strong id="swalNivelPreview">—</strong></div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Descripción</label>
                    <textarea name="descripcion" class="form-control" rows="2" placeholder="Descripción del concepto..."></textarea>
                </div>

                <hr>
                <div class="row">
                    <div class="col-4 mb-3">
                        <label class="form-label">Unidad</label>
                        <input type="text" name="unidad_medida" class="form-control" placeholder="Ej: m³, kg">
                    </div>
                    <div class="col-4 mb-3">
                        <label class="form-label">Cantidad</label>
                        <input type="number" step="0.001" name="cantidad" class="form-control" placeholder="0.000">
                    </div>
                    <div class="col-4 mb-3">
                        <label class="form-label">P. Unitario</label>
                        <input type="number" step="0.01" name="precio_unitario" class="form-control" placeholder="0.00">
                    </div>
                </div>
                <div class="row">
                    <div class="col-6 mb-3">
                        <label class="form-label">Importe Total</label>
                        <input type="number" step="0.01" name="importe" class="form-control" placeholder="0.00">
                    </div>
                    <div class="col-3 mb-3">
                        <label class="form-label">Inicio</label>
                        <input type="date" name="fecha_inicio" class="form-control">
                    </div>
                    <div class="col-3 mb-3">
                        <label class="form-label">Fin</label>
                        <input type="date" name="fecha_fin" class="form-control">
                    </div>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button type="button" class="btn btn-secondary" onclick="UI.modal.close()">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Concepto</button>
                </div>
            </form>
        `
    });

    const input = document.getElementById('swalNodoClave');
    const preview = document.getElementById('swalNivelPreview');
    input.addEventListener('input', () => {
        const val = input.value.trim();
        preview.textContent = val === '' ? '—' : val.split('.').length;
    });

    document.getElementById("formNuevoConcepto").addEventListener("submit", function(e) {
        e.preventDefault();
        UI.loading("Guardando concepto...");
        catalogosManager.crearConcepto(
            catalogoId,
            this.codigo_concepto.value.trim(),
            this.nombre_concepto.value.trim(),
            this.descripcion.value.trim(),
            this.unidad_medida.value.trim(),
            this.nodo_clave.value.trim(),
            this.numero_original.value.trim(),
            this.cantidad.value.trim(),
            this.precio_unitario.value.trim(),
            this.importe.value.trim(),
            this.fecha_inicio.value.trim(),
            this.fecha_fin.value.trim()
        ).then(result => {
            UI.loading.hide();
            if (result.success) {
                UI.modal.close();
                UI.toast.success('Concepto creado correctamente');
                setTimeout(() => location.reload(), 1500);
            } else {
                UI.toast.error(result.error || 'Error al crear concepto');
            }
        }).catch(err => {
            UI.loading.hide();
            UI.toast.error(err.message);
        });
    });
}

// ====================================
// IMPORTACIÓN DE EXCEL
// ====================================

function mostrarImportarExcelConceptos(catalogoId, catalogoNombre, obraId = null, obraNombre = null) {
    UI.modal({
        title: "Importar Conceptos desde Excel",
        size: "lg",
        html: `
            <div class="alert alert-info text-start mb-3">
                <small><i class="bi bi-info-circle me-1"></i> 
                Columnas requeridas: CLAVE, DESCRIPCIÓN. Opcionales: NUMERO, UNIDAD, P.U., IMPORTE, FECHAS.
                </small>
            </div>
            <div class="mb-3">
                <label class="form-label">Archivo Excel</label>
                <input type="file" id="archivoExcelConceptos" class="form-control" accept=".xlsx, .xls" required>
            </div>
            <div id="vistaPrevia" style="display: none;">
                <h6 class="mb-2">Vista previa:</h6>
                <div id="listaPrevia" class="small overflow-auto" style="max-height: 300px;"></div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-4">
                <button type="button" class="btn btn-secondary" onclick="UI.modal.close()">Cancelar</button>
                <button type="button" id="btnConfirmarImport" class="btn btn-primary" disabled>Importar</button>
            </div>
        `
    });

    const fileInput = document.getElementById('archivoExcelConceptos');
    const btnImport = document.getElementById('btnConfirmarImport');

    fileInput.addEventListener('change', function (e) {
        const file = e.target.files[0];
        if (file) {
            btnImport.disabled = false;
            mostrarVistaPrevia(file);
        } else {
            btnImport.disabled = true;
        }
    });

    btnImport.addEventListener('click', () => {
        if (!fileInput.files[0]) return;
        UI.loading("Importando conceptos...");
        catalogosManager.importarConceptosDesdeExcel(catalogoId, fileInput.files[0])
            .then(result => {
                UI.loading.hide();
                if (result.success) {
                    let resHtml = `
                        <div class="alert alert-success">
                            <h6><i class="bi bi-check-circle me-1"></i> Importación completada</h6>
                            <p class="mb-0"><strong>${result.conceptos_importados}</strong> conceptos importados.</p>
                        </div>
                    `;
                    if (result.errores && result.errores.length > 0) {
                        resHtml += `
                            <div class="alert alert-warning mt-2 small overflow-auto" style="max-height:150px;">
                                <strong>Avisos (${result.errores.length}):</strong>
                                <ul class="mb-0 mt-1">${result.errores.map(e => `<li>${e}</li>`).join('')}</ul>
                            </div>
                        `;
                    }
                    UI.modal({
                        title: "Resultado de Importación",
                        html: resHtml + '<div class="text-end mt-3"><button class="btn btn-primary" onclick="location.reload()">Cerrar</button></div>'
                    });
                } else {
                    UI.toast.error(result.error || 'Error en la importación');
                }
            })
            .catch(err => {
                UI.loading.hide();
                UI.toast.error(err.message);
            });
    });
}

async function mostrarVistaPrevia(file) {
    try {
        const conceptos = await catalogosManager.procesarArchivoExcel(file);
        const categorias = {};
        conceptos.forEach(c => {
            const cat = c.categoria || (c.es_nodo ? 'Nodo' : 'Sin categoría');
            if (!categorias[cat]) categorias[cat] = 0;
            categorias[cat]++;
        });

        const vistaPrevia = document.getElementById('vistaPrevia');
        const listaPrevia = document.getElementById('listaPrevia');
        vistaPrevia.style.display = 'block';

        if (conceptos.length > 0) {
            let html = `
                <div class="alert alert-success py-2 px-3 mb-3 small">
                    <strong>${conceptos.length} registros encontrados.</strong>
                </div>
                <div class="list-group list-group-flush border rounded">
            `;
            conceptos.slice(0, 15).forEach((concepto) => {
                html += `
                    <div class="list-group-item py-2">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="badge bg-light text-dark border me-2">${concepto.codigo_concepto || 'NODO'}</span>
                                <span class="small">${(concepto.nombre_concepto || concepto.nombre || '').substring(0, 80)}...</span>
                            </div>
                        </div>
                    </div>
                `;
            });
            if (conceptos.length > 15) html += `<div class="list-group-item text-center text-muted small">... y ${conceptos.length - 15} más</div>`;
            html += `</div>`;
            listaPrevia.innerHTML = html;
        } else {
            listaPrevia.innerHTML = `<div class="alert alert-warning small">No se encontraron conceptos válidos.</div>`;
        }
    } catch (error) {
        document.getElementById('listaPrevia').innerHTML = `<div class="alert alert-danger small">Error: ${error.message}</div>`;
    }
}

// ====================================
// FUNCIONES DE DETALLE Y ELIMINACIÓN
// ====================================

function verDetalleConcepto(conceptoId, codigoClave, catalogoId, catalogoNombre) {
    UI.loading("Cargando detalle...");
    catalogosManager.obtenerDetalleConcepto(conceptoId)
        .then(resp => {
            UI.loading.hide();
            let concepto = (resp && resp.concepto) ? resp.concepto : resp;
            if (resp.success === false) { UI.toast.error(resp.error); return; }

            const fmtNum = (v, d = 2) => isNaN(parseFloat(v)) ? 'N/A' : parseFloat(v).toLocaleString('es-MX', { minimumFractionDigits: d, maximumFractionDigits: d });
            const fmtDate = (f) => (!f || f === '0000-00-00') ? 'N/A' : f.split('-').reverse().join('/');

            UI.modal({
                title: 'Detalle del Concepto',
                size: 'lg',
                html: `
                    <div class="p-2">
                        <div class="row mb-4 text-center">
                            <div class="col-12"><h4 class="text-primary mb-1">${concepto.codigo_concepto}</h4><p class="text-muted small">${concepto.nombre_concepto}</p></div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="card bg-light border-0"><div class="card-body p-3">
                                    <div class="d-flex justify-content-between border-bottom pb-2 mb-2"><span>Unidad</span><strong>${concepto.unidad_medida || 'N/A'}</strong></div>
                                    <div class="d-flex justify-content-between border-bottom pb-2 mb-2"><span>Categoría</span><strong>${concepto.categoria || 'N/A'}</strong></div>
                                    <div class="d-flex justify-content-between"><span>Subcategoría</span><strong>${concepto.subcategoria || 'N/A'}</strong></div>
                                </div></div>
                            </div>
                            <div class="col-md-6">
                                <div class="card bg-light border-0"><div class="card-body p-3">
                                    <div class="d-flex justify-content-between border-bottom pb-2 mb-2"><span>Cantidad</span><strong>${fmtNum(concepto.cantidad, 3)}</strong></div>
                                    <div class="d-flex justify-content-between border-bottom pb-2 mb-2"><span>P. Unitario</span><strong class="text-success">$${fmtNum(concepto.precio_unitario)}</strong></div>
                                    <div class="d-flex justify-content-between"><span>Importe</span><strong class="text-success">$${fmtNum(concepto.importe)}</strong></div>
                                </div></div>
                            </div>
                        </div>
                        <div class="mt-3 card bg-light border-0"><div class="card-body p-3">
                            <div class="d-flex justify-content-between mb-2"><span>Periodo</span><strong>${fmtDate(concepto.fecha_inicio)} - ${fmtDate(concepto.fecha_fin)}</strong></div>
                            <div class="small text-muted border-top pt-2">${concepto.descripcion || 'Sin descripción adicional'}</div>
                        </div></div>
                        <div class="row mt-3 text-center">
                            <div class="col-6"><div class="border rounded p-2"><div class="h5 mb-0 text-primary">${concepto.total_items || 0}</div><small class="text-muted">Items OC</small></div></div>
                            <div class="col-6"><div class="border rounded p-2"><div class="h5 mb-0 text-success">$${fmtNum(concepto.monto_total)}</div><small class="text-muted">Monto total items</small></div></div>
                        </div>
                        <div class="d-flex justify-content-center gap-2 mt-4">
                            <button class="btn btn-info" onclick="verItemsConcepto(${conceptoId}, '${concepto.nombre_concepto.replace(/'/g, "\\'")}')"><i class="bi bi-list-ul me-1"></i> Ver Items OC</button>
                            <button class="btn btn-secondary" onclick="UI.modal.close()">Cerrar</button>
                        </div>
                    </div>
                `
            });
        })
        .catch(() => { UI.loading.hide(); UI.toast.error("Error al cargar detalle"); });
}

function verItemsConcepto(conceptoId, conceptoNombre) {
    UI.loading("Buscando items...");
    fetch(`/api/get_concepto_items.php?concepto_id=${conceptoId}`)
        .then(r => r.json())
        .then(data => {
            UI.loading.hide();
            if (data.success && data.items && data.items.length > 0) {
                let tableHtml = `
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle">
                            <thead class="table-light"><tr><th>Descripción</th><th class="text-center">Cant.</th><th class="text-end">P.U.</th><th class="text-end">Subtotal</th><th class="text-center">Orden</th></tr></thead>
                            <tbody>
                `;
                let total = 0;
                data.items.forEach(it => {
                    const sub = (parseFloat(it.cantidad) || 0) * (parseFloat(it.precio_unitario) || 0);
                    total += sub;
                    tableHtml += `
                        <tr>
                            <td class="small">${escapeHtml(it.descripcion)}</td>
                            <td class="text-center">${parseFloat(it.cantidad).toFixed(3)}</td>
                            <td class="text-end">$${parseFloat(it.precio_unitario).toLocaleString()}</td>
                            <td class="text-end fw-bold">$${sub.toLocaleString()}</td>
                            <td class="text-center"><span class="badge bg-success">${it.folio_oc || 'OC-'+it.orden_compra_id}</span></td>
                        </tr>
                    `;
                });
                tableHtml += `</tbody><tfoot class="table-light fw-bold"><tr><td colspan="3" class="text-end">Total:</td><td class="text-end text-success">$${total.toLocaleString()}</td><td></td></tr></tfoot></table></div>`;
                
                UI.modal({
                    title: `Items de OC: ${conceptoNombre}`,
                    size: 'xl',
                    html: tableHtml + '<div class="text-end mt-3"><button class="btn btn-secondary" onclick="UI.modal.close()">Cerrar</button></div>'
                });
            } else {
                UI.modal({
                    title: "Sin items",
                    html: '<div class="text-center py-4"><i class="bi bi-inbox display-4 text-muted d-block mb-3"></i>No hay items asignados en órdenes de compra para este concepto.</div>'
                });
            }
        })
        .catch(() => { UI.loading.hide(); UI.toast.error("Error al obtener items"); });
}

function eliminarCatalogo(catalogoId) {
    UI.confirm({
        title: '¿Eliminar catálogo?',
        message: 'Esta acción eliminará todos los conceptos y items asociados permanentemente.',
        danger: true
    }).then(conf => {
        if (conf) {
            UI.loading("Eliminando...");
            catalogosManager.eliminarCatalogo(catalogoId)
                .then(r => {
                    UI.loading.hide();
                    if (r.success) { UI.toast.success("Catálogo eliminado"); setTimeout(() => location.reload(), 1500); }
                    else UI.toast.error(r.error);
                }).catch(() => { UI.loading.hide(); UI.toast.error("Error de red"); });
        }
    });
}

function eliminarConcepto(conceptoId) {
    UI.confirm({
        title: '¿Eliminar concepto?',
        message: 'Se eliminarán también todos los items vinculados en el presupuesto de control.',
        danger: true
    }).then(conf => {
        if (conf) {
            UI.loading("Eliminando...");
            catalogosManager.eliminarConcepto(conceptoId)
                .then(r => {
                    UI.loading.hide();
                    if (r.success) { UI.toast.success("Concepto eliminado"); setTimeout(() => location.reload(), 1500); }
                    else UI.toast.error(r.error);
                }).catch(() => { UI.loading.hide(); UI.toast.error("Error de red"); });
        }
    });
}

function editarCatalogo(catalogoId, nombreActual = '', descActual = '') {
    UI.modal({
        title: "Editar Catálogo",
        html: `
            <form id="formEditCatalogo">
                <div class="mb-3">
                    <label class="form-label">Nombre del Catálogo <span class="text-danger">*</span></label>
                    <input type="text" name="nombre_catalogo" class="form-control" value="${nombreActual}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Descripción</label>
                    <textarea name="descripcion" class="form-control" rows="3">${descActual}</textarea>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button type="button" class="btn btn-secondary" onclick="UI.modal.close()">Cancelar</button>
                    <button type="submit" class="btn btn-warning">Guardar Cambios</button>
                </div>
            </form>
        `
    });

    document.getElementById("formEditCatalogo").addEventListener("submit", function(e) {
        e.preventDefault();
        const fd = new FormData(this);
        fd.append('action', 'actualizar_catalogo');
        fd.append('catalogo_id', catalogoId);

        UI.loading("Actualizando...");
        fetch('catalogos_manager.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                UI.loading.hide();
                if (res.success) {
                    UI.modal.close();
                    UI.toast.success("Catálogo actualizado");
                    setTimeout(() => location.reload(), 1500);
                } else UI.toast.error(res.error);
            }).catch(() => { UI.loading.hide(); UI.toast.error("Error de conexión"); });
    });
}

// Helpers
function escapeHtml(text) {
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return String(text || '').replace(/[&<>"']/g, m => map[m]);
}

function esCategoriaNivel1(clave) {
    if (!clave) return false;
    const v = clave.toString().trim().toUpperCase();
    if (/^\d+$/.test(v)) return parseInt(v) < 1000;
    return /^[IVXLCDM]+$/.test(v);
}
function esSubcategoria(clave) {
    if (!clave) return false;
    return /^[A-Z0-9]+\.[0-9]+$/.test(clave.toString().trim().toUpperCase());
}
function esCategoriaNivel2(clave) { return /^\d+\.\d+$/.test(clave.toString().trim()); }
function esCategoriaNivel3(clave) { return /^[A-Z0-9]+\.[0-9]+\.[0-9]+$/.test(clave.toString().trim().toUpperCase()); }

function procesarDatosCatalogoFlexible(jsonData) {
    const conceptos = [];
    let filaEnc = -1;
    let mapeo = {};

    for (let i = 0; i < Math.min(jsonData.length, 20); i++) {
        const det = detectarEncabezados(jsonData[i]);
        if (det.valido) { filaEnc = i; mapeo = det.mapeo; break; }
    }
    if (filaEnc === -1) throw new Error('No se encontraron los encabezados (CLAVE, DESCRIPCIÓN).');

    let nodoActual = '';
    let seq = 1;
    for (let i = filaEnc + 1; i < jsonData.length; i++) {
        const f = jsonData[i];
        if (!f || f.length === 0) continue;
        const num = obtenerValorColumna(f, mapeo.numero);
        const cve = obtenerValorColumna(f, mapeo.clave);
        const desc = obtenerValorColumna(f, mapeo.descripcion);
        if (!cve && !desc) continue;
        const cant = obtenerValorColumna(f, mapeo.cantidad);
        const pu = obtenerValorColumna(f, mapeo.precio_unitario);
        const hasM = (cant && !isNaN(parseFloat(cant))) || (pu && !isNaN(parseFloat(pu)));
        const cveU = cve.toUpperCase();

        if (!hasM && (esCategoriaNivel1(cveU) || esSubcategoria(cveU) || esCategoriaNivel2(cve) || esCategoriaNivel3(cve))) {
            nodoActual = cve;
            conceptos.push({ es_nodo: true, nodo_clave: cve, nombre: desc.trim() });
            continue;
        }
        if (cve && desc) {
            conceptos.push({
                codigo_concepto: cve,
                nombre_concepto: desc.substring(0, 100).trim(),
                descripcion: desc.trim(),
                unidad_medida: obtenerValorColumna(f, mapeo.unidad) || obtenerUnidadDesdeDescripcion(desc),
                nodo_clave: nodoActual,
                numero_original: num || String(seq),
                cantidad: cant,
                precio_unitario: pu,
                importe: obtenerValorColumna(f, mapeo.importe),
                fecha_inicio: normalizarFecha(obtenerValorColumna(f, mapeo.fecha_inicio)),
                fecha_fin: normalizarFecha(obtenerValorColumna(f, mapeo.fecha_fin))
            });
            seq++;
        }
    }
    return conceptos;
}

function detectarEncabezados(f) {
    const m = { numero: -1, clave: -1, descripcion: -1, unidad: -1, cantidad: -1, precio_unitario: -1, importe: -1, fecha_inicio: -1, fecha_fin: -1 };
    f.forEach((c, i) => {
        if (!c) return;
        const v = String(c).toUpperCase().trim().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
        if (v.includes('NUMERO') || v === 'NO.' || v === '#') m.numero = i;
        if (v.includes('CLAVE') || v.includes('CODIGO') || v === 'CVE') m.clave = i;
        if (v.includes('DESCRIPCION') || v.includes('CONCEPTO') || v === 'DESC') m.descripcion = i;
        if (v.includes('UNIDAD') || v === 'U.M' || v === 'UM') m.unidad = i;
        if (v === 'CANTIDAD' || v === 'CANT') m.cantidad = i;
        if (v === 'P.U.' || v === 'PU' || v.includes('UNITARIO')) m.precio_unitario = i;
        if (v === 'IMPORTE' || v.includes('MONTO')) m.importe = i;
        if (v.includes('FECHA INICIO') || v === 'INICIO') m.fecha_inicio = i;
        if (v.includes('FECHA FIN') || v === 'FIN' || v.includes('TERMINO')) m.fecha_fin = i;
    });
    return { valido: (m.clave !== -1 && m.descripcion !== -1), mapeo: m };
}

function obtenerValorColumna(f, i) { return (i === -1 || i >= f.length) ? '' : (f[i] ? String(f[i]).trim() : ''); }
function obtenerUnidadDesdeDescripcion(d) {
    const u = ['m³', 'm2', 'kg', 'pza', 'm', 'lts', 'hr', 'día', 'mes'];
    for (const x of u) if (d.toLowerCase().includes(x)) return x;
    return '';
}
function normalizarFecha(v) {
    if (!v) return '';
    const n = parseFloat(v);
    if (!isNaN(n) && n > 1000) {
        const d = new Date(Math.round((n - 25569) * 86400 * 1000));
        return isNaN(d.getTime()) ? '' : d.toISOString().split('T')[0];
    }
    const s = String(v).trim();
    const m1 = s.match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/);
    if (m1) return `${m1[3]}-${m1[2].padStart(2, '0')}-${m1[1].padStart(2, '0')}`;
    const m2 = s.match(/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/);
    if (m2) return `${m2[1]}-${m2[2].padStart(2, '0')}-${m2[3].padStart(2, '0')}`;
    const d2 = new Date(s);
    return isNaN(d2.getTime()) ? '' : d2.toISOString().split('T')[0];
}