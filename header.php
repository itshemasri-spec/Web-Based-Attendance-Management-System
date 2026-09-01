<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';

$user = currentUser();
$pageTitle = $pageTitle ?? 'Online Attendance System';
$activePage = $activePage ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= e(appUrl('assets/css/style.css')) ?>">
</head>
<body>
<div class="layout">
    <?php if (!empty($user)): ?>
        <aside class="sidebar">
            <h2>Attendance</h2>
            <p class="role-tag"><?= e(strtoupper($user['role'])) ?></p>
            <nav>
                <?php if ($user['role'] === 'admin'): ?>
                    <a class="<?= $activePage === 'admin-dashboard' ? 'active' : '' ?>" href="<?= e(appUrl('admin/dashboard.php')) ?>">Dashboard</a>
                    <a class="<?= $activePage === 'admin-manage-faculty' ? 'active' : '' ?>" href="<?= e(appUrl('admin/manage_faculty.php')) ?>">Manage Faculty</a>
                    <a class="<?= $activePage === 'admin-attendance' ? 'active' : '' ?>" href="<?= e(appUrl('admin/attendance.php')) ?>">View Attendance</a>
                <?php elseif ($user['role'] === 'faculty'): ?>
                    <a class="<?= $activePage === 'faculty-profile' ? 'active' : '' ?>" href="<?= e(appUrl('faculty/profile.php')) ?>">Profile</a>
                    <a class="<?= $activePage === 'faculty-attendance' ? 'active' : '' ?>" href="<?= e(appUrl('faculty/attendance.php')) ?>">Attendance</a>
                    <a class="<?= $activePage === 'faculty-import' ? 'active' : '' ?>" href="<?= e(appUrl('faculty/import_students.php')) ?>">Import Students</a>
                    <a class="<?= $activePage === 'faculty-add-student' ? 'active' : '' ?>" href="<?= e(appUrl('faculty/add_student.php')) ?>">Add Student</a>
                    <a class="<?= $activePage === 'faculty-export' ? 'active' : '' ?>" href="<?= e(appUrl('faculty/export_attendance.php')) ?>">Export Report</a>
                <?php elseif ($user['role'] === 'student'): ?>
                    <a class="<?= $activePage === 'student-dashboard' ? 'active' : '' ?>" href="<?= e(appUrl('student/dashboard.php')) ?>">Dashboard</a>
                    <a class="<?= $activePage === 'student-profile' ? 'active' : '' ?>" href="<?= e(appUrl('student/profile.php')) ?>">Profile</a>
                <?php endif; ?>
                <a href="<?= e(appUrl('logout.php')) ?>">Logout</a>
            </nav>
        </aside>
    <?php endif; ?>

    <main class="content">
        <header class="topbar">
            <h1><?= e($pageTitle) ?></h1>
            <?php if (!empty($user)): ?>
                <span>Welcome, <?= e($user['full_name']) ?></span>
            <?php endif; ?>
        </header>
