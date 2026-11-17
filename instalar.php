<?php
/*
 * Cine+ · Instalador
 * Crea la base de datos cineplus ejecutando bd/cineplus.sql.
 * Abre http://localhost/cineplus/instalar.php una sola vez.
 * OJO: si la base ya existe, se borra y se vuelve a crear vacía.
 */
require __DIR__ . '/config/conexion.php';

$mensaje = '';
$ok = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $sql = file_get_contents(__DIR__ . '/bd/cineplus.sql');
        $con = conectar(false);              // sin base de datos: el script la crea
        $con->multi_query($sql);
        do {                                 // recorrer todos los resultados
            if ($r = $con->store_result()) { $r->free(); }
        } while ($con->more_results() && $con->next_result());
        $con->select_db(BD_NOMBRE);
        $tablas = $con->query("SELECT COUNT(*) FROM information_schema.tables
                               WHERE table_schema = 'cineplus' AND table_type = 'BASE TABLE'")->fetch_row()[0];
        $ok = true;
        $mensaje = "Base de datos creada: $tablas tablas.";
    } catch (mysqli_sql_exception $ex) {
        $mensaje = 'Error al crear la base de datos: ' . $ex->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Instalar base de datos · Cine+</title>
  <style>
    body { font-family: Arial, sans-serif; background: #f4f4f6; color: #222; margin: 0; padding: 40px 16px; }
    .caja { max-width: 560px; margin: 0 auto; background: #fff; border-radius: 10px; padding: 28px; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
    h1 { margin-top: 0; }
    .alerta { padding: 12px 14px; border-radius: 6px; margin-bottom: 16px; }
    .exito { background: #e3f6e8; color: #1e6b34; }
    .error { background: #fde6e6; color: #9b1c1c; }
    button { background: #c0392b; color: #fff; border: 0; border-radius: 6px; padding: 10px 18px; font-size: 1rem; cursor: pointer; }
  </style>
</head>
<body>
  <div class="caja">
    <h1>Instalar la base de datos</h1>
    <p>Ejecuta el script <code>bd/cineplus.sql</code> en el MySQL de XAMPP.</p>
    <?php if ($mensaje): ?>
      <div class="alerta <?= $ok ? 'exito' : 'error' ?>"><?= e($mensaje) ?></div>
    <?php endif; ?>
    <p>Se creará la base de datos <strong>cineplus</strong> con sus 8 tablas y los roles Administrador y Cliente (sin datos de ejemplo).
       Si ya existe, se borrará y se volverá a crear.</p>
    <form method="post">
      <button type="submit">Crear base de datos</button>
    </form>
  </div>
</body>
</html>
