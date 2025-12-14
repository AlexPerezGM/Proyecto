<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: ../login.php');
    exit;
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../api/autorizacion.php';
requiere_login();
require_permission('rrhh', 'admin');

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
  <title>Recursos Humanos</title>
  <link rel="stylesheet" href="<?= $APP_BASE ?>public/css/dashboard.css">
  <link rel="stylesheet" href="<?= $APP_BASE ?>public/css/clientes.css">
  <link rel="stylesheet" href="<?= $APP_BASE ?>public/css/rrhh.css">
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
      <a class="nav-link"
         href="<?= $APP_BASE ?>views/seguridad.php">
        <span class="nav-icon">🔐</span>
        <span class="nav-text">Usuarios y Roles</span>
      </a>

      <a class="nav-link active"
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
</aside>
  <main class="content-area">
     <header class="topbar topbar-light">
      <div class="topbar-left">
        <div class="brand-inline">
          <span class="brand-logo">🧑</span>
          <span class="brand-name">Recursos Humanos</span>
        </div>
        <span class="range-pill">Gestión de empleados, nómina</span>
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
    <div style="margin:16px">
      <div class="list-tools">
        <div class="list-tools-inner">
          <button id="tabEmpleados" class="btn">Gestión de empleados</button>
          <button id="tabNomina" class="btn btn-light">Nómina</button>
        </div>
      </div>
      <section id="secEmpleados">
        <div class="card table-card">
          <div class="card-header">Empleados</div>
          <div class="card-body">
            <div class="list-tools">
              <div class="list-tools-inner">
                <input id="qEmp" class="input" placeholder="Buscar por nombre...">
                <button id="btnBuscarEmp" class="btn">Buscar</button>
                <button id="btnNuevoEmp" class="btn">Nuevo empleado</button>
              </div>
            </div>
            <div class="table-responsive">
              <table id="tablaEmpleados" class="table-simple">
                <thead>
                  <tr>
                    <th>ID</th><th>Nombre</th><th>Cédula</th><th>Email</th><th>Teléfono</th>
                    <th>Cargo</th><th>Salario base</th><th>Contratación</th><th></th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
            <div id="paginacionEmp" class="pager"></div>
            <div id="errorEmp" class="error-box" hidden></div>
          </div>
        </div>
      </section>
      <section id="secNomina" style="display:none">
        <div class="card">
          <div class="card-header">Calcular nómina</div>
          <div class="card-body">
            <div class="grid-2">
              <div class="grid-4">
                <label>Frecuencia</label>
                <select id="selFrecuencia" class="input">
                  <option value="Mensual">Mensual</option>
                  <option value="Quincenal">Quincenal</option>
                </select>
              </div>
              <div>
                <label>Fecha de Inicio</label>
                <input type="date" id="fechaInicio" class="input">
              </div>
              <div>
                <label>Fecha fin</label>
                <input type="date" id="fechaFin" class="input">
              </div>
              <div>
                <label>Fecha de pago</label>
                <input type="date" id="fechaPago" class="input">
              </div>

              <div class="grid-2" style="margin-top:10px;">
                <div>
                  <label for="txtSearchNomina">Buscar empleado</label>
                  <div style="display:flex; gap:5px;">
                    <input type="text" id="txtSearchNomina" class="input" placeholder="Buscar nombre...">
                    <button class="btn btn-light" id="btnBuscarNomina" type="button">Refrescar Lista</button>
                  </div>
              </div>
            </div>
            <div style="margin-top:10px; display:flex; gap:8px;">
              <button id="btnCalcular" class="btn">Calcular</button>
              <button id="btnGuardarNomina" class="btn btn-light">Guardar cálculo</button>
              <button id="btnComprobantes" class="btn">Generar comprobantes</button>
            </div>
          </div>
        </div>
        <div class="card table-card" style="margin-top:16px;">
          <div class="card-header">Resumen por empleado</div>
          <div class="card-body">
            <div class="table-responsive">
              <table id="tablaNomina" class="table-simple">
                <thead>
                  <tr>
                    <th>Empleado</th>
                    <th>Salario base</th>
                    <th>Horas extra</th>
                    <th>Bonificaciones</th>
                    <th>Deducciones</th>
                    <th>Salario neto</th>
                    <th>Acciones</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
            <div id="resumenTotales" style="margin-top:10px; font-weight:600;"></div>
            <div id="errorNomina" class="error-box" hidden></div>
          </div>
        </div>
      </section>
    </div>
  </main>
</div>
<!-- Nuevo empleado-->
<div class="modal" id="modalEmp">
  <div class="modal__dialog">
    <div class="modal__header">
      <h3 id="modalEmpTitulo" style="font-size: 20px;">Nuevo empleado</h3>
      <button class="modal__close" data-close>&times;</button>
    </div>
    <div class="modal__body">
      <form id="frmEmpleado">
        <input type="hidden" name="action" value="emp_create" />
        <input type="hidden" name="id_empleado" id="id_empleado" />
        <h4 style="font-size: 20px;">Información personal</h4>
        <div class="grid-2">
          <div>
            <label>Nombre</label>
            <input class="input" name="nombre" id="nombre" placeholder="Ingrese su nombre" required>
          </div>
          <div>
            <label>Apellido</label>
            <input class="input" name="apellido" id="apellido" placeholder="Ingrese su apellido" required>
          </div>
          <div><label>Cédula</label>
            <input type="hidden" name="id_tipo_documento" value="1">
            <input class="input" name="numero_documento" id="numero_documento" placeholder="000-0000000-0" required>
          </div>
          <div>
            <label>Fecha nacimiento</label>
            <input type="date" class="input" name="fecha_nacimiento" id="fecha_nacimiento" required>
          </div>
          <div>
            <label>Género</label>
            <select class="input" name="genero" id="genero" required></select>
          </div>
          <div>
            <label>Teléfono</label>
            <input class="input" name="telefono" id="telefono" placeholder="829-000-0000" required>
          </div>
          <div>
            <label>Email</label>
            <input class="input" name="email" id="email" placeholder="correo@gmail.com" required>
          </div>
          <div>
            <label>Dirección (Ciudad)</label>
            <input class="input" name="ciudad" id="ciudad" placeholder="Ciudad" required>
          </div>
          <div>
            <label>Sector</label>
            <input class="input" name="sector" id="sector" placeholder="Sector" required>
          </div>
          <div>
            <label>Calle</label>
            <input class="input" name="calle" id="calle" placeholder="Numero calle" required>
          </div>
          <div>
            <label>#Casa</label>
            <input class="input" name="numero_casa" id="numero_casa" placeholder="Numero casa" required>
          </div>
        </div>
        <h4 style="margin-top:14px; font-size: 20px;">Información laboral</h4>
        <div class="grid-2">
          <div><label>Cargo</label><input class="input" name="cargo" id="cargo"></div>
          <div>
            <label>Tipo de contrato</label>
            <select class="input" name="id_tipo_contrato" id="id_tipo_contrato" required></select>
          </div>
          <div>
            <label>Departamento (opcional)</label>
            <input class="input" name="departamento" id="departamento">
          </div>
          <div>
            <label>Fecha de contratación</label>
            <input type="date" class="input" name="fecha_contratacion" id="fecha_contratacion" required>
          </div>
          <div>
            <label>Salario base</label>
            <input type="number" step="0.01" class="input" name="salario_base" id="salario_base" placeholder="0.00" required>
          </div>
          <div>
            <label>Jefe (opcional)</label>
            <select class="input" name="id_jefe" id="id_jefe"><option value="">—</option></select>
          </div>
          <!-- Documentos adjuntos -->
        <div class="docs-section">
          <h5 style="font-weight: bold; font-size: 16px;">Documentación del cliente</h5>
          <div class="docs-controls">
            <label for="tipo_doc_empleado">Tipo de documento</label>
            <select id="tipo_doc_empleado" class="input">
              <option value="">Seleccione tipo…</option>
              <option value="CEDULA">Cédula de identidad</option>
              <option value="PASAPORTE">Pasaporte</option>
              <option value="LICENCIA">Licencia de conducir</option>
              <option value="CONTRATO">Contrato de trabajo</option>
              <option value="CV">Curriculum Vitae</option>
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
          </div>
        </div>
        </div>
        <div class="modal__footer">
          <button class="btn" type="submit" style="font-size: 16px;">Guardar</button>
          <button class="btn btn-light" type="button" data-close style="font-size: 16px;">Cancelar</button>
        </div>
      </form>
      <div id="errorEmpForm" class="error-box" hidden></div>
    </div>
  </div>
</div>
<div id="modalAjuste" class="modal">
  <div class="modal__dialog">
    <div class="modal__header">
      <h3 id="modalAjusteTitulo" style="font-size: 20px;">Nuevo ajuste</h3>
      <button class="modal__close" data-close-ajuste>&times;</button>
    </div>
    <div class="modal__body">
      <form id="frmAjuste">
        <input type="hidden" name="action" value="ajuste_crear_actualizar">
        <input type="hidden" name="id_empleado" id="ajuste_id_empleado">
        <input type="hidden" name="id_ajuste_empleado" id="ajuste_id_ajuste_empleado" value="0">
        <div class="grid-2">
          <div>
            <label>Tipo</label>
            <select class="input" name="tipo" id="ajuste_tipo" required>
              <option value="">Seleccionar...</option>
              <option value="Beneficio">Beneficio</option>
              <option value="Deduccion">Deducción</option>
            </select>
          </div>
          <div>
            <label>Concepto</label>
            <select class="input" name="id_catalogo" id="ajuste_id_catalogo" required>
              <option value="">Seleccionar...</option>
            </select>
          </div>
        </div>
        <div class="grid-3" style="margin-top:14px;">
          <div>
            <label>Porcentaje (%)</label>
            <input type="number" step="0.01" class="input" name="porcentaje" id="ajuste_porcentaje">
          </div>
          <div>
            <label>Monto ($)</label>
            <input type="number" step="0.01" class="input" name="monto" id="ajuste_monto">
          </div>
          <div>
            <label>Vigentes desde</label>
            <input type="date" class="input" name="vigente_desde" id="ajuste_vigente_desde" required>
          </div>
        </div>
        <div id="errorAjusteForm" class="error-box" hidden></div>
        <div class="modal__footer">
          <button class="btn btn-primary" type="submit" style="font-size: 16px;">Guardar ajuste</button>
          <button class="btn btn-light" type="button" data-close-ajuste style="font-size: 16px;">Cancelar</button>
        </div>
      </form>
    </div>
  </div>
</div>
 <!-- Modal para ver información del empleado -->
<div id="modalVer" class="modal">
  <div class="modal__dialog">
    <div class="modal__header">
        <h3 id="modalVerTitulo" style="font-size:20px;">Información del empleado</h3>
        <button class="modal__close" data-close>&times;</button>
    </div>
    <div class="modal__body">
      <div id="verContenido">Cargando…</div>
    </div>
  </div>
</div>
<script>window.APP_BASE = <?= json_encode($APP_BASE) ?>;</script>
<script src="<?= $APP_BASE ?>public/JS/rrhh.js"></script>
</body>
</html>
