<?php
// views/seguridad.php
require_once __DIR__ . '/../config/db.php';

// Base URL y APP_BASE igual que en clientes.php
$BASE_URL = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$BASE_URL = preg_replace('#/views$#','', $BASE_URL);
$BASE_URL = ($BASE_URL === '' ? '/' : $BASE_URL . '/');
$APP_BASE = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
if ($APP_BASE === '') $APP_BASE = '/';
$APP_BASE = $APP_BASE . (substr($APP_BASE,-1) === '/' ? '' : '/') ;
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Usuarios y Roles</title>
  <base href="<?= $BASE_URL ?>">
  <link rel="stylesheet" href="public/css/dashboard.css?v=1">
  <!-- Reutilizamos estilos base del módulo clientes (modales, grid, botones) -->
  <link rel="stylesheet" href="public/css/clientes.css?v=1">
</head>
<body>
<div class="app-shell">

  <!-- SIDEBAR -->
  <aside class="sidebar sidebar-expanded">
    <div class="sidebar-inner">

      <div class="sidebar-section">
        <div class="section-label">DASHBOARD</div>
        <a class="nav-link" href="<?= $APP_BASE ?>index.php">
          <span class="nav-icon">🏠</span><span class="nav-text">Dashboard</span>
        </a>
      </div>

      <div class="sidebar-section">
        <div class="section-label">GESTIÓN</div>
        <a class="nav-link" href="<?= $APP_BASE ?>views/clientes.php"><span class="nav-icon">👥</span><span class="nav-text">Gestión de Clientes</span></a>
        <a class="nav-link" href="<?= $APP_BASE ?>views/prestamos.php"><span class="nav-icon">💼</span><span class="nav-text">Control de Préstamos</span></a>
        <a class="nav-link" href="<?= $APP_BASE ?>views/pagos.php"><span class="nav-icon">💰</span><span class="nav-text">Gestión de Pagos</span></a>
        <a class="nav-link" href="<?= $APP_BASE ?>views/seguimiento.php"><span class="nav-icon">📈</span><span class="nav-text">Seguimiento de Préstamos</span></a>
        <a class="nav-link" href="<?= $APP_BASE ?>views/restructuracion.php"><span class="nav-icon">♻️</span><span class="nav-text">Reestructuración de Préstamos</span></a>
      </div>

      <div class="sidebar-section">
        <div class="section-label">ADMINISTRACIÓN</div>
        <a class="nav-link active" href="<?= $APP_BASE ?>views/seguridad.php"><span class="nav-icon">🔐</span><span class="nav-text">Usuarios y Roles</span></a>
        <a class="nav-link" href="<?= $APP_BASE ?>views/rrhh.php"><span class="nav-icon">🧑</span><span class="nav-text">Recursos Humanos</span></a>
        <a class="nav-link" href="<?= $APP_BASE ?>views/agenda.php"><span class="nav-icon">📅</span><span class="nav-text">Agenda y Citas</span></a>
        <a class="nav-link" href="<?= $APP_BASE ?>logout.php"><span class="nav-icon">🚪</span><span class="nav-text">Cerrar Sesión</span></a>
      </div>
    </div>

    <div class="sidebar-footer">
      <a class="nav-link footer-link" href="<?= $APP_BASE ?>views/perfil.php">
        <span class="nav-icon">👤</span><span class="nav-text">Mi Perfil</span>
      </a>
    </div>
  </aside>

  <!-- CONTENIDO -->
  <div class="content-area"><!-- (mismo layout que clientes)  -->
    <header class="topbar topbar-light">
      <div class="topbar-left">
        <div class="brand-inline"><span class="brand-logo">🔐</span><span class="brand-name">Usuarios y Roles</span></div>
      </div>
    </header>

    <main class="page-wrapper">
      <!-- Tabs simples -->
      <div class="list-tools">
        <div class="list-tools-inner">
          <button id="tabRoles" class="btn">Roles</button>
          <button id="tabUsuarios" class="btn-light">Usuarios</button>
          <button id="btnNuevoRol" class="btn" style="margin-left:auto">Nuevo Rol</button>
          <button id="btnNuevoUsuario" class="btn">Nuevo Usuario</button>
        </div>
      </div>

      <!-- Tabla de Roles -->
      <div id="panelRoles" class="card table-card">
        <div class="card-header">Roles registrados</div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table-simple" id="tablaRoles">
              <thead><tr><th>ID</th><th>Nombre</th><th>Permisos</th><th>Acciones</th></tr></thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Tabla de Usuarios -->
      <div id="panelUsuarios" class="card table-card" style="display:none">
        <div class="card-header">Usuarios registrados</div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table-simple" id="tablaUsuarios">
              <thead><tr><th>ID</th><th>Usuario</th><th>Rol</th><th>Acciones</th></tr></thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
      </div>

      <div id="errorBox" class="error-box" hidden></div>
    </main>
  </div>
</div>

<!-- MODAL Rol -->
<div id="modalRol" class="modal" aria-hidden="true">
  <div class="modal__dialog">
    <div class="modal__header">
      <h3 id="modalRolTitulo">Nuevo rol</h3>
      <button class="modal__close" data-close>✖</button>
    </div>
    <form id="frmRol" class="modal__body">
      <input type="hidden" name="action" value="role_create">
      <input type="hidden" name="id_rol" id="id_rol">

      <div class="grid-2">
        <div>
          <label>Nombre del rol *</label>
          <input name="nombre" id="rol_nombre" class="input" required>
        </div>
        <div>
          <label>Permisos</label>
          <div id="checksPermisos" style="display:grid; gap:6px;"></div>
          <!-- se cargan dinámicamente desde la API -->
        </div>
      </div>

      <div class="modal__footer">
        <button class="btn-light" type="button" data-close>Cancelar</button>
        <button class="btn" type="submit">Guardar</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL Usuario -->
<div id="modalUsuario" class="modal" aria-hidden="true">
  <div class="modal__dialog">
    <div class="modal__header">
      <h3 id="modalUsuarioTitulo">Nuevo usuario</h3>
      <button class="modal__close" data-close>✖</button>
    </div>
    <form id="frmUsuario" class="modal__body">
      <input type="hidden" name="action" value="user_create">
      <input type="hidden" name="id_usuario" id="id_usuario">

      <div class="grid-2">
        <div>
          <label>Usuario *</label>
          <input name="usuario" id="usuario" class="input" required>
          <label>Contraseña <small>(deja en blanco para no cambiar)</small></label>
          <input name="contrasena" id="contrasena" class="input" type="password">
        </div>
        <div>
          <label>Rol *</label>
          <select name="id_rol" id="id_rol_user" class="input" required></select>
        </div>
      </div>

      <div class="modal__footer">
        <button class="btn-light" type="button" data-close>Cancelar</button>
        <button class="btn" type="submit">Guardar</button>
      </div>
    </form>
  </div>
</div>

<script>window.APP_BASE = "<?= $BASE_URL ?>";</script>
<script src="public/js/seguridad.js?v=1"></script>
</body>
</html>
