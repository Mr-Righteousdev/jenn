<?php
require_once __DIR__ . '/../../utils/ui.php';
$navUser = isLoggedIn() ? getCurrentUser() : null;
$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
// Links depend on what the signed-in user may do; admin pages go under a "Manage" menu
$navLinks = [];
$manageLinks = [];
if ($navUser && isStudent()) {
    $navLinks = ['/dashboard.php' => 'Overview', '/profile.php' => 'My skills', '/jobs.php' => 'Job matches', '/report.php' => 'My report'];
} elseif ($navUser) {
    foreach (['/dashboard.php' => ['Overview', null], '/lecturer/reports.php' => ['Students', 'students.view'],
              '/lecturer/assess.php' => ['Evidence', 'evidence.record'], '/admin/curriculum.php' => ['Curriculum', 'curriculum.manage'],
              '/admin/jobs.php' => ['Jobs', 'jobs.manage'], '/admin/reports.php' => ['Analytics', 'analytics.view']] as $href => [$label, $perm]) {
        if (!$perm || can($perm)) $navLinks[$href] = $label;
    }
    foreach (['/admin/university.php' => ['University data', 'university.sync'], '/admin/skills.php' => ['Skills', 'skills.manage'],
              '/admin/cbc_levels.php' => ['CBC levels', 'cbc.manage'], '/admin/users.php' => ['Users', 'users.manage'],
              '/admin/roles.php' => ['Roles and permissions', 'roles.manage']] as $href => [$label, $perm]) {
        if (can($perm)) $manageLinks[$href] = $label;
    }
}
$initials = $navUser ? strtoupper(implode('', array_map(fn($w) => $w[0], array_slice(preg_split('/\s+/', trim($navUser['name'])), 0, 2)))) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?></title>
    <script>
        // Apply the saved theme before first paint to avoid a light flash
        (() => {
            let t = 'system';
            try { t = localStorage.getItem('theme') || 'system'; } catch (e) {}
            const dark = t === 'dark' || (t === 'system' && matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
            document.documentElement.dataset.themePref = t;
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/style.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark app-nav sticky-top">
    <div class="container">
        <a class="navbar-brand app-brand" href="<?= BASE_URL ?>/<?= $navUser ? 'dashboard.php' : '' ?>">
            <span class="app-mark" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l8 4.5v9L12 21l-8-4.5v-9z"/><path d="M12 12l8-4.5M12 12v9M12 12L4 7.5"/></svg>
            </span>
            Competency<span>System</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu" aria-controls="navMenu" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMenu">
            <ul class="navbar-nav me-auto app-links">
                <?php foreach ($navLinks as $href => $label): $active = str_ends_with($currentPath, $href); ?>
                    <li class="nav-item">
                        <a class="nav-link<?= $active ? ' active' : '' ?>" href="<?= BASE_URL . $href ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= $label ?></a>
                    </li>
                <?php endforeach; ?>
                <?php if ($manageLinks): $manageActive = (bool)array_filter(array_keys($manageLinks), fn($h) => str_ends_with($currentPath, $h)); ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle<?= $manageActive ? ' active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">Manage</a>
                        <ul class="dropdown-menu app-submenu">
                            <?php foreach ($manageLinks as $href => $label): ?>
                                <li><a class="dropdown-item<?= str_ends_with($currentPath, $href) ? ' active' : '' ?>" href="<?= BASE_URL . $href ?>"><?= $label ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>
            <?php if ($navUser): ?>
                <div class="dropdown">
                    <button class="user-btn" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                        <span class="avatar" aria-hidden="true"><?= htmlspecialchars($initials) ?></span>
                        <span class="user-meta">
                            <span class="user-name"><?= htmlspecialchars(nice($navUser['name'])) ?></span>
                            <span class="user-role"><?= htmlspecialchars($navUser['role_label']) ?></span>
                        </span>
                        <svg class="chev" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end user-menu">
                        <div class="user-menu-head">
                            <span class="avatar avatar-lg" aria-hidden="true"><?= htmlspecialchars($initials) ?></span>
                            <div>
                                <strong><?= htmlspecialchars(nice($navUser['name'])) ?></strong>
                                <span><?= htmlspecialchars($navUser['email'] ?? $navUser['username'] ?? '') ?></span>
                            </div>
                        </div>
                        <div class="user-menu-section">
                            <span class="user-menu-label" id="themeLabel">Theme</span>
                            <div class="theme-switch" role="radiogroup" aria-labelledby="themeLabel">
                                <button type="button" role="radio" data-theme-set="light">
                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>Light
                                </button>
                                <button type="button" role="radio" data-theme-set="dark">
                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>Dark
                                </button>
                                <button type="button" role="radio" data-theme-set="system">
                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="13" rx="2"/><path d="M8 21h8M12 17v4"/></svg>System
                                </button>
                            </div>
                        </div>
                        <a class="user-menu-logout" href="<?= BASE_URL ?>/logout.php">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/></svg>
                            Log out
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <a class="btn btn-sm btn-light" href="<?= BASE_URL ?>/login.php">Sign in</a>
            <?php endif; ?>
        </div>
    </div>
</nav>
<div class="container app-main">
