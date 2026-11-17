<?php
/*
 * Cine+ · Cerrar sesión
 * Vacía y destruye la sesión, y regresa al inicio de sesión con un aviso.
 */
require __DIR__ . '/config/sesion.php';

cerrarSesion();
header('Location: login.php?salida=1');
exit;
