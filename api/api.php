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

function ensure_email_verification_table(PDO $pdo): void {
    static $ready = false;
    if ($ready) return;
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS account_email_verifications (
            user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
            code_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
            last_sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            verified_at DATETIME NULL,
            CONSTRAINT fk_email_verification_user
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $ready = true;
}

function ensure_record_evidence_table(PDO $pdo): void {
    static $ready = false;
    if ($ready) return;
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS hour_record_evidence (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            record_id BIGINT UNSIGNED NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            evidence_path VARCHAR(500) NOT NULL,
            evidence_mime VARCHAR(100) NOT NULL,
            file_size INT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_hour_record_evidence_record (record_id),
            CONSTRAINT fk_hour_record_evidence_record
                FOREIGN KEY (record_id) REFERENCES hour_records(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $ready = true;
}

function email_verified(int $userId): bool {
    $pdo = db();
    ensure_email_verification_table($pdo);
    $st = $pdo->prepare('SELECT verified_at FROM account_email_verifications WHERE user_id = ?');
    $st->execute([$userId]);
    $verifiedAt = $st->fetchColumn();
    return $verifiedAt === false || $verifiedAt !== null;
}

function create_email_verification(PDO $pdo, int $userId): string {
    $code = (string)random_int(10000000, 99999999);
    $st = $pdo->prepare('INSERT INTO account_email_verifications (user_id, code_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))');
    $st->execute([$userId, password_hash($code, PASSWORD_DEFAULT)]);
    return $code;
}

function configured_mailer(): \PHPMailer\PHPMailer\PHPMailer {
    $autoload = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
    if (!is_file($autoload)) {
        throw new RuntimeException('Falta instalar PHPMailer. Ejecuta composer install en el servidor.');
    }
    require_once $autoload;

    $host = trim((string)getenv('SIGHO_SMTP_HOST'));
    $from = trim((string)getenv('SIGHO_MAIL_FROM'));
    if ($host === '' || $from === '') {
        throw new RuntimeException('El envío de correo no está configurado: define SIGHO_SMTP_HOST y SIGHO_MAIL_FROM.');
    }
    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('SIGHO_MAIL_FROM debe contener una dirección de correo válida.');
    }
    $portValue = (string)(getenv('SIGHO_SMTP_PORT') ?: '587');
    $port = filter_var($portValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
    if ($port === false) {
        throw new RuntimeException('SIGHO_SMTP_PORT debe ser un número entre 1 y 65535.');
    }
    $username = (string)getenv('SIGHO_SMTP_USERNAME');
    $password = (string)getenv('SIGHO_SMTP_PASSWORD');
    if (($username === '') !== ($password === '')) {
        throw new RuntimeException('Configura SIGHO_SMTP_USERNAME y SIGHO_SMTP_PASSWORD juntos, o deja ambos vacíos.');
    }

    $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mailer->isSMTP();
    $mailer->Host = $host;
    $mailer->Port = $port;
    $mailer->SMTPAuth = $username !== '';
    if ($mailer->SMTPAuth) {
        $mailer->Username = $username;
        $mailer->Password = $password;
    }
    $encryption = strtolower(trim((string)(getenv('SIGHO_SMTP_ENCRYPTION') ?: 'tls')));
    if ($encryption === 'tls') {
        $mailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    } elseif ($encryption === 'ssl') {
        $mailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
    } elseif ($encryption !== 'none') {
        throw new RuntimeException('SIGHO_SMTP_ENCRYPTION debe ser tls, ssl o none.');
    }
    $fromName = trim((string)(getenv('SIGHO_MAIL_FROM_NAME') ?: 'SIGHO'));
    $mailer->setFrom($from, $fromName);
    $mailer->CharSet = 'UTF-8';
    return $mailer;
}

function send_identity_email(string $email, string $name, string $role, string $code): void {
    $mailer = configured_mailer();
    $mailer->addAddress($email, $name);
    $mailer->isHTML(true);
    $mailer->Subject = 'Bienvenido a SIGHO - confirma tu correo';
    $safeName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeRole = htmlspecialchars($role, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $mailer->Body = '<div style="font-family:Arial,sans-serif;max-width:600px;margin:auto;color:#18314f">'
        . '<div style="text-align:center;padding:24px;background:#f2f7fc;border-radius:16px">'
        . '<img src="cid:sigho-logo" alt="SIGHO" style="width:84px;height:auto">'
        . '<h1 style="color:#1259a7;margin-bottom:8px">¡Te damos la bienvenida a SIGHO!</h1>'
        . '<p>Hola ' . $safeName . ', tu solicitud fue aprobada como ' . $safeRole . '.</p>'
        . '<p>Para confirmar que reconoces este registro, inicia sesión y escribe este código:</p>'
        . '<p style="font-size:30px;font-weight:bold;letter-spacing:8px;color:#1259a7">' . $code . '</p>'
        . '<p>El código vence en 30 minutos. Si tú no solicitaste esta cuenta, elige “No fui yo” en SIGHO y la cuenta se eliminará.</p>'
        . '<p style="font-size:13px;color:#62758b">Este mensaje fue enviado automáticamente por SIGHO. No respondas a este correo.</p>'
        . '</div></div>';
    $mailer->AltBody = "Hola {$name}, tu solicitud de acceso a SIGHO fue aprobada como {$role}. "
        . "Inicia sesión y escribe este código para confirmar tu correo: {$code}. "
        . "El código vence en 30 minutos. Si tú no solicitaste la cuenta, elige “No fui yo” para eliminarla.";
    $logo = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'escudo_jjfh.jpg';
    if (is_file($logo)) $mailer->addEmbeddedImage($logo, 'sigho-logo', 'escudo_jjfh.jpg');
    $mailer->send();
}

function notify_admins_of_unrecognized_account(PDO $pdo, string $name, string $email, string $role): bool {
    $recipients = [];
    $st = $pdo->query("SELECT u.email, u.first_name, u.last_name FROM users u JOIN roles r ON r.id=u.role_id WHERE u.status='activo' AND r.code IN ('administrador','superadmin')");
    foreach ($st->fetchAll() as $admin) {
        if (filter_var($admin['email'], FILTER_VALIDATE_EMAIL)) {
            $recipients[] = [$admin['email'], trim($admin['first_name'] . ' ' . $admin['last_name'])];
        }
    }
    $extraRecipients = array_filter(array_map('trim', explode(',', (string)getenv('SIGHO_MAIL_ADMIN_CC'))));
    foreach ($extraRecipients as $recipient) {
        if (filter_var($recipient, FILTER_VALIDATE_EMAIL)) $recipients[] = [$recipient, 'Administrador SIGHO'];
    }
    $recipients = array_values(array_unique($recipients, SORT_REGULAR));
    if (!$recipients) return false;

    $mailer = configured_mailer();
    foreach ($recipients as [$address, $recipientName]) $mailer->addAddress($address, $recipientName);
    $mailer->isHTML(true);
    $mailer->Subject = 'SIGHO: cuenta no reconocida eliminada';
    $safeName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeEmail = htmlspecialchars($email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeRole = htmlspecialchars($role, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $mailer->Body = '<div style="font-family:Arial,sans-serif;max-width:600px;margin:auto;color:#18314f">'
        . '<h2>Reporte de seguridad de cuenta</h2><p>El destinatario del correo de verificación indicó que no reconoce este registro.</p>'
        . '<p><strong>Nombre:</strong> ' . $safeName . '<br><strong>Correo:</strong> ' . $safeEmail
        . '<br><strong>Rol solicitado:</strong> ' . $safeRole . '</p><p>La cuenta y sus datos asociados fueron eliminados.</p></div>';
    $mailer->AltBody = "El usuario {$name} ({$email}), rol {$role}, indicó que no reconoce el registro. La cuenta y sus datos asociados fueron eliminados.";
    $mailer->send();
    return true;
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
    if (in_array($user['rol'], ['estudiante', 'profesor'], true) && !email_verified((int)$user['id'])) {
        fail('Confirma tu correo electrónico antes de usar SIGHO.', 403);
    }
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
        ['access_requests', 'document_type', "ALTER TABLE access_requests ADD COLUMN document_type VARCHAR(50) NULL AFTER identification"],
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

function record_payload(array $r, array $evidenceFiles = []): array {
    if (!$evidenceFiles && !empty($r['evidence_path'])) {
        $evidenceFiles[] = [
            'id' => null,
            'nombre' => $r['evidence_original_name'] ?? '',
            'tipo' => $r['evidence_mime'] ?? '',
            'url' => 'api/api.php?action=evidence&id=' . (int)$r['id'],
        ];
    }
    $firstEvidence = $evidenceFiles[0] ?? null;
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
        'evidencia' => $firstEvidence['nombre'] ?? ($r['evidence_original_name'] ?? ''),
        'evidenciaTipo' => $firstEvidence['tipo'] ?? ($r['evidence_mime'] ?? ''),
        'evidenciaUrl' => $firstEvidence['url'] ?? '',
        'evidenciaArchivos' => $evidenceFiles,
        'estado' => $r['status'],
        'observacion' => $r['observation'] ?? '',
        'motivoRechazo' => $r['rejection_reason'] ?? '',
        'revisadoPor' => $r['reviewer_email'] ?? '',
        'revisadoPorNombre' => $r['reviewer_name'] ?? '',
        'fechaRegistro' => $r['created_at'] ?? '',
    ];
}

function get_records(?int $studentId = null, bool $all = true, ?int $teacherStaffId = null): array {
    $pdo = db();
    ensure_record_evidence_table($pdo);
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
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll();
    if (!$rows) return [];

    $recordIds = array_map(static fn(array $row): int => (int)$row['id'], $rows);
    $placeholders = implode(',', array_fill(0, count($recordIds), '?'));
    $stEvidence = $pdo->prepare("SELECT id, record_id, original_name, evidence_mime FROM hour_record_evidence WHERE record_id IN ({$placeholders}) ORDER BY id");
    $stEvidence->execute($recordIds);
    $evidenceByRecord = [];
    foreach ($stEvidence->fetchAll() as $file) {
        $recordId = (int)$file['record_id'];
        $evidenceByRecord[$recordId][] = [
            'id' => (int)$file['id'],
            'nombre' => $file['original_name'],
            'tipo' => $file['evidence_mime'],
            'url' => 'api/api.php?action=evidence&id=' . $recordId . '&fileId=' . (int)$file['id'],
        ];
    }
    return array_map(
        static fn(array $row): array => record_payload($row, $evidenceByRecord[(int)$row['id']] ?? []),
        $rows
    );
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
                   COALESCE(sa.document_type, ar.document_type) AS requested_document_type,
                   COALESCE(sa.document_number, ar.identification) AS requested_document_number,
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
            'tipoDocumento' => $r['requested_document_type'] ?? '',
            'numeroDocumento' => $r['requested_document_number'] ?? $r['identification'],
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
    ensure_record_evidence_table(db());
    $st = db()->prepare("SELECT hr.*, s.user_id AS student_user_id FROM hour_records hr JOIN students s ON s.id = hr.student_id WHERE hr.id = ?");
    $st->execute([$id]);
    $r = $st->fetch();
    if (!$r) { http_response_code(404); exit('Evidencia no encontrada.'); }
    if ($user['rol'] === 'estudiante' && (int)$r['student_user_id'] !== (int)$user['id']) {
        http_response_code(403); exit('No autorizado.');
    }
    $fileId = (int)($_GET['fileId'] ?? 0);
    if ($fileId) {
        $st = db()->prepare('SELECT original_name, evidence_path, evidence_mime FROM hour_record_evidence WHERE id = ? AND record_id = ?');
        $st->execute([$fileId, $id]);
        $evidence = $st->fetch();
        if (!$evidence) { http_response_code(404); exit('Evidencia no encontrada.'); }
    } else {
        $evidence = [
            'original_name' => $r['evidence_original_name'],
            'evidence_path' => $r['evidence_path'],
            'evidence_mime' => $r['evidence_mime'],
        ];
    }
    if (!$evidence['evidence_path']) { http_response_code(404); exit('Este registro no tiene evidencia.'); }
    $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $evidence['evidence_path']);
    if (!is_file($path)) { http_response_code(404); exit('Archivo no encontrado.'); }
    header('Content-Type: ' . ($evidence['evidence_mime'] ?: 'application/octet-stream'));
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: inline; filename="' . basename($evidence['original_name']) . '"');
    readfile($path);
    exit;
}

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'session':
            $user = current_user();
            if ($user) {
                $user['emailVerificada'] = email_verified((int)$user['id']);
                $_SESSION['user'] = $user;
            }
            respond(['ok' => true, 'user' => $user]);

        case 'login':
            ensure_email_verification_table(db());
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
            $payload['emailVerificada'] = email_verified((int)$u['id']);
            $_SESSION['user'] = $payload;
            session_regenerate_id(true);
            respond(['ok' => true, 'user' => $payload]);

        case 'resend_identity_code':
            $user = current_user();
            if (!$user) fail('Debes iniciar sesión.', 401);
            if (!in_array($user['rol'], ['estudiante', 'profesor'], true)) fail('Esta verificación no aplica a tu cuenta.', 403);
            $pdo = db();
            ensure_email_verification_table($pdo);
            if (email_verified((int)$user['id'])) fail('Tu correo ya está verificado.');
            $st = $pdo->prepare('SELECT TIMESTAMPDIFF(SECOND, last_sent_at, NOW()) AS seconds_since_sent FROM account_email_verifications WHERE user_id = ?');
            $st->execute([(int)$user['id']]);
            $secondsSinceSent = $st->fetchColumn();
            if ($secondsSinceSent !== false && (int)$secondsSinceSent < 60) {
                fail('Espera un minuto antes de solicitar otro código.');
            }
            $code = (string)random_int(10000000, 99999999);
            $st = $pdo->prepare('UPDATE account_email_verifications SET code_hash = ?, expires_at = DATE_ADD(NOW(), INTERVAL 30 MINUTE), attempts = 0, last_sent_at = NOW(), verified_at = NULL WHERE user_id = ?');
            $st->execute([password_hash($code, PASSWORD_DEFAULT), (int)$user['id']]);
            try {
                send_identity_email($user['correo'], $user['nombre'], $user['rol'], $code);
            } catch (Throwable $e) {
                error_log('SIGHO verification email failed: ' . $e->getMessage());
                fail('No fue posible enviar el correo. Verifica la configuración SMTP e inténtalo de nuevo más tarde.', 503);
            }
            respond(['ok' => true, 'message' => 'Enviamos un nuevo código a tu correo.']);

        case 'verify_identity':
            $user = current_user();
            if (!$user) fail('Debes iniciar sesión.', 401);
            if (!in_array($user['rol'], ['estudiante', 'profesor'], true)) fail('Esta verificación no aplica a tu cuenta.', 403);
            $d = json_input();
            $decision = (string)($d['decision'] ?? '');
            $code = trim((string)($d['codigo'] ?? ''));
            if (!in_array($decision, ['si', 'no'], true) || !preg_match('/^\d{8}$/', $code)) {
                fail('Escribe el código de 8 dígitos del correo y selecciona una opción.');
            }
            $pdo = db();
            ensure_email_verification_table($pdo);
            $st = $pdo->prepare('SELECT code_hash, (expires_at <= NOW()) AS expired, attempts, verified_at FROM account_email_verifications WHERE user_id = ?');
            $st->execute([(int)$user['id']]);
            $verification = $st->fetch();
            if (!$verification || $verification['verified_at'] !== null) fail('El código no está disponible. Solicita uno nuevo.');
            if ((int)$verification['attempts'] >= 5) fail('Se agotaron los intentos. Solicita un código nuevo.');
            if ((bool)$verification['expired']) fail('El código venció. Solicita uno nuevo.');
            if (!password_verify($code, $verification['code_hash'])) {
                $st = $pdo->prepare('UPDATE account_email_verifications SET attempts = attempts + 1 WHERE user_id = ? AND verified_at IS NULL');
                $st->execute([(int)$user['id']]);
                fail('El código no es correcto. Verifícalo e inténtalo de nuevo.');
            }

            $pdo->beginTransaction();
            try {
                $st = $pdo->prepare('SELECT code_hash, (expires_at <= NOW()) AS expired, attempts, verified_at FROM account_email_verifications WHERE user_id = ? FOR UPDATE');
                $st->execute([(int)$user['id']]);
                $verification = $st->fetch();
                if (!$verification || $verification['verified_at'] !== null ||
                    (int)$verification['attempts'] >= 5 ||
                    (bool)$verification['expired'] ||
                    !password_verify($code, $verification['code_hash'])) {
                    fail('El código ya no es válido. Solicita uno nuevo.');
                }

                if ($decision === 'si') {
                    $st = $pdo->prepare('UPDATE account_email_verifications SET verified_at = NOW() WHERE user_id = ?');
                    $st->execute([(int)$user['id']]);
                    $pdo->commit();
                    $_SESSION['user']['emailVerificada'] = true;
                    respond(['ok' => true, 'verified' => true, 'message' => 'Correo confirmado. Ya puedes usar SIGHO.']);
                }

                $st = $pdo->prepare('SELECT id FROM access_requests WHERE LOWER(email) = LOWER(?) AND requested_role_id = (SELECT id FROM roles WHERE code = ?) FOR UPDATE');
                $st->execute([$user['correo'], $user['rol']]);
                $requestIds = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
                $st = $pdo->prepare('SELECT id FROM students WHERE user_id = ?');
                $st->execute([(int)$user['id']]);
                $studentId = $st->fetchColumn();
                if ($studentId !== false) {
                    $st = $pdo->prepare('DELETE FROM student_applications WHERE user_id = ? OR student_id = ?');
                    $st->execute([(int)$user['id'], (int)$studentId]);
                } else {
                    $st = $pdo->prepare('DELETE FROM student_applications WHERE user_id = ?');
                    $st->execute([(int)$user['id']]);
                }
                if ($requestIds) {
                    $placeholders = implode(',', array_fill(0, count($requestIds), '?'));
                    $st = $pdo->prepare("DELETE FROM student_applications WHERE access_request_id IN ({$placeholders})");
                    $st->execute($requestIds);
                }
                $st = $pdo->prepare('DELETE rh FROM review_history rh JOIN staff s ON s.id = rh.reviewer_staff_id WHERE s.user_id = ?');
                $st->execute([(int)$user['id']]);
                if ($requestIds) {
                    $placeholders = implode(',', array_fill(0, count($requestIds), '?'));
                    $st = $pdo->prepare("DELETE FROM access_requests WHERE id IN ({$placeholders})");
                    $st->execute($requestIds);
                }
                $st = $pdo->prepare('DELETE FROM users WHERE id = ?');
                $st->execute([(int)$user['id']]);
                if ($st->rowCount() !== 1) fail('No fue posible eliminar la cuenta.');
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }

            $_SESSION = [];
            session_destroy();
            try {
                $notified = notify_admins_of_unrecognized_account($pdo, (string)$user['nombre'], (string)$user['correo'], (string)$user['rol']);
                $notificationMessage = $notified
                    ? 'El administrador fue notificado por correo.'
                    : 'No se encontró un correo de administrador activo para notificar.';
            } catch (Throwable $e) {
                error_log('SIGHO unrecognized-account notification failed: ' . $e->getMessage());
                $notified = false;
                $notificationMessage = 'No se pudo enviar el aviso al administrador. La cuenta sí fue eliminada.';
            }
            respond([
                'ok' => true,
                'deleted' => true,
                'adminNotified' => $notified,
                'message' => 'La cuenta y sus datos asociados fueron eliminados. ' . $notificationMessage,
            ]);

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
            $tipoDocumento = trim((string)($d['tipoDocumento'] ?? ''));
            $numeroDocumento = trim((string)($d['numeroDocumento'] ?? ''));
            $camposNombreProfesor = $rol === 'profesor' && (
                array_key_exists('primerApellido', $d) ||
                array_key_exists('segundoApellido', $d) ||
                array_key_exists('primerNombre', $d) ||
                array_key_exists('segundoNombre', $d)
            );
            if ($rol === 'profesor') {
                $ident = $numeroDocumento;
                if ($camposNombreProfesor) {
                    $nombre = trim(implode(' ', array_filter([
                        trim((string)($d['primerNombre'] ?? '')),
                        trim((string)($d['segundoNombre'] ?? ''))
                    ], static fn($value) => $value !== '')));
                    $apellido = trim(implode(' ', array_filter([
                        trim((string)($d['primerApellido'] ?? '')),
                        trim((string)($d['segundoApellido'] ?? ''))
                    ], static fn($value) => $value !== '')));
                }
            }
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
                $sedesConProyectosLibres = ['Central JT', 'Central JM', 'Central J.N.'];
                if (!in_array($sede, $sedesConProyectosLibres, true) &&
                    $proyecto !== 'Acompañamiento a un docente de transición o primaria') {
                    fail('Para la sede seleccionada, el único proyecto permitido es Acompañamiento a un docente de transición o primaria.');
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
                            'Asociación Indígena del Cauca (AIC)', 'Dusakawi EPSI', 'Mallamas EPSI', 'Pijaos Salud EPSI',
                            'Otro'
                        ];
                        if (!in_array($epsSeleccionada, $epsPermitidas, true) ||
                            ($epsSeleccionada !== 'Otro' && $eps !== $epsSeleccionada)) {
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
                if ($camposNombreProfesor) {
                    $nombresProfesor = [
                        'Primer nombre' => capitalizarNombre(trim((string)($d['primerNombre'] ?? ''))),
                        'Segundo nombre' => capitalizarNombre(trim((string)($d['segundoNombre'] ?? ''))),
                        'Primer apellido' => capitalizarNombre(trim((string)($d['primerApellido'] ?? ''))),
                        'Segundo apellido' => capitalizarNombre(trim((string)($d['segundoApellido'] ?? '')))
                    ];
                    $regexNombreProfesor = '/^[\p{L}]+(?: [\p{L}]+)*$/u';
                    foreach ($nombresProfesor as $etiqueta => $valor) {
                        $obligatorio = in_array($etiqueta, ['Primer nombre', 'Primer apellido'], true);
                        if (!$valor && !$obligatorio) continue;
                        if (mb_strlen($valor, 'UTF-8') < 2 || mb_strlen($valor, 'UTF-8') > 30 || !preg_match($regexNombreProfesor, $valor)) {
                            fail($etiqueta . ' debe contener solo letras y espacios sencillos, entre 2 y 30 caracteres.');
                        }
                    }
                    $nombre = trim(implode(' ', array_filter([
                        $nombresProfesor['Primer nombre'],
                        $nombresProfesor['Segundo nombre']
                    ], static fn($value) => $value !== '')));
                    $apellido = trim(implode(' ', array_filter([
                        $nombresProfesor['Primer apellido'],
                        $nombresProfesor['Segundo apellido']
                    ], static fn($value) => $value !== '')));
                } else {
                    $nombre = capitalizarNombre($nombre);
                    $apellido = capitalizarNombre($apellido);
                }
                $ident = trim($ident);
                $correo = strtolower(trim($correo));

                if (!$camposNombreProfesor) {
                    $regexNombreProfesor = '/^[\p{L}]+(?: [\p{L}]+)*$/u';
                    if (mb_strlen($nombre, 'UTF-8') < 2 || mb_strlen($nombre, 'UTF-8') > 60 || !preg_match($regexNombreProfesor, $nombre)) {
                        fail('Los nombres del profesor solo pueden contener letras y un espacio sencillo entre palabras.');
                    }
                    if (mb_strlen($apellido, 'UTF-8') < 2 || mb_strlen($apellido, 'UTF-8') > 60 || !preg_match($regexNombreProfesor, $apellido)) {
                        fail('Los apellidos del profesor solo pueden contener letras y un espacio sencillo entre palabras.');
                    }
                }
                if (!in_array($tipoDocumento, ['Cédula de ciudadanía', 'PPT'], true)) {
                    fail('Selecciona Cédula de ciudadanía o PPT como tipo de documento.');
                }
                $patronDocumentoProfesor = $tipoDocumento === 'PPT' ? '/^\d{6,8}$/' : '/^\d{7,10}$/';
                if (!preg_match($patronDocumentoProfesor, $ident)) {
                    fail($tipoDocumento === 'PPT'
                        ? 'El PPT debe tener entre 6 y 8 dígitos.'
                        : 'La cédula debe tener entre 7 y 10 dígitos.');
                }
                if (!preg_match('/^3\d{9}$/', $telefonoProfesor)
                    || preg_match('/^(\d)\1{9}$/', $telefonoProfesor)
                    || in_array($telefonoProfesor, ['0123456789','1234567890','9876543210','123456789','234567890','987654321','876543210'], true)) {
                    fail('El teléfono de contacto del profesor debe tener exactamente 10 dígitos, comenzar por 3 y no ser un número repetido o una secuencia consecutiva.');
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
                $st = $pdo->prepare('INSERT INTO access_requests (identification, document_type, first_name, last_name, email, password_hash, requested_role_id, modality, technical_program, grade, service_site, service_project, phone) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
                $st->execute([
                    $ident,
                    $tipoDocumento !== '' ? $tipoDocumento : null,
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
            $correo = strtolower(trim((string)($d['correo'] ?? '')));
            $telefono = trim((string)($d['telefono'] ?? ''));
            if ($correo === '' || $telefono === '') fail('Completa el correo institucional y el teléfono de contacto.');
            if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || !is_institutional_email($correo)) {
                fail('El correo del profesor debe utilizar el dominio institucional @' . institutional_domain() . '.');
            }
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
                $st = $pdo->prepare('UPDATE users SET email=? WHERE id=?');
                $st->execute([$correo, (int)$user['id']]);
                $st = $pdo->prepare('SELECT id FROM staff WHERE user_id=? LIMIT 1');
                $st->execute([(int)$user['id']]);
                if ($st->fetchColumn()) {
                    $st = $pdo->prepare('UPDATE staff SET phone=? WHERE user_id=?');
                    $st->execute([$telefono, (int)$user['id']]);
                } else {
                    $st = $pdo->prepare('INSERT INTO staff (user_id, staff_code, phone) VALUES (?,?,?)');
                    $st->execute([(int)$user['id'], (string)($user['identificacion'] ?? ''), $telefono]);
                }
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
            $user = require_roles(['profesor', 'estudiante']);
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
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }
            session_destroy();
            respond(['ok'=>true,'message'=>'Contraseña actualizada correctamente.']);

        case 'my_profile':
            $user = require_roles(['estudiante']);
            $pdo = db();
            ensure_student_application_table($pdo);
            $st = $pdo->prepare('SELECT u.email, u.first_name, u.last_name, u.identification, s.id AS student_id, s.grade AS student_grade, s.modality, s.target_hours, sa.grade AS application_grade, sa.service_shift AS application_shift, sa.phone, sa.residence_address, sa.eps, sa.guardian_name, sa.guardian_phone, sa.guardian_phone_type FROM users u LEFT JOIN students s ON s.user_id = u.id LEFT JOIN student_applications sa ON sa.id = (SELECT MAX(sa2.id) FROM student_applications sa2 WHERE sa2.student_id = s.id OR sa2.user_id = u.id) WHERE u.id = ? LIMIT 1');
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
                'eps'=>$p['eps'] ?? '', 'nombreAcudiente'=>$p['guardian_name'] ?? '', 'tipoTelefonoAcudiente'=>$p['guardian_phone_type'] ?? '', 'telefonoAcudiente'=>$p['guardian_phone'] ?? ''
            ]]);

        case 'update_student_profile':
            $user = require_roles(['estudiante']);
            $d = json_input();
            $correo = strtolower(trim((string)($d['correo'] ?? '')));
            $telefono = trim((string)($d['telefono'] ?? ''));
            $direccion = trim((string)($d['direccion'] ?? ''));
            $eps = trim((string)($d['eps'] ?? ''));
            $acudiente = capitalizarNombre((string)($d['nombreAcudiente'] ?? ''));
            $tipoTelefonoAcudiente = trim((string)($d['tipoTelefonoAcudiente'] ?? ''));
            $telAcudiente = trim((string)($d['telefonoAcudiente'] ?? ''));
            $epsSeleccionada = trim((string)($d['epsSeleccionada'] ?? ''));
            if (!$correo || !filter_var($correo, FILTER_VALIDATE_EMAIL)) fail('Escribe un correo electrónico válido.');
            if (!$telefono || !$direccion || !$eps || !$acudiente || !$tipoTelefonoAcudiente || !$telAcudiente) fail('Completa todos los datos editables.');
            $telefono = preg_replace('/\D+/', '', $telefono);
            $telAcudiente = preg_replace('/\D+/', '', $telAcudiente);
            if (!preg_match('/^3\d{9}$/', $telefono)) fail('El celular debe tener exactamente 10 dígitos y comenzar por 3.');
            if (preg_match('/^(\d)\1{9}$/', $telefono) || $telefono === '1234567890') fail('El celular no puede tener todos sus dígitos iguales ni ser la secuencia 1234567890.');
            if (mb_strlen($direccion, 'UTF-8') > 100) fail('La dirección puede tener máximo 100 caracteres.');
            if (mb_strlen($eps, 'UTF-8') > 150 || $eps !== $epsSeleccionada) fail('Selecciona una opción válida de Seguridad Social.');
            $epsPermitidas = [
                'Nueva EPS', 'EPS Sanitas', 'EPS Sura', 'Salud Total EPS', 'Compensar EPS',
                'E.P.S. Famisanar', 'Aliansalud EPS', 'Servicio Occidental de Salud (S.O.S.)',
                'Salud Mía EPS', 'Coosalud', 'Mutual Ser', 'Capital Salud EPS', 'Savia Salud EPS',
                'Asmet Salud', 'Emssanar', 'Cajacopi Atlántico', 'Capresoca', 'Comfachocó',
                'Comfaoriente', 'Comfenalco Valle', 'EPS Familiar de Colombia', 'Anas Wayuu EPSI',
                'Asociación Indígena del Cauca (AIC)', 'Dusakawi EPSI', 'Mallamas EPSI', 'Pijaos Salud EPSI'
            ];
            $regimenesPermitidos = [
                'Fuerzas Militares / Policía Nacional',
                'FOMAG (Fondo Nacional de Prestaciones Sociales del Magisterio - Profesores)',
                'Ecopetrol',
                'Universidades Públicas (Salud Propia)'
            ];
            $sisbenValido = preg_match('/^Sisbén Grupo ([A-D]), Subgrupo (\d{1,2})$/u', $eps, $grupoSisben);
            if ($sisbenValido) {
                $maximosSisben = ['A' => 5, 'B' => 7, 'C' => 18, 'D' => 21];
                $sisbenValido = (int)$grupoSisben[2] >= 1 && (int)$grupoSisben[2] <= $maximosSisben[$grupoSisben[1]];
            }
            $seguridadSocialValida = in_array($eps, $epsPermitidas, true) || in_array($eps, $regimenesPermitidos, true) || $sisbenValido;
            if (!$seguridadSocialValida) {
                $pdoValidacion = db();
                ensure_student_application_table($pdoValidacion);
                $st = $pdoValidacion->prepare('SELECT eps FROM student_applications WHERE user_id = ? OR student_id = (SELECT id FROM students WHERE user_id = ? LIMIT 1) ORDER BY id DESC LIMIT 1');
                $st->execute([(int)$user['id'], (int)$user['id']]);
                $seguridadSocialValida = $eps === trim((string)($st->fetchColumn() ?: ''));
            }
            if (!$seguridadSocialValida) fail('Selecciona una opción válida de Seguridad Social.');
            if (!preg_match('/^[\p{L}]+(?: [\p{L}]+)*$/u', $acudiente) || mb_strlen($acudiente, 'UTF-8') < 2 || mb_strlen($acudiente, 'UTF-8') > 60) fail('El nombre del acudiente debe contener solo letras y espacios sencillos, entre 2 y 60 caracteres.');
            if (!in_array($tipoTelefonoAcudiente, ['fijo', 'celular'], true)) fail('Selecciona si el teléfono del acudiente es fijo o celular.');
            if ($tipoTelefonoAcudiente === 'celular') {
                if (!preg_match('/^3\d{9}$/', $telAcudiente) || preg_match('/^(\d)\1{9}$/', $telAcudiente) || $telAcudiente === '1234567890') fail('El celular del acudiente debe tener 10 dígitos, comenzar por 3 y no ser un número repetido ni la secuencia 1234567890.');
            } else {
                $restoFijo = substr($telAcudiente, 3);
                if (!preg_match('/^60[1-8][2-8]\d{6}$/', $telAcudiente) || preg_match('/^(\d)\1{6}$/', $restoFijo)) fail('El teléfono fijo del acudiente debe cumplir el formato de registro y no tener los siete dígitos finales iguales.');
            }
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
                    $st = $pdo->prepare('UPDATE student_applications SET phone=?, residence_address=?, eps=?, guardian_name=?, guardian_phone=?, guardian_phone_type=?, updated_at=CURRENT_TIMESTAMP WHERE id=?');
                } else {
                    $st = $pdo->prepare('UPDATE student_applications SET phone=?, residence_address=?, eps=?, guardian_name=?, guardian_phone=?, guardian_phone_type=? WHERE id=?');
                }
                $st->execute([$telefono,$direccion,$eps,$acudiente,$telAcudiente,$tipoTelefonoAcudiente,$applicationId]);
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
            // Permitir que el Super Admin abra Operación de Funcionario Público.
            $user = require_roles(['profesor', 'administrador', 'superadmin']);
            if ($user['rol'] === 'profesor') {
                ensure_optional_columns(db());
                respond(['ok' => true, 'records' => get_records(null, true, (int)$user['staffId'])]);
            }
            respond(['ok' => true, 'records' => get_records()]);

        case 'teacher_students':
            // El Super Admin puede consultar la lista general; el profesor mantiene
            // su filtro limitado a la sede y proyecto asignados.
            $user = require_roles(['profesor', 'superadmin']);
            ensure_student_application_table(db());
            ensure_optional_columns(db());

            if ($user['rol'] === 'superadmin') {
                $sql = "SELECT DISTINCT u.id, u.email AS correo,
                               CONCAT(u.first_name, ' ', u.last_name) AS nombre,
                               s.grade AS grado,
                               s.target_hours AS metaHoras,
                               sa.project AS proyecto,
                               sa.service_site AS sede
                        FROM students s
                        JOIN users u ON u.id = s.user_id
                        JOIN student_applications sa ON sa.student_id = s.id
                        WHERE sa.id = (
                            SELECT MAX(sa2.id)
                            FROM student_applications sa2
                            WHERE sa2.student_id = s.id
                        )
                        ORDER BY u.first_name, u.last_name";
                $st = db()->prepare($sql);
                $st->execute();
                respond([
                    'ok' => true,
                    'students' => $st->fetchAll(),
                    'sede' => '',
                    'proyecto' => ''
                ]);
            }

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

            if (!$fecha || !$horas) {
                fail('Completa los campos obligatorios: fecha y horas realizadas.');
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

            // B. Horas realizadas: únicamente enteros entre 1 y 15.
            if (!preg_match('/^[0-9]{1,2}$/', $horas)) {
                fail('Las horas realizadas deben ser números enteros del 1 al 15.');
            }
            $hours = (int)$horas;
            if ($hours < 1 || $hours > 15) {
                fail('Las horas realizadas deben ser números enteros del 1 al 15.');
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

            $upload = $_FILES['evidencia'] ?? null;
            if (!$upload || !isset($upload['name'])) fail('Debes adjuntar exactamente 3 imágenes JPG o JPEG.');
            if (!is_array($upload['name'])) {
                $upload = array_map(static fn($value): array => [$value], $upload);
            }
            $cantidadEvidencias = count($upload['name']);
            if ($cantidadEvidencias !== 3) {
                fail('Debes adjuntar exactamente 3 imágenes JPG o JPEG.');
            }

            if (!is_dir(EVIDENCE_DIR) && !mkdir(EVIDENCE_DIR, 0775, true) && !is_dir(EVIDENCE_DIR)) {
                fail('No fue posible preparar el almacenamiento de la evidencia.', 500);
            }

            $pdo = db();
            ensure_record_evidence_table($pdo);
            $uploadsValidados = [];
            for ($i = 0; $i < $cantidadEvidencias; $i++) {
                $file = [
                    'name' => $upload['name'][$i] ?? '',
                    'tmp_name' => $upload['tmp_name'][$i] ?? '',
                    'error' => $upload['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $upload['size'][$i] ?? 0,
                ];
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    fail($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE
                        ? 'Cada imagen debe pesar máximo 5 MB.'
                        : 'No se pudo recibir una de las imágenes adjuntas.');
                }
                if ((int)$file['size'] <= 0) fail('Una de las imágenes está vacía.');
                if ((int)$file['size'] > 5 * 1024 * 1024) fail('Cada imagen debe pesar máximo 5 MB.');
                if (!preg_match('/\.jpe?g$/i', (string)$file['name'])) {
                    fail('Solo se permiten imágenes JPG o JPEG.');
                }

                $rutaTemporal = (string)$file['tmp_name'];
                if (!is_file($rutaTemporal)) fail('No se pudo leer una de las imágenes adjuntas.');
                $handle = fopen($rutaTemporal, 'rb');
                if ($handle === false) fail('No se pudo leer una de las imágenes adjuntas.');
                $cabecera = fread($handle, 3);
                $longitud = filesize($rutaTemporal);
                $marcaFinal = '';
                if ($longitud !== false && $longitud >= 2 && fseek($handle, -2, SEEK_END) === 0) {
                    $marcaFinal = fread($handle, 2) ?: '';
                }
                fclose($handle);
                if ($cabecera !== "\xFF\xD8\xFF" || $marcaFinal !== "\xFF\xD9") {
                    fail('Solo se permiten imágenes JPG o JPEG válidas.');
                }
                $file['mime'] = 'image/jpeg';
                $uploadsValidados[] = $file;
            }

            $archivosGuardados = [];
            foreach ($uploadsValidados as $file) {
                $stored = bin2hex(random_bytes(16)) . '.jpg';
                $target = EVIDENCE_DIR . DIRECTORY_SEPARATOR . $stored;
                if (!move_uploaded_file((string)$file['tmp_name'], $target)) {
                    foreach ($archivosGuardados as $saved) @unlink($saved['target']);
                    fail('No fue posible guardar una de las imágenes.', 500);
                }
                $archivosGuardados[] = [
                    'nombre' => basename((string)$file['name']),
                    'path' => 'uploads/evidence/' . $stored,
                    'mime' => $file['mime'],
                    'size' => (int)$file['size'],
                    'target' => $target,
                ];
            }

            // Las nuevas jornadas ya no usan entrada/salida. Dejamos esas columnas como NULL para conservar compatibilidad con registros antiguos.
            try {
                $pdo->exec("ALTER TABLE hour_records MODIFY entry_time TIME NULL, MODIFY exit_time TIME NULL");
            } catch (Throwable $e) { /* si ya son NULL o el motor no requiere el cambio, continuamos */ }
            try {
                $pdo->beginTransaction();
                $primeraEvidencia = $archivosGuardados[0];
                $st = $pdo->prepare('INSERT INTO hour_records (student_id, record_date, entry_time, exit_time, hours, activity_description, evidence_original_name, evidence_path, evidence_mime) VALUES (?,?,?,?,?,?,?,?,?)');
                $st->execute([
                    $studentId,
                    $fecha,
                    null,
                    null,
                    $hours,
                    $descripcion,
                    $primeraEvidencia['nombre'],
                    $primeraEvidencia['path'],
                    $primeraEvidencia['mime']
                ]);
                $recordId = (int)$pdo->lastInsertId();
                $stEvidence = $pdo->prepare('INSERT INTO hour_record_evidence (record_id, original_name, evidence_path, evidence_mime, file_size) VALUES (?,?,?,?,?)');
                foreach ($archivosGuardados as $saved) {
                    $stEvidence->execute([$recordId, $saved['nombre'], $saved['path'], $saved['mime'], $saved['size']]);
                }
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                foreach ($archivosGuardados as $saved) @unlink($saved['target']);
                throw $e;
            }

            $records = get_records($studentId);
            $record = array_values(array_filter($records, fn($r) => (int)$r['id'] === $recordId))[0] ?? null;
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
            // Permitir que el Super Admin abra Operación de Administrador.
            require_roles(['administrador', 'superadmin']);
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
            ensure_email_verification_table($pdo);
            $approvedIdentity = null;
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
                    $verificationCode = create_email_verification($pdo, $userId);
                    $approvedIdentity = [
                        'email' => (string)$r['email'],
                        'name' => trim((string)$r['first_name'] . ' ' . (string)$r['last_name']),
                        'role' => (string)$r['role_code'],
                        'code' => $verificationCode,
                    ];
                }
                $st = $pdo->prepare('UPDATE access_requests SET status=?, validated_by=?, validated_at=NOW() WHERE id=?');
                $st->execute([$status, (int)require_login()['id'], $id]);
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack(); throw $e;
            }
            if ($approvedIdentity !== null) {
                try {
                    send_identity_email(
                        $approvedIdentity['email'],
                        $approvedIdentity['name'],
                        $approvedIdentity['role'],
                        $approvedIdentity['code']
                    );
                    respond([
                        'ok' => true,
                        'emailSent' => true,
                        'message' => 'Solicitud aprobada. Enviamos al usuario un correo de bienvenida con el código de verificación.',
                    ]);
                } catch (Throwable $e) {
                    error_log('SIGHO welcome email failed: ' . $e->getMessage());
                    respond([
                        'ok' => true,
                        'emailSent' => false,
                        'message' => 'La solicitud fue aprobada, pero no se pudo enviar el correo. Verifica SMTP; el usuario puede solicitar un nuevo código.',
                    ]);
                }
            }
            respond(['ok'=>true,'message'=>'Solicitud rechazada.']);

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