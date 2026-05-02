<?php
require_once __DIR__ . '/../config/db.php';

if (!function_exists('obtener_contrapropuestas_para_prestamo')) {
    function obtener_contrapropuestas_para_prestamo($conn, $id_prestamo)
    {
        $id_prestamo = (int)$id_prestamo;
        if ($id_prestamo <= 0) {
            return null;
        }

        $sqlPrestamo = "
            SELECT
                p.id_prestamo,
                p.id_cliente,
                p.numero_contrato,
                p.monto_solicitado,
                p.plazo_meses,
                tp.nombre AS tipo_prestamo,
                COALESCE(cp.tasa_interes, tp.tasa_interes) AS tasa_interes,
                dp.nombre AS nombre_cliente,
                dp.apellido AS apellido_cliente,
                ie.ingresos_mensuales,
                ev.capacidad_pago,
                ev.estado_evaluacion AS estado_evaluacion_actual
            FROM prestamo p
            INNER JOIN tipo_prestamo tp ON tp.id_tipo_prestamo = p.id_tipo_prestamo
            INNER JOIN cliente c ON c.id_cliente = p.id_cliente
            INNER JOIN datos_persona dp ON dp.id_datos_persona = c.id_datos_persona
            LEFT JOIN condicion_prestamo cp ON cp.id_condicion_prestamo = p.id_condicion_actual
            LEFT JOIN (
                SELECT ie1.*
                FROM ingresos_egresos ie1
                INNER JOIN (
                    SELECT id_cliente, MAX(id_ingresos_egresos) AS max_id
                    FROM ingresos_egresos
                    GROUP BY id_cliente
                ) ie2 ON ie2.id_cliente = ie1.id_cliente AND ie2.max_id = ie1.id_ingresos_egresos
            ) ie ON ie.id_cliente = p.id_cliente
            LEFT JOIN (
                SELECT ep1.id_prestamo, ep1.capacidad_pago
                FROM evaluacion_prestamo ep1
                INNER JOIN (
                    SELECT id_prestamo, MAX(id_evaluacion_prestamo) AS max_id
                    FROM evaluacion_prestamo
                    GROUP BY id_prestamo
                ) ep2 ON ep2.max_id = ep1.id_evaluacion_prestamo
            ) ev ON ev.id_prestamo = p.id_prestamo
            WHERE p.id_prestamo = ?
            LIMIT 1
        ";

        $stmt = $conn->prepare($sqlPrestamo);
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $id_prestamo);
        $stmt->execute();
        $prestamo = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$prestamo) {
            return null;
        }

        $estadoEvaluacionActual = trim((string)($prestamo['estado_evaluacion_actual'] ?? ''));
        if (strcasecmp($estadoEvaluacionActual, 'Contrapropuesta') !== 0) {
            return null;
        }

        $tasaAnual = (float)($prestamo['tasa_interes'] ?? 0);
        $tasaMensual = ($tasaAnual / 100) / 12;

        $montoOrig = (float)$prestamo['monto_solicitado'];
        $plazoOrig = max(1, (int)$prestamo['plazo_meses']);
        if ($tasaMensual > 0) {
            $pow = pow(1 + $tasaMensual, $plazoOrig);
            $den = ($pow - 1);
            $cuotaOrig = $den == 0 ? ($montoOrig / $plazoOrig) : $montoOrig * (($tasaMensual * $pow) / $den);
        } else {
            $cuotaOrig = $montoOrig / $plazoOrig;
        }

        $capDisponible = (float)($prestamo['capacidad_pago'] ?? 0);

        $stmtCp = $conn->prepare(
            "
            SELECT id_contrapropuesta, monto_sugerido, plazo_sugerido, estado_contrapropuesta
            FROM contrapropuesta_prestamo
            WHERE id_prestamo = ?
              AND estado_contrapropuesta = 'Pendiente'
            ORDER BY id_contrapropuesta ASC
            "
        );

        $opciones = [];
        if ($stmtCp) {
            $stmtCp->bind_param('i', $id_prestamo);
            $stmtCp->execute();
            $res = $stmtCp->get_result();
            while ($r = $res->fetch_assoc()) {
                $opciones[] = $r;
            }
            $stmtCp->close();
        }

        $lista = [];
        if (!empty($opciones)) {
            foreach ($opciones as $i => $cp) {
                $monto = (float)$cp['monto_sugerido'];
                $plazo = max(1, (int)$cp['plazo_sugerido']);
                if ($tasaMensual > 0) {
                    $pow = pow(1 + $tasaMensual, $plazo);
                    $den = ($pow - 1);
                    $cuota = $den == 0 ? ($monto / $plazo) : $monto * (($tasaMensual * $pow) / $den);
                } else {
                    $cuota = $monto / $plazo;
                }

                $lista[] = [
                    'opcion' => $i + 1,
                    'id_contrapropuesta' => (int)$cp['id_contrapropuesta'],
                    'monto' => $monto,
                    'plazo' => $plazo,
                    'cuota' => round($cuota, 2),
                    'tasa' => $tasaAnual,
                    'total_pagar' => round($cuota * $plazo, 2)
                ];
            }
        } else {
            $sessionCps = $_SESSION['contrapropuestas'][$id_prestamo] ?? [];
            foreach ($sessionCps as $i => $cp) {
                $lista[] = [
                    'opcion' => isset($cp['opcion']) ? (int)$cp['opcion'] : ($i + 1),
                    'id_contrapropuesta' => null,
                    'monto' => (float)($cp['monto'] ?? 0),
                    'plazo' => (int)($cp['plazo'] ?? 0),
                    'cuota' => (float)($cp['cuota'] ?? 0),
                    'tasa' => (float)($cp['tasa'] ?? $tasaAnual),
                    'total_pagar' => (float)($cp['total_pagar'] ?? 0)
                ];
            }
        }

        return [
            'prestamo' => $prestamo,
            'contrapropuestas' => $lista,
            'monto_orig' => $montoOrig,
            'plazo_orig' => $plazoOrig,
            'cuota_orig' => $cuotaOrig,
            'cap_disponible' => $capDisponible,
            'tasa_anual' => $tasaAnual,
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

    $data = obtener_contrapropuestas_para_prestamo($conn, $idPrestamo);
    if (!$data) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'No se encontro informacion de contrapropuesta']);
        exit;
    }

    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
