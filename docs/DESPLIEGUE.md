# Guía de despliegue

## Menú

- [A. InfinityFree (gratis)](#a-infinityfree-gratis)
- [B. Hosting con cPanel u otro panel](#b-hosting-con-cpanel-u-otro-panel)
- [C. En tu ordenador con XAMPP o Laragon](#c-en-tu-ordenador-con-xampp-o-laragon)
- [D. Instalación manual sin asistente](#d-instalación-manual-sin-asistente)
- [Activar HTTPS](#activar-https)
- [Actualizar a una versión nueva](#actualizar-a-una-versión-nueva)
- [Reinstalar sin perder las entradas](#reinstalar-sin-perder-las-entradas)
- [Problemas frecuentes](#problemas-frecuentes)
- [Lista de comprobación final](#lista-de-comprobación-final)

---

## A. InfinityFree (gratis)

1. Crea una cuenta y un sitio en InfinityFree. Puedes usar un subdominio gratuito.
2. Ve a **Control Panel > MySQL Databases** y crea una base de datos, por ejemplo `blog`. Apunta estos cuatro datos:
   - **MySQL Hostname**, por ejemplo `sql123.infinityfree.com`. No es `localhost`.
   - **Database name**, por ejemplo `if0_12345678_blog`.
   - **MySQL Username**, por ejemplo `if0_12345678`.
   - **Password**: es la contraseña de la cuenta de hosting (*Account Details > Show*).
3. Abre el **Online File Manager**, entra en `htdocs` y borra `index2.html` si existe.
4. Sube el ZIP del proyecto y **descomprímelo dentro de `htdocs`**. Tiene que quedar `htdocs/index.php`, no `htdocs/blog/index.php` (salvo que quieras el blog en una subcarpeta, que también funciona).
   - Por FTP con FileZilla (host `ftpupload.net`, puerto 21), activa *Servidor > Forzar mostrar archivos ocultos* para que se suba `.htaccess`.
5. Abre tu dominio y completa el **asistente** (4 pasos).
6. Activa el SSL: lo tienes en [Activar HTTPS](#activar-https).

> InfinityFree muestra a veces una pantalla de «comprobando tu navegador». Es su antibot y es normal. El blog no usa AJAX precisamente por eso.

## B. Hosting con cPanel u otro panel

1. En **MySQL Databases** (o «Bases de datos»), crea una base de datos y un usuario, y **asigna el usuario a la base de datos con todos los privilegios**.
2. Normalmente el host es `localhost`.
3. Sube los archivos a `public_html`, o a una subcarpeta como `public_html/blog`.
4. Elige PHP 8.2 o superior en *Select PHP Version* o *MultiPHP Manager*.
5. Abre la web y completa el asistente.

## C. En tu ordenador con XAMPP o Laragon

1. Instala XAMPP (o Laragon) y arranca **Apache** y **MySQL**.
2. Copia el proyecto a `C:\xampp\htdocs\blog`.
3. Entra en `http://localhost/phpmyadmin` y crea una base de datos llamada `blog` (cotejamiento `utf8mb4_unicode_ci`).
4. Abre `http://localhost/blog`. En el asistente usa estos datos:
   - host `localhost`
   - usuario `root`
   - contraseña vacía (la de XAMPP por defecto)
5. Si las URLs amigables dan 404, en `C:\xampp\apache\conf\httpd.conf` cambia `AllowOverride None` por `AllowOverride All` en el bloque de `htdocs` y reinicia Apache.

## D. Instalación manual sin asistente

Por si el hosting no deja crear archivos:

1. Importa `sql/schema.sql` en phpMyAdmin (pestaña *Importar*).
2. Copia `config.example.php` como `config.php` y rellena los datos.
3. Sube `config.php` y abre `install.php`: aparecerá con tus datos de MySQL ya rellenos (solo hay que volver a escribir la contraseña). Completa el paso 4 para crear las cuentas.

Si el asistente no puede escribir `config.php` por permisos, al final te muestra el contenido para que lo copies y lo subas a mano.

## Activar HTTPS

1. Pide el certificado gratuito en tu hosting. En InfinityFree está en *SSL Certificates*; en cPanel, en *SSL/TLS Status* o *Let's Encrypt*.
2. Cuando `https://tu-dominio` cargue sin avisos, haz dos cambios:
   - En `.htaccess`, descomenta las 3 líneas de **Redirección a HTTPS**.
   - En `config.php`, pon `'force_https' => true`.
3. **No lo actives antes de tener el certificado**, o la web dejará de cargar.

## Actualizar a una versión nueva

Las entradas, las cuentas y las páginas están en la **base de datos**, y las imágenes en `uploads/`. Actualizar los archivos no las toca.

1. Haz una copia de seguridad:
   - Exporta la base de datos en phpMyAdmin (*Exportar > Rápido > SQL*).
   - Descarga `config.php` y `uploads/`.
2. Sube los archivos nuevos **sobrescribiendo**, pero **sin** `install.php`.
3. No borres `config.php`, `uploads/` ni `includes/install.lock`.
4. Entra al panel. Si la versión nueva necesita columnas nuevas, la web las **añade sola**: solo añade, nunca borra. Si fallara, ejecuta en phpMyAdmin el archivo de `sql/migracion-*.sql` que te indique el panel.

## Reinstalar sin perder las entradas

Si has perdido `config.php`, o has borrado todo y vuelto a subir el proyecto:

1. Sube el proyecto con `install.php` y borra `includes/install.lock` si sigue ahí.
2. En el asistente, pon los mismos datos de MySQL y, en el paso 4, marca **«Ya tengo un blog en esta base de datos»**.
3. Escribe el usuario y la contraseña de una cuenta que ya exista. No se crea ni se borra nada: solo se reconecta.

## Problemas frecuentes

| Síntoma | Solución |
|---|---|
| «No se pudo conectar con MySQL» | Revisa el host (en hostings gratuitos no suele ser `localhost`) y la contraseña. Las bases recién creadas pueden tardar unos minutos en estar disponibles. |
| Las entradas dan 404 | En `.htaccess` descomenta `RewriteBase /` (o `/tu-subcarpeta/`). Si sigue igual, pon `'pretty_urls' => false` en `config.php`. |
| Error 500 en toda la web | Algún `.htaccess` usa una directiva que el hosting no permite. Usa los de este repositorio sin añadir `php_value` ni `php_flag`. |
| Imágenes rotas | Abre la imagen en otra pestaña. Un 404 significa que falta el archivo: vuelve a subirlo desde *Panel > Imágenes*. Un 403 o un 500 apunta a `uploads/.htaccess`. |
| «El formulario ha caducado» | La sesión expiró tras 30 minutos sin actividad. Recarga la página. |
| No llega el código de verificación en dos pasos | El código lo genera la app del móvil, no llega por mensaje. Comprueba que la hora del móvil está en automático. |
| Perdí el móvil de la verificación en dos pasos | Usa un código de recuperación, o pide al otro administrador que te la quite en *Mi cuenta > Rescatar la cuenta de tu compañero*. |
| CSS sin cargar o enlaces rotos | Pon `base_url` en `config.php`: `''` si está en la raíz o `'/blog'` si está en una subcarpeta. |

## Lista de comprobación final

- [ ] `install.php` ya no está en el servidor.
- [ ] HTTPS activo, `force_https` en `true` y la redirección del `.htaccess` descomentada.
- [ ] `debug` en `false`.
- [ ] Verificación en dos pasos activada en todas las cuentas.
- [ ] *Panel > Seguridad* sin elementos en rojo.
- [ ] La versión de PHP del hosting es 8.2.35, 8.3.36, 8.4.27, 8.5.12 o superior (parche de PHP2Shell).
- [ ] Copia de seguridad de la base de datos y de `uploads/`.
