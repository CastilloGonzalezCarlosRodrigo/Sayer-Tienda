<?php
session_start();
include('conexion.php');

$usuario = mysqli_real_escape_string($conexion, $_POST['usuario']);
$password = $_POST['password']; // En producción se recomienda usar password_verify con hashes

// Consulta para verificar usuario
$query = "SELECT * FROM usuarios WHERE username = '$usuario' AND password = '$password'";
$resultado = mysqli_query($conexion, $query);

if (mysqli_num_rows($resultado) == 1) {
    $datos_usuario = mysqli_fetch_assoc($resultado);
    
    // Guardamos variables de sesión importantes
    $_SESSION['id_usuario'] = $datos_usuario['id_usuario'];
    $_SESSION['nombre_usuario'] = $datos_usuario['nombre'];
    $_SESSION['rol'] = $datos_usuario['rol'];
    $_SESSION['id_tienda'] = $datos_usuario['id_tienda'];

    // Redirección según el rol
    if ($_SESSION['rol'] == 'administrador') {
        header("Location: panel_admin.php");
    } else {
        header("Location: panel_empleado.php");
    }
} else {
    // Si falla, regresa al login con un error
    header("Location: index.php?error=1");
}
mysqli_close($conexion);
?>