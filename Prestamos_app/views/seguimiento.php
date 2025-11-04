<?php
// /views/seguimiento.php
require_once __DIR__ . '/../config/db.php';

// Raíz base para URLs (idéntico a tus otros módulos)
$APP_BASE = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$APP_BASE = preg_replace('#/views$#','', $APP_BASE);
$APP_BASE = ($APP_BASE === '' ? '/' : $APP_BASE . '/');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Seguimiento de Préstamos</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Reutiliza tus mismos estilos base -->
  <link rel="stylesheet" href="<?= $APP_BASE ?>assets/css/dashboard.css">
  <link rel="stylesheet" href="<?= $APP_BASE ?>assets/css/clientes.css">
  <style>
    /* Ajustes pequeños específicos del módulo */
    .tabs { display:flex; gap:8px; margin-bottom:12px; }
    .tab-btn { border:1px solid var(--border-soft); background:#fff; padding:8px 12px; border-radius:8px; cursor:pointer; }
    .tab-btn.active { background:#e0e7ff; border-color:#6366f1; color:#4338ca; font-weight:600; }
    .layout { display:grid; grid-template-columns: 380px 1fr; gap:16px; }
    @media (max-width: 1100px){ .layout { grid-template-columns: 1fr; } }
    .sticky-col { position: sticky; top: 84px; align-self:start; }
    .list-tools .input { min-width: 0; }
    .pill { display:inline-block; padding:4px 8px; border-radius:999px; font-size:.75rem; }
    .pill.activo { background:#ecfdf5; color:#065f46; border:1px solid #10b981; }
    .pill.mora { background:#fee2e2; color:#991b1b; border:1px solid #ef4444; }
    .pill.pendiente { background:#fef9c3; color:#78350f; border:1px solid #eab308; }
    .mini-grid { display:grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap:10px; }
    .mini-grid > div { background:#fff; border:1px solid var(--border-soft); border-radius:10px; padding:10px; }
    .cfg-grid { display:grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap:10px; }
    @media (max-width: 720px){ .cfg-grid { grid-template-columns: 1fr; } }
    .preview-box { border:1px dashed var(--border-soft); background:#fff; border-radius:10px; padding:12px; white-space:pre-wrap; }
  </style>
  <script>window.APP_BASE = "<?= $APP_BASE ?>";</script>
</head>
<body>
<div class="app-shell">
  <!-- === SIDEBAR (idéntico a tus páginas) === -->
  <aside class="sidebar sidebar-expanded">
    <div class="sidebar-inner">

      <!-- DASHBOARD -->
      <div class="sidebar-section">
        <div class="section-label">DASHBOARD</div>
        <a class="nav-link" href="<?= $APP_BASE ?>index.php">
          <span class="nav-icon">🏠</span>
          <span class="nav-text">Dashboard</span>
        </a>
      </div>

      <!-- GESTIÓN -->
      <div class="sidebar-section">
        <div class="section-label">GESTIÓN</div>

        <a class="nav-link" href="<?= $APP_BASE ?>views/clientes.php">
          <span class="nav-icon">👥</span>
          <span class="nav-text">Gestión de Clientes</span>
        </a>

        <a class="nav-link" href="<?= $APP_BASE ?>views/prestamos.php">
          <span class="nav-icon">💼</span>
          <span class="nav-text">Control de Préstamos</span>
        </a>

        <a class="nav-link active" href="<?= $APP_BASE ?>views/seguimiento.php">
          <span class="nav-icon">🔎</span>
          <span class="nav-text">Seguimiento de Préstamos</span>
        </a>

        <a class="nav-link" href="<?= $APP_BASE ?>views/pagos.php">
          <span class="nav-icon">💳</span>
          <span class="nav-text">Gestión de Pagos</span>
        </a>

        <!-- Otras rutas futuras que ya tienes referenciadas -->
        <a class="nav-link" href="<?= $APP_BASE ?>views/reportes.php">
          <span class="nav-icon">📊</span>
          <span class="nav-text">Reportes</span>
        </a>
        <a class="nav-link" href="<?= $APP_BASE ?>views/configuracion.php">
          <span class="nav-icon">⚙️</span>
          <span class="nav-text">Configuración</span>
        </a>
      </div>

      <div class="sidebar-footer">
        <p class="footer-link">© Tu App</p>
      </div>
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
        <!-- Columna izquierda: LISTA (siempre visible) -->
        <div class="sticky-col">
          <div class="card">
            <div class="card-header">Préstamos</div>
            <div class="card-body">
              <div class="list-tools">
                <div class="list-tools-inner">
                  <input id="qSeg" class="input" placeholder="Buscar por nombre…">
                  <button id="btnBuscarSeg" class="btn">Buscar</button>
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
                      <th>Próx./Últ. Venc.</th>
                      <th>Mora</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
              </div>
              <div class="pager" id="paginacionSeg"></div>

              <div id="errorBoxSeg" class="error-box" hidden></div>
            </div>
          </div>
        </div>

        <!-- Columna derecha: CONTENIDOS POR PESTAÑA -->
        <div>
          <!-- EVALUACIÓN -->
          <div id="tabEvaluacion">
            <div class="card">
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

                <div style="margin-top:12px; display:flex; gap:8px;">
                  <button id="btnAprobar" class="btn">Aprobar</button>
                  <button id="btnRechazar" class="btn btn-light">Rechazar</button>
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

<script src="<?= $APP_BASE ?>assets/js/seguimiento.js" defer></script>
</body>
</html>
