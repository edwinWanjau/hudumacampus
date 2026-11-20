<?php
require_once dirname(__DIR__) . '/includes/functions.php';
requireLogin();

$pdo        = getDBConnection();
$userId     = (int) $_SESSION['user_id'];
$catalog    = getSubjectCatalog();
$allowedIds = array_map(static function (array $item): string {
    return (string) $item['id'];
}, $catalog);

$userStmt = $pdo->prepare('SELECT full_name, email FROM users WHERE id = :id LIMIT 1');
$userStmt->execute([':id' => $userId]);
$currentUser = $userStmt->fetch();

$old = [
    'sender_name'  => $currentUser['full_name'] ?? $_SESSION['username'],
    'sender_email' => $currentUser['email'] ?? '',
    'category_id'  => '',
    'message'      => '',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors['csrf'] = 'Invalid security token. Please refresh and resubmit.';
    } else {
        $parsed = parseAndValidateForm($_POST, [
            'sender_name'  => ['label' => 'Name', 'required' => true, 'min' => 2, 'max' => 100],
            'sender_email' => ['label' => 'Email', 'required' => true, 'email' => true, 'max' => 120],
            'category_id'  => ['label' => 'Subject', 'required' => true, 'in' => $allowedIds],
            'message'      => ['label' => 'Message', 'required' => true, 'min' => 10, 'max' => 2000],
        ]);

        $old    = array_merge($old, $parsed['data']);
        $errors = $parsed['errors'];

        if (empty($errors)) {
            $selectedCatId = (int) $parsed['data']['category_id'];
            $subjectName   = 'General Inquiry';

            foreach ($catalog as $item) {
                if ($item['id'] === $selectedCatId) {
                    $subjectName = $item['name'];
                    break;
                }
            }

            $stmt = $pdo->prepare(
                'INSERT INTO submissions (user_id, category_id, sender_name, sender_email, subject, message)
                 VALUES (:user_id, :category_id, :sender_name, :sender_email, :subject, :message)'
            );
            $stmt->execute([
                ':user_id'      => $userId,
                ':category_id'  => $selectedCatId,
                ':sender_name'  => $parsed['data']['sender_name'],
                ':sender_email' => strtolower($parsed['data']['sender_email']),
                ':subject'      => $subjectName,
                ':message'      => $parsed['data']['message'],
            ]);

            logActivity($pdo, $userId, 'SUBMIT_INQUIRY', 'Submitted inquiry under ' . $subjectName);
            setFlash('success', 'Your inquiry has been submitted to ' . $subjectName . '.');
            redirect('/pages/submissions.php');
        }
    }
}

$pageTitle = 'Contact Us';
$pageDesc  = 'Submit a campus IT, finance, academic, library, or facilities helpdesk inquiry on HudumaCampus.';
require_once dirname(__DIR__) . '/includes/header.php';
?>
<section class="grid-2">
    <article class="card">
        <h1>Contact Campus Helpdesk</h1>
        <p class="muted">Fill out the form below to log a support ticket. All entries are stored securely using PDO prepared statements.</p>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $msg): ?>
                        <li><?= e($msg) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= e(BASE_URL) ?>/pages/contact.php" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">

            <div class="form-group">
                <label for="sender_name">Name *</label>
                <input type="text" id="sender_name" name="sender_name" value="<?= e($old['sender_name']) ?>" required>
            </div>

            <div class="form-group">
                <label for="sender_email">Email *</label>
                <input type="email" id="sender_email" name="sender_email" value="<?= e($old['sender_email']) ?>" required>
            </div>

            <div class="form-group">
                <label for="category_id">Subject / Department *</label>
                <select id="category_id" name="category_id" required>
                    <option value="">-- Select a Helpdesk Subject --</option>
                    <?php foreach ($catalog as $item): ?>
                        <option value="<?= (int) $item['id'] ?>" <?= (string) $item['id'] === (string) $old['category_id'] ? 'selected' : '' ?>>
                            <?= e($item['name']) ?> [<?= e($item['code']) ?> &bull; SLA: <?= e($item['sla']) ?>]
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="message">Message (min 10 characters) *</label>
                <textarea id="message" name="message" rows="5" required placeholder="Describe your campus issue clearly..."><?= e($old['message']) ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary">Submit Inquiry</button>
        </form>
    </article>

    <aside class="card">
        <h2>Department Response Guide</h2>
        <p class="muted">Available support desks loaded from our multidimensional PHP subject catalog (<?= count($catalog) ?> desks):</p>
        <ul class="desk-list">
            <?php foreach ($catalog as $desk): ?>
                <li>
                    <strong><?= e($desk['name']) ?></strong>
                    <span class="badge badge-progress"><?= e($desk['code']) ?></span>
                    <br><small class="muted">Target Response SLA: <?= e($desk['sla']) ?></small>
                </li>
            <?php endforeach; ?>
        </ul>
    </aside>
</section>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>