<?php
// Cine+ · Página de Contacto
$titulo = 'Contacto';
$pagina = 'contacto';   // enlace del menú que se marca como activo
include __DIR__ . '/includes/encabezado.php';
?>

<!-- Título de la página -->
<section class="portada">
  <div class="contenedor">
    <span class="etiqueta">Estamos para ayudarte</span>
    <h1>Contáctanos</h1>
    <p class="texto-suave">
      ¿Tienes dudas sobre un pedido, un formato o una edición especial?
      Escríbenos o llámanos y con gusto te atendemos.
    </p>
  </div>
</section>

<!-- Datos de contacto (4 tarjetas) -->
<section class="contenedor rejilla-4">
  <div class="tarjeta">
    <div class="icono"></div>
    <span class="texto-suave">Correo</span>
    <h3 class="dato">contacto@cineplus.com</h3>
    <p class="texto-suave chico">Respondemos en menos de 24 horas.</p>
  </div>
  <div class="tarjeta">
    <div class="icono"></div>
    <span class="texto-suave">Teléfono</span>
    <h3 class="dato">81 1234 5678</h3>
    <p class="texto-suave chico">Lunes a sábado, de 9:00 a 19:00.</p>
  </div>
  <div class="tarjeta">
    <div class="icono"></div>
    <span class="texto-suave">Dirección</span>
    <h3 class="dato">Av. Universidad s/n, Cd. Universitaria</h3>
    <p class="texto-suave chico">San Nicolás de los Garza, N. L.</p>
  </div>
  <div class="tarjeta">
    <div class="icono"></div>
    <span class="texto-suave">Horario</span>
    <h3 class="dato">Lun–Sáb 9:00–19:00</h3>
    <p class="texto-suave chico">Domingo: solo pedidos en línea.</p>
  </div>
</section>

<!-- Mapa y preguntas frecuentes -->
<section class="seccion">
  <div class="contenedor dos-columnas arriba">

    <div class="tarjeta">
      <h3>Ubicación</h3>
      <!-- Mapa de ejemplo hecho con CSS (líneas como calles y un punto) -->
      <div class="mapa">
        <span class="mapa-punto"></span>
      </div>
      <p class="texto-suave chico">Cine+ opera en línea; esta dirección es para devoluciones y atención presencial con cita.</p>
    </div>

    <div class="tarjeta">
      <h3>Preguntas frecuentes</h3>
      <div class="pregunta">
        <h4>¿Cuánto tarda mi pedido?</h4>
        <p class="texto-suave chico">El envío estándar llega en 3 a 5 días hábiles y el exprés en 1 a 2 días.</p>
      </div>
      <div class="pregunta">
        <h4>¿Qué diferencia hay entre DVD, Blu-ray y 4K?</h4>
        <p class="texto-suave chico">La resolución y el audio. En la página de inicio hay una tabla comparativa de los tres formatos.</p>
      </div>
      <div class="pregunta">
        <h4>¿Puedo devolver una película?</h4>
        <p class="texto-suave chico">Sí, tienes 30 días si el disco viene dañado o no es la edición que pediste.</p>
      </div>
      <div class="pregunta">
        <h4>¿Necesito una cuenta para comprar?</h4>
        <p class="texto-suave chico">Puedes ver el catálogo sin cuenta, pero para usar el carrito y comprar debes registrarte.</p>
      </div>
    </div>

  </div>
</section>

<!-- Invitación a escribir un correo -->
<section class="contenedor">
  <div class="aviso">
    <div>
      <h3>¿Prefieres escribirnos?</h3>
      <p>El botón abre tu aplicación de correo con nuestra dirección lista.</p>
    </div>
    <!-- mailto: abre el programa de correo de la computadora -->
    <a class="btn btn-primario" href="mailto:contacto@cineplus.com">Enviar correo</a>
  </div>
</section>

<?php include __DIR__ . '/includes/pie.php'; ?>
