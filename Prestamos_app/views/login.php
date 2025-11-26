<?php
// Calcular ruta base para que los recursos funcionen si esta vista
// se incluye desde la raíz (`Index.php`) o se accede directamente.
$scriptDir = str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME']));
// Si la ruta termina en /views (acceso directo a la vista), subir un nivel.
if (basename($scriptDir) === 'views') {
    $APP_BASE = rtrim(dirname($scriptDir), '/');
} else {
    // Acceso desde Index.php u otro que ya está en la raíz del proyecto
    $APP_BASE = rtrim($scriptDir, '/');
}
if ($APP_BASE === '' || $APP_BASE === false) {
    $APP_BASE = '/';
}
$APP_BASE = $APP_BASE . '/';
?>
<!DOCTYPE html>
<html lang="es">
<head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Inicio de sesión</title>
        <link rel="stylesheet" href="<?= $APP_BASE ?>public/css/login.css">
        <script>window.APP_BASE = '<?= $APP_BASE ?>';</script>
        <script src="<?= $APP_BASE ?>public/JS/login.js"></script>
    </head>
    <body>
    <div class = 'logo'> Bienvenido a Prestamos App</div>
    <div class = 'login_contenedor'>
        <div class ='titulo'> Inicio de sesion</div>
        <form id= 'loginForm'>
            <div class = 'input_contenedor'>
                <label for='usuario'>Usuario:</label>
                <input type='text' id='usuario' name='usuario' required>
            </div>
            <div class = 'input_contenedor'>
                <label for='contrasena'>Contraseña:</label>
                <input type='password' id='contrasena' name='contrasena' required>
            </div>
            <div class = 'boton_contenedor'>
                <button type='submit'>Iniciar sesión</button>
            </div>
        </form>
    </div> 
</body>
</html>