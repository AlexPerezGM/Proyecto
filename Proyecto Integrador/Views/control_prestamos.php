<?php
session_start();

// Verificar autenticación del usuario
if (!isset($_SESSION['username'])) {
    header('Location: ../index.php');
    exit;
}

// Incluir consultas de base de datos
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
    <script src="../js/control-prestamos.js"></script>
    <script src="../js/sistema-prestamos.js"></script>
    <title>Control de Préstamos - Sistema de Préstamos</title>
</head>
<body class="pagina-control-prestamos">
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
            <div class="contenido-cabecera">
                <h2>Control de Préstamos</h2>
                <p>Gestión completa de préstamos y desembolsos</p>
            </div>
        </div>
        <div class="derecha-cabecera">
            <div class="informacion-usuario">
                <div class="nombre-usuario"><?php echo $_SESSION['username']; ?></div>
                <div class="avatar-usuario">AU</div>
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
                    <a href="../Views/control_prestamos.php" class="elemento-navegacion activo">
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
            <div class="rejilla-estadisticas">
                <div class="tarjeta-estadistica">
                    <div class="cabecera-estadistica">
                        <div class="titulo-estadistica">Préstamos Activos</div>
                        <div class="icono-estadistica">📊</div>
                    </div>
                    <div class="valor-estadistica">50</div>
                    <div class="cambio-estadistica positivo">+12% último mes</div>
                </div>

                <div class="tarjeta-estadistica">
                    <div class="cabecera-estadistica">
                        <div class="titulo-estadistica">Monto Total Prestado</div>
                        <div class="icono-estadistica">💰</div>
                    </div>
                    <div class="valor-estadistica">$50,000.00</div>
                    <div class="cambio-estadistica positivo">+5% último mes</div>
                </div>

                <div class="tarjeta-estadistica">
                    <div class="cabecera-estadistica">
                        <div class="titulo-estadistica">Préstamos en Mora</div>
                        <div class="icono-estadistica">⚠️</div>
                    </div>
                    <div class="valor-estadistica">12</div>
                    <div class="cambio-estadistica negativo">-3% último mes</div>
                </div>

                <div class="tarjeta-estadistica">
                    <div class="cabecera-estadistica">
                        <div class="titulo-estadistica">Tasa de Interés</div>
                        <div class="icono-estadistica">📈</div>
                    </div>
                    <div class="valor-estadistica">13%</div>
                    <div class="cambio-estadistica positivo">Estable</div>
                </div>
            </div>

            <!-- Navegación por pestañas del sistema de préstamos -->
            <div class="navegacion-pestanas">
                <button class="boton-pestana activo" data-tab="generar">Solicitar Préstamo</button>
                <button class="boton-pestana" data-tab="lista">Lista de Préstamos</button>
                <button class="boton-pestana" data-tab="desembolso">Desembolso de Préstamos</button>
            </div>

            <div class="seccion-formulario" id="generar" style="display: block;">
                <div class="titulo-seccion">Selección de Clientes</div>
                <div class="seccion-busqueda">
                    <form method="GET" action="" class="contenedor-busqueda">
                        <input type="text" name="search" id="search-input" class="campo-busqueda" 
                               placeholder="Buscar por ID o nombre del cliente" 
                               value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                        <button type="submit" class="boton-primario" id="btn-search">🔍 Buscar</button>
                        <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
                            <button type="button" class="boton-secundario" onclick="limpiarBusqueda()">Limpiar</button>
                        <?php endif; ?>
                        <button type="button" class="boton-exito" id="btn-add">➕ Nuevo Cliente</button>
                    </form>
                </div>

                <div class="titulo-seccion">Información del Cliente</div>
                <div class="rejilla-informacion-cliente">
                    <div class="campo-informacion">
                        <div class="etiqueta-informacion">Nombre Completo</div>
                        <div class="valor-informacion">-</div>
                    </div>
                    <div class="campo-informacion">
                        <div class="etiqueta-informacion">Cédula de Identidad</div>
                        <div class="valor-informacion">-</div>
                    </div>
                    <div class="campo-informacion">
                        <div class="etiqueta-informacion">Dirección Residencial</div>
                        <div class="valor-informacion">-</div>
                    </div>
                    <div class="campo-informacion">
                        <div class="etiqueta-informacion">Teléfono de Contacto</div>
                        <div class="valor-informacion">-</div>
                    </div>
                    <div class="campo-informacion">
                        <div class="etiqueta-informacion">Ocupación Laboral</div>
                        <div class="valor-informacion">-</div>
                    </div>
                    <div class="campo-informacion">
                        <div class="etiqueta-informacion">Ingresos Mensuales</div>
                        <div class="valor-informacion">-</div>
                    </div>
                    <div class="campo-informacion">
                        <div class="etiqueta-informacion">Egresos Mensuales</div>
                        <div class="valor-informacion">-</div>
                    </div>
                    <div class="campo-informacion">
                        <div class="etiqueta-informacion">Correo Electrónico</div>
                        <div class="valor-informacion">-</div>
                    </div>
                </div>

                <div class="titulo-seccion">Seleccionar Tipo de Préstamo</div>
                <div class="rejilla-tipos-prestamo">
                    <div class="tarjeta-tipo-prestamo" data-type="personal">
                        <div class="titulo-tipo-prestamo">Préstamo Personal</div>
                        <div class="descripcion-tipo-prestamo">Préstamos flexibles para necesidades personales con tasas competitivas y plazos adaptables</div>
                    </div>
                    <div class="tarjeta-tipo-prestamo" data-type="hipotecario">
                        <div class="titulo-tipo-prestamo">Préstamo Hipotecario</div>
                        <div class="descripcion-tipo-prestamo">Financiamiento para compra de vivienda con plazos extendidos y garantía hipotecaria</div>
                    </div>
                </div>
            </div>

            <!-- Sección: Lista de préstamos existentes -->
            <div class="seccion-formulario" id="lista" style="display: none;">
                <div class="titulo-seccion">Lista de Préstamos</div>
                <div class="seccion-busqueda">
                    <div class="contenedor-busqueda">
                        <input type="text" class="campo-busqueda" placeholder="Buscar préstamos por cliente, ID o estado...">
                        <button class="boton-primario">🔍 Buscar</button>
                    </div>
                </div>
                <div class="tabla-contenedor">
                    <table class="tabla-principal">
                        <thead>
                            <tr>
                                <th>ID Préstamo</th>
                                <th>Cliente</th>
                                <th>Monto</th>
                                <th>Tasa de Interés</th>
                                <th>Plazo (meses)</th>
                                <th>Estado</th>
                                <th>Fecha de Creación</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Ejemplo de filas -->
                            <tr>
                                <td><strong>PR-001</strong></td>
                                <td>Juan Carlos Pérez</td>
                                <td><strong>$5,000.00</strong></td>
                                <td>15.5%</td>
                                <td>12</td>
                                <td><span class="estado-activo">Activo</span></td>
                                <td>15/09/2025</td>
                                <td>
                                    <button class="boton-secundario">👁️ Ver</button>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>PR-002</strong></td>
                                <td>María Elena García</td>
                                <td><strong>$3,500.00</strong></td>
                                <td>14.8%</td>
                                <td>24</td>
                                <td><span class="estado-pendiente">Pendiente</span></td>
                                <td>20/09/2025</td>
                                <td>
                                    <button class="boton-secundario">👁️ Ver</button>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>PR-003</strong></td>
                                <td>Roberto Silva Torres</td>
                                <td><strong>$7,800.00</strong></td>
                                <td>16.2%</td>
                                <td>18</td>
                                <td><span class="estado-inactivo">Rechazado</span></td>
                                <td>22/09/2025</td>
                                <td>
                                    <button class="boton-secundario">👁️ Ver</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Sección: Desembolso de préstamos aprobados -->
            <div class="seccion-formulario" id="desembolso" style="display: none;">
                <div class="titulo-seccion">Desembolso de Préstamos</div>
                <form class="formulario-desembolso">
                    <div class="grupo-formulario">
                        <label for="prestamo-id" class="etiqueta-formulario">ID del Préstamo:</label>
                        <input type="text" id="prestamo-id" name="prestamo-id" class="campo-formulario" 
                               placeholder="Ej: PR-001" required>
                    </div>
                    <div class="grupo-formulario">
                        <label for="monto-desembolso" class="etiqueta-formulario">Monto a Desembolsar ($):</label>
                        <input type="number" id="monto-desembolso" name="monto-desembolso" class="campo-formulario" 
                               placeholder="Ej: 5000.00" step="0.01" required>
                    </div>
                    <div class="grupo-formulario">
                        <label for="metodo-pago" class="etiqueta-formulario">Método de Pago:</label>
                        <select id="metodo-pago" name="metodo-pago" class="campo-formulario" required>
                            <option value="">Seleccionar método de pago</option>
                            <option value="transferencia">Transferencia</option>
                            <option value="efectivo">Efectivo</option>
                            <option value="cheque">Cheque</option>
                        </select>
                    </div>
                    <div class="grupo-formulario">
                        <label for="observaciones" class="etiqueta-formulario">Observaciones (Opcional):</label>
                        <textarea id="observaciones" name="observaciones" class="campo-formulario" 
                                  placeholder="Ingrese cualquier observación relevante sobre el desembolso..." rows="3"></textarea>
                    </div>
                    <div style="display: flex; gap: 15px; margin-top: 20px;">
                        <button type="submit" class="boton-exito">Procesar Desembolso</button>
                    </div>
                </form>
            </div>


    <!-- Modal para Nuevo Cliente -->
    <div class="modal modal-nuevo-cliente" id="cliente-modal" style="display: none;">
        <div class="contenido-modal">
            <div class="cabecera-modal">
                <h3 class="titulo-modal" id="modal-title">Nuevo cliente</h3>
                <button class="cerrar-modal" id="close-modal" title="Cerrar modal">×</button>
            </div>
            
            <div class="cuerpo-modal">
                <form id="cliente-form" class="formulario-cliente">
                    <input type="hidden" name="id_cliente" id="id_cliente">
                    
                    <div class="grupo-formulario">
                        <label for="nombre" class="etiqueta-campo">Nombre *</label>
                        <input type="text" name="nombre" id="nombre" class="campo-entrada" required>
                    </div>
                    
                    <div class="grupo-formulario">
                        <label for="apellido" class="etiqueta-campo">Apellido *</label>
                        <input type="text" name="apellido" id="apellido" class="campo-entrada" required>
                    </div>
                    
                    <div class="grupo-formulario">
                        <label for="edad" class="etiqueta-campo">Edad *</label>
                        <input type="number" name="edad" id="edad" class="campo-entrada" min="18" max="80" required>
                    </div>
                    
                    <div class="grupo-formulario">
                        <label for="cedula" class="etiqueta-campo">Cédula de identidad *</label>
                        <input type="text" name="documento_identidad" id="cedula" class="campo-entrada" required>
                    </div>
                    
                    <div class="grupo-formulario">
                        <label for="telefono" class="etiqueta-campo">Teléfono *</label>
                        <input type="tel" name="telefono" id="telefono" class="campo-entrada" required>
                    </div>
                    
                    <div class="grupo-formulario">
                        <label for="email" class="etiqueta-campo">Email *</label>
                        <input type="email" name="email" id="email" class="campo-entrada" required>
                    </div>
                    
                    <div class="grupo-formulario">
                        <label for="ingresos" class="etiqueta-campo">Ingresos mensuales ($)</label>
                        <input type="number" name="ingresos_mensuales" id="ingresos" class="campo-entrada" step="0.01" min="0">
                    </div>
                    
                    <div class="grupo-formulario">
                        <label for="egresos" class="etiqueta-campo">Egresos mensuales ($)</label>
                        <input type="number" name="egresos_mensuales" id="egresos" class="campo-entrada" step="0.01" min="0">
                    </div>
                    
                    <div class="grupo-formulario">
                        <label for="ocupacion" class="etiqueta-campo">Ocupación *</label>
                        <input type="text" name="ocupacion" id="ocupacion" class="campo-entrada" required>
                    </div>
                    
                    <div class="grupo-formulario">
                        <label for="estado" class="etiqueta-campo">Estado</label>
                        <select name="estado" id="estado" class="campo-entrada">
                            <option value="Activo">Activo</option>
                            <option value="Inactivo">Inactivo</option>
                        </select>
                    </div>
                    
                    <div class="grupo-botones-modal">
                        <button type="button" class="boton-modal boton-cancelar" id="cancel-btn">Cancelar</button>
                        <button type="submit" class="boton-modal boton-agregar" id="save-btn">Agregar cliente</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

        </main>
    </div>
    
</body>
</html>