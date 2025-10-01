// Gestión de Clientes - JavaScript
// Manejo de formularios, búsquedas y operaciones CRUD

// Variables globales
let modal, viewModal, clienteForm;
let isEditMode = false;
let currentClientId = null;

// Funciones principales
function nuevoCliente() {
    isEditMode = false;
    currentClientId = null;

    const modalTitle = document.getElementById('modal-title');
    const saveBtn = document.getElementById('save-btn');

    if (modalTitle) modalTitle.textContent = '👤 Nuevo cliente';
    if (saveBtn) saveBtn.textContent = 'Agregar cliente';

    limpiarFormulario();
    mostrarModal();
}

function modificarCliente(id) {
    isEditMode = true;
    currentClientId = id;

    // Cambiar título del modal
    const modalTitle = document.getElementById('modal-title');
    const saveBtn = document.getElementById('save-btn');

    if (modalTitle) modalTitle.textContent = '✏️ Modificar cliente';
    if (saveBtn) saveBtn.textContent = 'Actualizar cliente';

    // Cargar datos del cliente
    cargarDatosCliente(id);

    // Mostrar modal
    mostrarModal();
}

function verDetallesCliente(id) {
    fetch(`../app/consulta_clientes.php?action=get_client&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const client = data.client;

                // Llenar los campos del modal de vista
                document.getElementById('detail-nombre').textContent = client.nombre || '-';
                document.getElementById('detail-apellido').textContent = client.apellido || '-';
                document.getElementById('detail-edad').textContent = client.edad || '-';
                document.getElementById('detail-cedula').textContent = client.documento_identidad || '-';
                document.getElementById('detail-telefono').textContent = client.telefono || '-';
                document.getElementById('detail-email').textContent = client.email || '-';
                document.getElementById('detail-ingresos').textContent = client.ingresos_mensuales ? `$${parseFloat(client.ingresos_mensuales).toFixed(2)}` : '-';
                document.getElementById('detail-egresos').textContent = client.egresos_mensuales ? `$${parseFloat(client.egresos_mensuales).toFixed(2)}` : '-';
                document.getElementById('detail-ocupacion').textContent = client.ocupacion || '-';

                // Calcular capacidad de pago
                const capacidadPago = client.ingresos_mensuales && client.egresos_mensuales
                    ? parseFloat(client.ingresos_mensuales) - parseFloat(client.egresos_mensuales)
                    : 0;
                document.getElementById('detail-capacidad').textContent = `$${capacidadPago.toFixed(2)}`;

                // Mostrar modal de vista
                mostrarModalVista();
            } else {
                mostrarNotificacion('Error al cargar los detalles del cliente', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            mostrarNotificacion('Error de conexión al servidor', 'error');
        });
}

function limpiarBusqueda() {
    const searchInput = document.getElementById('entrada-busqueda');
    if (searchInput) {
        searchInput.value = '';
        const currentUrl = new URL(window.location);
        currentUrl.searchParams.delete('search');
        currentUrl.searchParams.set('page', '1');
        window.location.href = currentUrl.toString();
    }
}

// Funciones de modal globales
function mostrarModal() {
    if (!modal) {
        modal = document.getElementById('cliente-modal');
    }

    if (modal) {
        modal.classList.add('active');

        // Enfocar primer campo después de la animación
        setTimeout(() => {
            const primerCampo = modal.querySelector('.campo-entrada');
            if (primerCampo) {
                primerCampo.focus();
            }
        }, 100);
    }
}

function cerrarModal() {
    if (!modal) {
        modal = document.getElementById('cliente-modal');
    }

    if (modal) {
        modal.classList.remove('active');
        setTimeout(() => {
            limpiarFormulario();
        }, 300);
    }
}

function mostrarModalVista() {
    if (!viewModal) {
        viewModal = document.getElementById('view-modal');
    }

    if (viewModal) {
        viewModal.classList.add('active');
    }
}

function cerrarModalVista() {
    if (!viewModal) {
        viewModal = document.getElementById('view-modal');
    }

    if (viewModal) {
        viewModal.classList.remove('active');
    }
}

function limpiarFormulario() {
    if (!clienteForm) {
        clienteForm = document.getElementById('cliente-form');
    }

    if (clienteForm) {
        clienteForm.reset();

        // Limpiar errores
        const errorMessages = clienteForm.querySelectorAll('.mensaje-error');
        errorMessages.forEach(msg => msg.remove());

        const fieldErrors = clienteForm.querySelectorAll('.campo-error');
        fieldErrors.forEach(field => field.classList.remove('campo-error'));

        // Resetear valores por defecto
        const estadoSelect = document.getElementById('estado');
        if (estadoSelect) {
            estadoSelect.value = 'Activo';
        }
    }
}

function cargarDatosCliente(id) {
    fetch(`../app/consulta_clientes.php?action=get_client&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const client = data.client;

                // Llenar el formulario con los datos del cliente
                document.getElementById('id_cliente').value = client.id_cliente;
                document.getElementById('nombre').value = client.nombre || '';
                document.getElementById('apellido').value = client.apellido || '';
                document.getElementById('edad').value = client.edad || '';
                document.getElementById('cedula').value = client.documento_identidad || '';
                document.getElementById('telefono').value = client.telefono || '';
                document.getElementById('email').value = client.email || '';
                document.getElementById('direccion').value = client.direccion || '';
                document.getElementById('ingresos').value = client.ingresos_mensuales || '';
                document.getElementById('egresos').value = client.egresos_mensuales || '';
                document.getElementById('ocupacion').value = client.ocupacion || '';
                document.getElementById('estado').value = client.estado || 'Activo';
            } else {
                mostrarNotificacion('Error al cargar los datos del cliente', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            mostrarNotificacion('Error de conexión al servidor', 'error');
        });
}

function mostrarNotificacion(mensaje, tipo = 'info') {
    // Remover notificaciones existentes
    const notificacionExistente = document.querySelector('.notificacion');
    if (notificacionExistente) {
        notificacionExistente.remove();
    }

    // Crear nueva notificación
    const notificacion = document.createElement('div');
    notificacion.className = `notificacion notificacion-${tipo}`;

    const iconos = {
        success: '✓',
        error: '✗',
        warning: '⚠',
        info: 'ℹ'
    };

    notificacion.innerHTML = `
        <div class="notificacion-contenido">
            <span class="notificacion-icono">${iconos[tipo] || iconos.info}</span>
            <span class="notificacion-mensaje">${mensaje}</span>
            <button class="notificacion-cerrar" onclick="this.parentElement.parentElement.remove()">×</button>
        </div>
    `;

    // Agregar al body
    document.body.appendChild(notificacion);

    // Mostrar con animación
    setTimeout(() => {
        notificacion.classList.add('show');
    }, 10);

    // Auto-remover después de 5 segundos
    setTimeout(() => {
        if (notificacion.parentNode) {
            notificacion.classList.remove('show');
            setTimeout(() => {
                if (notificacion.parentNode) {
                    notificacion.remove();
                }
            }, 300);
        }
    }, 5000);
}

// Función de prueba global
function testModal() {
    console.log('Test modal function called');
    const testModal = document.getElementById('cliente-modal');
    console.log('Modal element:', testModal);
    if (testModal) {
        testModal.classList.add('active');
        console.log('Modal should be visible now');
    } else {
        console.error('Modal element not found');
    }
}

document.addEventListener('DOMContentLoaded', function () {
    // Elementos del DOM
    const modal = document.getElementById('cliente-modal');
    const viewModal = document.getElementById('view-modal');
    const clienteForm = document.getElementById('cliente-form');
    const btnAgregar = document.getElementById('btn-agregar');
    const btnBuscar = document.getElementById('btn-buscar');
    const searchInput = document.getElementById('entrada-busqueda');
    const searchForm = document.querySelector('.entrada-busqueda');

    // Botones de cerrar modales
    const closeModal = document.getElementById('close-modal');
    const cancelBtn = document.getElementById('cancel-btn');
    const closeViewModal = document.getElementById('close-view-modal');
    const closeViewDetails = document.getElementById('close-view-details');

    // Estado de la aplicación
    let isEditMode = false;
    let currentClientId = null;

    // Inicialización
    init();

    function init() {
        setupEventListeners();
        setupFormValidation();

        // Debug: verificar elementos del DOM
        console.log('Sistema de gestión de clientes inicializado');
        console.log('Modal cliente:', modal ? 'Encontrado' : 'NO ENCONTRADO');
        console.log('Modal vista:', viewModal ? 'Encontrado' : 'NO ENCONTRADO');
        console.log('Botón agregar:', btnAgregar ? 'Encontrado' : 'NO ENCONTRADO');
        console.log('Formulario:', clienteForm ? 'Encontrado' : 'NO ENCONTRADO');
    }

    // Configurar event listeners
    function setupEventListeners() {
        // Botón agregar cliente
        if (btnAgregar) {
            btnAgregar.addEventListener('click', nuevoCliente);
        }

        // Botón buscar
        if (btnBuscar) {
            btnBuscar.addEventListener('click', function (e) {
                e.preventDefault();
                realizarBusqueda();
            });
        }

        // Formulario de búsqueda
        if (searchForm) {
            searchForm.addEventListener('submit', function (e) {
                e.preventDefault();
                realizarBusqueda();
            });
        }

        // Input de búsqueda - búsqueda en tiempo real
        if (searchInput) {
            let timeoutId;
            searchInput.addEventListener('input', function () {
                clearTimeout(timeoutId);
                timeoutId = setTimeout(() => {
                    if (this.value.trim().length > 2 || this.value.trim().length === 0) {
                        realizarBusqueda();
                    }
                }, 500);
            });

            // Buscar al presionar Enter
            searchInput.addEventListener('keypress', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    realizarBusqueda();
                }
            });
        }

        // Cerrar modales
        [closeModal, cancelBtn].forEach(btn => {
            if (btn) {
                btn.addEventListener('click', cerrarModal);
            }
        });

        [closeViewModal, closeViewDetails].forEach(btn => {
            if (btn) {
                btn.addEventListener('click', cerrarModalVista);
            }
        });

        // Cerrar modal al hacer clic fuera
        window.addEventListener('click', function (e) {
            if (e.target === modal) {
                cerrarModal();
            }
            if (e.target === viewModal) {
                cerrarModalVista();
            }
        });

        // Formulario de cliente
        if (clienteForm) {
            clienteForm.addEventListener('submit', guardarCliente);
        }

        // Tecla ESC para cerrar modales
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                cerrarModal();
                cerrarModalVista();
            }
        });
    }

    // Configurar validación del formulario
    function setupFormValidation() {
        const inputs = document.querySelectorAll('#cliente-form .campo-entrada');

        inputs.forEach(input => {
            input.addEventListener('blur', function () {
                validarCampo(this);
            });

            input.addEventListener('input', function () {
                limpiarErrores(this);
            });
        });

        // Validación específica para campos numéricos
        const edadInput = document.getElementById('edad');
        const ingresosInput = document.getElementById('ingresos');
        const egresosInput = document.getElementById('egresos');

        if (edadInput) {
            edadInput.addEventListener('input', function () {
                this.value = this.value.replace(/[^0-9]/g, '');
                if (parseInt(this.value) > 80) this.value = '80';
                if (parseInt(this.value) < 18 && this.value.length >= 2) this.value = '18';
            });
        }

        [ingresosInput, egresosInput].forEach(input => {
            if (input) {
                input.addEventListener('input', function () {
                    // Permitir solo números y punto decimal
                    this.value = this.value.replace(/[^0-9.]/g, '');

                    // Evitar múltiples puntos decimales
                    const parts = this.value.split('.');
                    if (parts.length > 2) {
                        this.value = parts[0] + '.' + parts.slice(1).join('');
                    }
                });
            }
        });

        // Validación de cédula (solo números)
        const cedulaInput = document.getElementById('cedula');
        if (cedulaInput) {
            cedulaInput.addEventListener('input', function () {
                this.value = this.value.replace(/[^0-9]/g, '');
            });
        }

        // Validación de teléfono
        const telefonoInput = document.getElementById('telefono');
        if (telefonoInput) {
            telefonoInput.addEventListener('input', function () {
                this.value = this.value.replace(/[^0-9+\-\s()]/g, '');
            });
        }
    }

    // Funciones principales
    function nuevoCliente() {
        console.log('nuevoCliente() llamada');
        console.log('Modal disponible:', modal ? 'SÍ' : 'NO');

        isEditMode = false;
        currentClientId = null;

        // Cambiar título del modal
        const modalTitle = document.getElementById('modal-title');
        const saveBtn = document.getElementById('save-btn');

        if (modalTitle) modalTitle.textContent = '👤 Nuevo cliente';
        if (saveBtn) saveBtn.textContent = 'Agregar cliente';

        // Limpiar formulario
        limpiarFormulario();

        // Mostrar modal
        console.log('Intentando mostrar modal...');
        mostrarModal();
    }

    function modificarCliente(id) {
        isEditMode = true;
        currentClientId = id;

        // Cambiar título del modal
        const modalTitle = document.getElementById('modal-title');
        const saveBtn = document.getElementById('save-btn');

        if (modalTitle) modalTitle.textContent = '✏️ Modificar cliente';
        if (saveBtn) saveBtn.textContent = 'Actualizar cliente';

        // Cargar datos del cliente
        cargarDatosCliente(id);

        // Mostrar modal
        mostrarModal();
    }

    function verDetallesCliente(id) {
        fetch(`../app/consulta_clientes.php?action=get_client&id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const client = data.client;

                    // Llenar los campos del modal de vista
                    document.getElementById('detail-nombre').textContent = client.nombre || '-';
                    document.getElementById('detail-apellido').textContent = client.apellido || '-';
                    document.getElementById('detail-edad').textContent = client.edad || '-';
                    document.getElementById('detail-cedula').textContent = client.documento_identidad || '-';
                    document.getElementById('detail-telefono').textContent = client.telefono || '-';
                    document.getElementById('detail-email').textContent = client.email || '-';
                    document.getElementById('detail-ingresos').textContent = client.ingresos_mensuales ? `$${parseFloat(client.ingresos_mensuales).toFixed(2)}` : '-';
                    document.getElementById('detail-egresos').textContent = client.egresos_mensuales ? `$${parseFloat(client.egresos_mensuales).toFixed(2)}` : '-';
                    document.getElementById('detail-ocupacion').textContent = client.ocupacion || '-';

                    // Calcular capacidad de pago
                    const capacidadPago = client.ingresos_mensuales && client.egresos_mensuales
                        ? parseFloat(client.ingresos_mensuales) - parseFloat(client.egresos_mensuales)
                        : 0;
                    document.getElementById('detail-capacidad').textContent = `$${capacidadPago.toFixed(2)}`;

                    // Mostrar modal de vista
                    mostrarModalVista();
                } else {
                    mostrarNotificacion('Error al cargar los detalles del cliente', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                mostrarNotificacion('Error de conexión al servidor', 'error');
            });
    }

    function cargarDatosCliente(id) {
        fetch(`../app/consulta_clientes.php?action=get_client&id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const client = data.client;

                    // Llenar el formulario con los datos del cliente
                    document.getElementById('id_cliente').value = client.id_cliente;
                    document.getElementById('nombre').value = client.nombre || '';
                    document.getElementById('apellido').value = client.apellido || '';
                    document.getElementById('edad').value = client.edad || '';
                    document.getElementById('cedula').value = client.documento_identidad || '';
                    document.getElementById('telefono').value = client.telefono || '';
                    document.getElementById('email').value = client.email || '';
                    document.getElementById('direccion').value = client.direccion || '';
                    document.getElementById('ingresos').value = client.ingresos_mensuales || '';
                    document.getElementById('egresos').value = client.egresos_mensuales || '';
                    document.getElementById('ocupacion').value = client.ocupacion || '';
                    document.getElementById('estado').value = client.estado || 'Activo';
                } else {
                    mostrarNotificacion('Error al cargar los datos del cliente', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                mostrarNotificacion('Error de conexión al servidor', 'error');
            });
    }

    function guardarCliente(e) {
        e.preventDefault();

        // Validar formulario
        if (!validarFormulario()) {
            return;
        }

        // Recopilar datos del formulario
        const formData = new FormData(clienteForm);

        // Agregar acción
        formData.append('action', isEditMode ? 'update_client' : 'add_client');

        // Deshabilitar botón de envío
        const saveBtn = document.getElementById('save-btn');
        const originalText = saveBtn.textContent;
        saveBtn.disabled = true;
        saveBtn.textContent = 'Guardando...';

        // Enviar datos
        fetch('../app/consulta_clientes.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarNotificacion(
                        isEditMode ? 'Cliente actualizado correctamente' : 'Cliente agregado correctamente',
                        'success'
                    );
                    cerrarModal();
                    // Recargar la página para mostrar los cambios
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    mostrarNotificacion(data.message || 'Error al guardar el cliente', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                mostrarNotificacion('Error de conexión al servidor', 'error');
            })
            .finally(() => {
                // Rehabilitar botón
                saveBtn.disabled = false;
                saveBtn.textContent = originalText;
            });
    }

    function realizarBusqueda() {
        const searchTerm = searchInput.value.trim();
        const currentUrl = new URL(window.location);

        if (searchTerm) {
            currentUrl.searchParams.set('search', searchTerm);
        } else {
            currentUrl.searchParams.delete('search');
        }

        // Resetear página a 1 al buscar
        currentUrl.searchParams.set('page', '1');

        window.location.href = currentUrl.toString();
    }



    // Funciones de validación
    function validarFormulario() {
        const campos = [
            { id: 'nombre', nombre: 'Nombre', required: true },
            { id: 'apellido', nombre: 'Apellido', required: true },
            { id: 'edad', nombre: 'Edad', required: true, min: 18, max: 80 },
            { id: 'cedula', nombre: 'Cédula', required: true, minLength: 8 },
            { id: 'telefono', nombre: 'Teléfono', required: true, minLength: 10 },
            { id: 'email', nombre: 'Email', required: true, pattern: 'email' },
            { id: 'direccion', nombre: 'Dirección', required: true },
            { id: 'ingresos', nombre: 'Ingresos mensuales', required: true, min: 0 },
            { id: 'egresos', nombre: 'Egresos mensuales', required: true, min: 0 },
            { id: 'ocupacion', nombre: 'Ocupación', required: true }
        ];

        let esValido = true;

        campos.forEach(campo => {
            const elemento = document.getElementById(campo.id);
            if (elemento && !validarCampo(elemento, campo)) {
                esValido = false;
            }
        });

        // Validación adicional: ingresos > egresos
        const ingresos = parseFloat(document.getElementById('ingresos').value) || 0;
        const egresos = parseFloat(document.getElementById('egresos').value) || 0;

        if (ingresos <= egresos) {
            mostrarErrorEnCampo(document.getElementById('egresos'), 'Los egresos no pueden ser mayores o iguales a los ingresos');
            esValido = false;
        }

        return esValido;
    }

    function validarCampo(elemento, config = null) {
        const valor = elemento.value.trim();

        // Limpiar errores previos
        limpiarErrores(elemento);

        // Si no hay configuración, crear una básica
        if (!config) {
            config = {
                nombre: elemento.getAttribute('placeholder') || elemento.name,
                required: elemento.hasAttribute('required')
            };
        }

        // Validar campo requerido
        if (config.required && !valor) {
            mostrarErrorEnCampo(elemento, `${config.nombre} es obligatorio`);
            return false;
        }

        if (valor) {
            // Validar longitud mínima
            if (config.minLength && valor.length < config.minLength) {
                mostrarErrorEnCampo(elemento, `${config.nombre} debe tener al menos ${config.minLength} caracteres`);
                return false;
            }

            // Validar rango numérico
            const numeroValor = parseFloat(valor);
            if (config.min !== undefined && numeroValor < config.min) {
                mostrarErrorEnCampo(elemento, `${config.nombre} debe ser mayor o igual a ${config.min}`);
                return false;
            }

            if (config.max !== undefined && numeroValor > config.max) {
                mostrarErrorEnCampo(elemento, `${config.nombre} debe ser menor o igual a ${config.max}`);
                return false;
            }

            // Validar email
            if (config.pattern === 'email') {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(valor)) {
                    mostrarErrorEnCampo(elemento, 'Por favor ingrese un email válido');
                    return false;
                }
            }
        }

        return true;
    }

    function mostrarErrorEnCampo(elemento, mensaje) {
        elemento.classList.add('campo-error');

        // Remover mensaje de error existente
        const mensajeExistente = elemento.parentNode.querySelector('.mensaje-error');
        if (mensajeExistente) {
            mensajeExistente.remove();
        }

        // Crear nuevo mensaje de error
        const mensajeError = document.createElement('div');
        mensajeError.className = 'mensaje-error';
        mensajeError.textContent = mensaje;

        elemento.parentNode.appendChild(mensajeError);
    }

    function limpiarErrores(elemento) {
        elemento.classList.remove('campo-error');
        const mensajeError = elemento.parentNode.querySelector('.mensaje-error');
        if (mensajeError) {
            mensajeError.remove();
        }
    }

    // Sistema de notificaciones
    function mostrarNotificacion(mensaje, tipo = 'info') {
        // Remover notificaciones existentes
        const notificacionExistente = document.querySelector('.notificacion');
        if (notificacionExistente) {
            notificacionExistente.remove();
        }

        // Crear nueva notificación
        const notificacion = document.createElement('div');
        notificacion.className = `notificacion notificacion-${tipo}`;

        const iconos = {
            success: '✓',
            error: '✗',
            warning: '⚠',
            info: 'ℹ'
        };

        notificacion.innerHTML = `
            <div class="notificacion-contenido">
                <span class="notificacion-icono">${iconos[tipo] || iconos.info}</span>
                <span class="notificacion-mensaje">${mensaje}</span>
                <button class="notificacion-cerrar" onclick="this.parentElement.parentElement.remove()">×</button>
            </div>
        `;

        // Agregar al body
        document.body.appendChild(notificacion);

        // Mostrar con animación
        setTimeout(() => {
            notificacion.classList.add('show');
        }, 10);

        // Auto-remover después de 5 segundos
        setTimeout(() => {
            if (notificacion.parentNode) {
                notificacion.classList.remove('show');
                setTimeout(() => {
                    if (notificacion.parentNode) {
                        notificacion.remove();
                    }
                }, 300);
            }
        }, 5000);
    }

    console.log('Sistema de gestión de clientes completamente inicializado');
});