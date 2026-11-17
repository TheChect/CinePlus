<?php
/*
 * Cine+ · Panel administrativo (Dashboard) con estadísticas en vivo
 * - Al cargar, PHP pinta los números reales con calcularEstadisticas() (config/estadisticas.php).
 * - Después, js/panel.js pide admin/estadisticas.php cada 10 segundos y los actualiza sin recargar.
 * - Todo se calcula con SELECT sobre las tablas: no hay vistas ni tablas de estadísticas.
 * Solo pueden verlo los administradores.
 */
require __DIR__ . '/../config/solo_admin.php';
require_once __DIR__ . '/../config/estadisticas.php';

$est = calcularEstadisticas(conectar());

// Texto de la variación de ingresos (lo mismo que hace panel.js)
function textoVariacion(array $est): string
{
    if ($est['variacionIngresos'] === null) {
        return '<span class="texto-suave">Sin ventas en ' . e($est['mesAnterior']) . ' para comparar</span>';
    }
    $sube = $est['variacionIngresos'] >= 0;
    return '<b class="' . ($sube ? 'verde' : 'rojo') . '">' . ($sube ? '+ ' : '− ') . abs($est['variacionIngresos']) . '%</b>'
        . ' <span class="texto-suave">vs. ' . e($est['mesAnterior']) . '</span>';
}

function textoDiferenciaPedidos(array $est): string
{
    $d = $est['diferenciaPedidos'];
    if ($d === 0) {
        return 'Igual que ' . e($est['mesAnterior']);
    }
    return ($d > 0 ? '+ ' : '− ') . abs($d) . ($d > 0 ? ' más' : ' menos') . ' que ' . e($est['mesAnterior']);
}

// 24600 -> "$24.6k" para las etiquetas de las barras
function cantidadCorta(float $cantidad): string
{
    return $cantidad >= 1000 ? '$' . number_format($cantidad / 1000, 1) . 'k' : '$' . round($cantidad);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Panel administrativo · Cine+</title>
  <link rel="stylesheet" href="../css/estilos.css">
  <link rel="stylesheet" href="../css/admin.css">
</head>
<body>

<div class="admin">

  <!-- Barra lateral con el menú de administración -->
  <aside class="lateral">
    <a class="logo" href="panel.php"><span class="logo-cuadro"></span> CINE+</a>

    <p class="lateral-titulo">Principal</p>
    <nav class="lateral-menu">
      <a class="activo" href="panel.php">Dashboard</a>
      <a href="usuarios.php">Gestión de Usuarios</a>
      <a href="peliculas.php">Gestión de Películas</a>
      <a href="generos.php">Gestión de Géneros</a>
      <a href="formatos.php">Gestión de Formatos</a>
    </nav>

    <!-- Parte de abajo: cerrar sesión y datos del administrador -->
    <div class="lateral-abajo">
      <a href="../logout.php">&larr; Cerrar sesión</a>
      <div class="admin-usuario">
        <div class="avatar avatar-chico"></div>
        <div>
          <!-- Datos del administrador que inició sesión -->
          <strong><?= e($admin['nombre']) ?></strong>
          <span class="texto-suave chico"><?= e($admin['correo']) ?></span>
        </div>
      </div>
    </div>
  </aside>

  <!-- Lado derecho: barra superior y contenido -->
  <div class="admin-derecha">
    <header class="admin-barra">
      <input class="campo buscador" type="search" placeholder="Buscar en el panel...">
      <div class="avatar avatar-chico"></div>
    </header>

    <main class="admin-contenido">
      <!-- Título y hora de la última actualización (la cambia js/panel.js) -->
      <div class="admin-titulo">
        <div>
          <h1>Panel administrativo</h1>
          <p class="texto-suave">Resumen de ventas e inventario · <span id="periodo"><?= e($est['periodo']) ?></span></p>
        </div>
        <span class="en-vivo"><span class="punto-vivo"></span> En vivo · <span id="actualizado">actualizado a las <?= e($est['generado']) ?></span></span>
      </div>

      <!-- Tarjetas con los números principales -->
      <div class="rejilla-4">
        <div class="tarjeta indicador">
          <span class="texto-suave">Ingresos del mes</span>
          <strong id="ingresos-mes">$<?= number_format($est['ingresosMes'], 2) ?></strong>
          <span class="chico" id="variacion-ingresos"><?= textoVariacion($est) ?></span>
        </div>
        <div class="tarjeta indicador">
          <span class="texto-suave">Pedidos del mes</span>
          <strong id="pedidos-mes"><?= $est['pedidosMes'] ?></strong>
          <span class="chico texto-suave" id="diferencia-pedidos"><?= textoDiferenciaPedidos($est) ?></span>
        </div>
        <div class="tarjeta indicador">
          <span class="texto-suave">Clientes registrados</span>
          <strong id="clientes"><?= $est['clientes'] ?></strong>
          <span class="chico texto-suave" id="clientes-nuevos">+<?= $est['clientesNuevos'] ?> nuevos este mes</span>
        </div>
        <div class="tarjeta indicador">
          <span class="texto-suave">Películas publicadas</span>
          <strong id="peliculas-publicadas"><?= $est['peliculasPublicadas'] ?></strong>
          <span class="texto-suave chico" id="peliculas-registradas">de <?= $est['peliculasRegistradas'] ?> registradas</span>
        </div>
      </div>

      <!-- Gráfica de barras y top 5 -->
      <div class="panel-fila">

        <div class="tarjeta">
          <div class="tarjeta-titulo">
            <h3>Ingresos por mes</h3>
            <span class="texto-suave chico">Últimos 6 meses · MXN</span>
          </div>
          <!-- Cada barra usa height en %: el mes más alto ocupa el 85% -->
          <div class="grafica" id="grafica">
            <?php foreach ($est['ingresosPorMes'] as $m): ?>
              <div class="barra-col">
                <span><?= cantidadCorta($m['ingresos']) ?></span>
                <div class="barra<?= $m['actual'] ? ' barra-actual' : '' ?>" style="height: <?= $m['altura'] ?>%;"></div>
                <small><?= $m['mes'] ?></small>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="tarjeta">
          <div class="tarjeta-titulo">
            <h3>Top 5 películas más vendidas</h3>
            <span class="texto-suave chico">Este mes</span>
          </div>
          <div class="top" id="top-peliculas">
            <?php if (count($est['topPeliculas']) === 0): ?>
              <p class="texto-suave">Todavía no hay ventas este mes.</p>
            <?php endif; ?>
            <?php foreach ($est['topPeliculas'] as $i => $t): ?>
              <div class="top-fila"><span><?= $i + 1 ?>. <?= e($t['titulo']) ?> · <?= e($t['formato']) ?></span><strong><?= $t['unidades'] ?> uds.</strong></div>
              <div class="progreso"><div style="width: <?= $t['ancho'] ?>%;"></div></div>
            <?php endforeach; ?>
          </div>
        </div>

      </div>

      <!-- Poca existencia y ventas por formato -->
      <div class="panel-fila">

        <div class="tarjeta sin-relleno">
          <div class="tarjeta-titulo con-relleno">
            <h3>Películas con poca existencia</h3>
            <span class="texto-suave chico">5 unidades o menos</span>
          </div>
          <table class="tabla tabla-admin">
            <thead>
              <tr><th>Película</th><th>Formato</th><th>Existencia</th><th>Acción</th></tr>
            </thead>
            <tbody id="poca-existencia">
              <?php if (count($est['pocaExistencia']) === 0): ?>
                <tr><td colspan="4" class="texto-suave">Todas las películas publicadas tienen existencia suficiente.</td></tr>
              <?php endif; ?>
              <?php foreach ($est['pocaExistencia'] as $p): ?>
                <tr>
                  <td><?= e($p['titulo']) ?></td>
                  <td><?= e($p['formato']) ?></td>
                  <td><span class="insignia <?= $p['existencia'] === 0 ? 'roja' : 'amarilla' ?>"><?= $p['existencia'] === 0 ? 'Agotada' : $p['existencia'] . ' uds.' ?></span></td>
                  <td><a href="pelicula_form.php?id=<?= $p['id_pelicula'] ?>">Editar</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="tarjeta">
          <div class="tarjeta-titulo">
            <h3>Ventas por formato</h3>
            <span class="texto-suave chico">% de unidades del mes</span>
          </div>
          <!-- Una sola barra dividida: cada parte tiene el ancho de su porcentaje -->
          <div class="barra-formatos" id="barra-formatos">
            <?php foreach ($est['ventasPorFormato'] as $i => $f): ?>
              <div class="color-<?= $i % 4 ?>" style="width: <?= $f['porcentaje'] ?>%;"></div>
            <?php endforeach; ?>
          </div>
          <div id="leyenda-formatos">
            <?php if (count($est['ventasPorFormato']) === 0): ?>
              <p class="texto-suave">Todavía no hay ventas este mes.</p>
            <?php endif; ?>
            <?php foreach ($est['ventasPorFormato'] as $i => $f): ?>
              <div class="leyenda"><span><i class="color-<?= $i % 4 ?>"></i> <?= e($f['formato']) ?></span><strong><?= $f['porcentaje'] ?>%</strong></div>
            <?php endforeach; ?>
          </div>
        </div>

      </div>
    </main>
  </div>

</div>

<!-- Primer JavaScript del proyecto: actualiza el panel cada 10 segundos -->
<script src="../js/panel.js"></script>
</body>
</html>
