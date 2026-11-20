<?php
/**
 * Shared Header Include - SEO, Open Graph & Semantic HTML5 Structure
 */
require_once __DIR__ . '/functions.php';
initSession();

$pageTitle = isset($pageTitle) ? $pageTitle . ' | ' . APP_NAME : APP_NAME . ' - ' . APP_TAGLINE;
$pageDesc  = $pageDesc ?? 'HudumaCampus is a secure campus student helpdesk portal for submitting, tracking, and resolving university inquiries.';
$theme     = getCurrentTheme();
$scheme    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host      = $_SERVER['HTTP_HOST'] ?? 'localhost';
$ogImage   = $scheme . '://' . $host . BASE_URL . '/assets/img/logo.svg';
$flash     = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($pageDesc) ?>">

    <!-- Open Graph / Social Media Optimisation -->
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($pageDesc) ?>">
    <meta property="og:image" content="<?= e($ogImage) ?>">
    <meta property="og:type" content="website">

    <link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/style.css">
</head>
<body data-theme="<?= e($theme) ?>">
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="<?= e(BASE_URL) ?>/<?= isLoggedIn() ? 'pages/dashboard.php' : 'index.php' ?>">
            <img src="<?= e(BASE_URL) ?>/assets/img/logo.svg" alt="HudumaCampus Helpdesk Logo" width="36" height="36">
            <div>
                <span class="brand-name"><?= e(APP_NAME) ?></span>
                <small class="brand-sub">Student Helpdesk Portal</small>
            </div>
        </a>
        <?php require __DIR__ . '/nav.php'; ?>
    </div>
</header>

<main class="container main-content">
    <?php if ($flash !== null): ?>
        <div class="alert alert-<?= e($flash['type']) ?>" role="alert">
            <?= e($flash['message']) ?>
        </div>
    <?php endif; ?>