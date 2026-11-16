<?php
/*
 * Cine+ · Conexión a la base de datos
 * Datos de XAMPP por defecto: usuario root sin contraseña.
 * Cambia estos valores si tu MySQL usa otro usuario o contraseña.
 */
define('BD_SERVIDOR', 'localhost');
define('BD_USUARIO',  'root');
define('BD_CLAVE',    '');
define('BD_NOMBRE',   'cineplus');

// Que mysqli lance excepciones cuando algo falle
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function conectar(bool $conBaseDeDatos = true): mysqli
{
    $bd = $conBaseDeDatos ? BD_NOMBRE : null;
    $con = new mysqli(BD_SERVIDOR, BD_USUARIO, BD_CLAVE, $bd);
    $con->set_charset('utf8mb4');   // acentos y ñ
    return $con;
}

// Escapa texto antes de mostrarlo en el HTML
function e($texto): string
{
    return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
}
