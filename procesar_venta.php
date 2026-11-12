<?php
session_start();
if (!isset($_SESSION['rol'])) {
    header("Location: index.php");
    exit();
}
include('conexion.php');

// Recibir datos del formulario
$id_producto = $_POST['id_producto'];
$cantidad = intval($_POST['cantidad']);
$id_tienda = $_SESSION['id_tienda'];
$id_usuario = $_SESSION['id_usuario'];

// Manejar lógica de los campos de factura
$requiere_factura = isset($_POST['requiere_factura']) ? 1 : 0;
$nombre_cliente = $requiere_factura ? mysqli_real_escape_string($conexion, $_POST['nombre_cliente']) : NULL;
$tel_cliente = $requiere_factura ? mysqli_real_escape_string($conexion, $_POST['tel_cliente']) : NULL;

// 1. Obtener los datos del producto (Precio e inventario actual)
$sql_prod = "SELECT precio, stock_actual FROM productos WHERE id_producto = $id_producto";
$res_prod = mysqli_query($conexion, $sql_prod);
$producto = mysqli_fetch_assoc($res_prod);

if ($producto['stock_actual'] >= $cantidad) {
    // Calcular Total
    $total = $producto['precio'] * $cantidad;

    // 2. Registrar la venta en la tabla 'ventas' (Guardando el id_tienda que la genera)
    $sql_venta = "INSERT INTO ventas (id_tienda, id_usuario, fecha, requiere_factura, cliente_nombre, cliente_telefono, total) 
                  VALUES ($id_tienda, $id_usuario, NOW(), $requiere_factura, " . 
                  ($nombre_cliente ? "'$nombre_cliente'" : "NULL") . ", " . 
                  ($tel_cliente ? "'$tel_cliente'" : "NULL") . ", $total)";
    
    if (mysqli_query($conexion, $sql_venta)) {
        // 3. Restar del Stock en la tabla 'productos'
        $sql_update_stock = "UPDATE productos SET stock_actual = stock_actual - $cantidad WHERE id_producto = $id_producto";
        mysqli_query($conexion, $sql_update_stock);

        echo "<script>alert('Venta guardada exitosamente y stock actualizado.'); window.location.href='panel_empleado.php';</script>";
    } else {
        echo "Error al registrar la venta: " . mysqli_error($conexion);
    }
} else {
    echo "<script>alert('Error: No hay suficiente stock para cubrir la venta.'); window.location.href='panel_empleado.php';</script>";
}

mysqli_close($conexion);
?>