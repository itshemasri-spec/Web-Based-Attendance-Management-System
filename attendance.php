<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireLogin('faculty');
$user = currentUser();

$facultyDepartment = strtoupper(trim((string) ($user['department'] ?? '')));
if ($facultyDepartment === '') {
    http_response_code(403);
    exit('Faculty department is not configured.');
}

$sections = ['A', 'B'];

$yearSemesterMap = [
    1 => [1, 2],
    2 => [3, 4],
    3 => [5, 6],
    4 => [7, 8],
];

$years = [1, 2, 3, 4];
$allowedPeriods = ['1', '2', '3', '4', '5', '6', '7', '8'];

$selectedYear = (int) ($_POST['year_of_study'] ?? 0);
$selectedSection = strtoupper(trim((string) ($_POST['section'] ?? '')));

$date = (string) ($_POST['attendance_date'] ?? date('Y-m-d'));
$period = (string) ($_POST['period'] ?? '1');
$semester = (int) ($_POST['semester'] ?? 0);
$subjectName = trim((string) ($_POST['subject_name'] ?? ''));

$department = $facultyDepartment;

$message = null;
$error = null;
$students = [];
$studentsLoaded = false;
$isLoadAction = $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['load_students'] ?? '') === '1';
$isSaveAction = $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['save_attendance'] ?? '') === '1';

if ($selectedYear > 0 && isset($yearSemesterMap[$selectedYear]) && $semester === 0) {
    $semester = $yearSemesterMap[$selectedYear][0];
}

if ($isLoadAction || $isSaveAction) {
    if ($selectedYear < 1 || $selectedYear > 4) {
        $error = 'Please select a valid year (1-4).';
    } elseif (!in_array($selectedSection, $sections, true)) {
        $error = 'Please select a valid section.';
    }

    if ($error === null) {
        $studentParams = [
            'department' => $department,
            'year_of_study' => $selectedYear,
            'section' => $selectedSection,
        ];

        $studentSql = 'SELECT id, application_no, student_name, roll_no, reg_no, category, scholar_type, section FROM students WHERE department = :department AND year_of_study = :year_of_study AND section = :section';
        $studentSql .= ' ORDER BY roll_no';

        $studentStmt = $pdo->prepare($studentSql);
        $studentStmt->execute($studentParams);
        $students = $studentStmt->fetchAll();
        $studentsLoaded = true;
    }
}

if ($isSaveAction) {
    $statuses = $_POST['status'] ?? [];
    $remarks = $_POST['remarks'] ?? [];

    if ($error !== null) {
        $statuses = [];
    } elseif (!in_array($period, $allowedPeriods, true)) {
        $error = 'Invalid period selection.';
    } elseif (!isset($yearSemesterMap[$selectedYear]) || !in_array($semester, $yearSemesterMap[$selectedYear], true)) {
        $error = 'Invalid semester for the selected year.';
    } elseif (empty($students)) {
        $error = 'No students available for the selected class.';
    }

    if ($error !== null) {
        $statuses = [];
    }

    try {
        if ($error !== null) {
            throw new RuntimeException($error);
        }

        $pdo->beginTransaction();

        $findSession = $pdo->prepare('SELECT id FROM attendance_sessions WHERE attendance_date=:attendance_date AND period=:period AND department=:department AND year_of_study=:year_of_study AND batch=:batch LIMIT 1');
        $findSession->execute([
            'attendance_date' => $date,
            'period' => $period,
            'department' => $department,
            'year_of_study' => $selectedYear,
            'batch' => '',
        ]);
        $sessionId = (int) ($findSession->fetch()['id'] ?? 0);

        if ($sessionId === 0) {
            $insertSession = $pdo->prepare('INSERT INTO attendance_sessions (attendance_date, period, department, year_of_study, batch, subject_name, faculty_id) VALUES (:attendance_date, :period, :department, :year_of_study, :batch, :subject_name, :faculty_id)');
            $insertSession->execute([
                'attendance_date' => $date,
                'period' => $period,
                'department' => $department,
                'year_of_study' => $selectedYear,
                'batch' => '',
                'subject_name' => $subjectName,
                'faculty_id' => $user['id'],
            ]);
            $sessionId = (int) $pdo->lastInsertId();
        } else {
            $updateSession = $pdo->prepare('UPDATE attendance_sessions SET subject_name=:subject_name, faculty_id=:faculty_id WHERE id=:id');
            $updateSession->execute([
                'subject_name' => $subjectName,
                'faculty_id' => $user['id'],
                'id' => $sessionId,
            ]);
        }

        $upsertRecord = $pdo->prepare("INSERT INTO attendance_records (session_id, student_id, status, semester, remarks) VALUES (:session_id, :student_id, :status, :semester, :remarks) ON DUPLICATE KEY UPDATE status = VALUES(status), semester = VALUES(semester), remarks = VALUES(remarks), marked_at = CURRENT_TIMESTAMP");

        $eligibleParams = [
            'department' => $department,
            'year_of_study' => $selectedYear,
            'section' => $selectedSection,
        ];
        $eligibleSql = 'SELECT id FROM students WHERE department = :department AND year_of_study = :year_of_study AND section = :section';

        $eligibleStmt = $pdo->prepare($eligibleSql);
        $eligibleStmt->execute($eligibleParams);
        $eligibleIds = array_map(static fn($row) => (int) $row['id'], $eligibleStmt->fetchAll());
        $eligibleIdMap = array_flip($eligibleIds);

        foreach ($statuses as $studentId => $status) {
            $studentId = (int) $studentId;
            if (!isset($eligibleIdMap[$studentId])) {
                continue;
            }

            $status = in_array($status, ['Present', 'Absent', 'OD'], true) ? $status : 'Present';
            $upsertRecord->execute([
                'session_id' => $sessionId,
                'student_id' => $studentId,
                'status' => $status,
                'semester' => $semester,
                'remarks' => trim((string) ($remarks[$studentId] ?? '')),
            ]);
        }

        $pdo->commit();
        $message = 'Attendance saved successfully.';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = 'Failed to save attendance: ' . $e->getMessage();
    }
}

if ($studentsLoaded && !empty($students) && in_array($period, $allowedPeriods, true) && isset($yearSemesterMap[$selectedYear]) && in_array($semester, $yearSemesterMap[$selectedYear], true)) {
    $existingParams = [
        'attendance_date' => $date,
        'period' => $period,
        'department' => $department,
        'year_of_study' => $selectedYear,
        'semester' => $semester,
    ];

    $existingSql = 'SELECT ar.student_id, ar.status, ar.remarks
        FROM attendance_records ar
        JOIN attendance_sessions s ON s.id = ar.session_id
        JOIN students st ON st.id = ar.student_id
        WHERE s.attendance_date = :attendance_date
          AND s.period = :period
          AND s.department = :department
          AND s.year_of_study = :year_of_study
          AND ar.semester = :semester
          AND st.section = :section
          AND s.batch = ""';
    $existingParams['section'] = $selectedSection;

    $existingStmt = $pdo->prepare($existingSql);
    $existingStmt->execute($existingParams);

    $existingMap = [];
    foreach ($existingStmt->fetchAll() as $row) {
        $existingMap[(int) $row['student_id']] = $row;
    }

    foreach ($students as &$student) {
        $sid = (int) $student['id'];
        $student['status'] = $existingMap[$sid]['status'] ?? 'Present';
        $student['remarks'] = $existingMap[$sid]['remarks'] ?? '';
    }
    unset($student);
}

$pageTitle = 'Attendance';
$activePage = 'faculty-attendance';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="card">
    <h2>Select Class</h2>

    <?php if ($message): ?>
        <div class="alert success"><?= e($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="form-grid grid-3">
            <div>
                <label>Year</label>
                <select id="year_of_study" name="year_of_study" required>
                    <option value="">Select Year</option>
                    <?php foreach ($years as $y): ?>
                        <option value="<?= e((string) $y) ?>" <?= $selectedYear === (int) $y ? 'selected' : '' ?>>Year <?= e((string) $y) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Section</label>
                <select name="section" required>
                    <option value="">Select Section</option>
                    <?php foreach ($sections as $sec): ?>
                        <option value="<?= e((string) $sec) ?>" <?= $selectedSection === (string) $sec ? 'selected' : '' ?>>Section <?= e((string) $sec) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Department</label>
                <input type="text" value="<?= e($facultyDepartment) ?>" readonly>
            </div>
        </div>

        <input type="hidden" name="load_students" value="1">
        <div class="mt-16">
            <button type="submit" class="btn-secondary">Load Students</button>
        </div>
    </form>
</section>

<?php if ($studentsLoaded): ?>
    <section class="card">
        <h2>Mark Attendance</h2>

        <?php if (empty($students)): ?>
            <p class="muted">No students found for selected filters.</p>
        <?php else: ?>
            <form method="post">
                <input type="hidden" name="year_of_study" value="<?= e((string) $selectedYear) ?>">
                <input type="hidden" name="section" value="<?= e($selectedSection) ?>">

                <div class="form-grid grid-6">
                    <div>
                        <label>Date</label>
                        <input type="date" name="attendance_date" value="<?= e($date) ?>" required>
                    </div>
                    <div>
                        <label>Semester <span class="required">*</span></label>
                        <select id="semester" name="semester" required>
                            <option value="">Select Semester</option>
                        </select>
                    </div>
                    <div>
                        <label>Period</label>
                        <select name="period" required>
                            <?php foreach ($allowedPeriods as $p): ?>
                                <option value="<?= e($p) ?>" <?= $period === $p ? 'selected' : '' ?>>Period <?= e($p) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Subject</label>
                        <input type="text" name="subject_name" value="<?= e($subjectName) ?>">
                    </div>
                </div>

            <div class="toolbar">
                <label><input type="checkbox" id="select-all-students"> Select All</label>
                <button type="button" class="btn-secondary" data-bulk-status="Present">Mark Selected Present</button>
                <button type="button" class="btn-secondary" data-bulk-status="Absent">Mark Selected Absent</button>
                <button type="button" class="btn-secondary" data-bulk-status="OD">Mark Selected OD</button>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Select</th>
                        <th>Student Name</th>
                        <th>Roll No</th>
                        <th>Section</th>
                        <th>Status</th>
                        <th>Remarks</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td><input type="checkbox" class="student-check" value="<?= (int) $student['id'] ?>"></td>
                            <td><?= e($student['student_name']) ?></td>
                            <td><?= e($student['roll_no']) ?></td>
                            <td><?= e((string) ($student['section'] ?? '-')) ?></td>
                            <td>
                                <select name="status[<?= (int) $student['id'] ?>]" class="status-select" data-student-id="<?= (int) $student['id'] ?>">
                                    <option value="Present" <?= $student['status'] === 'Present' ? 'selected' : '' ?>>Present</option>
                                    <option value="Absent" <?= $student['status'] === 'Absent' ? 'selected' : '' ?>>Absent</option>
                                    <option value="OD" <?= $student['status'] === 'OD' ? 'selected' : '' ?>>OD</option>
                                </select>
                            </td>
                            <td>
                                <input type="text" name="remarks[<?= (int) $student['id'] ?>]" value="<?= e((string) $student['remarks']) ?>" placeholder="Optional remark">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-16">
                <button type="submit" name="save_attendance" value="1">Save Attendance</button>
            </div>
            </form>
        <?php endif; ?>
    </section>
<?php endif; ?>

<script>
const yearSemesterMap = {
    1: [1, 2],
    2: [3, 4],
    3: [5, 6],
    4: [7, 8]
};

function populateSemesterDropdown(year, selectedSemester) {
    const semesterDropdown = document.getElementById("semester");
    if (!semesterDropdown) {
        return;
    }

    semesterDropdown.innerHTML = '<option value="">Select Semester</option>';
    if (!year || !yearSemesterMap[year]) {
        return;
    }

    yearSemesterMap[year].forEach(function(sem) {
        const option = document.createElement("option");
        option.value = String(sem);
        option.text = "Semester " + sem;
        if (String(selectedSemester) === String(sem)) {
            option.selected = true;
        }
        semesterDropdown.appendChild(option);
    });
}

document.addEventListener("DOMContentLoaded", function() {
    const selectedYear = parseInt("<?= (int) $selectedYear ?>", 10);
    const selectedSemester = parseInt("<?= (int) $semester ?>", 10);
    populateSemesterDropdown(selectedYear, selectedSemester);

    const selectAll = document.getElementById("select-all-students");
    if (selectAll) {
        selectAll.addEventListener("change", function() {
            document.querySelectorAll(".student-check").forEach(function(checkbox) {
                checkbox.checked = selectAll.checked;
            });
        });
    }

    document.querySelectorAll("[data-bulk-status]").forEach(function(button) {
        button.addEventListener("click", function() {
            const targetStatus = button.getAttribute("data-bulk-status");
            document.querySelectorAll(".student-check:checked").forEach(function(checkbox) {
                const row = checkbox.closest("tr");
                if (!row) {
                    return;
                }
                const select = row.querySelector(".status-select");
                if (select) {
                    select.value = targetStatus;
                }
            });
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php';
