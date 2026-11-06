<?php
require_once __DIR__ . '/../../src/config/app.php';
require_once __DIR__ . '/../../src/utils/auth.php';
requirePermission('roles.manage');

$db = Database::getConnection();
$message = $_SESSION['flash'] ?? '';
$error = '';
unset($_SESSION['flash']);
$perms = allPermissions();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $editable = $db->query("SELECT role_id FROM roles WHERE slug <> 'super_admin'")->fetchAll(PDO::FETCH_COLUMN);

    if (isset($_POST['save_matrix'])) {
        // Super admin is never in this loop: it always has everything
        $db->beginTransaction();
        $ins = $db->prepare("INSERT INTO role_permissions (role_id, permission) VALUES (?, ?)");
        $scope = $db->prepare("UPDATE roles SET scope = ? WHERE role_id = ?");
        foreach ($editable as $rid) {
            $db->prepare("DELETE FROM role_permissions WHERE role_id = ?")->execute([$rid]);
            foreach (array_keys($_POST['perm'][$rid] ?? []) as $p) {
                if (isset($perms[$p])) $ins->execute([$rid, $p]);
            }
            $sc = $_POST['scope'][$rid] ?? null;
            if (isset(SCOPES[$sc])) $scope->execute([$sc, $rid]);
        }
        $db->commit();
        $_SESSION['flash'] = 'Permissions saved. They apply the next time each person opens a page.';
    } elseif (isset($_POST['new_role'])) {
        $name = trim($_POST['name'] ?? '');
        $slug = trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($name)), '_');
        $exists = $db->prepare("SELECT 1 FROM roles WHERE slug = ? OR name = ?");
        $exists->execute([$slug, $name]);
        if ($name === '' || $slug === '') {
            $error = 'Give the new role a name.';
        } elseif ($exists->fetchColumn()) {
            $error = "A role called \"$name\" already exists.";
        } else {
            $sc = isset(SCOPES[$_POST['scope'] ?? '']) ? $_POST['scope'] : 'faculty';
            $db->prepare("INSERT INTO roles (slug, name, description, scope) VALUES (?, ?, ?, ?)")->execute([$slug, $name, trim($_POST['description'] ?? '') ?: null, $sc]);
            $newId = (int)$db->lastInsertId();
            // Optionally start from an existing role's permissions
            if ($from = (int)($_POST['copy_from'] ?? 0)) {
                $db->prepare("INSERT INTO role_permissions (role_id, permission) SELECT ?, permission FROM role_permissions WHERE role_id = ?")->execute([$newId, $from]);
            }
            $_SESSION['flash'] = "Role \"$name\" created. Tick its permissions below.";
        }
    } elseif (isset($_POST['delete_role'])) {
        $rid = (int)$_POST['delete_role'];
        $r = $db->prepare("SELECT name, is_system, (SELECT COUNT(*) FROM users WHERE role_id = roles.role_id) AS members FROM roles WHERE role_id = ?");
        $r->execute([$rid]);
        $r = $r->fetch();
        if (!$r || $r['is_system']) {
            $error = 'Built-in roles can\'t be deleted.';
        } elseif ($r['members'] > 0) {
            $error = "\"{$r['name']}\" still has {$r['members']} member" . ($r['members'] == 1 ? '' : 's') . '. Move them to another role on the Users page first.';
        } else {
            $db->prepare("DELETE FROM roles WHERE role_id = ?")->execute([$rid]);
            $_SESSION['flash'] = "Role \"{$r['name']}\" deleted.";
        }
    }
    if (!$error) {
        header('Location: ' . BASE_URL . '/admin/roles.php');
        exit;
    }
}

$roles = $db->query("SELECT r.*, (SELECT COUNT(*) FROM users u WHERE u.role_id = r.role_id AND u.is_active) AS members FROM roles r ORDER BY r.is_system DESC, r.role_id")->fetchAll();
$granted = [];
foreach ($db->query("SELECT role_id, permission FROM role_permissions") as $g) $granted[$g['role_id']][$g['permission']] = true;
?>
<?php require_once __DIR__ . '/../../src/views/layout/header.php'; ?>

<header class="page-head">
    <div>
        <h1>Roles and permissions</h1>
        <p>Tick what each role can do, and choose whose data it sees. Super admin always has everything. Changes apply to everyone with that role.</p>
    </div>
    <?php if (can('users.manage')): ?>
        <div class="page-actions"><a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-outline-primary btn-sm">Assign roles to people</a></div>
    <?php endif; ?>
</header>
<?= flash($message) ?>
<?= flash($error, 'err') ?>

<form method="POST" class="panel mb-3">
    <?= csrfField() ?>
    <div class="table-responsive">
        <table class="table data-table matrix mb-0">
            <thead>
                <tr>
                    <th class="matrix-first">Permission</th>
                    <?php foreach ($roles as $r): ?>
                        <th class="matrix-role">
                            <strong><?= htmlspecialchars($r['name']) ?></strong>
                            <span><?= $r['members'] ?> <?= $r['members'] == 1 ? 'person' : 'people' ?></span>
                        </th>
                    <?php endforeach; ?>
                </tr>
                <tr class="matrix-scope">
                    <th class="matrix-first">Sees data for</th>
                    <?php foreach ($roles as $r): ?>
                        <td>
                            <?php if ($r['slug'] === 'super_admin'): ?>
                                <span class="small text-muted">Whole university</span>
                            <?php else: ?>
                                <select name="scope[<?= $r['role_id'] ?>]" class="form-select form-select-sm" aria-label="Data scope for <?= htmlspecialchars($r['name']) ?>">
                                    <?php foreach (SCOPES as $k => $label): ?><option value="<?= $k ?>" <?= $r['scope'] === $k ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach (PERMISSIONS as $group => $list): ?>
                    <tr class="group-row"><td colspan="<?= count($roles) + 1 ?>"><?= htmlspecialchars($group) ?></td></tr>
                    <?php foreach ($list as $key => $label): ?>
                        <tr>
                            <td class="matrix-first"><?= htmlspecialchars($label) ?></td>
                            <?php foreach ($roles as $r): $super = $r['slug'] === 'super_admin'; $on = $super || isset($granted[$r['role_id']][$key]); ?>
                                <td class="matrix-cell">
                                    <input type="checkbox" class="form-check-input" name="perm[<?= $r['role_id'] ?>][<?= $key ?>]" value="1" <?= $on ? 'checked' : '' ?> <?= $super ? 'disabled' : '' ?>
                                           aria-label="<?= htmlspecialchars($r['name'] . ': ' . $label) ?>">
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                <tr class="matrix-foot">
                    <td class="matrix-first small text-muted">Built-in roles can be edited but not deleted.</td>
                    <?php foreach ($roles as $r): ?>
                        <td>
                            <?php if (!$r['is_system']): ?>
                                <button type="submit" name="delete_role" value="<?= $r['role_id'] ?>" class="btn btn-link btn-sm text-danger p-0" formnovalidate
                                        onclick="return confirm('Delete the <?= htmlspecialchars($r['name'], ENT_QUOTES) ?> role?')">Delete role</button>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            </tbody>
        </table>
    </div>
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">
        <span class="small text-muted">Tip: keep "Create and edit roles" and "Assign roles" for super admins only.</span>
        <button type="submit" name="save_matrix" value="1" class="btn btn-primary">Save permissions</button>
    </div>
</form>

<section class="panel">
    <h2 class="panel-title">Add a role</h2>
    <form method="POST" class="row g-3 align-items-end">
        <?= csrfField() ?>
        <div class="col-md-3">
            <label class="form-label" for="name">Name</label>
            <input type="text" id="name" name="name" class="form-control" placeholder="e.g. Internship coordinator" required>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="description">What it's for <span class="text-muted fw-normal">(optional)</span></label>
            <input type="text" id="description" name="description" class="form-control">
        </div>
        <div class="col-md-2">
            <label class="form-label" for="scope">Sees data for</label>
            <select id="scope" name="scope" class="form-select">
                <?php foreach (SCOPES as $k => $label): ?><option value="<?= $k ?>" <?= $k === 'faculty' ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label" for="copy_from">Start from</label>
            <select id="copy_from" name="copy_from" class="form-select">
                <option value="0">Nothing</option>
                <?php foreach ($roles as $r): if ($r['slug'] === 'super_admin') continue; ?><option value="<?= $r['role_id'] ?>"><?= htmlspecialchars($r['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-1"><button type="submit" name="new_role" value="1" class="btn btn-outline-primary w-100">Add</button></div>
    </form>
</section>

<?php require_once __DIR__ . '/../../src/views/layout/footer.php'; ?>
