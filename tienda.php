<?php
// Cine+ · Tienda (catálogo de películas)
$titulo = 'Tienda';
$pagina = 'tienda';   // enlace del menú que se marca como activo
include __DIR__ . '/includes/encabezado.php';
?>

<section class="seccion">
  <div class="contenedor">

    <!-- Título, buscador y botón del carrito -->
    <div class="tienda-arriba">
      <div>
        <h1>Tienda de películas físicas</h1>
        <p class="texto-suave">Colecciona el cine en la mejor calidad: 4K Ultra HD, Blu-ray y DVD.</p>
      </div>
      <div class="tienda-acciones">
        <input class="campo" type="search" placeholder="Buscar título o director...">
        <a class="btn btn-secundario" href="carrito.php">Carrito (3)</a>
      </div>
    </div>

    <div class="tienda">

      <!-- Filtros (por ahora solo diseño, no filtran) -->
      <aside class="tarjeta filtros">
        <h3>Filtros</h3>

        <h5>Formato</h5>
        <label><input type="checkbox" checked> 4K Ultra HD</label>
        <label><input type="checkbox" checked> Blu-ray</label>
        <label><input type="checkbox" checked> DVD</label>
        <label><input type="checkbox"> Steelbook</label>

        <h5>Género</h5>
        <label><input type="checkbox"> Acción</label>
        <label><input type="checkbox"> Ciencia ficción</label>
        <label><input type="checkbox"> Clásicos</label>
        <label><input type="checkbox"> Drama</label>
        <label><input type="checkbox"> Terror</label>

        <h5>Disponibilidad</h5>
        <label><input type="radio" name="disponible" checked> En stock</label>
        <label><input type="radio" name="disponible"> Preventa</label>

        <button class="btn btn-secundario btn-bloque">Limpiar filtros</button>
      </aside>

      <!-- Tarjetas de películas (datos de ejemplo escritos a mano) -->
      <div class="peliculas">

      <div class="pelicula">
        <div class="poster"><span class="formato f-4k">4K</span></div>
        <div class="pelicula-info">
          <h4>Oppenheimer</h4>
          <p class="texto-suave chico">Christopher Nolan (2023)</p>
          <div class="pelicula-precio">
            <strong>$349.00</strong>
            <span class="existencia ok">En stock</span>
          </div>
          <a class="btn btn-primario btn-bloque" href="carrito.php">Agregar al carrito</a>
        </div>
      </div>

      <div class="pelicula">
        <div class="poster"><span class="formato f-bluray">Blu-ray</span></div>
        <div class="pelicula-info">
          <h4>Blade Runner 2049</h4>
          <p class="texto-suave chico">Denis Villeneuve (2017)</p>
          <div class="pelicula-precio">
            <strong>$219.00</strong>
            <span class="existencia ok">En stock</span>
          </div>
          <a class="btn btn-primario btn-bloque" href="carrito.php">Agregar al carrito</a>
        </div>
      </div>

      <div class="pelicula">
        <div class="poster"><span class="formato f-especial">Steelbook</span></div>
        <div class="pelicula-info">
          <h4>Dune: Parte Dos</h4>
          <p class="texto-suave chico">Denis Villeneuve (2024)</p>
          <div class="pelicula-precio">
            <strong>$459.00</strong>
            <span class="existencia pocos">Preventa</span>
          </div>
          <a class="btn btn-primario btn-bloque" href="carrito.php">Agregar al carrito</a>
        </div>
      </div>

      <div class="pelicula">
        <div class="poster"><span class="formato f-4k">4K</span></div>
        <div class="pelicula-info">
          <h4>El Padrino</h4>
          <p class="texto-suave chico">Francis Ford Coppola (1972)</p>
          <div class="pelicula-precio">
            <strong>$299.00</strong>
            <span class="existencia ok">En stock</span>
          </div>
          <a class="btn btn-primario btn-bloque" href="carrito.php">Agregar al carrito</a>
        </div>
      </div>

      <div class="pelicula">
        <div class="poster"><span class="formato f-bluray">Blu-ray</span></div>
        <div class="pelicula-info">
          <h4>Pulp Fiction</h4>
          <p class="texto-suave chico">Quentin Tarantino (1994)</p>
          <div class="pelicula-precio">
            <strong>$189.00</strong>
            <span class="existencia pocos">Últimas 3</span>
          </div>
          <a class="btn btn-primario btn-bloque" href="carrito.php">Agregar al carrito</a>
        </div>
      </div>

      <div class="pelicula">
        <div class="poster"><span class="formato f-4k">4K</span></div>
        <div class="pelicula-info">
          <h4>Interestelar</h4>
          <p class="texto-suave chico">Christopher Nolan (2014)</p>
          <div class="pelicula-precio">
            <strong>$329.00</strong>
            <span class="existencia ok">En stock</span>
          </div>
          <a class="btn btn-primario btn-bloque" href="carrito.php">Agregar al carrito</a>
        </div>
      </div>

      <div class="pelicula">
        <div class="poster"><span class="formato f-dvd">DVD</span></div>
        <div class="pelicula-info">
          <h4>Cinema Paradiso</h4>
          <p class="texto-suave chico">Giuseppe Tornatore (1988)</p>
          <div class="pelicula-precio">
            <strong>$99.00</strong>
            <span class="existencia ok">En stock</span>
          </div>
          <a class="btn btn-primario btn-bloque" href="carrito.php">Agregar al carrito</a>
        </div>
      </div>

      <div class="pelicula">
        <div class="poster"><span class="formato f-4k">4K</span></div>
        <div class="pelicula-info">
          <h4>Matrix</h4>
          <p class="texto-suave chico">Lana y Lilly Wachowski (1999)</p>
          <div class="pelicula-precio">
            <strong>$279.00</strong>
            <span class="existencia agotado">Agotado</span>
          </div>
          <button class="btn btn-primario btn-bloque" disabled>Agotado</button>
        </div>
      </div>

      </div>
    </div>

    <!-- Paginación (solo diseño) -->
    <div class="paginacion">
      <a class="activo" href="#">1</a>
      <a href="#">2</a>
      <a href="#">3</a>
      <span>…</span>
      <a href="#">12</a>
    </div>

  </div>
</section>

<?php include __DIR__ . '/includes/pie.php'; ?>
