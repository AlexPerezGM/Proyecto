/**
 * CALENDARIO DASHBOARD - JAVASCRIPT
 * Funcionalidades específicas para el calendario del dashboard
 */

// Variables globales del calendario
let fechaActual = new Date();
let mesActual = fechaActual.getMonth();
let añoActual = fechaActual.getFullYear();

// Nombres de los meses y días en español
const nombresMeses = [
    'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
    'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
];

const nombresDias = ['DOM', 'LUN', 'MAR', 'MIÉ', 'JUE', 'VIE', 'SÁB'];

// Eventos y citas (simulados - en producción vendrían de la base de datos)
const eventosCalendario = {
    '2025-09-11': [
        { tipo: 'cita', titulo: 'Reunión con cliente', hora: '10:00' },
        { tipo: 'pago', titulo: 'Vencimiento préstamo #001', hora: '15:30' }
    ],
    '2025-09-22': [
        { tipo: 'revision', titulo: 'Revisión de cartera', hora: '09:00' },
        { tipo: 'pago', titulo: 'Pago programado', hora: '14:00' }
    ],
    '2025-09-23': [
        { tipo: 'cita', titulo: 'Evaluación crediticia', hora: '10:30' }
    ]
};

/**
 * Inicializa el calendario cuando se carga la página
 */
document.addEventListener('DOMContentLoaded', function () {
    inicializarCalendario();
});

/**
 * Inicializa el calendario con la fecha actual
 */
function inicializarCalendario() {
    const rejillaCalendario = document.getElementById('rejilla-calendario');
    const mesActualElemento = document.getElementById('mes-actual');

    if (!rejillaCalendario || !mesActualElemento) {
        console.warn('Elementos del calendario no encontrados');
        return;
    }

    generarCalendario();
    configurarEventosCalendario();
}

/**
 * Genera el calendario para el mes actual
 */
function generarCalendario() {
    const rejillaCalendario = document.getElementById('rejilla-calendario');
    const mesActualElemento = document.getElementById('mes-actual');

    if (!rejillaCalendario || !mesActualElemento) return;

    // Actualizar el título del mes
    mesActualElemento.textContent = `${añoActual} ${nombresMeses[mesActual]}`;

    // Limpiar el calendario
    rejillaCalendario.innerHTML = '';

    // Agregar cabecera de días
    nombresDias.forEach(dia => {
        const elementoDia = document.createElement('div');
        elementoDia.className = 'cabecera-dia';
        elementoDia.textContent = dia;
        rejillaCalendario.appendChild(elementoDia);
    });

    // Obtener información del mes
    const primerDia = new Date(añoActual, mesActual, 1);
    const ultimoDia = new Date(añoActual, mesActual + 1, 0);
    const primerDiaSemana = primerDia.getDay();
    const diasEnMes = ultimoDia.getDate();

    // Agregar días vacíos al inicio
    for (let i = 0; i < primerDiaSemana; i++) {
        const diaVacio = document.createElement('div');
        diaVacio.className = 'dia-calendario vacio';
        rejillaCalendario.appendChild(diaVacio);
    }

    // Agregar días del mes
    for (let dia = 1; dia <= diasEnMes; dia++) {
        const elementoDia = crearElementoDia(dia);
        rejillaCalendario.appendChild(elementoDia);
    }
}

/**
 * Crea un elemento de día para el calendario
 */
function crearElementoDia(numeroDia) {
    const elementoDia = document.createElement('div');
    elementoDia.className = 'dia-calendario';

    // Crear la fecha completa para comparaciones
    const fechaCompleta = `${añoActual}-${String(mesActual + 1).padStart(2, '0')}-${String(numeroDia).padStart(2, '0')}`;
    const fechaHoy = new Date();
    const esHoy = (
        numeroDia === fechaHoy.getDate() &&
        mesActual === fechaHoy.getMonth() &&
        añoActual === fechaHoy.getFullYear()
    );

    // Marcar el día actual
    if (esHoy) {
        elementoDia.classList.add('dia-actual');
    }

    // Crear el contenido del día
    const numeroDiaElemento = document.createElement('div');
    numeroDiaElemento.className = 'numero-dia';
    numeroDiaElemento.textContent = numeroDia;
    elementoDia.appendChild(numeroDiaElemento);

    // Agregar eventos si existen
    if (eventosCalendario[fechaCompleta]) {
        const contenedorEventos = document.createElement('div');
        contenedorEventos.className = 'eventos-dia';

        eventosCalendario[fechaCompleta].forEach(evento => {
            const puntoEvento = document.createElement('div');
            puntoEvento.className = `punto-evento ${evento.tipo}`;
            puntoEvento.title = `${evento.titulo}${evento.hora ? ` - ${evento.hora}` : ''}`;
            contenedorEventos.appendChild(puntoEvento);
        });

        elementoDia.appendChild(contenedorEventos);
        elementoDia.classList.add('tiene-eventos');
    }

    // Agregar evento de click
    elementoDia.addEventListener('click', function () {
        mostrarDetallesDia(numeroDia, fechaCompleta);
    });

    // Efectos hover
    elementoDia.addEventListener('mouseenter', function () {
        // Animación eliminada
        this.style.backgroundColor = 'rgba(0, 122, 204, 0.1)';
    });

    elementoDia.addEventListener('mouseleave', function () {
        // Animación eliminada
        if (!this.classList.contains('dia-actual')) {
            this.style.backgroundColor = '';
        }
    });

    return elementoDia;
}

/**
 * Cambia el mes del calendario
 */
function cambiarMes(direccion) {
    mesActual += direccion;

    if (mesActual > 11) {
        mesActual = 0;
        añoActual++;
    } else if (mesActual < 0) {
        mesActual = 11;
        añoActual--;
    }

    // Regenerar el calendario con animación
    const rejillaCalendario = document.getElementById('rejilla-calendario');
    if (rejillaCalendario) {
        rejillaCalendario.style.opacity = '0';
        // Animación de transición eliminada

        setTimeout(() => {
            generarCalendario();
            rejillaCalendario.style.opacity = '1';
            // Animación eliminada
        }, 200);
    }
}

/**
 * Configura los eventos del calendario
 */
function configurarEventosCalendario() {
    // Configurar botones de navegación
    const botonesNavegacion = document.querySelectorAll('.boton-nav');
    botonesNavegacion.forEach(boton => {
        boton.addEventListener('mouseenter', function () {
            this.style.backgroundColor = 'rgba(0, 122, 204, 0.2)';
            // Animación eliminada
        });

        boton.addEventListener('mouseleave', function () {
            this.style.backgroundColor = '';
            // Animación eliminada
        });
    });

    // Configurar navegación con teclado
    document.addEventListener('keydown', function (e) {
        if (e.target.closest('.calendario')) {
            if (e.key === 'ArrowLeft') {
                e.preventDefault();
                cambiarMes(-1);
            } else if (e.key === 'ArrowRight') {
                e.preventDefault();
                cambiarMes(1);
            }
        }
    });
}

/**
 * Muestra los detalles de un día específico
 */
function mostrarDetallesDia(dia, fechaCompleta) {
    const eventos = eventosCalendario[fechaCompleta] || [];

    if (eventos.length === 0) {
        mostrarNotificacion(`No hay eventos programados para el ${dia} de ${nombresMeses[mesActual]}`, 'info');
        return;
    }

    // Crear modal con los eventos del día
    const modal = crearModalEventos(dia, eventos);
    document.body.appendChild(modal);

    // Mostrar modal con animación
    setTimeout(() => {
        modal.classList.add('mostrar');
    }, 10);
}

/**
 * Crea un modal para mostrar los eventos de un día
 */
function crearModalEventos(dia, eventos) {
    const modal = document.createElement('div');
    modal.className = 'modal-eventos';
    modal.innerHTML = `
        <div class="contenido-modal">
            <div class="cabecera-modal">
                <h3>Eventos del ${dia} de ${nombresMeses[mesActual]}, ${añoActual}</h3>
                <button class="cerrar-modal" onclick="cerrarModal(this)">&times;</button>
            </div>
            <div class="cuerpo-modal">
                ${eventos.map(evento => `
                    <div class="evento-detalle ${evento.tipo}">
                        <div class="icono-evento">${obtenerIconoEvento(evento.tipo)}</div>
                        <div class="info-evento">
                            <div class="titulo-evento">${evento.titulo}</div>
                            ${evento.hora ? `<div class="hora-evento">${evento.hora}</div>` : ''}
                        </div>
                    </div>
                `).join('')}
            </div>
            <div class="pie-modal">
                <button class="boton-modal" onclick="cerrarModal(this)">Cerrar</button>
            </div>
        </div>
    `;

    // Cerrar modal al hacer click fuera
    modal.addEventListener('click', function (e) {
        if (e.target === modal) {
            cerrarModal(modal.querySelector('.cerrar-modal'));
        }
    });

    return modal;
}

/**
 * Obtiene el ícono apropiado para cada tipo de evento
 */
function obtenerIconoEvento(tipo) {
    const iconos = {
        'cita': '👥',
        'pago': '💰',
        'revision': '📋',
        'hoy': '📅'
    };
    return iconos[tipo] || '📌';
}

/**
 * Cierra el modal de eventos
 */
function cerrarModal(elemento) {
    const modal = elemento.closest('.modal-eventos');
    if (modal) {
        modal.classList.add('ocultar');
        setTimeout(() => {
            modal.remove();
        }, 300);
    }
}

/**
 * Actualiza el calendario con nuevos eventos (para uso futuro con AJAX)
 */
function actualizarEventosCalendario(nuevosEventos) {
    Object.assign(eventosCalendario, nuevosEventos);
    generarCalendario();
}

/**
 * Navega a una fecha específica
 */
function navegarAFecha(año, mes, dia) {
    añoActual = año;
    mesActual = mes;
    generarCalendario();

    if (dia) {
        // Destacar el día específico
        setTimeout(() => {
            const elementos = document.querySelectorAll('.dia-calendario');
            elementos.forEach(elemento => {
                if (elemento.querySelector('.numero-dia')?.textContent == dia) {
                    elemento.style.backgroundColor = 'rgba(0, 122, 204, 0.3)';
                    elemento.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            });
        }, 300);
    }
}

/**
 * Obtiene los eventos de un mes específico (para uso futuro con API)
 */
async function obtenerEventosMes(año, mes) {
    // Simulación de llamada a API
    return new Promise((resolve) => {
        setTimeout(() => {
            // En producción, esto haría una llamada real a la API
            resolve(eventosCalendario);
        }, 500);
    });
}