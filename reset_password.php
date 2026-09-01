<?php

declare(strict_types=1);

/**
 * Temporary password reset script.
 *
 * Usage (browser): /reset_password.php?user=faculty1
 * Usage (CLI): php reset_password.php faculty1
 *
 * IMPORTANT: Delete this file after successful reset.
 */

require_once __DIR__ . '/config/database.php';

if (PHP_SAPI === 'cli') {
    $username = $argv[1] ?? 'faculty1';
} else {
    $username = trim((string) ($_GET['user'] ?? 'faculty1'));
}

if ($username === '') {
    http_response_code(400);
    exit('Username is required.');
}

$newPlainPassword = 'Faculty@123';
$newPasswordHash = password_hash($newPlainPassword, PASSWORD_DEFAULT);

if ($newPasswordHash === false) {
    http_response_code(500);
    exit('Failed to generate password hash.');
}

try {
    $stmt = $pdo->prepare('UPDATE users SET password_hash = :password_hash WHERE username = :username');
    $stmt->execute([
        'password_hash' => $newPasswordHash,
        'username' => $username,
    ]);

    $checkStmt = $pdo->prepare('SELECT id, username, role FROM users WHERE username = :username LIMIT 1');
    $checkStmt->execute(['username' => $username]);
    $user = $checkStmt->fetch();

    if (!$user) {
        http_response_code(404);
        exit('User not found.');
    }

    echo 'Password reset successful for username: ' . $user['username'] . PHP_EOL;
    echo 'You can now login with Password: Faculty@123' . PHP_EOL;
    echo 'For security, delete reset_password.php after use.' . PHP_EOL;
} catch (Throwable $e) {
    http_response_code(500);
    exit('Password reset failed.');
}
