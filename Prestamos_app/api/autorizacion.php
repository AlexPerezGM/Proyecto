<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function is_logged(){
    return isset($_SESSION['usuario']) && !empty($_SESSION['usuario']['id_usuario']);
}

function requiere_login(){
    if (!is_logged()){
        if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strpos($_SERVER['HTTP_ACCEPT'], 'application/json')!== false){
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok'=> false, 'msg'=>'No autorizado.']);
            exit;
        }
        header('Location: ../views/login.php');
        exit;
    }
}

function has_permission($permiso){
    if (!is_logged()) return false;
    $u = $_SESSION['usuario'];
    $normalize = function($s){
        if (!is_string($s)) return '';
        $s = trim($s);
        $s = str_replace([' ', '-', '\\'], '_', $s);
        return strtolower($s);
    };
    $target = $normalize($permiso);
    if (isset($u['permisos'])){
        $perms = $u['permisos'];
        if (!is_array($perms)) {
            $perms = array_map('trim', explode(',', (string)$perms));
        }
        foreach ($perms as $p) {
            if (is_array($p) && isset($p['clave'])) {
                if ($normalize($p['clave']) === $target) return true;
            } elseif (is_array($p) && isset($p['nombre'])) {
                if ($normalize($p['nombre']) === $target) return true;
            } else {
                if ($normalize((string)$p) === $target) return true;
            }
        }
    }
    if (isset($u['rol']) && !empty($u['rol'])){
        $roleNorm = $normalize($u['rol']);
        if ($roleNorm === $target) return true;
        if (strpos($roleNorm, 'admin') !== false) return true;
    }

    return false;
}

function require_permission($permiso, $redirectOnFail = true){
    if (has_permission($permiso)) return true;

    if ($redirectOnFail){
        http_response_code(403);
        header('Location: ../views/403.php');
        exit;
    }
    return false;
}
?>