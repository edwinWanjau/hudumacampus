<?php
require_once dirname(__DIR__) . '/includes/functions.php';
initSession();

if (isLoggedIn()) {
    redirect('/pages/dashboard.php');
}

$errors = [];
$old    = ['username' => '', 'email' => '', 'full_name' => '', 'reg_number' => '', 'department' => 'Computer Science'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors['csrf'] = 'Invalid security token. Please refresh and try again.';
    } else {
        $parsed = parseAndValidateForm($_POST, [
            'username'         => [
                'label'       => 'Username',
                'required'    => true,
                'min'         => 3,
                'max'         => 50,
                'pattern'     => '/^[a-zA-Z0-9_]+$/',
                'pattern_msg' => 'Username may only contain letters, numbers, and underscores.',
            ],
            'full_name'        => ['label' => 'Full Name', 'required' => true, 'min' => 2, 'max' => 100],
            'email'            => ['label' => 'Email', 'required' => true, 'email' => true, 'max' => 120],
            'reg_number'       => ['label' => 'Registration Number', 'required' => false, 'max' => 40],
            'department'       => ['label' => 'Department', 'required' => true, 'max' => 80],
            'password'         => ['label' => 'Password', 'required' => true, 'min' => 6, 'max' => 72, 'raw' => true],
            'confirm_password' => ['label' => 'Confirm Password', 'required' => true, 'match' => 'password', 'raw' => true],
        ]);

        $old    = array_merge($old, $parsed['data']);
        $errors = $parsed['errors'];

        if (empty($errors)) {
            $pdo = getDBConnection();

            // Check uniqueness of username and email
            $check = $pdo->prepare('SELECT id, username, email FROM users WHERE username = :username OR email = :email LIMIT 1');
            $check->execute([
                ':username' => $parsed['data']['username'],
                ':email'    => strtolower($parsed['data']['email']),
            ]);
            $existing = $check->fetch();

            if ($existing) {
                if (strcasecmp($existing['username'], $parsed['data']['username']) === 0) {
                    $errors['username'] = 'That username is already taken.';
                }
                if (strcasecmp($existing['email'], $parsed['data']['email']) === 0) {
                    $errors['email'] = 'An account with this email already exists.';
                }
            } else {
                $hash = password_hash($parsed['data']['password'], PASSWORD_DEFAULT);

                $insert = $pdo->prepare(
                    'INSERT INTO users (username, email, password_hash, full_name, reg_number, department)
                     VALUES (:username, :email, :password_hash, :full_name, :reg_number, :department)'
                );
                $insert->execute([
                    ':username'      => $parsed['data']['username'],
                    ':email'         => strtolower($parsed['data']['email']),
                    ':password_hash' => $hash,
                    ':full_name'     => ucwords(strtolower($parsed['data']['full_name'])),
                    ':reg_number'    => strtoupper($parsed['data']['reg_number']) ?: null,
                    ':department'    => $parsed['data']['department'],
                ]);

                $newUserId = (int) $pdo->lastInsertId();
                logActivity($pdo, $newUserId, 'REGISTER', 'New account registered: ' . $parsed['data']['username']);

                setFlash('success', 'Registration successful! You can now log in.');
                redirect('/pages/login.php');
            }
        }
    }
}

$pageTitle = 'Register';
$pageDesc  = 'Create your HudumaCampus student helpdesk account to submit and track campus inquiries.';
require_once dirname(__DIR__) . '/includes/header.php';
?>
<section class="auth-card card">
    <h1>Create Student Account</h1>
    <p class="muted">Register with your student email to access the HudumaCampus portal.</p>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <ul>
                <?php foreach ($errors as $msg): ?>
                    <li><?= e($msg) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= e(BASE_URL) ?>/pages/register.php" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">

        <div class="form-group">
            <label for="username">Username *</label>
            <input type="text" id="username" name="username" value="<?= e($old['username']) ?>" required>
        </div>

        <div class="form-group">
            <label for="full_name">Full Name *</label>
            <input type="text" id="full_name" name="full_name" value="<?= e($old['full_name']) ?>" required>
        </div>

        <div class="form-group">
            <label for="email">Email Address *</label>
            <input type="email" id="email" name="email" value="<?= e($old['email']) ?>" required>
        </div>

        <div class="grid-2">
            <div class="form-group">
                <label for="reg_number">Student Reg. No.</label>
                <input type="text" id="reg_number" name="reg_number" placeholder="e.g. BCS-01-0123/2025" value="<?= e($old['reg_number']) ?>">
            </div>
            <div class="form-group">
                <label for="department">Department *</label>
                <input type="text" id="department" name="department" value="<?= e($old['department']) ?>" required>
            </div>
        </div>

        <div class="grid-2">
            <div class="form-group">
                <label for="password">Password (min 6 chars) *</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password *</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-block">Register Account</button>
    </form>

    <p class="auth-footer">Already registered? <a href="<?= e(BASE_URL) ?>/pages/login.php">Sign in here</a>.</p>
</section>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
