<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: ../login.php');
    exit;
}
require_once __DIR__ . '/../config/db.php';

$APP_BASE = rtrim(preg_replace('#/views$#','', str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME']))), '/');
$APP_BASE = ($APP_BASE === '' ? '/' : $APP_BASE . '/');

$APP_BASE = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$APP_BASE = preg_replace('#/views$#','', $APP_BASE);
$APP_BASE = ($APP_BASE === '' ? '/' : $APP_BASE . '/');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Campañas de promoción</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= $APP_BASE ?>public/css/dashboard.css">
  <link rel="stylesheet" href="<?= $APP_BASE ?>public/css/clientes.css">
  <link rel="stylesheet" href="<?= $APP_BASE ?>public/css/campanas_promocion.css">
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

      <a class="nav-link" href="<?= $APP_BASE ?>views/rrhh.php">
        <span class="nav-icon">🧑</span>
        <span class="nav-text">Recursos Humanos</span>
      </a>

      <a class="nav-link active"
         href="<?= $APP_BASE ?>views/campanas_promocion.php">
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
</aside>
  <main class="content-area">
    <header class="topbar topbar-light">
      <div class="topbar-left">
        <div class="brand-inline">
          <span class="brand-logo">🎯</span>
          <span class="brand-name">Campañas de promoción</span>
        </div>
        <span class="range-pill">Gestión de campañas y promociones</span>
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
    <div style="margin-top:12px;">
        <div class="grid-3">
            <div class="metric"><div class="metric-value" id="m_prom_activas">0</div><div class="metric-label">Activas</div></div>
            <div class="metric"><div class="metric-value" id="m_prom_usadas">0</div><div class="metric-label">Inactivas</div></div>
            <div class="metric"><div class="metric-value" id="m_prom_vencidas">0</div><div class="metric-label">Vencidas</div></div>
        </div>
    </div>
      <div class="list-tools">
        <div class="list-tools-inner">
          <button id="tabPromociones" class="btn">Promociones</button>
          <button id="tabCampanas" class="btn btn-light">Campañas</button>
        </div>
      </div>
      <!-- Seccion promociones -->
      <section id="secPromociones">
        <div class="card table-card">
          <div class="card-header">Promociones</div>
          <div class="card-body">
            <div class="list-tools">
              <div class="list-tools-inner">
                <input id="qProm" class="input" placeholder="Buscar promociones...">
                <button id="btnBuscarProm" class="btn">Buscar</button>
                <button id="btnNuevoProm" class="btn">Nueva promoción</button>
                <button id="btnAsignarProm" class="btn btn-light">Asignar a clientes</button>
              </div>
            </div>

            <div class="table-responsive">
              <table id="tablaPromociones" class="table-simple">
                <thead>
                  <tr>
                    <th>ID</th><th>Nombre</th><th>Tipo</th><th>Puntos</th>
                    <th>Inicio</th><th>Fin</th><th>Estado</th><th></th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
            <div id="paginacionProm" class="pager"></div>
            <div id="errorProm" class="error-box" hidden></div>
          </div>
        </div>
      </section>

      <!-- Seccion campañas -->
      <section id="secCampanas" style="display:none">
        <div class="card">
          <div class="card-header">Campañas</div>
          <div class="card-body">
            <div class="list-tools">
              <div class="list-tools-inner">
                <input id="qCamp" class="input" placeholder="Buscar campañas...">
                <button id="btnBuscarCamp" class="btn">Buscar</button>
                <button id="btnNuevaCamp" class="btn">Nueva campaña</button>
                <button id="btnAsignPromCamp" class="btn btn-light">Asignar promociones</button>
              </div>
            </div>

            <div class="table-responsive">
              <table id="tablaCampanas" class="table-simple">
                <thead>
                  <tr>
                    <th>ID</th><th>Nombre</th><th>Inicio</th><th>Fin</th><th>Estado</th><th></th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
          </div>
        </div>
      </section>

    </div>
  </main>
</div>

<div class="modal" id="modalProm">
  <div class="modal__dialog">
    <div class="modal__header">
      <h3 id="modalPromTitulo">Nueva promoción</h3>
      <button class="modal__close" data-close>&times;</button>
    </div>
    <div class="modal__body">
      <form id="frmPromocion">
        <input type="hidden" name="action" value="promocion_create" />
        <input type="hidden" name="id_promocion" id="id_promocion" />
        <div class="grid-2">
          <div>
            <label>Nombre</label>
            <input class="input" name="nombre" id="prom_nombre" required>
          </div>
          <div>
            <label>Tipo</label>
            <select class="input" name="tipo_promocion" id="prom_tipo">
              <option value="descuento">Descuento</option>
              <option value="puntos">Canje por puntos</option>
              <option value="bono">Bono</option>
            </select>
          </div>
          <div>
            <label>Puntos necesarios</label>
            <input type="number" min="0" class="input" name="puntos" id="prom_puntos">
          </div>
          <div>
            <label>Estado</label>
            <select class="input" name="estado" id="prom_estado">
              <option value="activa">Activa</option>
              <option value="inactiva">Inactiva</option>
            </select>
          </div>
          <div>
            <label>Fecha inicio</label>
            <input type="date" class="input" name="fecha_inicio" id="prom_inicio">
          </div>
          <div>
            <label>Fecha fin</label>
            <input type="date" class="input" name="fecha_fin" id="prom_fin">
          </div>
          <div style="grid-column:1 / -1;">
            <label>Descripción</label>
            <textarea class="input" name="descripcion" id="prom_descripcion" rows="3"></textarea>
          </div>
        </div>

        <div class="modal__footer">
          <button class="btn" type="submit">Guardar</button>
          <button class="btn btn-light" type="button" data-close>Cancelar</button>
        </div>
      </form>
      <div id="errorPromForm" class="error-box" hidden></div>
    </div>
  </div>
</div>

<div class="modal" id="modalAsignarProm">
  <div class="modal__dialog">
    <div class="modal__header">
      <h3 id="modalAsignarPromTitulo">Asignar promoción a clientes</h3>
      <button class="modal__close" data-close>&times;</button>
    </div>
      <div class="modal__body">
        <form id="frmAsignarProm">
          <div>
            <label>Seleccionar promoción</label>
            <select id="selPromosAsign" class="input" name="id_promocion" required>
              <option value="">Cargando...</option>
            </select>
          </div>
          <div>
            <label>Seleccionar clientes</label>
            <select id="selClientesAsign" class="input" name="clientes[]" multiple size="8"></select>
          </div>
          <div class="modal__footer">
            <button class="btn" type="submit">Asignar</button>
            <button class="btn btn-light" type="button" data-close>Cancelar</button>
          </div>
        </form>
      </div>
  </div>
</div>

<div class="modal" id="modalCamp">
  <div class="modal__dialog">
    <div class="modal__header">
      <h3 id="modalCampTitulo">Nueva campaña</h3>
      <button class="modal__close" data-close>&times;</button>
    </div>
    <div class="modal__body">
      <form id="frmCampana">
        <input type="hidden" name="action" value="campana_create" />
        <input type="hidden" name="id_campana" id="id_campana" />
        <div class="grid-2">
          <div><label>Nombre</label><input class="input" name="nombre" id="camp_nombre" required></div>
          <div><label>Estado</label>
            <select class="input" name="estado" id="camp_estado">
              <option value="inactiva">Inactiva</option>
              <option value="activa">Activa</option>
            </select>
          </div>
          <div><label>Fecha inicio</label><input type="date" class="input" name="fecha_inicio" id="camp_inicio" required></div>
          <div><label>Fecha fin</label><input type="date" class="input" name="fecha_fin" id="camp_fin" required></div>
          <div style="grid-column:1 / -1;"><label>Descripción</label><textarea class="input" name="descripcion" id="camp_descripcion" rows="3"></textarea></div>
        </div>

        <div class="modal__footer">
          <button class="btn" type="submit">Guardar</button>
          <button class="btn btn-light" type="button" data-close>Cancelar</button>
        </div>
      </form>
      <div id="errorCampForm" class="error-box" hidden></div>
    </div>
  </div>
</div>

          <div class="modal" id="modalAsignPromCamp">
            <div class="modal__dialog">
              <div class="modal__header">
                <h3 id="modalAsignPromCampTitulo">Asignar promociones a campaña</h3>
                <button class="modal__close" data-close>&times;</button>
              </div>
              <div class="modal__body">
                <form id="frmAsignPromCamp">
                  <div>
                    <label>Seleccionar campaña</label>
                    <select id="selCampanasAsign" class="input" name="id_campana" required>
                      <option value="">Cargando...</option>
                    </select>
                  </div>
                  <div>
                    <label>Seleccionar promociones</label>
                    <select id="selPromosParaCamp" class="input" name="promociones[]" multiple size="8"></select>
                  </div>
                  <div class="modal__footer">
                    <button class="btn" type="submit">Vincular</button>
                    <button class="btn btn-light" type="button" data-close>Cancelar</button>
                  </div>
                </form>
              </div>
            </div>
          </div>
          
<div class="modal" id="modalVerPromosCamp">
  <div class="modal__dialog">
    <div class="modal__header">
      <h3>Promociones vinculadas</h3>
      <button class="modal__close" data-close>&times;</button>
    </div>
    <div class="modal__body">
      <div class="table-responsive">
        <table class="table-simple" id="tablaPromosCamp">
          <thead>
            <tr>
              <th>ID</th>
              <th>Nombre</th>
              <th>Tipo</th>
              <th>Puntos</th>
              <th>Estado</th>
              <th>Inicio</th>
              <th>Fin</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
    <div class="modal__footer">
      <button class="btn btn-light" data-close>Cerrar</button>
    </div>
  </div>
</div>

<script>window.APP_BASE = <?= json_encode($APP_BASE) ?>;</script>
<script src="<?= $APP_BASE ?>public/JS/campanas_promocion.js"></script>
</body>
</html>
