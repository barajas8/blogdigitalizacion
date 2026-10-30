# Cambios

## 3.1.0
- Sección **Juegos**: test, verdadero o falso, clasificar, memoria, ahorcado, sopa de letras, completa la frase y «¿de qué entrada es?».
- *Panel > Juegos*: se escriben con un formato de texto sencillo, con ayuda de cada tipo, lista de slugs y botón «Comprobar» que marca los errores por línea.
- Cada pregunta puede enlazar a una entrada (`@ slug`). Al terminar, el juego propone repasar las entradas falladas.
- El enlace «Juegos» del menú solo aparece cuando hay al menos un juego publicado.
- La tabla `juegos` se crea sola en blogs ya instalados (o con `sql/migracion-juegos.sql`).
- Vuelven al repositorio los `.htaccess` (raíz, `includes/`, `sql/`, `uploads/`) y el `.gitignore`.

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
