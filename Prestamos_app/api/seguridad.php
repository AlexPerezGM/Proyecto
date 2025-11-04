<?php
// api/seguridad.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

function j($ok, $data = null, $msg = null, $extra = []) {
  return json_encode(array_merge(['ok'=>$ok, 'data'=>$data, 'msg'=>$msg], $extra));
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
  if ($action === 'permisos_list') {
    $res = $conn->query("SELECT id_permiso, clave, nombre FROM permiso ORDER BY id_permiso")->fetch_all(MYSQLI_ASSOC);
    echo j(true, $res); exit;
  }

  if ($action === 'roles_list') {
    $sql = "SELECT r.id_rol, r.nombre,
            GROUP_CONCAT(p.nombre ORDER BY p.id_permiso SEPARATOR ', ') AS permisos
            FROM rol r
            LEFT JOIN rol_permiso rp ON rp.id_rol = r.id_rol
            LEFT JOIN permiso p ON p.id_permiso = rp.id_permiso
            GROUP BY r.id_rol, r.nombre
            ORDER BY r.id_rol DESC";
    $rows = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
    echo j(true, $rows, null, ['total'=>count($rows)]); exit;
  }

  if ($action === 'role_create') {
    $nombre = trim($_POST['nombre'] ?? '');
    $perms  = $_POST['permisos'] ?? []; // array de claves
    if ($nombre === '') { echo j(false,null,'Nombre de rol requerido'); exit; }

    $stmt = $conn->prepare("INSERT INTO rol (nombre) VALUES (?)");
    $stmt->bind_param('s', $nombre);
    $stmt->execute();
    $idRol = $stmt->insert_id;

    if (!empty($perms)) {
      // traducir claves -> id_permiso
      $in = "'".implode("','", array_map(fn($s)=>$conn->real_escape_string($s), $perms))."'";
      $map = $conn->query("SELECT id_permiso, clave FROM permiso WHERE clave IN ($in)")->fetch_all(MYSQLI_ASSOC);
      foreach ($map as $m) {
        $conn->query("INSERT IGNORE INTO rol_permiso (id_rol, id_permiso) VALUES ($idRol, {$m['id_permiso']})");
      }
    }
    echo j(true, ['id_rol'=>$idRol], 'Rol creado'); exit;
  }

  if ($action === 'role_update') {
    $idRol  = (int)($_POST['id_rol'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');
    $perms  = $_POST['permisos'] ?? [];
    if ($idRol<=0 || $nombre===''){ echo j(false,null,'Datos incompletos'); exit; }

    $stmt = $conn->prepare("UPDATE rol SET nombre=? WHERE id_rol=?");
    $stmt->bind_param('si', $nombre, $idRol);
    $stmt->execute();

    // reset permisos del rol
    $conn->query("DELETE FROM rol_permiso WHERE id_rol=$idRol");
    if (!empty($perms)) {
      $in = "'".implode("','", array_map(fn($s)=>$conn->real_escape_string($s), $perms))."'";
      $map = $conn->query("SELECT id_permiso FROM permiso WHERE clave IN ($in)")->fetch_all(MYSQLI_ASSOC);
      foreach ($map as $m) {
        $conn->query("INSERT IGNORE INTO rol_permiso (id_rol, id_permiso) VALUES ($idRol, {$m['id_permiso']})");
      }
    }
    echo j(true, null, 'Rol actualizado'); exit;
  }

  if ($action === 'role_get') {
    $idRol = (int)($_POST['id_rol'] ?? 0);
    $rol = $conn->query("SELECT id_rol, nombre FROM rol WHERE id_rol=$idRol")->fetch_assoc();
    $perms = $conn->query("SELECT p.clave FROM rol_permiso rp JOIN permiso p ON p.id_permiso=rp.id_permiso WHERE rp.id_rol=$idRol")->fetch_all(MYSQLI_ASSOC);
    $rol['permisos'] = array_column($perms, 'clave');
    echo j(true, $rol); exit;
  }

  // ---------- Usuarios ----------
  if ($action === 'users_list') {
    $sql = "SELECT u.id_usuario, u.nombre_usuario,
            GROUP_CONCAT(r.nombre ORDER BY r.id_rol SEPARATOR ', ') AS roles
            FROM usuario u
            LEFT JOIN usuario_rol ur ON ur.id_usuario = u.id_usuario
            LEFT JOIN rol r ON r.id_rol = ur.id_rol
            GROUP BY u.id_usuario, u.nombre_usuario
            ORDER BY u.id_usuario DESC";
    $rows = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
    echo j(true, $rows, null, ['total'=>count($rows)]); exit;
  }

  if ($action === 'user_create') {
    $user = trim($_POST['usuario'] ?? '');
    $pass = $_POST['contrasena'] ?? '';
    $idRol = (int)($_POST['id_rol'] ?? 0);
    if ($user==='' || $pass==='' || $idRol<=0) { echo j(false,null,'Datos requeridos'); exit; }

    $hash = password_hash($pass, PASSWORD_BCRYPT);
    $stmt = $conn->prepare("INSERT INTO usuario (nombre_usuario, contrasena) VALUES (?, ?)");
    $stmt->bind_param('ss', $user, $hash);
    $stmt->execute();
    $idUsuario = $stmt->insert_id;

    $conn->query("INSERT IGNORE INTO usuario_rol (id_usuario, id_rol) VALUES ($idUsuario, $idRol)");
    echo j(true, ['id_usuario'=>$idUsuario], 'Usuario creado'); exit;
  }

  if ($action === 'user_get') {
    $id = (int)($_POST['id_usuario'] ?? 0);
    $u = $conn->query("SELECT id_usuario, nombre_usuario FROM usuario WHERE id_usuario=$id")->fetch_assoc();
    $roles = $conn->query("SELECT id_rol FROM usuario_rol WHERE id_usuario=$id")->fetch_all(MYSQLI_ASSOC);
    $u['id_rol'] = $roles ? (int)$roles[0]['id_rol'] : null; // un rol principal
    echo j(true, $u); exit;
  }

  if ($action === 'user_update') {
    $id   = (int)($_POST['id_usuario'] ?? 0);
    $user = trim($_POST['usuario'] ?? '');
    $pass = $_POST['contrasena'] ?? '';
    $idRol = (int)($_POST['id_rol'] ?? 0);
    if ($id<=0 || $user==='' || $idRol<=0) { echo j(false,null,'Datos incompletos'); exit; }

    if ($pass !== '') {
      $hash = password_hash($pass, PASSWORD_BCRYPT);
      $stmt = $conn->prepare("UPDATE usuario SET nombre_usuario=?, contrasena=? WHERE id_usuario=?");
      $stmt->bind_param('ssi', $user, $hash, $id);
    } else {
      $stmt = $conn->prepare("UPDATE usuario SET nombre_usuario=? WHERE id_usuario=?");
      $stmt->bind_param('si', $user, $id);
    }
    $stmt->execute();

    $conn->query("DELETE FROM usuario_rol WHERE id_usuario=$id");
    $conn->query("INSERT IGNORE INTO usuario_rol (id_usuario, id_rol) VALUES ($id, $idRol)");
    echo j(true, null, 'Usuario actualizado'); exit;
  }

  echo j(false,null,'Acción no reconocida');
} catch (Throwable $e) {
  echo j(false,null,'Error: '.$e->getMessage());
}
