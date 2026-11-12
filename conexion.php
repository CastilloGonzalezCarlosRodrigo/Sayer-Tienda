<?php
$host = "localhost";
$user = "root";
$password = ""; // Por defecto en XAMPP/Laragon viene vacío
$database = "sayertienda_db";

$conexion = mysqli_connect($host, $user, $password, $database);

if (!$conexion) {
    die("Error de conexión: " . mysqli_connect_error());
}

// Configurar codificación de caracteres a UTF-8
mysqli_set_charset($conexion, "utf8");
?>