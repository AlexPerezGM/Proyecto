<?php
// api/pagos.php
header('Content-Type: application/json; charset=utf-8');

// Intenta reutilizar tu conexión actual
$DB = null;
try {
  // Ajusta la ruta si tu db.php está en /config o similar
  $try1 = __DIR__ . '/../config/db.php';
  $try2 = __DIR__ . '/../../config/db.php';
  if (file_exists($try1)) require_once $try1;
  elseif (file_exists($try2)) require_once $try2;

  // Si tu db.php define $conn o $mysqli, úsalo.
  if (isset($conn) && $conn instanceof mysqli)      { $DB = $conn; }
  elseif (isset($mysqli) && $mysqli instanceof mysqli){ $DB = $mysqli; }
} catch(Exception $e){}

if (!$DB) {
  // Fallback (ajusta credenciales si hace falta)
  $DB = new mysqli('127.0.0.1','root','','prestamos_db');
  $DB->set_charset('utf8mb4');
}

function jok($arr=[]){ echo json_encode(['ok'=>true]+$arr); exit; }
function jerr($msg, $extra=[]){ http_response_code(400); echo json_encode(['ok'=>false,'msg'=>$msg]+$extra); exit; }
function param($k,$def=null){ return $_POST[$k] ?? $def; }

function get_tipo_pago_id($DB,$nombre){
  $sql="SELECT id_tipo_pago FROM cat_tipo_pago WHERE tipo_pago=? ORDER BY id_tipo_pago DESC LIMIT 1";
  $st=$DB->prepare($sql); $st->bind_param('s',$nombre); $st->execute();
  $st->bind_result($id); if($st->fetch()){ $st->close(); return (int)$id; } $st->close(); return null;
}
function get_estado_prestamo_id($DB,$nombre){
  $sql="SELECT id_estado_prestamo FROM cat_estado_prestamo WHERE estado=? ORDER BY id_estado_prestamo ASC LIMIT 1";
  $st=$DB->prepare($sql); $st->bind_param('s',$nombre); $st->execute();
  $st->bind_result($id); if($st->fetch()){ $st->close(); return (int)$id; } $st->close(); return null;
}
function get_moneda($DB,$id_tipo_moneda){
  $st=$DB->prepare("SELECT tipo_moneda, valor FROM cat_tipo_moneda WHERE id_tipo_moneda=? LIMIT 1");
  $st->bind_param('i',$id_tipo_moneda); $st->execute();
  $st->bind_result($tipo,$valor); if($st->fetch()){ $st->close(); return ['tipo'=>$tipo,'valor'=>(float)$valor]; }
  $st->close(); return ['tipo'=>'DOP','valor'=>1.0];
}

// Suma asignada previa por tipo (para distribuir correctamente)
function sum_asignado($DB,$id_crono,$tipo){
  $st=$DB->prepare("SELECT COALESCE(SUM(monto_asignado),0) FROM asignacion_pago WHERE id_cronograma_cuota=? AND tipo_asignacion=?");
  $st->bind_param('is',$id_crono,$tipo); $st->execute(); $st->bind_result($s); $st->fetch(); $st->close(); return (float)$s;
}

// Calcula mora sugerida (por día) sobre cuotas vencidas
function calcular_mora($DB,$id_prestamo,$mora_diaria=0.0015){ // 0.15%/día
  $sql="SELECT id_cronograma_cuota, fecha_vencimiento, saldo_pendiente
        FROM cronograma_cuota
        WHERE id_prestamo=? AND estado_cuota IN ('Vencida','Pendiente')
        ORDER BY fecha_vencimiento ASC, numero_cuota ASC";
  $st=$DB->prepare($sql); $st->bind_param('i',$id_prestamo); $st->execute(); $rs=$st->get_result();
  $hoy = new DateTime('today');
  $mora=0.0; $det=[];
  while($r=$rs->fetch_assoc()){
    $fv = DateTime::createFromFormat('Y-m-d',$r['fecha_vencimiento']);
    $dias = $fv && $fv < $hoy ? (int)$fv->diff($hoy)->format('%a') : 0;
    if ($dias>0){
      $m = round((float)$r['saldo_pendiente'] * $mora_diaria * $dias, 2);
      $mora += $m;
      $det[]=['id_cronograma_cuota'=>(int)$r['id_cronograma_cuota'],'dias'=>$dias,'mora'=>$m];
    }
  }
  $st->close();
  return ['mora_total'=>round($mora,2),'detalle'=>$det];
}

// Devuelve resumen del préstamo: estado, saldo total, cuota “actual”
function resumen_prestamo($DB,$id_prestamo){
  // Estado y cliente
  $q1="SELECT p.id_prestamo, p.id_estado_prestamo, ce.estado AS estado_txt, p.id_cliente
       FROM prestamo p
       LEFT JOIN cat_estado_prestamo ce ON ce.id_estado_prestamo=p.id_estado_prestamo
       WHERE p.id_prestamo=? LIMIT 1";
  $st=$DB->prepare($q1); $st->bind_param('i',$id_prestamo); $st->execute(); $r=$st->get_result()->fetch_assoc(); $st->close();
  if(!$r) return null;

  // Saldo total (cuotas no pagadas)
  $q2="SELECT COALESCE(SUM(saldo_pendiente),0) AS saldo
       FROM cronograma_cuota
       WHERE id_prestamo=? AND estado_cuota IN ('Pendiente','Vencida')";
  $st=$DB->prepare($q2); $st->bind_param('i',$id_prestamo); $st->execute(); $st->bind_result($saldo); $st->fetch(); $st->close();

  // Próxima/Actual cuota (vencida primero, si no la más próxima pendiente)
  $q3="SELECT *
       FROM cronograma_cuota
       WHERE id_prestamo=? AND estado_cuota IN ('Vencida','Pendiente')
       ORDER BY (estado_cuota='Vencida') DESC, fecha_vencimiento ASC, numero_cuota ASC
       LIMIT 1";
  $st=$DB->prepare($q3); $st->bind_param('i',$id_prestamo); $st->execute(); $cuota=$st->get_result()->fetch_assoc(); $st->close();

  return [
    'id_prestamo'=>(int)$r['id_prestamo'],
    'estado'=>$r['estado_txt'] ?? '',
    'id_estado_prestamo'=>(int)($r['id_estado_prestamo'] ?? 0),
    'saldo_total'=>round((float)$saldo,2),
    'cuota_actual'=>$cuota ? [
      'id_cronograma_cuota'=>(int)$cuota['id_cronograma_cuota'],
      'numero_cuota'=>(int)$cuota['numero_cuota'],
      'fecha_vencimiento'=>$cuota['fecha_vencimiento'],
      'capital'=>(float)$cuota['capital_cuota'],
      'interes'=>(float)$cuota['interes_cuota'],
      'cargos'=>(float)$cuota['cargos_cuota'],
      'saldo_pendiente'=>round((float)$cuota['saldo_pendiente'],2),
      'estado_cuota'=>$cuota['estado_cuota']
    ] : null
  ];
}

// Distribuye el pago sobre cuotas (Cargos -> Interés -> Capital)
function asignar_pago($DB,$id_prestamo,$id_pago,$monto_dop){
  // Cuotas en orden: vencidas primero, luego pendientes, por fecha
  $sql="SELECT id_cronograma_cuota, capital_cuota, interes_cuota, cargos_cuota, saldo_pendiente
        FROM cronograma_cuota
        WHERE id_prestamo=? AND estado_cuota IN ('Vencida','Pendiente')
        ORDER BY (estado_cuota='Vencida') DESC, fecha_vencimiento ASC, numero_cuota ASC";
  $st=$DB->prepare($sql); $st->bind_param('i',$id_prestamo); $st->execute(); $rs=$st->get_result();

  while($monto_dop>0 && ($c=$rs->fetch_assoc())){
    $idc=(int)$c['id_cronograma_cuota'];

    // Remanentes por componente (lo emitido en crono menos lo ya asignado históricamente)
    $pend_cargos  = max(0, (float)$c['cargos_cuota']  - sum_asignado($DB,$idc,'Cargos'));
    $pend_interes = max(0, (float)$c['interes_cuota'] - sum_asignado($DB,$idc,'Interes'));
    $pend_capital = max(0, (float)$c['capital_cuota'] - sum_asignado($DB,$idc,'Capital'));

    // 1) Cargos
    if($monto_dop>0 && $pend_cargos>0){
      $a=min($monto_dop,$pend_cargos);
      $DB->query("INSERT INTO asignacion_pago (id_pago,id_cronograma_cuota,monto_asignado,tipo_asignacion)
                  VALUES ($id_pago,$idc,$a,'Cargos')");
      $pend_cargos -= $a; $monto_dop -= $a;
    }
    // 2) Interés
    if($monto_dop>0 && $pend_interes>0){
      $a=min($monto_dop,$pend_interes);
      $DB->query("INSERT INTO asignacion_pago (id_pago,id_cronograma_cuota,monto_asignado,tipo_asignacion)
                  VALUES ($id_pago,$idc,$a,'Interes')");
      $pend_interes -= $a; $monto_dop -= $a;
    }
    // 3) Capital
    if($monto_dop>0 && $pend_capital>0){
      $a=min($monto_dop,$pend_capital);
      $DB->query("INSERT INTO asignacion_pago (id_pago,id_cronograma_cuota,monto_asignado,tipo_asignacion)
                  VALUES ($id_pago,$idc,$a,'Capital')");
      $pend_capital -= $a; $monto_dop -= $a;
    }

    // Actualiza saldo_pendiente y estado_cuota
    $nuevoSaldo = max(0.0, (float)$c['saldo_pendiente'] - ((float)$c['saldo_pendiente'] - ($pend_cargos+$pend_interes+$pend_capital)));
    // La línea anterior recalcula por componentes; simplifiquemos:
    $asigt = $DB->query("SELECT COALESCE(SUM(monto_asignado),0) t FROM asignacion_pago WHERE id_cronograma_cuota=$idc")->fetch_assoc()['t'];
    $row   = $DB->query("SELECT total_monto FROM cronograma_cuota WHERE id_cronograma_cuota=$idc")->fetch_assoc();
    $tot   = (float)$row['total_monto'];
    $nuevoSaldo = max(0.0, $tot - (float)$asigt);

    $estado = $nuevoSaldo <= 0.009 ? 'Pagada' : (new DateTime($DB->query("SELECT fecha_vencimiento FROM cronograma_cuota WHERE id_cronograma_cuota=$idc")->fetch_assoc()['fecha_vencimiento']) < new DateTime('today') ? 'Vencida' : 'Pendiente');
    $upd=$DB->prepare("UPDATE cronograma_cuota SET saldo_pendiente=?, estado_cuota=? WHERE id_cronograma_cuota=?");
    $upd->bind_param('dsi',$nuevoSaldo,$estado,$idc); $upd->execute(); $upd->close();
  }
  $st->close();
}

// ====== Router de acciones ======
$action = param('action','');

if ($action==='search'){
  $q = trim(param('q',''));
  if ($q==='') jok(['data'=>[]]);

  // Busca por nombre/apellido, cédula (documento) o id_prestamo / numero_contrato
  $like = '%'.$q.'%';
  $isNum = ctype_digit($q);
  $sql = "
    SELECT p.id_prestamo,
           dp.nombre, dp.apellido,
           di.numero_documento,
           ce.estado AS estado_prestamo
    FROM prestamo p
    JOIN cliente c ON c.id_cliente=p.id_cliente
    JOIN datos_persona dp ON dp.id_datos_persona=c.id_datos_persona
    LEFT JOIN documento_identidad di ON di.id_datos_persona=dp.id_datos_persona
    LEFT JOIN cat_estado_prestamo ce ON ce.id_estado_prestamo=p.id_estado_prestamo
    WHERE (dp.nombre LIKE ? OR dp.apellido LIKE ? OR di.numero_documento LIKE ?)
       ".($isNum ? " OR p.id_prestamo=?" : " OR p.numero_contrato LIKE ?")."
    ORDER BY p.id_prestamo DESC
    LIMIT 30";
  $st=$DB->prepare($sql);
  if ($isNum){ $st->bind_param('sssi',$like,$like,$like,$q); }
  else { $st->bind_param('sssss',$like,$like,$like,$like); }
  $st->execute();
  $rs=$st->get_result();
  $out=[];
  while($r=$rs->fetch_assoc()){ $out[]=$r; }
  $st->close();
  jok(['data'=>$out]);
}

if ($action==='summary'){
  $id = (int)param('id_prestamo',0);
  if (!$id) jerr('id_prestamo requerido');
  $r = resumen_prestamo($DB,$id);
  if (!$r) jerr('Préstamo no encontrado');
  $m = calcular_mora($DB,$id);
  jok(['resumen'=>$r,'mora'=>$m]);
}

if ($action==='calc_mora'){
  $id = (int)param('id_prestamo',0);
  $rate = (float)param('mora_diaria',0.0015);
  jok(calcular_mora($DB,$id,$rate));
}

if ($action==='pay'){
  $id = (int)param('id_prestamo',0);
  $metodo = param('metodo','Efectivo'); // 'Efectivo' | 'Transferencia'
  $monto  = (float)param('monto',0);
  $id_mon = (int)param('id_tipo_moneda',1); // 1 DOP, 2 USD
  $ref    = trim(param('referencia',''));
  $obs    = trim(param('observacion','')); // requiere ALTER si quieres guardarlo

  if(!$id || $monto<=0) jerr('Datos de pago inválidos');
  $tp_id = get_tipo_pago_id($DB,$metodo);
  if(!$tp_id) jerr('Método no disponible');

  $mon = get_moneda($DB,$id_mon);
  $monto_dop = round($monto * (float)$mon['valor'], 2);

  // Inserta pago (fecha CURDATE por trigger anti-futuro)
  $st=$DB->prepare("INSERT INTO pago (id_prestamo,fecha_pago,monto_pagado,metodo_pago,id_tipo_moneda,creado_por".(column_exists($DB,'pago','observacion')?',observacion':'').") VALUES (CURDATE() IS NULL, ?, ?, ?, ?, 1".(column_exists($DB,'pago','observacion')?',?':'').")");
  // Truco: mysqli no soporta fácilmente IF de columnas; reescribamos legible:
  $sql = "INSERT INTO pago (id_prestamo,fecha_pago,monto_pagado,metodo_pago,id_tipo_moneda,creado_por".(column_exists($DB,'pago','observacion')?',observacion':'').")
          VALUES (?,?,?,?,?,1".(column_exists($DB,'pago','observacion')?',?':'').")";
  $st=$DB->prepare($sql);
  if (column_exists($DB,'pago','observacion')){
    $today = (new DateTime('today'))->format('Y-m-d');
    $st->bind_param('isdiis',$id,$today,$monto_dop,$tp_id,$id_mon,$obs);
  } else {
    $today = (new DateTime('today'))->format('Y-m-d');
    $st->bind_param('isdis',$id,$today,$monto_dop,$tp_id,$id_mon);
  }
  $st->execute(); $id_pago = $DB->insert_id; $st->close();

  // Asignación a cuotas
  asignar_pago($DB,$id,$id_pago,$monto_dop);

  // Nuevo saldo y resumen
  $res = resumen_prestamo($DB,$id);
  jok(['id_pago'=>$id_pago,'resumen'=>$res,'comprobante'=>[
    'metodo'=>$metodo,'monto'=>$monto,'moneda'=>$mon['tipo'],'monto_dop'=>$monto_dop,'referencia'=>$ref,'observacion'=>$obs,'fecha'=>$today
  ]]);
}

if ($action==='garantia'){
  $id = (int)param('id_prestamo',0);
  $id_g = (int)param('id_garantia',0);
  $monto= (float)param('monto',0);
  $motivo= trim(param('motivo',''));
  $obs  = trim(param('observacion',''));
  if(!$id || !$id_g || $monto<=0) jerr('Datos de garantía inválidos');

  // Asegura método Garantía
  $tp = get_tipo_pago_id($DB,'Garantía');
  if(!$tp){
    $DB->query("INSERT INTO cat_tipo_pago (tipo_pago) VALUES ('Garantía')");
    $tp = get_tipo_pago_id($DB,'Garantía');
  }
  $today = (new DateTime('today'))->format('Y-m-d');
  // Inserta pago "Garantía"
  $st=$DB->prepare("INSERT INTO pago (id_prestamo,fecha_pago,monto_pagado,metodo_pago,id_tipo_moneda,creado_por".(column_exists($DB,'pago','observacion')?',observacion':'').") VALUES (?,?,?,?,1,1".(column_exists($DB,'pago','observacion')?',?':'').")");
  if (column_exists($DB,'pago','observacion')){
    $st->bind_param('isdiis',$id,$today,$monto,$tp,$obs);
  } else {
    $st->bind_param('isdi',$id,$today,$monto,$tp);
  }
  $st->execute(); $id_pago=$DB->insert_id; $st->close();

  // Registra retiro de garantía
  $st=$DB->prepare("INSERT INTO retiro_garantia (id_pago,id_garantia,fecha_uso,monto_usado) VALUES (?,?,?,?)");
  $st->bind_param('iisd',$id_pago,$id_g,$today,$monto); $st->execute(); $st->close();

  // Asigna a cuotas
  asignar_pago($DB,$id,$id_pago,$monto);

  $res = resumen_prestamo($DB,$id);
  jok(['id_pago'=>$id_pago,'resumen'=>$res,'comprobante'=>[
    'metodo'=>'Garantía','monto'=>$monto,'moneda'=>'DOP','monto_dop'=>$monto,'motivo'=>$motivo,'observacion'=>$obs,'fecha'=>$today
  ]]);
}

if ($action==='close'){
  $id=(int)param('id_prestamo',0); $obs=trim(param('observacion',''));
  if(!$id) jerr('id_prestamo requerido');
  $res = resumen_prestamo($DB,$id);
  if(!$res) jerr('Préstamo no encontrado');
  if ($res['saldo_total'] > 0.009) jerr('No puede cerrarse: saldo pendiente');

  // Cambia estado a Pagado
  $idEstado = get_estado_prestamo_id($DB,'Pagado');
  if($idEstado){
    $st=$DB->prepare("UPDATE prestamo SET id_estado_prestamo=? WHERE id_prestamo=?");
    $st->bind_param('ii',$idEstado,$id); $st->execute(); $st->close();
  }

  // (Opcional) liberar garantías: si usas prestamo_propiedad, puedes eliminar vínculos
  // $DB->query("DELETE FROM prestamo_propiedad WHERE id_prestamo=".$id);

  $hoy=(new DateTime('today'))->format('Y-m-d');
  jok(['cerrado'=>true,'comprobante_cierre'=>[
    'fecha'=>$hoy,'observacion'=>$obs,'id_prestamo'=>$id
  ]]);
}

jerr('Acción no reconocida');

// -------- helpers ----------
function column_exists($DB,$table,$col){
  $st=$DB->prepare("SHOW COLUMNS FROM `$table` LIKE ?"); $st->bind_param('s',$col);
  $st->execute(); $rs=$st->get_result(); $ok = $rs && $rs->num_rows>0; $st->close(); return $ok;
}
