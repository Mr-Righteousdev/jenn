<?php
require_once __DIR__ . '/../../src/config/app.php';
require_once __DIR__ . '/../../src/utils/auth.php';
requirePermission('users.manage');

$db = Database::getConnection();
$me = getCurrentUser();
$iAmSuper = $me['role_slug'] === 'super_admin';
$superId = (int)$db->query("SELECT role_id FROM roles WHERE slug = 'super_admin'")->fetchColumn();
$message = $_SESSION['flash'] ?? '';
$error = '';
unset($_SESSION['flash']);

$activeSupers = fn() => (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'staff' AND is_active AND role_id = $superId")->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $target = $db->prepare("SELECT user_id, name, role_id, is_active FROM users WHERE user_id = ? AND role = 'staff'");
    $target->execute([(int)($_POST['user_id'] ?? 0)]);
    $t = $target->fetch();

    if (!$t) {
        $error = 'That staff account no longer exists.';
    } elseif ((int)$t['user_id'] === (int)$me['user_id']) {
        $error = 'You can\'t change your own role or deactivate yourself. Ask another super admin.';
    } elseif (!$iAmSuper && ((int)$t['role_id'] === $superId || (int)($_POST['role_id'] ?? 0) === $superId)) {
        $error = 'Only a super admin can grant or change the Super admin role.';
    } elseif (isset($_POST['role_id'])) {
        $newRole = (int)$_POST['role_id'] ?: null;
        if ((int)$t['role_id'] === $superId && $newRole !== $superId && $t['is_active'] && $activeSupers() <= 1) {
            $error = 'This is the last active super admin. Give someone else the role first.';
        } else {
            $db->prepare("UPDATE users SET role_id = ? WHERE user_id = ?")->execute([$newRole, $t['user_id']]);
            $_SESSION['flash'] = "{$t['name']}'s role was updated.";
        }
    } elseif (isset($_POST['toggle_active'])) {
        if ($t['is_active'] && (int)$t['role_id'] === $superId && $activeSupers() <= 1) {
            $error = 'This is the last active super admin, so it can\'t be deactivated.';
        } else {
            $db->prepare("UPDATE users SET is_active = NOT is_active WHERE user_id = ?")->execute([$t['user_id']]);
            $_SESSION['flash'] = $t['name'] . ($t['is_active'] ? ' was deactivated and can no longer sign in.' : ' was reactivated.');
        }
    }
    if (!$error) {
        header('Location: ' . BASE_URL . '/admin/users.php' . (isset($_GET['role']) ? '?role=' . urlencode($_GET['role']) : ''));
        exit;
    }
}

$roles = $db->query("SELECT role_id, name, slug, scope FROM roles ORDER BY role_id")->fetchAll();
$staff = $db->query("
    SELECT u.user_id, u.name, u.email, u.source, u.is_active, u.role_id, r.name AS role_name, r.scope, f.name AS faculty
    FROM users u LEFT JOIN roles r ON r.role_id = u.role_id LEFT JOIN faculties f ON f.code = u.faculty_code
    WHERE u.role = 'staff' ORDER BY u.is_active DESC, r.role_id IS NULL, r.role_id, u.name")->fetchAll();
$studentCount = (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
$counts = array_count_values(array_map(fn($u) => (string)$u['role_id'], array_filter($staff, fn($u) => $u['is_active'])));
$filter = $_GET['role'] ?? '';
?>
<?php require_once __DIR__ . '/../../src/views/layout/header.php'; ?>

<header class="page-head">
    <div>
        <h1>Users</h1>
        <p>Staff come from the university system and sign in with their portal login. Give each person the role that matches their job; new staff start as Lecturers.</p>
    </div>
    <?php if (can('roles.manage')): ?>
        <div class="page-actions"><a href="<?= BASE_URL ?>/admin/roles.php" class="btn btn-outline-primary btn-sm">Roles and permissions</a></div>
    <?php endif; ?>
</header>
<?= flash($message) ?>
<?= flash($error, 'err') ?>

<section class="kpis kpis-4" aria-label="Accounts">
    <div class="kpi"><span class="kpi-label">Staff accounts</span><span class="kpi-value"><?= count(array_filter($staff, fn($u) => $u['is_active'])) ?></span><span class="kpi-sub">active</span></div>
    <div class="kpi"><span class="kpi-label">Super admins</span><span class="kpi-value"><?= $counts[(string)$superId] ?? 0 ?></span><span class="kpi-sub">full control</span></div>
    <div class="kpi"><span class="kpi-label">Deactivated</span><span class="kpi-value"><?= count(array_filter($staff, fn($u) => !$u['is_active'])) ?></span><span class="kpi-sub">can't sign in</span></div>
    <div class="kpi"><span class="kpi-label">Students</span><span class="kpi-value"><?= $studentCount ?></span><span class="kpi-sub">managed by the university</span></div>
</section>

<section class="panel">
    <div class="toolbar">
        <div class="chips" role="group" aria-label="Filter by role">
            <a class="chip<?= $filter === '' ? ' is-on' : '' ?>" href="?">All <span><?= count($staff) ?></span></a>
            <?php foreach ($roles as $r): ?>
                <a class="chip<?= $filter === $r['slug'] ? ' is-on' : '' ?>" href="?role=<?= $r['slug'] ?>"><?= htmlspecialchars($r['name']) ?> <span><?= $counts[(string)$r['role_id']] ?? 0 ?></span></a>
            <?php endforeach; ?>
        </div>
        <label class="search">
            <?= icon('search') ?>
            <input type="search" class="form-control" placeholder="Search name or email" aria-label="Search staff"
                   oninput="const q = this.value.toLowerCase(); document.querySelectorAll('#staffTable tbody tr').forEach(tr => tr.hidden = !tr.dataset.q.includes(q))">
        </label>
    </div>
    <div class="table-responsive">
        <table class="table data-table mb-0" id="staffTable">
            <thead><tr><th>Person</th><th>Faculty</th><th>Sign-in</th><th style="width: 280px">Role</th><th>Status</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($staff as $u):
                    if ($filter && !array_filter($roles, fn($r) => $r['slug'] === $filter && (int)$r['role_id'] === (int)$u['role_id'])) continue;
                    $isMe = (int)$u['user_id'] === (int)$me['user_id'];
                    $locked = $isMe || (!$iAmSuper && (int)$u['role_id'] === $superId); ?>
                    <tr data-q="<?= htmlspecialchars(strtolower($u['name'] . ' ' . ($u['email'] ?? ''))) ?>" class="<?= $u['is_active'] ? '' : 'is-inactive' ?>">
                        <td><strong><?= htmlspecialchars($u['name']) ?></strong><?= $isMe ? ' <span class="tag">You</span>' : '' ?><span class="d-block small text-muted"><?= htmlspecialchars($u['email'] ?? '') ?></span></td>
                        <td class="small"><?= htmlspecialchars(nice($u['faculty'] ?? '—')) ?></td>
                        <td><span class="tag <?= $u['source'] === 'local' ? 'tag-warn' : '' ?>" title="<?= $u['source'] === 'local' ? 'Emergency account with its own password' : 'University portal login' ?>"><?= $u['source'] === 'local' ? 'Local' : 'University' ?></span></td>
                        <td>
                            <form method="POST" class="d-flex gap-2 align-items-center">
                                <?= csrfField() ?>
                                <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                <select name="role_id" class="form-select form-select-sm" aria-label="Role for <?= htmlspecialchars($u['name']) ?>" <?= $locked ? 'disabled' : '' ?>
                                        onchange="this.form.querySelector('button').hidden = false">
                                    <option value="">No role (can sign in, sees nothing)</option>
                                    <?php foreach ($roles as $r): if ($r['role_id'] === $superId && !$iAmSuper) continue; ?>
                                        <option value="<?= $r['role_id'] ?>" <?= (int)$u['role_id'] === (int)$r['role_id'] ? 'selected' : '' ?>><?= htmlspecialchars($r['name']) ?> · <?= SCOPES[$r['scope']] ?></option>
                                    <?php endforeach; ?>
                                    <?php if ($locked && (int)$u['role_id'] === $superId && !$iAmSuper): ?><option selected>Super admin</option><?php endif; ?>
                                </select>
                                <button type="submit" class="btn btn-primary btn-sm" hidden>Save</button>
                            </form>
                        </td>
                        <td><?= $u['is_active'] ? '<span class="tag tag-brand">Active</span>' : '<span class="tag">Deactivated</span>' ?></td>
                        <td>
                            <?php if (!$locked): ?>
                                <form method="POST" class="row-actions">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                    <button type="submit" name="toggle_active" value="1" class="btn btn-sm <?= $u['is_active'] ? 'btn-outline-danger' : 'btn-outline-secondary' ?>"
                                            onclick="return confirm('<?= $u['is_active'] ? 'Deactivate this account? They will be signed out and unable to sign in.' : 'Reactivate this account?' ?>')"><?= $u['is_active'] ? 'Deactivate' : 'Reactivate' ?></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="small text-muted mt-3 mb-0">A faculty-level role covers the person's own faculty in the university system. Deactivating someone here doesn't touch their university account.</p>
</section>

<?php require_once __DIR__ . '/../../src/views/layout/footer.php'; ?>
