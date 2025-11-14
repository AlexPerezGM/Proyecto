<?php
// includes/dashboard_data.php
require_once __DIR__ . '/../config/db.php';

/**
 * 1. RESUMEN GLOBAL (tarjetas de arriba)
 *    Usa:
 *    - pago (monto_pagado, fecha_pago)
 *    - prestamo (monto_solicitado, id_estado_prestamo, fecha_solicitud)
 *    - cat_estado_prestamo (estado)
 *    - cronograma_cuota (estado_cuota, saldo_pendiente)
 *    - cliente / datos_persona
 *    - fondos (total_fondos)
 *    Estas tablas existen en tu esquema. :contentReference[oaicite:2]{index=2}
 */
mysqli_report(MYSQLI_REPORT_OFF);

function getResumenGlobal($conn) {
    $data = [
        'ganancia_mes'      => 0,
        'total_prestado'    => 0,
        'clientes_mora'     => 0,
        'clientes_al_dia'   => 0,
        'clientes_totales'  => 0,
        'flujo_disponible'  => 0,
    ];

    // Ganancia del mes:
    // suma de los pagos registrados este mes (pago.monto_pagado)
    $sqlGananciaMes = "
        SELECT COALESCE(SUM(monto_pagado),0) AS ganancia_mes
        FROM pago
        WHERE fecha_pago BETWEEN DATE_FORMAT(CURDATE(),'%Y-%m-01')
                            AND LAST_DAY(CURDATE())
    ";
    $res = $conn->query($sqlGananciaMes);
    if ($row = $res->fetch_assoc()) {
        $data['ganancia_mes'] = (float)$row['ganancia_mes'];
    }

    // Total Prestado (suma de préstamos activos / en mora)
    $sqlTotalPrestado = "
        SELECT COALESCE(SUM(p.monto_solicitado),0) AS total_prestado
        FROM prestamo p
        JOIN cat_estado_prestamo e 
            ON p.id_estado_prestamo = e.id_estado_prestamo
        WHERE e.estado IN ('Activo','En mora')
    ";
    $res = $conn->query($sqlTotalPrestado);
    if ($row = $res->fetch_assoc()) {
        $data['total_prestado'] = (float)$row['total_prestado'];
    }

    // Clientes en mora (tienen al menos una cuota vencida en cronograma_cuota)
    $sqlClientesMora = "
        SELECT COUNT(DISTINCT p.id_cliente) AS clientes_mora
        FROM cronograma_cuota c
        JOIN prestamo p ON p.id_prestamo = c.id_prestamo
        WHERE c.estado_cuota = 'Vencida'
    ";
    $res = $conn->query($sqlClientesMora);
    if ($row = $res->fetch_assoc()) {
        $data['clientes_mora'] = (int)$row['clientes_mora'];
    }

    // Clientes al día = clientes con préstamo que NO aparecen en mora
    $sqlClientesAlDia = "
        SELECT COUNT(DISTINCT p.id_cliente) AS clientes_al_dia
        FROM prestamo p
        WHERE p.id_cliente NOT IN (
            SELECT DISTINCT p2.id_cliente
            FROM cronograma_cuota c2
            JOIN prestamo p2 ON p2.id_prestamo = c2.id_prestamo
            WHERE c2.estado_cuota = 'Vencida'
        )
    ";
    $res = $conn->query($sqlClientesAlDia);
    if ($row = $res->fetch_assoc()) {
        $data['clientes_al_dia'] = (int)$row['clientes_al_dia'];
    }

    // Clientes Totales
    $sqlClientesTotales = "SELECT COUNT(*) AS clientes_totales FROM cliente";
    $res = $conn->query($sqlClientesTotales);
    if ($row = $res->fetch_assoc()) {
        $data['clientes_totales'] = (int)$row['clientes_totales'];
    }

    // Fondos disponibles (fondos.total_fondos) - total_prestado
    // Tu tabla fondos tiene: cartera_normal, cartera_vencida, total_fondos, actualizacion. :contentReference[oaicite:3]{index=3}
    $sqlFondos = "
        SELECT total_fondos 
        FROM fondos
        ORDER BY actualizacion DESC
        LIMIT 1
    ";
    $res = $conn->query($sqlFondos);
    $totalFondos = 0;
    if ($row = $res->fetch_assoc()) {
        $totalFondos = (float)$row['total_fondos'];
    }

    $data['flujo_disponible'] = $totalFondos - $data['total_prestado'];

    return $data;
}

/**
 * 2. TENDENCIA 30 DÍAS (línea)
 *    Series:
 *    - Préstamos otorgados por día (prestamo.monto_solicitado / fecha_solicitud)
 *    - Pagos recibidos por día (pago.monto_pagado / fecha_pago)
 *    - Mora generada por día (cronograma_cuota.saldo_pendiente cuando estado_cuota='Vencida')
 *    Todas estas tablas están en tu BD. :contentReference[oaicite:4]{index=4}
 */
function getTendencia30Dias($conn) {
    $desde = date('Y-m-d', strtotime('-30 days'));
    $hasta = date('Y-m-d');

    // Préstamos nuevos por día
    $sqlPrestamosDia = "
        SELECT fecha_solicitud AS fecha, SUM(monto_solicitado) AS total_prestado
        FROM prestamo
        WHERE fecha_solicitud BETWEEN '$desde' AND '$hasta'
        GROUP BY fecha_solicitud
    ";
    $prestamosMap = [];
    $res = $conn->query($sqlPrestamosDia);
    while ($row = $res->fetch_assoc()) {
        $prestamosMap[$row['fecha']] = (float)$row['total_prestado'];
    }

    // Pagos recibidos por día
    $sqlPagosDia = "
        SELECT fecha_pago AS fecha, SUM(monto_pagado) AS total_pagado
        FROM pago
        WHERE fecha_pago BETWEEN '$desde' AND '$hasta'
        GROUP BY fecha_pago
    ";
    $pagosMap = [];
    $res = $conn->query($sqlPagosDia);
    while ($row = $res->fetch_assoc()) {
        $pagosMap[$row['fecha']] = (float)$row['total_pagado'];
    }

    // Mora: saldo pendiente de las cuotas que están VENCIDAS ese día
    // cronograma_cuota tiene: fecha_vencimiento, saldo_pendiente, estado_cuota ('Pendiente','Pagada','Vencida'). :contentReference[oaicite:5]{index=5}
    $sqlMoraDia = "
        SELECT fecha_vencimiento AS fecha, SUM(saldo_cuota) AS total_mora
        FROM cronograma_cuota
        WHERE estado_cuota = 'Vencida'
        AND fecha_vencimiento BETWEEN '$desde' AND '$hasta'
        GROUP BY fecha_vencimiento
    ";
    $moraMap = [];
    $res = $conn->query($sqlMoraDia);
    while ($row = $res->fetch_assoc()) {
        $moraMap[$row['fecha']] = (float)$row['total_mora'];
    }

    // Unificamos fechas día por día
    $labels = [];
    $seriePrestamos = [];
    $seriePagos = [];
    $serieMora = [];

    $d = strtotime($desde);
    $end = strtotime($hasta);
    while ($d <= $end) {
        $f = date('Y-m-d', $d);
        $labels[] = $f;
        $seriePrestamos[] = $prestamosMap[$f] ?? 0;
        $seriePagos[]     = $pagosMap[$f] ?? 0;
        $serieMora[]      = $moraMap[$f] ?? 0;
        $d = strtotime('+1 day', $d);
    }

    return [
        'labels'    => $labels,
        'prestamos' => $seriePrestamos,
        'pagos'     => $seriePagos,
        'mora'      => $serieMora
    ];
}

/**
 * 3. INGRESOS POR INTERÉS (barras)
 *    Sacamos cuánto se cobró de INTERÉS por periodo (mes).
 *    - asignacion_pago (monto_asignado, tipo_asignacion = 'Interes')
 *    - pago (fecha_pago)
 *    Ambas tablas están en tu BD. :contentReference[oaicite:6]{index=6}
 */
function getIngresosInteresMensual($conn) {
    $sql = "
        SELECT DATE_FORMAT(p.fecha_pago, '%Y-%m') AS periodo,
               SUM(ap.monto_asignado) AS total_interes
        FROM asignacion_pago ap
        JOIN pago p ON p.id_pago = ap.id_pago
        WHERE ap.tipo_asignacion = 'Interes'
        AND p.fecha_pago >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY periodo
        ORDER BY periodo
    ";

    $labels = [];
    $values = [];
    $res = $conn->query($sql);
    while ($row = $res->fetch_assoc()) {
        $labels[] = $row['periodo']; // ej: "2025-10"
        $values[] = (float)$row['total_interes'];
    }

    return [
        'labels' => $labels,
        'values' => $values
    ];
}

/**
 * 4A. Últimos 3 clientes nuevos
 *    datos_persona (nombre, apellido, creado_en)
 *    cliente (id_cliente)
 *    prestamo (monto_solicitado, id_estado_prestamo)
 *    cat_estado_prestamo (estado)
 */
function getUltimosClientes($conn) {
    $sql = "
        SELECT dp.nombre,
               dp.apellido,
               dp.creado_en AS fecha_registro,
               pr.monto_solicitado,
               e.estado AS estado_prestamo
        FROM cliente c
        JOIN datos_persona dp 
            ON c.id_datos_persona = dp.id_datos_persona
        LEFT JOIN prestamo pr 
            ON pr.id_cliente = c.id_cliente
        LEFT JOIN cat_estado_prestamo e 
            ON e.id_estado_prestamo = pr.id_estado_prestamo
        ORDER BY dp.creado_en DESC
        LIMIT 3
    ";

    $rows = [];
    $res = $conn->query($sql);
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

/**
 * 4B. Préstamos más grandes activos / en mora
 *     prestamo, cliente, datos_persona, cronograma_cuota, cat_estado_prestamo
 */
function getPrestamosGrandesActivos($conn) {
    $sql = "
        SELECT pr.id_prestamo,
               dp.nombre,
               dp.apellido,
               pr.monto_solicitado,
               pr.fecha_solicitud,
               MIN(cc.fecha_vencimiento) AS primera_cuota,
               MAX(cc.fecha_vencimiento) AS ultima_cuota,
               e.estado AS estado_prestamo
        FROM prestamo pr
        JOIN cliente c ON c.id_cliente = pr.id_cliente
        JOIN datos_persona dp ON dp.id_datos_persona = c.id_datos_persona
        JOIN cat_estado_prestamo e ON e.id_estado_prestamo = pr.id_estado_prestamo
        LEFT JOIN cronograma_cuota cc ON cc.id_prestamo = pr.id_prestamo
        WHERE e.estado IN ('Activo','En mora')
        GROUP BY pr.id_prestamo
        ORDER BY pr.monto_solicitado DESC
        LIMIT 5
    ";

    $rows = [];
    $res = $conn->query($sql);
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

/**
 * 4C. Próximos pagos
 *     cronograma_cuota (fecha_vencimiento, total_monto, estado_cuota)
 *     prestamo -> cliente -> datos_persona
 */
function getProximosPagos($conn) {
    $sql = "
        SELECT ccu.fecha_vencimiento,
               dp.nombre,
               dp.apellido,
               ccu.total_monto,
               ccu.estado_cuota
        FROM cronograma_cuota ccu
        JOIN prestamo p ON p.id_prestamo = ccu.id_prestamo
        JOIN cliente cl ON cl.id_cliente = p.id_cliente
        JOIN datos_persona dp ON dp.id_datos_persona = cl.id_datos_persona
        WHERE ccu.estado_cuota IN ('Pendiente','Vencida')
        ORDER BY ccu.fecha_vencimiento ASC
        LIMIT 10
    ";

    $rows = [];
    $res = $conn->query($sql);
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

/**
 * 5. Calendario de cobros / mora:
 *    - Amarillo: pagos próximos (cuotas Pendiente que vencen pronto)
 *    - Rojo: pagos vencidos (Vencida)
 *    - Verde: pagadas (Pagada este mes)
 */
function getEventosCalendario($conn) {
    $eventos = [];

    // Amarillo: cuotas Pendiente que vencen en los próximos 7 días
    $sqlPendiente = "
        SELECT dp.nombre, dp.apellido,
               c.fecha_vencimiento AS fecha,
               c.numero_cuota,
               c.total_monto
        FROM cronograma_cuota c
        JOIN prestamo p ON p.id_prestamo = c.id_prestamo
        JOIN cliente cl ON cl.id_cliente = p.id_cliente
        JOIN datos_persona dp ON dp.id_datos_persona = cl.id_datos_persona
        WHERE c.estado_cuota = 'Pendiente'
          AND c.fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ";
    $res = $conn->query($sqlPendiente);
    while ($row = $res->fetch_assoc()) {
        $eventos[] = [
            "date"  => $row['fecha'],
            "title" => "Pagar cuota #".$row['numero_cuota']." - ".$row['nombre']." ".$row['apellido'],
            "type"  => "pendiente", // amarillo
            "monto" => $row['total_monto']
        ];
    }

    // Rojo: cuotas Vencida (mora activa)
    $sqlVencida = "
        SELECT dp.nombre, dp.apellido,
               c.fecha_vencimiento AS fecha,
               c.numero_cuota,
               c.saldo_cuota
        FROM cronograma_cuota c
        JOIN prestamo p ON p.id_prestamo = c.id_prestamo
        JOIN cliente cl ON cl.id_cliente = p.id_cliente
        JOIN datos_persona dp ON dp.id_datos_persona = cl.id_datos_persona
        WHERE c.estado_cuota = 'Vencida'
          AND c.fecha_vencimiento < CURDATE()
        ORDER BY c.fecha_vencimiento ASC
        LIMIT 30
    ";
    $res = $conn->query($sqlVencida);
    while ($row = $res->fetch_assoc()) {
        $eventos[] = [
            "date"  => $row['fecha'],
            "title" => "MORA cuota #".$row['numero_cuota']." - ".$row['nombre']." ".$row['apellido'],
            "type"  => "vencida", // rojo
            "monto" => $row['saldo_pendiente']
        ];
    }

    // Verde: cuotas Pagada este mes (control visual)
    $sqlPagada = "
        SELECT dp.nombre, dp.apellido,
               c.fecha_vencimiento AS fecha,
               c.numero_cuota,
               c.total_monto
        FROM cronograma_cuota c
        JOIN prestamo p ON p.id_prestamo = c.id_prestamo
        JOIN cliente cl ON cl.id_cliente = p.id_cliente
        JOIN datos_persona dp ON dp.id_datos_persona = cl.id_datos_persona
        WHERE c.estado_cuota = 'Pagada'
          AND c.fecha_vencimiento BETWEEN DATE_FORMAT(CURDATE(),'%Y-%m-01')
                                      AND LAST_DAY(CURDATE())
    ";
    $res = $conn->query($sqlPagada);
    while ($row = $res->fetch_assoc()) {
        $eventos[] = [
            "date"  => $row['fecha'],
            "title" => "Pagado cuota #".$row['numero_cuota']." - ".$row['nombre']." ".$row['apellido'],
            "type"  => "pagada", // verde
            "monto" => $row['total_monto']
        ];
    }

    return $eventos;
}

/**
 * 6. Alertas del sistema (panel lateral debajo del calendario)
 *    Ej:
 *    - "3 clientes están por entrar en mora esta semana"
 *    - "Hoy se vencen 2 préstamos"
 */
function getAlertasSistema($conn) {
    $alertas = [
        'casi_mora' => 0,
        'vencen_hoy' => 0
    ];

    // Clientes con cuotas pendientes que vencen en los próximos 7 días
    $sqlCasiMora = "
        SELECT COUNT(DISTINCT p.id_cliente) AS cant
        FROM cronograma_cuota c
        JOIN prestamo p ON p.id_prestamo = c.id_prestamo
        WHERE c.estado_cuota = 'Pendiente'
          AND c.fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ";
    $res = $conn->query($sqlCasiMora);
    if ($row = $res->fetch_assoc()) {
        $alertas['casi_mora'] = (int)$row['cant'];
    }

    // Cuotas que vencen HOY (Pendiente o Vencida)
    $sqlVencenHoy = "
        SELECT COUNT(*) AS cant
        FROM cronograma_cuota
        WHERE estado_cuota IN ('Pendiente','Vencida')
        AND fecha_vencimiento = CURDATE()
    ";
    $res = $conn->query($sqlVencenHoy);
    if ($row = $res->fetch_assoc()) {
        $alertas['vencen_hoy'] = (int)$row['cant'];
    }

    return $alertas;
}

?>
