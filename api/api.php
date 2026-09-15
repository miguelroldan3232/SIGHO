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
        $st = db()->prepare('SELECT id, grade, modality, technical_program, target_hours FROM students WHERE user_id = ?');
        $st->execute([$row['id']]);
        $s = $st->fetch();
        if ($s) {
            $payload['studentId'] = (int)$s['id'];
            $payload['grado'] = $s['grade'] ?? '';
            $payload['esTecnico'] = $s['modality'] === 'tecnico';
            $payload['programaTecnico'] = $s['technical_program'] ?? '';
            $payload['metaHoras'] = (float)$s['target_hours'];
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
        ['access_requests', 'service_project', "ALTER TABLE access_requests ADD COLUMN service_project VARCHAR(200) NULL AFTER service_site"]
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
        'entrada' => substr($r['entry_time'], 0, 5),
        'salida' => substr($r['exit_time'], 0, 5),
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
                   s.grade AS grade
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
                   sa.service_site,
                   sa.project,
                   sa.project_other,
                   sa.service_days,
                   sa.service_shift,
                   ar.service_site AS request_service_site,
                   ar.service_project AS request_service_project
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
            'sede' => $r['service_site'] ?? '',
            'proyecto' => $r['project'] ?? '',
            'proyectoOtro' => $r['project_other'] ?? '',
            'diasServicio' => $r['service_days'] ? (json_decode($r['service_days'], true) ?: []) : [],
            'jornada' => $r['service_shift'] ?? '',
            'sedeProfesor' => $r['request_service_site'] ?? '',
            'proyectoProfesor' => $r['request_service_project'] ?? ''
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
            if (!$rol || !$correo || !$clave) fail('Completa todos los campos.');

            $st = db()->prepare("SELECT u.*, r.code AS role_code FROM users u JOIN roles r ON r.id = u.role_id WHERE LOWER(u.email) = ? AND u.status = 'activo'");
            $st->execute([$correo]);
            $u = $st->fetch();
            if (!$u || $u['role_code'] !== $rol || !password_verify($clave, $u['password_hash'])) {
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

            if (!$rol || !$nombre || !$apellido || !$ident || !$correo || !$clave) {
                fail('Completa todos los campos.');
            }
            if (!in_array($rol, ['estudiante','profesor'], true)) {
                fail('En las solicitudes solo se permite el rol Estudiante o Profesor.');
            }
            if (strlen($clave) < 6) {
                fail('La contraseña debe tener mínimo 6 caracteres.');
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

                $anioServicio = (int)($d['anioServicio'] ?? 0);
                $actualYear = (int)date('Y');
                $primerApellido = trim((string)($d['primerApellido'] ?? ''));
                $segundoApellido = trim((string)($d['segundoApellido'] ?? ''));
                $primerNombre = trim((string)($d['primerNombre'] ?? ''));
                $segundoNombre = trim((string)($d['segundoNombre'] ?? ''));
                $tipoDocumento = trim((string)($d['tipoDocumento'] ?? ''));
                $numeroDocumento = trim((string)($d['numeroDocumento'] ?? ''));
                $gradoEstudiante = trim((string)($d['grado'] ?? ''));
                $fechaNacimiento = trim((string)($d['fechaNacimiento'] ?? ''));
                $direccion = trim((string)($d['direccion'] ?? ''));
                $telefono = trim((string)($d['telefono'] ?? ''));
                $eps = trim((string)($d['eps'] ?? ''));
                $nombreAcudiente = trim((string)($d['nombreAcudiente'] ?? ''));
                $telefonoAcudiente = trim((string)($d['telefonoAcudiente'] ?? ''));
                $sede = trim((string)($d['sede'] ?? ''));
                $proyecto = trim((string)($d['proyecto'] ?? ''));
                $proyectoOtro = trim((string)($d['proyectoOtro'] ?? ''));
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
                    'Otro'
                ];

                $diasPermitidos = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
                $jornadasPermitidas = ['JM','JN','Mañana','Nocturna'];

                if ($anioServicio < $actualYear || $anioServicio > 2100) {
                    fail("El año del servicio social debe estar entre {$actualYear} y 2100.");
                }
                if (!$primerApellido || !$primerNombre || !$tipoDocumento || !$numeroDocumento ||
                    !$gradoEstudiante || !$fechaNacimiento || !$direccion || !$telefono || !$eps ||
                    !$nombreAcudiente || !$telefonoAcudiente || !$sede || !$proyecto || !$jornada || !is_array($diasServicio) ||
                    count($diasServicio) === 0) {
                    fail('Completa todos los campos obligatorios del estudiante.');
                }
                if (!in_array($tipoDocumento, ['Tarjeta de identidad','Cédula de ciudadanía'], true)) {
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
                if ($proyecto === 'Otro' && !$proyectoOtro) {
                    fail('Debes especificar el proyecto.');
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
                $proyectosPermitidosProfesor = ['Educación Física / Tiempo Libre','Proyecto Ambiental','Logística y Vigilancia','Secretaría y/o Archivo','Acompañamiento a un docente de transición o primaria','Otro'];
                if (!$proyectoProfesor || !in_array($proyectoProfesor, $proyectosPermitidosProfesor, true)) {
                    fail('Selecciona un proyecto válido para el profesor.');
                }
                // La sede del profesor NO se solicita públicamente: la asigna el Administrador.
                $sedeProfesor = null;
            }

            ensure_optional_columns($pdo);
            $pdo->beginTransaction();
            try {
                $st = $pdo->prepare('INSERT INTO access_requests (identification, first_name, last_name, email, password_hash, requested_role_id, modality, technical_program, grade, service_site, service_project) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
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
                    $rol === 'profesor' ? $proyectoProfesor : null
                ]);
                $accessRequestId = (int)$pdo->lastInsertId();

                if ($rol === 'estudiante') {
                    $st = $pdo->prepare('
                        INSERT INTO student_applications
                        (access_request_id, service_year, first_surname, second_surname, first_name, second_name,
                         document_type, document_number, grade, birth_date, residence_address, phone, eps,
                         guardian_name, guardian_phone, service_site, project, project_other, service_days, service_shift)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
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
                        $telefono,
                        $eps,
                        $nombreAcudiente,
                        $telefonoAcudiente,
                        $sede,
                        $proyecto,
                        $proyecto === 'Otro' ? $proyectoOtro : null,
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
            $st = $pdo->prepare("SELECT u.first_name, u.last_name, u.email, u.identification, s.phone, s.service_site, s.service_project FROM users u LEFT JOIN staff s ON s.user_id=u.id WHERE u.id=? LIMIT 1");
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
                'proyecto'=>$p['service_project'] ?? ''
            ]]);

        case 'update_professor_profile':
            $user = require_roles(['profesor']);
            $d = json_input();
            $nombres = trim((string)($d['nombres'] ?? ''));
            $apellidos = trim((string)($d['apellidos'] ?? ''));
            $correo = strtolower(trim((string)($d['correo'] ?? '')));
            $telefono = trim((string)($d['telefono'] ?? ''));
            $proyecto = trim((string)($d['proyecto'] ?? ''));
            $proyectosPermitidos = ['Educación Física / Tiempo Libre','Proyecto Ambiental','Logística y Vigilancia','Secretaría y/o Archivo','Acompañamiento a un docente de transición o primaria','Otro'];
            if ($proyecto === '' || !in_array($proyecto, $proyectosPermitidos, true)) fail('Selecciona un proyecto válido.');
            if ($nombres === '' || $apellidos === '' || $correo === '' || $telefono === '') fail('Completa todos los datos editables.');
            if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || !is_institutional_email($correo)) {
                fail('El correo del profesor debe utilizar el dominio institucional @' . institutional_domain() . '.');
            }
            if (mb_strlen($nombres) < 2 || mb_strlen($apellidos) < 2) fail('Escribe nombres y apellidos válidos.');
            $pdo = db();
            $st = $pdo->prepare('SELECT id FROM users WHERE LOWER(email)=? AND id<>?');
            $st->execute([$correo, (int)$user['id']]);
            if ($st->fetchColumn()) fail('Ese correo ya está asociado a otra cuenta.');
            ensure_optional_columns($pdo);
            $pdo->beginTransaction();
            try {
                $st = $pdo->prepare('UPDATE users SET first_name=?, last_name=?, email=? WHERE id=?');
                $st->execute([$nombres, $apellidos, $correo, (int)$user['id']]);
                $st = $pdo->prepare('UPDATE staff SET phone=?, service_project=? WHERE user_id=?');
                $st->execute([$telefono, $proyecto, (int)$user['id']]);
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
            if (strlen($nueva) < 6) fail('La nueva contraseña debe tener mínimo 6 caracteres.');
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
            $st = $pdo->prepare('SELECT u.email, sa.phone, sa.residence_address, sa.eps, sa.guardian_name, sa.guardian_phone FROM users u LEFT JOIN student_applications sa ON sa.user_id = u.id WHERE u.id = ? ORDER BY sa.id DESC LIMIT 1');
            $st->execute([(int)$user['id']]);
            $p = $st->fetch() ?: [];
            respond(['ok'=>true,'profile'=>[
                'correo'=>$p['email'] ?? $user['correo'], 'telefono'=>$p['phone'] ?? '', 'direccion'=>$p['residence_address'] ?? '',
                'eps'=>$p['eps'] ?? '', 'nombreAcudiente'=>$p['guardian_name'] ?? '', 'telefonoAcudiente'=>$p['guardian_phone'] ?? ''
            ]]);

        case 'update_student_profile':
            $user = require_roles(['estudiante']);
            $d = json_input();
            $correo = strtolower(trim((string)($d['correo'] ?? '')));
            $telefono = trim((string)($d['telefono'] ?? ''));
            $direccion = trim((string)($d['direccion'] ?? ''));
            $eps = trim((string)($d['eps'] ?? ''));
            $acudiente = trim((string)($d['nombreAcudiente'] ?? ''));
            $telAcudiente = trim((string)($d['telefonoAcudiente'] ?? ''));
            if (!$correo || !filter_var($correo, FILTER_VALIDATE_EMAIL)) fail('Escribe un correo electrónico válido.');
            if (!$telefono || !$direccion || !$eps || !$acudiente || !$telAcudiente) fail('Completa todos los datos editables.');
            $pdo = db();
            $st = $pdo->prepare('SELECT id FROM users WHERE LOWER(email)=? AND id<>?'); $st->execute([$correo,(int)$user['id']]);
            if ($st->fetchColumn()) fail('Ese correo ya está asociado a otra cuenta.');
            $pdo->beginTransaction();
            try {
                $st=$pdo->prepare('UPDATE users SET email=? WHERE id=?'); $st->execute([$correo,(int)$user['id']]);
                ensure_student_application_table($pdo);
                $st=$pdo->prepare('UPDATE student_applications SET phone=?, residence_address=?, eps=?, guardian_name=?, guardian_phone=?, updated_at=CURRENT_TIMESTAMP WHERE user_id=?');
                $st->execute([$telefono,$direccion,$eps,$acudiente,$telAcudiente,(int)$user['id']]);
                $pdo->commit();
            } catch(Throwable $e) { if($pdo->inTransaction())$pdo->rollBack(); throw $e; }
            $row=find_user_by_id((int)$user['id']);
            $payload=user_payload($row); $_SESSION['user']=$payload;
            respond(['ok'=>true,'user'=>$payload]);

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
            $fecha = $_POST['fecha'] ?? '';
            $entrada = $_POST['entrada'] ?? '';
            $salida = $_POST['salida'] ?? '';
            $descripcion = trim($_POST['descripcion'] ?? '');
            if (!$fecha || !$entrada || !$salida || !$descripcion) fail('Faltan datos del registro.');
            $start = strtotime($fecha . ' ' . $entrada);
            $end = strtotime($fecha . ' ' . $salida);
            $hours = ($end - $start) / 3600;
            if ($hours <= 0) fail('La hora de salida debe ser posterior a la entrada.');
            if (empty($_FILES['evidencia']) || $_FILES['evidencia']['error'] !== UPLOAD_ERR_OK) fail('Debes adjuntar una evidencia.');
            $file = $_FILES['evidencia'];
            if ($file['size'] > 5 * 1024 * 1024) fail('La evidencia debe pesar máximo 5 MB.');
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
            $allowed = ['image/jpeg','image/png','image/webp','image/gif','application/pdf'];
            if (!in_array($mime, $allowed, true)) fail('Solo se permiten imágenes o PDF.');
            if (!is_dir(EVIDENCE_DIR)) mkdir(EVIDENCE_DIR, 0775, true);
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $stored = bin2hex(random_bytes(16)) . ($ext ? '.' . preg_replace('/[^a-zA-Z0-9]/', '', $ext) : '');
            $target = EVIDENCE_DIR . DIRECTORY_SEPARATOR . $stored;
            if (!move_uploaded_file($file['tmp_name'], $target)) fail('No fue posible guardar la evidencia.', 500);
            $relative = 'uploads/evidence/' . $stored;

            $pdo = db();
            $st = $pdo->prepare('INSERT INTO hour_records (student_id, record_date, entry_time, exit_time, hours, activity_description, evidence_original_name, evidence_path, evidence_mime) VALUES (?,?,?,?,?,?,?,?,?)');
            try {
                $st->execute([(int)$user['studentId'], $fecha, $entrada, $salida, $hours, $descripcion, $file['name'], $relative, $mime]);
            } catch (Throwable $e) {
                @unlink($target); throw $e;
            }
            $id = (int)$pdo->lastInsertId();
            $records = get_records((int)$user['studentId']);
            $record = array_values(array_filter($records, fn($r) => (int)$r['id'] === $id))[0] ?? null;
            respond(['ok' => true, 'record' => $record]);

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
            $proyectos = ['Educación Física / Tiempo Libre','Proyecto Ambiental','Logística y Vigilancia','Secretaría y/o Archivo','Acompañamiento a un docente de transición o primaria','Otro'];
            if (!$staffId || !in_array($sede,$sedes,true) || !in_array($proyecto,$proyectos,true)) fail('Sede o proyecto inválido.');
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

                        ensure_student_application_table($pdo);
                        $st = $pdo->prepare('UPDATE student_applications SET user_id = ?, student_id = ?, updated_at = CURRENT_TIMESTAMP WHERE access_request_id = ?');
                        $st->execute([$userId, $studentId, $id]);
                    } else {
                        $st = $pdo->prepare('INSERT INTO staff (user_id, staff_code, position) VALUES (?,?,\'Docente\')');
                        $st->execute([$userId,$r['identification']]);
                        ensure_optional_columns($pdo);
                        $st = $pdo->prepare('UPDATE staff SET service_site = NULL, service_project = ? WHERE user_id = ?');
                        $st->execute([$r['service_project'] ?? null, $userId]);
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
    error_log($e->getMessage());
    fail('Error de base de datos. Verifica que MySQL esté encendido y que la base de datos jjfh_servicio_social exista.', 500);
} catch (Throwable $e) {
    error_log($e->getMessage());
    fail('Error interno del servidor.', 500);
}
