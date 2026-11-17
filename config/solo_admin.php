<?php
/*
 * Cine+ · Protección de las páginas de administrador
 * Se incluye al inicio de cada página de la carpeta admin/ (menos acceso.php).
 * - Sin sesión manda a acceso.php; con sesión de cliente manda al inicio del sitio.
 * - Además revisa en la base que la cuenta siga existiendo, activa y con rol Administrador:
 *   si la suspendieron o le quitaron el rol mientras estaba dentro, se le cierra la sesión.
 * Después de incluirlo, $admin tiene los datos del administrador (id, nombre, correo).
 */
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/sesion.php';

$sesion = usuarioActual();
if ($sesion === null) {
    // Sin sesión: al acceso administrativo
    header('Location: acceso.php?requiere=1');
    exit;
}
if ($sesion['rol'] !== 'Administrador') {
    // Un cliente no tiene nada que hacer aquí: de regreso al sitio
    header('Location: ../index.php');
    exit;
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
    header('Location: acceso.php?requiere=1');
    exit;
}
