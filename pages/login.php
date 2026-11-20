<?php
require_once dirname(__DIR__) . '/includes/functions.php';
initSession();

if (isLoggedIn()) {
    redirect('/pages/dashboard.php');
}

$errors           = [];
$rememberedUser   = $_COOKIE['hc_remember_user'] ?? '';
$lastLoginCookie  = $_COOKIE['hc_last_login'] ?? '';
$identifierInput  = $rememberedUser;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $parsed = parseAndValidateForm($_POST, [
            'identifier' => ['label' => 'Username or Email', 'required' => true],
            'password'   => ['label' => 'Password', 'required' => true, 'raw' => true],
        ]);

        $identifierInput = $parsed['data']['identifier'];
        $errors          = array_values($parsed['errors']);

        if (empty($errors)) {
            $pdo  = getDBConnection();
            $stmt = $pdo->prepare(
                'SELECT id, username, email, password_hash, full_name
                 FROM users
                 WHERE username = :user_id OR email = :email_id
                 LIMIT 1'
            );
            $stmt->execute([
                ':user_id'  => $identifierInput,
                ':email_id' => strtolower($identifierInput),
            ]);
            $user = $stmt->fetch();

            if ($user && password_verify($parsed['data']['password'], $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id']   = (int) $user['id'];
                $_SESSION['username']  = $user['username'];
                $_SESSION['full_name'] = $user['full_name'] ?: $user['username'];

                // Cookie Requirement: store ONLY non-sensitive preference/audit values
                $cookieOpts = [
                    'expires'  => time() + (86400 * 30),
                    'path'     => '/',
                    'httponly' => true,
                    'samesite' => 'Lax',
                ];

                if (!empty($_POST['remember_me'])) {
                    setcookie('hc_remember_user', $user['username'], $cookieOpts);
                } else {
                    setcookie('hc_remember_user', '', time() - 3600, '/');
                }

                setcookie('hc_last_login', date('Y-m-d H:i:s'), $cookieOpts);

                logActivity($pdo, (int) $user['id'], 'LOGIN', 'User logged in successfully');
                setFlash('success', 'Welcome back, ' . $user['username'] . '!');
                redirect('/pages/dashboard.php');
            } else {
                $errors[] = 'Invalid username/email or password.';
            }
        }
    }
}

$pageTitle = 'Login';
$pageDesc  = 'Sign in to HudumaCampus to manage your student helpdesk submissions and profile.';
require_once dirname(__DIR__) . '/includes/header.php';
?>
<section class="auth-card card">
    <h1>Sign In to <?= e(APP_NAME) ?></h1>

    <?php if (isset($_GET['logged_out']) && $_GET['logged_out'] === '1'): ?>
        <div class="alert alert-success">You have been securely logged out.</div>
    <?php endif; ?>

    <?php if ($lastLoginCookie !== ''): ?>
        <p class="muted small">Last login from this browser: <strong><?= e($lastLoginCookie) ?></strong></p>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <ul>
                <?php foreach ($errors as $msg): ?>
                    <li><?= e($msg) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= e(BASE_URL) ?>/pages/login.php" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">

        <div class="form-group">
            <label for="identifier">Username or Email</label>
            <input type="text" id="identifier" name="identifier" value="<?= e($identifierInput) ?>" required>
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
        </div>

        <div class="form-group checkbox-group">
            <label>
                <input type="checkbox" name="remember_me" value="1" <?= $rememberedUser !== '' ? 'checked' : '' ?>>
                Remember my username on this device (non-sensitive cookie)
            </label>
        </div>

        <button type="submit" class="btn btn-primary btn-block">Login</button>
    </form>

    <p class="auth-footer">
        New student? <a href="<?= e(BASE_URL) ?>/pages/register.php">Create an account</a>.<br>
        <small class="muted">Demo account: <code>amina_k</code> / Password: <code>password</code></small>
    </p>
</section>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>