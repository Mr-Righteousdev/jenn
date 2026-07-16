<?php
require_once __DIR__ . '/../../src/config/app.php';
require_once __DIR__ . '/../../src/utils/auth.php';
requireRole('lecturer');

$db = Database::getConnection();
$message = '';
$selectedStudentId = $_GET['student_id'] ?? $_POST['student_id'] ?? null;

// Save assessment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['skills'])) {
    $studentId = (int) $_POST['student_id'];
    $db->beginTransaction();
    try {
        $stmt = $db->prepare("INSERT INTO student_skills (student_id, skill_id, skill_level, assessed_by, assessment_date, notes)
            VALUES (?, ?, ?, ?, CURDATE(), ?)
            ON DUPLICATE KEY UPDATE skill_level = VALUES(skill_level), assessed_by = VALUES(assessed_by), assessment_date = VALUES(assessment_date), notes = VALUES(notes)");
        foreach ($_POST['skills'] as $skillId => $data) {
            $level = (int) ($data['level'] ?? 0);
            $notes = trim($data['notes'] ?? '');
            if ($level > 0) {
                $stmt->execute([$studentId, (int) $skillId, min(100, max(0, $level)), $_SESSION['user_id'], $notes ?: null]);
            }
        }
        $db->commit();
        $message = 'Assessment saved';
    } catch (Exception $e) {
        $db->rollBack();
        $message = 'Error: ' . $e->getMessage();
    }
}

// Get all students
$students = $db->query("
    SELECT s.student_id, s.registration_no, s.course, s.year_of_study, u.name
    FROM students s JOIN users u ON s.user_id = u.user_id
    ORDER BY u.name
")->fetchAll();

// Get skills and existing assessments for selected student
$skills = $db->query("SELECT * FROM skills ORDER BY category, skill_name")->fetchAll();
$assessments = [];
if ($selectedStudentId) {
    $stmt = $db->prepare("SELECT skill_id, skill_level, notes FROM student_skills WHERE student_id = ? AND assessed_by = ?");
    $stmt->execute([$selectedStudentId, $_SESSION['user_id']]);
    foreach ($stmt->fetchAll() as $row) {
        $assessments[$row['skill_id']] = $row;
    }
}
?>
<?php require_once __DIR__ . '/../../src/views/layout/header.php'; ?>

<h2>Skill Assessment</h2>
<?php if ($message): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-4 mb-3">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Select Student</h5>
                <form method="GET">
                    <select name="student_id" class="form-select mb-2" onchange="this.form.submit()">
                        <option value="">-- Choose student --</option>
                        <?php foreach ($students as $s): ?>
                            <option value="<?= $s['student_id'] ?>" <?= $selectedStudentId == $s['student_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['name'] . ' (' . $s['registration_no'] . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <noscript><button type="submit" class="btn btn-primary btn-sm">Go</button></noscript>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <?php if ($selectedStudentId): ?>
            <?php
            $stmt = $db->prepare("SELECT u.name, s.registration_no, s.course, s.year_of_study FROM students s JOIN users u ON s.user_id = u.user_id WHERE s.student_id = ?");
            $stmt->execute([$selectedStudentId]);
            $student = $stmt->fetch();
            ?>
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">
                        Assessing: <?= htmlspecialchars($student['name']) ?>
                        <small class="text-muted">(<?= htmlspecialchars($student['registration_no']) ?> | <?= htmlspecialchars($student['course']) ?> Year <?= $student['year_of_study'] ?>)</small>
                    </h5>
                    <form method="POST">
                        <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Skill</th>
                                        <th>Category</th>
                                        <th style="width:120px">Level (0-100)</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($skills as $skill): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($skill['skill_name']) ?></td>
                                            <td><span class="badge bg-info"><?= htmlspecialchars($skill['category']) ?></span></td>
                                            <td>
                                                <input type="number" name="skills[<?= $skill['skill_id'] ?>][level]" class="form-control form-control-sm" min="0" max="100"
                                                    value="<?= $assessments[$skill['skill_id']]['skill_level'] ?? '' ?>"
                                                    placeholder="0-100">
                                            </td>
                                            <td>
                                                <input type="text" name="skills[<?= $skill['skill_id'] ?>][notes]" class="form-control form-control-sm"
                                                    value="<?= htmlspecialchars($assessments[$skill['skill_id']]['notes'] ?? '') ?>"
                                                    placeholder="Optional notes">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <button type="submit" class="btn btn-primary">Submit Assessment</button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-body text-center text-muted py-5">
                    <p class="mb-0">Select a student from the dropdown to begin assessment.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../src/views/layout/footer.php'; ?>
