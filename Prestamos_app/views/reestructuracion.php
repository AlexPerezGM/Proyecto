<?php
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$BASE = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$BASE = preg_replace('#/views$#','', $BASE);
$BASE = ($BASE === '' ? '/' : $BASE . '/');

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
  <link rel="stylesheet" href="<?= $APP_BASE ?>public/css/dashboard.css">
  <link rel="stylesheet" href="<?= $APP_BASE ?>public/css/clientes.css">
  <link rel="stylesheet" href="<?= $APP_BASE ?>public/css/prestamos.css">
  <link rel="stylesheet" href="<?= $APP_BASE ?>public/css/reestructuracon.css">
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

      <a class="nav-link" href="<?= $APP_BASE ?>views/campanas_promocion.php">
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
  <main class="content-area">
    <div class="topbar topbar-light">
      <div class="topbar-left">
        <div class="brand-inline">
          <span class="brand-logo">🛠️</span>
          <span class="brand-name">Reestructuración de Préstamos</span>
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
    </div>

    <div class="page-wrapper">
      <div class="grid-page">
        <section class="panel">
          <div class="panel-header">Buscar prestamos<small class="tag" id="totalTag"></small></div>
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
            <label>Cedula</label>
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
            <label>Nueva tasa de interés</label>
            <input class="input" type="number" step="0.01" name="nueva_tasa" id="nueva_tasa" placeholder="Ej. 12.50">
          </div>
          <div>
            <label>Nuevo plazo (meses)</label>
            <input class="input" type="number" name="nuevo_plazo" id="nuevo_plazo" required>
          </div>
          <div>
            <label>Nueva fecha de pago</label>
            <input class="input" type="date" name="nueva_fecha" id="nueva_fecha">
          </div>
          <div style="grid-column:1/-1">
            <label>Motivo de reestructuración</label>
            <textarea class="input" name="motivo" id="motivo" rows="3" placeholder="Describe brevemente el motivo…"></textarea>
          </div>
        </div>
        <div class="modal__footer">
          <button class="btn" type="submit">Reestructurar préstamo</button>
          <button class="btn btn-light" type="button" data-close>Cancelar</button>
        </div>
      </form>
      <div class="error-box" id="errorBoxForm" hidden></div>
    </div>
  </div>
</div>
<script src="<?= $APP_BASE ?>public/JS/restructuracion.js"></script>
</body>
</html>
