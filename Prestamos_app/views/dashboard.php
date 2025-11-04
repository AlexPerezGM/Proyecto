<?php
require_once __DIR__ . '/../includes/dashboard_data.php';

// Datos del dashboard
$resumen         = getResumenGlobal($conn);
$tendencia30     = getTendencia30Dias($conn);
$ingresosInteres = getIngresosInteresMensual($conn);
$ultimosClientes = getUltimosClientes($conn);
$prestamosTop    = getPrestamosGrandesActivos($conn);
$proximosPagos   = getProximosPagos($conn);
$eventosCal      = getEventosCalendario($conn);
$alertasSys      = getAlertasSistema($conn);

// Helper para JSON seguro
function j($arr){ return htmlspecialchars(json_encode($arr), ENT_QUOTES, 'UTF-8'); }

// Ruta base para WAMP
$APP_BASE = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
if ($APP_BASE === '') $APP_BASE = '/';
$APP_BASE = $APP_BASE . (substr($APP_BASE,-1) === '/' ? '' : '/') ;
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title>Sistema de Préstamos - Panel General</title>
  <link rel="stylesheet" href="<?= $APP_BASE ?>public/css/dashboard.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<div class="app-shell">

  <!-- 
     SIDEBAR 
   -->
  <aside class="sidebar sidebar-expanded">
    <div class="sidebar-inner">

      <!-- DASHBOARD -->
      <div class="sidebar-section">
        <div class="section-label">DASHBOARD</div>

        <a class="nav-link active"
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

        <a class="nav-link"
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

  <!-- ╭─────────────────────────╮
       │ ÁREA PRINCIPAL (CLARO) │
       ╰─────────────────────────╯ -->
  <div class="main-area">

    <!-- TOPBAR CLARA -->
    <header class="topbar topbar-light">
      <div class="topbar-left">
        <div class="brand-inline">
          <span class="brand-logo">🏠</span>
          <span class="brand-name">Sistema de Préstamos</span>
        </div>

        <div class="range-pill">
          <span>📅 Últimos 30 días ▼</span>
        </div>
      </div>

      <div class="topbar-right">
        <button class="small-btn">⚙ Editar</button>

        <div class="user-chip">
          <div class="avatar-circle">R</div>
          <div class="user-info">
            <div class="user-name">Ricardo</div>
            <div class="user-role">Administrador</div>
          </div>
        </div>
      </div>
    </header>

    <!-- CONTENIDO DEL DASHBOARD -->
    <main class="page-wrapper">

      <!-- KPIs -->
      <section class="top-kpis">

        <div class="kpi-card green">
          <div class="kpi-label">Ganancia del mes</div>
          <div class="kpi-value">
            $<?= number_format($resumen['ganancia_mes'], 2) ?>
          </div>
        </div>

        <div class="kpi-card blue">
          <div class="kpi-label">Total Prestado</div>
          <div class="kpi-value">
            $<?= number_format($resumen['total_prestado'], 2) ?>
          </div>
        </div>

        <div class="kpi-card red">
          <div class="kpi-label">Clientes en mora</div>
          <div class="kpi-value">
            <?= $resumen['clientes_mora'] ?>
          </div>
        </div>

        <div class="kpi-card soft-green">
          <div class="kpi-label">Clientes al día</div>
          <div class="kpi-value">
            <?= $resumen['clientes_al_dia'] ?>
          </div>
        </div>

        <div class="kpi-card grayblue">
          <div class="kpi-label">Clientes Totales</div>
          <div class="kpi-value">
            <?= $resumen['clientes_totales'] ?>
          </div>
        </div>

        <div class="kpi-card yellow">
          <div class="kpi-label">Dinero disponible (flujo neto)</div>
          <div class="kpi-value">
            $<?= number_format($resumen['flujo_disponible'], 2) ?>
          </div>
        </div>

      </section>

      <!-- GRÁFICOS + LADO DERECHO -->
      <section class="middle-grid">

        <!-- IZQUIERDA: gráficos -->
        <div class="main-charts">

          <div class="card charts-card">
            <div class="card-header">
              <h3>Préstamos vs Pagos vs Mora (últimos 30 días)</h3>
              <small>Línea de tiempo diaria</small>
            </div>
            <div class="card-body">
              <canvas
                id="chartTendencia30d"
                data-series='<?= j($tendencia30) ?>'></canvas>
            </div>
          </div>

          <div class="card charts-card">
            <div class="card-header">
              <h3>Ingresos por interés</h3>
              <small>Acumulado por mes</small>
            </div>
            <div class="card-body">
              <canvas
                id="chartIngresosInteres"
                data-series='<?= j($ingresosInteres) ?>'></canvas>
            </div>
          </div>

        </div>

        <!-- DERECHA: calendario + alertas -->
        <aside class="side-panel">

          <div class="card">
            <div class="card-header">
              <h3>Calendario de cobros</h3>
              <small>🔴 Mora | 🟡 Próximo pago | 🟢 Pagado</small>
            </div>
            <div class="card-body">
              <div id="calendar"
                   data-events='<?= j($eventosCal) ?>'></div>
            </div>
          </div>

          <div class="card alert-card">
            <div class="card-header">
              <h3>Alertas</h3>
            </div>
            <div class="card-body">
              <p>🔔 <?= $alertasSys['casi_mora'] ?> cliente(s) están por caer en mora esta semana.</p>
              <p>⏰ Hoy se vencen <?= $alertasSys['vencen_hoy'] ?> cuota(s).</p>
            </div>
          </div>

        </aside>

      </section>

      <!-- TABLAS -->
      <section class="bottom-tables">

        <div class="card table-card">
          <div class="card-header">
            <h3>Últimos clientes nuevos</h3>
          </div>
          <div class="card-body">
            <table class="table-simple">
              <thead>
                <tr>
                  <th>Cliente</th>
                  <th>Registro</th>
                  <th>Monto solicitado</th>
                  <th>Estado préstamo</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($ultimosClientes as $c): ?>
                <tr>
                  <td><?= $c['nombre'] . ' ' . $c['apellido'] ?></td>
                  <td><?= $c['fecha_registro'] ?></td>
                  <td>$<?= number_format($c['monto_solicitado'] ?? 0, 2) ?></td>
                  <td><?= $c['estado_prestamo'] ?? '-' ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="card table-card">
          <div class="card-header">
            <h3>Préstamos más grandes (activos / en mora)</h3>
          </div>
          <div class="card-body">
            <table class="table-simple">
              <thead>
                <tr>
                  <th>Cliente</th>
                  <th>Monto</th>
                  <th>Inicio</th>
                  <th>1ra cuota</th>
                  <th>Última cuota</th>
                  <th>Estado</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($prestamosTop as $p): ?>
                <tr>
                  <td><?= $p['nombre'] . ' ' . $p['apellido'] ?></td>
                  <td>$<?= number_format($p['monto_solicitado'], 2) ?></td>
                  <td><?= $p['fecha_solicitud'] ?></td>
                  <td><?= $p['primera_cuota'] ?></td>
                  <td><?= $p['ultima_cuota'] ?></td>
                  <td><?= $p['estado_prestamo'] ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="card table-card">
          <div class="card-header">
            <h3>Próximos pagos</h3>
          </div>
          <div class="card-body">
            <table class="table-simple">
              <thead>
                <tr>
                  <th>Fecha</th>
                  <th>Cliente</th>
                  <th>Monto</th>
                  <th>Estado</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($proximosPagos as $p): ?>
                <tr class="estado-<?= strtolower($p['estado_cuota']) ?>">
                  <td><?= $p['fecha_vencimiento'] ?></td>
                  <td><?= $p['nombre'] . ' ' . $p['apellido'] ?></td>
                  <td>$<?= number_format($p['total_monto'], 2) ?></td>
                  <td><?= $p['estado_cuota'] ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>

            <small class="legend">
              Verde = al día, Rojo = vencida
            </small>
          </div>
        </div>

      </section>

    </main><!-- /page-wrapper -->

  </div><!-- /main-area -->
</div><!-- /app-shell -->

<!-- JS -->
<script src="<?= $APP_BASE ?>public/js/dashboard.js"></script>
<script src="<?= $APP_BASE ?>public/js/calendar.js"></script>
</body>
</html>
