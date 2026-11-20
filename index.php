<?php
require_once __DIR__ . '/includes/functions.php';
initSession();

if (isLoggedIn()) {
    redirect('/pages/dashboard.php');
}

$pageTitle = 'Welcome';
$pageDesc  = 'Submit, track, and resolve campus IT, finance, library, and academic inquiries online with HudumaCampus.';
require_once __DIR__ . '/includes/header.php';
?>
<section class="hero card">
    <img src="<?= e(BASE_URL) ?>/assets/img/logo.svg" alt="HudumaCampus Portal Emblem" width="64" height="64">
    <h1>Welcome to <?= e(APP_NAME) ?></h1>
    <p class="lead">One central campus helpdesk for IT support, fee queries, academic records, library services, and facilities maintenance.</p>
    <div class="btn-group">
        <a href="<?= e(BASE_URL) ?>/pages/login.php" class="btn btn-primary">Student Login</a>
        <a href="<?= e(BASE_URL) ?>/pages/register.php" class="btn btn-secondary">Create Account</a>
    </div>
</section>

<section class="grid-3">
    <article class="card">
        <h2>Fast Inquiry Tracking</h2>
        <p>Log inquiries directly to the right campus department and monitor status updates in real time.</p>
    </article>
    <article class="card">
        <h2>Search &amp; CSV Export</h2>
        <p>Filter past submissions by keyword or category and export clean CSV summaries anytime.</p>
    </article>
    <article class="card">
        <h2>Secure by Design</h2>
        <p>Built with bcrypt password hashing, PDO prepared statements, CSRF tokens, and XSS output escaping.</p>
    </article>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>