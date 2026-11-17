/*
 * Cine+ · Validaciones con JavaScript
 * Revisa los formularios ANTES de enviarlos y muestra el error junto a cada campo.
 * Es solo una comodidad: el servidor (PHP) vuelve a validar todo siempre.
 *
 * Funciona con cualquier formulario que tenga el atributo data-validar y usa
 * los atributos normales de HTML de cada campo:
 *   required            -> campo obligatorio
 *   type="email"        -> formato de correo
 *   minlength="8"       -> mínimo de caracteres
 *   pattern="[0-9]{10}" -> formato (el mensaje sale de data-mensaje)
 *   type="number" min   -> número y que no sea menor al mínimo
 *   data-igual-a="id"   -> debe ser igual a otro campo (confirmar contraseña)
 *   type="file" data-max-mb="2" accept="..." -> tamaño y tipo de la imagen
 */

// Busca (o crea) el <span> donde va el mensaje de error de un campo
function cajaDeError(campo) {
  // Para radios y casillas el mensaje va después del grupo, no de cada opción
  let referencia = campo;
  if (campo.type === 'radio' || campo.type === 'checkbox' || campo.type === 'file') {
    referencia = campo.closest('.pildoras') || campo.closest('label') || campo;
  }
  let caja = referencia.nextElementSibling;
  if (!caja || !caja.classList.contains('error-campo')) {
    caja = document.createElement('span');
    caja.className = 'error-campo';
    referencia.insertAdjacentElement('afterend', caja);
  }
  return caja;
}

function mostrarError(campo, mensaje) {
  campo.classList.add('campo-con-error');
  cajaDeError(campo).textContent = mensaje;   // textContent: nunca HTML
}

function quitarError(campo) {
  campo.classList.remove('campo-con-error');
  const caja = cajaDeError(campo);
  caja.textContent = '';
}

// Regresa el mensaje de error de un campo, o '' si está bien
function revisarCampo(campo, formulario) {
  const valor = campo.type === 'file' ? '' : campo.value.trim();

  // Grupo de radios obligatorio: al menos uno marcado
  if (campo.type === 'radio') {
    if (!campo.required) {
      return '';
    }
    const grupo = formulario.querySelectorAll('input[type="radio"][name="' + campo.name + '"]');
    const hayMarcado = Array.prototype.some.call(grupo, function (r) { return r.checked; });
    return hayMarcado ? '' : 'Elige una opción.';
  }

  if (campo.type === 'checkbox') {
    return campo.required && !campo.checked ? 'Debes marcar esta casilla.' : '';
  }

  if (campo.type === 'file') {
    const archivo = campo.files[0];
    if (!archivo) {
      return '';   // la imagen es opcional
    }
    const tiposPermitidos = ['image/jpeg', 'image/png', 'image/webp'];
    if (tiposPermitidos.indexOf(archivo.type) === -1) {
      return 'La imagen debe ser JPG, PNG o WEBP.';
    }
    const maximoMb = Number(campo.dataset.maxMb || 2);
    if (archivo.size > maximoMb * 1024 * 1024) {
      return 'La imagen no puede pesar más de ' + maximoMb + ' MB.';
    }
    return '';
  }

  if (campo.required && valor === '') {
    return 'Este campo es obligatorio.';
  }
  if (valor === '') {
    return '';   // opcional y vacío: no hay nada que revisar
  }
  if (campo.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(valor)) {
    return 'Escribe un correo válido (por ejemplo nombre@correo.com).';
  }
  if (campo.minLength > 0 && valor.length < campo.minLength) {
    return 'Debe tener al menos ' + campo.minLength + ' caracteres.';
  }
  if (campo.pattern && !new RegExp('^(?:' + campo.pattern + ')$').test(valor)) {
    return campo.dataset.mensaje || 'El formato no es válido.';
  }
  if (campo.type === 'number') {
    const numero = Number(valor);
    if (isNaN(numero)) {
      return 'Escribe un número.';
    }
    if (campo.min !== '' && numero < Number(campo.min)) {
      return 'No puede ser menor que ' + campo.min + '.';
    }
    if (campo.max !== '' && numero > Number(campo.max)) {
      return 'No puede ser mayor que ' + campo.max + '.';
    }
    if (campo.step === '1' && !Number.isInteger(numero)) {
      return 'Debe ser un número entero.';
    }
  }
  if (campo.dataset.igualA) {
    const otro = document.getElementById(campo.dataset.igualA);
    if (otro && otro.value !== campo.value) {
      return 'Las contraseñas no coinciden.';
    }
  }
  return '';
}

// Revisa todo el formulario; regresa true si no hay errores
function revisarFormulario(formulario) {
  let primerError = null;
  // formulario.elements incluye también los campos que se unen con form="id"
  Array.prototype.forEach.call(formulario.elements, function (campo) {
    if (!campo.name || campo.type === 'hidden' || campo.type === 'submit' || campo.disabled) {
      return;
    }
    const mensaje = revisarCampo(campo, formulario);
    if (mensaje) {
      mostrarError(campo, mensaje);
      if (!primerError) {
        primerError = campo;
      }
    } else {
      quitarError(campo);
    }
  });
  if (primerError) {
    primerError.focus();   // lleva al usuario al primer campo con error
    return false;
  }
  return true;
}

// Activa la validación en todos los formularios con data-validar
document.querySelectorAll('form[data-validar]').forEach(function (formulario) {
  // Con JavaScript usamos nuestros mensajes en vez de los globos del navegador
  formulario.noValidate = true;

  formulario.addEventListener('submit', function (evento) {
    if (!revisarFormulario(formulario)) {
      evento.preventDefault();   // no se envía hasta que se corrija
    }
  });

  // Al corregir un campo, su mensaje se quita
  Array.prototype.forEach.call(formulario.elements, function (campo) {
    campo.addEventListener('input', function () {
      if (campo.classList.contains('campo-con-error') && !revisarCampo(campo, formulario)) {
        quitarError(campo);
      }
    });
    campo.addEventListener('change', function () {
      if (campo.type === 'file' || campo.type === 'radio' || campo.type === 'checkbox') {
        const mensaje = revisarCampo(campo, formulario);
        if (mensaje) {
          mostrarError(campo, mensaje);
        } else {
          quitarError(campo);
        }
      }
    });
  });
});
