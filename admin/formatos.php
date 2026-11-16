<?php
/*
 * Cine+ · Gestión de formatos (CRUD, parte 1: listar y crear)
 * - Lee los formatos de la tabla Formato con el número de películas de cada uno.
 * - La ventana "Nuevo formato" envía un formulario POST que hace un INSERT.
 * Editar y Eliminar todavía son solo diseño.
 */
require __DIR__ . '/../config/conexion.php';
$con = conectar();

$mensaje = '';        // texto que se muestra arriba de la tabla
$tipoMensaje = '';    // 'exito' o 'error'
$abrirNuevo = false;  // si hubo error, la ventana se vuelve a abrir
$nombre = '';
$descripcion = '';

// ---------- Crear un formato (cuando se envía el formulario) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'crear') {
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');

    if ($nombre === '') {
        $mensaje = 'El nombre es obligatorio.';
    } elseif (mb_strlen($nombre) > 30) {
        $mensaje = 'El nombre no puede tener más de 30 caracteres.';
    } elseif (mb_strlen($descripcion) > 200) {
        $mensaje = 'La descripción no puede tener más de 200 caracteres.';
    } else {
        // ¿Ya existe un formato con ese nombre? (consulta preparada)
        $buscar = $con->prepare('SELECT COUNT(*) FROM Formato WHERE nombre = ?');
        $buscar->bind_param('s', $nombre);
        $buscar->execute();
        $buscar->bind_result($existe);
        $buscar->fetch();
        $buscar->close();

        if ($existe > 0) {
            $mensaje = 'Ya existe un formato llamado "' . $nombre . '".';
        } else {
            // Si la descripción viene vacía se guarda como NULL
            $desc = $descripcion === '' ? null : $descripcion;
            $insertar = $con->prepare('INSERT INTO Formato (nombre, descripcion) VALUES (?, ?)');
            $insertar->bind_param('ss', $nombre, $desc);
            $insertar->execute();
            $insertar->close();
            // Se redirige para que al recargar no se vuelva a guardar
            header('Location: formatos.php?creado=1');
            exit;
        }
    }
    $tipoMensaje = 'error';
    $abrirNuevo = true;
}

if (isset($_GET['creado'])) {
    $mensaje = 'Formato creado correctamente.';
    $tipoMensaje = 'exito';
}

// ---------- Listar los formatos con cuántas películas tiene cada uno ----------
$sql = 'SELECT f.id_formato, f.nombre, f.descripcion, COUNT(p.id_pelicula) AS peliculas
        FROM Formato f
        LEFT JOIN Pelicula p ON p.id_formato = f.id_formato
        GROUP BY f.id_formato, f.nombre, f.descripcion
        ORDER BY f.nombre';
$formatos = $con->query($sql)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gestión de formatos · Cine+</title>
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
      <a href="peliculas.html">Gestión de Películas</a>
      <a href="generos.php">Gestión de Géneros</a>
      <a class="activo" href="formatos.php">Gestión de Formatos</a>
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
          <h1>Gestión de formatos</h1>
          <p class="texto-suave">Catálogo de formatos físicos en los que se vende cada película.</p>
        </div>
        <!-- href="#nuevo" abre la ventana con id="nuevo" (ver :target en admin.css) -->
        <a class="btn btn-primario" href="#nuevo">+ Nuevo formato</a>
      </div>

      <!-- Buscador (solo diseño) -->
      <div class="tarjeta barra-busqueda">
        <input class="campo" type="search" placeholder="Buscar formato...">
      </div>

      <!-- Mensaje de éxito o de error -->
      <?php if ($mensaje !== ''): ?>
        <p class="alerta-<?= $tipoMensaje ?>"><?= e($mensaje) ?></p>
      <?php endif; ?>

      <!-- Tabla con los formatos de la base de datos -->
      <div class="tarjeta sin-relleno">
        <table class="tabla tabla-admin tabla-catalogo">
          <thead>
            <tr><th>ID</th><th>Nombre</th><th>Descripción</th><th>Películas</th><th>Acciones</th></tr>
          </thead>
          <tbody>
            <?php if (count($formatos) === 0): ?>
              <tr><td colspan="5" class="centrado">Todavía no hay formatos registrados.</td></tr>
            <?php endif; ?>

            <?php foreach ($formatos as $f): ?>
            <tr>
              <td><?= $f['id_formato'] ?></td>
              <td><?= e($f['nombre']) ?></td>
              <td><?= e($f['descripcion'] ?? '') ?></td>
              <!-- Insignia azul si tiene películas, gris si tiene 0 -->
              <td><span class="insignia <?= $f['peliculas'] > 0 ? 'azul' : 'gris' ?>"><?= $f['peliculas'] ?></span></td>
              <td>
                <a class="accion editar" href="#editar">Editar</a>
                <a class="accion eliminar" href="#eliminar">Eliminar</a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p class="texto-suave chico total-registros">Mostrando <?= count($formatos) ?> formatos · tabla Formato</p>
    </main>

    <!-- ===== Ventanas (modales) =====
         Están ocultas y solo se ven cuando la dirección termina en #nuevo, #editar o #eliminar.
         "Cancelar" usa href="#" para quitar ese final y cerrarlas. -->

    <!-- Crear: formulario real que se envía por POST a esta misma página -->
    <div class="modal<?= $abrirNuevo ? ' abierta' : '' ?>" id="nuevo">
      <form class="tarjeta modal-caja" method="post" action="formatos.php">
        <input type="hidden" name="accion" value="crear">
        <h2>Nuevo formato</h2>
        <p class="texto-suave">Agrega un formato al catálogo. El nombre no se puede repetir.</p>
        <?php if ($abrirNuevo): ?>
          <p class="alerta-error chico"><?= e($mensaje) ?></p>
        <?php endif; ?>
        <label for="nuevo-nombre">Nombre *</label>
        <input class="campo" type="text" id="nuevo-nombre" name="nombre" maxlength="30" required
               placeholder="Ej. Steelbook" value="<?= e($nombre) ?>">
        <label for="nuevo-desc">Descripción</label>
        <textarea class="campo" id="nuevo-desc" name="descripcion" rows="3" maxlength="200"
                  placeholder="Breve descripción (opcional)"><?= e($descripcion) ?></textarea>
        <div class="modal-botones">
          <a class="btn btn-secundario" href="formatos.php">Cancelar</a>
          <button class="btn btn-primario" type="submit">Crear formato</button>
        </div>
      </form>
    </div>

    <!-- Editar (todavía solo diseño, se conecta en el siguiente paso) -->
    <div class="modal" id="editar">
      <form class="tarjeta modal-caja" action="#">
        <h2>Editar formato</h2>
        <p class="texto-suave">Modifica los datos del registro con ID 2.</p>
        <label for="editar-nombre">Nombre *</label>
        <input class="campo" type="text" id="editar-nombre" value="Blu-ray">
        <label for="editar-desc">Descripción</label>
        <textarea class="campo" id="editar-desc" rows="3">Disco de alta definición Full HD (1080p)</textarea>
        <div class="modal-botones">
          <a class="btn btn-secundario" href="#">Cancelar</a>
          <a class="btn btn-primario" href="#">Guardar cambios</a>
        </div>
      </form>
    </div>

    <!-- Confirmar eliminación (todavía solo diseño) -->
    <div class="modal" id="eliminar">
      <div class="tarjeta modal-caja">
        <div class="icono-alerta">!</div>
        <h2>¿Eliminar este formato?</h2>
        <p class="texto-suave">Esta acción no se puede deshacer. Se eliminará el registro de la tabla Formato.</p>
        <div class="registro-borrar">
          <strong>4K Ultra HD</strong>
          <span class="texto-suave chico">ID 3 · 3 películas registradas</span>
        </div>
        <p class="alerta-amarilla chico">Solo se pueden eliminar los formatos que no tienen películas registradas.</p>
        <div class="modal-botones">
          <a class="btn btn-secundario" href="#">Cancelar</a>
          <a class="btn btn-rojo" href="#">Sí, eliminar</a>
        </div>
      </div>
    </div>
  </div>

</div>

</body>
</html>
