<?php
/*
 * Cine+ · Crear cuenta (registro de clientes)
 * - Guarda la cuenta en la tabla Usuario con el rol "Cliente".
 * - Valida en PHP: nombre, correo válido y no registrado, contraseña de 8+ caracteres
 *   igual a la confirmación y aceptación de términos.
 * - La contraseña se guarda cifrada con password_hash().
 */
require __DIR__ . '/config/conexion.php';
$con = conectar();

$nombre = '';
$correo = '';
$errores = [];
$creada = isset($_GET['creada']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $contrasena = $_POST['contrasena'] ?? '';
    $confirmar = $_POST['confirmar'] ?? '';
    $acepta = isset($_POST['terminos']);

    // ---------- Validaciones ----------
    if ($nombre === '') {
        $errores[] = 'Escribe tu nombre.';
    } elseif (mb_strlen($nombre) > 100) {
        $errores[] = 'El nombre no puede tener más de 100 caracteres.';
    }
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || mb_strlen($correo) > 120) {
        $errores[] = 'Escribe un correo electrónico válido.';
    } else {
        $buscar = $con->prepare('SELECT COUNT(*) FROM Usuario WHERE correo = ?');
        $buscar->bind_param('s', $correo);
        $buscar->execute();
        $buscar->bind_result($existe);
        $buscar->fetch();
        $buscar->close();
        if ($existe > 0) {
            $errores[] = 'Ya hay una cuenta con ese correo. ¿Quieres iniciar sesión?';
        }
    }
    if (mb_strlen($contrasena) < 8) {
        $errores[] = 'La contraseña debe tener al menos 8 caracteres.';
    } elseif ($contrasena !== $confirmar) {
        $errores[] = 'Las contraseñas no coinciden.';
    }
    if (!$acepta) {
        $errores[] = 'Debes aceptar los Términos y condiciones y el Aviso de privacidad.';
    }

    // Las cuentas que se crean aquí siempre son de cliente (nunca administrador)
    $rol = $con->query("SELECT id_rol FROM Rol WHERE nombre = 'Cliente'")->fetch_row();
    if ($rol === null) {
        $errores[] = 'Por el momento no se pueden crear cuentas. Intenta más tarde.';
    }

    // ---------- Guardar ----------
    if (count($errores) === 0) {
        $idRol = (int) $rol[0];
        $hash = password_hash($contrasena, PASSWORD_DEFAULT);
        $insertar = $con->prepare("INSERT INTO Usuario (id_rol, nombre, correo, contrasena_hash, estado)
                                   VALUES (?, ?, ?, ?, 'Activo')");
        $insertar->bind_param('isss', $idRol, $nombre, $correo, $hash);
        try {
            $insertar->execute();
            $insertar->close();
            // Se redirige para que al recargar no se cree otra vez la cuenta
            header('Location: registro.php?creada=1');
            exit;
        } catch (mysqli_sql_exception $ex) {
            // 1062 = correo duplicado (por si alguien lo registró al mismo tiempo)
            $errores[] = $ex->getCode() === 1062
                ? 'Ya hay una cuenta con ese correo. ¿Quieres iniciar sesión?'
                : 'No se pudo crear la cuenta. Intenta de nuevo.';
        }
    }
}
?>
<?php
$titulo = 'Crear cuenta';
include __DIR__ . '/includes/encabezado.php';
?>

<!-- Formulario para crear cuenta: se envía por POST a esta misma página -->
<section class="seccion">
  <div class="caja-formulario tarjeta">
    <a class="chico" href="login.php">&larr; Volver</a>
    <h2>Crear cuenta</h2>

    <?php if ($creada): ?>
      <!-- Mensaje después de registrarse -->
      <p class="alerta-exito">¡Tu cuenta se creó correctamente! Ya puedes iniciar sesión con tu correo y contraseña.</p>
      <a class="btn btn-primario btn-bloque" href="login.php">Iniciar sesión</a>
    <?php else: ?>
    <p class="texto-suave chico">Regístrate para comprar y guardar tus pedidos.</p>

    <?php if (count($errores) > 0): ?>
      <div class="alerta-error lista-errores">
        <ul>
          <?php foreach ($errores as $error): ?>
            <li><?= e($error) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" action="registro.php">
      <label for="nombre">Nombre completo</label>
      <input class="campo" type="text" id="nombre" name="nombre" maxlength="100" required placeholder="Tu nombre" value="<?= e($nombre) ?>">

      <label for="correo">Correo electrónico</label>
      <input class="campo" type="email" id="correo" name="correo" maxlength="120" required placeholder="nombre@correo.com" value="<?= e($correo) ?>">

      <!-- Las contraseñas nunca se vuelven a llenar -->
      <label for="contrasena">Contraseña</label>
      <input class="campo" type="password" id="contrasena" name="contrasena" minlength="8" required placeholder="Mínimo 8 caracteres">

      <label for="confirmar">Confirmar contraseña</label>
      <input class="campo" type="password" id="confirmar" name="confirmar" minlength="8" required placeholder="Repite tu contraseña">

      <!-- Casilla para aceptar términos (obligatoria) -->
      <label class="casilla chico">
        <input type="checkbox" name="terminos" value="1" required> Acepto los <a href="#">Términos y condiciones</a> y el <a href="#">Aviso de privacidad</a> de Cine+
      </label>

      <button class="btn btn-primario btn-bloque" type="submit">Registrarse</button>
    </form>

    <div class="separador"><span>o</span></div>

    <!-- El registro con Google no forma parte del proyecto por ahora -->
    <button class="btn btn-secundario btn-bloque" type="button" disabled title="Próximamente">Registrarse con Google (próximamente)</button>

    <p class="cambio chico">¿Ya tienes una cuenta? <a href="login.php">Inicia sesión</a></p>
    <?php endif; ?>
  </div>
</section>

<?php include __DIR__ . '/includes/pie.php'; ?>
