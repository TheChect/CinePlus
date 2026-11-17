<?php
/*
 * Cine+ · Gestión de películas (CRUD, parte 1: listar)
 * - La tabla sale de Pelicula unida (JOIN) con Genero y Formato.
 * - El buscador y los filtros se envían por GET y se aplican con una consulta preparada.
 * - Las 4 tarjetas de abajo se calculan con COUNT sobre la tabla Pelicula.
 * - Editar lleva a pelicula_form.php?id=ID.
 * - Eliminar abre una confirmación con ?eliminar=ID. Si la película ya se vendió
 *   no se borra (se pierde el historial de pedidos): se pasa a Borrador.
 */
// Solo administradores con sesión iniciada (si no, se redirige y aquí se detiene)
require __DIR__ . '/../config/solo_admin.php';
require_once __DIR__ . '/../config/conexion.php';
$con = conectar();

// Busca una película con su formato y cuántas unidades se han vendido
function buscarPelicula(mysqli $con, int $id): ?array
{
    $consulta = $con->prepare('SELECT p.id_pelicula, p.titulo, p.anio, p.existencia, p.imagen, f.nombre AS formato,
                                      (SELECT COUNT(*) FROM Detalle_Pedido d WHERE d.id_pelicula = p.id_pelicula) AS ventas
                               FROM Pelicula p
                               JOIN Formato f ON f.id_formato = p.id_formato
                               WHERE p.id_pelicula = ?');
    $consulta->bind_param('i', $id);
    $consulta->execute();
    $fila = $consulta->get_result()->fetch_assoc();
    $consulta->close();
    return $fila ?: null;
}

// Películas con 5 unidades o menos se consideran "poca existencia"
const POCA_EXISTENCIA = 5;
const POR_PAGINA = 10;

$mensaje = '';
$tipoMensaje = 'exito';

// ---------- Eliminar (después de confirmar) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar') {
    $id = (int) ($_POST['id'] ?? 0);
    $pelicula = buscarPelicula($con, $id);

    if ($pelicula === null) {
        $mensaje = 'La película que intentas eliminar ya no existe.';
        $tipoMensaje = 'error';
    } elseif ($pelicula['ventas'] > 0) {
        // Ya tiene ventas: no se borra, solo se oculta de la tienda
        $ocultar = $con->prepare("UPDATE Pelicula SET estado = 'Borrador' WHERE id_pelicula = ?");
        $ocultar->bind_param('i', $id);
        $ocultar->execute();
        $ocultar->close();
        header('Location: peliculas.php?borrador=1');
        exit;
    } else {
        try {
            $borrar = $con->prepare('DELETE FROM Pelicula WHERE id_pelicula = ?');
            $borrar->bind_param('i', $id);
            $borrar->execute();
            $borrar->close();
            // También se borra el archivo de su portada
            if ($pelicula['imagen'] !== null && str_starts_with($pelicula['imagen'], 'img/portadas/')) {
                $archivo = __DIR__ . '/../img/portadas/' . basename($pelicula['imagen']);
                if (is_file($archivo)) {
                    unlink($archivo);
                }
            }
            header('Location: peliculas.php?eliminado=1');
            exit;
        } catch (mysqli_sql_exception $ex) {
            // Por si acaso: la llave foránea de Detalle_Pedido impide borrar (error 1451)
            $mensaje = $ex->getCode() === 1451
                ? 'No se puede eliminar porque la película tiene pedidos registrados.'
                : 'No se pudo eliminar la película: ' . $ex->getMessage();
            $tipoMensaje = 'error';
        }
    }
}

// ---------- Mensajes después de redirigir ----------
if (isset($_GET['creado'])) {
    $mensaje = 'Película guardada correctamente.';
} elseif (isset($_GET['editado'])) {
    $mensaje = 'Película actualizada correctamente.';
} elseif (isset($_GET['eliminado'])) {
    $mensaje = 'Película eliminada correctamente.';
} elseif (isset($_GET['borrador'])) {
    $mensaje = 'La película ya tiene ventas, así que no se borró: se cambió a Borrador y ya no aparece en la tienda.';
    $tipoMensaje = 'aviso';
} elseif (isset($_GET['noexiste'])) {
    $mensaje = 'La película que buscas no existe.';
    $tipoMensaje = 'error';
}

// Película que se va a eliminar (abre la ventana de confirmación)
$eliminar = isset($_GET['eliminar']) ? buscarPelicula($con, (int) $_GET['eliminar']) : null;

// ---------- Leer los filtros que llegan por GET ----------
$buscar    = trim($_GET['buscar'] ?? '');
$idFormato = (int) ($_GET['formato'] ?? 0);   // 0 = todos
$idGenero  = (int) ($_GET['genero'] ?? 0);    // 0 = todos
$estado    = $_GET['estado'] ?? '';           // '', 'Publicado' o 'Borrador'
if (!in_array($estado, ['', 'Publicado', 'Borrador'], true)) {
    $estado = '';
}
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));

// Listas de los <select> desde la base de datos
$listaFormatos = $con->query('SELECT id_formato, nombre FROM Formato ORDER BY nombre')->fetch_all(MYSQLI_ASSOC);
$listaGeneros  = $con->query('SELECT id_genero, nombre FROM Genero ORDER BY nombre')->fetch_all(MYSQLI_ASSOC);

// ---------- Armar el WHERE según los filtros elegidos ----------
// Cada condición agrega un "?" y su valor; al final se pasan todos con bind_param.
$condiciones = [];
$tipos = '';
$valores = [];

if ($buscar !== '') {
    $condiciones[] = '(p.titulo LIKE ? OR p.director LIKE ?)';
    $texto = '%' . $buscar . '%';
    $tipos .= 'ss';
    $valores[] = $texto;
    $valores[] = $texto;
}
if ($idFormato > 0) {
    $condiciones[] = 'p.id_formato = ?';
    $tipos .= 'i';
    $valores[] = $idFormato;
}
if ($idGenero > 0) {
    $condiciones[] = 'p.id_genero = ?';
    $tipos .= 'i';
    $valores[] = $idGenero;
}
if ($estado !== '') {
    $condiciones[] = 'p.estado = ?';
    $tipos .= 's';
    $valores[] = $estado;
}
$where = count($condiciones) > 0 ? 'WHERE ' . implode(' AND ', $condiciones) : '';

// Ejecuta una consulta preparada con los filtros (si hay) y regresa el resultado
function consultar(mysqli $con, string $sql, string $tipos, array $valores): mysqli_result
{
    $consulta = $con->prepare($sql);
    if ($tipos !== '') {
        $consulta->bind_param($tipos, ...$valores);
    }
    $consulta->execute();
    return $consulta->get_result();
}

// ---------- Total de resultados y página actual ----------
$total = consultar($con, "SELECT COUNT(*) FROM Pelicula p $where", $tipos, $valores)->fetch_row()[0];
$paginas = max(1, (int) ceil($total / POR_PAGINA));
$pagina = min($pagina, $paginas);
$desde = ($pagina - 1) * POR_PAGINA;

// ---------- Películas de la página actual ----------
$sql = "SELECT p.id_pelicula, p.titulo, p.director, p.anio, p.precio, p.existencia, p.estado, p.imagen,
               g.nombre AS genero, f.nombre AS formato
        FROM Pelicula p
        JOIN Genero g  ON g.id_genero  = p.id_genero
        JOIN Formato f ON f.id_formato = p.id_formato
        $where
        ORDER BY p.titulo
        LIMIT " . POR_PAGINA . " OFFSET $desde";
$peliculas = consultar($con, $sql, $tipos, $valores)->fetch_all(MYSQLI_ASSOC);

// ---------- Tarjetas de resumen (todo el inventario, sin filtros) ----------
$resumen = $con->query('SELECT COUNT(*) AS registradas,
                               SUM(estado = "Publicado") AS publicadas,
                               SUM(existencia > 0 AND existencia <= ' . POCA_EXISTENCIA . ') AS poca,
                               SUM(existencia = 0) AS agotadas
                        FROM Pelicula')->fetch_assoc();

// Dirección para abrir la confirmación de eliminar sin perder los filtros
function enlaceEliminar(int $id): string
{
    $parametros = $_GET;
    unset($parametros['creado'], $parametros['editado'], $parametros['eliminado'], $parametros['borrador'], $parametros['noexiste']);
    $parametros['eliminar'] = $id;
    return 'peliculas.php?' . http_build_query($parametros);
}

// Arma la dirección de una página conservando los filtros actuales
function enlacePagina(int $numero): string
{
    $parametros = $_GET;
    $parametros['pagina'] = $numero;
    return 'peliculas.php?' . http_build_query($parametros);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gestión de películas · Cine+</title>
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
      <a href="panel.php">Dashboard</a>
      <a href="usuarios.php">Gestión de Usuarios</a>
      <a class="activo" href="peliculas.php">Gestión de Películas</a>
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
      <!-- Búsqueda rápida de películas desde cualquier pantalla del panel -->
      <form class="buscador" method="get" action="peliculas.php">
        <input class="campo" type="search" name="buscar" placeholder="Buscar películas por título o director...">
      </form>
      <div class="avatar avatar-chico"></div>
    </header>

    <main class="admin-contenido">
      <div class="admin-titulo">
        <div>
          <h1>Catálogo de películas</h1>
          <p class="texto-suave">Administra el inventario de la tienda: títulos, formatos, precios y existencias.</p>
        </div>
        <a class="btn btn-primario" href="pelicula_form.php">+ Nueva película</a>
      </div>

      <!-- Buscador y filtros: se envían por GET a esta misma página -->
      <form class="tarjeta barra-busqueda filtros-admin" method="get" action="peliculas.php">
        <input class="campo" type="search" name="buscar" placeholder="Buscar por título o director..."
               value="<?= e($buscar) ?>">
        <label>Formato
          <select class="campo" name="formato">
            <option value="0">Todos</option>
            <?php foreach ($listaFormatos as $f): ?>
              <option value="<?= $f['id_formato'] ?>" <?= $f['id_formato'] == $idFormato ? 'selected' : '' ?>><?= e($f['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Género
          <select class="campo" name="genero">
            <option value="0">Todos</option>
            <?php foreach ($listaGeneros as $g): ?>
              <option value="<?= $g['id_genero'] ?>" <?= $g['id_genero'] == $idGenero ? 'selected' : '' ?>><?= e($g['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Estado
          <select class="campo" name="estado">
            <option value="">Todos</option>
            <option value="Publicado" <?= $estado === 'Publicado' ? 'selected' : '' ?>>Publicado</option>
            <option value="Borrador" <?= $estado === 'Borrador' ? 'selected' : '' ?>>Borrador</option>
          </select>
        </label>
        <button class="btn btn-secundario" type="submit">Filtrar</button>
        <?php if ($where !== ''): ?>
          <a class="chico" href="peliculas.php">Limpiar</a>
        <?php endif; ?>
      </form>

      <?php if ($mensaje !== ''): ?>
        <p class="alerta-<?= $tipoMensaje ?>"><?= e($mensaje) ?></p>
      <?php endif; ?>

      <!-- Tabla de películas de la base de datos -->
      <div class="tarjeta sin-relleno">
        <table class="tabla tabla-admin tabla-lista">
          <thead>
            <tr><th>Película</th><th>Género</th><th>Año</th><th>Formato</th><th>Precio</th><th>Existencia</th><th>Estado</th><th>Acciones</th></tr>
          </thead>
          <tbody>
            <?php if (count($peliculas) === 0): ?>
              <tr><td colspan="8" class="centrado">
                <?= $where === '' ? 'Todavía no hay películas registradas.' : 'No se encontraron películas con esos filtros.' ?>
              </td></tr>
            <?php endif; ?>

            <?php foreach ($peliculas as $p): ?>
              <?php
                // Si no hay existencia se muestra "Agotado" aunque esté publicada
                if ($p['existencia'] == 0) {
                    $textoEstado = 'Agotado';
                    $claseEstado = 'roja';
                } elseif ($p['estado'] === 'Publicado') {
                    $textoEstado = 'Publicado';
                    $claseEstado = 'verde';
                } else {
                    $textoEstado = 'Borrador';
                    $claseEstado = 'amarilla';
                }
              ?>
            <tr>
              <td>
                <div class="celda-pelicula">
                  <?php if (!empty($p['imagen'])): ?>
                    <img class="mini-poster mini-tabla" src="../<?= e($p['imagen']) ?>" alt="Portada de <?= e($p['titulo']) ?>">
                  <?php else: ?>
                    <div class="mini-poster mini-tabla"></div>
                  <?php endif; ?>
                  <div><strong><?= e($p['titulo']) ?></strong><span class="texto-suave chico"><?= e($p['director']) ?></span></div>
                </div>
              </td>
              <td><?= e($p['genero']) ?></td>
              <td><?= $p['anio'] ?? '—' ?></td>
              <td><?= e($p['formato']) ?></td>
              <td><strong>$<?= number_format($p['precio'], 2) ?></strong></td>
              <td class="<?= $p['existencia'] <= POCA_EXISTENCIA ? 'amarillo-texto' : '' ?>"><?= $p['existencia'] ?> uds.</td>
              <td><span class="insignia <?= $claseEstado ?>"><?= $textoEstado ?></span></td>
              <td class="acciones">
                <a class="accion ver" href="pelicula_form.php?id=<?= $p['id_pelicula'] ?>">Ver</a>
                <a class="accion editar" href="pelicula_form.php?id=<?= $p['id_pelicula'] ?>">Editar</a>
                <a class="accion eliminar" href="<?= e(enlaceEliminar($p['id_pelicula'])) ?>">Eliminar</a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="pie-tabla">
        <p class="texto-suave chico">
          <?php if ($total > 0): ?>
            Mostrando <?= $desde + 1 ?> a <?= $desde + count($peliculas) ?> de <?= $total ?> películas
          <?php else: ?>
            0 películas
          <?php endif; ?>
        </p>
        <!-- La paginación solo aparece si hay más de una página -->
        <?php if ($paginas > 1): ?>
        <div class="paginacion">
          <?php if ($pagina > 1): ?><a href="<?= e(enlacePagina($pagina - 1)) ?>">Anterior</a><?php endif; ?>
          <?php for ($i = 1; $i <= $paginas; $i++): ?>
            <a class="<?= $i === $pagina ? 'activo' : '' ?>" href="<?= e(enlacePagina($i)) ?>"><?= $i ?></a>
          <?php endfor; ?>
          <?php if ($pagina < $paginas): ?><a href="<?= e(enlacePagina($pagina + 1)) ?>">Siguiente</a><?php endif; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Resumen del inventario (todas las películas, sin filtros) -->
      <div class="rejilla-4 resumen-inventario">
        <div class="tarjeta indicador"><span class="texto-suave">Películas registradas</span><strong><?= (int) $resumen['registradas'] ?></strong></div>
        <div class="tarjeta indicador"><span class="texto-suave">Publicadas</span><strong class="verde"><?= (int) $resumen['publicadas'] ?></strong></div>
        <div class="tarjeta indicador"><span class="texto-suave">Con poca existencia</span><strong class="amarillo-texto"><?= (int) $resumen['poca'] ?></strong></div>
        <div class="tarjeta indicador"><span class="texto-suave">Agotadas</span><strong class="rojo"><?= (int) $resumen['agotadas'] ?></strong></div>
      </div>
    </main>

    <!-- Confirmar eliminación: se muestra cuando la dirección trae ?eliminar=ID -->
    <?php if ($eliminar !== null): ?>
    <div class="modal abierta" id="eliminar">
      <form class="tarjeta modal-caja" method="post" action="peliculas.php">
        <input type="hidden" name="accion" value="eliminar">
        <input type="hidden" name="id" value="<?= $eliminar['id_pelicula'] ?>">
        <div class="icono-alerta">!</div>
        <h2>¿Eliminar esta película?</h2>
        <div class="registro-borrar">
          <strong><?= e($eliminar['titulo']) ?></strong>
          <span class="texto-suave chico">
            <?= e($eliminar['formato']) ?> · <?= $eliminar['anio'] ?? 'sin año' ?> · <?= $eliminar['existencia'] ?> uds. en existencia
          </span>
        </div>
        <?php if ($eliminar['ventas'] > 0): ?>
          <!-- Con ventas: no se borra, se pasa a Borrador -->
          <p class="alerta-amarilla chico">
            Esta película ya aparece en <?= $eliminar['ventas'] ?> venta(s). Para no perder el historial de pedidos
            no se borrará: se cambiará a Borrador y dejará de verse en la tienda.
          </p>
          <div class="modal-botones">
            <a class="btn btn-secundario" href="peliculas.php">Cancelar</a>
            <button class="btn btn-rojo" type="submit">Pasar a Borrador</button>
          </div>
        <?php else: ?>
          <p class="texto-suave">Esta acción no se puede deshacer. Se eliminará el registro de la tabla Pelicula y su portada.</p>
          <div class="modal-botones">
            <a class="btn btn-secundario" href="peliculas.php">Cancelar</a>
            <button class="btn btn-rojo" type="submit">Sí, eliminar</button>
          </div>
        <?php endif; ?>
      </form>
    </div>
    <?php endif; ?>
  </div>

</div>

<!-- Validaciones de formularios antes de enviarlos -->
<script src="../js/validaciones.js"></script>
</body>
</html>
