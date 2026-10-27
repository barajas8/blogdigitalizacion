/* Se ejecuta antes de pintar: aplica el tema guardado (sin parpadeo) y marca que hay JS,
   para que las animaciones de aparición solo oculten contenido si luego se van a mostrar. */
(function () {
  var r = document.documentElement;
  r.classList.add('js');
  try {
    var t = localStorage.getItem('tema');
    if (t === 'dark' || t === 'light') r.setAttribute('data-theme', t);
  } catch (e) { /* almacenamiento no disponible: se usa el tema del sistema */ }
})();
