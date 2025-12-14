<?php
$APP_BASE = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$APP_BASE = preg_replace('#/views$#','', $APP_BASE);
$APP_BASE = ($APP_BASE === '' ? '/' : $APP_BASE.'/');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso denegado</title>
    <link rel="stylesheet" href="<?= $APP_BASE ?>public/css/clientes.css">
    <link rel="stylesheet" href="<?= $APP_BASE ?>public/css/dashboard.css?v=1">
</head>
<body>
    <main class='page-wrapper'>
        <section class="card table-card">
            <div class="card-header">
                <h2 style="text-align: center;">Nombre de la empresa</h2>
                <h3 style="text-align: center;">Acceso denegado</h3>
                <p class="content-text"  style="text-align: center;">No tienes los permisos necesarios para acceder a este proceso.</p>
                <p class="content-text" style="text-align: center;">Si cree que deberia tener acceso al proceso, contacte con su superior o con el administrador del sistema.</p>
                <a href="<?= $APP_BASE ?>views/dashboard.php" class="btn btn-primary" style="display: block; margin: 0 auto; width: max-content;">Volver al inicio</a>
            </div>
        </section>
    </main>    
</body>
</html>