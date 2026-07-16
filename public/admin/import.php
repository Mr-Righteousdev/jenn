<?php
require_once __DIR__ . '/../../src/config/app.php';
require_once __DIR__ . '/../../src/utils/auth.php';
require_once __DIR__ . '/../../src/utils/import.php';
requireRole('admin');

$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    if ($_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $content = file_get_contents($_FILES['csv_file']['tmp_name']);
        $result = importStudentResults($content);
    } else {
        $result = ['success' => false, 'error' => 'File upload error'];
    }
}
?>
<?php require_once __DIR__ . '/../../src/views/layout/header.php'; ?>

<h2>Import Student Results</h2>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Upload CSV</h5>
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">CSV File</label>
                        <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Import</button>
                </form>

                <?php if ($result): ?>
                    <hr>
                    <?php if ($result['success']): ?>
                        <div class="alert alert-success">
                            Imported <?= $result['imported'] ?> result(s) successfully.
                        </div>
                        <?php if (!empty($result['errors'])): ?>
                            <div class="alert alert-warning">
                                <strong>Warnings:</strong>
                                <ul class="mb-0">
                                    <?php foreach ($result['errors'] as $e): ?>
                                        <li><?= htmlspecialchars($e) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($result['error']) ?></div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">CSV Format</h5>
                <p>Required columns:</p>
                <code>registration_no, course_code, grade</code>
                <p class="mt-2">Optional columns:</p>
                <code>name, email, course, year_of_study, course_name, semester, academic_year</code>
                <hr>
                <h6>Example:</h6>
                <pre class="bg-light p-2 rounded"><code>registration_no,name,course,year_of_study,course_code,course_name,grade,semester,academic_year
S001,Jennifer N,BIT,3,DBMS101,Database Management,A,1,2025
S001,Jennifer N,BIT,3,PROG101,Programming,B,1,2025</code></pre>
                <p class="text-muted small mb-0">Default password for imported students: <code>student123</code></p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../src/views/layout/footer.php'; ?>
