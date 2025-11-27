<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

$root = dirname(__DIR__);
require_once $root . '/config/db.php'; // Debe exponer $conn (mysqli)

/* -------- Helpers -------- */
function rq(string $k, $def = null) {
  return $_POST[$k] ?? $def;
}
function ok(array $data = []) { echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
function bad(string $msg, int $code = 400) { http_response_code($code); ok(['ok'=>false,'msg'=>$msg]); }

if (!function_exists('column_exists')) {
  function column_exists(mysqli $conn, string $table, string $column): bool {
    $db = $conn->real_escape_string($conn->query("SELECT DATABASE() db")->fetch_assoc()['db'] ?? '');
    $sql = "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1";
    $st = $conn->prepare($sql);
    $st->bind_param('sss', $db, $table, $column);
    $st->execute();
    $r = $st->get_result()->fetch_row();
    $st->close();
    return (bool)$r;
  }
}
function bind_dynamic(mysqli_stmt $st, string $types, array &$params): void {
  // mysqli::bind_param necesita referencias
  $refs = [];
  foreach ($params as $k => &$v) { $refs[$k] = &$v; }
  array_unshift($refs, $types);
  call_user_func_array([$st, 'bind_param'], $refs);
}
function num_or_null($v){ return is_numeric($v) ? (int)$v : null; }

function current_user_id(): ?int {
  if (session_status() === PHP_SESSION_NONE) {
    @session_start();
  }
  if (isset($_SESSION['usuario']['id_usuario']) && is_numeric((string)$_SESSION['usuario']['id_usuario'])) {
    return (int)$_SESSION['usuario']['id_usuario'];
  }
  return null;
}

function require_user_id(): int {
  $id = current_user_id();
  if (!$id) {
    bad('Sesión no válida. Inicia sesión nuevamente.', 401);
  }
  return $id;
}

$action = (string)rq('action','');
if ($action === '') bad('Falta action');

if ($action === 'search') {
  $q = trim((string)rq('q',''));
  if ($q === '') ok(['ok'=>true,'data'=>[]]);

  $like = "%{$q}%";
  $digits = preg_replace('/\D+/', '', $q);
  $hasDigits = ($digits !== '');
  $isNumId   = ctype_digit($q);
  $hasContrato = column_exists($conn, 'prestamo', 'numero_contrato');

  $sql = "SELECT 
            p.id_prestamo,
            dp.nombre, dp.apellido,
            di.numero_documento,
            ce.estado AS estado_prestamo
          FROM prestamo p
          JOIN cliente c        ON c.id_cliente = p.id_cliente
          JOIN datos_persona dp ON dp.id_datos_persona = c.id_datos_persona
          LEFT JOIN documento_identidad di ON di.id_datos_persona = dp.id_datos_persona
          LEFT JOIN cat_estado_prestamo ce ON ce.id_estado_prestamo = p.id_estado_prestamo
          WHERE (
                dp.nombre LIKE ?
            OR  dp.apellido LIKE ?
            OR  di.numero_documento LIKE ?"
          . ($hasContrato ? " OR p.numero_contrato LIKE ?" : "")
          . ($hasDigits   ? " OR REPLACE(di.numero_documento,'-','') LIKE ?" : "")
          . ($isNumId     ? " OR p.id_prestamo = ?" : "")
          . ")
          GROUP BY p.id_prestamo, dp.nombre, dp.apellido, di.numero_documento, ce.estado
          ORDER BY p.id_prestamo DESC
          LIMIT 50";

  $st = $conn->prepare($sql);
  $types = 'sss'; $params = [$like,$like,$like];
  if ($hasContrato){ $types.='s'; $params[]=$like; }
  if ($hasDigits)  { $types.='s'; $params[]="%{$digits}%"; }
  if ($isNumId)    { $types.='i'; $params[]=(int)$q; }
  bind_dynamic($st, $types, $params);
  $st->execute();
  $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
  $st->close();

  ok(['ok'=>true,'data'=>$rows]);
}

/*
   SUMMARY: resumen de préstamo + cuota actual + mora
 */
if ($action === 'summary') {
  $id = num_or_null(rq('id_prestamo'));
  if (!$id) bad('id_prestamo inválido');
  // Datos principales del préstamo
$sql = "SELECT 
            p.id_prestamo,
            ce.estado,
            COALESCE(SUM(cu.saldo_cuota),0) AS saldo_total
        FROM prestamo p
        LEFT JOIN cronograma_cuota cu 
          ON cu.id_prestamo = p.id_prestamo
        LEFT JOIN cat_estado_prestamo ce 
          ON ce.id_estado_prestamo = p.id_estado_prestamo
        WHERE p.id_prestamo = ?
        GROUP BY p.id_prestamo, ce.estado";
  $st = $conn->prepare($sql);
  $st->bind_param('i', $id);
  $st->execute();
  $resumen = $st->get_result()->fetch_assoc() ?: [];
  $st->close();

  // Cuota actual (vencida o la siguiente pendiente)
  $sql = "SELECT 
            id_cronograma_cuota,
            numero_cuota,
            fecha_vencimiento,
            capital_cuota    AS capital,
            interes_cuota    AS interes,
            cargos_cuota     AS cargos,
            total_monto      AS cuota_a_pagar,
            saldo_cuota      AS saldo_restante,
            estado_cuota
          FROM cronograma_cuota
          WHERE id_prestamo=?
          ORDER BY 
            (estado_cuota='Vencida') DESC,
            (estado_cuota='Pendiente') DESC,
            fecha_vencimiento ASC
          LIMIT 1";
  $st = $conn->prepare($sql);
  $st->bind_param('i', $id);
  $st->execute();
  $cuota = $st->get_result()->fetch_assoc() ?: null;
  $st->close();

  // Normalizar tipos numéricos de la cuota para que el frontend reciba números
  if ($cuota) {
    $cuota['numero_cuota'] = isset($cuota['numero_cuota']) ? (int)$cuota['numero_cuota'] : null;
    $cuota['capital']      = (float)($cuota['capital'] ?? 0);
    $cuota['interes']      = (float)($cuota['interes'] ?? 0);
    $cuota['cargos']       = (float)($cuota['cargos'] ?? 0);
    $cuota['cuota_a_pagar']  = (float)($cuota['cuota_a_pagar'] ?? 0);
    $cuota['saldo_restante'] = (float)($cuota['saldo_restante'] ?? 0);
  }
  $st = $conn->prepare("SELECT COALESCE(SUM(cargos_cuota),0) AS mora_total
                      FROM cronograma_cuota
                      WHERE id_prestamo=? AND estado_cuota='Vencida'");
  $st->bind_param('i', $id);
  $st->execute();
  $mora = (float)($st->get_result()->fetch_assoc()['mora_total'] ?? 0);
  $st->close();

  ok(['ok'=>true,'resumen'=>[
        'id_prestamo'=>$id,
        'estado'=>$resumen['estado'] ?? '',
        'saldo_total'=>(float)($resumen['saldo_total'] ?? 0),
        'cuota_actual'=>$cuota
      ],
      'mora'=>['mora_total'=>$mora]
    ]);
} 

/*  registra pago (efectivo/transferencia)
   */
if ($action === 'calc_mora') {
  $id = num_or_null(rq('id_prestamo')); if (!$id) bad('id_prestamo inválido');
  $st = $conn->prepare("SELECT COALESCE(SUM(cargos_cuota),0) AS mora_total
                      FROM cronograma_cuota
                      WHERE id_prestamo=? AND estado_cuota='Vencida'");
  $st->bind_param('i', $id);
  $st->execute();
  $mora = (float)($st->get_result()->fetch_assoc()['mora_total'] ?? 0);
  $st->close();
  ok(['ok'=>true,'mora_total'=>$mora]);
}

if ($action === 'cronograma') {
  $id = num_or_null(rq('id_prestamo')); 
  if (!$id) bad('id_prestamo invalido');
  $st = $conn->prepare("SELECT id_cronograma_cuota, numero_cuota, fecha_vencimiento, capital_cuota, interes_cuota, cargos_cuota, total_monto, saldo_cuota, estado_cuota 
                      FROM cronograma_cuota
                      WHERE id_prestamo = ?
                      ORDER BY numero_cuota ASC");
  $st->bind_param('i', $id);
  $st->execute();
  $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
  $st->close();

  foreach ($rows as &$r){
    $r['capital_cuota'] = (float)($r['capital_cuota'] ?? 0);
    $r['interes_cuota'] = (float)($r['interes_cuota'] ?? 0);
    $r['cargos_cuota']  = (float)($r['cargos_cuota'] ?? 0);
    $r['total_monto']   = (float)($r['total_monto'] ?? 0);
    $r['saldo_cuota']   = (float)($r['saldo_cuota'] ?? 0);
  }
  ok(['ok'=>true,'cronograma'=>$rows]);
}

if ($action === 'pay'){
  $id_prestamo = num_or_null(rq('id_prestamo')); if(!$id_prestamo) bad('id_prestamo invalido');
  $metodo = (string)rq('metodo', ''); if ($metodo === '') bad('metodo requerido');
  $monto = (float)rq('monto', 0); if ($monto <= 0) bad('monto invalido');
  $id_moneda = num_or_null(rq('id_tipo_moneda', 1)) ?? 1;
  $ref = (string)rq('referencia', '');
  $obs = (string)rq('observacion', '');
  
  $st = $conn->prepare("SELECT valor, tipo_moneda 
  FROM cat_tipo_moneda 
  WHERE id_tipo_moneda=? LIMIT 1");
  $st->bind_param('i', $id_moneda);
  $st->execute();
  $tm = $st->get_result()->fetch_assoc();
  $st->close();

  $factor = (float)($tm['valor'] ?? 1.0);
  $moneda_nombre = (string)($tm['tipo_moneda']?? 'DOP');
  $monto_dop = round($monto * $factor, 2);

  $id_usuario = require_user_id();

  $map = ['Efectivo' => 1, 'Transferencia' => 2, 'Cheque' => 3];
  $id_metodo = $map[$metodo] ?? 1;

  $conn->begin_transaction();
  try {
    $sql = "INSERT INTO pago (id_prestamo, fecha_pago, monto_pagado, metodo_pago, id_tipo_moneda, creado_por)
          VALUES (?,current_date(),?,?,?,?)";
    $st = $conn->prepare($sql);
    $st->bind_param('idiii', $id_prestamo, $monto_dop, $id_metodo, $id_moneda, $id_usuario);
    $st->execute();
    $id_pago = (int) $st->insert_id;
    $st->close();

    if ($metodo === 'Transferencia' && column_exists($conn, 'pago_transferencia', 'codigo_transferencia')){
      $sql = "INSERT INTO pago_transferencia (id_pago, codigo_transferencia, banco) 
      VALUES (?,?,'N/A')";
      $st = $conn->prepare($sql);
      $st->bind_param('is', $id_pago, $ref);
      $st->execute();
      $st->close();
    }

    $sql_cuotas = "SELECT id_cronograma_cuota, numero_cuota, capital_cuota, interes_cuota, cargos_cuota, total_monto, saldo_cuota
            FROM  cronograma_cuota
            WHERE id_prestamo = ? AND total_monto > 0
            ORDER BY fecha_vencimiento ASC";
    $st = $conn->prepare($sql_cuotas);
    $st->bind_param('i', $id_prestamo);
    $st->execute();
    $res_cuotas = $st->get_result();
    $cuotas = $res_cuotas->fetch_all(MYSQLI_ASSOC);
    $st->close();

    $restante = $monto_dop;
    $cuotas_afectadas = 0;
    $numero_cuota = null;

    foreach ($cuotas as $c){
      if ($restante <= 0.009) break;

      $id_cuota = (int)$c['id_cronograma_cuota'];
      $sql_pago = "SELECT tipo_asignacion, SUM(monto_asignado) AS total
                  FROM asignacion_pago
                  WHERE id_cronograma_cuota = ?
                  GROUP BY tipo_asignacion";
      $st_p = $conn->prepare($sql_pago);
      $st_p->bind_param('i', $id_cuota);
      $st_p->execute();
      $rows_pago = $st_p->get_result()->fetch_all(MYSQLI_ASSOC);
      $st_p->close();

      $pagado_cargos = 0.0;
      $pagado_interes = 0.0;
      $pagado_capital = 0.0;

      foreach ($rows_pago as $rp) {
        if($rp['tipo_asignacion'] === 'Cargos') $pagado_cargos = (float)$rp['total'];
        if($rp['tipo_asignacion'] === 'Intereses') $pagado_interes = (float)$rp['total'];
        if($rp['tipo_asignacion'] === 'Capital') $pagado_capital = (float)$rp['total'];
      }

      $cargos_pendiente = max(0.0, (float)$c['cargos_cuota'] - $pagado_cargos);
      $interes_pendiente = max(0.0, (float)$c['interes_cuota'] - $pagado_interes);
      $capital_pendiente = max(0.0, (float)$c['capital_cuota'] - $pagado_capital);

      $insert = $conn->prepare("INSERT INTO asignacion_pago (id_pago, id_cronograma_cuota, monto_asignado, tipo_asignacion)
                      VALUES (?,?,?,?)");
      

      $monto_a_cargos = 0.0;
      if($cargos_pendiente > 0 && $restante > 0){
        $monto_a_cargos = min($restante, $cargos_pendiente);
        $tipo = 'Cargos';
        $insert->bind_param('iids', $id_pago, $id_cuota, $monto_a_cargos, $tipo);
        $insert->execute();
        $restante -= $monto_a_cargos;
      }

      $monto_a_interes = 0.0;
      if($interes_pendiente > 0 && $restante > 0){
        $monto_a_interes = min($restante, $interes_pendiente);
        $tipo = 'Intereses';
        $insert->bind_param('iids', $id_pago, $id_cuota, $monto_a_interes, $tipo);
        $insert->execute();
        $restante -= $monto_a_interes;
      }

      $monto_a_capital = 0.0;
      if($capital_pendiente > 0 && $restante > 0){
        $monto_a_capital = min($restante, $capital_pendiente);
        $tipo = 'Capital';
        $insert->bind_param('iids', $id_pago, $id_cuota, $monto_a_capital, $tipo);
        $insert->execute();
        $restante -= $monto_a_capital;
      }
      $insert->close();

      $total_abonado_hoy = $monto_a_cargos + $monto_a_interes + $monto_a_capital;

      $saldo_antes = max(0.0, ($c['capital_cuota'] + $c['interes_cuota'] + $c['cargos_cuota']) - ($pagado_capital + $pagado_interes + $pagado_cargos));
      $nuevo_saldo_cuota = max(0.0, $saldo_antes - $total_abonado_hoy);
      $numero_cuota = $c['numero_cuota'];

      $cambio_estado = ($nuevo_saldo_cuota <= 0.01) ? 'Pagada' : 'Pendiente';

      $st_upd = $conn->prepare("UPDATE cronograma_cuota
                              SET total_monto = ?, estado_cuota = ?
                              WHERE id_cronograma_cuota = ?");
      $st_upd->bind_param('dsi', $nuevo_saldo_cuota, $cambio_estado, $id_cuota);
      $st_upd->execute();
      $st_upd->close();

      $cuotas_afectadas ++;
    }
    $conn->commit();

    $nombre = '';
    $apellido = '';
    $st_p = $conn->prepare("SELECT dp.nombre, dp.apellido
                            FROM prestamo p
                            JOIN cliente c ON c.id_cliente = p.id_cliente
                            JOIN datos_persona dp ON dp.id_datos_persona = c.id_datos_persona
                            WHERE p.id_prestamo = ? LIMIT 1");
    if ($st_p) {
      $st_p->bind_param('i', $id_prestamo);
      $st_p->execute();
      $r = $st_p->get_result()->fetch_assoc();
      if ($r) {
        $nombre = $r['nombre'] ?? '';
        $apellido = $r['apellido'] ?? '';
      }
      $st_p->close();
    }


    ok(['ok'=>true,
        'id_pago'=>$id_pago,
        'comprobante'=>[
          'id_prestamo'=>$id_prestamo,
          'metodo'=>$metodo,
          'moneda'=>$moneda_nombre,
          'monto'=>$monto,
          'monto_dop'=>$monto_dop,
          'referencia'=>$ref,
          'observacion'=>$obs,
          'cuotas_afectadas'=>$cuotas_afectadas,
          'fecha' => date('Y-m-d H:i'),
          'numero_cuota'=>$numero_cuota,
          'nombre'=>$nombre,
          'apellido'=>$apellido,
          'capital' =>$pagado_capital,
          'interes' =>$pagado_interes,
          'mora' =>$pagado_cargos
        ]
      ]);
  } catch (Exception $e) {
    $conn->rollback();
    bad('Error al registrar el pago: ' . $e->getMessage());
  }
}

/*
   GARANTÍA: por ahora no implementado → se deshabilita en UI */
if ($action === 'garantia') {
  bad('El uso de garantía aún no está implementado en este módulo.');
}

/*
   CLOSE: cerrar préstamo si saldo total = 0
 */
if ($action === 'close') {
  $id = num_or_null(rq('id_prestamo')); if (!$id) bad('id_prestamo inválido');
  $obs = (string)rq('observacion','');

  require_user_id();

  $sql = "SELECT COALESCE(SUM(saldo_cuota),0) saldo FROM cronograma_cuota WHERE id_prestamo=?";
  $st = $conn->prepare($sql);
  $st->bind_param('i', $id);
  $st->execute();
  $saldo = (float)($st->get_result()->fetch_assoc()['saldo'] ?? 0);
  $st->close();

  if ($saldo > 0.0001) bad('No se puede cerrar: aún existe saldo pendiente.');

  $sql = "SELECT id_estado_prestamo FROM cat_estado_prestamo WHERE estado='Cerrado' LIMIT 1";
  $st = $conn->prepare($sql);
  $st->execute();
  $row = $st->get_result()->fetch_assoc();
  $st->close();
  $id_estado = (int)($row['id_estado_prestamo'] ?? 0);
  if ($id_estado <= 0) {
    bad("No se encontró el estado 'Cerrado' en cat_estado_prestamo.");
  }

  $st = $conn->prepare("UPDATE prestamo SET id_estado_prestamo=? WHERE id_prestamo=?");
  $st->bind_param('ii', $id_estado, $id);
  $st->execute();
  $st->close();

  ok(['ok'=>true,'comprobante_cierre'=>[
    'id_prestamo'=>$id,
    'fecha'=>date('Y-m-d H:i'),
    'observacion'=>$obs
  ]]);
}

bad('Action no soportada', 404);
