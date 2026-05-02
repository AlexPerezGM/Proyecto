<?php
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../api/autorizacion.php';
requiere_login();
require_permission('clientes', 'admin');

$BASE_URL = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$BASE_URL = preg_replace('#/views$#','', $BASE_URL);
$BASE_URL = ($BASE_URL === '' ? '/' : $BASE_URL . '/');

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
  <style>
    .client-form-dialog {
      max-width: 900px;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 28px 48px rgba(17, 24, 39, 0.24);
    }

    .client-form-header {
      background: linear-gradient(135deg, #f3f8ff 0%, #ecfeff 100%);
      border-bottom: 1px solid #dbe4f0;
    }

    .client-form-title-wrap h3 {
      margin: 0;
      font-size: 1.06rem;
    }

    .client-form-subtitle {
      margin: 4px 0 0;
      font-size: 0.82rem;
      color: #475569;
    }

    .client-form-body {
      padding: 0;
    }

    .client-form-tabs {
      position: sticky;
      top: 0;
      z-index: 2;
      background: #fff;
      padding: 12px 16px;
      border-bottom: 1px solid #e5e7eb;
      margin-bottom: 0;
    }

    .client-form-content {
      padding: 16px;
      background: #f8fafc;
    }

    .client-form-section {
      background: #fff;
      border: 1px solid #e5e7eb;
      border-radius: 12px;
      padding: 14px;
      margin-bottom: 12px;
    }

    .client-form-section:last-child {
      margin-bottom: 0;
    }

    .client-form-section-title {
      margin: 0 0 10px;
      font-size: 0.9rem;
      color: #1e293b;
      font-weight: 700;
      letter-spacing: 0.01em;
    }

    .client-label {
      font-weight: 700;
      font-size: 0.84rem;
      color: #334155;
      margin-bottom: 3px;
      display: inline-block;
    }

    .client-doc-btn {
      margin-top: 8px;
    }

    .client-form-footer {
      padding: 12px 16px 16px;
      border-top: 1px solid #e5e7eb;
      background: #fff;
    }

    @media (max-width: 860px) {
      .client-form-tabs {
        overflow-x: auto;
        white-space: nowrap;
      }

      .client-form-tabs .tab-btn {
        flex: 0 0 auto;
      }
    }
  </style>
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
    <div class="modal__dialog client-form-dialog">
      <div class="modal__header client-form-header">
        <div class="client-form-title-wrap">
          <h3 id="modalTitulo">Registrar cliente</h3>
        </div>
        <button class="modal__close" data-close>✖</button>
      </div>
      <form id="frmCliente" autocomplete="off" class="modal__body client-form-body" novalidate>
        <input type="hidden" name="action" value="create">
        <input type="hidden" name="id_cliente" id="id_cliente">
        <input type="hidden" name="existing_id_dp" id="existing_id_dp" value="0">

        <div class="tabs client-form-tabs">
          <button type="button" class="tab-btn active" data-tab="tab-identidad">Identidad</button>
          <button type="button" class="tab-btn" data-tab="tab-economicos">Socieconomico</button>
          <button type="button" class="tab-btn" data-tab="tab-laboral">Laboral</button>
          <button type="button" class="tab-btn" data-tab="tab-documentos">Documentos</button>
        </div>

        <div class="client-form-content">
          <!-- Bloque Identidad -->
          <div class="tab-pane active" id="tab-identidad">
            <div class="client-form-section">
              <h4 class="client-form-section-title">Datos personales</h4>
              <div class="grid-2">
                <div class="form-group">
                  <label class="client-label">Nombre</label>
                  <input id="nombre" name="nombre" class="input" placeholder="Ingrese nombre" required>
                </div>
                <div class="form-group">
                  <label class="client-label">Apellido</label>
                  <input id="apellido" name="apellido" class="input" placeholder="Ingrese apellido" required>
                </div>
                <div class="form-group">
                  <label class="client-label">Fecha de nacimiento</label>
                  <input id="fecha_nacimiento" name="fecha_nacimiento" type="date" class="input" required>
                </div>
                <div class="form-group">
                  <label class="client-label">Tipo de documento</label>
                  <select id="id_tipo_documento_modal" name="id_tipo_documento" class="input" required>
                    <option value="">Seleccione…</option>
                  <?php foreach ($catDocs as $t): ?>
                    <option value="<?= $t['id_tipo_documento'] ?>"><?= htmlspecialchars($t['tipo_documento']) ?></option>
                  <?php endforeach ?>
                  </select>
                </div>
                <div class="form-group">
                  <label class="client-label">Numero documento</label>
                  <input id="numero_documento" name="numero_documento" class="input" placeholder="000-0000000-0" required>
                </div>
                <div class="form-group">
                  <label class="client-label">Genero</label>
                  <select id="genero_modal" name="genero" class="input" required>
                    <option value="">Seleccione…</option>
                  <?php foreach ($catGeneros as $g): ?>
                    <option value="<?= $g['id_genero'] ?>"><?= htmlspecialchars($g['genero']) ?></option>
                  <?php endforeach ?>
                  </select>
                </div>
                <div class="form-group">
                  <label class="client-label">Telefono</label>
                  <input id="telefono" name="telefono" class="input" placeholder="809-555-5555" required>
                </div>
                <div class="form-group">
                  <label class="client-label">Email</label>
                  <input id="email" name="email" type="email" class="input" placeholder="correo@ejemplo.com" required>
                </div>
              </div>
            </div>
          </div>
          <!-- Bloque Socioeconomico -->
          <div class="tab-pane" id="tab-economicos">
            <div class="client-form-section">
              <h4 class="client-form-section-title">Ubicacion y direccion</h4>
              <div class="grid-2">
                <div class="form-group">
                  <label class="client-label">Tipo de vivienda</label>
                  <select id="tipo_vivienda" name="tipo_vivienda" class="input" required>
                    <option value="">Seleccione…</option>
                  </select>
                </div>
                <div class="form-group">
                  <label class="client-label">Personas a cargo</label>
                  <input type="number" id="dependientes" name="dependientes" class="input" value="0" min="0" required>
                </div>
                <div class="form-group">
                  <label class="client-label">Ciudad</label>
                  <input id="ciudad" name="ciudad" class="input" placeholder="Ingrese ciudad" required>
                </div>
                <div class="form-group">
                  <label class="client-label">Sector residencial</label>
                  <input id="sector" name="sector" class="input" placeholder="Ingrese sector" required>
                </div>
                <div class="form-group">
                  <label class="client-label">Calle</label>
                  <input id="calle" name="calle" class="input" placeholder="Ingrese numero de calle" required>
                </div>
                <div class="form-group">
                  <label class="client-label">Numero casa</label>
                  <input id="numero_casa" name="numero_casa" class="input" placeholder="Ingrese numero de casa" required>
                </div>
              </div>
            </div>
          </div>

          <!-- Bloque Laboral -->
          <div class="tab-pane" id="tab-laboral">
            <div class="client-form-section">
              <h4 class="client-form-section-title">Informacion laboral y financiera</h4>
              <div class="grid-2">
                <div class="form-group">
                  <label class="client-label">Ocupacion</label>
                  <input id="ocupacion" name="ocupacion" class="input" placeholder="Ocupacion" required>
                </div>
                <div class="form-group">
                  <label class="client-label">Empresa</label>
                  <input id="empresa" name="empresa" class="input" placeholder="Empresa" required>
                </div>
                <div class="form-group">
                  <label class="client-label">Antiguedad laboral</label>
                  <input id="antiguedad_laboral" name="antiguedad_laboral" type="number" min="0" class="input" placeholder="Antiguedad laboral en meses" required>
                </div>
                <div class="form-group">
                  <label class="client-label">Sector laboral</label>
                  <select id="sector_laboral" name="sector_laboral" class="input" required>
                    <option value="">Seleccione…</option>
                  </select>
                </div>
                <div class="form-group">
                  <label class="client-label">Ingresos mensuales</label>
                  <input id="ingresos_mensuales" name="ingresos_mensuales" class="input" placeholder="Ingresos mensuales" required>
                </div>
                <div class="form-group">
                  <label class="client-label">Fuente de ingresos</label>
                  <select id="fuente_ingresos" name="fuente_ingresos" class="input" required>
                    <option value="">Seleccione…</option>
                  </select>
                </div>
                <div class="form-group">
                  <label class="client-label">Egresos mensuales</label>
                  <input id="egresos_mensuales" name="egresos_mensuales" class="input" placeholder="Egresos mensuales" required>
                </div>
              </div>
            </div>
          </div>

          <!-- Bloque Documentos -->
          <div class="tab-pane" id="tab-documentos">
            <div class="client-form-section docs-section">
              <h4 class="client-form-section-title">Documentacion del cliente</h4>
              <div class="grid-2">
                <div class="form-group">
                  <label for="tipo_doc_cliente" class="client-label">Tipo de documento</label>
                  <select id="tipo_doc_cliente" class="input">
                    <option value="">Seleccione tipo…</option>
                    <option value="CEDULA">Cedula de identidad</option>
                    <option value="PASAPORTE">Pasaporte</option>
                    <option value="LICENCIA">Licencia de conducir</option>
                    <option value="EXTRACTO">Extracto bancario</option>
                    <option value="CONTRATO">Cronograma de cuotas</option>
                    <option value="OTRO">Otro documento</option>
                  </select>
                </div>
                <div class="form-group">
                  <label class="client-label">Seleccionar archivo</label>
                  <input type="file" id="archivo_doc" class="input" accept=".pdf,.jpg,.jpeg,.png">
                </div>
              </div>
              <button type="button" id="btnSubirDoc" class="btn client-doc-btn" disabled>Agregar documento</button>
              <div id="boxDocs" class="docs-list">
              <!-- Lista de documentos cargados por JS -->
              </div>
            </div>
          </div>

        </div>
        <div class="modal__footer client-form-footer">
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
  <script src="public/js/clientes.js?v=4"></script>
</body>
</html>
