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
    <title>Gestión de Clientes - Sistema de Préstamos</title>
</head>
<body class="pagina-gestion-clientes">
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
                <h2>Gestión de Clientes</h2>
                <p>Administrar información completa de clientes</p>
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
                    <a href="../Views/gestion_clientes.php" class="elemento-navegacion activo">
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
            <div class="barra-herramientas">
                <div class="grupo-busqueda">
                    <form method="GET" action="" class="entrada-busqueda">
                        <input type="text" name="search" id="entrada-busqueda" placeholder="Buscar por ID o nombre" 
                        value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                    </form>
                    <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
                        <button type="button" class="boton boton-secundario" onclick="limpiarBusqueda()">Limpiar</button>
                    <?php endif; ?>
                </div>
                <div class="grupo-acciones">
                    <button type="submit" class="boton boton-primario" id="btn-buscar">Buscar</button>
                    <button class="boton-nuevo-cliente" id="btn-agregar" onclick="nuevoCliente()">Nuevo cliente</button>
                </div>
            </div>

            <div class="contenedor-tabla-clientes">
                <div class="cabecera-tabla">
                    <h2 class="titulo-tabla">Lista de clientes</h2>
                    <div class="contador-tabla">Total de clientes: <?php echo $totalClients; ?></div>
                </div>
                
                <div class="tabla-responsiva">
                    <table class="tabla-clientes" id="tabla-clientes">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Cédula</th>
                                <th>Email</th>
                                <th>Teléfono</th>
                                <th>Estado</th>
                                <th>Fecha de registro</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->rowCount() > 0): ?>
                                <?php while ($row = $result->fetch()): ?>
                                    <tr>
                                        <td data-label="ID"><?php echo $row['id_cliente']; ?></td>
                                        <td data-label="Nombre"><?php echo htmlspecialchars($row['nombre'] . ' ' . $row['apellido']); ?></td>
                                        <td data-label="Cédula"><?php echo htmlspecialchars($row['documento_identidad']); ?></td>
                                        <td data-label="Email"><?php echo htmlspecialchars($row['email']); ?></td>
                                        <td data-label="Teléfono"><?php echo htmlspecialchars($row['telefono']); ?></td>
                                        <td data-label="Estado">
                                            <span class="estado-cliente <?php echo strtolower($row['estado']); ?>">
                                                <?php echo $row['estado']; ?>
                                            </span>
                                        </td>
                                        <td data-label="Fecha"><?php echo $row['fecha_registro']; ?></td>
                                        <td data-label="Acciones">
                                            <div class="acciones-fila">
                                                <button class="boton-accion boton-editar btn-ver" data-id="<?php echo $row['id_cliente']; ?>" onclick="verDetallesCliente(<?php echo $row['id_cliente']; ?>)">Ver</button>
                                                <button class="boton-accion boton-editar btn-modificar" data-id="<?php echo $row['id_cliente']; ?>" onclick="modificarCliente(<?php echo $row['id_cliente']; ?>)">Modificar</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" style="text-align: center; padding: 40px; color: rgba(255, 255, 255, 0.7);">
                                            <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
                                                No se encontraron clientes que coincidan con "<?php echo htmlspecialchars($_GET['search']); ?>".
                                                <br><small>Intenta con otros términos de búsqueda.</small>
                                            <?php else: ?>
                                                No hay clientes registrados en el sistema.
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="contenedor-paginacion" id="paginacion">
                    <div class="informacion-paginacion">
                        Mostrando página <?php echo $page; ?> de <?php echo $totalPages; ?>
                    </div>
                    <div class="controles-paginacion">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo isset($_GET['search']) ? urlencode($_GET['search']) : ''; ?>" 
                            class="boton-pagina">
                                <span>← Anterior</span>
                            </a>
                        <?php else: ?>
                            <span class="boton-pagina" disabled>← Anterior</span>
                        <?php endif; ?>

                        <div class="numeros-pagina">
                        <?php 
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        
                        if ($startPage > 1): ?>
                            <a href="?page=1&search=<?php echo isset($_GET['search']) ? urlencode($_GET['search']) : ''; ?>" 
                            class="page-number">1</a>
                            <?php if ($startPage > 2): ?>
                                <span class="pagination-dots">...</span>
                            <?php endif; ?>
                        <?php endif; ?>

                            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo isset($_GET['search']) ? urlencode($_GET['search']) : ''; ?>" 
                                class="boton-pagina <?php echo ($i === $page) ? 'activo' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($endPage < $totalPages): ?>
                                <?php if ($endPage < $totalPages - 1): ?>
                                    <span class="puntos-paginacion">...</span>
                                <?php endif; ?>
                                <a href="?page=<?php echo $totalPages; ?>&search=<?php echo isset($_GET['search']) ? urlencode($_GET['search']) : ''; ?>" 
                                class="boton-pagina"><?php echo $totalPages; ?></a>
                            <?php endif; ?>
                        </div>

                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo isset($_GET['search']) ? urlencode($_GET['search']) : ''; ?>" 
                            class="boton-pagina">
                                <span>Siguiente →</span>
                            </a>
                        <?php else: ?>
                            <span class="boton-pagina" disabled>Siguiente →</span>
                        <?php endif; ?>
                    </div>
                    
                </div>
            </div>

        </main>
    </div>

    <div class="modal" id="view-modal">
        <div class="contenido-modal">
            <div class="cabecera-modal">
                <h3 class="titulo-modal">📋 Detalles del cliente</h3>
                <button class="cerrar-modal" id="close-view-modal" title="Cerrar">×</button>
            </div>
            <div class="cuerpo-modal">
                <div class="client-details-section">
                    <h3 class="modal-subtitle">👤 Información Personal</h3>
                    <p><strong>Nombre:</strong> <div id="detail-nombre">-</div></p>
                    <p><strong>Apellido:</strong> <div id="detail-apellido">-</div></p>
                    <p><strong>Edad:</strong> <div id="detail-edad">-</div></p>
                    <p><strong>Cédula de Identidad:</strong> <div id="detail-cedula">-</div></p>
                    <p><strong>Teléfono:</strong> <div id="detail-telefono">-</div></p>
                    <p><strong>Email:</strong> <div id="detail-email">-</div></p>
                </div>
                <div class="client-details-section">
                    <h3 class="modal-subtitle">💰 Información Financiera</h3>
                    <p><strong>Ingreso Mensual:</strong> <div id="detail-ingresos">-</div></p>
                    <p><strong>Egresos Mensuales:</strong> <div id="detail-egresos">-</div></p>
                    <p><strong>Ocupación:</strong> <div id="detail-ocupacion">-</div></p>
                    <p><strong>Capacidad de pago:</strong> <div id="detail-capacidad">-</div></p>
                </div>
                <div class="pie-modal">
                    <button type="button" class="boton boton-secundario" id="close-view-details">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Ventana emergente de Cliente -->
    <div class="modal modal-nuevo-cliente" id="cliente-modal">
        <div class="contenido-modal">
            <div class="cabecera-modal">
                <h3 class="titulo-modal" id="modal-title">👤 Nuevo cliente</h3>
                <button class="cerrar-modal" id="close-modal" title="Cerrar">×</button>
            </div>
            
            <div class="cuerpo-modal">
                <form id="cliente-form" class="formulario-cliente">
                    <input type="hidden" name="id_cliente" id="id_cliente">
                    
                    <div class="grupo-formulario">
                        <label for="nombre" class="etiqueta-campo campo-obligatorio">Nombre *</label>
                        <input type="text" name="nombre" id="nombre" class="campo-entrada" required placeholder="Ingrese el nombre">
                    </div>
                    
                    <div class="grupo-formulario">
                        <label for="apellido" class="etiqueta-campo campo-obligatorio">Apellido *</label>
                        <input type="text" name="apellido" id="apellido" class="campo-entrada" required placeholder="Ingrese el apellido">
                    </div>
                    
                    <div class="grupo-formulario">
                        <label for="edad" class="etiqueta-campo campo-obligatorio">Edad *</label>
                        <input type="number" name="edad" id="edad" class="campo-entrada" min="18" max="80" required placeholder="Edad (18-80)">
                    </div>
                    
                    <div class="grupo-formulario">
                        <label for="cedula" class="etiqueta-campo campo-obligatorio">Cédula de Identidad *</label>
                        <input type="text" name="documento_identidad" id="cedula" class="campo-entrada" required placeholder="Número de cédula">
                    </div>
                    
                    <div class="grupo-formulario">
                        <label for="telefono" class="etiqueta-campo campo-obligatorio">Teléfono *</label>
                        <input type="tel" name="telefono" id="telefono" class="campo-entrada" required placeholder="Número de teléfono">
                    </div>
                    
                    <div class="grupo-formulario">
                        <label for="email" class="etiqueta-campo campo-obligatorio">Email *</label>
                        <input type="email" name="email" id="email" class="campo-entrada" required placeholder="correo@ejemplo.com">
                    </div>

                    <div class="grupo-formulario grupo-campo-completo">
                        <label for="direccion" class="etiqueta-campo campo-obligatorio">Dirección *</label>
                        <input type="text" name="direccion" id="direccion" class="campo-entrada" required placeholder="Dirección completa">
                    </div>
                    
                    <div class="grupo-formulario">
                        <label for="ingresos" class="etiqueta-campo">Ingresos mensuales ($) *</label>
                        <input type="number" name="ingresos_mensuales" id="ingresos" class="campo-entrada" step="0.01" min="0" required placeholder="0.00">
                    </div>
                    
                    <div class="grupo-formulario">
                        <label for="egresos" class="etiqueta-campo">Egresos mensuales ($) *</label>
                        <input type="number" name="egresos_mensuales" id="egresos" class="campo-entrada" step="0.01" min="0" required placeholder="0.00">
                    </div>
                    
                    <div class="grupo-formulario">
                        <label for="ocupacion" class="etiqueta-campo campo-obligatorio">Ocupación *</label>
                        <input type="text" name="ocupacion" id="ocupacion" class="campo-entrada" required placeholder="Profesión u ocupación">
                    </div>
                    
                    <div class="grupo-formulario">
                        <label for="estado" class="etiqueta-campo">Estado</label>
                        <select name="estado" id="estado" class="campo-entrada selector-estado">
                            <option value="Activo">Activo</option>
                            <option value="Inactivo">Inactivo</option>
                        </select>
                    </div>
                    
                    <div class="grupo-campo-completo grupo-botones">
                        <button type="button" class="boton boton-secundario" id="cancel-btn">Cerrar</button>
                        <button type="submit" class="boton boton-exito" id="save-btn">Agregar cliente</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="../js/sistema-prestamos.js"></script>
    <script src="../js/gestion-clientes.js"></script>
</body>
</html>