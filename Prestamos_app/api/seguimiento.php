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
  if ($cn->connect_errno) { http_response_code(500); echo json_encode(['ok'=>false,'msg'=>'No DB']); exit; }
  return $cn;
}

function j($arr){ echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }

$cn = db();
$action = $_POST['action'] ?? '';

function read($stmt){
  $res = $stmt->get_result();
  $out = [];
  while($r = $res->fetch_assoc()) $out[] = $r;
  return $out;
}

if ($action === 'list_loans'){
  $q = trim($_POST['q'] ?? '');
  $page = max(1, (int)($_POST['page'] ?? 1));
  $size = max(1, min(200, (int)($_POST['size'] ?? 10)));
  $off = ($page-1)*$size;

  $like = '%' . $q . '%';
  $sql = "
  SELECT p.id_prestamo, dp.nombre, dp.apellido, p.monto_solicitado, p.plazo_meses,
         coalesce(ce.estado,'') as estado_prestamo,
         DATE_FORMAT(p.fecha_solicitud, '%Y-%m-%d') as fecha_inicio,
         ROUND(COALESCE((
           SELECT ep.capacidad_pago 
           FROM evaluacion_prestamo ep 
           WHERE ep.id_prestamo = p.id_prestamo 
           ORDER BY ep.fecha_evaluacion DESC 
           LIMIT 1
         ), (
           SELECT ie.ingresos_mensuales - ie.egresos_mensuales
           FROM ingresos_egresos ie
           WHERE ie.id_cliente = p.id_cliente
           ORDER BY ie.id_ingresos_egresos DESC
           LIMIT 1
         ), 0), 2) as capacidad_pago
  FROM prestamo p
  JOIN cliente c ON c.id_cliente = p.id_cliente
  JOIN datos_persona dp ON dp.id_datos_persona = c.id_datos_persona
  LEFT JOIN cat_estado_prestamo ce ON ce.id_estado_prestamo = p.id_estado_prestamo
  WHERE CONCAT(dp.nombre,' ',dp.apellido) LIKE ?
  ORDER BY p.creado_en DESC
  LIMIT ?, ?";

  $st = $cn->prepare($sql);
  $st->bind_param('sii', $like, $off, $size);
  $st->execute();
  $rows = read($st);

  $st2 = $cn->prepare("
    SELECT COUNT(*) total
    FROM prestamo p
    JOIN cliente c ON c.id_cliente = p.id_cliente
    JOIN datos_persona dp ON dp.id_datos_persona = c.id_datos_persona
    WHERE CONCAT(dp.nombre,' ',dp.apellido) LIKE ?
  ");
  $st2->bind_param('s', $like);
  $st2->execute();
  $total = ($st2->get_result()->fetch_assoc()['total'] ?? 0);

  j(['ok'=>true, 'data'=>$rows, 'total'=>$total]);
}

if ($action === 'list_morosos'){
  $q = trim($_POST['q'] ?? '');
  $page = max(1, (int)($_POST['page'] ?? 1));
  $size = max(1, min(200, (int)($_POST['size'] ?? 10)));
  $off = ($page-1)*$size;
  $like = '%' . $q . '%';
  $sql = "
  WITH pagos AS (
    SELECT id_cronograma_cuota, SUM(monto_asignado) sum_asignado
    FROM asignacion_pago GROUP BY 1
  ), morosos AS (
    SELECT cc.id_prestamo,
           SUM(CASE WHEN cc.estado_cuota='Vencida' THEN (cc.total_monto - COALESCE(pg.sum_asignado,0)) ELSE 0 END) monto_mora,
           MIN(CASE WHEN cc.estado_cuota='Vencida' THEN cc.fecha_vencimiento END) primera_venc,
           COUNT(CASE WHEN cc.estado_cuota='Vencida' THEN 1 END) cuotas_vencidas
    FROM cronograma_cuota cc
    LEFT JOIN pagos pg ON pg.id_cronograma_cuota = cc.id_cronograma_cuota
    GROUP BY cc.id_prestamo
    HAVING SUM(CASE WHEN cc.estado_cuota='Vencida' THEN 1 ELSE 0 END) > 0
  )
  SELECT p.id_prestamo, dp.nombre, dp.apellido, p.monto_solicitado, p.plazo_meses,
         coalesce(ce.estado,'En mora') as estado_prestamo,
         DATE_FORMAT(p.fecha_solicitud, '%Y-%m-%d') as fecha_inicio,
         ROUND(m.monto_mora, 2) as monto_mora,
         CASE WHEN m.primera_venc IS NULL THEN 0 ELSE DATEDIFF(CURRENT_DATE(), m.primera_venc) END as dias_atraso,
         m.cuotas_vencidas,
         ROUND(COALESCE((
           SELECT ep.capacidad_pago 
           FROM evaluacion_prestamo ep 
           WHERE ep.id_prestamo = p.id_prestamo 
           ORDER BY ep.fecha_evaluacion DESC 
           LIMIT 1
         ), (
           SELECT ie.ingresos_mensuales - ie.egresos_mensuales
           FROM ingresos_egresos ie
           WHERE ie.id_cliente = p.id_cliente
           ORDER BY ie.id_ingresos_egresos DESC
           LIMIT 1
         ), 0), 2) as capacidad_pago
  FROM prestamo p
  JOIN cliente c ON c.id_cliente = p.id_cliente
  JOIN datos_persona dp ON dp.id_datos_persona = c.id_datos_persona
  LEFT JOIN cat_estado_prestamo ce ON ce.id_estado_prestamo = p.id_estado_prestamo
  INNER JOIN morosos m ON m.id_prestamo = p.id_prestamo
  WHERE CONCAT(dp.nombre,' ',dp.apellido) LIKE ?
  ORDER BY dias_atraso DESC, m.monto_mora DESC
  LIMIT ?, ?";

  $st = $cn->prepare($sql);
  $st->bind_param('sii', $like, $off, $size);
  $st->execute();
  $rows = read($st);

  $st2 = $cn->prepare("
    WITH pagos AS (
      SELECT id_cronograma_cuota, SUM(monto_asignado) sum_asignado
      FROM asignacion_pago GROUP BY 1
    ), morosos AS (
      SELECT cc.id_prestamo
      FROM cronograma_cuota cc
      LEFT JOIN pagos pg ON pg.id_cronograma_cuota = cc.id_cronograma_cuota
      GROUP BY cc.id_prestamo
      HAVING SUM(CASE WHEN cc.estado_cuota='Vencida' THEN 1 ELSE 0 END) > 0
    )
    SELECT COUNT(*) total
    FROM prestamo p
    JOIN cliente c ON c.id_cliente = p.id_cliente
    JOIN datos_persona dp ON dp.id_datos_persona = c.id_datos_persona
    INNER JOIN morosos m ON m.id_prestamo = p.id_prestamo
    WHERE CONCAT(dp.nombre,' ',dp.apellido) LIKE ?
  ");
  $st2->bind_param('s', $like);
  $st2->execute();
  $total = ($st2->get_result()->fetch_assoc()['total'] ?? 0);

  j(['ok'=>true, 'data'=>$rows, 'total'=>$total]);
}

if ($action === 'get_eval_data'){
  $id = (int)($_POST['id_prestamo'] ?? 0);
  if (!$id) 
    j(['ok'=>false,'msg'=>'id_prestamo requerido']);

  $st = $cn->prepare("
    SELECT p.id_prestamo, p.monto_solicitado, p.id_cliente, dp.nombre, dp.apellido
    FROM prestamo p
    JOIN cliente c ON c.id_cliente = p.id_cliente
    JOIN datos_persona dp ON dp.id_datos_persona = c.id_datos_persona
    WHERE p.id_prestamo = ?
  ");
  $st->bind_param('i', $id); 
  $st->execute();
  $row = $st->get_result()->fetch_assoc();

  $st = $cn->prepare("
    SELECT ingresos_mensuales, egresos_mensuales
    FROM ingresos_egresos
    WHERE id_cliente = ?
    ORDER BY id_ingresos_egresos DESC
    LIMIT 1
  ");
  $st->bind_param('i', $row['id_cliente']); 
  $st->execute();
  $fin = $st->get_result()->fetch_assoc() ?: ['ingresos_mensuales'=>0,'egresos_mensuales'=>0];

  $riesgos = [];
  $rs = $cn->query("SELECT id_nivel_riesgo as id, nivel 
  FROM cat_nivel_riesgo 
  ORDER BY id_nivel_riesgo ASC");
  while($r = $rs->fetch_assoc()) $riesgos[] = $r;

  j(['ok'=>true,
     'cliente'=>['nombre'=>$row['nombre'],'apellido'=>$row['apellido']],
     'prestamo'=>['monto_solicitado'=>$row['monto_solicitado'],'id_cliente'=>$row['id_cliente']],
     'finanzas'=>$fin,
     'riesgos'=>$riesgos
  ]);
}

if ($action === 'decidir_evaluacion'){
  $id = (int)($_POST['id_prestamo'] ?? 0);
  $idRiesgo = (int)($_POST['id_nivel_riesgo'] ?? 0);
  $decision = $_POST['decision'] ?? '';
  if (!$id || !$decision) 
    j(['ok'=>false,'msg'=>'Parámetros incompletos']);

  $st = $cn->prepare("SELECT p.id_cliente, p.monto_solicitado, p.id_estado_prestamo, ce.estado AS estado_nombre
                      FROM prestamo p
                      LEFT JOIN cat_estado_prestamo ce ON ce.id_estado_prestamo = p.id_estado_prestamo
                      WHERE p.id_prestamo=?");
  $st->bind_param('i', $id); 
  $st->execute();
  $p = $st->get_result()->fetch_assoc();
  if (!$p) 
    j(['ok'=>false,'msg'=>'Préstamo no existe']);

  if (strcasecmp((string)$p['estado_nombre'], 'En evaluacion') !== 0) {
    j(['ok'=>false,'msg'=>'Solo se pueden evaluar préstamos en estado "En evaluacion"']);
  }

  $st = $cn->prepare("SELECT ingresos_mensuales, egresos_mensuales 
  FROM ingresos_egresos 
  WHERE id_cliente=? 
  ORDER BY id_ingresos_egresos DESC LIMIT 1");
  $st->bind_param('i', $p['id_cliente']); 
  $st->execute();
  $fin = $st->get_result()->fetch_assoc() ?: ['ingresos_mensuales'=>0,'egresos_mensuales'=>0];
  $cap = (float)$fin['ingresos_mensuales'] - (float)$fin['egresos_mensuales'];

  $st = $cn->prepare("INSERT INTO evaluacion_prestamo (id_cliente,id_prestamo,capacidad_pago,nivel_riesgo,estado_evaluacion,fecha_evaluacion)
                      VALUES (?,?,?,?,?, CURRENT_DATE())");
  $st->bind_param('iidis', $p['id_cliente'], $id, $cap, $idRiesgo, $decision);
  $st->execute();

  $estadoNombre = ($decision === 'Aprobado') ? 'Desembolso' : 'Rechazado';
  $cn->query("UPDATE prestamo p
              JOIN (SELECT id_estado_prestamo 
              FROM cat_estado_prestamo 
              WHERE estado='".$cn->real_escape_string($estadoNombre)."' LIMIT 1) ce
              SET p.id_estado_prestamo = ce.id_estado_prestamo
              WHERE p.id_prestamo = ".(int)$id);
  j(['ok'=>true]);
}

if ($action === 'get_mora_data'){
  $id = (int)($_POST['id_prestamo'] ?? 0);
  if (!$id) 
    j(['ok'=>false,'msg'=>'id_prestamo requerido']);
  $sql = "
    WITH pagos AS (
      SELECT id_cronograma_cuota, SUM(monto_asignado) sum_asignado
      FROM asignacion_pago GROUP BY 1
    ), vencidas AS (
      SELECT cc.id_prestamo,
             SUM(CASE WHEN cc.estado_cuota='Vencida' THEN (cc.total_monto - COALESCE(pg.sum_asignado,0)) ELSE 0 END) monto_acumulado,
             MIN(CASE WHEN cc.estado_cuota='Vencida' THEN cc.fecha_vencimiento END) primera_venc
      FROM cronograma_cuota cc
      LEFT JOIN pagos pg ON pg.id_cronograma_cuota = cc.id_cronograma_cuota
      WHERE cc.id_prestamo = ?
      GROUP BY cc.id_prestamo
    )
    SELECT ce.estado,
           ROUND(COALESCE(v.monto_acumulado,0),2) monto_acumulado,
           CASE WHEN v.primera_venc IS NULL THEN 0 ELSE DATEDIFF(CURRENT_DATE(), v.primera_venc) END dias_atraso
    FROM prestamo p
    LEFT JOIN cat_estado_prestamo ce ON ce.id_estado_prestamo = p.id_estado_prestamo
    LEFT JOIN vencidas v ON v.id_prestamo = p.id_prestamo
    WHERE p.id_prestamo = ?";
  $st = $cn->prepare($sql);
  $st->bind_param('ii', $id, $id);
  $st->execute();
  $r = $st->get_result()->fetch_assoc() ?: ['estado'=>'','monto_acumulado'=>0,'dias_atraso'=>0];
  j(['ok'=>true] + $r);
}

if ($action === 'notif_bootstrap'){
  $id = (int)($_POST['id_prestamo'] ?? 0);
  $plant = [];
  $rs = $cn->query("SELECT id_plantilla_notificacion as id, tipo_notificacion as tipo, asunto, cuerpo 
  FROM plantilla_notificacion 
  ORDER BY id_plantilla_notificacion DESC");
  while($r = $rs->fetch_assoc()) $plant[] = $r;
  $cn->query("CREATE TABLE IF NOT EXISTS config_notificacion (
    id_config INT PRIMARY KEY AUTO_INCREMENT,
    dias_anticipacion INT DEFAULT 0,
    metodo VARCHAR(20) DEFAULT 'email',
    fecha_envio DATE NULL,
    escalamiento TEXT NULL
  )");
  $cfg = $cn->query("SELECT dias_anticipacion, metodo, DATE_FORMAT(fecha_envio,'%Y-%m-%d') fecha_envio, escalamiento 
  FROM config_notificacion 
  LIMIT 1")->fetch_assoc();
  if (!$cfg){ 
    $cn->query("INSERT INTO config_notificacion (dias_anticipacion,metodo,fecha_envio,escalamiento) 
    VALUES (2,'email',NULL,'[]')"); 
    $cfg = ['dias_anticipacion'=>2,'metodo'=>'email','fecha_envio'=>NULL,'escalamiento'=>'[]']; 
  }
  $para = '';
  if ($id) {
    $st = $cn->prepare("SELECT em.email
                        FROM prestamo p
                        JOIN cliente c ON c.id_cliente = p.id_cliente
                        JOIN datos_persona dp ON dp.id_datos_persona = c.id_datos_persona
                        LEFT JOIN email em ON em.id_datos_persona = dp.id_datos_persona AND em.es_principal = 1
                        WHERE p.id_prestamo = ?");
    $st->bind_param('i', $id);
    $st->execute();
    $em = $st->get_result()->fetch_assoc();
    if ($em && !empty($em['email'])) $para = $em['email'];
  }

  j(['ok'=>true, 'plantillas'=>$plant, 'config'=>$cfg, 'para'=>$para]);
}

if ($action === 'plantilla_preview'){
  $idP = (int)($_POST['id_plantilla'] ?? 0);
  $idLoan = (int)($_POST['id_prestamo'] ?? 0);
  $r = $cn->query("SELECT asunto, cuerpo 
  FROM plantilla_notificacion 
  WHERE id_plantilla_notificacion=".(int)$idP)->fetch_assoc();
  if (!$r) 
    j(['ok'=>false,'msg'=>'Plantilla no existe']);

  $data = ['cliente'=>'','monto'=>'','id'=>'#'];
  if ($idLoan){
    $st = $cn->prepare("SELECT p.id_prestamo, p.monto_solicitado, dp.nombre, dp.apellido
                        FROM prestamo p
                        JOIN cliente c ON c.id_cliente=p.id_cliente
                        JOIN datos_persona dp ON dp.id_datos_persona=c.id_datos_persona
                        WHERE p.id_prestamo=?");
    $st->bind_param('i', $idLoan); $st->execute();
    $x = $st->get_result()->fetch_assoc();
    if ($x){ 
      $data['cliente'] = $x['nombre'].' '.$x['apellido']; 
      $data['monto'] = number_format($x['monto_solicitado'],2); 
      $data['id'] = $x['id_prestamo']; 
    }
  }
  $preview = str_replace(['{{cliente}}','{{monto}}','{{id_prestamo}}'], 
  [$data['cliente'],$data['monto'],$data['id']], $r['cuerpo']);
  j(['ok'=>true, 'asunto'=>$r['asunto'], 'preview'=>$preview]);
}

if ($action === 'notif_save_config'){
  $dias = (int)($_POST['dias_anticipacion'] ?? 0);
  $met  = $_POST['metodo'] ?? 'email';
  $fec  = $_POST['fecha_envio'] ?? null;
  $esc  = $_POST['escalamiento'] ?? '[]';

  $cn->query("CREATE TABLE IF NOT EXISTS config_notificacion (
    id_config INT PRIMARY KEY AUTO_INCREMENT,
    dias_anticipacion INT DEFAULT 0,
    metodo VARCHAR(20) DEFAULT 'email',
    fecha_envio DATE NULL,
    escalamiento TEXT NULL
  )");

  $exists = $cn->query("SELECT id_config FROM config_notificacion LIMIT 1")->fetch_assoc();
  if ($exists){
    $st = $cn->prepare("UPDATE config_notificacion SET dias_anticipacion=?, metodo=?, fecha_envio=?, escalamiento=? WHERE id_config=?");
    $idc = $exists['id_config'];
    $st->bind_param('isssi', $dias, $met, $fec, $esc, $idc);
  } else {
    $st = $cn->prepare("INSERT INTO config_notificacion (dias_anticipacion,metodo,fecha_envio,escalamiento) VALUES (?,?,?,?)");
    $st->bind_param('isss', $dias, $met, $fec, $esc);
  }
  $st->execute();
  j(['ok'=>true]);
}

if ($action === 'notif_send'){
  j(['ok'=>true, 'msg'=>'Notificación enviada (simulación)']);
}

if ($action === 'check_datacredito'){
  $id = (int)($_POST['id_prestamo'] ?? 0);
  if (!$id) j(['ok'=>false,'msg'=>'id_prestamo requerido']);
  $st = $cn->prepare("
    SELECT di.numero_documento as cedula, dp.nombre, dp.apellido, p.monto_solicitado
    FROM prestamo p
    JOIN cliente c ON c.id_cliente = p.id_cliente
    JOIN datos_persona dp ON dp.id_datos_persona = c.id_datos_persona
    LEFT JOIN documento_identidad di ON di.id_datos_persona = dp.id_datos_persona 
      AND di.id_tipo_documento = (SELECT id_tipo_documento FROM cat_tipo_documento WHERE tipo_documento = 'Cedula' LIMIT 1)
    WHERE p.id_prestamo = ?
  ");
  $st->bind_param('i', $id);
  $st->execute();
  $cliente = $st->get_result()->fetch_assoc();
  if (!$cliente) 
    j(['ok'=>false,'msg'=>'Préstamo no encontrado']);
  if (!$cliente['cedula']) 
    j(['ok'=>false,'msg'=>'Cliente no tiene cédula registrada']);
   
  $baseUrl = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
  $baseUrl .= $_SERVER['HTTP_HOST'];
  $currentPath = dirname($_SERVER['REQUEST_URI']);

  if (strpos($currentPath, '/api') !== false) {
    $baseUrl .= $currentPath . '/fake_datacredito.php';
  } else {
    $baseUrl .= $currentPath . '/api/fake_datacredito.php';
  }
  $datacreditoUrl = $baseUrl . '?cedula=' . urlencode($cliente['cedula']);
  $context = stream_context_create([
    'http' => [
      'method' => 'GET',
      'timeout' => 10,
      'ignore_errors' => true,
      'header' => "User-Agent: PrestamosApp/1.0\r\n"
    ]
  ]);
  $response = @file_get_contents($datacreditoUrl, false, $context);
  if ($response === false) {
    $localUrl = 'http://localhost' . str_replace($_SERVER['HTTP_HOST'], '', $baseUrl) . '?cedula=' . urlencode($cliente['cedula']);
    $response = @file_get_contents($localUrl, false, $context);
    
    if ($response === false) {
      j(['ok'=>false,'msg'=>'Error al conectar con el servicio de Datacrédito. Verifique que el servicio esté disponible.']);
    }
  }
  $datacreditoData = json_decode($response, true);
  if (!$datacreditoData || !$datacreditoData['ok']) {
    j(['ok'=>false,'msg'=>'Error en la respuesta del servicio de Datacrédito']);
  }
  $score = $datacreditoData['data']['score'];
  $resumen = $datacreditoData['data']['resumen_crediticio'];
  $resultado = "=== REPORTE DATACRÉDITO ===\n";
  $resultado .= "Cliente: {$cliente['nombre']} {$cliente['apellido']}\n";
  $resultado .= "Cédula: {$cliente['cedula']}\n\n";
  $resultado .= "SCORE CREDITICIO:\n";
  $resultado .= "Puntuación: {$score['valor']}/900\n";
  $resultado .= "Nivel: {$score['nivel']} - {$score['riesgo']}\n\n";
  $resultado .= "RESUMEN CREDITICIO:\n";
  $resultado .= "Préstamos activos: {$resumen['prestamos_activos']}\n";
  $resultado .= "Tarjetas de crédito: {$resumen['tarjetas_credito']}\n";
  $resultado .= "Líneas de crédito: {$resumen['lineas_credito']}\n";
  $resultado .= "Consultas recientes: {$resumen['consultas_recientes']}\n";
  $resultado .= "Atraso máximo: {$resumen['atraso_maximo']} días\n\n";
  
  if (!empty($datacreditoData['data']['alertas'])) {
    $resultado .= "ALERTAS:\n";
    foreach ($datacreditoData['data']['alertas'] as $alerta) {
      $resultado .= "• $alerta\n";
    }
    $resultado .= "\n";
  }
  $recomendacion = "";
  switch ($score['nivel']) {
    case 'A':
      $recomendacion = "APROBADO - Excelente historial crediticio";
      break;
    case 'B':
      $recomendacion = "CONDICIONAL - Buen historial con condiciones estándar";
      break;
    case 'C':
      $recomendacion = "REVISAR - Requiere análisis adicional y posibles condiciones especiales";
      break;
    case 'D':
      $recomendacion = "RECHAZAR - Alto riesgo crediticio";
      break;
  }
  $datacreditoData['data']['resumen_crediticio']['recomendacion'] = $recomendacion;
  $datacreditoData['data']['cliente'] = [
    'nombre'  => $cliente['nombre'] ?? '',
    'apellido'=> $cliente['apellido'] ?? '',
    'cedula' => $cliente['cedula'] ?? ''
  ];
  $resultado .= $recomendacion;
  $resultado .= "\n\nFuente: {$datacreditoData['data']['fuente']}";
  
  j(['ok'=>true, 'resultado'=>$resultado, 'data_completa'=>$datacreditoData['data']]);
}

j(['ok'=>false,'msg'=>'Acción no válida']);
