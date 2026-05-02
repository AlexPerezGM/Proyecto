<?php
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../api/autorizacion.php';
requiere_login();
require_permission('prestamos', 'admin');

$id_prestamo = (int)($_GET['id_prestamo'] ?? 0);
if ($id_prestamo <= 0) {
    header('Location: prestamos.php');
    exit;
}

$stmt = $conn->prepare("\n    SELECT p.id_prestamo, p.numero_contrato, p.monto_solicitado, p.plazo_meses,\n           tp.nombre AS tipo_prestamo,\n           dp.nombre AS nombre_cliente, dp.apellido AS apellido_cliente,\n           cep.estado AS estado_prestamo\n    FROM prestamo p\n    INNER JOIN tipo_prestamo tp ON tp.id_tipo_prestamo = p.id_tipo_prestamo\n    INNER JOIN cliente c ON c.id_cliente = p.id_cliente\n    INNER JOIN datos_persona dp ON dp.id_datos_persona = c.id_datos_persona\n    LEFT JOIN cat_estado_prestamo cep ON cep.id_estado_prestamo = p.id_estado_prestamo\n    WHERE p.id_prestamo = ?\n    LIMIT 1\n");

if (!$stmt) {
    header('Location: prestamos.php');
    exit;
}

$stmt->bind_param('i', $id_prestamo);
$stmt->execute();
$prestamo = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$prestamo) {
    header('Location: prestamos.php');
    exit;
}

$APP_BASE = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$APP_BASE = preg_replace('#/views$#', '', $APP_BASE);
$APP_BASE = ($APP_BASE === '' ? '/' : $APP_BASE . '/');
$BASE = $APP_BASE;
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Evaluacion de Prestamo - <?= htmlspecialchars($prestamo['numero_contrato']) ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <base href="<?= $BASE ?>">
  <link rel="stylesheet" href="public/css/dashboard.css">
  <link rel="stylesheet" href="public/css/clientes.css">
  <link rel="stylesheet" href="public/css/prestamos.css">
  <link rel="stylesheet" href="public/css/evaluacion.css">
  <script>
    window.APP_BASE = <?= json_encode($APP_BASE, JSON_UNESCAPED_SLASHES) ?>;
    window.ID_PRESTAMO = <?= (int)$id_prestamo ?>;
  </script>
</head>
<body>
<div class="app-shell">
  <aside class="sidebar sidebar-expanded">
    <div class="sidebar-inner">
      <div class="sidebar-section">
        <div class="section-label">DASHBOARD</div>
        <a class="nav-link" href="<?= $APP_BASE ?>views/dashboard.php">
          <span class="nav-icon">🏠</span>
          <span class="nav-text">Dashboard</span>
        </a>
      </div>
      <div class="sidebar-section">
        <div class="section-label">GESTION</div>
        <a class="nav-link" href="<?= $APP_BASE ?>views/clientes.php">
          <span class="nav-icon">👥</span>
          <span class="nav-text">Gestion de Clientes</span>
        </a>
        <a class="nav-link active" href="<?= $APP_BASE ?>views/prestamos.php">
          <span class="nav-icon">💼</span>
          <span class="nav-text">Control de Prestamos</span>
        </a>
        <a class="nav-link" href="<?= $APP_BASE ?>views/pagos.php">
          <span class="nav-icon">💰</span>
          <span class="nav-text">Gestion de Pagos</span>
        </a>
        <a class="nav-link" href="<?= $APP_BASE ?>views/seguimiento.php">
          <span class="nav-icon">📈</span>
          <span class="nav-text">Seguimiento de Prestamos</span>
        </a>
      </div>
      <div class="sidebar-section">
        <div class="section-label">ADMINISTRACION</div>
        <a class="nav-link" href="<?= $APP_BASE ?>views/seguridad.php">
          <span class="nav-icon">🔐</span>
          <span class="nav-text">Usuarios y Roles</span>
        </a>
        <a class="nav-link" href="<?= $APP_BASE ?>views/rrhh.php">
          <span class="nav-icon">🧑</span>
          <span class="nav-text">Recursos Humanos</span>
        </a>
        <a class="nav-link" href="<?= $APP_BASE ?>views/configuracion.php">
          <span class="nav-icon">⚙️</span>
          <span class="nav-text">Configuracion</span>
        </a>
        <a class="nav-link" href="<?= $APP_BASE ?>api/cerrar_sesion.php">
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
          <span class="brand-logo">⚖️</span>
          <span class="brand-name">Evaluacion de Prestamo</span>
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
      <div class="ev-page">
        <div class="ev-header-card">
          <div class="ev-header-title">Evaluacion de solicitud de prestamo</div>
          <div class="ev-contract-badge"><?= htmlspecialchars($prestamo['numero_contrato']) ?></div>
        </div>

        <div class="ev-client-strip">
          <div class="field">
            <label>Cliente</label>
            <span><?= htmlspecialchars($prestamo['nombre_cliente'] . ' ' . $prestamo['apellido_cliente']) ?></span>
          </div>
          <div class="field">
            <label>Tipo de prestamo</label>
            <span><?= htmlspecialchars($prestamo['tipo_prestamo']) ?></span>
          </div>
          <div class="field">
            <label>Monto solicitado</label>
            <span>RD$ <?= number_format((float)$prestamo['monto_solicitado'], 2) ?></span>
          </div>
          <div class="field">
            <label>Plazo</label>
            <span><?= (int)$prestamo['plazo_meses'] ?> meses</span>
          </div>
          <div class="field">
            <label>Estado actual</label>
            <span><?= htmlspecialchars($prestamo['estado_prestamo'] ?: 'N/D') ?></span>
          </div>
        </div>

        <div class="ev-progress-panel">
          <div class="ev-spinner-wrap" id="spinnerWrap">
            <div class="ev-spinner" id="mainSpinner"></div>
            <div class="ev-spinner-icon" id="spinnerIcon">🔍</div>
          </div>

          <div class="ev-main-label">
            <h2 id="mainLabel">Ejecutando evaluacion automatica</h2>
            <p id="mainSub">Por favor espere mientras el sistema analiza la solicitud</p>
          </div>

          <div class="ev-progress-bar-wrap">
            <div class="ev-progress-bar-track">
              <div class="ev-progress-bar-fill" id="progressBar"></div>
            </div>
            <div class="ev-progress-pct" id="progressPct">0%</div>
          </div>

          <div class="ev-steps" id="stepsContainer">
            <div class="ev-step" id="step-1" data-step="1">
              <div class="ev-step-icon">📋</div>
              <div>
                <div class="ev-step-label">Evaluando datos del cliente</div>
                <div class="ev-step-sub">Verificando informacion personal y laboral</div>
              </div>
            </div>
            <div class="ev-step" id="step-2" data-step="2">
              <div class="ev-step-icon">💰</div>
              <div>
                <div class="ev-step-label">Calculando capacidad de pago</div>
                <div class="ev-step-sub">Analizando ingresos, egresos y otro datos importantes</div>
              </div>
            </div>
            <div class="ev-step" id="step-3" data-step="3">
              <div class="ev-step-icon">🏆</div>
              <div>
                <div class="ev-step-label">Calculando puntaje interno</div>
                <div class="ev-step-sub">Evaluando historial, comportamiento y perfil de riesgo</div>
              </div>
            </div>
            <div class="ev-step" id="step-4" data-step="4">
              <div class="ev-step-icon">⚖️</div>
              <div>
                <div class="ev-step-label">Tomando decision</div>
                <div class="ev-step-sub">Aplicando reglas de negocio y politicas de credito</div>
              </div>
            </div>
          </div>

          <div class="ev-results-box" id="resultsBox">
            <div class="hint">Los resultados apareceran aqui al finalizar el analisis...</div>
          </div>

          <button class="ev-btn-results" id="btnVerResultados">📊 Ver resultados completos</button>
        </div>
      </div>
    </div>
  </main>
</div>

<script src="public/JS/evaluacion.js"></script>
</body>
</html>