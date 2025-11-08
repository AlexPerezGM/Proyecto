<?php
// views/restructuracion.php
require_once __DIR__ . '/../config/db.php';
$BASE = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$BASE = preg_replace('#/views$#','', $BASE);
$BASE = ($BASE === '' ? '/' : $BASE . '/');

// Base de la app para URLs absolutas tipo /Prestamos_app/
$APP_BASE = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$APP_BASE = preg_replace('#/views$#','', $APP_BASE);
$APP_BASE = ($APP_BASE === '' ? '/' : $APP_BASE . '/');

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Restructuración de Préstamos</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <script>window.APP_BASE = <?= json_encode($APP_BASE) ?>;</script>

  <!-- CSS base del dashboard y clientes (reuso de clases/estética) -->
  <link rel="stylesheet" href="<?= $APP_BASE ?>public/css/dashboard.css">
  <link rel="stylesheet" href="<?= $APP_BASE ?>public/css/clientes.css">
  <link rel="stylesheet" href="<?= $APP_BASE ?>public/css/prestamos.css">
  <link rel="stylesheet" href="<?= $APP_BASE ?>public/css/reestructuracon.css">
</head>
<body>
<div class="app-shell">
  <!-- ===== Sidebar (mismo markup) ===== -->
  <div class="app-shell">
  <aside class="sidebar sidebar-expanded">
  <div class="sidebar-inner">

    <!-- DASHBOARD -->
    <div class="sidebar-section">
      <div class="section-label">DASHBOARD</div>

      <a class="nav-link"
         href="<?= $APP_BASE ?>index.php">
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

      <a class="nav-link active"
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
         href="<?= $APP_BASE ?>logout.php">
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
  <!-- ===== Contenido ===== -->
  <main class="content-area">
    <div class="page-wrapper">

      <div class="topbar topbar-light">
        <div class="topbar-left">
          <div class="brand-inline">
            <div class="brand-logo">🛠️</div>
            <div class="brand-name">Reestructuración de Préstamos</div>
          </div>
          <span class="range-pill">Supervisor</span>
        </div>
        <div class="topbar-right">
          <div class="user-chip">
            <div class="avatar-circle">RV</div>
            <div class="user-info"><div class="user-name">Ricardo</div><div class="user-role">Admin</div></div>
          </div>
        </div>
      </div>

      <div class="grid-page">
        <!-- LISTADO -->
        <section class="panel">
          <div class="panel-header">Seleccionados para reestructuración <small class="tag" id="totalTag"></small></div>
          <div class="panel-body">
            <div class="list-tools">
              <div class="list-tools-inner">
                <input id="q" class="input" placeholder="Buscar por nombre, apellido o documento…">
                <button id="btnBuscar" class="btn">Buscar</button>
              </div>
            </div>

            <div class="table-responsive table-card">
              <table class="table-simple" id="tablaRestructuracion">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Cliente</th>
                    <th>Documento</th>
                    <th>Monto</th>
                    <th>Plazo (meses)</th>
                    <th>Acciones</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>

            <div class="pager" id="paginacion"></div>
            <div class="error-box" id="errorBox" hidden></div>
          </div>
        </section>
      </div>
    </div>
  </main>
</div>

<!-- ===== Modal Formulario ===== -->
<div class="modal" id="modalForm">
  <div class="modal__dialog">
    <div class="modal__header">
      <div><b id="modalTitulo">Reestructurar préstamo</b></div>
      <button class="modal__close" data-close>Cerrar</button>
    </div>
    <div class="modal__body">
      <form id="frmRestructurar">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id_prestamo" id="id_prestamo">
        <div class="grid-2">
          <div>
            <label>Cliente</label>
            <input class="input" id="cliente_lbl" disabled>
          </div>
          <div>
            <label>Documento</label>
            <input class="input" id="doc_lbl" disabled>
          </div>

          <div>
            <label>Monto actual</label>
            <input class="input" id="monto_actual" disabled>
          </div>
          <div>
            <label>Plazo actual (meses)</label>
            <input class="input" id="plazo_actual" disabled>
          </div>

          <div>
            <label>Nueva tasa de interés (%)</label>
            <input class="input" type="number" step="0.01" name="nueva_tasa" id="nueva_tasa" placeholder="Ej. 12.50">
          </div>
          <div>
            <label>Nuevo monto solicitado</label>
            <input class="input" type="number" step="0.01" name="nuevo_monto" id="nuevo_monto" required>
          </div>
          <div>
            <label>Nuevo plazo (meses)</label>
            <input class="input" type="number" name="nuevo_plazo" id="nuevo_plazo" required>
          </div>
          <div>
            <label>Nueva fecha de pago (próxima cuota)</label>
            <input class="input" type="date" name="nueva_fecha" id="nueva_fecha">
          </div>

          <div style="grid-column:1/-1">
            <label>Motivo de reestructuración</label>
            <textarea class="input" name="motivo" id="motivo" rows="3" placeholder="Describe brevemente el motivo…"></textarea>
          </div>
        </div>
        <div class="modal__footer">
          <button class="btn" type="submit">Actualizar préstamo</button>
          <button class="btn btn-light" type="button" data-close>Cancelar</button>
        </div>
      </form>
      <div class="error-box" id="errorBoxForm" hidden></div>
    </div>
  </div>
</div>

<script src="<?= $APP_BASE ?>public/js/restructuracion.js"></script>
</body>
</html>
