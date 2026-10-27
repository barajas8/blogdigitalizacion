# Cambios

## 3.0.0
- Asistente de instalación: pide la base de datos, el nombre del blog y las cuentas, y escribe `config.php` solo.
- Modo «reconectar un blog existente» para reinstalar sin perder nada.
- Verificación en dos pasos (TOTP) con QR y códigos de recuperación.
- Login reforzado: campo trampa, comprobación de origen, sesiones atadas al navegador, cerrar en todos los dispositivos y registro de accesos.
- Página *Panel > Seguridad* con diagnóstico de PHP2Shell.
- Migraciones automáticas de la base de datos (solo añaden).
- `uploads/.htaccess` compatible con hostings compartidos: las imágenes ya no dan error 500.

## 2.0.0
- Interfaz nueva animada, entradas fijadas, páginas de Categorías y Entradas.

## 1.0.0
- Primera versión: web pública, panel, Markdown e imágenes.
