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

$razones_rechazo = $_SESSION['razones_rechazo'][$id_prestamo] ?? [];


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

        <?php if ($requiere_ajuste || !empty($razones_rechazo)): ?>
        <div class="cp-explainer">
          ⚠️
          <div>
            <strong style="color: #b91c1c;">La solicitud original no es viable debido a las siguientes restricciones:</strong>
            <ul style="margin-top: 8px; margin-left: 20px; font-weight: 500; font-size: 0.85rem; color: #92400e; list-style-type: disc;">
                <?php foreach ($razones_rechazo as $razon): ?>
                    <li style="margin-bottom: 4px;"><?= htmlspecialchars($razon) ?></li>
                <?php endforeach; ?>
                <?php if(empty($razones_rechazo)): ?>
                    <li>El perfil de riesgo crediticio del cliente no permite aprobar las condiciones originales.</li>
                <?php endif; ?>
            </ul>
            <p style="margin-top: 10px; font-weight: 600;">El sistema ha generado automáticamente las siguientes opciones estratégicas viables para retener la solicitud de forma segura.</p>
          </div>
        </div>
        <?php else: ?>
        <div class="cp-explainer" style="background:#f0fdf4; border-color:#bbf7d0; color:#166534;">
          ✅
          <div>La solicitud original ya cumple con las políticas y capacidad disponible.</div>
        </div>
        <?php endif; ?>

        
        <div class="cp-option-card <?= ($es_aprobado) ? '' : (($cp['opcion'] == 1) ? 'selected' : '') ?>" id="card-<?= $cp['opcion'] ?>">
          
          <!-- Indicador tipo LED futurista -->
          <div style="display: flex; align-items: center; gap: 8px;">
            <div style="width: 8px; height: 8px; background: var(--cp-primary); border-radius: 50%; box-shadow: 0 0 8px var(--cp-primary);"></div>
            <div style="font-size: .78rem; font-weight: 800; color: var(--cp-primary); letter-spacing: 0.1em;">
              SYS_OPT_<?= $cp['opcion'] ?> // ESTRATEGIA
            </div>
          </div>

          <div class="cp-option-monto">RD$ <?= number_format($cp['monto'], 2) ?></div>
          
          <div style="border-top: 1px dashed var(--cp-border); padding-top: 10px;">
            <div class="cp-option-row"><span style="font-family: monospace;">[PLAZO]</span><strong><?= $cp['plazo'] ?> MESES</strong></div>
            <div class="cp-option-row"><span style="font-family: monospace;">[CUOTA_SEGURA]</span><strong style="color: var(--cp-success);">RD$ <?= number_format($cp['cuota'], 2) ?></strong></div>
          </div>
          
          <div style="font-size: .75rem; color: #6b7280; font-family: monospace;">> <?= htmlspecialchars($cp['descripcion'] ?? 'Ajuste paramétrico activo.') ?></div>
          
          <!-- Botón de acción -->
          <button class="cp-option-btn" onclick='abrirConfirmacion(<?= htmlspecialchars(json_encode($cp, JSON_UNESCAPED_UNICODE), ENT_QUOTES, "UTF-8") ?>)'>
            [ EJECUTAR OFERTA ]
          </button>
        </div>

        <div class="cp-reject-wrap">
          <button class="cp-reject-btn" onclick="rechazarTodo()">✖️ Ignorar / Rechazar y Cancelar Solicitud</button>
        </div>
      </div>
    </div>
  </main>
</div>

<div class="cp-confirm-overlay" id="confirmOverlay">
  <div class="cp-confirm-dialog" style="border-radius: 0; border: 1px solid var(--cp-primary); box-shadow: 0 0 30px rgba(79,70,229,0.3);">
    
    <h3 style="margin-bottom: 10px; font-family: monospace; color: var(--cp-primary);">> CONFIRMACIÓN_REQUERIDA</h3>
    <p style="color: #6b7280; font-size: 0.9rem; margin-bottom: 20px;">Inicializando recálculo de cronograma...</p>
    
    <div id="confirmDetail" style="background: rgba(79,70,229,0.05); border-left: 3px solid var(--cp-primary); padding:15px; margin-bottom:20px; font-family: monospace;"></div>
    
    <div style="display:flex; gap: 10px; flex-direction: column;">
        <!-- Botón de confirmar destacado con el estilo futurista -->
        <button class="cp-option-btn" id="confirmOkBtn" style="background: var(--cp-success); color: white;">[ INICIAR DESEMBOLSO ]</button>
        <button style="padding: 10px; background: transparent; border: 1px solid var(--cp-border); cursor: pointer; font-weight: bold; font-family: monospace; color: #374151;" onclick="cerrarModal()">[ ABORTAR ]</button>
    </div>
  </div>
</div>

<script src="public/JS/contrapropuesta.js"></script>

</body>
</html>
