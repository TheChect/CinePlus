<?php
/*
 * Cine+ · Acceso administrativo
 * Usa las mismas funciones que login.php, pero solo deja entrar cuentas con rol Administrador.
 */
require __DIR__ . '/../config/conexion.php';
require __DIR__ . '/../config/sesion.php';

// Un administrador que ya inició sesión pasa directo al panel
$actual = usuarioActual();
if ($actual !== null && $actual['rol'] === 'Administrador') {
    header('Location: panel.html');
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
        if ($usuario !== null && $usuario['rol'] !== 'Administrador') {
            // La contraseña es correcta, pero es un cliente: no puede entrar al panel
            $error = 'Esta cuenta no tiene acceso al panel administrativo.';
            $usuario = null;
        }
        if ($usuario !== null) {
            iniciarSesion($con, $usuario);
            header('Location: panel.html');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<!-- Cine+ · Acceso administrativo (solo administradores) -->
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Acceso administrativo · Cine+</title>
  <!-- Los estilos están una carpeta arriba, por eso se usa ../ -->
  <link rel="stylesheet" href="../css/estilos.css">
  <link rel="stylesheet" href="../css/admin.css">
</head>
<body class="acceso-admin">

  <div class="caja-formulario tarjeta">
    <div class="acceso-logo">
      <span class="logo-cuadro"></span>
    </div>
    <h2 class="centrado">Panel administrativo</h2>
    <p class="texto-suave chico centrado">Ingresa tus credenciales para gestionar la plataforma.</p>

    <?php if ($error !== ''): ?>
      <p class="alerta-error chico"><?= e($error) ?></p>
    <?php endif; ?>

    <form method="post" action="acceso.php">
      <label for="usuario">Correo</label>
      <input class="campo" type="email" id="usuario" name="correo" required placeholder="admin@cineplus.com" value="<?= e($correo) ?>">

      <label for="contrasena">Contraseña</label>
      <input class="campo" type="password" id="contrasena" name="contrasena" required placeholder="••••••••">

      <button class="btn btn-primario btn-bloque" type="submit">Iniciar sesión</button>
    </form>

    <p class="cambio chico"><a href="../index.html">&larr; Volver al sitio</a></p>
  </div>

  <p class="pie-copy centrado">© 2026 Cine+. Acceso exclusivo para administradores.</p>

</body>
</html>
