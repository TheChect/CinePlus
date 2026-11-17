<?php
/*
 * Cine+ · Protección de las páginas de administrador
 * Se incluye al inicio de cada página de la carpeta admin/ (menos acceso.php).
 * - Sin sesión manda a acceso.php; con sesión de cliente manda al inicio del sitio.
 * - Además revisa en la base que la cuenta siga existiendo, activa y con rol Administrador:
 *   si la suspendieron o le quitaron el rol mientras estaba dentro, se le cierra la sesión.
 * - Si antes de incluirlo se define $respuestaJson = true (lo hace estadisticas.php),
 *   en lugar de redirigir responde un error en JSON (401 sin sesión, 403 sin permiso).
 * Después de incluirlo, $admin tiene los datos del administrador (id, nombre, correo).
 */
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/sesion.php';

// Responde "no autorizado" según el tipo de página y termina
function rechazar(int $codigo, string $redirigir): void
{
    global $respuestaJson;
    if (!empty($respuestaJson)) {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => $codigo === 401 ? 'Inicia sesión como administrador.' : 'Sin permiso.']);
    } else {
        header('Location: ' . $redirigir);
    }
    exit;
}

$sesion = usuarioActual();
if ($sesion === null) {
    // Sin sesión: al acceso administrativo
    rechazar(401, 'acceso.php?requiere=1');
}
if ($sesion['rol'] !== 'Administrador') {
    // Un cliente no tiene nada que hacer aquí: de regreso al sitio
    rechazar(403, '../index.php');
}

// La conexión se guarda en una variable: si no, PHP la cerraría antes de ejecutar la consulta
$conAdmin = conectar();
$revisar = $conAdmin->prepare("SELECT u.id_usuario, u.nombre, u.correo
                            FROM Usuario u JOIN Rol r ON r.id_rol = u.id_rol
                            WHERE u.id_usuario = ? AND u.estado = 'Activo' AND r.nombre = 'Administrador'");
$revisar->bind_param('i', $sesion['id']);
$revisar->execute();
$admin = $revisar->get_result()->fetch_assoc();
$revisar->close();

if ($admin === null) {
    cerrarSesion();
    rechazar(401, 'acceso.php?requiere=1');
}
