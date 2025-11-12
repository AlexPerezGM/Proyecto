<?php
// api/prestamos.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

function dbh(){
  // Intenta recuperar $conn/$mysqli del db.php del proyecto
  global $conn, $mysqli;
  if ($conn instanceof mysqli) return $conn;
  if ($mysqli instanceof mysqli) return $mysqli;
  // Fallback opcional (ajusta a tu entorno)
  $tmp = @new mysqli('127.0.0.1','root','','prestamos_db');
  if ($tmp->connect_errno) { http_response_code(500); echo json_encode(['ok'=>false,'msg'=>'DB error']); exit; }
  $tmp->set_charset('utf8mb4');
  return $tmp;
}

function out($arr){ echo json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }

$db = dbh();
$act = $_POST['action'] ?? $_GET['action'] ?? '';

/** Helpers */
function q($db, $sql, $params=[], $types=''){
  $st = $db->prepare($sql);
  if(!$st){ return [false, $db->error]; }
  if($params){
    if(!$types){ // inferencia simple
      $types = '';
      foreach($params as $p){ $types .= is_int($p) || is_float($p) ? 'd' : 's'; }
    }
    $st->bind_param($types, ...$params);
  }
  if(!$st->execute()) return [false, $st->error];
  return [$st, null];
}
function moneyDOP($db, $monto, $id_moneda){
  // Convierte a DOP usando cat_tipo_moneda.valor (1 DOP, ~62.97 USD, etc.)
  if(!$id_moneda) return (float)$monto;
  [$st, $err] = q($db, "SELECT valor FROM cat_tipo_moneda WHERE id_tipo_moneda=?", [$id_moneda], 'i');
  if(!$st) return (float)$monto;
  $valor = ($st->get_result()->fetch_assoc()['valor'] ?? 1);
  return (float)$monto * (float)$valor;
}
function per_to_periods_year($periodo){
  $p = strtolower($periodo);
  if(str_starts_with($p,'seman')) return 52;
  if(str_starts_with($p,'quin'))  return 24;
  return 12; // mensual
}
function create_cronograma_frances($db, $id_prestamo, $principal, $tasa_anual, $periodo_txt, $n_meses, $fecha_ini){
  $per_year   = per_to_periods_year($periodo_txt);
  $n_periodos = max(1, round($n_meses * ($per_year/12)));
  $i = ($tasa_anual/100) / $per_year; // tasa por periodo
  $cuota = ($i>0) ? ($principal*$i)/(1-pow(1+$i,-$n_periodos)) : ($principal/$n_periodos);

  $saldo = $principal;
  $fecha = new DateTime($fecha_ini ?: date('Y-m-d'));
  for($k=1; $k<=$n_periodos; $k++){
    // avanzar fecha según periodo
    if($per_year===52){ $fecha->modify('+1 week'); }
    elseif($per_year===24){ $fecha->modify('+15 day'); }
    else { $fecha->modify('+1 month'); }

    $interes = $saldo * $i;
    $capital = $cuota - $interes;
    if($k==$n_periodos){ $capital = $saldo; $cuota = $capital + $interes; }
    $saldo   = max(0, $saldo - $capital);

    // Ajuste a la estructura real de la tabla cronograma_cuota: usamos cargos_cuota y saldo_cuota
    q($db, "INSERT INTO cronograma_cuota (id_prestamo,numero_cuota,fecha_vencimiento,capital_cuota,interes_cuota,cargos_cuota,total_monto,saldo_cuota,estado_cuota) VALUES (?,?,?,?,?,?,?,?, 'Pendiente')",
      [$id_prestamo,$k,$fecha->format('Y-m-d'), round($capital,2), round($interes,2), 0.00, round($cuota,2), round($saldo,2)],
      'iissdddd');
  }
}

/** 0) catálogos básicos */
if ($act==='catalogos'){
  $cats = [];
  $cats['monedas'] = $db->query("SELECT id_tipo_moneda AS id, tipo_moneda AS txt, valor FROM cat_tipo_moneda")->fetch_all(MYSQLI_ASSOC);
  $cats['metodos'] = $db->query("SELECT id_tipo_pago AS id, tipo_pago AS txt FROM cat_tipo_pago")->fetch_all(MYSQLI_ASSOC);
  $cats['periodos'] = $db->query("SELECT id_periodo_pago AS id, periodo AS txt FROM cat_periodo_pago")->fetch_all(MYSQLI_ASSOC);
  // Defaults: desde tipo_prestamo (1: Personal; 2: Comercial → usamos como Hipotecario por ahora)
  $cats['defaults'] = $db->query("SELECT id_tipo_prestamo, nombre, tasa_interes, monto_minimo FROM tipo_prestamo")->fetch_all(MYSQLI_ASSOC);
  out(['ok'=>true,'data'=>$cats]);
}

/** 1) Buscar clientes (nombre/cédula) */
if ($act==='buscar_cliente'){
  $q = trim($_POST['q'] ?? '');
  if($q==='') out(['ok'=>true,'data'=>[]]);
  $sql = "
    SELECT c.id_cliente, dp.nombre, dp.apellido,
           di.numero_documento, e.email, t.telefono,
           dp.fecha_nacimiento, dp.genero,
           ie.ingresos_mensuales, ie.egresos_mensuales,
           oc.ocupacion, oc.empresa
    FROM cliente c
    JOIN datos_persona dp ON c.id_datos_persona = dp.id_datos_persona
    LEFT JOIN documento_identidad di ON di.id_datos_persona = dp.id_datos_persona
    LEFT JOIN email e ON e.id_datos_persona = dp.id_datos_persona AND e.es_principal=1
    LEFT JOIN telefono t ON t.id_datos_persona = dp.id_datos_persona AND t.es_principal=1
    LEFT JOIN ingresos_egresos ie ON ie.id_cliente = c.id_cliente
    LEFT JOIN ocupacion oc ON oc.id_datos_persona = dp.id_datos_persona
    WHERE CONCAT(dp.nombre,' ',dp.apellido) LIKE CONCAT('%',?,'%')
       OR di.numero_documento = ?
    GROUP BY c.id_cliente
    LIMIT 20";
  [$st,$err] = q($db, $sql, [$q,$q], 'ss');
  if(!$st) out(['ok'=>false,'msg'=>$err]);
  $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
  out(['ok'=>true,'data'=>$rows]);
}

/** 2) Crear préstamo PERSONAL */
if ($act==='crear_personal'){
  $id_cliente = (int)$_POST['id_cliente'];
  $monto_user = (float)($_POST['monto_solicitado'] ?? 0);
  $tasa       = (float)($_POST['tasa_interes'] ?? 0);
  $plazo      = (int)($_POST['plazo_meses'] ?? 0);
  $id_periodo = (int)($_POST['id_periodo_pago'] ?? 0);
  $fecha      = $_POST['fecha_solicitud'] ?? date('Y-m-d');
  $motivo     = $_POST['motivo'] ?? '';
  $id_moneda  = (int)($_POST['id_tipo_moneda'] ?? 1);

  // mínimos desde tipo_prestamo id=1
  $row = $db->query("SELECT tasa_interes, monto_minimo FROM tipo_prestamo WHERE id_tipo_prestamo=1")->fetch_assoc();
  $min = (float)($row['monto_minimo'] ?? 1000);
  $monto_dop = moneyDOP($db, $monto_user, $id_moneda);
  if ($monto_dop < $min) out(['ok'=>false,'msg'=>'Monto menor al mínimo establecido']);

  [$st,$err] = q($db, "INSERT INTO prestamo (id_cliente,id_tipo_prestamo,monto_solicitado,fecha_solicitud,plazo_meses,id_estado_prestamo,creado_por) VALUES (?,?,?,?,?,2,1)",
                [$id_cliente,1,$monto_dop,$fecha,$plazo],'iissd');
  if(!$st) out(['ok'=>false,'msg'=>$err]);
  $id_p = $db->insert_id;

  // condición y cronograma
  [$st2,$e2] = q($db, "INSERT INTO condicion_prestamo (tasa_interes,tipo_interes,tipo_amortizacion,id_periodo_pago,vigente_desde,esta_activo) VALUES (?,?,?,?,CURDATE(),1)",
                [$tasa,'Nominal','Frances',$id_periodo],'dssi');
  if(!$st2) out(['ok'=>false,'msg'=>$e2]);
  // esquema actual: condicion_prestamo no tiene id_prestamo — guardamos la condición y enlazamos desde prestamo.id_condicion_actual
  $id_cond = $db->insert_id;
  $db->query("UPDATE prestamo SET id_condicion_actual = $id_cond WHERE id_prestamo = $id_p");

  $per = $db->query("SELECT periodo FROM cat_periodo_pago WHERE id_periodo_pago={$id_periodo}")->fetch_assoc()['periodo'] ?? 'Mensual';
  create_cronograma_frances($db, $id_p, $monto_dop, $tasa, $per, $plazo, $fecha);

  out(['ok'=>true,'id_prestamo'=>$id_p]);
}

/** 3) Crear préstamo HIPOTECARIO */
if ($act==='crear_hipotecario'){
  $id_cliente = (int)$_POST['id_cliente'];
  $monto_user = (float)($_POST['monto_solicitado'] ?? 0);
  $tasa       = (float)($_POST['tasa_interes'] ?? 0);
  $plazo      = (int)($_POST['plazo_meses'] ?? 0);
  $id_periodo = (int)($_POST['id_periodo_pago'] ?? 0);
  $fecha      = $_POST['fecha_solicitud'] ?? date('Y-m-d');
  $dir        = $_POST['direccion_propiedad'] ?? '';
  $valor      = (float)($_POST['valor_propiedad'] ?? 0);
  $porc       = (float)($_POST['porcentaje_financiamiento'] ?? 0);
  $id_moneda  = (int)($_POST['id_tipo_moneda'] ?? 1);

  // mínimos desde tipo_prestamo id=2 (usado como "Hipotecario/Comercial")
  $row = $db->query("SELECT tasa_interes, monto_minimo FROM tipo_prestamo WHERE id_tipo_prestamo=2")->fetch_assoc();
  $min = (float)($row['monto_minimo'] ?? 12000);

  $monto_dop = moneyDOP($db, $monto_user, $id_moneda);
  if ($monto_dop < $min) out(['ok'=>false,'msg'=>'Monto menor al mínimo establecido']);
  if ($porc > 80) out(['ok'=>false,'msg'=>'El porcentaje financiado no puede exceder 80%']);
  if ($monto_dop > ($valor * $porc/100)) out(['ok'=>false,'msg'=>'Monto supera el % permitido sobre el valor del inmueble']);

  [$st,$err] = q($db, "INSERT INTO prestamo (id_cliente,id_tipo_prestamo,monto_solicitado,fecha_solicitud,plazo_meses,id_estado_prestamo,creado_por) VALUES (?,?,?,?,?,2,1)",
                [$id_cliente,2,$monto_dop,$fecha,$plazo],'iissd');
  if(!$st) out(['ok'=>false,'msg'=>$err]);
  $id_p = $db->insert_id;

  q($db, "INSERT INTO prestamos_hipotecario (id_prestamo,valor_propiedad,porcentaje_financiamiento,direccion_propiedad) VALUES (?,?,?,?)",
     [$id_p,$valor,$porc,$dir],'idds');

  // condición y cronograma
  [$st2,$e2] = q($db, "INSERT INTO condicion_prestamo (tasa_interes,tipo_interes,tipo_amortizacion,id_periodo_pago,vigente_desde,esta_activo) VALUES (?,?,?,?,CURDATE(),1)",
                [$tasa,'Nominal','Frances',$id_periodo],'dssi');
  if(!$st2) out(['ok'=>false,'msg'=>$e2]);
  // esquema actual: condicion_prestamo no tiene id_prestamo — guardamos la condición y enlazamos desde prestamo.id_condicion_actual
  $id_cond = $db->insert_id;
  $db->query("UPDATE prestamo SET id_condicion_actual = $id_cond WHERE id_prestamo = $id_p");

  $per = $db->query("SELECT periodo FROM cat_periodo_pago WHERE id_periodo_pago={$id_periodo}")->fetch_assoc()['periodo'] ?? 'Mensual';
  create_cronograma_frances($db, $id_p, $monto_dop, $tasa, $per, $plazo, $fecha);

  out(['ok'=>true,'id_prestamo'=>$id_p]);
}

/** 4) Listado de préstamos */
if ($act==='list'){
  $q  = trim($_POST['q'] ?? '');
  $tipo = trim($_POST['tipo'] ?? '');
  $page = max(1, (int)($_POST['page'] ?? 1));
  $size = max(1, min(50,(int)($_POST['size'] ?? 10)));
  $off = ($page-1)*$size;

  $where = [];
  $params = []; $types='';
  if($q!==''){
    $where[] = "(CONCAT(dp.nombre,' ',dp.apellido) LIKE CONCAT('%',?,'%') OR di.numero_documento= ? OR p.id_prestamo=?)";
    $params[]=$q; $params[]=$q; $params[]=(int)$q; $types.='ssi';
  }
  if($tipo!==''){
    if(strtolower($tipo)==='hipotecario'){ $where[]="p.id_tipo_prestamo=2"; }
    if(strtolower($tipo)==='personal'){    $where[]="p.id_tipo_prestamo=1"; }
  }
  $w = $where? ('WHERE '.implode(' AND ',$where)) : '';

  $sql = "
    SELECT SQL_CALC_FOUND_ROWS
           p.id_prestamo, dp.nombre, dp.apellido,
           CASE WHEN p.id_tipo_prestamo=2 THEN 'Hipotecario' ELSE 'Personal' END AS tipo_prestamo,
           p.monto_solicitado, cp.tasa_interes, p.plazo_meses,
           ce.estado AS estado_prestamo,
           (SELECT MIN(fecha_vencimiento) FROM cronograma_cuota cc WHERE cc.id_prestamo=p.id_prestamo AND cc.estado_cuota='Pendiente') AS proximo_pago
    FROM prestamo p
    JOIN cliente c ON c.id_cliente=p.id_cliente
    JOIN datos_persona dp ON c.id_datos_persona=dp.id_datos_persona
  LEFT JOIN condicion_prestamo cp ON cp.id_condicion_prestamo = p.id_condicion_actual AND cp.esta_activo=1
    LEFT JOIN cat_estado_prestamo ce ON ce.id_estado_prestamo=p.id_estado_prestamo
    LEFT JOIN documento_identidad di ON di.id_datos_persona=dp.id_datos_persona
    $w
    ORDER BY p.id_prestamo DESC
    LIMIT $off,$size";
  [$st,$err]=q($db,$sql,$params,$types);
  if(!$st) out(['ok'=>false,'msg'=>$err]);
  $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
  $total = $db->query("SELECT FOUND_ROWS() AS t")->fetch_assoc()['t'] ?? 0;
  out(['ok'=>true,'data'=>$rows,'total'=>$total]);
}

/** 5) Ver préstamo */
if ($act==='get'){
  $id = (int)($_POST['id_prestamo'] ?? 0);
  $sql = "
    SELECT p.*, dp.nombre, dp.apellido,
           CASE WHEN p.id_tipo_prestamo=2 THEN 'Hipotecario' ELSE 'Personal' END AS tipo_prestamo,
           ce.estado,
           cp.tasa_interes, cp.id_periodo_pago,
           (SELECT periodo FROM cat_periodo_pago WHERE id_periodo_pago=cp.id_periodo_pago) AS periodo_txt
    FROM prestamo p
    JOIN cliente c ON c.id_cliente=p.id_cliente
    JOIN datos_persona dp ON c.id_datos_persona=dp.id_datos_persona
  LEFT JOIN condicion_prestamo cp ON cp.id_condicion_prestamo = p.id_condicion_actual AND cp.esta_activo=1
    LEFT JOIN cat_estado_prestamo ce ON ce.id_estado_prestamo=p.id_estado_prestamo
    WHERE p.id_prestamo=?";
  [$st,$err]=q($db,$sql,[$id],'i');
  if(!$st) out(['ok'=>false,'msg'=>$err]);
  $row = $st->get_result()->fetch_assoc() ?: [];
  // cronograma
  $cron = $db->query("SELECT numero_cuota, fecha_vencimiento, capital_cuota, interes_cuota, total_monto, estado_cuota FROM cronograma_cuota WHERE id_prestamo=$id ORDER BY numero_cuota ASC")->fetch_all(MYSQLI_ASSOC);
  out(['ok'=>true,'data'=>$row,'cronograma'=>$cron]);
}

/** 6) Buscar para desembolso */
if ($act==='buscar_prestamo'){
  $q = trim($_POST['q'] ?? '');
  if($q==='') out(['ok'=>true,'data'=>[]]);
  $sql = "
    SELECT p.id_prestamo, CONCAT(dp.nombre,' ',dp.apellido) AS cliente,
           CASE WHEN p.id_tipo_prestamo=2 THEN 'Hipotecario' ELSE 'Personal' END AS tipo,
           p.monto_solicitado
    FROM prestamo p
    JOIN cliente c ON c.id_cliente=p.id_cliente
    JOIN datos_persona dp ON dp.id_datos_persona=c.id_datos_persona
    LEFT JOIN documento_identidad di ON di.id_datos_persona=dp.id_datos_persona
    WHERE CONCAT(dp.nombre,' ',dp.apellido) LIKE CONCAT('%',?,'%')
       OR di.numero_documento = ?
       OR p.id_prestamo = ?
    ORDER BY p.id_prestamo DESC LIMIT 20";
  [$st,$err]=q($db,$sql,[$q,$q,(int)$q],'ssi');
  if(!$st) out(['ok'=>false,'msg'=>$err]);
  out(['ok'=>true,'data'=>$st->get_result()->fetch_all(MYSQLI_ASSOC)]);
}

/** 7) Desembolsar */
if ($act==='desembolsar'){
  $id_p = (int)($_POST['id_prestamo'] ?? 0);
  $monto = (float)($_POST['monto_desembolsado'] ?? 0);
  $fecha = $_POST['fecha_desembolso'] ?? date('Y-m-d');
  $met   = (int)($_POST['metodo_entrega'] ?? 1);

  [$st,$err]=q($db,"INSERT INTO desembolso (id_prestamo,monto_desembolsado,fecha_desembolso,metodo_entrega) VALUES (?,?,?,?)",
               [$id_p,$monto,$fecha,$met],'idss');
  if(!$st) out(['ok'=>false,'msg'=>$err]);

  // Estado a ACTIVO si no lo estaba
  $db->query("UPDATE prestamo SET id_estado_prestamo=1, fecha_otrogamiento=IFNULL(fecha_otrogamiento,'$fecha') WHERE id_prestamo=$id_p");

  out(['ok'=>true,'id_desembolso'=>$db->insert_id]);
}

/** 8) HTML del recibo (para imprimir/guardar PDF) */
if ($act==='recibo_html'){
  header('Content-Type: text/html; charset=utf-8');
  $id_p = (int)($_GET['id_prestamo'] ?? 0);
  $p = $db->query("
    SELECT p.id_prestamo, p.monto_solicitado, p.plazo_meses, p.fecha_solicitud,
           CONCAT(dp.nombre,' ',dp.apellido) AS cliente,
           cp.tasa_interes,
           (SELECT MIN(fecha_vencimiento) FROM cronograma_cuota cc WHERE cc.id_prestamo=p.id_prestamo AND cc.estado_cuota='Pendiente') AS proximo_pago
    FROM prestamo p
    JOIN cliente c ON c.id_cliente=p.id_cliente
    JOIN datos_persona dp ON dp.id_datos_persona=c.id_datos_persona
  LEFT JOIN condicion_prestamo cp ON cp.id_condicion_prestamo = p.id_condicion_actual AND cp.esta_activo=1
    WHERE p.id_prestamo=$id_p")->fetch_assoc();

  $metodos = $db->query("SELECT id_tipo_pago, tipo_pago FROM cat_tipo_pago")->fetch_all(MYSQLI_ASSOC);
  ?>
  <div style="font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; color:#111827; max-width:780px; margin:auto;">
    <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e5e7eb; padding-bottom:8px; margin-bottom:12px;">
      <div>
        <div style="font-weight:800; font-size:1.2rem;">Auto-Botic Financier S.R.L.</div>
        <div class="mini">RNC: 1-00-00000-0 · Suc. Central · contacto@empresa.com</div>
      </div>
      <div style="text-align:right;">
        <div class="pill">Comprobante: CP-<?= str_pad($id_p,6,'0',STR_PAD_LEFT) ?></div>
        <div class="mini">Fecha de emisión: <?= date('Y-m-d') ?></div>
      </div>
    </div>

    <h3 style="margin:8px 0;">Datos del cliente</h3>
    <div class="panel" style="padding:12px;">
      <div><b>Nombre:</b> <?= htmlspecialchars($p['cliente'] ?? '-') ?></div>
      <div><b>Contacto:</b> —</div>
      <div><b>Documento:</b> —</div>
    </div>

    <h3 style="margin:12px 0 6px;">Detalle de la transacción</h3>
    <table style="width:100%; border-collapse:collapse; font-size:.9rem;">
      <thead>
        <tr>
          <th style="text-align:left; border-bottom:1px solid #e5e7eb; padding:8px;">Concepto</th>
          <th style="text-align:left; border-bottom:1px solid #e5e7eb; padding:8px;">Descripción</th>
          <th style="text-align:right; border-bottom:1px solid #e5e7eb; padding:8px;">Monto</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td style="padding:8px;">Préstamo otorgado</td>
          <td style="padding:8px;">Código #<?= $p['id_prestamo'] ?></td>
          <td style="padding:8px; text-align:right;">$<?= number_format($p['monto_solicitado'] ?? 0,2) ?></td>
        </tr>
        <tr>
          <td style="padding:8px;">Interés aplicado</td>
          <td style="padding:8px;"><?= (float)$p['tasa_interes'] ?>% anual</td>
          <td style="padding:8px; text-align:right;">—</td>
        </tr>
        <tr>
          <td style="padding:8px;">Próximo pago</td>
          <td style="padding:8px;"><?= htmlspecialchars($p['proximo_pago'] ?? '-') ?></td>
          <td style="padding:8px; text-align:right;">—</td>
        </tr>
      </tbody>
    </table>

    <div style="margin-top:12px; display:grid; grid-template-columns:1fr 1fr; gap:12px;">
      <div class="panel" style="padding:12px;">
        <div><b>Total Pagado:</b> $0.00</div>
        <div><b>Monto Restante:</b> —</div>
        <div><b>Estado del préstamo:</b> Activo</div>
      </div>
      <div class="panel" style="padding:12px;">
        <div style="height:60px; border:1px dashed #d1d5db; border-radius:8px; display:flex; align-items:center; justify-content:center;">
          <span class="mini">Espacio para firma</span>
        </div>
        <p class="mini" style="margin-top:8px;">Este comprobante es válido como constancia del pago realizado. No requiere firma física si fue emitido digitalmente por el sistema.</p>
      </div>
    </div>
  </div>
  <?php
  exit;
}

/** 9) Catálogos sueltos para selects */
if ($act==='metodos'){
  $r = $db->query("SELECT id_tipo_pago AS id, tipo_pago AS txt FROM cat_tipo_pago")->fetch_all(MYSQLI_ASSOC);
  out(['ok'=>true,'data'=>$r]);
}
if ($act==='monedas'){
  $r = $db->query("SELECT id_tipo_moneda AS id, tipo_moneda AS txt, valor FROM cat_tipo_moneda")->fetch_all(MYSQLI_ASSOC);
  out(['ok'=>true,'data'=>$r]);
}
if ($act==='periodos'){
  $r = $db->query("SELECT id_periodo_pago AS id, periodo AS txt FROM cat_periodo_pago")->fetch_all(MYSQLI_ASSOC);
  out(['ok'=>true,'data'=>$r]);
}

out(['ok'=>false,'msg'=>'Acción no reconocida']);
