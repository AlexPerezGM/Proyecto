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
    <title>Gestión de Pagos - Sistema de Préstamos</title>
</head>
<body class="pagina-gestion-pagos">
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
                <h2>Gestión de Pagos</h2>
                <p>Administrar pagos y cuotas de préstamos</p>
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
                    <a href="../Views/control_prestamos.php" class="elemento-navegacion">
                        <div class="icono-navegacion">💰</div>
                        Control de Préstamos
                    </a>
                    <a href="../Views/gestion_pagos.php" class="elemento-navegacion activo">
                        <div class="icono-navegacion">💳</div>
                        Gestión de Pagos
                    </a>
                    <a href="../Views/seguimiento_prestamos.php" class="elemento-navegacion">
                        <div class="icono-navegacion">📊</div>
                        Seguimiento de Préstamos
                    </a>
                    <a href="manage_users.php" class="elemento-navegacion">
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
            <div class="contenedor-gestion-pagos">
                <!-- Columna izquierda: Seleccionar préstamo -->
                <div class="columna-izquierda-pagos">
                    <div class="seccion-seleccionar-prestamo">
                        <div class="titulo-seccion-pagos">
                            🔍 Seleccionar préstamo
                        </div>
                        
                        <div class="busqueda-prestamo">
                            <label class="etiqueta-busqueda">Buscar préstamo por cliente</label>
                            <div class="contenedor-busqueda">
                                <input type="text" id="searchInput" class="campo-busqueda-prestamo" placeholder="Buscar por nombre o ID">
                                <button class="boton-buscar-prestamo" onclick="buscarPrestamos()">Buscar</button>
                            </div>
                        </div>

                        <div class="informacion-prestamo" id="loanDetails">
                            <div class="fila-info-prestamo">
                                <div class="etiqueta-info-prestamo">Estado del préstamo</div>
                                <div class="valor-info-prestamo activo-atrasado" id="loanStatus">Activo, moroso, atrasado</div>
                            </div>

                            <div class="fila-info-prestamo">
                                <div class="etiqueta-info-prestamo">Saldo pendiente</div>
                                <div class="valor-info-prestamo" id="loanBalance">$55,000.00</div>
                            </div>

                            <div class="fila-info-prestamo">
                                <div class="etiqueta-info-prestamo">Monto a pagar</div>
                                <div class="valor-info-prestamo" id="loanAmountDue">$5,000.00</div>
                            </div>

                            <div class="fila-info-prestamo">
                                <div class="etiqueta-info-prestamo">Mora</div>
                                <div class="valor-info-prestamo" id="loanPenalty">$300.00</div>
                            </div>

                            <div class="fila-info-prestamo">
                                <div class="etiqueta-info-prestamo">Monto con mora</div>
                                <div class="valor-info-prestamo" id="loanAmountWithPenalty">$5,200.00</div>
                            </div>

                            <button class="boton-aplicar-mora" onclick="aplicarMora()">Aplicar mora</button>
                        </div>
                    </div>
                </div>

                <!-- Columna derecha: Tipo de pago y acciones -->
                <div class="columna-derecha-pagos">
                    <div class="seccion-tipo-pago">
                        <div class="titulo-seccion-pagos">
                            Seleccionar tipo de pago
                        </div>
                        
                        <div class="opciones-pago">
                            <div class="opcion-pago efectivo" onclick="seleccionarTipoPago('efectivo')">
                                <div class="icono-pago">💵</div>
                                <div class="texto-pago">Pago en efectivo</div>
                            </div>
                            <div class="opcion-pago transferencia" onclick="seleccionarTipoPago('transferencia')">
                                <div class="icono-pago">🏦</div>
                                <div class="texto-pago">Transferencia</div>
                            </div>
                            <div class="opcion-pago garantia" onclick="seleccionarTipoPago('garantia')">
                                <div class="icono-pago">🔒</div>
                                <div class="texto-pago">Pago con garantía</div>
                            </div>
                        </div>
                    </div>

                    <div class="seccion-cerrar-prestamo">
                        <div class="titulo-seccion-pagos">
                            Cerrar préstamo
                        </div>
                        <button class="boton-cerrar-prestamo" onclick="cerrarPrestamo()">Cerrar prestamo</button>
                    </div>
                </div>
            </div>
        </main>

<!--modal pago en efectivo-->
    <div class="overlay-modal" id="modalEfectivo">
        <div class="contenido-modal">
            <div class="cabecera-modal">
                <h3 class="titulo-modal">Pago en efectivo</h3>
                <button class="boton-cerrar-modal" onclick="cerrarModal('modalEfectivo')">×</button>
            </div>
            <div class="cuerpo-modal">
                <div class="grupo-formulario">
                    <label class="etiqueta-formulario">Registrar monto entregado</label>
                    <input type="number" id="montoEntregado" class="campo-formulario" placeholder="$0.00">
                </div>
                <div class="grupo-formulario">
                    <label class="etiqueta-formulario">Tipo de moneda</label>
                    <select id="tipoMoneda" class="campo-formulario">
                        <option value="USD">Dólares (USD)</option>
                        <option value="DOP">Pesos dominicanos (DOP)</option>
                    </select>
                </div>
                
                <div class="grupo-formulario">
                    <label class="etiqueta-formulario">Observaciones</label>
                    <textarea id="observacionesEfectivo" class="campo-formulario" placeholder="Agregar observaciones..." rows="4">El pago fue realizado sin problemas</textarea>
                </div>
            </div>
            <div class="pie-modal">
                <button class="boton-secundario" onclick="cerrarModal('modalEfectivo')">Cancelar</button>
                <button class="boton-exito" onclick="confirmarPagoEfectivo()">Confirmar pago</button>
            </div>
        </div>
    </div>

    <!--modal pago por transferencia-->
    <div class="overlay-modal" id="modalTransferencia">
        <div class="contenido-modal">
            <div class="cabecera-modal">
                <h3 class="titulo-modal">Pago por transferencia</h3>
                <button class="boton-cerrar-modal" onclick="cerrarModal('modalTransferencia')">×</button>
            </div>
            <div class="cuerpo-modal">
                <div class="grupo-formulario">
                    <label class="etiqueta-formulario">Registrar numero de transferencia bancaria</label>
                    <input type="text" id="numeroTransferencia" class="campo-formulario" placeholder="Número de transferencia">
                </div>
                <div class="grupo-formulario">
                    <label class="etiqueta-formulario">Registrar monto transferido</label>
                    <input type="number" id="montoTransferido" class="campo-formulario" placeholder="$0.00">
                </div>
                <div class="grupo-formulario">
                    <label class="etiqueta-formulario">Tipo de moneda</label>
                    <select id="tipoMoneda" class="campo-formulario">
                        <option value="USD">Dólares (USD)</option>
                        <option value="DOP">Pesos dominicanos (DOP)</option>
                    </select>
                </div>
                
                <div class="grupo-formulario">
                    <label class="etiqueta-formulario">Observaciones</label>
                    <textarea id="observacionesTransferencia" class="campo-formulario" placeholder="Agregar observaciones..." rows="4">El pago fue realizado sin problemas</textarea>
                </div>
            </div>
            <div class="pie-modal">
                <button class="boton-secundario" onclick="cerrarModal('modalTransferencia')">Cancelar</button>
                <button class="boton-exito" onclick="confirmarPagoTransferencia()">Confirmar pago</button>
            </div>
        </div>
    </div>

    <!--modal retiro de garantia-->
    <div class="overlay-modal" id="modalGarantias">
        <div class="contenido-modal">
            <div class="cabecera-modal">
                <h3 class="titulo-modal">Retiro de garantias</h3>
                <button class="boton-cerrar-modal" onclick="cerrarModal('modalGarantias')">×</button>
            </div>
            <div class="cuerpo-modal">
                <div class="grupo-formulario">
                    <label class="etiqueta-formulario">Motivo del retiro</label>
                    <input type="text" id="motivoRetiro" class="campo-formulario" placeholder="Motivo del retiro">
                </div>
                <div class="grupo-formulario">
                    <label class="etiqueta-formulario">Valor de la garantia</label>
                    <input type="number" id="valorGarantia" class="campo-formulario" placeholder="$0.00">
                </div>
                
                <div class="grupo-formulario">
                    <label class="etiqueta-formulario">Observaciones</label>
                    <textarea id="observacionesGarantia" class="campo-formulario" placeholder="Agregar observaciones..." rows="4">El cliente no cumplio con los terminos del contrato...</textarea>
                </div>
            </div>
            <div class="pie-modal">
                <button class="boton-secundario" onclick="cerrarModal('modalGarantias')">Cancelar</button>
                <button class="boton-peligro" onclick="confirmarPagoGarantias()">Confirmar retiro</button>
            </div>
        </div>
    </div>

    </main>
    </div>
    
    <script src="../js/sistema-prestamos.js"></script>
</body>
</html>