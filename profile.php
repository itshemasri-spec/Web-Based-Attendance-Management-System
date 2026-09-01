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

$pageTitle = 'Profile';
$activePage = 'student-profile';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="card">
    <h2>Student Profile</h2>
    <div class="profile-grid">
        <div><strong>Application Number</strong><br><?= e($student['application_no']) ?></div>
        <div><strong>Student Name</strong><br><?= e($student['student_name']) ?></div>
        <div><strong>Roll No</strong><br><?= e($student['roll_no']) ?></div>
        <div><strong>Reg No</strong><br><?= e($student['reg_no']) ?></div>
        <div><strong>Department</strong><br><?= e($student['department']) ?></div>
        <div><strong>Year of Study</strong><br><?= e((string) $student['year_of_study']) ?></div>
        <div><strong>Batch</strong><br><?= e($student['batch']) ?></div>
        <div><strong>Category</strong><br><?= e($student['category']) ?></div>
        <div><strong>Dayscholar/Hosteller</strong><br><?= e($student['scholar_type']) ?></div>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php';
