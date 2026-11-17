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

/*
 * Lee de la base las películas del carrito y lo corrige:
 * - quita las que ya no están publicadas o se agotaron,
 * - baja la cantidad si ya no alcanza la existencia.
 * Regresa [filas, avisos]. Cada fila trae los datos de la película más 'cantidad' y 'total'.
 */
function leerCarrito(mysqli $con): array
{
    $carrito = carrito();
    $filas = [];
    $cambios = [];
    if (count($carrito) === 0) {
        return [$filas, $cambios];
    }

    // Un "?" por cada id del carrito: IN (?, ?, ?)
    $ids = array_keys($carrito);
    $marcas = implode(', ', array_fill(0, count($ids), '?'));
    $consulta = $con->prepare("SELECT p.id_pelicula, p.titulo, p.director, p.anio, p.precio, p.existencia,
                                      p.imagen, p.estado, f.nombre AS formato
                               FROM Pelicula p
                               JOIN Formato f ON f.id_formato = p.id_formato
                               WHERE p.id_pelicula IN ($marcas)");
    $consulta->bind_param(str_repeat('i', count($ids)), ...$ids);
    $consulta->execute();
    $encontradas = [];
    foreach ($consulta->get_result()->fetch_all(MYSQLI_ASSOC) as $p) {
        $encontradas[$p['id_pelicula']] = $p;
    }
    $consulta->close();

    foreach ($carrito as $id => $cantidad) {
        $p = $encontradas[$id] ?? null;
        if ($p === null || $p['estado'] !== 'Publicado' || $p['existencia'] < 1) {
            // Ya no se puede comprar: se quita del carrito
            $cambios[] = ($p['titulo'] ?? 'Una película') . ' ya no está disponible y se quitó de tu carrito.';
            unset($carrito[$id]);
            continue;
        }
        if ($cantidad > $p['existencia']) {
            $cambios[] = 'De ' . $p['titulo'] . ' solo quedan ' . $p['existencia'] . ', así que ajustamos la cantidad.';
            $cantidad = (int) $p['existencia'];
            $carrito[$id] = $cantidad;
        }
        $p['cantidad'] = $cantidad;
        $p['total'] = $cantidad * $p['precio'];
        $filas[] = $p;
    }
    $_SESSION['carrito'] = $carrito;
    return [$filas, $cambios];
}
