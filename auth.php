<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function appBaseUrl(): string
{
    if (defined('APP_BASE_URL') && is_string(APP_BASE_URL) && APP_BASE_URL !== '') {
        $configured = '/' . trim(APP_BASE_URL, '/') . '/';
        return preg_replace('#/+#', '/', $configured) ?? '/';
    }

    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $parts = array_values(array_filter(explode('/', trim($scriptName, '/'))));

    if (count($parts) >= 2) {
        return '/' . $parts[0] . '/';
    }

    return '/';
}

function appUrl(string $path = ''): string
{
    $base = appBaseUrl();
    $path = ltrim($path, '/');
    return $path === '' ? $base : $base . $path;
}

function isLoggedIn(): bool
{
    return !empty($_SESSION['user']);
}

function requireLogin(?string $role = null): void
{
    if (!isLoggedIn()) {
        header('Location: ' . appUrl('index.php'));
        exit;
    }

    if ($role !== null && ($_SESSION['user']['role'] ?? '') !== $role) {
        redirectByRole();
        exit;
    }
}

function currentUser(): array
{
    return $_SESSION['user'] ?? [];
}

function redirectByRole(): void
{
    if (!isLoggedIn()) {
        return;
    }

    if (($_SESSION['user']['role'] ?? '') === 'admin') {
        header('Location: ' . appUrl('admin/dashboard.php'));
        exit;
    }

    if (($_SESSION['user']['role'] ?? '') === 'faculty') {
        header('Location: ' . appUrl('faculty/attendance.php'));
        exit;
    }

    header('Location: ' . appUrl('student/dashboard.php'));
    exit;
}
