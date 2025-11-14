<?php
// config/db.php
$DB_HOST = "localhost";
$DB_USER = "root";
$DB_PASS = ""; // Wamp normalmente no tiene password por default
$DB_NAME = "p_test2"; // Asegúrate que importaste prestamos_db.sql con este nombre exacto

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if ($conn->connect_error) {
    die("Error de conexión a la base de datos: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
?>