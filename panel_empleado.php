<?php
session_start();
// Validar que el usuario tenga sesión iniciada
if (!isset($_SESSION['rol'])) {
    header("Location: index.php");
    exit();
}
include('conexion.php');

// Extraer los productos para cargarlos en el buscador predictivo
$sql_prod = "SELECT id_producto, nombre, precio, stock_actual FROM productos WHERE stock_actual > 0 ORDER BY nombre ASC";
$res_prod = mysqli_query($conexion, $sql_prod);

// Creamos una matriz en PHP para pasársela a JavaScript y tener los precios/stock al instante
$productos_json = [];
while ($row = mysqli_fetch_assoc($res_prod)) {
    $productos_json[$row['id_producto']] = [
        'nombre' => $row['nombre'],
        'precio' => (float)$row['precio'],
        'stock' => (int)$row['stock_actual']
    ];
}
// Regresar el puntero al inicio para el HTML
mysqli_data_seek($res_prod, 0);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SayerTienda - Punto de Venta</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>
<body>
    
    <!-- ENCABEZADO -->
    <header style="background: var(--madera-oscura); color: white; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1 style="margin: 0; font-size: 1.6rem; color: #FFB300;">Punto de Venta - Sucursal</h1>
            <p style="margin: 5px 0 0 0; font-size: 0.9rem; color: #D7CCC8;">Empleado: <strong><?php echo $_SESSION['nombre_usuario']; ?></strong></p>
        </div>
        <a href="index.php" style="color: #FF8A80; font-weight: bold; text-decoration: none;">Cerrar Sesión 🚪</a>
    </header>

    <!-- CONTENEDOR PRINCIPAL EN DOS COLUMNAS -->
    <main class="pos-container">
        
        <!-- COLUMNA IZQUIERDA: SELECTOR Y DATOS DE FACTURA -->
        <div class="pos-card">
            <h3><span>🎨</span> Nueva Nota de Despacho</h3>
            
            <!-- Buscador Predictivo (Datalist) -->
            <div class="input-group" style="margin-bottom: 15px;">
                <label>Buscar Producto (Escribe nombre o código):</label>
                <input type="text" id="buscador-producto" list="lista-productos" placeholder="Ej. Fondo Poliuretano, U-0500..." onchange="seleccionarProducto()">
                <datalist id="lista-productos">
                    <?php while($p = mysqli_fetch_assoc($res_prod)): ?>
                        <option value="<?php echo $p['nombre']; ?>" data-id="<?php echo $p['id_producto']; ?>">
                    <?php endwhile; ?>
                </datalist>
                <!-- Campos ocultos para almacenar la selección activa -->
                <input type="hidden" id="producto-id-actual">
                <input type="hidden" id="producto-precio-actual">
                <input type="hidden" id="producto-stock-actual">
            </div>

            <!-- Monitor de Existencias en Vivo -->
            <div id="monitor-stock" style="margin-bottom: 15px; display: none;">
                <span id="stock-mensaje" class="stock-badge"></span>
                <span id="precio-mensaje" style="margin-left: 15px; font-weight: bold; color: #5D4037;"></span>
            </div>

            <!-- Cantidad a vender con validación reactiva -->
            <div class="input-group" style="margin-bottom: 15px;">
                <label>Cantidad a vender:</label>
                <input type="number" id="cantidad-vender" min="1" value="1" oninput="validarCantidadEnVivo()">
            </div>

            <button type="button" class="btn-añadir" id="btn-agregar-lista" onclick="agregarAlCarrito()" disabled>
                Añadir a la Lista ➕
            </button>

            <!-- Casilla de Facturación Electrónica Condicional -->
            <div style="margin-top: 30px; border-top: 1px dashed #E0E0E0; padding-top: 20px;">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-weight: bold;">
                    <input type="checkbox" id="chk-requiere-factura" onchange="alternarFacturacion()"> 
                    ¿El cliente requiere Factura Electrónica?
                </label>
                
                <!-- Subformulario animado que se despliega con JS -->
                <div id="form-factura" class="seccion-factura-dinamica">
                    <div class="input-group" style="margin-bottom: 10px;">
                        <label>Razón Social / Nombre Completo:</label>
                        <input type="text" id="factura-nombre" placeholder="Ej. Juan Pérez S.A. de C.V.">
                    </div>
                    <div class="input-group">
                        <label>Teléfono de Contacto Fiscal:</label>
                        <input type="tel" id="factura-telefono" placeholder="10 dígitos">
                    </div>
                </div>
            </div>
        </div>

        <!-- COLUMNA DERECHA: CARRITO DE COMPRAS EN VIVO -->
        <div class="pos-card" style="display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <h3><span>🛒</span> Lista de Compra Actual</h3>
                
                <table class="tabla-carrito" id="tabla-items-carrito">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Cant.</th>
                            <th>P. Unitario</th>
                            <th>Subtotal</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Fila vacía inicial -->
                        <tr id="carrito-vacio">
                            <td colspan="5" style="text-align: center; color: #9E9E9E; padding: 20px;">
                                El carrito está vacío. Añade productos desde la izquierda.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Panel de Cierre de Venta -->
            <div>
                <div class="total-contenedor">
                    Total a Pagar: <span id="txt-total-neto">$0.00</span>
                </div>
                
                <!-- Formulario invisible final que enviará los datos procesados a tu base de datos -->
                <form id="form-final-venta" method="POST" action="procesar_venta.php" style="box-shadow: none; padding: 0; background: none; margin-top: 15px;">
                    <!-- Aquí JS inyectará los datos del carrito en un string JSON -->
                    <input type="hidden" name="carrito_datos" id="json-carrito-input">
                    <input type="hidden" name="requiere_factura" id="final-requiere-factura" value="0">
                    <input type="hidden" name="cliente_nombre" id="final-cliente-nombre" value="">
                    <input type="hidden" name="cliente_telefono" id="final-cliente-telefono" value="">
                    
                    <button type="submit" class="btn" style="width: 100%; background-color: var(--pintura-ok); height: 45px; font-size: 1.1rem;" id="btn-cobrar" disabled>
                        CONSOLIDAR VENTA Y DESCONTAR STOCK ✔️
                    </button>
                </form>
            </div>
        </div>

    </main>

    <!-- ==========================================
         LÓGICA INTERACTIVA JAVASCRIPT (FRONTEND)
         ========================================== -->
    <script>
        // Traemos el diccionario de productos generado por PHP
        const catalogoProductos = <?php echo json_encode($productos_json); ?>;
        let carrito = [];

        // 1. EVENTO: SELECCIONAR PRODUCTO DESDE EL BUSCADOR PREDICTIVO
        function seleccionarProducto() {
            const inputBuscador = document.getElementById('buscador-producto');
            const lista = document.getElementById('lista-productos');
            const val = inputBuscador.value;
            
            let idEncontrado = null;
            
            // Buscar si el texto coincide con una opción del datalist
            for (let i = 0; i < lista.options.length; i++) {
                if (lista.options[i].value === val) {
                    idEncontrado = lista.options[i].getAttribute('data-id');
                    break;
                }
            }

            const monitor = document.getElementById('monitor-stock');
            const btnAgregar = document.getElementById('btn-agregar-lista');

            if (idEncontrado && catalogoProductos[idEncontrado]) {
                const prod = catalogoProductos[idEncontrado];
                
                // Guardar metadatos en campos ocultos
                document.getElementById('producto-id-actual').value = idEncontrado;
                document.getElementById('producto-precio-actual').value = prod.precio;
                document.getElementById('producto-stock-actual').value = prod.stock;

                // Actualizar monitor de stock e indicaciones en pantalla
                monitor.style.display = 'block';
                document.getElementById('precio-mensaje').innerText = `Precio: $${prod.precio.toFixed(2)} MXN`;
                
                const stockMsg = document.getElementById('stock-mensaje');
                stockMsg.innerText = `Existencia en bodega: ${prod.stock} piezas`;
                
                if(prod.stock > 0) {
                    stockMsg.className = "stock-badge stock-ok";
                    btnAgregar.disabled = false;
                } else {
                    stockMsg.className = "stock-badge stock-alert";
                    btnAgregar.disabled = true;
                }
                document.getElementById('cantidad-vender').value = 1;
            } else {
                // Si borran o escriben algo que no existe
                monitor.style.display = 'none';
                btnAgregar.disabled = true;
                document.getElementById('producto-id-actual').value = "";
            }
        }

        // 2. VALIDACIÓN DE STOCK EN TIEMPO REAL AL TECLEAR
        function validarCantidadEnVivo() {
            const cantInput = document.getElementById('cantidad-vender');
            const stockReal = parseInt(document.getElementById('producto-stock-actual').value) || 0;
            const btnAgregar = document.getElementById('btn-agregar-lista');
            const stockMsg = document.getElementById('stock-mensaje');

            let cantidadPedida = parseInt(cantInput.value) || 0;

            if (cantidadPedida > stockReal) {
                stockMsg.innerText = `🛑 ¡ERROR! Excedes el inventario (Máximo: ${stockReal} pzas)`;
                stockMsg.className = "stock-badge stock-alert";
                btnAgregar.disabled = true;
            } else if (cantidadPedida <= 0) {
                btnAgregar.disabled = true;
            } else {
                stockMsg.innerText = `Existencia en bodega: ${stockReal} piezas`;
                stockMsg.className = "stock-badge stock-ok";
                btnAgregar.disabled = false;
            }
        }

        // 3. ENLAZAR DESPLIEGUE CONDICIONAL DE FACTURACIÓN
        function alternarFacturacion() {
            const chk = document.getElementById('chk-requiere-factura');
            const formFactura = document.getElementById('form-factura');
            formFactura.style.display = chk.checked ? 'block' : 'none';
        }

        // 4. LÓGICA DEL CARRITO: AGREGAR ELEMENTO A LA TABLA DERECHA
        function agregarAlCarrito() {
            const id = document.getElementById('producto-id-actual').value;
            const nombre = document.getElementById('buscador-producto').value;
            const precio = parseFloat(document.getElementById('producto-precio-actual').value);
            const cantidad = parseInt(document.getElementById('cantidad-vender').value);

            if (!id || cantidad <= 0) return;

            // Verificar si el producto ya estaba en la lista para solo sumar la cantidad
            const indexExistente = carrito.findIndex(item => item.id === id);
            if (indexExistente > -1) {
                // Validar que la suma no supere el stock total disponible
                const nuevoTotalCant = carrito[indexExistente].cantidad + cantidad;
                if(nuevoTotalCant > catalogoProductos[id].stock) {
                    alert("No puedes agregar más de este artículo. Supera el inventario de la tienda.");
                    return;
                }
                carrito[indexExistente].cantidad = nuevoTotalCant;
                carrito[indexExistente].subtotal = nuevoTotalCant * precio;
            } else {
                // Agregar nueva fila al arreglo
                carrito.push({
                    id: id,
                    nombre: nombre,
                    cantidad: cantidad,
                    precio: precio,
                    subtotal: cantidad * precio
                });
            }

            // Resetear el buscador de la izquierda para la siguiente captura
            document.getElementById('buscador-producto').value = "";
            document.getElementById('monitor-stock').style.display = 'none';
            document.getElementById('btn-agregar-lista').disabled = true;

            renderizarCarrito();
        }

        // 5. FUNCIÓN PARA ELIMINAR ARTÍCULO DEL CARRITO
        function eliminarDelCarrito(index) {
            carrito.splice(index, 1);
            renderizarCarrito();
        }

        // 6. RENDERIZAR LA TABLA DEL CARRITO EN PANTALLA
        function renderizarCarrito() {
            const tbody = document.querySelector('#tabla-items-carrito tbody');
            const filaVacia = document.getElementById('carrito-vacio');
            
            // Limpiar filas de datos anteriores
            document.querySelectorAll('.item-carrito-row').forEach(row => row.remove());

            if (carrito.length === 0) {
                if(filaVacia) filaVacia.style.display = 'table-row';
                document.getElementById('btn-cobrar').disabled = true;
                document.getElementById('txt-total-neto').innerText = "$0.00";
                return;
            }

            if(filaVacia) filaVacia.style.display = 'none';
            
            let totalNeto = 0;

            carrito.forEach((item, index) => {
                totalNeto += item.subtotal;
                
                const tr = document.createElement('tr');
                tr.className = 'item-carrito-row';
                tr.innerHTML = `
                    <td><small>${item.nombre}</small></td>
                    <td><strong>${item.cantidad}</strong></td>
                    <td>$${item.precio.toFixed(2)}</td>
                    <td><strong>$${item.subtotal.toFixed(2)}</strong></td>
                    <td><button type="button" class="btn-eliminar" onclick="eliminarDelCarrito(${index})">❌</button></td>
                `;
                tbody.appendChild(tr);
            });

            // Actualizar total visual
            document.getElementById('txt-total-neto').innerText = `$${totalNeto.toFixed(2)} MXN`;
            document.getElementById('btn-cobrar').disabled = false;
            
            // Empaquetar todo el carrito en la variable oculta estructurada en JSON
            document.getElementById('json-carrito-input').value = JSON.stringify(carrito);
        }

        // 7. PREPARAR DATOS ANTES DE ENVIAR EL FORMULARIO FINAL POR POST
        document.getElementById('form-final-venta').addEventListener('submit', function(e) {
            const chkFactura = document.getElementById('chk-requiere-factura').checked;
            
            document.getElementById('final-requiere-factura').value = chkFactura ? "1" : "0";
            document.getElementById('final-cliente-nombre').value = document.getElementById('factura-nombre').value;
            document.getElementById('final-cliente-telefono').value = document.getElementById('factura-telefono').value;
            
            if(chkFactura && document.getElementById('factura-nombre').value.trim() === "") {
                e.preventDefault();
                alert("Por favor introduce la Razón Social para la factura electrónica.");
            }
        });
    </script>
</body>
</html>