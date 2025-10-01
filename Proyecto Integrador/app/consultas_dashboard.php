<?php
require_once 'conf/connection.php';

try {
    $stmt_prestamos = $pdo->prepare("SELECT COUNT(*) FROM prestamos 
    WHERE estado = 'activo'");
    $stmt_prestamos->execute();
    $total_prestamos = $stmt_prestamos->fetchColumn();

    $stmt_clientes = $pdo->prepare("SELECT COUNT(*) FROM clientes");
    $stmt_clientes->execute();
    $total_clientes = $stmt_clientes->fetchColumn();

    $stmt_morosos = $pdo->prepare("SELECT COUNT(*) 
        FROM (
            SELECT id_prestamo, fecha_vencimiento 
            FROM prestamos_personales 
            UNION ALL
            SELECT id_prestamo, fecha_vencimiento 
            FROM prestamos_hipotecarios
        ) AS sub
        INNER JOIN prestamos p ON p.id_prestamo = sub.id_prestamo
        WHERE sub.fecha_vencimiento < CURDATE()
        AND p.estado = 'activo'");
    $stmt_morosos->execute();
    $total_morosos = $stmt_morosos->fetchColumn();

    $stmt_ingresos = $pdo->prepare("SELECT COALESCE(SUM(monto_pagado), 0) 
        FROM pagos 
        WHERE MONTH(fecha_pago) = MONTH(CURDATE()) 
        AND YEAR(fecha_pago) = YEAR(CURDATE())");
    $stmt_ingresos->execute();
    $total_ingresos = $stmt_ingresos->fetchColumn();

} catch (\Throwable $th) {
    echo "Error al obtener datos del dashboard: " . $th->getMessage();
    exit;
}