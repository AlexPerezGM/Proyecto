<?php
session_start();

if (!isset($_SESSION['username']) || $_SESSION['role_id'] != 1) {
    header('Location: ../index.php');
    exit;
}
require_once 'conf/connection.php';
$connection = new Connection();
$pdo = $connection->connect();

$sql = "SELECT id, username FROM usuarios";
$stmt = $pdo->query($sql);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once 'app/consultas_dashboard.php';

?>



<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/sistema-prestamos.css">
    <link rel="stylesheet" href="css/interactividad.css">
    <title>Dashboard - Admin</title>
</head>
<body class="pagina-dashboard">
    <header class="cabecera-superior">
        <div class="izquierda-cabecera">
            <button class="boton-menu" onclick="toggleSidebar()">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <div class="logotipo">
                <h1>🏠 Sistema de Préstamos</h1>
            </div>
            <div>
                <h2>Dashboard Principal</h2>
                <p>Resumen del sistema</p>
            </div>
        </div>
        <div class="derecha-cabecera">
            <div class="contenido-cabecera">
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
                    <div class="titulo-navegacion">Dashboard</div>
                    <a href="#" class="elemento-navegacion activo">
                        <div class="icono-navegacion">🏠</div>
                        Dashboard
                    </a>
                </div>

                <div class="seccion-navegacion">
                    <div class="titulo-navegacion">Gestión</div>
                    <a href="Views/gestion_clientes.php" class="elemento-navegacion">
                        <div class="icono-navegacion">👥</div>
                        Gestión de Clientes
                    </a>
                    <a href="Views/control_prestamos.php" class="elemento-navegacion">
                        <div class="icono-navegacion">💰</div>
                        Control de Préstamos
                    </a>
                    <a href="Views/gestion_pagos.php" class="elemento-navegacion">
                        <div class="icono-navegacion">💳</div>
                        Gestión de Pagos
                    </a>
                    <a href="Views/seguimiento_prestamos.php" class="elemento-navegacion">
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
                    <a href="Views/administracion_reportes.php" class="elemento-navegacion">
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
                    <a href="InicioSesion/cerrarSesion.php" class="elemento-navegacion">
                        <div class="icono-navegacion">🚪</div>
                        Cerrar Sesión
                    </a>
                </div>
            </nav>
        </aside>

        <main class="contenido-principal">
            <div class="rejilla-estadisticas">
                <div class="tarjeta-estadistica">
                    <div class="cabecera-estadistica">
                        <div class="titulo-estadistica">Préstamos Activos</div>
                        <div class="icono-estadistica">📊</div>
                    </div>
                    <div class="valor-estadistica"><?php echo $total_prestamos; ?></div>
                    <div class="cambio-estadistica positivo">+12% último mes</div>
                </div>

                <div class="tarjeta-estadistica">
                    <div class="cabecera-estadistica">
                        <div class="titulo-estadistica">Clientes Totales</div>
                        <div class="icono-estadistica">👥</div>
                    </div>
                    <div class="valor-estadistica"><?php echo $total_clientes; ?></div>
                    <div class="cambio-estadistica positivo">+5% último mes</div>
                </div>

                <div class="tarjeta-estadistica">
                    <div class="cabecera-estadistica">
                        <div class="titulo-estadistica">Carteras Morosas</div>
                        <div class="icono-estadistica">⚠️</div>
                    </div>
                    <div class="valor-estadistica"><?php echo $total_morosos; ?></div>
                    <div class="cambio-estadistica negativo">-3% último mes</div>
                </div>

                <div class="tarjeta-estadistica">
                    <div class="cabecera-estadistica">
                        <div class="titulo-estadistica">Ingresos del Mes</div>
                        <div class="icono-estadistica">💰</div>
                    </div>
                    <div class="valor-estadistica">$<?php echo number_format($total_ingresos, 0); ?></div>
                    <div class="cambio-estadistica positivo">+8% último mes</div>
                </div>
            </div>

            <div class="rejilla-contenido">
                <div class="tarjeta-contenido">
                    <h3 class="titulo-tarjeta">Actividad Reciente</h3>
                    <div id="actividad-reciente">
                        <p style="text-align: center; color: #888;">No hay actividad reciente.</p>
                    </div>
                </div>

                <div class="tarjeta-contenido">
                    <h3 class="titulo-tarjeta">Calendario</h3>
                    <div class="calendario">
                        <div class="cabecera-calendario">
                            <button class="boton-nav" onclick="cambiarMes(-1)">&#8592;</button>
                            <div id="mes-actual">2025 Septiembre</div>
                            <button class="boton-nav" onclick="cambiarMes(1)">&#8594;</button>
                        </div>
                        <div class="rejilla-calendario" id="rejilla-calendario">
                        </div>
                    </div>
                </div>
            </div>    
        </main>
    </div>
    
    <script src="js/sistema-prestamos.js"></script>
    <script src="js/calendario_dashboard.js"></script>
</body>