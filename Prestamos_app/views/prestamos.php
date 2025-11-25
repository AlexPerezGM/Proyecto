<?php

require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$APP_BASE = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$APP_BASE = preg_replace('#/views$#','', $APP_BASE);
$APP_BASE = ($APP_BASE === '' ? '/' : $APP_BASE . '/');

// Base para rutas JS/API
$BASE = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$BASE = preg_replace('#/views$#','', $BASE);
$BASE = ($BASE === '' ? '/' : $BASE . '/');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Control de Préstamos</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="stylesheet" href="<?= $BASE ?>public/css/dashboard.css">
  <link rel="stylesheet" href="<?= $BASE ?>public/css/clientes.css">
  <link rel="stylesheet" href="<?= $BASE ?>public/css/prestamos.css">
</head>
<body>
  
<div class="app-shell">
  <aside class="sidebar sidebar-expanded">
  <div class="sidebar-inner">

    <!-- DASHBOARD -->
    <div class="sidebar-section">
      <div class="section-label">DASHBOARD</div>

      <a class="nav-link"
         href="<?= $APP_BASE ?>views/dashboard.php">
        <span class="nav-icon">🏠</span>
        <span class="nav-text">Dashboard</span>
      </a>
    </div>

    <!-- GESTIÓN -->
    <div class="sidebar-section">
      <div class="section-label">GESTIÓN</div>

      <a class="nav-link"
         href="<?= $APP_BASE ?>views/clientes.php">
        <span class="nav-icon">👥</span>
        <span class="nav-text">Gestión de Clientes</span>
      </a>

      <a class="nav-link active"
         href="<?= $APP_BASE ?>views/prestamos.php">
        <span class="nav-icon">💼</span>
        <span class="nav-text">Control de Préstamos</span>
      </a>

      <a class="nav-link"
         href="<?= $APP_BASE ?>views/pagos.php">
        <span class="nav-icon">💰</span>
        <span class="nav-text">Gestión de Pagos</span>
      </a>

      <a class="nav-link"
         href="<?= $APP_BASE ?>views/seguimiento.php">
        <span class="nav-icon">📈</span>
        <span class="nav-text">Seguimiento de Préstamos</span>
      </a>

      <a class="nav-link"
         href="<?= $APP_BASE ?>views/reestructuracion.php">
        <span class="nav-icon">♻️</span>
        <span class="nav-text">Reestructuración de Préstamos</span>
      </a>
    </div>

    <!-- ADMINISTRACIÓN -->
    <div class="sidebar-section">
      <div class="section-label">ADMINISTRACIÓN</div>

      <a class="nav-link"
         href="<?= $APP_BASE ?>views/seguridad.php">
        <span class="nav-icon">🔐</span>
        <span class="nav-text">Usuarios y Roles</span>
      </a>

      <a class="nav-link"
         href="<?= $APP_BASE ?>views/rrhh.php">
        <span class="nav-icon">🧑</span>
        <span class="nav-text">Recursos Humanos</span>
      </a>

      <a class="nav-link" href="<?= $APP_BASE ?>views/promociones.php">
        <span class="nav-icon">📅</span>
        <span class="nav-text">Campañas de promoción</span>
      </a>

      <a class="nav-link"
         href="<?= $APP_BASE ?>views/configuracion.php">
        <span class="nav-icon">⚙️</span>
        <span class="nav-text">Configuración</span>
      </a>

      <a class="nav-link"
         href="<?= $APP_BASE ?>api/cerrar_sesion.php">
        <span class="nav-icon">🚪</span>
        <span class="nav-text">Cerrar Sesión</span>
      </a>
    </div>
  </div>

  <div class="sidebar-footer">
    <a class="nav-link footer-link"
       href="<?= $APP_BASE ?>views/perfil.php">
      <span class="nav-icon">👤</span>
      <span class="nav-text">Mi Perfil</span>
    </a>
  </div>
</aside>

  <!-- CONTENIDO -->
  <main class="content-area">
    <header class="topbar topbar-light">
      <div class="topbar-left">
        <div class="brand-inline">
          <span class="brand-logo">🏦</span>
          <span class="brand-name">Control de Préstamos</span>
        </div>
        <span class="range-pill">Módulo de solicitudes, lista y desembolsos</span>
      </div>
      <div class="topbar-right toolbar-end">
        <label class="mini">Moneda</label>
      </div>
      <div class = "topbar-right toolbar-end">
        <select id="selMoneda" class="input w-120"></select>
      </div>
      <div class="topbar-right">
        <?php if (!empty($_SESSION['usuario'])): ?>
        <div class="user-chip">
          <div class="avatar-circle"><?= htmlspecialchars($_SESSION['usuario']['inicial_empleado'] ?? '') ?></div>
          <div class="user-info"><div class="user-name"><?= htmlspecialchars($_SESSION['usuario']['nombre_empleado'] ?? $_SESSION['usuario']['nombre_usuario'] ?? '') ?></div><div class="user-role"><?= htmlspecialchars($_SESSION['usuario']['rol'] ?? '') ?></div></div>
        </div>
        <?php endif; ?>
      </div>

    </header>

    <div class="page-wrapper">
      <div class="tabs">
      </div>
      <section id="sec-solicitar" class="panel">
        <div class="card">
          <div class="card-header">Solicitar préstamo</div>
          <div class="card-body">
            <div class="list-tools">
              <div class="list-tools-inner">
                <input id="qCliente" class="input" placeholder="Buscar cliente por nombre o cédula..." />
                <button id="btnBuscarCliente" class="btn">Buscar</button>
                <button id="btnAbrirCrearCliente" class="btn btn-light">Agregar cliente</button>
              </div>
            </div>

            <div id="boxCliente" class="panel" style="margin-top:12px;">
              <div class="mini">Selecciona un cliente para continuar</div>
              <div id="resClientes"></div>
            </div>

            <div id="boxInfoCliente" class="panel hidden" style="margin-top:12px;">
              <h4 style="margin-top:0;">Información del cliente</h4>
              <div class="info-grid" id="infoClienteGrid"></div>
              <div style="margin-top:12px; display:flex; gap:8px; flex-wrap:wrap;">
                <button id="btnPrestamoPersonal" class="btn">Préstamo personal</button>
                <button id="btnPrestamoHipotecario" class="btn">Préstamo hipotecario</button>
              </div>
            </div>
          </div>
        </div>
      </section>
      <section id="sec-lista" class="panel hidden" style="margin-top: 16px;">
        <div class="card">
          <div class="card-header">Lista de préstamos</div>
          <div class="card-body">
            <div class="inline-form" style="margin-bottom:10px;">
              <input id="qPrestamo" class="input" placeholder="Buscar por nombre, cédula o código..." />
            </div>
            <div class="inline-form" style="margin-bottom:10px;">
              <select id="fTipoPrestamo" class="input w-160">
                <option value="">Todos los tipos</option>
                <option value="Personal">Personal</option>
                <option value="Hipotecario">Hipotecario</option>
              </select>
            </div>
            <div class="inline-form" style="margin-bottom:10px;">
            <button id="btnBuscarPrestamo" class="btn">Buscar</button>
            </div>

            <div class="table-responsive">
              <table class="table-simple" id="tablaPrestamos">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Tipo</th>
                    <th>Monto</th>
                    <th>Tasa</th>
                    <th>Plazo</th>
                    <th>Estado</th>
                    <th>Próximo pago</th>
                    <th>Acciones</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
              <div class="pager" id="pagPrestamos"></div>
            </div>
          </div>
        </div>
      </section>
      <section id="sec-desembolso" class="panel hidden" style="margin-top: 16px;">
        <div class="card">
          <div class="card-header">Desembolso</div>
          <div class="card-body">
            <div class="inline-form" style="margin-bottom:10px;">
              <input id="qDesembolso" class="input" placeholder="Buscar préstamo por nombre/código..." />
            </div>
            <div class="inline-form" style="margin-bottom:10px;">
              <button id="btnBuscarDesembolso" class="btn">Buscar</button>
            </div>

            <div id="boxDesembolso" class="panel hidden" style="margin-top:12px;">
              <h4 style="margin:0 0 8px;">Procesar desembolso</h4>
              <form id="frmDesembolso" class="info-grid">
                <input type="hidden" name="action" value="desembolsar">
                <input type="hidden" name="id_prestamo" id="id_prestamo_des">
                <div>
                  <label class="mini">Cliente / Préstamo</label>
                  <input id="desResumen" class="input" readonly>
                </div>
                <div>
                  <label class="mini">Método de desembolso</label>
                  <select name="metodo_entrega" id="metodo_entrega" class="input"></select>
                </div>
                <div>
                  <label class="mini">Monto a desembolsar</label>
                  <input name="monto_desembolsado" id="monto_desembolsado" class="input" type="number" min="0" step="0.01" required>
                </div>
                <div>
                  <label class="mini">Fecha de desembolso</label>
                  <input name="fecha_desembolso" id="fecha_desembolso" class="input" type="date" required value="<?= date('Y-m-d') ?>">
                </div>
                <div style="grid-column:1/-1; display:flex; gap:8px; justify-content:flex-end;">
                  <button class="btn btn-light" type="button" id="btnRecibo">Generar comprobante</button>
                  <button class="btn" type="submit">Procesar desembolso</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </section>
      <div id="errorBox" class="error-box" hidden></div>
    </div>
  </main>
</div>

<div class="modal" id="modalCrearCliente">
  <div class="modal__dialog">
    <div class="modal__header">
      <h4 style="margin:0;">Registrar cliente</h4>
      <button class="modal__close" data-close>Salir</button>
    </div>
    <div class="modal__body">
      <form id="frmClienteQuick">
        <input type="hidden" name="action" value="create">
        <div class="grid-2">
          <div><label class="mini">Nombre</label><input class="input" name="nombre" required></div>
          <div><label class="mini">Apellido</label><input class="input" name="apellido" required></div>
          <div><label class="mini">Fecha nac.</label><input class="input" type="date" name="fecha_nacimiento" required></div>
          <div><label class="mini">Género</label>
            <select class="input" name="genero">
              <option value="1">Masculino</option>
              <option value="2">Femenino</option>
              <option value="3">Otro</option>
            </select>
          </div>
          <div><label class="mini">Documento</label><input class="input" name="numero_documento" required></div>
          <div><label class="mini">Teléfono</label><input class="input" name="telefono"></div>
          <div><label class="mini">Email</label><input class="input" type="email" name="email"></div>
          <div><label class="mini">Ingresos mensuales</label><input class="input" type="number" step="0.01" name="ingresos_mensuales"></div>
        </div>
        <div class="modal__footer">
          <button class="btn" type="submit">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Solicitud préstamo personal -->
<div class="modal" id="modalPersonal">
  <div class="modal__dialog">
    <div class="modal__header">
      <h4 style="margin:0;">Préstamo personal</h4>
      <button class="modal__close" data-close>Salir</button>
    </div>
    <div class="modal__body">
      <form id="frmPersonal">
        <input type="hidden" name="action" value="crear_personal">
        <input type="hidden" name="id_cliente" id="id_cliente_personal">
        <div class="grid-2">
          <div>
            <label class="mini">Monto solicitado</label>
            <input class="input" name="monto_solicitado" id="monto_personal" type="number" step="0.01" min="10000" required>
          </div>
          <div>
            <label class="mini">Tasa interés (anual %)</label>
            <input class="input" name="tasa_interes" id="tasa_personal" type="number" step="0.01" required readonly>
          </div>
          <div>
            <label class="mini">Plazo (meses)</label>
            <select class="input" name="plazo_meses" id="plazo_personal" required></select>
          </div>
          <div>
            <label class="mini">Fecha solicitud</label>
            <input class="input" name="fecha_solicitud" type="date" required value="<?= date('Y-m-d') ?>">
          </div>
          <div>
            <label class="mini">Tipo de amortización</label>
            <select class="input" name="id_tipo_amortizacion" id="amort_personal" required></select>
          </div>
          <div>
            <label class="mini">Tipo de garantía</label>
            <select class="input" name="tipo_garantia" id="garantia_personal" required></select>
          </div>
          <div>
            <label class="mini">Frecuencia de pagos</label>
            <select class="input" name="id_periodo_pago" id="per_personal" required></select>
          </div>
          <div>
            <label class="mini">Motivo del préstamo</label>
            <input class="input" name="motivo" placeholder="Ej: Gastos médicos, educación, etc.">
          </div>
        </div>

        <!-- NUEVA SECCIÓN DOCUMENTOS ADJUNTOS (PERSONAL) -->
        <div style="margin-top: 16px; border-top: 1px solid #e5e7eb; padding-top: 16px;">
          <h5 style="margin: 0 0 8px 0;">Documentos adjuntos</h5>
          <p class="mini">
            Estos documentos se guardarán en la carpeta del cliente seleccionado.
          </p>
          <div class="grid-2">
            <div>
              <label class="mini">Tipo de documento</label>
              <select id="tipoDocPersonal" class="input" disabled>
                <option value="">Seleccione...</option>
              </select>
              <small class="mini">La lista se ajusta para préstamos personales.</small>
            </div>
            <div>
              <label class="mini">Archivo (PDF/JPG/PNG)</label>
              <input type="file"
                     id="archivoDocPersonal"
                     class="input"
                     accept=".pdf,.jpg,.jpeg,.png"
                     disabled>
              <small class="mini">Se permite un archivo por vez.</small>
            </div>
          </div>
          <div style="margin-top: 8px; display:flex; gap:8px; flex-wrap:wrap;">
            <button type="button" id="btnSubirDocPersonal" class="btn" disabled>Subir documento</button>
            <button type="button" id="btnVerDocsPersonal" class="btn btn-light" disabled>📁 Ver documentos del cliente</button>
          </div>
          <div id="boxDocsPersonal" class="mini" style="margin-top:6px;"></div>
        </div>

        <div class="modal__footer">
          <button class="btn" type="submit">Crear préstamo</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Solicitud préstamo hipotecario -->
<div class="modal" id="modalHipotecario">
  <div class="modal__dialog">
    <div class="modal__header">
      <h4 style="margin:0;">Préstamo hipotecario</h4>
      <button class="modal__close" data-close>Salir</button>
    </div>
    <div class="modal__body">
      <form id="frmHipotecario">
        <input type="hidden" name="action" value="crear_hipotecario">
        <input type="hidden" name="id_cliente" id="id_cliente_hipo">
        
        <div class="grid-2">
          <div>
            <label class="mini">Monto solicitado</label>
            <input class="input" name="monto_solicitado" id="monto_hipo" type="number" step="0.01" min="10000" required>
          </div>
          <div>
            <label class="mini">Tasa interés (anual %)</label>
            <input class="input" name="tasa_interes" id="tasa_hipo" type="number" step="0.01" required readonly>
          </div>
          <div>
            <label class="mini">Plazo (meses)</label>
            <select class="input" name="plazo_meses" id="plazo_hipo" required></select>
          </div>
          <div>
            <label class="mini">Fecha solicitud</label>
            <input class="input" name="fecha_solicitud" type="date" required value="<?= date('Y-m-d') ?>">
          </div>
          <div>
            <label class="mini">Tipo de amortización</label>
            <select class="input" name="id_tipo_amortizacion" id="amort_hipo" required></select>
          </div>
          <div>
            <label class="mini">Tipo de garantía</label>
            <select class="input" name="tipo_garantia" id="garantia_hipo" required></select>
          </div>
          <div>
            <label class="mini">Frecuencia de pagos</label>
            <select class="input" name="id_periodo_pago" id="per_hipo" required></select>
          </div>
          <div>
            <label class="mini">Dirección del inmueble</label>
            <input class="input" name="direccion_propiedad" required placeholder="Dirección completa del inmueble">
          </div>
          <div>
            <label class="mini">Valor del inmueble</label>
            <input class="input" name="valor_propiedad" id="valor_inmueble" type="number" step="0.01" required>
          </div>
          <div>
            <label class="mini">Porcentaje a financiar</label>
            <input class="input" name="porcentaje_financiamiento" id="porc_fin" type="number" min="0" max="80" step="0.01" required readonly>
          </div>
        </div>

        <!-- NUEVA SECCIÓN DOCUMENTOS ADJUNTOS (HIPOTECARIO) -->
        <div style="margin-top: 16px; border-top: 1px solid #e5e7eb; padding-top: 16px;">
          <h5 style="margin: 0 0 8px 0;">Documentos adjuntos</h5>
          <p class="mini">
            Estos documentos se guardarán en la carpeta del cliente seleccionado (título, tasaciones, seguros, etc.).
          </p>
          <div class="grid-2">
            <div>
              <label class="mini">Tipo de documento</label>
              <select id="tipoDocHipotecario" class="input" disabled>
                <option value="">Seleccione...</option>
              </select>
              <small class="mini">La lista se ajusta para préstamos hipotecarios.</small>
            </div>
            <div>
              <label class="mini">Archivo (PDF/JPG/PNG)</label>
              <input type="file"
                     id="archivoDocHipotecario"
                     class="input"
                     accept=".pdf,.jpg,.jpeg,.png"
                     disabled>
              <small class="mini">Se permite un archivo por vez.</small>
            </div>
          </div>
          <div style="margin-top: 8px; display:flex; gap:8px; flex-wrap:wrap;">
            <button type="button" id="btnSubirDocHipotecario" class="btn" disabled>Subir documento</button>
            <button type="button" id="btnVerDocsHipotecario" class="btn btn-light" disabled>📁 Ver documentos del cliente</button>
          </div>
          <div id="boxDocsHipotecario" class="mini" style="margin-top:6px;"></div>
        </div>

        <div class="modal__footer">
          <button class="btn" type="submit">Crear préstamo</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal" id="modalVerPrestamo">
  <div class="modal__dialog">
    <div class="modal__header">
      <h4 style="margin:0;">Detalle de préstamo</h4>
      <button class="btn btn-light" id="btnExportarCronograma">Exportar Cronograma</button>
      <button class="modal__close" data-close>Salir</button>
    </div>
    <div class="modal__body" id="verPrestamoContenido"></div>
  </div>
</div>
<!-- Recibo -->
<div class="modal" id="modalRecibo">
  <div class="modal__dialog">
    <div class="modal__header">
      <h4 style="margin:0;">Comprobante</h4>
      <div style="display:flex; gap:8px;">
        <button class="btn btn-light" id="btnReciboImprimir">Imprimir</button>
        <button class="btn" id="btnReciboDescargar">Descargar PDF</button>
        <button class="modal__close" data-close>Salir</button>
      </div>
    </div>
    <div class="modal__body" id="reciboHTML"></div>
  </div>
</div>
<script>window.APP_BASE = "<?= $BASE ?>";</script>
<script src="<?= $BASE ?>public/JS/prestamos.js"></script>
</body>
</html>
