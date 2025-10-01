// Script para Control de Préstamos - Sistema de gestión de pestañas

document.addEventListener('DOMContentLoaded', function () {
    // Asegurar que el estado inicial sea correcto
    ensureInitialState();
    initializeTabs();
    initializeOtherFeatures();
});

// Asegurar que el estado inicial sea correcto
function ensureInitialState() {
    // Asegurar que la primera pestaña esté activa
    const firstTab = document.querySelector('.boton-pestana[data-tab="generar"]');
    const firstSection = document.getElementById('generar');

    if (firstTab) {
        firstTab.classList.add('activo');
    }

    if (firstSection) {
        firstSection.style.display = 'block';
        firstSection.style.visibility = 'visible';
        firstSection.style.opacity = '1';
    }

    // Asegurar que las otras secciones estén ocultas
    const otherSections = ['lista', 'desembolso'];
    otherSections.forEach(sectionId => {
        const section = document.getElementById(sectionId);
        if (section) {
            section.style.display = 'none';
        }
    });

    console.log('Estado inicial de pestañas establecido');
}

// Inicializar funcionalidad de pestañas
function initializeTabs() {
    const tabButtons = document.querySelectorAll('.boton-pestana');
    const tabSections = document.querySelectorAll('.seccion-formulario');

    tabButtons.forEach(button => {
        button.addEventListener('click', function () {
            const targetTab = this.getAttribute('data-tab');
            switchTab(targetTab);
        });
    });
}

// Cambiar entre pestañas
function switchTab(targetTab) {
    console.log(`Cambiando a pestaña: ${targetTab}`);

    // Remover clase activa de todos los botones
    const tabButtons = document.querySelectorAll('.boton-pestana');
    tabButtons.forEach(button => {
        button.classList.remove('activo');
    });

    // Ocultar todas las secciones de forma segura
    const tabSections = document.querySelectorAll('.seccion-formulario');
    tabSections.forEach(section => {
        section.style.display = 'none';
        section.style.visibility = 'hidden';
        section.style.opacity = '0';
    });

    // Activar el botón seleccionado
    const activeButton = document.querySelector(`[data-tab="${targetTab}"]`);
    if (activeButton) {
        activeButton.classList.add('activo');
    }

    // Mostrar la sección correspondiente de forma segura
    const activeSection = document.getElementById(targetTab);
    if (activeSection) {
        activeSection.style.display = 'block';
        activeSection.style.visibility = 'visible';
        activeSection.style.opacity = '1';

        // Asegurar que no haya animaciones conflictivas
        activeSection.style.animation = 'none';
        activeSection.style.transition = 'none';

        console.log(`Sección ${targetTab} mostrada correctamente`);
    }

    // Ejecutar acciones específicas según la pestaña
    switch (targetTab) {
        case 'generar':
            console.log('Pestaña Solicitar Préstamo activada');
            break;
        case 'lista':
            console.log('Pestaña Lista de Préstamos activada');
            break;
        case 'desembolso':
            console.log('Pestaña Desembolso activada');
            break;
    }
}

// Inicializar otras características
function initializeOtherFeatures() {
    // Funcionalidad para limpiar búsqueda
    window.limpiarBusqueda = function () {
        const searchInput = document.getElementById('search-input');
        if (searchInput) {
            searchInput.value = '';
            // Redirigir para limpiar la URL
            window.location.href = window.location.pathname;
        }
    };

    // Funcionalidad para modal de nuevo cliente
    initializeClientModal();

    // Funcionalidad para tipos de préstamo
    initializeLoanTypes();

    // Funcionalidad para formulario de desembolso
    initializeDesembolsoForm();

    // Funcionalidad para verificar préstamo
    initializeVerificarPrestamo();
}

// Inicializar selección de tipos de préstamo
function initializeLoanTypes() {
    const loanTypeCards = document.querySelectorAll('.tarjeta-tipo-prestamo');

    loanTypeCards.forEach(card => {
        card.addEventListener('click', function () {
            // Remover selección previa
            loanTypeCards.forEach(c => c.classList.remove('seleccionado'));

            // Agregar selección a la tarjeta clickeada
            this.classList.add('seleccionado');

            const loanType = this.getAttribute('data-type');
            console.log(`Tipo de préstamo seleccionado: ${loanType}`);

            // Aquí puedes agregar lógica específica para cada tipo de préstamo
            handleLoanTypeSelection(loanType);
        });
    });
}

// Manejar selección de tipo de préstamo
function handleLoanTypeSelection(loanType) {
    switch (loanType) {
        case 'personal':
            console.log('Configurando formulario para préstamo personal');
            // Aquí puedes agregar lógica específica para préstamo personal
            break;
        case 'hipotecario':
            console.log('Configurando formulario para préstamo hipotecario');
            // Aquí puedes agregar lógica específica para préstamo hipotecario
            break;
    }
}

// Inicializar formulario de desembolso
function initializeDesembolsoForm() {
    const desembolsoForm = document.querySelector('.formulario-desembolso');

    if (desembolsoForm) {
        desembolsoForm.addEventListener('submit', function (e) {
            e.preventDefault();

            const prestamoId = document.getElementById('prestamo-id').value;
            const montoDesembolso = document.getElementById('monto-desembolso').value;
            const metodoPago = document.getElementById('metodo-pago').value;
            const observaciones = document.getElementById('observaciones').value;

            // Validar campos requeridos
            if (!prestamoId || !montoDesembolso || !metodoPago) {
                alert('⚠️ Por favor, complete todos los campos requeridos');
                return;
            }

            // Confirmación antes de procesar
            const confirmacion = confirm(`¿Confirma el desembolso de $${montoDesembolso} para el préstamo ${prestamoId}?`);

            if (confirmacion) {
                procesarDesembolso(prestamoId, montoDesembolso, metodoPago, observaciones);
            }
        });
    }
}

// Procesar desembolso
function procesarDesembolso(prestamoId, monto, metodo, observaciones) {
    // Mostrar indicador de carga
    const submitButton = document.querySelector('.formulario-desembolso .boton-exito');
    const originalText = submitButton.textContent;
    submitButton.textContent = '⏳ Procesando...';
    submitButton.disabled = true;

    // Simular procesamiento (aquí iría la llamada AJAX real)
    setTimeout(() => {
        // Restaurar botón
        submitButton.textContent = originalText;
        submitButton.disabled = false;

        // Mostrar resultado
        alert(`✅ Desembolso procesado exitosamente\n\nPréstamo: ${prestamoId}\nMonto: $${monto}\nMétodo: ${metodo}`);

        // Limpiar formulario
        document.querySelector('.formulario-desembolso').reset();

        console.log('Desembolso procesado:', {
            prestamoId,
            monto,
            metodo,
            observaciones,
            fecha: new Date().toISOString()
        });
    }, 2000);
}

// Inicializar funcionalidad de verificar préstamo
function initializeVerificarPrestamo() {
    const verificarButton = document.querySelector('.formulario-desembolso .boton-secundario');

    if (verificarButton) {
        verificarButton.addEventListener('click', function () {
            const prestamoId = document.getElementById('prestamo-id').value;

            if (!prestamoId) {
                alert('⚠️ Por favor, ingrese el ID del préstamo');
                return;
            }

            verificarPrestamo(prestamoId);
        });
    }
}

// Verificar préstamo
function verificarPrestamo(prestamoId) {
    // Mostrar indicador de carga
    const verificarButton = document.querySelector('.formulario-desembolso .boton-secundario');
    const originalText = verificarButton.textContent;
    verificarButton.textContent = '⏳ Verificando...';
    verificarButton.disabled = true;

    // Simular verificación (aquí iría la llamada AJAX real)
    setTimeout(() => {
        // Restaurar botón
        verificarButton.textContent = originalText;
        verificarButton.disabled = false;

        // Datos simulados del préstamo
        const prestamoData = {
            id: prestamoId,
            cliente: 'Juan Carlos Pérez',
            monto: '5000.00',
            estado: 'Aprobado',
            fechaAprobacion: '15/09/2025'
        };

        // Mostrar información del préstamo
        alert(`✅ Préstamo verificado\n\nID: ${prestamoData.id}\nCliente: ${prestamoData.cliente}\nMonto: $${prestamoData.monto}\nEstado: ${prestamoData.estado}\nFecha de Aprobación: ${prestamoData.fechaAprobacion}`);

        // Auto-completar el monto si el préstamo es válido
        document.getElementById('monto-desembolso').value = prestamoData.monto;

        console.log('Préstamo verificado:', prestamoData);
    }, 1500);
}

// Función para toggle del sidebar (si no está definida en otro archivo)
if (typeof toggleSidebar === 'undefined') {
    window.toggleSidebar = function () {
        const sidebar = document.getElementById('sidebar');
        if (sidebar) {
            sidebar.classList.toggle('activa');
        }
    };
}

// Funciones auxiliares para animaciones y efectos visuales
function addLoadingState(element) {
    element.classList.add('cargando');
    element.disabled = true;
}

function removeLoadingState(element) {
    element.classList.remove('cargando');
    element.disabled = false;
}

// Mostrar notificaciones personalizadas
function mostrarNotificacion(mensaje, tipo = 'info') {
    // Remover notificaciones existentes
    const existingNotifications = document.querySelectorAll('.notificacion');
    existingNotifications.forEach(notification => {
        notification.remove();
    });

    const notification = document.createElement('div');
    notification.className = `notificacion notificacion-${tipo}`;

    // Crear estructura de la notificación
    const iconMap = {
        'success': '✅',
        'error': '❌',
        'warning': '⚠️',
        'info': 'ℹ️'
    };

    const icon = iconMap[tipo] || 'ℹ️';

    notification.innerHTML = `
        <div class="notificacion-contenido">
            <span class="notificacion-icono">${icon}</span>
            <span class="notificacion-mensaje">${mensaje}</span>
            <button class="notificacion-cerrar" onclick="this.parentElement.parentElement.remove()">×</button>
        </div>
    `;

    document.body.appendChild(notification);

    // Mostrar con animación
    setTimeout(() => {
        notification.classList.add('visible');
    }, 100);

    // Remover después de 5 segundos
    setTimeout(() => {
        if (notification.parentElement) {
            notification.classList.remove('visible');
            setTimeout(() => {
                if (notification.parentElement) {
                    document.body.removeChild(notification);
                }
            }, 300);
        }
    }, 5000);
}

// Función de seguridad para mantener el estado de las pestañas
function maintainTabState() {
    const activeButton = document.querySelector('.boton-pestana.activo');
    if (activeButton) {
        const activeTab = activeButton.getAttribute('data-tab');
        const activeSection = document.getElementById(activeTab);

        if (activeSection) {
            // Asegurar que la sección activa permanezca visible
            if (activeSection.style.display !== 'block') {
                console.log(`Corrigiendo visibilidad de la sección ${activeTab}`);
                activeSection.style.display = 'block';
                activeSection.style.visibility = 'visible';
                activeSection.style.opacity = '1';
            }
        }
    }
}

// ===== FUNCIONALIDAD DEL MODAL DE NUEVO CLIENTE =====

// Inicializar modal de cliente
function initializeClientModal() {
    const btnAdd = document.getElementById('btn-add');
    const modal = document.getElementById('cliente-modal');
    const closeBtn = document.getElementById('close-modal');
    const cancelBtn = document.getElementById('cancel-btn');
    const clientForm = document.getElementById('cliente-form');

    // Abrir modal
    if (btnAdd) {
        btnAdd.addEventListener('click', function (e) {
            e.preventDefault();
            openClientModal();
        });
    }

    // Cerrar modal con X
    if (closeBtn) {
        closeBtn.addEventListener('click', function (e) {
            e.preventDefault();
            closeClientModal();
        });
    }

    // Cerrar modal con botón cancelar
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function (e) {
            e.preventDefault();
            closeClientModal();
        });
    }

    // Cerrar modal haciendo clic fuera del contenido
    if (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                closeClientModal();
            }
        });
    }

    // Cerrar modal con tecla Escape
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal && modal.style.display === 'block') {
            closeClientModal();
        }
    });

    // Manejar envío del formulario
    if (clientForm) {
        clientForm.addEventListener('submit', function (e) {
            e.preventDefault();
            submitClientForm();
        });
    }

    console.log('✅ Modal de cliente inicializado');
}

// Abrir modal de cliente
function openClientModal() {
    const modal = document.getElementById('cliente-modal');
    const form = document.getElementById('cliente-form');

    if (modal) {
        // Limpiar formulario
        if (form) {
            form.reset();
            clearValidationErrors();
        }

        // Mostrar modal
        modal.style.display = 'block';

        // Agregar clase activa para animaciones
        setTimeout(() => {
            modal.classList.add('active');
        }, 10);

        // Enfocar primer campo
        const firstInput = document.getElementById('nombre');
        if (firstInput) {
            setTimeout(() => {
                firstInput.focus();
            }, 300);
        }

        // Bloquear scroll del body
        document.body.style.overflow = 'hidden';

        console.log('Modal de cliente abierto');
    }
}

// Cerrar modal de cliente
function closeClientModal() {
    const modal = document.getElementById('cliente-modal');

    if (modal) {
        // Remover clase activa
        modal.classList.remove('active');

        // Ocultar modal después de la animación
        setTimeout(() => {
            modal.style.display = 'none';
        }, 300);

        // Restaurar scroll del body
        document.body.style.overflow = 'auto';

        console.log('Modal de cliente cerrado');
    }
}



// Enviar formulario de cliente
function submitClientForm() {
    const form = document.getElementById('cliente-form');
    const saveBtn = document.getElementById('save-btn');

    if (!form || !validateClientForm()) {
        return;
    }

    // Mostrar estado de carga
    const originalText = saveBtn.textContent;
    saveBtn.textContent = 'Guardando...';
    saveBtn.disabled = true;

    // Recopilar datos del formulario
    const formData = new FormData(form);
    const clientData = Object.fromEntries(formData.entries());

    // Simular guardado (aquí iría la llamada AJAX real)
    setTimeout(() => {
        try {
            console.log('Datos del cliente a guardar:', clientData);

            // Mostrar mensaje de éxito
            mostrarNotificacion('✅ Cliente agregado exitosamente', 'success');

            // Cerrar modal
            closeClientModal();

            // Aquí podrías actualizar la lista de clientes o recargar la página
            // location.reload(); // Descomenta si necesitas recargar

        } catch (error) {
            console.error('Error al guardar cliente:', error);
            mostrarNotificacion('❌ Error al guardar el cliente', 'error');
        } finally {
            // Restaurar botón
            saveBtn.textContent = originalText;
            saveBtn.disabled = false;
        }
    }, 2000);
}

// Validar formulario de cliente
function validateClientForm() {
    const requiredFields = [
        { id: 'nombre', name: 'Nombre' },
        { id: 'apellido', name: 'Apellido' },
        { id: 'edad', name: 'Edad' },
        { id: 'cedula', name: 'Cédula de identidad' },
        { id: 'telefono', name: 'Teléfono' },
        { id: 'email', name: 'Email' },
        { id: 'ocupacion', name: 'Ocupación' }
    ];

    let isValid = true;
    const errors = [];

    // Limpiar errores previos
    clearValidationErrors();

    // Validar campos requeridos
    requiredFields.forEach(field => {
        const input = document.getElementById(field.id);
        if (!input || !input.value.trim()) {
            markFieldAsError(input, `${field.name} es requerido`);
            errors.push(`${field.name} es requerido`);
            isValid = false;
        }
    });

    // Validaciones específicas
    const edad = document.getElementById('edad');
    if (edad && edad.value && (edad.value < 18 || edad.value > 80)) {
        markFieldAsError(edad, 'La edad debe estar entre 18 y 80 años');
        errors.push('La edad debe estar entre 18 y 80 años');
        isValid = false;
    }

    const email = document.getElementById('email');
    if (email && email.value && !isValidEmail(email.value)) {
        markFieldAsError(email, 'Ingrese un email válido');
        errors.push('Ingrese un email válido');
        isValid = false;
    }

    // Mostrar errores si los hay
    if (!isValid) {
        mostrarNotificacion(`❌ Por favor corrige los siguientes errores:\n• ${errors.join('\n• ')}`, 'error');
    }

    return isValid;
}

// Marcar campo con error
function markFieldAsError(input, message) {
    if (!input) return;

    input.classList.add('campo-error');

    // Agregar mensaje de error
    let errorMsg = input.parentNode.querySelector('.mensaje-error');
    if (!errorMsg) {
        errorMsg = document.createElement('div');
        errorMsg.className = 'mensaje-error';
        input.parentNode.appendChild(errorMsg);
    }
    errorMsg.textContent = message;
}

// Limpiar errores de validación
function clearValidationErrors() {
    const errorFields = document.querySelectorAll('.campo-error');
    const errorMessages = document.querySelectorAll('.mensaje-error');

    errorFields.forEach(field => field.classList.remove('campo-error'));
    errorMessages.forEach(msg => msg.remove());
}

// Validar email
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// Ejecutar verificación de estado cada 2 segundos (solo como medida de seguridad)
setInterval(maintainTabState, 2000);

console.log('✅ Control de Préstamos JavaScript cargado correctamente');