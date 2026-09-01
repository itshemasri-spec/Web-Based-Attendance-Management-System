<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireLogin('faculty');

// Validate session department
$sessionDepartment = strtoupper(trim((string) ($_SESSION['department'] ?? '')));
if ($sessionDepartment === '') {
    die('Error: Your department is not set in the session. Please log in again.');
}

// Predefined constants for departments and batches
const ALLOWED_DEPARTMENTS = ['CSE', 'ECE', 'EEE', 'MECH', 'AIDS', 'CSBS'];
const ALLOWED_BATCHES = ['2025-2029', '2024-2028', '2023-2027', '2022-2026'];
const ALLOWED_SECTIONS = ['A', 'B'];

$success = null;
$error = null;
$formData = [];

$years = range(1, 4);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'roll_no' => trim((string) ($_POST['roll_no'] ?? '')),
        'student_name' => trim((string) ($_POST['student_name'] ?? '')),
        'reg_no' => trim((string) ($_POST['reg_no'] ?? '')),
        'department' => $sessionDepartment,  // Always use session department, never POST
        'section' => strtoupper(trim((string) ($_POST['section'] ?? ''))),
        'year_of_study' => (int) trim((string) ($_POST['year_of_study'] ?? '0')),
        'batch' => trim((string) ($_POST['batch'] ?? '')),
        'category' => strtoupper(trim((string) ($_POST['category'] ?? 'GQ'))),
        'scholar_type' => trim((string) ($_POST['scholar_type'] ?? 'Dayscholar')),
    ];

    if ($formData['roll_no'] === '') {
        $error = 'Roll Number is required.';
    } elseif ($formData['student_name'] === '') {
        $error = 'Student Name is required.';
    } elseif ($formData['reg_no'] === '') {
        $error = 'Registration Number is required.';
    } elseif ($formData['department'] === '') {
        $error = 'Department is required.';
    } elseif (!in_array($formData['department'], ALLOWED_DEPARTMENTS, true)) {
        $error = 'Invalid Department. Allowed: ' . implode(', ', ALLOWED_DEPARTMENTS);
    } elseif ($formData['section'] === '') {
        $error = 'Section is required.';
    } elseif (!in_array($formData['section'], ALLOWED_SECTIONS, true)) {
        $error = 'Invalid Section. Allowed: ' . implode(', ', ALLOWED_SECTIONS);
    } elseif ($formData['year_of_study'] < 1 || $formData['year_of_study'] > 4) {
        $error = 'Year of Study must be between 1 and 4.';
    } elseif ($formData['batch'] === '') {
        $error = 'Batch is required.';
    } elseif (!in_array($formData['batch'], ALLOWED_BATCHES, true)) {
        $error = 'Invalid Batch. Allowed: ' . implode(', ', ALLOWED_BATCHES);
    } elseif ($formData['category'] !== 'MQ' && $formData['category'] !== 'GQ') {
        $error = 'Invalid Category. Use MQ or GQ.';
    } elseif ($formData['scholar_type'] !== 'Dayscholar' && $formData['scholar_type'] !== 'Hosteller') {
        $error = 'Invalid Scholar Type. Use Dayscholar or Hosteller.';
    } else {
        try {
            if (!in_array($formData['section'], ['A', 'B'], true)) {
                throw new RuntimeException('Invalid section selected');
            }

            $pdo->beginTransaction();

            $checkRollNo = $pdo->prepare('SELECT id FROM students WHERE roll_no = :roll_no LIMIT 1');
            $checkRollNo->execute(['roll_no' => $formData['roll_no']]);
            if ($checkRollNo->fetch()) {
                throw new RuntimeException('Roll Number already exists.');
            }

            $checkRegNo = $pdo->prepare('SELECT id FROM students WHERE reg_no = :reg_no LIMIT 1');
            $checkRegNo->execute(['reg_no' => $formData['reg_no']]);
            if ($checkRegNo->fetch()) {
                throw new RuntimeException('Registration Number already exists.');
            }

            $checkUser = $pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
            $checkUser->execute(['username' => $formData['roll_no']]);
            $existingUser = $checkUser->fetch();
            $userId = null;

            if (!$existingUser) {
                $insertUser = $pdo->prepare('INSERT INTO users (username, password_hash, role, full_name, department) VALUES (:username, :password_hash, :role, :full_name, :department)');
                $insertUser->execute([
                    'username' => $formData['roll_no'],
                    'password_hash' => password_hash($formData['roll_no'], PASSWORD_DEFAULT),
                    'role' => 'student',
                    'full_name' => $formData['student_name'],
                    'department' => $formData['department'],
                ]);
                $userId = (int) $pdo->lastInsertId();
            } else {
                $userId = (int) $existingUser['id'];
            }

            $applicationNo = 'APP_' . strtoupper($formData['roll_no']) . '_' . time();

            $insertStudent = $pdo->prepare('INSERT INTO students (application_no, student_name, roll_no, reg_no, department, section, year_of_study, batch, category, scholar_type, user_id) VALUES (:application_no, :student_name, :roll_no, :reg_no, :department, :section, :year_of_study, :batch, :category, :scholar_type, :user_id)');
            $insertStudent->execute([
                'application_no' => $applicationNo,
                'student_name' => $formData['student_name'],
                'roll_no' => $formData['roll_no'],
                'reg_no' => $formData['reg_no'],
                'department' => $formData['department'],
                'section' => $formData['section'],
                'year_of_study' => $formData['year_of_study'],
                'batch' => $formData['batch'],
                'category' => $formData['category'],
                'scholar_type' => $formData['scholar_type'],
                'user_id' => $userId,
            ]);

            $pdo->commit();
            $success = 'Student added successfully. Login credentials: Username = ' . $formData['roll_no'] . ', Password = ' . $formData['roll_no'];
            $formData = [];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Failed to add student: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Add Student';
$activePage = 'faculty-add-student';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="card">
    <h2>Add New Student</h2>

    <?php if ($success): ?>
        <div class="alert success"><?= e($success) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" class="form-grid grid-2">
        <div>
            <label>Roll Number <span class="required">*</span></label>
            <input type="text" name="roll_no" value="<?= e($formData['roll_no'] ?? '') ?>" required>
            <small>Auto-generates username for login</small>
        </div>

        <div>
            <label>Student Name <span class="required">*</span></label>
            <input type="text" name="student_name" value="<?= e($formData['student_name'] ?? '') ?>" required>
        </div>

        <div>
            <label>Registration Number <span class="required">*</span></label>
            <input type="text" name="reg_no" value="<?= e($formData['reg_no'] ?? '') ?>" required>
        </div>

        <div>
            <label>Department <span class="required">*</span></label>
            <input type="text" value="<?= e($sessionDepartment) ?>" readonly>
            <input type="hidden" name="department" value="<?= e($sessionDepartment) ?>">
            <small>Your department is fixed. Contact admin to change.</small>
        </div>

        <div>
            <label>Year of Study <span class="required">*</span></label>
            <select name="year_of_study" required>
                <option value="">-- Select Year --</option>
                <?php foreach ($years as $y): ?>
                    <option value="<?= $y ?>" <?= ($formData['year_of_study'] ?? 0) === $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label>Section <span class="required">*</span></label>
            <select name="section" required>
                <option value="">Select Section</option>
                <?php foreach (ALLOWED_SECTIONS as $section): ?>
                    <option value="<?= e($section) ?>" <?= ($formData['section'] ?? '') === $section ? 'selected' : '' ?>>Section <?= e($section) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label>Batch <span class="required">*</span></label>
            <select name="batch" required>
                <option value="">-- Select Batch --</option>
                <?php foreach (ALLOWED_BATCHES as $b): ?>
                    <option value="<?= e($b) ?>" <?= ($formData['batch'] ?? '') === $b ? 'selected' : '' ?>><?= e($b) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label>Category <span class="required">*</span></label>
            <select name="category" required>
                <option value="GQ" <?= ($formData['category'] ?? 'GQ') === 'GQ' ? 'selected' : '' ?>>GQ (General Quota)</option>
                <option value="MQ" <?= ($formData['category'] ?? '') === 'MQ' ? 'selected' : '' ?>>MQ (Management Quota)</option>
            </select>
        </div>

        <div>
            <label>Scholar Type <span class="required">*</span></label>
            <select name="scholar_type" required>
                <option value="Dayscholar" <?= ($formData['scholar_type'] ?? 'Dayscholar') === 'Dayscholar' ? 'selected' : '' ?>>Dayscholar</option>
                <option value="Hosteller" <?= ($formData['scholar_type'] ?? '') === 'Hosteller' ? 'selected' : '' ?>>Hosteller</option>
            </select>
        </div>

        <div style="grid-column: 1 / -1;">
            <button type="submit">Add Student</button>
               <a href="<?= e(appUrl('faculty/attendance.php')) ?>" class="btn-secondary">Cancel</a>
        </div>
    </form>

    <div style="margin-top: 20px; padding: 12px; background: #f0f9ff; border-left: 4px solid #2563eb; border-radius: 4px;">
        <strong>Login Credentials:</strong><br>
        Username = Roll Number<br>
        Password = Roll Number (auto-hashed using bcrypt)
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php';
