<?php
/*
 * Cine+ · Añadir usuario (CRUD de usuarios, parte 1: crear)
 * - El rol sale de la tabla Rol.
 * - Valida los datos, revisa que el correo no esté registrado y guarda con INSERT preparado.
 * - La contraseña nunca se guarda tal cual: se guarda cifrada con password_hash().
 * - La foto (opcional) se guarda en img/perfiles/ con un nombre que inventa el servidor.
 */
require __DIR__ . '/../config/conexion.php';
$con = conectar();

const MAX_FOTO = 1024 * 1024;   // 1 MB
const TIPOS_FOTO = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];
const PAISES = ['México', 'Estados Unidos', 'Otro'];

$listaRoles = $con->query('SELECT id_rol, nombre FROM Rol ORDER BY nombre')->fetch_all(MYSQLI_ASSOC);

// Por defecto se marca el rol "Cliente" si existe
$rolCliente = '';
foreach ($listaRoles as $r) {
    if ($r['nombre'] === 'Cliente') {
        $rolCliente = (string) $r['id_rol'];
    }
}

$datos = [
    'nombre' => '', 'correo' => '', 'telefono' => '', 'pais_region' => '',
    'id_rol' => $rolCliente, 'estado' => 'Activo',
];
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($datos as $campo => $valor) {
        $datos[$campo] = trim($_POST[$campo] ?? '');
    }
    // Las contraseñas no se recortan ni se vuelven a mostrar en el formulario
    $contrasena = $_POST['contrasena'] ?? '';
    $confirmar = $_POST['confirmar'] ?? '';

    // ---------- Validaciones ----------
    if ($datos['nombre'] === '') {
        $errores[] = 'El nombre es obligatorio.';
    } elseif (mb_strlen($datos['nombre']) > 100) {
        $errores[] = 'El nombre no puede tener más de 100 caracteres.';
    }
    if (!filter_var($datos['correo'], FILTER_VALIDATE_EMAIL) || mb_strlen($datos['correo']) > 120) {
        $errores[] = 'Escribe un correo electrónico válido.';
    } else {
        // El correo no se puede repetir (también lo protege el UNIQUE de la tabla)
        $buscar = $con->prepare('SELECT COUNT(*) FROM Usuario WHERE correo = ?');
        $buscar->bind_param('s', $datos['correo']);
        $buscar->execute();
        $buscar->bind_result($existe);
        $buscar->fetch();
        $buscar->close();
        if ($existe > 0) {
            $errores[] = 'Ya hay una cuenta registrada con ese correo.';
        }
    }
    if ($datos['telefono'] !== '' && !preg_match('/^[0-9]{10}$/', $datos['telefono'])) {
        $errores[] = 'El teléfono debe tener 10 dígitos (solo números).';
    }
    if ($datos['pais_region'] !== '' && !in_array($datos['pais_region'], PAISES, true)) {
        $errores[] = 'Selecciona un país de la lista.';
    }
    if (mb_strlen($contrasena) < 8) {
        $errores[] = 'La contraseña debe tener al menos 8 caracteres.';
    } elseif ($contrasena !== $confirmar) {
        $errores[] = 'Las contraseñas no coinciden.';
    }
    $rolValido = false;
    foreach ($listaRoles as $r) {
        if ((string) $r['id_rol'] === $datos['id_rol']) {
            $rolValido = true;
        }
    }
    if (!$rolValido) {
        $errores[] = 'Selecciona un rol válido.';
    }
    if (!in_array($datos['estado'], ['Activo', 'Suspendido'], true)) {
        $errores[] = 'El estado de la cuenta no es válido.';
    }

    // ---------- Foto de perfil (opcional) ----------
    $foto = $_FILES['foto'] ?? null;
    $extension = null;
    if ($foto !== null && $foto['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($foto['error'] === UPLOAD_ERR_INI_SIZE || $foto['error'] === UPLOAD_ERR_FORM_SIZE
            || ($foto['error'] === UPLOAD_ERR_OK && $foto['size'] > MAX_FOTO)) {
            $errores[] = 'La foto no puede pesar más de 1 MB.';
        } elseif ($foto['error'] !== UPLOAD_ERR_OK) {
            $errores[] = 'No se pudo subir la foto. Intenta de nuevo.';
        } else {
            // Se revisa el contenido real del archivo, no la extensión
            $info = getimagesize($foto['tmp_name']);
            $tipo = $info === false ? '' : $info['mime'];
            if (!isset(TIPOS_FOTO[$tipo])) {
                $errores[] = 'La foto debe ser una imagen JPG, PNG o WEBP.';
            } else {
                $extension = TIPOS_FOTO[$tipo];
            }
        }
    }

    // ---------- Guardar ----------
    if (count($errores) === 0) {
        $rutaFoto = null;
        if ($extension !== null) {
            $rutaFoto = 'img/perfiles/perfil_' . bin2hex(random_bytes(8)) . '.' . $extension;
            if (!move_uploaded_file($foto['tmp_name'], __DIR__ . '/../' . $rutaFoto)) {
                $errores[] = 'No se pudo guardar la foto en el servidor.';
            }
        }
    }

    if (count($errores) === 0) {
        // password_hash cifra la contraseña; para revisarla al iniciar sesión se usa password_verify
        $hash = password_hash($contrasena, PASSWORD_DEFAULT);
        $idRol = (int) $datos['id_rol'];
        $telefono = $datos['telefono'] === '' ? null : $datos['telefono'];
        $pais = $datos['pais_region'] === '' ? null : $datos['pais_region'];

        $insertar = $con->prepare('INSERT INTO Usuario
            (id_rol, nombre, correo, contrasena_hash, telefono, pais_region, foto, estado)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $insertar->bind_param('isssssss', $idRol, $datos['nombre'], $datos['correo'], $hash,
            $telefono, $pais, $rutaFoto, $datos['estado']);
        try {
            $insertar->execute();
            $insertar->close();
            header('Location: usuarios.php?creado=1');
            exit;
        } catch (mysqli_sql_exception $ex) {
            // 1062 = valor duplicado: otra persona registró ese correo justo antes
            if ($ex->getCode() === 1062) {
                $errores[] = 'Ya hay una cuenta registrada con ese correo.';
            } else {
                $errores[] = 'No se pudo guardar el usuario: ' . $ex->getMessage();
            }
            // Si se había subido una foto, se borra porque el usuario no se guardó
            if ($rutaFoto !== null && is_file(__DIR__ . '/../' . $rutaFoto)) {
                unlink(__DIR__ . '/../' . $rutaFoto);
            }
        }
    }
}

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
  <title>Añadir usuario · Cine+</title>
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
      <a class="activo" href="usuarios.php">Gestión de Usuarios</a>
      <a href="peliculas.php">Gestión de Películas</a>
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
      <p class="ruta chico"><a href="usuarios.php">Gestión de Usuarios</a> / <span>Añadir usuario</span></p>
      <div class="admin-titulo">
        <div>
          <h1>Añadir usuario</h1>
          <p class="texto-suave">Registra una nueva cuenta desde el área administrativa.</p>
        </div>
        <div class="modal-botones sin-margen">
          <a class="btn btn-secundario" href="usuarios.php">Cancelar</a>
          <button class="btn btn-primario" type="submit" form="form-usuario">Crear usuario</button>
        </div>
      </div>

      <?php if (count($listaRoles) === 0): ?>
        <!-- Sin roles no se puede crear ninguna cuenta -->
        <p class="alerta-amarilla">
          La tabla Rol está vacía. Antes de crear usuarios hay que registrar los roles (Cliente y Administrador) en la base de datos.
        </p>
      <?php endif; ?>

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

      <form class="formulario-admin" id="form-usuario" method="post" action="usuario_form.php" enctype="multipart/form-data">

        <div class="tarjeta centrado">
          <h3>Foto de perfil</h3>
          <div class="foto-vacia">Sin foto</div>
          <p class="texto-suave chico">Opcional. JPG, PNG o WEBP, máx. 1 MB</p>
          <label class="btn btn-secundario btn-bloque">Subir foto
            <input type="file" name="foto" accept="image/jpeg,image/png,image/webp" class="archivo-oculto">
          </label>
        </div>

        <div class="tarjeta campos-admin">
          <h3 class="subtitulo-form">Datos personales</h3>
          <div class="dos-campos">
            <div>
              <label for="nombre">Nombre completo *</label>
              <input class="campo" id="nombre" name="nombre" maxlength="100" required placeholder="Ej. María López" value="<?= e($datos['nombre']) ?>">
            </div>
            <div>
              <label for="correo">Correo electrónico *</label>
              <input class="campo" id="correo" name="correo" type="email" maxlength="120" required placeholder="correo@ejemplo.com" value="<?= e($datos['correo']) ?>">
            </div>
          </div>
          <div class="dos-campos">
            <div>
              <label for="telefono">Teléfono</label>
              <input class="campo" id="telefono" name="telefono" type="tel" maxlength="10" placeholder="10 dígitos" value="<?= e($datos['telefono']) ?>">
            </div>
            <div>
              <label for="pais">País o región</label>
              <select class="campo" id="pais" name="pais_region">
                <option value="">Selecciona un país</option>
                <?php foreach (PAISES as $pais): ?>
                  <option value="<?= e($pais) ?>" <?= marcado($datos['pais_region'], $pais, 'selected') ?>><?= e($pais) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <h3 class="subtitulo-form">Acceso y permisos</h3>
          <!-- Las contraseñas no se vuelven a llenar si hay un error -->
          <div class="dos-campos">
            <div>
              <label for="contrasena">Contraseña *</label>
              <input class="campo" id="contrasena" name="contrasena" type="password" minlength="8" required placeholder="Mínimo 8 caracteres">
            </div>
            <div>
              <label for="confirmar">Confirmar contraseña *</label>
              <input class="campo" id="confirmar" name="confirmar" type="password" minlength="8" required placeholder="Repite la contraseña">
            </div>
          </div>

          <label>Rol *</label>
          <div class="pildoras">
            <?php foreach ($listaRoles as $r): ?>
              <label class="pildora"><input type="radio" name="id_rol" value="<?= $r['id_rol'] ?>" required <?= marcado($datos['id_rol'], $r['id_rol'], 'checked') ?>> <?= e($r['nombre']) ?></label>
            <?php endforeach; ?>
          </div>
          <span class="texto-suave chico">Define a qué pantallas puede entrar el usuario.</span>

          <label>Estado de la cuenta</label>
          <div class="pildoras">
            <label class="pildora"><input type="radio" name="estado" value="Activo" <?= marcado($datos['estado'], 'Activo', 'checked') ?>> Activo</label>
            <label class="pildora"><input type="radio" name="estado" value="Suspendido" <?= marcado($datos['estado'], 'Suspendido', 'checked') ?>> Suspendido</label>
          </div>
          <span class="texto-suave chico">Un usuario suspendido no puede iniciar sesión.</span>

          <p class="texto-suave chico nota-form">Los campos con * son obligatorios. La contraseña se guarda cifrada en la base de datos.</p>
        </div>

      </form>
    </main>
  </div>

</div>

</body>
</html>
