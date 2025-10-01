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
    <link rel="stylesheet" href="../css/sistema-prestamos.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/interactividad.css">
    <title>Seguimiento de Préstamos - Sistema de Préstamos</title>
</head>
<body class="pagina-seguimiento-prestamos">
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
                <h2>Seguimiento de Préstamos</h2>
                <p>Monitoreo y control de préstamos activos</p>
            </div>
        </div>
        <div class="derecha-cabecera">
            <div class="informacion-usuario">
                <div class="nombre-usuario"><?php echo $_SESSION['username']; ?></div>
                <div class="avatar-usuario">AU</div>
            </div>
        </div>
    </header>

    <div class="container">
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
                    <a href="../Views/seguimiento_prestamos.php" class="elemento-navegacion activo">
                        <div class="icono-navegacion">📊</div>
                        Seguimiento de Préstamos
                    </a>
                     <a href="#" class="elemento-navegacion">
                        <div class="icono-navegacion">🔄</div>
                        Reestructuración de prestamos
                    </a>
                </div>

                <div class="seccion-navegacion">
                    <div class="titulo-navegacion">Reportes</div>
                    <a href="../Views/administracion_reportes.php" class="elemento-navegacion">
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
            <div class="navegacion-pestanas">
                <button class="boton-pestana activo" data-tab="estado-normal">📊 Evaluación de Préstamos</button>
                <button class="boton-pestana" data-tab="prestamos-morosos">⚠️ Préstamos Morosos</button>
                <button class="boton-pestana" data-tab="alertas-tempranas">🔔 Alertas Tempranas</button>
            </div>

            <div class="seccion-formulario aparecer-gradual" id="estado-normal">
                <div class="titulo-seccion">Evaluación de Préstamos</div>
                <div class="seccion-busqueda">
                    <form method="GET" action="" class="contenedor-busqueda">
                        <input type="text" name="search" id="search-input" class="campo-busqueda" 
                               placeholder="Buscar por ID, cliente o estado..." 
                               value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                        <button type="submit" class="boton-primario" id="btn-search">🔍 Filtrar</button>
                        <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
                            <button type="button" class="boton-secundario" onclick="clearSearch()">Limpiar</button>
                        <?php endif; ?>
                    </form>
                </div>
                <div class="seccion-formulario">
                    <form method="POST" action="" class="formulario">
                        <div class="grupo-formulario">
                            <label for="ingresos-mensuales" class="etiqueta-campo">Ingresos Mensuales</label>
                            <input type="number" name="ingresos-mensuales" id="ingresos-mensuales" class="campo-entrada" placeholder="Ingresos mensuales">
                        </div>
                        <div class="grupo-formulario">
                            <label for="egresos-mensuales" class="etiqueta-campo">Egresos mensuales</label>
                            <input type="number" id="egresos-mensuales" name="egresos-mensuales" class="campo-entrada" placeholder="Egresos mensuales">
                        </div>
                        <div class="grupo-formulario">
                            <label for="capacidad-pago" class="etiqueta-campo">Capacidad de pago</label>
                            <input type="number" id="capacidad-pago" name="capacidad-pago" class="campo-entrada" placeholder="Capacidad de pago">
                        </div>
                        <div class="grupo-formulario">
                            <label for="monto-solicitado" class="etiqueta-campo">Monto solicitado</label>
                            <input type="number" id="monto-solicitado" name="monto-solicitado" class="campo-entrada" placeholder="Monto solicitado">
                        </div>
                        <button type="submit" class="boton-primario">Aprobar prestamo</button>
                        <button type="submit" class="boton-secundario">Rechazar prestamo</button>
                    </form>
                </div>
                <div class="tabla-contenedor">
                    <table class="tabla-principal">
                        <thead>
                            <tr>
                                <th>ID Préstamo</th>
                                <th>Cliente</th>
                                <th>Monto</th>
                                <th>Cuotas Pagadas</th>
                                <th>Próximo Pago</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>PR-001</strong></td>
                                <td>Juan Carlos Pérez</td>
                                <td><strong>$5,000.00</strong></td>
                                <td>8/12</td>
                                <td>15/10/2025</td>
                                <td><span class="estado-activo">Al día</span></td>
                                <td>
                                    <button class="boton-secundario">👁️ Ver</button>
                                    <button class="boton-primario">📋 Historial</button>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>PR-005</strong></td>
                                <td>Ana María García</td>
                                <td><strong>$3,200.00</strong></td>
                                <td>15/24</td>
                                <td>20/10/2025</td>
                                <td><span class="estado-activo">Al día</span></td>
                                <td>
                                    <button class="boton-secundario">👁️ Ver</button>
                                    <button class="boton-primario">📋 Historial</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="seccion-formulario oculto" id="prestamos-morosos">
                <div class="titulo-seccion">Préstamos en Estado de Mora</div>
                <div class="mensaje-informativo">
                    <div class="icono-mensaje">⚠️</div>
                    <div class="texto-mensaje">
                        <h3>Gestión de Morosos</h3>
                        <p>Seguimiento especial para préstamos con pagos vencidos que requieren atención inmediata.</p>
                    </div>
                </div>
                <div class="tabla-contenedor">
                    <table class="tabla-principal">
                        <thead>
                            <tr>
                                <th>ID Préstamo</th>
                                <th>Cliente</th>
                                <th>Días de Mora</th>
                                <th>Monto Vencido</th>
                                <th>Último Contacto</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>PR-003</strong></td>
                                <td>Roberto Silva Torres</td>
                                <td><strong>15 días</strong></td>
                                <td><strong>$450.00</strong></td>
                                <td>22/09/2025</td>
                                <td><span class="estado-inactivo">Mora</span></td>
                                <td>
                                    <button class="boton-peligro">📞 Contactar</button>
                                    <button class="boton-secundario">📋 Historial</button>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>PR-008</strong></td>
                                <td>Luis Fernando Martínez</td>
                                <td><strong>32 días</strong></td>
                                <td><strong>$780.00</strong></td>
                                <td>10/09/2025</td>
                                <td><span class="estado-inactivo">Mora</span></td>
                                <td>
                                    <button class="boton-peligro">📞 Contactar</button>
                                    <button class="boton-secundario">📋 Historial</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="seccion-formulario oculto" id="alertas-tempranas">
                <div class="titulo-seccion">Sistema de Alertas Tempranas</div>
                <div class="mensaje-informativo">
                    <div class="icono-mensaje">🔔</div>
                    <div class="texto-mensaje">
                        <h3>Prevención de Mora</h3>
                        <p>Identificación temprana de clientes con riesgo de mora para tomar acciones preventivas.</p>
                    </div>
                </div>
                <div class="rejilla-tipos-prestamo">
                    <div class="tarjeta-tipo-prestamo">
                        <div class="titulo-tipo-prestamo">🟡 Riesgo Medio</div>
                        <div class="descripcion-tipo-prestamo">Clientes con 1-2 pagos atrasados en los últimos 6 meses</div>
                        <div style="margin-top: 15px;">
                            <button class="boton-secundario">Ver Lista (12)</button>
                        </div>
                    </div>
                    <div class="tarjeta-tipo-prestamo">
                        <div class="titulo-tipo-prestamo">🟠 Riesgo Alto</div>
                        <div class="descripcion-tipo-prestamo">Clientes con patrón de pagos irregulares y atrasos frecuentes</div>
                        <div style="margin-top: 15px;">
                            <button class="boton-advertencia">Ver Lista (5)</button>
                        </div>
                    </div>
                    <div class="tarjeta-tipo-prestamo">
                        <div class="titulo-tipo-prestamo">🔴 Riesgo Crítico</div>
                        <div class="descripcion-tipo-prestamo">Clientes próximos a entrar en mora que requieren contacto inmediato</div>
                        <div style="margin-top: 15px;">
                            <button class="boton-peligro">Ver Lista (3)</button>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>
    
    <script src="../js/sistema-prestamos.js"></script>
</body>
</html>