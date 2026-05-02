<?php
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../api/autorizacion.php';
requiere_login();
require_permission('seguimiento', 'admin');

$APP_BASE = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$APP_BASE = preg_replace('#/views$#','', $APP_BASE);
$APP_BASE = ($APP_BASE === '' ? '/' : $APP_BASE . '/');

$BASE_URL = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$BASE_URL = preg_replace('#/views$#','', $BASE_URL);
$BASE_URL = ($BASE_URL === '' ? '/' : $BASE_URL . '/');

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Seguimiento de Préstamos</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <base href="<?= $BASE_URL ?>">
  <link rel="stylesheet" href="public/css/dashboard.css?v=1">
  <link rel="stylesheet" href="public/css/clientes.css?v=1">
  <link rel="stylesheet" href="public/css/seguimiento.css?v=1">
  <script>window.APP_BASE = "<?= $APP_BASE ?>";</script>
</head>
<body>
<div class="app-shell">
  <aside class="sidebar sidebar-expanded">
  <div class="sidebar-inner">
    <div class="sidebar-section">
      <div class="section-label">DASHBOARD</div>
      <a class="nav-link"
         href="<?= $APP_BASE ?>views/dashboard.php">
        <span class="nav-icon">🏠</span>
        <span class="nav-text">Dashboard</span>
      </a>
    </div>
    <div class="sidebar-section">
      <div class="section-label">GESTIÓN</div>
      <a class="nav-link"
         href="<?= $APP_BASE ?>views/clientes.php">
        <span class="nav-icon">👥</span>
        <span class="nav-text">Gestión de Clientes</span>
      </a>

      <a class="nav-link"
         href="<?= $APP_BASE ?>views/prestamos.php">
        <span class="nav-icon">💼</span>
        <span class="nav-text">Control de Préstamos</span>
      </a>

      <a class="nav-link"
         href="<?= $APP_BASE ?>views/pagos.php">
        <span class="nav-icon">💰</span>
        <span class="nav-text">Gestión de Pagos</span>
      </a>

      <a class="nav-link active"
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
</aside>
  <main class="content-area">
    <header class="topbar topbar-light">
      <div class="topbar-left">
        <div class="brand-inline">
          <div class="brand-logo">🔎</div>
          <div class="brand-name">Seguimiento de Préstamos</div>
        </div>
      <span class="range-pill">Reestructurar préstamos</span>
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
        <button class="tab-btn active" data-tab="evaluacion">Evaluación</button>
        <button class="tab-btn" data-tab="morosos">Morosos</button>
        <button id="btnHistorial" class="btn btn-light" style="margin-left: 10px; display:flex; align-items: center; gap: 5px;">
          Historial de evaluaciones</button>

        <button id="btnAutoNotify" class="btn btn-primary" style="display:none; margin-left: auto; background-color: #e53e3e; color: white; border: none;">
          Notificar clientes en Mora
        </button>
      </div>
      <div class="layout">
        <div style="grid-column:1 / -1;">
          <div id="tabEvaluacion">
            <div class="card">
              <div class="card-header">Préstamos</div>
              <div class="card-body">
                <div class="list-tools">
                  <div class="list-tools-inner">
                    <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                      <input id="qSeg" class="input" placeholder="Buscar por nombre o cédula..." style="min-width:180px;">
                      <button id="btnBuscarSeg" class="btn">Buscar</button>
                      <button id="btnExportSeg" class="btn btn-light">Exportar CSV</button>
                    </div>
                  </div>
                </div>
                <div class="table-responsive">
                  <table class="table-simple" id="tablaPrestamos">
                    <thead>
                      <tr>
                        <th>ID</th>
                        <th>Cliente</th>
                        <th>Estado</th>
                        <th>Monto</th>
                        <th>Plazo</th>
                        <th>Fecha Inicio</th>
                        <th>Capacidad de Pago</th>
                        <th></th>
                      </tr>
                    </thead>
                    <tbody></tbody>
                  </table>
                </div>
                <div class="pager" id="paginacionSeg"></div>
                <div id="errorBoxSeg" class="error-box" hidden></div>
                <div id="spinnerSeg" class="spinner" hidden aria-hidden="true"></div>
              </div>
            </div>
            <div class="card" style="margin-top:12px;" id="cardEvaluacion">
              <div class="card-header">Evaluación del préstamo</div>
              <div class="card-body">
                <div class="mini-grid">
                  <div>
                    <b>Cliente</b>
                    <div id="evCliente">—</div>
                  </div>
                  <div>
                    <b>Monto solicitado</b>
                    <div id="evMonto">—</div>
                  </div>
                  <div>
                    <b>Ingresos mensuales</b>
                    <div id="evIngresos">—</div>
                  </div>
                  <div>
                    <b>Egresos mensuales</b>
                    <div id="evEgresos">—</div>
                  </div>
                  <div>
                    <b>Capacidad de pago</b>
                    <div id="evCapacidad">—</div>
                  </div>
                  <div>
                    <b>Nivel de riesgo</b>
                    <select id="evRiesgo"></select>
                  </div>
                </div>
                <hr style="margin:14px 0;">
                <div style="margin-top:6px;">
                  <h4>Verificación Datacrédito</h4>
                  <div>
                    <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                      <button id="btnCheckData" class="btn btn-light">Verificar Datacrédito</button>
                    </div>
                    <div id="evDataResult" class="card card-body ev-data-result">—</div>
                  </div>
                </div>
                <hr style="margin:14px 0;">
                <div>
                  <h4>Documentos del cliente</h4>
                   <button id="btnVerDocsEvaluacion" class="btn btn-light" disabled>Ver documentos del cliente</button>
                </div>
                <div style="margin-top:12px; display:flex; gap:8px; align-items:center;">
                  <button id="btnAprobar" class="btn">Aprobar</button>
                  <button id="btnRechazar" class="btn btn-light">Rechazar</button>
                  <div style="margin-left:12px;">&nbsp;</div>
                </div>
                <div style="margin-top:12px;">
                  <button class="btn js-abrir-notif">Notificar al cliente</button>
                </div>
              </div>
            </div>
          </div>
          <div id="tabMorosos" style="display:none;">
            <div class="card">
              <div class="card-header">Lista de Préstamos en Mora</div>
              <div class="card-body">
                <div class="list-tools">
                  <div class="list-tools-inner">
                    <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                      <input id="qMorosos" class="input" placeholder="Buscar por nombre o cédula..." style="min-width:180px;">
                      <button id="btnBuscarMorosos" class="btn">Buscar</button>
                      <button id="btnExportMorosos" class="btn btn-light">Exportar CSV</button>
                    </div>
                  </div>
                </div>
                <div class="table-responsive">
                  <table class="table-simple" id="tablaMorosos">
                    <thead>
                      <tr>
                        <th>ID</th>
                        <th>Cliente</th>
                        <th>Monto Préstamo</th>
                        <th>Deuda en Mora</th>
                        <th>Días Atraso</th>
                        <th>Cuotas Vencidas</th>
                        <th>Plazo</th>
                        <th>Fecha Inicio</th>
                        <th></th>
                      </tr>
                    </thead>
                    <tbody></tbody>
                  </table>
                </div>
                <div class="pager" id="paginacionMorosos"></div>
                <div id="errorBoxMorosos" class="error-box" hidden></div>
                <div id="spinnerMorosos" class="spinner" hidden aria-hidden="true"></div>
              </div>
            </div>
            <div class="card" style="margin-top:12px;" id="cardGestionMoroso">
              <div class="card-header">Seleccion de prestamos en mora</div>
              <div class="card-body">
                <div class="mini-grid">
                  <div>
                    <b>Cliente</b>
                    <div id="moCliente">— Seleccione un préstamo en mora</div>
                  </div>
                  <div>
                    <b>Estado del préstamo</b>
                    <div id="moEstado">—</div>
                  </div>
                  <div>
                    <b>Monto acumulado (con mora)</b>
                    <div id="moMonto">—</div>
                  </div>
                  <div>
                    <b>Días de atraso</b>
                    <div id="moDias">—</div>
                  </div>
                </div>
                <hr style="margin:14px 0;">
                <div>
                  <button class="btn js-abrir-notif">Notificar al cliente</button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>
<!-- Enviar Notificación -->
<div class="modal" id="modalNotif">
  <div class="modal__dialog">
    <div class="modal__header">
      <div>Enviar notificación</div>
      <button class="modal__close" data-close>✕</button>
    </div>
    <div class="modal__body">
      <div class="grid-2">
        <div>
          <label>Para (email)</label>
          <input name="correo" id="nfEmail" class="input" placeholder="cliente@correo.com">
          <label>Asunto</label>
          <input name="asunto" id="nfAsunto" class="input" placeholder="Asunto">
        </div>
        <div>
          <label>Plantilla</label>
          <select id="nfPlantilla" class="input"></select>
          <label>Vista previa</label>
          <div id="nfPreview" class="preview-box" style="min-height:120px;"></div>
        </div>
      </div>
      <label>Mensaje</label>
      <textarea name="mensaje" id="nfMensaje" class="input" rows="6"></textarea>
    </div>
    <div class="modal__footer">
      <button class="btn btn-light" data-close>Cancelar</button>
      <button id="btnEnviarNotif" class="btn">Enviar</button>
    </div>
  </div>
</div>
<!-- Historial de evaluaciones -->
<div class="modal" id="modalHistorial">
  <div class="modal__dialog modal__dialog--lg">
    <div class="modal__header">
      <div>Historial de evaluaciones</div>
      <button class="modal__close" data-close>✕</button>
    </div>
    <div class="modal__body">
      <div class="table-container">
        <table class="table-simple" id="tablaHistorial">
          <thead>
            <tr>
              <th> ID prestamo</th>
              <th>Cliente</th>
              <th>Fecha evaluación</th>
              <th>Resultado</th>
              <th style="text-align:right;">Accion</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<script>window.APP_BASE = "<?= $APP_BASE ?>";</script>
<script src="public/JS/seguimiento.js?v=1"></script>
</body>
</html>
