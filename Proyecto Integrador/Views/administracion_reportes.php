<?php
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['username'])) {
    header('Location: ../index.php');
    exit;
}

// Incluir archivos necesarios
require_once '../app/consulta_clientes.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/sistema-prestamos.css">
    <link rel="stylesheet" href="../css/interactividad.css">
    <title>Administración de Reportes - Sistema de Préstamos</title>
</head>
<body class="pagina-administracion-reportes">
    <header class="cabecera-superior">
        <div class="izquierda-cabecera">
            <button class="boton-menu" onclick="alternarBarraLateral()">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <div class="logotipo">
                <h1>🏠 Sistema de Préstamos</h1>
            </div>
            <div class="contenido-cabecera">
                <h2>Administración de Reportes</h2>
                <p>Gestionar y visualizar reportes del sistema</p>
            </div>
        </div>
        <div class="derecha-cabecera">
            <div class="informacion-usuario">
                <div class="nombre-usuario"><?php echo $_SESSION['username']; ?></div>
                <div class="avatar-usuario">AU</div>
            </div>
        </div>
        </div>
    </header>

    <div class="contenedor-principal">
        <aside class="barra-lateral" id="sidebar">
            <nav>
                <div class="seccion-navegacion">
                    <div class="titulo-navegacion">Principal</div>
                    <a href="../dashboard.php" class="elemento-navegacion">
                        <div class="icono-navegacion">🏠</div>
                        Dashboard
                    </a>
                </div>

                <div class="seccion-navegacion">
                    <div class="titulo-navegacion">Gestión</div>
                    <a href="../Views/gestion_clientes.php" class="elemento-navegacion">
                        <div class="icono-navegacion">👥</div>
                        Gestión de Clientes
                    </a>
                    <a href="../Views/control_prestamos.php" class="elemento-navegacion">
                        <div class="icono-navegacion">💰</div>
                        Control de Préstamos
                    </a>
                    <a href="../Views/gestion_pagos.php" class="elemento-navegacion">
                        <div class="icono-navegacion">💳</div>
                        Gestión de Pagos
                    </a>
                    <a href="../Views/seguimiento_prestamos.php" class="elemento-navegacion">
                        <div class="icono-navegacion">📊</div>
                        Seguimiento de Préstamos
                    </a>
                    <a href="#" class="elemento-navegacion">
                        <div class="icono-navegacion">🔄</div>
                        Reestructuración de Préstamos
                    </a>
                </div>

                <div class="seccion-navegacion">
                    <div class="titulo-navegacion">Reportes</div>
                    <a href="../Views/administracion_reportes.php" class="elemento-navegacion activo">
                        <div class="icono-navegacion">📋</div>
                        Administración de Reportes
                    </a>
                </div>

                <div class="seccion-navegacion">
                    <div class="titulo-navegacion">Administración</div>
                    <a href="#" class="elemento-navegacion">
                        <div class="icono-navegacion">👤</div>
                        Usuarios y Roles
                    </a>
                    <a href="#" class="elemento-navegacion">
                        <div class="icono-navegacion">🏢</div>
                        Recursos Humanos
                    </a>
                    <a href="#" class="elemento-navegacion">
                        <div class="icono-navegacion">📅</div>
                        Agenda y Citas
                    </a>
                    <a href="../InicioSesion/cerrarSesion.php" class="elemento-navegacion">
                        <div class="icono-navegacion">🚪</div>
                        Cerrar Sesión
                    </a>
                </div>
            </nav>
        </aside>

        <main class="contenido-principal">
            <div class="seccion-formulario">
                <div class="titulo-seccion">Reportes de Préstamos</div>
                <div class="rejilla-tipos-prestamo">
                    <div class="tarjeta-tipo-prestamo">
                        <div class="titulo-tipo-prestamo">📈 Préstamos Activos</div>
                        <div class="descripcion-tipo-prestamo">Listado completo de préstamos en estado normal y al día con sus pagos</div>
                        <div style="margin-top: 15px;">
                            <button class="boton-primario">Generar Reporte</button>
                        </div>
                    </div>

                    <div class="tarjeta-tipo-prestamo">
                        <div class="titulo-tipo-prestamo">⚠️ Préstamos en Mora</div>
                        <div class="descripcion-tipo-prestamo">Reporte detallado de préstamos con pagos vencidos y en estado de mora</div>
                        <div style="margin-top: 15px;">
                            <button class="boton-peligro">Generar Reporte</button>
                        </div>
                    </div>

                    <div class="tarjeta-tipo-prestamo">
                        <div class="titulo-tipo-prestamo">💰 Análisis Financiero</div>
                        <div class="descripcion-tipo-prestamo">Reporte de ingresos, intereses generados y análisis de rentabilidad</div>
                        <div style="margin-top: 15px;">
                            <button class="boton-exito">Generar Reporte</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="seccion-formulario">
                <div class="titulo-seccion">Reportes de Clientes</div>
                <div class="rejilla-tipos-prestamo">
                    <div class="tarjeta-tipo-prestamo">
                        <div class="titulo-tipo-prestamo">👥 Listado General</div>
                        <div class="descripcion-tipo-prestamo">Reporte completo de todos los clientes registrados en el sistema</div>
                        <div style="margin-top: 15px;">
                            <button class="boton-primario">Generar Reporte</button>
                        </div>
                    </div>

                    <div class="tarjeta-tipo-prestamo">
                        <div class="titulo-tipo-prestamo">🆕 Clientes Nuevos</div>
                        <div class="descripcion-tipo-prestamo">Reporte de clientes registrados en el último mes</div>
                        <div style="margin-top: 15px;">
                            <button class="boton-secundario">Generar Reporte</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="seccion-formulario">
                <div class="titulo-seccion">Configuración de Reportes</div>
                <div class="mensaje-informativo">
                    <div class="icono-mensaje">⚙️</div>
                    <div class="texto-mensaje">
                        <h3>Reportes Automáticos</h3>
                        <p>Configure la generación automática de reportes periódicos para mantener un control constante del sistema.</p>
                    </div>
                </div>
                <form class="formulario-reportes">
                    <div class="grupo-formulario">
                        <label class="etiqueta-formulario">Frecuencia de Reportes:</label>
                        <select class="campo-formulario">
                            <option value="diario">Diario</option>
                            <option value="semanal">Semanal</option>
                            <option value="mensual" selected>Mensual</option>
                            <option value="trimestral">Trimestral</option>
                        </select>
                    </div>
                    <div class="grupo-formulario">
                        <label class="etiqueta-formulario">Formato de Exportación:</label>
                        <select class="campo-formulario">
                            <option value="pdf">PDF</option>
                            <option value="excel">Excel</option>
                            <option value="csv">CSV</option>
                        </select>
                    </div>
                    <div style="display: flex; gap: 15px; margin-top: 20px;">
                        <button type="button" class="boton-primario">💾 Guardar Configuración</button>
                        <button type="button" class="boton-secundario">📧 Enviar por Email</button>
                    </div>
                </form>
            </div>
        </div>
        </main>
    </div>
    
    <script src="../js/sistema-prestamos.js"></script>
</body>
</html>