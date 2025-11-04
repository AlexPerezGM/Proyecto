<?php
// /api/clientes.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php'; // $conn = new mysqli(...)

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function out($arr){ echo json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE); exit; }
function bad($msg){ out(['ok'=>false,'msg'=>$msg]); }
function ok($data=[]){ $data['ok']=true; out($data); }

// Helper: bind seguro con referencias (evita el error del "unpacking")
function bind_params_safe(mysqli_stmt $stmt, string $types, array &$params): void {
  $refs = [];
  foreach ($params as $k => $v) { $refs[$k] = &$params[$k]; } // referencias
  array_unshift($refs, $types);                               // tipos primero
  call_user_func_array([$stmt, 'bind_param'], $refs);
}

// Normaliza enteros
function i($key, $def=0){ return isset($_POST[$key]) ? (int)$_POST[$key] : $def; }
function s($key, $def=''){ return isset($_POST[$key]) ? trim((string)$_POST[$key]) : $def; }

try {
  $action = $_POST['action'] ?? $_GET['action'] ?? 'list';

  // -------- LISTAR (paginado + búsqueda) ----------
  if ($action === 'list') {
    $q    = $_POST['q'] ?? $_GET['q'] ?? '';
    $page = max(1, (int)($_POST['page'] ?? $_GET['page'] ?? 1));
    $size = max(1, min(100, (int)($_POST['size'] ?? $_GET['size'] ?? 10)));
    $off  = ($page-1) * $size;

    // WHERE dinámico
    $where = " WHERE 1=1 ";
    $types = "";
    $vals  = [];
    if ($q !== '') {
      $where .= " AND (dp.nombre LIKE ? OR dp.apellido LIKE ? OR di.numero_documento LIKE ?) ";
      $types .= "sss";
      $like = "%$q%";
      $vals[] = $like; $vals[] = $like; $vals[] = $like;
    }

    // total
    $sqlCount = "
      SELECT COUNT(*) AS total
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

    // data
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
    $vals2  = $vals;
    $vals2[] = $size; $vals2[] = $off;
    bind_params_safe($st, $types2, $vals2);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();

    ok(['data'=>$rows,'total'=>$total,'page'=>$page,'size'=>$size]);
  }

  // -------- OBTENER FICHA ----------
  if ($action === 'get') {
    $id = (int)($_POST['id_cliente'] ?? $_GET['id_cliente'] ?? 0);
    if ($id <= 0) bad('id_cliente requerido');

    $sql = "
      SELECT
        c.id_cliente,
        dp.id_datos_persona, dp.nombre, dp.apellido, dp.fecha_nacimiento, dp.estado_cliente, dp.creado_en,
        cg.genero AS genero_txt, dp.genero,
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
    $one = [$id];
    bind_params_safe($st, "i", $one);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();

    if (!$row) bad('No encontrado');
    ok($row);
  }

  // -------- CREAR ----------
  if ($action === 'create') {
    // Campos mínimos (el resto opcional y se puede ir ampliando)
    $nombre  = s('nombre');  $apellido = s('apellido');
    $fnac    = s('fecha_nacimiento');
    $genero  = i('genero') ?: null;
    $tipoDoc = i('id_tipo_documento');
    $numDoc  = s('numero_documento');
    $tel     = s('telefono'); $email = s('email');

    if ($nombre==='' || $apellido==='' || $fnac==='' || !$tipoDoc || $numDoc==='') bad('Campos requeridos incompletos');

    // Mayor de edad (servidor)
    $bday = new DateTime($fnac);
    $mayor = (new DateTime())->diff($bday)->y >= 18;
    if (!$mayor) bad('El cliente debe ser mayor de edad');

    $conn->begin_transaction();

    // 1) datos_persona
    $st = $conn->prepare("INSERT INTO datos_persona(nombre,apellido,fecha_nacimiento,genero,estado_cliente) VALUES (?,?,?,?, 'Activo')");
    $p = [$nombre,$apellido,$fnac,$genero];
    bind_params_safe($st,"sssi",$p);
    $st->execute();
    $id_dp = $conn->insert_id;
    $st->close();

    // 2) cliente
    $st = $conn->prepare("INSERT INTO cliente(id_datos_persona) VALUES (?)");
    $p = [$id_dp]; bind_params_safe($st,"i",$p); $st->execute(); $id_cliente=$conn->insert_id; $st->close();

    // 3) documento_identidad (único por número)
    $st = $conn->prepare("
      INSERT INTO documento_identidad(id_datos_persona,id_tipo_documento,numero_documento,fecha_emision)
      VALUES (?,?,?,CURDATE())
      ON DUPLICATE KEY UPDATE id_datos_persona=VALUES(id_datos_persona), id_tipo_documento=VALUES(id_tipo_documento)
    ");
    $p = [$id_dp,$tipoDoc,$numDoc];
    bind_params_safe($st,"iis",$p); $st->execute(); $st->close();

    // 4) email + telefono (principales)
    if ($email !== '') {
      $st = $conn->prepare("INSERT INTO email(id_datos_persona,email,es_principal) VALUES (?,?,1)");
      $p = [$id_dp,$email]; bind_params_safe($st,"is",$p); $st->execute(); $st->close();
    }
    if ($tel !== '') {
      $st = $conn->prepare("INSERT INTO telefono(id_datos_persona,telefono,es_principal) VALUES (?,?,1)");
      $p = [$id_dp,$tel]; bind_params_safe($st,"is",$p); $st->execute(); $st->close();
    }

    // 5) dirección
    $ciudad = s('ciudad'); $sector = s('sector'); $calle = s('calle'); $numCasa = i('numero_casa');
    if ($ciudad || $sector || $calle || $numCasa) {
      $st = $conn->prepare("INSERT INTO direccion(id_datos_persona,ciudad,sector,calle,numero_casa,es_principal) VALUES (?,?,?,?,?,1)");
      $p = [$id_dp,$ciudad,$sector,$calle,$numCasa];
      bind_params_safe($st,"isssi",$p); $st->execute(); $st->close();
    }

    // 6) ingresos/egresos + fuente
    $ing = (float)($_POST['ingresos_mensuales'] ?? 0);
    $egr = (float)($_POST['egresos_mensuales'] ?? 0);
    $fuente = s('fuente_ingresos');
    if ($ing>0 || $egr>0) {
      $st = $conn->prepare("INSERT INTO ingresos_egresos(id_cliente,ingresos_mensuales,egresos_mensuales) VALUES (?,?,?)");
      $p = [$id_cliente,$ing,$egr]; bind_params_safe($st,"idd",$p); $st->execute();
      $id_ie = $conn->insert_id; $st->close();
      if ($fuente!=='') {
        $st = $conn->prepare("INSERT INTO fuente_ingreso(id_ingresos_egresos,fuente,monto) VALUES (?,?,?)");
        $p = [$id_ie,$fuente,$ing]; bind_params_safe($st,"isd",$p); $st->execute(); $st->close();
      }
    }

    // 7) ocupación/empresa
    $ocup = s('ocupacion'); $emp = s('empresa');
    if ($ocup!=='' || $emp!=='') {
      $st = $conn->prepare("INSERT INTO ocupacion(id_datos_persona,ocupacion,empresa) VALUES (?,?,?)");
      $p = [$id_dp,$ocup,$emp]; bind_params_safe($st,"iss",$p); $st->execute(); $st->close();
    }

    $conn->commit();
    ok(['id_cliente'=>$id_cliente]);
  }

  // -------- ACTUALIZAR ----------
  if ($action === 'update') {
    $id = i('id_cliente'); if ($id<=0) bad('id_cliente requerido');

    // Recuperar id_datos_persona
    $st = $conn->prepare("SELECT id_datos_persona FROM cliente WHERE id_cliente=? LIMIT 1");
    $p = [$id]; bind_params_safe($st,"i",$p); $st->execute();
    $id_dp = (int)$st->get_result()->fetch_column(); $st->close();
    if (!$id_dp) bad('Cliente inválido');

    $nombre  = s('nombre');  $apellido = s('apellido');
    $fnac    = s('fecha_nacimiento'); $genero = i('genero') ?: null;

    $conn->begin_transaction();

    $st = $conn->prepare("UPDATE datos_persona SET nombre=?, apellido=?, fecha_nacimiento=?, genero=? WHERE id_datos_persona=?");
    $p = [$nombre,$apellido,$fnac,$genero,$id_dp];
    bind_params_safe($st,"sssii",$p); $st->execute(); $st->close();

    // Doc, email, tel
    $tipoDoc = i('id_tipo_documento'); $numDoc = s('numero_documento');
    if ($tipoDoc && $numDoc!=='') {
      $st = $conn->prepare("
        INSERT INTO documento_identidad(id_datos_persona,id_tipo_documento,numero_documento,fecha_emision)
        VALUES (?,?,?,CURDATE())
        ON DUPLICATE KEY UPDATE id_datos_persona=VALUES(id_datos_persona), id_tipo_documento=VALUES(id_tipo_documento)
      ");
      $p = [$id_dp,$tipoDoc,$numDoc]; bind_params_safe($st,"iis",$p); $st->execute(); $st->close();
    }
    $email = s('email'); if ($email!=='') {
      // set principal (simple: borrar y crear)
      $conn->query("DELETE FROM email WHERE id_datos_persona=".$id_dp);
      $st = $conn->prepare("INSERT INTO email(id_datos_persona,email,es_principal) VALUES (?,?,1)");
      $p = [$id_dp,$email]; bind_params_safe($st,"is",$p); $st->execute(); $st->close();
    }
    $tel = s('telefono'); if ($tel!=='') {
      $conn->query("DELETE FROM telefono WHERE id_datos_persona=".$id_dp);
      $st = $conn->prepare("INSERT INTO telefono(id_datos_persona,telefono,es_principal) VALUES (?,?,1)");
      $p = [$id_dp,$tel]; bind_params_safe($st,"is",$p); $st->execute(); $st->close();
    }

    // Dirección
    $ciudad = s('ciudad'); $sector = s('sector'); $calle = s('calle'); $numCasa = i('numero_casa');
    $conn->query("DELETE FROM direccion WHERE id_datos_persona=".$id_dp);
    if ($ciudad||$sector||$calle||$numCasa){
      $st = $conn->prepare("INSERT INTO direccion(id_datos_persona,ciudad,sector,calle,numero_casa,es_principal) VALUES (?,?,?,?,?,1)");
      $p = [$id_dp,$ciudad,$sector,$calle,$numCasa]; bind_params_safe($st,"isssi",$p); $st->execute(); $st->close();
    }

    // IE + fuente
    $ing = (float)($_POST['ingresos_mensuales'] ?? 0);
    $egr = (float)($_POST['egresos_mensuales'] ?? 0);
    $fuente = s('fuente_ingresos');
    $conn->query("DELETE FROM ingresos_egresos WHERE id_cliente=".$id);
    if ($ing>0 || $egr>0){
      $st = $conn->prepare("INSERT INTO ingresos_egresos(id_cliente,ingresos_mensuales,egresos_mensuales) VALUES (?,?,?)");
      $p = [$id,$ing,$egr]; bind_params_safe($st,"idd",$p); $st->execute(); $id_ie=$conn->insert_id; $st->close();
      if ($fuente!=='') {
        $st = $conn->prepare("INSERT INTO fuente_ingreso(id_ingresos_egresos,fuente,monto) VALUES (?,?,?)");
        $p = [$id_ie,$fuente,$ing]; bind_params_safe($st,"isd",$p); $st->execute(); $st->close();
      }
    }

    // Ocupación
    $ocup = s('ocupacion'); $emp = s('empresa');
    $conn->query("DELETE FROM ocupacion WHERE id_datos_persona=".$id_dp);
    if ($ocup!=='' || $emp!==''){
      $st = $conn->prepare("INSERT INTO ocupacion(id_datos_persona,ocupacion,empresa) VALUES (?,?,?)");
      $p = [$id_dp,$ocup,$emp]; bind_params_safe($st,"iss",$p); $st->execute(); $st->close();
    }

    $conn->commit();
    ok();
  }

  bad('Acción no soportada');
} catch (Throwable $e) {
  if ($conn->errno) { $conn->rollback(); }
  bad($e->getMessage());
}
