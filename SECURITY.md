# Seguridad

## Qué protege el blog

| Riesgo | Protección |
|---|---|
| Robo de contraseñas | bcrypt (coste 12) con `password_hash()`. Nunca se guardan en claro. |
| Contraseña adivinada o filtrada | Verificación en dos pasos (TOTP) opcional, con 8 códigos de recuperación de un solo uso (guardados como hash). |
| Fuerza bruta | Bloqueo de 15 minutos tras 5 fallos por usuario o 10 por IP, retardo en cada fallo y campo trampa contra bots. |
| CSRF | Token en todos los formularios, comprobación de `Origin` y `Sec-Fetch-Site`, y cookies `SameSite=Lax`. |
| Robo de sesión | ID nuevo al entrar y cada 15 minutos. Cookie `HttpOnly` y `Secure`, limitada a `/admin/`. Sesión atada al navegador, con caducidad por inactividad (30 min) y absoluta (8 h). Opción de cerrar las sesiones en todos los dispositivos. Archivos de sesión en una carpeta privada. |
| Inyección SQL | Solo consultas preparadas con PDO. |
| XSS | Todo se escapa al mostrarlo. El Markdown se escapa antes de darle formato. CSP estricta sin scripts en línea. |
| Subida de archivos maliciosos | Se comprueba el tipo real del archivo, se re-codifica con GD y se guarda con nombre aleatorio. En `uploads/` solo se sirven imágenes. |
| Archivos internos | `.htaccess` bloquea `config.php`, `includes/`, `sql/`, `docs/`, los `.md` y `.lock`, y el listado de carpetas. |
| Instalador expuesto | Se bloquea con `includes/install.lock` y se borra solo. Reconectar un blog existente exige la contraseña de una cuenta. |

El panel incluye **Seguridad**, una página que revisa el servidor: versión de PHP frente a PHP2Shell (CVE-2026-109618 y CVE-2026-109619) y CVE-2026-115560, HTTPS, modo debug, instalador, permisos y estado de la verificación en dos pasos de cada cuenta.

## Lo que depende del hosting

- **Versión de PHP.** Usa 8.2.35, 8.3.36, 8.4.27, 8.5.12 o superior. Los fallos del propio PHP no se pueden parchear desde el código de una web.
- **HTTPS.** Sin certificado, la contraseña viaja sin cifrar.

## Informar de un fallo

Si encuentras una vulnerabilidad, **no abras un issue público**. Usa *Security > Report a vulnerability* en este repositorio de GitHub (aviso privado).
