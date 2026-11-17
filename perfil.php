<?php
/*
 * Cine+ · Mi perfil
 * - Solo para personas con sesión iniciada (si no, manda a login.php).
 * - Muestra y deja editar nombre, correo, teléfono y país o región de la propia cuenta.
 * - Cambiar contraseña pide la contraseña actual antes de guardar la nueva.
 * - Siempre se usa el id de la sesión: nadie puede editar la cuenta de otra persona.
 */
require __DIR__ . '/config/conexion.php';
require __DIR__ . '/config/sesion.php';

$sesion = usuarioActual();
if ($sesion === null) {
    header('Location: login.php');
    exit;
}

$con = conectar();
const PAISES = ['México', 'Estados Unidos', 'Otro'];

// Datos actuales de la cuenta
function buscarCuenta(mysqli $con, int $id): ?array
{
    $consulta = $con->prepare('SELECT nombre, correo, telefono, pais_region, foto, fecha_registro, contrasena_hash
                               FROM Usuario WHERE id_usuario = ?');
    $consulta->bind_param('i', $id);
    $consulta->execute();
    $fila = $consulta->get_result()->fetch_assoc();
    $consulta->close();
    return $fila ?: null;
}

$cuenta = buscarCuenta($con, $sesion['id']);
if ($cuenta === null) {
    // La cuenta ya no existe (la borraron): se cierra la sesión
    cerrarSesion();
    header('Location: login.php');
    exit;
}

$datos = [
    'nombre' => $cuenta['nombre'],
    'correo' => $cuenta['correo'],
    'telefono' => $cuenta['telefono'] ?? '',
    'pais_region' => $cuenta['pais_region'] ?? '',
];
$errores = [];
$erroresClave = [];
$accion = $_POST['accion'] ?? '';

// ---------- Guardar datos personales ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'datos') {
    foreach ($datos as $campo => $valor) {
        $datos[$campo] = trim($_POST[$campo] ?? '');
    }

    if ($datos['nombre'] === '' || mb_strlen($datos['nombre']) > 100) {
        $errores[] = 'Escribe tu nombre (máximo 100 caracteres).';
    }
    if (!filter_var($datos['correo'], FILTER_VALIDATE_EMAIL) || mb_strlen($datos['correo']) > 120) {
        $errores[] = 'Escribe un correo electrónico válido.';
    } else {
        // El correo no puede ser de otra cuenta (la propia no cuenta)
        $buscar = $con->prepare('SELECT COUNT(*) FROM Usuario WHERE correo = ? AND id_usuario <> ?');
        $buscar->bind_param('si', $datos['correo'], $sesion['id']);
        $buscar->execute();
        $buscar->bind_result($existe);
        $buscar->fetch();
        $buscar->close();
        if ($existe > 0) {
            $errores[] = 'Ese correo ya lo usa otra cuenta.';
        }
    }
    if ($datos['telefono'] !== '' && !preg_match('/^[0-9]{10}$/', $datos['telefono'])) {
        $errores[] = 'El teléfono debe tener 10 dígitos (solo números).';
    }
    if ($datos['pais_region'] !== '' && !in_array($datos['pais_region'], PAISES, true)) {
        $errores[] = 'Selecciona un país de la lista.';
    }

    if (count($errores) === 0) {
        $telefono = $datos['telefono'] === '' ? null : $datos['telefono'];
        $pais = $datos['pais_region'] === '' ? null : $datos['pais_region'];
        $actualizar = $con->prepare('UPDATE Usuario SET nombre = ?, correo = ?, telefono = ?, pais_region = ?
                                     WHERE id_usuario = ?');
        $actualizar->bind_param('ssssi', $datos['nombre'], $datos['correo'], $telefono, $pais, $sesion['id']);
        try {
            $actualizar->execute();
            $actualizar->close();
            // El nombre del menú sale de la sesión: se actualiza también ahí
            $_SESSION['usuario']['nombre'] = $datos['nombre'];
            header('Location: perfil.php?guardado=1');
            exit;
        } catch (mysqli_sql_exception $ex) {
            $errores[] = $ex->getCode() === 1062 ? 'Ese correo ya lo usa otra cuenta.' : 'No se pudieron guardar los cambios.';
        }
    }
}

// ---------- Cambiar contraseña ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'contrasena') {
    $actual = $_POST['actual'] ?? '';
    $nueva = $_POST['nueva'] ?? '';
    $confirmar = $_POST['confirmar'] ?? '';

    if (!password_verify($actual, $cuenta['contrasena_hash'])) {
        $erroresClave[] = 'Tu contraseña actual no es correcta.';
    }
    if (mb_strlen($nueva) < 8) {
        $erroresClave[] = 'La nueva contraseña debe tener al menos 8 caracteres.';
    } elseif ($nueva !== $confirmar) {
        $erroresClave[] = 'Las contraseñas nuevas no coinciden.';
    }

    if (count($erroresClave) === 0) {
        $hash = password_hash($nueva, PASSWORD_DEFAULT);
        $actualizar = $con->prepare('UPDATE Usuario SET contrasena_hash = ? WHERE id_usuario = ?');
        $actualizar->bind_param('si', $hash, $sesion['id']);
        $actualizar->execute();
        $actualizar->close();
        header('Location: perfil.php?clave=1');
        exit;
    }
}

// Fecha de registro en español: "Miembro desde noviembre de 2026"
$meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto',
          'septiembre', 'octubre', 'noviembre', 'diciembre'];
$t = strtotime($cuenta['fecha_registro']);
$miembroDesde = $meses[date('n', $t) - 1] . ' de ' . date('Y', $t);

$titulo = 'Mi perfil';
include __DIR__ . '/includes/encabezado.php';
?>

<section class="seccion">
  <div class="caja-formulario tarjeta">
    <a class="chico" href="index.php">&larr; Volver</a>
    <h2>Mi perfil</h2>

    <!-- Foto e información de la cuenta -->
    <div class="perfil-cabecera">
      <?php if (!empty($cuenta['foto'])): ?>
        <img class="perfil-foto" src="<?= e($cuenta['foto']) ?>" alt="Foto de perfil">
      <?php else: ?>
        <div class="perfil-foto perfil-sin-foto"><?= e(mb_strtoupper(mb_substr($cuenta['nombre'], 0, 1))) ?></div>
      <?php endif; ?>
      <strong><?= e($cuenta['nombre']) ?></strong>
      <span class="texto-suave chico">Miembro desde <?= $miembroDesde ?></span>
    </div>

    <?php if (isset($_GET['guardado'])): ?>
      <p class="alerta-exito">Tus datos se guardaron correctamente.</p>
    <?php elseif (isset($_GET['clave'])): ?>
      <p class="alerta-exito">Tu contraseña se cambió correctamente.</p>
    <?php endif; ?>

    <?php if (count($errores) > 0): ?>
      <div class="alerta-error lista-errores">
        <ul>
          <?php foreach ($errores as $error): ?>
            <li><?= e($error) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <!-- Datos personales -->
    <form method="post" action="perfil.php">
      <input type="hidden" name="accion" value="datos">
      <label for="nombre">Nombre completo</label>
      <input class="campo" type="text" id="nombre" name="nombre" maxlength="100" required value="<?= e($datos['nombre']) ?>">

      <label for="correo">Correo electrónico</label>
      <input class="campo" type="email" id="correo" name="correo" maxlength="120" required value="<?= e($datos['correo']) ?>">

      <label for="telefono">Teléfono</label>
      <input class="campo" type="tel" id="telefono" name="telefono" maxlength="10" placeholder="10 dígitos" value="<?= e($datos['telefono']) ?>">

      <label for="pais">País o región</label>
      <select class="campo" id="pais" name="pais_region">
        <option value="">Selecciona un país</option>
        <?php foreach (PAISES as $pais): ?>
          <option value="<?= e($pais) ?>" <?= $datos['pais_region'] === $pais ? 'selected' : '' ?>><?= e($pais) ?></option>
        <?php endforeach; ?>
      </select>

      <button class="btn btn-primario btn-bloque" type="submit">Guardar cambios</button>
    </form>

    <!-- Cambiar contraseña: <details> se abre y se cierra sin JavaScript -->
    <details class="perfil-seccion" <?= count($erroresClave) > 0 ? 'open' : '' ?>>
      <summary>Cambiar contraseña</summary>

      <?php if (count($erroresClave) > 0): ?>
        <div class="alerta-error lista-errores">
          <ul>
            <?php foreach ($erroresClave as $error): ?>
              <li><?= e($error) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="post" action="perfil.php">
        <input type="hidden" name="accion" value="contrasena">
        <label for="actual">Contraseña actual</label>
        <input class="campo" type="password" id="actual" name="actual" required>
        <label for="nueva">Nueva contraseña</label>
        <input class="campo" type="password" id="nueva" name="nueva" minlength="8" required placeholder="Mínimo 8 caracteres">
        <label for="confirmar">Confirmar nueva contraseña</label>
        <input class="campo" type="password" id="confirmar" name="confirmar" minlength="8" required>
        <button class="btn btn-secundario btn-bloque" type="submit">Cambiar contraseña</button>
      </form>
    </details>

    <p class="cambio chico"><a class="rojo" href="logout.php">Cerrar sesión</a></p>
  </div>
</section>

<?php include __DIR__ . '/includes/pie.php'; ?>
