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
    header('Location: ' . paginaInicioSegunRol($actual['rol']));
    exit;
}

$correo = '';
$error = '';

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
            header('Location: ' . paginaInicioSegunRol($usuario['rol']));
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<!-- Cine+ · Iniciar sesión -->
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Iniciar sesión · Cine+</title>
  <link rel="stylesheet" href="css/estilos.css">
</head>
<body>

<!-- Encabezado: es igual en todas las páginas; solo cambia el enlace con class="activo" -->
<header class="encabezado">
  <div class="contenedor encabezado-contenido">
    <a class="logo" href="index.html">
      <span class="logo-cuadro"></span> CINE+
    </a>

    <nav class="menu">
      <a href="index.html">Inicio</a>
      <a href="tienda.html">Catálogo</a>
      <a href="acerca.html">Acerca de nosotros</a>
      <a href="contacto.html">Contacto</a>
    </nav>

    <div class="menu-cuenta">
      <a href="login.php">Iniciar sesión</a>
      <a class="btn btn-primario" href="registro.php">Suscribirse</a>
    </div>
  </div>
</header>

<main>

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
    <?php endif; ?>

    <form method="post" action="login.php">
      <label for="correo">Correo electrónico</label>
      <input class="campo" type="email" id="correo" name="correo" required placeholder="nombre@correo.com" value="<?= e($correo) ?>">

      <label for="contrasena">Contraseña</label>
      <input class="campo" type="password" id="contrasena" name="contrasena" required placeholder="••••••••">

      <a class="olvide chico" href="contacto.html">¿Olvidaste tu contraseña?</a>

      <button class="btn btn-primario btn-bloque" type="submit">Iniciar sesión</button>
    </form>

    <!-- Línea separadora con la letra "o" en medio -->
    <div class="separador"><span>o</span></div>

    <!-- El inicio con Google no forma parte del proyecto por ahora -->
    <button class="btn btn-secundario btn-bloque" type="button" disabled title="Próximamente">Continuar con Google (próximamente)</button>

    <p class="cambio chico">¿No tienes una cuenta? <a href="registro.php">Regístrate</a></p>
  </div>
</section>

</main>

<!-- Pie de página -->
<footer class="pie">
  <div class="contenedor pie-columnas">
    <div class="pie-marca">
      <a class="logo" href="index.html"><span class="logo-cuadro"></span> CINE+</a>
      <p class="texto-suave">La tienda de cine físico para coleccionistas exigentes.</p>
    </div>

    <div>
      <h4>Navegación</h4>
      <a href="index.html">Inicio</a>
      <a href="tienda.html">Catálogo</a>
      <a href="acerca.html">Acerca de nosotros</a>
      <a href="contacto.html">Contacto</a>
    </div>

    <div>
      <h4>Mi cuenta</h4>
      <a href="login.php">Iniciar sesión</a>
      <a href="registro.php">Crear cuenta</a>
      <a href="carrito.html">Carrito</a>
      <a href="perfil.html">Mi perfil</a>
    </div>

    <div>
      <h4>Soporte</h4>
      <a href="contacto.html">Preguntas frecuentes</a>
      <a href="contacto.html">Envíos y entregas</a>
      <a href="contacto.html">Política de devolución</a>
    </div>
  </div>

  <div class="contenedor">
    <p class="pie-copy">© 2026 Cine+. Proyecto académico FIME · UANL. Todos los derechos reservados.</p>
  </div>
</footer>

</body>
</html>
