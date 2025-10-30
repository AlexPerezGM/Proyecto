<?php
require_once __DIR__ . '/../config/db.php';

// Raíz de la app para URLs (quita "/views" si el script está allí)

$BASE_URL = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$BASE_URL = preg_replace('#/views$#','', $BASE_URL);
$BASE_URL = ($BASE_URL === '' ? '/' : $BASE_URL . '/');


// BASE_URL = /Prestamos_app/
$script = str_replace('\\','/', $_SERVER['SCRIPT_NAME']); // /Prestamos_app/views/clientes.php

// Utilidad para ruta base (soporta Prestamos_app / prestamos_app)
$APP_BASE = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
if ($APP_BASE === '') $APP_BASE = '/';
$APP_BASE = $APP_BASE . (substr($APP_BASE,-1) === '/' ? '' : '/') ;

// Cargar catálogos (género y tipo documento)
$catGeneros = $conn->query("SELECT id_genero, genero FROM cat_genero ORDER BY id_genero")->fetch_all(MYSQLI_ASSOC);
$catDocs    = $conn->query("SELECT id_tipo_documento, tipo_documento FROM cat_tipo_documento ORDER BY id_tipo_documento")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Gestión de Clientes</title>
  <base href="<?= $BASE_URL ?>">
  <link rel="stylesheet" href="public/css/dashboard.css?v=1">
  <link rel="stylesheet" href="public/css/clientes.css?v=1">
</head>
<body>
  <div class="app-shell">
    
  <aside class="sidebar sidebar-expanded">
    <div class="sidebar-inner">
      <div class="sidebar-section">
        <div class="section-label">DASHBOARD</div>
        <a class="nav-link" href="index.php"><span class="nav-icon">🏠</span><span class="nav-text">Dashboard</span></a>
      </div>

      <div class="sidebar-section">
        <div class="section-label">GESTIÓN</div>
        <a class="nav-link active" href="views/clientes.php"><span class="nav-icon">👥</span><span class="nav-text">Gestión de Clientes</span></a>
        <a class="nav-link" href="<?= $APP_BASE ?>views/prestamos.php"><span class="nav-icon">💼</span><span class="nav-text">Control de Préstamos</span></a>
        <a class="nav-link" href="<?= $APP_BASE ?>views/pagos.php"><span class="nav-icon">💰</span><span class="nav-text">Gestión de Pagos</span></a>
        <a class="nav-link" href="<?= $APP_BASE ?>views/seguimiento.php"><span class="nav-icon">📈</span><span class="nav-text">Seguimiento de Préstamos</span></a>
      </div>

      <div class="sidebar-section">
        <div class="section-label">REPORTES</div>
        <a class="nav-link" href="<?= $APP_BASE ?>views/reportes.php"><span class="nav-icon">📊</span><span class="nav-text">Administración de Reportes</span></a>
      </div>

      <div class="sidebar-section">
        <div class="section-label">ADMINISTRACIÓN</div>
        <a class="nav-link" href="<?= $APP_BASE ?>views/seguridad.php"><span class="nav-icon">🔐</span><span class="nav-text">Usuarios y Roles</span></a>
        <a class="nav-link" href="<?= $APP_BASE ?>views/rrhh.php"><span class="nav-icon">🧑‍💼</span><span class="nav-text">Recursos Humanos</span></a>
        <a class="nav-link" href="<?= $APP_BASE ?>views/agenda.php"><span class="nav-icon">📅</span><span class="nav-text">Agenda y Citas</span></a>
      </div>
    </div>
    <div class="sidebar-footer">
      <a class="nav-link footer-link" href="<?= $APP_BASE ?>logout.php"><span class="nav-icon">🚪</span><span class="nav-text">Cerrar Sesión</span></a>
    </div>
  </aside>

    <!-- CONTENIDO -->
    <div class="content-area">
      <header class="topbar">
        <div class="topbar-left">
          <a class="home-btn" href="index.php" title="Volver al Dashboard">🏠</a>
          <div class="brand">
            <span class="brand-logo">👥</span>
            <span class="brand-text">Gestión de Clientes</span>
          </div>
        </div>
        <div class="topbar-right">
          <button id="btnAbrirCrear" class="small-btn">➕ Registrar cliente</button>
        </div>
      </header>

      <main class="page-wrapper">
        <!-- Buscador -->
        <div class="list-tools">
          <div class="list-tools-inner">
            <input id="q" class="input" placeholder="Buscar por nombre o documento..." />
            <button id="btnBuscar" class="btn">Buscar</button>
          </div>
        </div>

        <!-- Tabla -->
        <section class="card table-card">
          <div class="card-header"><h3>Clientes</h3></div>
          <div class="card-body table-responsive">
            <table id="tablaClientes" class="table-simple">
              <thead>
                <tr>
                  <th>ID</th><th>Nombre</th><th>Documento</th><th>Email</th>
                  <th>Teléfono</th><th>Estado</th><th>Registro</th><th>Acciones</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
            <div id="paginacion" class="pager"></div>
            <pre id="errorBox" class="error-box" hidden></pre>
          </div>
        </section>
      </main>
    </div>
  </div>

  <!-- Modales (crear/editar y ver) -->
  <div id="modalForm" class="modal" aria-hidden="true">
    <div class="modal__dialog">
      <div class="modal__header">
        <h3 id="modalTitulo">Registrar cliente</h3>
        <button class="modal__close" data-close>✖</button>
      </div>
      <form id="frmCliente" class="modal__body">
        <input type="hidden" name="action" value="create">
        <input type="hidden" name="id_cliente" id="id_cliente">

        <div class="grid-2">
          <div>
            <label>Nombre *</label><input id="nombre" name="nombre" class="input" required>
            <label>Apellido *</label><input id="apellido" name="apellido" class="input" required>
            <label>Fecha de nacimiento *</label><input id="fecha_nacimiento" name="fecha_nacimiento" type="date" class="input" required>
            <label>Género *</label>
            <select id="genero" name="genero" class="input" required>
              <option value="">Seleccione…</option>
              <option value="1">Masculino</option>
              <option value="2">Femenino</option>
              <option value="3">Otro</option>
            </select>
            <label>Tipo de documento *</label>
            <select id="id_tipo_documento" name="id_tipo_documento" class="input" required>
              <option value="">Seleccione…</option>
              <option value="1">Cédula</option>
              <option value="2">Pasaporte</option>
              <option value="3">Licencia de conducir</option>
            </select>
            <label>Número documento *</label><input id="numero_documento" name="numero_documento" class="input" required>
          </div>

          <div>
            <label>Teléfono *</label><input id="telefono" name="telefono" class="input" required>
            <label>Email *</label><input id="email" name="email" type="email" class="input" required>
            <label>Dirección:</label>
           <div> </div>
            <label>Ciudad *</label><input id="ciudad" name="ciudad" class="input" required>
            <label>Sector *</label><input id="sector" name="sector" class="input" required>
            <label>Calle *</label><input id="calle" name="calle" class="input" required>
            <label>Número casa *</label><input id="numero_casa" name="numero_casa" class="input" required>
          </div>

          <div>
            <label>Ingresos mensuales *</label><input id="ingresos_mensuales" name="ingresos_mensuales" class="input" required>
            <label>Fuente de ingresos *</label><input id="fuente_ingresos" name="fuente_ingresos" class="input" required>
            <label>Egresos mensuales *</label><input id="egresos_mensuales" name="egresos_mensuales" class="input" required>
            <label>Ocupación *</label><input id="ocupacion" name="ocupacion" class="input" required>
            <label>Empresa *</label><input id="empresa" name="empresa" class="input" required>
          </div>
        </div>

        <div class="modal__footer">
          <button class="btn-light" type="button" data-close>Cancelar</button>
          <button class="btn" type="submit">Guardar</button>
        </div>
      </form>
    </div>
  </div>

  <div id="modalVer" class="modal" aria-hidden="true">
    <div class="modal__dialog">
      <div class="modal__header">
        <h3>Ficha del cliente</h3>
        <button class="modal__close" data-close>✖</button>
      </div>
      <div id="verContenido" class="modal__body"></div>
    </div>
  </div>

  <!-- Variables y scripts -->
  <script>window.APP_BASE = "<?= $BASE_URL ?>";</script>
  <script src="public/js/clientes.js?v=2"></script>
</body>
</html>
