<?php
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
  <title>Recursos Humanos</title>
  <link rel="stylesheet" href="<?= $APP_BASE ?>public/css/dashboard.css">
  <link rel="stylesheet" href="<?= $APP_BASE ?>public/css/clientes.css">
</head>
<body>
  
<div class="app-shell">
  <!-- === SIDEBAR (idéntico a tus páginas) === -->
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

      <a class="nav-link active"
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

  <!-- CONTENIDO -->
  <main class="content-area">
    <div class="page-wrapper">

      <div class="topbar topbar-light">
        <div class="topbar-left">
          <div class="brand-inline">
            <span class="brand-logo">🏢</span><span class="brand-name">Recursos Humanos</span>
          </div>
        </div>
      </div>

      <!-- Tabs simples -->
      <div class="list-tools">
        <div class="list-tools-inner">
          <button id="tabEmpleados" class="btn">Gestión de empleados</button>
          <button id="tabNomina" class="btn btn-light">Nómina</button>
        </div>
      </div>

      <!-- ======== SECCIÓN EMPLEADOS ======== -->
      <section id="secEmpleados">
        <div class="card table-card">
          <div class="card-header">Empleados</div>
          <div class="card-body">
            <div class="list-tools">
              <div class="list-tools-inner">
                <input id="qEmp" class="input" placeholder="Buscar por nombre...">
                <button id="btnBuscarEmp" class="btn">Buscar</button>
                <button id="btnNuevoEmp" class="btn">+ Nuevo empleado</button>
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

      <!-- ======== SECCIÓN NÓMINA ======== -->
      <section id="secNomina" style="display:none">
        <div class="card">
          <div class="card-header">Calcular nómina</div>
          <div class="card-body">
            <div class="grid-2">
              <div>
                <label>Periodo (YYYY-MM)</label>
                <input id="periodo" class="input" placeholder="2025-11">
              </div>
              <div>
                <label>Empleado</label>
                <select id="selEmpleado" class="input">
                  <option value="ALL">Todos</option>
                </select>
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
                    <th>Empleado</th><th>Salario base</th><th>Horas extra</th>
                    <th>Bonificaciones</th><th>Deducciones</th><th>Salario neto</th>
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

<!-- MODAL EMPLEADO (alta/edición) -->
<div class="modal" id="modalEmp">
  <div class="modal__dialog">
    <div class="modal__header">
      <h3 id="modalEmpTitulo">Nuevo empleado</h3>
      <button class="modal__close" data-close>&times;</button>
    </div>
    <div class="modal__body">
      <form id="frmEmpleado">
        <input type="hidden" name="action" value="emp_create" />
        <input type="hidden" name="id_empleado" id="id_empleado" />
        <h4>Información personal</h4>
        <div class="grid-2">
          <div><label>Nombre</label><input class="input" name="nombre" id="nombre"></div>
          <div><label>Apellido</label><input class="input" name="apellido" id="apellido"></div>
          <div><label>Cédula</label>
            <input type="hidden" name="id_tipo_documento" value="1">
            <input class="input" name="numero_documento" id="numero_documento" placeholder="000-0000000-0">
          </div>
          <div><label>Fecha nacimiento</label><input type="date" class="input" name="fecha_nacimiento" id="fecha_nacimiento"></div>
          <div><label>Género</label><select class="input" name="genero" id="genero"></select></div>
          <div><label>Teléfono</label><input class="input" name="telefono" id="telefono"></div>
          <div><label>Email</label><input class="input" name="email" id="email"></div>
          <div><label>Dirección (Ciudad)</label><input class="input" name="ciudad" id="ciudad"></div>
          <div><label>Sector</label><input class="input" name="sector" id="sector"></div>
          <div><label>Calle</label><input class="input" name="calle" id="calle"></div>
          <div><label># Casa</label><input class="input" name="numero_casa" id="numero_casa"></div>
        </div>

        <h4 style="margin-top:14px;">Información laboral</h4>
        <div class="grid-2">
          <div><label>Cargo</label><input class="input" name="cargo" id="cargo"></div>
          <div>
            <label>Tipo de contrato</label>
            <select class="input" name="id_tipo_contrato" id="id_tipo_contrato"></select>
          </div>
          <div><label>Departamento (opcional)</label><input class="input" name="departamento" id="departamento"></div>
          <div><label>Fecha de contratación</label><input type="date" class="input" name="fecha_contratacion" id="fecha_contratacion"></div>
          <div><label>Salario base</label><input type="number" step="0.01" class="input" name="salario_base" id="salario_base"></div>
          <div><label>Jefe (opcional)</label><select class="input" name="id_jefe" id="id_jefe"><option value="">—</option></select></div>
        </div>

        <div class="modal__footer">
          <button class="btn" type="submit">Guardar</button>
          <button class="btn btn-light" type="button" data-close>Cancelar</button>
        </div>
      </form>
      <div id="errorEmpForm" class="error-box" hidden></div>
    </div>
  </div>
</div>

<script>window.APP_BASE = <?= json_encode($APP_BASE) ?>;</script>
<script src="<?= $APP_BASE ?>public/JS/rrhh.js"></script>
</body>
</html>
