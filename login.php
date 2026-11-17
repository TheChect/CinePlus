<?php
/*
 * Cine+ · Iniciar sesión (clientes y administradores)
 * - Revisa correo y contraseña con password_verify() (funciones en config/sesion.php).
 * - Si entra bien, guarda id, nombre y rol en la sesión y lo manda a su página:
 *   Administrador -> panel, Cliente -> inicio.
 */
require __DIR__ . '/config/conexion.php';
require __DIR__ . '/config/sesion.php';

// Si ya tiene sesión, no tiene caso volver a iniciarla
$actual = usuarioActual();
if ($actual !== null) {
    header('Location: ' . (($_GET['volver'] ?? '') === 'carrito' ? 'carrito.php' : paginaInicioSegunRol($actual['rol'])));
    exit;
}

$correo = '';
$error = '';
// Si viene del carrito ("Inicia sesión para pagar"), después de entrar regresa al carrito
$volverCarrito = ($_GET['volver'] ?? $_POST['volver'] ?? '') === 'carrito';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = trim($_POST['correo'] ?? '');
    $contrasena = $_POST['contrasena'] ?? '';

    if ($correo === '' || $contrasena === '') {
        $error = 'Escribe tu correo y tu contraseña.';
    } else {
        $con = conectar();
        $usuario = verificarCredenciales($con, $correo, $contrasena, $error);
        if ($usuario !== null) {
            iniciarSesion($con, $usuario);
            header('Location: ' . ($volverCarrito ? 'carrito.php' : paginaInicioSegunRol($usuario['rol'])));
            exit;
        }
    }
}
?>
<?php
$titulo = 'Iniciar sesión';
include __DIR__ . '/includes/encabezado.php';
?>

<!-- Formulario de inicio de sesión: se envía por POST a esta misma página -->
<section class="seccion">
  <div class="caja-formulario tarjeta">
    <h2>Inicia sesión</h2>
    <p class="texto-suave chico">Entra a tu cuenta para usar el carrito y comprar.</p>

    <?php if (isset($_GET['salida'])): ?>
      <p class="alerta-exito">Cerraste sesión correctamente.</p>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
      <p class="alerta-error"><?= e($error) ?></p>
    <?php elseif ($volverCarrito): ?>
      <p class="alerta-exito">Inicia sesión para terminar tu compra. Tu carrito se conserva.</p>
    <?php endif; ?>

    <form method="post" action="login.php">
      <?php if ($volverCarrito): ?>
        <input type="hidden" name="volver" value="carrito">
      <?php endif; ?>
      <label for="correo">Correo electrónico</label>
      <input class="campo" type="email" id="correo" name="correo" required placeholder="nombre@correo.com" value="<?= e($correo) ?>">

      <label for="contrasena">Contraseña</label>
      <input class="campo" type="password" id="contrasena" name="contrasena" required placeholder="••••••••">

      <a class="olvide chico" href="contacto.php">¿Olvidaste tu contraseña?</a>

      <button class="btn btn-primario btn-bloque" type="submit">Iniciar sesión</button>
    </form>

    <!-- Línea separadora con la letra "o" en medio -->
    <div class="separador"><span>o</span></div>

    <!-- El inicio con Google no forma parte del proyecto por ahora -->
    <button class="btn btn-secundario btn-bloque" type="button" disabled title="Próximamente">Continuar con Google (próximamente)</button>

    <p class="cambio chico">¿No tienes una cuenta? <a href="registro.php">Regístrate</a></p>
  </div>
</section>

<?php include __DIR__ . '/includes/pie.php'; ?>
