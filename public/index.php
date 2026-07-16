<?php
require_once __DIR__ . '/../src/config/app.php';
require_once __DIR__ . '/../src/utils/auth.php';

$user = getCurrentUser();
?>
<?php require_once __DIR__ . '/../src/views/layout/header.php'; ?>

<div class="row align-items-center min-vh-75">
    <div class="col-lg-6">
        <h1 class="display-4 fw-bold">Competency Profiling System</h1>
        <p class="lead mt-3">
            Track student competencies, match them to real job market requirements,
            and get actionable recommendations — aligned with the CBC framework.
        </p>
        <?php if (!$user): ?>
            <div class="mt-4">
                <a href="<?= BASE_URL ?>/register.php" class="btn btn-primary btn-lg me-2">Get Started</a>
                <a href="<?= BASE_URL ?>/login.php" class="btn btn-outline-secondary btn-lg">Login</a>
            </div>
        <?php else: ?>
            <div class="mt-4">
                <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-primary btn-lg">Go to Dashboard</a>
            </div>
        <?php endif; ?>
    </div>
    <div class="col-lg-6 text-center">
        <img src="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/icons/hexagon.svg" alt="Logo" class="img-fluid opacity-25" style="max-height: 300px;">
    </div>
</div>

<?php require_once __DIR__ . '/../src/views/layout/footer.php'; ?>
