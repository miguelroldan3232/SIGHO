<?php
// Configuración local para XAMPP.
// Si tu MySQL tiene contraseña, cambia DB_PASS.

define('DB_HOST', 'sql207.infinityfree.com'); // El servidor MySQL que te muestra InfinityFree
define('DB_NAME', 'if0_43161262_sigho');       // El nombre exacto de tu base de datos con el prefijo
define('DB_USER', 'if0_43161262');               // Tu usuario de MySQL de InfinityFree
define('DB_PASS', 'Migueleidy03');  // La contraseña que creaste para tu cuenta de InfinityFree
define('EVIDENCE_DIR', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'evidence');

date_default_timezone_set('America/Bogota');
