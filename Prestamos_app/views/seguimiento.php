<?php
// /views/seguimiento.php
require_once __DIR__ . '/../config/db.php';

$APP_BASE = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$APP_BASE = preg_replace('#/views$#','', $APP_BASE);
$APP_BASE = ($APP_BASE === '' ? '/' : $APP_BASE . '/');

$BASE_URL = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$BASE_URL = preg_replace('#/views$#','', $BASE_URL);
$BASE_URL = ($BASE_URL === '' ? '/' : $BASE_URL . '/')

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
         href="<?= $APP_BASE ?>api/cerrar_sesion.php">
        <span class="nav-icon">🚪</span>
        <span class="nav-text">Cerrar Sesión</span>
      </a>
    </div>

  </div><!-- /sidebar-inner -->

  <div class="sidebar-footer">
    <a class="nav-link footer-link"
       href="<?= $APP_BASE ?>views/perfil.php">
      <span class="nav-icon">👤</span>
      <span class="nav-text">Mi Perfil</span>
    </a>
  </div>
</aside>

  <!-- === CONTENIDO === -->
  <main class="content-area">
    <header class="topbar topbar-light">
      <div class="topbar-left">
        <div class="brand-inline">
          <div class="brand-logo">🔎</div>
          <div class="brand-name">Seguimiento de Préstamos</div>
        </div>
      </div>
      <div class="topbar-right">
        <span class="range-pill">Hoy</span>
      </div>
    </header>

    <div class="page-wrapper">
      <div class="tabs">
        <button class="tab-btn active" data-tab="evaluacion">Evaluación</button>
        <button class="tab-btn" data-tab="morosos">Morosos</button>
      </div>

      <div class="layout">
        <!-- Columna izquierda: (moved into Evaluación) -->

        <!-- Contenido principal: ocupar ambas columnas -->
        <div style="grid-column:1 / -1;">
          <!-- EVALUACIÓN -->
          <div id="tabEvaluacion">
            <!-- Lista de Préstamos (moved arriba de Evaluación) -->
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

            <!-- Evaluación (abajo) -->
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
                <!-- Datacrédito / Buro -->
                <div style="margin-top:6px;">
                  <h4>Verificación Datacrédito</h4>
                  <div style="display:flex; gap:8px; align-items:center;">
                    <button id="btnCheckData" class="btn btn-light">Verificar Datacrédito</button>
                    <div id="evDataResult" style="font-size:.95rem; color:var(--muted,#333);">—</div>
                  </div>
                </div>
                <hr style="margin:14px 0;">
                <!-- Documentos -->
                <div>
                  <h4>Documentos del cliente</h4>
                  <div id="evDocs" class="preview-box">No se han cargado los documentos.</div>
                </div>
                <div style="margin-top:12px; display:flex; gap:8px; align-items:center;">
                  <button id="btnAprobar" class="btn">Aprobar</button>
                  <button id="btnRechazar" class="btn btn-light">Rechazar</button>
                  <div style="margin-left:12px;">&nbsp;</div>
                </div>
              </div>
            </div>
          </div>

          <!-- MOROSOS -->
          <div id="tabMorosos" style="display:none;">
            <div class="card">
              <div class="card-header">Gestión de préstamos morosos</div>
              <div class="card-body">
                <div class="mini-grid">
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
                  <button id="btnAbrirNotif" class="btn">Notificar al cliente</button>
                </div>
              </div>
            </div>

            <!-- Configuración de notificaciones -->
            <div class="card" style="margin-top:16px;">
              <div class="card-header">Configuración de notificaciones</div>
              <div class="card-body">
                <div class="cfg-grid">
                  <div>
                    <h4>Pago próximo</h4>
                    <label>Días de anticipación</label>
                    <input type="number" min="0" id="cfgDiasAnt" class="input" placeholder="Ej: 2">
                    <label>Método de envío</label>
                    <select id="cfgMetodo" class="input">
                      <option value="email">Email</option>
                      <option value="sms">SMS</option>
                    </select>
                  </div>
                  <div>
                    <h4>Pago vencido</h4>
                    <label>Fecha de envío (primer aviso)</label>
                    <input type="date" id="cfgFechaVenc" class="input">
                    <label>Escalamiento (JSON)</label>
                    <textarea id="cfgEscalamiento" class="input" placeholder='[{"dias":5,"canal":"email"},{"dias":10,"canal":"sms"}]'></textarea>
                  </div>
                </div>

                <h4 style="margin-top:12px;">Plantillas</h4>
                <div style="display:flex; gap:8px; align-items:flex-end;">
                  <select id="selPlantilla" class="input" style="max-width:360px;"></select>
                  <button id="btnPreview" class="btn btn-light">Vista previa</button>
                  <button id="btnGuardarCfg" class="btn">Guardar configuración</button>
                </div>

                <div id="previewBox" class="preview-box" style="margin-top:10px;">Seleccione una plantilla…</div>
              </div>
            </div>
          </div>

        </div>
      </div>

    </div>
  </main>
</div>

<!-- Modal Notificar -->
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
          <input id="nfEmail" class="input" placeholder="cliente@correo.com">
          <label>Asunto</label>
          <input id="nfAsunto" class="input" placeholder="Asunto">
        </div>
        <div>
          <label>Plantilla</label>
          <select id="nfPlantilla" class="input"></select>
          <label>Vista previa</label>
          <div id="nfPreview" class="preview-box" style="min-height:120px;"></div>
        </div>
      </div>
      <label>Mensaje</label>
      <textarea id="nfMensaje" class="input" rows="6"></textarea>
    </div>
    <div class="modal__footer">
      <button class="btn btn-light" data-close>Cancelar</button>
      <button id="btnEnviarNotif" class="btn">Enviar</button>
    </div>
  </div>
</div>

<!-- Modal Detalle del préstamo -->
<div class="modal" id="modalDetalle">
  <div class="modal__dialog modal__dialog--wide">
    <div class="modal__header">
      <div>Detalle del préstamo</div>
      <button class="modal__close" data-close>✕</button>
    </div>
    <div class="modal__body">
      <div id="detalleContent" style="min-height:160px; white-space:pre-wrap; font-family:monospace; font-size:0.95rem;">Cargando…</div>
    </div>
    <div class="modal__footer">
      <button class="btn btn-light" data-close>Cerrar</button>
    </div>
  </div>
</div>

  <script>window.APP_BASE = "<?= $BASE_URL ?>";</script>
  <script src="public/JS/seguimiento.js?v=1"></script>
</body>
</html>
