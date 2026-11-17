<?php
/*
 * Cine+ · Gestión de usuarios (CRUD, parte 1: listar)
 * - La tabla sale de Usuario unida (JOIN) con Rol, con cuántos pedidos tiene cada uno.
 * - El buscador y los filtros de rol y estado se envían por GET (consulta preparada).
 * - Editar lleva a usuario_form.php?id=ID.
 * - Eliminar abre una confirmación con ?eliminar=ID. Si el usuario ya tiene pedidos
 *   no se borra (se perderían datos de las ventas): se suspende su cuenta.
 */
require __DIR__ . '/../config/conexion.php';
$con = conectar();

const POR_PAGINA = 10;

$mensaje = '';
$tipoMensaje = 'exito';

// Busca un usuario con su rol y su número de pedidos
function buscarUsuario(mysqli $con, int $id): ?array
{
    $consulta = $con->prepare('SELECT u.id_usuario, u.nombre, u.correo, u.foto, u.estado, r.nombre AS rol,
                                      (SELECT COUNT(*) FROM Pedido p WHERE p.id_usuario = u.id_usuario) AS pedidos
                               FROM Usuario u JOIN Rol r ON r.id_rol = u.id_rol
                               WHERE u.id_usuario = ?');
    $consulta->bind_param('i', $id);
    $consulta->execute();
    $fila = $consulta->get_result()->fetch_assoc();
    $consulta->close();
    return $fila ?: null;
}

// ¿Es el único administrador activo? (no se puede borrar)
function esUltimoAdmin(mysqli $con, array $usuario): bool
{
    if ($usuario['rol'] !== 'Administrador' || $usuario['estado'] !== 'Activo') {
        return false;
    }
    $consulta = $con->prepare("SELECT COUNT(*) FROM Usuario u JOIN Rol r ON r.id_rol = u.id_rol
                               WHERE r.nombre = 'Administrador' AND u.estado = 'Activo' AND u.id_usuario <> ?");
    $consulta->bind_param('i', $usuario['id_usuario']);
    $consulta->execute();
    $consulta->bind_result($otros);
    $consulta->fetch();
    $consulta->close();
    return $otros === 0;
}

// ---------- Eliminar (después de confirmar) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'eliminar') {
    $usuario = buscarUsuario($con, (int) ($_POST['id'] ?? 0));
    if ($usuario === null) {
        $mensaje = 'El usuario que intentas eliminar ya no existe.';
        $tipoMensaje = 'error';
    } elseif (esUltimoAdmin($con, $usuario)) {
        $mensaje = 'No se puede eliminar al único administrador activo.';
        $tipoMensaje = 'error';
    } elseif ($usuario['pedidos'] > 0) {
        // Ya tiene pedidos: no se borra, se suspende para que no pueda iniciar sesión
        $suspender = $con->prepare("UPDATE Usuario SET estado = 'Suspendido' WHERE id_usuario = ?");
        $suspender->bind_param('i', $usuario['id_usuario']);
        $suspender->execute();
        $suspender->close();
        header('Location: usuarios.php?suspendido=1');
        exit;
    } else {
        try {
            $borrar = $con->prepare('DELETE FROM Usuario WHERE id_usuario = ?');
            $borrar->bind_param('i', $usuario['id_usuario']);
            $borrar->execute();
            $borrar->close();
            // Se borra también su foto de perfil
            if ($usuario['foto'] !== null && str_starts_with($usuario['foto'], 'img/perfiles/')) {
                $archivo = __DIR__ . '/../img/perfiles/' . basename($usuario['foto']);
                if (is_file($archivo)) {
                    unlink($archivo);
                }
            }
            header('Location: usuarios.php?eliminado=1');
            exit;
        } catch (mysqli_sql_exception $ex) {
            // 1451 = otra tabla todavía usa este registro
            $mensaje = $ex->getCode() === 1451
                ? 'No se puede eliminar porque el usuario tiene registros relacionados.'
                : 'No se pudo eliminar el usuario: ' . $ex->getMessage();
            $tipoMensaje = 'error';
        }
    }
}

if (isset($_GET['creado'])) {
    $mensaje = 'Usuario creado correctamente.';
} elseif (isset($_GET['editado'])) {
    $mensaje = 'Usuario actualizado correctamente.';
} elseif (isset($_GET['eliminado'])) {
    $mensaje = 'Usuario eliminado correctamente.';
} elseif (isset($_GET['suspendido'])) {
    $mensaje = 'El usuario ya tiene pedidos, así que no se borró: se suspendió su cuenta y ya no podrá iniciar sesión.';
    $tipoMensaje = 'aviso';
} elseif (isset($_GET['noexiste'])) {
    $mensaje = 'El usuario que buscas no existe.';
    $tipoMensaje = 'error';
}

// Usuario que se va a eliminar (abre la ventana de confirmación)
$eliminar = isset($_GET['eliminar']) ? buscarUsuario($con, (int) $_GET['eliminar']) : null;

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

// Dirección para abrir la confirmación de eliminar sin perder los filtros
function enlaceEliminar(int $id): string
{
    $parametros = $_GET;
    unset($parametros['creado'], $parametros['editado'], $parametros['eliminado'], $parametros['suspendido'], $parametros['noexiste']);
    $parametros['eliminar'] = $id;
    return 'usuarios.php?' . http_build_query($parametros);
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
        <p class="alerta-<?= $tipoMensaje ?>"><?= e($mensaje) ?></p>
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
                <a class="accion ver" href="usuario_form.php?id=<?= $u['id_usuario'] ?>">Ver</a>
                <a class="accion editar" href="usuario_form.php?id=<?= $u['id_usuario'] ?>">Editar</a>
                <a class="accion eliminar" href="<?= e(enlaceEliminar($u['id_usuario'])) ?>">Eliminar</a>
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


    <!-- Confirmar eliminación: se muestra cuando la dirección trae ?eliminar=ID -->
    <?php if ($eliminar !== null): ?>
    <div class="modal abierta" id="eliminar">
      <form class="tarjeta modal-caja" method="post" action="usuarios.php">
        <input type="hidden" name="accion" value="eliminar">
        <input type="hidden" name="id" value="<?= $eliminar['id_usuario'] ?>">
        <div class="icono-alerta">!</div>
        <h2>¿Eliminar este usuario?</h2>
        <div class="registro-borrar">
          <strong><?= e($eliminar['nombre']) ?></strong>
          <span class="texto-suave chico"><?= e($eliminar['correo']) ?> · <?= e($eliminar['rol']) ?> · <?= $eliminar['pedidos'] ?> pedido(s)</span>
        </div>
        <?php if (esUltimoAdmin($con, $eliminar)): ?>
          <p class="alerta-error chico">Es el único administrador activo, por eso no se puede eliminar.</p>
          <div class="modal-botones">
            <a class="btn btn-secundario" href="usuarios.php">Cerrar</a>
          </div>
        <?php elseif ($eliminar['pedidos'] > 0): ?>
          <!-- Con pedidos: no se borra, se suspende -->
          <p class="alerta-amarilla chico">
            Este usuario tiene <?= $eliminar['pedidos'] ?> pedido(s). Para no perder la información de esas ventas
            no se borrará: se suspenderá su cuenta y ya no podrá iniciar sesión.
          </p>
          <div class="modal-botones">
            <a class="btn btn-secundario" href="usuarios.php">Cancelar</a>
            <button class="btn btn-rojo" type="submit">Suspender cuenta</button>
          </div>
        <?php else: ?>
          <p class="texto-suave">Esta acción no se puede deshacer. Se eliminará la cuenta de la tabla Usuario y su foto.</p>
          <div class="modal-botones">
            <a class="btn btn-secundario" href="usuarios.php">Cancelar</a>
            <button class="btn btn-rojo" type="submit">Sí, eliminar</button>
          </div>
        <?php endif; ?>
      </form>
    </div>
    <?php endif; ?>
  </div>

</div>

</body>
</html>
