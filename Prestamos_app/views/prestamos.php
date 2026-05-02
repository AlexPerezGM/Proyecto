<?php

require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once  __DIR__ . '/../api/autorizacion.php';
requiere_login();
require_permission('prestamos', 'admin');

$APP_BASE = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$APP_BASE = preg_replace('#/views$#','', $APP_BASE);
$APP_BASE = ($APP_BASE === '' ? '/' : $APP_BASE . '/');

$BASE = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$BASE = preg_replace('#/views$#','', $BASE);
$BASE = ($BASE === '' ? '/' : $BASE . '/');

$qGeneros = $conn->query("SELECT id_genero, genero FROM cat_genero ORDER BY id_genero");
$catGeneros = $qGeneros ? $qGeneros->fetch_all(MYSQLI_ASSOC) : [];

$qDocs = $conn->query("SELECT id_tipo_documento, tipo_documento FROM cat_tipo_documento ORDER BY id_tipo_documento");
$catDocs = $qDocs ? $qDocs->fetch_all(MYSQLI_ASSOC) : [];
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Control de Préstamos</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="stylesheet" href="<?= $BASE ?>public/css/dashboard.css">
  <link rel="stylesheet" href="<?= $BASE ?>public/css/clientes.css">
  <link rel="stylesheet" href="<?= $BASE ?>public/css/prestamos.css">
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

      <a class="nav-link active"
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
  <main class="content-area">
    <header class="topbar topbar-light">
      <div class="topbar-left">
        <div class="brand-inline">
          <span class="brand-logo">💼</span>
          <span class="brand-name">Control de Préstamos</span>
        </div>
        <span class="range-pill">Gestión de solicitudes, lista y desembolsos de préstamos</span>
      </div>
      <div class="topbar-right toolbar-end">
        <label class="mini">Moneda</label>
      </div>
      <div class = "topbar-right toolbar-end">
        <select id="selMoneda" class="input w-120"></select>
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
      <div class="tabs">
      </div>
      <section id="sec-solicitar" class="panel">
        <div class="card">
          <div class="card-header">Solicitar préstamo</div>
          <div class="card-body">
            <div class="list-tools">
              <div class="list-tools-inner">
                <input id="qCliente" class="input" placeholder="Buscar cliente por nombre o cédula..." />
                <button id="btnBuscarCliente" class="btn">Buscar</button>
                <button id="btnAbrirCrearCliente" class="btn btn-light">Agregar cliente</button>
              </div>
            </div>
            <div id="boxCliente" class="panel" style="margin-top:12px;">
              <div class="mini">Selecciona un cliente para continuar</div>
              <div id="resClientes"></div>
            </div>
            <div id="boxInfoCliente" class="panel hidden" style="margin-top:12px;">
            <div id="wrapper_garantia_hipo" style="display:none; margin-top:12px;">
              <div class="grid-2">
                <div>
                  <label class="mini">Valor estimado de la garantía</label>
                  <input class="input" name="valor_garantia_hipo" type="number" step="0.01" placeholder="0.00">
                </div>
                <div>
                  <label class="mini">Descripción de la garantía</label>
                  <input class="input" name="descripcion_garantia_hipo" placeholder="Detalles de la garantía">
                </div>
              </div>
            </div>
              <h4 style="margin-top:0;">Información del cliente</h4>
              <div class="info-grid" id="infoClienteGrid"></div>
              <div style="margin-top:12px; display:flex; gap:8px; flex-wrap:wrap;">
                <button id="btnPrestamoPersonal" class="btn">Préstamo personal</button>
                <button id="btnPrestamoHipotecario" class="btn">Préstamo hipotecario</button>
              </div>
            </div>
          </div>
        </div>
      </section>
      <section id="sec-lista" class="panel hidden" style="margin-top: 16px;">
        <div class="card">
          <div class="card-header">Lista de préstamos</div>
          <div class="card-body">
            <div class="inline-form" style="margin-bottom:10px;">
              <input id="qPrestamo" class="input" placeholder="Buscar por nombre, cédula o código..." />
            </div>
            <div class="inline-form" style="margin-bottom:10px;">
              <select id="fTipoPrestamo" class="input w-160">
                <option value="">Todos los tipos</option>
                <option value="Personal">Personal</option>
                <option value="Hipotecario">Hipotecario</option>
              </select>
            </div>
            <div class="inline-form" style="margin-bottom:10px;">
            <button id="btnBuscarPrestamo" class="btn">Buscar</button>
            </div>
            <div class="table-responsive">
              <table class="table-simple" id="tablaPrestamos">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Tipo</th>
                    <th>Monto</th>
                    <th>Tasa</th>
                    <th>Plazo</th>
                    <th>Estado</th>
                    <th>Próximo pago</th>
                    <th>Acciones</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
              <div class="pager" id="pagPrestamos"></div>
            </div>
          </div>
        </div>
      </section>
      <section id="sec-desembolso" class="panel hidden" style="margin-top: 16px;">
        <div class="card">
          <div class="card-header">Desembolso</div>
          <div class="card-body">
            <div class="inline-form" style="margin-bottom:10px;">
              <input id="qDesembolso" class="input" placeholder="Buscar préstamo por nombre/código..." />
            </div>
            <div class="inline-form" style="margin-bottom:10px;">
              <button id="btnBuscarDesembolso" class="btn">Buscar</button>
            </div>
            <div id="boxDesembolso" class="panel hidden" style="margin-top:12px;">
              <h4 style="margin:0 0 8px;">Procesar desembolso</h4>
              <form id="frmDesembolso" class="info-grid">
                <input type="hidden" name="action" value="desembolsar">
                <input type="hidden" name="id_prestamo" id="id_prestamo_des">
                <div>
                  <label class="mini">Cliente / Préstamo</label>
                  <input id="desResumen" class="input" readonly>
                </div>
                <div>
                  <label class="mini">Método de desembolso</label>
                  <select name="metodo_entrega" id="metodo_entrega" class="input"></select>
                </div>
                <div>
                  <label class="mini">Monto a desembolsar</label>
                  <input name="monto_desembolsado" id="monto_desembolsado" class="input" type="number" min="0" step="0.01" required>
                </div>
                <div>
                  <label class="mini">Fecha de desembolso</label>
                  <input name="fecha_desembolso" id="fecha_desembolso" class="input" type="date" required value="<?= date('Y-m-d') ?>">
                </div>
                <div style="grid-column:1/-1; display:flex; gap:8px; justify-content:flex-end;">
                  <button class="btn btn-light" type="button" id="btnRecibo">Generar comprobante</button>
                  <button class="btn" type="submit">Procesar desembolso</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </section>
      <div id="errorBox" class="error-box" hidden></div>
    </div>
  </main>
</div>
<div class="modal" id="modalCrearCliente">
  <div class="modal__dialog">
    <div class="modal__header">
      <h4 style="margin:0;">Registrar cliente</h4>
      <button class="modal__close" data-close>Salir</button>
    </div>
    <div class="modal__body">
      <form id="frmClienteQuick">
        <input type="hidden" name="action" value="create">
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
          <button class="btn" type="submit">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>
<!-- Solicitud préstamo personal -->
<div class="modal" id="modalPersonal">
  <div class="modal__dialog" style="max-width:800px;">
    <div class="modal__header">
      <h4 style="margin:0;">Solicitud de préstamo personal</h4>
      <button class="modal__close" data-close>Salir</button>
    </div>
    <div class="modal__body">
      <form id="frmPersonal">
        <input type="hidden" name="action" value="crear_personal">
        <input type="hidden" name="id_cliente" id="id_cliente_personal">

        <div class="tabs">
          <button type="button" class="tab-btn active" data-tab="p-datos">Datos del prestamo</button>
          <button type="button" class="tab-btn" data-tab="p-finansas">Datos financieros</button>
          <button type="button" class="tab-btn" data-tab="p-motivo">Garantía/Garante</button>
        </div>

        <div class="tab-contents">
          <div class="tab-pane show" id="p-datos">
            <div class="grid-2">
              <div>
                <label class="mini">Monto solicitado</label>
                <input class="input" name="monto_solicitado" id="monto_personal" type="number" step="0.01" min="10000" required>
              </div>
              <div>
                <label class="mini">Tasa interés (%)</label>
                <input class="input" name="tasa_interes" id="tasa_personal" type="number" step="0.01" required readonly>
              </div>
              <div>
                <label class="mini">Plazo (meses)</label>
                <select class="input" name="plazo_meses" id="plazo_personal" required></select>
              </div>
              <div>
                <label class="mini">Frecuencia de pagos</label>
                <select class="input" name="id_periodo_pago" id="per_personal" required></select>
              </div>
              <div>
                <label class="mini">Tipo de amortización</label>
                <select class="input" name="id_tipo_amortizacion" id="amort_personal" required></select>
              </div>
              <div>
                <label class="mini">Fecha solicitud</label>
                <input class="input" name="fecha_solicitud" type="date" required value="<?= date('Y-m-d') ?>">
              </div>
              <div>
                <label class="mini">Motivo del préstamo</label>
                <input class="input" name="motivo" placeholder="Ej: Gastos médicos, educación, etc.">
              </div>
            </div>
          </div>

          <div class="tab-pane" id="p-finansas">
            <div class="info-group" style="border-left-color: #f59e0b;">
              <h4 style="margin-top:0; color:#f59e0b;">Información financiera del cliente</h4>
              <div class="info-grid" id="infoFinanciera"></div>
              <div class="grid-2">
                <div>
                  <label class="mini"> Score crediticio</label>
                  <input type="number" id="p_score" name="score" class= "input" readonly>
                </div>
                <div>
                  <label class="mini">Deuda externa</label>
                  <input type="number" id="p_deuda_externa" name="deuda_externa" class="input" readonly>
                </div>
                <div>
                  <label class="mini">Porcentaje de uso de tarjetas</label>
                  <input type="number" id="p_uso_tarjetas" name="uso_tarjetas" class="input" readonly>
                </div>
                <div>
                  <label class="mini">Cantidad de productos (tarjetas y otros)</label>
                  <input type="number" id="p_cantidad_productos" name="cantidad_productos" class="input" readonly>
                </div>
                <div>
                  <label class="mini">Nivel de riesgo</label>
                  <input type="text" id="p_nivel_riesgo" name="nivel_riesgo" class="input" readonly>
                </div>
                <div>
                  <label class="mini">Gastos mensuales</label>
                  <input type="number" name="gastos_mensuales" class="input" placeholder="Suma de gastos mensuales" required>
                </div>
              </div>
            </div>
          </div>

          <div class="tab-pane" id="p-motivo">
              <div style="background:#e0f2fe; padding:10px; border-radius: 8px">
              <label style="display:flex; align-items:center; gap:10px; cursor:pointer; font-weight:bold;">
                <input id="check_tiene_garantia_p" type="checkbox" name="tiene_garantia" value="1">
                Necesita garantia o garante?
              </label>
            </div>

            <div id="wrapper_garantia_personal" style="display:none; margin-top:15px;">
              <div class="grid-2">
                <div>
                  <label class="mini">Tipo de garantía</label>
                  <select class="input" name="tipo_garantia" id="garantia_personal"></select>
                </div>
                <div>
                  <label class="mini">Valor estimado garantía</label>
                  <input class="input" name="valor_garantia" type="number" placeholder="0.00">
                </div>
              </div>
              <div style="margin-top:10px;">
                <label class="mini">Descripción detallada</label>
                <textarea class="input" name="descripcion_garantia" rows="2" placeholder="Marca, modelo, número de registro, etc."></textarea>
              </div>
            </div>
          </div>
        </div>

        <div class="modal__footer">
          <div style="flex:1; text-align:left;">
            <label class="mini">Politica de cancelacion</label>
            <select class="input" name="id_politica_cancelacion" id="politica_personal" required></select>
          </div>
          <button class="btn" type="submit">Evaluar prestamo</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Solicitud préstamo hipotecario -->
<div class="modal" id="modalHipotecario">
  <div class="modal__dialog" style="max-width:800px;">
    <div class="modal__header">
      <h4 style="margin:0;">Solicitud de préstamo hipotecario</h4>
      <button class="modal__close" data-close>Salir</button>
    </div>
    <div class="modal__body">
      <form id="frmHipotecario">
        <input type="hidden" name="action" value="crear_hipotecario">
        <input type="hidden" name="id_cliente" id="id_cliente_hipo">

        <div class="tabs">
          <button type="button" class="tab-btn active" data-tab="h-datos">Datos del prestamo</button>
          <button type="button" class="tab-btn" data-tab="h-finansas">Datos financieros</button>
          <button type="button" class="tab-btn" data-tab="h-inmueble">Datos Inmueble</button>
        </div>

        <div class="tab-contents">
          <div class="tab-pane show" id="h-datos">
            <div class="grid-2">
              <div>
                <label class="mini">Monto solicitado</label>
                <input class="input" name="monto_solicitado" id="monto_hipo" type="number" step="0.01" min="10000" required>
              </div>
              <div>
                <label class="mini">Tasa interés (%)</label>
                <input class="input" name="tasa_interes" id="tasa_hipo" type="number" step="0.01" required readonly>
              </div>
              <div>
                <label class="mini">Plazo (meses)</label>
                <select class="input" name="plazo_meses" id="plazo_hipo" required></select>
              </div>
              <div>
                <label class="mini">Frecuencia de pagos</label>
                <select class="input" name="id_periodo_pago" id="per_hipo" required></select>
              </div>
              <div>
                <label class="mini">Tipo de amortización</label>
                <select class="input" name="id_tipo_amortizacion" id="amort_hipo" required></select>
              </div>
              <div>
                <label class="mini">Fecha solicitud</label>
                <input class="input" name="fecha_solicitud" type="date" required value="<?= date('Y-m-d') ?>">
              </div>
              <div>
                <label class="mini">Tipo de garantía</label>
                <select class="input" name="tipo_garantia" id="garantia_hipo" required></select>
              </div>
              <div>
                <label class="mini">Dirección del inmueble</label>
                <input class="input" name="direccion_propiedad" placeholder="Dirección completa del inmueble">
              </div>
              <div>
                <label class="mini">Valor del bien a financiar</label>
                <input class="input" name="valor_propiedad" id="valor_inmueble" type="number" step="0.01" required>
              </div>
              <div>
                <label class="mini">Porcentaje a financiar</label>
                <input class="input" name="porcentaje_financiamiento" id="porc_fin" type="number" min="0" max="80" step="0.01" required readonly>
              </div>
            </div>
          </div>

          <div class="tab-pane" id="h-finansas">
            <div class="info-group" style="border-left-color: #f59e0b;">
              <h4 style="margin-top:0; color:#f59e0b;">Información financiera del cliente</h4>
              <div class="info-grid" id="infoFinancieraHipo"></div>
              <div class="grid-2">
                <div>
                  <label class="mini"> Score crediticio</label>
                  <input type="number" id="h_score" name="Score" class="input" readonly>
                </div>
                <div>
                  <label class="mini">Deuda externa</label>
                  <input type="number" id="h_deuda_externa" name="deuda_externa" class="input" readonly>
                </div>
                <div>
                  <label class="mini">Porcentaje de uso de tarjetas</label>
                  <input type="number" id="h_uso_tarjetas" name="uso_tarjetas" class="input" readonly>
                </div>
                <div>
                  <label class="mini">Cantidad de productos (tarjetas y otros)</label>
                  <input type="number" id="h_cantidad_productos" name="cantidad_productos" class="input" readonly>
                </div>
                <div>
                  <label class="mini">Nivel de riesgo</label>
                  <input type="text" id="h_nivel_riesgo" name="nivel_riesgo" class="input" readonly>
                </div>
                <div>
                  <label class="mini">Gastos mensuales</label>
                  <input type="number" name="gastos_mensuales" class="input" placeholder="Suma de gastos mensuales" required>
                </div>
              </div>
            </div>
          </div>

          <div class="tab-pane" id="h-inmueble">
            <div class="grid-2">
              <div>
                <label class="mini">Dirección</label>
                <input class="input" name="direccion_inmueble" placeholder="Dirección del inmueble">
              </div>
              <div>
                <label class="mini">Registro/Referencia</label>
                <input class="input" name="referencia_inmueble" placeholder="Referencia / número de registro">
              </div>
            </div>
          </div>
        </div>

        <div class="modal__footer">
          <div style="flex:1; text-align:left;">
            <label class="mini">Politica de cancelacion</label>
            <select class="input" name="id_politica_cancelacion" id="politica_hipo" required></select>
          </div>
          <button class="btn" type="submit">Evaluar préstamo</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal" id="modalVerPrestamo">
  <div class="modal__dialog">
    <div class="modal__header">
      <h4 style="margin:0;">Detalle de préstamo</h4>
      <div style="display:flex; gap:8px; align-items:center;">
        <button class="btn" id="btnSubirDocsPrestamo">Subir documentos</button>
        <button class="btn btn-light" id="btnVerCarpetaDocsPrestamo">Abrir documentos del préstamo</button>
        <button class="btn btn-light" id="btnExportarCronograma">Exportar Cronograma</button>
      </div>
      <button class="modal__close" data-close>Salir</button>
    </div>
    <div class="modal__body" id="verPrestamoContenido"></div>
  </div>
</div>
<div class="modal" id="modalCancelacion">
  <div class="modal__dialog">
    <div class="modal__header">
      <h4 style="margin:0; color:#ef4444;">Cancelación Anticipada</h4>
      <button class="modal__close" data-close>Salir</button>
    </div>
    <div class="modal__body">
      <div id="cancelacionLoading">Calculando montos...</div>
      <form id="frmCancelacion" class="hidden">
        <input type="hidden" name="action" value="procesar_cancelacion">
        <input type="hidden" name="id_prestamo" id="id_prestamo_cancelar">
        
        <div class="info-grid" style="grid-template-columns: 1fr;">
          <div style="background:#fef2f2; padding:10px; border-radius:6px; border:1px solid #fee2e2;">
            <p style="margin:0;"><b>Política aplicada:</b> <span id="txtPolitica"></span></p>
          </div>
          
          <table class="table-simple" style="margin-top:10px;">
            <tr><td>Capital Pendiente:</td><td class="text-right" id="valCapital">0.00</td></tr>
            <tr><td>Interés Vencido:</td><td class="text-right" id="valInteres">0.00</td></tr>
            <tr><td>Mora/Cargos:</td><td class="text-right" id="valMora">0.00</td></tr>
            <tr style="color:#ef4444; font-weight:bold;"><td>Penalidad (<span id="txtPorc"></span>%):</td><td class="text-right" id="valPenalidad">0.00</td></tr>
            <tr style="background:#f3f4f6; font-weight:800; font-size:1.1em;"><td>TOTAL A PAGAR:</td><td class="text-right" id="valTotal">0.00</td></tr>
          </table>

          <input type="hidden" name="monto_total" id="inputMontoTotal">
          <div style="margin-top:10px;">
             <label class="mini">Monto recibido para cancelación</label>
             <input name="total_recibido" id="total_recibido" class="input" type="number" min="0" step="0.01" placeholder="Ingrese el monto pagado" required>
          </div>
          
          <div style="margin-top:10px;">
             <label class="mini">Método de Pago</label>
             <select name="metodo_pago" class="input">
               <option value="1">Efectivo</option>
               <option value="2">Transferencia</option>
             </select>
          </div>
          <div style="margin-top:10px;">
             <label class="mini">Notas de cancelación</label>
             <textarea name="notas" class="input" rows="2" placeholder="Motivo de la cancelación..."></textarea>
          </div>
           <div style="margin-top:10px;">
             <label class="mini">Moneda</label>
             <select name="id_tipo_moneda" id="moneda_cancelacion" class="input"></select>
           </div>
        </div>
        <div class="modal__footer">
            <button type="submit" class="btn btn-danger">Confirmar Cancelación</button>
        </div>
      </form>
    </div>
  </div>
</div>
<!-- Recibo -->
<div class="modal" id="modalRecibo">
  <div class="modal__dialog">
    <div class="modal__header">
      <h4 style="margin:0;">Comprobante</h4>
      <div style="display:flex; gap:8px;">
        <button class="btn btn-light" id="btnReciboImprimir">Imprimir</button>
        <button class="btn" id="btnReciboDescargar">Descargar PDF</button>
        <button class="modal__close" data-close>Salir</button>
      </div>
    </div>
    <div class="modal__body" id="reciboHTML"></div>
  </div>
</div>
<script>window.APP_BASE = "<?= $BASE ?>";</script>
<script src="<?= $BASE ?>public/JS/prestamos.js"></script>
</body>
</html>
