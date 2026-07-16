<?php
require_once __DIR__ . '/../../src/config/app.php';
require_once __DIR__ . '/../../src/utils/auth.php';
requireRole('admin');

$db = Database::getConnection();
$message = '';
$action = $_GET['action'] ?? '';
$categories = ['Programming', 'Web Development', 'Database Management', 'Server Configuration', 'Networking', 'Soft Skills'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add') {
        $stmt = $db->prepare("INSERT INTO skills (skill_name, category, default_weight) VALUES (?, ?, ?)");
        $stmt->execute([$_POST['skill_name'], $_POST['category'], $_POST['default_weight'] ?? 0.50]);
        $message = 'Skill added';
    } elseif ($action === 'edit' && isset($_POST['skill_id'])) {
        $stmt = $db->prepare("UPDATE skills SET skill_name=?, category=?, default_weight=? WHERE skill_id=?");
        $stmt->execute([$_POST['skill_name'], $_POST['category'], $_POST['default_weight'] ?? 0.50, $_POST['skill_id']]);
        $message = 'Skill updated';
    }
} elseif ($action === 'delete' && isset($_GET['id'])) {
    $stmt = $db->prepare("DELETE FROM skills WHERE skill_id=?");
    $stmt->execute([$_GET['id']]);
    $message = 'Skill deleted';
}

$skills = $db->query("SELECT * FROM skills ORDER BY category, skill_name")->fetchAll();
$editSkill = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $stmt = $db->prepare("SELECT * FROM skills WHERE skill_id=?");
    $stmt->execute([$_GET['id']]);
    $editSkill = $stmt->fetch();
}
?>
<?php require_once __DIR__ . '/../../src/views/layout/header.php'; ?>

<h2>Skill Editor</h2>
<?php if ($message): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title"><?= $editSkill ? 'Edit Skill' : 'Add Skill' ?></h5>
                <form method="POST" action="?action=<?= $editSkill ? 'edit' : 'add' ?>">
                    <?php if ($editSkill): ?>
                        <input type="hidden" name="skill_id" value="<?= $editSkill['skill_id'] ?>">
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label">Skill Name</label>
                        <input type="text" name="skill_name" class="form-control" value="<?= htmlspecialchars($editSkill['skill_name'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-select">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat ?>" <?= ($editSkill['category'] ?? '') === $cat ? 'selected' : '' ?>><?= $cat ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Default Weight (0.00 - 1.00)</label>
                        <input type="number" step="0.01" min="0" max="1" name="default_weight" class="form-control" value="<?= $editSkill['default_weight'] ?? 0.50 ?>" required>
                    </div>
                    <button type="submit" class="btn btn-primary"><?= $editSkill ? 'Update' : 'Add' ?></button>
                    <?php if ($editSkill): ?>
                        <a href="?" class="btn btn-secondary">Cancel</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Existing Skills</h5>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Weight</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($skills as $skill): ?>
                                <tr>
                                    <td><?= htmlspecialchars($skill['skill_name']) ?></td>
                                    <td><?= htmlspecialchars($skill['category']) ?></td>
                                    <td><?= $skill['default_weight'] ?></td>
                                    <td class="text-nowrap">
                                        <a href="?action=edit&id=<?= $skill['skill_id'] ?>" class="btn btn-sm btn-outline-primary me-1">Edit</a>
                                        <a href="?action=delete&id=<?= $skill['skill_id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this skill?')">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../src/views/layout/footer.php'; ?>
