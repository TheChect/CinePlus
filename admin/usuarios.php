<?php
/*
 * Cine+ · Gestión de usuarios (CRUD, parte 1: listar)
 * - La tabla sale de Usuario unida (JOIN) con Rol, con cuántos pedidos tiene cada uno.
 * - El buscador y los filtros de rol y estado se envían por GET (consulta preparada).
 * Editar y Eliminar todavía son solo diseño.
 */
require __DIR__ . '/../config/conexion.php';
$con = conectar();

const POR_PAGINA = 10;

$mensaje = isset($_GET['creado']) ? 'Usuario creado correctamente.' : '';

// ---------- Filtros que llegan por GET ----------
$buscar = trim($_GET['buscar'] ?? '');
$idRol  = (int) ($_GET['rol'] ?? 0);      // 0 = todos los roles
$estado = $_GET['estado'] ?? '';          // '', 'Activo' o 'Suspendido'
if (!in_array($estado, ['', 'Activo', 'Suspendido'], true)) {
    $estado = '';
}
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));

$listaRoles = $con->query('SELECT id_rol, nombre FROM Rol ORDER BY nombre')->fetch_all(MYSQLI_ASSOC);

// ---------- WHERE según los filtros (un "?" por cada valor) ----------
$condiciones = [];
$tipos = '';
$valores = [];
if ($buscar !== '') {
    $condiciones[] = '(u.nombre LIKE ? OR u.correo LIKE ?)';
    $texto = '%' . $buscar . '%';
    $tipos .= 'ss';
    $valores[] = $texto;
    $valores[] = $texto;
}
if ($idRol > 0) {
    $condiciones[] = 'u.id_rol = ?';
    $tipos .= 'i';
    $valores[] = $idRol;
}
if ($estado !== '') {
    $condiciones[] = 'u.estado = ?';
    $tipos .= 's';
    $valores[] = $estado;
}
$where = count($condiciones) > 0 ? 'WHERE ' . implode(' AND ', $condiciones) : '';

function consultar(mysqli $con, string $sql, string $tipos, array $valores): mysqli_result
{
    $consulta = $con->prepare($sql);
    if ($tipos !== '') {
        $consulta->bind_param($tipos, ...$valores);
    }
    $consulta->execute();
    return $consulta->get_result();
}

$total = consultar($con, "SELECT COUNT(*) FROM Usuario u $where", $tipos, $valores)->fetch_row()[0];
$paginas = max(1, (int) ceil($total / POR_PAGINA));
$pagina = min($pagina, $paginas);
$desde = ($pagina - 1) * POR_PAGINA;

// Usuarios con su rol y número de pedidos (subconsulta COUNT)
$sql = "SELECT u.id_usuario, u.nombre, u.correo, u.foto, u.estado, u.fecha_registro, r.nombre AS rol,
               (SELECT COUNT(*) FROM Pedido p WHERE p.id_usuario = u.id_usuario) AS pedidos
        FROM Usuario u
        JOIN Rol r ON r.id_rol = u.id_rol
        $where
        ORDER BY u.fecha_registro DESC, u.id_usuario DESC
        LIMIT " . POR_PAGINA . " OFFSET $desde";
$usuarios = consultar($con, $sql, $tipos, $valores)->fetch_all(MYSQLI_ASSOC);

// Iniciales para el círculo: "María López" -> "ML"
function iniciales(string $nombre): string
{
    $partes = preg_split('/\s+/', trim($nombre));
    $letras = mb_substr($partes[0], 0, 1);
    if (count($partes) > 1) {
        $letras .= mb_substr(end($partes), 0, 1);
    }
    return mb_strtoupper($letras);
}

// Fecha corta en español: 2026-11-16 -> 16 nov 2026
function fechaCorta(string $fecha): string
{
    $meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    $t = strtotime($fecha);
    return date('j', $t) . ' ' . $meses[date('n', $t) - 1] . ' ' . date('Y', $t);
}

function enlacePagina(int $numero): string
{
    $parametros = $_GET;
    $parametros['pagina'] = $numero;
    return 'usuarios.php?' . http_build_query($parametros);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gestión de usuarios · Cine+</title>
  <link rel="stylesheet" href="../css/estilos.css">
  <link rel="stylesheet" href="../css/admin.css">
</head>
<body>

<div class="admin">

  <!-- Barra lateral con el menú de administración -->
  <aside class="lateral">
    <a class="logo" href="panel.html"><span class="logo-cuadro"></span> CINE+</a>

    <p class="lateral-titulo">Principal</p>
    <nav class="lateral-menu">
      <a href="panel.html">Dashboard</a>
      <a class="activo" href="usuarios.php">Gestión de Usuarios</a>
      <a href="peliculas.php">Gestión de Películas</a>
      <a href="generos.php">Gestión de Géneros</a>
      <a href="formatos.php">Gestión de Formatos</a>
    </nav>

    <!-- Parte de abajo: cerrar sesión y datos del administrador -->
    <div class="lateral-abajo">
      <a href="acceso.html">&larr; Cerrar sesión</a>
      <div class="admin-usuario">
        <div class="avatar avatar-chico"></div>
        <div>
          <strong>Admin Central</strong>
          <span class="texto-suave chico">admin@cineplus.com</span>
        </div>
      </div>
    </div>
  </aside>

  <!-- Lado derecho: barra superior y contenido -->
  <div class="admin-derecha">
    <header class="admin-barra">
      <input class="campo buscador" type="search" placeholder="Buscar en el panel...">
      <div class="avatar avatar-chico"></div>
    </header>

    <main class="admin-contenido">
      <div class="admin-titulo">
        <div>
          <h1>Gestión de usuarios</h1>
          <p class="texto-suave">Administra las cuentas, roles y el acceso a la plataforma.</p>
        </div>
        <a class="btn btn-primario" href="usuario_form.php">+ Añadir usuario</a>
      </div>

      <!-- Buscador y filtros: se envían por GET a esta misma página -->
      <form class="tarjeta barra-busqueda filtros-admin" method="get" action="usuarios.php">
        <input class="campo" type="search" name="buscar" placeholder="Buscar por nombre o correo..." value="<?= e($buscar) ?>">
        <label>Rol
          <select class="campo" name="rol">
            <option value="0">Todos los roles</option>
            <?php foreach ($listaRoles as $r): ?>
              <option value="<?= $r['id_rol'] ?>" <?= $r['id_rol'] == $idRol ? 'selected' : '' ?>><?= e($r['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Estado
          <select class="campo" name="estado">
            <option value="">Cualquier estado</option>
            <option value="Activo" <?= $estado === 'Activo' ? 'selected' : '' ?>>Activo</option>
            <option value="Suspendido" <?= $estado === 'Suspendido' ? 'selected' : '' ?>>Suspendido</option>
          </select>
        </label>
        <button class="btn btn-secundario" type="submit">Filtrar</button>
        <?php if ($where !== ''): ?>
          <a class="chico" href="usuarios.php">Limpiar</a>
        <?php endif; ?>
      </form>

      <?php if ($mensaje !== ''): ?>
        <p class="alerta-exito"><?= e($mensaje) ?></p>
      <?php endif; ?>

      <div class="tarjeta sin-relleno">
        <table class="tabla tabla-admin tabla-lista">
          <thead>
            <tr><th>Usuario</th><th>Rol</th><th>Estado</th><th>Registro</th><th>Pedidos</th><th>Acciones</th></tr>
          </thead>
          <tbody>
            <?php if (count($usuarios) === 0): ?>
              <tr><td colspan="6" class="centrado">
                <?= $where === '' ? 'Todavía no hay usuarios registrados.' : 'No se encontraron usuarios con esos filtros.' ?>
              </td></tr>
            <?php endif; ?>

            <!-- La contraseña nunca se consulta ni se muestra -->
            <?php foreach ($usuarios as $u): ?>
            <tr>
              <td>
                <div class="celda-pelicula">
                  <?php if (!empty($u['foto'])): ?>
                    <img class="iniciales" src="../<?= e($u['foto']) ?>" alt="Foto de <?= e($u['nombre']) ?>">
                  <?php else: ?>
                    <div class="iniciales"><?= e(iniciales($u['nombre'])) ?></div>
                  <?php endif; ?>
                  <div><strong><?= e($u['nombre']) ?></strong><span class="texto-suave chico"><?= e($u['correo']) ?></span></div>
                </div>
              </td>
              <td><span class="insignia <?= $u['rol'] === 'Administrador' ? 'azul' : 'gris' ?>"><?= e($u['rol']) ?></span></td>
              <td><span class="insignia <?= $u['estado'] === 'Activo' ? 'verde' : 'amarilla' ?>">● <?= e($u['estado']) ?></span></td>
              <td><?= fechaCorta($u['fecha_registro']) ?></td>
              <td><?= $u['pedidos'] ?></td>
              <td class="acciones">
                <!-- Editar y Eliminar se conectan en el siguiente paso -->
                <a class="accion ver" href="#">Ver</a>
                <a class="accion editar" href="#">Editar</a>
                <a class="accion eliminar" href="#">Eliminar</a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="pie-tabla">
        <p class="texto-suave chico">
          <?php if ($total > 0): ?>
            Mostrando <?= $desde + 1 ?> a <?= $desde + count($usuarios) ?> de <?= $total ?> usuarios
          <?php else: ?>
            0 usuarios
          <?php endif; ?>
        </p>
        <?php if ($paginas > 1): ?>
        <div class="paginacion">
          <?php if ($pagina > 1): ?><a href="<?= e(enlacePagina($pagina - 1)) ?>">Anterior</a><?php endif; ?>
          <?php for ($i = 1; $i <= $paginas; $i++): ?>
            <a class="<?= $i === $pagina ? 'activo' : '' ?>" href="<?= e(enlacePagina($i)) ?>"><?= $i ?></a>
          <?php endfor; ?>
          <?php if ($pagina < $paginas): ?><a href="<?= e(enlacePagina($pagina + 1)) ?>">Siguiente</a><?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
    </main>

  </div>

</div>

</body>
</html>
