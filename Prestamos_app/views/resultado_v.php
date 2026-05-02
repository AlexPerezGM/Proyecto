<?php
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../api/autorizacion.php';
requiere_login();
if (!has_permission('prestamos') && !has_permission('seguimiento')) {
    header('Location: 403.php');
    exit;
}

$id_prestamo = (int)($_GET['id_prestamo'] ?? 0);
if ($id_prestamo <= 0) {
    header('Location: prestamos.php');
    exit;
}

$id_evaluacion = (int)($_GET['id_evaluacion'] ?? 0);
$id_evaluacion = $id_evaluacion > 0 ? $id_evaluacion : null;

require_once __DIR__ . '/../api/resultado.php';

$vm = obtener_resultado_evaluacion($conn, $id_prestamo, $id_evaluacion);
if (!$vm) {
  header('Location: prestamos.php');
  exit;
}

extract($vm, EXTR_SKIP);

$APP_BASE = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$APP_BASE = preg_replace('#/views$#', '', $APP_BASE);
$APP_BASE = ($APP_BASE === '' ? '/' : $APP_BASE . '/');
$BASE = $APP_BASE;
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Resultado de Evaluacion - <?= htmlspecialchars((string)$ev['numero_contrato']) ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <base href="<?= $BASE ?>">
  <link rel="stylesheet" href="public/css/dashboard.css">
  <link rel="stylesheet" href="public/css/clientes.css">
  <link rel="stylesheet" href="public/css/prestamos.css">
  <link rel="stylesheet" href="public/css/resultado.css">
  <style>
    :root {
      --res-badge-bg: <?= htmlspecialchars($col['badge_bg']) ?>;
      --res-estado-border: <?= htmlspecialchars($col['estado_border']) ?>;
      --res-estado-bg: <?= htmlspecialchars($col['estado_bg']) ?>;
      --res-cap-fill-bg: <?= htmlspecialchars($col['cap_bg']) ?>;
      --res-cap-fill-width: <?= (int)$porcCubierto ?>%;
    }
  </style>
  <script>
    window.APP_BASE = <?= json_encode($APP_BASE, JSON_UNESCAPED_SLASHES) ?>;
    window.ID_PRESTAMO = <?= (int)$id_prestamo ?>;
    window.ID_EVALUACION = <?= (int)($id_evaluacion ?? 0) ?>;
  </script>
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
        <div class="section-label">GESTION</div>
        <a class="nav-link" 
          href="<?= $APP_BASE ?>views/clientes.php">
          <span class="nav-icon">👥</span>
          <span class="nav-text">Gestion de Clientes</span>
        </a>
        <a class="nav-link active" 
          href="<?= $APP_BASE ?>views/prestamos.php">
          <span class="nav-icon">💼</span>
          <span class="nav-text">Control de Prestamos</span>
        </a>
        <a class="nav-link" 
          href="<?= $APP_BASE ?>views/pagos.php">
          <span class="nav-icon">💰</span>
          <span class="nav-text">Gestion de Pagos</span>
        </a>
        <a class="nav-link" 
          href="<?= $APP_BASE ?>views/seguimiento.php">
          <span class="nav-icon">📈</span>
          <span class="nav-text">Seguimiento de Prestamos</span>
        </a>
      </div>
      <div class="sidebar-section">
        <div class="section-label">ADMINISTRACION</div>
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
          <span class="nav-text">Configuracion</span>
        </a>
        <a class="nav-link" 
          href="<?= $APP_BASE ?>api/cerrar_sesion.php">
          <span class="nav-icon">🚪</span>
          <span class="nav-text">Cerrar Sesion</span>
        </a>
      </div>
    </div>
  </aside>

  <main class="content-area">
    <header class="topbar topbar-light">
      <div class="topbar-left">
        <div class="brand-inline">
          <span class="brand-logo">📊</span>
          <span class="brand-name">Resultado de Evaluacion</span>
        </div>
      </div>
      <div class="topbar-right">
        <?php if (!empty($_SESSION['usuario'])): ?>
        <div class="user-chip">
          <div class="avatar-circle"><?= htmlspecialchars($_SESSION['usuario']['inicial_empleado'] ?? '') ?></div>
          <div class="user-info">
            <div class="user-name"><?= htmlspecialchars($_SESSION['usuario']['nombre_empleado'] ?? $_SESSION['usuario']['nombre_usuario'] ?? '') ?></div>
            <div class="user-role"><?= htmlspecialchars($_SESSION['usuario']['rol'] ?? '') ?></div>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </header>

    <div class="page-wrapper">
      <div class="res-page">
        <div class="res-header">
          <div class="res-header-title">Resultados de la evaluacion</div>
          <div class="res-decision-badge"><?= $col['icon'] ?> <?= htmlspecialchars($col['label']) ?></div>
        </div>

        <div class="res-grid">
          <div style="display:flex; flex-direction:column; gap:16px;">
            <div class="res-card">
              <div class="res-card-title">🏆 Puntaje interno del cliente</div>
              <?php
                $puntajeNum = is_numeric($puntaje) ? (int)$puntaje : 0;
                $scoreClass = $puntajeNum >= 80 ? 'res-score-green' : ($puntajeNum >= 40 ? 'res-score-yellow' : 'res-score-red');
                $riskClass = ($ev['nivel_riesgo'] ?? '') === 'Bajo' ? 'risk-bajo' : ((($ev['nivel_riesgo'] ?? '') === 'Medio') ? 'risk-medio' : 'risk-alto');
              ?>
              <div class="res-score-big <?= $scoreClass ?>"><?= htmlspecialchars((string)$puntaje) ?></div>
              <span class="res-risk-label <?= $riskClass ?>">Nivel de riesgo: <?= htmlspecialchars((string)($ev['nivel_riesgo'] ?? 'N/D')) ?></span>

              <div style="margin-top:18px;">
                <div class="res-hist-row">
                  <span>Historial de pagos</span>
                  <span id="txtHistPagos"><?= (int)$hist['pagos_ok'] ?> al dia, <?= (int)$hist['pagos_vencidos'] ?> vencidos</span>
                </div>
                <div class="res-hist-row">
                  <span>Tiempo como cliente</span>
                  <span id="txtTiempoCliente"><?= (int)$mesesCliente ?> mes(es)</span>
                </div>
                <div class="res-hist-row">
                  <span>Creditos activos</span>
                  <span id="txtCreditosActivos"><?= (int)$hist['prestamos_activos'] ?></span>
                </div>
                <div class="res-hist-row">
                  <span>Score crediticio</span>
                  <span><?= htmlspecialchars((string)($ev['score_crediticio'] ?? '—')) ?></span>
                </div>
              </div>
            </div>

            <div class="res-card">
              <div class="res-card-title">💰 Capacidad de pago</div>
              <div class="res-hist-row">
                <span>Ingresos mensuales</span>
                <span>RD$ <?= number_format((float)($ev['ingresos_mensuales'] ?? 0), 2) ?></span>
              </div>
              <div class="res-hist-row">
                <span>Egresos mensuales</span>
                <span>RD$ <?= number_format((float)($ev['egresos_mensuales'] ?? 0), 2) ?></span>
              </div>
              <div class="res-hist-row">
                <span>Capacidad de pago neta</span>
                <span style="color:#4f46e5; font-weight:700;">RD$ <?= number_format($capacidad, 2) ?></span>
              </div>
              <div class="res-hist-row">
                <span>Cuota mensual calculada</span>
                <span style="color:<?= $cuota <= $capacidad ? '#16a34a' : '#dc2626' ?>; font-weight:700;">RD$ <?= number_format($cuota, 2) ?></span>
              </div>

              <div class="res-cap-bar-wrap">
                <div style="font-size:.78rem; color:#6b7280; margin-bottom:4px;">Porcentaje cubierto por la capacidad de pago</div>
                <div class="res-cap-bar-track">
                  <div class="res-cap-bar-fill"></div>
                </div>
                <div class="res-cap-bar-label">
                  <span>0%</span>
                  <span style="font-weight:700; color:#111;"><?= (int)$porcCubierto ?>%</span>
                  <span>100%</span>
                </div>
              </div>
            </div>
          </div>

          <div style="display:flex; flex-direction:column; gap:16px;">
            <div class="res-card">
              <div class="res-card-title">📄 Condiciones del prestamo</div>
              <div class="res-cond-grid">
                <div class="res-cond-item">
                  <label>Numero de contrato</label>
                  <span><?= htmlspecialchars((string)$ev['numero_contrato']) ?></span>
                </div>
                <div class="res-cond-item">
                  <label>Tipo de prestamo</label>
                  <span><?= htmlspecialchars((string)$ev['tipo_prestamo']) ?></span>
                </div>
                <div class="res-cond-item">
                  <label>Monto solicitado</label>
                  <span>RD$ <?= number_format($monto, 2) ?></span>
                </div>
                <div class="res-cond-item">
                  <label>Total a pagar</label>
                  <span>RD$ <?= number_format($totalPagar, 2) ?></span>
                </div>
                <div class="res-cond-item">
                  <label>Tasa de interes</label>
                  <span><?= number_format($tasaAnual, 2) ?>% anual</span>
                </div>
                <div class="res-cond-item">
                  <label>Fecha de evaluacion</label>
                  <span><?= htmlspecialchars((string)$ev['fecha_evaluacion']) ?></span>
                </div>
                <div class="res-cond-item">
                  <label>Plazo</label>
                  <span><?= (int)$plazo ?> meses</span>
                </div>
                <div class="res-cond-item">
                  <label>Tipo de amortizacion</label>
                  <span><?= htmlspecialchars((string)($ev['tipo_amortizacion'] ?? 'N/D')) ?></span>
                </div>
                <div class="res-cond-item">
                  <label>Cuota mensual</label>
                  <span>RD$ <?= number_format($cuota, 2) ?></span>
                </div>
              </div>

              <div class="res-estado-banner">
                <?= $col['icon'] ?>
                Estado:
                <?php
                  if ($decision === 'Aprobado') {
                      echo 'Aprobado - listo para desembolso';
                  } elseif ($decision === 'Rechazado') {
                      echo 'Rechazado';
                  } elseif ($decision === 'Contrapropuesta') {
                      echo 'Se requiere validar contrapropuesta';
                  } elseif ($decision === 'Revision_manual') {
                      echo 'En revision manual por analista de credito';
                  } else {
                      echo 'Pendiente';
                  }
                ?>
              </div>

              <?php if ($decision === 'Rechazado' && !empty($razones)): ?>
              <div style="margin-top:14px;">
                <div class="res-card-title" style="color:#dc2626;">⚠️ Razones de rechazo</div>
                <ul class="res-rejection-list">
                  <?php foreach ($razones as $r): ?>
                  <li><?= htmlspecialchars($r) ?></li>
                  <?php endforeach; ?>
                </ul>
              </div>
              <?php endif; ?>

              <?php if ($decision === 'Revision_manual' || $decision === 'Pendiente'): ?>
              <div style="margin-top:14px; padding:12px; background:#fffbeb; border:1px solid #fde68a; border-radius:8px; font-size:.88rem; color:#78350f;">
                <strong>⏳ Revision manual requerida:</strong> El analista revisara la solicitud antes de emitir una respuesta final.
              </div>
              <?php endif; ?>
            </div>

            <div class="res-actions">
              <button class="res-btn res-btn-back" onclick="window.location='<?= $APP_BASE ?>views/prestamos.php'">← Salir</button>
              <button class="res-btn res-btn-print" onclick="window.print()">🖨️ Imprimir</button>
              <?php if ($decision === 'Aprobado'): ?>
              <button class="res-btn res-btn-primary" id="btnGenerarContrato">📋 Generar contrato</button>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<script src="public/JS/resultado.js"></script>
</body>
</html>
