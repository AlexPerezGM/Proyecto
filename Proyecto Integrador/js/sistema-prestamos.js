/**
 * SISTEMA DE PRÉSTAMOS - JAVASCRIPT PRINCIPAL
 * Funcionalidades generales para el dashboard y navegación
 */

// Variables globales
let sidebarAbierta = true;

/**
 * Inicialización del sistema al cargar la página
 */
document.addEventListener('DOMContentLoaded', function () {
    inicializarSistema();
    configurarEventosNavegacion();
    marcarOpcionActiva();
});

/**
 * Inicializa el sistema y configura los elementos principales
 */
function inicializarSistema() {
    console.log('Sistema de préstamos inicializado');

    // Configurar el estado inicial del sidebar
    const sidebar = document.getElementById('sidebar');
    const botonMenu = document.querySelector('.boton-menu');

    if (sidebar && botonMenu) {
        // Establecer estado inicial
        actualizarEstadoSidebar();

        // Agregar eventos de teclado para accesibilidad
        botonMenu.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                toggleSidebar();
            }
        });
    }
}

/**
 * Alterna la visibilidad del sidebar
 */
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const contenidoPrincipal = document.querySelector('.contenido-principal');
    const botonMenu = document.querySelector('.boton-menu');

    if (!sidebar) return;

    sidebarAbierta = !sidebarAbierta;

    // Mostrar/ocultar sidebar SIN animaciones
    if (sidebarAbierta) {
        sidebar.classList.remove('cerrada');
        sidebar.style.display = 'block';
        if (contenidoPrincipal) {
            contenidoPrincipal.style.marginLeft = '280px';
        }
    } else {
        sidebar.classList.add('cerrada');
        sidebar.style.display = 'none';
        if (contenidoPrincipal) {
            contenidoPrincipal.style.marginLeft = '0';
        }
    }

    // Animar el botón hamburguesa
    animarBotonHamburguesa(botonMenu, sidebarAbierta);

    // Guardar estado en localStorage
    localStorage.setItem('sidebarAbierta', sidebarAbierta);
}

/**
 * Anima el botón hamburguesa cuando cambia de estado
 */
function animarBotonHamburguesa(boton, abierta) {
    if (!boton) return;

    const spans = boton.querySelectorAll('span');

    if (spans.length >= 3) {
        if (abierta) {
            // Estado normal (hamburguesa) - SIN ANIMACIONES
            boton.classList.remove('activo');
        } else {
            // Estado X - SIN ANIMACIONES
            boton.classList.add('activo');
        }
    }
}

/**
 * Actualiza el estado del sidebar basado en localStorage
 */
function actualizarEstadoSidebar() {
    const estadoGuardado = localStorage.getItem('sidebarAbierta');
    if (estadoGuardado !== null) {
        sidebarAbierta = estadoGuardado === 'true';

        const sidebar = document.getElementById('sidebar');
        const contenidoPrincipal = document.querySelector('.contenido-principal');
        const botonMenu = document.querySelector('.boton-menu');

        if (sidebar) {
            if (sidebarAbierta) {
                sidebar.classList.remove('cerrada');
                sidebar.style.display = 'block';
                if (contenidoPrincipal) {
                    contenidoPrincipal.style.marginLeft = '280px';
                }
            } else {
                sidebar.classList.add('cerrada');
                sidebar.style.display = 'none';
                if (contenidoPrincipal) {
                    contenidoPrincipal.style.marginLeft = '0';
                }
            }

            animarBotonHamburguesa(botonMenu, sidebarAbierta);
        }
    }
}

/**
 * Configura los eventos de navegación
 */
function configurarEventosNavegacion() {
    const elementosNavegacion = document.querySelectorAll('.elemento-navegacion');

    elementosNavegacion.forEach(elemento => {
        elemento.addEventListener('click', function (e) {
            // Solo prevenir el comportamiento por defecto si es un enlace vacío
            if (this.getAttribute('href') === '#') {
                e.preventDefault();
            }

            // Remover clase activa de todos los elementos
            elementosNavegacion.forEach(el => el.classList.remove('activo'));

            // Agregar clase activa al elemento clickeado
            this.classList.add('activo');

            // Sin efectos visuales de click
        });

        // Efectos hover mejorados
        elemento.addEventListener('mouseenter', function () {
            if (!this.classList.contains('activo')) {
                this.style.backgroundColor = 'rgba(0, 122, 204, 0.1)';
            }
        });

        elemento.addEventListener('mouseleave', function () {
            if (!this.classList.contains('activo')) {
                this.style.backgroundColor = '';
            }
        });
    });
}

/**
 * Marca la opción activa basada en la URL actual
 */
function marcarOpcionActiva() {
    const rutaActual = window.location.pathname;
    const elementosNavegacion = document.querySelectorAll('.elemento-navegacion');

    elementosNavegacion.forEach(elemento => {
        elemento.classList.remove('activo');

        const href = elemento.getAttribute('href');
        if (href && href !== '#') {
            // Normalizar las rutas para comparación
            const rutaElemento = href.replace(/^\.\.?\//g, '');
            const rutaNormalizada = rutaActual.split('/').pop();

            if (rutaElemento.includes(rutaNormalizada) ||
                (rutaNormalizada === 'dashboard.php' && href === '#')) {
                elemento.classList.add('activo');
            }
        }
    });

    // Si estamos en dashboard.php, marcar el primer elemento como activo
    if (rutaActual.includes('dashboard.php') || rutaActual.endsWith('/')) {
        const primerElemento = document.querySelector('.elemento-navegacion');
        if (primerElemento) {
            primerElemento.classList.add('activo');
        }
    }
}

/**
 * Función para mostrar notificaciones (toast)
 */
function mostrarNotificacion(mensaje, tipo = 'info', duracion = 3000) {
    // Crear el elemento de notificación
    const notificacion = document.createElement('div');
    notificacion.className = `notificacion notificacion-${tipo}`;
    notificacion.innerHTML = `
        <div class="contenido-notificacion">
            <span class="icono-notificacion">${obtenerIconoNotificacion(tipo)}</span>
            <span class="mensaje-notificacion">${mensaje}</span>
            <button class="cerrar-notificacion" onclick="cerrarNotificacion(this)">&times;</button>
        </div>
    `;

    // Agregar al DOM
    document.body.appendChild(notificacion);

    // Mostrar con animación
    setTimeout(() => {
        notificacion.classList.add('mostrar');
    }, 100);

    // Ocultar automáticamente
    setTimeout(() => {
        cerrarNotificacion(notificacion.querySelector('.cerrar-notificacion'));
    }, duracion);
}

/**
 * Obtiene el ícono apropiado para el tipo de notificación
 */
function obtenerIconoNotificacion(tipo) {
    const iconos = {
        'success': '✅',
        'error': '❌',
        'warning': '⚠️',
        'info': 'ℹ️'
    };
    return iconos[tipo] || iconos['info'];
}

/**
 * Cierra una notificación
 */
function cerrarNotificacion(boton) {
    const notificacion = boton.closest('.notificacion');
    if (notificacion) {
        notificacion.classList.add('ocultar');
        setTimeout(() => {
            notificacion.remove();
        }, 300);
    }
}

/**
 * Manejo de errores globales
 */
window.addEventListener('error', function (e) {
    console.error('Error detectado:', e.error);
    mostrarNotificacion('Ha ocurrido un error inesperado', 'error');
});

/**
 * Función para manejar la responsividad
 */
function manejarResponsividad() {
    const anchoPantalla = window.innerWidth;
    const sidebar = document.getElementById('sidebar');
    const contenidoPrincipal = document.querySelector('.contenido-principal');

    if (anchoPantalla <= 768) {
        // En móviles, cerrar sidebar por defecto
        if (sidebarAbierta) {
            toggleSidebar();
        }

        // Hacer que el sidebar se superponga en lugar de empujar el contenido
        if (sidebar) {
            sidebar.style.position = 'fixed';
            sidebar.style.zIndex = '1001';
        }
        if (contenidoPrincipal) {
            contenidoPrincipal.style.marginLeft = '0';
        }
    } else {
        // En escritorio, restaurar comportamiento normal
        if (sidebar) {
            sidebar.style.position = 'fixed';
            sidebar.style.zIndex = '999';
        }
        if (contenidoPrincipal && sidebarAbierta) {
            contenidoPrincipal.style.marginLeft = '280px';
        }
    }
}

// Escuchar cambios de tamaño de ventana
window.addEventListener('resize', manejarResponsividad);

// Aplicar responsividad al cargar
document.addEventListener('DOMContentLoaded', manejarResponsividad);

// Función global para compatibilidad con HTML
function alternarBarraLateral() {
    toggleSidebar();
}