<?php
/*
 * Cine+ · Encabezado público
 * Se incluye al inicio de cada página pública.
 * Antes de incluirlo, cada página define:
 *   $titulo  -> texto de la pestaña del navegador
 *   $pagina  -> nombre de la página actual para marcarla en el menú
 */
if (!isset($titulo)) { $titulo = 'Cine+'; }
if (!isset($pagina)) { $pagina = ''; }

// Regresa la clase "activo" si el enlace es de la página actual
function activo($nombre, $pagina) {
    return $nombre === $pagina ? 'activo' : '';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $titulo; ?> · Cine+</title>
  <link rel="stylesheet" href="css/estilos.css">
</head>
<body>

<!-- Barra de navegación -->
<header class="encabezado">
  <div class="contenedor encabezado-contenido">
    <a class="logo" href="index.php">
      <span class="logo-cuadro"></span> CINE+
    </a>

    <nav class="menu">
      <a class="<?php echo activo('inicio', $pagina); ?>" href="index.php">Inicio</a>
      <a class="<?php echo activo('tienda', $pagina); ?>" href="tienda.php">Catálogo</a>
      <a class="<?php echo activo('acerca', $pagina); ?>" href="acerca.php">Acerca de nosotros</a>
      <a class="<?php echo activo('contacto', $pagina); ?>" href="contacto.php">Contacto</a>
    </nav>

    <div class="menu-cuenta">
      <a href="login.php">Iniciar sesión</a>
      <a class="btn btn-primario" href="registro.php">Suscribirse</a>
    </div>
  </div>
</header>

<main>
