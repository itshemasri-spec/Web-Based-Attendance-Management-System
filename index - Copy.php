<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

if (isLoggedIn()) {
    redirectByRole();
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Username and password are required.';
    } else {
        try {
            // Fetch user
            $stmt = $pdo->prepare('SELECT * FROM users WHERE username = :username LIMIT 1');
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Verify password
            if ($user && isset($user['password_hash']) && password_verify($password, $user['password_hash'])) {
                $role = (string) ($user['role'] ?? '');

                if (!in_array($role, ['admin', 'faculty', 'student'], true)) {
                    throw new RuntimeException('Invalid user role.');
                }

                // Rehash if needed
                if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                    $rehashStmt = $pdo->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
                    $rehashStmt->execute([
                        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                        'id' => (int) $user['id'],
                    ]);
                }

                // Session setup
                session_regenerate_id(true);
                $_SESSION['user'] = $user;
                $_SESSION['user_id'] = (int) $user['id'];
                $_SESSION['role'] = $role;

                // ✅ FIXED STUDENT FETCH (IMPORTANT)
                if ($role === 'student') {
                    $studentStmt = $pdo->prepare(
                        'SELECT department, section FROM students WHERE roll_no = :username LIMIT 1'
                    );
                    $studentStmt->execute(['username' => $user['username']]);
                    $student = $studentStmt->fetch(PDO::FETCH_ASSOC) ?: [];

                    $_SESSION['department'] = strtoupper(trim((string) ($student['department'] ?? '')));
                    $_SESSION['section'] = strtoupper(trim((string) ($student['section'] ?? 'A')));
                } elseif ($role === 'faculty') {
                    // Faculty department from users table
                    $facultyDepartment = strtoupper(trim((string) ($user['department'] ?? '')));
                    if ($facultyDepartment === '') {
                        throw new RuntimeException('Your department is not set. Contact administrator.');
                    }
                    $_SESSION['department'] = $facultyDepartment;
                } else {
                    unset($_SESSION['department'], $_SESSION['section']);
                }

                // Role-based redirect
                if ($role === 'admin') {
                    header('Location: ' . appUrl('admin/dashboard.php'));
                    exit;
                }

                if ($role === 'faculty') {
                    header('Location: ' . appUrl('faculty/attendance.php'));
                    exit;
                }

                if ($role === 'student') {
                    header('Location: ' . appUrl('student/dashboard.php'));
                    exit;
                }
            }

            $error = 'Invalid username or password.';
        } catch (Throwable $e) {
            // TEMP DEBUG (use if needed)
            // echo $e->getMessage(); exit;

            $error = 'Login failed. Please try again.';
        }
    }
}

$pageTitle = 'Login';
$activePage = '';
require_once __DIR__ . '/includes/header.php';
?>

<section class="card login-card">
    <?php if ($error): ?>
        <div class="alert error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" class="form-grid">
        <div>
            <label>Username</label>
            <input type="text" name="username" required>
        </div>
        <div>
            <label>Password</label>
            <input type="password" name="password" required>
        </div>
        <button type="submit">Login</button>
    </form>

    <p class="muted">Faculty demo: faculty1 / Faculty@123</p>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>