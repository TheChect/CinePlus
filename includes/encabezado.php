<?php
/*
 * Cine+ · Encabezado de las páginas públicas
 * Antes de incluirlo, cada página define:
 *   $titulo -> texto de la pestaña del navegador
 *   $pagina -> 'inicio', 'tienda', 'acerca' o 'contacto' para marcar el enlace activo del menú
 * El lado derecho del menú cambia según la sesión:
 *   - sin sesión: Iniciar sesión y Suscribirse
 *   - cliente: su nombre y Cerrar sesión
 *   - administrador: su nombre, Panel y Cerrar sesión
 */
require_once __DIR__ . '/../config/conexion.php';   // trae la función e() para escapar texto
require_once __DIR__ . '/../config/sesion.php';

$titulo = $titulo ?? 'Cine+';
$pagina = $pagina ?? '';
$usuario = usuarioActual();

// Regresa ' class="activo"' si el enlace es el de la página actual
function claseActivo(string $nombre, string $pagina): string
{
    return $nombre === $pagina ? ' class="activo"' : '';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($titulo) ?> · Cine+</title>
  <link rel="stylesheet" href="css/estilos.css">
</head>
<body>

<!-- Encabezado: el mismo en todas las páginas públicas -->
<header class="encabezado">
  <div class="contenedor encabezado-contenido">
    <a class="logo" href="index.php">
      <span class="logo-cuadro"></span> CINE+
    </a>

    <nav class="menu">
      <a<?= claseActivo('inicio', $pagina) ?> href="index.php">Inicio</a>
      <a<?= claseActivo('tienda', $pagina) ?> href="tienda.php">Catálogo</a>
      <a<?= claseActivo('acerca', $pagina) ?> href="acerca.php">Acerca de nosotros</a>
      <a<?= claseActivo('contacto', $pagina) ?> href="contacto.php">Contacto</a>
    </nav>

    <div class="menu-cuenta">
      <?php if ($usuario === null): ?>
        <a href="login.php">Iniciar sesión</a>
        <a class="btn btn-primario" href="registro.php">Suscribirse</a>
      <?php else: ?>
        <span class="menu-usuario">Hola, <?= e($usuario['nombre']) ?></span>
        <?php if ($usuario['rol'] === 'Administrador'): ?>
          <a class="btn btn-primario" href="admin/panel.php">Panel</a>
        <?php endif; ?>
        <a href="logout.php">Cerrar sesión</a>
      <?php endif; ?>
    </div>
  </div>
</header>

<main>
