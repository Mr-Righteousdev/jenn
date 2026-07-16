<?php
require_once __DIR__ . '/../../src/config/app.php';
require_once __DIR__ . '/../../src/utils/auth.php';
requireRole('admin');

$db = Database::getConnection();
$message = '';

// Handle actions
$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add') {
        $stmt = $db->prepare("INSERT INTO cbc_levels (level_name, min_score, max_score, description) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_POST['level_name'], $_POST['min_score'], $_POST['max_score'], $_POST['description'] ?? null]);
        $message = 'Level added';
    } elseif ($action === 'edit' && isset($_POST['level_id'])) {
        $stmt = $db->prepare("UPDATE cbc_levels SET level_name=?, min_score=?, max_score=?, description=? WHERE level_id=?");
        $stmt->execute([$_POST['level_name'], $_POST['min_score'], $_POST['max_score'], $_POST['description'] ?? null, $_POST['level_id']]);
        $message = 'Level updated';
    }
} elseif ($action === 'delete' && isset($_GET['id'])) {
    $stmt = $db->prepare("DELETE FROM cbc_levels WHERE level_id=?");
    $stmt->execute([$_GET['id']]);
    $message = 'Level deleted';
}

$levels = $db->query("SELECT * FROM cbc_levels ORDER BY min_score")->fetchAll();
$editLevel = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $stmt = $db->prepare("SELECT * FROM cbc_levels WHERE level_id=?");
    $stmt->execute([$_GET['id']]);
    $editLevel = $stmt->fetch();
}
?>
<?php require_once __DIR__ . '/../../src/views/layout/header.php'; ?>

<h2>CBC Level Editor</h2>
<?php if ($message): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title"><?= $editLevel ? 'Edit Level' : 'Add Level' ?></h5>
                <form method="POST" action="?action=<?= $editLevel ? 'edit' : 'add' ?>">
                    <?php if ($editLevel): ?>
                        <input type="hidden" name="level_id" value="<?= $editLevel['level_id'] ?>">
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label">Level Name</label>
                        <input type="text" name="level_name" class="form-control" value="<?= htmlspecialchars($editLevel['level_name'] ?? '') ?>" required>
                    </div>
                    <div class="row mb-3">
                        <div class="col">
                            <label class="form-label">Min Score</label>
                            <input type="number" name="min_score" class="form-control" value="<?= $editLevel['min_score'] ?? '' ?>" required>
                        </div>
                        <div class="col">
                            <label class="form-label">Max Score</label>
                            <input type="number" name="max_score" class="form-control" value="<?= $editLevel['max_score'] ?? '' ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($editLevel['description'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary"><?= $editLevel ? 'Update' : 'Add' ?></button>
                    <?php if ($editLevel): ?>
                        <a href="?" class="btn btn-secondary">Cancel</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Existing Levels</h5>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Range</th>
                                <th>Description</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($levels as $level): ?>
                                <tr>
                                    <td><?= htmlspecialchars($level['level_name']) ?></td>
                                    <td><?= $level['min_score'] ?> - <?= $level['max_score'] ?></td>
                                    <td><?= htmlspecialchars($level['description'] ?? '') ?></td>
                                    <td class="text-nowrap">
                                        <a href="?action=edit&id=<?= $level['level_id'] ?>" class="btn btn-sm btn-outline-primary me-1">Edit</a>
                                        <a href="?action=delete&id=<?= $level['level_id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this level?')">Delete</a>
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
