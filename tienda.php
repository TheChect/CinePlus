<?php
/*
 * Cine+ · Tienda (catálogo de películas)
 * - Muestra las películas de la tabla Pelicula que están en estado "Publicado"
 *   (las de Borrador nunca se ven), unidas con Genero y Formato.
 * - Si una película no tiene existencia se muestra como "Agotado" con el botón desactivado.
 * - 12 películas por página con LIMIT/OFFSET.
 * La búsqueda, los filtros y el carrito todavía son solo diseño.
 */
require_once __DIR__ . '/config/conexion.php';
$con = conectar();

const POR_PAGINA = 12;
const POCA_EXISTENCIA = 5;

// ---------- Total de películas publicadas y página actual ----------
$total = $con->query("SELECT COUNT(*) FROM Pelicula WHERE estado = 'Publicado'")->fetch_row()[0];
$paginas = max(1, (int) ceil($total / POR_PAGINA));
$paginaActual = min(max(1, (int) ($_GET['pagina'] ?? 1)), $paginas);
$desde = ($paginaActual - 1) * POR_PAGINA;

// ---------- Películas de la página (consulta preparada) ----------
// Primero las que tienen existencia; las agotadas al final
$consulta = $con->prepare("SELECT p.titulo, p.director, p.anio, p.precio, p.existencia, p.imagen,
                                  g.nombre AS genero, f.nombre AS formato
                           FROM Pelicula p
                           JOIN Genero g  ON g.id_genero  = p.id_genero
                           JOIN Formato f ON f.id_formato = p.id_formato
                           WHERE p.estado = 'Publicado'
                           ORDER BY p.existencia = 0, p.titulo
                           LIMIT ? OFFSET ?");
$limite = POR_PAGINA;
$consulta->bind_param('ii', $limite, $desde);
$consulta->execute();
$peliculas = $consulta->get_result()->fetch_all(MYSQLI_ASSOC);
$consulta->close();

// Color de la etiqueta según el nombre del formato (usa las clases f-4k, f-bluray, f-dvd...)
function claseFormato(string $formato): string
{
    $nombre = mb_strtolower($formato);
    if (str_contains($nombre, '4k')) {
        return 'f-4k';
    }
    if (str_contains($nombre, 'blu')) {
        return 'f-bluray';
    }
    if (str_contains($nombre, 'dvd')) {
        return 'f-dvd';
    }
    return 'f-especial';
}

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

      <!-- Tarjetas de películas (vienen de la tabla Pelicula) -->
      <div class="peliculas">
        <?php if (count($peliculas) === 0): ?>
          <div class="tarjeta tienda-vacia">
            <h3>Muy pronto tendremos películas aquí</h3>
            <p class="texto-suave">Estamos preparando el catálogo. Vuelve en unos días o escríbenos desde <a href="contacto.php">Contacto</a>.</p>
          </div>
        <?php endif; ?>

        <?php foreach ($peliculas as $p): ?>
          <?php
            // Texto y color de la existencia
            if ($p['existencia'] == 0) {
                $textoExistencia = 'Agotado';
                $claseExistencia = 'agotado';
            } elseif ($p['existencia'] <= POCA_EXISTENCIA) {
                $textoExistencia = 'Últimas ' . $p['existencia'];
                $claseExistencia = 'pocos';
            } else {
                $textoExistencia = 'En stock';
                $claseExistencia = 'ok';
            }
          ?>
        <div class="pelicula">
          <div class="poster">
            <?php if (!empty($p['imagen'])): ?>
              <img src="<?= e($p['imagen']) ?>" alt="Portada de <?= e($p['titulo']) ?>">
            <?php else: ?>
              <!-- Sin portada: se muestra el título sobre el fondo gris -->
              <span class="poster-sin-imagen"><?= e($p['titulo']) ?></span>
            <?php endif; ?>
            <span class="formato <?= claseFormato($p['formato']) ?>"><?= e($p['formato']) ?></span>
          </div>
          <div class="pelicula-info">
            <h4><?= e($p['titulo']) ?></h4>
            <p class="texto-suave chico">
              <?= e($p['director']) ?><?= $p['anio'] ? ' (' . (int) $p['anio'] . ')' : '' ?> · <?= e($p['genero']) ?>
            </p>
            <div class="pelicula-precio">
              <strong>$<?= number_format($p['precio'], 2) ?></strong>
              <span class="existencia <?= $claseExistencia ?>"><?= $textoExistencia ?></span>
            </div>
            <?php if ($p['existencia'] == 0): ?>
              <button class="btn btn-primario btn-bloque" type="button" disabled>Agotado</button>
            <?php else: ?>
              <!-- El carrito se conecta en un paso posterior -->
              <a class="btn btn-primario btn-bloque" href="carrito.php">Agregar al carrito</a>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Paginación: solo aparece si hay más de una página -->
    <?php if ($paginas > 1): ?>
    <div class="paginacion">
      <?php if ($paginaActual > 1): ?><a href="tienda.php?pagina=<?= $paginaActual - 1 ?>">Anterior</a><?php endif; ?>
      <?php for ($i = 1; $i <= $paginas; $i++): ?>
        <a class="<?= $i === $paginaActual ? 'activo' : '' ?>" href="tienda.php?pagina=<?= $i ?>"><?= $i ?></a>
      <?php endfor; ?>
      <?php if ($paginaActual < $paginas): ?><a href="tienda.php?pagina=<?= $paginaActual + 1 ?>">Siguiente</a><?php endif; ?>
    </div>
    <?php endif; ?>

  </div>
</section>

<?php include __DIR__ . '/includes/pie.php'; ?>
