<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireLogin('faculty');

$departments = $pdo->query('SELECT DISTINCT department FROM students ORDER BY department')->fetchAll(PDO::FETCH_COLUMN);
$batches = $pdo->query('SELECT DISTINCT batch FROM students ORDER BY batch')->fetchAll(PDO::FETCH_COLUMN);
$years = $pdo->query('SELECT DISTINCT year_of_study FROM students ORDER BY year_of_study')->fetchAll(PDO::FETCH_COLUMN);

$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$department = $_GET['department'] ?? ($departments[0] ?? '');
$yearOfStudy = (int) ($_GET['year_of_study'] ?? ($years[0] ?? 1));
$batch = $_GET['batch'] ?? ($batches[0] ?? '');

if (isset($_GET['export']) && $_GET['export'] === '1') {
    $stmt = $pdo->prepare("SELECT s.attendance_date, s.period, s.subject_name, st.application_no, st.student_name, st.roll_no, st.reg_no, st.department, st.year_of_study, st.batch, ar.status, ar.remarks FROM attendance_records ar JOIN attendance_sessions s ON s.id = ar.session_id JOIN students st ON st.id = ar.student_id WHERE s.attendance_date BETWEEN :date_from AND :date_to AND s.department = :department AND s.year_of_study = :year_of_study AND s.batch = :batch ORDER BY s.attendance_date, s.period, st.roll_no");

    $stmt->execute([
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'department' => $department,
        'year_of_study' => $yearOfStudy,
        'batch' => $batch,
    ]);

    $filename = 'attendance_report_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename={$filename}");

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Date', 'Period', 'Subject', 'Application No', 'Student Name', 'Roll No', 'Reg No', 'Department', 'Year', 'Batch', 'Status', 'Remarks']);

    while ($row = $stmt->fetch()) {
        fputcsv($output, $row);
    }

    fclose($output);
    exit;
}

$pageTitle = 'Export Attendance';
$activePage = 'faculty-export';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="card">
    <h2>Export Attendance Report (CSV)</h2>
    <form method="get" class="form-grid grid-5">
        <div>
            <label>Date From</label>
            <input type="date" name="date_from" value="<?= e($dateFrom) ?>" required>
        </div>
        <div>
            <label>Date To</label>
            <input type="date" name="date_to" value="<?= e($dateTo) ?>" required>
        </div>
        <div>
            <label>Department</label>
            <select name="department" required>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= e((string) $d) ?>" <?= $department === $d ? 'selected' : '' ?>><?= e((string) $d) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>Year</label>
            <select name="year_of_study" required>
                <?php foreach ($years as $y): ?>
                    <option value="<?= e((string) $y) ?>" <?= $yearOfStudy === (int) $y ? 'selected' : '' ?>><?= e((string) $y) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>Batch</label>
            <select name="batch" required>
                <?php foreach ($batches as $b): ?>
                    <option value="<?= e((string) $b) ?>" <?= $batch === $b ? 'selected' : '' ?>><?= e((string) $b) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <input type="hidden" name="export" value="1">
        <button type="submit">Download CSV</button>
    </form>
</section>

<?php require_once __DIR__ . '/../includes/footer.php';
