<?php
require_once __DIR__ . '/../config/db.php';

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

    $sqlClientesTotales = "SELECT COUNT(*) AS clientes_totales FROM cliente";
    $res = $conn->query($sqlClientesTotales);
    if ($row = $res->fetch_assoc()) {
        $data['clientes_totales'] = (int)$row['clientes_totales'];
    }

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

function getTendencia30Dias($conn) {
    $desde = date('Y-m-d', strtotime('-30 days'));
    $hasta = date('Y-m-d');

    $sqlPrestamosDia = "
        SELECT fecha_desembolso AS fecha, SUM(monto_solicitado) AS total_prestado
        FROM prestamo p
        JOIN cat_estado_prestamo e ON e.id_estado_prestamo = p.id_estado_prestamo
        JOIN desembolso d ON d.id_prestamo = p.id_prestamo
        WHERE d.fecha_desembolso IS NOT NULL
          AND e.estado IN ('Activo','En mora')
          AND d.fecha_desembolso BETWEEN '$desde' AND '$hasta'
        GROUP BY d.fecha_desembolso
    ";
    $prestamosMap = [];
    $res = $conn->query($sqlPrestamosDia);
    while ($row = $res->fetch_assoc()) {
        $prestamosMap[$row['fecha']] = (float)$row['total_prestado'];
    }

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
        $labels[] = $row['periodo'];
        $values[] = (float)$row['total_interes'];
    }

    return [
        'labels' => $labels,
        'values' => $values
    ];
}

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

function getEventosCalendario($conn) {
    $eventos = [];

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
            "type"  => "pendiente", 
            "monto" => $row['total_monto']
        ];
    }

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
            "monto" => $row['saldo_cuota']
        ];
    }

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
            "type"  => "pagada",
            "monto" => $row['total_monto']
        ];
    }

    return $eventos;
}

function getAlertasSistema($conn) {
    $alertas = [
        'casi_mora' => 0,
        'vencen_hoy' => 0
    ];

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
