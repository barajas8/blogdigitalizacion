/* Asistente de instalación: oculta los campos de cuentas nuevas al reconectar un blog existente. */
(function () {
  var box = document.querySelector('[data-toggle="existente"]');
  if (!box) return;
  function sync() {
    document.querySelectorAll('[data-only-new]').forEach(function (el) { el.hidden = box.checked; });
  }
  box.addEventListener('change', sync);
  sync();
})();
