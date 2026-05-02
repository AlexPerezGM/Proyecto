<?php
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/autorizacion.php';
requiere_login();

header('Content-Type: application/json; charset=utf-8');

if (!($conn instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Conexion de base de datos no disponible']);
    exit;
}

$conn->set_charset('utf8mb4');

function json_ok(array $data = []) {
    echo json_encode(['ok' => true] + $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_err($msg, $code = 400, array $extra = []) {
    http_response_code((int)$code);
    echo json_encode(['ok' => false, 'error' => (string)$msg] + $extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function input($key, $default = null) {
    static $body = null;
    if ($body === null) {
        $raw = file_get_contents('php://input');
        $body = [];
        if ($raw !== false && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $body = $decoded;
            }
        }
    }

    if (isset($body[$key])) {
        return $body[$key];
    }
    if (isset($_POST[$key])) {
        return $_POST[$key];
    }
    if (isset($_GET[$key])) {
        return $_GET[$key];
    }

    return $default;
}

function contains_text($haystack, $needle) {
    if (!is_string($haystack) || !is_string($needle) || $needle === '') {
        return false;
    }
    return strpos($haystack, $needle) !== false;
}

function fetch_one(mysqli $conn, $sql, $types = '', array $params = []) {
    $st = $conn->prepare($sql);
    if (!$st) {
        throw new Exception($conn->error ?: 'Error preparando consulta');
    }
    if ($types !== '' && !empty($params)) {
        $st->bind_param($types, ...$params);
    }
    if (!$st->execute()) {
        $err = $st->error ?: 'Error ejecutando consulta';
        $st->close();
        throw new Exception($err);
    }
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return $row ?: null;
}

function exec_stmt(mysqli $conn, $sql, $types = '', array $params = []) {
    $st = $conn->prepare($sql);
    if (!$st) {
        throw new Exception($conn->error ?: 'Error preparando consulta');
    }
    if ($types !== '' && !empty($params)) {
        $st->bind_param($types, ...$params);
    }
    if (!$st->execute()) {
        $err = $st->error ?: 'Error ejecutando consulta';
        $st->close();
        throw new Exception($err);
    }
    $affected = $st->affected_rows;
    $insertId = $conn->insert_id;
    $st->close();
    return [$affected, $insertId];
}

function estado_id(mysqli $conn, $estado, $fallback) {
    $row = fetch_one(
        $conn,
        'SELECT id_estado_prestamo FROM cat_estado_prestamo WHERE estado = ? LIMIT 1',
        's',
        [$estado]
    );
    return (int)($row['id_estado_prestamo'] ?? $fallback);
}

function config_decimal(mysqli $conn, $nombre, $fallback = 0.0) {
    $row = fetch_one(
        $conn,
        "SELECT valor_decimal FROM configuracion WHERE nombre_configuracion = ? AND estado = 'Activo' LIMIT 1",
        's',
        [$nombre]
    );
    if (!$row || !isset($row['valor_decimal'])) {
        return (float)$fallback;
    }
    return (float)$row['valor_decimal'];
}

function table_columns(mysqli $conn, $table) {
    static $cache = [];
    if (isset($cache[$table])) {
        return $cache[$table];
    }

    $cols = [];
    $sql = "
        SELECT COLUMN_NAME
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
    ";

    $st = $conn->prepare($sql);
    if ($st) {
        $st->bind_param('s', $table);
        if ($st->execute()) {
            $res = $st->get_result();
            while ($r = $res->fetch_assoc()) {
                $cols[(string)$r['COLUMN_NAME']] = true;
            }
        }
        $st->close();
    }

    $cache[$table] = $cols;
    return $cols;
}

$action = (string)input('action', '');
if ($action === 'evaluar') {
    $action = 'evaluar_prestamo';
}

if ($action === 'evaluar_prestamo') {
    $id_prestamo = (int)input('id_prestamo', 0);
    if ($id_prestamo <= 0) {
        json_err('ID de prestamo no proporcionado');
    }

    try {
        $res_reglas = $conn->query("SELECT clave_regla, puntos FROM reglas_puntaje_interno WHERE estado = 'Activo'");
        if (!$res_reglas) {
            throw new Exception($conn->error ?: 'No fue posible cargar las reglas de puntaje');
        }

        $cols_eval_estrategica = table_columns($conn, 'evaluacion_estrategica');
        $eval_estrategica_tiene_id_cliente = isset($cols_eval_estrategica['id_cliente']);

        $reglas_db = [];
        while ($r = $res_reglas->fetch_assoc()) {
            $reglas_db[(string)$r['clave_regla']] = (int)$r['puntos'];
        }

        $getPuntos = function ($clave) use ($reglas_db) {
            return (int)($reglas_db[$clave] ?? 0);
        };

        $prestamo = fetch_one(
            $conn,
            "
            SELECT p.id_prestamo, p.id_cliente, p.monto_solicitado, p.plazo_meses,
                   p.fecha_solicitud, p.id_tipo_prestamo, p.numero_contrato,
                   tp.nombre AS tipo_prestamo, tp.tasa_interes AS tasa_interes_base,
                   tp.monto_minimo, tp.plazo_minimo_meses, tp.plazo_maximo_meses,
                   cep.estado AS estado_prestamo,
                   cp.tasa_interes AS tasa_condicion,
                   cp.id_tipo_amortizacion, cp.id_periodo_pago,
                   ta.tipo_amortizacion,
                   pp.periodo AS periodo_pago
            FROM prestamo p
            INNER JOIN tipo_prestamo tp ON tp.id_tipo_prestamo = p.id_tipo_prestamo
            LEFT JOIN cat_estado_prestamo cep ON cep.id_estado_prestamo = p.id_estado_prestamo
            LEFT JOIN condicion_prestamo cp ON cp.id_condicion_prestamo = p.id_condicion_actual
            LEFT JOIN cat_tipo_amortizacion ta ON ta.id_tipo_amortizacion = cp.id_tipo_amortizacion
            LEFT JOIN cat_periodo_pago pp ON pp.id_periodo_pago = cp.id_periodo_pago
            WHERE p.id_prestamo = ?
            LIMIT 1
            ",
            'i',
            [$id_prestamo]
        );

        if (!$prestamo) {
            json_err('Prestamo no encontrado', 404);
        }

        $id_cliente = (int)$prestamo['id_cliente'];

        $join_eval_estrategica_sql = $eval_estrategica_tiene_id_cliente
            ? "
            LEFT JOIN (
                  SELECT es1.id_cliente, es1.id_tipo_vivienda,
                      es1.id_sector_economico,
                      es1.antiguedad_empleado,
                      es1.cantidad_dependientes,
                       es1.porcentaje_utilizacion_credito,
                       es1.total_deudas_externas,
                       es1.cantidad_tarjetas_activas
                FROM evaluacion_estrategica es1
                INNER JOIN (
                    SELECT id_cliente, MAX(id_evaluacion_estrategica) AS max_id
                    FROM evaluacion_estrategica
                    GROUP BY id_cliente
                ) es2 ON es2.id_cliente = es1.id_cliente AND es2.max_id = es1.id_evaluacion_estrategica
            ) es ON es.id_cliente = c.id_cliente
            "
            : "
            LEFT JOIN (
                SELECT es1.id_evaluacion_estrategica,
                       p_es.id_cliente AS id_cliente_ref,
                       es1.id_tipo_vivienda,
                       es1.id_sector_economico,
                       es1.antiguedad_empleado,
                       es1.cantidad_dependientes,
                       es1.porcentaje_utilizacion_credito,
                       es1.total_deudas_externas,
                       es1.cantidad_tarjetas_activas
                FROM evaluacion_estrategica es1
                INNER JOIN prestamo p_es ON p_es.id_prestamo = es1.id_prestamo
            ) es ON es.id_cliente_ref = c.id_cliente
                AND es.id_evaluacion_estrategica = (
                    SELECT MAX(es2.id_evaluacion_estrategica)
                    FROM evaluacion_estrategica es2
                    INNER JOIN prestamo p2 ON p2.id_prestamo = es2.id_prestamo
                    WHERE p2.id_cliente = c.id_cliente
                )
            ";

        $sql_cliente = "
            SELECT dp.nombre, dp.apellido, dp.fecha_nacimiento,
                   c.fecha_registro,
                   ie.ingresos_mensuales, ie.egresos_mensuales,
                   oc.ocupacion, oc.empresa,
                   pc.puntaje AS score_crediticio,
                   pc.total_transacciones,
                   nr.nivel AS nivel_riesgo,
                   nr.id_nivel_riesgo,
                   tv.tipo_vivienda,
                   es.id_tipo_vivienda,
                   es.id_sector_economico,
                   es.antiguedad_empleado,
                   es.cantidad_dependientes,
                   es.porcentaje_utilizacion_credito AS uso_tarjetas,
                   es.total_deudas_externas AS deuda_externa,
                   es.cantidad_tarjetas_activas AS cantidad_productos
            FROM cliente c
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
            LEFT JOIN ocupacion oc ON oc.id_datos_persona = c.id_datos_persona
            LEFT JOIN (
                SELECT pc1.*
                FROM puntaje_crediticio pc1
                INNER JOIN (
                    SELECT id_cliente, MAX(id_puntaje_crediticio) AS max_id
                    FROM puntaje_crediticio
                    GROUP BY id_cliente
                ) pc2 ON pc2.id_cliente = pc1.id_cliente AND pc2.max_id = pc1.id_puntaje_crediticio
            ) pc ON pc.id_cliente = c.id_cliente
            LEFT JOIN cat_nivel_riesgo nr ON nr.id_nivel_riesgo = pc.id_nivel_riesgo
            {$join_eval_estrategica_sql}
            LEFT JOIN cat_tipo_vivienda tv ON tv.id_tipo_vivienda = es.id_tipo_vivienda
            WHERE c.id_cliente = ?
            LIMIT 1
            ";

        $cliente = fetch_one(
            $conn,
            $sql_cliente,
            'i',
            [$id_cliente]
        );

        if (!$cliente) {
            json_err('Cliente no encontrado', 404);
        }

        $historial_pagos = fetch_one(
            $conn,
            "
            SELECT COUNT(cc.id_cronograma_cuota) AS total_pagos,
                   SUM(CASE WHEN cc.estado_cuota = 'Pagada' THEN 1 ELSE 0 END) AS pagos_ok,
                   SUM(CASE WHEN cc.estado_cuota = 'Vencida' THEN 1 ELSE 0 END) AS pagos_vencidos,
                   COUNT(DISTINCT p2.id_prestamo) AS prestamos_activos
            FROM prestamo p2
            LEFT JOIN cronograma_cuota cc ON cc.id_prestamo = p2.id_prestamo
            WHERE p2.id_cliente = ? AND p2.id_prestamo <> ?
            ",
            'ii',
            [$id_cliente, $id_prestamo]
        );
        if (!$historial_pagos) {
            $historial_pagos = [
                'total_pagos' => 0,
                'pagos_ok' => 0,
                'pagos_vencidos' => 0,
                'prestamos_activos' => 0,
            ];
        }

        $ext = fetch_one(
            $conn,
            "
            SELECT COALESCE(SUM(t.cuota_referencia), 0) AS deuda_mensual_externa
            FROM (
                SELECT p2.id_prestamo, MIN(cc.total_monto) AS cuota_referencia
                FROM prestamo p2
                INNER JOIN cronograma_cuota cc ON cc.id_prestamo = p2.id_prestamo
                WHERE p2.id_cliente = ?
                  AND p2.id_prestamo <> ?
                  AND cc.estado_cuota IN ('Pendiente', 'Vencida')
                GROUP BY p2.id_prestamo
            ) t
            ",
            'ii',
            [$id_cliente, $id_prestamo]
        );

        $ingresos = (float)($cliente['ingresos_mensuales'] ?? 0);
        $egresos = (float)($cliente['egresos_mensuales'] ?? 0);
        $deuda_externa = (float)($cliente['deuda_externa'] ?? 0);
        $deuda_mensual_ext = (float)($ext['deuda_mensual_externa'] ?? 0);
        if ($deuda_mensual_ext <= 0 && $deuda_externa > 0) {
            $deuda_mensual_ext = $deuda_externa / 12;
        }

        $monto_solicitado = (float)$prestamo['monto_solicitado'];
        $plazo = max(1, (int)$prestamo['plazo_meses']);
        $tasa_anual = (float)($prestamo['tasa_condicion'] ?? $prestamo['tasa_interes_base']);
        if ($tasa_anual <= 0) {
            $tasa_anual = (float)($prestamo['tasa_interes_base'] ?? 0);
        }

        $max_porc_cap = config_decimal($conn, 'MAX_PORCENTAJE_CAPACIDAD_PAGO', 0.40);
        $ingreso_neto = $ingresos - $egresos;
        $capacidad_disponible = ($ingreso_neto * $max_porc_cap) - $deuda_mensual_ext;

        $tasa_mensual = ($tasa_anual / 100) / 12;
        if ($tasa_mensual > 0) {
            $pow = pow(1 + $tasa_mensual, $plazo);
            $den = ($pow - 1);
            $cuota = $den == 0 ? ($monto_solicitado / $plazo) : $monto_solicitado * (($tasa_mensual * $pow) / $den);
        } else {
            $cuota = $monto_solicitado / $plazo;
        }

        $total_pagar = $cuota * $plazo;
        $nivel_endeudamiento = ($ingresos > 0)
            ? (($cuota + $deuda_mensual_ext) / $ingresos) * 100
            : 100;

        $fecha_reg = new DateTime($cliente['fecha_registro'] ?: date('Y-m-d'));
        $hoy = new DateTime();
        $meses_cliente = (int)floor($fecha_reg->diff($hoy)->days / 30);

        $puntaje = 0;
        $detalle_puntaje = [];

        $score = (int)($cliente['score_crediticio'] ?? 0);
        $total_pagos = (int)($historial_pagos['total_pagos'] ?? 0);
        $pagos_vencidos = (int)($historial_pagos['pagos_vencidos'] ?? 0);

        $ocupacion_txt = strtolower((string)($cliente['ocupacion'] ?? ''));
        if (contains_text($ocupacion_txt, 'publi') || contains_text($ocupacion_txt, 'gobier')) {
            $pts = $getPuntos('ESTABILIDAD_PUBLICO');
            $label = 'Publico';
        } elseif (contains_text($ocupacion_txt, 'independ') || contains_text($ocupacion_txt, 'cuenta prop')) {
            $pts = $getPuntos('ESTABILIDAD_INDEPENDIENTE');
            $label = 'Independiente';
        } elseif (!empty($cliente['empresa'])) {
            $pts = $getPuntos('ESTABILIDAD_PRIVADO_FIJO');
            $label = 'Privado fijo';
        } else {
            $pts = $getPuntos('ESTABILIDAD_TEMPORAL');
            $label = 'Temporal';
        }
        $puntaje += $pts;
        $detalle_puntaje[] = ['factor' => 'Estabilidad laboral', 'descripcion' => $label, 'puntos' => $pts];

        $antiguedad_meses = max(0, (int)($cliente['antiguedad_empleado'] ?? 0));
        if ($antiguedad_meses >= 24) {
            $pts = $getPuntos('ANTIGUEDAD_24M_O_MAS');
            $label = '2 anos o mas';
        } elseif ($antiguedad_meses >= 12) {
            $pts = $getPuntos('ANTIGUEDAD_12_23M');
            $label = '1-2 anos';
        } elseif ($antiguedad_meses >= 6) {
            $pts = $getPuntos('ANTIGUEDAD_6_11M');
            $label = '6-12 meses';
        } else {
            $pts = $getPuntos('ANTIGUEDAD_0_5M');
            $label = 'Menos de 6 meses';
        }
        $puntaje += $pts;
        $detalle_puntaje[] = ['factor' => 'Antiguedad laboral', 'descripcion' => $label, 'puntos' => $pts];

        if ($total_pagos <= 0) {
            $pts = 0;
            $label = 'Sin historial de pagos';
        } elseif ($pagos_vencidos === 0) {
            $pts = $getPuntos('COMPORTAMIENTO_SIN_ATRASOS');
            $label = 'Sin atrasos';
        } elseif ($pagos_vencidos <= 2) {
            $pts = $getPuntos('COMPORTAMIENTO_ATRASOS_LEVES');
            $label = 'Atrasos leves';
        } else {
            $pts = $getPuntos('COMPORTAMIENTO_ATRASOS_RECURRENTES');
            $label = 'Atrasos recurrentes';
        }
        $puntaje += $pts;
        $detalle_puntaje[] = ['factor' => 'Comportamiento crediticio', 'descripcion' => $label, 'puntos' => $pts];

        $uso_tarjetas = max(0, (float)($cliente['uso_tarjetas'] ?? 0));
        if ($uso_tarjetas <= 30) {
            $pts = $getPuntos('USO_CREDITO_HASTA_30');
            $label = 'Hasta 30%';
        } elseif ($uso_tarjetas <= 70) {
            $pts = $getPuntos('USO_CREDITO_31_70');
            $label = '30%-70%';
        } else {
            $pts = $getPuntos('USO_CREDITO_MAS_70');
            $label = 'Mas de 70%';
        }
        $puntaje += $pts;
        $detalle_puntaje[] = ['factor' => 'Uso de credito', 'descripcion' => $label, 'puntos' => $pts];

        $vivienda = strtolower((string)($cliente['tipo_vivienda'] ?? ''));
        if (contains_text($vivienda, 'propia')) {
            $pts = $getPuntos('VIVIENDA_PROPIA');
            $label = 'Propia';
        } elseif (contains_text($vivienda, 'familiar')) {
            $pts = $getPuntos('VIVIENDA_FAMILIAR');
            $label = 'Familiar';
        } elseif (contains_text($vivienda, 'alquilada')) {
            $pts = $getPuntos('VIVIENDA_ALQUILADA');
            $label = 'Alquilada';
        } else {
            $pts = 0;
            $label = 'No especificado';
        }
        $puntaje += $pts;
        $detalle_puntaje[] = ['factor' => 'Condicion de vivienda', 'descripcion' => $label, 'puntos' => $pts];

        $dependientes = max(0, (int)($cliente['cantidad_dependientes'] ?? 0));
        if ($dependientes === 0) {
            $pts = $getPuntos('DEPENDIENTES_0');
            $label = '0 dependientes';
        } elseif ($dependientes <= 2) {
            $pts = $getPuntos('DEPENDIENTES_1_2');
            $label = '1-2 dependientes';
        } elseif ($dependientes <= 4) {
            $pts = $getPuntos('DEPENDIENTES_3_4');
            $label = '3-4 dependientes';
        } else {
            $pts = $getPuntos('DEPENDIENTES_MAS_4');
            $label = 'Mas de 4 dependientes';
        }
        $puntaje += $pts;
        $detalle_puntaje[] = ['factor' => 'Dependientes', 'descripcion' => $label, 'puntos' => $pts];

        if ($total_pagos > 0 && $pagos_vencidos === 0) {
            $pts = $getPuntos('RELACION_CLIENTE_BUENO');
            $label = 'Cliente bueno';
        } elseif ($meses_cliente < 1 || $total_pagos === 0) {
            $pts = $getPuntos('RELACION_NUEVO');
            $label = 'Nuevo';
        } else {
            $pts = $getPuntos('RELACION_HISTORIAL_NEGATIVO');
            $label = 'Historial negativo';
        }
        $puntaje += $pts;
        $detalle_puntaje[] = ['factor' => 'Relacion con la entidad', 'descripcion' => $label, 'puntos' => $pts];

        // Puntaje de nivel de riesgo segun el puntaje total
        $p = obtener_nivel_riesgo($conn, (int)$puntaje);
        $nivel_riesgo = $p['nivel'];
        $id_nivel_riesgo = (int)$p['id_nivel_riesgo'];

        // Parametros de decision
        $max_nivel_endeudamiento = config_decimal($conn, 'MAX_NIVEL_ENDEUDAMIENTO', 60.0);
        $min_puntaje_rechazo = config_int(
            $conn,
            'MIN_PUNTAJE_RECHAZO',
            config_int($conn, 'MIN_PUNTAJE_RECHAZADO', 20)
        );
        $min_puntaje_contrapropuesta = config_int($conn, 'MIN_PUNTAJE_CONTRAPROPUESTA', 40);

        $decision_codigo = obtener_decision($conn, (int)$puntaje);
        $map_decision = [
            'APROBADO' => 'Aprobado',
            'RECHAZADO' => 'Rechazado',
            'CONTRAPROPUESTA' => 'Contrapropuesta',
            'REVISION_MANUAL' => 'Revision_manual',
            'PENDIENTE' => 'Pendiente',
        ];
        $decision = $map_decision[$decision_codigo] ?? 'Revision_manual';

        $razones_rechazo = [];
        $capacidad_ok = $cuota <= $capacidad_disponible;
        if (!$capacidad_ok) {
            $razones_rechazo[] = 'Cuota mensual de RD$' . number_format($cuota, 2) . ' supera la capacidad disponible de RD$' . number_format($capacidad_disponible, 2);
        }
        if ($nivel_endeudamiento > $max_nivel_endeudamiento) {
            $razones_rechazo[] = 'Nivel de endeudamiento de ' . number_format($nivel_endeudamiento, 2) . '%, excede el limite recomendado del ' . number_format($max_nivel_endeudamiento, 2) . '%.';
        }
        if ($total_pagos > 0 && $pagos_vencidos > 0 && ($pagos_vencidos / $total_pagos) > 0.3) {
            $razones_rechazo[] = 'Historial de pagos desfavorable: ' . $pagos_vencidos . ' cuotas vencidas de ' . $total_pagos . ' pagos registrados.';
        }
        if ($ingresos < ((float)$prestamo['monto_minimo'] / 10)) {
            $razones_rechazo[] = 'Ingresos mensuales insuficientes para el tipo de prestamo solicitado.';
        }

        // Toma de decisiones
        if ($decision !== 'Revision_manual' && !$capacidad_ok && $puntaje >= $min_puntaje_contrapropuesta && count($razones_rechazo) === 1) {
            $decision = 'Contrapropuesta';
        } elseif ($puntaje < $min_puntaje_rechazo || $nivel_endeudamiento > $max_nivel_endeudamiento) {
            $decision = 'Rechazado';
        } elseif (!empty($razones_rechazo) && $decision === 'Aprobado') {
            $decision = 'Revision_manual';
        }

        $contrapropuestas = [];
        if ($decision === 'Contrapropuesta') {
            $factor_capacidad_cp = config_decimal($conn, 'FACTOR_CAPACIDAD_CONTRAPROPUESTA', 0.95);
            $factor_extension_plazo_cp = config_decimal($conn, 'FACTOR_EXTENSION_PLAZO_CONTRAPROPUESTA', 1.5);
            $plazo_max = max($plazo, (int)($prestamo['plazo_maximo_meses'] ?? $plazo));

            $cuota_max = max(0, $capacidad_disponible * $factor_capacidad_cp);
            $plazo_op2 = min((int)ceil($plazo * $factor_extension_plazo_cp), $plazo_max);

            $calcular_monto_maximo = function ($cuota_target, $plazo_target, $tasa_m) {
                if ($plazo_target <= 0) {
                    return 0;
                }
                if ($tasa_m > 0) {
                    $pow = pow(1 + $tasa_m, $plazo_target);
                    return $cuota_target * (($pow - 1) / ($tasa_m * $pow));
                }
                return $cuota_target * $plazo_target;
            };

            $monto_op1 = round($calcular_monto_maximo($cuota_max, $plazo, $tasa_mensual), -3);
            if ($monto_op1 > 0) {
                $cuota_op1 = ($tasa_mensual > 0)
                    ? $monto_op1 * (($tasa_mensual * pow(1 + $tasa_mensual, $plazo)) / (pow(1 + $tasa_mensual, $plazo) - 1))
                    : ($monto_op1 / $plazo);
                $contrapropuestas[] = [
                    'opcion' => count($contrapropuestas) + 1,
                    'monto' => $monto_op1,
                    'plazo' => $plazo,
                    'cuota' => round($cuota_op1, 2),
                    'tasa' => $tasa_anual,
                    'total_pagar' => round($cuota_op1 * $plazo, 2),
                    'descripcion' => 'Mismo plazo, monto ajustado a la capacidad de pago',
                ];
            }

            if ($plazo_op2 > $plazo) {
                $cuota_op2 = ($tasa_mensual > 0)
                    ? $monto_solicitado * (($tasa_mensual * pow(1 + $tasa_mensual, $plazo_op2)) / (pow(1 + $tasa_mensual, $plazo_op2) - 1))
                    : ($monto_solicitado / $plazo_op2);
                if ($cuota_op2 <= $capacidad_disponible) {
                    $contrapropuestas[] = [
                        'opcion' => count($contrapropuestas) + 1,
                        'monto' => $monto_solicitado,
                        'plazo' => $plazo_op2,
                        'cuota' => round($cuota_op2, 2),
                        'tasa' => $tasa_anual,
                        'total_pagar' => round($cuota_op2 * $plazo_op2, 2),
                        'descripcion' => 'Monto original con plazo extendido para reducir la cuota',
                    ];
                }
            }

            $monto_op3 = round($calcular_monto_maximo($cuota_max, $plazo_op2, $tasa_mensual), -3);
            if ($plazo_op2 > 0 && $monto_op3 >= (float)$prestamo['monto_minimo']) {
                $cuota_op3 = ($tasa_mensual > 0)
                    ? $monto_op3 * (($tasa_mensual * pow(1 + $tasa_mensual, $plazo_op2)) / (pow(1 + $tasa_mensual, $plazo_op2) - 1))
                    : ($monto_op3 / $plazo_op2);
                $contrapropuestas[] = [
                    'opcion' => count($contrapropuestas) + 1,
                    'monto' => $monto_op3,
                    'plazo' => $plazo_op2,
                    'cuota' => round($cuota_op3, 2),
                    'tasa' => $tasa_anual,
                    'total_pagar' => round($cuota_op3 * $plazo_op2, 2),
                    'descripcion' => 'Monto y plazo ajustados para mayor comodidad',
                ];
            }

            if (empty($contrapropuestas)) {
                $decision = 'Rechazado';
                $razones_rechazo[] = 'No fue posible generar una contrapropuesta viable con los parametros del cliente';
            }
        }

        $estado_desembolso = estado_id($conn, 'Desembolso', 3);
        $estado_eval = estado_id($conn, 'En evaluacion', 2);
        $estado_rechazo = estado_id($conn, 'Rechazado', 4);

        $nuevo_estado = $estado_eval;
        if ($decision === 'Aprobado') {
            $nuevo_estado = $estado_desembolso;
        } elseif ($decision === 'Rechazado') {
            $nuevo_estado = $estado_rechazo;
        }

        $estado_evaluacion = in_array($decision, ['Aprobado', 'Rechazado', 'Contrapropuesta', 'Revision_manual'], true)
            ? $decision
            : 'Pendiente';

        $obs = 'Puntaje interno: ' . $puntaje . ' | Decision: ' . $decision . ' | ';
        $obs .= 'Cuota: RD$' . number_format($cuota, 2) . ' | Capacidad disponible: RD$' . number_format($capacidad_disponible, 2);
        if (!empty($razones_rechazo)) {
            $obs .= ' | RAZONES: ' . implode('; ', $razones_rechazo);
        }

        $id_tipo_vivienda_eval = (int)($cliente['id_tipo_vivienda'] ?? 0);
        $id_sector_eval = (int)($cliente['id_sector_economico'] ?? 0);
        $antiguedad_eval = (int)($cliente['antiguedad_empleado'] ?? 0);
        $dependientes_eval = (int)($cliente['cantidad_dependientes'] ?? 0);

        if ($id_tipo_vivienda_eval <= 0) {
            $rowV = fetch_one($conn, 'SELECT id_tipo_vivienda FROM cat_tipo_vivienda ORDER BY id_tipo_vivienda ASC LIMIT 1');
            $id_tipo_vivienda_eval = (int)($rowV['id_tipo_vivienda'] ?? 1);
        }
        if ($id_sector_eval <= 0) {
            $rowS = fetch_one($conn, 'SELECT id_sector_economico FROM cat_sector_economico ORDER BY id_sector_economico ASC LIMIT 1');
            $id_sector_eval = (int)($rowS['id_sector_economico'] ?? 1);
        }

        $tipo_ingreso_eval = 'fijo';
        if (contains_text((string)($cliente['ocupacion'] ?? ''), 'independ')) {
            $tipo_ingreso_eval = 'Variable';
        }

        $empresa_eval = trim((string)($cliente['empresa'] ?? ''));
        if ($empresa_eval === '') $empresa_eval = 'N/D';

        $edad_al_finalizar = 0;
        try {
            $fn = new DateTime((string)$cliente['fecha_nacimiento']);
            $ff = new DateTime();
            $ff->modify('+' . $plazo . ' months');
            $edad_al_finalizar = (int)$fn->diff($ff)->y;
        } catch (Throwable $__) {
            $edad_al_finalizar = 0;
        }

        $puntaje_crediticio_eval = $score > 0 ? $score : $puntaje;
        $uso_tarjetas_eval = (float)$uso_tarjetas;
        $deuda_externa_eval = (float)$deuda_externa;
        $cantidad_productos_eval = (int)($cliente['cantidad_productos'] ?? 0);
        $salario_neto_eval = (float)$ingreso_neto;
        $gastos_fijos_eval = (float)$egresos;
        $capacidad_pago_eval = (float)$capacidad_disponible;
        $nivel_endeudamiento_eval = (float)$nivel_endeudamiento;

        $id_usuario = isset($_SESSION['usuario']['id_usuario']) ? (int)$_SESSION['usuario']['id_usuario'] : null;

        $conn->begin_transaction();
        try {
            exec_stmt(
                $conn,
                'UPDATE prestamo SET id_estado_prestamo = ? WHERE id_prestamo = ?',
                'ii',
                [$nuevo_estado, $id_prestamo]
            );

            list(, $id_evaluacion) = exec_stmt(
                $conn,
                'INSERT INTO evaluacion_prestamo (id_cliente, id_prestamo, capacidad_pago, puntaje_total, nivel_riesgo, estado_evaluacion, fecha_evaluacion) VALUES (?, ?, ?, ?, ?, ?, CURDATE())',
                'iidiis',
                [$id_cliente, $id_prestamo, $capacidad_disponible, $puntaje, $id_nivel_riesgo, $estado_evaluacion]
            );

            if ($eval_estrategica_tiene_id_cliente) {
                $es = fetch_one(
                    $conn,
                    'SELECT id_evaluacion_estrategica FROM evaluacion_estrategica WHERE id_cliente = ? ORDER BY id_evaluacion_estrategica DESC LIMIT 1',
                    'i',
                    [$id_cliente]
                );
            } else {
                $es = fetch_one(
                    $conn,
                    'SELECT id_evaluacion_estrategica FROM evaluacion_estrategica WHERE id_prestamo = ? ORDER BY id_evaluacion_estrategica DESC LIMIT 1',
                    'i',
                    [$id_prestamo]
                );
            }

            if ($es && !empty($es['id_evaluacion_estrategica'])) {
                exec_stmt(
                    $conn,
                    'UPDATE evaluacion_estrategica
                     SET id_prestamo = ?, salario_neto = ?, tipo_ingreso = ?, empresa_laboral = ?, antiguedad_empleado = ?, id_sector_economico = ?,
                         puntaje_crediticio = ?, cantidad_tarjetas_activas = ?, porcentaje_utilizacion_credito = ?, total_deudas_externas = ?,
                         total_gastos_fijos = ?, capacidad_pago = ?, nivel_endeudamiento = ?, id_tipo_vivienda = ?, cantidad_dependientes = ?, edad_al_finalizar = ?
                     WHERE id_evaluacion_estrategica = ?',
                    'idssiiiidddddiiii',
                    [
                        $id_prestamo,
                        $salario_neto_eval,
                        $tipo_ingreso_eval,
                        $empresa_eval,
                        $antiguedad_eval,
                        $id_sector_eval,
                        $puntaje_crediticio_eval,
                        $cantidad_productos_eval,
                        $uso_tarjetas_eval,
                        $deuda_externa_eval,
                        $gastos_fijos_eval,
                        $capacidad_pago_eval,
                        $nivel_endeudamiento_eval,
                        $id_tipo_vivienda_eval,
                        $dependientes_eval,
                        $edad_al_finalizar,
                        (int)$es['id_evaluacion_estrategica']
                    ]
                );
            } else {
                if ($eval_estrategica_tiene_id_cliente) {
                    exec_stmt(
                        $conn,
                        'INSERT INTO evaluacion_estrategica
                         (id_cliente, id_prestamo, salario_neto, tipo_ingreso, empresa_laboral, antiguedad_empleado, id_sector_economico,
                          puntaje_crediticio, cantidad_tarjetas_activas, porcentaje_utilizacion_credito, total_deudas_externas,
                          total_gastos_fijos, capacidad_pago, nivel_endeudamiento, id_tipo_vivienda, cantidad_dependientes, edad_al_finalizar)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                        'iidssiiiidddddiii',
                        [
                            $id_cliente,
                            $id_prestamo,
                            $salario_neto_eval,
                            $tipo_ingreso_eval,
                            $empresa_eval,
                            $antiguedad_eval,
                            $id_sector_eval,
                            $puntaje_crediticio_eval,
                            $cantidad_productos_eval,
                            $uso_tarjetas_eval,
                            $deuda_externa_eval,
                            $gastos_fijos_eval,
                            $capacidad_pago_eval,
                            $nivel_endeudamiento_eval,
                            $id_tipo_vivienda_eval,
                            $dependientes_eval,
                            $edad_al_finalizar
                        ]
                    );
                } else {
                    exec_stmt(
                        $conn,
                        'INSERT INTO evaluacion_estrategica
                         (id_prestamo, salario_neto, tipo_ingreso, empresa_laboral, antiguedad_empleado, id_sector_economico,
                          puntaje_crediticio, cantidad_tarjetas_activas, porcentaje_utilizacion_credito, total_deudas_externas,
                          total_gastos_fijos, capacidad_pago, nivel_endeudamiento, id_tipo_vivienda, cantidad_dependientes, edad_al_finalizar)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                        'idssiiiidddddiii',
                        [
                            $id_prestamo,
                            $salario_neto_eval,
                            $tipo_ingreso_eval,
                            $empresa_eval,
                            $antiguedad_eval,
                            $id_sector_eval,
                            $puntaje_crediticio_eval,
                            $cantidad_productos_eval,
                            $uso_tarjetas_eval,
                            $deuda_externa_eval,
                            $gastos_fijos_eval,
                            $capacidad_pago_eval,
                            $nivel_endeudamiento_eval,
                            $id_tipo_vivienda_eval,
                            $dependientes_eval,
                            $edad_al_finalizar
                        ]
                    );
                }
            }

            if ($id_usuario !== null) {
                exec_stmt(
                    $conn,
                    'INSERT INTO detalle_evaluacion (id_evaluacion_prestamo, fecha, observacion, evaluado_por) VALUES (?, CURDATE(), ?, ?)',
                    'isi',
                    [$id_evaluacion, $obs, $id_usuario]
                );
            } else {
                exec_stmt(
                    $conn,
                    'INSERT INTO detalle_evaluacion (id_evaluacion_prestamo, fecha, observacion, evaluado_por) VALUES (?, CURDATE(), ?, NULL)',
                    'is',
                    [$id_evaluacion, $obs]
                );
            }

            if (!empty($contrapropuestas)) {
                exec_stmt($conn, 'DELETE FROM contrapropuesta_prestamo WHERE id_prestamo = ? AND estado_contrapropuesta = "Pendiente"', 'i', [$id_prestamo]);
                foreach ($contrapropuestas as $cp) {
                    exec_stmt(
                        $conn,
                        'INSERT INTO contrapropuesta_prestamo (id_prestamo, monto_sugerido, plazo_sugerido, estado_contrapropuesta, fecha_contrapropuesta) VALUES (?, ?, ?, "Pendiente", CURDATE())',
                        'idi',
                        [$id_prestamo, (float)$cp['monto'], (int)$cp['plazo']]
                    );
                }
                $_SESSION['contrapropuestas'][$id_prestamo] = $contrapropuestas;
            } else {
                // Evita mostrar contrapropuestas viejas cuando una nueva evaluacion ya no requiere ajuste.
                exec_stmt($conn, 'DELETE FROM contrapropuesta_prestamo WHERE id_prestamo = ? AND estado_contrapropuesta = "Pendiente"', 'i', [$id_prestamo]);
                unset($_SESSION['contrapropuestas'][$id_prestamo]);
            }

            $conn->commit();
        } catch (Throwable $txErr) {
            $conn->rollback();
            throw $txErr;
        }

        json_ok([
            'decision' => $decision,
            'puntaje' => $puntaje,
            'nivel_riesgo' => $nivel_riesgo,
            'id_evaluacion' => (int)$id_evaluacion,
            'capacidad_disponible' => round($capacidad_disponible, 2),
            'cuota_calculada' => round($cuota, 2),
            'total_pagar' => round($total_pagar, 2),
            'nivel_endeudamiento' => round($nivel_endeudamiento, 2),
            'ingreso_neto' => round($ingreso_neto, 2),
            'razones_rechazo' => $razones_rechazo,
            'detalle_puntaje' => $detalle_puntaje,
            'contrapropuestas' => $contrapropuestas,
            'prestamo' => [
                'numero_contrato' => $prestamo['numero_contrato'],
                'tipo_prestamo' => $prestamo['tipo_prestamo'],
                'monto_solicitado' => $monto_solicitado,
                'plazo_meses' => $plazo,
                'tasa_interes' => $tasa_anual,
                'tipo_amortizacion' => $prestamo['tipo_amortizacion'],
                'periodo_pago' => $prestamo['periodo_pago'],
            ],
            'cliente' => [
                'nombre' => trim((string)$cliente['nombre'] . ' ' . (string)$cliente['apellido']),
                'ingresos' => $ingresos,
                'egresos' => $egresos,
                'score' => $score,
                'prestamos_activos' => (int)($historial_pagos['prestamos_activos'] ?? 0),
                'meses_cliente' => (int)$meses_cliente,
                'pagos_ok' => (int)($historial_pagos['pagos_ok'] ?? 0),
                'pagos_vencidos' => $pagos_vencidos,
            ],
        ]);
    } catch (Throwable $e) {
        json_err('Error al evaluar el prestamo: ' . $e->getMessage(), 500);
    }
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
                   COALESCE(cpc.tasa_interes, tp.tasa_interes) AS tasa_anual
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
                'total_pagar' => round($cuotaCp * $plazoCp, 2),
            ];
        }
    }

    if (!$seleccionada && $opcion > 0) {
        $listCp = [];
        $stmtCp = $conn->prepare(
            "
            SELECT cp.id_contrapropuesta, cp.monto_sugerido, cp.plazo_sugerido,
                   COALESCE(cpc.tasa_interes, tp.tasa_interes) AS tasa_anual
            FROM contrapropuesta_prestamo cp
            INNER JOIN prestamo p ON p.id_prestamo = cp.id_prestamo
            INNER JOIN tipo_prestamo tp ON tp.id_tipo_prestamo = p.id_tipo_prestamo
            LEFT JOIN condicion_prestamo cpc ON cpc.id_condicion_prestamo = p.id_condicion_actual
            WHERE cp.id_prestamo = ?
              AND cp.estado_contrapropuesta = 'Pendiente'
            ORDER BY cp.id_contrapropuesta ASC
            "
        );
        if ($stmtCp) {
            $stmtCp->bind_param('i', $id_prestamo);
            $stmtCp->execute();
            $resCp = $stmtCp->get_result();
            while ($r = $resCp->fetch_assoc()) {
                $listCp[] = $r;
            }
            $stmtCp->close();
        }

        if (isset($listCp[$opcion - 1])) {
            $cp = $listCp[$opcion - 1];
            $montoCp = (float)$cp['monto_sugerido'];
            $plazoCp = max(1, (int)$cp['plazo_sugerido']);
            $tasaAnualCp = (float)($cp['tasa_anual'] ?? 0);
            $tasaMensualCp = ($tasaAnualCp / 100) / 12;
            if ($tasaMensualCp > 0) {
                $pow = pow(1 + $tasaMensualCp, $plazoCp);
                $den = ($pow - 1);
                $cuotaCp = $den == 0 ? ($montoCp / $plazoCp) : $montoCp * (($tasaMensualCp * $pow) / $den);
            } else {
                $cuotaCp = $montoCp / $plazoCp;
            }

            $seleccionada = [
                'id_contrapropuesta' => (int)$cp['id_contrapropuesta'],
                'opcion' => $opcion,
                'monto' => $montoCp,
                'plazo' => $plazoCp,
                'cuota' => round($cuotaCp, 2),
                'tasa' => $tasaAnualCp,
                'total_pagar' => round($cuotaCp * $plazoCp, 2),
            ];
        }
    }

    if (!$seleccionada && $opcion > 0) {
        $contrapropuestas = $_SESSION['contrapropuestas'][$id_prestamo] ?? [];
        foreach ($contrapropuestas as $cp) {
            if ((int)$cp['opcion'] === $opcion) {
                $seleccionada = $cp;
                break;
            }
        }
    }

    if (!$seleccionada) {
        json_err('Contrapropuesta no encontrada', 404);
    }

    $estado_eval = estado_id($conn, 'En evaluacion', 2);

    $conn->begin_transaction();
    try {
        exec_stmt(
            $conn,
            'UPDATE prestamo SET monto_solicitado = ?, plazo_meses = ?, id_estado_prestamo = ? WHERE id_prestamo = ?',
            'diii',
            [(float)$seleccionada['monto'], (int)$seleccionada['plazo'], $estado_eval, $id_prestamo]
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
        } else {
            exec_stmt(
                $conn,
                'UPDATE contrapropuesta_prestamo SET estado_contrapropuesta = "Aceptada" WHERE id_prestamo = ? AND estado_contrapropuesta = "Pendiente"',
                'i',
                [$id_prestamo]
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

    $estado_rechazo = estado_id($conn, 'Rechazado', 4);

    $conn->begin_transaction();
    try {
        exec_stmt(
            $conn,
            'UPDATE prestamo SET id_estado_prestamo = ? WHERE id_prestamo = ?',
            'ii',
            [$estado_rechazo, $id_prestamo]
        );

        exec_stmt(
            $conn,
            'UPDATE evaluacion_prestamo SET estado_evaluacion = "Rechazado" WHERE id_evaluacion_prestamo = (SELECT t.id_evaluacion_prestamo FROM (SELECT id_evaluacion_prestamo FROM evaluacion_prestamo WHERE id_prestamo = ? ORDER BY id_evaluacion_prestamo DESC LIMIT 1) t)',
            'i',
            [$id_prestamo]
        );

        exec_stmt(
            $conn,
            'UPDATE contrapropuesta_prestamo SET estado_contrapropuesta = "Rechazada" WHERE id_prestamo = ? AND estado_contrapropuesta = "Pendiente"',
            'i',
            [$id_prestamo]
        );

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        json_err('Error al rechazar contrapropuesta: ' . $e->getMessage(), 500);
    }

    unset($_SESSION['contrapropuestas'][$id_prestamo]);
    json_ok(['mensaje' => 'El cliente rechazo las contrapropuestas. Prestamo marcado como rechazado.']);
}
json_err('Accion no reconocida: ' . $action);

function config_int(mysqli $conn, $nombre, $fallback = 0){
    return (int)round(config_decimal($conn, $nombre, $fallback));
}

function obtener_nivel_riesgo(mysqli $conn, int $puntaje): array{
    $sql = "
        SELECT nr.id_nivel_riesgo, nr.nivel
        FROM configuracion_intervalo_riesgo cir
        INNER JOIN cat_nivel_riesgo nr ON nr.id_nivel_riesgo = cir.id_nivel_riesgo
        WHERE cir.estado = 'Activo' AND (cir.vigente_hasta IS NULL OR cir.vigente_hasta >= CURDATE())
            AND cir.vigente_desde <= CURDATE() AND ? BETWEEN cir.puntaje_minimo AND cir.puntaje_maximo
            ORDER BY cir.prioridad ASC, cir.id_intervalo_riesgo ASC LIMIT 1
    ";
    $row = fetch_one($conn, $sql, 'i', [$puntaje]);
    if ($row) {
        return [
            'id_nivel_riesgo' => (int)$row['id_nivel_riesgo'],
            'nivel' => $row['nivel'],
        ];
    }

    $sqlFallbackIntervalo = "
        SELECT nr.id_nivel_riesgo, nr.nivel
        FROM configuracion_intervalo_riesgo cir
        INNER JOIN cat_nivel_riesgo nr ON nr.id_nivel_riesgo = cir.id_nivel_riesgo
        WHERE cir.estado = 'Activo'
            AND (cir.vigente_hasta IS NULL OR cir.vigente_hasta >= CURDATE())
            AND cir.vigente_desde <= CURDATE()
        ORDER BY
            CASE
                WHEN ? < cir.puntaje_minimo THEN cir.puntaje_minimo - ?
                WHEN ? > cir.puntaje_maximo THEN ? - cir.puntaje_maximo
                ELSE 0
            END ASC,
            cir.prioridad ASC,
            cir.id_intervalo_riesgo ASC
        LIMIT 1
    ";
    $fallback = fetch_one($conn, $sqlFallbackIntervalo, 'iiii', [$puntaje, $puntaje, $puntaje, $puntaje]);

    if (!$fallback) {
        $fallback = fetch_one($conn, "SELECT id_nivel_riesgo, nivel FROM cat_nivel_riesgo ORDER BY id_nivel_riesgo ASC LIMIT 1");
    }

    return [
        'id_nivel_riesgo' => (int)($fallback['id_nivel_riesgo'] ?? 0),
        'nivel' => (string)($fallback['nivel'] ?? 'Desconocido'),
    ];
}

function obtener_decision(mysqli $conn, int $puntaje): string{
    $sql = "
        SELECT cde.codigo_decision
        FROM configuracion_intervalo_decision cid
        INNER JOIN cat_decision_evaluacion cde ON cde.id_decision_evaluacion = cid.id_decision_evaluacion
        WHERE cid.estado = 'Activo' 
            AND cde.estado = 'Activo'
            AND (cid.vigente_hasta IS NULL OR cid.vigente_hasta >= CURDATE())
            AND cid.vigente_desde <= CURDATE()
            AND ? BETWEEN cid.puntaje_minimo AND cid.puntaje_maximo
            ORDER BY cid.prioridad ASC, cid.id_intervalo_decision ASC LIMIT 1";
    $row = fetch_one($conn, $sql, 'i', [$puntaje]);
    if (!$row || empty($row['codigo_decision'])){
        return 'REVISION_MANUAL';
    }
    return (string)$row['codigo_decision'];
}
