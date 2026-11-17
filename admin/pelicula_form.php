<?php
/*
 * Cine+ · Formulario de películas (crear y editar)
 * - pelicula_form.php        -> película nueva (INSERT).
 * - pelicula_form.php?id=ID  -> editar una película existente (UPDATE).
 * - Las listas de Género y Formato salen de sus tablas.
 * - El formulario se envía por POST con enctype="multipart/form-data" para poder subir la portada.
 * - Se valida todo en PHP y se guarda con un INSERT preparado.
 * - La portada (opcional) se guarda en img/portadas/ con un nombre que inventa el servidor.
 */
require __DIR__ . '/../config/conexion.php';
$con = conectar();

const MAX_PORTADA = 2 * 1024 * 1024;   // 2 MB
// Tipos de imagen permitidos y la extensión que se usará al guardar
const TIPOS_PORTADA = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];

$listaGeneros  = $con->query('SELECT id_genero, nombre FROM Genero ORDER BY nombre')->fetch_all(MYSQLI_ASSOC);
$listaFormatos = $con->query('SELECT id_formato, nombre FROM Formato ORDER BY nombre')->fetch_all(MYSQLI_ASSOC);

// Valores del formulario (vacíos al entrar; con lo escrito si hubo errores)
$datos = [
    'titulo' => '', 'director' => '', 'anio' => '', 'id_genero' => '', 'calificacion' => '',
    'sinopsis' => '', 'id_formato' => '', 'estado' => 'Borrador', 'precio' => '', 'existencia' => '',
];
$errores = [];

// ---------- ¿Es una película nueva o se está editando una? ----------
// El id llega por GET al abrir (pelicula_form.php?id=5) y por POST al guardar (campo oculto).
$id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
$editando = $id > 0;
$portadaActual = null;   // ruta de la portada que ya tiene la película (al editar)

if ($editando) {
    $buscar = $con->prepare('SELECT * FROM Pelicula WHERE id_pelicula = ?');
    $buscar->bind_param('i', $id);
    $buscar->execute();
    $pelicula = $buscar->get_result()->fetch_assoc();
    $buscar->close();

    // Si el id no existe se regresa a la lista
    if ($pelicula === null) {
        header('Location: peliculas.php?noexiste=1');
        exit;
    }
    $portadaActual = $pelicula['imagen'];

    // Al abrir la página se llenan los campos con lo que hay en la base
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        foreach ($datos as $campo => $valor) {
            $datos[$campo] = (string) ($pelicula[$campo] ?? '');
        }
    }
}

// Borra el archivo de una portada. Solo acepta rutas dentro de img/portadas/.
function borrarPortada(?string $ruta): void
{
    if ($ruta === null || !str_starts_with($ruta, 'img/portadas/')) {
        return;
    }
    $archivo = __DIR__ . '/../img/portadas/' . basename($ruta);
    if (is_file($archivo)) {
        unlink($archivo);
    }
}

// Revisa si un ID existe en una tabla (para el género y el formato elegidos)
function existeId(mysqli $con, string $tabla, string $columna, int $id): bool
{
    $consulta = $con->prepare("SELECT COUNT(*) FROM $tabla WHERE $columna = ?");
    $consulta->bind_param('i', $id);
    $consulta->execute();
    $consulta->bind_result($cuantos);
    $consulta->fetch();
    $consulta->close();
    return $cuantos > 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ---------- 1. Leer lo que se escribió ----------
    foreach ($datos as $campo => $valor) {
        $datos[$campo] = trim($_POST[$campo] ?? $valor);
    }

    // ---------- 2. Validar ----------
    if ($datos['titulo'] === '') {
        $errores[] = 'El título es obligatorio.';
    } elseif (mb_strlen($datos['titulo']) > 150) {
        $errores[] = 'El título no puede tener más de 150 caracteres.';
    }
    if ($datos['director'] === '') {
        $errores[] = 'El director es obligatorio.';
    } elseif (mb_strlen($datos['director']) > 100) {
        $errores[] = 'El director no puede tener más de 100 caracteres.';
    }
    // El año es opcional; si se escribe debe estar entre 1888 (primera película) y el año que viene
    $anioMaximo = (int) date('Y') + 1;
    if ($datos['anio'] !== '' && (!ctype_digit($datos['anio']) || $datos['anio'] < 1888 || $datos['anio'] > $anioMaximo)) {
        $errores[] = "El año debe estar entre 1888 y $anioMaximo.";
    }
    if (!existeId($con, 'Genero', 'id_genero', (int) $datos['id_genero'])) {
        $errores[] = 'Selecciona un género válido.';
    }
    if ($datos['calificacion'] !== '' && (!is_numeric($datos['calificacion']) || $datos['calificacion'] < 0 || $datos['calificacion'] > 10)) {
        $errores[] = 'La calificación debe ser un número de 0 a 10.';
    }
    if (!existeId($con, 'Formato', 'id_formato', (int) $datos['id_formato'])) {
        $errores[] = 'Selecciona un formato válido.';
    }
    if (!in_array($datos['estado'], ['Publicado', 'Borrador'], true)) {
        $errores[] = 'El estado no es válido.';
    }
    if ($datos['precio'] === '' || !is_numeric($datos['precio']) || $datos['precio'] < 0) {
        $errores[] = 'El precio es obligatorio y no puede ser negativo.';
    }
    if ($datos['existencia'] === '' || !ctype_digit($datos['existencia'])) {
        $errores[] = 'La existencia es obligatoria y debe ser un número entero (0 o más).';
    }

    // ---------- 3. Revisar la portada (si se subió una) ----------
    $portada = $_FILES['portada'] ?? null;
    $extension = null;
    if ($portada !== null && $portada['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($portada['error'] === UPLOAD_ERR_INI_SIZE || $portada['error'] === UPLOAD_ERR_FORM_SIZE
            || ($portada['error'] === UPLOAD_ERR_OK && $portada['size'] > MAX_PORTADA)) {
            $errores[] = 'La portada no puede pesar más de 2 MB.';
        } elseif ($portada['error'] !== UPLOAD_ERR_OK) {
            $errores[] = 'No se pudo subir la portada. Intenta de nuevo.';
        } else {
            // Se revisa el contenido real del archivo, no la extensión del nombre
            $info = getimagesize($portada['tmp_name']);
            $tipo = $info === false ? '' : $info['mime'];
            if (!isset(TIPOS_PORTADA[$tipo])) {
                $errores[] = 'La portada debe ser una imagen JPG, PNG o WEBP.';
            } else {
                $extension = TIPOS_PORTADA[$tipo];
            }
        }
    }

    // ---------- 4. Guardar ----------
    if (count($errores) === 0) {
        // Al editar, si no se sube portada nueva se conserva la actual
        $rutaImagen = $portadaActual;
        if ($extension !== null) {
            // Nombre inventado por el servidor: así nadie puede elegir el nombre del archivo
            $nombreArchivo = 'portada_' . bin2hex(random_bytes(8)) . '.' . $extension;
            $rutaImagen = 'img/portadas/' . $nombreArchivo;
            if (!move_uploaded_file($portada['tmp_name'], __DIR__ . '/../' . $rutaImagen)) {
                $errores[] = 'No se pudo guardar la portada en el servidor.';
                $rutaImagen = $portadaActual;
            }
        }
    }

    if (count($errores) === 0) {
        // Los campos opcionales vacíos se guardan como NULL
        $anio = $datos['anio'] === '' ? null : (int) $datos['anio'];
        $calificacion = $datos['calificacion'] === '' ? null : (float) $datos['calificacion'];
        $sinopsis = $datos['sinopsis'] === '' ? null : $datos['sinopsis'];
        $idGenero = (int) $datos['id_genero'];
        $idFormato = (int) $datos['id_formato'];
        $precio = (float) $datos['precio'];
        $existencia = (int) $datos['existencia'];

        if ($editando) {
            $actualizar = $con->prepare('UPDATE Pelicula SET
                id_genero = ?, id_formato = ?, titulo = ?, director = ?, anio = ?, sinopsis = ?,
                calificacion = ?, precio = ?, existencia = ?, imagen = ?, estado = ?
                WHERE id_pelicula = ?');
            $actualizar->bind_param('iississdissi', $idGenero, $idFormato, $datos['titulo'], $datos['director'],
                $anio, $sinopsis, $calificacion, $precio, $existencia, $rutaImagen, $datos['estado'], $id);
            $actualizar->execute();
            $actualizar->close();

            // Si se cambió la portada, se borra el archivo viejo para no dejar basura
            if ($portadaActual !== null && $rutaImagen !== $portadaActual) {
                borrarPortada($portadaActual);
            }
            header('Location: peliculas.php?editado=1');
            exit;
        }

        $insertar = $con->prepare('INSERT INTO Pelicula
            (id_genero, id_formato, titulo, director, anio, sinopsis, calificacion, precio, existencia, imagen, estado)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $insertar->bind_param('iississdiss', $idGenero, $idFormato, $datos['titulo'], $datos['director'],
            $anio, $sinopsis, $calificacion, $precio, $existencia, $rutaImagen, $datos['estado']);
        $insertar->execute();
        $insertar->close();

        header('Location: peliculas.php?creado=1');
        exit;
    }
}

// Para marcar la opción elegida en las listas y en las píldoras
function marcado(string $valorActual, $valorOpcion, string $palabra): string
{
    return $valorActual === (string) $valorOpcion ? $palabra : '';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $editando ? 'Editar película' : 'Nueva película' ?> · Cine+</title>
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
      <a href="usuarios.php">Gestión de Usuarios</a>
      <a class="activo" href="peliculas.php">Gestión de Películas</a>
      <a href="generos.php">Gestión de Géneros</a>
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
      <p class="ruta chico"><a href="peliculas.php">Gestión de Películas</a> / <span><?= $editando ? 'Editar película' : 'Nueva película' ?></span></p>
      <div class="admin-titulo">
        <div>
          <h1><?= $editando ? 'Editar película' : 'Nueva película' ?></h1>
          <p class="texto-suave">
            <?= $editando ? 'Modifica los datos de la película con ID ' . $id . '.' : 'Registra un nuevo título en el catálogo de Cine+.' ?>
          </p>
        </div>
        <div class="modal-botones sin-margen">
          <a class="btn btn-secundario" href="peliculas.php">Cancelar</a>
          <!-- form="form-pelicula" hace que este botón envíe el formulario aunque esté fuera de él -->
          <button class="btn btn-primario" type="submit" form="form-pelicula">Guardar película</button>
        </div>
      </div>

      <?php if (count($listaGeneros) === 0 || count($listaFormatos) === 0): ?>
        <!-- Sin géneros o formatos no se puede registrar una película -->
        <p class="alerta-amarilla">
          Antes de registrar películas necesitas al menos un
          <?php if (count($listaGeneros) === 0): ?><a href="generos.php#nuevo">género</a><?php endif; ?>
          <?php if (count($listaGeneros) === 0 && count($listaFormatos) === 0): ?> y un <?php endif; ?>
          <?php if (count($listaFormatos) === 0): ?><a href="formatos.php#nuevo">formato</a><?php endif; ?>.
        </p>
      <?php endif; ?>

      <!-- Lista de errores de validación -->
      <?php if (count($errores) > 0): ?>
        <div class="alerta-error lista-errores">
          <strong>Revisa lo siguiente:</strong>
          <ul>
            <?php foreach ($errores as $error): ?>
              <li><?= e($error) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <!-- enctype multipart/form-data es necesario para enviar archivos -->
      <form class="formulario-admin" id="form-pelicula" method="post" action="pelicula_form.php<?= $editando ? '?id=' . $id : '' ?>" enctype="multipart/form-data">
        <!-- Al editar, el id viaja en un campo oculto -->
        <?php if ($editando): ?>
          <input type="hidden" name="id" value="<?= $id ?>">
        <?php endif; ?>

        <div class="tarjeta">
          <h3>Portada</h3>
          <?php if ($portadaActual !== null): ?>
            <!-- Portada que ya tiene la película -->
            <img class="portada-actual" src="../<?= e($portadaActual) ?>" alt="Portada actual">
            <p class="texto-suave chico centrado">Portada actual. Sube otra solo si quieres cambiarla.</p>
          <?php else: ?>
          <div class="subir-imagen">
            <div class="circulo-subir">&uarr;</div>
            <strong>Sube la portada de la película</strong>
            <span class="texto-suave chico">JPG, PNG o WEBP, máx. 2 MB (opcional)</span>
          </div>
          <?php endif; ?>
          <!-- El input de archivo va dentro de un label con forma de botón -->
          <label class="btn btn-secundario btn-bloque">Seleccionar archivo
            <input type="file" name="portada" accept="image/jpeg,image/png,image/webp" class="archivo-oculto">
          </label>
          <?php if (count($errores) > 0): ?>
            <p class="texto-suave chico">Si elegiste una portada, vuelve a seleccionarla.</p>
          <?php endif; ?>
        </div>

        <div class="tarjeta campos-admin">
          <h3 class="subtitulo-form">Información general</h3>
          <div class="dos-campos">
            <div>
              <label for="titulo">Título *</label>
              <input class="campo" id="titulo" name="titulo" maxlength="150" required placeholder="Ej. El Padrino" value="<?= e($datos['titulo']) ?>">
            </div>
            <div>
              <label for="director">Director *</label>
              <input class="campo" id="director" name="director" maxlength="100" required placeholder="Ej. Francis Ford Coppola" value="<?= e($datos['director']) ?>">
            </div>
          </div>
          <div class="tres-campos">
            <div>
              <label for="anio">Año de estreno</label>
              <input class="campo" id="anio" name="anio" type="number" min="1888" max="<?= (int) date('Y') + 1 ?>" placeholder="AAAA" value="<?= e($datos['anio']) ?>">
            </div>
            <div>
              <label for="genero">Género *</label>
              <select class="campo" id="genero" name="id_genero" required>
                <option value="">Selecciona un género</option>
                <?php foreach ($listaGeneros as $g): ?>
                  <option value="<?= $g['id_genero'] ?>" <?= marcado($datos['id_genero'], $g['id_genero'], 'selected') ?>><?= e($g['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label for="calificacion">Calificación</label>
              <input class="campo" id="calificacion" name="calificacion" type="number" step="0.1" min="0" max="10" placeholder="0.0 a 10.0" value="<?= e($datos['calificacion']) ?>">
            </div>
          </div>
          <label for="sinopsis">Sinopsis</label>
          <textarea class="campo" id="sinopsis" name="sinopsis" rows="3" placeholder="Describe brevemente la película..."><?= e($datos['sinopsis']) ?></textarea>

          <h3 class="subtitulo-form">Venta e inventario</h3>
          <div class="dos-campos">
            <div>
              <label>Formato *</label>
              <!-- Una píldora por cada formato de la tabla Formato -->
              <div class="pildoras">
                <?php foreach ($listaFormatos as $f): ?>
                  <label class="pildora"><input type="radio" name="id_formato" value="<?= $f['id_formato'] ?>" required <?= marcado($datos['id_formato'], $f['id_formato'], 'checked') ?>> <?= e($f['nombre']) ?></label>
                <?php endforeach; ?>
              </div>
            </div>
            <div>
              <label>Estado de publicación</label>
              <div class="pildoras">
                <label class="pildora"><input type="radio" name="estado" value="Publicado" <?= marcado($datos['estado'], 'Publicado', 'checked') ?>> Publicado</label>
                <label class="pildora"><input type="radio" name="estado" value="Borrador" <?= marcado($datos['estado'], 'Borrador', 'checked') ?>> Borrador</label>
              </div>
            </div>
          </div>
          <div class="dos-campos">
            <div>
              <label for="precio">Precio (MXN) *</label>
              <input class="campo" id="precio" name="precio" type="number" step="0.01" min="0" required placeholder="0.00" value="<?= e($datos['precio']) ?>">
            </div>
            <div>
              <label for="existencia">Existencia *</label>
              <input class="campo" id="existencia" name="existencia" type="number" min="0" step="1" required placeholder="0" value="<?= e($datos['existencia']) ?>">
              <span class="texto-suave chico">Unidades disponibles en almacén</span>
            </div>
          </div>
          <p class="texto-suave chico nota-form">Los campos con * son obligatorios. Al guardar, la película se registra en la base de datos y aparece en la tienda si su estado es Publicado.</p>
        </div>

      </form>
    </main>
  </div>

</div>

</body>
</html>
