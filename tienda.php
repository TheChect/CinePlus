<?php
/*
 * Cine+ · Tienda (catálogo de películas)
 * - Muestra las películas de la tabla Pelicula que están en estado "Publicado"
 *   (las de Borrador nunca se ven), unidas con Genero y Formato.
 * - Si una película no tiene existencia se muestra como "Agotado" con el botón desactivado.
 * - 12 películas por página con LIMIT/OFFSET.
 * - Búsqueda (título o director), filtros de formato, género y disponibilidad, y orden:
 *   todo llega por GET y se aplica con una consulta preparada.
 * - "Agregar al carrito" manda un POST a carrito.php.
 */
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/config/carrito.php';
$con = conectar();

const POR_PAGINA = 12;
const POCA_EXISTENCIA = 5;

// Opciones de orden: valor del formulario => ORDER BY (nunca se pone texto del usuario en el SQL)
const ORDENES = [
    'titulo'      => ['Título (A-Z)', 'p.titulo'],
    'nuevas'      => ['Más nuevas', 'p.fecha_registro DESC'],
    'precio_asc'  => ['Precio: menor a mayor', 'p.precio ASC'],
    'precio_desc' => ['Precio: mayor a menor', 'p.precio DESC'],
];

// ---------- Filtros que llegan por GET ----------
$buscar = trim($_GET['buscar'] ?? '');
// Formatos y géneros pueden ser varios (casillas): se guardan como lista de números
$formatosElegidos = array_values(array_filter(array_map('intval', (array) ($_GET['formato'] ?? []))));
$generosElegidos  = array_values(array_filter(array_map('intval', (array) ($_GET['genero'] ?? []))));
$soloEnStock = ($_GET['disponible'] ?? '') === 'stock';
$orden = array_key_exists($_GET['orden'] ?? '', ORDENES) ? $_GET['orden'] : 'titulo';

// Listas de las casillas desde las tablas
$listaFormatos = $con->query('SELECT id_formato, nombre FROM Formato ORDER BY nombre')->fetch_all(MYSQLI_ASSOC);
$listaGeneros  = $con->query('SELECT id_genero, nombre FROM Genero ORDER BY nombre')->fetch_all(MYSQLI_ASSOC);

// ---------- WHERE: siempre solo publicadas, más un "?" por cada filtro ----------
$condiciones = ["p.estado = 'Publicado'"];
$tipos = '';
$valores = [];
if ($buscar !== '') {
    $condiciones[] = '(p.titulo LIKE ? OR p.director LIKE ?)';
    $tipos .= 'ss';
    $valores[] = '%' . $buscar . '%';
    $valores[] = '%' . $buscar . '%';
}
if (count($formatosElegidos) > 0) {
    $condiciones[] = 'p.id_formato IN (' . implode(', ', array_fill(0, count($formatosElegidos), '?')) . ')';
    $tipos .= str_repeat('i', count($formatosElegidos));
    array_push($valores, ...$formatosElegidos);
}
if (count($generosElegidos) > 0) {
    $condiciones[] = 'p.id_genero IN (' . implode(', ', array_fill(0, count($generosElegidos), '?')) . ')';
    $tipos .= str_repeat('i', count($generosElegidos));
    array_push($valores, ...$generosElegidos);
}
if ($soloEnStock) {
    $condiciones[] = 'p.existencia > 0';
}
$where = 'WHERE ' . implode(' AND ', $condiciones);
$hayFiltros = $buscar !== '' || count($formatosElegidos) > 0 || count($generosElegidos) > 0 || $soloEnStock;

// Ejecuta una consulta preparada con los valores de los filtros
function consultar(mysqli $con, string $sql, string $tipos, array $valores): mysqli_result
{
    $consulta = $con->prepare($sql);
    if ($tipos !== '') {
        $consulta->bind_param($tipos, ...$valores);
    }
    $consulta->execute();
    return $consulta->get_result();
}

// ---------- Total y página actual ----------
$total = consultar($con, "SELECT COUNT(*) FROM Pelicula p $where", $tipos, $valores)->fetch_row()[0];
$paginas = max(1, (int) ceil($total / POR_PAGINA));
$paginaActual = min(max(1, (int) ($_GET['pagina'] ?? 1)), $paginas);
$desde = ($paginaActual - 1) * POR_PAGINA;

// ---------- Películas de la página ----------
// Las agotadas siempre al final; luego el orden elegido
$sql = "SELECT p.id_pelicula, p.titulo, p.director, p.anio, p.precio, p.existencia, p.imagen,
               g.nombre AS genero, f.nombre AS formato
        FROM Pelicula p
        JOIN Genero g  ON g.id_genero  = p.id_genero
        JOIN Formato f ON f.id_formato = p.id_formato
        $where
        ORDER BY p.existencia = 0, " . ORDENES[$orden][1] . "
        LIMIT " . POR_PAGINA . " OFFSET $desde";
$peliculas = consultar($con, $sql, $tipos, $valores)->fetch_all(MYSQLI_ASSOC);

// Enlace a otra página conservando los filtros
function enlacePagina(int $numero): string
{
    $parametros = $_GET;
    $parametros['pagina'] = $numero;
    return 'tienda.php?' . http_build_query($parametros);
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
        <!-- form="filtros" une este buscador con el formulario de la izquierda -->
        <input class="campo" type="search" name="buscar" form="filtros" placeholder="Buscar título o director..." value="<?= e($buscar) ?>">
        <a class="btn btn-secundario" href="carrito.php">Carrito (<?= articulosEnCarrito() ?>)</a>
      </div>
    </div>

    <div class="tienda">

      <!-- Filtros: formulario GET; las casillas permiten elegir varios -->
      <form class="tarjeta filtros" id="filtros" method="get" action="tienda.php">
        <h3>Filtros</h3>

        <h5>Ordenar por</h5>
        <select class="campo" name="orden">
          <?php foreach (ORDENES as $valor => $opcion): ?>
            <option value="<?= $valor ?>" <?= $orden === $valor ? 'selected' : '' ?>><?= $opcion[0] ?></option>
          <?php endforeach; ?>
        </select>

        <h5>Formato</h5>
        <?php foreach ($listaFormatos as $f): ?>
          <label><input type="checkbox" name="formato[]" value="<?= $f['id_formato'] ?>" <?= in_array((int) $f['id_formato'], $formatosElegidos, true) ? 'checked' : '' ?>> <?= e($f['nombre']) ?></label>
        <?php endforeach; ?>

        <h5>Género</h5>
        <?php foreach ($listaGeneros as $g): ?>
          <label><input type="checkbox" name="genero[]" value="<?= $g['id_genero'] ?>" <?= in_array((int) $g['id_genero'], $generosElegidos, true) ? 'checked' : '' ?>> <?= e($g['nombre']) ?></label>
        <?php endforeach; ?>

        <h5>Disponibilidad</h5>
        <label><input type="radio" name="disponible" value="" <?= $soloEnStock ? '' : 'checked' ?>> Todas</label>
        <label><input type="radio" name="disponible" value="stock" <?= $soloEnStock ? 'checked' : '' ?>> Solo en stock</label>

        <button class="btn btn-primario btn-bloque" type="submit">Aplicar filtros</button>
        <a class="btn btn-secundario btn-bloque" href="tienda.php">Limpiar filtros</a>
      </form>

      <!-- Tarjetas de películas (vienen de la tabla Pelicula) -->
      <div class="peliculas">
        <?php if (count($peliculas) === 0): ?>
          <div class="tarjeta tienda-vacia">
            <?php if ($hayFiltros): ?>
              <h3>No encontramos películas con esos filtros</h3>
              <p class="texto-suave">Prueba con otra búsqueda o <a href="tienda.php">limpia los filtros</a>.</p>
            <?php else: ?>
              <h3>Muy pronto tendremos películas aquí</h3>
              <p class="texto-suave">Estamos preparando el catálogo. Vuelve en unos días o escríbenos desde <a href="contacto.php">Contacto</a>.</p>
            <?php endif; ?>
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
              <!-- Manda un POST a carrito.php con el id de la película -->
              <form method="post" action="carrito.php">
                <input type="hidden" name="accion" value="agregar">
                <input type="hidden" name="id" value="<?= $p['id_pelicula'] ?>">
                <button class="btn btn-primario btn-bloque" type="submit">Agregar al carrito</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Paginación: solo aparece si hay más de una página -->
    <?php if ($paginas > 1): ?>
    <div class="paginacion">
      <?php if ($paginaActual > 1): ?><a href="<?= e(enlacePagina($paginaActual - 1)) ?>">Anterior</a><?php endif; ?>
      <?php for ($i = 1; $i <= $paginas; $i++): ?>
        <a class="<?= $i === $paginaActual ? 'activo' : '' ?>" href="<?= e(enlacePagina($i)) ?>"><?= $i ?></a>
      <?php endfor; ?>
      <?php if ($paginaActual < $paginas): ?><a href="<?= e(enlacePagina($paginaActual + 1)) ?>">Siguiente</a><?php endif; ?>
    </div>
    <?php endif; ?>

  </div>
</section>

<?php include __DIR__ . '/includes/pie.php'; ?>
