<?php
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../api/autorizacion.php';
requiere_login();
require_permission('prestamos', 'admin');

$id_prestamo = (int)($_GET['id_prestamo'] ?? 0);
if ($id_prestamo <= 0) { header('Location: prestamos.php'); exit; }

require_once __DIR__ . '/../api/contrapropuesta.php';

$vm = obtener_contrapropuestas_para_prestamo($conn, $id_prestamo);

$prestamo = $vm['prestamo'] ?? null;
if (!$prestamo) {
    header('Location: resultado_v.php?id_prestamo=' . $id_prestamo);
    exit;
}

$contrapropuestas = $vm['contrapropuestas'] ?? [];
$monto_orig = (float)($vm['monto_orig'] ?? 0);
$plazo_orig = (int)($vm['plazo_orig'] ?? 0);
$cuota_orig = (float)($vm['cuota_orig'] ?? 0);
$cap_disponible = (float)($vm['cap_disponible'] ?? 0);
$requiere_ajuste = $cuota_orig > $cap_disponible;
$es_aprobado = ($prestamo['estado_evaluacion_actual'] === 'Aprobado' || $prestamo['estado_evaluacion_actual'] === 'Revision_manual');

// Recuperar las razones de rechazo
$razones_rechazo = $_SESSION['razones_rechazo'][$id_prestamo] ?? [];

$APP_BASE = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$APP_BASE = preg_replace('#/views$#', '', $APP_BASE);
$APP_BASE = ($APP_BASE === '' ? '/' : $APP_BASE . '/');
$BASE = $APP_BASE;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Centro de Mando - Contrapropuestas - Prestamos_app</title>
    <link rel="stylesheet" href="<?= $BASE ?>public/css/dashboard.css">
    <style>
      * { box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 0; }
      body { background: #eef2f6; overflow-x: hidden; }
      
      :root { 
          --brand-primary: #4f46e5; 
          --brand-primary-glow: rgba(79, 70, 229, 0.2);
          --brand-success: #16a34a; 
          --brand-danger: #dc2626; 
          --brand-warning: #d97706;
          --panel-bg: #ffffff;
          --panel-border: #e5e7eb;
      }

      .app-shell { display: flex; height: 100vh; overflow: hidden; }
      .content-area { flex: 1; display: flex; flex-direction: column; overflow: hidden; background-image: radial-gradient(circle at 100% 0%, rgba(79, 70, 229, 0.05), transparent 40%); }
      
      /* HUD Layout Dashboard */
      .hud-workspace {
          display: grid;
          grid-template-columns: 400px 1fr;
          gap: 24px;
          padding: 24px;
          height: calc(100vh - 60px); 
          overflow: hidden;
      }

      /* PANEL IZQUIERDO: DIAGNÓSTICO */
      .hud-diagnostic-panel {
          display: flex;
          flex-direction: column;
          gap: 20px;
          background: var(--panel-bg);
          border-right: 1px solid var(--panel-border);
          border-radius: 16px;
          padding: 24px;
          box-shadow: 0 10px 30px rgba(0,0,0,0.03);
          overflow-y: auto;
      }

      .hud-status-banner {
          padding: 16px;
          border-radius: 12px;
          font-weight: 800;
          font-size: 1.1rem;
          display: flex;
          align-items: center;
          gap: 12px;
          text-transform: uppercase;
          letter-spacing: 1px;
      }
      .status-approved { background: #f0fdf4; color: var(--brand-success); border: 1px solid #bbf7d0; box-shadow: 0 0 20px rgba(22, 163, 74, 0.1); }
      .status-adjust { background: #fffbeb; color: var(--brand-warning); border: 1px solid #fde68a; box-shadow: 0 0 20px rgba(217, 119, 6, 0.1); }
      .status-rejected { background: #fef2f2; color: var(--brand-danger); border: 1px solid #fecaca; box-shadow: 0 0 20px rgba(220, 38, 38, 0.1); }

      .hud-client-block { border-bottom: 1px solid var(--panel-border); padding-bottom: 16px; }
      .hud-client-block h3 { font-size: 1.2rem; color: #111827; margin-bottom: 4px; }
      .hud-client-block p { color: #6b7280; font-size: 0.85rem; font-weight: 600; }

      .hud-data-group { display: flex; flex-direction: column; gap: 12px; }
      .hud-data-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; background: #f9fafb; border-radius: 8px; font-size: 0.9rem; }
      .hud-data-row label { font-weight: 700; color: #9ca3af; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; }
      .hud-data-row span { font-weight: 800; color: #1f2937; }

      /* Visualizador de Capacidad */
      .hud-capacity-meter { margin-top: 10px; }
      .meter-track { height: 8px; background: #e5e7eb; border-radius: 10px; overflow: hidden; display: flex; }
      .meter-fill { height: 100%; background: var(--brand-success); }
      .meter-overage { height: 100%; background: var(--brand-danger); }

      /* AI Log Console */
      .hud-ai-log {
          background: #111827;
          border-radius: 12px;
          padding: 16px;
          color: #10b981;
          font-family: 'Courier New', Courier, monospace;
          font-size: 0.85rem;
          margin-top: auto;
          box-shadow: inset 0 0 10px rgba(0,0,0,0.5);
      }
      .hud-ai-log-title { color: #9ca3af; font-size: 0.75rem; text-transform: uppercase; margin-bottom: 10px; border-bottom: 1px solid #374151; padding-bottom: 4px; }
      .hud-ai-log ul { list-style: none; padding-left: 0; display: flex; flex-direction: column; gap: 8px; }
      .hud-ai-log li::before { content: '>'; color: #3b82f6; margin-right: 8px; font-weight: bold; }
      .log-danger li { color: #ef4444; }

      /* PANEL DERECHO: SOLUCIONES (ACCIONES) */
      .hud-solutions-panel {
          display: flex;
          flex-direction: column;
          gap: 24px;
          overflow-y: auto;
          padding-right: 10px;
      }

      .hud-solutions-header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          background: var(--panel-bg);
          padding: 16px 24px;
          border-radius: 12px;
          box-shadow: 0 4px 15px rgba(0,0,0,0.02);
      }
      .hud-solutions-header h2 { font-size: 1.25rem; font-weight: 800; color: #1e1b4b; display: flex; align-items: center; gap: 8px; }

      /* Grid de Opciones Estratégicas */
      .hud-tactical-grid {
          display: grid;
          grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
          gap: 20px;
      }

      .hud-tactical-card {
          background: var(--panel-bg);
          border: 1px solid var(--panel-border);
          border-radius: 16px;
          display: flex;
          flex-direction: column;
          transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
          overflow: hidden;
      }
      .hud-tactical-card:hover {
          transform: translateY(-5px);
          box-shadow: 0 15px 30px var(--brand-primary-glow);
          border-color: var(--brand-primary);
      }
      .hud-tactical-card.recommended {
          border: 2px solid var(--brand-primary);
          box-shadow: 0 0 20px var(--brand-primary-glow);
      }

      .hud-card-action-zone {
          background: #f8fafc;
          padding: 20px;
          border-bottom: 1px dashed var(--panel-border);
      }
      .btn-apply-strategy {
          width: 100%;
          background: var(--brand-primary);
          color: white;
          border: none;
          padding: 14px;
          border-radius: 8px;
          font-weight: 800;
          font-size: 1rem;
          cursor: pointer;
          display: flex;
          justify-content: center;
          align-items: center;
          gap: 8px;
          transition: all 0.2s;
          box-shadow: 0 4px 12px var(--brand-primary-glow);
      }
      .btn-apply-strategy:hover { background: #4338ca; transform: scale(1.02); }

      .hud-card-data-zone { padding: 20px; display: flex; flex-direction: column; gap: 12px; }
      .hud-strategy-badge { font-size: 0.75rem; font-weight: 800; color: var(--brand-primary); text-transform: uppercase; letter-spacing: 1px; }
      .hud-card-monto { font-size: 2.2rem; font-weight: 900; color: #111827; line-height: 1; margin: 5px 0; }
      
      .hud-detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px; }
      .hud-detail-item { background: #f3f4f6; padding: 10px; border-radius: 8px; }
      .hud-detail-item label { display: block; font-size: 0.7rem; color: #6b7280; text-transform: uppercase; font-weight: 700; margin-bottom: 2px; }
      .hud-detail-item span { font-size: 1.1rem; font-weight: 800; color: #1f2937; }
      .hud-detail-item.highlight span { color: var(--brand-success); }

      .hud-card-desc { font-size: 0.85rem; color: #6b7280; padding-top: 10px; border-top: 1px solid #e5e7eb; margin-top: 5px; }

      .btn-reject-global {
          background: white;
          color: #4b5563;
          border: 1px solid #d1d5db;
          padding: 10px 20px;
          border-radius: 8px;
          font-weight: 700;
          cursor: pointer;
          transition: all 0.2s;
          display: flex;
          align-items: center;
          justify-content: center;
          gap: 6px;
      }
      .btn-reject-global:hover { background: #fef2f2; color: var(--brand-danger); border-color: #fca5a5; }

      /* Modal Holográfico */
      .cp-confirm-overlay { display: none; position: fixed; inset: 0; background: rgba(17, 24, 39, 0.7); backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center; }
      .cp-confirm-overlay.open { display: flex; }
      .cp-confirm-dialog { background: var(--panel-bg); border-radius: 16px; padding: 32px; max-width: 420px; width: 90%; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); }
    </style>
    <script>
        window.APP_BASE = <?= json_encode($APP_BASE, JSON_UNESCAPED_SLASHES) ?>;
        window.ID_PRESTAMO = <?= (int)$id_prestamo ?>;
    </script>
</head>
<body>
    <div class="app-shell">
        <main class="content-area">
            <header class="topbar">
              <div class="topbar-left">
                <div class="brand-inline">
                  <span class="brand-logo">⚙️</span>
                  <span class="brand-name">Sistema Táctico de Resolución</span>
                </div>
              </div>
            </header>
            
            <div class="hud-workspace">
                
                <!-- PANEL IZQUIERDO: DIAGNÓSTICO Y SOLICITUD ORIGINAL -->
                <aside class="hud-diagnostic-panel">
                    <?php if ($es_aprobado): ?>
                        <div class="hud-status-banner status-approved">Propuesta: Factible</div>
                    <?php elseif ($requiere_ajuste || !empty($razones_rechazo)): ?>
                        <div class="hud-status-banner status-adjust">⚠️ Ajuste de Parámetros Requerido</div>
                    <?php else: ?>
                        <div class="hud-status-banner status-rejected">❌ Operación Inviable</div>
                    <?php endif; ?>

                    <div class="hud-client-block">
                        <h3><?= htmlspecialchars($prestamo['nombre_cliente'] . ' ' . $prestamo['apellido_cliente']) ?></h3>
                        <p>ID Contrato: <?= htmlspecialchars($prestamo['numero_contrato']) ?></p>
                    </div>

                    <div class="hud-data-group">
                        <div style="font-size: 0.8rem; font-weight: 800; color: var(--brand-primary); text-transform: uppercase;">Métricas Originales</div>
                        <div class="hud-data-row">
                            <label>Monto</label>
                            <span>RD$ <?= number_format($monto_orig, 2) ?></span>
                        </div>
                        <div class="hud-data-row">
                            <label>Plazo</label>
                            <span><?= $plazo_orig ?> meses</span>
                        </div>
                        <div class="hud-data-row">
                            <label>Cuota Calculada</label>
                            <span style="<?= $requiere_ajuste ? 'color: var(--brand-danger);' : '' ?>">RD$ <?= number_format($cuota_orig, 2) ?></span>
                        </div>
                        
                        <div class="hud-capacity-meter">
                            <div style="display:flex; justify-content:space-between; font-size:0.75rem; font-weight:700; color:#6b7280; margin-bottom:4px;">
                                <span>Límite Capacidad: RD$ <?= number_format($cap_disponible, 2) ?></span>
                            </div>
                            <?php 
                                $pct_usado = $cap_disponible > 0 ? min(100, ($cuota_orig / $cap_disponible) * 100) : 100; 
                                $excede = $cuota_orig > $cap_disponible;
                            ?>
                            <div class="meter-track">
                                <div class="<?= $excede ? 'meter-overage' : 'meter-fill' ?>" style="width: <?= $pct_usado ?>%;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- BOTONERA ORIGINAL: ACCIÓN INMEDIATA -->
                    <div style="margin-top: 10px; display: flex; flex-direction: column; gap: 10px;">
                        <?php if ($es_aprobado): ?>
                            <button class="cp-option-btn" style="background: var(--brand-success); padding: 12px; font-size: 0.95rem;" onclick="confirmarOriginal()">
                                ✅ Proceder con Solicitud Original
                            </button>
                        <?php endif; ?>
                        
                        <button class="btn-reject-global" style="padding: 12px; width: 100%; <?= !$es_aprobado ? 'border-color: #fca5a5; color: var(--brand-danger); background: #fef2f2;' : '' ?>" onclick="rechazarTodo()">
                            ✖️ Descartar y Anular Solicitud
                        </button>
                    </div>

                    <!-- Consola Log del Sistema -->
                    <div class="hud-ai-log <?= !$es_aprobado ? 'log-danger' : '' ?>">
                        <div class="hud-ai-log-title">Salida del Motor de Riesgo</div>
                        <ul>
                            <?php if ($es_aprobado): ?>
                                <li>Capacidad de pago confirmada.</li>
                                <li>Márgenes de riesgo superados positivamente.</li>
                                <li style="color:#60a5fa;">Iniciando subrutina de Up-Sell (Opcional).</li>
                            <?php else: ?>
                                <?php foreach ($razones_rechazo as $razon): ?>
                                    <li><?= htmlspecialchars($razon) ?></li>
                                <?php endforeach; ?>
                                <?php if(empty($razones_rechazo)): ?>
                                    <li>Las métricas actuales violan las políticas de riesgo estructural.</li>
                                <?php endif; ?>
                            <?php endif; ?>
                        </ul>
                    </div>
                </aside>

                <!-- PANEL DERECHO: ACCIONES Y SOLUCIONES -->
                <section class="hud-solutions-panel">
                  
                    <header class="hud-solutions-header">
                        <h2><?= $es_aprobado ? 'Oportunidades de Up-Sell (Cálculos de IA)' : 'Estrategias de Mitigación Generadas' ?></h2>
                        <button class="btn-reject-global" onclick="rechazarTodo()">
                            ✖️ Descartar Todo
                        </button>
                    </header>

                    <div class="hud-tactical-grid">
                        <?php if (!empty($contrapropuestas)): ?>
                            <?php foreach ($contrapropuestas as $index => $cp): ?>
                            <div class="hud-tactical-card <?= ($index === 0 && !$es_aprobado) ? 'recommended' : '' ?>" id="card-<?= $cp['opcion'] ?>">
                                
                                <div class="hud-card-action-zone">
                                    <button class="btn-apply-strategy" onclick='abrirConfirmacion(<?= htmlspecialchars(json_encode($cp, JSON_UNESCAPED_UNICODE), ENT_QUOTES, "UTF-8") ?>)'>
                                        ✅ Aplicar Estrategia <?= $cp['opcion'] ?>
                                    </button>
                                </div>

                                <div class="hud-card-data-zone">
                                    <div class="hud-strategy-badge">Nuevo Parámetro Financiero</div>
                                    <div class="hud-card-monto">RD$ <?= number_format($cp['monto'], 2) ?></div>
                                    
                                    <div class="hud-detail-grid">
                                        <div class="hud-detail-item">
                                            <label>Nuevo Plazo</label>
                                            <span><?= $cp['plazo'] ?> meses</span>
                                        </div>
                                        <div class="hud-detail-item highlight">
                                            <label>Cuota Segura</label>
                                            <span>RD$ <?= number_format($cp['cuota'], 2) ?></span>
                                        </div>
                                    </div>
                                    
                                    <?php
                                        $tasa_orig = (float)$vm['tasa_anual_orig'];
                                        $cambio_monto = abs($cp['monto'] - $monto_orig) > 0.01;
                                        $cambio_plazo = (int)$cp['plazo'] !== (int)$plazo_orig;
                                        $cambio_tasa = isset($cp['tasa']) && abs($cp['tasa'] - $tasa_orig) > 0.01;
                                    ?>
                                    <?php if ($cambio_monto || $cambio_plazo || $cambio_tasa): ?>
                                    <div style="margin-top: 12px; padding: 10px; background: rgba(217, 119, 6, 0.05); border: 1px dashed #fde68a; border-radius: 8px;">
                                        <span style="display: block; font-size: 0.7rem; font-weight: 800; color: var(--brand-warning); text-transform: uppercase; margin-bottom: 6px;">Optimización Matemática Aplicada:</span>
                                        <ul style="list-style: none; padding: 0; margin: 0; font-size: 0.8rem; color: #4b5563; display: flex; flex-direction: column; gap: 4px;">
                                            <?php if ($cambio_monto): ?>
                                                <li>• <b>Capital:</b> <span style="text-decoration: line-through; color: #9ca3af;">RD$ <?= number_format($monto_orig, 2) ?></span> ➔ <b style="color: #1f2937;">RD$ <?= number_format($cp['monto'], 2) ?></b></li>
                                            <?php endif; ?>
                                            <?php if ($cambio_plazo): ?>
                                                <li>• <b>Plazo:</b> <span style="text-decoration: line-through; color: #9ca3af;"><?= $plazo_orig ?> meses</span> ➔ <b style="color: #1f2937;"><?= $cp['plazo'] ?> meses</b></li>
                                            <?php endif; ?>
                                            <?php if ($cambio_tasa): ?>
                                                <li>• <b>Tasa:</b> <span style="text-decoration: line-through; color: #9ca3af;"><?= number_format($tasa_orig, 2) ?>%</span> ➔ <b style="color: #16a34a;"><?= number_format($cp['tasa'], 2) ?>%</b></li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                    <?php endif; ?>

                                    <div class="hud-card-desc">
                                        <?= htmlspecialchars($cp['descripcion'] ?? ($es_aprobado ? 'Extensión de crédito sugerida por IA.' : 'Ajuste paramétrico para mitigación de riesgo.')) ?>
                                    </div>
                                </div>

                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="grid-column: 1 / -1; background: #fef2f2; border: 1px solid #fecaca; padding: 30px; border-radius: 12px; text-align: center; color: #991b1b; font-weight: 700;">
                                ⚠️ El motor matemático no encontró ninguna configuración de Up-Sell o mitigación viable según los permisos actuales.
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
                
            </div>
        </main>
    </div>

    <!-- MODAL DE CONFIRMACIÓN -->
    <div class="cp-confirm-overlay" id="confirmOverlay">
      <div class="cp-confirm-dialog" style="border-radius: 0; border: 1px solid var(--cp-primary); box-shadow: 0 0 30px rgba(79,70,229,0.3);">
        <h3 style="margin-bottom: 10px; font-family: monospace; color: var(--cp-primary);">> CONFIRMACIÓN_REQUERIDA</h3>
        <p style="color: #6b7280; font-size: 0.9rem; margin-bottom: 20px;">Inicializando recálculo de cronograma y sobreescritura de parámetros...</p>
        
        <div id="confirmDetail" style="background: rgba(79,70,229,0.05); border-left: 3px solid var(--cp-primary); padding:15px; margin-bottom:20px; font-family: monospace;"></div>
        
        <div style="display:flex; gap: 10px; flex-direction: column;">
            <button class="cp-option-btn" id="confirmOkBtn" style="background: var(--brand-success); color: white;">[ INICIAR RE-ESCRITURA ]</button>
            <button style="padding: 10px; background: transparent; border: 1px solid var(--panel-border); cursor: pointer; font-weight: bold; font-family: monospace; color: #374151;" onclick="cerrarModal()">[ ABORTAR ]</button>
        </div>
      </div>
    </div>

    <script src="<?= $BASE ?>public/JS/contrapropuesta.js"></script>
</body>
</html>