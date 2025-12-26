<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

function j($ok, $data = null, $msg = null, $extra = []) {
  return json_encode(array_merge(['ok'=>$ok, 'data'=>$data, 'msg'=>$msg], $extra));
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
  if ($action === 'permisos_list') {
    $sql = "SELECT id_permiso, LOWER(REPLACE(nombre_permiso, ' ', '_')) AS clave, nombre_permiso AS nombre 
    FROM permisos ORDER BY id_permiso";
    $res = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
    echo j(true, $res); exit;
  }

  if ($action === 'roles_list') {
    $sql = "SELECT r.id_rol, r.nombre_rol AS nombre,
            GROUP_CONCAT(p.nombre_permiso ORDER BY p.id_permiso SEPARATOR ', ') AS permisos
            FROM roles r
            LEFT JOIN roles_permisos rp ON rp.id_rol = r.id_rol
            LEFT JOIN permisos p ON p.id_permiso = rp.id_permiso
            GROUP BY r.id_rol, r.nombre_rol
            ORDER BY r.id_rol DESC";
    $rows = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
    echo j(true, $rows, null, ['total'=>count($rows)]); 
    exit;
  }

  if ($action === 'role_create') {
    $nombre = trim($_POST['nombre'] ?? '');
    $perms  = $_POST['permisos'] ?? []; 
    if ($nombre === '') { 
      echo j(false,null,'Nombre de rol requerido'); 
      exit; 
    }

    $descripcion = $nombre;
    $stmt = $conn->prepare("INSERT INTO roles (nombre_rol, descripcion) VALUES (?, ?)");
    $stmt->bind_param('ss', $nombre, $descripcion);
    $stmt->execute();
    $idRol = $stmt->insert_id;

    if (!empty($perms)) {
      $escaped = array_map(fn($s) => "'".$conn->real_escape_string($s)."'", $perms);
      $in = implode(',', $escaped);
      $map = $conn->query("SELECT id_permiso 
      FROM permisos 
      WHERE LOWER(REPLACE(nombre_permiso,' ', '_')) 
      IN ($in)")->fetch_all(MYSQLI_ASSOC);
      foreach ($map as $m) {
        $conn->query("INSERT IGNORE INTO roles_permisos (id_rol, id_permiso) VALUES ($idRol, {$m['id_permiso']})");
      }
    }
    echo j(true, ['id_rol'=>$idRol], 'Rol creado'); 
    exit;
  }

  if ($action === 'role_update') {
    $idRol  = (int)($_POST['id_rol'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');
    $perms  = $_POST['permisos'] ?? [];
    if ($idRol<=0 || $nombre===''){ 
      echo j(false,null,'Datos incompletos'); 
      exit; 
    }
    $stmt = $conn->prepare("UPDATE roles SET nombre_rol=? WHERE id_rol=?");
    $stmt->bind_param('si', $nombre, $idRol);
    $stmt->execute();

    $conn->query("DELETE FROM roles_permisos WHERE id_rol=$idRol");
    if (!empty($perms)) {
      $escaped = array_map(fn($s) => "'".$conn->real_escape_string($s)."'", $perms);
      $in = implode(',', $escaped);
      $map = $conn->query("SELECT id_permiso 
      FROM permisos 
      WHERE LOWER(REPLACE(nombre_permiso,' ', '_')) 
      IN ($in)")->fetch_all(MYSQLI_ASSOC);
      foreach ($map as $m) {
        $conn->query("INSERT IGNORE INTO roles_permisos (id_rol, id_permiso) 
        VALUES ($idRol, {$m['id_permiso']})");
      }
    }
    echo j(true, null, 'Rol actualizado'); 
    exit;
  }

  if ($action === 'role_get') {
    $idRol = (int)($_POST['id_rol'] ?? 0);
    $rol = $conn->query("SELECT id_rol, nombre_rol AS nombre 
    FROM roles 
    WHERE id_rol=$idRol")->fetch_assoc();
    
    $perms = $conn->query("SELECT LOWER(REPLACE(p.nombre_permiso,' ', '_')) AS clave 
    FROM roles_permisos rp 
    JOIN permisos p 
    ON p.id_permiso=rp.id_permiso 
    WHERE rp.id_rol=$idRol")->fetch_all(MYSQLI_ASSOC);
    $rol['permisos'] = array_column($perms, 'clave');
    echo j(true, $rol); 
    exit;
  }

  if ($action === 'users_list') {
    $sql = "SELECT u.id_usuario, u.nombre_usuario,
              dp.id_datos_persona,
              CONCAT(dp.nombre, ' ', dp.apellido) AS empleado,
            GROUP_CONCAT(r.nombre_rol ORDER BY r.id_rol SEPARATOR ', ') AS roles
            FROM usuario u
            LEFT JOIN datos_persona dp ON dp.id_datos_persona = u.id_datos_persona
            LEFT JOIN usuarios_roles ur ON ur.id_usuario = u.id_usuario
            LEFT JOIN roles r ON r.id_rol = ur.id_rol
            GROUP BY u.id_usuario, u.nombre_usuario, dp.id_datos_persona, dp.nombre, dp.apellido
            ORDER BY u.id_usuario DESC";
    $rows = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
    echo j(true, $rows, null, ['total'=>count($rows)]); 
    exit;
  }

  if ($action === 'empleados_list') {
    $sql = "SELECT 
            e.id_empleado,
            dp.id_datos_persona,
            CONCAT(dp.nombre, ' ', dp.apellido) AS nombre_completo
          FROM empleado e
          JOIN datos_persona dp ON dp.id_datos_persona = e.id_datos_persona
          ORDER BY dp.nombre, dp.apellido";
    $res = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
    echo j(true, $res); 
    exit;              
  }

  if ($action === 'user_create') {
    $user = trim($_POST['usuario'] ?? '');
    $pass = $_POST['contrasena'] ?? '';
    $idRol = (int)($_POST['id_rol'] ?? 0);
    $idDatos = (int)($_POST['id_datos_persona'] ?? 0);
    if ($user==='' || $pass==='' || $idRol<=0 || $idDatos<=0) { 
      echo j(false,null,'Datos requeridos'); 
      exit; 
    }

    $hash = password_hash($pass, PASSWORD_BCRYPT);
    $stmt = $conn->prepare("INSERT INTO usuario (nombre_usuario, contrasena, id_datos_persona) 
    VALUES (?, ?, ?)");
    $stmt->bind_param('ssi', $user, $hash, $idDatos);
    $stmt->execute();
    $idUsuario = $stmt->insert_id;

    $conn->query("INSERT IGNORE INTO usuarios_roles (id_usuario, id_rol) VALUES ($idUsuario, $idRol)");
    echo j(true, ['id_usuario'=>$idUsuario], 'Usuario creado'); 
    exit;
  }

  if ($action === 'user_get') {
    $id = (int)($_POST['id_usuario'] ?? 0);
    $u = $conn->query("SELECT id_usuario, nombre_usuario, id_datos_persona 
    FROM usuario 
    WHERE id_usuario=$id")->fetch_assoc();

    $roles = $conn->query("SELECT id_rol 
    FROM usuarios_roles 
    WHERE id_usuario=$id")->fetch_all(MYSQLI_ASSOC);
    $u['id_rol'] = $roles ? (int)$roles[0]['id_rol'] : null;
    echo j(true, $u); 
    exit;
  }

  if ($action === 'user_update') {
    $id   = (int)($_POST['id_usuario'] ?? 0);
    $user = trim($_POST['usuario'] ?? '');
    $pass = $_POST['contrasena'] ?? '';
    $idRol = (int)($_POST['id_rol'] ?? 0);
    $idDatos = (int)($_POST['id_datos_persona'] ?? 0);
    if ($id<=0 || $user==='' || $idRol<=0 || $idDatos<=0) { 
      echo j(false,null,'Datos incompletos'); 
      exit; 
    }

    if ($pass !== '') {
      $hash = password_hash($pass, PASSWORD_BCRYPT);
      $stmt = $conn->prepare("UPDATE usuario SET nombre_usuario=?, contrasena=?, id_datos_persona=? WHERE id_usuario=?");
      $stmt->bind_param('ssii', $user, $hash, $idDatos, $id);
    } else {
      $stmt = $conn->prepare("UPDATE usuario SET nombre_usuario=?, id_datos_persona=? WHERE id_usuario=?");
      $stmt->bind_param('sii', $user, $idDatos, $id);
    }
    $stmt->execute();

    $conn->query("DELETE FROM usuarios_roles WHERE id_usuario=$id");
    $conn->query("INSERT IGNORE INTO usuarios_roles (id_usuario, id_rol) VALUES ($id, $idRol)");
    echo j(true, null, 'Usuario actualizado'); 
    exit;
  }
} catch (Exception $e) {
  echo j(false, null, 'Error en el servidor: '.$e->getMessage()); 
  exit;
}