<?php
session_start();
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }
    return $pdo;
}

function json_input(): array {
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function respond(array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function fail(string $message, int $status = 400): never {
    respond(['ok' => false, 'message' => $message], $status);
}

function capitalizarNombre(string $valor): string {
    $valor = trim(preg_replace('/\s+/u', ' ', $valor));
    if ($valor === '') return '';
    $palabras = explode(' ', mb_strtolower($valor, 'UTF-8'));
    foreach ($palabras as &$palabra) {
        if ($palabra !== '') $palabra = mb_strtoupper(mb_substr($palabra, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($palabra, 1, null, 'UTF-8');
    }
    return implode(' ', $palabras);
}


function institutional_domain(): string {
    return 'iejosejoaquinflorezhernandez.edu.co';
}

function is_institutional_email(string $email): bool {
    $email = strtolower(trim($email));
    return str_ends_with($email, '@' . institutional_domain());
}

function role_name(int $roleId): string {
    $st = db()->prepare('SELECT code FROM roles WHERE id = ?');
    $st->execute([$roleId]);
    return (string)($st->fetchColumn() ?: '');
}

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function require_login(): array {
    $user = current_user();
    if (!$user) fail('Debes iniciar sesión.', 401);
    return $user;
}

function require_roles(array $roles): array {
    $user = require_login();
    if (!in_array($user['rol'], $roles, true)) fail('No tienes permisos para realizar esta acción.', 403);
    return $user;
}

function user_payload(array $row): array {
    $payload = [
        'id' => (int)$row['id'],
        'identificacion' => $row['identification'],
        'nombre' => trim($row['first_name'] . ' ' . $row['last_name']),
        'correo' => $row['email'],
        'rol' => $row['role_code'],
        'esTecnico' => false,
        'programaTecnico' => '',
        'grado' => '',
        'metaHoras' => null,
        'staffId' => null,
        'studentId' => null,
    ];
    if ($row['role_code'] === 'estudiante') {
        $st = db()->prepare('SELECT id, grade, modality, technical_program, target_hours FROM students WHERE user_id = ? LIMIT 1');
        $st->execute([$row['id']]);
        $s = $st->fetch();
        if ($s) {
            $payload['studentId'] = (int)$s['id'];
            $payload['grado'] = trim((string)($s['grade'] ?? ''));
            // Algunas cuentas antiguas guardan el grado en la solicitud y no en students.
            if ($payload['grado'] === '') {
                $stGrade = db()->prepare("SELECT grade FROM student_applications WHERE student_id = ? AND grade IS NOT NULL AND TRIM(grade) <> '' ORDER BY id DESC LIMIT 1");
                $stGrade->execute([(int)$s['id']]);
                $payload['grado'] = trim((string)($stGrade->fetchColumn() ?: ''));
            }
            if ($payload['grado'] === '') {
                $stGrade = db()->prepare("SELECT grade FROM access_requests WHERE (email = ? OR identification = ?) AND grade IS NOT NULL AND TRIM(grade) <> '' ORDER BY id DESC LIMIT 1");
                $stGrade->execute([$row['email'], $row['identification']]);
                $payload['grado'] = trim((string)($stGrade->fetchColumn() ?: ''));
            }
            $payload['esTecnico'] = $s['modality'] === 'tecnico';
            $payload['programaTecnico'] = $s['technical_program'] ?? '';
            $payload['metaHoras'] = (float)$s['target_hours'];
        } else {
            // Fallback para estudiantes antiguos sin fila en students.
            $stGrade = db()->prepare("SELECT grade FROM access_requests WHERE (email = ? OR identification = ?) AND grade IS NOT NULL AND TRIM(grade) <> '' ORDER BY id DESC LIMIT 1");
            $stGrade->execute([$row['email'], $row['identification']]);
            $payload['grado'] = trim((string)($stGrade->fetchColumn() ?: ''));
        }
    } else {
        ensure_optional_columns(db());
        $st = db()->prepare('SELECT id, service_site, service_project, phone FROM staff WHERE user_id = ?');
        $st->execute([$row['id']]);
        $staff = $st->fetch();
        if ($staff) {
            $payload['staffId'] = (int)$staff['id'];
            $payload['sede'] = $staff['service_site'] ?? '';
            $payload['proyecto'] = $staff['service_project'] ?? '';
            $payload['telefono'] = $staff['phone'] ?? '';
        }
    }
    return $payload;
}


function ensure_student_application_table(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS student_applications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            access_request_id INT NOT NULL,
            user_id INT NULL,
            student_id INT NULL,
            service_year SMALLINT NOT NULL,
            first_surname VARCHAR(100) NOT NULL,
            second_surname VARCHAR(100) NULL,
            first_name VARCHAR(100) NOT NULL,
            second_name VARCHAR(100) NULL,
            document_type VARCHAR(50) NOT NULL,
            document_number VARCHAR(50) NOT NULL,
            grade VARCHAR(20) NOT NULL,
            birth_date DATE NOT NULL,
            residence_address TEXT NOT NULL,
            phone VARCHAR(30) NOT NULL,
            eps VARCHAR(150) NOT NULL,
            guardian_name VARCHAR(200) NOT NULL,
            guardian_phone VARCHAR(30) NOT NULL,
            service_site VARCHAR(150) NOT NULL,
            project VARCHAR(200) NOT NULL,
            project_other VARCHAR(255) NULL,
            service_days TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_student_app_access_request (access_request_id),
            INDEX idx_student_app_user (user_id),
            INDEX idx_student_app_student (student_id),
            INDEX idx_student_app_year_document (service_year, document_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function ensure_optional_columns(PDO $pdo): void {
    // Estas columnas permiten conservar la jornada del estudiante y la sede del profesor.
    $checks = [
        ['student_applications', 'service_shift', "ALTER TABLE student_applications ADD COLUMN service_shift VARCHAR(30) NULL AFTER service_days"],
        ['access_requests', 'service_site', "ALTER TABLE access_requests ADD COLUMN service_site VARCHAR(150) NULL AFTER grade"],
        ['staff', 'service_site', "ALTER TABLE staff ADD COLUMN service_site VARCHAR(150) NULL AFTER position"],
        ['staff', 'service_project', "ALTER TABLE staff ADD COLUMN service_project VARCHAR(200) NULL AFTER service_site"],
        ['staff', 'phone', "ALTER TABLE staff ADD COLUMN phone VARCHAR(30) NULL AFTER service_project"],
        ['access_requests', 'service_project', "ALTER TABLE access_requests ADD COLUMN service_project VARCHAR(200) NULL AFTER service_site"],
        ['access_requests', 'phone', "ALTER TABLE access_requests ADD COLUMN phone VARCHAR(30) NULL AFTER service_project"],
        ['student_applications', 'guardian_phone_type', "ALTER TABLE student_applications ADD COLUMN guardian_phone_type VARCHAR(20) NULL AFTER guardian_phone"]
    ];
    foreach ($checks as [$table, $column, $alter]) {
        $exists = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $exists->execute([$table, $column]);
        if (!(int)$exists->fetchColumn()) $pdo->exec($alter);
    }
}

function find_user_by_id(int $id): ?array {
    $st = db()->prepare('SELECT u.*, r.code AS role_code FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ?: null;
}

function record_payload(array $r): array {
    return [
        'id' => (int)$r['id'],
        'estudiante' => $r['student_name'],
        'correoEstudiante' => $r['student_email'],
        'grado' => $r['grade'] ?? '',
        'fecha' => $r['record_date'],
        'entrada' => !empty($r['entry_time']) ? substr($r['entry_time'], 0, 5) : '',
        'salida' => !empty($r['exit_time']) ? substr($r['exit_time'], 0, 5) : '',
        'horas' => (float)$r['hours'],
        'descripcion' => $r['activity_description'],
        'evidencia' => $r['evidence_original_name'] ?? '',
        'evidenciaTipo' => $r['evidence_mime'] ?? '',
        'evidenciaUrl' => !empty($r['evidence_path']) ? 'api/api.php?action=evidence&id=' . (int)$r['id'] : '',
        'estado' => $r['status'],
        'observacion' => $r['observation'] ?? '',
        'motivoRechazo' => $r['rejection_reason'] ?? '',
        'revisadoPor' => $r['reviewer_email'] ?? '',
        'revisadoPorNombre' => $r['reviewer_name'] ?? '',
        'fechaRegistro' => $r['created_at'] ?? '',
    ];
}

function get_records(?int $studentId = null, bool $all = true, ?int $teacherStaffId = null): array {
    $sql = "SELECT hr.*, 
                   CONCAT(su.first_name, ' ', su.last_name) AS student_name,
                   su.email AS student_email,
                   pu.email AS reviewer_email,
                   CONCAT(pu.first_name, ' ', pu.last_name) AS reviewer_name,
                   COALESCE(NULLIF(TRIM(s.grade), ''), (
                       SELECT sa.grade
                       FROM student_applications sa
                       WHERE sa.student_id = s.id
                         AND sa.grade IS NOT NULL
                         AND TRIM(sa.grade) <> ''
                       ORDER BY sa.id DESC
                       LIMIT 1
                   )) AS grade
            FROM hour_records hr
            JOIN students s ON s.id = hr.student_id
            JOIN users su ON su.id = s.user_id
            LEFT JOIN staff rst ON rst.id = hr.reviewed_by
            LEFT JOIN users pu ON pu.id = rst.user_id";
    $params = [];
    $where = [];
    if ($studentId !== null) {
        $where[] = 'hr.student_id = ?';
        $params[] = $studentId;
    }
    if ($teacherStaffId !== null) {
        ensure_student_application_table(db());
        ensure_optional_columns(db());
        $stTeacher = db()->prepare('SELECT service_site, service_project FROM staff WHERE id = ?');
        $stTeacher->execute([$teacherStaffId]);
        $teacher = $stTeacher->fetch();
        if (!$teacher || empty($teacher['service_site']) || empty($teacher['service_project'])) {
            return [];
        }
        $where[] = "EXISTS (SELECT 1 FROM student_applications sa WHERE sa.student_id = s.id AND sa.id = (SELECT MAX(sa2.id) FROM student_applications sa2 WHERE sa2.student_id = s.id) AND sa.service_site = ? AND sa.project = ?)";
        $params[] = $teacher['service_site'];
        $params[] = $teacher['service_project'];
    }
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= ' ORDER BY hr.id DESC';
    $st = db()->prepare($sql);
    $st->execute($params);
    return array_map('record_payload', $st->fetchAll());
}

function pending_requests(string $roleFilter = ''): array {
    $pdo = db();
    ensure_student_application_table($pdo);
    ensure_optional_columns($pdo);

    $sql = "SELECT ar.*, r.code AS role_code,
                   sa.id AS application_id,
                   sa.service_year,
                   sa.first_surname,
                   sa.second_surname,
                   sa.first_name AS application_first_name,
                   sa.second_name,
                   sa.document_type,
                   sa.document_number,
                   sa.grade AS application_grade,
                   sa.birth_date,
                   sa.residence_address,
                   sa.phone AS application_phone,
                   sa.eps,
                   sa.guardian_name,
                   sa.guardian_phone,
                    sa.guardian_phone_type,
                   sa.service_site,
                   sa.project,
                   sa.project_other,
                   sa.service_days,
                   sa.service_shift,
                   ar.service_site AS request_service_site,
                   ar.service_project AS request_service_project,
                   ar.phone AS request_phone
            FROM access_requests ar
            JOIN roles r ON r.id = ar.requested_role_id
            LEFT JOIN student_applications sa ON sa.access_request_id = ar.id
            WHERE ar.status = 'pendiente'";
    $params = [];
    if ($roleFilter !== '') {
        $sql .= ' AND r.code = ?';
        $params[] = $roleFilter;
    }
    $sql .= ' ORDER BY ar.id DESC';

    $st = $pdo->prepare($sql);
    $st->execute($params);

    $out = [];
    foreach ($st->fetchAll() as $r) {
        $out[] = [
            'id' => (int)$r['id'],
            'identificacion' => $r['identification'],
            'nombre' => trim($r['first_name'] . ' ' . $r['last_name']),
            'correo' => $r['email'],
            'rol' => $r['role_code'],
            'esTecnico' => $r['modality'] === 'tecnico',
            'programaTecnico' => $r['technical_program'] ?? '',
            'grado' => $r['grade'] ?? '',
            'fechaSolicitud' => $r['requested_at'],
            'estado' => $r['status'],

            // Ficha detallada del estudiante
            'applicationId' => $r['application_id'] ? (int)$r['application_id'] : null,
            'anioServicio' => $r['service_year'] !== null ? (int)$r['service_year'] : null,
            'primerApellido' => $r['first_surname'] ?? '',
            'segundoApellido' => $r['second_surname'] ?? '',
            'primerNombre' => $r['application_first_name'] ?? '',
            'segundoNombre' => $r['second_name'] ?? '',
            'tipoDocumento' => $r['document_type'] ?? '',
            'numeroDocumento' => $r['document_number'] ?? '',
            'gradoSolicitud' => $r['application_grade'] ?? '',
            'fechaNacimiento' => $r['birth_date'] ?? '',
            'direccion' => $r['residence_address'] ?? '',
            'telefono' => $r['application_phone'] ?? '',
            'eps' => $r['eps'] ?? '',
            'nombreAcudiente' => $r['guardian_name'] ?? '',
            'telefonoAcudiente' => $r['guardian_phone'] ?? '',
             'tipoTelefonoAcudiente' => $r['guardian_phone_type'] ?? '',
            'sede' => $r['service_site'] ?? '',
            'proyecto' => $r['project'] ?? '',
            'proyectoOtro' => $r['project_other'] ?? '',
            'diasServicio' => $r['service_days'] ? (json_decode($r['service_days'], true) ?: []) : [],
            'jornada' => $r['service_shift'] ?? '',
            'sedeProfesor' => $r['request_service_site'] ?? '',
            'proyectoProfesor' => $r['request_service_project'] ?? '',
            'telefonoProfesor' => $r['request_phone'] ?? ''
        ];
    }
    return $out;
}

function handle_evidence(int $id): never {
    $user = require_login();
    $st = db()->prepare("SELECT hr.*, s.user_id AS student_user_id FROM hour_records hr JOIN students s ON s.id = hr.student_id WHERE hr.id = ?");
    $st->execute([$id]);
    $r = $st->fetch();
    if (!$r) { http_response_code(404); exit('Evidencia no encontrada.'); }
    if ($user['rol'] === 'estudiante' && (int)$r['student_user_id'] !== (int)$user['id']) {
        http_response_code(403); exit('No autorizado.');
    }
    if (!$r['evidence_path']) { http_response_code(404); exit('Este registro no tiene evidencia.'); }
    $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $r['evidence_path']);
    if (!is_file($path)) { http_response_code(404); exit('Archivo no encontrado.'); }
    header('Content-Type: ' . ($r['evidence_mime'] ?: 'application/octet-stream'));
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: inline; filename="' . basename($r['evidence_original_name']) . '"');
    readfile($path);
    exit;
}

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'session':
            respond(['ok' => true, 'user' => current_user()]);

        case 'login':
            $d = json_input();
            $rol = trim((string)($d['rol'] ?? ''));
            $correo = strtolower(trim((string)($d['correo'] ?? '')));
            $clave = (string)($d['clave'] ?? '');
            if (!$correo || !$clave) fail('Completa todos los campos.');

            $st = db()->prepare("SELECT u.*, r.code AS role_code FROM users u JOIN roles r ON r.id = u.role_id WHERE LOWER(u.email) = ? AND u.status = 'activo'");
            $st->execute([$correo]);
            $u = $st->fetch();
            if (!$u || !password_verify($clave, $u['password_hash'])) {
                fail('Correo o clave incorrectos.', 401);
            }

            $rol = $rol ?: $u['role_code'];
            if ($u['role_code'] !== $rol) {
                fail('Correo, clave o rol incorrectos.', 401);
            }

            if ($rol === 'profesor' && !is_institutional_email($correo)) {
                fail('Los docentes deben usar el correo institucional @' . institutional_domain() . '.');
            }
            if ($rol === 'administrador' && !is_institutional_email($correo)) {
                fail('Los administradores deben usar el correo institucional @' . institutional_domain() . '.');
            }
            if ($rol === 'estudiante' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                fail('El estudiante debe usar un correo electrónico válido.');
            }
            $payload = user_payload($u);
            $_SESSION['user'] = $payload;
            session_regenerate_id(true);
            respond(['ok' => true, 'user' => $payload]);

        case 'logout':
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }
            session_destroy();
            respond(['ok' => true]);

        case 'request_access':
            $d = json_input();
            $rol = trim((string)($d['rol'] ?? ''));
            $nombre = trim((string)($d['nombre'] ?? ''));
            $apellido = trim((string)($d['apellido'] ?? ''));
            $ident = trim((string)($d['identificacion'] ?? ''));
            $correo = strtolower(trim((string)($d['correo'] ?? '')));
            $clave = (string)($d['clave'] ?? '');
            $modalidad = $d['modalidad'] ?? null;
            $programa = trim((string)($d['programaTecnico'] ?? '')) ?: null;
            $grado = trim((string)($d['grado'] ?? '')) ?: null;
            $sedeProfesor = trim((string)($d['sede'] ?? ''));
            $proyectoProfesor = trim((string)($d['proyecto'] ?? ''));
            $telefonoProfesor = trim((string)($d['telefono'] ?? ''));

            if (!$rol || !$nombre || !$apellido || !$ident || !$correo || !$clave) {
                fail('Completa todos los campos.');
            }
            if (!in_array($rol, ['estudiante','profesor'], true)) {
                fail('En las solicitudes solo se permite el rol Estudiante o Profesor.');
            }
            if ($rol === 'estudiante') {
                if (!preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)\S{8,}$/', $clave)) {
                    fail('La contraseña del estudiante debe tener mínimo 8 caracteres, sin espacios, con mayúscula, minúscula y número.');
                }
            } else {
                if (!preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)\S{8,}$/', $clave)) {
                    fail('La contraseña del profesor debe tener mínimo 8 caracteres, sin espacios, con mayúscula, minúscula y número.');
                }
            }
            if ($rol === 'profesor' && !is_institutional_email($correo)) {
                 fail('El profesor debe usar un correo @' . institutional_domain() . '.');
            }
            if ($rol === 'estudiante' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                fail('El estudiante debe usar un correo electrónico válido.');
            }
            if ($rol === 'estudiante' && !in_array($modalidad, ['academico','tecnico'], true)) {
                fail('Selecciona la modalidad del estudiante.');
            }

            $pdo = db();
            $st = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ? OR identification = ?');
            $st->execute([$correo, $ident]);
            if ((int)$st->fetchColumn() > 0) {
                fail('Ya existe una cuenta con ese correo o identificación.');
            }

            $st = $pdo->prepare("SELECT COUNT(*) FROM access_requests WHERE status = 'pendiente' AND (email = ? OR identification = ?)");
            $st->execute([$correo, $ident]);
            if ((int)$st->fetchColumn() > 0) {
                fail('Ya existe una solicitud pendiente con esos datos.');
            }

            $roleId = (int)$pdo->query("SELECT id FROM roles WHERE code = " . $pdo->quote($rol))->fetchColumn();
            if (!$roleId) {
                fail('No se encontró el rol seleccionado.', 500);
            }

            // Los estudiantes tienen una ficha adicional con todos los datos del formulario.
            if ($rol === 'estudiante') {
                ensure_student_application_table($pdo);

                $anioServicio = (int)date('Y');
                $actualYear = (int)date('Y');
                $primerApellido = capitalizarNombre((string)($d['primerApellido'] ?? ''));
                $segundoApellido = capitalizarNombre((string)($d['segundoApellido'] ?? ''));
                $primerNombre = capitalizarNombre((string)($d['primerNombre'] ?? ''));
                $segundoNombre = capitalizarNombre((string)($d['segundoNombre'] ?? ''));
                $tipoDocumento = trim((string)($d['tipoDocumento'] ?? ''));
                $numeroDocumento = trim((string)($d['numeroDocumento'] ?? ''));
                $gradoEstudiante = trim((string)($d['grado'] ?? ''));
                $fechaNacimiento = trim((string)($d['fechaNacimiento'] ?? ''));
                $direccion = trim((string)($d['direccion'] ?? ''));
                $telefono = trim((string)($d['telefono'] ?? ''));
                $eps = trim((string)($d['eps'] ?? ''));
                $nombreAcudiente = capitalizarNombre((string)($d['nombreAcudiente'] ?? ''));
                $telefonoAcudiente = trim((string)($d['telefonoAcudiente'] ?? ''));
                $tipoTelefonoAcudiente = trim((string)($d['tipoTelefonoAcudiente'] ?? ''));
                $sede = trim((string)($d['sede'] ?? ''));
                $proyecto = trim((string)($d['proyecto'] ?? ''));
                $diasServicio = $d['diasServicio'] ?? [];
                $jornada = trim((string)($d['jornada'] ?? ''));

                $sedesPermitidas = [
                    'Central JT',
                    'San Francisco Club',
                    'Picaleña',
                    'Central J.N.',
                    'Secundino Porras Cruz',
                    'San Martín',
                    'Central JM',
                    'Bello Horizonte'
                ];

                $proyectosPermitidos = [
                    'Educación Física / Tiempo Libre',
                    'Proyecto Ambiental',
                    'Logística y Vigilancia',
                    'Secretaría y/o Archivo',
                    'Acompañamiento a un docente de transición o primaria',
                    'Eventos especiales'
                ];

                $diasPermitidos = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
                $jornadasPermitidas = ['JM','JN','Mañana','Nocturna'];

                if ($anioServicio < $actualYear || $anioServicio > $actualYear + 3) {
                    fail("El año del servicio social debe estar entre " . $actualYear . " y " . ($actualYear + 3) . ".");
                }
                if (!$primerApellido || !$primerNombre || !$tipoDocumento || !$numeroDocumento ||
                    !$gradoEstudiante || !$fechaNacimiento || !$direccion || !$telefono || !$eps ||
                    !$nombreAcudiente || !$telefonoAcudiente || !$sede || !$proyecto || !$jornada || !is_array($diasServicio) ||
                    count($diasServicio) === 0) {
                    fail('Completa todos los campos obligatorios del estudiante.');
                }

                $regexNombre = '/^[\p{L}]+(?: [\p{L}]+)*$/u';
                foreach ([
                    'Primer apellido' => [$primerApellido, true, 30],
                    'Segundo apellido' => [$segundoApellido, false, 30],
                    'Primer nombre' => [$primerNombre, true, 30],
                    'Segundo nombre' => [$segundoNombre, false, 30],
                    'Nombre del acudiente' => [$nombreAcudiente, true, 60]
                ] as $etiqueta => [$valor, $obligatorio, $maximo]) {
                    if (!$valor && !$obligatorio) continue;
                    if (mb_strlen($valor, 'UTF-8') < 2 || mb_strlen($valor, 'UTF-8') > $maximo || !preg_match($regexNombre, $valor)) {
                        fail($etiqueta . " debe contener solo letras y espacios sencillos, entre 2 y {$maximo} caracteres.");
                    }
                }
                $patronDocumento = match ($tipoDocumento) {
                    'Tarjeta de identidad' => '/^\d{10}$/',
                    'Cédula de ciudadanía' => '/^\d{7,10}$/',
                    'PPT' => '/^\d{6,8}$/',
                    default => '/^$/',
                };
                if (!preg_match($patronDocumento, $numeroDocumento)) {
                    fail(match ($tipoDocumento) {
                        'Tarjeta de identidad' => 'La tarjeta de identidad debe tener exactamente 10 dígitos.',
                        'Cédula de ciudadanía' => 'La cédula debe tener entre 7 y 10 dígitos.',
                        'PPT' => 'El PPT debe tener entre 6 y 8 dígitos.',
                        default => 'Tipo de documento no válido.',
                    });
                }
                if (mb_strlen($direccion, 'UTF-8') > 100) {
                    fail('La dirección puede tener máximo 100 caracteres.');
                }
                $telefonoSoloDigitos = preg_replace('/\D+/', '', $telefono);
                if (!preg_match('/^3\d{9}$/', $telefonoSoloDigitos)) {
                    fail('El celular del estudiante debe tener exactamente 10 dígitos y comenzar por 3.');
                }
                if (preg_match('/^(\d)\1{9}$/', $telefonoSoloDigitos) || $telefonoSoloDigitos === '1234567890') {
                    fail('El celular del estudiante no puede tener todos sus dígitos iguales ni ser la secuencia 1234567890.');
                }
                if (!in_array($tipoTelefonoAcudiente, ['fijo', 'celular'], true)) {
                    fail('Selecciona si el teléfono del acudiente es fijo o celular.');
                }

                $telefonoAcudienteSoloDigitos = preg_replace('/\D+/', '', $telefonoAcudiente);

                if ($tipoTelefonoAcudiente === 'celular') {
                    if (!preg_match('/^3\d{9}$/', $telefonoAcudienteSoloDigitos)) {
                        fail('El celular del acudiente debe tener exactamente 10 dígitos y comenzar por 3.');
                    }
                    if (preg_match('/^(\d)\1{9}$/', $telefonoAcudienteSoloDigitos) || $telefonoAcudienteSoloDigitos === '1234567890') {
                        fail('El celular del acudiente no puede tener todos sus dígitos iguales ni ser la secuencia 1234567890.');
                    }
                } else {
                    if (!preg_match('/^60[1-8][2-8]\d{6}$/', $telefonoAcudienteSoloDigitos)) {
                        fail('El teléfono fijo del acudiente debe iniciar con 60, incluir un indicativo regional del 1 al 8 y tener 7 dígitos finales cuyo primero esté entre 2 y 8.');
                    }
                    $restoFijo = substr($telefonoAcudienteSoloDigitos, 3);
                    if (preg_match('/^(\d)\1{6}$/', $restoFijo)) {
                        fail('Los 7 dígitos finales del teléfono fijo no pueden ser todos iguales.');
                    }
                }
                if (mb_strlen($eps, 'UTF-8') > 150) {
                    fail('La opción de Seguridad Social puede tener máximo 150 caracteres.');
                }
                if ($gradoEstudiante === 'Ciclo V' && $modalidad === 'tecnico') {
                    fail('Ciclo V no puede seleccionar la modalidad Técnica.');
                }
                if (!in_array($modalidad, ['academico','tecnico'], true)) {
                    fail('La modalidad seleccionada no es válida.');
                }
                if ($gradoEstudiante === 'Ciclo V' && $jornada !== 'JN') {
                    fail('Ciclo V debe tener jornada JN.');
                }
                if (in_array($gradoEstudiante, ['10°','11°'], true) && $jornada !== 'JM') {
                    fail('10° y 11° deben tener jornada JM.');
                }
                $actualYearValidacion = (int)date('Y');
                if ($anioServicio < $actualYearValidacion || $anioServicio > $actualYearValidacion + 3) {
                    fail("El año del servicio social debe estar entre {$actualYearValidacion} y " . ($actualYearValidacion + 3) . '.');
                }
                if (!in_array($tipoDocumento, ['Tarjeta de identidad','Cédula de ciudadanía','PPT'], true)) {
                    fail('Tipo de documento no válido.');
                }
                if (!in_array($gradoEstudiante, ['10°','11°','Ciclo V'], true)) {
                    fail('El grado debe ser 10°, 11° o Ciclo V.');
                }
                if (!in_array($jornada, $jornadasPermitidas, true)) {
                    fail('La jornada seleccionada no es válida.');
                }
                // Compatibilidad con solicitudes antiguas y normalización para las nuevas.
                if ($jornada === 'Mañana') $jornada = 'JM';
                if ($jornada === 'Nocturna') $jornada = 'JN';
                $jornadaEsperada = $gradoEstudiante === 'Ciclo V' ? 'JN' : 'JM';
                if ($jornada !== $jornadaEsperada) {
                    fail('La jornada se asigna automáticamente según el grado seleccionado.');
                }
                if (!in_array($sede, $sedesPermitidas, true)) {
                    fail('La sede seleccionada no es válida.');
                }
                if (!in_array($proyecto, $proyectosPermitidos, true)) {
                    fail('El proyecto seleccionado no es válido.');
                }
                $regexTextoOtro = '/^[\p{L}]+(?: [\p{L}]+)*$/u';
                $epsSeleccionada = trim((string)($d['epsSeleccionada'] ?? ''));
                $seguridadSocialTipo = trim((string)($d['seguridadSocialTipo'] ?? ''));
                if ($seguridadSocialTipo !== '') {
                    if ($seguridadSocialTipo === 'eps') {
                        $epsPermitidas = [
                            'Nueva EPS', 'EPS Sanitas', 'EPS Sura', 'Salud Total EPS', 'Compensar EPS',
                            'E.P.S. Famisanar', 'Aliansalud EPS', 'Servicio Occidental de Salud (S.O.S.)',
                            'Salud Mía EPS', 'Coosalud', 'Mutual Ser', 'Capital Salud EPS', 'Savia Salud EPS',
                            'Asmet Salud', 'Emssanar', 'Cajacopi Atlántico', 'Capresoca', 'Comfachocó',
                            'Comfaoriente', 'Comfenalco Valle', 'EPS Familiar de Colombia', 'Anas Wayuu EPSI',
                            'Asociación Indígena del Cauca (AIC)', 'Dusakawi EPSI', 'Mallamas EPSI', 'Pijaos Salud EPSI'
                        ];
                        if (!in_array($epsSeleccionada, $epsPermitidas, true) || $eps !== $epsSeleccionada) {
                            fail('Selecciona una EPS válida.');
                        }
                    } elseif ($seguridadSocialTipo === 'especial') {
                        $regimenesPermitidos = [
                            'Fuerzas Militares / Policía Nacional',
                            'FOMAG (Fondo Nacional de Prestaciones Sociales del Magisterio - Profesores)',
                            'Ecopetrol',
                            'Universidades Públicas (Salud Propia)'
                        ];
                        if (!in_array($epsSeleccionada, $regimenesPermitidos, true) || $eps !== $epsSeleccionada) {
                            fail('Selecciona un régimen especial o de excepción válido.');
                        }
                    } elseif ($seguridadSocialTipo === 'sisben') {
                        $formatoSisbenDetallado = preg_match('/^Sisbén Grupo ([A-D]), Subgrupo (\d{1,2})$/u', $epsSeleccionada, $grupoSisben);
                        $formatoSisbenAnterior = !$formatoSisbenDetallado && preg_match('/^Sisbén ([A-D])(\d{1,2})$/u', $epsSeleccionada, $grupoSisben);
                        if ((!$formatoSisbenDetallado && !$formatoSisbenAnterior) || $eps !== $epsSeleccionada) {
                            fail('Selecciona un grupo Sisbén válido.');
                        }
                        $maximosSisben = ['A' => 5, 'B' => 7, 'C' => 18, 'D' => 21];
                        $numeroSisben = (int)$grupoSisben[2];
                        if ($numeroSisben < 1 || $numeroSisben > $maximosSisben[$grupoSisben[1]]) {
                            fail('El número no corresponde al grupo Sisbén seleccionado.');
                        }
                    } else {
                        fail('Selecciona una opción válida de Seguridad Social.');
                    }
                }
                if ($epsSeleccionada === 'Otro') {
                    if (!$eps || mb_strlen($eps, 'UTF-8') < 2 || mb_strlen($eps, 'UTF-8') > 60 || !preg_match($regexTextoOtro, $eps)) {
                        fail("La EPS de 'Otro' debe contener solo letras y espacios sencillos, entre 2 y 60 caracteres.");
                    }
                }
                $diasServicio = array_values(array_unique(array_filter(
                    array_map('trim', $diasServicio),
                    fn($dia) => in_array($dia, $diasPermitidos, true)
                )));
                if (count($diasServicio) === 0) {
                    fail('Selecciona al menos un día de servicio social.');
                }

                $dateObj = DateTime::createFromFormat('Y-m-d', $fechaNacimiento);
                if (!$dateObj || $dateObj->format('Y-m-d') !== $fechaNacimiento) {
                    fail('La fecha de nacimiento no es válida.');
                }
                if ($dateObj > new DateTime('today')) {
                    fail('La fecha de nacimiento no puede ser futura.');
                }
                $anioNacimiento = (int)$dateObj->format('Y');
                $hoy = new DateTime('today');
                $edad = $hoy->diff($dateObj)->y;

                if ($gradoEstudiante === 'Ciclo V') {
                    // Ciclo V: rango dinámico de 18 a 40 años cumplidos.
                    if ($edad < 18 || $edad > 40) {
                        fail('Para Ciclo V la edad del estudiante debe estar entre 18 y 40 años.');
                    }
                } else {
                    // 10° y 11°: rango dinámico de 14 a 21 años cumplidos.
                    if ($edad < 14 || $edad > 21) {
                        fail('Para 10° y 11° la edad del estudiante debe estar entre 14 y 21 años.');
                    }
                }

                // Regla documental: menor de 18 = TI, 18 o más = CC.
                $tipoDocumentoEsperado = $edad >= 18 ? 'Cédula de ciudadanía' : 'Tarjeta de identidad';
                if ($tipoDocumento !== 'PPT' && $tipoDocumento !== $tipoDocumentoEsperado) {
                    fail("Por la edad del estudiante debe seleccionar {$tipoDocumentoEsperado}, salvo que utilice PPT.");
                }

                // El número de documento es la identificación principal de la cuenta.
                $ident = $numeroDocumento;
                $nombre = [$primerNombre, $segundoNombre];
                $nombre = trim(implode(' ', array_filter($nombre)));
                $apellido = [$primerApellido, $segundoApellido];
                $apellido = trim(implode(' ', array_filter($apellido)));

                $st = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ? OR identification = ?');
                $st->execute([$correo, $ident]);
                if ((int)$st->fetchColumn() > 0) {
                    fail('Ya existe una cuenta con ese correo o número de documento.');
                }

                $st = $pdo->prepare("SELECT COUNT(*) FROM access_requests WHERE status = 'pendiente' AND (email = ? OR identification = ?)");
                $st->execute([$correo, $ident]);
                if ((int)$st->fetchColumn() > 0) {
                    fail('Ya existe una solicitud pendiente con ese correo o número de documento.');
                }
            }

            if ($rol === 'profesor') {
                $nombre = capitalizarNombre($nombre);
                $apellido = capitalizarNombre($apellido);
                $ident = trim($ident);
                $correo = strtolower(trim($correo));

                $regexNombreProfesor = '/^[\p{L}]+(?: [\p{L}]+)*$/u';
                if (mb_strlen($nombre, 'UTF-8') < 2 || mb_strlen($nombre, 'UTF-8') > 60 || !preg_match($regexNombreProfesor, $nombre)) {
                    fail('Los nombres del profesor solo pueden contener letras y un espacio sencillo entre palabras.');
                }
                if (mb_strlen($apellido, 'UTF-8') < 2 || mb_strlen($apellido, 'UTF-8') > 60 || !preg_match($regexNombreProfesor, $apellido)) {
                    fail('Los apellidos del profesor solo pueden contener letras y un espacio sencillo entre palabras.');
                }
                if ($ident === '') {
                    fail('La identificación o código institucional del profesor es obligatorio.');
                }
                if (!preg_match('/^3\d{9}$/', $telefonoProfesor)
                    || preg_match('/^(\d)\1{9}$/', $telefonoProfesor)
                    || in_array($telefonoProfesor, ['0123456789','1234567890','9876543210','123456789','234567890','987654321','876543210'], true)) {
                    fail('El teléfono de contacto del profesor debe tener exactamente 10 dígitos, comenzar por 3 y no ser un número repetido o una secuencia consecutiva.');
                }
                // La identificación/código se conserva libre para no romper formatos existentes como EST-100.
                if (preg_match('/[<>\"\']/u', $ident)) {
                    fail('La identificación o código institucional contiene caracteres no permitidos.');
                }
                if (preg_match('/\s/u', $correo) || !preg_match('/^[^\s@]+@iejosejoaquinflorezhernandez\.edu\.co$/i', $correo)) {
                    fail('El correo del profesor debe ser institucional, sin espacios, y usar @iejosejoaquinflorezhernandez.edu.co.');
                }

                // El proyecto y la sede del profesor no se solicitan públicamente: los asigna el Administrador.
                $proyectoProfesor = null;
                $sedeProfesor = null;
            }

            ensure_optional_columns($pdo);
            $pdo->beginTransaction();
            try {
                $st = $pdo->prepare('INSERT INTO access_requests (identification, first_name, last_name, email, password_hash, requested_role_id, modality, technical_program, grade, service_site, service_project, phone) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
                $st->execute([
                    $ident,
                    $nombre,
                    $apellido,
                    $correo,
                    password_hash($clave, PASSWORD_BCRYPT),
                    $roleId,
                    $rol === 'estudiante' ? $modalidad : null,
                    $rol === 'estudiante' && $modalidad === 'tecnico' ? ($programa ?: 'Técnica') : null,
                    $rol === 'estudiante' ? $gradoEstudiante : $grado,
                    $rol === 'profesor' ? $sedeProfesor : null,
                    $rol === 'profesor' ? $proyectoProfesor : null,
                    $rol === 'profesor' ? $telefonoProfesor : null
                ]);
                $accessRequestId = (int)$pdo->lastInsertId();

                if ($rol === 'estudiante') {
                    $st = $pdo->prepare('
                        INSERT INTO student_applications
                        (access_request_id, service_year, first_surname, second_surname, first_name, second_name,
                         document_type, document_number, grade, birth_date, residence_address, phone, eps,
                         guardian_name, guardian_phone, guardian_phone_type, service_site, project, project_other,
                         service_days, service_shift)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                    ');
                    $st->execute([
                        $accessRequestId,
                        $anioServicio,
                        $primerApellido,
                        $segundoApellido ?: null,
                        $primerNombre,
                        $segundoNombre ?: null,
                        $tipoDocumento,
                        $numeroDocumento,
                        $gradoEstudiante,
                        $fechaNacimiento,
                        $direccion,
                        $telefonoSoloDigitos,
                        $eps,
                        $nombreAcudiente,
                        $telefonoAcudienteSoloDigitos,
                        $tipoTelefonoAcudiente,
                        $sede,
                        $proyecto,
                        null,
                        json_encode($diasServicio, JSON_UNESCAPED_UNICODE),
                        $jornada
                    ]);
                }

                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }

            $message = 'Solicitud enviada. Quedará pendiente de validación por un administrador.';
            respond(['ok' => true, 'message' => $message]);

        case 'professor_profile':
            $user = require_roles(['profesor']);
            ensure_optional_columns(db());
            $pdo = db();
            $st = $pdo->prepare("SELECT u.first_name, u.last_name, u.email, u.identification, s.phone, s.service_site, s.service_project, (SELECT ar.status FROM access_requests ar JOIN roles rr ON rr.id=ar.requested_role_id WHERE rr.code='administrador' AND ar.email=u.email ORDER BY ar.id DESC LIMIT 1) AS admin_request_status FROM users u LEFT JOIN staff s ON s.user_id=u.id WHERE u.id=? LIMIT 1");
            $st->execute([(int)$user['id']]);
            $p = $st->fetch() ?: [];
            respond(['ok'=>true,'profile'=>[
                'nombres'=>$p['first_name'] ?? '',
                'apellidos'=>$p['last_name'] ?? '',
                'correo'=>$p['email'] ?? $user['correo'],
                'telefono'=>$p['phone'] ?? '',
                'rol'=>'Profesor',
                'identificacion'=>$p['identification'] ?? $user['identificacion'] ?? '',
                'sede'=>$p['service_site'] ?? '',
                'proyecto'=>$p['service_project'] ?? '',
                'solicitudAdminEstado'=>$p['admin_request_status'] ?? ''
            ]]);

        case 'update_professor_profile':
            $user = require_roles(['profesor']);
            $d = json_input();
            $nombres = trim((string)($d['nombres'] ?? ''));
            $apellidos = trim((string)($d['apellidos'] ?? ''));
            $correo = strtolower(trim((string)($d['correo'] ?? '')));
            $telefono = trim((string)($d['telefono'] ?? ''));
            if ($nombres === '' || $apellidos === '' || $correo === '' || $telefono === '') fail('Completa todos los datos editables.');
            if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || !is_institutional_email($correo)) {
                fail('El correo del profesor debe utilizar el dominio institucional @' . institutional_domain() . '.');
            }
            $regexNombreProfesor = '/^[\p{L}]+(?: [\p{L}]+)*$/u';
            $nombres = capitalizarNombre($nombres);
            $apellidos = capitalizarNombre($apellidos);
            if (mb_strlen($nombres, 'UTF-8') < 2 || mb_strlen($nombres, 'UTF-8') > 60 || !preg_match($regexNombreProfesor, $nombres)) fail('Los nombres del profesor solo pueden contener letras y un espacio sencillo entre palabras.');
            if (mb_strlen($apellidos, 'UTF-8') < 2 || mb_strlen($apellidos, 'UTF-8') > 60 || !preg_match($regexNombreProfesor, $apellidos)) fail('Los apellidos del profesor solo pueden contener letras y un espacio sencillo entre palabras.');
            if (!preg_match('/^3\d{9}$/', $telefono)
                || preg_match('/^(\d)\1{9}$/', $telefono)
                || in_array($telefono, ['0123456789','1234567890','9876543210','123456789','234567890','987654321','876543210'], true)) {
                fail('El teléfono debe tener exactamente 10 dígitos, comenzar por 3 y no ser un número repetido o una secuencia consecutiva.');
            }
            $pdo = db();
            $st = $pdo->prepare('SELECT id FROM users WHERE LOWER(email)=? AND id<>?');
            $st->execute([$correo, (int)$user['id']]);
            if ($st->fetchColumn()) fail('Ese correo ya está asociado a otra cuenta.');
            ensure_optional_columns($pdo);
            $pdo->beginTransaction();
            try {
                $st = $pdo->prepare('UPDATE users SET first_name=?, last_name=?, email=? WHERE id=?');
                $st->execute([$nombres, $apellidos, $correo, (int)$user['id']]);
                $st = $pdo->prepare('UPDATE staff SET phone=? WHERE user_id=?');
                $st->execute([$telefono, (int)$user['id']]);
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
            $row = find_user_by_id((int)$user['id']);
            $payload = user_payload($row);
            $_SESSION['user'] = $payload;
            respond(['ok'=>true,'user'=>$payload,'message'=>'Datos actualizados correctamente.']);

        case 'change_professor_password':
            $user = require_roles(['profesor']);
            $d = json_input();
            $actual = (string)($d['claveActual'] ?? '');
            $nueva = (string)($d['nuevaClave'] ?? '');
            $confirmar = (string)($d['confirmarClave'] ?? '');
            if ($actual === '' || $nueva === '' || $confirmar === '') fail('Completa todos los campos de contraseña.');
            if (!preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)\S{8,}$/', $nueva)) fail('La nueva contraseña debe tener mínimo 8 caracteres, con mayúscula, minúscula y número.');
            if ($nueva !== $confirmar) fail('La confirmación de la nueva contraseña no coincide.');
            $st = db()->prepare('SELECT password_hash FROM users WHERE id=? AND status=\'activo\'');
            $st->execute([(int)$user['id']]);
            $hash = (string)$st->fetchColumn();
            if (!$hash || !password_verify($actual, $hash)) fail('La contraseña actual es incorrecta.', 401);
            $nuevoHash = password_hash($nueva, PASSWORD_DEFAULT);
            $st = db()->prepare('UPDATE users SET password_hash=? WHERE id=?');
            $st->execute([$nuevoHash, (int)$user['id']]);
            respond(['ok'=>true,'message'=>'Contraseña actualizada correctamente.']);

        case 'my_profile':
            $user = require_roles(['estudiante']);
            $pdo = db();
            ensure_student_application_table($pdo);
            $st = $pdo->prepare('SELECT u.email, u.first_name, u.last_name, u.identification, s.id AS student_id, s.grade AS student_grade, s.modality, s.target_hours, sa.grade AS application_grade, sa.service_shift AS application_shift, sa.phone, sa.residence_address, sa.eps, sa.guardian_name, sa.guardian_phone FROM users u LEFT JOIN students s ON s.user_id = u.id LEFT JOIN student_applications sa ON sa.id = (SELECT MAX(sa2.id) FROM student_applications sa2 WHERE sa2.student_id = s.id OR sa2.user_id = u.id) WHERE u.id = ? LIMIT 1');
            $st->execute([(int)$user['id']]);
            $p = $st->fetch() ?: [];
            $gradoPerfil = trim((string)($p['student_grade'] ?? ''));
            if ($gradoPerfil === '') $gradoPerfil = trim((string)($p['application_grade'] ?? ''));
            if ($gradoPerfil === '') {
                $stGrade = $pdo->prepare("SELECT grade FROM access_requests WHERE (email = ? OR identification = ?) AND grade IS NOT NULL AND TRIM(grade) <> '' ORDER BY id DESC LIMIT 1");
                $stGrade->execute([$user['correo'], $user['identificacion']]);
                $gradoPerfil = trim((string)($stGrade->fetchColumn() ?: ''));
            }
            respond(['ok'=>true,'profile'=>[
                'correo'=>$p['email'] ?? $user['correo'],
                'nombre'=>trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? '')),
                'identificacion'=>$p['identification'] ?? $user['identificacion'],
                'grado'=>$gradoPerfil,
                'Grado'=>$gradoPerfil,
                'jornada'=>$p['application_shift'] ?? '',
                'Jornada'=>$p['application_shift'] ?? '',
                'modalidad'=>$p['modality'] ?? '',
                'metaHoras'=>$p['target_hours'] ?? null,
                'telefono'=>$p['phone'] ?? '', 'direccion'=>$p['residence_address'] ?? '',
                'eps'=>$p['eps'] ?? '', 'nombreAcudiente'=>$p['guardian_name'] ?? '', 'telefonoAcudiente'=>$p['guardian_phone'] ?? ''
            ]]);

        case 'update_student_profile':
            $user = require_roles(['estudiante']);
            $d = json_input();
            $correo = strtolower(trim((string)($d['correo'] ?? '')));
            $telefono = trim((string)($d['telefono'] ?? ''));
            $direccion = trim((string)($d['direccion'] ?? ''));
            $eps = trim((string)($d['eps'] ?? ''));
            $acudiente = capitalizarNombre((string)($d['nombreAcudiente'] ?? ''));
            $telAcudiente = trim((string)($d['telefonoAcudiente'] ?? ''));
            $epsSeleccionada = trim((string)($d['epsSeleccionada'] ?? ''));
            if (!$correo || !filter_var($correo, FILTER_VALIDATE_EMAIL)) fail('Escribe un correo electrónico válido.');
            if (!$telefono || !$direccion || !$eps || !$acudiente || !$telAcudiente) fail('Completa todos los datos editables.');
            $telefono = preg_replace('/\D+/', '', $telefono);
            $telAcudiente = preg_replace('/\D+/', '', $telAcudiente);
            if (!preg_match('/^3\d{9}$/', $telefono)) fail('El celular debe tener exactamente 10 dígitos y comenzar por 3.');
            if (preg_match('/^(\d)\1{9}$/', $telefono) || $telefono === '1234567890') fail('El celular no puede tener todos sus dígitos iguales ni ser la secuencia 1234567890.');
            if (mb_strlen($direccion, 'UTF-8') > 100) fail('La dirección puede tener máximo 100 caracteres.');
            if (mb_strlen($eps, 'UTF-8') > 60) fail('La EPS puede tener máximo 60 caracteres.');
            if ($epsSeleccionada === 'Otro' && !preg_match('/^[\p{L}]+(?: [\p{L}]+)*$/u', $eps)) fail('La EPS de "Otro" solo puede contener letras y espacios sencillos.');
            if (!preg_match('/^[\p{L}]+(?: [\p{L}]+)*$/u', $acudiente) || mb_strlen($acudiente, 'UTF-8') > 60) fail('El nombre del acudiente debe contener solo letras y espacios sencillos, máximo 60 caracteres.');
            if (!preg_match('/^\d{7,10}$/', $telAcudiente)) fail('El teléfono del acudiente debe tener entre 7 y 10 dígitos.');
            $pdo = db();
            $st = $pdo->prepare('SELECT id FROM users WHERE LOWER(email)=? AND id<>?');
            $st->execute([$correo,(int)$user['id']]);
            if ($st->fetchColumn()) fail('Ese correo ya está asociado a otra cuenta.');

            ensure_student_application_table($pdo);
            // Algunas cuentas antiguas tienen user_id vacío en student_applications.
            // Buscamos primero por user_id y, si hace falta, por student_id.
            $st = $pdo->prepare('SELECT id FROM student_applications WHERE user_id = ? OR student_id = (SELECT id FROM students WHERE user_id = ? LIMIT 1) ORDER BY id DESC LIMIT 1');
            $st->execute([(int)$user['id'], (int)$user['id']]);
            $applicationId = (int)($st->fetchColumn() ?: 0);
            if (!$applicationId) fail('No se encontró la solicitud de estudiante asociada a esta cuenta.');

            try {
                $pdo->beginTransaction();
                $st = $pdo->prepare('UPDATE users SET email=? WHERE id=?');
                $st->execute([$correo,(int)$user['id']]);

                // Compatibilidad con instalaciones donde updated_at todavía no existe.
                $st = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='student_applications' AND COLUMN_NAME='updated_at'");
                $st->execute();
                $tieneUpdatedAt = (int)$st->fetchColumn() > 0;
                if ($tieneUpdatedAt) {
                    $st = $pdo->prepare('UPDATE student_applications SET phone=?, residence_address=?, eps=?, guardian_name=?, guardian_phone=?, updated_at=CURRENT_TIMESTAMP WHERE id=?');
                } else {
                    $st = $pdo->prepare('UPDATE student_applications SET phone=?, residence_address=?, eps=?, guardian_name=?, guardian_phone=? WHERE id=?');
                }
                $st->execute([$telefono,$direccion,$eps,$acudiente,$telAcudiente,$applicationId]);
                $pdo->commit();
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('SIGHO update_student_profile: '.$e->getMessage());
                fail('No se pudieron guardar los datos. MySQL rechazó la actualización: '.$e->getMessage(), 500);
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('SIGHO update_student_profile: '.$e->getMessage());
                fail('No se pudieron guardar los datos. '.$e->getMessage(), 500);
            }
            $row=find_user_by_id((int)$user['id']);
            $payload=user_payload($row); $_SESSION['user']=$payload;
            respond(['ok'=>true,'user'=>$payload,'message'=>'Datos actualizados correctamente.']);

        case 'my_records':
            $user = require_roles(['estudiante']);
            respond(['ok' => true, 'records' => get_records((int)$user['studentId'])]);

        case 'teacher_data':
            $user = require_roles(['profesor','administrador']);
            if ($user['rol'] === 'profesor') {
                ensure_optional_columns(db());
                respond(['ok' => true, 'records' => get_records(null, true, (int)$user['staffId'])]);
            }
            respond(['ok' => true, 'records' => get_records()]);

        case 'teacher_students':
            $user = require_roles(['profesor']);
            ensure_student_application_table(db());
            ensure_optional_columns(db());
            $st = db()->prepare('SELECT service_site, service_project FROM staff WHERE id = ?');
            $st->execute([(int)$user['staffId']]);
            $teacher = $st->fetch();
            if (!$teacher || empty($teacher['service_site']) || empty($teacher['service_project'])) {
                respond(['ok'=>true,'students'=>[],'sede'=>$teacher['service_site']??'','proyecto'=>$teacher['service_project']??'']);
            }
            $sql = "SELECT DISTINCT u.id, u.email AS correo, CONCAT(u.first_name, ' ', u.last_name) AS nombre, s.grade AS grado,
                           s.target_hours AS metaHoras,
                           sa.project AS proyecto,
                           sa.service_site AS sede
                    FROM students s JOIN users u ON u.id=s.user_id
                    JOIN student_applications sa ON sa.student_id=s.id
                    WHERE sa.id=(SELECT MAX(sa2.id) FROM student_applications sa2 WHERE sa2.student_id=s.id)
                      AND sa.service_site=?
                      AND sa.project=?
                    ORDER BY u.first_name,u.last_name";
            $st=db()->prepare($sql);$st->execute([$teacher['service_site'],$teacher['service_project']]);
            respond(['ok'=>true,'students'=>$st->fetchAll(),'sede'=>$teacher['service_site'],'proyecto'=>$teacher['service_project']]);

        case 'create_record':
            $user = require_roles(['estudiante']);

            // Solo una cuenta de estudiante aceptada y autenticada puede registrar horas.
            $studentId = (int)($user['studentId'] ?? 0);
            if ($studentId <= 0) fail('La cuenta de estudiante no está habilitada para registrar horas.', 403);

            $fecha = trim((string)($_POST['fecha'] ?? ''));
            $horas = trim((string)($_POST['horas'] ?? ''));
            $descripcion = trim((string)($_POST['descripcion'] ?? ''));

            if (!$fecha || !$horas || !$descripcion) {
                fail('Faltan datos del registro.');
            }

            // A. Fecha: únicamente días del año actual y nunca una fecha futura.
            $hoy = new DateTime('today');
            $anioActual = (int)$hoy->format('Y');
            $fechaObj = DateTime::createFromFormat('!Y-m-d', $fecha);
            $erroresFecha = DateTime::getLastErrors();
            $fechaValida = $fechaObj && (!$erroresFecha || ($erroresFecha['warning_count'] === 0 && $erroresFecha['error_count'] === 0)) && $fechaObj->format('Y-m-d') === $fecha;

            if (!$fechaValida) fail('La fecha seleccionada no es válida.');
            if ((int)$fechaObj->format('Y') !== $anioActual || $fecha < $anioActual . '-01-01' || $fecha > $hoy->format('Y-m-d')) {
                fail("La fecha debe pertenecer al año {$anioActual} y no puede ser posterior a hoy.");
            }

            // B. Horas realizadas: únicamente enteros entre 1 y 50.
            if (!preg_match('/^[0-9]{1,2}$/', $horas)) {
                fail('Las horas realizadas deben ser números enteros del 1 al 50.');
            }
            $hours = (int)$horas;
            if ($hours < 1 || $hours > 50) {
                fail('Las horas realizadas deben ser números enteros del 1 al 50.');
            }

            // C. Restricción por grado. Ciclo V mantiene horario flexible.
            $st = db()->prepare('SELECT grade FROM students WHERE id = ? AND user_id = ? LIMIT 1');
            $st->execute([$studentId, (int)$user['id']]);
            $grado = trim((string)($st->fetchColumn() ?: ($user['grado'] ?? '')));
            if (!$grado) fail('No fue posible determinar el grado del estudiante.', 409);

            // Normalizamos pequeñas variaciones antiguas del grado (10, 10°, Grado 10, etc.).
            $gradoTexto = mb_strtolower(trim($grado), 'UTF-8');
            if (strpos($gradoTexto, 'ciclo v') !== false) {
                $grado = 'Ciclo V';
            } elseif (preg_match('/(^|\D)11(\D|$)/u', $gradoTexto)) {
                $grado = '11°';
            } elseif (preg_match('/(^|\D)10(\D|$)/u', $gradoTexto)) {
                $grado = '10°';
            }

            if (!in_array($grado, ['10°', '11°', 'Ciclo V'], true)) {
                fail('El grado del estudiante no es válido para registrar horas.', 409);
            }

            if (mb_strlen($descripcion, 'UTF-8') > 1000) fail('La descripción puede tener máximo 1000 caracteres.');

            // Evidencia obligatoria. El servidor valida MIME real, no solo la extensión.
            if (empty($_FILES['evidencia']) || $_FILES['evidencia']['error'] !== UPLOAD_ERR_OK) {
                fail('Debes adjuntar una evidencia.');
            }
            $file = $_FILES['evidencia'];
            if ((int)$file['size'] <= 0) fail('La evidencia está vacía.');
            if ((int)$file['size'] > 5 * 1024 * 1024) fail('La evidencia debe pesar máximo 5 MB.');

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
            $allowed = ['image/jpeg'];
            if (!in_array($mime, $allowed, true)) fail('Solo se permiten imágenes JPG o JPEG.');

            if (!is_dir(EVIDENCE_DIR) && !mkdir(EVIDENCE_DIR, 0775, true) && !is_dir(EVIDENCE_DIR)) {
                fail('No fue posible preparar el almacenamiento de la evidencia.', 500);
            }

            $extPorMime = ['image/jpeg' => 'jpg'];
            $stored = bin2hex(random_bytes(16)) . '.' . $extPorMime[$mime];
            $target = EVIDENCE_DIR . DIRECTORY_SEPARATOR . $stored;
            if (!move_uploaded_file($file['tmp_name'], $target)) {
                fail('No fue posible guardar la evidencia.', 500);
            }
            $relative = 'uploads/evidence/' . $stored;

            $pdo = db();
            // Las nuevas jornadas ya no usan entrada/salida. Dejamos esas columnas como NULL para conservar compatibilidad con registros antiguos.
            try {
                $pdo->exec("ALTER TABLE hour_records MODIFY entry_time TIME NULL, MODIFY exit_time TIME NULL");
            } catch (Throwable $e) { /* si ya son NULL o el motor no requiere el cambio, continuamos */ }
            try {
                $st = $pdo->prepare('INSERT INTO hour_records (student_id, record_date, entry_time, exit_time, hours, activity_description, evidence_original_name, evidence_path, evidence_mime) VALUES (?,?,?,?,?,?,?,?,?)');
                $st->execute([
                    $studentId,
                    $fecha,
                    null,
                    null,
                    $hours,
                    $descripcion,
                    basename((string)$file['name']),
                    $relative,
                    $mime
                ]);
            } catch (Throwable $e) {
                @unlink($target);
                throw $e;
            }

            $id = (int)$pdo->lastInsertId();
            $records = get_records($studentId);
            $record = array_values(array_filter($records, fn($r) => (int)$r['id'] === $id))[0] ?? null;
            if (!$record) fail('La jornada fue guardada, pero no fue posible recuperar su información.', 500);

            respond([
                'ok' => true,
                'message' => 'Jornada registrada correctamente. Quedó pendiente de revisión por el profesor.',
                'record' => $record
            ]);

        case 'review_record':
            $user = require_roles(['profesor','administrador']);
            $d = json_input();
            $id = (int)($d['id'] ?? 0);
            $newStatus = (string)($d['estado'] ?? '');
            $observation = trim((string)($d['observacion'] ?? ''));
            if (!$id) fail('Registro inválido.');
            if (!in_array($newStatus, ['pendiente','aprobada','rechazada'], true)) fail('Estado inválido.');
            $pdo = db();
            $st = $pdo->prepare('SELECT * FROM hour_records WHERE id = ?');
            $st->execute([$id]);
            $record = $st->fetch();
            if (!$record) fail('Registro no encontrado.', 404);
            $staffId = (int)$user['staffId'];
            if ($user['rol'] === 'profesor') {
                ensure_student_application_table($pdo);
                ensure_optional_columns($pdo);
                $stArea = $pdo->prepare("SELECT st.service_site, st.service_project, sa.service_site AS student_site, sa.project AS student_project FROM staff st JOIN students ss ON ss.id=? JOIN student_applications sa ON sa.student_id=ss.id AND sa.id=(SELECT MAX(sa2.id) FROM student_applications sa2 WHERE sa2.student_id=ss.id) WHERE st.id=?");
                $stArea->execute([(int)$record['student_id'], $staffId]);
                $area = $stArea->fetch();
                if (!$area || empty($area['service_site']) || empty($area['service_project']) || $area['service_site'] !== $area['student_site'] || $area['service_project'] !== $area['student_project']) {
                    fail('Este registro no pertenece a tu sede y proyecto asignados.', 403);
                }
            }
            $oldStatus = $record['status'];
            $pdo->beginTransaction();
            try {
                $st = $pdo->prepare('UPDATE hour_records SET status = ?, observation = ?, rejection_reason = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?');
                $reason = $newStatus === 'rechazada' ? $observation : null;
                $st->execute([$newStatus, $observation, $reason, $staffId, $id]);
                if ($oldStatus !== $newStatus || $observation !== ($record['observation'] ?? '')) {
                    $st = $pdo->prepare('INSERT INTO review_history (hour_record_id, reviewer_staff_id, previous_status, new_status, observation) VALUES (?,?,?,?,?)');
                    $st->execute([$id, $staffId, $oldStatus, $newStatus, $observation ?: null]);
                }
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack(); throw $e;
            }
            respond(['ok' => true, 'message' => 'Registro actualizado.']);

        case 'assign_teacher':
            $user = require_roles(['administrador']);
            $d = json_input();
            $staffId = (int)($d['staffId'] ?? 0);
            $sede = trim((string)($d['sede'] ?? ''));
            $proyecto = trim((string)($d['proyecto'] ?? ''));
            $sedes = ['Central JT','San Francisco Club','Picaleña','Central J.N.','Secundino Porras Cruz','San Martín','Central JM','Bello Horizonte'];
            $proyectos = ['Educación Física / Tiempo Libre','Proyecto Ambiental','Logística y Vigilancia','Secretaría y/o Archivo','Acompañamiento a un docente de transición o primaria','Eventos especiales','Otro'];
            if (!$staffId || !in_array($sede,$sedes,true)) fail('Sede inválida.');
            if (!$proyecto || !in_array($proyecto,$proyectos,true)) fail('Proyecto inválido.');
            ensure_optional_columns(db());
            $st=db()->prepare("SELECT s.id FROM staff s JOIN users u ON u.id=s.user_id JOIN roles r ON r.id=u.role_id WHERE s.id=? AND r.code='profesor' AND u.status='activo'");
            $st->execute([$staffId]); if(!$st->fetchColumn()) fail('Profesor no encontrado.',404);
            $st=db()->prepare('UPDATE staff SET service_site=?, service_project=? WHERE id=?');
            $st->execute([$sede,$proyecto,$staffId]);
            respond(['ok'=>true,'message'=>'Sede y proyecto asignados correctamente.']);

        case 'request_admin_permission':
            $user = require_roles(['profesor']);
            $pdo = db();
            ensure_optional_columns($pdo);
            $st = $pdo->prepare("SELECT COUNT(*) FROM access_requests WHERE status='pendiente' AND requested_role_id=(SELECT id FROM roles WHERE code='administrador') AND email=?");
            $st->execute([$user['correo']]);
            if ((int)$st->fetchColumn() > 0) fail('Ya tienes una solicitud de Administrador pendiente.');
            $st = $pdo->prepare("SELECT COUNT(*) FROM users u WHERE u.id=? AND u.status='activo' AND u.role_id=(SELECT id FROM roles WHERE code='administrador')");
            $st->execute([(int)$user['id']]);
            if ((int)$st->fetchColumn() > 0) fail('Tu cuenta ya tiene permisos de Administrador.');
            $st = $pdo->prepare("INSERT INTO access_requests (identification, first_name, last_name, email, password_hash, requested_role_id, status, service_project) SELECT u.identification,u.first_name,u.last_name,u.email,u.password_hash,r.id,'pendiente',s.service_project FROM users u JOIN roles r ON r.code='administrador' LEFT JOIN staff s ON s.user_id=u.id WHERE u.id=?");
            $st->execute([(int)$user['id']]);
            respond(['ok'=>true,'message'=>'Solicitud de permisos de Administrador enviada al panel administrativo.']);

        case 'validate_admin_request_by_admin':
            $admin = require_roles(['administrador']);
            $d = json_input();
            $id = (int)($d['id'] ?? 0);
            $status = (string)($d['estado'] ?? '');
            if (!$id || !in_array($status,['aceptada','rechazada'],true)) fail('Solicitud inválida.');
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $st=$pdo->prepare("SELECT ar.*, r.code AS role_code FROM access_requests ar JOIN roles r ON r.id=ar.requested_role_id WHERE ar.id=? AND ar.status='pendiente' FOR UPDATE");
                $st->execute([$id]); $r=$st->fetch();
                if(!$r || $r['role_code']!=='administrador') fail('Solicitud de Administrador no encontrada.');
                if($status==='aceptada'){
                    $roleId=(int)$pdo->query("SELECT id FROM roles WHERE code='administrador'")->fetchColumn();
                    $st=$pdo->prepare("UPDATE users SET role_id=? WHERE LOWER(email)=LOWER(?) AND status='activo'");
                    $st->execute([$roleId,$r['email']]);
                    if ($st->rowCount() < 1) fail('No se encontró la cuenta del profesor solicitante.');
                }
                $st=$pdo->prepare('UPDATE access_requests SET status=?, validated_by=?, validated_at=NOW() WHERE id=?');
                $st->execute([$status,(int)$admin['id'],$id]);
                $pdo->commit();
            }catch(Throwable $e){ if($pdo->inTransaction())$pdo->rollBack(); throw $e; }
            respond(['ok'=>true,'message'=>$status==='aceptada'?'Permisos de Administrador concedidos correctamente.':'Solicitud de Administrador rechazada.']);

        case 'admin_data':
            require_roles(['administrador']);
            $pdo = db();
            $users = [];
            $st = $pdo->query("SELECT u.*, r.code AS role_code FROM users u JOIN roles r ON r.id=u.role_id WHERE u.status='activo' AND r.code IN ('estudiante','profesor') ORDER BY u.first_name, u.last_name");
            foreach ($st->fetchAll() as $u) $users[] = user_payload($u);
            $profesores = [];
            $st = $pdo->query("SELECT u.*, r.code AS role_code, s.id AS staff_id, s.service_site, s.service_project FROM users u JOIN roles r ON r.id=u.role_id JOIN staff s ON s.user_id=u.id WHERE u.status='activo' AND r.code='profesor' ORDER BY u.first_name,u.last_name");
            foreach ($st->fetchAll() as $u) $profesores[] = ['id'=>(int)$u['staff_id'],'userId'=>(int)$u['id'],'nombre'=>trim($u['first_name'].' '.$u['last_name']),'correo'=>$u['email'],'sede'=>$u['service_site']??'','proyecto'=>$u['service_project']??''];
            respond([
                'ok' => true,
                'users' => $users,
                'profesores' => $profesores,
                'records' => get_records(),
                'requests' => array_merge(pending_requests('estudiante'), pending_requests('profesor')),
                'adminRequests' => pending_requests('administrador')
            ]);

        case 'validate_request':
            require_roles(['administrador']);
            $d = json_input();
            $id = (int)($d['id'] ?? 0);
            $status = (string)($d['estado'] ?? '');
            if (!$id || !in_array($status, ['aceptada','rechazada'], true)) fail('Solicitud inválida.');
            $pdo = db();
            // Las operaciones de creación/alteración de tablas deben hacerse
            // fuera de la transacción porque MySQL puede hacer COMMIT implícito.
            ensure_student_application_table($pdo);
            ensure_optional_columns($pdo);
            $pdo->beginTransaction();
            try {
                $st = $pdo->prepare("SELECT ar.*, r.code AS role_code FROM access_requests ar JOIN roles r ON r.id=ar.requested_role_id WHERE ar.id=? AND ar.status='pendiente' FOR UPDATE");
                $st->execute([$id]);
                $r = $st->fetch();
                if (!$r) fail('Solicitud no encontrada o ya procesada.');
                if (!in_array($r['role_code'], ['estudiante','profesor'], true)) fail('Esta solicitud corresponde al Super Admin.');
                if ($status === 'aceptada') {
                    $roleId = (int)$pdo->query('SELECT id FROM roles WHERE code=' . $pdo->quote($r['role_code']))->fetchColumn();
                    $st = $pdo->prepare('INSERT INTO users (identification, first_name, last_name, email, password_hash, role_id, status) VALUES (?,?,?,?,?,?,\'activo\')');
                    $st->execute([$r['identification'],$r['first_name'],$r['last_name'],$r['email'],$r['password_hash'],$roleId]);
                    $userId = (int)$pdo->lastInsertId();
                    if ($r['role_code'] === 'estudiante') {
                        $target = $r['modality'] === 'tecnico' ? 100 : 120;
                        $st = $pdo->prepare('INSERT INTO students (user_id, student_code, grade, modality, technical_program, target_hours) VALUES (?,?,?,?,?,?)');
                        $st->execute([$userId,$r['identification'],$r['grade'],$r['modality'],$r['technical_program'],$target]);
                        $studentId = (int)$pdo->lastInsertId();

                        $st = $pdo->prepare('UPDATE student_applications SET user_id = ?, student_id = ?, updated_at = CURRENT_TIMESTAMP WHERE access_request_id = ?');
                        $st->execute([$userId, $studentId, $id]);
                    } else {
                        $st = $pdo->prepare('INSERT INTO staff (user_id, staff_code, position, phone) VALUES (?,?,\'Docente\',?)');
                        $st->execute([$userId,$r['identification'],$r['phone'] ?? null]);
                        $st = $pdo->prepare('UPDATE staff SET service_site = NULL, service_project = NULL WHERE user_id = ?');
                        $st->execute([$userId]);
                    }
                }
                $st = $pdo->prepare('UPDATE access_requests SET status=?, validated_by=?, validated_at=NOW() WHERE id=?');
                $st->execute([$status, (int)require_login()['id'], $id]);
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack(); throw $e;
            }
            respond(['ok'=>true]);

        case 'superadmin_data':
            require_roles(['superadmin']);
            $pdo = db();
            $requests = pending_requests('administrador');
            $totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status='activo'")->fetchColumn();
            $totalAdmins = (int)$pdo->query("SELECT COUNT(*) FROM users u JOIN roles r ON r.id=u.role_id WHERE u.status='activo' AND r.code='administrador'")->fetchColumn();
            respond(['ok'=>true,'requests'=>$requests,'totalUsuarios'=>$totalUsers,'totalAdministradores'=>$totalAdmins]);

        case 'validate_admin_request':
            require_roles(['superadmin']);
            $d = json_input();
            $id = (int)($d['id'] ?? 0);
            $status = (string)($d['estado'] ?? '');
            if (!$id || !in_array($status,['aceptada','rechazada'],true)) fail('Solicitud inválida.');
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $st=$pdo->prepare("SELECT ar.*, r.code AS role_code FROM access_requests ar JOIN roles r ON r.id=ar.requested_role_id WHERE ar.id=? AND ar.status='pendiente' FOR UPDATE");
                $st->execute([$id]); $r=$st->fetch();
                if(!$r || $r['role_code']!=='administrador') fail('Solicitud no encontrada o no corresponde a administrador.');
                if($status==='aceptada'){
                    $roleId=(int)$pdo->query("SELECT id FROM roles WHERE code='administrador'")->fetchColumn();
                    $st=$pdo->prepare("INSERT INTO users (identification, first_name, last_name, email, password_hash, role_id, status) VALUES (?,?,?,?,?,?, 'activo')");
                    $st->execute([$r['identification'],$r['first_name'],$r['last_name'],$r['email'],$r['password_hash'],$roleId]);
                    $userId=(int)$pdo->lastInsertId();
                    $st=$pdo->prepare("INSERT INTO staff (user_id, staff_code, position) VALUES (?,?, 'Docente Administrador')");
                    $st->execute([$userId,$r['identification']]);
                }
                $st=$pdo->prepare('UPDATE access_requests SET status=?, validated_by=?, validated_at=NOW() WHERE id=?');
                $st->execute([$status,(int)require_login()['id'],$id]);
                $pdo->commit();
            }catch(Throwable $e){ if($pdo->inTransaction())$pdo->rollBack(); throw $e; }
            respond(['ok'=>true]);

        case 'reset_records':
            require_roles(['administrador']);
            $pdo=db();
            $pdo->exec('DELETE FROM hour_records');
            respond(['ok'=>true]);

        case 'evidence':
            handle_evidence((int)($_GET['id'] ?? 0));
case 'pending_requests':
    require_roles(['administrador']);

    $requests = pending_requests('estudiante');

    $requests = array_merge(
        $requests,
        pending_requests('profesor')
    );

    respond([
        'ok' => true,
        'requests' => $requests
    ]);
        default:
            fail('Acción no encontrada.', 404);
    }
} catch (PDOException $e) {
    error_log('SIGHO PDO: ' . $e->getMessage());
    fail('Error SQL real: ' . $e->getMessage(), 500);
} catch (Throwable $e) {
    error_log('SIGHO Throwable: ' . $e->getMessage());
    fail('Error interno del servidor: ' . $e->getMessage(), 500);
}
