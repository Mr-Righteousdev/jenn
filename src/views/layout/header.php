<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/style.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
    <div class="container">
        <a class="navbar-brand" href="<?= BASE_URL ?>/"><?= APP_NAME ?></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMenu">
            <ul class="navbar-nav ms-auto">
                <?php if (isLoggedIn()): $user = getCurrentUser(); ?>
                    <li class="nav-item">
                        <span class="nav-link text-white fw-light"><?= htmlspecialchars($user['name']) ?> (<?= $user['role'] ?>)</span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/dashboard.php">Dashboard</a>
                    </li>
                    <?php if ($user['role'] === 'student'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>/jobs.php">Job Matching</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>/report.php">My Report</a>
                        </li>
                    <?php elseif ($user['role'] === 'admin'): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Admin</a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/cbc_levels.php">CBC Levels</a></li>
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/skills.php">Skills</a></li>
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/import.php">Import CSV</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/jobs.php">Jobs</a></li>
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/reports.php">Analytics</a></li>
                            </ul>
                        </li>
                    <?php elseif ($user['role'] === 'lecturer'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>/lecturer/assess.php">Assess</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>/lecturer/reports.php">Reports</a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/logout.php">Logout</a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/register.php">Register</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<div class="container">
