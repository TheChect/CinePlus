<?php
/*
 * Cine+ · Estadísticas en JSON para el panel
 * js/panel.js lo pide cada 10 segundos con fetch() y actualiza los números sin recargar la página.
 * Solo para administradores: sin sesión responde 401 y con sesión de cliente 403 (también en JSON).
 */
$respuestaJson = true;   // le dice a solo_admin.php que responda en JSON en vez de redirigir
require __DIR__ . '/../config/solo_admin.php';
require_once __DIR__ . '/../config/estadisticas.php';

header('Content-Type: application/json; charset=utf-8');
// Que el navegador no guarde la respuesta: siempre se quieren los datos más nuevos
header('Cache-Control: no-store');
echo json_encode(calcularEstadisticas(conectar()), JSON_UNESCAPED_UNICODE);
