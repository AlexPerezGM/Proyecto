<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../api/autorizacion.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }
requiere_login();

$APP_BASE = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$APP_BASE = preg_replace('#/views$#','', $APP_BASE);
$APP_BASE = ($APP_BASE === '' ? '/' : $APP_BASE . '/');

$id_prestamo = (int)($_GET['id_prestamo'] ?? 0);

if ($id_prestamo <= 0) {
    die("ID de préstamo inválido.");
}

// Obtener datos básicos para mostrar en la cabecera de carga
$sql = "SELECT p.monto_solicitado, p.plazo_meses, p.numero_contrato, dp.nombre, dp.apellido 
        FROM prestamo p 
        JOIN cliente c ON c.id_cliente = p.id_cliente 
        JOIN datos_persona dp ON dp.id_datos_persona = c.id_datos_persona 
        WHERE p.id_prestamo = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $id_prestamo);
$stmt->execute();
$prestamoInfo = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Motor de Evaluación - Prestamos_app</title>
    <style>
        * { box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 0; }
        body { 
            background: #eef2f6; 
            background-image: radial-gradient(circle at 0% 0%, rgba(79, 70, 229, 0.05), transparent 50%);
            overflow: hidden;
        }
        
        :root { 
            --brand-primary: #4f46e5; 
            --brand-primary-glow: rgba(79, 70, 229, 0.2);
            --brand-success: #16a34a; 
            --brand-danger: #dc2626; 
            --brand-warning: #d97706;
            --panel-bg: rgba(255, 255, 255, 0.85);
            --panel-border: rgba(79, 70, 229, 0.15);
            --text-main: #111827;
            --text-muted: #6b7280;
        }

        .app-shell { display: flex; height: 100vh; width: 100vw; justify-content: center; align-items: center; }
        
        .hud-workspace {
            width: 100%;
            max-width: 1100px;
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        /* PANELS */
        .hud-panel {
            background: var(--panel-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--panel-border);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(31, 38, 135, 0.05);
            padding: 24px;
        }

        /* HEADER INFO */
        .hud-header {
            display: flex; justify-content: space-between; align-items: center;
            border-bottom: 1px solid rgba(229, 231, 235, 0.6);
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .hud-header-title { font-size: 1.2rem; font-weight: 800; color: var(--text-main); text-transform: uppercase; letter-spacing: 1px; display: flex; align-items: center; gap: 10px; }
        .hud-header-title::before { content: ''; display: inline-block; width: 12px; height: 12px; background: var(--brand-primary); border-radius: 50%; box-shadow: 0 0 10px var(--brand-primary); animation: pulse-dot 1.5s infinite; }
        .hud-contract-badge { background: rgba(79, 70, 229, 0.1); color: var(--brand-primary); padding: 6px 16px; border-radius: 100px; font-weight: 800; font-size: 0.9rem; letter-spacing: 1px; border: 1px solid var(--panel-border); }

        @keyframes pulse-dot { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.5; transform: scale(0.8); } }

        .hud-client-data { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; }
        .hud-data-item label { display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 4px; letter-spacing: 0.5px; }
        .hud-data-item span { font-size: 1.1rem; font-weight: 800; color: var(--text-main); }

        /* SPLIT SCREEN */
        .hud-split { display: grid; grid-template-columns: 1fr 1.2fr; gap: 24px; }
        @media (max-width: 800px) { .hud-split { grid-template-columns: 1fr; } }

        /* LEFT: ENGINE CORE */
        .hud-engine-core { display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; gap: 30px; padding: 40px 20px; }
        
        .engine-status-text h2 { font-size: 1.5rem; font-weight: 900; color: var(--text-main); margin-bottom: 8px; letter-spacing: -0.5px; }
        .engine-status-text p { font-size: 0.95rem; color: var(--text-muted); font-weight: 600; }

        .engine-progress-wrapper { width: 100%; max-width: 350px; }
        .engine-progress-track { height: 12px; background: #e2e8f0; border-radius: 100px; overflow: hidden; box-shadow: inset 0 2px 4px rgba(0,0,0,0.05); position: relative; }
        .engine-progress-fill { height: 100%; background: linear-gradient(90deg, #818cf8, var(--brand-primary)); border-radius: 100px; width: 0%; transition: width 0.4s ease-out; box-shadow: 0 0 15px var(--brand-primary); }
        .engine-progress-pct { margin-top: 12px; font-size: 1.8rem; font-weight: 900; color: var(--brand-primary); font-variant-numeric: tabular-nums; }

        /* RIGHT: MODULE PIPELINE */
        .hud-pipeline { display: flex; flex-direction: column; gap: 16px; }
        
        .pipeline-module { 
            display: flex; align-items: center; gap: 16px; 
            padding: 16px 20px; 
            background: #f8fafc; 
            border: 1px solid #e2e8f0; 
            border-left: 6px solid #cbd5e1;
            border-radius: 12px; 
            transition: all 0.3s;
        }
        
        .pipeline-module.running { 
            background: #fff; 
            border-color: var(--brand-primary); 
            border-left-color: var(--brand-primary); 
            box-shadow: 0 10px 25px var(--brand-primary-glow); 
            transform: translateX(10px);
        }
        
        .pipeline-module.done { 
            background: #f0fdf4; 
            border-color: #bbf7d0; 
            border-left-color: var(--brand-success); 
        }

        .module-icon { 
            width: 40px; height: 40px; border-radius: 10px; 
            display: flex; align-items: center; justify-content: center; 
            background: #e2e8f0; font-size: 1.2rem; transition: all 0.3s; 
        }
        .pipeline-module.running .module-icon { background: var(--brand-primary-glow); color: var(--brand-primary); }
        .pipeline-module.done .module-icon { background: #dcfce7; color: var(--brand-success); }

        .module-info { flex: 1; }
        .module-title { font-weight: 800; font-size: 0.95rem; color: var(--text-main); }
        .module-desc { font-size: 0.8rem; color: var(--text-muted); margin-top: 2px; font-weight: 600; }

        .module-status-badge { 
            font-size: 0.7rem; font-weight: 800; padding: 4px 10px; border-radius: 100px; text-transform: uppercase; letter-spacing: 0.5px;
            background: #e2e8f0; color: #64748b;
        }
        .pipeline-module.running .module-status-badge { background: var(--brand-primary); color: white; box-shadow: 0 0 10px var(--brand-primary-glow); animation: blink 1s infinite; }
        .pipeline-module.done .module-status-badge { background: var(--brand-success); color: white; }

        @keyframes blink { 50% { opacity: 0.7; } }
    </style>
</head>
<body>
    <div class="app-shell">
        <div class="hud-workspace">
            
            <!-- PANEL DE IDENTIFICACIÓN -->
            <div class="hud-panel">
                <div class="hud-header">
                    <div class="hud-header-title">Auditoría Lógica en Curso</div>
                    <div class="hud-contract-badge">ID: <?= htmlspecialchars($prestamoInfo['numero_contrato']) ?></div>
                </div>
                <div class="hud-client-data">
                    <div class="hud-data-item"><label>Cliente Analizado</label><span><?= htmlspecialchars($prestamoInfo['nombre'] . ' ' . $prestamoInfo['apellido']) ?></span></div>
                    <div class="hud-data-item"><label>Capital Solicitado</label><span>RD$ <?= number_format($prestamoInfo['monto_solicitado'], 2) ?></span></div>
                    <div class="hud-data-item"><label>Plazo Proyectado</label><span><?= $prestamoInfo['plazo_meses'] ?> meses</span></div>
                </div>
            </div>

            <div class="hud-split">
                <!-- MOTOR PRINCIPAL -->
                <div class="hud-panel hud-engine-core">
                    <div class="engine-status-text">
                        <h2 id="mainLabel">Procesando Parámetros</h2>
                        <p>El motor estratégico está evaluando el riesgo.</p>
                    </div>

                    <div class="engine-progress-wrapper">
                        <div class="engine-progress-track">
                            <div class="engine-progress-fill" id="progressBar"></div>
                        </div>
                        <div class="engine-progress-pct" id="progressPct">0%</div>
                    </div>
                </div>

                <!-- PIPELINE DE MÓDULOS -->
                <div class="hud-panel hud-pipeline" id="stepsContainer">
                    
                    <div class="pipeline-module" id="step-1">
                        <div class="module-icon" id="icon-1">📋</div>
                        <div class="module-info">
                            <div class="module-title">Validación de Identidad</div>
                            <div class="module-desc">Verificando información personal y laboral</div>
                        </div>
                        <div class="module-status-badge" id="badge-1">En Espera</div>
                    </div>

                    <div class="pipeline-module" id="step-2">
                        <div class="module-icon" id="icon-2">💰</div>
                        <div class="module-info">
                            <div class="module-title">Análisis de Solvencia</div>
                            <div class="module-desc">Calculando capacidad de pago y egresos</div>
                        </div>
                        <div class="module-status-badge" id="badge-2">En Espera</div>
                    </div>

                    <div class="pipeline-module" id="step-3">
                        <div class="module-icon" id="icon-3">🏆</div>
                        <div class="module-info">
                            <div class="module-title">Scoring Interno</div>
                            <div class="module-desc">Evaluando historial y comportamiento crediticio</div>
                        </div>
                        <div class="module-status-badge" id="badge-3">En Espera</div>
                    </div>

                    <div class="pipeline-module" id="step-4">
                        <div class="module-icon" id="icon-4">⚖️</div>
                        <div class="module-info">
                            <div class="module-title">Resolución Táctica</div>
                            <div class="module-desc">Aplicando reglas de negocio del sistema</div>
                        </div>
                        <div class="module-status-badge" id="badge-4">En Espera</div>
                    </div>

                </div>
            </div>

        </div>
    </div>

<script>
        const APP_BASE = "<?= $APP_BASE ?>";
        const ID_PRESTAMO = <?= $id_prestamo ?>;
        
        let pct = 0;
        const bar = document.getElementById('progressBar');
        const pctText = document.getElementById('progressPct');
        
        const steps = [document.getElementById('step-1'), document.getElementById('step-2'), document.getElementById('step-3'), document.getElementById('step-4')];
        const badges = [document.getElementById('badge-1'), document.getElementById('badge-2'), document.getElementById('badge-3'), document.getElementById('badge-4')];

        // Iniciar el paso 1
        steps[0].classList.add('running');
        badges[0].innerText = 'EJECUTANDO';

        let resultadoApi = null;
        let evaluacionTerminada = false;

        const interval = setInterval(() => {
            if(pct < 95 || evaluacionTerminada) {
                pct += (evaluacionTerminada ? 5 : 2); 
            }
            if(pct > 100) pct = 100;
            
            bar.style.width = pct + '%';
            pctText.innerText = pct + '%';

            // Transiciones de pasos
            if (pct >= 25 && pct < 50 && steps[0].classList.contains('running')) { 
                steps[0].classList.replace('running', 'done'); badges[0].innerText = 'VERIFICADO';
                steps[1].classList.add('running'); badges[1].innerText = 'EJECUTANDO';
            }
            if (pct >= 50 && pct < 75 && steps[1].classList.contains('running')) { 
                steps[1].classList.replace('running', 'done'); badges[1].innerText = 'VERIFICADO';
                steps[2].classList.add('running'); badges[2].innerText = 'EJECUTANDO';
            }
            if (pct >= 75 && pct < 100 && steps[2].classList.contains('running')) { 
                steps[2].classList.replace('running', 'done'); badges[2].innerText = 'VERIFICADO';
                steps[3].classList.add('running'); badges[3].innerText = 'EJECUTANDO';
            }
            
            // Finalización
            if (pct === 100 && evaluacionTerminada) {
                clearInterval(interval);
                steps[3].classList.replace('running', 'done'); badges[3].innerText = 'COMPLETADO';
                
                const dictamenLabel = document.getElementById('mainLabel');
                const decision = (resultadoApi.decision || 'REVISION_MANUAL').toUpperCase();
                
                if (decision === 'APROBADO') {
                    bar.style.background = '#16a34a';
                    bar.style.boxShadow = '0 0 15px #16a34a';
                    dictamenLabel.style.color = '#16a34a';
                } else if (decision === 'RECHAZADO') {
                    bar.style.background = '#dc2626';
                    bar.style.boxShadow = '0 0 15px #dc2626';
                    dictamenLabel.style.color = '#dc2626';
                } else { 
                    bar.style.background = '#d97706';
                    bar.style.boxShadow = '0 0 15px #d97706';
                    dictamenLabel.style.color = '#d97706';
                }
                dictamenLabel.innerText = 'Dictamen: ' + decision;

                // Redirección al Centro de Mando / Contrapropuestas
                setTimeout(() => {
                    window.location.href = APP_BASE + 'views/Contrapropuestas_Inteligentes.php?id_prestamo=' + ID_PRESTAMO;
                }, 1200);
            }
        }, 70);

        async function procesarEvaluacion() {
            try {
                const params = new URLSearchParams({ id_prestamo: ID_PRESTAMO });
                const res = await fetch(APP_BASE + 'api/Evaluar_prestamo_propuesta.php', {
                    method: 'POST',
                    body: params
                });
                
                const data = await res.json();
                
                if (data.ok) {
                    resultadoApi = data;
                } else {
                    console.error("Error API:", data.msg);
                    resultadoApi = { decision: 'REVISION_MANUAL' }; 
                }
            } catch (e) {
                console.error("Error en Fetch:", e);
                resultadoApi = { decision: 'REVISION_MANUAL' };
            } finally {
                evaluacionTerminada = true; 
            }
        }

        procesarEvaluacion();
    </script>
</body>
</html>