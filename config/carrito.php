<?php
/*
 * Cine+ · Funciones del carrito
 * El carrito vive en la sesión de PHP (no en una tabla):
 *   $_SESSION['carrito'] = [ id_pelicula => cantidad, ... ]
 * No hace falta haber iniciado sesión con una cuenta para usarlo.
 */
require_once __DIR__ . '/sesion.php';   // abre la sesión de PHP

// Regresa el carrito como arreglo id => cantidad
function carrito(): array
{
    return $_SESSION['carrito'] ?? [];
}

// Total de artículos (suma de cantidades) para mostrarlo en el menú
function articulosEnCarrito(): int
{
    return array_sum(carrito());
}

// Busca una película publicada (las de Borrador no se pueden comprar)
function peliculaPublicada(mysqli $con, int $id): ?array
{
    $consulta = $con->prepare("SELECT id_pelicula, titulo, existencia FROM Pelicula
                               WHERE id_pelicula = ? AND estado = 'Publicado'");
    $consulta->bind_param('i', $id);
    $consulta->execute();
    $fila = $consulta->get_result()->fetch_assoc();
    $consulta->close();
    return $fila ?: null;
}

// Color de la etiqueta según el nombre del formato (usa las clases f-4k, f-bluray, f-dvd...)
function claseFormato(string $formato): string
{
    $nombre = mb_strtolower($formato);
    if (str_contains($nombre, '4k')) {
        return 'f-4k';
    }
    if (str_contains($nombre, 'blu')) {
        return 'f-bluray';
    }
    if (str_contains($nombre, 'dvd')) {
        return 'f-dvd';
    }
    return 'f-especial';
}
