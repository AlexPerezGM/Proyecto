<?php
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$BASE_URL = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$BASE_URL = preg_replace('#/views$#','', $BASE_URL);
$BASE_URL = ($BASE_URL === '' ? '/' : $BASE_URL . '/');

// BASE_URL = /Prestamos_app/
$script = str_replace('\\','/', $_SERVER['SCRIPT_NAME']);

$APP_BASE = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
if ($APP_BASE === '') $APP_BASE = '/';
$APP_BASE = $APP_BASE . (substr($APP_BASE,-1) === '/' ? '' : '/') ;

$APP_BASE = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$APP_BASE = preg_replace('#/views$#', '', $APP_BASE);
$APP_BASE = ($APP_BASE === '' ? '/' : $APP_BASE . '/');

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

      <a class="nav-link"
         href="<?= $APP_BASE ?>views/dashboard.php">
        <span class="nav-icon">🏠</span>
        <span class="nav-text">Dashboard</span>
      </a>
    </div>
    <div class="sidebar-section">
      <div class="section-label">GESTIÓN</div>

      <a class="nav-link active"
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
    <div class="content-area">
      <header class="topbar topbar-light">
      <div class="topbar-left">
        <div class="brand-inline">
          <span class="brand-logo">👥</span>
            <span class="brand-text">Gestión de Clientes</span>
          </div>
          <span class="range-pill">Gestión de Clientes</span>
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
            <input id="q" class="input" placeholder="Buscar por nombre o documento..." />
            <button id="btnBuscar" class="btn">Buscar</button>
          </div>
          <div class="topbar-right" style="margin-top: 10px;">
          <button id="btnAbrirCrear" class="small-btn">Registrar cliente</button>
        </div>
        </div>
        <section class="card table-card">
          <div class="card-header"><h4 style="font-weight: bold; font-size: 18px;">Lista de Clientes</h4></div>
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

  <div id="modalForm" class="modal" aria-hidden="true">    
    <div class="modal__dialog">
      <div class="modal__header">
        <h3 id="modalTitulo">Registrar cliente</h3>
        <button class="modal__close" data-close>✖</button>
      </div>
      <form id="frmCliente" class="modal__body">
        <input type="hidden" name="action" value="create">
        <input type="hidden" name="id_cliente" id="id_cliente">
        <input type="hidden" name="existing_id_dp" id="existing_id_dp" value="0">
        <div class="grid-2">
          <div>
            <label style="font-weight: bold; font-size: 15px;">Nombre</label>
            <input id="nombre" name="nombre" class="input" placeholder="Ingrese nombre" required>
            <label style="font-weight: bold; font-size: 15px;">Apellido</label>
            <input id="apellido" name="apellido" class="input" placeholder="Ingrese apellido" required>
            <label style="font-weight: bold; font-size: 15px;">Fecha de nacimiento</label>
            <input id="fecha_nacimiento" name="fecha_nacimiento" type="date" class="input" required>
            <label style="font-weight: bold; font-size: 15px;">Tipo de documento</label>
            <select id="id_tipo_documento_modal" name="id_tipo_documento" class="input" required>
              <option value="">Seleccione…</option>
              <?php foreach ($catDocs as $t): ?>
                <option value="<?= $t['id_tipo_documento'] ?>"><?= htmlspecialchars($t['tipo_documento']) ?></option>
              <?php endforeach ?>
            </select>
            <label style="font-weight: bold; font-size: 15px;">Número documento</label>
            <input id="numero_documento" name="numero_documento" class="input" placeholder="0-0000000-0" required>
            <label style="font-weight: bold; font-size: 15px;">Género</label>
            <select id="genero_modal" name="genero" class="input" required>
              <option value="">Seleccione…</option>
              <?php foreach ($catGeneros as $g): ?>
                <option value="<?= $g['id_genero'] ?>"><?= htmlspecialchars($g['genero']) ?></option>
              <?php endforeach ?>
            </select>
          </div>

          <div>
            <label style="font-weight: bold; font-size: 15px;" >Teléfono</label>
            <input id="telefono" name="telefono" class="input" placeholder="809-555-5555" required>
            <label style="font-weight: bold; font-size: 15px;" >Email</label>
            <input id="email" name="email" type="email" class="input" placeholder="correo@ejemplo.com" required>
            <div color = "Green" >
              <div style="margin-bottom: 5px;">
              <label style="font-weight: bold; font-size: 15px;">Dirección:</label>
              </div>
              <label style="font-weight: bold; font-size: 15px;">Ciudad</label>
              <input id="ciudad" name="ciudad" class="input" placeholder="Ingrese ciudad" required>
              <label style="font-weight: bold; font-size: 15px;">Sector</label>
              <input id="sector" name="sector" class="input" placeholder="Ingrese sector" required>
              <label style="font-weight: bold; font-size: 15px;">Calle</label>
              <input id="calle" name="calle" class="input" placeholder="Ingrese numero de calle" required>
              <label style="font-weight: bold; font-size: 15px;">Número casa</label>
              <input id="numero_casa" name="numero_casa" class="input" placeholder="Ingrese número de casa" required>
            </div>
          </div>
          <div>
            <label style="font-weight: bold; font-size: 15px;">Ingresos mensuales</label>
            <input id="ingresos_mensuales" name="ingresos_mensuales" class="input" placeholder="Ingresos mensuales" required>
            <label style="font-weight: bold; font-size: 15px;">Fuente de ingresos</label>
            <input id="fuente_ingresos" name="fuente_ingresos" class="input" placeholder="Fuente de ingresos" required>
            <label style="font-weight: bold; font-size: 15px;">Egresos mensuales</label>
            <input id="egresos_mensuales" name="egresos_mensuales" class="input" placeholder="Egresos mensuales" required>
            <label style="font-weight: bold; font-size: 15px;">Ocupación</label>
            <input id="ocupacion" name="ocupacion" class="input" placeholder="Ocupación" required>
            <label style="font-weight: bold; font-size: 15px;">Empresa</label>
            <input id="empresa" name="empresa" class="input" placeholder="Empresa" required>
          </div>
        </div>
        <div class="docs-section">
          <h4>Documentación del cliente</h4>
          <div class="docs-controls">
            <label for="tipo_doc_cliente">Tipo de documento</label>
            <select id="tipo_doc_cliente" class="input">
              <option value="">Seleccione tipo…</option>
              <option value="CEDULA">Cédula de identidad</option>
              <option value="PASAPORTE">Pasaporte</option>
              <option value="LICENCIA">Licencia de conducir</option>
              <option value="EXTRACTO">Extracto bancario</option>
              <option value="CONTRATO">Cronograma de cuotas</option>
              <option value="OTRO">Otro documento</option>
            </select>
          </div>
          
          <div class="docs-controls" style="margin-top: 10px">
            <label for="archivo_doc">Archivo (PDF, JPG o PNG)</label>
            <input type="file" id="archivo_doc" class="input"
                   accept=".pdf,.jpg,.jpeg,.png" disabled>
            <button type="button" id="btnSubirDoc" class="btn" style="margin-top: 10px" disabled>Agregar documento</button>
          </div>
          <div id="boxDocs" class="docs-list">
            <!-- Lista de documentos cargados por JS -->
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
  <script>window.APP_BASE = "<?= $BASE_URL ?>";</script>
  <script src="public/js/clientes.js?v=3"></script>
</body>
</html>
