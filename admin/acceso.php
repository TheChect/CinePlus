<?php
/*
 * Cine+ · Acceso administrativo
 * Usa las mismas funciones que login.php, pero solo deja entrar cuentas con rol Administrador.
 * Si todavía no existe ningún administrador activo (sistema recién instalado), muestra un
 * formulario para crear la primera cuenta de administrador. En cuanto existe uno, desaparece.
 */
require __DIR__ . '/../config/conexion.php';
require __DIR__ . '/../config/sesion.php';

// Un administrador que ya inició sesión pasa directo al panel
$actual = usuarioActual();
if ($actual !== null && $actual['rol'] === 'Administrador') {
    header('Location: panel.php');
    exit;
}

$correo = '';
$error = '';
$con = conectar();

// ¿Hay al menos un administrador activo?
$hayAdmin = $con->query("SELECT COUNT(*) FROM Usuario u JOIN Rol r ON r.id_rol = u.id_rol
                         WHERE r.nombre = 'Administrador' AND u.estado = 'Activo'")->fetch_row()[0] > 0;

// ---------- Primera cuenta de administrador (solo si no hay ninguno) ----------
$nombre = '';
$errores = [];
if (!$hayAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'primer_admin') {
    $nombre = trim($_POST['nombre'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $contrasena = $_POST['contrasena'] ?? '';
    $confirmar = $_POST['confirmar'] ?? '';

    if ($nombre === '' || mb_strlen($nombre) > 100) {
        $errores[] = 'Escribe tu nombre (máximo 100 caracteres).';
    }
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || mb_strlen($correo) > 120) {
        $errores[] = 'Escribe un correo electrónico válido.';
    }
    if (mb_strlen($contrasena) < 8) {
        $errores[] = 'La contraseña debe tener al menos 8 caracteres.';
    } elseif ($contrasena !== $confirmar) {
        $errores[] = 'Las contraseñas no coinciden.';
    }
    $rol = $con->query("SELECT id_rol FROM Rol WHERE nombre = 'Administrador'")->fetch_row();
    if ($rol === null) {
        $errores[] = 'Falta el rol Administrador en la base de datos.';
    }

    if (count($errores) === 0) {
        $idRol = (int) $rol[0];
        $hash = password_hash($contrasena, PASSWORD_DEFAULT);
        $insertar = $con->prepare("INSERT INTO Usuario (id_rol, nombre, correo, contrasena_hash, estado)
                                   VALUES (?, ?, ?, ?, 'Activo')");
        $insertar->bind_param('isss', $idRol, $nombre, $correo, $hash);
        try {
            $insertar->execute();
            $insertar->close();
            // Se inicia sesión con la cuenta recién creada y se entra al panel
            $error = '';
            $usuario = verificarCredenciales($con, $correo, $contrasena, $error);
            iniciarSesion($con, $usuario);
            header('Location: panel.php');
            exit;
        } catch (mysqli_sql_exception $ex) {
            $errores[] = $ex->getCode() === 1062
                ? 'Ese correo ya está registrado (como cliente). Usa otro correo para el administrador.'
                : 'No se pudo crear la cuenta: ' . $ex->getMessage();
        }
    }
}

// ---------- Inicio de sesión normal ----------
if ($hayAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = trim($_POST['correo'] ?? '');
    $contrasena = $_POST['contrasena'] ?? '';

    if ($correo === '' || $contrasena === '') {
        $error = 'Escribe tu correo y tu contraseña.';
    } else {
        $usuario = verificarCredenciales($con, $correo, $contrasena, $error);
        if ($usuario !== null && $usuario['rol'] !== 'Administrador') {
            // La contraseña es correcta, pero es un cliente: no puede entrar al panel
            $error = 'Esta cuenta no tiene acceso al panel administrativo.';
            $usuario = null;
        }
        if ($usuario !== null) {
            iniciarSesion($con, $usuario);
            header('Location: panel.php');
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

    <?php if (!$hayAdmin): ?>
      <!-- Sistema recién instalado: todavía no hay administradores -->
      <p class="alerta-amarilla chico">Todavía no hay ninguna cuenta de administrador. Crea la primera para entrar al panel.</p>
      <?php if (count($errores) > 0): ?>
        <div class="alerta-error chico lista-errores">
          <ul>
            <?php foreach ($errores as $err): ?>
              <li><?= e($err) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
      <form method="post" action="acceso.php">
        <input type="hidden" name="accion" value="primer_admin">
        <label for="nombre">Nombre completo</label>
        <input class="campo" type="text" id="nombre" name="nombre" maxlength="100" required value="<?= e($nombre) ?>">
        <label for="usuario">Correo</label>
        <input class="campo" type="email" id="usuario" name="correo" maxlength="120" required value="<?= e($correo) ?>">
        <label for="contrasena">Contraseña</label>
        <input class="campo" type="password" id="contrasena" name="contrasena" minlength="8" required placeholder="Mínimo 8 caracteres">
        <label for="confirmar">Confirmar contraseña</label>
        <input class="campo" type="password" id="confirmar" name="confirmar" minlength="8" required>
        <button class="btn btn-primario btn-bloque" type="submit">Crear administrador y entrar</button>
      </form>
    <?php else: ?>
    <?php if (isset($_GET['requiere']) && $error === ''): ?>
      <p class="alerta-amarilla chico">Inicia sesión como administrador para entrar al panel.</p>
    <?php endif; ?>
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
    <?php endif; ?>

    <p class="cambio chico"><a href="../index.html">&larr; Volver al sitio</a></p>
  </div>

  <p class="pie-copy centrado">© 2026 Cine+. Acceso exclusivo para administradores.</p>

</body>
</html>
