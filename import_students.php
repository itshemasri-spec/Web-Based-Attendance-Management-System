<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireLogin('faculty');

$success = null;
$error = null;
$report = [
    'inserted' => 0,
    'updated' => 0,
    'skipped' => 0,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['student_file'])) {
    $file = $_FILES['student_file'];

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $error = 'File upload failed.';
    } else {
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['xlsx', 'xls', 'csv'], true)) {
            $error = 'Only .xlsx, .xls, .csv files are allowed.';
        } else {
            try {
                require_once __DIR__ . '/../includes/excel.php';
                $rows = readSpreadsheetRows($file['tmp_name'], $file['name']);

                if (count($rows) < 2) {
                    throw new RuntimeException('No data rows found.');
                }

                $headers = array_map(static fn($h) => normalizeHeader((string) $h), $rows[0]);

                $map = [
                    'application_no' => ['application number', 'application no', 'application'],
                    'student_name' => ['student name', 'name'],
                    'roll_no' => ['roll no', 'roll number'],
                    'reg_no' => ['reg no', 'register no', 'registration no', 'reg number'],
                    'department' => ['department', 'dept'],
                    'year_of_study' => ['year of study', 'year'],
                    'batch' => ['batch'],
                    'category' => ['category'],
                    'scholar_type' => ['dayscholar hosteller', 'dayscholar/hosteller', 'scholar type', 'day scholar hosteller'],
                ];

                $index = [];
                foreach ($map as $field => $aliases) {
                    $index[$field] = null;
                    foreach ($headers as $i => $header) {
                        if (in_array($header, $aliases, true)) {
                            $index[$field] = $i;
                            break;
                        }
                    }
                    if ($index[$field] === null) {
                        throw new RuntimeException("Missing column: {$field}");
                    }
                }

                $pdo->beginTransaction();

                $findUser = $pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
                $insertUser = $pdo->prepare('INSERT INTO users (username, password_hash, role, full_name, department) VALUES (:username, :password_hash, :role, :full_name, :department)');

                $findStudent = $pdo->prepare('SELECT id FROM students WHERE application_no = :application_no LIMIT 1');
                $insertStudent = $pdo->prepare('INSERT INTO students (application_no, student_name, roll_no, reg_no, department, year_of_study, batch, category, scholar_type, user_id) VALUES (:application_no, :student_name, :roll_no, :reg_no, :department, :year_of_study, :batch, :category, :scholar_type, :user_id)');
                $updateStudent = $pdo->prepare('UPDATE students SET student_name=:student_name, roll_no=:roll_no, reg_no=:reg_no, department=:department, year_of_study=:year_of_study, batch=:batch, category=:category, scholar_type=:scholar_type, user_id=:user_id WHERE id=:id');

                for ($r = 1; $r < count($rows); $r++) {
                    $row = $rows[$r];
                    $applicationNo = trim((string) ($row[$index['application_no']] ?? ''));
                    $studentName = trim((string) ($row[$index['student_name']] ?? ''));
                    $rollNo = trim((string) ($row[$index['roll_no']] ?? ''));
                    $regNo = trim((string) ($row[$index['reg_no']] ?? ''));
                    $department = trim((string) ($row[$index['department']] ?? ''));
                    $yearOfStudy = (int) trim((string) ($row[$index['year_of_study']] ?? '0'));
                    $batch = trim((string) ($row[$index['batch']] ?? ''));
                    $category = strtoupper(trim((string) ($row[$index['category']] ?? '')));
                    $scholarTypeRaw = strtolower(trim((string) ($row[$index['scholar_type']] ?? '')));

                    $scholarType = str_contains($scholarTypeRaw, 'host') ? 'Hosteller' : 'Dayscholar';
                    if ($category !== 'MQ' && $category !== 'GQ') {
                        $category = 'GQ';
                    }

                    if ($applicationNo === '' || $studentName === '' || $rollNo === '' || $regNo === '' || $department === '' || $yearOfStudy < 1 || $yearOfStudy > 4 || $batch === '') {
                        $report['skipped']++;
                        continue;
                    }

                    $findUser->execute(['username' => $rollNo]);
                    $userId = (int) ($findUser->fetch()['id'] ?? 0);

                    if ($userId === 0) {
                        $insertUser->execute([
                            'username' => $rollNo,
                            'password_hash' => password_hash($applicationNo, PASSWORD_DEFAULT),
                            'role' => 'student',
                            'full_name' => $studentName,
                            'department' => $department,
                        ]);
                        $userId = (int) $pdo->lastInsertId();
                    }

                    $studentPayload = [
                        'application_no' => $applicationNo,
                        'student_name' => $studentName,
                        'roll_no' => $rollNo,
                        'reg_no' => $regNo,
                        'department' => $department,
                        'year_of_study' => $yearOfStudy,
                        'batch' => $batch,
                        'category' => $category,
                        'scholar_type' => $scholarType,
                        'user_id' => $userId,
                    ];

                    $findStudent->execute(['application_no' => $applicationNo]);
                    $existing = $findStudent->fetch();

                    if ($existing) {
                        $studentPayload['id'] = (int) $existing['id'];
                        $updateStudent->execute($studentPayload);
                        $report['updated']++;
                    } else {
                        $insertStudent->execute($studentPayload);
                        $report['inserted']++;
                    }
                }

                $pdo->commit();
                $success = 'Student import completed successfully.';
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Import failed: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Import Students';
$activePage = 'faculty-import';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="card">
    <h2>Upload Student Excel/CSV</h2>

    <?php if ($success): ?>
        <div class="alert success"><?= e($success) ?></div>
        <div class="badge-row">
            <span class="badge">Inserted: <?= $report['inserted'] ?></span>
            <span class="badge">Updated: <?= $report['updated'] ?></span>
            <span class="badge">Skipped: <?= $report['skipped'] ?></span>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="form-grid">
        <div>
            <label>Student File (.xlsx/.xls/.csv)</label>
            <input type="file" name="student_file" accept=".xlsx,.xls,.csv" required>
        </div>
        <button type="submit">Import Students</button>
    </form>
</section>

<?php require_once __DIR__ . '/../includes/footer.php';
