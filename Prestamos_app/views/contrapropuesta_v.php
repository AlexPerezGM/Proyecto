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

require_once __DIR__ . '/../api/contrapropuesta.php';

$vm = obtener_contrapropuestas_para_prestamo($conn, $id_prestamo);
if (!$vm) {
    header('Location: resultado_v.php?id_prestamo=' . $id_prestamo);
    exit;
}

$prestamo = $vm['prestamo'];
$contrapropuestas = $vm['contrapropuestas'];
$monto_orig = (float)$vm['monto_orig'];
$plazo_orig = (int)$vm['plazo_orig'];
$cuota_orig = (float)$vm['cuota_orig'];
$cap_disponible = (float)$vm['cap_disponible'];
$requiere_ajuste = $cuota_orig > $cap_disponible;

if (empty($contrapropuestas)) {
    header('Location: resultado_v.php?id_prestamo=' . $id_prestamo);
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
  <title>Contrapropuesta - <?= htmlspecialchars((string)$prestamo['numero_contrato']) ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <base href="<?= $BASE ?>">
  <link rel="stylesheet" href="public/css/dashboard.css">
  <link rel="stylesheet" href="public/css/clientes.css">
  <link rel="stylesheet" href="public/css/prestamos.css">
  <link rel="stylesheet" href="public/css/contrapropuesta.css">
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
          <span class="brand-logo">🔄</span>
          <span class="brand-name">Contrapropuesta de Prestamo</span>
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
      <div class="cp-page">
        <div class="cp-header">
          <div style="flex:1;">
            <div class="cp-header-title">Seleccion de contrapropuestas</div>
            <div class="cp-header-sub">
              <?= htmlspecialchars((string)$prestamo['nombre_cliente'] . ' ' . (string)$prestamo['apellido_cliente']) ?>
              &nbsp;·&nbsp; <?= htmlspecialchars((string)$prestamo['numero_contrato']) ?>
            </div>
          </div>
          <?php if ($requiere_ajuste): ?>
          <div style="background:#fef3c7; color:#92400e; padding:7px 14px; border-radius:100px; font-weight:700; font-size:.82rem; border:1px solid #fde68a;">
            🔄 Ajuste requerido
          </div>
          <?php else: ?>
          <div style="background:#dcfce7; color:#166534; padding:7px 14px; border-radius:100px; font-weight:700; font-size:.82rem; border:1px solid #86efac;">
            ✅ Solicitud viable
          </div>
          <?php endif; ?>
        </div>

        <div class="cp-original">
          <div class="cp-original-title">Solicitud original</div>
          <div class="cp-orig-grid">
            <div class="cp-orig-item">
              <label>Monto solicitado</label>
              <div class="value">RD$ <?= number_format($monto_orig, 2) ?></div>
            </div>
            <div class="cp-orig-item">
              <label>Plazo</label>
              <div class="value"><?= (int)$plazo_orig ?> meses</div>
            </div>
            <div class="cp-orig-item">
              <label>Cuota req. vs disponible</label>
              <div class="value <?= $requiere_ajuste ? 'danger' : '' ?>">RD$ <?= number_format($cuota_orig, 2) ?></div>
              <div class="sub">vs RD$ <?= number_format($cap_disponible, 2) ?> disponible</div>
            </div>
          </div>
        </div>

        <?php if ($requiere_ajuste): ?>
        <div class="cp-explainer">
          ⚠️
          <div>La cuota mensual del prestamo solicitado supera la capacidad de pago disponible del cliente. Selecciona una alternativa para continuar.</div>
        </div>
        <?php else: ?>
        <div class="cp-explainer" style="background:#f0fdf4; border-color:#bbf7d0; color:#166534;">
          ✅
          <div>La solicitud original ya cumple con la capacidad disponible. Si llegaste aqui por una evaluacion anterior, reevalua o vuelve al resultado para continuar con aprobacion.</div>
        </div>
        <?php endif; ?>

        <div class="cp-options-grid">
          <?php foreach ($contrapropuestas as $cp): ?>
          <div class="cp-option-card" id="card-opcion-<?= (int)$cp['opcion'] ?>">
            <div class="cp-option-num">Opcion <?= (int)$cp['opcion'] ?></div>
            <div class="cp-option-monto">RD$ <?= number_format((float)$cp['monto'], 2) ?></div>
            <div class="cp-option-rows">
              <div class="cp-option-row">
                <span class="lbl">Plazo</span>
                <span class="val"><?= (int)$cp['plazo'] ?> meses</span>
              </div>
              <div class="cp-option-row">
                <span class="lbl">Cuota mensual</span>
                <span class="val" style="color:#16a34a;">RD$ <?= number_format((float)$cp['cuota'], 2) ?></span>
              </div>
              <div class="cp-option-row">
                <span class="lbl">Tasa interes</span>
                <span class="val"><?= number_format((float)$cp['tasa'], 2) ?>%</span>
              </div>
              <div class="cp-option-row">
                <span class="lbl">Total a pagar</span>
                <span class="val">RD$ <?= number_format((float)$cp['total_pagar'], 2) ?></span>
              </div>
            </div>
            <div class="cp-option-desc"><?= htmlspecialchars((string)($cp['descripcion'] ?? '')) ?></div>
            <button
              class="cp-option-btn"
              onclick='abrirConfirmacion(<?= htmlspecialchars(json_encode($cp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, "UTF-8") ?>)'>
              📋 Seleccionar
            </button>
          </div>
          <?php endforeach; ?>
        </div>

        <div class="cp-reject-wrap">
          <button class="cp-reject-btn" onclick="rechazarTodo()">📋 Rechazar contrapropuestas / cancelar solicitud</button>
        </div>
      </div>
    </div>
  </main>
</div>

<div class="cp-confirm-overlay" id="confirmOverlay">
  <div class="cp-confirm-dialog">
    <div class="cp-confirm-title">Confirmar seleccion de contrapropuesta</div>
    <div class="cp-confirm-sub">Detalles de la opcion elegida:</div>
    <div class="cp-confirm-detail" id="confirmDetail"></div>
    <div class="cp-confirm-btns">
      <button class="cp-confirm-ok" id="confirmOkBtn">📋 Confirmar contrapropuesta</button>
      <button class="cp-confirm-cancel" onclick="cerrarModal()">✕ Elegir otra opcion</button>
    </div>
  </div>
</div>

<script src="public/JS/contrapropuesta.js"></script>
</body>
</html>
