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
        <a class="nav-link" href="<?= $APP_BASE ?>views/dashboard.php">
          <span class="nav-icon">🏠</span><span class="nav-text">Dashboard</span>
        </a>
      </div>
      <div class="sidebar-section">
        <div class="section-label">GESTION</div>
        <a class="nav-link" href="<?= $APP_BASE ?>views/clientes.php">
          <span class="nav-icon">👥</span><span class="nav-text">Gestion de Clientes</span>
        </a>
        <a class="nav-link active" href="<?= $APP_BASE ?>views/prestamos.php">
          <span class="nav-icon">💼</span><span class="nav-text">Control de Prestamos</span>
        </a>
        <a class="nav-link" href="<?= $APP_BASE ?>views/pagos.php">
          <span class="nav-icon">💰</span><span class="nav-text">Gestion de Pagos</span>
        </a>
        <a class="nav-link" href="<?= $APP_BASE ?>views/seguimiento.php">
          <span class="nav-icon">📈</span><span class="nav-text">Seguimiento de Prestamos</span>
        </a>
        <a class="nav-link" href="<?= $APP_BASE ?>views/reestructuracion.php">
          <span class="nav-icon">♻️</span><span class="nav-text">Reestructuración de Préstamos</span>
        </a>
      </div>
      <div class="sidebar-section">
        <div class="section-label">ADMINISTRACION</div>
        <a class="nav-link" href="<?= $APP_BASE ?>views/seguridad.php">
          <span class="nav-icon">🔐</span><span class="nav-text">Usuarios y Roles</span>
        </a>
        <a class="nav-link" href="<?= $APP_BASE ?>views/rrhh.php">
          <span class="nav-icon">🧑</span><span class="nav-text">Recursos Humanos</span>
        </a>
        <a class="nav-link" href="<?= $APP_BASE ?>views/configuracion.php">
          <span class="nav-icon">⚙️</span><span class="nav-text">Configuracion</span>
        </a>
        <a class="nav-link" href="<?= $APP_BASE ?>api/cerrar_sesion.php">
          <span class="nav-icon">🚪</span><span class="nav-text">Cerrar Sesion</span>
        </a>
      </div>
    </div>
  </aside>

  <main class="content-area">
    <header class="topbar topbar-light">
      <div class="topbar-left">
        <div class="brand-inline">
          <span class="brand-logo">📊</span>
          <span class="brand-name">Sistema de Análisis Financiero</span>
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

    <div class="hud-workspace">
        
        <!-- CABECERA: ACCIONES Y DICTAMEN PRINCIPAL -->
        <div class="hud-top-banner">
            <div class="hud-banner-info">
                <div class="hud-banner-badge"><?= $col['icon'] ?> <?= htmlspecialchars($col['label']) ?></div>
                <h1 class="hud-banner-title">Evaluación de Solicitud</h1>
                <p class="hud-banner-subtitle">Contrato N° <?= htmlspecialchars((string)$ev['numero_contrato']) ?> • Evaluación: <?= htmlspecialchars((string)$ev['fecha_evaluacion']) ?></p>
            </div>
            
            <!-- Botones de Acción Primero -->
            <div class="hud-actions-zone">
                <button class="hud-btn hud-btn-outline" onclick="window.location='<?= $APP_BASE ?>views/prestamos.php'">← Retornar</button>
                <button class="hud-btn hud-btn-outline" onclick="window.print()">🖨️ Exportar / Imprimir</button>
                <?php if ($decision === 'Aprobado'): ?>
                <button class="hud-btn hud-btn-action" id="btnGenerarContrato">📋 Generar Contrato</button>
                <?php endif; ?>
            </div>
        </div>

        <div class="hud-main-grid">
            
            <!-- PANEL 1: MOTOR DE RIESGO -->
            <div class="hud-panel">
                <div class="hud-panel-header">
                    <h3>🏆 Motor de Riesgo y Perfil</h3>
                </div>
                <div class="hud-panel-body">
                    <?php
                        $puntajeNum = is_numeric($puntaje) ? (int)$puntaje : 0;
                        $scoreClass = $puntajeNum >= 80 ? 'score-optimal' : ($puntajeNum >= 40 ? 'score-warning' : 'score-critical');
                        $riskClass = ($ev['nivel_riesgo'] ?? '') === 'Bajo' ? 'risk-low' : ((($ev['nivel_riesgo'] ?? '') === 'Medio') ? 'risk-mid' : 'risk-high');
                    ?>
                    
                    <div class="hud-score-display">
                        <div class="hud-score-circle <?= $scoreClass ?>">
                            <span class="score-value"><?= htmlspecialchars((string)$puntaje) ?></span>
                            <span class="score-label">PUNTOS</span>
                        </div>
                        <div class="hud-risk-tag <?= $riskClass ?>">Riesgo <?= htmlspecialchars((string)($ev['nivel_riesgo'] ?? 'N/D')) ?></div>
                    </div>

                    <div class="hud-data-list">
                        <div class="hud-data-row"><span>Comportamiento de Pagos</span><strong><?= (int)$hist['pagos_ok'] ?> al día / <?= (int)$hist['pagos_vencidos'] ?> venc.</strong></div>
                        <div class="hud-data-row"><span>Antigüedad del Cliente</span><strong><?= (int)$mesesCliente ?> meses</strong></div>
                        <div class="hud-data-row"><span>Créditos Activos</span><strong><?= (int)$hist['prestamos_activos'] ?></strong></div>
                        <div class="hud-data-row"><span>Score Buró Externo</span><strong><?= htmlspecialchars((string)($ev['score_crediticio'] ?? '—')) ?></strong></div>
                    </div>
                </div>
            </div>

            <!-- PANEL 2: TELEMETRÍA DE CAPACIDAD -->
            <div class="hud-panel">
                <div class="hud-panel-header">
                    <h3>💰 Telemetría de Capacidad</h3>
                </div>
                <div class="hud-panel-body">
                    <div class="hud-data-list">
                        <div class="hud-data-row"><span>Ingresos Verificados</span><strong>RD$ <?= number_format((float)($ev['ingresos_mensuales'] ?? 0), 2) ?></strong></div>
                        <div class="hud-data-row"><span>Egresos Estimados</span><strong>RD$ <?= number_format((float)($ev['egresos_mensuales'] ?? 0), 2) ?></strong></div>
                        <div class="hud-data-row highlight"><span>Capacidad Neta (Margen)</span><strong style="color: #4f46e5;">RD$ <?= number_format($capacidad, 2) ?></strong></div>
                        <div class="hud-data-row highlight"><span>Proyección de Cuota</span><strong style="color: <?= $cuota <= $capacidad ? '#16a34a' : '#dc2626' ?>;">RD$ <?= number_format($cuota, 2) ?></strong></div>
                    </div>

                    <div class="hud-meter-container">
                        <div class="hud-meter-labels">
                            <span>Ocupación de Capacidad</span>
                            <span><?= (int)$porcCubierto ?>%</span>
                        </div>
                        <div class="hud-meter-track">
                            <div class="hud-meter-fill" style="width: var(--res-cap-fill-width); background: var(--res-cap-fill-bg);"></div>
                        </div>
                        <div class="hud-meter-legend">
                            <span>0%</span>
                            <span style="color: var(--res-cap-fill-bg); font-weight: 700;">Estado Actual</span>
                            <span>100% (Tope)</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PANEL 3: ESTRUCTURA DEL CONTRATO Y LOG -->
            <div class="hud-panel panel-span-all">
                <div class="hud-panel-header">
                    <h3>📄 Estructura Financiera Propuesta</h3>
                </div>
                <div class="hud-panel-body hud-contract-grid">
                    <div class="hud-contract-item"><label>Tipo Producto</label><span><?= htmlspecialchars((string)$ev['tipo_prestamo']) ?></span></div>
                    <div class="hud-contract-item"><label>Monto Base</label><span>RD$ <?= number_format($monto, 2) ?></span></div>
                    <div class="hud-contract-item"><label>Plazo Aprobado</label><span><?= (int)$plazo ?> meses</span></div>
                    <div class="hud-contract-item"><label>Tasa Retorno</label><span><?= number_format($tasaAnual, 2) ?>% anual</span></div>
                    <div class="hud-contract-item"><label>Amortización</label><span><?= htmlspecialchars((string)($ev['tipo_amortizacion'] ?? 'N/D')) ?></span></div>
                    <div class="hud-contract-item"><label>Total Proyectado</label><span>RD$ <?= number_format($totalPagar, 2) ?></span></div>
                </div>
                
                <!-- CONSOLA DE SISTEMA Y ALERTAS -->
                <div class="hud-system-console">
                    <div class="console-header">STATUS DEL SISTEMA: 
                        <?php
                          if ($decision === 'Aprobado') echo '🟢 LISTO PARA DESEMBOLSO';
                          elseif ($decision === 'Rechazado') echo '🔴 OPERACIÓN DENEGADA';
                          elseif ($decision === 'Contrapropuesta') echo '🟡 VALIDACIÓN TÁCTICA PENDIENTE';
                          elseif ($decision === 'Revision_manual') echo '🟠 REQUERIDA INTERVENCIÓN HUMANA';
                          else echo '⚪ PENDIENTE';
                        ?>
                    </div>
                    
                    <?php if ($decision === 'Rechazado' && !empty($razones)): ?>
                    <ul class="console-log log-error">
                      <?php foreach ($razones as $r): ?>
                      <li>[CRÍTICO] <?= htmlspecialchars($r) ?></li>
                      <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>

                    <?php if ($decision === 'Revision_manual' || $decision === 'Pendiente'): ?>
                    <div class="console-log log-warning">
                        > [ALERTA] Análisis algorítmico pausado. Se requiere revisión manual por un analista de crédito para emitir veredicto final.
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
  </main>
</div>

<script src="public/JS/resultado.js"></script>
</body>
</html>