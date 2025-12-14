let currentModule = '';
let currentData = {};
let hasFondos = false;

document.addEventListener('DOMContentLoaded', function () {
    initializeConfiguracion();
    loadDefaultData();
});

function initializeConfiguracion() {
    setupTabNavigation();
    setupModalListeners();
}

function setupTabNavigation() {
    const tabBtns = document.querySelectorAll('.tab-btn');
    const sections = document.querySelectorAll('.config-section');

    tabBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            const targetTab = this.dataset.tab;
            tabBtns.forEach(b => b.classList.remove('active'));
            sections.forEach(s => s.classList.remove('active'));
            this.classList.add('active');
            document.getElementById(targetTab).classList.add('active');
            loadTabData(targetTab);
        });
    });
}

function setupModalListeners() {
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function (e) {
            if (e.target === this) {
                closeModal(this.id);
            }
        });
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            const openModal = document.querySelector('.modal.show');
            if (openModal) {
                closeModal(openModal.id);
            }
        }
    });
}

function loadDefaultData() {
    loadTabData('catalogos');
}
function loadTabData(tab) {
    switch (tab) {
        case 'catalogos':
            loadDeduccion();
            loadMoneda();
            loadContrato();
            loadGarantia();
            loadBeneficio();
            loadPlantillasNotificacion();
            loadPoliticaCancelacion();
            break;
        case 'parametros':
            loadConfiguracion();
            loadMora();
            loadTipoPrestamo();
            break;
        case 'auditoria':
            loadAuditoria();
            break;
        case 'fondos':
            loadFondos();
            loadCaja();
            break;
    }
}

async function apiRequest(module, action, data = {}) {
    try {
        const formData = new FormData();
        formData.append('module', module);
        formData.append('action', action);

        Object.keys(data).forEach(key => {
            formData.append(key, data[key]);
        });

        const response = await fetch('../api/configuracion.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (!result.ok) {
            throw new Error(result.error || 'Error en la petición');
        }

        return result;
    } catch (error) {
        console.error('Error en API request:', error);
        throw error;
    }
}

async function aplicarMoraAhora() {

    if (!confirm('Esto recalculará y aplicará la mora según la configuración ACTIVA. ¿Deseas continuar?')) return;

    try {
        const result = await apiRequest('mora', 'apply');
        alert('Mora aplicada correctamente.');
        loadMora();
    } catch (error) {
        alert('Error aplicando mora: ' + error.message);
        console.error(error);
    }
}
document.addEventListener('DOMContentLoaded', () => {
    const btnAplicar = document.getElementById('btnAplicarMora');
    if (btnAplicar) btnAplicar.addEventListener('click', aplicarMoraAhora);
});

async function loadDeduccion() {
    try {
        const response = await apiRequest('deduccion', 'list');
        const tbody = document.querySelector('#tableDeduccion tbody');
        tbody.innerHTML = '';

        response.data.forEach(item => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${item.tipo_deduccion}</td>
                <td>${parseFloat(item.valor).toFixed(2)}%</td>
                <td class="table-actions">
                    <button class="action-btn edit" onclick="editDeduccion(${item.id_tipo_deduccion})">
                        Editar
                    </button>
                    <button class="action-btn delete" onclick="deleteDeduccion(${item.id_tipo_deduccion})">
                        Eliminar
                    </button>
                </td>
            `;
            tbody.appendChild(row);
        });
    } catch (error) {
        console.error('Error cargando deducciones:', error);
    }
}
async function editDeduccion(id) {
    try {
        const response = await apiRequest('deduccion', 'list');
        const item = response.data.find(d => d.id_tipo_deduccion == id);

        if (item) {
            document.getElementById('idDeduccion').value = item.id_tipo_deduccion;
            document.getElementById('tipoDeduccion').value = item.tipo_deduccion;
            document.getElementById('valorDeduccion').value = item.valor;
            document.getElementById('titleDeduccion').textContent = 'Editar Deducción';
            openModal('modalDeduccion');
        }
    } catch (error) {
        console.error('Error editando deducción:', error);
    }
}
async function deleteDeduccion(id) {
    if (!confirm('¿Está seguro de que desea eliminar esta deducción?')) return;

    try {
        await apiRequest('deduccion', 'delete', { id });
        loadDeduccion();
    } catch (error) {
        console.error('Error eliminando deducción:', error);
    }
}
async function saveDeduccion(event) {
    event.preventDefault();

    const formData = new FormData(event.target);
    const data = Object.fromEntries(formData);

    try {
        await apiRequest('deduccion', 'save', data);
        closeModal('modalDeduccion');
        event.target.reset();
        loadDeduccion();
    } catch (error) {
        console.error('Error guardando deducción:', error);
    }
}

async function loadMoneda() {
    try {
        const response = await apiRequest('moneda', 'list');
        const tbody = document.querySelector('#tableMoneda tbody');
        tbody.innerHTML = '';

        response.data.forEach(item => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${item.tipo_moneda}</td>
                <td>${parseFloat(item.valor).toFixed(2)}</td>
                <td class="table-actions">
                    <button class="action-btn edit" onclick="editMoneda(${item.id_tipo_moneda})">
                        Editar
                    </button>
                    <button class="action-btn delete" onclick="deleteMoneda(${item.id_tipo_moneda})">
                        Eliminar
                    </button>
                </td>
            `;
            tbody.appendChild(row);
        });
    } catch (error) {
        console.error('Error cargando monedas:', error);
    }
}
async function editMoneda(id) {
    try {
        const response = await apiRequest('moneda', 'list');
        const item = response.data.find(m => m.id_tipo_moneda == id);

        if (item) {
            document.getElementById('idMoneda').value = item.id_tipo_moneda;
            document.getElementById('tipoMoneda').value = item.tipo_moneda;
            document.getElementById('valorMoneda').value = item.valor;
            document.getElementById('titleMoneda').textContent = 'Editar Tipo de Moneda';
            openModal('modalMoneda');
        }
    } catch (error) {
        console.error('Error editando moneda:', error);
    }
}
async function deleteMoneda(id) {
    if (!confirm('¿Está seguro de que desea eliminar este tipo de moneda?')) return;

    try {
        await apiRequest('moneda', 'delete', { id });
        loadMoneda();
    } catch (error) {
        console.error('Error eliminando moneda:', error);
    }
}
async function saveMoneda(event) {
    event.preventDefault();

    const formData = new FormData(event.target);
    const data = Object.fromEntries(formData);

    try {
        await apiRequest('moneda', 'save', data);
        closeModal('modalMoneda');
        event.target.reset();
        loadMoneda();
    } catch (error) {
        console.error('Error guardando moneda:', error);
    }
}

async function loadContrato() {
    try {
        const response = await apiRequest('contrato', 'list');
        const tbody = document.querySelector('#tableContrato tbody');
        tbody.innerHTML = '';

        response.data.forEach(item => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${item.tipo_contrato}</td>
                <td class="table-actions">
                    <button class="action-btn edit" onclick="editContrato(${item.id_tipo_contrato})">
                        Editar
                    </button>
                    <button class="action-btn delete" onclick="deleteContrato(${item.id_tipo_contrato})">
                        Eliminar
                    </button>
                </td>
            `;
            tbody.appendChild(row);
        });
    } catch (error) {
        console.error('Error cargando contratos:', error);
    }
}

async function editContrato(id) {
    try {
        const response = await apiRequest('contrato', 'list');
        const item = response.data.find(c => c.id_tipo_contrato == id);

        if (item) {
            document.getElementById('idContrato').value = item.id_tipo_contrato;
            document.getElementById('tipoContrato').value = item.tipo_contrato;
            document.getElementById('titleContrato').textContent = 'Editar Tipo de Contrato';
            openModal('modalContrato');
        }
    } catch (error) {
        console.error('Error editando contrato:', error);
    }
}

async function deleteContrato(id) {
    if (!confirm('¿Está seguro de que desea eliminar este tipo de contrato?')) return;

    try {
        await apiRequest('contrato', 'delete', { id });
        loadContrato();
    } catch (error) {
        console.error('Error eliminando contrato:', error);
    }
}

async function saveContrato(event) {
    event.preventDefault();

    const formData = new FormData(event.target);
    const data = Object.fromEntries(formData);

    try {
        await apiRequest('contrato', 'save', data);
        closeModal('modalContrato');
        event.target.reset();
        loadContrato();
    } catch (error) {
        console.error('Error guardando contrato:', error);
    }
}

async function loadGarantia() {
    try {
        const response = await apiRequest('garantia', 'list');
        const tbody = document.querySelector('#tableGarantia tbody');
        tbody.innerHTML = '';

        response.data.forEach(item => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${item.tipo_garantia}</td>
                <td>${item.descripcion || 'Sin descripción'}</td>
                <td class="table-actions">
                    <button class="action-btn edit" onclick="editGarantia(${item.id_tipo_garantia})">
                        Editar
                    </button>
                    <button class="action-btn delete" onclick="deleteGarantia(${item.id_tipo_garantia})">
                        Eliminar
                    </button>
                </td>
            `;
            tbody.appendChild(row);
        });
    } catch (error) {
        console.error('Error cargando garantías:', error);
    }
}

async function editGarantia(id) {
    try {
        const response = await apiRequest('garantia', 'list');
        const item = response.data.find(g => g.id_tipo_garantia == id);

        if (item) {
            document.getElementById('idGarantia').value = item.id_tipo_garantia;
            document.getElementById('tipoGarantia').value = item.tipo_garantia;
            document.getElementById('descripcionGarantia').value = item.descripcion || '';
            document.getElementById('titleGarantia').textContent = 'Editar Tipo de Garantía';
            openModal('modalGarantia');
        }
    } catch (error) {
        console.error('Error editando garantía:', error);
    }
}

async function deleteGarantia(id) {
    if (!confirm('¿Está seguro de que desea eliminar este tipo de garantía?')) return;

    try {
        await apiRequest('garantia', 'delete', { id });
        loadGarantia();
    } catch (error) {
        console.error('Error eliminando garantía:', error);
    }
}
async function saveGarantia(event) {
    event.preventDefault();

    const formData = new FormData(event.target);
    const data = Object.fromEntries(formData);

    try {
        await apiRequest('garantia', 'save', data);
        closeModal('modalGarantia');
        event.target.reset();
        loadGarantia();
    } catch (error) {
        console.error('Error guardando garantía:', error);
    }
}

async function loadBeneficio() {
    try {
        const response = await apiRequest('beneficio', 'list');
        const tbody = document.querySelector('#tableBeneficio tbody');
        tbody.innerHTML = '';

        response.data.forEach(item => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${item.tipo_beneficio}</td>
                <td>${parseFloat(item.valor).toFixed(2)}</td>
                <td class="table-actions">
                    <button class="action-btn edit" onclick="editBeneficio(${item.id_beneficio})">
                        Editar
                    </button>
                    <button class="action-btn delete" onclick="deleteBeneficio(${item.id_beneficio})">
                        Eliminar
                    </button>
                </td>
            `;
            tbody.appendChild(row);
        });
    } catch (error) {
        console.error('Error cargando beneficios:', error);
    }
}

async function editBeneficio(id) {
    try {
        const response = await apiRequest('beneficio', 'list');
        const item = response.data.find(b => b.id_beneficio == id);

        if (item) {
            document.getElementById('idBeneficio').value = item.id_beneficio;
            document.getElementById('tipoBeneficio').value = item.tipo_beneficio;
            document.getElementById('valorBeneficio').value = item.valor;
            document.getElementById('titleBeneficio').textContent = 'Editar Beneficio';
            openModal('modalBeneficio');
        }
    } catch (error) {
        console.error('Error editando beneficio:', error);
    }
}

async function deleteBeneficio(id) {
    if (!confirm('¿Está seguro de que desea eliminar este beneficio? Esta acción también lo eliminará de la configuración general.')) return;
    try {
        await apiRequest('beneficio', 'delete', { id });
        loadBeneficio();
        if (document.getElementById('parametros').classList.contains('active')) {
            loadConfiguracion();
        }
    } catch (error) {
        console.error('Error eliminando beneficio:', error);
    }
}

async function saveBeneficio(event) {
    event.preventDefault();

    const formData = new FormData(event.target);
    const data = Object.fromEntries(formData);

    try {
        await apiRequest('beneficio', 'save', data);
        closeModal('modalBeneficio');
        event.target.reset();
        loadBeneficio();
        if (document.getElementById('parametros').classList.contains('active')) {
            loadConfiguracion();
        }
    } catch (error) {
        console.error('Error guardando beneficio:', error);
    }
}

async function loadConfiguracion() {
    try {
        const response = await apiRequest('configuracion', 'list');
        const tbody = document.querySelector('#tableConfiguracion tbody');
        tbody.innerHTML = '';

        response.data.forEach(item => {
            const row = document.createElement('tr');
            const estado = item.estado;
            const fechaFormateada = new Date(item.actualizado_en).toLocaleString('es-ES');

            row.innerHTML = `
                <td>${item.nombre_configuracion}</td>
                <td>${parseFloat(item.valor_decimal).toFixed(2)}</td>
                <td><span class="status-badge ${estado.toLowerCase()}">${estado}</span></td>
                <td>${fechaFormateada}</td>
                <td class="table-actions">
                    <button class="action-btn edit" onclick="editConfiguracion(${item.id_configuracion})">
                        Editar
                    </button>
                    <button class="action-btn delete" onclick="deleteConfiguracion(${item.id_configuracion})">
                        Eliminar
                    </button>
                </td>
            `;
            tbody.appendChild(row);
        });
    } catch (error) {
        console.error('Error cargando configuración:', error);
    }
}

async function editConfiguracion(id) {
    try {
        const response = await apiRequest('configuracion', 'list');
        const item = response.data.find(c => c.id_configuracion == id);

        if (item) {
            document.getElementById('idConfiguracion').value = item.id_configuracion;
            document.getElementById('nombreConfiguracion').value = item.nombre_configuracion;
            document.getElementById('valorConfiguracion').value = item.valor_decimal;
            document.getElementById('estadoConfiguracion').value = item.estado;
            document.getElementById('titleConfiguracion').textContent = 'Editar Configuración';
            openModal('modalConfiguracion');
        }
    } catch (error) {
        console.error('Error editando configuración:', error);
    }
}

async function deleteConfiguracion(id) {
    if (!confirm('¿Está seguro de que desea eliminar esta configuración?')) return;

    try {
        await apiRequest('configuracion', 'delete', { id });
        loadConfiguracion();
    } catch (error) {
        console.error('Error eliminando configuración:', error);
    }
}

async function saveConfiguracion(event) {
    event.preventDefault();

    const formData = new FormData(event.target);
    const data = Object.fromEntries(formData);

    try {
        await apiRequest('configuracion', 'save', data);
        closeModal('modalConfiguracion');
        event.target.reset();
        loadConfiguracion();
    } catch (error) {
        console.error('Error guardando configuración:', error);
    }
}

async function loadMora() {
    try {
        const response = await apiRequest('mora', 'list');
        const tbody = document.querySelector('#tableMora tbody');
        tbody.innerHTML = '';

        response.data.forEach(item => {
            const row = document.createElement('tr');
            const estado = item.estado;

            row.innerHTML = `
                <td>${parseFloat(item.porcentaje_mora).toFixed(2)}%</td>
                <td>${item.dias_gracia}</td>
                <td>${item.vigente_desde}</td>
                <td>${item.vigente_hasta || 'Sin límite'}</td>
                <td><span class="status-badge ${estado.toLowerCase()}">${estado}</span></td>
                <td class="table-actions">
                    <button class="action-btn edit" onclick="editMora(${item.id_mora})">
                        Editar
                    </button>
                    <button class="action-btn delete" onclick="deleteMora(${item.id_mora})">
                        Eliminar
                    </button>
                </td>
            `;
            tbody.appendChild(row);
        });
    } catch (error) {
        console.error('Error cargando configuración de mora:', error);
    }
}

async function editMora(id) {
    try {
        const response = await apiRequest('mora', 'list');
        const item = response.data.find(m => m.id_mora == id);

        if (item) {
            document.getElementById('idMora').value = item.id_mora;
            document.getElementById('porcentajeMora').value = item.porcentaje_mora;
            document.getElementById('diasGracia').value = item.dias_gracia;
            document.getElementById('vigenteDesde').value = item.vigente_desde;
            document.getElementById('vigenteHasta').value = item.vigente_hasta || '';
            document.getElementById('estadoMora').value = item.estado;
            document.getElementById('titleMora').textContent = 'Editar Configuración de Mora';
            openModal('modalMora');
        }
    } catch (error) {
        console.error('Error editando configuración de mora:', error);
    }
}

async function deleteMora(id) {
    if (!confirm('¿Está seguro de que desea eliminar esta configuración de mora?')) return;

    try {
        await apiRequest('mora', 'delete', { id });
        loadMora();
    } catch (error) {
        console.error('Error eliminando configuración de mora:', error);
    }
}

async function saveMora(event) {
    event.preventDefault();

    const formData = new FormData(event.target);
    const data = Object.fromEntries(formData);

    try {
        await apiRequest('mora', 'save', data);
        closeModal('modalMora');
        event.target.reset();
        loadMora();
    } catch (error) {
        console.error('Error guardando configuración de mora:', error);
    }
}

async function loadTipoPrestamo() {
    try {
        const response = await apiRequest('tipo_prestamo', 'list');
        const tbody = document.querySelector('#tableTipoPrestamo tbody');
        tbody.innerHTML = '';

        response.data.forEach(item => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${item.nombre}</td>
                <td>${parseFloat(item.tasa_interes).toFixed(2)}%</td>
                <td>$${parseFloat(item.monto_minimo).toFixed(2)}</td>
                <td>${item.plazo_minimo_meses}</td>
                <td>${item.plazo_maximo_meses}</td>
                <td class="table-actions">
                    <button class="action-btn edit" onclick="editTipoPrestamo(${item.id_tipo_prestamo})">
                        Editar
                    </button>
                    <button class="action-btn delete" onclick="deleteTipoPrestamo(${item.id_tipo_prestamo})">
                        Eliminar
                    </button>
                </td>
            `;
            tbody.appendChild(row);
        });
    } catch (error) {
        console.error('Error cargando tipos de préstamo:', error);
    }
}

async function editTipoPrestamo(id) {
    try {
        const response = await apiRequest('tipo_prestamo', 'list');
        const item = response.data.find(tp => tp.id_tipo_prestamo == id);

        if (item) {
            document.getElementById('idTipoPrestamo').value = item.id_tipo_prestamo;
            document.getElementById('nombreTipoPrestamo').value = item.nombre;
            document.getElementById('tasaInteresTipoPrestamo').value = item.tasa_interes;
            document.getElementById('montoMinimoTipoPrestamo').value = item.monto_minimo;
            document.getElementById('plazoMinimoTipoPrestamo').value = item.plazo_minimo_meses;
            document.getElementById('plazoMaximoTipoPrestamo').value = item.plazo_maximo_meses;
            document.getElementById('titleTipoPrestamo').textContent = 'Editar Tipo de Préstamo';
            openModal('modalTipoPrestamo');
        }
    } catch (error) {
        console.error('Error editando tipo de préstamo:', error);
    }
}

async function deleteTipoPrestamo(id) {
    if (!confirm('¿Está seguro de que desea eliminar este tipo de préstamo?')) return;

    try {
        await apiRequest('tipo_prestamo', 'delete', { id });
        loadTipoPrestamo();
    } catch (error) {
        console.error('Error eliminando tipo de préstamo:', error);
    }
}

async function saveTipoPrestamo(event) {
    event.preventDefault();

    const formData = new FormData(event.target);
    const data = Object.fromEntries(formData);

    try {
        await apiRequest('tipo_prestamo', 'save', data);
        closeModal('modalTipoPrestamo');
        event.target.reset();
        loadTipoPrestamo();
    } catch (error) {
        console.error('Error guardando tipo de préstamo:', error);
    }
}

async function loadAuditoria() {
    try {
        const response = await apiRequest('auditoria', 'list');
        const tbody = document.querySelector('#tableAuditoria tbody');
        tbody.innerHTML = '';

        response.data.forEach(item => {
            const row = document.createElement('tr');
            const fechaFormateada = new Date(item.fecha_cambio).toLocaleString('es-ES');

            row.innerHTML = `
                <td>${fechaFormateada}</td>
                <td>${item.tabla_afectada}</td>
                <td>${item.id_registro}</td>
                <td><span class="status-badge ${item.tipo_cambio.toLowerCase()}">${item.tipo_cambio}</span></td>
                <td>${item.usuario}</td>
                <td class="table-actions">
                    <button class="action-btn view" onclick="viewAuditoriaDetail(${item.id_auditoria})">
                        Ver Detalle
                    </button>
                </td>
            `;
            tbody.appendChild(row);
        });
    } catch (error) {
        console.error('Error cargando auditoría:', error);
    }
}

async function filtrarAuditoria() {
    try {
        const tabla = document.getElementById('filterTabla').value;
        const tipo = document.getElementById('filterTipo').value;
        const fecha = document.getElementById('filterFecha').value;

        const response = await apiRequest('auditoria', 'list', { tabla, tipo, fecha });
        const tbody = document.querySelector('#tableAuditoria tbody');
        tbody.innerHTML = '';

        response.data.forEach(item => {
            const row = document.createElement('tr');
            const fechaFormateada = new Date(item.fecha_cambio).toLocaleString('es-ES');

            row.innerHTML = `
                <td>${fechaFormateada}</td>
                <td>${item.tabla_afectada}</td>
                <td>${item.id_registro}</td>
                <td><span class="status-badge ${item.tipo_cambio.toLowerCase()}">${item.tipo_cambio}</span></td>
                <td>${item.usuario}</td>
                <td class="table-actions">
                    <button class="action-btn view" onclick="viewAuditoriaDetail(${item.id_auditoria})">
                        Ver Detalle
                    </button>
                </td>
            `;
            tbody.appendChild(row);
        });
    } catch (error) {
        console.error('Error filtrando auditoría:', error);
    }
}

async function viewAuditoriaDetail(id) {
    try {
        const response = await apiRequest('auditoria', 'detail', { id });
        const item = response.data;

        let content = `
            <div class="audit-detail">
                <h4>Información del Cambio</h4>
                <p><strong>Tabla:</strong> ${item.tabla_afectada}</p>
                <p><strong>ID Registro:</strong> ${item.id_registro}</p>
                <p><strong>Tipo de Cambio:</strong> ${item.tipo_cambio}</p>
                <p><strong>Usuario:</strong> ${item.usuario}</p>
                <p><strong>Fecha:</strong> ${new Date(item.fecha_cambio).toLocaleString('es-ES')}</p>
        `;

        if (item.valores_anteriores) {
            const anteriores = JSON.parse(item.valores_anteriores);
            content += `
                <h4>Valores Anteriores</h4>
                <pre>${JSON.stringify(anteriores, null, 2)}</pre>
            `;
        }

        if (item.valores_nuevos) {
            const nuevos = JSON.parse(item.valores_nuevos);
            content += `
                <h4>Valores Nuevos</h4>
                <pre>${JSON.stringify(nuevos, null, 2)}</pre>
            `;
        }

        content += '</div>';
        const modal = document.createElement('div');
        modal.className = 'modal show';
        modal.id = 'modalAuditoriaDetail';
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Detalle de Auditoría</h3>
                    <span class="close" onclick="closeModal('modalAuditoriaDetail')">&times;</span>
                </div>
                <div style="padding: 24px;">
                    ${content}
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal('modalAuditoriaDetail')">Cerrar</button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
    } catch (error) {
        console.error('Error viendo detalle de auditoría:', error);
    }
}

async function loadEmpleadosSelect() {
    try {
        const response = await apiRequest('caja', 'empleados');
        const select = document.getElementById('empleadoCaja');
        select.innerHTML = '<option value="">Seleccionar empleado...</option>';

        response.data.forEach(empleado => {
            const option = document.createElement('option');
            option.value = empleado.id_empleado;
            option.textContent = empleado.nombre_empleado;
            select.appendChild(option);
        });
    } catch (error) {
        console.error('Error cargando empleados:', error);
    }
}

async function loadCajasAbiertas() {
    try {
        const response = await apiRequest('caja', 'list');
        const select = document.getElementById('cajaMovimientoFondo');
        select.innerHTML = '<option value="">Seleccionar caja...</option>';

        response.data.filter(caja => caja.estado_caja === 'Abierta').forEach(caja => {
            const option = document.createElement('option');
            option.value = caja.id_caja;
            option.textContent = `${caja.empleado} - $${parseFloat(caja.monto_asignado).toFixed(2)}`;
            select.appendChild(option);
        });
    } catch (error) {
        console.error('Error cargando cajas abiertas:', error);
    }
}

async function openCaja(event) {
    event.preventDefault();

    const formData = new FormData(event.target);
    const data = Object.fromEntries(formData);

    try {
        const result = await apiRequest('caja', 'open', data);
        if (result.fondos) {
            document.getElementById('carteraNormal').textContent = `$${parseFloat(result.fondos.cartera_normal).toFixed(2)}`;
            document.getElementById('totalFondos').textContent = `$${parseFloat(result.fondos.total_fondos).toFixed(2)}`;
            hasFondos = true;
            updateFondosDirectosUI();
        }
        closeModal('modalCaja');
        event.target.reset();
        loadCaja();
    } catch (error) {
        console.error('Error abriendo caja:', error);
    }
}

async function saveMovimientoFondo(event) {
    event.preventDefault();

    const formData = new FormData(event.target);
    const data = Object.fromEntries(formData);

    try {
        const result = await apiRequest('fondos', 'movimiento', data);
        closeModal('modalMovimientoFondo');
        event.target.reset();
        loadFondos();
        loadCaja();
    } catch (error) {
        console.error('Error registrando movimiento de fondo:', error);
    }
}

async function loadFondos() {
    try {
        const response = await apiRequest('fondos', 'list');

        if (response.fondos) {
            document.getElementById('carteraNormal').textContent = `$${parseFloat(response.fondos.cartera_normal).toFixed(2)}`;
            document.getElementById('carteraVencida').textContent = `$${parseFloat(response.fondos.cartera_vencida).toFixed(2)}`;
            document.getElementById('totalFondos').textContent = `$${parseFloat(response.fondos.total_fondos).toFixed(2)}`;
            hasFondos = true;
        } else {
            hasFondos = false;
        }

        if (response.movimientos) {
            const tbody = document.querySelector('#tableFondos tbody');
            tbody.innerHTML = '';

            response.movimientos.forEach(item => {
                const row = document.createElement('tr');
                const fechaFormateada = new Date(item.fecha_movimiento).toLocaleString('es-ES');

                row.innerHTML = `
                    <td>${fechaFormateada}</td>
                    <td><span class="status-badge ${item.tipo_movimiento}">${item.tipo_movimiento}</span></td>
                    <td>$${parseFloat(item.monto).toFixed(2)}</td>
                    <td>${item.empleado}</td>
                `;
                tbody.appendChild(row);
            });
        }
        updateFondosDirectosUI();
    } catch (error) {
        console.error('Error cargando fondos:', error);
    }
}

async function loadCaja() {
    try {
        const response = await apiRequest('caja', 'list');
        const tbody = document.querySelector('#tableCaja tbody');
        tbody.innerHTML = '';

        response.data.forEach(item => {
            const row = document.createElement('tr');
            const fechaApertura = new Date(item.fecha_apertura).toLocaleString('es-ES');

            row.innerHTML = `
                <td>${item.empleado}</td>
                <td>$${parseFloat(item.monto_asignado).toFixed(2)}</td>
                <td>${fechaApertura}</td>
                <td><span class="status-badge ${item.estado_caja.toLowerCase()}">${item.estado_caja}</span></td>
                <td class="table-actions">
                    ${item.estado_caja === 'Abierta' ?
                    `<button class="action-btn delete" onclick="closeCaja(${item.id_caja})">Cerrar</button>` :
                    '<span class="text-muted">Cerrada</span>'
                }
                </td>
            `;
            tbody.appendChild(row);
        });
    } catch (error) {
        console.error('Error cargando cajas:', error);
    }
}

async function closeCaja(id) {
    if (!confirm('¿Está seguro de que desea cerrar esta caja?')) return;

    try {
        await apiRequest('caja', 'close', { id });
        loadCaja();
    } catch (error) {
        console.error('Error cerrando caja:', error);
    }
}

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('show');
        if (modalId === 'modalCaja') {
            loadEmpleadosSelect();
        } else if (modalId === 'modalMovimientoFondo') {
            loadCajasAbiertas();
        }

        const form = modal.querySelector('form');
        if (form) {
            const hiddenField = form.querySelector('input[type="hidden"]');
            if (!hiddenField || !hiddenField.value) {
                form.reset();
                const title = modal.querySelector('.modal-header h3');
                if (title) {
                    title.textContent = title.textContent.replace('Editar', 'Agregar');
                }
            }
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const formFondos = document.getElementById('formFondosDirectos');
    if (formFondos) {
        formFondos.addEventListener('submit', saveFondosDirectos);
    }
    const btnRetiro = document.getElementById('btnRetirarFondos');
    if (btnRetiro) {
        btnRetiro.addEventListener('click', retirarFondosDirectos);
    }
});

function updateFondosDirectosUI() {
    const btn = document.getElementById('btnFondosDirectos');
    const hint = document.getElementById('hintFondosDirectos');
    if (!btn || !hint) return;
    if (hasFondos) {
        btn.textContent = 'Aportar Fondos';
        hint.textContent = 'Realice aportes adicionales al capital disponible.';
    } else {
        btn.textContent = 'Inicializar Fondos';
        hint.textContent = 'Registre el capital inicial del negocio.';
    }
}

async function saveFondosDirectos(event) {
    event.preventDefault();
    const montoInput = document.getElementById('montoFondosDirectos');
    if (!montoInput) return;
    const monto = montoInput.value;
    if (!monto || parseFloat(monto) <= 0) {
        return;
    }
    const action = hasFondos ? 'aporte_directo' : 'initialize';
    try {
        const payload = hasFondos ? { monto } : { monto_inicial: monto };
        const result = await apiRequest('fondos', action, payload);
        loadFondos();
        event.target.reset();
    } catch (error) {
        console.error('Error guardando fondos directos:', error);
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('show');
        if (modalId === 'modalAuditoriaDetail') {
            modal.remove();
        } else {
            const form = modal.querySelector('form');
            if (form) {
                form.reset();
                const hiddenId = form.querySelector('input[type="hidden"]');
                if (hiddenId) {
                    hiddenId.value = '';
                }
                const title = modal.querySelector('.modal-header h3');
                if (title) {
                    title.textContent = title.textContent.replace('Editar', 'Agregar');
                }
            }
        }
    }
}

async function loadPlantillasNotificacion() {
    try {
        const response = await apiRequest('plantilla_notificacion', 'list');
        const tbody = document.querySelector('#tablePlantillaNotificacion tbody');
        tbody.innerHTML = '';

        response.data.forEach(item => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${item.tipo}</td>
                <td>${item.nombre}</td>
                <td class="table-actions">
                    <button class="action-btn edit" onclick="editPlantillaNotificacion(${item.id})">
                        Editar
                    </button>
                    <button class="action-btn delete" onclick="deletePlantillaNotificacion(${item.id})">
                        Eliminar
                    </button>
                </td>
            `;
            tbody.appendChild(row);
        });
    } catch (error) {
        console.error('Error cargando plantillas de notificación:', error);
    }
}

async function editPlantillaNotificacion(id) {
    try {
        const response = await apiRequest('plantilla_notificacion', 'list');
        const item = response.data.find(p => p.id == id);

        if (item) {
            document.getElementById('idPlantillaNotificacion').value = item.id;
            document.getElementById('nombrePlantilla').value = item.nombre;
            document.getElementById('descripcionPlantilla').value = item.descripcion;
            document.getElementById('tipoPlantilla').value = item.tipo;
            document.getElementById('titlePlantillaNotificacion').textContent = 'Editar Plantilla de Notificación';
            openModal('modalPlantillaNotificacion');
        }
    } catch (error) {
        console.error('Error editando plantilla de notificación:', error);
    }
}

async function deletePlantillaNotificacion(id) {
    if (!confirm('¿Está seguro de que desea eliminar esta plantilla de notificación?')) return;

    try {
        await apiRequest('plantilla_notificacion', 'delete', { id });
        loadPlantillasNotificacion();
    } catch (error) {
        console.error('Error eliminando plantilla de notificación:', error);
    }
}

async function savePlantillaNotificacion(event) {
    event.preventDefault();

    const formData = new FormData(event.target);
    const data = Object.fromEntries(formData);

    try {
        await apiRequest('plantilla_notificacion', 'save', data);
        closeModal('modalPlantillaNotificacion');
        event.target.reset();
        loadPlantillasNotificacion();
    } catch (error) {
        console.error('Error guardando plantilla de notificación:', error);
    }
}

async function retirarFondosDirectos(e) {
    e.preventDefault();
    const montoInput = document.getElementById('montoFondosDirectos');
    if (!montoInput) return;
    const monto = montoInput.value;
    if (!monto || parseFloat(monto) <= 0) {
        return;
    }
    try {
        await apiRequest('fondos', 'retiro_directo', { monto });
        loadFondos();
        const form = document.getElementById('formFondosDirectos');
        if (form) form.reset();
    } catch (error) {
        console.error('Error realizando retiro:', error);
    }
}

async function loadPoliticaCancelacion() {
    try {
        const response = await apiRequest('politica_cancelacion', 'list');
        const tbody = document.querySelector('#tablePoliticaCancelacion tbody');
        if (!tbody) return;
        tbody.innerHTML = '';

        response.data.forEach(item => {
            const row = document.createElement('tr');
            const estado = (item.estado || '').toString();
            const estadoText = estado.toUpperCase();
            const estadoClass = estado.toLowerCase();

            row.innerHTML = `
                <td>
                    <strong>${item.nombre_politica}</strong><br>
                </td>
                <td>${parseFloat(item.porcentaje_penalidad).toFixed(2)}%</td>
                <td>${item.dias_minimos_cancelacion}</td>
                <td>
                    <span class="status-badge ${estadoClass}">${estadoText}</span>
                </td>
                <td class="table-actions">
                    <button class="action-btn edit" onclick="editPoliticaCancelacion(${item.id_politica_cancelacion})">Editar</button>
                    <button class="action-btn delete" onclick="deletePoliticaCancelacion(${item.id_politica_cancelacion})">Eliminar</button>
                </td>
            `;
            tbody.appendChild(row);
        });
    } catch (error) {
        console.error('Error cargando políticas:', error);
    }
}

async function editPoliticaCancelacion(id) {
    try {
        const response = await apiRequest('politica_cancelacion', 'list');
        const item = response.data.find(p => p.id_politica_cancelacion == id);

        if (item) {
            document.getElementById('idPoliticaCancelacion').value = item.id_politica_cancelacion;
            document.getElementById('nombrePoliticaCancelacion').value = item.nombre_politica;
            document.getElementById('descripcionPoliticaCancelacion').value = item.descripcion || '';
            document.getElementById('porcentajePoliticaCancelacion').value = item.porcentaje_penalidad;
            document.getElementById('diasPoliticaCancelacion').value = item.dias_minimos_cancelacion;
            document.getElementById('estadoPoliticaCancelacion').value = item.estado;

            document.getElementById('titlePoliticaCancelacion').textContent = 'Editar Política de Cancelación';
            openModal('modalPoliticaCancelacion');
        }

    } catch (error) {
        console.error('Error editando politica:', error);
    }
}

async function savePoliticaCancelacion(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);

    try {
        await apiRequest('politica_cancelacion', 'save', data);
        closeModal('modalPoliticaCancelacion');
        form.reset();
        loadPoliticaCancelacion();
    } catch (error) {
        alert('Error guardando política de cancelación');
        console.error('Error guardando política de cancelación:', error);
    }
}

async function deletePoliticaCancelacion(id) {
    if (!confirm('¿Está seguro de que desea eliminar esta política de cancelación? Esta acción también la eliminará de los tipos de préstamo asociados.')) return;

    try {
        await apiRequest('politica_cancelacion', 'delete', { id });
        loadPoliticaCancelacion();
    } catch (error) {
        console.error('Error eliminando política:', error);
    }
}