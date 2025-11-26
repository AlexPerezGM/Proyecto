<?php
require_once __DIR__ . '/../config/db.php'; // conexión mysqli

$nombre_usuario = $_POST['usuario'] ?? '';
$contrasena = $_POST['contrasena'] ?? '';

if (empty($nombre_usuario) || empty($contrasena)){
    echo json_encode(['ok' => false, 'mensaje'=>'Deb ingresar usuario y contraseña']);
    exit;
}

$stmt = $conn ->prepare(
    "SELECT 
    u.id_usuario,
    u.nombre_usuario,
    u.contrasena,
    dp.nombre,
    dp.apellido,
    e.id_empleado,
    r.id_rol,
    r.nombre_rol
    FROM usuario u
    JOIN datos_persona dp ON u.id_datos_persona = dp.id_datos_persona
    LEFT JOIN empleado e ON e.id_datos_persona = dp.id_datos_persona
    LEFT JOIN usuarios_roles ur ON ur.id_usuario = u.id_usuario
    LEFT JOIN roles r ON r.id_rol = ur.id_rol
    WHERE u.nombre_usuario = ? LIMIT 1
    "
);
$stmt->bind_param('s', $nombre_usuario);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0){
    echo json_encode(['ok' => false, 'mensaje'=>'Usuario incorrectos']);
    exit;
}

$usuario = $result->fetch_assoc();

if (!password_verify($contrasena, $usuario['contrasena'])){
    echo json_encode(['ok' => false, 'mensaje'=>'Contraseña incorrecta']);
    exit;
}

$nombreEmpleado = null;
$inicialEmpleado = null;

if (!empty($usuario['id_empleado'])){
    $stmt2 = $conn->prepare('
        SELECT dp2.nombre, 
        dp2.apellido
        FROM empleado e2
        JOIN datos_persona dp2 ON e2.id_datos_persona = dp2.id_datos_persona
        WHERE e2.id_empleado = ?
    ');
    $stmt2->bind_param('i', $usuario['id_empleado']);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    if ($result2->num_rows > 0) {
        $empleado = $result2->fetch_assoc();
        $nombreEmpleado = $empleado['nombre'] . ' ' . $empleado['apellido'];
        $inicialEmpleado = strtoupper(substr($empleado['nombre'], 0, 1));
    }
}

$respuesta = [
    'ok'=> true,
    'msg'=> 'Inicio de sesion exitoso',
    'usuario'=>[
        'id_usuario'=> $usuario['id_usuario'],
        'nombre_usuario'=> $usuario['nombre_usuario'],
        'id_empleado'=>$usuario['id_empleado'],
        'nombre_empleado'=> $nombreEmpleado,
        'inicial_empleado'=> $inicialEmpleado,
        'rol'=>[
            'id_rol'=>$usuario['id_rol'],
            'nombre_rol'=>$usuario['nombre_rol']
        ]
    ]
];


$ip = $_SERVER['REMOTE_ADDR']?? 'desconocida';
$accion = 'Inicio de sesion';
$historial = $conn->prepare('INSERT INTO historial_acceso (id_usuario, ip_acceso, accion) VALUES (?,?,?)');
$historial->bind_param('iss', $usuario['id_usuario'], $ip, $accion);
$historial->execute();

$rolId = $usuario['id_rol'] ?? null;
$permisosArray = [];
if ($rolId ){
    $stmtp = $conn->prepare("
        SELECT LOWER(REPLACE(p.nombre_permiso, ' ', '_')) AS permiso
        FROM roles_permisos rp
        JOIN permisos p ON p.id_permiso = rp.id_permiso
        WHERE rp.id_rol = ?"
    );
    $stmtp->bind_param('i', $rolId);
    $stmtp->execute();
    $resp = $stmtp->get_result()->fetch_all(MYSQLI_ASSOC);
    $permisosArray = array_column($resp, 'permiso');
}

session_start();

$_SESSION['usuario'] = [
    'id_usuario'      => $usuario['id_usuario'],
    'nombre_usuario'  => $usuario['nombre_usuario'],
    'nombre_empleado' => $nombreEmpleado,
    'inicial'         => $inicialEmpleado ?? $inicial,
    'rol'             => $usuario['nombre_rol'],
    'permisos'        => $permisosArray
];

echo json_encode(['ok' => true,
 'mensaje' => 'Inicio de sesión exitoso',
 'redirect' => '../views/dashboard.php']
, JSON_UNESCAPED_UNICODE);
exit;
?>