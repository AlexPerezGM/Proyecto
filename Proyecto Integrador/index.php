<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/styles.css?v=<?php echo time(); ?>">
    <title>Login</title>
</head>

<body>
    <div class="wrapper">
        <div class="title">Inicia sesion</div>
        <form action="InicioSesion/InicioSesion.php" method="POST">
            <div class="field">
                <input type="text" required name="username">
                <label>Usuario del sistema</label>
            </div>
            <div class="field">
                <input type="password" required name="password">
                <label>Contraseña</label>
            </div>
            <div class="field">
                <input type="submit" value="Ingresar">
            </div>
        </form>
    </div>
</body>

</html>
