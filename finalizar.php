<?php
/*
 * Cine+ · Finalizar compra (compra simulada)
 * - Requiere sesión iniciada y un carrito con películas.
 * - Pide los datos de envío y el método de pago. No se piden ni se guardan datos de tarjeta.
 * - Al confirmar, en UNA transacción:
 *     1. vuelve a leer cada película con SELECT ... FOR UPDATE (la bloquea mientras se compra),
 *     2. revisa que siga publicada y con existencia suficiente,
 *     3. guarda Pedido, Detalle_Pedido y Envio, y descuenta la existencia.
 *   Si algo falla se deshace todo (rollback) y el carrito se conserva.
 * - finalizar.php?pedido=ID muestra la confirmación, solo si el pedido es de quien tiene la sesión.
 */
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/config/carrito.php';

$sesion = usuarioActual();
if ($sesion === null) {
    header('Location: login.php?volver=carrito');
    exit;
}
$con = conectar();

const IVA = 0.16;
const ENVIO_GRATIS_DESDE = 999;   // envío estándar gratis desde este subtotal
const COSTO_ESTANDAR = 79;
const COSTO_EXPRES = 100;
const METODOS_PAGO = ['Tarjeta (simulada)', 'Pago en OXXO', 'Transferencia'];

// Error "esperado" de la compra (por ejemplo, ya no hay existencia): su mensaje sí se le muestra a la persona.
// Cualquier otro error (de la base de datos) se muestra con un mensaje general.
class CompraException extends Exception
{
}

// Costo de envío según el método y el subtotal (siempre se calcula aquí, nunca viene del formulario)
function costoEnvio(string $metodo, float $subtotal): float
{
    if ($metodo === 'Exprés') {
        return COSTO_EXPRES;
    }
    return $subtotal >= ENVIO_GRATIS_DESDE ? 0 : COSTO_ESTANDAR;
}

// ===================== Confirmación de un pedido =====================
if (isset($_GET['pedido'])) {
    $idPedido = (int) $_GET['pedido'];
    // Solo se muestra si el pedido es del usuario con sesión (id_usuario = ?)
    $buscar = $con->prepare('SELECT p.id_pedido, p.fecha, p.subtotal, p.impuestos, p.total, p.metodo_pago,
                                    e.destinatario, e.direccion, e.ciudad, e.codigo_postal, e.metodo, e.costo
                             FROM Pedido p JOIN Envio e ON e.id_pedido = p.id_pedido
                             WHERE p.id_pedido = ? AND p.id_usuario = ?');
    $buscar->bind_param('ii', $idPedido, $sesion['id']);
    $buscar->execute();
    $pedido = $buscar->get_result()->fetch_assoc();
    $buscar->close();

    if ($pedido === null) {
        header('Location: index.php');
        exit;
    }

    $detalle = $con->prepare('SELECT pe.titulo, f.nombre AS formato, d.cantidad, d.precio_unitario
                              FROM Detalle_Pedido d
                              JOIN Pelicula pe ON pe.id_pelicula = d.id_pelicula
                              JOIN Formato f ON f.id_formato = pe.id_formato
                              WHERE d.id_pedido = ?');
    $detalle->bind_param('i', $idPedido);
    $detalle->execute();
    $lineas = $detalle->get_result()->fetch_all(MYSQLI_ASSOC);
    $detalle->close();

    $titulo = 'Pedido confirmado';
    $pagina = 'tienda';
    include __DIR__ . '/includes/encabezado.php';
    ?>
<section class="seccion">
  <div class="contenedor confirmacion">
    <div class="tarjeta">
      <div class="icono-ok">✓</div>
      <h1>¡Gracias por tu compra!</h1>
      <p class="texto-suave">Tu pedido <strong>#<?= $pedido['id_pedido'] ?></strong> quedó registrado. Esta es una compra simulada: no se hizo ningún cobro.</p>

      <div class="resumen">
        <?php foreach ($lineas as $l): ?>
          <div class="resumen-linea">
            <span><?= e($l['titulo']) ?> (<?= e($l['formato']) ?>) × <?= $l['cantidad'] ?></span>
            <span>$<?= number_format($l['cantidad'] * $l['precio_unitario'], 2) ?></span>
          </div>
        <?php endforeach; ?>
        <div class="resumen-linea"><span>Subtotal</span><span>$<?= number_format($pedido['subtotal'], 2) ?></span></div>
        <div class="resumen-linea"><span>Envío <?= e($pedido['metodo']) ?></span><span><?= $pedido['costo'] > 0 ? '$' . number_format($pedido['costo'], 2) : 'Gratis' ?></span></div>
        <div class="resumen-linea"><span>IVA (16%)</span><span>$<?= number_format($pedido['impuestos'], 2) ?></span></div>
        <div class="resumen-total"><span>Total</span><strong>$<?= number_format($pedido['total'], 2) ?> MXN</strong></div>
      </div>

      <p class="texto-suave chico">
        Se enviará a <?= e($pedido['destinatario']) ?>, <?= e($pedido['direccion']) ?>, <?= e($pedido['ciudad']) ?>, C.P. <?= e($pedido['codigo_postal']) ?>.
        Método de pago: <?= e($pedido['metodo_pago']) ?>.
      </p>
      <a class="btn btn-primario" href="tienda.php">Seguir comprando</a>
    </div>
  </div>
</section>
<?php
    include __DIR__ . '/includes/pie.php';
    exit;
}

// ===================== Formulario de compra =====================
[$filas, $cambios] = leerCarrito($con);
if (count($filas) === 0) {
    header('Location: carrito.php');
    exit;
}

// Datos por defecto tomados de la cuenta (nombre y teléfono)
$cuenta = $con->prepare('SELECT nombre, telefono FROM Usuario WHERE id_usuario = ?');
$cuenta->bind_param('i', $sesion['id']);
$cuenta->execute();
$usuario = $cuenta->get_result()->fetch_assoc();
$cuenta->close();

$datos = [
    'destinatario' => $usuario['nombre'] ?? '',
    'telefono' => $usuario['telefono'] ?? '',
    'direccion' => '',
    'codigo_postal' => '',
    'ciudad' => '',
    'metodo' => 'Estándar',
    'metodo_pago' => METODOS_PAGO[0],
];
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($datos as $campo => $valor) {
        $datos[$campo] = trim($_POST[$campo] ?? '');
    }

    // ---------- Validar los datos de envío ----------
    if ($datos['destinatario'] === '' || mb_strlen($datos['destinatario']) > 100) {
        $errores[] = 'Escribe el nombre de quien recibe (máximo 100 caracteres).';
    }
    if (!preg_match('/^[0-9]{10}$/', $datos['telefono'])) {
        $errores[] = 'El teléfono debe tener 10 dígitos (solo números).';
    }
    if ($datos['direccion'] === '' || mb_strlen($datos['direccion']) > 200) {
        $errores[] = 'Escribe la dirección de entrega (máximo 200 caracteres).';
    }
    if (!preg_match('/^[0-9]{5}$/', $datos['codigo_postal'])) {
        $errores[] = 'El código postal debe tener 5 dígitos.';
    }
    if ($datos['ciudad'] === '' || mb_strlen($datos['ciudad']) > 80) {
        $errores[] = 'Escribe la ciudad (máximo 80 caracteres).';
    }
    if (!in_array($datos['metodo'], ['Estándar', 'Exprés'], true)) {
        $errores[] = 'Elige un método de envío.';
    }
    if (!in_array($datos['metodo_pago'], METODOS_PAGO, true)) {
        $errores[] = 'Elige un método de pago.';
    }
    if (count($cambios) > 0) {
        // El carrito cambió (algo se agotó o se quitó): que la persona revise antes de pagar
        $errores[] = 'Tu carrito cambió. Revisa el resumen antes de confirmar.';
    }

    // ---------- Guardar el pedido en una transacción ----------
    if (count($errores) === 0) {
        $con->begin_transaction();
        try {
            $leer = $con->prepare("SELECT titulo, precio, existencia, estado FROM Pelicula WHERE id_pelicula = ? FOR UPDATE");
            $compra = [];
            $subtotal = 0;
            foreach (carrito() as $idPelicula => $cantidad) {
                $leer->bind_param('i', $idPelicula);
                $leer->execute();
                $p = $leer->get_result()->fetch_assoc();
                if ($p === null || $p['estado'] !== 'Publicado' || $p['existencia'] < $cantidad) {
                    // Lanzar una excepción manda directo al rollback
                    throw new CompraException('Ya no hay suficientes unidades de "' . ($p['titulo'] ?? 'una película') . '". Revisa tu carrito.');
                }
                // El precio sale de la base, nunca del formulario
                $compra[] = [$idPelicula, $cantidad, (float) $p['precio']];
                $subtotal += $cantidad * $p['precio'];
            }
            $leer->close();

            $costoEnvio = costoEnvio($datos['metodo'], $subtotal);
            $impuestos = round($subtotal * IVA, 2);
            $total = $subtotal + $impuestos + $costoEnvio;

            // 1) Pedido
            $pedido = $con->prepare('INSERT INTO Pedido (id_usuario, subtotal, impuestos, total, metodo_pago) VALUES (?, ?, ?, ?, ?)');
            $pedido->bind_param('iddds', $sesion['id'], $subtotal, $impuestos, $total, $datos['metodo_pago']);
            $pedido->execute();
            $idPedido = $con->insert_id;
            $pedido->close();

            // 2) Detalle y existencia de cada película
            $detalle = $con->prepare('INSERT INTO Detalle_Pedido (id_pedido, id_pelicula, cantidad, precio_unitario) VALUES (?, ?, ?, ?)');
            $descontar = $con->prepare('UPDATE Pelicula SET existencia = existencia - ? WHERE id_pelicula = ? AND existencia >= ?');
            foreach ($compra as [$idPelicula, $cantidad, $precio]) {
                $detalle->bind_param('iiid', $idPedido, $idPelicula, $cantidad, $precio);
                $detalle->execute();
                $descontar->bind_param('iii', $cantidad, $idPelicula, $cantidad);
                $descontar->execute();
                if ($descontar->affected_rows !== 1) {
                    throw new CompraException('No se pudo apartar la existencia. Intenta de nuevo.');
                }
            }
            $detalle->close();
            $descontar->close();

            // 3) Envío
            $envio = $con->prepare('INSERT INTO Envio (id_pedido, destinatario, telefono, direccion, codigo_postal, ciudad, metodo, costo)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $envio->bind_param('issssssd', $idPedido, $datos['destinatario'], $datos['telefono'], $datos['direccion'],
                $datos['codigo_postal'], $datos['ciudad'], $datos['metodo'], $costoEnvio);
            $envio->execute();
            $envio->close();

            $con->commit();

            // Compra lista: se vacía el carrito y se muestra la confirmación
            $_SESSION['carrito'] = [];
            header('Location: finalizar.php?pedido=' . $idPedido);
            exit;
        } catch (Throwable $ex) {
            // Se deshace todo lo que se haya guardado; el carrito sigue igual
            $con->rollback();
            $errores[] = $ex instanceof CompraException ? $ex->getMessage() : 'No se pudo completar la compra. Intenta de nuevo.';
            [$filas, $cambios] = leerCarrito($con);
            if (count($filas) === 0) {
                header('Location: carrito.php');
                exit;
            }
        }
    }
}

// ---------- Totales para el resumen ----------
$articulos = array_sum(array_column($filas, 'cantidad'));
$subtotal = array_sum(array_column($filas, 'total'));
$impuestos = round($subtotal * IVA, 2);
$costoEnvio = costoEnvio($datos['metodo'], $subtotal);
$total = $subtotal + $impuestos + $costoEnvio;

$titulo = 'Finalizar compra';
$pagina = 'tienda';
include __DIR__ . '/includes/encabezado.php';
?>

<section class="seccion">
  <div class="contenedor">
    <p class="ruta chico"><a href="carrito.php">Carrito</a> / <span>Finalizar compra</span></p>
    <h1>Finalizar compra</h1>

    <?php foreach ($cambios as $cambio): ?>
      <p class="alerta-error"><?= e($cambio) ?></p>
    <?php endforeach; ?>
    <?php if (count($errores) > 0): ?>
      <div class="alerta-error lista-errores">
        <ul>
          <?php foreach ($errores as $error): ?>
            <li><?= e($error) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <!-- Un solo formulario: los campos y el botón de la derecha se unen a él con form="form-compra" -->
    <form id="form-compra" method="post" action="finalizar.php"></form>

    <div class="compra">

      <div>
        <!-- Paso 1: datos de envío -->
        <div class="tarjeta formulario-compra">
          <h3><span class="paso">1</span> Información de envío</h3>

          <div class="dos-campos">
            <div>
              <label for="destinatario">Nombre completo</label>
              <input class="campo" type="text" id="destinatario" name="destinatario" form="form-compra" maxlength="100" required value="<?= e($datos['destinatario']) ?>">
            </div>
            <div>
              <label for="telefono">Teléfono de contacto</label>
              <input class="campo" type="tel" id="telefono" name="telefono" form="form-compra" maxlength="10" required placeholder="10 dígitos" value="<?= e($datos['telefono']) ?>">
            </div>
          </div>

          <label for="direccion">Dirección de entrega</label>
          <input class="campo" type="text" id="direccion" name="direccion" form="form-compra" maxlength="200" required placeholder="Calle, número, colonia" value="<?= e($datos['direccion']) ?>">

          <div class="dos-campos">
            <div>
              <label for="cp">Código postal</label>
              <input class="campo" type="text" id="cp" name="codigo_postal" form="form-compra" maxlength="5" required placeholder="66450" value="<?= e($datos['codigo_postal']) ?>">
            </div>
            <div>
              <label for="ciudad">Ciudad</label>
              <input class="campo" type="text" id="ciudad" name="ciudad" form="form-compra" maxlength="80" required placeholder="San Nicolás de los Garza" value="<?= e($datos['ciudad']) ?>">
            </div>
          </div>

          <label>Método de envío</label>
          <div class="dos-campos">
            <label class="opcion-envio">
              <input type="radio" name="metodo" value="Estándar" form="form-compra" <?= $datos['metodo'] !== 'Exprés' ? 'checked' : '' ?>>
              <span><strong>Envío estándar</strong><br><span class="texto-suave chico">Entrega en 3 a 5 días hábiles</span></span>
              <span class="precio-envio"><?= $subtotal >= ENVIO_GRATIS_DESDE ? 'Gratis' : '$' . number_format(COSTO_ESTANDAR, 2) ?></span>
            </label>
            <label class="opcion-envio">
              <input type="radio" name="metodo" value="Exprés" form="form-compra" <?= $datos['metodo'] === 'Exprés' ? 'checked' : '' ?>>
              <span><strong>Envío exprés</strong><br><span class="texto-suave chico">Entrega en 24/48 horas</span></span>
              <span class="precio-envio">$<?= number_format(COSTO_EXPRES, 2) ?></span>
            </label>
          </div>
        </div>

        <!-- Paso 2: método de pago (simulado: no se piden datos de tarjeta) -->
        <div class="tarjeta formulario-compra">
          <h3><span class="paso">2</span> Método de pago</h3>
          <label for="metodo_pago">Forma de pago</label>
          <select class="campo" id="metodo_pago" name="metodo_pago" form="form-compra">
            <?php foreach (METODOS_PAGO as $metodoPago): ?>
              <option value="<?= e($metodoPago) ?>" <?= $datos['metodo_pago'] === $metodoPago ? 'selected' : '' ?>><?= e($metodoPago) ?></option>
            <?php endforeach; ?>
          </select>
          <p class="nota-segura chico">Pago simulado para el proyecto: no se piden ni se guardan datos de tarjeta, y no se hace ningún cobro.</p>
        </div>
      </div>

      <!-- Resumen del pedido (leído de la base) -->
      <div class="tarjeta resumen">
        <h3>Resumen del pedido</h3>

        <?php foreach ($filas as $p): ?>
        <div class="resumen-producto">
          <?php if (!empty($p['imagen'])): ?>
            <img class="mini-poster chico-poster" src="<?= e($p['imagen']) ?>" alt="">
          <?php else: ?>
            <div class="mini-poster chico-poster"></div>
          <?php endif; ?>
          <div><strong><?= e($p['titulo']) ?><?= $p['anio'] ? ' (' . (int) $p['anio'] . ')' : '' ?></strong><br><span class="texto-suave chico"><?= e($p['formato']) ?> · Cant. <?= $p['cantidad'] ?></span></div>
          <span>$<?= number_format($p['total'], 2) ?></span>
        </div>
        <?php endforeach; ?>

        <div class="resumen-linea"><span>Subtotal (<?= $articulos ?> artículos)</span><span>$<?= number_format($subtotal, 2) ?></span></div>
        <div class="resumen-linea"><span>Envío <?= e($datos['metodo']) ?></span><span><?= $costoEnvio > 0 ? '$' . number_format($costoEnvio, 2) : 'Gratis' ?></span></div>
        <div class="resumen-linea"><span>IVA (16%)</span><span>$<?= number_format($impuestos, 2) ?></span></div>
        <div class="resumen-total"><span>Total</span><strong>$<?= number_format($total, 2) ?> MXN</strong></div>

        <button class="btn btn-primario btn-bloque" type="submit" form="form-compra">Confirmar y pagar</button>
        <p class="texto-suave chico centrado">Si eliges envío exprés, el total se ajusta al confirmar.</p>
      </div>

    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/pie.php'; ?>
