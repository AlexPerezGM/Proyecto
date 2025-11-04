<?php
// api/restructuracion.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php'; // usa tu conexión actual

function j($ok, $data=[], $extra=[]) {
  echo json_encode(array_merge(['ok'=>$ok], $extra, $data ? ['data'=>$data] : []));
  exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'list';

// Asegurar tabla de control de reestructuración (casos aprobados/pendientes)
@$conn->query("
  CREATE TABLE IF NOT EXISTS restructuracion_prestamo (
    id_restructuracion INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_prestamo INT NOT NULL,
    estado ENUM('Pendiente','Aprobada','Ejecutada') NOT NULL DEFAULT 'Aprobada',
    motivo TEXT NULL,
    aprobado_por INT NULL,
    fecha_aprobacion DATE NULL,
    ejecutada TINYINT(1) NOT NULL DEFAULT 0,
    fecha_ejecucion DATETIME NULL,
    INDEX (id_prestamo)
  ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;
");

if ($action === 'list') {
  $q    = trim($_POST['q'] ?? $_GET['q'] ?? '');
  $page = max(1, (int)($_POST['page'] ?? $_GET['page'] ?? 1));
  $size = max(1, min(50, (int)($_POST['size'] ?? $_GET['size'] ?? 10)));
  $off  = ($page - 1) * $size;

  $where = "rp.estado IN ('Aprobada','Pendiente') AND rp.ejecutada=0";
  $params = [];
  if ($q !== '') {
    $where .= " AND (dp.nombre LIKE ? OR dp.apellido LIKE ? OR di.numero_documento LIKE ?)";
    $like = "%$q%"; $params = [$like,$like,$like];
  }

  $sqlCount = "
    SELECT COUNT(*) AS c
    FROM restructuracion_prestamo rp
    JOIN prestamo p ON p.id_prestamo = rp.id_prestamo
    JOIN cliente c ON c.id_cliente = p.id_cliente
    JOIN datos_persona dp ON dp.id_datos_persona = c.id_datos_persona
    LEFT JOIN documento_identidad di ON di.id_datos_persona = dp.id_datos_persona
    WHERE $where
  ";
  $stmt = $conn->prepare($q!=='' ? $sqlCount : str_replace(' ?','',$sqlCount));
  if ($q!=='') { $stmt->bind_param('sss', ...$params); }
  $stmt->execute(); $res = $stmt->get_result()->fetch_assoc(); $total = (int)$res['c'];
  $stmt->close();

  $sql = "
    SELECT
      p.id_prestamo, p.monto_solicitado, p.plazo_meses, p.id_estado_prestamo,
      dp.nombre, dp.apellido,
      COALESCE(di.numero_documento,'') AS numero_documento
    FROM restructuracion_prestamo rp
    JOIN prestamo p ON p.id_prestamo = rp.id_prestamo
    JOIN cliente c ON c.id_cliente = p.id_cliente
    JOIN datos_persona dp ON dp.id_datos_persona = c.id_datos_persona
    LEFT JOIN documento_identidad di ON di.id_datos_persona = dp.id_datos_persona
    WHERE $where
    ORDER BY rp.id_restructuracion DESC
    LIMIT $off, $size
  ";
  $stmt = $conn->prepare($q!=='' ? $sql : str_replace(' ?','',$sql));
  if ($q!=='') { $stmt->bind_param('sss', ...$params); }
  $stmt->execute(); $rs = $stmt->get_result();
  $data = [];
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
  $stmt->close();
  j(true, $data, ['total'=>$total]);
}

if ($action === 'get') {
  $id_prestamo = (int)($_POST['id_prestamo'] ?? $_GET['id_prestamo'] ?? 0);
  if (!$id_prestamo) j(false, [], ['msg'=>'id_prestamo requerido']);

  // Datos básicos del préstamo y cliente
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

  // Tasa vigente (de condicion_prestamo si existe; si no, intenta tipo_prestamo)
  $tasa = null; $periodo = null; $tipo_amort = null;
  if (!empty($p['id_condicion_actual'])) {
    $sql = "SELECT tasa_interes, id_periodo_pago, tipo_amortizacion FROM condicion_prestamo WHERE id_condicion_prestamo=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $p['id_condicion_actual']);
    $stmt->execute(); $c = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if ($c){ $tasa = (float)$c['tasa_interes']; $periodo = (int)$c['id_periodo_pago']; $tipo_amort = $c['tipo_amortizacion']; }
  }
  if ($tasa===null) {
    $sql = "
      SELECT tp.tasa_interes, tp.tipo_amortizacion
      FROM prestamo p JOIN tipo_prestamo tp ON tp.id_tipo_prestamo = p.id_tipo_prestamo
      WHERE p.id_prestamo=?
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id_prestamo);
    $stmt->execute(); $tp = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if ($tp){ $tasa = (float)$tp['tasa_interes']; $tipo_amort = $tp['tipo_amortizacion']; }
  }

  // Próxima cuota pendiente (si hay)
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
  $id_prestamo   = (int)($_POST['id_prestamo'] ?? 0);
  $nuevo_monto   = (float)($_POST['nuevo_monto'] ?? 0);
  $nueva_tasa    = isset($_POST['nueva_tasa']) && $_POST['nueva_tasa'] !== '' ? (float)$_POST['nueva_tasa'] : null;
  $nuevo_plazo   = (int)($_POST['nuevo_plazo'] ?? 0);
  $nueva_fecha   = trim($_POST['nueva_fecha'] ?? '');
  $motivo        = trim($_POST['motivo'] ?? '');
  if (!$id_prestamo) j(false, [], ['msg'=>'id_prestamo requerido']);
  if ($nuevo_monto <= 0 || $nuevo_plazo <= 0) j(false, [], ['msg'=>'Monto y plazo deben ser > 0']);

  // 1) Desactivar condición vigente (si hay) y crear nueva condición con la nueva tasa (si viene)
  $condAnteriorId = null; $periodoPago = null; $tipoAmort = 'Frances';
  $stmt = $conn->prepare("SELECT id_condicion_actual FROM prestamo WHERE id_prestamo=?");
  $stmt->bind_param('i', $id_prestamo);
  $stmt->execute(); $row = $stmt->get_result()->fetch_assoc(); $stmt->close();

  if (!empty($row['id_condicion_actual'])) {
    $condAnteriorId = (int)$row['id_condicion_actual'];
    @$conn->query("UPDATE condicion_prestamo SET esta_activo=0, vigente_hasta=CURDATE() WHERE id_condicion_prestamo=".$condAnteriorId." LIMIT 1");
    // rescatar periodo/tipo para reusar
    $rs = $conn->query("SELECT id_periodo_pago, tipo_amortizacion FROM condicion_prestamo WHERE id_condicion_prestamo=".$condAnteriorId." LIMIT 1");
    if ($rs && $c = $rs->fetch_assoc()) { $periodoPago = $c['id_periodo_pago']; $tipoAmort = $c['tipo_amortizacion']; }
  } else {
    // intentar desde tipo_prestamo si no existía condición previa
    $rs = $conn->query("
      SELECT tp.tipo_amortizacion FROM prestamo p JOIN tipo_prestamo tp ON tp.id_tipo_prestamo=p.id_tipo_prestamo
      WHERE p.id_prestamo=".$id_prestamo." LIMIT 1
    ");
    if ($rs && $c = $rs->fetch_assoc()) { $tipoAmort = $c['tipo_amortizacion']; }
  }

  $newCondId = null;
  if ($nueva_tasa !== null) {
    $stmt = $conn->prepare("
      INSERT INTO condicion_prestamo (id_prestamo, tasa_interes, tipo_interes, tipo_amortizacion, id_periodo_pago, vigente_desde, esta_activo)
      VALUES (?, ?, 'Nominal', ?, ?, CURDATE(), 1)
    ");
    $stmt->bind_param('idsii', $id_prestamo, $nueva_tasa, $tipoAmort, $periodoPago);
    $stmt->execute(); $newCondId = $stmt->insert_id; $stmt->close();
    @$conn->query("UPDATE prestamo SET id_condicion_actual=".$newCondId." WHERE id_prestamo=".$id_prestamo." LIMIT 1");
  }

  // 2) Actualizar monto/plazo del préstamo
  $stmt = $conn->prepare("UPDATE prestamo SET monto_solicitado=?, plazo_meses=?, actualizado_en=CURRENT_TIMESTAMP WHERE id_prestamo=?");
  $stmt->bind_param('dii', $nuevo_monto, $nuevo_plazo, $id_prestamo);
  $stmt->execute(); $stmt->close();

  // 3) Reprogramar próxima cuota (opcional)
  if ($nueva_fecha !== '') {
    $stmt = $conn->prepare("
      UPDATE cronograma_cuota
      SET fecha_vencimiento=?
      WHERE id_prestamo=? AND estado_cuota='Pendiente'
      ORDER BY numero_cuota ASC
      LIMIT 1
    ");
    $stmt->bind_param('si', $nueva_fecha, $id_prestamo);
    $stmt->execute(); $stmt->close();
  }

  // 4) Marcar la reestructuración como ejecutada (si existía una fila aprobada)
  $stmt = $conn->prepare("
    UPDATE restructuracion_prestamo
    SET ejecutada=1, estado='Ejecutada', motivo=IF(?<>'',?,motivo), fecha_ejecucion=NOW()
    WHERE id_prestamo=? AND ejecutada=0
  ");
  $stmt->bind_param('ssi', $motivo, $motivo, $id_prestamo);
  $stmt->execute(); $stmt->close();

  j(true, [], ['msg'=>'Préstamo reestructurado correctamente']);
}

j(false, [], ['msg'=>'Acción no válida']);
