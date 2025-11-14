<?php
// /api/clientes.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php'; // $conn = new mysqli(...)

// Lanza excepciones de mysqli para atraparlas y responder JSON
if (function_exists('mysqli_report')) {
  mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
}

/* ---------------- Utils ---------------- */
function out($arr){ echo json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE); exit; }
function ok($data=[]){
  // Estructura amplia para compatibilidad con vistas antiguas/nuevas
  if (isset($data['rows']) && !isset($data['data'])) $data['data'] = $data['rows'];
  $data['ok'] = true;
  out($data);
}
function bad($msg, $code=500){ http_response_code($code); out(['ok'=>false,'error'=>$msg]); }
function s($k,$def=''){ return isset($_POST[$k]) ? trim((string)$_POST[$k]) : (isset($_GET[$k]) ? trim((string)$_GET[$k]) : $def); }
function i($k,$def=0){ $v = $_POST[$k] ?? $_GET[$k] ?? $def; return is_numeric($v) ? (int)$v : (int)$def; }
function fnum_in($k){ $v = $_POST[$k] ?? $_GET[$k] ?? null; return is_numeric($v) ? (float)$v : 0.0; }

// bind seguro (genera referencias internas)
function bind_params_safe(mysqli_stmt $st, string $types, array $params): bool {
  if ($types === '' || empty($params)) return true;
  $refs = [];
  foreach ($params as $k=>$v) { $refs[$k] = &$params[$k]; }
  return $st->bind_param($types, ...$refs);
}

// Detecta si una tabla tiene una columna (cachea en estático)
function table_has_column(mysqli $conn, string $table, string $column): bool {
  static $cache = [];
  $key = $table.'.'.$column;
  if (isset($cache[$key])) return $cache[$key];
  $rs = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
  $cache[$key] = (bool)$rs->num_rows;
  return $cache[$key];
}

// Helper de formato (por si la vista lo invoca vía include)
if (!function_exists('fnum')) {
  function fnum($v,$d=2){ return number_format((float)$v,$d,'.',','); }
}

/* ---------------- Router ---------------- */
try {
  $action = s('action','list');

  /* ----- Catálogos ----- */
  if ($action === 'catalogos') {
    $out = ['generos'=>[], 'tipos_documento'=>[]];
    $res = $conn->query("SELECT id_genero, genero FROM cat_genero ORDER BY id_genero");
    while ($r = $res->fetch_assoc()) $out['generos'][] = $r;

    $res = $conn->query("SELECT id_tipo_documento, tipo_documento FROM cat_tipo_documento ORDER BY id_tipo_documento");
    while ($r = $res->fetch_assoc()) $out['tipos_documento'][] = $r;

    ok($out);
  }

  /* ----- Listado con búsqueda + paginación ----- */
  if ($action === 'list' || $action === '') {
    $q     = s('q','');
    $page  = max(1, i('page', 1));
    $size  = max(1, min(100, i('size', (i('limit',10))))); // soporta size o limit
    $off   = ($page-1) * $size;

    $where = " WHERE 1=1 ";
    $types = "";
    $vals  = [];

    if ($q !== '') {
      $where .= " AND (dp.nombre LIKE ? OR dp.apellido LIKE ? OR di.numero_documento LIKE ?) ";
      $like   = "%$q%";
      $types .= "sss";
      $vals[] = $like; $vals[] = $like; $vals[] = $like;
    }

    // total
    $sqlCount = "
      SELECT COUNT(DISTINCT c.id_cliente) AS total
      FROM cliente c
      JOIN datos_persona dp ON dp.id_datos_persona = c.id_datos_persona
      LEFT JOIN documento_identidad di ON di.id_datos_persona = dp.id_datos_persona
      $where
    ";
    $st = $conn->prepare($sqlCount);
    if ($types) bind_params_safe($st, $types, $vals);
    $st->execute();
    $total = (int)$st->get_result()->fetch_assoc()['total'];
    $st->close();

    // filas
     $sql = "
      SELECT
        c.id_cliente,
        dp.nombre, dp.apellido, dp.creado_en,
        di.numero_documento,
        em.email,
        t.telefono,
        COALESCE((
          SELECT ce.estado
          FROM prestamo p
          LEFT JOIN cat_estado_prestamo ce ON ce.id_estado_prestamo=p.id_estado_prestamo
          WHERE p.id_cliente=c.id_cliente
          ORDER BY p.actualizado_en DESC
          LIMIT 1
        ), 'Inactivo') AS estado_prestamo
      FROM cliente c
      JOIN datos_persona dp ON dp.id_datos_persona = c.id_datos_persona
      LEFT JOIN documento_identidad di ON di.id_datos_persona = dp.id_datos_persona
      LEFT JOIN email em ON em.id_datos_persona = dp.id_datos_persona AND em.es_principal=1
      LEFT JOIN telefono t ON t.id_datos_persona = dp.id_datos_persona AND t.es_principal=1
      $where
      ORDER BY dp.creado_en DESC
      LIMIT ? OFFSET ?
    ";
    $st = $conn->prepare($sql);
    $types2 = $types . "ii";
    $vals2  = $vals; $vals2[] = $size; $vals2[] = $off;
    bind_params_safe($st, $types2, $vals2);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();

    ok(['rows'=>$rows, 'total'=>$total, 'page'=>$page, 'size'=>$size, 'pages'=>($size>0?ceil($total/$size):1)]);
  }

  /* ----- Obtener detalle ----- */
  if ($action === 'get') {
    $id = i('id_cliente', 0);
    if ($id <= 0) bad('id_cliente requerido', 400);

    $sql = "
      SELECT
        c.id_cliente,
        dp.id_datos_persona, dp.nombre, dp.apellido, dp.fecha_nacimiento, dp.estado_cliente, dp.creado_en,
        dp.genero, cg.genero AS genero_txt,
        di.numero_documento, td.tipo_documento, di.fecha_emision,
        em.email, t.telefono,
        d.ciudad, d.sector, d.calle, d.numero_casa,
        ie.ingresos_mensuales, ie.egresos_mensuales, fi.fuente,
        oc.ocupacion, oc.empresa,
        COALESCE((
          SELECT ce.estado
          FROM prestamo p
          LEFT JOIN cat_estado_prestamo ce ON ce.id_estado_prestamo=p.id_estado_prestamo
          WHERE p.id_cliente=c.id_cliente
          ORDER BY p.actualizado_en DESC
          LIMIT 1
        ), 'Inactivo') AS estado_prestamo
      FROM cliente c
      JOIN datos_persona dp ON dp.id_datos_persona = c.id_datos_persona
      LEFT JOIN cat_genero cg ON cg.id_genero = dp.genero
      LEFT JOIN documento_identidad di ON di.id_datos_persona = dp.id_datos_persona
      LEFT JOIN cat_tipo_documento td ON td.id_tipo_documento = di.id_tipo_documento
      LEFT JOIN email em ON em.id_datos_persona = dp.id_datos_persona AND em.es_principal=1
      LEFT JOIN telefono t ON t.id_datos_persona = dp.id_datos_persona AND t.es_principal=1
      LEFT JOIN direccion d ON d.id_datos_persona = dp.id_datos_persona
      LEFT JOIN ingresos_egresos ie ON ie.id_cliente = c.id_cliente
      LEFT JOIN fuente_ingreso fi ON fi.id_ingresos_egresos = ie.id_ingresos_egresos
      LEFT JOIN ocupacion oc ON oc.id_datos_persona = dp.id_datos_persona
      WHERE c.id_cliente = ?
      LIMIT 1
    ";
    $st = $conn->prepare($sql);
    bind_params_safe($st, "i", [$id]);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();

    if (!$row) bad('No encontrado', 404);
    ok($row);
  }

  /* ----- Validaciones comunes (ingresos/egresos) ----- */
  $ing = fnum_in('ingresos_mensuales');
  $egr = fnum_in('egresos_mensuales');
  $valida_ie = function(float $ing, float $egr){
    if ($ing > 0 || $egr > 0) {
      if ($ing < 10000) bad('Ingresos mensuales deben ser ≥ 10,000.', 422);
      if ($ing <= $egr) bad('Ingresos deben ser mayores que egresos.', 422);
    }
  };

  /* ----- Crear cliente ----- */
  if ($action === 'create') {
    $nombre   = s('nombre');
    $apellido = s('apellido');
    $fnac     = s('fecha_nacimiento');
    $genero   = i('genero') ?: null;
    $tipoDoc  = i('id_tipo_documento');
    $numDoc   = s('numero_documento');
    if ($nombre==='' || $apellido==='' || $fnac==='' || !$tipoDoc || $numDoc==='') bad('Campos requeridos incompletos', 422);

    // mayor de edad
    $bday  = new DateTime($fnac);
    $mayor = (new DateTime())->diff($bday)->y >= 18;
    if (!$mayor) bad('El cliente debe ser mayor de edad', 422);

    $valida_ie($ing, $egr);

    $estadoCliente = s('estado_cliente','Activo');
    $tel   = s('telefono');
    $email = s('email');

    $ciudad = s('ciudad'); $sector = s('sector'); $calle = s('calle'); $numCasa = i('numero_casa');

    $fuente = s('fuente_ingresos');
    $ocup   = s('ocupacion'); $emp = s('empresa');

    $conn->begin_transaction();

    // datos_persona
    $st = $conn->prepare("INSERT INTO datos_persona(nombre,apellido,fecha_nacimiento,genero,estado_cliente) VALUES (?,?,?,?,?)");
    bind_params_safe($st, "sssis", [$nombre,$apellido,$fnac,$genero,$estadoCliente]);
    $st->execute();
    $id_dp = $conn->insert_id; $st->close();

    // cliente
    $st = $conn->prepare("INSERT INTO cliente(id_datos_persona) VALUES (?)");
    bind_params_safe($st, "i", [$id_dp]);
    $st->execute();
    $id_cliente = $conn->insert_id; $st->close();

    // documento_identidad (unique por numero_documento)
    $st = $conn->prepare("
      INSERT INTO documento_identidad(id_datos_persona,id_tipo_documento,numero_documento,fecha_emision)
      VALUES (?,?,?,CURDATE())
      ON DUPLICATE KEY UPDATE id_datos_persona=VALUES(id_datos_persona), id_tipo_documento=VALUES(id_tipo_documento)
    ");
    bind_params_safe($st, "iis", [$id_dp,$tipoDoc,$numDoc]);
    $st->execute(); $st->close();

    // email/telefono principal
    if ($email !== '') {
      $st = $conn->prepare("INSERT INTO email(id_datos_persona,email,es_principal) VALUES (?,?,1)");
      bind_params_safe($st, "is", [$id_dp,$email]);
      $st->execute(); $st->close();
    }
    if ($tel !== '') {
      $st = $conn->prepare("INSERT INTO telefono(id_datos_persona,telefono,es_principal) VALUES (?,?,1)");
      bind_params_safe($st, "is", [$id_dp,$tel]);
      $st->execute(); $st->close();
    }

    // dirección (la tabla exige NOT NULL en todos)
    if ($ciudad !== '' && $sector !== '' && $calle !== '' && $numCasa > 0) {
      $st = $conn->prepare("INSERT INTO direccion(id_datos_persona,ciudad,sector,calle,numero_casa) VALUES (?,?,?,?,?)");
      bind_params_safe($st, "isssi", [$id_dp,$ciudad,$sector,$calle,$numCasa]);
      $st->execute(); $st->close();
    }

    // ingresos/egresos + fuente
    if ($ing>0 || $egr>0) {
      $st = $conn->prepare("INSERT INTO ingresos_egresos(id_cliente,ingresos_mensuales,egresos_mensuales) VALUES (?,?,?)");
      bind_params_safe($st, "idd", [$id_cliente,$ing,$egr]);
      $st->execute(); $id_ie = $conn->insert_id; $st->close();

      if ($fuente!=='') {
        if (table_has_column($conn, 'fuente_ingreso', 'monto')) {
          $st = $conn->prepare("INSERT INTO fuente_ingreso(id_ingresos_egresos,fuente,monto) VALUES (?,?,?)");
          bind_params_safe($st, "isd", [$id_ie,$fuente,$ing]);
        } else {
          $st = $conn->prepare("INSERT INTO fuente_ingreso(id_ingresos_egresos,fuente) VALUES (?,?)");
          bind_params_safe($st, "is", [$id_ie,$fuente]);
        }
        $st->execute(); $st->close();
      }
    }

    // ocupación (opcional)
    if ($ocup!=='' || $emp!=='') {
      $st = $conn->prepare("INSERT INTO ocupacion(id_datos_persona,ocupacion,empresa) VALUES (?,?,?)");
      bind_params_safe($st, "iss", [$id_dp,$ocup,$emp]);
      $st->execute(); $st->close();
    }

    $conn->commit();
    ok(['id_cliente'=>$id_cliente]);
  }

  /* ----- Actualizar cliente ----- */
  if ($action === 'update') {
    $id = i('id_cliente',0);
    if ($id <= 0) bad('id_cliente requerido', 400);

    // id_datos_persona
    $st = $conn->prepare("SELECT id_datos_persona FROM cliente WHERE id_cliente=? LIMIT 1");
    bind_params_safe($st, "i", [$id]); $st->execute();
    $id_dp = (int)$st->get_result()->fetch_column(); $st->close();
    if (!$id_dp) bad('Cliente inválido', 404);

    $nombre   = s('nombre');
    $apellido = s('apellido');
    $fnac     = s('fecha_nacimiento');
    $genero   = i('genero') ?: null;
    $estadoCliente = s('estado_cliente','');

    $tipoDoc = i('id_tipo_documento'); $numDoc = s('numero_documento');
    $email = s('email'); $tel = s('telefono');

    $ciudad = s('ciudad'); $sector = s('sector'); $calle = s('calle'); $numCasa = i('numero_casa');
    $direccion_alguno   = ($ciudad!=='' || $sector!=='' || $calle!=='' || $numCasa>0);
    $direccion_completa = ($ciudad!=='' && $sector!=='' && $calle!=='' && $numCasa>0);

    $fuente = s('fuente_ingresos');
    $ocup = s('ocupacion'); $emp = s('empresa');

    // validar ingresos si llegan
    $valida_ie($ing, $egr);

    $conn->begin_transaction();

    // datos_persona
    if ($estadoCliente !== '') {
      $st = $conn->prepare("UPDATE datos_persona SET nombre=?, apellido=?, fecha_nacimiento=?, genero=?, estado_cliente=? WHERE id_datos_persona=?");
      bind_params_safe($st,"sssisi",[$nombre,$apellido,$fnac,$genero,$estadoCliente,$id_dp]);
    } else {
      $st = $conn->prepare("UPDATE datos_persona SET nombre=?, apellido=?, fecha_nacimiento=?, genero=? WHERE id_datos_persona=?");
      bind_params_safe($st,"sssii",[$nombre,$apellido,$fnac,$genero,$id_dp]);
    }
    $st->execute(); $st->close();

    // documento_identidad
    if ($tipoDoc && $numDoc!=='') {
      $st = $conn->prepare("
        INSERT INTO documento_identidad(id_datos_persona,id_tipo_documento,numero_documento,fecha_emision)
        VALUES (?,?,?,CURDATE())
        ON DUPLICATE KEY UPDATE id_datos_persona=VALUES(id_datos_persona), id_tipo_documento=VALUES(id_tipo_documento)
      ");
      bind_params_safe($st,"iis",[$id_dp,$tipoDoc,$numDoc]);
      $st->execute(); $st->close();
    }

    // email / telefono (si llegan)
    if ($email!=='') {
      $conn->query("DELETE FROM email WHERE id_datos_persona=".$id_dp);
      $st = $conn->prepare("INSERT INTO email(id_datos_persona,email,es_principal) VALUES (?,?,1)");
      bind_params_safe($st,"is",[$id_dp,$email]); $st->execute(); $st->close();
    }
    if ($tel!=='') {
      $conn->query("DELETE FROM telefono WHERE id_datos_persona=".$id_dp);
      $st = $conn->prepare("INSERT INTO telefono(id_datos_persona,telefono,es_principal) VALUES (?,?,1)");
      bind_params_safe($st,"is",[$id_dp,$tel]); $st->execute(); $st->close();
    }

    // dirección
    if ($direccion_alguno) {
      if (!$direccion_completa) bad('Para actualizar dirección debes enviar: ciudad, sector, calle y numero_casa.', 422);
      $conn->query("DELETE FROM direccion WHERE id_datos_persona=".$id_dp);
      $st = $conn->prepare("INSERT INTO direccion(id_datos_persona,ciudad,sector,calle,numero_casa) VALUES (?,?,?,?,?)");
      bind_params_safe($st,"isssi",[$id_dp,$ciudad,$sector,$calle,$numCasa]);
      $st->execute(); $st->close();
    }

    // ingresos/egresos + fuente (si llegan)
    if ($ing>0 || $egr>0) {
      // borra anteriores y sus fuentes
      $conn->query("DELETE fi FROM fuente_ingreso fi JOIN ingresos_egresos ie ON fi.id_ingresos_egresos=ie.id_ingresos_egresos WHERE ie.id_cliente=".$id);
      $conn->query("DELETE FROM ingresos_egresos WHERE id_cliente=".$id);

      $st = $conn->prepare("INSERT INTO ingresos_egresos(id_cliente,ingresos_mensuales,egresos_mensuales) VALUES (?,?,?)");
      bind_params_safe($st,"idd",[$id,$ing,$egr]); $st->execute(); $id_ie = $conn->insert_id; $st->close();

      if ($fuente!=='') {
        if (table_has_column($conn, 'fuente_ingreso', 'monto')) {
          $st = $conn->prepare("INSERT INTO fuente_ingreso(id_ingresos_egresos,fuente,monto) VALUES (?,?,?)");
          bind_params_safe($st,"isd",[$id_ie,$fuente,$ing]);
        } else {
          $st = $conn->prepare("INSERT INTO fuente_ingreso(id_ingresos_egresos,fuente) VALUES (?,?)");
          bind_params_safe($st,"is",[$id_ie,$fuente]);
        }
        $st->execute(); $st->close();
      }
    }

    // ocupación
    if ($ocup!=='' || $emp!=='') {
      $conn->query("DELETE FROM ocupacion WHERE id_datos_persona=".$id_dp);
      $st = $conn->prepare("INSERT INTO ocupacion(id_datos_persona,ocupacion,empresa) VALUES (?,?,?)");
      bind_params_safe($st,"iss",[$id_dp,$ocup,$emp]);
      $st->execute(); $st->close();
    }

    $conn->commit();
    ok(['id_cliente'=>$id]);
  }

  /* ----- Salud (ping) opcional ----- */
  if ($action === 'ping') { ok(['pong'=>true]); }
  
  /* ---- accion borrar -----*/
  
  if ($action === 'delete') {
  $id = i('id_cliente',0);
  if ($id <= 0) bad('id_cliente requerido', 400);

  // bloquear si tiene préstamos
  $st=$conn->prepare("SELECT COUNT(*) FROM prestamo WHERE id_cliente=?");
  bind_params_safe($st,"i",[$id]); $st->execute();
  $t=(int)$st->get_result()->fetch_column(); $st->close();
  if ($t>0) bad('No se puede eliminar: el cliente tiene préstamos asociados.', 409);

  // obtener id_datos_persona
  $st=$conn->prepare("SELECT id_datos_persona FROM cliente WHERE id_cliente=? LIMIT 1");
  bind_params_safe($st,"i",[$id]); $st->execute();
  $id_dp=(int)$st->get_result()->fetch_column(); $st->close();
  if (!$id_dp) bad('Cliente inválido', 404);

  $conn->begin_transaction();
  $conn->query("DELETE fi FROM fuente_ingreso fi JOIN ingresos_egresos ie ON fi.id_ingresos_egresos=ie.id_ingresos_egresos WHERE ie.id_cliente=".$id);
  $conn->query("DELETE FROM ingresos_egresos WHERE id_cliente=".$id);
  $conn->query("DELETE FROM direccion WHERE id_datos_persona=".$id_dp);
  $conn->query("DELETE FROM email WHERE id_datos_persona=".$id_dp);
  $conn->query("DELETE FROM telefono WHERE id_datos_persona=".$id_dp);
  $conn->query("DELETE FROM ocupacion WHERE id_datos_persona=".$id_dp);
  $conn->query("DELETE FROM documento_identidad WHERE id_datos_persona=".$id_dp);
  $conn->query("DELETE FROM cliente WHERE id_cliente=".$id);
  $conn->query("DELETE FROM datos_persona WHERE id_datos_persona=".$id_dp);
  $conn->commit();

  ok(['deleted'=>true]);
}


  /* ----- Acción desconocida ----- */
  bad('Acción no soportada', 404);

} catch (Throwable $e) {
  // Si hay transacción abierta, intenta revertir
  try { if (isset($conn) && $conn->errno===0) { /* noop */ } } catch (Throwable $__) {}
  try { if (isset($conn)) $conn->rollback(); } catch (Throwable $__) {}
  bad('Error en la operación: '.$e->getMessage(), 500);
}
