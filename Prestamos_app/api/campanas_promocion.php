<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

function j($arr){echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }

$action = $_POST['action'] ?? $_GET['action'] ?? '';
if ($conn-> connect_error) j([
    'ok'=>false, 'msg'=>'Error de conexion a la base de datos'
]);

if ($action === 'promociones_listar'){
    $q = $_GET['q']?? '';
    $sql = "SELECT p.id_promocion,
        p.nombre_promocion AS nombre,
        tp.tipo_promocion AS tipo,
        p.puntos_necesarios AS puntos,
        p.descripcion AS descripcion,
        p.fecha_inicio AS inicio,
        p.fecha_fin AS fin,
        p.estado
        FROM promociones p
        LEFT JOIN tipo_promocion tp ON p.id_tipo_promocion = tp.id_tipo_promocion
        WHERE p.nombre_promocion LIKE ?";
    $stmt = $conn->prepare($sql);
    $like = "%$q%";
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    j(['ok'=> true, 'data'=>$res]);
}

if ($action === 'promocion_create' || $action === 'promocion_update'){
    $id = $_POST['id_promocion'] ?? null;
    $nombre = $_POST['nombre'] ?? '';
    $tipo = $_POST['tipo_promocion'] ?? '';
    $puntos = $_POST['puntos'] ?? 0;
    $estado = $_POST['estado'] ?? 'inactiva';
    $inicio = $_POST['fecha_inicio'] ?? null;
    $fin = $_POST['fecha_fin'] ?? null;
    $descripcion = $_POST['descripcion'] ?? '';
    
    //Buscar o crear tipo de promocion 
    $stmt = $conn->prepare("SELECT id_tipo_promocion FROM tipo_promocion WHERE tipo_promocion = ? LIMIT 1");
    $stmt->bind_param('s', $tipo);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    if ($r){
        $id_tipo = $r['id_tipo_promocion'];
    } else {
        $stmt2 = $conn->prepare("INSERT INTO tipo_promocion (tipo_promocion) VALUES (?)");
        $stmt2->bind_param('s', $tipo);
        $stmt2->execute();
        $id_tipo = $stmt2->insert_id;
    }

    if ($action === 'promocion_create' || !$id){
        $stmt = $conn->prepare("INSERT INTO promociones (nombre_promocion, estado, fecha_inicio, fecha_fin, puntos_necesarios, descripcion, id_tipo_promocion)
            VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param('ssssisi', $nombre, $estado, $inicio, $fin, $puntos, $descripcion, $id_tipo);
        $ok = $stmt->execute();
        j(['ok'=>$ok, 'msg'=>$ok ? 'Promocion creada': 'Error al crear la promocion']);
    } else {
        $stmt = $conn->prepare("UPDATE promociones 
            SET nombre_promocion=?, estado=?, fecha_inicio=?, fecha_fin=?, puntos_necesarios=?, descripcion=?, id_tipo_promocion=?
            WHERE id_promocion=?");
        $stmt->bind_param('ssssisii', $nombre, $estado, $inicio, $fin, $puntos, $descripcion, $id_tipo, $id);
        $ok = $stmt->execute();
        j(['ok'=>$ok, 'msg'=>$ok ? 'Promocion actualizada': 'Error al actualizar la promocion']);
    }
}

// Asignar promocion a un cliente cliente 
if ($action === 'promocion_asignar'){
    $idprom = $_POST['id_promocion'] ?? 0;
    $cliente = $_POST['cliente'] ?? [];
    $hoy = date('Y-m-d');

    $stmt = $conn->prepare("INSERT INTO asignacion_promocion(id_cliente, id_promocion, fecha_asignacion)
        VALUES (?,?,?)");
    foreach ($cliente as $c){
        $stmt->bind_param('iis', $c, $idprom, $hoy);
        $stmt->execute();
    }
    j(['ok'=>true, 'msg'=>'Promociones asignadas']);
}

// Crear campañas
if ($action === 'campana_lista'){
    $q = $_GET['q']?? '';
    $sql = "SELECT * FROM campanas WHERE nombre_campana LIKE ?";
    $stmt = $conn->prepare($sql);
    $like = "%$q%";
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    j(['ok'=> true, 'data'=>$res]);
}

if ($action === 'campana_create' || $action === 'campana_update'){
    $id = $_POST['id_campana']?? null;
    $nombre = $_POST['nombre']?? '';
    $estado = $_POST['estado']?? 'Inactiva';
    $inicio = $_POST['fecha_inicio']?? '';
    $fin = $_POST['fecha_fin']?? '';
    $descripcion = $_POST['descripcion']?? '';

    if ($action === 'campana_create'){
        $stmt = $conn->prepare("INSERT INTO campanas (nombre_campana, descripcion, fecha_inicio, fecha_fin, estado_campana)
            VALUES (?,?,?,?,?)");
        $stmt->bind_param('sssss', $nombre, $descripcion, $inicio, $fin, $estado);
        $ok = $stmt->execute();
        j(['ok'=>$ok, 'msg'=>$ok ? 'Campaña creada': 'Error al crear la campaña']);
    } else {
        $stmt = $conn->prepare("UPDATE campanas
            SET nombre_campana=?, descripcion=?, fecha_inicio=?, fecha_fin=?, estado_campana=?
            WHERE id_campana=?");
        $stmt->bind_param('sssssi', $nombre, $descripcion, $inicio, $fin, $estado, $id);
        $ok = $stmt->execute();
        j(['ok'=>$ok, 'msg'=>$ok ? 'Campaña actualizada': 'Error al actualizar la campaña']);
    }
}

// Vincular promociones a campañas
if ($action === 'campana_vinc_promocion') {
    $idcampana = $_POST['id_campana'] ?? 0;
    $promociones = $_POST['promociones'] ?? [];

    $conn->query("DELETE FROM promociones_campana WHERE id_campana = $idcampana");
    $stmt = $conn->prepare("INSERT INTO promociones_campana (id_promocion, id_campana) VALUES (?, ?)");
    foreach ($promociones as $p){
        $stmt->bind_param('ii', $p, $idcampana);
        $stmt->execute();
    }
    j(['ok'=>true, 'msg'=>'Promociones vinculadas a la campaña']);
}

if ($action === 'metricas_campana'){
    $sql = "SELECT 
        SUM(CASE WHEN LOWER(p.estado) = 'activa' THEN 1 ELSE 0 END) AS activas,
        SUM(CASE WHEN LOWER(p.estado) = 'inactiva' THEN 1 ELSE 0 END) AS inactivas,
        SUM(CASE WHEN p.fecha_fin < CURDATE() THEN 1 ELSE 0 END) AS vencidas
        FROM promociones p";
    $res = $conn->query($sql);
    if (!$res) 
        { error_log("ERR metricas query: ".$conn->error); 
        j(['ok'=>false, 'msg'=>'Error al obtener metricas']);}
    $data = $res->fetch_assoc();
    j(['ok'=>true, 'data'=>$data]);
}

// Lista de clientes para asignar promociones
if ($action === 'clientes_listar'){
    $sql = "SELECT c.id_cliente, CONCAT(d.nombre, ' ', d.apellido) AS nombre
        FROM cliente c
        JOIN datos_persona d ON c.id_datos_persona = d.id_datos_persona
        WHERE d.estado_cliente = 'Activo'";
    $res = $conn->query($sql);
    $clientes = [];
    while ($r = $res->fetch_assoc()) $clientes[] = $r;
    j(['ok'=>true, 'data'=>$clientes]);
}

// Lista de promociones vinculadas a una campaña
if ($action === 'campana_promociones_listar') {
    $idcampana = $_GET['id_campana'] ?? 0;

    $sql = "SELECT 
                p.id_promocion,
                p.nombre_promocion AS nombre,
                tp.tipo_promocion AS tipo,
                p.puntos_necesarios AS puntos,
                p.estado,
                p.fecha_inicio AS inicio,
                p.fecha_fin AS fin
            FROM promociones_campana pc
            JOIN promociones p ON p.id_promocion = pc.id_promocion
            JOIN tipo_promocion tp ON tp.id_tipo_promocion = p.id_tipo_promocion
            WHERE pc.id_campana = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log('campana_promociones_listar prepare failed: ' . $conn->error);
        j(['ok' => false, 'msg' => 'Error interno al preparar la consulta']);
    }
    $stmt->bind_param('i', $idcampana);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    j(['ok' => true, 'data' => $res]);
}
?>
