<?php
/*
 * Cine+ · Estadísticas del panel de administración
 * Todo se calcula EN VIVO con consultas SELECT sobre las tablas (no hay vistas ni tablas extra).
 * La usan admin/panel.php (al cargar la página) y admin/estadisticas.php (JSON para el fetch cada 10 s).
 * Los pedidos con estado "Cancelado" no cuentan como venta.
 */

const MESES_CORTOS = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
const MESES_LARGOS = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto',
                      'septiembre', 'octubre', 'noviembre', 'diciembre'];
const POCA_EXISTENCIA_PANEL = 5;

// Ejecuta una consulta preparada y regresa todas las filas
function filas(mysqli $con, string $sql, string $tipos = '', array $valores = []): array
{
    $consulta = $con->prepare($sql);
    if ($tipos !== '') {
        $consulta->bind_param($tipos, ...$valores);
    }
    $consulta->execute();
    $resultado = $consulta->get_result()->fetch_all(MYSQLI_ASSOC);
    $consulta->close();
    return $resultado;
}

// Porcentaje seguro (si el total es 0 regresa 0, así nunca se divide entre cero)
function porcentaje(float $parte, float $total): int
{
    return $total > 0 ? (int) round($parte * 100 / $total) : 0;
}

function calcularEstadisticas(mysqli $con): array
{
    // Rangos de fechas: [inicio, fin) del mes actual y del anterior
    $inicioMes = date('Y-m-01 00:00:00');
    $inicioSiguiente = date('Y-m-01 00:00:00', strtotime('first day of next month'));
    $inicioAnterior = date('Y-m-01 00:00:00', strtotime('first day of last month'));

    // ---------- Ingresos y pedidos del mes y del mes anterior ----------
    $sqlMes = "SELECT COUNT(*) AS pedidos, COALESCE(SUM(total), 0) AS ingresos
               FROM Pedido WHERE estado <> 'Cancelado' AND fecha >= ? AND fecha < ?";
    $mes = filas($con, $sqlMes, 'ss', [$inicioMes, $inicioSiguiente])[0];
    $anterior = filas($con, $sqlMes, 'ss', [$inicioAnterior, $inicioMes])[0];

    $ingresosMes = (float) $mes['ingresos'];
    $ingresosAnterior = (float) $anterior['ingresos'];
    // Variación en %: si el mes anterior fue 0 no se puede calcular (null)
    $variacion = $ingresosAnterior > 0 ? (int) round(($ingresosMes - $ingresosAnterior) * 100 / $ingresosAnterior) : null;

    // ---------- Clientes ----------
    $clientes = filas($con, "SELECT COUNT(*) AS total, COALESCE(SUM(u.fecha_registro >= ?), 0) AS nuevos
                             FROM Usuario u JOIN Rol r ON r.id_rol = u.id_rol
                             WHERE r.nombre = 'Cliente'", 's', [$inicioMes])[0];

    // ---------- Películas ----------
    $peliculas = filas($con, "SELECT COUNT(*) AS registradas, COALESCE(SUM(estado = 'Publicado'), 0) AS publicadas
                              FROM Pelicula")[0];

    // ---------- Ingresos por mes (últimos 6 meses, incluido el actual) ----------
    $inicio6 = date('Y-m-01 00:00:00', strtotime('first day of -5 month'));
    $porMes = [];
    foreach (filas($con, "SELECT DATE_FORMAT(fecha, '%Y-%m') AS mes, SUM(total) AS ingresos
                          FROM Pedido WHERE estado <> 'Cancelado' AND fecha >= ?
                          GROUP BY DATE_FORMAT(fecha, '%Y-%m')", 's', [$inicio6]) as $f) {
        $porMes[$f['mes']] = (float) $f['ingresos'];
    }
    $meses = [];
    for ($i = 5; $i >= 0; $i--) {
        $t = strtotime("first day of -$i month");
        $clave = date('Y-m', $t);
        $meses[] = [
            'mes' => MESES_CORTOS[date('n', $t) - 1],
            'ingresos' => $porMes[$clave] ?? 0.0,
            'actual' => $i === 0,
        ];
    }
    // Altura de cada barra: el mes más alto ocupa el 85% del espacio
    $maximo = max(array_column($meses, 'ingresos'));
    foreach ($meses as &$m) {
        $m['altura'] = $maximo > 0 ? (int) round($m['ingresos'] * 85 / $maximo) : 0;
    }
    unset($m);

    // ---------- Top 5 películas más vendidas del mes (unidades) ----------
    $top = filas($con, "SELECT pe.titulo, f.nombre AS formato, SUM(d.cantidad) AS unidades
                        FROM Detalle_Pedido d
                        JOIN Pedido p   ON p.id_pedido = d.id_pedido
                        JOIN Pelicula pe ON pe.id_pelicula = d.id_pelicula
                        JOIN Formato f  ON f.id_formato = pe.id_formato
                        WHERE p.estado <> 'Cancelado' AND p.fecha >= ? AND p.fecha < ?
                        GROUP BY pe.id_pelicula, pe.titulo, f.nombre
                        ORDER BY unidades DESC, pe.titulo
                        LIMIT 5", 'ss', [$inicioMes, $inicioSiguiente]);
    $maxTop = $top ? (int) $top[0]['unidades'] : 0;
    foreach ($top as &$t) {
        $t['unidades'] = (int) $t['unidades'];
        $t['ancho'] = porcentaje($t['unidades'], $maxTop);   // ancho de la barra de progreso
    }
    unset($t);

    // ---------- Ventas por formato del mes (% de unidades) ----------
    $formatos = filas($con, "SELECT f.nombre AS formato, SUM(d.cantidad) AS unidades
                             FROM Detalle_Pedido d
                             JOIN Pedido p   ON p.id_pedido = d.id_pedido
                             JOIN Pelicula pe ON pe.id_pelicula = d.id_pelicula
                             JOIN Formato f  ON f.id_formato = pe.id_formato
                             WHERE p.estado <> 'Cancelado' AND p.fecha >= ? AND p.fecha < ?
                             GROUP BY f.id_formato, f.nombre
                             ORDER BY unidades DESC", 'ss', [$inicioMes, $inicioSiguiente]);
    $totalUnidades = array_sum(array_column($formatos, 'unidades'));
    foreach ($formatos as &$f) {
        $f['unidades'] = (int) $f['unidades'];
        $f['porcentaje'] = porcentaje($f['unidades'], $totalUnidades);
    }
    unset($f);

    // ---------- Películas con poca existencia (publicadas, 5 o menos) ----------
    $poca = filas($con, "SELECT pe.id_pelicula, pe.titulo, f.nombre AS formato, pe.existencia
                         FROM Pelicula pe JOIN Formato f ON f.id_formato = pe.id_formato
                         WHERE pe.estado = 'Publicado' AND pe.existencia <= ?
                         ORDER BY pe.existencia, pe.titulo
                         LIMIT 8", 'i', [POCA_EXISTENCIA_PANEL]);
    foreach ($poca as &$p) {
        $p['id_pelicula'] = (int) $p['id_pelicula'];
        $p['existencia'] = (int) $p['existencia'];
    }
    unset($p);

    return [
        'periodo' => MESES_LARGOS[date('n') - 1] . ' ' . date('Y'),
        'mesAnterior' => MESES_LARGOS[date('n', strtotime('first day of last month')) - 1],
        'ingresosMes' => $ingresosMes,
        'variacionIngresos' => $variacion,
        'pedidosMes' => (int) $mes['pedidos'],
        'diferenciaPedidos' => (int) $mes['pedidos'] - (int) $anterior['pedidos'],
        'clientes' => (int) $clientes['total'],
        'clientesNuevos' => (int) $clientes['nuevos'],
        'peliculasPublicadas' => (int) $peliculas['publicadas'],
        'peliculasRegistradas' => (int) $peliculas['registradas'],
        'ingresosPorMes' => $meses,
        'topPeliculas' => $top,
        'ventasPorFormato' => $formatos,
        'pocaExistencia' => $poca,
        'generado' => date('H:i:s'),
    ];
}
