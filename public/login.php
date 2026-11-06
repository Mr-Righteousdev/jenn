<?php
require_once __DIR__ . '/../src/config/app.php';
require_once __DIR__ . '/../src/utils/auth.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Enter your email or registration number, and your password.';
    } else {
        $result = loginUser($email, $password);
        if ($result['success']) {
            header('Location: ' . BASE_URL . '/dashboard.php');
            exit;
        } else {
            $error = $result['error'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in · <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('/css/style.css') ?>">
</head>
<body>
<div class="auth-page">
    <main class="auth-main">
        <a href="<?= BASE_URL ?>/" class="auth-logo">Competency<span>System</span></a>

        <div class="auth-form">
            <h1>Sign in</h1>
            <p class="lead-sm">Students and staff use their university portal login.</p>

            <?php if ($error): ?>
                <div class="alert alert-danger py-2" role="alert"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-3">
                    <label for="identifier" class="form-label">Email or registration number</label>
                    <div class="auth-field">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 6l-10 7L2 6"/></svg>
                        <input type="text" class="form-control" id="identifier" name="identifier" placeholder="e.g. 23/U/BIT/0111"
                               value="<?= htmlspecialchars($email ?? '') ?>" autocomplete="username" autocapitalize="off" spellcheck="false" required <?= empty($email) ? 'autofocus' : '' ?>>
                    </div>
                </div>
                <div class="mb-4">
                    <label for="password" class="form-label">Password</label>
                    <div class="auth-field">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <input type="password" class="form-control has-toggle" id="password" name="password" placeholder="Enter your password"
                               autocomplete="current-password" required <?= !empty($email) ? 'autofocus' : '' ?>>
                        <button type="button" class="auth-toggle" aria-controls="password" aria-label="Show password"
                                onclick="const i=document.getElementById('password'),s=i.type==='password';i.type=s?'text':'password';this.textContent=s?'Hide':'Show';this.setAttribute('aria-label',s?'Hide password':'Show password')">Show</button>
                    </div>
                </div>
                <button type="submit" class="btn btn-brand w-100">Sign in</button>
            </form>

            <p class="auth-alt text-center mt-4 mb-0">
                No account to create: your university portal login works here once your programme is enabled.
            </p>
        </div>

        <div class="auth-foot">
            <span>St Lawrence University · Informatics &amp; Engineering</span>
            <a href="<?= BASE_URL ?>/" class="auth-back">Back to home</a>
        </div>
    </main>

    <aside class="auth-visual">
        <div class="auth-visual-inner">
            <figure class="auth-figure">
                <svg class="auth-radar" viewBox="0 0 600 600" role="img" aria-labelledby="radar-title"><title id="radar-title">Illustration: a student competency profile compared with a job's skill requirements</title>
                <polygon class="r-grid" points="300.0,252.5 341.1,276.2 341.1,323.8 300.0,347.5 258.9,323.8 258.9,276.2"/>
                <polygon class="r-grid" points="300.0,205.0 382.3,252.5 382.3,347.5 300.0,395.0 217.7,347.5 217.7,252.5"/>
                <polygon class="r-grid" points="300.0,157.5 423.4,228.8 423.4,371.2 300.0,442.5 176.6,371.2 176.6,228.8"/>
                <polygon class="r-grid" points="300.0,110.0 464.5,205.0 464.5,395.0 300.0,490.0 135.5,395.0 135.5,205.0"/>
                <line class="r-grid" x1="300" y1="300" x2="300.0" y2="110.0"/>
                <line class="r-grid" x1="300" y1="300" x2="464.5" y2="205.0"/>
                <line class="r-grid" x1="300" y1="300" x2="464.5" y2="395.0"/>
                <line class="r-grid" x1="300" y1="300" x2="300.0" y2="490.0"/>
                <line class="r-grid" x1="300" y1="300" x2="135.5" y2="395.0"/>
                <line class="r-grid" x1="300" y1="300" x2="135.5" y2="205.0"/>
                <polygon class="r-job" points="300.0,138.5 448.1,214.5 415.2,366.5 300.0,414.0 176.6,371.2 184.8,233.5"/>
                <g class="r-student"><polygon points="300.0,148.0 418.5,231.6 428.3,374.1 300.0,395.0 247.3,330.4 165.1,222.1"/>
                <circle cx="300.0" cy="148.0" r="5"/>
                <circle cx="418.5" cy="231.6" r="5"/>
                <circle cx="428.3" cy="374.1" r="5"/>
                <circle cx="300.0" cy="395.0" r="5"/>
                <circle cx="247.3" cy="330.4" r="5"/>
                <circle cx="165.1" cy="222.1" r="5"/>
                </g>
                <line class="r-gap" x1="247.3" y1="330.4" x2="176.6" y2="371.2"/><circle class="r-gap-dot" cx="176.6" cy="371.2" r="7"/>
                <text x="300.0" y="85.0" text-anchor="middle">Programming</text>
                <text x="490.5" y="195.0" text-anchor="start">Web Dev</text>
                <text x="490.5" y="415.0" text-anchor="start">Databases</text>
                <text x="300.0" y="525.0" text-anchor="middle">Server Config</text>
                <text x="109.5" y="415.0" text-anchor="end" class="is-gap">Networking</text>
                <text x="109.5" y="195.0" text-anchor="end">Soft Skills</text>
                </svg>
                <figcaption class="auth-legend">
                    <span><i class="sw sw-student"></i>Your profile</span>
                    <span><i class="sw sw-job"></i>Junior Web Developer</span>
                </figcaption>
            </figure>

            <div class="auth-note">
                <span class="auth-note-k">Gap found</span>
                <strong>Networking</strong>
                <span>Recommended: a networking fundamentals course and two practice labs.</span>
            </div>

            <p class="auth-tagline">See exactly where your skills meet the job market, and what to learn next.</p>
        </div>
    </aside>
</div>
</body>
</html>
