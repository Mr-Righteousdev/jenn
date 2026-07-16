<?php

require_once __DIR__ . '/../config/database.php';

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function getCurrentUser(): ?array {
    if (!isLoggedIn()) return null;

    $stmt = Database::getConnection()->prepare(
        "SELECT user_id, name, email, role FROM users WHERE user_id = ?"
    );
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

function requireRole(string $role): void {
    requireLogin();
    $user = getCurrentUser();
    if ($user['role'] !== $role) {
        http_response_code(403);
        die('Unauthorized. This page requires role: ' . $role);
    }
}

function isStudent(): bool {
    $user = getCurrentUser();
    return $user && $user['role'] === 'student';
}

function isLecturer(): bool {
    $user = getCurrentUser();
    return $user && $user['role'] === 'lecturer';
}

function isAdmin(): bool {
    $user = getCurrentUser();
    return $user && $user['role'] === 'admin';
}

function registerUser(string $name, string $email, string $password, string $role): array {
    $db = Database::getConnection();

    $stmt = $db->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return ['success' => false, 'error' => 'Email already registered'];
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare(
        "INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([$name, $email, $hash, $role]);
    $userId = (int) $db->lastInsertId();

    if ($role === 'student') {
        $stmt = $db->prepare(
            "INSERT INTO students (user_id, registration_no, course, year_of_study) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$userId, 'REG-' . $userId, 'Unassigned', 1]);
    }

    return ['success' => true, 'user_id' => $userId];
}

function loginUser(string $email, string $password): array {
    $db = Database::getConnection();

    $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'error' => 'Invalid email or password'];
    }

    $_SESSION['user_id'] = $user['user_id'];
    return ['success' => true, 'role' => $user['role']];
}

function logoutUser(): void {
    session_destroy();
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}
