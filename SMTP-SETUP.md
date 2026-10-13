# Configurar correo de SIGHO

SIGHO usa PHPMailer para enviar el código de bienvenida y los avisos de cuentas no reconocidas. La cuenta de correo y sus credenciales se configuran en el entorno del servidor; no se deben guardar en Git.

1. Instala las dependencias desde la raíz del proyecto con `composer install`.
2. Usa [.env.example](./.env.example) como lista de variables. El archivo `.env` no se carga automáticamente: configura estas variables en el entorno de PHP o de Apache y reinicia el servicio.
3. Completa `SIGHO_SMTP_HOST`, `SIGHO_SMTP_PORT`, `SIGHO_SMTP_USERNAME`, `SIGHO_SMTP_PASSWORD`, `SIGHO_SMTP_ENCRYPTION` (`tls`, `ssl` o `none`), `SIGHO_MAIL_FROM` y `SIGHO_MAIL_FROM_NAME`. El servidor SMTP debe autorizar la dirección remitente, idealmente una dirección `no-reply`.
4. Opcionalmente, define `SIGHO_MAIL_ADMIN_CC` como una lista de correos separada por comas para recibir avisos además de las cuentas activas de administrador y superadministrador.
5. Comprueba que la extensión OpenSSL esté habilitada para PHP cuando uses TLS o SSL.

La tabla de códigos de verificación se crea automáticamente al iniciar sesión o aprobar una cuenta. Los códigos vencen en 30 minutos, admiten cinco intentos y solo se almacena su hash. Sin SMTP configurado, SIGHO muestra que el mensaje no pudo enviarse; la aprobación ya realizada no se revierte.
