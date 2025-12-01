<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

header('Content-Type: application/json; charset=utf-8');

// Carga de PHPMailer (ruta relativa correcta desde /api)
require_once __DIR__ . '/../libs/PHPMailer/Exception.php';
require_once __DIR__ . '/../libs/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../libs/PHPMailer/SMTP.php';

// Datos recibidos del formulario
$correo = isset($_POST['correo']) ? trim($_POST['correo']) : '';
$mensaje = isset($_POST['mensaje']) ? (string)$_POST['mensaje'] : '';
$asunto = isset($_POST['asunto']) ? trim($_POST['asunto']) : '';

if ($correo === '' || $asunto === '' || $mensaje === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parámetros incompletos: correo, asunto y mensaje son requeridos.']);
    exit;
}

$mail = new PHPMailer(true);
try {
    // Configuración del servidor SMTP (dejado hardcodeado a petición)
    $mail->isSMTP();
    $mail->Host = 'smtp-relay.brevo.com';
    $mail->SMTPAuth = true;
    $mail->Username = '9bcc3d001@smtp-brevo.com';
    $mail->Password = 'LIJEO0Xghb78z5Ad';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    // Remitente
    $mail->setFrom('alexperez2517@gmail.com', 'Sistema de Préstamos');

    // Destinatario
    $mail->addAddress($correo);

    // Contenido
    $mail->isHTML(true);
    $mail->Subject = $asunto;
    $mail->Body = $mensaje;

    $mail->send();
    echo json_encode(['success' => true, 'message' => 'Correo enviado correctamente']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $mail->ErrorInfo ?: $e->getMessage()]);
}
