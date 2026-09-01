<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireLogin('admin');

// Define available departments
$departments = ['CSE', 'ECE', 'EEE', 'MECH', 'AIDS', 'CSBS'];

$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['add_faculty'] ?? '') === '1') {
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $department = strtoupper(trim((string) ($_POST['department'] ?? '')));

    if ($fullName === '' || $username === '' || $password === '' || $department === '') {
        $error = 'All fields are required.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif (!in_array($department, $departments, true)) {
        $error = 'Invalid department selected.';
    } else {
        try {
            $insertFaculty = $pdo->prepare('INSERT INTO users (username, password_hash, role, full_name, email, department) VALUES (:username, :password_hash, :role, :full_name, NULL, :department)');
            $insertFaculty->execute([
                'username' => $username,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role' => 'faculty',
                'full_name' => $fullName,
                'department' => $department,
            ]);
            $message = 'Faculty added successfully.';
        } catch (Throwable $e) {
            $error = 'Failed to add faculty. Username may already exist.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['delete_faculty'] ?? '') === '1') {
    $facultyId = (int) ($_POST['faculty_id'] ?? 0);

    if ($facultyId <= 0) {
        $error = 'Invalid faculty selection.';
    } else {
        $deleteFaculty = $pdo->prepare('DELETE FROM users WHERE id = :id AND role = :role');
        $deleteFaculty->execute([
            'id' => $facultyId,
            'role' => 'faculty',
        ]);
        $message = $deleteFaculty->rowCount() > 0 ? 'Faculty deleted successfully.' : 'Faculty record not found.';
    }
}

$facultyStmt = $pdo->prepare('SELECT id, full_name, username, department, created_at FROM users WHERE role = :role ORDER BY full_name');
$facultyStmt->execute(['role' => 'faculty']);
$facultyList = $facultyStmt->fetchAll();

$pageTitle = 'Manage Faculty';
$activePage = 'admin-manage-faculty';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="card">
    <h2>Add Faculty</h2>

    <?php if ($message): ?>
        <div class="alert success"><?= e($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" class="form-grid grid-2">
        <div>
            <label>Full Name</label>
            <input type="text" name="full_name" required>
        </div>
        <div>
            <label>Username</label>
            <input type="text" name="username" required>
        </div>
        <div>
            <label>Password</label>
            <input type="password" name="password" required>
        </div>
        <div>
            <label>Department</label>
            <select name="department" required>
                <option value="">Select Department</option>
                <?php foreach ($departments as $department): ?>
                    <option value="<?= e((string) $department) ?>"><?= e((string) $department) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <input type="hidden" name="add_faculty" value="1">
            <button type="submit">Add Faculty</button>
        </div>
    </form>
</section>

<section class="card">
    <h2>Faculty List</h2>

    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>#</th>
                <th>Name</th>
                <th>Username</th>
                <th>Department</th>
                <th>Created At</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($facultyList)): ?>
                <tr>
                    <td colspan="6" class="muted">No faculty records available.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($facultyList as $index => $faculty): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><?= e((string) $faculty['full_name']) ?></td>
                        <td><?= e((string) $faculty['username']) ?></td>
                        <td><?= e((string) $faculty['department']) ?></td>
                        <td><?= e((string) $faculty['created_at']) ?></td>
                        <td>
                            <form method="post" onsubmit="return confirm('Delete this faculty account?');" style="display:inline;">
                                <input type="hidden" name="faculty_id" value="<?= (int) $faculty['id'] ?>">
                                <input type="hidden" name="delete_faculty" value="1">
                                <button type="submit" class="btn-secondary">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php';
