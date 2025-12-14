<?php

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

function db(){
    if (isset($GLOBALS['mysqli']) && $GLOBALS['mysqli'] instanceof mysqli) return $GLOBALS['mysqli'];
    if (isset($GLOBALS['conn'])   && $GLOBALS['conn']   instanceof mysqli) return $GLOBALS['conn'];

    if (function_exists('getConnection')) return getConnection();
    if (function_exists('db_connect'))    return db_connect();

    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '';
    $name = getenv('DB_NAME') ?: 'prestamos_db';
    $cn = @new mysqli($host, $user, $pass, $name);
    if ($cn->connect_errno) {
        http_response_code(500);
        echo json_encode(['ok'=>false,'msg'=>'No se pudo conectar a la base de datos']);
        exit;
    }
    $cn->set_charset('utf8mb4');
    return $cn;
}

function j($arr){
    echo json_encode($arr, JSON_UNESCAPED_UNICODE);
    exit;
}

$cn     = db();
$action = $_POST['action'] ?? ($_GET['action'] ?? 'run');

function getNotifConfig(mysqli $cn){
    $cn->query("CREATE TABLE IF NOT EXISTS config_notificacion (
        id_config INT PRIMARY KEY AUTO_INCREMENT,
        dias_anticipacion INT DEFAULT 0,
        metodo VARCHAR(20) DEFAULT 'email',
        fecha_envio DATE NULL,
        escalamiento TEXT NULL
    )");

    $cfg = $cn->query("SELECT dias_anticipacion, metodo FROM config_notificacion LIMIT 1")->fetch_assoc();
    if (!$cfg){
        $cn->query("INSERT INTO config_notificacion (dias_anticipacion,metodo,fecha_envio,escalamiento) VALUES (2,'email',NULL,'[]')");
        $cfg = ['dias_anticipacion'=>2,'metodo'=>'email'];
    }
    return $cfg;
}

function getMoraConfig(mysqli $cn){
    $res = $cn->query("SELECT dias_gracia, porcentaje_mora 
                       FROM config_mora 
                       WHERE estado = 'Activo'
                       ORDER BY vigente_desde DESC 
                       LIMIT 1");
    return $res ? $res->fetch_assoc() : null;
}

function ensureTemplates(mysqli $cn){
    try {
        $cn->query("CREATE UNIQUE INDEX IF NOT EXISTS ux_plantilla_tipo_asunto ON plantilla_notificacion (tipo_notificacion, asunto)");
    } catch (Throwable $e) {
    }

    $out = [
        'PRE_MORA'      => null,
        'MORA_APLICADA' => null,
    ];

    $rs = $cn->query("SELECT id_plantilla_notificacion, tipo_notificacion, asunto 
                      FROM plantilla_notificacion 
                      WHERE tipo_notificacion IN ('PRE_MORA','MORA_APLICADA')");
    while($r = $rs->fetch_assoc()){
        $tipo = $r['tipo_notificacion'];
        if (isset($out[$tipo]) && !$out[$tipo]) {
            $out[$tipo] = (int)$r['id_plantilla_notificacion'];
        }
    }

    $preAsunto = "Aviso: su periodo de gracia está próximo a finalizar";
    $preCuerpo = "Estimado(a) {{cliente}}, le informamos que su periodo de gracia de {{dias_gracia}} días para el préstamo {{id_prestamo}} está próximo a finalizar el {{fecha_limite}}.\n\n".
                "Por favor, realice el pago correspondiente antes de la fecha límite para evitar la aplicación de cargos por mora.\n".
                "Si tiene alguna duda, no dude en contactarnos.";

    $st = $cn->prepare("SELECT id_plantilla_notificacion FROM plantilla_notificacion WHERE tipo_notificacion = 'PRE_MORA' AND asunto = ? LIMIT 1");
    $st->bind_param('s', $preAsunto);
    $st->execute();
    $pre = $st->get_result()->fetch_assoc();
    $st->close();
    if ($pre){
        $out['PRE_MORA'] = (int)$pre['id_plantilla_notificacion'];
        $st = $cn->prepare("UPDATE plantilla_notificacion SET cuerpo = ? WHERE id_plantilla_notificacion = ?");
        $st->bind_param('si', $preCuerpo, $out['PRE_MORA']);
        $st->execute();
        $st->close();
    } else if (!$out['PRE_MORA']) {
        $st = $cn->prepare("INSERT INTO plantilla_notificacion (tipo_notificacion, asunto, cuerpo) VALUES ('PRE_MORA', ?, ?)");
        $st->bind_param('ss', $preAsunto, $preCuerpo);
        $st->execute();
        $out['PRE_MORA'] = $st->insert_id;
        $st->close();
    }

    $moraAsunto = "Notificación: se ha aplicado un cargo por mora a su cuenta";
    $moraCuerpo = "Estimado(a) {{cliente}}, le notificamos que el periodo de gracia para el préstamo {{id_prestamo}} ha finalizado y se ha aplicado un cargo por mora a su cuenta.\n\n".
                 "Le invitamos a regularizar su situación lo antes posible para evitar mayores inconvenientes. " .
                 "Para más información, comuníquese con nuestro equipo de atención al cliente.";

    $st = $cn->prepare("SELECT id_plantilla_notificacion FROM plantilla_notificacion WHERE tipo_notificacion = 'MORA_APLICADA' AND asunto = ? LIMIT 1");
    $st->bind_param('s', $moraAsunto);
    $st->execute();
    $mora = $st->get_result()->fetch_assoc();
    $st->close();
    if ($mora){
        $out['MORA_APLICADA'] = (int)$mora['id_plantilla_notificacion'];
        $st = $cn->prepare("UPDATE plantilla_notificacion SET cuerpo = ? WHERE id_plantilla_notificacion = ?");
        $st->bind_param('si', $moraCuerpo, $out['MORA_APLICADA']);
        $st->execute();
        $st->close();
    } else if (!$out['MORA_APLICADA']) {
        $st = $cn->prepare("INSERT INTO plantilla_notificacion (tipo_notificacion, asunto, cuerpo) VALUES ('MORA_APLICADA', ?, ?)");
        $st->bind_param('ss', $moraAsunto, $moraCuerpo);
        $st->execute();
        $out['MORA_APLICADA'] = $st->insert_id;
        $st->close();
    }

    return $out;
}

function renderTemplateText($texto, array $row, array $extra = []){
    $cliente  = trim(($row['nombre'] ?? '') . ' ' . ($row['apellido'] ?? ''));
    $monto    = isset($row['monto_prestamo']) ? number_format((float)$row['monto_prestamo'], 2) : '';
    $idPrest  = $row['id_prestamo'] ?? '';
    $diasG    = $extra['dias_gracia']   ?? ($row['dias_gracia']   ?? '');
    $fecLim   = $extra['fecha_limite']  ?? ($row['fecha_limite']  ?? '');
    $diasRest = $extra['dias_restantes'] ?? ($row['dias_restantes'] ?? '');

    $repls = [
        '{{cliente}}'      => $cliente,
        '{{monto}}'        => $monto,
        '{{id_prestamo}}'  => $idPrest,
        '{{dias_gracia}}'  => $diasG,
        '{{fecha_limite}}' => $fecLim,
        '{{dias_restantes}}'=> $diasRest,
    ];
    return strtr($texto, $repls);
}

function getNotifyUrl(){
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path   = dirname($_SERVER['SCRIPT_NAME']); 
    $path   = rtrim(str_replace('\\','/',$path), '/');
    return $scheme . '://' . $host . $path . '/enviarnotificacion.php';
}

function enviarCorreo($para, $asunto, $mensaje){
    $url = getNotifyUrl();

    $postData = http_build_query([
        'correo'  => $para,
        'asunto'  => $asunto,
        'mensaje' => $mensaje
    ]);

    $opts = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n"
                       . "Content-Length: " . strlen($postData) . "\r\n",
            'content' => $postData,
            'timeout' => 30
        ]
    ];

    $context = stream_context_create($opts);
    $resp    = @file_get_contents($url, false, $context);

    if ($resp === false){
        return ['success'=>false, 'message'=>'No se pudo contactar el servicio de envío'];
    }

    $json = json_decode($resp, true);
    if (!is_array($json)){
        return ['success'=>false, 'message'=>'Respuesta inválida del servicio de envío'];
    }

    $ok  = !empty($json['success']);
    $msg = $json['message'] ?? ($ok ? 'Enviado' : 'Error desconocido');
    return ['success'=>$ok, 'message'=>$msg];
}

function yaEnviadoHoy(mysqli $cn, $idCliente, $idPlantilla){
    $st = $cn->prepare("
        SELECT 1 
        FROM notificaciones 
        WHERE id_cliente = ? 
          AND id_plantilla_notificacion = ?
          AND DATE(programada_para) = CURDATE()
          AND estado_envio = 'Enviado'
        LIMIT 1
    ");
    $st->bind_param('ii', $idCliente, $idPlantilla);
    $st->execute();
    $res = $st->get_result()->fetch_assoc();
    $st->close();
    return (bool)$res;
}

function registrarNotificacion(mysqli $cn, $idCliente, $idPlantilla, $estado){
    $canal = 'email';
    $st = $cn->prepare("INSERT INTO notificaciones (id_cliente, id_plantilla_notificacion, canal_envio, programada_para, estado_envio) VALUES (?,?,?,NOW(),?)");
    $st->bind_param('iiss', $idCliente, $idPlantilla, $canal, $estado);
    $st->execute();
    $st->close();
}

if ($action !== 'run'){
    j(['ok'=>false,'msg'=>'Acción no soportada en notificaciones_auto.php']);
}

// Logica principal

$cfgNotif = getNotifConfig($cn);
$moraCfg  = getMoraConfig($cn);

if (!$moraCfg){
    j(['ok'=>false,'msg'=>'No hay configuración de mora activa (config_mora.estado=Activo)']);
}

$diasAnt   = (int)$cfgNotif['dias_anticipacion'];
$metodo    = $cfgNotif['metodo'] ?? 'email';
$diasGracia= (int)$moraCfg['dias_gracia'];

// Por ahora solo soportamos método email
if ($metodo !== 'email'){
    j(['ok'=>false,'msg'=>'Método de notificación configurado no soportado aún: '.$metodo]);
}

// Aseguramos plantillas
$tplIds = ensureTemplates($cn);

$preMoraEnviadas = 0;
$moraEnviadas    = 0;

if ($diasAnt > 0){
    $sqlPre = "
        SELECT
            c.id_cliente,
            p.id_prestamo,
            dp.nombre,
            dp.apellido,
            em.email,
            p.monto_solicitado AS monto_prestamo,
            DATE_FORMAT(
                MIN(DATE_ADD(cc.fecha_vencimiento, INTERVAL ? DAY)),
                '%Y-%m-%d'
            ) AS fecha_limite,
            MIN(
                DATEDIFF(
                    DATE_ADD(cc.fecha_vencimiento, INTERVAL ? DAY),
                    CURDATE()
                )
            ) AS dias_restantes
        FROM cronograma_cuota cc
        JOIN prestamo p        ON p.id_prestamo = cc.id_prestamo
        JOIN cliente c         ON c.id_cliente  = p.id_cliente
        JOIN datos_persona dp  ON dp.id_datos_persona = c.id_datos_persona
        LEFT JOIN email em     ON em.id_datos_persona = dp.id_datos_persona AND em.es_principal = 1
        WHERE cc.estado_cuota = 'Pendiente'
          AND em.email IS NOT NULL
          AND DATEDIFF(
                DATE_ADD(cc.fecha_vencimiento, INTERVAL ? DAY),
                CURDATE()
              ) BETWEEN 0 AND ?
        GROUP BY c.id_cliente, p.id_prestamo, dp.nombre, dp.apellido, em.email, p.monto_solicitado
    ";

    $st = $cn->prepare($sqlPre);
    $st->bind_param('iiii', $diasGracia, $diasGracia, $diasGracia, $diasAnt);
    $st->execute();
    $res = $st->get_result();

    // Obtenemos la plantilla PRE_MORA
    $tplId  = (int)$tplIds['PRE_MORA'];
    $tplRow = $cn->query("SELECT asunto, cuerpo FROM plantilla_notificacion WHERE id_plantilla_notificacion = ".$tplId)->fetch_assoc();

    while($row = $res->fetch_assoc()){
        $idCliente = (int)$row['id_cliente'];
        $para      = $row['email'];

        if (!$para) continue;

        // Evitar duplicado diario por cliente/plantilla
        if (yaEnviadoHoy($cn, $idCliente, $tplId)) continue;

        $extra = [
            'dias_gracia'    => $diasGracia,
            'fecha_limite'   => $row['fecha_limite'],
            'dias_restantes' => $row['dias_restantes'],
        ];

        $asunto  = renderTemplateText($tplRow['asunto'], $row, $extra);
        $mensaje = renderTemplateText($tplRow['cuerpo'], $row, $extra);

        $envio = enviarCorreo($para, $asunto, $mensaje);
        $estadoEnvio = $envio['success'] ? 'Enviado' : 'Fallido';
        registrarNotificacion($cn, $idCliente, $tplId, $estadoEnvio);

        if ($envio['success']) $preMoraEnviadas++;
    }

    $st->close();
}

$sqlMora = "
    SELECT
        c.id_cliente,
        p.id_prestamo,
        dp.nombre,
        dp.apellido,
        em.email,
        p.monto_solicitado AS monto_prestamo,
        DATE_FORMAT(
            MIN(DATE_ADD(cc.fecha_vencimiento, INTERVAL ? DAY)),
            '%Y-%m-%d'
        ) AS fecha_limite
    FROM cronograma_cuota cc
    JOIN prestamo p        ON p.id_prestamo = cc.id_prestamo
    JOIN cliente c         ON c.id_cliente  = p.id_cliente
    JOIN datos_persona dp  ON dp.id_datos_persona = c.id_datos_persona
    LEFT JOIN email em     ON em.id_datos_persona = dp.id_datos_persona AND em.es_principal = 1
    WHERE cc.estado_cuota = 'Vencida'
      AND em.email IS NOT NULL
      AND DATEDIFF(
            CURDATE(),
            DATE_ADD(cc.fecha_vencimiento, INTERVAL ? DAY)
          ) >= 1
    GROUP BY c.id_cliente, p.id_prestamo, dp.nombre, dp.apellido, em.email, p.monto_solicitado
";

$st2 = $cn->prepare($sqlMora);
$st2->bind_param('ii', $diasGracia, $diasGracia);
$st2->execute();
$res2 = $st2->get_result();

$tplIdMora  = (int)$tplIds['MORA_APLICADA'];
$tplRowMora = $cn->query("SELECT asunto, cuerpo FROM plantilla_notificacion WHERE id_plantilla_notificacion = ".$tplIdMora)->fetch_assoc();

while($row = $res2->fetch_assoc()){
    $idCliente = (int)$row['id_cliente'];
    $para      = $row['email'];

    if (!$para) continue;

    // Evitar duplicado diario por cliente/plantilla
    if (yaEnviadoHoy($cn, $idCliente, $tplIdMora)) continue;

    $extra = [
        'dias_gracia'   => $diasGracia,
        'fecha_limite'  => $row['fecha_limite'],
        'dias_restantes'=> 0,
    ];

    $asunto  = renderTemplateText($tplRowMora['asunto'], $row, $extra);
    $mensaje = renderTemplateText($tplRowMora['cuerpo'], $row, $extra);

    $envio = enviarCorreo($para, $asunto, $mensaje);
    $estadoEnvio = $envio['success'] ? 'Enviado' : 'Fallido';
    registrarNotificacion($cn, $idCliente, $tplIdMora, $estadoEnvio);

    if ($envio['success']) $moraEnviadas++;
}

$st2->close();

j([
    'ok' => true,
    'msg'=> 'Proceso de notificaciones automáticas ejecutado correctamente',
    'resumen' => [
        'pre_mora_enviadas'  => $preMoraEnviadas,
        'mora_enviadas'      => $moraEnviadas,
        'dias_gracia'        => $diasGracia,
        'dias_anticipacion'  => $diasAnt,
        'metodo'             => $metodo
    ]
]);