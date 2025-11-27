<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

function j($arr){ echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }
$action = $_POST['action'] ?? $_REQUEST['action'] ?? '';

// DEBUG: si no llega acción, devolver información útil para diagnóstico
if (!$action) {
  // recolectar cabeceras y cuerpo crudo
  $headers = [];
  foreach (getallheaders() as $k => $v) $headers[$k] = $v;
  $raw = file_get_contents('php://input');
  out([
    'ok' => false,
    'debug' => true,
    'msg' => 'No action received',
    'action_value_post' => isset($_POST['action']) ? $_POST['action'] : null,
    'post_keys' => array_keys($_POST),
    'request_keys' => array_keys($_REQUEST),
    'raw_input' => $raw,
    'headers' => $headers
  ]);
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

if ($conn->connect_error) j(['ok'=>false,'msg'=>'DB Error']);

function getInt($k,$d=0){ return isset($_REQUEST[$k]) ? (int)$_REQUEST[$k] : $d; }
function getStr($k,$d=''){ return isset($_REQUEST[$k]) ? trim($_REQUEST[$k]) : $d; }

function table_has_column($conn, $table, $column){
  $rs = $conn->query("SHOW COLUMNS FROM `$table` LIKE '" . $conn->real_escape_string($column) . "'");
  return $rs ? $rs->num_rows > 0 : false;
}

function datos_basicos_empleado($conn, int $id_empleado){
  $sql = "SELECT dp.nombre, dp.apellido, di.numero_documento
            FROM empleado e
            JOIN datos_persona dp ON dp.id_datos_persona = e.id_datos_persona
            LEFT JOIN documento_identidad di ON di.id_datos_persona = dp.id_datos_persona
            WHERE e.id_empleado = ? LIMIT 1";
  $st = $conn->prepare($sql);
  $st->bind_param('i', $id_empleado);
  $st->execute();
  $r = $st->get_result()->fetch_assoc() ?: [];
  $st->close();
  return [
    'nombre' => $r['nombre'] ?? '',
    'apellido' => $r['apellido'] ?? '',
    'numero' => $r['numero_documento'] ?? ''
  ];
}

function crearCarpetaEmpleado(int $id_empleado): string {
    global $conn;

    $info = datos_basicos_empleado($conn, $id_empleado);
    $num = preg_replace('/[^0-9A-Za-z]/', '', $info['numero']);
    if ($num === '') $num = 'EMP_' . $id_empleado;

    $base = __DIR__ . '/../uploads/empleados';
    if (!file_exists($base)) mkdir($base, 0777, true);

    $path = $base . '/' . $num;
    if (!file_exists($path)) mkdir($path, 0777, true);

    return $path;
}

function resolve_id_documentacion_empleado($conn, string $tipoCodigo){
  $tipoCodigo = strtoupper(trim($tipoCodigo));
  $sql = "SELECT id_documentacion_empleado 
  FROM cat_documentacion_empleado 
  WHERE UPPER(tipo_documentacion) LIKE CONCAT('%', ?, '%') LIMIT 1";
  $st = $conn->prepare($sql);
  $st->bind_param('s', $tipoCodigo);
  $st->execute();
  $res = $st->get_result()->fetch_column();
  $st->close();
  if ($res) return (int)$res;
  $rs2 = $conn->query("SELECT id_documentacion_empleado 
  FROM cat_documentacion_empleado 
  ORDER BY id_documentacion_empleado 
  LIMIT 1");
  return (int)($rs2 ? $rs2->fetch_column() : 0);
}
    if ($action === 'upload_doc') {
        $id_empleado = i('id_empleado', 0);
        if ($id_empleado <= 0) bad('id_empleado requerido', 400);

        $tipo = strtoupper(trim($_POST['tipo_archivo'] ?? ''));
        if ($tipo === '') bad('Tipo de documento requerido', 422);

        if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
            bad('No se recibió archivo válido', 400);
        }

        $origName = $_FILES['archivo']['name'] ?? '';
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $allowed = ['pdf','jpg','jpeg','png'];
        if (!in_array($ext, $allowed, true)) {
            bad('Tipo de archivo no permitido. Solo PDF, JPG o PNG.', 422);
        }

        $path = crearCarpetaEmpleado($id_empleado);
        $folderName = basename($path);

        $nombreForm   = trim($_POST['nombre'] ?? '');
        $apellidoForm = trim($_POST['apellido'] ?? '');
        $numDocForm   = trim($_POST['numero_documento'] ?? '');

        $info = datos_basicos_empleado($conn, $id_empleado);

        $nombreBase = trim(
            ($nombreForm   !== '' ? $nombreForm   : ($info['nombre']   ?? 'Empleado')) . ' ' .
            ($apellidoForm !== '' ? $apellidoForm : ($info['apellido'] ?? (string)$id_empleado))
        );
        $numeroDoc = $numDocForm !== '' ? $numDocForm : ($info['numero'] ?? '');

        $baseName = $tipo . '_' . $nombreBase . '_' . $numeroDoc;
        $baseName = iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$baseName) ?: $baseName;
        $baseName = preg_replace('/[^A-Za-z0-9_]/', '_', $baseName);

        $fileName = $baseName . '_' . time() . '.' . $ext;
        $destino  = $path . '/' . $fileName;

        if (!move_uploaded_file($_FILES['archivo']['tmp_name'], $destino)) {
            bad('Error al mover el archivo subido', 500);
        }

        $rutaRel = "uploads/empleados/{$folderName}/{$fileName}";

        if (table_has_column($conn, 'documentacion_empleado', 'id_empleado') &&
            table_has_column($conn, 'documentacion_empleado', 'ruta_documento')) {

            $tieneTipoCat = table_has_column($conn, 'documentacion_empleado', 'id_documentacion_empleado');

            if ($tieneTipoCat) {
                $idDocCat = resolve_id_documentacion_empleado($conn, $tipo);
                $sqlIns = "
                    INSERT INTO documentacion_empleado (id_empleado, id_documentacion_empleado, ruta_documento)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE ruta_documento = VALUES(ruta_documento)
                ";
                $st = $conn->prepare($sqlIns);
                bind_params_safe($st, "iis", [$id_empleado, $idDocCat, $rutaRel]);
            } else {
                $sqlIns = "
                    INSERT INTO documentacion_empleado (id_empleado, ruta_documento)
                    VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE ruta_documento = VALUES(ruta_documento)
                ";
                $st = $conn->prepare($sqlIns);
                bind_params_safe($st, "is", [$id_empleado, $rutaRel]);
            }

            $st->execute();
            $st->close();
        }

        ok([
            'message'   => 'Documento subido correctamente',
            'file'      => $fileName,
            'ruta'      => $rutaRel,
            'carpeta'   => "uploads/empleados/{$folderName}/"
        ]);
    }
    if ($action === 'list_docs') {
        $id = i('id_empleado', 0);
        if ($id <= 0) bad('id_empleado requerido', 400);

        $path = crearCarpetaEmpleado($id);
        $folderName = basename($path);
        $folderUrl  = "uploads/empleados/{$folderName}/";

        $files = [];
        if (file_exists($path)) {
            foreach (scandir($path) as $f) {
                if ($f !== "." && $f !== "..") {
                    $files[] = [
                        'nombre' => $f,
                        'ruta'   => $folderUrl . $f
                    ];
                }
            }
        }

        ok([
          'files'   => $files,
          'carpeta' => $folderUrl
        ]);
      }

if ($action === 'options'){
  $gen = $conn->query("SELECT id_genero, genero FROM cat_genero ORDER BY id_genero")->fetch_all(MYSQLI_ASSOC);
  $con = $conn->query("SELECT id_tipo_contrato, tipo_contrato FROM cat_tipo_contrato ORDER BY id_tipo_contrato")->fetch_all(MYSQLI_ASSOC);
  $jef = $conn->query("
    SELECT e.id_empleado, CONCAT(dp.nombre,' ',dp.apellido) AS nombre
    FROM empleado e
    JOIN datos_persona dp ON dp.id_datos_persona=e.id_datos_persona
    ORDER BY nombre
  ")->fetch_all(MYSQLI_ASSOC) ?? [];
  $ded = $conn->query("SELECT id_tipo_deduccion, tipo_deduccion FROM cat_tipo_deduccion ORDER BY id_tipo_deduccion ")->fetch_all(MYSQLI_ASSOC);
  $ben = $conn->query("SELECT id_beneficio, tipo_beneficio FROM cat_beneficio ORDER BY id_beneficio")->fetch_all(MYSQLI_ASSOC);
  j(['ok'=>true,'generos'=>$gen,'contratos'=>$con,'jefes'=>$jef, 'deducciones'=> $ded, 'beneficios'=> $ben]);
}
if ($action === 'ajuste_list'){
  $id_empleado = getInt('id_empleado');
  if(!$id_empleado) j(['ok'=>false, 'msg'=> 'id_empleado requerido']);

  $sql = "
    SELECT ae.id_ajuste_empleado, ae.tipo, ae.porcentaje, ae.monto, ae.vigente_desde, ae.vigente_hasta, ae.estado,
      COALESCE(td.tipo_deduccion, b.tipo_beneficio) AS nombre_ajuste
    FROM ajuste_empleado ae
    LEFT JOIN cat_tipo_deduccion td ON td.id_tipo_deduccion = ae.id_tipo_deduccion
    LEFT JOIN cat_beneficio b ON b.id_beneficio = ae.id_beneficio
    WHERE ae.id_empleado = ?
    ORDER BY ae.vigente_desde DESC, ae.tipo";

    $st = $conn->prepare($sql);
    $st->bind_param('i', $id_empleado);
    $st->execute();
    $data = $st->get_result()->fetch_all(MYSQLI_ASSOC) ?? [];
    $st->close();
    j(['ok'=>true, 'data'=>$data]);
}
if ($action === 'ajuste_crear_actualizar'){
  $id_empleado = getInt('id_empleado');
  $id_ajuste_empleado = getInt('id_ajuste_empleado');
  $tipo = getStr('tipo');
  $id_catalogo = getInt('id_catalogo');
  $porcentaje = (float)getStr('porcentaje');
  $monto = (float)getStr('monto');
  $vigente_desde = getStr('vigente_desde');

  if(!$id_empleado || !in_array($tipo, ['Deduccion', 'Beneficio']) || !$id_catalogo || !$vigente_desde)
    j(['ok'=>false, 'msg'=>'Faltan campos requeridos']);

  $id_deduccion = ($tipo === 'Deduccion')? $id_catalogo : null;
  $id_beneficio = ($tipo === 'Beneficio')? $id_catalogo : null;

  $conn->begin_transaction();
  try{
    if ($id_ajuste_empleado){
      $sql = "UPDATE ajuste_empleado SET
           porcentaje = ?, monto = ?, vigente_desde = ?,
           id_tipo_deduccion = ?, id_beneficio =?
           WHERE id_ajuste_empleado = ? AND estado = 'Activo'";
      $st = $conn->prepare($sql);
      $st->bind_param('ddsiii', $porcentaje, $monto, $vigente_desde, $id_deduccion, $id_beneficio, $id_ajuste_empleado);
      $st->execute();
      $st->close();
    } else {
      $sql = "INSERT INTO ajuste_empleado
           (id_empleado, tipo, id_tipo_deduccion, id_beneficio, porcentaje, monto, vigente_desde)
           VALUES (?, ?, ?, ?, ?, ?, ?)";
      $st = $conn->prepare($sql);
      $st->bind_param('isiidds', $id_empleado, $tipo, $id_deduccion, $id_beneficio, $porcentaje, $monto, $vigente_desde);
      $st->execute();
      $st->close();
    }
    $conn->commit();
    j(['ok'=>true, 'msg'=>'Ajuste guardado']);
  } catch(Exception $e){
    $conn->rollback();
    j(['ok'=>false, 'msg'=>'Error al guardar: ' .$e->getMessage()]);
  }
}

if ($action === 'ajuste_cambiar_estado' || $action === 'ajuste_toggle_status'){
  $id_ajuste_empleado = getInt('id_ajuste_empleado');
  $estado_nuevo = getStr('estado');

  if(!$id_ajuste_empleado || !in_array($estado_nuevo, ['Activo', 'Inactivo']))
    j(['ok'=>false, 'msg'=>'Faltan campos o estado invalido']);

  $sql = "UPDATE ajuste_empleado SET estado = ? WHERE id_ajuste_empleado = ?";
  $st = $conn->prepare($sql);
  $st->bind_param('si', $estado_nuevo, $id_ajuste_empleado);
  $st->execute();
  $st->close();
  j(['ok'=>true, 'msg'=>'Estado del ajuste actualizado']);
}

if ($action === 'emp_list'){
  $q = getStr('q');
  $page = max(1, getInt('page',1));
  $size = max(1, getInt('size',10));
  $off = ($page-1)*$size;

  $where = $q ? "WHERE CONCAT(dp.nombre,' ',dp.apellido) LIKE CONCAT('%',?,'%')" : "";
  $sql = "
    SELECT e.id_empleado,
           CONCAT(dp.nombre,' ',dp.apellido) AS nombre,
           (SELECT numero_documento FROM documento_identidad di WHERE di.id_datos_persona=dp.id_datos_persona LIMIT 1) AS cedula,
           (SELECT email FROM email em WHERE em.id_datos_persona=dp.id_datos_persona AND es_principal=1 LIMIT 1) AS email,
           (SELECT telefono FROM telefono t WHERE t.id_datos_persona=dp.id_datos_persona AND es_principal=1 LIMIT 1) AS telefono,
           c.cargo, c.salario_base, c.fecha_contratacion
    FROM empleado e
    JOIN datos_persona dp ON dp.id_datos_persona=e.id_datos_persona
    LEFT JOIN contrato_empleado c ON c.id_empleado=e.id_empleado AND c.vigente=1
    $where
    ORDER BY dp.nombre, dp.apellido
    LIMIT $size OFFSET $off";
  if ($q){
    $st = $conn->prepare($sql); 
    $st->bind_param('s',$q); 
    $st->execute(); 
    $rs=$st->get_result();

    $data = $rs->fetch_all(MYSQLI_ASSOC);
    $st->close();
    $st = $conn->prepare("SELECT COUNT(*) AS n FROM empleado e 
    JOIN datos_persona dp ON dp.id_datos_persona=e.id_datos_persona 
    WHERE CONCAT(dp.nombre,' ',dp.apellido) LIKE CONCAT('%',?,'%')");
    $st->bind_param('s',$q); 
    $st->execute(); 
    $tot = (int)$st->get_result()->fetch_assoc()['n']; 
    $st->close();
  } else {
    $data = $conn->query($sql)->fetch_all(MYSQLI_ASSOC) ?? [];
    $tot = (int)($conn->query("SELECT COUNT(*) AS n FROM empleado")->fetch_assoc()['n'] ?? 0);
  }
  j(['ok'=>true,'data'=>$data,'total'=>$tot]);
}

if ($action === 'emp_get'){
  $id = getInt('id_empleado');
  if(!$id) j(['ok'=>false,'msg'=>'id_empleado requerido']);
  $sql = "
    SELECT e.id_empleado, dp.id_datos_persona, dp.nombre, dp.apellido, dp.fecha_nacimiento, dp.genero,
           (SELECT numero_documento FROM documento_identidad di WHERE di.id_datos_persona=dp.id_datos_persona LIMIT 1) AS numero_documento,
           (SELECT email FROM email em WHERE em.id_datos_persona=dp.id_datos_persona AND es_principal=1 LIMIT 1) AS email,
           (SELECT telefono FROM telefono t WHERE t.id_datos_persona=dp.id_datos_persona AND es_principal=1 LIMIT 1) AS telefono,
           (SELECT ciudad FROM direccion d WHERE d.id_datos_persona=dp.id_datos_persona LIMIT 1) AS ciudad,
           (SELECT sector FROM direccion d WHERE d.id_datos_persona=dp.id_datos_persona LIMIT 1) AS sector,
           (SELECT calle FROM direccion d WHERE d.id_datos_persona=dp.id_datos_persona LIMIT 1) AS calle,
           (SELECT numero_casa FROM direccion d WHERE d.id_datos_persona=dp.id_datos_persona LIMIT 1) AS numero_casa,
           c.cargo, c.departamento, c.id_tipo_contrato, c.fecha_contratacion, c.salario_base, c.id_jefe
    FROM empleado e
    JOIN datos_persona dp ON dp.id_datos_persona=e.id_datos_persona
    LEFT JOIN contrato_empleado c ON c.id_empleado=e.id_empleado AND c.vigente=1
    WHERE e.id_empleado=?";
  $st=$conn->prepare($sql); $st->bind_param('i',$id); $st->execute(); $rs=$st->get_result(); $row=$rs->fetch_assoc(); $st->close();
  if(!$row) j(['ok'=>false,'msg'=>'No encontrado']);
  $row['id_empleado']=$id;
  j(['ok'=>true]+$row);
}

if ($action === 'emp_create' || $action === 'emp_update'){
  $id_empleado = getInt('id_empleado');
  $nombre = getStr('nombre'); 
  $apellido=getStr('apellido'); 
  $fecha_nacimiento=getStr('fecha_nacimiento');
  $genero = getInt('genero'); 
  $telefono=getStr('telefono'); 
  $email=getStr('email');
  $ciudad=getStr('ciudad'); 
  $sector=getStr('sector'); 
  $calle=getStr('calle'); 
  $numero_casa=getStr('numero_casa');
  $numero_documento=getStr('numero_documento'); 
  $id_tipo_documento = getInt('id_tipo_documento',1);
  $cargo=getStr('cargo'); 
  $id_tipo_contrato=getInt('id_tipo_contrato'); 
  $departamento=getStr('departamento');
  $fecha_contratacion=getStr('fecha_contratacion'); 
  $salario_base=(float)getStr('salario_base'); 
  $id_jefe = strlen(getStr('id_jefe')) ? getInt('id_jefe') : null;

  $conn->begin_transaction();
  try{
    if ($action === 'emp_create'){
      $st=$conn->prepare("INSERT INTO datos_persona (nombre,apellido,fecha_nacimiento,genero) 
      VALUES (?,?,?,?)");
      $st->bind_param('sssi',$nombre,$apellido,$fecha_nacimiento,$genero); $st->execute();
      $id_dp = $conn->insert_id; $st->close();
      $st=$conn->prepare("INSERT INTO empleado (id_datos_persona) VALUES (?)");
      $st->bind_param('i',$id_dp); 
      $st->execute(); 
      $id_empleado = $conn->insert_id; 
      $st->close();
      if($numero_documento){
        $st=$conn->prepare("INSERT INTO documento_identidad (id_datos_persona,id_tipo_documento,numero_documento,fecha_emision) 
        VALUES (?,?,?,CURDATE())");
        $st->bind_param('iis',$id_dp,$id_tipo_documento,$numero_documento); 
        $st->execute(); 
        $st->close();
      }
      if($telefono){ 
        $st=$conn->prepare("INSERT INTO telefono (id_datos_persona,telefono,es_principal) 
        VALUES (?,?,1)"); 
        $st->bind_param('is',$id_dp,$telefono); 
        $st->execute(); 
        $st->close(); 
      }
      if($email){ 
        $st=$conn->prepare("INSERT INTO email (id_datos_persona,email,es_principal) VALUES (?,?,1)"); 
        $st->bind_param('is',$id_dp,$email); 
        $st->execute(); 
        $st->close(); 
      }
      if($ciudad || $sector || $calle || $numero_casa){
        $st=$conn->prepare("INSERT INTO direccion (id_datos_persona,ciudad,sector,calle,numero_casa) 
        VALUES (?,?,?,?,?)");
        $st->bind_param('isssi',$id_dp,$ciudad,$sector,$calle,$numero_casa); 
        $st->execute(); 
        $st->close();
      }
      $st=$conn->prepare("INSERT INTO contrato_empleado (id_empleado,cargo,id_tipo_contrato,departamento,fecha_contratacion,salario_base,id_jefe,vigente) 
      VALUES (?,?,?,?,?,?,?,1)");
      $st->bind_param('isissdi',$id_empleado,$cargo,$id_tipo_contrato,$departamento,$fecha_contratacion,$salario_base,$id_jefe);
      $st->execute(); 
      $st->close();
    }else{
      $st=$conn->prepare("SELECT id_datos_persona FROM empleado WHERE id_empleado=?"); $st->bind_param('i',$id_empleado); $st->execute();
      $rs=$st->get_result(); $row=$rs->fetch_assoc(); $st->close();
      if(!$row) throw new Exception('Empleado no existe');
      $id_dp=(int)$row['id_datos_persona'];

      $st=$conn->prepare("UPDATE datos_persona SET nombre=?,apellido=?,fecha_nacimiento=?,genero=? 
      WHERE id_datos_persona=?");
      $st->bind_param('sssii',$nombre,$apellido,$fecha_nacimiento,$genero,$id_dp); 
      $st->execute(); 
      $st->close();

      if($numero_documento){
        $st=$conn->prepare("SELECT id_documento_identidad 
        FROM documento_identidad 
        WHERE id_datos_persona=? 
        LIMIT 1");
        $st->bind_param('i',$id_dp); 
        $st->execute(); 
        $r=$st->get_result()->fetch_assoc();
         $st->close();

        if($r){
          $st=$conn->prepare("UPDATE documento_identidad SET id_tipo_documento=?, numero_documento=?
          WHERE id_documento_identidad=?");
          $st->bind_param('isi',$id_tipo_documento,$numero_documento,$r['id_documento_identidad']); 
          $st->execute(); 
          $st->close();
        } else {
          $st=$conn->prepare("INSERT INTO documento_identidad (id_datos_persona,id_tipo_documento,numero_documento,fecha_emision) VALUES (?,?,?,CURDATE())");
          $st->bind_param('iis',$id_dp,$id_tipo_documento,$numero_documento); 
          $st->execute(); 
          $st->close();
        }
      }
      if($telefono){ $conn->query("DELETE FROM telefono 
      WHERE id_datos_persona=$id_dp AND es_principal=1");
        $st=$conn->prepare("INSERT INTO telefono (id_datos_persona,telefono,es_principal) 
        VALUES (?,?,1)"); $st->bind_param('is',$id_dp,$telefono); $st->execute(); $st->close(); }

      if($email){ $conn->query("DELETE FROM email 
      WHERE id_datos_persona=$id_dp AND es_principal=1");
        $st=$conn->prepare("INSERT INTO email (id_datos_persona,email,es_principal) 
        VALUES (?,?,1)"); $st->bind_param('is',$id_dp,$email); $st->execute(); $st->close(); }

      $conn->query("DELETE FROM direccion 
      WHERE id_datos_persona=$id_dp");
      if($ciudad||$sector||$calle||$numero_casa){
        $st=$conn->prepare("INSERT INTO direccion (id_datos_persona,ciudad,sector,calle,numero_casa) 
        VALUES (?,?,?,?,?)");
        $st->bind_param('isssi',$id_dp,$ciudad,$sector,$calle,$numero_casa);
        $st->execute(); 
        $st->close();
      }
      $conn->query("UPDATE contrato_empleado SET vigente=0 
      WHERE id_empleado=$id_empleado 
      AND vigente=1");
      $st=$conn->prepare("INSERT INTO contrato_empleado (id_empleado,cargo,id_tipo_contrato,departamento,fecha_contratacion,salario_base,id_jefe,vigente) VALUES (?,?,?,?,?,?,?,1)");
      $st->bind_param('isissdi',$id_empleado,$cargo,$id_tipo_contrato,$departamento,$fecha_contratacion,$salario_base,$id_jefe);
      $st->execute(); 
      $st->close();
    }

    $conn->commit();
    j(['ok'=>true,'id_empleado'=>$id_empleado]);
  }catch(Exception $e){
    $conn->rollback();
    j(['ok'=>false,'msg'=>$e->getMessage()]);
  }
}

if ($action === 'buscar_empleado'){
  $periodo = getStr('periodo');
  $q = getStr('q');

  if (!$periodo) j(['ok'=>false,'msg'=>'Periodo requerido']);

  $filtro = '';
  if ($q){
    $qEsc = $conn->real_escape_string($q);
    $filtro = "AND (dp.nombre LIKE '%$qEsc%' OR dp.apellido LIKE '%$qEsc%' OR di.numero_documento LIKE '%$qEsc%')";
  }
  $sql = "SELECT
    e.id_empleado,
    CONCAT(dp.nombre,' ',dp.apellido) AS nombre,
    c.salario_base,
    c.cargo,
    COALESCE((
      SELECT SUM(monto_total)
      FROM horas_extras he
      WHERE he.id_empleado = e.id_empleado
      AND DATE_FORMAT(he.fecha, '%Y-%m') = ?), 0) AS horas_extra
    FROM empleado e
    JOIN datos_persona dp ON dp.id_datos_persona = e.id_datos_persona
    LEFT JOIN documento_identidad di ON di.id_datos_persona = dp.id_datos_persona
    JOIN contrato_empleado c ON c.id_empleado = e.id_empleado AND c.vigente = 1
    WHERE 1=1 $filtro
    ORDER BY dp.nombre, dp.apellido ASC
  ";

  $st = $conn->prepare($sql);
  $st->bind_param('s', $periodo);
  $st->execute();
  $data = $st->get_result()->fetch_all(MYSQLI_ASSOC) ?? [];
  $st->close();

  foreach ($data as &$row) {
    if (!isset($row['horas_extra'])) $row['horas_extra'] = 0;
    if (!isset($row['bonificaciones'])) $row['bonificaciones'] = 0;
    if (!isset($row['deducciones'])) $row['deducciones'] = 0;
  }
  unset($row);

  j(['ok'=>true,'rows'=>$data, 'data'=>$data]);
}

if ($action === 'horas_extras_g'){
  $periodo = getStr('periodo');
  $items = $_POST['items'] ?? [];
  if (!$periodo) j(['ok'=>false,'msg'=>'Periodo requerido']);

  // Fecha de registro para las horas extra (usar fecha actual)
  $fecha = date('Y-m-d');

  $conn->begin_transaction();
  try{
    $stDel = $conn->prepare("DELETE FROM horas_extras 
    WHERE id_empleado = ? 
    AND DATE_FORMAT(fecha, '%Y-%m') = ?");
    $stIns = $conn->prepare("INSERT INTO horas_extras (id_empleado, cantidad_horas, pago_horas_extras, monto_total, fecha)
    VALUES (?,1,?,?,?)");

    foreach($items as $item){
      $id = (int)$item['id_empleado'];
      $monto = (float)$item['monto'];

      $stDel->bind_param('is', $id, $periodo);
      $stDel->execute();

      if ($monto > 0){
        // pago_horas_extras y monto_total guardan el mismo valor aquí
        $stIns->bind_param('idds', $id, $monto, $monto, $fecha);
        $stIns->execute();
      }
    }
    $conn->commit();
    j(['ok'=>true,'msg'=>'Horas extras guardadas']);
  }catch(Exception $e) {
    $conn->rollback();
    j(['ok'=>false,'msg'=>'Error al guardar: '.$e->getMessage()]);
  }
}

if ($action === 'nomina_preview'){
  $periodo = getStr('periodo');
  $id_emp = getStr('id_empleado','ALL');
  if(!$periodo) j(['ok'=>false,'msg'=>'Periodo requerido']);
  $periodoSQL = $conn->real_escape_string($periodo);
  if ($conn->multi_query("CALL generar_nomina_empleado('".$periodoSQL."')")){
    do {
      if ($res = $conn->store_result()) { $res->free(); }
    } while ($conn->more_results() && $conn->next_result());
  } else {
    j(['ok'=>false,'msg'=>'Error al ejecutar procedimiento de nómina']);
  }

  $rsNom = $conn->query("SELECT id_nomina FROM nomina WHERE periodo='$periodoSQL'")->fetch_assoc();
  if(!$rsNom) j(['ok'=>false,'msg'=>'No se pudo generar nómina']);
  $id_nomina = (int)$rsNom['id_nomina'];

  $filtroEmpleado = ($id_emp==='ALL' ? '' : ' AND ne.id_empleado='.(int)$id_emp.' ');
  $sql = "SELECT ne.id_empleado, ne.salario_base, CONCAT(dp.nombre,' ',dp.apellido) AS nombre
          FROM nomina_empleado ne
          JOIN empleado e ON e.id_empleado=ne.id_empleado
          JOIN datos_persona dp ON dp.id_datos_persona=e.id_datos_persona
          WHERE ne.id_nomina=$id_nomina $filtroEmpleado
          ORDER BY nombre";
  $rows = $conn->query($sql)->fetch_all(MYSQLI_ASSOC) ?? [];

  foreach($rows as &$r){
    $idEmpleado = (int)$r['id_empleado'];
    $idNErow = $conn->query("SELECT id_nomina_empleado 
    FROM nomina_empleado 
    WHERE id_nomina=$id_nomina 
    AND id_empleado=$idEmpleado 
    LIMIT 1")->fetch_assoc();
    $idNE = $idNErow ? (int)$idNErow['id_nomina_empleado'] : 0;
    $hxRow = $conn->query("SELECT SUM(monto_total) AS total 
    FROM horas_extras WHERE id_empleado=$idEmpleado 
    AND DATE_FORMAT(fecha,'%Y-%m')='$periodoSQL'")->fetch_assoc();
    $r['horas_extra'] = ($hxRow && $hxRow['total']!==null) ? (float)$hxRow['total'] : 0.0;
    $benRow = $idNE ? $conn->query("SELECT SUM(monto) AS total 
    FROM beneficios_empleado 
    WHERE id_nomina_empleado=$idNE")->fetch_assoc() : null;
    $r['bonificaciones'] = ($benRow && $benRow['total']!==null) ? (float)$benRow['total'] : 0.0;
    $dedRow = $idNE ? $conn->query("SELECT SUM(monto) AS total 
    FROM deducciones_empleado 
    WHERE id_nomina_empleado=$idNE")->fetch_assoc() : null;
    $r['deducciones'] = ($dedRow && $dedRow['total']!==null) ? (float)$dedRow['total'] : 0.0;
  }
  unset($r);
  j(['ok'=>true,'rows'=>$rows,'id_nomina'=>$id_nomina]);
}

if ($action === 'nomina_save'){
  $periodo = getStr('periodo');
  if (!$periodo) j(['ok'=>false, 'msg'=>'Periodo requerido']);

  $periodoSQL = $conn->real_escape_string($periodo);
  $row = null;
  if ($conn->multi_query("CALL generar_nomina_empleado('".$periodoSQL."')")){
    if ($res = $conn->store_result()){
      $row = $res->fetch_assoc();
      $res->free();
    }
    while ($conn->more_results() && $conn->next_result()){
      if ($res = $conn->store_result()) { $res->free(); }
    }
    j(['ok'=> true, 'id_nomina' => $row['id_nomina'] ?? null]);
  } else {
    j(['ok'=> false, 'msg' => 'Error al generar la nomina']);
  }
}

if ($action === 'nomina_comprobantes'){
  $periodo = getStr('periodo');
  $rs = $conn->query("SELECT id_nomina FROM nomina WHERE periodo='".$conn->real_escape_string($periodo)."'")->fetch_assoc();
  if(!$rs) j(['ok'=>false,'msg'=>'No hay nómina para ese periodo']);
  $id_nomina = (int)$rs['id_nomina'];
  $rows = $conn->query("
    SELECT ne.*, CONCAT(dp.nombre,' ',dp.apellido) AS nombre, c.cargo
    FROM nomina_empleado ne
    JOIN empleado e ON e.id_empleado=ne.id_empleado
    JOIN datos_persona dp ON dp.id_datos_persona=e.id_datos_persona
    LEFT JOIN contrato_empleado c ON c.id_empleado=e.id_empleado AND c.vigente=1
    WHERE ne.id_nomina=$id_nomina
    ORDER BY nombre
  ")->fetch_all(MYSQLI_ASSOC) ?? [];
  ob_start();
  echo "<html><head><meta charset='utf-8'><title>Comprobantes $periodo</title></head><body>";
  foreach($rows as $r){
    $idNE = (int)$r['id_nomina_empleado'];
    $idEmp = (int)$r['id_empleado'];
    $sb = (float)$r['salario_base'];
    $hx = 0.0; $bo = 0.0; $dd = 0.0;
    $hxRow = $conn->query("SELECT SUM(monto_total) AS total 
    FROM horas_extras 
    WHERE id_empleado=$idEmp 
    AND DATE_FORMAT(fecha, '%Y-%m')='".$conn->real_escape_string($periodo)."'")->fetch_assoc();
    if ($hxRow && $hxRow['total']!==null) $hx = (float)$hxRow['total'];

    $boRow = $conn->query("SELECT SUM(monto) AS total 
    FROM beneficios_empleado 
    WHERE id_nomina_empleado=$idNE")->fetch_assoc();
    if ($boRow && $boRow['total']!==null) $bo = (float)$boRow['total'];
    
    $ddRow = $conn->query("SELECT SUM(monto) AS total 
    FROM deducciones_empleado 
    WHERE id_nomina_empleado=$idNE")->fetch_assoc();
    if ($ddRow && $ddRow['total']!==null) $dd = (float)$ddRow['total'];
    $neto = $sb + $hx + $bo - $dd;
    echo "<html><div style='border:1px solid #ddd;padding:12px;margin:10px 0;border-radius:8px;'>
      <h3 style='margin:0 0 8px 0;'>Comprobante de pago — $periodo</h3>
      <p><b>Empleado:</b> {$r['nombre']} — {$r['cargo']}</p>
      <p><b>Salario base:</b> $".number_format($sb,2)."</p>
      <p><b>Horas extra:</b> $".number_format($hx,2)."</p>
      <p><b>Bonificaciones:</b> $".number_format($bo,2)."</p>
      <p><b>Deducciones:</b> $".number_format($dd,2)."</p>
      <p><b>Salario neto:</b> $".number_format($neto,2)."</p>
    </div>";
  }
  echo "<script>window.onload=()=>window.print();</script></body></html>";
  $html = ob_get_clean();
  j(['ok'=>true,'html'=>$html]);
}

j(['ok'=>false,'msg'=>'Acción no reconocida']);
