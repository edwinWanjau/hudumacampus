<?php
/**
 * HudumaCampus - Core Helper Functions
 * Provides session guards, CSRF protection, reusable form parser,
 * multidimensional arrays, file logging, and CSV export utilities.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/db.php';

/**
 * Starts a hardened PHP session if not already active.
 */
function initSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

/**
 * Escapes output safely for HTML rendering to prevent XSS.
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Cleans raw user input strings (trim + strip control characters).
 */
function sanitizeInput(string $data): string
{
    return trim(str_replace("\0", '', $data));
}

/**
 * Validates an email address using PHP's built-in filter_var().
 */
function validateEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Redirects the browser to a path relative to BASE_URL and halts execution.
 */
function redirect(string $path): never
{
    header('Location: ' . BASE_URL . $path);
    exit;
}

/**
 * Stores or retrieves one-time flash notifications in $_SESSION.
 */
function setFlash(string $type, string $message): void
{
    initSession();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array
{
    initSession();
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Checks whether a user is logged in and enforces authentication on protected pages.
 */
function isLoggedIn(): bool
{
    initSession();
    return isset($_SESSION['user_id']) && is_int($_SESSION['user_id']);
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        setFlash('error', 'Please log in to access HudumaCampus.');
        redirect('/pages/login.php');
    }
}

/**
 * Generates and verifies session-based CSRF tokens for POST forms.
 */
function generateCsrfToken(): string
{
    initSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(?string $token): bool
{
    initSession();
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Reusable Form Parser: sanitizes and validates an entire form data array
 * against declarative rules so validation logic is never duplicated.
 *
 * Supported rules: required, email, min, max, in, match, pattern, label
 *
 * @return array{data: array<string, string>, errors: array<string, string>}
 */
function parseAndValidateForm(array $source, array $rules): array
{
    $cleaned = [];
    $errors  = [];

    foreach ($rules as $field => $ruleSet) {
        $label = $ruleSet['label'] ?? ucfirst(str_replace('_', ' ', $field));
        $raw   = isset($source[$field]) && is_string($source[$field]) ? $source[$field] : '';
        $value = !empty($ruleSet['raw']) ? $raw : sanitizeInput($raw);
        $cleaned[$field] = $value;

        if (!empty($ruleSet['required']) && $value === '') {
            $errors[$field] = "{$label} is required.";
            continue;
        }

        if ($value === '') {
            continue;
        }

        if (!empty($ruleSet['email']) && !validateEmail($value)) {
            $errors[$field] = "Please enter a valid email address for {$label}.";
        }

        if (isset($ruleSet['min']) && strlen($value) < $ruleSet['min']) {
            $errors[$field] = "{$label} must be at least {$ruleSet['min']} characters.";
        }

        if (isset($ruleSet['max']) && strlen($value) > $ruleSet['max']) {
            $errors[$field] = "{$label} must not exceed {$ruleSet['max']} characters.";
        }

        if (isset($ruleSet['in']) && !in_array($value, $ruleSet['in'], true)) {
            $errors[$field] = "Selected {$label} is not allowed.";
        }

        if (isset($ruleSet['pattern']) && !preg_match($ruleSet['pattern'], $value)) {
            $errors[$field] = $ruleSet['pattern_msg'] ?? "{$label} format is invalid.";
        }

        if (isset($ruleSet['match'])) {
            $targetField = $ruleSet['match'];
            $targetValue = isset($source[$targetField]) ? (string) $source[$targetField] : '';
            if ($value !== $targetValue) {
                $errors[$field] = "{$label} does not match.";
            }
        }
    }

    return ['data' => $cleaned, 'errors' => $errors];
}

/**
 * Multidimensional array of Helpdesk Subject Categories.
 * IDs align with the `categories` table in MySQL.
 */
function getSubjectCatalog(): array
{
    return [
        ['id' => 1, 'name' => 'IT & Portal Support',   'code' => 'IT-01',  'sla' => '24 hrs'],
        ['id' => 2, 'name' => 'Finance & Fee Queries', 'code' => 'FIN-02', 'sla' => '48 hrs'],
        ['id' => 3, 'name' => 'Academics & Exams',     'code' => 'ACA-03', 'sla' => '48 hrs'],
        ['id' => 4, 'name' => 'Library Services',      'code' => 'LIB-04', 'sla' => '24 hrs'],
        ['id' => 5, 'name' => 'Hostels & Facilities',  'code' => 'FAC-05', 'sla' => '72 hrs'],
    ];
}

/**
 * Writes audit events to both logs/activity.log (PHP file handling)
 * and the MySQL `activity_logs` table.
 */
function logActivity(PDO $pdo, ?int $userId, string $action, string $details): bool
{
    $ip        = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $timestamp = date('Y-m-d H:i:s');
    $logDir    = dirname(LOG_FILE);

    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    // 1. Append to text log file using fopen() + fwrite()
    $line = sprintf("[%s] [IP:%s] [USER:%s] [%s] %s%s",
        $timestamp,
        $ip,
        $userId !== null ? (string) $userId : 'GUEST',
        strtoupper($action),
        $details,
        PHP_EOL
    );

    $handle = @fopen(LOG_FILE, 'a');
    if ($handle !== false) {
        fwrite($handle, $line);
        fclose($handle);
    }

    // 2. Record in relational activity_logs table
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO activity_logs (user_id, action, details, ip_address)
             VALUES (:user_id, :action, :details, :ip)'
        );
        return $stmt->execute([
            ':user_id' => $userId,
            ':action'  => strtoupper($action),
            ':details' => substr($details, 0, 255),
            ':ip'      => $ip,
        ]);
    } catch (PDOException $e) {
        error_log('Activity DB log failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Streams an array of submission rows as a downloadable CSV file.
 */
function exportSubmissionsToCSV(array $rows, string $filename = 'hudumacampus_submissions.csv'): never
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . basename($filename) . '"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Sender Name', 'Sender Email', 'Category', 'Subject', 'Message', 'Status', 'Created At']);

    foreach ($rows as $row) {
        fputcsv($output, [
            $row['id'],
            $row['sender_name'],
            $row['sender_email'],
            $row['category_name'] ?? '',
            $row['subject'],
            $row['message'],
            $row['status'],
            $row['created_at'],
        ]);
    }

    fclose($output);
    exit;
}

/**
 * Reads the current UI theme from $_COOKIE (defaults to 'light').
 */
function getCurrentTheme(): string
{
    $allowed = ['light', 'dark'];
    $theme   = $_COOKIE['hc_theme'] ?? 'light';
    return in_array($theme, $allowed, true) ? $theme : 'light';
}