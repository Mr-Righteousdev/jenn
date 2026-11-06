<?php
require_once __DIR__ . '/../../src/config/app.php';
require_once __DIR__ . '/../../src/utils/auth.php';
requirePermission('cbc.manage');

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

$levels = $db->query("SELECT cl.*, (SELECT COUNT(*) FROM students s WHERE s.cbc_level_id = cl.level_id) AS students FROM cbc_levels cl ORDER BY cl.min_score")->fetchAll();
$unassigned = (int)$db->query("SELECT COUNT(*) FROM students WHERE cbc_level_id IS NULL")->fetchColumn();
// One-hue ramp, light to dark, for the ordinal levels
$ramp = ['#9FD6D2', '#4DB3AC', '#008C85', '#0A5F5A', '#063D3A'];
$editLevel = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $stmt = $db->prepare("SELECT * FROM cbc_levels WHERE level_id=?");
    $stmt->execute([$_GET['id']]);
    $editLevel = $stmt->fetch();
}
?>
<?php require_once __DIR__ . '/../../src/views/layout/header.php'; ?>

<header class="page-head">
    <div>
        <h1>CBC levels</h1>
        <p>The competency bands students are placed in, from their overall score out of 100.</p>
    </div>
</header>
<?= flash($message) ?>

<section class="panel mb-3">
    <div class="d-flex justify-content-between align-items-baseline flex-wrap gap-2 mb-3">
        <h2 class="panel-title mb-0">Scale</h2>
        <span class="small text-muted"><?= $unassigned ? "$unassigned student" . ($unassigned === 1 ? '' : 's') . ' not yet placed' : 'All students placed' ?></span>
    </div>
    <div class="scale" role="list">
        <?php foreach ($levels as $i => $l): $span = max(1, $l['max_score'] - $l['min_score'] + 1); ?>
            <div class="scale-seg" role="listitem" style="flex: <?= $span ?>; --seg: <?= $ramp[min($i, count($ramp) - 1)] ?>">
                <div class="scale-bar"></div>
                <strong><?= htmlspecialchars($l['level_name']) ?></strong>
                <span><?= $l['min_score'] ?>–<?= $l['max_score'] ?> · <?= $l['students'] ?> student<?= $l['students'] == 1 ? '' : 's' ?></span>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="scale-axis"><span>0</span><span>50</span><span>100</span></div>
</section>

<div class="row g-3">
    <div class="col-lg-4">
        <section class="panel form-panel">
            <h2 class="panel-title"><?= $editLevel ? 'Edit level' : 'Add a level' ?></h2>
            <form method="POST" action="?action=<?= $editLevel ? 'edit' : 'add' ?>">
                <?php if ($editLevel): ?>
                    <input type="hidden" name="level_id" value="<?= $editLevel['level_id'] ?>">
                <?php endif; ?>
                <div class="mb-3">
                    <label class="form-label" for="level_name">Level name</label>
                    <input type="text" id="level_name" name="level_name" class="form-control" placeholder="e.g. Advanced" value="<?= htmlspecialchars($editLevel['level_name'] ?? '') ?>" required>
                </div>
                <div class="row mb-3">
                    <div class="col">
                        <label class="form-label" for="min_score">Min score</label>
                        <input type="number" id="min_score" name="min_score" min="0" max="100" class="form-control" value="<?= $editLevel['min_score'] ?? '' ?>" required>
                    </div>
                    <div class="col">
                        <label class="form-label" for="max_score">Max score</label>
                        <input type="number" id="max_score" name="max_score" min="0" max="100" class="form-control" value="<?= $editLevel['max_score'] ?? '' ?>" required>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label" for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="2" placeholder="What a student at this level can do"><?= htmlspecialchars($editLevel['description'] ?? '') ?></textarea>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><?= $editLevel ? 'Save changes' : 'Add level' ?></button>
                    <?php if ($editLevel): ?><a href="?" class="btn btn-outline-secondary">Cancel</a><?php endif; ?>
                </div>
            </form>
        </section>
    </div>
    <div class="col-lg-8">
        <section class="panel">
            <h2 class="panel-title"><?= count($levels) ?> levels</h2>
            <div class="table-responsive">
                <table class="table data-table mb-0">
                    <thead><tr><th>Level</th><th>Range</th><th>Description</th><th class="text-end">Students</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($levels as $i => $level): ?>
                            <tr>
                                <td><span class="swatch" style="--seg: <?= $ramp[min($i, count($ramp) - 1)] ?>"></span><strong><?= htmlspecialchars($level['level_name']) ?></strong></td>
                                <td class="num"><?= $level['min_score'] ?>–<?= $level['max_score'] ?></td>
                                <td class="text-muted"><?= htmlspecialchars($level['description'] ?? '') ?></td>
                                <td class="text-end num"><?= $level['students'] ?></td>
                                <td>
                                    <div class="row-actions">
                                        <a href="?action=edit&id=<?= $level['level_id'] ?>" class="icon-btn" title="Edit" aria-label="Edit <?= htmlspecialchars($level['level_name']) ?>"><?= icon('edit') ?></a>
                                        <a href="?action=delete&id=<?= $level['level_id'] ?>" class="icon-btn is-danger" title="Delete" aria-label="Delete <?= htmlspecialchars($level['level_name']) ?>" onclick="return confirm('Delete this level?')"><?= icon('trash') ?></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>

<?php require_once __DIR__ . '/../../src/views/layout/footer.php'; ?>
