<?php
/*
 * Cine+ · Carrito de compras
 * - El carrito está en $_SESSION['carrito'] como [id_pelicula => cantidad] (funciones en config/carrito.php).
 * - Acciones por POST: agregar (desde la tienda), sumar, restar, quitar y vaciar.
 *   Después de cada POST se redirige para que al recargar no se repita la acción.
 * - Los precios, títulos y existencias siempre se leen de la base, nunca del formulario.
 * - Si una película dejó de estar publicada o ya no alcanza la existencia, se quita o se ajusta con aviso.
 */
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/config/carrito.php';
$con = conectar();

const IVA = 0.16;

// ---------- Acciones del carrito (POST) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    $carrito = carrito();
    $aviso = '';

    if ($accion === 'vaciar') {
        $carrito = [];
        $aviso = 'vaciado';
    } elseif ($accion === 'quitar') {
        unset($carrito[$id]);
        $aviso = 'quitado';
    } elseif (in_array($accion, ['agregar', 'sumar', 'restar'], true)) {
        // Solo películas publicadas que existen en la base (el id del formulario no es de confianza)
        $pelicula = peliculaPublicada($con, $id);
        if ($pelicula === null || $pelicula['existencia'] < 1) {
            unset($carrito[$id]);
            $aviso = 'nodisponible';
        } else {
            $cantidad = $carrito[$id] ?? 0;
            $cantidad += $accion === 'restar' ? -1 : 1;

            if ($cantidad > $pelicula['existencia']) {
                // No se puede pedir más de lo que hay en existencia
                $cantidad = (int) $pelicula['existencia'];
                $aviso = 'limite';
            } elseif ($accion === 'agregar') {
                $aviso = 'agregado';
            }

            if ($cantidad <= 0) {
                unset($carrito[$id]);
            } else {
                $carrito[$id] = $cantidad;
            }
        }
    }

    $_SESSION['carrito'] = $carrito;
    header('Location: carrito.php' . ($aviso !== '' ? '?aviso=' . $aviso : ''));
    exit;
}

// ---------- Leer de la base las películas que están en el carrito ----------
$carrito = carrito();
$filas = [];
$cambios = [];   // avisos de películas que se quitaron o ajustaron

if (count($carrito) > 0) {
    // Un "?" por cada id del carrito: IN (?, ?, ?)
    $ids = array_keys($carrito);
    $marcas = implode(', ', array_fill(0, count($ids), '?'));
    $consulta = $con->prepare("SELECT p.id_pelicula, p.titulo, p.director, p.anio, p.precio, p.existencia,
                                      p.imagen, p.estado, f.nombre AS formato
                               FROM Pelicula p
                               JOIN Formato f ON f.id_formato = p.id_formato
                               WHERE p.id_pelicula IN ($marcas)");
    $consulta->bind_param(str_repeat('i', count($ids)), ...$ids);
    $consulta->execute();
    $encontradas = [];
    foreach ($consulta->get_result()->fetch_all(MYSQLI_ASSOC) as $p) {
        $encontradas[$p['id_pelicula']] = $p;
    }
    $consulta->close();

    foreach ($carrito as $id => $cantidad) {
        $p = $encontradas[$id] ?? null;
        if ($p === null || $p['estado'] !== 'Publicado' || $p['existencia'] < 1) {
            // Ya no se puede comprar: se quita del carrito
            $cambios[] = ($p['titulo'] ?? 'Una película') . ' ya no está disponible y se quitó de tu carrito.';
            unset($carrito[$id]);
            continue;
        }
        if ($cantidad > $p['existencia']) {
            $cambios[] = 'De ' . $p['titulo'] . ' solo quedan ' . $p['existencia'] . ', así que ajustamos la cantidad.';
            $cantidad = (int) $p['existencia'];
            $carrito[$id] = $cantidad;
        }
        $p['cantidad'] = $cantidad;
        $p['total'] = $cantidad * $p['precio'];
        $filas[] = $p;
    }
    $_SESSION['carrito'] = $carrito;
}

// ---------- Totales ----------
$articulos = array_sum(array_column($filas, 'cantidad'));
$subtotal = array_sum(array_column($filas, 'total'));
$iva = round($subtotal * IVA, 2);
$total = $subtotal + $iva;

// Mensajes después de una acción
$avisos = [
    'agregado'     => ['exito', 'La película se agregó a tu carrito.'],
    'quitado'      => ['exito', 'La película se quitó de tu carrito.'],
    'vaciado'      => ['exito', 'Tu carrito quedó vacío.'],
    'limite'       => ['error', 'Ya tienes en tu carrito todas las unidades disponibles de esa película.'],
    'nodisponible' => ['error', 'Esa película ya no está disponible.'],
];
$aviso = $avisos[$_GET['aviso'] ?? ''] ?? null;

// Para "Proceder al pago" hace falta iniciar sesión
$haySesion = usuarioActual() !== null;


$titulo = 'Carrito';
$pagina = 'tienda';   // enlace del menú que se marca como activo
include __DIR__ . '/includes/encabezado.php';
?>

<section class="seccion">
  <div class="contenedor">
    <!-- Ruta de navegación -->
    <p class="ruta chico"><a href="tienda.php">Catálogo</a> / <span>Carrito de compras</span></p>
    <h1>Carrito de compras</h1>
    <p class="texto-suave">
      <?php if ($articulos > 0): ?>
        Tienes <?= $articulos ?> artículo(s) (<?= count($filas) ?> título(s)) en tu carrito
      <?php else: ?>
        Tu carrito está vacío
      <?php endif; ?>
    </p>

    <?php if ($aviso !== null): ?>
      <p class="alerta-<?= $aviso[0] ?>"><?= e($aviso[1]) ?></p>
    <?php endif; ?>
    <?php foreach ($cambios as $cambio): ?>
      <p class="alerta-error"><?= e($cambio) ?></p>
    <?php endforeach; ?>

    <?php if (count($filas) === 0): ?>
      <!-- Carrito vacío -->
      <div class="tarjeta carrito-vacio">
        <h3>Todavía no agregas películas</h3>
        <p class="texto-suave">Explora el catálogo y encuentra tu próxima película favorita.</p>
        <a class="btn btn-primario" href="tienda.php">Ir a la tienda</a>
      </div>
    <?php else: ?>

    <div class="compra">

      <!-- Lista de productos (desde la base de datos) -->
      <div class="tarjeta">
        <div class="carrito-encabezado chico">
          <span></span><span>Producto</span><span>Precio</span><span>Cantidad</span><span>Total</span><span></span>
        </div>

        <?php foreach ($filas as $p): ?>
        <div class="carrito-fila">
          <?php if (!empty($p['imagen'])): ?>
            <img class="mini-poster" src="<?= e($p['imagen']) ?>" alt="Portada de <?= e($p['titulo']) ?>">
          <?php else: ?>
            <div class="mini-poster"></div>
          <?php endif; ?>
          <div class="carrito-producto">
            <h4><?= e($p['titulo']) ?></h4>
            <p class="texto-suave chico"><?= e($p['director']) ?><?= $p['anio'] ? ' · ' . (int) $p['anio'] : '' ?></p>
            <span class="etiqueta-formato <?= claseFormato($p['formato']) ?>"><?= e($p['formato']) ?></span>
            <p class="existencia ok">● En existencia</p>
            <?php if ($p['existencia'] <= 5): ?>
              <p class="aviso-stock chico">Solo quedan <?= $p['existencia'] ?> en existencia</p>
            <?php endif; ?>
          </div>
          <div class="carrito-precio"><span class="texto-suave chico">Precio</span>$<?= number_format($p['precio'], 2) ?></div>

          <!-- Cantidad: cada botón envía un POST con su acción (sumar o restar) -->
          <form class="cantidad" method="post" action="carrito.php">
            <input type="hidden" name="id" value="<?= $p['id_pelicula'] ?>">
            <button type="submit" name="accion" value="restar" title="Quitar una">−</button>
            <span><?= $p['cantidad'] ?></span>
            <button type="submit" name="accion" value="sumar" title="Agregar una" <?= $p['cantidad'] >= $p['existencia'] ? 'disabled' : '' ?>>+</button>
          </form>

          <div class="carrito-total"><span class="texto-suave chico">Total</span><strong>$<?= number_format($p['total'], 2) ?></strong></div>

          <form method="post" action="carrito.php">
            <input type="hidden" name="id" value="<?= $p['id_pelicula'] ?>">
            <button class="quitar" type="submit" name="accion" value="quitar" title="Quitar del carrito">✕</button>
          </form>
        </div>
        <?php endforeach; ?>

        <div class="carrito-pie">
          <a href="tienda.php">&larr; Seguir comprando</a>
          <form method="post" action="carrito.php">
            <button class="boton-texto rojo" type="submit" name="accion" value="vaciar">Vaciar carrito</button>
          </form>
        </div>
      </div>

      <!-- Resumen con los totales -->
      <div>
        <div class="tarjeta resumen">
          <h3>Resumen del pedido</h3>
          <div class="resumen-linea"><span>Subtotal (<?= $articulos ?> artículos)</span><span>$<?= number_format($subtotal, 2) ?></span></div>
          <div class="resumen-linea"><span>Envío</span><span class="texto-suave">Se calcula al finalizar</span></div>
          <div class="resumen-linea"><span>IVA (16%)</span><span>$<?= number_format($iva, 2) ?></span></div>
          <div class="resumen-total"><span>Total estimado</span><strong>$<?= number_format($total, 2) ?> MXN</strong></div>
          <?php if ($haySesion): ?>
            <a class="btn btn-primario btn-bloque" href="finalizar.php">Proceder al pago</a>
          <?php else: ?>
            <!-- Para pagar hay que iniciar sesión; después regresa al carrito -->
            <a class="btn btn-primario btn-bloque" href="login.php?volver=carrito">Inicia sesión para pagar</a>
          <?php endif; ?>
          <p class="texto-suave chico centrado">Pago simulado · no se guardan datos de tarjeta</p>
        </div>

        <div class="tarjeta resumen">
          <h4>Envío a todo México</h4>
          <p class="texto-suave chico">Estándar gratis en compras mayores a $999. Exprés en 24/48 horas por $100.</p>
        </div>
      </div>

    </div>
    <?php endif; ?>
  </div>
</section>

<?php include __DIR__ . '/includes/pie.php'; ?>
