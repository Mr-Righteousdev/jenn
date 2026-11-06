<?php

require_once __DIR__ . '/../config/database.php';

// Everything a staff role can be allowed to do. Grouped for the roles editor.
const PERMISSIONS = [
    'Overview and reporting' => [
        'overview.view'     => 'See the staff overview dashboard',
        'analytics.view'    => 'View analytics',
        'students.view'     => 'View student profiles and readiness',
    ],
    'Teaching' => [
        'evidence.record'   => 'Record practical evidence for students',
        'curriculum.manage' => 'Map course units to skills',
    ],
    'Framework and jobs' => [
        'skills.manage'     => 'Manage the skills framework',
        'cbc.manage'        => 'Manage CBC levels',
        'jobs.manage'       => 'Manage job postings',
    ],
    'Administration' => [
        'university.sync'   => 'Sync university data and choose which programmes use the system',
        'users.manage'      => 'Assign roles to staff and deactivate accounts',
        'roles.manage'      => 'Create and edit roles and their permissions',
    ],
];

// The university system has no departments, so data is limited by faculty
const SCOPES = [
    'all'     => 'Whole university',
    'faculty' => 'Their faculty',
];

function allPermissions(): array {
    return array_merge(...array_values(PERMISSIONS));
}

function isLoggedIn(): bool {
    return getCurrentUser() !== null;
}

/**
 * The signed-in user with their role, permissions and data scope. Cached per request.
 * Returns null when nobody is signed in or the account has been deactivated.
 */
function getCurrentUser(): ?array {
    static $user = false;
    if ($user !== false) return $user;
    if (!isset($_SESSION['user_id'])) return $user = null;

    $stmt = Database::getConnection()->prepare("
        SELECT u.user_id, u.name, u.username, u.email, u.role, u.source, u.faculty_code, u.is_active,
               r.role_id, r.slug AS role_slug, r.name AS role_name, r.scope
        FROM users u
        LEFT JOIN roles r ON r.role_id = u.role_id
        WHERE u.user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $u = $stmt->fetch();
    if (!$u || !$u['is_active']) return $user = null;

    if ($u['role_slug'] === 'super_admin') {
        $u['permissions'] = array_keys(allPermissions());
    } else {
        $p = Database::getConnection()->prepare("SELECT permission FROM role_permissions WHERE role_id = ?");
        $p->execute([$u['role_id'] ?? 0]);
        $u['permissions'] = $p->fetchAll(PDO::FETCH_COLUMN);
    }
    $u['role_label'] = $u['role'] === 'student' ? 'Student' : ($u['role_name'] ?? 'No role');
    return $user = $u;
}

function isStudent(): bool {
    return (getCurrentUser()['role'] ?? null) === 'student';
}

function can(string $permission): bool {
    $u = getCurrentUser();
    return $u !== null && $u['role'] === 'staff' && in_array($permission, $u['permissions'], true);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

function forbid(string $why): never {
    http_response_code(403);
    require_once __DIR__ . '/ui.php';
    exit('<!DOCTYPE html><meta charset="utf-8"><title>Not allowed</title><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">'
        . '<div class="container py-5" style="max-width:560px"><h1 class="h3">You don\'t have access to this page</h1><p class="text-muted">' . htmlspecialchars($why)
        . '</p><a href="' . BASE_URL . '/dashboard.php" class="btn btn-outline-secondary btn-sm">Back to overview</a></div>');
}

function requirePermission(string $permission): void {
    requireLogin();
    if (!can($permission)) {
        $label = allPermissions()[$permission] ?? $permission;
        forbid("Your role doesn't include \"$label\". Ask a super admin if you need it.");
    }
}

function requireStudent(): void {
    requireLogin();
    if (!isStudent()) forbid('This page is for students.');
}

/**
 * SQL condition limiting a student_id column to the students the current user may see:
 * everyone, or the students of programmes in the user's faculty.
 */
function studentScope(string $column = 's.student_id'): string {
    $u = getCurrentUser();
    if (($u['scope'] ?? 'faculty') === 'all') return '1=1';
    $faculty = Database::getConnection()->quote((string)($u['faculty_code'] ?? ''));
    return "$column IN (SELECT st.student_id FROM students st JOIN programmes p ON p.code = st.programme_code WHERE p.faculty_code = $faculty)";
}

/** Same idea for programme-level data such as curricula. */
function programmeScope(string $column = 'p.faculty_code'): string {
    $u = getCurrentUser();
    if (($u['scope'] ?? 'faculty') === 'all') return '1=1';
    return "$column = " . Database::getConnection()->quote((string)($u['faculty_code'] ?? ''));
}

function scopeLabel(): string {
    $u = getCurrentUser();
    if (($u['scope'] ?? 'faculty') === 'all') return 'the whole university';
    $stmt = Database::getConnection()->prepare("SELECT name FROM faculties WHERE code = ?");
    $stmt->execute([$u['faculty_code'] ?? '']);
    return $stmt->fetchColumn() ?: 'your faculty';
}

// CSRF protection for forms that change access
function csrfToken(): string {
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(16));
}

function csrfField(): string {
    return '<input type="hidden" name="csrf" value="' . csrfToken() . '">';
}

function checkCsrf(): void {
    if (!hash_equals(csrfToken(), (string)($_POST['csrf'] ?? ''))) {
        forbid('This form expired or came from somewhere else. Go back, reload the page and try again.');
    }
}

// The local admin signs in with email and password. Students and staff sign in with their university
// portal credentials (registration number, or staff username / email), checked against the SIS.
function loginUser(string $identifier, string $password): array {
    require_once __DIR__ . '/University.php';
    $db = Database::getConnection();
    $fail = ['success' => false, 'error' => 'Those details don\'t match a university or admin account. Check them and try again.'];
    $disabled = ['success' => false, 'error' => 'This account has been deactivated. Contact the system administrator.'];

    $identifier = trim($identifier);
    $stmt = $db->prepare("SELECT user_id, source, password_hash, is_active FROM users WHERE email = ? AND source = 'local'");
    $stmt->execute([$identifier]);
    $local = $stmt->fetch();

    if ($local && $local['source'] === 'local') {
        if (!password_verify($password, $local['password_hash'])) return $fail;
        $user = $local;
    } else {
        try {
            $person = University::authenticate($identifier, $password);
        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'The university system is unavailable right now. Try again shortly.'];
        }
        if (!$person) return $fail;
        $find = $db->prepare("SELECT user_id, source, password_hash, is_active FROM users WHERE username = ? AND source = 'university'");
        $find->execute([$person['username']]);
        $user = $find->fetch();
        if (!$user) {
            return ['success' => false, 'error' => 'Your university account is valid, but your programme isn\'t using this system yet. Contact the career office.'];
        }
    }
    if (!$user['is_active']) return $disabled;

    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['user_id'];
    return ['success' => true];
}

function logoutUser(): void {
    session_destroy();
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}
