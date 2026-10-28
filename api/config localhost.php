<?php
// Configuración local para XAMPP.
// Si tu MySQL tiene contraseña, cambia DB_PASS.

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'jjfh_servicio_social');
define('DB_USER', 'root');
define('DB_PASS', '');
define('EVIDENCE_DIR', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'evidence');

date_default_timezone_set('America/Bogota');
