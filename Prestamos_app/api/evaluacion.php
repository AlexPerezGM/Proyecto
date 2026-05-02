<?php
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/autorizacion.php';
requiere_login();

$id_prestamo = (int)($_GET['id_prestamo'] ?? 0);
if ($id_prestamo <= 0) {
    header('Location: ../views/prestamos.php');
    exit;
}

header('Location: ../views/Evaluacion_v.php?id_prestamo=' . $id_prestamo);
exit;
