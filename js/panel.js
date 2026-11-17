/*
 * Cine+ · Panel en vivo
 * Cada 10 segundos le pide a admin/estadisticas.php los números nuevos (en JSON)
 * y los cambia en la página sin recargarla.
 *
 * Regla de seguridad: los textos que vienen de la base se ponen con textContent,
 * nunca con innerHTML, para que nadie pueda meter HTML o código en la página.
 */

const CADA_CUANTO = 10000;   // 10 segundos (en milisegundos)
let ultimaActualizacion = Date.now();

// Formato de dinero: 24580.5 -> "$24,580.50"
function dinero(cantidad) {
  return '$' + Number(cantidad).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// Formato corto para las barras: 24600 -> "$24.6k"
function dineroCorto(cantidad) {
  if (cantidad >= 1000) {
    return '$' + (cantidad / 1000).toFixed(1) + 'k';
  }
  return '$' + Math.round(cantidad);
}

// Cambia el texto de un elemento por su id
function ponerTexto(id, texto) {
  document.getElementById(id).textContent = texto;
}

// Crea un elemento con una clase y un texto (atajo para no repetir código)
function crear(etiqueta, clase, texto) {
  const elemento = document.createElement(etiqueta);
  if (clase) {
    elemento.className = clase;
  }
  if (texto !== undefined) {
    elemento.textContent = texto;
  }
  return elemento;
}

// Mensaje gris cuando una lista está vacía
function mensajeVacio(texto) {
  return crear('p', 'texto-suave', texto);
}

// ---------- Tarjetas de arriba ----------
function actualizarTarjetas(datos) {
  ponerTexto('periodo', datos.periodo);
  ponerTexto('ingresos-mes', dinero(datos.ingresosMes));
  ponerTexto('pedidos-mes', datos.pedidosMes);
  ponerTexto('clientes', datos.clientes);
  ponerTexto('clientes-nuevos', '+' + datos.clientesNuevos + ' nuevos este mes');
  ponerTexto('peliculas-publicadas', datos.peliculasPublicadas);
  ponerTexto('peliculas-registradas', 'de ' + datos.peliculasRegistradas + ' registradas');

  // Variación de ingresos contra el mes anterior
  const variacion = document.getElementById('variacion-ingresos');
  variacion.textContent = '';
  if (datos.variacionIngresos === null) {
    variacion.appendChild(crear('span', 'texto-suave', 'Sin ventas en ' + datos.mesAnterior + ' para comparar'));
  } else {
    const signo = datos.variacionIngresos >= 0 ? '+ ' : '− ';
    const color = datos.variacionIngresos >= 0 ? 'verde' : 'rojo';
    variacion.appendChild(crear('b', color, signo + Math.abs(datos.variacionIngresos) + '%'));
    variacion.appendChild(crear('span', 'texto-suave', ' vs. ' + datos.mesAnterior));
  }

  // Diferencia de pedidos contra el mes anterior
  const diferencia = datos.diferenciaPedidos;
  if (diferencia === 0) {
    ponerTexto('diferencia-pedidos', 'Igual que ' + datos.mesAnterior);
  } else {
    ponerTexto('diferencia-pedidos', (diferencia > 0 ? '+ ' : '− ') + Math.abs(diferencia) +
      (diferencia > 0 ? ' más' : ' menos') + ' que ' + datos.mesAnterior);
  }
}

// ---------- Gráfica de ingresos por mes ----------
function actualizarGrafica(meses) {
  const grafica = document.getElementById('grafica');
  grafica.textContent = '';   // se borran las barras viejas
  meses.forEach(function (mes) {
    const columna = crear('div', 'barra-col');
    columna.appendChild(crear('span', '', dineroCorto(mes.ingresos)));
    const barra = crear('div', mes.actual ? 'barra barra-actual' : 'barra');
    barra.style.height = mes.altura + '%';
    columna.appendChild(barra);
    columna.appendChild(crear('small', '', mes.mes));
    grafica.appendChild(columna);
  });
}

// ---------- Top 5 ----------
function actualizarTop(peliculas) {
  const lista = document.getElementById('top-peliculas');
  lista.textContent = '';
  if (peliculas.length === 0) {
    lista.appendChild(mensajeVacio('Todavía no hay ventas este mes.'));
    return;
  }
  peliculas.forEach(function (pelicula, i) {
    const fila = crear('div', 'top-fila');
    fila.appendChild(crear('span', '', (i + 1) + '. ' + pelicula.titulo + ' · ' + pelicula.formato));
    fila.appendChild(crear('strong', '', pelicula.unidades + ' uds.'));
    const progreso = crear('div', 'progreso');
    const relleno = crear('div');
    relleno.style.width = pelicula.ancho + '%';
    progreso.appendChild(relleno);
    lista.appendChild(fila);
    lista.appendChild(progreso);
  });
}

// ---------- Poca existencia ----------
function actualizarPocaExistencia(peliculas) {
  const cuerpo = document.getElementById('poca-existencia');
  cuerpo.textContent = '';
  if (peliculas.length === 0) {
    const fila = crear('tr');
    const celda = crear('td', 'texto-suave', 'Todas las películas publicadas tienen existencia suficiente.');
    celda.colSpan = 4;
    fila.appendChild(celda);
    cuerpo.appendChild(fila);
    return;
  }
  peliculas.forEach(function (pelicula) {
    const fila = crear('tr');
    fila.appendChild(crear('td', '', pelicula.titulo));
    fila.appendChild(crear('td', '', pelicula.formato));
    const celdaExistencia = crear('td');
    const agotada = pelicula.existencia === 0;
    celdaExistencia.appendChild(crear('span', agotada ? 'insignia roja' : 'insignia amarilla',
      agotada ? 'Agotada' : pelicula.existencia + ' uds.'));
    fila.appendChild(celdaExistencia);
    const celdaAccion = crear('td');
    const enlace = crear('a', '', 'Editar');
    enlace.href = 'pelicula_form.php?id=' + encodeURIComponent(pelicula.id_pelicula);
    celdaAccion.appendChild(enlace);
    fila.appendChild(celdaAccion);
    cuerpo.appendChild(fila);
  });
}

// ---------- Ventas por formato ----------
function actualizarFormatos(formatos) {
  const barra = document.getElementById('barra-formatos');
  const leyenda = document.getElementById('leyenda-formatos');
  barra.textContent = '';
  leyenda.textContent = '';
  if (formatos.length === 0) {
    leyenda.appendChild(mensajeVacio('Todavía no hay ventas este mes.'));
    return;
  }
  formatos.forEach(function (formato, i) {
    const parte = crear('div', 'color-' + (i % 4));
    parte.style.width = formato.porcentaje + '%';
    barra.appendChild(parte);

    const fila = crear('div', 'leyenda');
    const nombre = crear('span');
    nombre.appendChild(crear('i', 'color-' + (i % 4)));
    nombre.appendChild(document.createTextNode(' ' + formato.formato));
    fila.appendChild(nombre);
    fila.appendChild(crear('strong', '', formato.porcentaje + '%'));
    leyenda.appendChild(fila);
  });
}

// ---------- Pedir los datos al servidor ----------
function pedirEstadisticas() {
  fetch('estadisticas.php', { cache: 'no-store' })
    .then(function (respuesta) {
      // 401 o 403: la sesión terminó o ya no es administrador -> al acceso
      if (respuesta.status === 401 || respuesta.status === 403) {
        window.location.href = 'acceso.php?requiere=1';
        return null;
      }
      if (!respuesta.ok) {
        throw new Error('Respuesta ' + respuesta.status);
      }
      return respuesta.json();   // convierte el texto JSON en un objeto de JavaScript
    })
    .then(function (datos) {
      if (datos === null) {
        return;
      }
      actualizarTarjetas(datos);
      actualizarGrafica(datos.ingresosPorMes);
      actualizarTop(datos.topPeliculas);
      actualizarPocaExistencia(datos.pocaExistencia);
      actualizarFormatos(datos.ventasPorFormato);
      ultimaActualizacion = Date.now();
      ponerTexto('actualizado', 'actualizado ahora');
    })
    .catch(function () {
      // Si falla (por ejemplo, se apagó el servidor) la página no se rompe:
      // se queda con los últimos datos y lo vuelve a intentar en 10 segundos
      ponerTexto('actualizado', 'sin conexión, reintentando…');
    });
}

// Texto "actualizado hace X s" que se renueva cada segundo
function mostrarTiempo() {
  const segundos = Math.round((Date.now() - ultimaActualizacion) / 1000);
  const texto = document.getElementById('actualizado');
  if (texto.textContent.indexOf('sin conexión') === -1) {
    texto.textContent = segundos === 0 ? 'actualizado ahora' : 'actualizado hace ' + segundos + ' s';
  }
}

setInterval(pedirEstadisticas, CADA_CUANTO);
setInterval(mostrarTiempo, 1000);
