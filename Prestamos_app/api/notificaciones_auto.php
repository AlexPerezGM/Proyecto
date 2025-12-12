<?php
/**
 * API: notificaciones_auto.php
 *
 * Objetivo:
 *   - Enviar notificaciones automáticas a clientes que:
 *       a) Están próximos a entrar en mora (PRE_MORA)
 *       b) Ya tienen mora aplicada (MORA_APLICADA)
 *
 *   - Basado en:
 *       * config_mora (dias_gracia)
 *       * cronograma_cuota (estado_cuota, fecha_vencimiento)
 *       * config_notificacion (dias_anticipacion, metodo='email')
 *       * plantilla_notificacion (tipo_notificacion, asunto, cuerpo)
 *       * notificaciones (histórico de envíos)
 *
 * Uso típico (cron / tarea programada):
 *   POST o GET a: /prestamos_app/api/notificaciones_auto.php?action=run
 */

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

/**
 * Asegura que exista al menos una fila en config_notificacion.
 * Devuelve: ['dias_anticipacion'=>int, 'metodo'=>string]
 */
function getNotifConfig(mysqli $cn){
    // Por si no existe la tabla (por compatibilidad con seguimiento.php)
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

/**
 * Lee la configuración de mora activa.
 * Devuelve: ['dias_gracia'=>int, 'porcentaje_mora'=>float] o null si no hay ninguna activa.
 */
function getMoraConfig(mysqli $cn){
    $res = $cn->query("SELECT dias_gracia, porcentaje_mora 
                       FROM config_mora 
                       WHERE estado = 'Activo'
                       ORDER BY vigente_desde DESC 
                       LIMIT 1");
    return $res ? $res->fetch_assoc() : null;
}

/**
 * Asegura que existan las plantillas PRE_MORA y MORA_APLICADA.
 * Devuelve: ['PRE_MORA'=>id_plantilla, 'MORA_APLICADA'=>id_plantilla]
 */
function ensureTemplates(mysqli $cn){
    $out = [
        'PRE_MORA'      => null,
        'MORA_APLICADA' => null,
    ];

    $rs = $cn->query("SELECT id_plantilla_notificacion, tipo_notificacion 
                      FROM plantilla_notificacion 
                      WHERE tipo_notificacion IN ('PRE_MORA','MORA_APLICADA')");
    while($r = $rs->fetch_assoc()){
        $tipo = $r['tipo_notificacion'];
        if (isset($out[$tipo])) {
            $out[$tipo] = (int)$r['id_plantilla_notificacion'];
        }
    }

    // Plantilla PRE_MORA
    if (!$out['PRE_MORA']){
        $asunto = "Aviso: su periodo de gracia está próximo a finalizar";
        $cuerpo = "Estimado(a) {{cliente}}, le informamos que su periodo de gracia de {{dias_gracia}} días para el préstamo {{id_prestamo}} está próximo a finalizar el {{fecha_limite}}.\n\n".
                  "Por favor, realice el pago correspondiente antes de la fecha límite para evitar la aplicación de cargos por mora.\n".
                  "Si tiene alguna duda, no dude en contactarnos.";
        $st = $cn->prepare("INSERT INTO plantilla_notificacion (tipo_notificacion, asunto, cuerpo) VALUES ('PRE_MORA', ?, ?)");
        $st->bind_param('ss', $asunto, $cuerpo);
        $st->execute();
        $out['PRE_MORA'] = $st->insert_id;
        $st->close();
    }

    // Plantilla MORA_APLICADA
    if (!$out['MORA_APLICADA']){
        $asunto = "Notificación: se ha aplicado un cargo por mora a su cuenta";
        $cuerpo = "Estimado(a) {{cliente}}, le notificamos que el periodo de gracia para el préstamo {{id_prestamo}} ha finalizado y se ha aplicado un cargo por mora a su cuenta.\n\n".
                  "Le invitamos a regularizar su situación lo antes posible para evitar mayores inconvenientes. ".
                  "Para más información, comuníquese con nuestro equipo de atención al cliente.";
        $st = $cn->prepare("INSERT INTO plantilla_notificacion (tipo_notificacion, asunto, cuerpo) VALUES ('MORA_APLICADA', ?, ?)");
        $st->bind_param('ss', $asunto, $cuerpo);
        $st->execute();
        $out['MORA_APLICADA'] = $st->insert_id;
        $st->close();
    }

    return $out;
}

/**
 * Renderiza asunto/cuerpo reemplazando placeholders.
 * $row debe tener: nombre, apellido, id_prestamo, monto_prestamo, fecha_limite, dias_restantes, dias_gracia (según aplique).
 */
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

/**
 * Construye la URL absoluta a api/enviarnotificacion.php
 */
function getNotifyUrl(){
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path   = dirname($_SERVER['SCRIPT_NAME']); // /prestamos_app/api
    $path   = rtrim(str_replace('\\','/',$path), '/');
    return $scheme . '://' . $host . $path . '/enviarnotificacion.php';
}

/**
 * Envía correo usando api/enviarnotificacion.php
 * Devuelve: ['success'=>bool, 'message'=>string]
 */
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

/**
 * Verifica si ya se envió hoy una notificación de esa plantilla a ese cliente.
 */
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

/**
 * Inserta registro en notificaciones.
 */
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

// ===============================
//      LÓGICA PRINCIPAL
// ===============================

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

// ===============================
//  1) PRÓXIMO A ENTRAR EN MORA
// ===============================

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

// ===============================
//  2) MORA YA APLICADA (CUOTAS VENCIDAS)
// ===============================
//
// El EVENT de mora marca las cuotas como 'Vencida' cuando:
//
//   DATE_ADD(fecha_vencimiento, INTERVAL dias_gracia DAY) < CURDATE()
//
// Es decir, el día que pasan a 'Vencida' se cumple:
//   DATEDIFF(CURDATE(), DATE_ADD(fecha_vencimiento, INTERVAL dias_gracia DAY)) = 1
//
// Usamos eso para enviar la notificación de MORA_APLICADA sólo una vez.

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
          ) = 1
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
