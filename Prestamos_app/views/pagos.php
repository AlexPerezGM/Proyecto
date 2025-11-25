<?php
// views/pagos.php
declare(strict_types=1);

// Carga DB si la usas para pintar selects; no es obligatorio aquí
$root = dirname(__DIR__);
if (file_exists($root.'/config/db.php')) { require_once $root.'/config/db.php'; }

// Asegurar sesión para poder mostrar user-chip cuando corresponda
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Calcula raíz para todas las rutas (igual que en tus otras vistas)
$APP_BASE = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$APP_BASE = preg_replace('#/views$#','', $APP_BASE);
$APP_BASE = ($APP_BASE === '' ? '/' : $APP_BASE.'/');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <title>Gestión de Pagos</title>
  <base href="<?= htmlspecialchars($APP_BASE, ENT_QUOTES) ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="public/css/dashboard.css">
  <link rel="stylesheet" href="public/css/pagos.css">
  <link rel="stylesheet" href="public/css/clientes.css">
  <style>
    @media (min-width: 900px) {
      .main-area { margin-left: 240px; }
    }
    @media (max-width: 900px) {
      .main-area { margin-left: 0; }
    }
  </style>
</head>
<body>

  <div class="app-shell">
  <!-- Sidebar unificado -->
  <aside class="sidebar sidebar-expanded">
    <div class="sidebar-inner">

      <!-- DASHBOARD -->
      <div class="sidebar-section">
        <div class="section-label">DASHBOARD</div>
        <a class="nav-link" href="views/dashboard.php">
          <span class="nav-icon">🏠</span>
          <span class="nav-text">Dashboard</span>
        </a>
      </div>

      <!-- GESTIÓN -->
      <div class="sidebar-section">
        <div class="section-label">GESTIÓN</div>

        <a class="nav-link" href="views/clientes.php">
          <span class="nav-icon">👥</span>
          <span class="nav-text">Gestión de Clientes</span>
        </a>

        <a class="nav-link" href="views/prestamos.php">
          <span class="nav-icon">💼</span>
          <span class="nav-text">Control de Préstamos</span>
        </a>

        <a class="nav-link active" href="views/pagos.php">
          <span class="nav-icon">💰</span>
          <span class="nav-text">Gestión de Pagos</span>
        </a>

        <a class="nav-link" href="views/seguimiento.php">
          <span class="nav-icon">📈</span>
          <span class="nav-text">Seguimiento de Préstamos</span>
        </a>

        <a class="nav-link" href="views/reestructuracion.php">
          <span class="nav-icon">♻️</span>
          <span class="nav-text">Reestructuración de Préstamos</span>
        </a>
      </div>

      <!-- ADMINISTRACIÓN -->
      <div class="sidebar-section">
        <div class="section-label">ADMINISTRACIÓN</div>

        <a class="nav-link" href="views/seguridad.php">
          <span class="nav-icon">🔐</span>
          <span class="nav-text">Usuarios y Roles</span>
        </a>

        <a class="nav-link" href="views/rrhh.php">
          <span class="nav-icon">🧑</span>
          <span class="nav-text">Recursos Humanos</span>
        </a>

        <a class="nav-link" href="views/promociones.php">
          <span class="nav-icon">📅</span>
          <span class="nav-text">Campañas de promoción</span>
        </a>

        <a class="nav-link" href="api/cerrar_sesion.php">
          <span class="nav-icon">🚪</span>
          <span class="nav-text">Cerrar Sesión</span>
        </a>
      </div>
    </div>

    <div class="sidebar-footer">
      <a class="nav-link footer-link" href="views/perfil.php">
        <span class="nav-icon">👤</span>
        <span class="nav-text">Mi Perfil</span>
      </a>
    </div>
  </aside>

  <!-- Contenido -->
  <div class="main-area">
    <!-- TOPBAR UNIFICADA -->
    <header class="topbar topbar-light">
      <div class="topbar-left">
        <div class="brand-inline">
          <span class="brand-logo">💰</span>
          <span class="brand-name">Gestión de Pagos</span>
        </div>
      </div>

      <div class="topbar-right">
        <button class="small-btn">Registrar pago</button>
        <?php if (!empty($_SESSION['usuario'])): ?>
        <div class="user-chip">
          <div class="avatar-circle"><?= htmlspecialchars($_SESSION['usuario']['inicial_empleado'] ?? '') ?></div>
          <div class="user-info">
            <div class="user-name">
              <?= htmlspecialchars($_SESSION['usuario']['nombre_empleado'] ?? $_SESSION['usuario']['nombre_usuario'] ?? '') ?>
            </div>
            <div class="user-role">
              <?= htmlspecialchars($_SESSION['usuario']['rol'] ?? '') ?>
            </div>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </header>

    <!-- Caja de error para respuestas no-JSON -->
    <div id="errorBox" class="alert" hidden></div>

    <!-- Buscar préstamo -->
    <section class="card">
      <div class="card-header">Buscar préstamo / contrato</div>
      <div class="card-body">
        <p class="card-subtitle">
          Selecciona el préstamo que se va a cobrar. La búsqueda puede hacerse por nombre del cliente,
          cédula, número de contrato o ID interno del préstamo.
        </p>

        <div style="display:flex;gap:.5rem;align-items:center">
          <input id="q" type="text" class="input" placeholder="Nombre / Cédula / Contrato / ID préstamo">
          <button id="btnBuscar" class="btn-primary">Buscar</button>
        </div>

        <div class="table-wrap" style="margin-top:12px">
          <table id="tablaResultados" class="table">
            <thead>
              <tr>
                <th style="width:90px">ID</th>
                <th>Cliente</th>
                <th>Documento</th>
                <th>Estado</th>
                <th style="width:140px">Acción</th>
              </tr>
            </thead>
            <tbody><!-- js pinta aquí --></tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- Panel de resumen / cobro -->
    <section id="panelResumen" class="card" style="display:none">
      <div class="card-header">Resumen y cobro del préstamo</div>
      <div class="card-body">

        <div class="grid" style="grid-template-columns: repeat(2, minmax(0,1fr)); gap:12px">
          <!-- Información general -->
          <div class="box">
            <h3>Información del préstamo</h3>
            <p><b>Estado:</b> <span id="p_estado">-</span></p>
            <p><b>Saldo pendiente:</b> RD$ <span id="p_saldo">0.00</span></p>
            <p class="text-muted">
              El saldo incluye capital pendiente más intereses generados hasta la fecha.
            </p>
          </div>

          <!-- Factura / cuota actual -->
          <div class="box">
            <h3>Factura / cuota actual</h3>
            <p>
              <b>Factura:</b> <span id="c_factura">-</span>
            </p>
            <p>
              <b>Cuota #</b> <span id="c_num">-</span>
              &bull;
              <b>Vence:</b> <span id="c_fecha">-</span>
            </p>
            <ul style="margin:0;padding-left:16px">
              <li>Capital: RD$ <span id="c_capital">0.00</span></li>
              <li>Interés: RD$ <span id="c_interes">0.00</span></li>
              <li>Cargos / comisiones: RD$ <span id="c_cargos">0.00</span></li>
              <li>Saldo de la cuota: RD$ <span id="c_saldo">0.00</span></li>
            </ul>
            <p class="text-muted" style="margin-top:4px">
              Cada factura se asocia a una cuota específica del cronograma (por ejemplo,
              factura 1 → cuota 5).
            </p>
          </div>

          <!-- Mora -->
          <div class="box">
            <h3>Mora acumulada</h3>
            <p><b>Mora calculada:</b> RD$ <span id="p_mora">0.00</span></p>
            <p class="text-muted">
              La mora se calcula automáticamente en la base de datos mediante un evento nocturno,
              según el porcentaje de mora configurado.
            </p>
            <button id="btnAplicarMora" class="btn-light">Recalcular mora</button>
          </div>

          <!-- Total a pagar -->
          <div class="box">
            <h3>Total a pagar (hoy)</h3>
            <p style="font-size:1.3rem">
              <b>RD$ <span id="p_total_hoy">0.00</span></b>
            </p>
            <ul style="margin:0;padding-left:16px" class="text-muted">
              <li>1️⃣ Primero se cancela la <b>mora</b> generada.</li>
              <li>2️⃣ Luego se paga el <b>interés</b> de la cuota.</li>
              <li>3️⃣ Finalmente se abona al <b>capital</b> de la cuota.</li>
              <li>Si el pago no cubre el total, el restante queda pendiente y genera nueva mora.</li>
              <li>Si el pago excede el total, el excedente se abona a la siguiente cuota.</li>
            </ul>
          </div>
        </div>

        <!-- Selección de tipo de pago -->
        <div class="pago-tipos">
          <p class="pago-tipos__title">
            Selecciona el tipo de pago que se va a registrar para esta factura:
          </p>
          <div class="pago-tipos__buttons">
            <button id="btnEfectivo" class="btn-primary">Pago en efectivo</button>
            <button id="btnTransfer" class="btn-primary">Pago por transferencia</button>
            <button id="btnGarantia" class="btn-primary">Usar garantía</button>
            <button id="btnCerrar"   class="btn-danger">Cerrar préstamo</button>
          </div>
        </div>

      </div>
    </section>
  </div>
  </div>

  <!-- MODALES -->
  <!-- Efectivo -->
  <div id="modalEfectivo" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <b>Pago en efectivo</b>
        <button data-close class="btn-light">✕</button>
      </div>
      <form id="frmEfectivo">
        <input type="hidden" name="action" value="pay">
        <input type="hidden" name="metodo" value="Efectivo">
        <input type="hidden" id="ef_id_prestamo" name="id_prestamo">
        <div class="modal-body">
          <label>Monto entregado</label>
          <input type="number" step="0.01" name="monto" class="input" required>

          <label>Tipo de moneda</label>
          <select name="id_tipo_moneda" class="input">
            <option value="1">DOP</option>
            <option value="2">USD</option>
          </select>

          <label>Observación del pago (opcional)</label>
          <textarea name="observacion" class="input"></textarea>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn-primary">Registrar pago y generar factura (PDF)</button>
          <button type="button" data-close class="btn-light">Cancelar</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Transferencia -->
  <div id="modalTransfer" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <b>Pago por transferencia</b>
        <button data-close class="btn-light">✕</button>
      </div>
      <form id="frmTransfer">
        <input type="hidden" name="action" value="pay">
        <input type="hidden" name="metodo" value="Transferencia">
        <input type="hidden" id="tr_id_prestamo" name="id_prestamo">
        <div class="modal-body">
          <label>Número de referencia bancaria</label>
          <input type="text" name="referencia" class="input">

          <label>Monto transferido</label>
          <input type="number" step="0.01" name="monto" class="input" required>

          <label>Tipo de moneda</label>
          <select name="id_tipo_moneda" class="input">
            <option value="1">DOP</option>
            <option value="2">USD</option>
          </select>

          <label>Observación del pago (opcional)</label>
          <textarea name="observacion" class="input"></textarea>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn-primary">Registrar pago y generar factura (PDF)</button>
          <button type="button" data-close class="btn-light">Cancelar</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Garantía -->
  <div id="modalGarantia" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <b>Uso de garantía</b>
        <button data-close class="btn-light">✕</button>
      </div>
      <form id="frmGarantia">
        <input type="hidden" name="action" value="garantia">
        <input type="hidden" id="ga_id_prestamo" name="id_prestamo">
        <div class="modal-body">
          <p class="text-muted">
            Se aplicará el monto seleccionado de la garantía asociada al préstamo.
          </p>

          <label>ID Garantía (asociada al préstamo)</label>
          <input type="number" name="id_garantia" class="input" required>

          <label>Monto a usar</label>
          <input type="number" step="0.01" name="monto" class="input" required>

          <label>Motivo del uso de la garantía (opcional)</label>
          <input type="text" name="motivo" class="input">

          <label>Observación del pago (opcional)</label>
          <textarea name="observacion" class="input"></textarea>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn-primary">Registrar uso y generar comprobante</button>
          <button type="button" data-close class="btn-light">Cancelar</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Cierre -->
  <div id="modalCierre" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <b>Cerrar préstamo</b>
        <button data-close class="btn-light">✕</button>
      </div>
      <form id="frmCierre">
        <input type="hidden" name="action" value="close">
        <input type="hidden" id="cl_id_prestamo" name="id_prestamo">
        <div class="modal-body">
          <p class="text-muted">
            Se validará que no exista saldo pendiente (capital, intereses o mora). Si todo está en cero,
            el préstamo pasará a estado <b>Cerrado</b> y las garantías quedarán liberadas.
          </p>
          <label>Observación (opcional)</label>
          <textarea name="observacion" class="input"></textarea>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn-danger">Validar, cerrar y generar comprobante</button>
          <button type="button" data-close class="btn-light">Cancelar</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Comprobante -->
  <div id="modalComprobante" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <b>Comprobante</b>
        <button data-close class="btn-light">✕</button>
      </div>
      <div id="compContenido" class="modal-body"></div>
      <div class="modal-footer">
        <button data-close class="btn-light">Cerrar</button>
      </div>
    </div>
  </div>

  <script src="<?= $APP_BASE ?>public/JS/pagos.js"></script>

</body>
</html>
