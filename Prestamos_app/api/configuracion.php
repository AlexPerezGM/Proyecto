<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';

if (function_exists('mysqli_report')) {
  mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
}

function out($arr){ echo json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE); exit; }
function ok($data=[]){
  if (isset($data['rows']) && !isset($data['data'])) $data['data'] = $data['rows'];
  $data['ok'] = true;
  out($data);
}
function bad($msg, $code=500){ http_response_code($code); out(['ok'=>false,'error'=>$msg]); }
function s($k,$def=''){ return isset($_POST[$k]) ? trim((string)$_POST[$k]) : (isset($_GET[$k]) ? trim((string)$_GET[$k]) : $def); }
function i($k,$def=0){ $v = $_POST[$k] ?? $_GET[$k] ?? $def; return is_numeric($v) ? (int)$v : (int)$def; }
function fnum_in($k){ $v = $_POST[$k] ?? $_GET[$k] ?? null; return is_numeric($v) ? (float)$v : 0.0; }

function bind_params_safe(mysqli_stmt $st, string $types, array $params): bool {
  if ($types === '' || empty($params)) return true;
  $refs = [];
  foreach ($params as $k=>$v) { $refs[$k] = &$params[$k]; }
  return $st->bind_param($types, ...$refs);
}
try {
  $action = s('action','list');
  $module = s('module','');
  session_start();
  if (!isset($_SESSION['usuario'])) {
    bad('No autorizado', 401);
  }
  if ($module === 'mora' && $action === 'apply') {
    try {
      if ($conn->multi_query("CALL aplicar_mora")) {
        do {
          if ($rs = $conn->store_result()) {
            $rs->free();
          }
        } while ($conn->more_results() && $conn->next_result());
      } else {
        throw new Exception($conn->error ?: 'No se pudo ejecutar la aplicación de mora');
      }
      ok(['message' => 'Mora aplicada correctamente.']);
    } catch (Throwable $e) {
      bad($e->getMessage());
    }
    exit;
  }

  switch($module) {
    case 'deduccion':
      handleDeduccion($conn, $action);
      break;
    case 'moneda':
      handleMoneda($conn, $action);
      break;
    case 'contrato':
      handleContrato($conn, $action);
      break;
    case 'garantia':
      handleGarantia($conn, $action);
      break;
    case 'beneficio':
      handleBeneficio($conn, $action);
      break;
    case 'configuracion':
      handleConfiguracion($conn, $action);
      break;
    case 'mora':
      handleMora($conn, $action);
      break;
    case 'tipo_prestamo':
      handleTipoPrestamo($conn, $action);
      break;
    case 'auditoria':
      handleAuditoria($conn, $action);
      break;
    case 'fondos':
      handleFondos($conn, $action);
      break;
    case 'caja':
      handleCaja($conn, $action);
      break;
    case 'plantilla_notificacion':
      handlePlantillaNotificacion($conn, $action);
      break;
    case 'politica_cancelacion':
      handlePoliticaCancelacion($conn, $action);
      break;
    default:
      bad('Módulo no válido');
  }

} catch (Exception $e) {
  error_log("Error en configuracion.php: " . $e->getMessage());
  bad('Error interno del servidor');
}

function handleDeduccion($conn, $action) {
  switch($action) {
    case 'list':
      $stmt = $conn->prepare("SELECT id_tipo_deduccion, tipo_deduccion, valor 
      FROM cat_tipo_deduccion 
      ORDER BY tipo_deduccion");
      $stmt->execute();
      $result = $stmt->get_result();
      $data = [];
      while ($row = $result->fetch_assoc()) {
        $data[] = $row;
      }
      ok(['data' => $data]);
      break;

    case 'save':
      $id = i('id');
      $tipo = s('tipo_deduccion');
      $valor = fnum_in('valor');

      if (empty($tipo)) 
        bad('El tipo de deducción es requerido');
      if ($valor < 0 || $valor > 100) 
        bad('El valor debe estar entre 0 y 100');

      if ($id > 0) {
        $stmt = $conn->prepare("UPDATE cat_tipo_deduccion SET tipo_deduccion = ?, valor = ? WHERE id_tipo_deduccion = ?");
        $stmt->bind_param('sdi', $tipo, $valor, $id);
        $stmt->execute();
        if ($stmt->affected_rows === 0) 
          bad('No se encontró el registro a actualizar');
      } else {
        $stmt = $conn->prepare("INSERT INTO cat_tipo_deduccion (tipo_deduccion, valor) 
        VALUES (?, ?)");
        $stmt->bind_param('sd', $tipo, $valor);
        $stmt->execute();
      }
      ok(['message' => 'Deducción guardada exitosamente']);
      break;

    case 'delete':
      $id = i('id');
      if ($id <= 0) bad('ID inválido');

      $stmt = $conn->prepare("DELETE FROM cat_tipo_deduccion WHERE id_tipo_deduccion = ?");
      $stmt->bind_param('i', $id);
      $stmt->execute();
      if ($stmt->affected_rows === 0) 
        bad('No se encontró el registro a eliminar');

      ok(['message' => 'Deducción eliminada exitosamente']);
      break;

    default:
      bad('Acción no válida');
  }
}

function handleMoneda($conn, $action) {
  switch($action) {
    case 'list':
      $stmt = $conn->prepare("SELECT id_tipo_moneda, tipo_moneda, valor 
      FROM cat_tipo_moneda 
      ORDER BY tipo_moneda");
      $stmt->execute();
      $result = $stmt->get_result();
      $data = [];
      while ($row = $result->fetch_assoc()) {
        $data[] = $row;
      }
      ok(['data' => $data]);
      break;

    case 'save':
      $id = i('id');
      $tipo = s('tipo_moneda');
      $valor = fnum_in('valor');

      if (empty($tipo)) 
        bad('El tipo de moneda es requerido');
      if ($valor < 0) 
        bad('El valor debe ser mayor a 0');

      if ($id > 0) {
        $stmt = $conn->prepare("UPDATE cat_tipo_moneda SET tipo_moneda = ?, valor = ? WHERE id_tipo_moneda = ?");
        $stmt->bind_param('sdi', $tipo, $valor, $id);
        $stmt->execute();
        if ($stmt->affected_rows === 0) 
          bad('No se encontró el registro a actualizar');
      } else {
        $stmt = $conn->prepare("INSERT INTO cat_tipo_moneda (tipo_moneda, valor) VALUES (?, ?)");
        $stmt->bind_param('sd', $tipo, $valor);
        $stmt->execute();
      }
      ok(['message' => 'Moneda guardada exitosamente']);
      break;

    case 'delete':
      $id = i('id');
      if ($id <= 0) 
        bad('ID inválido');

      $stmt = $conn->prepare("DELETE FROM cat_tipo_moneda WHERE id_tipo_moneda = ?");
      $stmt->bind_param('i', $id);
      $stmt->execute();
      if ($stmt->affected_rows === 0) 
        bad('No se encontró el registro a eliminar');

      ok(['message' => 'Moneda eliminada exitosamente']);
      break;

    default:
      bad('Acción no válida');
  }
}

function handleContrato($conn, $action) {
  switch($action) {
    case 'list':
      $stmt = $conn->prepare("SELECT id_tipo_contrato, tipo_contrato 
      FROM cat_tipo_contrato 
      ORDER BY tipo_contrato");
      $stmt->execute();
      $result = $stmt->get_result();
      $data = [];
      while ($row = $result->fetch_assoc()) {
        $data[] = $row;
      }
      ok(['data' => $data]);
      break;

    case 'save':
      $id = i('id');
      $tipo = s('tipo_contrato');

      if (empty($tipo)) 
        bad('El tipo de contrato es requerido');

      if ($id > 0) {
        $stmt = $conn->prepare("UPDATE cat_tipo_contrato SET tipo_contrato = ? WHERE id_tipo_contrato = ?");
        $stmt->bind_param('si', $tipo, $id);
        $stmt->execute();
        if ($stmt->affected_rows === 0) 
          bad('No se encontró el registro a actualizar');
      } else {
        $stmt = $conn->prepare("INSERT INTO cat_tipo_contrato (tipo_contrato) VALUES (?)");
        $stmt->bind_param('s', $tipo);
        $stmt->execute();
      }
      ok(['message' => 'Tipo de contrato guardado exitosamente']);
      break;

    case 'delete':
      $id = i('id');
      if ($id <= 0) 
        bad('ID inválido');

      $stmt = $conn->prepare("DELETE FROM cat_tipo_contrato WHERE id_tipo_contrato = ?");
      $stmt->bind_param('i', $id);
      $stmt->execute();
      if ($stmt->affected_rows === 0) 
        bad('No se encontró el registro a eliminar');

      ok(['message' => 'Tipo de contrato eliminado exitosamente']);
      break;

    default:
      bad('Acción no válida');
  }
}

function handleGarantia($conn, $action) {
  switch($action) {
    case 'list':
      $stmt = $conn->prepare("SELECT id_tipo_garantia, tipo_garantia, descripcion 
      FROM cat_tipo_garantia 
      ORDER BY tipo_garantia");
      $stmt->execute();
      $result = $stmt->get_result();
      $data = [];
      while ($row = $result->fetch_assoc()) {
        $data[] = $row;
      }
      ok(['data' => $data]);
      break;

    case 'save':
      $id = i('id');
      $tipo = s('tipo_garantia');
      $descripcion = s('descripcion');

      if (empty($tipo)) 
        bad('El tipo de garantía es requerido');

      if ($id > 0) {
        $stmt = $conn->prepare("UPDATE cat_tipo_garantia SET tipo_garantia = ?, descripcion = ? WHERE id_tipo_garantia = ?");
        $stmt->bind_param('ssi', $tipo, $descripcion, $id);
        $stmt->execute();
        if ($stmt->affected_rows === 0) bad('No se encontró el registro a actualizar');
      } else {
        $stmt = $conn->prepare("INSERT INTO cat_tipo_garantia (tipo_garantia, descripcion) VALUES (?, ?)");
        $stmt->bind_param('ss', $tipo, $descripcion);
        $stmt->execute();
      }
      ok(['message' => 'Tipo de garantía guardado exitosamente']);
      break;

    case 'delete':
      $id = i('id');
      if ($id <= 0) 
        bad('ID inválido');

      $stmt = $conn->prepare("DELETE FROM cat_tipo_garantia WHERE id_tipo_garantia = ?");
      $stmt->bind_param('i', $id);
      $stmt->execute();
      if ($stmt->affected_rows === 0) 
        bad('No se encontró el registro a eliminar');

      ok(['message' => 'Tipo de garantía eliminado exitosamente']);
      break;

    default:
      bad('Acción no válida');
  }
}

function handleBeneficio($conn, $action) {
  switch($action) {
    case 'list':
      $stmt = $conn->prepare("SELECT id_beneficio, tipo_beneficio, valor 
      FROM cat_beneficio 
      ORDER BY tipo_beneficio");
      $stmt->execute();
      $result = $stmt->get_result();
      $data = [];
      while ($row = $result->fetch_assoc()) {
        $data[] = $row;
      }
      ok(['data' => $data]);
      break;

    case 'save':
      $id = i('id');
      $tipo = s('tipo_beneficio');
      $valor = fnum_in('valor');

      if (empty($tipo)) 
        bad('El tipo de beneficio es requerido');
      if ($valor < 0) 
        bad('El valor debe ser mayor o igual a 0');

      if ($id > 0) {
        $stmt = $conn->prepare("UPDATE cat_beneficio SET tipo_beneficio = ?, valor = ? WHERE id_beneficio = ?");
        $stmt->bind_param('sdi', $tipo, $valor, $id);
        $stmt->execute();
        if ($stmt->affected_rows === 0) 
          bad('No se encontró el registro a actualizar');
        
        $config_key = 'BENEFICIO_' . strtoupper(str_replace(' ', '_', $tipo));
        $stmt = $conn->prepare("UPDATE configuracion SET valor_decimal = ? WHERE nombre_configuracion = ?");
        $stmt->bind_param('ds', $valor, $config_key);
        $stmt->execute();
      } else {
        $stmt = $conn->prepare("INSERT INTO cat_beneficio (tipo_beneficio, valor) VALUES (?, ?)");
        $stmt->bind_param('sd', $tipo, $valor);
        $stmt->execute();
        
        $config_key = 'BENEFICIO_' . strtoupper(str_replace(' ', '_', $tipo));
        $stmt = $conn->prepare("INSERT INTO configuracion (nombre_configuracion, valor_decimal, estado) VALUES (?, ?, 'Activo')");
        $stmt->bind_param('sd', $config_key, $valor);
        $stmt->execute();
      }
      ok(['message' => 'Beneficio guardado exitosamente']);
      break;

    case 'delete':
      $id = i('id');
      if ($id <= 0) 
        bad('ID inválido');

      $stmt = $conn->prepare("SELECT tipo_beneficio FROM cat_beneficio WHERE id_beneficio = ?");
      $stmt->bind_param('i', $id);
      $stmt->execute();
      $result = $stmt->get_result();
      $beneficio = $result->fetch_assoc();
      
      if (!$beneficio) 
        bad('No se encontró el beneficio');

      $stmt = $conn->prepare("DELETE FROM cat_beneficio WHERE id_beneficio = ?");
      $stmt->bind_param('i', $id);
      $stmt->execute();
      if ($stmt->affected_rows === 0) 
        bad('No se encontró el registro a eliminar');

      $config_key = 'BENEFICIO_' . strtoupper(str_replace(' ', '_', $beneficio['tipo_beneficio']));
      $stmt = $conn->prepare("DELETE FROM configuracion WHERE nombre_configuracion = ?");
      $stmt->bind_param('s', $config_key);
      $stmt->execute();

      ok(['message' => 'Beneficio eliminado exitosamente']);
      break;

    default:
      bad('Acción no válida');
  }
}

function handleConfiguracion($conn, $action) {
  switch($action) {
    case 'list':
      $stmt = $conn->prepare("SELECT id_configuracion, nombre_configuracion, valor_decimal, estado, actualizado_en 
      FROM configuracion 
      ORDER BY nombre_configuracion");
      $stmt->execute();
      $result = $stmt->get_result();
      $data = [];
      while ($row = $result->fetch_assoc()) {
        $data[] = $row;
      }
      ok(['data' => $data]);
      break;

    case 'save':
      $id = i('id');
      $nombre = s('nombre_configuracion');
      $valor = fnum_in('valor_decimal');
      $estado = s('estado');

      if (empty($nombre)) 
        bad('El nombre de la configuración es requerido');
      if (!in_array($estado, ['Activo', 'Inactivo'])) 
        bad('Estado inválido');

      if ($id > 0) {
        $stmt = $conn->prepare("UPDATE configuracion SET nombre_configuracion = ?, valor_decimal = ?, estado = ? 
        WHERE id_configuracion = ?");
        $stmt->bind_param('sdsi', $nombre, $valor, $estado, $id);
        $stmt->execute();
        if ($stmt->affected_rows === 0) 
          bad('No se encontró el registro a actualizar');
      } else {
        $stmt = $conn->prepare("INSERT INTO configuracion (nombre_configuracion, valor_decimal, estado) VALUES (?, ?, ?)");
        $stmt->bind_param('sds', $nombre, $valor, $estado);
        $stmt->execute();
      }
      ok(['message' => 'Configuración guardada exitosamente']);
      break;

    case 'delete':
      $id = i('id');
      if ($id <= 0) 
        bad('ID inválido');

      $stmt = $conn->prepare("DELETE FROM configuracion WHERE id_configuracion = ?");
      $stmt->bind_param('i', $id);
      $stmt->execute();
      if ($stmt->affected_rows === 0) 
        bad('No se encontró el registro a eliminar');

      ok(['message' => 'Configuración eliminada exitosamente']);
      break;

    default:
      bad('Acción no válida');
  }
}

function handleMora($conn, $action) {
  switch($action) {
    case 'list':
      $stmt = $conn->prepare("SELECT id_mora, fecha_registro, dias_gracia, porcentaje_mora, vigente_desde, vigente_hasta, estado 
      FROM config_mora 
      ORDER BY vigente_desde DESC");
      $stmt->execute();
      $result = $stmt->get_result();
      $data = [];
      while ($row = $result->fetch_assoc()) {
        $data[] = $row;
      }
      ok(['data' => $data]);
      break;

    case 'save':
      $id = i('id');
      $dias_gracia = i('dias_gracia');
      $porcentaje_mora = fnum_in('porcentaje_mora');
      $vigente_desde = s('vigente_desde');
      $vigente_hasta = s('vigente_hasta');
      $estado = s('estado');

      if ($module === 'mora' && $action === 'apply'){
        try {
          if ($conn->multi_query("CALL aplicar_mora")){
            do{
              if ($rs = $conn->store_result()) {
                $rs->free();
              }
            } while ($conn->more_results() && $conn->next_result());
          } else {
              throw new Exception($conn->error ?: 'No se pudo ejecutar la aplicacion de mora');
          }
          echo json_encode([
              'ok'      => true,
              'message' => 'Mora aplicada correctamente.'
              ]);
        } catch (Throwable $e){
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        break;
        exit;}

      if ($porcentaje_mora < 0 || $porcentaje_mora > 50) 
        bad('El porcentaje de mora debe estar entre 0 y 50');
      if ($dias_gracia < 0) 
        bad('Los días de gracia no pueden ser negativos');
      if (empty($vigente_desde)) 
        bad('La fecha vigente desde es requerida');
      if (!in_array($estado, ['Activo', 'Inactivo'])) 
        bad('Estado inválido');

      if ($id > 0) {
        $stmt = $conn->prepare("UPDATE config_mora SET dias_gracia = ?, porcentaje_mora = ?, vigente_desde = ?, vigente_hasta = ?, estado = ? 
        WHERE id_mora = ?");
        $stmt->bind_param('idsssi', $dias_gracia, $porcentaje_mora, $vigente_desde, $vigente_hasta, $estado, $id);
        $stmt->execute();
        if ($stmt->affected_rows === 0) 
          bad('No se encontró el registro a actualizar');
      } else {
        $vigente_hasta_param = empty($vigente_hasta) ? null : $vigente_hasta;
        $stmt = $conn->prepare("INSERT INTO config_mora (fecha_registro, dias_gracia, porcentaje_mora, vigente_desde, vigente_hasta, estado) 
        VALUES (CURDATE(), ?, ?, ?, ?, ?)");
        $stmt->bind_param('idsss', $dias_gracia, $porcentaje_mora, $vigente_desde, $vigente_hasta_param, $estado);
        $stmt->execute();
      }
      ok(['message' => 'Configuración de mora guardada exitosamente']);
      break;

    case 'delete':
      $id = i('id');
      if ($id <= 0) 
        bad('ID inválido');

      $stmt = $conn->prepare("DELETE FROM config_mora WHERE id_mora = ?");
      $stmt->bind_param('i', $id);
      $stmt->execute();
      if ($stmt->affected_rows === 0) 
        bad('No se encontró el registro a eliminar');

      ok(['message' => 'Configuración de mora eliminada exitosamente']);
      break;

    default:
      bad('Acción no válida');
  }
}

function handleTipoPrestamo($conn, $action) {
  switch($action) {
    case 'list':
      $stmt = $conn->prepare("
        SELECT tp.id_tipo_prestamo, tp.nombre, tp.tasa_interes, tp.monto_minimo, 
               tp.plazo_minimo_meses, tp.plazo_maximo_meses, ta.tipo_amortizacion
        FROM tipo_prestamo tp 
        LEFT JOIN cat_tipo_amortizacion ta ON tp.id_tipo_amortizacion = ta.id_tipo_amortizacion
        ORDER BY tp.nombre
      ");
      $stmt->execute();
      $result = $stmt->get_result();
      $data = [];
      while ($row = $result->fetch_assoc()) {
        $data[] = $row;
      }
      ok(['data' => $data]);
      break;

    case 'save':
      $id = i('id');
      $nombre = s('nombre');
      $tasa_interes = fnum_in('tasa_interes');
      $monto_minimo = fnum_in('monto_minimo');
      $id_tipo_amortizacion = i('id_tipo_amortizacion');
      $plazo_minimo = i('plazo_minimo_meses');
      $plazo_maximo = i('plazo_maximo_meses');

      if (empty($nombre)) 
        bad('El nombre del tipo de préstamo es requerido');
      if ($tasa_interes < 0) 
        bad('La tasa de interés no puede ser negativa');
      if ($monto_minimo < 0) 
        bad('El monto mínimo no puede ser negativo');
      if ($plazo_minimo <= 0 || $plazo_maximo <= 0) 
        bad('Los plazos deben ser mayores a 0');
      if ($plazo_minimo > $plazo_maximo) 
        bad('El plazo mínimo no puede ser mayor al máximo');
      if ($id > 0) {
        $stmt = $conn->prepare("UPDATE tipo_prestamo SET nombre = ?, tasa_interes = ?, monto_minimo = ?, id_tipo_amortizacion = ?, plazo_minimo_meses = ?, plazo_maximo_meses = ? 
        WHERE id_tipo_prestamo = ?");
        $stmt->bind_param('sddiiii', $nombre, $tasa_interes, $monto_minimo, $id_tipo_amortizacion, $plazo_minimo, $plazo_maximo, $id);
        $stmt->execute();
        if ($stmt->affected_rows === 0) 
          bad('No se encontró el registro a actualizar');
      } else {
        $stmt = $conn->prepare("INSERT INTO tipo_prestamo (nombre, tasa_interes, monto_minimo, id_tipo_amortizacion, plazo_minimo_meses, plazo_maximo_meses) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('sddiii', $nombre, $tasa_interes, $monto_minimo, $id_tipo_amortizacion, $plazo_minimo, $plazo_maximo);
        $stmt->execute();
      }
      ok(['message' => 'Tipo de préstamo guardado exitosamente']);
      break;

    case 'delete':
      $id = i('id');
      if ($id <= 0) 
        bad('ID inválido');

      $stmt = $conn->prepare("DELETE FROM tipo_prestamo WHERE id_tipo_prestamo = ?");
      $stmt->bind_param('i', $id);
      $stmt->execute();
      if ($stmt->affected_rows === 0) 
        bad('No se encontró el registro a eliminar');

      ok(['message' => 'Tipo de préstamo eliminado exitosamente']);
      break;

    default:
      bad('Acción no válida');
  }
}

function handleAuditoria($conn, $action) {
  switch($action) {
    case 'list':
      $tabla = s('tabla');
      $tipo = s('tipo');
      $fecha = s('fecha');
      
      $where = [];
      $params = [];
      $types = '';

      if (!empty($tabla)) {
        $where[] = "a.tabla_afectada = ?";
        $params[] = $tabla;
        $types .= 's';
      }

      if (!empty($tipo)) {
        $where[] = "a.tipo_cambio = ?";
        $params[] = $tipo;
        $types .= 's';
      }

      if (!empty($fecha)) {
        $where[] = "DATE(a.fecha_cambio) = ?";
        $params[] = $fecha;
        $types .= 's';
      }

      $whereClause = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

      $sql = "
        SELECT a.id_auditoria, a.tabla_afectada, a.id_registro, a.tipo_cambio, 
               a.fecha_cambio, a.valores_anteriores, a.valores_nuevos,
               COALESCE(CONCAT(dp.nombre, ' ', dp.apellido), 'Sistema') as usuario
        FROM auditoria_cambios a
        LEFT JOIN usuario u ON a.realizado_por = u.id_usuario
        LEFT JOIN datos_persona dp ON u.id_datos_persona = dp.id_datos_persona
        $whereClause
        ORDER BY a.fecha_cambio DESC
        LIMIT 100
      ";

      $stmt = $conn->prepare($sql);
      if (!empty($params)) {
        bind_params_safe($stmt, $types, $params);
      }
      $stmt->execute();
      $result = $stmt->get_result();
      $data = [];
      while ($row = $result->fetch_assoc()) {
        $data[] = $row;
      }
      ok(['data' => $data]);
      break;

    case 'detail':
      $id = i('id');
      if ($id <= 0) bad('ID inválido');

      $stmt = $conn->prepare("
        SELECT a.*, COALESCE(CONCAT(dp.nombre, ' ', dp.apellido), 'Sistema') as usuario
        FROM auditoria_cambios a
        LEFT JOIN usuario u ON a.realizado_por = u.id_usuario
        LEFT JOIN datos_persona dp ON u.id_datos_persona = dp.id_datos_persona
        WHERE a.id_auditoria = ?
      ");
      $stmt->bind_param('i', $id);
      $stmt->execute();
      $result = $stmt->get_result();
      $data = $result->fetch_assoc();
      
      if (!$data) 
        bad('Registro de auditoría no encontrado');
      
      ok(['data' => $data]);
      break;

    default:
      bad('Acción no válida');
  }
}
function handleFondos($conn, $action) {
  switch($action) {
    case 'list':
      $stmt = $conn->prepare("SELECT id_fondo, cartera_normal, cartera_vencida, total_fondos, actualizacion 
      FROM fondos 
      ORDER BY actualizacion 
      DESC LIMIT 1");
      $stmt->execute();
      $result = $stmt->get_result();
      $fondos = $result->fetch_assoc();
      $stmt = $conn->prepare("
        SELECT mf.id_mov_fondo, mf.monto, mf.fecha_movimiento, mf.tipo_movimiento,
               COALESCE(CONCAT(dp.nombre, ' ', dp.apellido), 'N/A') as empleado
        FROM movimiento_fondo mf
        LEFT JOIN caja c ON mf.id_caja = c.id_caja
        LEFT JOIN empleado e ON c.id_empleado = e.id_empleado
        LEFT JOIN datos_persona dp ON e.id_datos_persona = dp.id_datos_persona
        ORDER BY mf.fecha_movimiento DESC
        LIMIT 20
      ");
      $stmt->execute();
      $result = $stmt->get_result();
      $movimientos = [];
      while ($row = $result->fetch_assoc()) {
        $movimientos[] = $row;
      }

      ok(['fondos' => $fondos, 'movimientos' => $movimientos]);
      break;

    case 'initialize':
      $monto_inicial = fnum_in('monto_inicial');
      if ($monto_inicial <= 0) 
        bad('El monto inicial debe ser mayor a 0', 400);

      $stmt = $conn->prepare("SELECT id_fondo 
      FROM fondos 
      ORDER BY actualizacion DESC 
      LIMIT 1");
      $stmt->execute();
      $result = $stmt->get_result();
      if ($result->fetch_assoc()) {
        bad('Ya existe un registro de fondos, use un aporte en su lugar');
      }
      $stmt = $conn->prepare("INSERT INTO fondos (cartera_normal, cartera_vencida, total_fondos) VALUES (?, 0, ?)");
      $stmt->bind_param('dd', $monto_inicial, $monto_inicial);
      $stmt->execute();
      ok(['message' => 'Fondos iniciales registrados', 'fondos' => [
        'cartera_normal' => $monto_inicial,
        'cartera_vencida' => 0,
        'total_fondos' => $monto_inicial
      ]]);
      break;

    case 'aporte_directo':
      $monto = fnum_in('monto');
      if ($monto <= 0) 
        bad('El monto debe ser mayor a 0', 400);
      $stmt = $conn->prepare("SELECT id_fondo, cartera_normal, cartera_vencida, total_fondos 
      FROM fondos 
      ORDER BY actualizacion 
      DESC LIMIT 1");
      $stmt->execute();
      $result = $stmt->get_result();
      $fondos = $result->fetch_assoc();
      if (!$fondos) {
        $stmt = $conn->prepare("INSERT INTO fondos (cartera_normal, cartera_vencida, total_fondos) VALUES (0,0,0)");
        $stmt->execute();
        $fondos = ['id_fondo' => $conn->insert_id, 'cartera_normal' => 0, 'cartera_vencida' => 0, 'total_fondos' => 0];
      }
      $fondos['cartera_normal'] += $monto;
      $fondos['total_fondos'] += $monto;
      $stmt = $conn->prepare("UPDATE fondos SET cartera_normal = ?, total_fondos = ? WHERE id_fondo = ?");
      $stmt->bind_param('ddi', $fondos['cartera_normal'], $fondos['total_fondos'], $fondos['id_fondo']);
      $stmt->execute();

      $stmt = $conn->prepare("INSERT INTO movimiento_fondo (id_fondo, id_caja, monto, tipo_movimiento) 
      VALUES (?, NULL, ?, 'aporte')");
      $stmt->bind_param('id', $fondos['id_fondo'], $monto);
      $stmt->execute();

      ok(['message' => 'Aporte registrado', 'fondos' => [
        'cartera_normal' => $fondos['cartera_normal'],
        'cartera_vencida' => $fondos['cartera_vencida'],
        'total_fondos' => $fondos['total_fondos']
      ]]);
      break;

    case 'retiro_directo':
      $monto = fnum_in('monto');
      if ($monto <= 0) 
        bad('El monto debe ser mayor a 0', 400);
      $stmt = $conn->prepare("SELECT id_fondo, cartera_normal, cartera_vencida, total_fondos 
      FROM fondos 
      ORDER BY actualizacion 
      DESC LIMIT 1");
      $stmt->execute();
      $result = $stmt->get_result();
      $fondos = $result->fetch_assoc();
      if (!$fondos) {
        bad('Debe registrar fondos iniciales antes de retirar', 400);
      }
      if ($fondos['cartera_normal'] < $monto || $fondos['total_fondos'] < $monto) {
        bad('Fondos insuficientes para realizar el retiro', 400);
      }
      $nueva_cartera_normal = $fondos['cartera_normal'] - $monto;
      $nuevo_total = $fondos['total_fondos'] - $monto;

      $stmt = $conn->prepare("UPDATE fondos SET cartera_normal = ?, total_fondos = ? WHERE id_fondo = ?");
      $stmt->bind_param('ddi', $nueva_cartera_normal, $nuevo_total, $fondos['id_fondo']);
      $stmt->execute();

      $stmt = $conn->prepare("INSERT INTO movimiento_fondo (id_fondo, id_caja, monto, tipo_movimiento) 
      VALUES (?, NULL, ?, 'retiro')");
      $stmt->bind_param('id', $fondos['id_fondo'], $monto);
      $stmt->execute();

      ok(['message' => 'Retiro registrado', 'fondos' => [
        'cartera_normal' => $nueva_cartera_normal,
        'cartera_vencida' => $fondos['cartera_vencida'],
        'total_fondos' => $nuevo_total
      ]]);
      break;

    case 'movimiento':
      $monto = fnum_in('monto');
      $tipo = s('tipo_movimiento');
      $id_caja = i('id_caja');

      if ($monto <= 0) 
        bad('El monto debe ser mayor a 0', 400);

      if (!in_array($tipo, ['aporte', 'desembolso'])) 
        bad('Tipo de movimiento inválido', 400);

      if ($id_caja <= 0) 
        bad('Debe seleccionar una caja válida', 400);

      $stmt = $conn->prepare("SELECT estado_caja, monto_asignado 
      FROM caja 
      WHERE id_caja = ?");
      $stmt->bind_param('i', $id_caja);
      $stmt->execute();
      $result = $stmt->get_result();
      $caja = $result->fetch_assoc();

      if (!$caja || $caja['estado_caja'] !== 'Abierta') 
        bad('La caja seleccionada no está disponible', 400);
      if ($tipo === 'desembolso' && $caja['monto_asignado'] < $monto) 
        bad('La caja no tiene saldo suficiente para el desembolso', 400);
      $stmt = $conn->prepare("SELECT id_fondo FROM fondos ORDER BY actualizacion DESC LIMIT 1");
      $stmt->execute();
      $result = $stmt->get_result();
      $fondo = $result->fetch_assoc();

      if (!$fondo) {
        $stmt = $conn->prepare("INSERT INTO fondos (cartera_normal, cartera_vencida, total_fondos) VALUES (0, 0, 0)");
        $stmt->execute();
        $id_fondo = $conn->insert_id;
      } else {
        $id_fondo = $fondo['id_fondo'];
      }
      $stmt = $conn->prepare("SELECT cartera_normal, cartera_vencida, total_fondos 
      FROM fondos 
      WHERE id_fondo = ?");
      $stmt->bind_param('i', $id_fondo);
      $stmt->execute();
      $result = $stmt->get_result();
      $totales = $result->fetch_assoc();
      if (!$totales) 
        bad('Registro de fondos no encontrado');

      if ($tipo === 'aporte') {
        $totales['cartera_normal'] += $monto;
        $totales['total_fondos'] += $monto;
      } elseif ($tipo === 'desembolso') {
        if ($totales['cartera_normal'] < $monto) 
          bad('Fondos insuficientes para desembolso', 400);

        if ($totales['total_fondos'] < $monto) 
          bad('Total de fondos insuficiente', 400);

        $totales['cartera_normal'] -= $monto;
        $totales['total_fondos'] -= $monto;
        $nuevo_monto_caja = $caja['monto_asignado'] - $monto;
        if ($nuevo_monto_caja < 0) 
          bad('Error interno: saldo de caja negativo', 500);
        $stmt = $conn->prepare("UPDATE caja SET monto_asignado = ? WHERE id_caja = ?");
        $stmt->bind_param('di', $nuevo_monto_caja, $id_caja);
        $stmt->execute();
      }
      $stmt = $conn->prepare("UPDATE fondos SET cartera_normal = ?, total_fondos = ? WHERE id_fondo = ?");
      $stmt->bind_param('ddi', $totales['cartera_normal'], $totales['total_fondos'], $id_fondo);
      $stmt->execute();
      $stmt = $conn->prepare("INSERT INTO movimiento_fondo (id_fondo, id_caja, monto, tipo_movimiento) VALUES (?, ?, ?, ?)");
      $stmt->bind_param('iids', $id_fondo, $id_caja, $monto, $tipo);
      $stmt->execute();

      ok(['message' => 'Movimiento de fondo registrado exitosamente', 'fondos' => [
        'cartera_normal' => $totales['cartera_normal'],
        'cartera_vencida' => $totales['cartera_vencida'] ?? 0,
        'total_fondos' => $totales['total_fondos']
      ]]);
      break;

    default:
      bad('Acción no válida');
  }
}

function handleCaja($conn, $action) {
  switch($action) {
    case 'list':
      $stmt = $conn->prepare("
        SELECT c.id_caja, c.monto_asignado, c.fecha_apertura, c.fecha_cierre, c.estado_caja,
               CONCAT(dp.nombre, ' ', dp.apellido) as empleado
        FROM caja c
        LEFT JOIN empleado e ON c.id_empleado = e.id_empleado
        LEFT JOIN datos_persona dp ON e.id_datos_persona = dp.id_datos_persona
        ORDER BY c.fecha_apertura DESC
      ");
      $stmt->execute();
      $result = $stmt->get_result();
      $data = [];
      while ($row = $result->fetch_assoc()) {
        $data[] = $row;
      }
      ok(['data' => $data]);
      break;

    case 'open':
      $id_empleado = i('id_empleado');
      $monto_asignado = fnum_in('monto_asignado');

      if ($id_empleado <= 0) 
        bad('Debe seleccionar un empleado válido', 400);
      if ($monto_asignado < 0) 
        bad('El monto asignado no puede ser negativo', 400);
      $stmt = $conn->prepare("SELECT id_caja 
      FROM caja 
      WHERE id_empleado = ? 
      AND estado_caja = 'Abierta'");
      $stmt->bind_param('i', $id_empleado);
      $stmt->execute();
      $result = $stmt->get_result();
      if ($result->num_rows > 0) {
        bad('El empleado ya tiene una caja abierta', 400);
      }
      $stmt = $conn->prepare("SELECT id_fondo, cartera_normal, total_fondos 
      FROM fondos 
      ORDER BY actualizacion DESC LIMIT 1");
      $stmt->execute();
      $result = $stmt->get_result();
      $fondos = $result->fetch_assoc();
      if (!$fondos) 
        bad('Debe registrar fondos iniciales antes de abrir una caja', 400);
      if ($fondos['cartera_normal'] < $monto_asignado || $fondos['total_fondos'] < $monto_asignado) 
        bad('Fondos insuficientes para asignar a la caja', 400);
      $stmt = $conn->prepare("INSERT INTO caja (id_empleado, monto_asignado, estado_caja) VALUES (?, ?, 'Abierta')");
      $stmt->bind_param('id', $id_empleado, $monto_asignado);
      $stmt->execute();
      ok(['message' => 'Caja abierta exitosamente']);
      break;

    case 'close':
      $id = i('id');
      if ($id <= 0) bad('ID inválido');

      $stmt = $conn->prepare("UPDATE caja SET estado_caja = 'Cerrada', fecha_cierre = NOW() WHERE id_caja = ? AND estado_caja = 'Abierta'");
      $stmt->bind_param('i', $id);
      $stmt->execute();
      if ($stmt->affected_rows === 0) 
        bad('No se encontró la caja o ya está cerrada');

      ok(['message' => 'Caja cerrada exitosamente']);
      break;

    case 'empleados':
      $stmt = $conn->prepare("
        SELECT e.id_empleado, CONCAT(dp.nombre, ' ', dp.apellido) as nombre_empleado
        FROM empleado e
        JOIN datos_persona dp ON e.id_datos_persona = dp.id_datos_persona
        JOIN contrato_empleado ce ON e.id_empleado = ce.id_empleado
        WHERE ce.vigente = 1 AND ce.estado_contrato = 'Activo'
        ORDER BY dp.nombre, dp.apellido
      ");
      $stmt->execute();
      $result = $stmt->get_result();
      $data = [];
      while ($row = $result->fetch_assoc()) {
        $data[] = $row;
      }
      ok(['data' => $data]);
      break;

    default:
      bad('Acción no válida');
  }
}
function handlePlantillaNotificacion($conn, $action) {
    switch ($action) {
        case 'list':
      $stmt = $conn->prepare("SELECT 
        id_plantilla_notificacion AS id,
        asunto AS nombre,
        cuerpo AS descripcion,
        tipo_notificacion AS tipo
      FROM plantilla_notificacion
      ORDER BY asunto");
            $stmt->execute();
            $result = $stmt->get_result();
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            ok(['data' => $data]);
            break;

      case 'save':
      $id = i('id');
      $nombre = s('nombre');      
      $descripcion = s('descripcion');
      $tipo = s('tipo');
            if (empty($nombre)) 
              bad('El nombre de la plantilla es requerido');
            if (empty($tipo)) 
              bad('El tipo de la plantilla es requerido');
            if ($id > 0) {
        $stmt = $conn->prepare("UPDATE plantilla_notificacion 
          SET asunto = ?, cuerpo = ?, tipo_notificacion = ? 
          WHERE id_plantilla_notificacion = ?");
        $stmt->bind_param('sssi', $nombre, $descripcion, $tipo, $id);
                $stmt->execute();
                if ($stmt->affected_rows === 0) 
                  bad('No se encontró el registro a actualizar');
            } else {
        try {
          $stmt = $conn->prepare("INSERT INTO plantilla_notificacion (tipo_notificacion, asunto, cuerpo) VALUES (?, ?, ?)");
          $stmt->bind_param('sss', $tipo, $nombre, $descripcion);
          $stmt->execute();
        } catch (mysqli_sql_exception $ex) {
          if (strpos(strtolower($ex->getMessage()), 'duplicate') !== false || strpos(strtolower($ex->getMessage()), 'unique') !== false) {
            $stmt = $conn->prepare("UPDATE plantilla_notificacion SET cuerpo = ? WHERE tipo_notificacion = ? AND asunto = ?");
            $stmt->bind_param('sss', $descripcion, $tipo, $nombre);
            $stmt->execute();
          } else {
            throw $ex;
          }
        }
            }
            ok(['message' => 'Plantilla de notificación guardada exitosamente']);
            break;

        case 'delete':
            $id = i('id');
            if ($id <= 0) 
              bad('ID inválido');

            try {
              $stmt = $conn->prepare("DELETE FROM plantilla_notificacion WHERE id_plantilla_notificacion = ?");
              $stmt->bind_param('i', $id);
              $stmt->execute();
              if ($stmt->affected_rows === 0) 
                bad('No se encontró el registro a eliminar', 404);
              ok(['message' => 'Plantilla de notificación eliminada exitosamente']);
            } catch (mysqli_sql_exception $ex) {
              if (strpos($ex->getMessage(), 'foreign key') !== false || strpos(strtolower($ex->getMessage()), 'constraint') !== false) {
                bad('No se puede eliminar la plantilla: está siendo utilizada por otros registros.', 409);
              }
              bad('Error al eliminar la plantilla: ' . $ex->getMessage());
            }
            break;

        default:
            bad('Acción no válida');
    }
}

function handlePoliticaCancelacion($conn, $action){
  switch($action) {
    case 'list':
      $stmt = $conn->prepare("SELECT id_politica_cancelacion, nombre_politica, descripcion, porcentaje_penalidad, dias_minimos_cancelacion, estado
      FROM politicas_cancelacion ORDER BY nombre_politica");
      $stmt->execute();
      $result = $stmt->get_result();
      $data = [];
      while ($row = $result->fetch_assoc()) {
        $data[] = $row;
      }
      ok(['data' => $data]);
      break;

    case 'save':
      $id = i('id');
      // Aceptar tanto 'nombre' (desde el front actual) como 'nombre_politica' por compatibilidad
      $nombre = s('nombre') ?: s('nombre_politica');
      $descripcion = s('descripcion');
      $porcentaje_penalidad = fnum_in('porcentaje_penalidad');
      $dias_minimos_cancelacion = i('dias_minimos_cancelacion');
      $estado = s('estado', 'Activo');

      if (empty($nombre)) 
        bad('El nombre de la política es requerido');
      if ($porcentaje_penalidad < 0 || $porcentaje_penalidad > 100) bad('El porcentaje debe estar entre 0 y 100');

      if ($id > 0){
        $stmt = $conn->prepare("UPDATE politicas_cancelacion SET nombre_politica = ?, descripcion = ?, porcentaje_penalidad = ?, dias_minimos_cancelacion = ?, estado = ? WHERE id_politica_cancelacion = ?");
        $stmt->bind_param('ssdisi', $nombre, $descripcion, $porcentaje_penalidad, $dias_minimos_cancelacion, $estado, $id);
        $stmt->execute();
        if ($stmt->affected_rows === 0) 
          bad('No se encontró el registro a actualizar');
      } else {
        $stmt = $conn->prepare("INSERT INTO politicas_cancelacion (nombre_politica, descripcion, porcentaje_penalidad, dias_minimos_cancelacion, estado) 
        VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('ssdis', $nombre, $descripcion, $porcentaje_penalidad, $dias_minimos_cancelacion, $estado);
        $stmt->execute();
      }
      ok(['message' => 'Política de cancelación guardada exitosamente']);
      break;

      case 'delete':
        $id = i('id');
        if ($id <= 0) 
          bad('ID inválido');

        $stmt = $conn->prepare("SELECT id_tipo_prestamo FROM tipo_prestamo WHERE id_politica_cancelacion = ? LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
          bad('No se puede eliminar la política porque está asociada a uno o más tipos de préstamo');
        }

        $stmt = $conn->prepare("DELETE FROM politicas_cancelacion WHERE id_politica_cancelacion = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        ok(['message' => 'Política eliminada exitosamente']);
        break;

      default:
        bad('Acción no válida');
  }
} 
?>