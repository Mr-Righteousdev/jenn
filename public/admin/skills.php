<?php
require_once __DIR__ . '/../../src/config/app.php';
require_once __DIR__ . '/../../src/utils/auth.php';
requirePermission('skills.manage');

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

$skills = $db->query("SELECT sk.*,
        (SELECT COUNT(*) FROM job_skills js WHERE js.skill_id = sk.skill_id) AS jobs,
        (SELECT ROUND(AVG(skill_level)) FROM student_skills ss WHERE ss.skill_id = sk.skill_id) AS avg_level
    FROM skills sk ORDER BY category, skill_name")->fetchAll();
$catCounts = array_count_values(array_column($skills, 'category'));
$editSkill = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $stmt = $db->prepare("SELECT * FROM skills WHERE skill_id=?");
    $stmt->execute([$_GET['id']]);
    $editSkill = $stmt->fetch();
}
?>
<?php require_once __DIR__ . '/../../src/views/layout/header.php'; ?>

<header class="page-head">
    <div>
        <h1>Skills</h1>
        <p>The competencies students are assessed on and jobs are matched against. Weight sets how much a skill counts by default.</p>
    </div>
</header>
<?= flash($message) ?>

<div class="row g-3">
    <div class="col-lg-4">
        <section class="panel form-panel">
            <h2 class="panel-title"><?= $editSkill ? 'Edit skill' : 'Add a skill' ?></h2>
            <form method="POST" action="?action=<?= $editSkill ? 'edit' : 'add' ?>">
                <?php if ($editSkill): ?>
                    <input type="hidden" name="skill_id" value="<?= $editSkill['skill_id'] ?>">
                <?php endif; ?>
                <div class="mb-3">
                    <label class="form-label" for="skill_name">Skill name</label>
                    <input type="text" id="skill_name" name="skill_name" class="form-control" placeholder="e.g. Docker" value="<?= htmlspecialchars($editSkill['skill_name'] ?? '') ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="category">Category</label>
                    <select id="category" name="category" class="form-select">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat ?>" <?= ($editSkill['category'] ?? '') === $cat ? 'selected' : '' ?>><?= $cat ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="form-label d-flex justify-content-between" for="default_weight">Default weight <output id="weightOut" class="text-muted fw-semibold"><?= $editSkill['default_weight'] ?? '0.50' ?></output></label>
                    <input type="range" step="0.05" min="0" max="1" id="default_weight" name="default_weight" class="form-range" value="<?= $editSkill['default_weight'] ?? 0.50 ?>"
                           oninput="weightOut.value = Number(this.value).toFixed(2)">
                    <div class="d-flex justify-content-between small text-muted"><span>Minor</span><span>Critical</span></div>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><?= $editSkill ? 'Save changes' : 'Add skill' ?></button>
                    <?php if ($editSkill): ?><a href="?" class="btn btn-outline-secondary">Cancel</a><?php endif; ?>
                </div>
            </form>
        </section>
    </div>

    <div class="col-lg-8">
        <section class="panel">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <h2 class="panel-title mb-0"><?= count($skills) ?> skills</h2>
                <div class="chips" role="group" aria-label="Filter by category">
                    <button type="button" class="chip is-on" data-filter="">All</button>
                    <?php foreach ($catCounts as $cat => $n): ?>
                        <button type="button" class="chip" data-filter="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?> <span><?= $n ?></span></button>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table data-table mb-0" id="skillsTable">
                    <thead><tr><th>Skill</th><th>Category</th><th style="width: 22%">Weight</th><th class="text-end">Jobs asking</th><th class="text-end">Student avg</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($skills as $skill): $w = (float)$skill['default_weight']; ?>
                            <tr data-cat="<?= htmlspecialchars($skill['category']) ?>">
                                <td><strong><?= htmlspecialchars($skill['skill_name']) ?></strong></td>
                                <td><span class="tag"><?= htmlspecialchars($skill['category']) ?></span></td>
                                <td><div class="meter meter-sm"><span class="is-ready" style="width: <?= $w * 100 ?>%"></span></div><span class="meter-val"><?= number_format($w, 2) ?></span></td>
                                <td class="text-end num"><?= $skill['jobs'] ?: '<span class="text-muted">0</span>' ?></td>
                                <td class="text-end num"><?= $skill['avg_level'] !== null ? $skill['avg_level'] . '<span class="text-muted">/100</span>' : '<span class="text-muted">—</span>' ?></td>
                                <td>
                                    <div class="row-actions">
                                        <a href="?action=edit&id=<?= $skill['skill_id'] ?>" class="icon-btn" title="Edit" aria-label="Edit <?= htmlspecialchars($skill['skill_name']) ?>"><?= icon('edit') ?></a>
                                        <a href="?action=delete&id=<?= $skill['skill_id'] ?>" class="icon-btn is-danger" title="Delete" aria-label="Delete <?= htmlspecialchars($skill['skill_name']) ?>" onclick="return confirm('Delete this skill?')"><?= icon('trash') ?></a>
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

<script>
document.querySelectorAll('.chip[data-filter]').forEach(chip => chip.addEventListener('click', () => {
    document.querySelectorAll('.chip[data-filter]').forEach(c => c.classList.toggle('is-on', c === chip));
    document.querySelectorAll('#skillsTable tbody tr').forEach(tr => tr.hidden = chip.dataset.filter && tr.dataset.cat !== chip.dataset.filter);
}));
</script>

<?php require_once __DIR__ . '/../../src/views/layout/footer.php'; ?>
