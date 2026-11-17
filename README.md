# Cine+

Página web de **Cine+**, una tienda de renta y venta de películas en formato físico (DVD, Blu-ray y 4K) pensada para un público retro y de nicho que prefiere lo físico.

Proyecto Integrador de Aprendizaje (PIA) de Programación Web · FIME UANL · Grupo 217, hora N2.

## Funciones

**Cliente**
- Crear cuenta, iniciar y cerrar sesión.
- Ver el catálogo con búsqueda, filtros por género y formato, y orden por precio o título.
- Agregar películas al carrito, cambiar cantidades y quitarlas.
- Finalizar la compra con datos de envío (estándar gratis desde $999, exprés $100) y pago simulado.
- Ver su historial de pedidos y editar sus datos y su contraseña en "Mi perfil".

**Administrador**
- Acceso propio en `admin/acceso.php`.
- CRUD de géneros, formatos, películas (con portada) y usuarios (con foto de perfil).
- Panel con estadísticas en vivo: ingresos y pedidos del mes, películas más vendidas, ventas por día y películas con poca existencia. Se actualiza solo cada 10 segundos.

## Tecnologías

- HTML, CSS y JavaScript (sin librerías)
- PHP 8
- MySQL
- XAMPP (Apache + MySQL)

## Estructura de carpetas

```
cineplus/
├── admin/        Páginas del administrador (panel, CRUD y estadisticas.php en JSON)
├── bd/           cineplus.sql: crea la base de datos y sus 8 tablas
├── config/       Conexión, sesiones, carrito, estadísticas y protección del admin
├── css/          estilos.css (tienda) y admin.css (panel)
├── img/          portadas/ y perfiles/ (imágenes que se suben desde el admin)
├── includes/     Encabezado y pie de página de la tienda
├── js/           validaciones.js (formularios) y panel.js (estadísticas en vivo)
├── index.php, tienda.php, carrito.php, finalizar.php, perfil.php, ...
└── instalar.php  Instalador de la base de datos
```

Tablas: `Rol`, `Usuario`, `Genero`, `Formato`, `Pelicula`, `Pedido`, `Envio` y `Detalle_Pedido`.

## Instalación en XAMPP

1. Clona el repositorio dentro de `C:\xampp\htdocs`:
   ```bash
   cd /c/xampp/htdocs
   git clone https://github.com/TheChect/CinePlus.git cineplus
   ```
2. Abre el panel de XAMPP e inicia **Apache** y **MySQL**.
3. Entra a `http://localhost/cineplus/instalar.php` y presiona el botón para crear la base de datos `cineplus` (usa `bd/cineplus.sql`).
4. Crea la primera cuenta de administrador en `http://localhost/cineplus/admin/acceso.php`.
5. Listo: la tienda está en `http://localhost/cineplus/`.

La conexión usa el usuario `root` sin contraseña (lo normal en XAMPP). Si tu MySQL es diferente, cambia los datos en `config/conexion.php`.

## Uso

- Como **administrador**: registra géneros y formatos, después las películas (con estado *Publicado* para que salgan en la tienda) y revisa el panel.
- Como **cliente**: crea una cuenta en "Crear cuenta", busca en el catálogo, agrega al carrito y finaliza la compra. El pedido aparece en "Mi perfil" y en el panel del administrador al momento.

## Seguridad

- Contraseñas guardadas con `password_hash()` y revisadas con `password_verify()`.
- Todas las consultas con datos del usuario usan sentencias preparadas (`prepare` / `bind_param`).
- Todo lo que se imprime en la página pasa por `e()` (`htmlspecialchars`) para evitar XSS.
- Sesiones de PHP con `session_regenerate_id()` al iniciar sesión; las páginas del admin revisan el rol.
- La compra se guarda dentro de una transacción y bloquea la existencia de cada película para que no se venda de más.
- El pago es **simulado**: no se piden ni se guardan datos de tarjeta.
- Los formularios se validan con JavaScript antes de enviarse y PHP vuelve a validar todo en el servidor.
- `instalar.php` no deja reinstalar la base de datos si ya existe un administrador, salvo que lo haga un administrador con sesión iniciada.

## Estadísticas en vivo

Las estadísticas del panel **no usan vistas** de la base de datos: se calculan en PHP con consultas al momento (`config/estadisticas.php`). `admin/estadisticas.php` las regresa en JSON y `js/panel.js` las pide cada 10 segundos para actualizar el panel sin recargar la página.
