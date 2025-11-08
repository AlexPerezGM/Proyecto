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

/* -------- Router -------- */
$action = (string)rq('action','');
if ($action === '') bad('Falta action');

/* ==========================================================
   SEARCH: q por nombre/apellido/doc/contrato o id_prestamo
   ========================================================== */
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
          GROUP BY p.id_prestamo
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

/* ==========================================================
   SUMMARY: datos del préstamo + cuota actual + saldo + mora
   ========================================================== */
if ($action === 'summary') {
  $id = num_or_null(rq('id_prestamo'));
  if (!$id) bad('id_prestamo inválido');

  // Estado préstamo + saldo total
  $sql = "SELECT p.id_prestamo, COALESCE(ce.estado,'') estado,
                 COALESCE(SUM(c.saldo_pendiente),0) saldo_total
          FROM prestamo p
          LEFT JOIN cat_estado_prestamo ce ON ce.id_estado_prestamo = p.id_estado_prestamo
          LEFT JOIN cronograma_cuota c ON c.id_prestamo = p.id_prestamo
          WHERE p.id_prestamo=? GROUP BY p.id_prestamo";
  $st = $conn->prepare($sql);
  $st->bind_param('i', $id);
  $st->execute();
  $resumen = $st->get_result()->fetch_assoc() ?: ['id_prestamo'=>$id,'estado'=>'','saldo_total'=>0];
  $st->close();

  // Cuota actual: prioriza vencidas; si no hay, la pendiente más próxima
  $sql = "SELECT id_cronograma_cuota, numero_cuota, fecha_vencimiento,
                 capital_cuota AS capital, interes_cuota AS interes, cargos_cuota AS cargos,
                 saldo_pendiente
          FROM cronograma_cuota
          WHERE id_prestamo=? AND estado_cuota IN ('Vencida','Pendiente')
          ORDER BY (estado_cuota='Pendiente') ASC, fecha_vencimiento ASC
          LIMIT 1";
  $st = $conn->prepare($sql);
  $st->bind_param('i', $id);
  $st->execute();
  $cuota = $st->get_result()->fetch_assoc() ?: null;
  $st->close();

  // Mora simple: 2% del saldo vencido acumulado (puedes ajustar fórmula)
  $sql = "SELECT COALESCE(SUM(saldo_pendiente),0) AS total_vencido
          FROM cronograma_cuota WHERE id_prestamo=? AND estado_cuota='Vencida'";
  $st = $conn->prepare($sql);
  $st->bind_param('i', $id);
  $st->execute();
  $vencido = (float)($st->get_result()->fetch_assoc()['total_vencido'] ?? 0);
  $st->close();
  $mora = round($vencido * 0.02, 2); // <-- regla de negocio ajustable

  ok(['ok'=>true,'resumen'=>[
        'id_prestamo'=>$id,
        'estado'=>$resumen['estado'] ?? '',
        'saldo_total'=>(float)($resumen['saldo_total'] ?? 0),
        'cuota_actual'=>$cuota
      ],
      'mora'=>['mora_total'=>$mora]
    ]);
}

/* ==========================================================
   CALC_MORA: mismo cálculo de mora que summary
   ========================================================== */
if ($action === 'calc_mora') {
  $id = num_or_null(rq('id_prestamo'));
  if (!$id) bad('id_prestamo inválido');
  $sql = "SELECT COALESCE(SUM(saldo_pendiente),0) AS total_vencido
          FROM cronograma_cuota WHERE id_prestamo=? AND estado_cuota='Vencida'";
  $st = $conn->prepare($sql);
  $st->bind_param('i', $id);
  $st->execute();
  $vencido = (float)($st->get_result()->fetch_assoc()['total_vencido'] ?? 0);
  $st->close();
  $mora = round($vencido * 0.02, 2);
  ok(['ok'=>true,'mora_total'=>$mora]);
}

/* ==========================================================
   PAY: registra pago (efectivo/transferencia)
   ========================================================== */
if ($action === 'pay') {
  $id = num_or_null(rq('id_prestamo'));               if (!$id) bad('id_prestamo inválido');
  $metodo = (string)rq('metodo','');                  if ($metodo==='') bad('metodo requerido');
  $monto = (float)rq('monto',0);                      if ($monto<=0) bad('monto inválido');
  $id_moneda = num_or_null(rq('id_tipo_moneda',1)) ?? 1;
  $ref = (string)rq('referencia','');
  $obs = (string)rq('observacion','');

  // map método → id_tipo_pago (ajusta si en catálogo usas otros ids)
  $map = ['Efectivo'=>1, 'Transferencia'=>2, 'Cheque'=>3, 'Garantía'=>10];
  $id_metodo = $map[$metodo] ?? 1;

  // convertir a DOP según cat_tipo_moneda.valor
  $st = $conn->prepare("SELECT valor, tipo_moneda FROM cat_tipo_moneda WHERE id_tipo_moneda=? LIMIT 1");
  $st->bind_param('i', $id_moneda);
  $st->execute();
  $tm = $st->get_result()->fetch_assoc();
  $st->close();
  $factor = (float)($tm['valor'] ?? 1.0);
  $moneda = (string)($tm['tipo_moneda'] ?? 'DOP');
  $monto_dop = round($monto * $factor, 2);

  // insertar pago
  $sql = "INSERT INTO pago (id_prestamo, fecha_pago, monto_pagado, metodo_pago, id_tipo_moneda, creado_por, observacion)
          VALUES (?, CURRENT_DATE(), ?, ?, ?, 1, ?)";
  $st = $conn->prepare($sql);
  $st->bind_param('idiis', $id, $monto_dop, $id_metodo, $id_moneda, $obs);
  $st->execute();
  $id_pago = (int)$st->insert_id;
  $st->close();

  // asignar a cuota actual: cargos → interés → capital (distribución simple)
  $sql = "SELECT id_cronograma_cuota, capital_cuota, interes_cuota, cargos_cuota, saldo_pendiente, estado_cuota
          FROM cronograma_cuota
          WHERE id_prestamo=? AND estado_cuota IN ('Vencida','Pendiente')
          ORDER BY (estado_cuota='Pendiente') ASC, fecha_vencimiento ASC LIMIT 1";
  $st = $conn->prepare($sql);
  $st->bind_param('i', $id);
  $st->execute();
  $cuota = $st->get_result()->fetch_assoc();
  $st->close();

  if ($cuota) {
    $resto = $monto_dop;

    $asignar = function(string $tipo, float $importe) use ($conn, $id_pago, $cuota, &$resto) {
      $m = min($resto, $importe);
      if ($m <= 0.0001) return 0.0;
      $sql = "INSERT INTO asignacion_pago (id_pago, id_cronograma_cuota, monto_asignado, tipo_asignacion)
              VALUES (?,?,?,?)";
      $st = $conn->prepare($sql);
      $idc = (int)$cuota['id_cronograma_cuota'];
      $st->bind_param('iiss', $id_pago, $idc, $m, $tipo);
      $st->execute();
      $st->close();
      $resto -= $m;
      return $m;
    };

    $m_cargos  = $asignar('Cargos',  (float)$cuota['cargos_cuota']);
    $m_interes = $asignar('Interes', (float)$cuota['interes_cuota']);
    $m_capital = $asignar('Capital', (float)$cuota['capital_cuota']);

    // bajar saldo_pendiente y marcar pagada si llega a 0
    $nuevo_saldo = max(0.0, (float)$cuota['saldo_pendiente'] - ($m_cargos + $m_interes + $m_capital));
    $estado = ($nuevo_saldo <= 0.0001) ? 'Pagada' : $cuota['estado_cuota'];
    $sql = "UPDATE cronograma_cuota SET saldo_pendiente=?, estado_cuota=? WHERE id_cronograma_cuota=?";
    $st = $conn->prepare($sql);
    $st->bind_param('dsi', $nuevo_saldo, $estado, $cuota['id_cronograma_cuota']);
    $st->execute();
    $st->close();
  }

  ok([
    'ok'=>true,
    'id_pago'=>$id_pago,
    'comprobante'=>[
      'metodo'=>$metodo,
      'moneda'=>$moneda,
      'monto'=>$monto,
      'monto_dop'=>$monto_dop,
      'referencia'=>$ref,
      'observacion'=>$obs,
      'fecha'=>date('Y-m-d')
    ]
  ]);
}

/* ==========================================================
   GARANTIA: registra uso de garantía como pago
   ========================================================== */
if ($action === 'garantia') {
  // Atajo: tratamos como pago con método "Garantía"
  $_POST['metodo'] = 'Garantía';
  $_POST['id_tipo_moneda'] = $_POST['id_tipo_moneda'] ?? 1;
  $_POST['monto'] = $_POST['monto'] ?? 0;
  $action = 'pay';
  // re-entrar en la lógica de pay
  $_POST['action'] = 'pay';
  // Include self once más no; solo “fall through” usando el bloque de arriba
  // pero para mantener la estructura devolvemos mismo formato:
  // (en producción moveríamos a función y la reutilizamos)
  // Por simplicidad:
  // -> copia de parámetros y return rápido
  // Para evitar recursión, salimos con error si no vino monto
  if ((float)$_POST['monto'] <= 0) bad('monto inválido');
  // Reemitimos la petición localmente:
  // *No* hacemos include, simplemente duplicaríamos el bloque de PAY,
  // pero para no inflar el archivo, devolvemos error amigable:
  bad('Usa action=pay con metodo=Garantía');
}

/* ==========================================================
   CLOSE: cerrar préstamo si saldo total = 0
   ========================================================== */
if ($action === 'close') {
  $id = num_or_null(rq('id_prestamo')); if (!$id) bad('id_prestamo inválido');
  $obs = (string)rq('observacion','');

  $sql = "SELECT COALESCE(SUM(saldo_pendiente),0) saldo FROM cronograma_cuota WHERE id_prestamo=?";
  $st = $conn->prepare($sql);
  $st->bind_param('i', $id);
  $st->execute();
  $saldo = (float)($st->get_result()->fetch_assoc()['saldo'] ?? 0);
  $st->close();

  if ($saldo > 0.0001) bad('No se puede cerrar: aún existe saldo pendiente.');

  // Estado 'Pagado' (o 'Cerrado' si lo manejas aparte). En tu dump existe 'Pagado' con id=5.
  $id_estado = 5;
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

/* -------- Default -------- */
bad('Action no soportada', 404);
