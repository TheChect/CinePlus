<?php
/*
 * Cine+ · Funciones de sesión
 * Se usa en login.php, admin/acceso.php y logout.php.
 * En $_SESSION solo se guarda lo necesario: id, nombre y rol (nunca la contraseña).
 */

// Abre la sesión si todavía no está abierta
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Regresa los datos del usuario con sesión iniciada, o null si no hay nadie
function usuarioActual(): ?array
{
    return $_SESSION['usuario'] ?? null;
}

// Página a la que va cada rol después de iniciar sesión.
// $prefijo sirve para llamarla desde la raíz ('') o desde la carpeta admin ('../').
function paginaInicioSegunRol(string $rol, string $prefijo = ''): string
{
    return $rol === 'Administrador' ? $prefijo . 'admin/panel.html' : $prefijo . 'index.html';
}

/*
 * Revisa correo y contraseña.
 * - Si son correctos y la cuenta está activa, regresa los datos del usuario.
 * - Si no, regresa null y deja el motivo en $error.
 */
function verificarCredenciales(mysqli $con, string $correo, string $contrasena, string &$error): ?array
{
    $buscar = $con->prepare('SELECT u.id_usuario, u.nombre, u.contrasena_hash, u.estado, r.nombre AS rol
                             FROM Usuario u
                             JOIN Rol r ON r.id_rol = u.id_rol
                             WHERE u.correo = ?');
    $buscar->bind_param('s', $correo);
    $buscar->execute();
    $usuario = $buscar->get_result()->fetch_assoc();
    $buscar->close();

    // Mismo mensaje si el correo no existe o si la contraseña está mal:
    // así nadie puede averiguar qué correos están registrados.
    if ($usuario === null || !password_verify($contrasena, $usuario['contrasena_hash'])) {
        $error = 'Correo o contraseña incorrectos.';
        return null;
    }
    if ($usuario['estado'] !== 'Activo') {
        $error = 'Tu cuenta está suspendida. Escríbenos desde la página de Contacto.';
        return null;
    }
    return $usuario;
}

// Guarda al usuario en la sesión y registra la fecha de su último acceso
function iniciarSesion(mysqli $con, array $usuario): void
{
    // Nuevo identificador de sesión para evitar que alguien reutilice uno viejo
    session_regenerate_id(true);
    $_SESSION['usuario'] = [
        'id'     => (int) $usuario['id_usuario'],
        'nombre' => $usuario['nombre'],
        'rol'    => $usuario['rol'],
    ];

    $acceso = $con->prepare('UPDATE Usuario SET ultimo_acceso = NOW() WHERE id_usuario = ?');
    $acceso->bind_param('i', $usuario['id_usuario']);
    $acceso->execute();
    $acceso->close();
}

// Borra todo lo de la sesión, incluida la cookie del navegador
function cerrarSesion(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $datos = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $datos['path'], $datos['domain'], $datos['secure'], $datos['httponly']);
    }
    session_destroy();
}
