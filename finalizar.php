<?php
// Cine+ · Finalizar compra (todavía es diseño; se conecta en el siguiente paso)
// Para pagar hay que tener sesión iniciada
require_once __DIR__ . '/config/sesion.php';
if (usuarioActual() === null) {
    header('Location: login.php?volver=carrito');
    exit;
}
$titulo = 'Finalizar compra';
$pagina = 'tienda';   // enlace del menú que se marca como activo
include __DIR__ . '/includes/encabezado.php';
?>

<section class="seccion">
  <div class="contenedor">
    <p class="ruta chico"><a href="carrito.php">Carrito</a> / <span>Finalizar compra</span></p>
    <h1>Finalizar compra</h1>

    <div class="compra">

      <div>
        <!-- Paso 1: datos de envío (solo diseño, no se envía) -->
        <form class="tarjeta formulario-compra" action="#">
          <h3><span class="paso">1</span> Información de envío</h3>

          <div class="dos-campos">
            <div>
              <label for="destinatario">Nombre completo</label>
              <input class="campo" type="text" id="destinatario" placeholder="Ej. Juan Pérez">
            </div>
            <div>
              <label for="telefono">Teléfono de contacto</label>
              <input class="campo" type="tel" id="telefono" placeholder="81 1234 5678">
            </div>
          </div>

          <label for="direccion">Dirección de entrega</label>
          <input class="campo" type="text" id="direccion" placeholder="Calle, número, colonia">

          <div class="dos-campos">
            <div>
              <label for="cp">Código postal</label>
              <input class="campo" type="text" id="cp" placeholder="66450" maxlength="5">
            </div>
            <div>
              <label for="ciudad">Ciudad</label>
              <input class="campo" type="text" id="ciudad" placeholder="San Nicolás de los Garza">
            </div>
          </div>

          <label>Método de envío</label>
          <div class="dos-campos">
            <!-- Cada opción es un radio dentro de una caja -->
            <label class="opcion-envio">
              <input type="radio" name="envio" checked>
              <span><strong>Envío estándar</strong><br><span class="texto-suave chico">Entrega en 3 a 5 días hábiles</span></span>
              <span class="precio-envio">Gratis</span>
            </label>
            <label class="opcion-envio">
              <input type="radio" name="envio">
              <span><strong>Envío exprés</strong><br><span class="texto-suave chico">Entrega en 24/48 horas</span></span>
              <span class="precio-envio">$100.00</span>
            </label>
          </div>
        </form>

        <!-- Paso 2: método de pago (simulado) -->
        <form class="tarjeta formulario-compra" action="#">
          <h3><span class="paso">2</span> Método de pago</h3>

          <label for="metodo">Forma de pago</label>
          <select class="campo" id="metodo">
            <option>Tarjeta de crédito o débito</option>
            <option>Pago en OXXO</option>
            <option>Transferencia</option>
          </select>

          <label for="tarjeta">Número de tarjeta</label>
          <input class="campo" type="text" id="tarjeta" placeholder="0000 0000 0000 0000">

          <div class="dos-campos">
            <div>
              <label for="vence">Fecha de expiración</label>
              <input class="campo" type="text" id="vence" placeholder="MM / AA">
            </div>
            <div>
              <label for="cvv">CVV</label>
              <input class="campo" type="text" id="cvv" placeholder="123" maxlength="3">
            </div>
          </div>

          <p class="nota-segura chico">Pago simulado para el proyecto: Cine+ no guarda los datos de tu tarjeta.</p>
        </form>
      </div>

      <!-- Resumen del pedido -->
      <div class="tarjeta resumen">
        <h3>Resumen del pedido</h3>

        <div class="resumen-producto">
          <div class="mini-poster chico-poster"></div>
          <div><strong>Oppenheimer (2023)</strong><br><span class="texto-suave chico">4K Ultra HD · Cant. 1</span></div>
          <span>$349.00</span>
        </div>
        <div class="resumen-producto">
          <div class="mini-poster chico-poster"></div>
          <div><strong>Dune: Parte Dos (2024)</strong><br><span class="texto-suave chico">Steelbook · Cant. 2</span></div>
          <span>$918.00</span>
        </div>
        <div class="resumen-producto">
          <div class="mini-poster chico-poster"></div>
          <div><strong>Cinema Paradiso (1988)</strong><br><span class="texto-suave chico">DVD · Cant. 1</span></div>
          <span>$99.00</span>
        </div>

        <div class="resumen-linea"><span>Subtotal (4 artículos)</span><span>$1,366.00</span></div>
        <div class="resumen-linea"><span>Envío</span><span class="verde">Gratis</span></div>
        <div class="resumen-linea"><span>IVA (16%)</span><span>$218.56</span></div>
        <div class="resumen-total"><span>Total</span><strong>$1,584.56 MXN</strong></div>

        <button class="btn btn-primario btn-bloque" type="button">Confirmar y pagar</button>
        <p class="texto-suave chico centrado">Pago 100% simulado</p>
      </div>

    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/pie.php'; ?>
