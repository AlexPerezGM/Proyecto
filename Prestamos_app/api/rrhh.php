<?php
// api/rrhh.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php'; // $conn = new mysqli(...)

function j($arr){ echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }
$action = $_POST['action'] ?? $_REQUEST['action'] ?? '';

if ($conn->connect_error) j(['ok'=>false,'msg'=>'DB Error']);

function getInt($k,$d=0){ return isset($_REQUEST[$k]) ? (int)$_REQUEST[$k] : $d; }
function getStr($k,$d=''){ return isset($_REQUEST[$k]) ? trim($_REQUEST[$k]) : $d; }

if ($action === 'options'){
  $gen = $conn->query("SELECT id_genero, genero FROM cat_genero ORDER BY id_genero")->fetch_all(MYSQLI_ASSOC);
  $con = $conn->query("SELECT id_tipo_contrato, tipo_contrato FROM cat_tipo_contrato ORDER BY id_tipo_contrato")->fetch_all(MYSQLI_ASSOC);
  // jefes/empleados para combos
  $jef = $conn->query("
    SELECT e.id_empleado, CONCAT(dp.nombre,' ',dp.apellido) AS nombre
    FROM empleado e
    JOIN datos_persona dp ON dp.id_datos_persona=e.id_datos_persona
    ORDER BY nombre
  ")->fetch_all(MYSQLI_ASSOC) ?? [];
  j(['ok'=>true,'generos'=>$gen,'contratos'=>$con,'jefes'=>$jef]);
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
    $st = $conn->prepare($sql); $st->bind_param('s',$q); $st->execute(); $rs=$st->get_result();
    $data = $rs->fetch_all(MYSQLI_ASSOC);
    $st->close();
    $st = $conn->prepare("SELECT COUNT(*) AS n FROM empleado e JOIN datos_persona dp ON dp.id_datos_persona=e.id_datos_persona WHERE CONCAT(dp.nombre,' ',dp.apellido) LIKE CONCAT('%',?,'%')");
    $st->bind_param('s',$q); $st->execute(); $tot = (int)$st->get_result()->fetch_assoc()['n']; $st->close();
  }else{
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
  $nombre = getStr('nombre'); $apellido=getStr('apellido'); $fecha_nacimiento=getStr('fecha_nacimiento');
  $genero = getInt('genero'); $telefono=getStr('telefono'); $email=getStr('email');
  $ciudad=getStr('ciudad'); $sector=getStr('sector'); $calle=getStr('calle'); $numero_casa=getStr('numero_casa');
  $numero_documento=getStr('numero_documento'); $id_tipo_documento = getInt('id_tipo_documento',1);
  $cargo=getStr('cargo'); $id_tipo_contrato=getInt('id_tipo_contrato'); $departamento=getStr('departamento');
  $fecha_contratacion=getStr('fecha_contratacion'); $salario_base=(float)getStr('salario_base'); $id_jefe = strlen(getStr('id_jefe')) ? getInt('id_jefe') : null;

  $conn->begin_transaction();
  try{
    if ($action === 'emp_create'){
      // 1) datos_persona
      $st=$conn->prepare("INSERT INTO datos_persona (nombre,apellido,fecha_nacimiento,genero) VALUES (?,?,?,?)");
      $st->bind_param('sssi',$nombre,$apellido,$fecha_nacimiento,$genero); $st->execute();
      $id_dp = $conn->insert_id; $st->close();

      // 2) empleado
      $st=$conn->prepare("INSERT INTO empleado (id_datos_persona) VALUES (?)");
      $st->bind_param('i',$id_dp); $st->execute(); $id_empleado = $conn->insert_id; $st->close();

      // 3) documento_identidad
      if($numero_documento){
        $st=$conn->prepare("INSERT INTO documento_identidad (id_datos_persona,id_tipo_documento,numero_documento,fecha_emision) VALUES (?,?,?,CURDATE())");
        $st->bind_param('iis',$id_dp,$id_tipo_documento,$numero_documento); $st->execute(); $st->close();
      }
      // 4) contacto
      if($telefono){ $st=$conn->prepare("INSERT INTO telefono (id_datos_persona,telefono,es_principal) VALUES (?,?,1)"); $st->bind_param('is',$id_dp,$telefono); $st->execute(); $st->close(); }
      if($email){ $st=$conn->prepare("INSERT INTO email (id_datos_persona,email,es_principal) VALUES (?,?,1)"); $st->bind_param('is',$id_dp,$email); $st->execute(); $st->close(); }
      if($ciudad || $sector || $calle || $numero_casa){
        $st=$conn->prepare("INSERT INTO direccion (id_datos_persona,ciudad,sector,calle,numero_casa) VALUES (?,?,?,?,?)");
        $st->bind_param('isssi',$id_dp,$ciudad,$sector,$calle,$numero_casa); $st->execute(); $st->close();
      }
      // 5) contrato
      $st=$conn->prepare("INSERT INTO contrato_empleado (id_empleado,cargo,id_tipo_contrato,departamento,fecha_contratacion,salario_base,id_jefe,vigente) VALUES (?,?,?,?,?,?,?,1)");
      $st->bind_param('isissdi',$id_empleado,$cargo,$id_tipo_contrato,$departamento,$fecha_contratacion,$salario_base,$id_jefe);
      $st->execute(); $st->close();
    }else{
      // UPDATE (básico) — obtenemos id_datos_persona
      $st=$conn->prepare("SELECT id_datos_persona FROM empleado WHERE id_empleado=?"); $st->bind_param('i',$id_empleado); $st->execute();
      $rs=$st->get_result(); $row=$rs->fetch_assoc(); $st->close();
      if(!$row) throw new Exception('Empleado no existe');
      $id_dp=(int)$row['id_datos_persona'];

      $st=$conn->prepare("UPDATE datos_persona SET nombre=?,apellido=?,fecha_nacimiento=?,genero=? WHERE id_datos_persona=?");
      $st->bind_param('sssii',$nombre,$apellido,$fecha_nacimiento,$genero,$id_dp); $st->execute(); $st->close();

      // upsert documento
      if($numero_documento){
        $st=$conn->prepare("SELECT id_documento_identidad FROM documento_identidad WHERE id_datos_persona=? LIMIT 1");
        $st->bind_param('i',$id_dp); $st->execute(); $r=$st->get_result()->fetch_assoc(); $st->close();
        if($r){
          $st=$conn->prepare("UPDATE documento_identidad SET id_tipo_documento=?, numero_documento=? WHERE id_documento_identidad=?");
          $st->bind_param('isi',$id_tipo_documento,$numero_documento,$r['id_documento_identidad']); $st->execute(); $st->close();
        }else{
          $st=$conn->prepare("INSERT INTO documento_identidad (id_datos_persona,id_tipo_documento,numero_documento,fecha_emision) VALUES (?,?,?,CURDATE())");
          $st->bind_param('iis',$id_dp,$id_tipo_documento,$numero_documento); $st->execute(); $st->close();
        }
      }
      // contacto simple (sobrescribe principal)
      if($telefono){ $conn->query("DELETE FROM telefono WHERE id_datos_persona=$id_dp AND es_principal=1");
        $st=$conn->prepare("INSERT INTO telefono (id_datos_persona,telefono,es_principal) VALUES (?,?,1)"); $st->bind_param('is',$id_dp,$telefono); $st->execute(); $st->close(); }
      if($email){ $conn->query("DELETE FROM email WHERE id_datos_persona=$id_dp AND es_principal=1");
        $st=$conn->prepare("INSERT INTO email (id_datos_persona,email,es_principal) VALUES (?,?,1)"); $st->bind_param('is',$id_dp,$email); $st->execute(); $st->close(); }

      // direccion
      $conn->query("DELETE FROM direccion WHERE id_datos_persona=$id_dp");
      if($ciudad||$sector||$calle||$numero_casa){
        $st=$conn->prepare("INSERT INTO direccion (id_datos_persona,ciudad,sector,calle,numero_casa) VALUES (?,?,?,?,?)");
        $st->bind_param('isssi',$id_dp,$ciudad,$sector,$calle,$numero_casa); $st->execute(); $st->close();
      }
      // contrato vigente (reemplaza)
      $conn->query("UPDATE contrato_empleado SET vigente=0 WHERE id_empleado=$id_empleado AND vigente=1");
      $st=$conn->prepare("INSERT INTO contrato_empleado (id_empleado,cargo,id_tipo_contrato,departamento,fecha_contratacion,salario_base,id_jefe,vigente) VALUES (?,?,?,?,?,?,?,1)");
      $st->bind_param('isissdi',$id_empleado,$cargo,$id_tipo_contrato,$departamento,$fecha_contratacion,$salario_base,$id_jefe);
      $st->execute(); $st->close();
    }

    $conn->commit();
    j(['ok'=>true,'id_empleado'=>$id_empleado]);
  }catch(Exception $e){
    $conn->rollback();
    j(['ok'=>false,'msg'=>$e->getMessage()]);
  }
}

if ($action === 'nomina_preview'){
  $periodo = getStr('periodo');
  $id_emp = getStr('id_empleado','ALL');

  $where = ($id_emp==='ALL' ? '' : 'AND e.id_empleado='.(int)$id_emp);
  $sql = "
    SELECT e.id_empleado, CONCAT(dp.nombre,' ',dp.apellido) AS nombre,
           IFNULL(c.salario_base,0) AS salario_base
    FROM empleado e
    JOIN datos_persona dp ON dp.id_datos_persona=e.id_datos_persona
    LEFT JOIN contrato_empleado c ON c.id_empleado=e.id_empleado AND c.vigente=1
    WHERE 1=1 $where
    ORDER BY nombre";
  $rows = $conn->query($sql)->fetch_all(MYSQLI_ASSOC) ?? [];

  // trae valores guardados si existen
  $rsNom = $conn->query("SELECT id_nomina FROM nomina WHERE periodo='".$conn->real_escape_string($periodo)."'")->fetch_assoc();
  if($rsNom){
    $id_nomina = (int)$rsNom['id_nomina'];
    foreach($rows as &$r){
      $det = $conn->query("SELECT horas_extra,bonificaciones,deducciones FROM nomina_empleado WHERE id_nomina=$id_nomina AND id_empleado=".$r['id_empleado'])->fetch_assoc();
      if($det){ $r['horas_extra']=(float)$det['horas_extra']; $r['bonificaciones']=(float)$det['bonificaciones']; $r['deducciones']=(float)$det['deducciones']; }
    }
  }
  j(['ok'=>true,'rows'=>$rows]);
}

if ($action === 'nomina_save'){
  $periodo = getStr('periodo');
  $rows = json_decode($_POST['rows'] ?? '[]', true);
  if(!$periodo || !is_array($rows)) j(['ok'=>false,'msg'=>'Datos incompletos']);

  $conn->begin_transaction();
  try{
    // upsert nomina
    $rs = $conn->query("SELECT id_nomina FROM nomina WHERE periodo='".$conn->real_escape_string($periodo)."'")->fetch_assoc();
    if($rs){ $id_nomina=(int)$rs['id_nomina']; $conn->query("DELETE FROM nomina_empleado WHERE id_nomina=$id_nomina"); }
    else { $conn->query("INSERT INTO nomina (periodo) VALUES ('".$conn->real_escape_string($periodo)."')"); $id_nomina=(int)$conn->insert_id; }

    $st=$conn->prepare("INSERT INTO nomina_empleado (id_nomina,id_empleado,salario_base,horas_extra,bonificaciones,deducciones,salario_neto) VALUES (?,?,?,?,?,?,?)");
    foreach($rows as $r){
      $sb=(float)$r['salario_base']; $hx=(float)$r['horas_extra']; $bo=(float)$r['bonificaciones']; $de=(float)$r['deducciones'];
      $neto = $sb + $hx + $bo - $de;
      $id_emp=(int)$r['id_empleado'];
      $st->bind_param('iiiddid',$id_nomina,$id_emp,$sb,$hx,$bo,$de,$neto);
      $st->execute();
    }
    $st->close();
    $conn->commit();
    j(['ok'=>true,'id_nomina'=>$id_nomina]);
  }catch(Exception $e){
    $conn->rollback(); j(['ok'=>false,'msg'=>$e->getMessage()]);
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
    echo "<div style='border:1px solid #ddd;padding:12px;margin:10px 0;border-radius:8px;'>
      <h3 style='margin:0 0 8px 0;'>Comprobante de pago — $periodo</h3>
      <p><b>Empleado:</b> {$r['nombre']} — {$r['cargo']}</p>
      <p><b>Salario base:</b> $".number_format($r['salario_base'],2)."</p>
      <p><b>Horas extra:</b> $".number_format($r['horas_extra'],2)."</p>
      <p><b>Bonificaciones:</b> $".number_format($r['bonificaciones'],2)."</p>
      <p><b>Deducciones:</b> $".number_format($r['deducciones'],2)."</p>
      <p><b>Salario neto:</b> $".number_format($r['salario_neto'],2)."</p>
    </div>";
  }
  echo "<script>window.onload=()=>window.print();</script></body></html>";
  $html = ob_get_clean();
  j(['ok'=>true,'html'=>$html]);
}

if ($action === 'asis_list'){
  $fecha = getStr('fecha', date('Y-m-d'));
  $sql = "
    SELECT a.id_asistencia_empleado, CONCAT(dp.nombre,' ',dp.apellido) AS nombre,
           a.hora_entrada, a.hora_salida, a.retraso_minutos
    FROM asistencia_empleado a
    JOIN empleado e ON e.id_empleado=a.id_empleado
    JOIN datos_persona dp ON dp.id_datos_persona=e.id_datos_persona
    WHERE a.fecha=?
    ORDER BY nombre";
  $st=$conn->prepare($sql);
  $st->bind_param('s',$fecha);
  $st->execute();
  $rs=$st->get_result();
  j(['ok'=>true,'data'=>$rs->fetch_all(MYSQLI_ASSOC)]);
}

if ($action === 'asis_entrada'){
  $fecha = date('Y-m-d'); $hora = date('H:i:s');
  $id_emp = 1;
  $horaRef = strtotime('08:00:00');
  $retraso = max(0, round((strtotime($hora) - $horaRef)/60));
  $conn->query("INSERT INTO asistencia_empleado (id_empleado,fecha,hora_entrada,retraso_minutos)
                VALUES ($id_emp,'$fecha','$hora',$retraso)
                ON DUPLICATE KEY UPDATE hora_entrada='$hora', retraso_minutos=$retraso");
  j(['ok'=>true]);
}

if ($action === 'asis_salida'){
  $fecha = date('Y-m-d'); $hora = date('H:i:s');
  $id_emp = 1; 
  $conn->query("UPDATE asistencia_empleado SET hora_salida='$hora' WHERE id_empleado=$id_emp AND fecha='$fecha'");
  j(['ok'=>true]);
}
// Acción por defecto si no coincide ninguna
j(['ok'=>false,'msg'=>'Acción no reconocida']);
