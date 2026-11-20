<?php
require_once dirname(__DIR__) . '/includes/functions.php';
requireLogin();

$pdo    = getDBConnection();
$userId = (int) $_SESSION['user_id'];

// Aggregate query 1: Submission totals and latest timestamp
$subStats = $pdo->query(
    "SELECT
        COUNT(id) AS total_submissions,
        COALESCE(SUM(CASE WHEN status = 'Open' THEN 1 ELSE 0 END), 0) AS open_count,
        COALESCE(SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END), 0) AS progress_count,
        COALESCE(SUM(CASE WHEN status = 'Resolved' THEN 1 ELSE 0 END), 0) AS resolved_count,
        MAX(created_at) AS latest_submission
     FROM submissions"
)->fetch();

// Aggregate query 2: Total registered users
$userCount = (int) $pdo->query('SELECT COUNT(id) FROM users')->fetchColumn();

// Aggregate query 3: Logged-in user's own submissions
$myStmt = $pdo->prepare('SELECT COUNT(id) FROM submissions WHERE user_id = :uid');
$myStmt->execute([':uid' => $userId]);
$mySubmissions = (int) $myStmt->fetchColumn();

// Complex query: JOIN + CONCAT() + ORDER BY + LIMIT (no SELECT *)
$recentStmt = $pdo->query(
    "SELECT
        s.id,
        CONCAT('#', s.id, ' - ', s.subject) AS ticket_label,
        s.sender_name,
        c.name AS category_name,
        s.status,
        s.created_at
     FROM submissions s
     INNER JOIN categories c ON s.category_id = c.id
     ORDER BY s.created_at DESC
     LIMIT 5"
);
$recentTickets = $recentStmt->fetchAll();

// Indexed array rendered via foreach loop
$quickActions = [
    ['label' => 'Submit New Inquiry', 'url' => '/pages/contact.php',     'style' => 'btn-primary'],
    ['label' => 'Browse Directory',   'url' => '/pages/submissions.php', 'style' => 'btn-secondary'],
    ['label' => 'Reports & Tools',    'url' => '/pages/info_hub.php',    'style' => 'btn-secondary'],
    ['label' => 'Update My Profile',  'url' => '/pages/profile.php',     'style' => 'btn-secondary'],
];

$pageTitle = 'Dashboard';
$pageDesc  = 'HudumaCampus student dashboard showing live helpdesk statistics and recent campus inquiries.';
require_once dirname(__DIR__) . '/includes/header.php';
?>
<section class="page-header card">
    <div>
        <h1>Welcome, <?= e($_SESSION['username']) ?></h1>
        <p class="muted">
            Today is <strong><?= e(date('l, j F Y')) ?></strong>
            &bull; Server Time: <?= e(date('H:i')) ?>
            <?php if (!empty($_COOKIE['hc_last_login'])): ?>
                &bull; Last Login: <?= e($_COOKIE['hc_last_login']) ?>
            <?php endif; ?>
        </p>
    </div>
    <div class="btn-group">
        <?php foreach ($quickActions as $action): ?>
            <a href="<?= e(BASE_URL . $action['url']) ?>" class="btn <?= e($action['style']) ?>">
                <?= e($action['label']) ?>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="stats-grid" aria-label="Helpdesk Summary Statistics">
    <article class="stat-card card">
        <span class="stat-label">Total Submissions</span>
        <strong class="stat-value"><?= (int) $subStats['total_submissions'] ?></strong>
        <small class="muted"><?= $mySubmissions ?> submitted by you</small>
    </article>

    <article class="stat-card card">
        <span class="stat-label">Registered Students</span>
        <strong class="stat-value"><?= $userCount ?></strong>
        <small class="muted">Active portal accounts</small>
    </article>

    <article class="stat-card card">
        <span class="stat-label">Open / In Progress</span>
        <strong class="stat-value"><?= (int) $subStats['open_count'] ?> / <?= (int) $subStats['progress_count'] ?></strong>
        <small class="muted"><?= (int) $subStats['resolved_count'] ?> resolved</small>
    </article>

    <article class="stat-card card">
        <span class="stat-label">Latest Inquiry Date</span>
        <strong class="stat-value stat-date">
            <?= $subStats['latest_submission'] ? e(date('d M Y, H:i', strtotime($subStats['latest_submission']))) : 'None yet' ?>
        </strong>
        <small class="muted">Most recent ticket</small>
    </article>
</section>

<section class="card">
    <div class="section-head">
        <h2>Latest 5 Helpdesk Submissions</h2>
        <a href="<?= e(BASE_URL) ?>/pages/submissions.php" class="link">View all submissions &rarr;</a>
    </div>

    <?php if (empty($recentTickets)): ?>
        <p class="muted">No submissions recorded yet. <a href="<?= e(BASE_URL) ?>/pages/contact.php">Create the first inquiry</a>.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Ticket</th>
                        <th>Submitted By</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentTickets as $ticket): ?>
                        <?php
                        switch ($ticket['status']) {
                            case 'Resolved':
                                $badgeClass = 'badge-resolved';
                                break;
                            case 'In Progress':
                                $badgeClass = 'badge-progress';
                                break;
                            default:
                                $badgeClass = 'badge-open';
                                break;
                        }
                        ?>
                        <tr>
                            <td><strong><?= e($ticket['ticket_label']) ?></strong></td>
                            <td><?= e($ticket['sender_name']) ?></td>
                            <td><?= e($ticket['category_name']) ?></td>
                            <td><span class="badge <?= e($badgeClass) ?>"><?= e($ticket['status']) ?></span></td>
                            <td><?= e(date('d M Y H:i', strtotime($ticket['created_at']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>