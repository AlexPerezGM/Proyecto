<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: ../login.php');
    exit;
}
require_once __DIR__ . '/../config/db.php';

require_once __DIR__ . '/../api/autorizacion.php';
requiere_login();
require_permission('admin');

$BASE_URL = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$BASE_URL = preg_replace('#/views$#','', $BASE_URL);
$BASE_URL = ($BASE_URL === '' ? '/' : $BASE_URL . '/');

$APP_BASE = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
if ($APP_BASE === '') $APP_BASE = '/';
$APP_BASE = $APP_BASE . (substr($APP_BASE,-1) === '/' ? '' : '/') ;

$APP_BASE = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$APP_BASE = preg_replace('#/views$#','', $APP_BASE);
$APP_BASE = ($APP_BASE === '' ? '/' : $APP_BASE . '/');

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Usuarios y Roles</title>
  <base href="<?= $BASE_URL ?>">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="public/css/dashboard.css?v=1">
  <link rel="stylesheet" href="public/css/clientes.css?v=1">
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

      <a class="nav-link"
         href="<?= $APP_BASE ?>views/reestructuracion.php">
        <span class="nav-icon">♻️</span>
        <span class="nav-text">Reestructuración de Préstamos</span>
      </a>
    </div>
    <div class="sidebar-section">
      <div class="section-label">ADMINISTRACIÓN</div>
      <a class="nav-link active"
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
  <div class="content-area">
    <header class="topbar topbar-light">
      <div class="topbar-left">
        <div class="brand-inline">
          <span class="brand-logo">🔐</span>
          <span class="brand-name">Usuarios y Roles</span>
        </div>
        <span class="range-pill">Gestión de usuarios y roles</span>
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
    <main class="page-wrapper">
      <div class="list-tools">
        <div class="list-tools-inner">
          <button id="tabRoles" class="btn">Roles</button>
          <button id="tabUsuarios" class="btn-light">Usuarios</button>
          <button id="btnNuevoRol" class="btn" style="margin-left:auto">Nuevo Rol</button>
          <button id="btnNuevoUsuario" class="btn">Nuevo Usuario</button>
        </div>
      </div>
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
      <div id="panelUsuarios" class="card table-card" style="display:none">
        <div class="card-header">Usuarios registrados</div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table-simple" id="tablaUsuarios">
              <thead><tr>
                <th>ID</th>
                <th>Usuario</th>
                <th>Rol</th>
                <th>Empleado</th>
                <th>Acciones</th>
              </tr></thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
      </div>
      <div id="errorBox" class="error-box" hidden></div>
    </main>
  </div>
</div>
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
        </div>
      </div>
      <div class="modal__footer">
        <button class="btn-light" type="button" data-close>Cancelar</button>
        <button class="btn" type="submit">Guardar</button>
      </div>
    </form>
  </div>
</div>
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
          <label>Contraseña</label>
          <input name="contrasena" id="contrasena" class="input" required type="password">
        </div>
        <div>
          <label>Rol</label>
          <select name="id_rol" id="id_rol_user" class="input" required></select>
        </div>
        <div>
          <label>Empleado</label>
          <select name="id_datos_persona" id="id_datos_persona" class="input" required></select>
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
