<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireLogin('student');
$user = currentUser();

$studentStmt = $pdo->prepare('SELECT * FROM students WHERE user_id = :user_id LIMIT 1');
$studentStmt->execute(['user_id' => $user['id']]);
$student = $studentStmt->fetch();

if (!$student) {
    http_response_code(404);
    exit('Student profile not found.');
}

$_SESSION['department'] = $student['department'];
$_SESSION['section'] = $student['section'] ?? 'A'; // Default to 'A' if NULL

$department = strtoupper(trim((string) ($_SESSION['department'] ?? '')));
$section = strtoupper(trim((string) ($_SESSION['section'] ?? 'A'))); // Default to 'A' if empty

// Validate department exists and section is valid
if ($department === '' || !in_array($section, ['A', 'B'], true)) {
    // Try to use default 'A' if section is invalid
    if (!in_array($section, ['A', 'B'], true)) {
        $section = 'A';
        $_SESSION['section'] = 'A';
    }
    
    // If department is still empty, redirect to login
    if ($department === '') {
        header('Location: ' . appUrl('index.php'));
        exit;
    }
}

$semester = (int) ($_GET['semester'] ?? 1);
if ($semester < 1 || $semester > 8) {
    $semester = 1;
}

$statusStmt = $pdo->prepare("SELECT ar.status, COUNT(*) AS total
    FROM attendance_records ar
    JOIN attendance_sessions sess ON sess.id = ar.session_id
    JOIN students st ON st.id = ar.student_id
    WHERE st.id = :student_id
      AND st.department = :department
      AND st.section = :section
      AND ar.semester = :semester
    GROUP BY ar.status");

$statusStmt->execute([
    'student_id' => $student['id'],
    'department' => $department,
    'section' => $section,
    'semester' => $semester,
]);

$counts = ['Present' => 0, 'Absent' => 0, 'OD' => 0, 'ML' => 0];
$total = 0;
foreach ($statusStmt->fetchAll() as $row) {
    if (!isset($counts[$row['status']])) {
        continue;
    }
    $counts[$row['status']] = (int) $row['total'];
    $total += (int) $row['total'];
}

$percentage = $total > 0 ? round(($counts['Present'] / $total) * 100, 2) : 0;

$tableStmt = $pdo->prepare("SELECT sess.attendance_date, sess.period, ar.status
    FROM attendance_records ar
    JOIN attendance_sessions sess ON sess.id = ar.session_id
    JOIN students st ON st.id = ar.student_id
    WHERE st.id = :student_id
      AND st.department = :department
      AND st.section = :section
      AND ar.semester = :semester
    ORDER BY sess.attendance_date DESC, CAST(sess.period AS UNSIGNED) ASC");

$tableStmt->execute([
    'student_id' => $student['id'],
    'department' => $department,
    'section' => $section,
    'semester' => $semester,
]);

$rowsByDate = [];
foreach ($tableStmt->fetchAll() as $row) {
    $dateKey = (string) $row['attendance_date'];
    if (!isset($rowsByDate[$dateKey])) {
        $rowsByDate[$dateKey] = [
            'date' => $dateKey,
            'day' => date('D', strtotime($dateKey)),
            'periods' => [
                1 => '-', 2 => '-', 3 => '-', 4 => '-',
                5 => '-', 6 => '-', 7 => '-', 8 => '-',
            ],
        ];
    }

    $periodNumber = 0;
    if (preg_match('/(\d+)/', (string) $row['period'], $m) === 1) {
        $periodNumber = (int) $m[1];
    }

    if ($periodNumber >= 1 && $periodNumber <= 8) {
        $rowsByDate[$dateKey]['periods'][$periodNumber] = $row['status'];
    }
}

$attendanceTable = array_values($rowsByDate);

$pageTitle = 'Student Dashboard';
$activePage = 'student-dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="card">
    <h2>Attendance Overview</h2>
    <form method="get" class="form-grid grid-3">
        <div>
            <label>Semester</label>
            <select name="semester">
                <?php for ($s = 1; $s <= 8; $s++): ?>
                    <option value="<?= $s ?>" <?= $semester === $s ? 'selected' : '' ?>>Semester <?= $s ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div>
            <label>Department</label>
            <input type="text" value="<?= e($department) ?>" readonly>
        </div>
        <div>
            <label>Section</label>
            <input type="text" value="Section <?= e($section) ?>" readonly>
        </div>
        <button type="submit">Apply</button>
    </form>
</section>

<section class="stats-grid">
    <article class="stat-card">
        <h3>Present (Hours)</h3>
        <p><?= $counts['Present'] ?></p>
    </article>
    <article class="stat-card">
        <h3>Absent (Hours)</h3>
        <p><?= $counts['Absent'] ?></p>
    </article>
    <article class="stat-card">
        <h3>OD (Hours)</h3>
        <p><?= $counts['OD'] ?></p>
    </article>
    <article class="stat-card">
        <h3>ML (Hours)</h3>
        <p><?= $counts['ML'] ?></p>
    </article>
</section>

<section class="card">
    <div class="stats-grid" style="grid-template-columns: repeat(2, minmax(160px, 1fr)); margin-bottom: 8px;">
        <article class="stat-card">
            <h3>Worked (Hours)</h3>
            <p><?= $total ?></p>
        </article>
        <article class="stat-card">
            <h3>Attendance (%)</h3>
            <p><?= $percentage ?>%</p>
        </article>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>#</th>
                <th>Date</th>
                <th>Day</th>
                <th>Period 1</th>
                <th>Period 2</th>
                <th>Period 3</th>
                <th>Period 4</th>
                <th>Period 5</th>
                <th>Period 6</th>
                <th>Period 7</th>
                <th>Period 8</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($attendanceTable)): ?>
                <tr>
                    <td colspan="11" class="muted">No attendance data available.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($attendanceTable as $index => $row): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><?= e($row['date']) ?></td>
                        <td><?= e($row['day']) ?></td>
                        <?php for ($p = 1; $p <= 8; $p++): ?>
                            <?php $status = (string) $row['periods'][$p]; ?>
                            <td>
                                <?php if ($status === 'Present'): ?>
                                    <span class="status status-present">P</span>
                                <?php elseif ($status === 'Absent'): ?>
                                    <span class="status status-absent">A</span>
                                <?php elseif ($status === 'OD'): ?>
                                    <span class="status status-od">OD</span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        <?php endfor; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php';
