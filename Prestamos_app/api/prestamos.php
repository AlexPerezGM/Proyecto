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
function generar_cronograma_prestamo($db, $id_prestamo){
  // Validar que el préstamo exista y tenga datos válidos antes de llamar el SP
  $check = $db->query("
    SELECT p.id_prestamo, p.monto_solicitado, p.plazo_meses, p.fecha_solicitud,
           p.id_condicion_actual, cp.tasa_interes, cp.id_tipo_amortizacion
    FROM prestamo p 
    LEFT JOIN condicion_prestamo cp ON p.id_condicion_actual = cp.id_condicion_prestamo
    WHERE p.id_prestamo = $id_prestamo
  ")->fetch_assoc();
  
  if (!$check) {
    throw new Exception("Préstamo no encontrado con ID: " . $id_prestamo);
  }
  
  if (!$check['fecha_solicitud']) {
    // Asignar fecha actual si no tiene fecha
    $db->query("UPDATE prestamo SET fecha_solicitud = CURDATE() WHERE id_prestamo = $id_prestamo");
  }
  
  if (!$check['id_condicion_actual']) {
    throw new Exception("El préstamo no tiene condiciones configuradas");
  }
  
  // Usar el stored procedure generar_cronograma que ya maneja todos los tipos de amortización
  [$st, $err] = q($db, "CALL generar_cronograma(?)", [$id_prestamo], 'i');
  if(!$st) {
    throw new Exception("Error generando cronograma: " . $err);
  }
  return true;
}

/** 0) catálogos básicos */
if ($act==='catalogos'){
  $cats = [];
  $cats['monedas'] = $db->query("SELECT id_tipo_moneda AS id, tipo_moneda AS txt, valor FROM cat_tipo_moneda")->fetch_all(MYSQLI_ASSOC);
  $cats['metodos'] = $db->query("SELECT id_tipo_pago AS id, tipo_pago AS txt FROM cat_tipo_pago")->fetch_all(MYSQLI_ASSOC);
  $cats['periodos'] = $db->query("SELECT id_periodo_pago AS id, periodo AS txt FROM cat_periodo_pago")->fetch_all(MYSQLI_ASSOC);
  $cats['amortizacion'] = $db->query("SELECT id_tipo_amortizacion AS id, tipo_amortizacion AS txt FROM cat_tipo_amortizacion")->fetch_all(MYSQLI_ASSOC);
  // Defaults: desde tipo_prestamo (1: Personal; 2: Comercial → usamos como Hipotecario por ahora)
  $cats['defaults'] = $db->query("SELECT id_tipo_prestamo, nombre, tasa_interes, monto_minimo, id_tipo_amortizacion FROM tipo_prestamo")->fetch_all(MYSQLI_ASSOC);
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
           oc.ocupacion, oc.empresa,
           d.ciudad, d.sector, d.calle, d.numero_casa,
           CONCAT_WS(', ', 
             NULLIF(d.calle, ''), 
             NULLIF(CONCAT('No. ', NULLIF(d.numero_casa, 0)), 'No. 0'),
             NULLIF(d.sector, ''),
             NULLIF(d.ciudad, '')
           ) AS direccion_completa
    FROM cliente c
    JOIN datos_persona dp ON c.id_datos_persona = dp.id_datos_persona
    LEFT JOIN documento_identidad di ON di.id_datos_persona = dp.id_datos_persona
    LEFT JOIN email e ON e.id_datos_persona = dp.id_datos_persona AND e.es_principal=1
    LEFT JOIN telefono t ON t.id_datos_persona = dp.id_datos_persona AND t.es_principal=1
    LEFT JOIN ingresos_egresos ie ON ie.id_cliente = c.id_cliente
    LEFT JOIN ocupacion oc ON oc.id_datos_persona = dp.id_datos_persona
    LEFT JOIN direccion d ON d.id_datos_persona = dp.id_datos_persona
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
  $id_amortizacion = (int)($_POST['id_tipo_amortizacion'] ?? 1);
  $fecha      = trim($_POST['fecha_solicitud'] ?? '') ?: date('Y-m-d');
  $motivo     = $_POST['motivo'] ?? '';
  $id_moneda  = (int)($_POST['id_tipo_moneda'] ?? 1);
  $num_contrato = 'PER-' . date('Ymd') . '-' . str_pad($id_cliente, 4, '0', STR_PAD_LEFT);

  // Validar que la fecha sea válida
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || !strtotime($fecha)) {
    $fecha = date('Y-m-d');
  }

  // mínimos desde tipo_prestamo id=1
  $row = $db->query("SELECT tasa_interes, monto_minimo FROM tipo_prestamo WHERE id_tipo_prestamo=1")->fetch_assoc();
  $min = (float)($row['monto_minimo'] ?? 1000);
  $monto_dop = moneyDOP($db, $monto_user, $id_moneda);
  if ($monto_dop < $min) out(['ok'=>false,'msg'=>'Monto menor al mínimo establecido']);

  $db->autocommit(false);
  try {
    // Crear condición del préstamo primero
    [$st2,$e2] = q($db, "INSERT INTO condicion_prestamo (tasa_interes,id_tipo_amortizacion,id_periodo_pago,vigente_desde,esta_activo) VALUES (?,?,?,CURDATE(),1)",
                  [$tasa,$id_amortizacion,$id_periodo],'dii');
    if(!$st2) throw new Exception($e2);
    $id_cond = $db->insert_id;

    // Crear préstamo
    [$st,$err] = q($db, "INSERT INTO prestamo (id_cliente,id_tipo_prestamo,numero_contrato,monto_solicitado,fecha_solicitud,plazo_meses,id_estado_prestamo,id_condicion_actual,creado_por) VALUES (?,?,?,?,?,?,2,?,1)",
                  [$id_cliente,1,$num_contrato,$monto_dop,$fecha,$plazo,$id_cond],'issdsii');
    if(!$st) throw new Exception($err);
    $id_p = $db->insert_id;

    // Crear detalle personal
    q($db, "INSERT INTO prestamo_personal (id_prestamo,motivo) VALUES (?,?)", [$id_p,$motivo], 'is');

    // Generar cronograma usando stored procedure
    generar_cronograma_prestamo($db, $id_p);

    $db->commit();
    out(['ok'=>true,'id_prestamo'=>$id_p,'numero_contrato'=>$num_contrato]);
  } catch (Exception $e) {
    $db->rollback();
    out(['ok'=>false,'msg'=>$e->getMessage()]);
  } finally {
    $db->autocommit(true);
  }
}

/** 3) Crear préstamo HIPOTECARIO */
if ($act==='crear_hipotecario'){
  $id_cliente = (int)$_POST['id_cliente'];
  $monto_user = (float)($_POST['monto_solicitado'] ?? 0);
  $tasa       = (float)($_POST['tasa_interes'] ?? 0);
  $plazo      = (int)($_POST['plazo_meses'] ?? 0);
  $id_periodo = (int)($_POST['id_periodo_pago'] ?? 0);
  $id_amortizacion = (int)($_POST['id_tipo_amortizacion'] ?? 2);
  $fecha      = trim($_POST['fecha_solicitud'] ?? '') ?: date('Y-m-d');
  $dir        = $_POST['direccion_propiedad'] ?? '';
  $valor      = (float)($_POST['valor_propiedad'] ?? 0);
  $porc       = (float)($_POST['porcentaje_financiamiento'] ?? 0);
  $id_moneda  = (int)($_POST['id_tipo_moneda'] ?? 1);
  $num_contrato = 'HIP-' . date('Ymd') . '-' . str_pad($id_cliente, 4, '0', STR_PAD_LEFT);

  // Validar que la fecha sea válida
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || !strtotime($fecha)) {
    $fecha = date('Y-m-d');
  }

  // mínimos desde tipo_prestamo id=2 (usado como "Hipotecario/Comercial")
  $row = $db->query("SELECT tasa_interes, monto_minimo FROM tipo_prestamo WHERE id_tipo_prestamo=2")->fetch_assoc();
  $min = (float)($row['monto_minimo'] ?? 12000);

  $monto_dop = moneyDOP($db, $monto_user, $id_moneda);
  if ($monto_dop < $min) out(['ok'=>false,'msg'=>'Monto menor al mínimo establecido']);
  if ($porc > 80) out(['ok'=>false,'msg'=>'El porcentaje financiado no puede exceder 80%']);
  if ($monto_dop > ($valor * $porc/100)) out(['ok'=>false,'msg'=>'Monto supera el % permitido sobre el valor del inmueble']);

  $db->autocommit(false);
  try {
    // Crear condición del préstamo primero
    [$st2,$e2] = q($db, "INSERT INTO condicion_prestamo (tasa_interes,id_tipo_amortizacion,id_periodo_pago,vigente_desde,esta_activo) VALUES (?,?,?,CURDATE(),1)",
                  [$tasa,$id_amortizacion,$id_periodo],'dii');
    if(!$st2) throw new Exception($e2);
    $id_cond = $db->insert_id;

    // Crear préstamo
    [$st,$err] = q($db, "INSERT INTO prestamo (id_cliente,id_tipo_prestamo,numero_contrato,monto_solicitado,fecha_solicitud,plazo_meses,id_estado_prestamo,id_condicion_actual,creado_por) VALUES (?,?,?,?,?,?,2,?,1)",
                  [$id_cliente,2,$num_contrato,$monto_dop,$fecha,$plazo,$id_cond],'issdiii');
    if(!$st) throw new Exception($err);
    $id_p = $db->insert_id;

    // Crear detalle hipotecario
    q($db, "INSERT INTO prestamo_hipotecario (id_prestamo,valor_propiedad,porcentaje_financiamiento,direccion_propiedad) VALUES (?,?,?,?)",
       [$id_p,$valor,$porc,$dir],'idds');

    // Generar cronograma usando stored procedure
    generar_cronograma_prestamo($db, $id_p);

    $db->commit();
    out(['ok'=>true,'id_prestamo'=>$id_p,'numero_contrato'=>$num_contrato]);
  } catch (Exception $e) {
    $db->rollback();
    out(['ok'=>false,'msg'=>$e->getMessage()]);
  } finally {
    $db->autocommit(true);
  }
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
           cp.tasa_interes, cp.id_periodo_pago, cp.id_tipo_amortizacion,
           (SELECT periodo FROM cat_periodo_pago WHERE id_periodo_pago=cp.id_periodo_pago) AS periodo_txt,
           (SELECT tipo_amortizacion FROM cat_tipo_amortizacion WHERE id_tipo_amortizacion=cp.id_tipo_amortizacion) AS amortizacion_txt
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
  $cron = $db->query("SELECT numero_cuota, fecha_vencimiento, capital_cuota, interes_cuota, cargos_cuota, total_monto, saldo_cuota, estado_cuota FROM cronograma_cuota WHERE id_prestamo=$id ORDER BY numero_cuota ASC")->fetch_all(MYSQLI_ASSOC);
  // resumen cronograma
  $resumen = $db->query("SELECT total_capital, total_interes, total_pagar FROM resumen_cronograma WHERE id_prestamo=$id")->fetch_assoc() ?: [];
  out(['ok'=>true,'data'=>$row,'cronograma'=>$cron,'resumen'=>$resumen]);
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
if ($act==='amortizacion'){
  $r = $db->query("SELECT id_tipo_amortizacion AS id, tipo_amortizacion AS txt FROM cat_tipo_amortizacion")->fetch_all(MYSQLI_ASSOC);
  out(['ok'=>true,'data'=>$r]);
}

out(['ok'=>false,'msg'=>'Acción no reconocida']);
