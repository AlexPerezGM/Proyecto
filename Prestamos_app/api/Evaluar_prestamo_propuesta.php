<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/autorizacion.php';

if (!is_logged() || !has_permission('seguimiento')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'No autorizado.']);
    exit;
}

$db = $conn ?? $mysqli;
$id_prestamo = (int)($_POST['id_prestamo'] ?? 0);

if ($id_prestamo <= 0) {
    echo json_encode(['ok' => false, 'msg' => 'ID de préstamo inválido.']);
    exit;
}

$db->autocommit(false);

try {
    // 1. Extraer datos del préstamo, cliente Y REGLAS DE LA IA VINCULADAS AL PRODUCTO
    $sql_prestamo = "SELECT p.monto_solicitado, p.plazo_meses, p.id_cliente, p.id_tipo_prestamo,
                            cp.tasa_interes, cp.id_periodo_pago, cp.id_tipo_amortizacion,
                            tp.plazo_maximo_meses, tp.monto_minimo, tp.porcentaje_capacidad,
                            tp.cp_permitir_monto, tp.cp_upsell_pct, tp.cp_downsell_pct,
                            tp.cp_permitir_plazo, tp.cp_factor_plazo,
                            tp.cp_permitir_tasa, tp.cp_tasa_minima, tp.cp_permitir_amortizacion,
                            ev.capacidad_pago, ev.puntaje_crediticio, ev.salario_neto, ev.total_deudas_externas
                     FROM prestamo p
                     JOIN condicion_prestamo cp ON cp.id_condicion_prestamo = p.id_condicion_actual
                     JOIN tipo_prestamo tp ON tp.id_tipo_prestamo = p.id_tipo_prestamo
                     LEFT JOIN evaluacion_estrategica ev ON ev.id_prestamo = p.id_prestamo
                     WHERE p.id_prestamo = ? LIMIT 1";
                     
    $stmt = $db->prepare($sql_prestamo);
    $stmt->bind_param('i', $id_prestamo);
    $stmt->execute();
    $prestamo = $stmt->get_result()->fetch_assoc();
    
    if (!$prestamo) throw new Exception("Préstamo no encontrado.");

    // 2. Extraer parámetros de configuración general
    $config = [];
    $res_cfg = $db->query("SELECT nombre_configuracion, valor_decimal FROM configuracion WHERE estado = 'Activo'");
    while ($c = $res_cfg->fetch_assoc()) {
        $config[$c['nombre_configuracion']] = (float)$c['valor_decimal'];
    }

    $factor_capacidad = $config['FACTOR_CAPACIDAD_CONTRAPROPUESTA'] ?? 0.95;
    $min_puntaje_rechazo = $config['MIN_PUNTAJE_RECHAZADO'] ?? $config['MIN_PUNTAJE_RECHAZO'] ?? 200;

    // 3. Mapeo de reglas empresariales del producto
    $permitir_monto = ($prestamo['cp_permitir_monto'] ?? 'Si') === 'Si';
    $upsell_pct = (float)($prestamo['cp_upsell_pct'] ?? 15) / 100;
    $downsell_pct = (float)($prestamo['cp_downsell_pct'] ?? 30) / 100;
    
    $permitir_plazo = ($prestamo['cp_permitir_plazo'] ?? 'Si') === 'Si';
    $factor_plazo = (float)($prestamo['cp_factor_plazo'] ?? 1.50);
    
    $permitir_tasa = ($prestamo['cp_permitir_tasa'] ?? 'No') === 'Si';
    $tasa_minima_empresa = (float)($prestamo['cp_tasa_minima'] ?? 10);
    
    $permitir_amortizacion = ($prestamo['cp_permitir_amortizacion'] ?? 'No') === 'Si';

    // 4. Scoring y Decisión Base
    $puntaje = (int)($prestamo['puntaje_crediticio'] ?? 0);
    $stmt_dec = $db->prepare("
        SELECT de.codigo_decision, ir.id_nivel_riesgo
        FROM configuracion_intervalo_decision cid
        JOIN cat_decision_evaluacion de ON de.id_decision_evaluacion = cid.id_decision_evaluacion
        JOIN configuracion_intervalo_riesgo ir ON ? BETWEEN ir.puntaje_minimo AND ir.puntaje_maximo
        WHERE ? BETWEEN cid.puntaje_minimo AND cid.puntaje_maximo
          AND cid.estado = 'Activo' AND ir.estado = 'Activo'
        ORDER BY cid.prioridad ASC LIMIT 1
    ");
    $stmt_dec->bind_param('ii', $puntaje, $puntaje);
    $stmt_dec->execute();
    $decision_row = $stmt_dec->get_result()->fetch_assoc();
    
    $decision = $decision_row['codigo_decision'] ?? 'REVISION_MANUAL';
    $id_riesgo = $decision_row['id_nivel_riesgo'] ?? 2;

    $monto_orig = (float)$prestamo['monto_solicitado'];
    $plazo_orig = (int)$prestamo['plazo_meses'];
    $tasa_orig = (float)$prestamo['tasa_interes'];
    $amortizacion_orig = (int)$prestamo['id_tipo_amortizacion'];
    $periodo_orig = (int)$prestamo['id_periodo_pago'];
    $tasa_mensual = ($tasa_orig / 100) / 12;

    $cuota_original = ($tasa_mensual > 0) ? $monto_orig * ($tasa_mensual * pow(1 + $tasa_mensual, $plazo_orig)) / (pow(1 + $tasa_mensual, $plazo_orig) - 1) : $monto_orig / $plazo_orig;
    
    // --- CÁLCULO DE CAPACIDAD: PORCENTAJE SOBRE EL DINERO SOBRANTE ---
    $dinero_sobrante = (float)($prestamo['salario_neto'] ?? 0); 
    $deuda_externa = (float)($prestamo['total_deudas_externas'] ?? 0);
    $deuda_mensual_aprox = $deuda_externa > 0 ? ($deuda_externa / 12) : 0;
    
    $disponible_real = $dinero_sobrante - $deuda_mensual_aprox;
    $porcentaje_permitido = (float)($prestamo['porcentaje_capacidad'] ?? 40) / 100;
    
    $capacidad = $disponible_real * $porcentaje_permitido;
    if ($capacidad < 0) $capacidad = 0;
    // -------------------------------------------------------------------------

    $razones_rechazo = [];
    if ($cuota_original > $capacidad) {
        $razones_rechazo[] = "La cuota estimada (RD$ " . number_format($cuota_original, 2) . ") excede el límite de capacidad de pago (RD$ " . number_format($capacidad, 2) . ").";
    }
    if ($monto_orig < $prestamo['monto_minimo']) {
        $razones_rechazo[] = "El monto solicitado es inferior al mínimo de nuestras políticas (RD$ " . number_format($prestamo['monto_minimo'], 2) . ").";
    }

    // LÍMITES MATEMÁTICOS DE LA IA PARA ESTE PRODUCTO
    $monto_minimo_permitido = max($prestamo['monto_minimo'], $monto_orig * (1 - $downsell_pct));
    $plazo_maximo_permitido = $permitir_plazo ? min($prestamo['plazo_maximo_meses'], (int)ceil($plazo_orig * $factor_plazo)) : $plazo_orig;
    $tasa_minima_ia = $permitir_tasa ? max($tasa_minima_empresa, $tasa_orig - 3.00) : $tasa_orig;

    $cuota_objetivo = $capacidad * $factor_capacidad;

    $tasa_min_mensual = ($tasa_minima_ia / 100) / 12;
    $monto_maximo_absoluto = ($tasa_min_mensual > 0) ? $cuota_objetivo * ((pow(1 + $tasa_min_mensual, $plazo_maximo_permitido) - 1) / ($tasa_min_mensual * pow(1 + $tasa_min_mensual, $plazo_maximo_permitido))) : $cuota_objetivo * $plazo_maximo_permitido;
    $monto_maximo_absoluto = floor($monto_maximo_absoluto / 1000) * 1000;

    // --- EVALUACIÓN MATEMÁTICA Y DE RIESGO ---
    
    // 1. PRIMER FILTRO: BARRERA DE RIESGO (SCORE CREDITICIO DE LUCÍA)
    if (strtoupper($decision) === 'RECHAZADO' || $puntaje < $min_puntaje_rechazo) {
        $decision_final = 'Rechazado';
        $razones_rechazo[] = "ALERTA DE RIESGO: El puntaje crediticio del cliente ($puntaje) es inferior al mínimo permitido o se encuentra en un rango inaceptable. Operación bloqueada.";
        $_SESSION['razones_rechazo'][$id_prestamo] = $razones_rechazo;
        $db->query("DELETE FROM contrapropuesta_prestamo WHERE id_prestamo = $id_prestamo");
    }
    // 2. SEGUNDO FILTRO: BARRERA FINANCIERA (CAPACIDAD)
    else if ($capacidad <= 0 || $monto_maximo_absoluto < $monto_minimo_permitido) {
        $decision_final = 'Rechazado';
        if ($capacidad <= 0) {
            $razones_rechazo[] = "La capacidad de pago neta es nula o negativa (RD$ " . number_format($capacidad, 2) . "). Cliente sobreendeudado.";
        } else {
            $razones_rechazo[] = "Incluso aplicando las estrategias máximas permitidas por el sistema, el cliente no posee capacidad para cubrir la cuota de la deuda mínima requerida (RD$ " . number_format($monto_minimo_permitido, 2) . ").";
        }
        $_SESSION['razones_rechazo'][$id_prestamo] = $razones_rechazo;
        $db->query("DELETE FROM contrapropuesta_prestamo WHERE id_prestamo = $id_prestamo");
    } 
    // 3. TERCER FILTRO: PROCESAR INTELIGENCIA SEGÚN PERMISOS
    else {
        $db->query("DELETE FROM contrapropuesta_prestamo WHERE id_prestamo = $id_prestamo AND estado_contrapropuesta = 'Pendiente'");
        
        if ($cuota_original <= $capacidad && $monto_orig >= $prestamo['monto_minimo'] && empty($razones_rechazo)) {
            $decision_final = 'Aprobado';
            $_SESSION['razones_rechazo'][$id_prestamo] = [];
            
            if ($permitir_monto) {
                $monto_upsell = floor(($monto_orig * (1 + $upsell_pct)) / 1000) * 1000;
                $cuota_upsell = ($tasa_mensual > 0) ? $monto_upsell * ($tasa_mensual * pow(1 + $tasa_mensual, $plazo_orig)) / (pow(1 + $tasa_mensual, $plazo_orig) - 1) : $monto_upsell / $plazo_orig;
                
                if ($cuota_upsell <= $capacidad && $monto_upsell > $monto_orig) {
                    $desc = "Oferta Ampliada Pre-aprobada (+".($upsell_pct*100)."% de Capital).";
                    $stmt = $db->prepare("INSERT INTO contrapropuesta_prestamo (id_prestamo, monto_sugerido, plazo_sugerido, tasa_sugerida, id_periodo_sugerido, id_amortizacion_sugerida, descripcion_estrategia, estado_contrapropuesta, fecha_contrapropuesta) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pendiente', CURDATE())");
                    $stmt->bind_param('ididiis', $id_prestamo, $monto_upsell, $plazo_orig, $tasa_orig, $periodo_orig, $amortizacion_orig, $desc);
                    $stmt->execute();
                }

                $monto_tope = ($tasa_mensual > 0) ? $cuota_objetivo * ((pow(1 + $tasa_mensual, $plazo_maximo_permitido) - 1) / ($tasa_mensual * pow(1 + $tasa_mensual, $plazo_maximo_permitido))) : $cuota_objetivo * $plazo_maximo_permitido;
                $monto_tope = floor($monto_tope / 1000) * 1000;
                
                if ($monto_tope >= ($monto_upsell + 5000)) {
                    $desc = $permitir_plazo ? "Extensión Estratégica: Límite de crédito llevando el plazo a $plazo_maximo_permitido meses." : "Límite Máximo de Exposición Financiera permitida.";
                    $stmt = $db->prepare("INSERT INTO contrapropuesta_prestamo (id_prestamo, monto_sugerido, plazo_sugerido, tasa_sugerida, id_periodo_sugerido, id_amortizacion_sugerida, descripcion_estrategia, estado_contrapropuesta, fecha_contrapropuesta) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pendiente', CURDATE())");
                    $stmt->bind_param('ididiis', $id_prestamo, $monto_tope, $plazo_maximo_permitido, $tasa_orig, $periodo_orig, $amortizacion_orig, $desc);
                    $stmt->execute();
                }
            }
        } 
        else {
            $decision_final = 'Contrapropuesta';
            $_SESSION['razones_rechazo'][$id_prestamo] = $razones_rechazo;
            $opciones = 0;
            
            if ($permitir_plazo) {
                $cuota_ext = ($tasa_mensual > 0) ? $monto_orig * ($tasa_mensual * pow(1 + $tasa_mensual, $plazo_maximo_permitido)) / (pow(1 + $tasa_mensual, $plazo_maximo_permitido) - 1) : $monto_orig / $plazo_maximo_permitido;
                if ($cuota_ext <= $capacidad) {
                    $desc = "Fase 1: Dilución de cuota mediante extensión del ciclo de vida a $plazo_maximo_permitido meses.";
                    $stmt = $db->prepare("INSERT INTO contrapropuesta_prestamo (id_prestamo, monto_sugerido, plazo_sugerido, tasa_sugerida, id_periodo_sugerido, id_amortizacion_sugerida, descripcion_estrategia, estado_contrapropuesta, fecha_contrapropuesta) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pendiente', CURDATE())");
                    $stmt->bind_param('ididiis', $id_prestamo, $monto_orig, $plazo_maximo_permitido, $tasa_orig, $periodo_orig, $amortizacion_orig, $desc);
                    $stmt->execute();
                    $opciones++;
                }
            }

            if ($opciones === 0 && $permitir_tasa) {
                $plazo_uso = $permitir_plazo ? $plazo_maximo_permitido : $plazo_orig;
                $cuota_rescate = ($tasa_min_mensual > 0) ? $monto_orig * ($tasa_min_mensual * pow(1 + $tasa_min_mensual, $plazo_uso)) / (pow(1 + $tasa_min_mensual, $plazo_uso) - 1) : $monto_orig / $plazo_uso;
                
                if ($cuota_rescate <= $capacidad) {
                    $desc = "Fase 2: Rescate mediante bonificación de tasa al " . number_format($tasa_minima_ia, 2) . "% para encajar la cuota.";
                    $stmt = $db->prepare("INSERT INTO contrapropuesta_prestamo (id_prestamo, monto_sugerido, plazo_sugerido, tasa_sugerida, id_periodo_sugerido, id_amortizacion_sugerida, descripcion_estrategia, estado_contrapropuesta, fecha_contrapropuesta) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pendiente', CURDATE())");
                    $stmt->bind_param('ididiis', $id_prestamo, $monto_orig, $plazo_uso, $tasa_minima_ia, $periodo_orig, $amortizacion_orig, $desc);
                    $stmt->execute();
                    $opciones++;
                }
            }
            
            if ($permitir_monto) {
                $mejor_tasa = $permitir_tasa ? $tasa_minima_ia : $tasa_orig;
                $mejor_tasa_m = ($mejor_tasa / 100) / 12;
                $mejor_plazo = $permitir_plazo ? $plazo_maximo_permitido : $plazo_orig;
                
                $monto_posible = ($mejor_tasa_m > 0) ? $cuota_objetivo * ((pow(1 + $mejor_tasa_m, $mejor_plazo) - 1) / ($mejor_tasa_m * pow(1 + $mejor_tasa_m, $mejor_plazo))) : $cuota_objetivo * $mejor_plazo;
                $monto_posible = floor($monto_posible / 1000) * 1000;
                
                if ($monto_posible >= $monto_minimo_permitido) {
                    $desc = "Fase 3: Contracción de capital a RD$ ".number_format($monto_posible)." respetando el límite de Down-Sell del ".($downsell_pct*100)."%.";
                    $stmt = $db->prepare("INSERT INTO contrapropuesta_prestamo (id_prestamo, monto_sugerido, plazo_sugerido, tasa_sugerida, id_periodo_sugerido, id_amortizacion_sugerida, descripcion_estrategia, estado_contrapropuesta, fecha_contrapropuesta) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pendiente', CURDATE())");
                    $stmt->bind_param('ididiis', $id_prestamo, $monto_posible, $mejor_plazo, $mejor_tasa, $periodo_orig, $amortizacion_orig, $desc);
                    $stmt->execute();
                    $opciones++;
                }
            }

            if ($opciones === 0) {
                $decision_final = 'Rechazado';
                $razones_rechazo[] = "Los límites impuestos en el producto (Down-Sell Máx: ".($downsell_pct*100)."%, Tasa Min: $tasa_minima_empresa%) restringen la viabilidad matemática. Operación cancelada.";
                $_SESSION['razones_rechazo'][$id_prestamo] = $razones_rechazo;
            }
        }
    }

    $stmt_eval = $db->prepare("INSERT INTO evaluacion_prestamo (id_cliente, id_prestamo, capacidad_pago, puntaje_total, nivel_riesgo, estado_evaluacion, fecha_evaluacion) VALUES (?, ?, ?, ?, ?, ?, CURDATE())");
    $stmt_eval->bind_param('iidiis', $prestamo['id_cliente'], $id_prestamo, $capacidad, $puntaje, $id_riesgo, $decision_final);
    $stmt_eval->execute();

    $estado_nuevo = ($decision_final === 'Rechazado') ? 'Rechazado' : 'En evaluacion';
    $db->query("UPDATE prestamo p JOIN cat_estado_prestamo ce ON ce.estado = '{$estado_nuevo}' SET p.id_estado_prestamo = ce.id_estado_prestamo WHERE p.id_prestamo = {$id_prestamo}");
    
    $db->commit();
    echo json_encode(['ok' => true, 'decision' => $decision_final]);

} catch (Exception $e) {
    $db->rollback();
    echo json_encode(['ok' => false, 'msg' => 'Error en motor de simulación: ' . $e->getMessage()]);
} finally {
    $db->autocommit(true);
}

$action = (string)input('action', '');

if ($action === 'confirmar_original') {
    $id_prestamo = (int)input('id_prestamo', 0);
    if ($id_prestamo <= 0) json_err('id_prestamo requerido');

    $estado_eval = estado_id($conn, 'En evaluacion', 2);

    $sqlInfo = "SELECT id_cliente, id_tipo_prestamo FROM prestamo WHERE id_prestamo = " . $id_prestamo;
    $resInfo = $conn->query($sqlInfo)->fetch_assoc();
    $prefijo = ($resInfo['id_tipo_prestamo'] == 2) ? 'HIP-' : 'PER-';
    $nuevo_contrato = $prefijo . date('Ymd-Hi') . '-' . str_pad($resInfo['id_cliente'], 4, '0', STR_PAD_LEFT);

    $conn->begin_transaction();
    try {
        exec_stmt(
            $conn,
            'UPDATE prestamo SET id_estado_prestamo = ?, numero_contrato = ? WHERE id_prestamo = ?',
            'isi',
            [$estado_eval, $nuevo_contrato, $id_prestamo]
        );
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        json_err('Error al confirmar original: ' . $e->getMessage(), 500);
    }

    unset($_SESSION['contrapropuestas'][$id_prestamo]);
    json_ok(['mensaje' => 'Solicitud original confirmada y enviada a revisión.']);
}

if ($action === 'confirmar_contrapropuesta') {
    $id_prestamo = (int)input('id_prestamo', 0);
    $opcion = (int)input('opcion', 0);
    $id_contrapropuesta = (int)input('id_contrapropuesta', 0);
    if ($id_prestamo <= 0) {
        json_err('Faltan parametros para confirmar contrapropuesta');
    }

    $seleccionada = null;

    if ($id_contrapropuesta > 0) {
        $rowCp = fetch_one(
            $conn,
            "
            SELECT cp.id_contrapropuesta, cp.monto_sugerido, cp.plazo_sugerido,
                   COALESCE(cp.tasa_sugerida, cpc.tasa_interes, tp.tasa_interes) AS tasa_anual,
                   COALESCE(cp.id_amortizacion_sugerida, cpc.id_tipo_amortizacion) AS amortizacion,
                   COALESCE(cp.id_periodo_sugerido, cpc.id_periodo_pago) AS periodo
            FROM contrapropuesta_prestamo cp
            INNER JOIN prestamo p ON p.id_prestamo = cp.id_prestamo
            INNER JOIN tipo_prestamo tp ON tp.id_tipo_prestamo = p.id_tipo_prestamo
            LEFT JOIN condicion_prestamo cpc ON cpc.id_condicion_prestamo = p.id_condicion_actual
            WHERE cp.id_contrapropuesta = ?
              AND cp.id_prestamo = ?
              AND cp.estado_contrapropuesta = 'Pendiente'
            LIMIT 1
            ",
            'ii',
            [$id_contrapropuesta, $id_prestamo]
        );

        if ($rowCp) {
            $montoCp = (float)$rowCp['monto_sugerido'];
            $plazoCp = max(1, (int)$rowCp['plazo_sugerido']);
            $tasaAnualCp = (float)($rowCp['tasa_anual'] ?? 0);
            $tasaMensualCp = ($tasaAnualCp / 100) / 12;
            if ($tasaMensualCp > 0) {
                $pow = pow(1 + $tasaMensualCp, $plazoCp);
                $den = ($pow - 1);
                $cuotaCp = $den == 0 ? ($montoCp / $plazoCp) : $montoCp * (($tasaMensualCp * $pow) / $den);
            } else {
                $cuotaCp = $montoCp / $plazoCp;
            }

            $seleccionada = [
                'id_contrapropuesta' => (int)$rowCp['id_contrapropuesta'],
                'opcion' => max(1, $opcion),
                'monto' => $montoCp,
                'plazo' => $plazoCp,
                'cuota' => round($cuotaCp, 2),
                'tasa' => $tasaAnualCp,
                'amortizacion' => (int)$rowCp['amortizacion'],
                'periodo' => (int)$rowCp['periodo'],
                'total_pagar' => round($cuotaCp * $plazoCp, 2),
            ];
        }
    }

    if (!$seleccionada) {
        json_err('Contrapropuesta no encontrada', 404);
    }

    $estado_eval = estado_id($conn, 'En evaluacion', 2);
    $sqlInfo = "SELECT id_cliente, id_tipo_prestamo FROM prestamo WHERE id_prestamo = " . $id_prestamo;
    $resInfo = $conn->query($sqlInfo)->fetch_assoc();
    $prefijo = ($resInfo['id_tipo_prestamo'] == 2) ? 'HIP-' : 'PER-';
    $nuevo_contrato = $prefijo . date('Ymd-Hi') . '-' . str_pad($resInfo['id_cliente'], 4, '0', STR_PAD_LEFT);

    $conn->begin_transaction();
    try {
        // Generar las nuevas condiciones vinculadas al préstamo si se alteraron
        $stmt_cond = $conn->prepare("INSERT INTO condicion_prestamo (tasa_interes, id_tipo_amortizacion, id_periodo_pago, vigente_desde, esta_activo) VALUES (?, ?, ?, CURDATE(), 1)");
        $stmt_cond->bind_param('dii', $seleccionada['tasa'], $seleccionada['amortizacion'], $seleccionada['periodo']);
        $stmt_cond->execute();
        $id_nueva_condicion = $conn->insert_id;

        exec_stmt(
            $conn,
            'UPDATE prestamo SET monto_solicitado = ?, plazo_meses = ?, id_estado_prestamo = ?, numero_contrato = ?, id_condicion_actual = ? WHERE id_prestamo = ?',
            'diisii',
            [(float)$seleccionada['monto'], (int)$seleccionada['plazo'], $estado_eval, $nuevo_contrato, $id_nueva_condicion, $id_prestamo]
        );

        if (!empty($seleccionada['id_contrapropuesta'])) {
            exec_stmt(
                $conn,
                'UPDATE contrapropuesta_prestamo SET estado_contrapropuesta = "Aceptada" WHERE id_contrapropuesta = ? AND id_prestamo = ?',
                'ii',
                [(int)$seleccionada['id_contrapropuesta'], $id_prestamo]
            );
            exec_stmt(
                $conn,
                'UPDATE contrapropuesta_prestamo SET estado_contrapropuesta = "Rechazada" WHERE id_prestamo = ? AND estado_contrapropuesta = "Pendiente" AND id_contrapropuesta <> ?',
                'ii',
                [$id_prestamo, (int)$seleccionada['id_contrapropuesta']]
            );
        }

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        json_err('Error al confirmar contrapropuesta: ' . $e->getMessage(), 500);
    }

    unset($_SESSION['contrapropuestas'][$id_prestamo]);
    json_ok([
        'mensaje' => 'Contrapropuesta confirmada. Se iniciara una nueva evaluacion con los datos ajustados.',
        're_evaluar' => true,
        'contrapropuesta' => $seleccionada,
    ]);
}

if ($action === 'rechazar_contrapropuesta') {
    $id_prestamo = (int)input('id_prestamo', 0);
    if ($id_prestamo <= 0) {
        json_err('id_prestamo requerido');
    }

    $conn->begin_transaction();
    try {
        exec_stmt($conn, 'DELETE FROM evaluacion_estrategica WHERE id_prestamo = ?', 'i', [$id_prestamo]);
        exec_stmt($conn, 'DELETE FROM evaluacion_prestamo WHERE id_prestamo = ?', 'i', [$id_prestamo]);
        exec_stmt($conn, 'DELETE FROM contrapropuesta_prestamo WHERE id_prestamo = ?', 'i', [$id_prestamo]);
        exec_stmt($conn, 'DELETE FROM cronograma_cuota WHERE id_prestamo = ?', 'i', [$id_prestamo]);
        
        $resGar = $conn->query("SELECT id_garantia FROM garantia WHERE id_prestamo = " . $id_prestamo);
        if ($resGar && $gar = $resGar->fetch_assoc()) {
            exec_stmt($conn, 'DELETE FROM detalle_garantia WHERE id_garantia = ?', 'i', [$gar['id_garantia']]);
            exec_stmt($conn, 'DELETE FROM garantia WHERE id_garantia = ?', 'i', [$gar['id_garantia']]);
        }

        exec_stmt($conn, 'DELETE FROM prestamo WHERE id_prestamo = ?', 'i', [$id_prestamo]);

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        json_err('Error al limpiar la solicitud: ' . $e->getMessage(), 500);
    }

    unset($_SESSION['contrapropuestas'][$id_prestamo]);
    json_ok(['mensaje' => 'Operación cancelada. El registro temporal ha sido eliminado del sistema.']);
}