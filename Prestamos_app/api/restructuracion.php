<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php'; 

function j($ok, $data=[], $extra=[]) {
  echo json_encode(array_merge(['ok'=>$ok], $extra, $data ? ['data'=>$data] : []));
  exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'list';
if ($action === 'list') {
  $q    = trim($_POST['q'] ?? $_GET['q'] ?? '');
  $page = max(1, (int)($_POST['page'] ?? $_GET['page'] ?? 1));
  $size = max(1, min(50, (int)($_POST['size'] ?? $_GET['size'] ?? 10)));
  $off  = ($page - 1) * $size;

  $where = "p.id_estado_prestamo IN (1,5)";
  $params = [];
  $types = '';
  if ($q !== '') {
    $where .= " AND (dp.nombre LIKE ? OR dp.apellido LIKE ? OR di.numero_documento LIKE ?)";
    $like = "%$q%"; $params = [$like,$like,$like]; $types = 'sss';
  }

  $sqlCount = "
    SELECT COUNT(*) AS c
    FROM prestamo p
    JOIN cliente c ON c.id_cliente = p.id_cliente
    JOIN datos_persona dp ON dp.id_datos_persona = c.id_datos_persona
    LEFT JOIN documento_identidad di ON di.id_datos_persona = dp.id_datos_persona
    WHERE $where
  ";
  if ($q !== '') {
    $stmt = $conn->prepare($sqlCount);
    $stmt->bind_param($types, ...$params);
    $stmt->execute(); $res = $stmt->get_result()->fetch_assoc(); $total = (int)$res['c'];
    $stmt->close();
  } else {
    $res = $conn->query($sqlCount);
    $row = $res ? $res->fetch_assoc() : ['c'=>0];
    $total = (int)$row['c'];
  }

  $sql = "
    SELECT
      p.id_prestamo, p.monto_solicitado, p.plazo_meses, p.id_estado_prestamo,
      dp.nombre, dp.apellido,
      COALESCE(di.numero_documento,'') AS numero_documento
    FROM prestamo p
    JOIN cliente c ON c.id_cliente = p.id_cliente
    JOIN datos_persona dp ON dp.id_datos_persona = c.id_datos_persona
    LEFT JOIN documento_identidad di ON di.id_datos_persona = dp.id_datos_persona
    WHERE $where
    ORDER BY p.id_prestamo DESC
    LIMIT $off, $size
  ";
  if ($q !== '') {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute(); $rs = $stmt->get_result();
  } else {
    $rs = $conn->query($sql);
  }
  $data = [];
  if ($rs) {
    while ($r = $rs->fetch_assoc()){
      $data[] = [
        'id_prestamo' => (int)$r['id_prestamo'],
        'cliente'     => $r['nombre'].' '.$r['apellido'],
        'documento'   => $r['numero_documento'],
        'monto'       => (float)$r['monto_solicitado'],
        'plazo'       => (int)$r['plazo_meses'],
        'estado'      => (int)$r['id_estado_prestamo']
      ];
    }
  }
  j(true, $data, ['total'=>$total]);
}
if ($action === 'get') {
  $id_prestamo = (int)($_POST['id_prestamo'] ?? $_GET['id_prestamo'] ?? 0);
  if (!$id_prestamo) j(false, [], ['msg'=>'id_prestamo requerido']);
  
  $sql = "
    SELECT
      p.id_prestamo, p.monto_solicitado, p.plazo_meses, p.id_condicion_actual,
      dp.nombre, dp.apellido, COALESCE(di.numero_documento,'') AS numero_documento
    FROM prestamo p
    JOIN cliente c ON c.id_cliente = p.id_cliente
    JOIN datos_persona dp ON dp.id_datos_persona = c.id_datos_persona
    LEFT JOIN documento_identidad di ON di.id_datos_persona = dp.id_datos_persona
    WHERE p.id_prestamo = ?
  ";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('i', $id_prestamo);
  $stmt->execute(); $p = $stmt->get_result()->fetch_assoc(); $stmt->close();
  if (!$p) j(false, [], ['msg'=>'Préstamo no encontrado']);
  $tasa = null; $periodo = null; $tipo_amort = null; $tipo_amort_id = null;
  if (!empty($p['id_condicion_actual'])) {
    $sql = "
      SELECT cp.tasa_interes, cp.id_periodo_pago, cp.id_tipo_amortizacion, cta.tipo_amortizacion
      FROM condicion_prestamo cp
      JOIN cat_tipo_amortizacion cta ON cta.id_tipo_amortizacion = cp.id_tipo_amortizacion
      WHERE cp.id_condicion_prestamo=?
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $p['id_condicion_actual']);
    $stmt->execute(); $c = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if ($c){
      $tasa = (float)$c['tasa_interes'];
      $periodo = (int)$c['id_periodo_pago'];
      $tipo_amort_id = (int)$c['id_tipo_amortizacion'];
      $tipo_amort = $c['tipo_amortizacion'];
    }
  }
  if ($tasa===null) {
    $sql = "
      SELECT tp.tasa_interes, tp.id_tipo_amortizacion, cta.tipo_amortizacion
      FROM prestamo p 
      JOIN tipo_prestamo tp ON tp.id_tipo_prestamo = p.id_tipo_prestamo
      JOIN cat_tipo_amortizacion cta ON cta.id_tipo_amortizacion = tp.id_tipo_amortizacion
      WHERE p.id_prestamo=?
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id_prestamo);
    $stmt->execute(); $tp = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if ($tp){ $tasa = (float)$tp['tasa_interes']; $tipo_amort_id = (int)$tp['id_tipo_amortizacion']; $tipo_amort = $tp['tipo_amortizacion']; }
  }
  $sql = "
    SELECT id_cronograma_cuota, fecha_vencimiento
    FROM cronograma_cuota
    WHERE id_prestamo=? AND estado_cuota='Pendiente'
    ORDER BY numero_cuota ASC
    LIMIT 1
  ";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('i', $id_prestamo);
  $stmt->execute(); $cuo = $stmt->get_result()->fetch_assoc(); $stmt->close();

  j(true, [
    'id_prestamo'   => (int)$p['id_prestamo'],
    'cliente'       => $p['nombre'].' '.$p['apellido'],
    'documento'     => $p['numero_documento'],
    'monto_actual'  => (float)$p['monto_solicitado'],
    'plazo_actual'  => (int)$p['plazo_meses'],
    'tasa_actual'   => $tasa,
    'tipo_amort'    => $tipo_amort,
    'prox_cuota_id' => $cuo['id_cronograma_cuota'] ?? null,
    'prox_fecha'    => $cuo['fecha_vencimiento'] ?? null,
  ]);
}

if ($action === 'update') {
  $id_prestamo = (int)($_POST['id_prestamo'] ?? 0);
  $nuevo_plazo = (int)($_POST['nuevo_plazo'] ?? 0);
  $nueva_tasa = isset($_POST['nueva_tasa']) && $_POST['nueva_tasa'] !== '' ? (float)$_POST['nueva_tasa']: null;
  $nueva_fecha = trim($_POST['nueva_fecha']?? '');
  $motivo = trim($_POST['motivo'] ?? '');
  
  if(!$id_prestamo) j(false,[],['msg' => 'id_prestamo requerido']);
  if($nuevo_plazo <= 0) j(false,[],['msg' => 'Nuevo plazo invalido']);

  if (session_status() === PHP_SESSION_NONE) session_start();
  $usuario = $_SESSION['usuario']['id_usuario']?? null;

  $stmt = $conn->prepare("
  SELECT tasa_interes, id_tipo_amortizacion< id_periodo_pago
  FROM condicion_prestamo
  WHERE id_condicion_prestamo = (
    SELECT id_condicion_actual FROM prestamo WHERE id_prestamo = ?)"
  );
  $stmt->bind_param('i', $id_prestamo);
  $stmt->execute();
  $cond = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$cond) j(false, [],['msg'=>'No se pudo cargar la condicion actual']);

  $tasa_actual = (float)$cond['tasa_interes'];
  $tipo_amortizacion = (int)$cond['id_tipo_amortizacion'];
  $periodo = (int)$cond['id_periodo_pago'];

  $tasa_i = $nueva_tasa ?? $tasa_actual;

  $stmt = $conn->prepare("CALL reestructurar_prestamo(?,?,?,?,?,?)");
  $stmt->bind_param(
    'idiiii',
    $id_prestamo,
    $nuevo_plazo,
    $tasa_i,
    $tipo_amortizacion,
    $periodo,
    $usuario
  );
  if (!$stmt->execute()){
    j(false, [], ['msg'=>'Error: ' .$stmt->error]);
  }
  $stmt->close();

  if ($nueva_fecha !== ''){
    $stmt = $conn->prepare("
    UPDATE cronograma_cuota
    SET fecha_vencimiento =?
    WHERE id_prestamo = ? AND estado_cuota = 'Pendiente'
    ORDER BY numero_cuota ASC
    LIMIT 1    
    ");
    $stmt->bind_param('si', $nueva_fecha, $id_prestamo);
    $stmt->execute();
    $stmt->close();
  }

  if ($motivo !== ''){
    $stmt = $conn->prepare("
    UPDATE restructuracion_prestamo
    SET notas = CONCAT(COALESCE(notas, ''), '\n', ?)
    WHERE id_prestamo = ?
    ORDER BY id_restructuracion_prestamo DESC
    LIMIT 1
    ");
    $stmt->bind_param('si', $motivo, $id_prestamo);
    $stmt->execute();
    $stmt->close();
  }
  j(true, [], ['msg'=>'Reestructuracion completada']);

}

j(false, [], ['msg'=>'Acción no válida']);
