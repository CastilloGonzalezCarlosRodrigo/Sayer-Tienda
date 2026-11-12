<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SayerTienda - Login</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>
<body>
    <div class="login-container">
        <h2>SayerTienda - Control Interno</h2>
        <?php
        if (isset($_GET['error'])) {
            echo '<p class="error-msg">Usuario o contraseña incorrectos</p>';
        }
        ?>
        <form action="login_process.php" method="POST">
            <label>Usuario (Nombre de usuario):</label>
            <input type="text" name="usuario" required>
            
            <label>Contraseña:</label>
            <input type="password" name="password" required>
            
            <button type="submit">Ingresar</button>
        </form>
    </div>
</body>
</html>