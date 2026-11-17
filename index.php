<?php
// Cine+ · Página de inicio "Por qué elegir Cine+"
$titulo = 'Inicio';
$pagina = 'inicio';   // enlace del menú que se marca como activo
include __DIR__ . '/includes/encabezado.php';
?>

<!-- Portada: título principal -->
<section class="portada">
  <div class="contenedor">
    <span class="etiqueta">Tienda de cine físico</span>
    <h1>Por qué elegir Cine+</h1>
    <p class="texto-suave">
      La experiencia definitiva para los amantes del cine en formato físico.
      Calidad sin compromisos, directamente en tu colección.
    </p>
    <div class="portada-botones">
      <a class="btn btn-primario" href="tienda.php">Ver catálogo</a>
      <a class="btn btn-secundario" href="acerca.php">Conócenos</a>
    </div>
  </div>
</section>

<!-- Ventajas de la tienda (6 tarjetas) -->
<section class="seccion">
  <div class="contenedor rejilla-3">

    <div class="tarjeta">
      <div class="icono"></div>
      <h3>Catálogo amplio</h3>
      <p class="texto-suave">Cientos de títulos en DVD, Blu-ray y 4K, desde clásicos hasta estrenos recientes.</p>
    </div>

    <div class="tarjeta">
      <div class="icono"></div>
      <h3>Formatos de calidad</h3>
      <p class="texto-suave">Discos originales con la mejor imagen y sonido, sin depender de tu conexión a internet.</p>
    </div>

    <div class="tarjeta">
      <div class="icono"></div>
      <h3>Envío a todo el país</h3>
      <p class="texto-suave">Enviamos tus películas bien empacadas a cualquier estado de México.</p>
    </div>

    <div class="tarjeta">
      <div class="icono"></div>
      <h3>Recomendaciones</h3>
      <p class="texto-suave">Te sugerimos películas según los géneros que más te gustan.</p>
    </div>

    <div class="tarjeta">
      <div class="icono"></div>
      <h3>Ediciones especiales</h3>
      <p class="texto-suave">Steelbooks, ediciones de colección y versiones del director para coleccionistas.</p>
    </div>

    <div class="tarjeta">
      <div class="icono"></div>
      <h3>Garantía de devolución</h3>
      <p class="texto-suave">Si tu disco llega dañado, tienes 30 días para cambiarlo sin costo.</p>
    </div>

  </div>
</section>

<!-- Tabla comparativa de formatos -->
<section class="seccion seccion-oscura">
  <div class="contenedor">
    <h2 class="centrado">Comparativa de formatos</h2>
    <p class="texto-suave centrado">Te ayudamos a elegir el formato que mejor se adapta a tu equipo.</p>

    <div class="tabla-scroll">
      <table class="tabla">
        <thead>
          <tr>
            <th>Característica</th>
            <th>DVD</th>
            <th>Blu-ray</th>
            <th class="destacado">4K Ultra HD</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>Resolución</td>
            <td>480p</td>
            <td>1080p (Full HD)</td>
            <td class="destacado">2160p (4K)</td>
          </tr>
          <tr>
            <td>Capacidad del disco</td>
            <td>4.7 a 8.5 GB</td>
            <td>25 a 50 GB</td>
            <td class="destacado">66 a 100 GB</td>
          </tr>
          <tr>
            <td>Calidad de audio</td>
            <td>Dolby Digital 5.1</td>
            <td>DTS-HD / Dolby TrueHD</td>
            <td class="destacado">Dolby Atmos</td>
          </tr>
          <tr>
            <td>Reproductor necesario</td>
            <td>Cualquier reproductor</td>
            <td>Reproductor Blu-ray o consola</td>
            <td class="destacado">Reproductor 4K</td>
          </tr>
          <tr>
            <td>Ideal para</td>
            <td>Precio bajo y colecciones retro</td>
            <td>Buena calidad en casa</td>
            <td class="destacado">Pantallas grandes y cinéfilos</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/pie.php'; ?>
