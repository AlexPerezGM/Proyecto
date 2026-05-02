<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/autorizacion.php';

function dbh(){
  global $conn, $mysqli;
  if ($conn instanceof mysqli){
    $conn-> set_charset('utf8mb4'); return $conn;
  }
  if ($mysqli instanceof mysqli){
    $mysqli-> set_charset('utf8mb4'); return $mysqli;
  }
  return null;
}

function out($arr){
  if (ob_get_length()) { @ob_clean(); }
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}

if (!is_logged()) {
  http_response_code(401);
  out(['ok' => false, 'msg' => 'No autorizado.']);
}
if (!has_permission('prestamos')) {
  http_response_code(403);
  out(['ok' => false, 'msg' => 'No autorizado.']);
}

$db = dbh();
$act = $_POST['action'] ?? $_GET['action'] ?? '';

function q($db, $sql, $params=[], $types=''){
  $st = $db->prepare($sql);
  if(!$st){ return [false, $db->error]; }
  if($params){
    if(!$types){
      $types = '';
      foreach($params as $p){ $types .= is_int($p) || is_float($p) ? 'd' : 's'; }
    }
    $st->bind_param($types, ...$params);
  }
  if(!$st->execute()) return [false, $st->error];
  return [$st, null];
}

function toFloatOrNull($v){
  if ($v === null) return null;
  if (is_string($v)) {
    $v = trim($v);
    if ($v === '') return null;
    $v = str_replace('%', '', $v);
    $v = str_replace(',', '.', $v);
  }
  if (!is_numeric($v)) return null;
  return (float)$v;
}

function toIntOrNull($v){
  if ($v === null) return null;
  if (is_string($v)) {
    $v = trim($v);
    if ($v === '') return null;
  }
  if (!is_numeric($v)) return null;
  return (int)round((float)$v);
}

function normalizeRiskLabel($txt){
  $t = strtolower(trim((string)$txt));
  if ($t === '') return '';
  if (strpos($t, 'baj') !== false) return 'Bajo';
  if (strpos($t, 'med') !== false) return 'Medio';
  if (strpos($t, 'alt') !== false) return 'Alto';
  return '';
}

function riskIdFromLabel($db, $label, $fallback = 2){
  $label = normalizeRiskLabel($label);
  if ($label === '') return (int)$fallback;

  [$st, $err] = q($db, "SELECT id_nivel_riesgo FROM cat_nivel_riesgo WHERE nivel = ? LIMIT 1", [$label], 's');
  if (!$st) return (int)$fallback;

  $row = $st->get_result()->fetch_assoc();
  $st->close();
  return (int)($row['id_nivel_riesgo'] ?? $fallback);
}

function tableColumns($db, $table){
  static $cache = [];
  if (isset($cache[$table])) return $cache[$table];

  $cols = [];
  [$st, $err] = q(
    $db,
    "SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, DATA_TYPE
     FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
    [$table],
    's'
  );

  if ($st) {
    $res = $st->get_result();
    while ($r = $res->fetch_assoc()) {
      $cols[$r['COLUMN_NAME']] = $r;
    }
    $st->close();
  }

  $cache[$table] = $cols;
  return $cols;
}

function firstIdFromTable($db, $table, $idColumn){
  $table = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$table);
  $idColumn = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$idColumn);
  if ($table === '' || $idColumn === '') return null;

  $sql = "SELECT $idColumn AS id FROM $table ORDER BY $idColumn ASC LIMIT 1";
  $res = $db->query($sql);
  if (!$res) return null;
  $row = $res->fetch_assoc();
  return $row ? (int)$row['id'] : null;
}

function inferTypes(array $values){
  $types = '';
  foreach ($values as $v) {
    if (is_int($v)) {
      $types .= 'i';
    } elseif (is_float($v)) {
      $types .= 'd';
    } else {
      $types .= 's';
    }
  }
  return $types;
}

function saveEvaluationProfileFromRequest($db, $id_cliente, $id_prestamo, array $post){
  $id_cliente = (int)$id_cliente;
  $id_prestamo = (int)$id_prestamo;
  if ($id_cliente <= 0 || $id_prestamo <= 0) return;

  $score = toIntOrNull($post['score'] ?? ($post['Score'] ?? null));
  $deudaExterna = toFloatOrNull($post['deuda_externa'] ?? null);
  $usoTarjetas = toFloatOrNull($post['uso_tarjetas'] ?? null);
  $cantidadProductos = toIntOrNull($post['cantidad_productos'] ?? null);
  $gastosMensuales = toFloatOrNull($post['gastos_mensuales'] ?? null);
  $idNivelRiesgo = riskIdFromLabel($db, $post['nivel_riesgo'] ?? '', 2);

  $colsEs = tableColumns($db, 'evaluacion_estrategica');
  if (empty($colsEs)) return;

  $evalWhereCol = isset($colsEs['id_cliente']) ? 'id_cliente' : (isset($colsEs['id_prestamo']) ? 'id_prestamo' : null);
  $evalWhereVal = ($evalWhereCol === 'id_cliente') ? $id_cliente : $id_prestamo;
  if ($evalWhereCol === null) return;

  [$stEs, $errEs] = q(
    $db,
    "SELECT *
     FROM evaluacion_estrategica
     WHERE {$evalWhereCol} = ?
     ORDER BY id_evaluacion_estrategica DESC
     LIMIT 1",
    [$evalWhereVal],
    'i'
  );
  if (!$stEs) return;
  $es = $stEs->get_result()->fetch_assoc();
  $stEs->close();

  if ($gastosMensuales !== null && $gastosMensuales >= 0) {
    [$stIe, $errIe] = q(
      $db,
      "SELECT id_ingresos_egresos, ingresos_mensuales
       FROM ingresos_egresos
       WHERE id_cliente = ?
       ORDER BY id_ingresos_egresos DESC
       LIMIT 1",
      [$id_cliente],
      'i'
    );
    if ($stIe) {
      $ie = $stIe->get_result()->fetch_assoc();
      $stIe->close();
      if ($ie) {
        $ingresos = (float)($ie['ingresos_mensuales'] ?? 0);
        if ($ingresos > 0) {
          $egresosAjustados = min($gastosMensuales, max(0, $ingresos - 1));
          q(
            $db,
            "UPDATE ingresos_egresos SET egresos_mensuales = ? WHERE id_ingresos_egresos = ?",
            [$egresosAjustados, (int)$ie['id_ingresos_egresos']],
            'di'
          );
        }
      }
    }
  }

  $ingresosMensuales = null;
  $egresosMensuales = null;
  [$stIeNow, $errIeNow] = q(
    $db,
    "SELECT ingresos_mensuales, egresos_mensuales
     FROM ingresos_egresos
     WHERE id_cliente = ?
     ORDER BY id_ingresos_egresos DESC
     LIMIT 1",
    [$id_cliente],
    'i'
  );
  if ($stIeNow) {
    $ieNow = $stIeNow->get_result()->fetch_assoc();
    $stIeNow->close();
    if ($ieNow) {
      $ingresosMensuales = toFloatOrNull($ieNow['ingresos_mensuales'] ?? null);
      $egresosMensuales = toFloatOrNull($ieNow['egresos_mensuales'] ?? null);
    }
  }

  if ($score !== null) {
    [$stPc, $errPc] = q(
      $db,
      "SELECT id_puntaje_crediticio, total_transacciones, id_nivel_riesgo
       FROM puntaje_crediticio
       WHERE id_cliente = ?
       ORDER BY id_puntaje_crediticio DESC
       LIMIT 1",
      [$id_cliente],
      'i'
    );

    if ($stPc) {
      $pc = $stPc->get_result()->fetch_assoc();
      $stPc->close();

      if ($pc) {
        $idPc = (int)$pc['id_puntaje_crediticio'];
        $trx = (int)($pc['total_transacciones'] ?? 0);
        $nivel = $idNivelRiesgo > 0 ? $idNivelRiesgo : (int)($pc['id_nivel_riesgo'] ?? 2);
        q(
          $db,
          "UPDATE puntaje_crediticio
           SET puntaje = ?, id_nivel_riesgo = ?, total_transacciones = ?
           WHERE id_puntaje_crediticio = ?",
          [$score, $nivel, $trx, $idPc],
          'iiii'
        );
      } else {
        q(
          $db,
          "INSERT INTO puntaje_crediticio (id_cliente, id_nivel_riesgo, puntaje, total_transacciones)
           VALUES (?, ?, ?, 0)",
          [$id_cliente, $idNivelRiesgo, $score],
          'iii'
        );
      }
    }
  }

  $scoreActual = null;
  [$stPcNow, $errPcNow] = q(
    $db,
    "SELECT puntaje
     FROM puntaje_crediticio
     WHERE id_cliente = ?
     ORDER BY id_puntaje_crediticio DESC
     LIMIT 1",
    [$id_cliente],
    'i'
  );
  if ($stPcNow) {
    $pcNow = $stPcNow->get_result()->fetch_assoc();
    $stPcNow->close();
    $scoreActual = toIntOrNull($pcNow['puntaje'] ?? null);
  }

  $empresaLaboral = null;
  [$stOc, $errOc] = q(
    $db,
    "SELECT oc.empresa
     FROM cliente c
     LEFT JOIN ocupacion oc ON oc.id_datos_persona = c.id_datos_persona
     WHERE c.id_cliente = ?
     LIMIT 1",
    [$id_cliente],
    'i'
  );
  if ($stOc) {
    $oc = $stOc->get_result()->fetch_assoc();
    $stOc->close();
    $empresaLaboral = trim((string)($oc['empresa'] ?? ''));
  }

  $idTipoVivienda = null;
  $idSectorEconomico = null;
  $dependientes = null;
  $antiguedadEmpleado = null;
  $fuenteIngresosTxt = '';
  $perfilCols = tableColumns($db, 'cliente_perfil_socioeconomico');
  if (!empty($perfilCols)) {
    $perfilWhereCol = isset($perfilCols['id_cliente']) ? 'id_cliente' : (isset($perfilCols['id_perfil_cliente']) ? 'id_perfil_cliente' : null);
    if ($perfilWhereCol !== null) {
    [$stPs, $errPs] = q(
      $db,
      "SELECT id_tipo_vivienda, id_sector_economico, cantidad_dependientes, antiguedad_laboral_meses, id_fuente_ingreso, fuente_ingresos
       FROM cliente_perfil_socioeconomico
       WHERE {$perfilWhereCol} = ?
       LIMIT 1",
      [$id_cliente],
      'i'
    );
    if ($stPs) {
      $ps = $stPs->get_result()->fetch_assoc();
      $stPs->close();
      if ($ps) {
        $idTipoVivienda = toIntOrNull($ps['id_tipo_vivienda'] ?? null);
        $idSectorEconomico = toIntOrNull($ps['id_sector_economico'] ?? null);
        $dependientes = toIntOrNull($ps['cantidad_dependientes'] ?? null);
        $antiguedadEmpleado = toIntOrNull($ps['antiguedad_laboral_meses'] ?? null);
        $fuenteIngresosTxt = trim((string)($ps['fuente_ingresos'] ?? ''));

        $idFuente = toIntOrNull($ps['id_fuente_ingreso'] ?? null);
        if ($idFuente !== null && !empty(tableColumns($db, 'cat_fuente_ingreso'))) {
          [$stFi, $errFi] = q(
            $db,
            "SELECT fuente_ingreso FROM cat_fuente_ingreso WHERE id_fuente_ingreso = ? LIMIT 1",
            [$idFuente],
            'i'
          );
          if ($stFi) {
            $fi = $stFi->get_result()->fetch_assoc();
            $stFi->close();
            $fuenteCat = trim((string)($fi['fuente_ingreso'] ?? ''));
            if ($fuenteCat !== '') $fuenteIngresosTxt = $fuenteCat;
          }
        }
      }
    }
    }
  }

  $fechaNacimiento = '';
  $plazoMeses = null;
  [$stCli, $errCli] = q(
    $db,
    "SELECT dp.fecha_nacimiento, p.plazo_meses
     FROM cliente c
     JOIN datos_persona dp ON dp.id_datos_persona = c.id_datos_persona
     LEFT JOIN prestamo p ON p.id_prestamo = ?
     WHERE c.id_cliente = ?
     LIMIT 1",
    [$id_prestamo, $id_cliente],
    'ii'
  );
  if ($stCli) {
    $cli = $stCli->get_result()->fetch_assoc();
    $stCli->close();
    $fechaNacimiento = (string)($cli['fecha_nacimiento'] ?? '');
    $plazoMeses = toIntOrNull($cli['plazo_meses'] ?? null);
  }

  $defaultSector = firstIdFromTable($db, 'cat_sector_economico', 'id_sector_economico');
  $defaultVivienda = firstIdFromTable($db, 'cat_tipo_vivienda', 'id_tipo_vivienda');

  $scoreFinal = $score ?? $scoreActual ?? toIntOrNull($es['puntaje_crediticio'] ?? null) ?? 0;
  $deudaTotalFinal = $deudaExterna ?? toFloatOrNull($es['total_deudas_externas'] ?? null) ?? 0.0;
  $usoTarjetasFinal = $usoTarjetas ?? toFloatOrNull($es['porcentaje_utilizacion_credito'] ?? null) ?? 0.0;
  $cantidadProductosFinal = $cantidadProductos ?? toIntOrNull($es['cantidad_tarjetas_activas'] ?? null) ?? 0;
  $gastosFinal = $gastosMensuales ?? $egresosMensuales ?? toFloatOrNull($es['total_gastos_fijos'] ?? null) ?? 0.0;

  $salarioNeto = 0.0;
  if ($ingresosMensuales !== null) {
    $salarioNeto = (float)$ingresosMensuales - (float)$gastosFinal;
  }

  $tipoIngreso = 'fijo';
  if (preg_match('/variable|comision|independ|negocio|remesa/i', strtolower($fuenteIngresosTxt))) {
    $tipoIngreso = 'Variable';
  }

  $empresaLaboralFinal = $empresaLaboral !== null && $empresaLaboral !== ''
    ? $empresaLaboral
    : (trim((string)($es['empresa_laboral'] ?? '')) !== '' ? (string)$es['empresa_laboral'] : 'N/D');

  $antiguedadFinal = $antiguedadEmpleado ?? toIntOrNull($es['antiguedad_empleado'] ?? null) ?? 0;
  $idSectorFinal = $idSectorEconomico ?? toIntOrNull($es['id_sector_economico'] ?? null) ?? $defaultSector;
  $idViviendaFinal = $idTipoVivienda ?? toIntOrNull($es['id_tipo_vivienda'] ?? null) ?? $defaultVivienda;
  $dependientesFinal = $dependientes ?? toIntOrNull($es['cantidad_dependientes'] ?? null) ?? 0;

  $edadAlFinalizar = toIntOrNull($es['edad_al_finalizar'] ?? null) ?? 0;
  if ($fechaNacimiento !== '') {
    try {
      $fechaNac = new DateTime($fechaNacimiento);
      $fechaFinEstimada = new DateTime();
      if ($plazoMeses !== null && $plazoMeses > 0) {
        $fechaFinEstimada->modify('+' . $plazoMeses . ' months');
      }
      $edadAlFinalizar = (int)$fechaNac->diff($fechaFinEstimada)->y;
    } catch (Throwable $__) {
      // Ignorar fecha inválida.
    }
  }

  $maxPorcCap = 0.40;
  [$stCfg, $errCfg] = q(
    $db,
    "SELECT valor_decimal
     FROM configuracion
     WHERE nombre_configuracion = 'MAX_PORCENTAJE_CAPACIDAD_PAGO' AND estado = 'Activo'
     LIMIT 1"
  );
  if ($stCfg) {
    $cfg = $stCfg->get_result()->fetch_assoc();
    $stCfg->close();
    $cfgVal = toFloatOrNull($cfg['valor_decimal'] ?? null);
    if ($cfgVal !== null && $cfgVal > 0) $maxPorcCap = $cfgVal;
  }

  $deudaMensualAprox = $deudaTotalFinal > 0 ? ($deudaTotalFinal / 12) : 0.0;
  $capacidadPagoFinal = toFloatOrNull($es['capacidad_pago'] ?? null) ?? 0.0;
  $nivelEndeudamientoFinal = toFloatOrNull($es['nivel_endeudamiento'] ?? null) ?? 0.0;
  if ($ingresosMensuales !== null && $ingresosMensuales > 0) {
    $ingresoNeto = (float)$ingresosMensuales - (float)$gastosFinal;
    $capacidadPagoFinal = round(($ingresoNeto * $maxPorcCap) - $deudaMensualAprox, 2);
    $nivelEndeudamientoFinal = round((((float)$gastosFinal + $deudaMensualAprox) / (float)$ingresosMensuales) * 100, 2);
  }

  $setData = [];
  if (isset($colsEs['id_prestamo'])) $setData['id_prestamo'] = $id_prestamo;
  if (isset($colsEs['salario_neto'])) $setData['salario_neto'] = $salarioNeto;
  if (isset($colsEs['tipo_ingreso'])) $setData['tipo_ingreso'] = $tipoIngreso;
  if (isset($colsEs['empresa_laboral'])) $setData['empresa_laboral'] = $empresaLaboralFinal;
  if (isset($colsEs['antiguedad_empleado'])) $setData['antiguedad_empleado'] = $antiguedadFinal;
  if (isset($colsEs['id_sector_economico']) && $idSectorFinal !== null) $setData['id_sector_economico'] = $idSectorFinal;
  if (isset($colsEs['puntaje_crediticio'])) $setData['puntaje_crediticio'] = $scoreFinal;
  if (isset($colsEs['cantidad_tarjetas_activas'])) $setData['cantidad_tarjetas_activas'] = $cantidadProductosFinal;
  if (isset($colsEs['porcentaje_utilizacion_credito'])) $setData['porcentaje_utilizacion_credito'] = $usoTarjetasFinal;
  if (isset($colsEs['total_deudas_externas'])) $setData['total_deudas_externas'] = $deudaTotalFinal;
  if (isset($colsEs['total_gastos_fijos'])) $setData['total_gastos_fijos'] = $gastosFinal;
  if (isset($colsEs['capacidad_pago'])) $setData['capacidad_pago'] = $capacidadPagoFinal;
  if (isset($colsEs['nivel_endeudamiento'])) $setData['nivel_endeudamiento'] = $nivelEndeudamientoFinal;
  if (isset($colsEs['id_tipo_vivienda']) && $idViviendaFinal !== null) $setData['id_tipo_vivienda'] = $idViviendaFinal;
  if (isset($colsEs['cantidad_dependientes'])) $setData['cantidad_dependientes'] = $dependientesFinal;
  if (isset($colsEs['edad_al_finalizar'])) $setData['edad_al_finalizar'] = $edadAlFinalizar;

  if ($es) {
    if (!empty($setData)) {
      $sets = [];
      $vals = [];
      foreach ($setData as $col => $val) {
        $sets[] = "$col = ?";
        $vals[] = $val;
      }
      $vals[] = (int)$es['id_evaluacion_estrategica'];
      $types = inferTypes($vals);
      q(
        $db,
        "UPDATE evaluacion_estrategica SET " . implode(', ', $sets) . " WHERE id_evaluacion_estrategica = ?",
        $vals,
        $types
      );
    }
    return;
  }

  $insertData = [];

  $baseCandidates = [
    'id_cliente' => $id_cliente,
    'id_prestamo' => $id_prestamo,
    'salario_neto' => $salarioNeto,
    'tipo_ingreso' => $tipoIngreso,
    'empresa_laboral' => $empresaLaboralFinal,
    'antiguedad_empleado' => $antiguedadFinal,
    'id_sector_economico' => $idSectorFinal,
    'puntaje_crediticio' => $scoreFinal,
    'cantidad_tarjetas_activas' => $cantidadProductosFinal,
    'porcentaje_utilizacion_credito' => $usoTarjetasFinal,
    'total_deudas_externas' => $deudaTotalFinal,
    'total_gastos_fijos' => $gastosFinal,
    'capacidad_pago' => $capacidadPagoFinal,
    'nivel_endeudamiento' => $nivelEndeudamientoFinal,
    'id_tipo_vivienda' => $idViviendaFinal,
    'cantidad_dependientes' => $dependientesFinal,
    'edad_al_finalizar' => $edadAlFinalizar,
  ];

  foreach ($baseCandidates as $col => $val) {
    if (isset($colsEs[$col]) && $val !== null) {
      $insertData[$col] = $val;
    }
  }

  $missingRequired = [];
  foreach ($colsEs as $colName => $meta) {
    $required = strtoupper((string)$meta['IS_NULLABLE']) === 'NO'
      && $meta['COLUMN_DEFAULT'] === null
      && stripos((string)$meta['EXTRA'], 'auto_increment') === false;
    if ($required && !array_key_exists($colName, $insertData)) {
      $missingRequired[] = $colName;
    }
  }

  if (!empty($missingRequired)) return;

  $cols = array_keys($insertData);
  if (empty($cols)) return;
  $vals = array_values($insertData);
  $place = implode(',', array_fill(0, count($cols), '?'));
  $types = inferTypes($vals);
  q(
    $db,
    "INSERT INTO evaluacion_estrategica (" . implode(',', $cols) . ") VALUES (" . $place . ")",
    $vals,
    $types
  );
}

function moneyDOP($db, $monto, $id_moneda){
  if(!$id_moneda) return (float)$monto;
  [$st, $err] = q($db, "SELECT valor 
  FROM cat_tipo_moneda 
  WHERE id_tipo_moneda=?", [$id_moneda], 'i');
  if(!$st) 
    return (float)$monto;
  $valor = ($st->get_result()->fetch_assoc()['valor'] ?? 1);
  return (float)$monto * (float)$valor;
}
function per_to_periods_year($periodo){
  $p = strtolower($periodo);
  if (strpos($p, 'seman') === 0) return 52;
  if (strpos($p, 'quin') === 0) return 24;
  return 12;
}

function generar_cronograma_prestamo($db, $id_prestamo, $fecha_inicio){
  $check = $db->query("
    SELECT p.id_prestamo, p.monto_solicitado, p.plazo_meses, p.fecha_solicitud,
           p.id_condicion_actual, cp.tasa_interes, cp.id_tipo_amortizacion
    FROM prestamo p 
    LEFT JOIN condicion_prestamo cp ON p.id_condicion_actual = cp.id_condicion_prestamo
    WHERE p.id_prestamo = $id_prestamo
  ")->fetch_assoc();
  try {
    if (!$check) {
    throw new Exception("Préstamo no encontrado con ID: $id_prestamo");
  }
  if (empty($check['fecha_solicitud'])) {
    $db->query("UPDATE prestamo SET fecha_solicitud = CURDATE() WHERE id_prestamo = $id_prestamo");
  }
  if (empty($check['id_condicion_actual'])) {
    throw new Exception("El préstamo no tiene condiciones configuradas");
  }
  [$st, $err] = q($db, "CALL generar_cronograma(?, ?)", [$id_prestamo, $fecha_inicio], 'is');
  if(!$st) {
    throw new Exception($err ?: "Error al generar el cronograma");
  }
  if (is_object($st) && method_exists($st, 'close')) $st->close();
  while ($db-> more_results() && $db->next_result()){;}
  return [true, null];

  } catch (Exception $e){
    return [false, $e->getMessage()];
  }

}

if ($act==='catalogos'){
  $cats = [];
  $cats['monedas'] = $db->query("SELECT id_tipo_moneda AS id, tipo_moneda AS txt, valor FROM cat_tipo_moneda")->fetch_all(MYSQLI_ASSOC);
  $cats['metodos'] = $db->query("SELECT id_tipo_pago AS id, tipo_pago AS txt FROM cat_tipo_pago")->fetch_all(MYSQLI_ASSOC);
  $cats['periodos'] = $db->query("SELECT id_periodo_pago AS id, periodo AS txt FROM cat_periodo_pago")->fetch_all(MYSQLI_ASSOC);
  $cats['amortizacion'] = $db->query("SELECT id_tipo_amortizacion AS id, tipo_amortizacion AS txt FROM cat_tipo_amortizacion")->fetch_all(MYSQLI_ASSOC);
  $cats['garantias'] = $db->query("SELECT id_tipo_garantia AS id, tipo_garantia AS txt FROM cat_tipo_garantia")->fetch_all(MYSQLI_ASSOC);
  $cats['politicas'] = $db->query("SELECT id_politica_cancelacion AS id, nombre_politica AS txt, porcentaje_penalidad FROM politicas_cancelacion WHERE estado = 'Activo' ORDER BY nombre_politica ASC")->fetch_all(MYSQLI_ASSOC);
  $cats['defaults'] = $db->query("SELECT id_tipo_prestamo, nombre, tasa_interes, monto_minimo, id_tipo_amortizacion, plazo_minimo_meses, plazo_maximo_meses, id_politica_cancelacion FROM tipo_prestamo")->fetch_all(MYSQLI_ASSOC);
  out(['ok'=>true,'data'=>$cats]);
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
if ($action === 'upload_doc') {
    upload_doc_prestamo($conn);
    exit;
}

function _san($s) {
    $s = trim((string)$s);
    $s = preg_replace('/\s+/', '_', $s);
    $s = preg_replace('/[^0-9A-Za-z_\-]/', '', $s);
    return $s !== '' ? $s : 'NA';
}

function upload_doc_prestamo(mysqli $conn) {
    header('Content-Type: application/json; charset=utf-8');

    $id_cliente  = (int)($_POST['id_cliente']  ?? 0);
    $id_prestamo = (int)($_POST['id_prestamo'] ?? 0);
    $tipo_arch   = strtoupper((string)($_POST['tipo_archivo'] ?? 'OTRO'));

    if ($id_cliente <= 0 || $id_prestamo <= 0) {
        echo json_encode(['ok' => false, 'msg' => 'Parámetros inválidos']);
        return;
    }
    if (
        !isset($_FILES['archivo']) ||
        ($_FILES['archivo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
    ) {
        echo json_encode(['ok' => false, 'msg' => 'Archivo requerido']);
        return;
    }

    $sqlCli = "SELECT c.id_cliente, dp.nombre, dp.apellido, di.numero_documento
               FROM cliente c
               JOIN datos_persona dp ON dp.id_datos_persona = c.id_datos_persona
               LEFT JOIN documento_identidad di ON di.id_datos_persona = dp.id_datos_persona
               WHERE c.id_cliente = ? LIMIT 1";
    $st = $conn->prepare($sqlCli);
    $st->bind_param('i', $id_cliente);
    $st->execute();
    $cli = $st->get_result()->fetch_assoc();
    $st->close();

    if (!$cli) {
        echo json_encode(['ok' => false, 'msg' => 'Cliente no encontrado']);
        return;
    }

    $nombre   = (string)($cli['nombre'] ?? '');
    $apellido = (string)($cli['apellido'] ?? '');
    $docRaw   = (string)($cli['numero_documento'] ?? '');

    $docDir = preg_replace('/[^0-9A-Za-z]/', '', $docRaw);
    if ($docDir === '') {
        $docDir = 'CLI_' . $id_cliente;
    }

    $numLocal = null;

    if ($q = $conn->prepare("
        SELECT numero_prestamo_cliente
        FROM cliente_prestamo
        WHERE id_prestamo = ?
        LIMIT 1
    ")) {
        $q->bind_param('i', $id_prestamo);
        $q->execute();
        $r = $q->get_result()->fetch_assoc();
        $q->close();
        if ($r && isset($r['numero_prestamo_cliente'])) {
            $numLocal = (int)$r['numero_prestamo_cliente'];
        }
    }

    if (!$numLocal) {
        $hasCol = false;
        if ($chk = $conn->prepare("
            SELECT COUNT(*) AS n
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name   = 'prestamo'
              AND column_name  = 'numero_prestamo_cliente'
        ")) {
            $chk->execute();
            $c = $chk->get_result()->fetch_assoc();
            $chk->close();
            $hasCol = (int)($c['n'] ?? 0) > 0;
        }
        if ($hasCol) {
            if ($q2 = $conn->prepare("
                SELECT numero_prestamo_cliente
                FROM prestamo
                WHERE id_prestamo = ?
                LIMIT 1
            ")) {
                $q2->bind_param('i', $id_prestamo);
                $q2->execute();
                $r2 = $q2->get_result()->fetch_assoc();
                $q2->close();
                if ($r2 && isset($r2['numero_prestamo_cliente'])) {
                    $numLocal = (int)$r2['numero_prestamo_cliente'];
                }
            }
        }
    }

    $numeroSecuencial = $numLocal ?: $id_prestamo;

    $carpetaPrestamo = 'PRESTAMO_' . $numeroSecuencial;

    $baseDir = __DIR__ . '/../uploads/clientes';
    if (!is_dir($baseDir)) {
        @mkdir($baseDir, 0777, true);
    }

    $clienteDir = $baseDir . DIRECTORY_SEPARATOR . $docDir;
    if (!is_dir($clienteDir)) {
        @mkdir($clienteDir, 0777, true);
    }

    $prestamoDir = $clienteDir . DIRECTORY_SEPARATOR . $carpetaPrestamo;
    if (!is_dir($prestamoDir)) {
        @mkdir($prestamoDir, 0777, true);
    }

    $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
    $orig    = (string)($_FILES['archivo']['name'] ?? '');
    $ext     = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        echo json_encode(['ok' => false, 'msg' => 'Extensión no permitida (solo PDF/JPG/PNG)']);
        return;
    }

    $tipo   = _san($tipo_arch);                
    $nomCli = _san($nombre . '_' . $apellido);

    $fileOut = sprintf(
        '%s_Prestamo%d_%s_%s_%d_%s.%s',
        $tipo,                
        $numeroSecuencial,     
        $nomCli,               
        $docDir,               
        $id_prestamo,          
        date('Ymd_His'),       
        $ext
    );

    $destAbs = $prestamoDir . DIRECTORY_SEPARATOR . $fileOut;

    if (!move_uploaded_file($_FILES['archivo']['tmp_name'], $destAbs)) {
        echo json_encode(['ok' => false, 'msg' => 'No se pudo guardar el archivo']);
        return;
    }

    $pathRel = 'uploads/clientes/' . $docDir . '/' . $carpetaPrestamo . '/' . $fileOut;

    echo json_encode([
        'ok'               => true,
        'msg'              => 'Documento subido',
        'path'             => $pathRel,
        'carpeta_prestamo' => $carpetaPrestamo,
        'numero_prestamo'  => $numeroSecuencial,
        'id_prestamo'      => $id_prestamo,
        'id_cliente'       => $id_cliente,
        'nombre_archivo'   => $fileOut
    ]);
}

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
  if(!$st) 
    out(['ok'=>false,'msg'=>$err]);
  $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
  out(['ok'=>true,'data'=>$rows]);
}

if ($act==='crear_personal'){
  $id_cliente = (int)$_POST['id_cliente'];
  $monto_user = (float)($_POST['monto_solicitado'] ?? 0);
  $tasa       = (float)($_POST['tasa_interes'] ?? 0);
  $plazo      = (int)($_POST['plazo_meses'] ?? 0);
  $id_periodo = (int)($_POST['id_periodo_pago'] ?? 0);
  $id_amortizacion = (int)($_POST['id_tipo_amortizacion'] ?? 1);
  $id_politica = (int)($_POST['id_politica_cancelacion'] ?? 0);
  if ($id_politica <= 0) $id_politica = null;
  $fecha      = trim($_POST['fecha_solicitud'] ?? '') ?: date('Y-m-d');
  $motivo     = $_POST['motivo'] ?? '';
  $id_moneda  = (int)($_POST['id_tipo_moneda'] ?? 1);
  $num_contrato = 'PER-' . date('Ymd') . '-' . str_pad($id_cliente, 4, '0', STR_PAD_LEFT);
  
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || !strtotime($fecha)) {
    $fecha = date('Y-m-d');
  }

  $row = $db->query("SELECT tasa_interes, monto_minimo 
  FROM tipo_prestamo 
  WHERE id_tipo_prestamo=1")->fetch_assoc();
  $min = (float)($row['monto_minimo'] ?? 10000);
  $monto_dop = moneyDOP($db, $monto_user, $id_moneda);
  if ($monto_dop < $min) 
    out(['ok'=>false,'msg'=>'Monto menor al mínimo establecido']);

  $db->autocommit(false);
  try {
    [$st2,$e2] = q($db, "INSERT INTO condicion_prestamo (tasa_interes,id_tipo_amortizacion,id_periodo_pago,vigente_desde,esta_activo) 
    VALUES (?,?,?,CURDATE(),1)",
                  [$tasa,$id_amortizacion,$id_periodo],'dii');
    if(!$st2) throw new Exception($e2);
    $id_cond = $db->insert_id;
    
    if ($id_politica === null) {
      [$st,$err] = q($db, "INSERT INTO prestamo (id_cliente,
      id_tipo_prestamo,
      numero_contrato,
      monto_solicitado,
      fecha_solicitud,
      plazo_meses,
      id_estado_prestamo,
      id_condicion_actual,
      creado_por)
      VALUES (?,?,?,?,?,?,2,?,1)",
                    [$id_cliente,1,$num_contrato,$monto_dop,$fecha,$plazo,$id_cond],'iisdsii');
    } else {
      [$st,$err] = q($db, "INSERT INTO prestamo (id_cliente,
      id_tipo_prestamo,
      numero_contrato,
      monto_solicitado,
      fecha_solicitud,
      plazo_meses,
      id_estado_prestamo,
      id_condicion_actual,
      id_politica_cancelacion,
      creado_por)
      VALUES (?,?,?,?,?,?,2,?,?,1)",
                    [$id_cliente,1,$num_contrato,$monto_dop,$fecha,$plazo,$id_cond,$id_politica],'iisdsiii');
    }
    if(!$st) throw new Exception($err);
   
    $id_p = $db->insert_id;
    
    q($db, "INSERT INTO prestamo_personal (id_prestamo,motivo) 
    VALUES (?,?)", [$id_p,$motivo], 'is');

    saveEvaluationProfileFromRequest($db, $id_cliente, $id_p, $_POST);

    list($ok, $e_cronograma) = generar_cronograma_prestamo($db, $id_p, $fecha);
    if (!$ok) 
      throw new Exception("Error al generar el cronograma: " . $e_cronograma);
    
    $tipo_garantia = $_POST['tipo_garantia'] ?? '';
    $descripcion = $_POST['descripcion_garantia'] ?? 'Garantia Personal';
    $valor_estimado = $monto_dop;

    if (!empty($tipo_garantia)){
      [$st_g, $err_g] = q($db, "INSERT INTO garantia (id_prestamo, id_cliente, descripcion, valor) 
      VALUES (?,?,?,?)",
      [$id_p, $id_cliente, $descripcion, $valor_estimado], 'iisd');

      if (!$st_g) throw new Exception("Error al guardar la garantia:". $err_g);
      $id_garantia = $db->insert_id;

      $res_tipo = $db->query("SELECT id_tipo_garantia 
      FROM cat_tipo_garantia 
      WHERE tipo_garantia = '" . $db->real_escape_string($tipo_garantia) . "' LIMIT 1");
      $row_tipo = $res_tipo->fetch_assoc();
      $id_tipo_g = $row_tipo ? $row_tipo['id_tipo_garantia'] : 1;

      [$st_dg, $err_dg] = q($db, "INSERT INTO detalle_garantia (id_garantia, id_tipo_garantia, descripcion, valor_estimado, estado_garantia)
      VALUES (?,?,?,?, 'Activa')",
      [$id_garantia, $id_tipo_g, $descripcion, $valor_estimado], 'iisd');

      if (!$st_dg) throw new Exception("Error en detalle garantia:". $err_dg);      
    }
    $db->commit();
    out(['ok'=>true,'id_prestamo'=>$id_p,'numero_contrato'=>$num_contrato]);
  } catch (Exception $e) {
    $db->rollback();
    out(['ok'=>false,'msg'=>$e->getMessage()]);
  } finally {
    $db->autocommit(true);
  }
}

if ($act==='crear_hipotecario'){
  $id_cliente = (int)$_POST['id_cliente'];
  $monto_user = (float)($_POST['monto_solicitado'] ?? 0);
  $tasa       = (float)($_POST['tasa_interes'] ?? 0);
  $plazo      = (int)($_POST['plazo_meses'] ?? 0);
  $id_periodo = (int)($_POST['id_periodo_pago'] ?? 0);
  $id_amortizacion = (int)($_POST['id_tipo_amortizacion'] ?? 2);
  $id_politica = (int)($_POST['id_politica_cancelacion'] ?? 0);
  if ($id_politica <=0 ) $id_politica = null;
  $fecha      = trim($_POST['fecha_solicitud'] ?? '') ?: date('Y-m-d');
  $dir        = $_POST['direccion_propiedad'] ?? '';
  $valor      = (float)($_POST['valor_propiedad'] ?? 0);
  $porc       = (float)($_POST['porcentaje_financiamiento'] ?? 0);
  $id_moneda  = (int)($_POST['id_tipo_moneda'] ?? 1);
  $num_contrato = 'HIP-' . date('Ymd') . '-' . str_pad($id_cliente, 4, '0', STR_PAD_LEFT);
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || !strtotime($fecha)) {
    $fecha = date('Y-m-d');
  }
  $row = $db->query("SELECT tasa_interes, monto_minimo 
  FROM tipo_prestamo 
  WHERE id_tipo_prestamo=2")->fetch_assoc();
  $min = (float)($row['monto_minimo'] ?? 12000);
  $monto_dop = moneyDOP($db, $monto_user, $id_moneda);
  $valor_dop = moneyDOP($db, $valor, $id_moneda);

  if ($monto_dop < $min) out(['ok'=>false,'msg'=>'Monto menor al mínimo establecido']);
  $porc_calc = $valor_dop > 0 ? ($monto_dop / $valor_dop) * 100 : 0;
  if ($porc_calc > 80.01) 
    out(['ok'=>false,'msg'=>'El porcentaje financiado no puede exceder 80%']);
  if ($porc > 0) { 
    $porc = round(min($porc, $porc_calc), 2); 
  } else { 
    $porc = round($porc_calc, 2); 
  }
  $db->autocommit(false);
  try {
    [$st2,$e2] = q($db, "INSERT INTO condicion_prestamo (tasa_interes,
    id_tipo_amortizacion,
    id_periodo_pago,
    vigente_desde,
    esta_activo) 
    VALUES (?,?,?,CURDATE(),1)",
                  [$tasa,$id_amortizacion,$id_periodo],'dii');
    if(!$st2) 
      throw new Exception($e2);
    $id_cond = $db->insert_id;
    
        if ($id_politica === null) {
          [$st,$err] = q($db, "INSERT INTO prestamo (id_cliente,
          id_tipo_prestamo,
          numero_contrato,
          monto_solicitado,
          fecha_solicitud,
          plazo_meses,
          id_estado_prestamo,
          id_condicion_actual,
          creado_por)
          VALUES (?,?,?,?,?,?,2,?,1)",
            [$id_cliente,2,$num_contrato,$monto_dop,$fecha,$plazo,$id_cond],'iisdsii');
        } else {
          [$st,$err] = q($db, "INSERT INTO prestamo (id_cliente,
          id_tipo_prestamo,
          numero_contrato,
          monto_solicitado,
          fecha_solicitud,
          plazo_meses,
          id_estado_prestamo,
          id_condicion_actual,
          id_politica_cancelacion,
          creado_por)
          VALUES (?,?,?,?,?,?,2,?,?,1)",
            [$id_cliente,2,$num_contrato,$monto_dop,$fecha,$plazo,$id_cond,$id_politica],'iisdsiii');
        }
    if(!$st) 
      throw new Exception($err);
    $id_p = $db->insert_id;
    
     q($db, "INSERT INTO prestamo_hipotecario (id_prestamo,
     valor_propiedad,
     porcentaje_financiamiento,
     direccion_propiedad) 
     VALUES (?,?,?,?)",
       [$id_p,$valor_dop,$porc,$dir],'idds');

    saveEvaluationProfileFromRequest($db, $id_cliente, $id_p, $_POST);
    
    list($ok, $e_cronograma) = generar_cronograma_prestamo($db, $id_p, $fecha);
    if (!$ok) 
      throw new Exception("Error al generar el cronograma: " . $e_cronograma);
    
    [$st_g, $err_g] = q($db, "INSERT INTO garantia (id_prestamo, id_cliente, descripcion, valor) 
    VALUES (?,?,?,?)", 
    [$id_p, $id_cliente, "Garantia Hipotecaria para $num_contrato", $valor_dop], 'iisd');

    $id_garantia = $db->insert_id;

    $res_tipo = $db->query("SELECT id_tipo_garantia FROM cat_tipo_garantia LIMIT 1");
    $row_tipo = $res_tipo->fetch_assoc();
    $id_tipo_g = $row_tipo ? $row_tipo['id_tipo_garantia'] : 1;

    [$st_dg, $err_dg] = q($db, "INSERT INTO detalle_garantia (id_garantia, id_tipo_garantia, descripcion, valor_estimado, estado_garantia) 
    VALUES (?,?,?,?, 'Activa')",
    [$id_garantia, $id_tipo_g, $dir, $valor_dop], 'iisd');

    if (!$st_dg) 
      throw new Exception("Error en detalle garantia:". $err_dg);
    $db->commit();
    out(['ok'=>true,'id_prestamo'=>$id_p,'numero_contrato'=>$num_contrato]);
  } catch (Exception $e) {
    $db->rollback();
    out(['ok'=>false,'msg'=>$e->getMessage()]);
  } finally {
    $db->autocommit(true);
  }
}

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
  if(!$st) 
    out(['ok'=>false,'msg'=>$err]);
  $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
  $total = $db->query("SELECT FOUND_ROWS() AS t")->fetch_assoc()['t'] ?? 0;
  out(['ok'=>true,'data'=>$rows,'total'=>$total]);
}

if ($act==='get'){
  $id = (int)($_POST['id_prestamo'] ?? 0);
  $sql = "
    SELECT p.*, dp.nombre, dp.apellido,
           CASE WHEN p.id_tipo_prestamo=2 THEN 'Hipotecario' ELSE 'Personal' END AS tipo_prestamo,
           ce.estado,
           cp.tasa_interes, cp.id_periodo_pago, cp.id_tipo_amortizacion,
           d.ciudad, d.sector, d.calle, d.numero_casa,
           CASE WHEN p.id_tipo_prestamo=2 THEN ph.direccion_propiedad ELSE NULL END AS direccion_garantia,
           (SELECT periodo FROM cat_periodo_pago WHERE id_periodo_pago=cp.id_periodo_pago) AS periodo_txt,
           (SELECT tipo_amortizacion FROM cat_tipo_amortizacion WHERE id_tipo_amortizacion=cp.id_tipo_amortizacion) AS amortizacion_txt
    FROM prestamo p
    JOIN cliente c ON c.id_cliente=p.id_cliente
    JOIN datos_persona dp ON c.id_datos_persona=dp.id_datos_persona
    LEFT JOIN condicion_prestamo cp ON cp.id_condicion_prestamo = p.id_condicion_actual AND cp.esta_activo=1
    LEFT JOIN cat_estado_prestamo ce ON ce.id_estado_prestamo=p.id_estado_prestamo
    LEFT JOIN prestamo_hipotecario ph ON ph.id_prestamo = p.id_prestamo
    LEFT JOIN direccion d ON d.id_datos_persona = dp.id_datos_persona
    WHERE p.id_prestamo=?";
  [$st,$err]=q($db,$sql,[$id],'i');
  if(!$st) 
    out(['ok'=>false,'msg'=>$err]);
  $row = $st->get_result()->fetch_assoc() ?: [];
  $cron = $db->query("SELECT numero_cuota, fecha_vencimiento, capital_cuota, interes_cuota, cargos_cuota, total_monto, saldo_cuota, estado_cuota 
  FROM cronograma_cuota 
  WHERE id_prestamo=$id 
  ORDER BY numero_cuota ASC")->fetch_all(MYSQLI_ASSOC);
  $resumen = $db->query("SELECT total_capital, total_interes, total_pagar 
  FROM resumen_cronograma 
  WHERE id_prestamo=$id")->fetch_assoc() ?: [];
  out(['ok'=>true,'data'=>$row,'cronograma'=>$cron,'resumen'=>$resumen]);
}
 
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
    LEFT JOIN cat_estado_prestamo ep ON ep.id_estado_prestamo = p.id_estado_prestamo
    WHERE (CONCAT(dp.nombre,' ',dp.apellido) LIKE CONCAT('%',?,'%')
       OR di.numero_documento = ?
       OR p.id_prestamo = ?)
       AND ep.estado = 'Desembolso'
    ORDER BY p.id_prestamo DESC LIMIT 20";
  [$st,$err]=q($db,$sql,[$q,$q,(int)$q],'ssi');

  if(!$st) 
    out(['ok'=>false,'msg'=>$err]);
  out(['ok'=>true,'data'=>$st->get_result()->fetch_all(MYSQLI_ASSOC)]);
}
 
if ($act==='desembolsar'){
  $id_p = (int)($_POST['id_prestamo'] ?? 0);
  $monto = (float)($_POST['monto_desembolsado'] ?? 0);
  $fecha = $_POST['fecha_desembolso'] ?? date('Y-m-d');
  $met   = (int)($_POST['metodo_entrega'] ?? 1);

  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || !strtotime($fecha)){
    out(['ok'=> false, 'msg'=> 'Fecha de desembolso invalida.']);
  }

  $db -> autocommit(false);
  try {
  [$st,$err]=q($db,"INSERT INTO desembolso (id_prestamo,
  monto_desembolsado,
  fecha_desembolso,
  metodo_entrega) 
  VALUES (?,?,?,?)",
               [$id_p,$monto,$fecha,$met],'idss');
  if(!$st) 
    out(['ok'=>false,'msg'=>$err]);

  list($ok, $e_cronograma) = generar_cronograma_prestamo($db, $id_p, $fecha);
  if (!$ok)  
    throw new Exception("Error al generar el cronograma: " . $e_cronograma);

  [$st_upd, $err_upd] = q($db, "UPDATE prestamo
  SET id_estado_prestamo=1
  WHERE id_prestamo=?", [$id_p], 'i');
  if (!$st_upd) 
    throw new Exception("Error al actulizar el estado del prestamo: " . $err_upd);
  
  $db->commit();

  out(['ok'=>true,'id_desembolso'=>$db->insert_id]);
  } catch (Exception $e){
    $db->rollback();
    out(['ok'=>false, 'msg'=>$e->getMessage()]);
  } finally {
    $db->autocommit(true);
  } 
}

if ($act==='recibo_html'){
  header('Content-Type: text/html; charset=utf-8');
  $id_p = (int)($_GET['id_prestamo'] ?? 0);
  $p = $db->query("
    SELECT p.id_prestamo, p.monto_solicitado, p.plazo_meses, p.fecha_solicitud, p.numero_contrato,
           CONCAT(dp.nombre,' ',dp.apellido) AS cliente,
           cp.tasa_interes,
           c.id_cliente, dp.id_datos_persona,
           CONCAT_WS(', ',
             NULLIF(d.calle, ''),
             CASE WHEN d.numero_casa IS NOT NULL AND d.numero_casa<>'' AND d.numero_casa<>'0' THEN CONCAT('No. ', d.numero_casa) ELSE NULL END,
             NULLIF(d.sector, ''),
             NULLIF(d.ciudad, '')
           ) AS direccion_completa,
           (SELECT MIN(fecha_vencimiento) FROM cronograma_cuota cc WHERE cc.id_prestamo=p.id_prestamo AND cc.estado_cuota='Pendiente') AS proximo_pago
    FROM prestamo p
    JOIN cliente c ON c.id_cliente=p.id_cliente
    JOIN datos_persona dp ON dp.id_datos_persona=c.id_datos_persona
    LEFT JOIN condicion_prestamo cp ON cp.id_condicion_prestamo = p.id_condicion_actual AND cp.esta_activo=1
    LEFT JOIN direccion d ON d.id_datos_persona = dp.id_datos_persona
    WHERE p.id_prestamo=$id_p")->fetch_assoc();
  $c = ['contacto' => '-', 'documento' => '-'];
  if ($p && !empty($p['id_datos_persona'])) {
    $id_dp = (int)$p['id_datos_persona'];

    $tel = $db->query("SELECT telefono 
    FROM telefono 
    WHERE id_datos_persona=$id_dp 
    AND es_principal=1 
    LIMIT 1")->fetch_assoc();
    
    $eml = $db->query("SELECT email 
    FROM email 
    WHERE id_datos_persona=$id_dp 
    AND es_principal=1 
    LIMIT 1")->fetch_assoc();
    $c['contacto'] = $tel['telefono'] ?? ($eml['email'] ?? '-');
    $doc = $db->query("
      SELECT di.numero_documento AS documento
      FROM documento_identidad di 
      JOIN cat_tipo_documento td ON td.id_tipo_documento=di.id_tipo_documento
      WHERE di.id_datos_persona=$id_dp
      ORDER BY (td.tipo_documento='Cedula') DESC, di.id_documento_identidad ASC
      LIMIT 1
    ")->fetch_assoc();
    if ($doc && !empty($doc['documento'])) $c['documento'] = $doc['documento'];
  }
  $resumen = $db->query("SELECT total_capital, total_interes, total_pagar 
  FROM resumen_cronograma 
  WHERE id_prestamo=$id_p")->fetch_assoc() ?: [];

  $des = $db->query("
    SELECT d.fecha_desembolso, tp.tipo_pago AS metodo
    FROM desembolso d
    LEFT JOIN cat_tipo_pago tp ON tp.id_tipo_pago=d.metodo_entrega
    WHERE d.id_prestamo=$id_p
    ORDER BY d.id_desembolso DESC
    LIMIT 1
  ")->fetch_assoc() ?: [];

  $metodos = $db->query("SELECT id_tipo_pago, tipo_pago FROM cat_tipo_pago")->fetch_all(MYSQLI_ASSOC);
  ?>
  <div style="font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; color:#111827; max-width:780px; margin:auto;">
    <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e5e7eb; padding-bottom:8px; margin-bottom:12px;">
      <div>
        <div style="font-weight:800; font-size:16px;">Nombre Empresa S.R.L.</div>
        <div class="mini">RNC: 1-00-00000-0 · · contacto@empresa.com</div>
      </div>
      <div style="text-align:right;">
        <div class="pill">Comprobante: CP-<?= str_pad($id_p,6,'0',STR_PAD_LEFT) ?></div>
        <div class="mini">Fecha de emisión: <?= date('Y-m-d') ?></div>
      </div>
    </div>

    <h3 style="margin:8px 0;">Datos del cliente</h3>
    <div class="panel" style="padding:12px;">
      <div><b>Nombre:</b> <?= htmlspecialchars($p['cliente'] ?? '-') ?></div>
      <div><b>Contacto:</b> <?= htmlspecialchars($c['contacto'] ?? '-') ?></div>
      <div><b>Cédula:</b> <?= htmlspecialchars($c['documento'] ?? '-') ?></div>
      <div><b>Dirección:</b> <?= htmlspecialchars($p['direccion_completa'] ?? '-') ?></div>
      <div><b>Codigo de contrato:</b> <?= htmlspecialchars($p['numero_contrato'] ?? '-') ?></div>
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
          <td style="padding:8px;"><?= (float)$p['tasa_interes'] ?>%</td>
          <td style="padding:8px; text-align:right;">—</td>
        </tr>
        <tr>
          <td style="padding:8px;">Interés total del préstamo</td>
          <td style="padding:8px;">Según cronograma completo</td>
          <td style="padding:8px; text-align:right;">$<?= number_format((float)($resumen['total_interes'] ?? 0), 2) ?></td>
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
        <div><b>Fecha de desembolso:</b> <?= date('Y-m-d') ?></div>
        <div><b>Estado del préstamo:</b> Activo</div>
      </div>
      <div class="panel" style="padding:12px;">
        <div style="height:60px; border:1px dashed #d1d5db; border-radius:8px; display:flex; align-items:center; justify-content:center;">
          <span class="mini">Firma aquí</span>
        </div>
        <p class="mini" style="margin-top:8px;">Este comprobante es válido como constancia del pago realizado. No requiere firma física si fue emitido digitalmente por el sistema.</p>
      </div>
    </div>
  </div>
  <?php
  exit;
}

if ($act === 'calcular_liquidacion'){
  $id_prestamo = (int)($_POST['id_prestamo'] ?? 0);

  $sql = "SELECT p.id_prestamo, p.id_politica_cancelacion, pc.porcentaje_penalidad, pc.nombre_politica
         FROM prestamo p
         LEFT JOIN politicas_cancelacion pc ON p.id_politica_cancelacion = pc.id_politica_cancelacion
         WHERE p.id_prestamo = ?";
  [$st, $err] = q($db, $sql, [$id_prestamo], 'i');
  $info = $st->get_result()->fetch_assoc();

  if (!$info) out(['ok' => false, 'msg' => 'Prestamo no encontrado.']);

  $sqlCap = "SELECT COALESCE(SUM(capital_cuota), 0) AS capital_pendiente
             FROM cronograma_cuota
             WHERE id_prestamo = ? AND estado_cuota = 'Pagada'";
  $capital = (float)($db->query("SELECT COALESCE(SUM(capital_cuota), 0) 
                                FROM cronograma_cuota
                                WHERE id_prestamo = $id_prestamo AND estado_cuota != 'Pagada'")->fetch_row()[0]??0);

  $cargos = (float)($db->query("SELECT COALESCE(SUM(cargos_cuota), 0) 
                               FROM cronograma_cuota
                               WHERE id_prestamo = $id_prestamo AND estado_cuota != 'Pagada'")->fetch_row()[0]??0);
  $interes_vencido = (float)($db->query("SELECT COALESCE(SUM(interes_cuota), 0)
                                       FROM cronograma_cuota
                                       WHERE id_prestamo = $id_prestamo AND estado_cuota = 'Vencida'")->fetch_row()[0] ?? 0);
  $porcentaje = (float)($info['porcentaje_penalidad'] ?? 0);
  $penalidad = round($capital * ($porcentaje / 100), 2);

  $total_pagar = $capital + $interes_vencido + $cargos + $penalidad;

  out([
    'ok' => true,
    'data' => [
      'politica' => $info['nombre_politica'] ?? 'Sin politica (0%)',
      'porcentaje' => $porcentaje,
      'capital_pendiente' => $capital,
      'interes_vencido' => $interes_vencido,
      'cargos_mora' => $cargos,
      'penalidad' => $penalidad,
      'total_cancelacion' => $total_pagar
    ]
  ]);
}

if ($act === 'procesar_cancelacion'){
  $id_prestamo = (int)($_POST['id_prestamo']?? 0);
  $total_recibido = (float)($_POST['total_recibido'] ?? 0);
  $metodo = (int)($_POST['metodo_pago'] ?? 1);
  $id_moneda = (int)($_POST['id_tipo_moneda'] ?? 1);
  $notas = $_POST['notas'] ?? '';
  if ($id_prestamo <= 0) {
    out(['ok' => false, 'msg' => 'ID de préstamo inválido.']);
  }
  if ($total_recibido <= 0) {
    out(['ok' => false, 'msg' => 'Debe indicar el monto pagado para la cancelación.']);
  }

  $rowEstado = $db->query("SELECT p.id_estado_prestamo,
                                  (SELECT id_estado_prestamo FROM cat_estado_prestamo WHERE estado='Cancelado' LIMIT 1) AS id_cancelado
                           FROM prestamo p
                           WHERE p.id_prestamo = $id_prestamo")->fetch_assoc();
  $estado_actual = (int)($rowEstado['id_estado_prestamo'] ?? 0);
  $id_cancelado  = (int)($rowEstado['id_cancelado'] ?? 0);
  if ($id_cancelado && $estado_actual === $id_cancelado) {
    out(['ok' => false, 'msg' => 'El préstamo ya está cancelado.']);
  }

  $db->autocommit(false);
  try {
    $monto_recibido_dop = moneyDOP($db, $total_recibido, $id_moneda);
    [$stP, $errP] = q($db, "INSERT INTO pago (id_prestamo, fecha_pago, monto_pagado, metodo_pago, id_tipo_moneda, creado_por) 
                VALUES (?,CURDATE(),?,?,?,1)", 
                [$id_prestamo, $monto_recibido_dop, $metodo, $id_moneda], 'idii');
    $db->query("UPDATE cronograma_cuota SET estado_cuota='Pagada', saldo_cuota=0 WHERE id_prestamo=$id_prestamo AND estado_cuota!='Pagada'");

    $db->query("UPDATE prestamo SET id_estado_prestamo=$id_cancelado, id_condicion_actual=NULL WHERE id_prestamo=$id_prestamo");

    $db->commit();
    out(['ok' => true, 'msg' => 'Préstamo cancelado exitosamente.', 'monto_recibido' => $monto_recibido_dop]);
  } catch (Exception $e) {
    $db->rollback();
    out(['ok' => false, 'msg' => $e->getMessage()]);
  }finally {
    $db->autocommit(true);
  }
}

if ($act === 'ejecutar_garantia'){
  $id_prestamo = (int)($_POST['id_prestamo'] ?? 0);
  $observacion = $_POST['observacion'] ?? 'Ejecucion por mora excesiva';
  if ($id_prestamo <= 0) {
    out(['ok' => false, 'msg' => 'ID de préstamo inválido.']);
  }

  $db->autocommit(false);
  try {
    [$st_est, $err_est] = q($db, "SELECT id_estado_prestamo
                                  FROM cat_estado_prestamo
                                  WHERE estado = 'Garantia usada'
                                  LIMIT 1");
    if (!$st_est) throw new Exception($err_est ?: "No se pudo consultar el estado de garantía usada.");
    $row_est = $st_est->get_result()->fetch_assoc();
    $st_est->close();

    $id_estado_g = (int)($row_est['id_estado_prestamo'] ?? 0);
    if (!$id_estado_g) throw new Exception("Estado 'Garantia Usada' no existe.");

    [$st_upd, $err_upd] = q($db, "UPDATE prestamo SET id_estado_prestamo=? WHERE id_prestamo=?", [$id_estado_g, $id_prestamo], 'ii');
    if (!$st_upd) throw new Exception($err_upd ?: "No se pudo actualizar el estado del préstamo.");

    [$st_g, $err_g] = q($db, "SELECT id_garantia FROM garantia WHERE id_prestamo=? ORDER BY id_garantia DESC LIMIT 1", [$id_prestamo], 'i');
    if (!$st_g) throw new Exception($err_g ?: "No se pudo consultar la garantía del préstamo.");
    $row_g = $st_g->get_result()->fetch_assoc();
    $st_g->close();
    $id_garantia = (int)($row_g['id_garantia'] ?? 0);

    if ($id_garantia > 0) {
      [$st_ej, $err_ej] = q(
        $db,
        "INSERT INTO ejecucion_garantia (id_prestamo, id_garantia, fecha_ejecucion, valor_adjudicado, observacion)
         VALUES (?, ?, CURDATE(), 0, ?)",
        [$id_prestamo, $id_garantia, $observacion],
        'iis'
      );
    } else {
      [$st_ej, $err_ej] = q(
        $db,
        "INSERT INTO ejecucion_garantia (id_prestamo, fecha_ejecucion, valor_adjudicado, observacion)
         VALUES (?, CURDATE(), 0, ?)",
        [$id_prestamo, $observacion],
        'is'
      );
    }
    if (!$st_ej) throw new Exception($err_ej ?: "No se pudo registrar la ejecución de garantía.");

    $db->commit();
    out(['ok' => true, 'msg' => 'Garantia ejecutada correctamente.']);
  } catch (Exception $e) {
    $db->rollback();
    out(['ok' => false, 'msg' => $e->getMessage()]);
  }finally {
    $db->autocommit(true);
}
}

if ($act === 'historial_cliente'){
  $id_cliente = (int)($_POST['id_cliente'] ?? 0);
  if ($id_cliente <= 0){
    out(['ok' => false, 'msg' => 'ID de cliente inválido.']);
  }

  $sql_h = "
    SELECT p.id_prestamo, p.numero_contrato,
     p.fecha_solicitud, p.monto_solicitado,
           cep.estado,
           (SELECT COUNT(*) FROM cronograma_cuota cc WHERE cc.id_prestamo = p.id_prestamo AND cc.estado_cuota = 'Pendiente' AND cc.fecha_vencimiento < CURDATE()) AS cuotas_atrasadas,
           (SELECT COALESCE(SUM(total_monto), 0) FROM cronograma_cuota cc WHERE cc.id_prestamo = p.id_prestamo) AS saldo_actual,
           (SELECT COALESCE(total_monto, 0) FROM cronograma_cuota cc WHERE cc.id_prestamo = p.id_prestamo AND cc.estado_cuota = 'Pendiente' ORDER BY cc.fecha_vencimiento ASC LIMIT 1) AS total_a_pagar,
              (SELECT COUNT(*) FROM cronograma_cuota cc WHERE cc.id_prestamo = p.id_prestamo AND cc.estado_cuota = 'Pagada') AS cuotas_pagadas
    FROM prestamo p
    LEFT JOIN cat_estado_prestamo cep ON cep.id_estado_prestamo = p.id_estado_prestamo
    WHERE p.id_cliente = ?
    ORDER BY p.creado_en DESC
  ";
  $st = $db->prepare($sql_h);
  if (!$st) {
    out(['ok' => false, 'msg' => 'Error preparando consulta: ' . $db->error]);
  }
  $st->bind_param('i', $id_cliente);
  if (!$st->execute()) {
    out(['ok' => false, 'msg' => 'Error ejecutando consulta: ' . $st->error]);
  }
  $prestamos = $st->get_result()->fetch_all(MYSQLI_ASSOC);
  $st->close();

  $tiene_atrasos = false;
  $tiene_historial = count($prestamos) > 0;
  foreach ($prestamos as $p) {
    if (!empty($p['cuotas_atrasadas']) && $p['cuotas_atrasadas'] > 0) {
      $tiene_atrasos = true;
      break;
    }
  }
  out([
    'ok' => true,
    'historial' => $prestamos,
    'promocion' => [
      'aplica' => $tiene_historial && !$tiene_atrasos,
      'mensaje' => ($tiene_historial && !$tiene_atrasos)
        ? 'Cliente elegible para promoción por buen historial.'
        : 'Cliente no elegible para promoción por historial de pagos.'
    ]
  ]);
}

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
if ($act==='garantias'){
  $r = $db->query("SELECT id_tipo_garantia AS id, tipo_garantia AS txt FROM cat_tipo_garantia")->fetch_all(MYSQLI_ASSOC);
  out(['ok'=>true,'data'=>$r]);
}
out(['ok'=>false,'msg'=>'Acción no reconocida']);
