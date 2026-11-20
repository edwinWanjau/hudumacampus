<?php
/**
 * Shared Navigation Include
 */
$currentFile = basename($_SERVER['PHP_SELF']);
?>
<nav class="site-nav" aria-label="Primary Navigation">
    <ul>
        <?php if (isLoggedIn()): ?>
            <li><a href="<?= e(BASE_URL) ?>/pages/dashboard.php" class="<?= $currentFile === 'dashboard.php' ? 'active' : '' ?>">Dashboard</a></li>
            <li><a href="<?= e(BASE_URL) ?>/pages/contact.php" class="<?= $currentFile === 'contact.php' ? 'active' : '' ?>">Contact Us</a></li>
            <li><a href="<?= e(BASE_URL) ?>/pages/submissions.php" class="<?= $currentFile === 'submissions.php' ? 'active' : '' ?>">Submissions</a></li>
            <li><a href="<?= e(BASE_URL) ?>/pages/info_hub.php" class="<?= $currentFile === 'info_hub.php' ? 'active' : '' ?>">Info Hub</a></li>
            <li><a href="<?= e(BASE_URL) ?>/pages/profile.php" class="<?= $currentFile === 'profile.php' ? 'active' : '' ?>">Profile</a></li>
            <li><a href="<?= e(BASE_URL) ?>/pages/logout.php" class="nav-logout">Logout (<?= e($_SESSION['username']) ?>)</a></li>
        <?php else: ?>
            <li><a href="<?= e(BASE_URL) ?>/index.php" class="<?= $currentFile === 'index.php' ? 'active' : '' ?>">Home</a></li>
            <li><a href="<?= e(BASE_URL) ?>/pages/login.php" class="<?= $currentFile === 'login.php' ? 'active' : '' ?>">Login</a></li>
            <li><a href="<?= e(BASE_URL) ?>/pages/register.php" class="<?= $currentFile === 'register.php' ? 'active' : '' ?>">Register</a></li>
        <?php endif; ?>
    </ul>
</nav>