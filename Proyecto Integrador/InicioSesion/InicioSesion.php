<?php
require_once '../conf/connection.php';

session_start();
if ($_SERVER['REQUEST_METHOD'] == 'POST'){
    $username = trim($_POST['username']); // Eliminar espacios
    $password = $_POST['password'];

    try {
        $connection = new Connection();
        $pdo = $connection->connect();

        $sql = "SELECT * FROM usuarios WHERE LOWER(username) = LOWER(:username)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Verificar que el usuario existe y tiene contraseña
        if ($user && isset($user['PASSWORD']) && !empty($user['PASSWORD'])) {
            // Verificar la contraseña
            if (password_verify($password, $user['PASSWORD'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role_id'] = $user['role_id'];

                if ($user['role_id'] == 1){
                    header('Location: ../dashboard.php');
                    exit();
                }elseif ($user['role_id'] == 2){
                    header('Location: ../dashboard_user.php');
                    exit();
                }else {
                    echo 'Acceso no autorizado';
                }
            } else {
                $error_message = "Usuario o contraseña incorrectos";
            }
        } else {
            $error_message = "Usuario no encontrado o datos incorrectos";
        }
    } catch (\Throwable $th) {
        $error_message = "Error en el servidor: " . $th->getMessage();
    }
}

// Mostrar errores si existen
if (isset($error_message)) {
    echo "<div style='background-color: #f44336; color: white; padding: 10px; margin: 10px; border-radius: 5px;'>";
    echo "Error: " . htmlspecialchars($error_message);
    echo "</div>";
    echo "<a href='../index.php' style='color: #007acc; text-decoration: none; margin: 10px; display: inline-block;'>← Volver al inicio de sesión</a>";
}
?>