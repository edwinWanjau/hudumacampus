<?php
require_once dirname(__DIR__) . '/includes/functions.php';
initSession();

if (isLoggedIn()) {
    $pdo = getDBConnection();
    logActivity($pdo, (int) $_SESSION['user_id'], 'LOGOUT', 'User logged out securely');
}

// Clear all session variables
$_SESSION = [];

// Delete the PHP session cookie
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();
redirect('/pages/login.php?logged_out=1');