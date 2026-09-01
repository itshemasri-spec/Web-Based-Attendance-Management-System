<?php

declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function flash(string $key, ?string $value = null): ?string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if ($value !== null) {
        $_SESSION['_flash'][$key] = $value;
        return null;
    }

    if (!isset($_SESSION['_flash'][$key])) {
        return null;
    }

    $message = $_SESSION['_flash'][$key];
    unset($_SESSION['_flash'][$key]);

    return $message;
}

function normalizeHeader(string $header): string
{
    $header = strtolower(trim($header));
    $header = str_replace(['.', '-', '/'], ' ', $header);
    return preg_replace('/\s+/', ' ', $header) ?? $header;
}
