<?php
session_start();
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'administrador') {
    header("Location: index.php");
    exit();
}
include('conexion.php');

// ==========================================================
// 1. CONSULTA DE ALERTAS
// ==========================================================
$sql_alertas = "SELECT nombre, stock_actual, stock_minimo FROM productos WHERE stock_actual <= stock_minimo";
$res_alertas = mysqli_query($conexion, $sql_alertas);

// ==========================================================
// 2. CORTE DE CAJA DIARIO
// ==========================================================
$sql_hoy = "SELECT SUM(total) AS total_dia, COUNT(id_venta) AS total_transacciones 
            FROM ventas 
            WHERE DATE(fecha) = CURDATE()";
$res_hoy = mysqli_query($conexion, $sql_hoy);
$datos_hoy = mysqli_fetch_assoc($res_hoy);
$total_dia = $datos_hoy['total_dia'] ?? 0.00;
$transacciones_hoy = $datos_hoy['total_transacciones'] ?? 0;

// ==========================================================
// 3. REPORTE DE FACTURAS
// ==========================================================
$mes_seleccionado = isset($_GET['mes']) ? $_GET['mes'] : date('m');
$anio_seleccionado = isset($_GET['anio']) ? $_GET['anio'] : date('Y');

$sql_reporte = "SELECT v.id_venta, t.nombre AS sucursal, v.fecha, v.cliente_nombre, v.cliente_telefono, v.total
                FROM ventas v
                INNER JOIN tiendas t ON v.id_tienda = t.id_tienda
                WHERE v.requiere_factura = 1 AND MONTH(v.fecha) = '$mes_seleccionado' AND YEAR(v.fecha) = '$anio_seleccionado'
                ORDER BY v.fecha DESC";
$res_reporte = mysqli_query($conexion, $sql_reporte);

// ==========================================================
// 4. INVENTARIO COMPLETO
// ==========================================================
$sql_inventario = "SELECT * FROM productos ORDER BY categoria ASC, nombre ASC";
$res_inventario = mysqli_query($conexion, $sql_inventario);

// ==========================================================
// 5. CONSULTA PARA LA GRÁFICA COMPARATIVA
// ==========================================================
$sql_grafica = "SELECT MONTH(fecha) AS mes_num, SUM(total) AS total_mes 
                FROM ventas 
                GROUP BY MONTH(fecha) 
                ORDER BY mes_num ASC LIMIT 6";
$res_grafica = mysqli_query($conexion, $sql_grafica);

$meses_js = [];
$totales_js = [];
$meses_traducidos = [1=>'Ene', 2=>'Feb', 3=>'Mar', 4=>'Abr', 5=>'May', 6=>'Jun', 7=>'Jul', 8=>'Ago', 9=>'Sep', 10=>'Oct', 11=>'Nov', 12=>'Dic'];

while($g = mysqli_fetch_assoc($res_grafica)) {
    $meses_js[] = $meses_traducidos[$g['mes_num']];
    $totales_js[] = (float)$g['total_mes'];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>SayerTienda - Dashboard Administradora</title>
    <link rel="stylesheet" href="css/estilos.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .resumen-cards { display: flex; gap: 20px; margin-bottom: 25px; }
        .card { background: #fdfdfd; padding: 20px; border-radius: 6px; flex: 1; box-shadow: 0 2px 5px rgba(0,0,0,0.05); border-top: 5px solid var(--madera-media); }
        .card h3 { margin: 0 0 10px 0; color: var(--madera-oscura); font-size: 1rem; }
        .card p { margin: 0; font-size: 1.6rem; font-weight: bold; }
        .tabla-reporte th, .tabla-reporte td { padding: 12px; text-align: left; border-bottom: 1px solid #E0E0E0; }
        .tabla-reporte th { background-color: var(--madera-oscura); color: #fff; }
        .filtro-box form { display: flex; gap: 15px; box-shadow: none; padding: 0; border: none; align-items: flex-end; }
        .badge-reabastecer { background: #C62828; color: white; padding: 4px 8px; border-radius: 3px; font-size: 0.8rem; font-weight: bold; }
        .badge-ok { background: #388E3C; color: white; padding: 4px 8px; border-radius: 3px; font-size: 0.8rem; font-weight: bold; }
    </style>
</head>
<body>

    <div class="dashboard-container">
        
        <aside class="sidebar">
            <div class="sidebar-header">
                <h3>SayerTienda 🖌️</h3>
                <span style="font-size: 0.85rem; color: #BCAAA4;">Admin Control Panel</span>
            </div>
            <ul class="sidebar-menu">
                <li class="menu-item active" onclick="cambiarPestana(this, 'seccion-alertas')"><a><span class="icon">⚠️</span> Alertas Stock</a></li>
                <li class="menu-item" onclick="cambiarPestana(this, 'seccion-ventas')"><a><span class="icon">📊</span> Ventas Diarias</a></li>
                <li class="menu-item" onclick="cambiarPestana(this, 'seccion-graficas')"><a><span class="icon">📈</span> Gráficas Mensuales</a></li>
                <li class="menu-item" onclick="cambiarPestana(this, 'seccion-facturas')"><a><span class="icon">📋</span> Facturas Electrón.</a></li>
                <li class="menu-item" onclick="cambiarPestana(this, 'seccion-productos')"><a><span class="icon">📦</span> Catálogo Sayer</a></li>
                <li style="margin-top: 40px; border-top: 1px solid rgba(255,255,255,0.1);">
                    <a href="index.php" style="color: #FF8A80;"><span class="icon">🚪</span> Salir del Sistema</a>
                </li>
            </ul>
        </aside>

        <main class="main-content">
            
            <div style="margin-bottom: 30px; border-bottom: 2px solid #E0E0E0; padding-bottom: 10px;">
                <h1 style="margin: 0; color: var(--madera-oscura);">Panel de Control Operativo</h1>
                <p style="margin: 5px 0 0 0; color: #616161;">Sesión activa: <strong><?php echo $_SESSION['nombre_usuario']; ?></strong></p>
            </div>

            <div id="seccion-alertas" class="dashboard-section active-tab">
                <h2>⚠️ Alertas Críticas de Inventario</h2>
                <?php if (mysqli_num_rows($res_alertas) > 0): ?>
                    <div class="contenedor-alertas">
                        <?php while($prod = mysqli_fetch_assoc($res_alertas)): ?>
                            <div class="alerta-item">
                                <strong>¡Reabastecer!</strong> El producto <span><?php echo $prod['nombre']; ?></span> está por debajo del mínimo seguro.
                                (Actual: <?php echo $prod['stock_actual']; ?> pzas | Mínimo: <?php echo $prod['stock_minimo']; ?>)
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p class="ok-msg">✅ Niveles de poliuretanos, solventes y nitroselulosas estables en ambas sucursales.</p>
                <?php endif; ?>
            </div>

            <div id="seccion-ventas" class="dashboard-section">
                <h2>📊 Balance de Ventas del Día</h2>
                <div class="resumen-cards">
                    <div class="card">
                        <h3>Corte de Caja Unificado (Hoy)</h3>
                        <p style="color: var(--pintura-ok); font-size: 2rem;">$<?php echo number_format($total_dia, 2); ?> MXN</p>
                    </div>
                    <div class="card" style="border-top-color: #FFB300;">
                        <h3>Tickets Despachados</h3>
                        <p style="font-size: 2rem;"><?php echo $transacciones_hoy; ?> ventas</p>
                    </div>
                </div>
            </div>

            <div id="seccion-graficas" class="dashboard-section">
                <h2>📈 Gráfica Comparativa de Ventas por Mes</h2>
                <p style="color: #616161; font-size: 0.9rem; margin-bottom: 20px;">Histórico financiero consolidado para la toma de decisiones.</p>
                <div class="canvas-container">
                    <canvas id="chartVentasMeses"></canvas>
                </div>
            </div>

            <div id="seccion-facturas" class="dashboard-section">
                <h2>📋 Registro Mensual de Facturas Electrónicas</h2>
                <div class="filtro-box" style="margin-bottom: 20px;">
                    <form method="GET" action="panel_admin.php">
                        <input type="hidden" name="tab" value="seccion-facturas">
                        <div class="filtro-group" style="flex: 2;">
                            <label>Filtrar Mes:</label>
                            <select name="mes">
                                <?php
                                $meses = ["01"=>"Enero","02"=>"Febrero","03"=>"Marzo","04"=>"Abril","05"=>"Mayo","06"=>"Junio","07"=>"Julio","08"=>"Agosto","09"=>"Septiembre","10"=>"Octubre","11"=>"Noviembre","12"=>"Diciembre"];
                                foreach($meses as $num => $nombre) {
                                    $sel = ($mes_seleccionado == $num) ? "selected" : "";
                                    echo "<option value='$num' $sel>$nombre</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="filtro-group" style="flex: 1;">
                            <label>Año:</label>
                            <select name="anio">
                                <option value="2026" <?php if($anio_seleccionado == '2026') echo 'selected'; ?>>2026</option>
                                <option value="2025" <?php if($anio_seleccionado == '2025') echo 'selected'; ?>>2025</option>
                            </select>
                        </div>
                        <button type="submit" class="btn-filtrar">Consultar Periodo</button>
                    </form>
                </div>

                <?php if(mysqli_num_rows($res_reporte) > 0): ?>
                    <table class="tabla-reporte">
                        <thead>
                            <tr>
                                <th>Folio</th>
                                <th>Sucursal</th>
                                <th>Fecha</th>
                                <th>Razón Social</th>
                                <th>Teléfono</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($reg = mysqli_fetch_assoc($res_reporte)): ?>
                                <tr>
                                    <td><strong>#<?php echo $reg['id_venta']; ?></strong></td>
                                    <td><?php echo $reg['sucursal']; ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($reg['fecha'])); ?></td>
                                    <td><?php echo $reg['cliente_nombre']; ?></td>
                                    <td><?php echo $reg['cliente_telefono']; ?></td>
                                    <td><strong>$<?php echo number_format($reg['total'], 2); ?></strong></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="ok-msg" style="background-color: #ECEFF1; color: #555; border-left-color: #90A4AE;">ℹ️ Sin solicitudes de facturas en este mes.</p>
                <?php endif; ?>
            </div>

            <div id="seccion-productos" class="dashboard-section">
                <h2>📦 Catálogo y Especificación de Productos Sayer Wood</h2>
                <table class="tabla-reporte">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Descripción Técnica de Uso</th>
                            <th>Stock</th>
                            <th>Precio</th>
                            <th>Estatus</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $cat_actual = "";
                        while($prod = mysqli_fetch_assoc($res_inventario)): 
                            if($prod['categoria'] !== $cat_actual): 
                                $cat_actual = $prod['categoria'];
                        ?>
                            <tr style="background-color: var(--madera-clara); font-weight: bold;">
                                <td colspan="5" style="color: var(--madera-oscura);">📁 Línea Sayer: <?php echo $cat_actual; ?></td>
                            </tr>
                        <?php endif; ?>
                        <tr>
                            <td><strong><?php echo $prod['nombre']; ?></strong></td>
                            <td style="font-size: 0.85rem; color: #555; max-width: 320px;"><?php echo $prod['descripcion']; ?></td>
                            <td><strong><?php echo $prod['stock_actual']; ?></strong> pzas</td>
                            <td>$<?php echo number_format($prod['precio'], 2); ?></td>
                            <td>
                                <?php echo ($prod['stock_actual'] <= $prod['stock_minimo']) ? '<span class="badge-reabastecer">REABASTECER</span>' : '<span class="badge-ok">OK</span>'; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

        </main>
    </div>

    <script>
        // 1. FUNCIÓN PARA INTERCAMBIAR PESTAÑAS (TABS) SIN RECARGAR
        function cambiarPestana(elementoMenu, idSeccion) {
            // Quitar clase activa de todos los botones del menú lateral
            document.querySelectorAll('.sidebar-menu .menu-item').forEach(item => {
                item.classList.remove('active');
            });
            // Ocultar todas las secciones de contenido
            document.querySelectorAll('.dashboard-section').forEach(seccion => {
                seccion.classList.remove('active-tab');
            });

            // Activar el botón cliqueado y mostrar la sección correspondiente
            elementoMenu.classList.add('active');
            document.getElementById(idSeccion).classList.add('active-tab');
        }

        // Mantener la pestaña si viene un filtro por URL (para el formulario de facturas)
        const urlParams = new URLSearchParams(window.location.search);
        const tabActiva = urlParams.get('tab');
        if (tabActiva) {
            // Buscamos el elemento del menú que corresponde a la sección
            const mapeoMenu = {
                'seccion-alertas': 0, 'seccion-ventas': 1, 'seccion-graficas': 2, 'seccion-facturas': 3, 'seccion-productos': 4
            };
            const items = document.querySelectorAll('.sidebar-menu .menu-item');
            if(items[mapeoMenu[tabActiva]]) {
                items[mapeoMenu[tabActiva]].click();
            }
        }

        // 2. CONFIGURACIÓN DE LA GRÁFICA (CHART.JS)
        const etiquetasMeses = <?php echo json_encode($meses_js); ?>;
        const datosVentas = <?php echo json_encode($totales_js); ?>;

        const ctx = document.getElementById('chartVentasMeses').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: etiquetasMeses.length ? etiquetasMeses : ['Sin datos'],
                datasets: [{
                    label: 'Ventas Netas Mensuales ($ MXN)',
                    data: datosVentas.length ? datosVentas : [0],
                    backgroundColor: 'rgba(93, 64, 55, 0.7)',
                    borderColor: '#3E2723',
                    borderWidth: 2,
                    borderRadius: 4,
                    hoverBackgroundColor: '#FFB300'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true, ticks: { callback: function(val) { return '$' + val; } } } }
            }
        });
    </script>
</body>
</html>