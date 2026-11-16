<?php
/*
 * Cine+ · Gestión de géneros (CRUD completo)
 * - Listar: lee la tabla Genero con el número de películas de cada uno.
 * - Crear:  ventana "Nuevo género" -> POST accion=crear -> INSERT.
 * - Editar: generos.php?editar=ID abre la ventana con los datos -> POST accion=editar -> UPDATE.
 * - Eliminar: generos.php?eliminar=ID pide confirmación -> POST accion=eliminar -> DELETE.
 */
require __DIR__ . '/../config/conexion.php';
$con = conectar();

$mensaje = '';         // texto que se muestra arriba de la tabla
$tipoMensaje = '';     // 'exito' o 'error'
$abrirNuevo = false;   // si hubo error al crear, la ventana se vuelve a abrir
$nombre = '';
$descripcion = '';
$editar = null;        // género que se está editando (o null)
$errorEditar = '';
$eliminar = null;      // género que se va a eliminar (o null)

// Busca un género por su ID y regresa sus datos con el número de películas
function buscarGenero(mysqli $con, int $id): ?array
{
    $consulta = $con->prepare('SELECT g.id_genero, g.nombre, g.descripcion, COUNT(p.id_pelicula) AS peliculas
                               FROM Genero g
                               LEFT JOIN Pelicula p ON p.id_genero = g.id_genero
                               WHERE g.id_genero = ?
                               GROUP BY g.id_genero, g.nombre, g.descripcion');
    $consulta->bind_param('i', $id);
    $consulta->execute();
    $fila = $consulta->get_result()->fetch_assoc();
    $consulta->close();
    return $fila ?: null;
}

// Revisa nombre y descripción; regresa el mensaje de error o '' si todo está bien.
// $idIgnorar sirve al editar: el nombre puede repetirse con el mismo registro.
function validarGenero(mysqli $con, string $nombre, string $descripcion, int $idIgnorar = 0): string
{
    if ($nombre === '') {
        return 'El nombre es obligatorio.';
    }
    if (mb_strlen($nombre) > 50) {
        return 'El nombre no puede tener más de 50 caracteres.';
    }
    if (mb_strlen($descripcion) > 200) {
        return 'La descripción no puede tener más de 200 caracteres.';
    }
    $buscar = $con->prepare('SELECT COUNT(*) FROM Genero WHERE nombre = ? AND id_genero <> ?');
    $buscar->bind_param('si', $nombre, $idIgnorar);
    $buscar->execute();
    $buscar->bind_result($existe);
    $buscar->fetch();
    $buscar->close();
    if ($existe > 0) {
        return 'Ya existe un género llamado "' . $nombre . '".';
    }
    return '';
}

$accion = $_POST['accion'] ?? '';

// ---------- Crear ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'crear') {
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $mensaje = validarGenero($con, $nombre, $descripcion);

    if ($mensaje === '') {
        $desc = $descripcion === '' ? null : $descripcion;   // vacía se guarda como NULL
        $insertar = $con->prepare('INSERT INTO Genero (nombre, descripcion) VALUES (?, ?)');
        $insertar->bind_param('ss', $nombre, $desc);
        $insertar->execute();
        $insertar->close();
        // Se redirige para que al recargar no se vuelva a guardar
        header('Location: generos.php?creado=1');
        exit;
    }
    $tipoMensaje = 'error';
    $abrirNuevo = true;
}

// ---------- Editar (guardar cambios) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'editar') {
    $id = (int) ($_POST['id'] ?? 0);
    $editar = buscarGenero($con, $id);

    if ($editar === null) {
        $mensaje = 'El género que intentas editar ya no existe.';
        $tipoMensaje = 'error';
    } else {
        $nuevoNombre = trim($_POST['nombre'] ?? '');
        $nuevaDesc = trim($_POST['descripcion'] ?? '');
        $errorEditar = validarGenero($con, $nuevoNombre, $nuevaDesc, $id);

        if ($errorEditar === '') {
            $desc = $nuevaDesc === '' ? null : $nuevaDesc;
            $actualizar = $con->prepare('UPDATE Genero SET nombre = ?, descripcion = ? WHERE id_genero = ?');
            $actualizar->bind_param('ssi', $nuevoNombre, $desc, $id);
            $actualizar->execute();
            $actualizar->close();
            header('Location: generos.php?editado=1');
            exit;
        }
        // Si hubo error se vuelve a mostrar la ventana con lo que se escribió
        $editar['nombre'] = $nuevoNombre;
        $editar['descripcion'] = $nuevaDesc;
    }
}

// ---------- Eliminar (después de confirmar) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'eliminar') {
    $id = (int) ($_POST['id'] ?? 0);
    $genero = buscarGenero($con, $id);

    if ($genero === null) {
        $mensaje = 'El género que intentas eliminar ya no existe.';
    } elseif ($genero['peliculas'] > 0) {
        // Primera revisión: si tiene películas no se borra
        $mensaje = 'No se puede eliminar "' . $genero['nombre'] . '" porque tiene ' . $genero['peliculas'] . ' película(s) registrada(s).';
    } else {
        try {
            $borrar = $con->prepare('DELETE FROM Genero WHERE id_genero = ?');
            $borrar->bind_param('i', $id);
            $borrar->execute();
            $borrar->close();
            header('Location: generos.php?eliminado=1');
            exit;
        } catch (mysqli_sql_exception $ex) {
            // Segunda revisión: la llave foránea (ON DELETE RESTRICT) también lo impide.
            // El código 1451 significa que otra tabla todavía usa este registro.
            if ($ex->getCode() === 1451) {
                $mensaje = 'No se puede eliminar "' . $genero['nombre'] . '" porque hay películas que lo usan.';
            } else {
                $mensaje = 'No se pudo eliminar el género: ' . $ex->getMessage();
            }
        }
    }
    $tipoMensaje = 'error';
}

// ---------- Mensajes después de redirigir ----------
if (isset($_GET['creado'])) {
    $mensaje = 'Género creado correctamente.';
    $tipoMensaje = 'exito';
} elseif (isset($_GET['editado'])) {
    $mensaje = 'Género actualizado correctamente.';
    $tipoMensaje = 'exito';
} elseif (isset($_GET['eliminado'])) {
    $mensaje = 'Género eliminado correctamente.';
    $tipoMensaje = 'exito';
}

// ---------- Abrir las ventanas de editar o eliminar desde el enlace ----------
if (isset($_GET['editar'])) {
    $editar = buscarGenero($con, (int) $_GET['editar']);
}
if (isset($_GET['eliminar'])) {
    $eliminar = buscarGenero($con, (int) $_GET['eliminar']);
}

// ---------- Listar los géneros con cuántas películas tiene cada uno ----------
$sql = 'SELECT g.id_genero, g.nombre, g.descripcion, COUNT(p.id_pelicula) AS peliculas
        FROM Genero g
        LEFT JOIN Pelicula p ON p.id_genero = g.id_genero
        GROUP BY g.id_genero, g.nombre, g.descripcion
        ORDER BY g.nombre';
$generos = $con->query($sql)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gestión de géneros · Cine+</title>
  <link rel="stylesheet" href="../css/estilos.css">
  <link rel="stylesheet" href="../css/admin.css">
</head>
<body>

<div class="admin">

  <!-- Barra lateral con el menú de administración -->
  <aside class="lateral">
    <a class="logo" href="panel.html"><span class="logo-cuadro"></span> CINE+</a>

    <p class="lateral-titulo">Principal</p>
    <nav class="lateral-menu">
      <a href="panel.html">Dashboard</a>
      <a href="usuarios.html">Gestión de Usuarios</a>
      <a href="peliculas.php">Gestión de Películas</a>
      <a class="activo" href="generos.php">Gestión de Géneros</a>
      <a href="formatos.php">Gestión de Formatos</a>
    </nav>

    <!-- Parte de abajo: cerrar sesión y datos del administrador -->
    <div class="lateral-abajo">
      <a href="acceso.html">&larr; Cerrar sesión</a>
      <div class="admin-usuario">
        <div class="avatar avatar-chico"></div>
        <div>
          <strong>Admin Central</strong>
          <span class="texto-suave chico">admin@cineplus.com</span>
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
      <!-- Título y botón para agregar -->
      <div class="admin-titulo">
        <div>
          <h1>Gestión de géneros</h1>
          <p class="texto-suave">Catálogo de géneros que se usan para clasificar y filtrar las películas.</p>
        </div>
        <!-- href="#nuevo" abre la ventana con id="nuevo" (ver :target en admin.css) -->
        <a class="btn btn-primario" href="#nuevo">+ Nuevo género</a>
      </div>

      <!-- Buscador (solo diseño) -->
      <div class="tarjeta barra-busqueda">
        <input class="campo" type="search" placeholder="Buscar género...">
      </div>

      <!-- Mensaje de éxito o de error -->
      <?php if ($mensaje !== ''): ?>
        <p class="alerta-<?= $tipoMensaje ?>"><?= e($mensaje) ?></p>
      <?php endif; ?>

      <!-- Tabla con los géneros de la base de datos -->
      <div class="tarjeta sin-relleno">
        <table class="tabla tabla-admin tabla-catalogo">
          <thead>
            <tr><th>ID</th><th>Nombre</th><th>Descripción</th><th>Películas</th><th>Acciones</th></tr>
          </thead>
          <tbody>
            <?php if (count($generos) === 0): ?>
              <tr><td colspan="5" class="centrado">Todavía no hay géneros registrados.</td></tr>
            <?php endif; ?>

            <?php foreach ($generos as $g): ?>
            <tr>
              <td><?= $g['id_genero'] ?></td>
              <td><?= e($g['nombre']) ?></td>
              <td><?= e($g['descripcion'] ?? '') ?></td>
              <!-- Insignia azul si tiene películas, gris si tiene 0 -->
              <td><span class="insignia <?= $g['peliculas'] > 0 ? 'azul' : 'gris' ?>"><?= $g['peliculas'] ?></span></td>
              <td>
                <!-- El ID viaja en la dirección: PHP abre la ventana con ese género -->
                <a class="accion editar" href="generos.php?editar=<?= $g['id_genero'] ?>">Editar</a>
                <a class="accion eliminar" href="generos.php?eliminar=<?= $g['id_genero'] ?>">Eliminar</a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p class="texto-suave chico total-registros">Mostrando <?= count($generos) ?> géneros · tabla Genero</p>
    </main>

    <!-- ===== Ventanas (modales) =====
         "Nuevo" se abre con #nuevo (CSS :target). Editar y Eliminar las abre PHP
         con la clase "abierta" cuando la dirección trae ?editar=ID o ?eliminar=ID.
         "Cancelar" regresa a generos.php y las cierra. -->

    <!-- Crear: formulario real que se envía por POST a esta misma página -->
    <div class="modal<?= $abrirNuevo ? ' abierta' : '' ?>" id="nuevo">
      <form class="tarjeta modal-caja" method="post" action="generos.php">
        <input type="hidden" name="accion" value="crear">
        <h2>Nuevo género</h2>
        <p class="texto-suave">Agrega un género al catálogo. El nombre no se puede repetir.</p>
        <?php if ($abrirNuevo): ?>
          <p class="alerta-error chico"><?= e($mensaje) ?></p>
        <?php endif; ?>
        <label for="nuevo-nombre">Nombre *</label>
        <input class="campo" type="text" id="nuevo-nombre" name="nombre" maxlength="50" required
               placeholder="Ej. Animación" value="<?= e($nombre) ?>">
        <label for="nuevo-desc">Descripción</label>
        <textarea class="campo" id="nuevo-desc" name="descripcion" rows="3" maxlength="200"
                  placeholder="Breve descripción (opcional)"><?= e($descripcion) ?></textarea>
        <div class="modal-botones">
          <a class="btn btn-secundario" href="generos.php">Cancelar</a>
          <button class="btn btn-primario" type="submit">Crear género</button>
        </div>
      </form>
    </div>

    <!-- Editar: se muestra cuando PHP encontró el género de ?editar=ID -->
    <?php if ($editar !== null): ?>
    <div class="modal abierta" id="editar">
      <form class="tarjeta modal-caja" method="post" action="generos.php">
        <input type="hidden" name="accion" value="editar">
        <input type="hidden" name="id" value="<?= $editar['id_genero'] ?>">
        <h2>Editar género</h2>
        <p class="texto-suave">Modifica los datos del registro con ID <?= $editar['id_genero'] ?>.</p>
        <?php if ($errorEditar !== ''): ?>
          <p class="alerta-error chico"><?= e($errorEditar) ?></p>
        <?php endif; ?>
        <label for="editar-nombre">Nombre *</label>
        <input class="campo" type="text" id="editar-nombre" name="nombre" maxlength="50" required
               value="<?= e($editar['nombre']) ?>">
        <label for="editar-desc">Descripción</label>
        <textarea class="campo" id="editar-desc" name="descripcion" rows="3" maxlength="200"><?= e($editar['descripcion'] ?? '') ?></textarea>
        <div class="modal-botones">
          <a class="btn btn-secundario" href="generos.php">Cancelar</a>
          <button class="btn btn-primario" type="submit">Guardar cambios</button>
        </div>
      </form>
    </div>
    <?php endif; ?>

    <!-- Confirmar eliminación: se muestra con ?eliminar=ID -->
    <?php if ($eliminar !== null): ?>
    <div class="modal abierta" id="eliminar">
      <form class="tarjeta modal-caja" method="post" action="generos.php">
        <input type="hidden" name="accion" value="eliminar">
        <input type="hidden" name="id" value="<?= $eliminar['id_genero'] ?>">
        <div class="icono-alerta">!</div>
        <h2>¿Eliminar este género?</h2>
        <p class="texto-suave">Esta acción no se puede deshacer. Se eliminará el registro de la tabla Genero.</p>
        <div class="registro-borrar">
          <strong><?= e($eliminar['nombre']) ?></strong>
          <span class="texto-suave chico">ID <?= $eliminar['id_genero'] ?> · <?= $eliminar['peliculas'] ?> películas registradas</span>
        </div>
        <?php if ($eliminar['peliculas'] > 0): ?>
          <!-- Si tiene películas no se muestra el botón de borrar -->
          <p class="alerta-error chico">Este género tiene películas registradas, por eso no se puede eliminar. Cambia primero el género de esas películas.</p>
          <div class="modal-botones">
            <a class="btn btn-secundario" href="generos.php">Cerrar</a>
          </div>
        <?php else: ?>
          <p class="alerta-amarilla chico">Solo se pueden eliminar los géneros que no tienen películas registradas.</p>
          <div class="modal-botones">
            <a class="btn btn-secundario" href="generos.php">Cancelar</a>
            <button class="btn btn-rojo" type="submit">Sí, eliminar</button>
          </div>
        <?php endif; ?>
      </form>
    </div>
    <?php endif; ?>
  </div>

</div>

</body>
</html>
