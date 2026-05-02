<?php
require_once __DIR__ . '/../config/db.php';

if (!function_exists('obtener_resultado_evaluacion')) {
    function obtener_resultado_evaluacion(mysqli $conn, int $id_prestamo, ?int $id_evaluacion = null): ?array
    {
        if ($id_prestamo <= 0) {
            return null;
        }

        $whereEvaluacion = 'ep.id_prestamo = ?';
        $bindTypes = 'i';
        $bindValues = [$id_prestamo];

        if ($id_evaluacion !== null && $id_evaluacion > 0) {
            $whereEvaluacion .= ' AND ep.id_evaluacion_prestamo = ?';
            $bindTypes .= 'i';
            $bindValues[] = $id_evaluacion;
        }

        $sql = "
            SELECT
                ep.id_evaluacion_prestamo,
                ep.capacidad_pago,
                ep.estado_evaluacion,
                ep.fecha_evaluacion,
                nr.nivel AS nivel_riesgo,
                de.observacion,
                p.id_prestamo,
                p.numero_contrato,
                p.monto_solicitado,
                p.plazo_meses,
                p.id_tipo_prestamo,
                tp.nombre AS tipo_prestamo,
                COALESCE(cp.tasa_interes, tp.tasa_interes) AS tasa_aplicada,
                cep.estado AS estado_prestamo,
                ta.tipo_amortizacion,
                dp.nombre AS nombre_cliente,
                dp.apellido AS apellido_cliente,
                c.fecha_registro,
                ie.ingresos_mensuales,
                ie.egresos_mensuales,
                pc.puntaje AS score_crediticio,
                c.id_cliente,
                rc.total_pagar AS total_pagar_resumen
            FROM evaluacion_prestamo ep
            INNER JOIN prestamo p ON p.id_prestamo = ep.id_prestamo
            INNER JOIN tipo_prestamo tp ON tp.id_tipo_prestamo = p.id_tipo_prestamo
            LEFT JOIN cat_estado_prestamo cep ON cep.id_estado_prestamo = p.id_estado_prestamo
            LEFT JOIN cat_nivel_riesgo nr ON nr.id_nivel_riesgo = ep.nivel_riesgo
            INNER JOIN cliente c ON c.id_cliente = ep.id_cliente
            INNER JOIN datos_persona dp ON dp.id_datos_persona = c.id_datos_persona
            LEFT JOIN (
                SELECT ie1.*
                FROM ingresos_egresos ie1
                INNER JOIN (
                    SELECT id_cliente, MAX(id_ingresos_egresos) AS max_id
                    FROM ingresos_egresos
                    GROUP BY id_cliente
                ) ie2 ON ie2.id_cliente = ie1.id_cliente AND ie2.max_id = ie1.id_ingresos_egresos
            ) ie ON ie.id_cliente = c.id_cliente
            LEFT JOIN (
                SELECT pc1.*
                FROM puntaje_crediticio pc1
                INNER JOIN (
                    SELECT id_cliente, MAX(id_puntaje_crediticio) AS max_id
                    FROM puntaje_crediticio
                    GROUP BY id_cliente
                ) pc2 ON pc2.id_cliente = pc1.id_cliente AND pc2.max_id = pc1.id_puntaje_crediticio
            ) pc ON pc.id_cliente = c.id_cliente
            LEFT JOIN (
                SELECT de1.id_evaluacion_prestamo, de1.observacion
                FROM detalle_evaluacion de1
                INNER JOIN (
                    SELECT id_evaluacion_prestamo, MAX(id_detalle_evaluacion) AS max_id
                    FROM detalle_evaluacion
                    GROUP BY id_evaluacion_prestamo
                ) de2 ON de2.max_id = de1.id_detalle_evaluacion
            ) de ON de.id_evaluacion_prestamo = ep.id_evaluacion_prestamo
            LEFT JOIN condicion_prestamo cp ON cp.id_condicion_prestamo = p.id_condicion_actual
            LEFT JOIN cat_tipo_amortizacion ta ON ta.id_tipo_amortizacion = cp.id_tipo_amortizacion
            LEFT JOIN resumen_cronograma rc ON rc.id_prestamo = p.id_prestamo
            WHERE {$whereEvaluacion}
            ORDER BY ep.id_evaluacion_prestamo DESC
            LIMIT 1
        ";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param($bindTypes, ...$bindValues);
        $stmt->execute();
        $ev = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$ev) {
            return null;
        }

        $hist = [
            'pagos_ok' => 0,
            'pagos_vencidos' => 0,
            'prestamos_activos' => 0,
        ];

        $stmtHist = $conn->prepare(
            "
            SELECT
                SUM(CASE WHEN cc.estado_cuota = 'Pagada' THEN 1 ELSE 0 END) AS pagos_ok,
                SUM(CASE WHEN cc.estado_cuota = 'Vencida' THEN 1 ELSE 0 END) AS pagos_vencidos,
                COUNT(DISTINCT p2.id_prestamo) AS prestamos_activos
            FROM prestamo p2
            LEFT JOIN cronograma_cuota cc ON cc.id_prestamo = p2.id_prestamo
            WHERE p2.id_cliente = ? AND p2.id_prestamo <> ?
            "
        );

        if ($stmtHist) {
            $idClienteHist = (int)$ev['id_cliente'];
            $stmtHist->bind_param('ii', $idClienteHist, $id_prestamo);
            $stmtHist->execute();
            $histRow = $stmtHist->get_result()->fetch_assoc();
            if ($histRow) {
                $hist = [
                    'pagos_ok' => (int)($histRow['pagos_ok'] ?? 0),
                    'pagos_vencidos' => (int)($histRow['pagos_vencidos'] ?? 0),
                    'prestamos_activos' => (int)($histRow['prestamos_activos'] ?? 0),
                ];
            }
            $stmtHist->close();
        }

        $fechaRegistro = !empty($ev['fecha_registro']) ? new DateTime($ev['fecha_registro']) : new DateTime();
        $hoy = new DateTime();
        $mesesCliente = (int)floor($fechaRegistro->diff($hoy)->days / 30);

        $observacion = (string)($ev['observacion'] ?? '');

        $puntaje = '—';
        if (preg_match('/Puntaje\s+interno:\s*(-?\d+)/i', $observacion, $mP)) {
            $puntaje = (int)$mP[1];
        } elseif (isset($ev['score_crediticio']) && $ev['score_crediticio'] !== null) {
            $puntaje = (int)$ev['score_crediticio'];
        }

        $decision = (string)($ev['estado_evaluacion'] ?? 'Pendiente');
        if (preg_match('/Decision:\s*([A-Za-z_]+)/i', $observacion, $mD)) {
            $decision = $mD[1];
        }

        $mapDecision = [
            'Aprobado' => 'Aprobado',
            'Rechazado' => 'Rechazado',
            'Revision_manual' => 'Revision_manual',
            'Contrapropuesta' => 'Contrapropuesta',
            'Pendiente' => 'Pendiente',
        ];
        $decision = $mapDecision[$decision] ?? 'Pendiente';

        $razones = [];
        if (preg_match('/RAZONES:\s*(.+)$/i', $observacion, $mR)) {
            $tmp = explode(';', $mR[1]);
            foreach ($tmp as $r) {
                $r = trim($r);
                if ($r !== '') {
                    $razones[] = $r;
                }
            }
        }

        $monto = (float)($ev['monto_solicitado'] ?? 0);
        $plazo = max(1, (int)($ev['plazo_meses'] ?? 1));
        $tasaAnual = (float)($ev['tasa_aplicada'] ?? 0);
        $tasaMensual = ($tasaAnual / 100) / 12;

        if ($tasaMensual > 0) {
            $pow = pow(1 + $tasaMensual, $plazo);
            $den = ($pow - 1);
            $cuota = $den == 0 ? ($monto / $plazo) : $monto * (($tasaMensual * $pow) / $den);
        } else {
            $cuota = $monto / $plazo;
        }

        $totalPagar = isset($ev['total_pagar_resumen']) && $ev['total_pagar_resumen'] !== null
            ? (float)$ev['total_pagar_resumen']
            : ($cuota * $plazo);

        $capacidad = (float)($ev['capacidad_pago'] ?? 0);
        $porcCubierto = $cuota > 0 ? (int)min(100, round(($capacidad / $cuota) * 100)) : 0;

        $colorMap = [
            'Aprobado' => [
                'badge_bg' => '#16a34a',
                'icon' => '✅',
                'label' => 'APROBADO',
                'estado_border' => '#86efac',
                'estado_bg' => '#f0fdf4',
                'cap_bg' => '#16a34a',
            ],
            'Rechazado' => [
                'badge_bg' => '#dc2626',
                'icon' => '❌',
                'label' => 'RECHAZADO',
                'estado_border' => '#fca5a5',
                'estado_bg' => '#fff1f2',
                'cap_bg' => '#dc2626',
            ],
            'Contrapropuesta' => [
                'badge_bg' => '#0f766e',
                'icon' => '🔄',
                'label' => 'CONTRAPROPUESTA',
                'estado_border' => '#99f6e4',
                'estado_bg' => '#f0fdfa',
                'cap_bg' => '#0f766e',
            ],
            'Revision_manual' => [
                'badge_bg' => '#d97706',
                'icon' => '👁️',
                'label' => 'REVISION MANUAL',
                'estado_border' => '#fde68a',
                'estado_bg' => '#fffbeb',
                'cap_bg' => '#d97706',
            ],
            'Pendiente' => [
                'badge_bg' => '#d97706',
                'icon' => '⏳',
                'label' => 'PENDIENTE',
                'estado_border' => '#fde68a',
                'estado_bg' => '#fffbeb',
                'cap_bg' => '#d97706',
            ],
        ];

        return [
            'ev' => $ev,
            'hist' => $hist,
            'mesesCliente' => $mesesCliente,
            'observacion' => $observacion,
            'puntaje' => $puntaje,
            'decision' => $decision,
            'razones' => $razones,
            'monto' => $monto,
            'plazo' => $plazo,
            'tasaAnual' => $tasaAnual,
            'tasaMensual' => $tasaMensual,
            'cuota' => $cuota,
            'totalPagar' => $totalPagar,
            'capacidad' => $capacidad,
            'porcCubierto' => $porcCubierto,
            'col' => $colorMap[$decision] ?? $colorMap['Pendiente'],
        ];
    }
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    require_once __DIR__ . '/autorizacion.php';
    requiere_login();

    header('Content-Type: application/json; charset=utf-8');

    $idPrestamo = (int)($_GET['id_prestamo'] ?? $_POST['id_prestamo'] ?? 0);
    if ($idPrestamo <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'id_prestamo requerido']);
        exit;
    }

    $idEvaluacion = (int)($_GET['id_evaluacion'] ?? $_POST['id_evaluacion'] ?? 0);
    $idEvaluacion = $idEvaluacion > 0 ? $idEvaluacion : null;

    $data = obtener_resultado_evaluacion($conn, $idPrestamo, $idEvaluacion);
    if (!$data) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Resultado no encontrado']);
        exit;
    }

    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
