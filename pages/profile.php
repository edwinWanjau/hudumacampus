<?php
require_once dirname(__DIR__) . '/includes/functions.php';
requireLogin();

$pdo    = getDBConnection();
$userId = (int) $_SESSION['user_id'];
$errors = [];

$stmt = $pdo->prepare(
    'SELECT id, username, email, full_name, reg_number, department, created_at
     FROM users
     WHERE id = :id
     LIMIT 1'
);
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch();

if (!$user) {
    redirect('/pages/logout.php');
}

$countStmt = $pdo->prepare('SELECT COUNT(id) FROM submissions WHERE user_id = :uid');
$countStmt->execute([':uid' => $userId]);
$userTicketCount = (int) $countStmt->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors['csrf'] = 'Invalid security token. Please refresh and try again.';
    } else {
        $parsed = parseAndValidateForm($_POST, [
            'full_name'  => ['label' => 'Full Name', 'required' => true, 'min' => 2, 'max' => 100],
            'email'      => ['label' => 'Email Address', 'required' => true, 'email' => true, 'max' => 120],
            'reg_number' => ['label' => 'Registration Number', 'required' => false, 'max' => 40],
            'department' => ['label' => 'Department', 'required' => true, 'min' => 2, 'max' => 80],
        ]);

        $errors = $parsed['errors'];

        if (empty($errors)) {
            $newEmail = strtolower($parsed['data']['email']);

            $dup = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id != :id LIMIT 1');
            $dup->execute([':email' => $newEmail, ':id' => $userId]);

            if ($dup->fetch()) {
                $errors['email'] = 'That email address is already linked to another account.';
            } else {
                $formattedName = ucwords(strtolower($parsed['data']['full_name']));
                $update = $pdo->prepare(
                    'UPDATE users
                     SET full_name = :full_name,
                         email = :email,
                         reg_number = :reg_number,
                         department = :department
                     WHERE id = :id'
                );
                $update->execute([
                    ':full_name'  => $formattedName,
                    ':email'      => $newEmail,
                    ':reg_number' => strtoupper($parsed['data']['reg_number']) ?: null,
                    ':department' => $parsed['data']['department'],
                    ':id'         => $userId,
                ]);

                $_SESSION['full_name'] = $formattedName;
                logActivity($pdo, $userId, 'UPDATE_PROFILE', 'Updated student profile details');

                setFlash('success', 'Your profile has been updated successfully.');
                redirect('/pages/profile.php');
            }
        }

        $user = array_merge($user, $parsed['data']);
    }
}

$pageTitle = 'My Profile';
$pageDesc  = 'View and update your HudumaCampus student account details.';
require_once dirname(__DIR__) . '/includes/header.php';
?>
<section class="grid-2">
    <article class="card">
        <h1>Student Profile</h1>
        <p class="muted">Account information pulled directly from the MySQL database.</p>

        <dl class="profile-list">
            <div><dt>Username</dt><dd><code><?= e($user['username']) ?></code></dd></div>
            <div><dt>Full Name</dt><dd><?= e($user['full_name']) ?></dd></div>
            <div><dt>Email</dt><dd><?= e($user['email']) ?></dd></div>
            <div><dt>Reg. Number</dt><dd><?= e($user['reg_number'] ?? 'Not provided') ?></dd></div>
            <div><dt>Department</dt><dd><?= e($user['department']) ?></dd></div>
            <div><dt>Member Since</dt><dd><?= e(date('d M Y, H:i', strtotime($user['created_at']))) ?></dd></div>
            <div><dt>Inquiries Logged</dt><dd><strong><?= $userTicketCount ?></strong></dd></div>
        </dl>
    </article>

    <article class="card">
        <h2>Update Profile Details</h2>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $msg): ?>
                        <li><?= e($msg) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= e(BASE_URL) ?>/pages/profile.php" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">

            <div class="form-group">
                <label for="full_name">Full Name *</label>
                <input type="text" id="full_name" name="full_name" value="<?= e($user['full_name']) ?>" required>
            </div>

            <div class="form-group">
                <label for="email">Email Address *</label>
                <input type="email" id="email" name="email" value="<?= e($user['email']) ?>" required>
            </div>

            <div class="form-group">
                <label for="reg_number">Student Registration Number</label>
                <input type="text" id="reg_number" name="reg_number" value="<?= e($user['reg_number'] ?? '') ?>" placeholder="e.g. BCS-01-0101/2024">
            </div>

            <div class="form-group">
                <label for="department">Department *</label>
                <input type="text" id="department" name="department" value="<?= e($user['department']) ?>" required>
            </div>

            <button type="submit" class="btn btn-primary">Save Changes</button>
        </form>
    </article>
</section>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>