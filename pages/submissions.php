<?php
require_once dirname(__DIR__) . '/includes/functions.php';
requireLogin();

$pdo             = getDBConnection();
$userId          = (int) $_SESSION['user_id'];
$catalog         = getSubjectCatalog();
$allowedStatuses = ['Open', 'In Progress', 'Resolved'];
$allowedCatIds   = array_map(static function (array $c): string {
    return (string) $c['id'];
}, $catalog);

// 1. Handle POST CRUD Operations (UPDATE & DELETE)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Invalid security token. Action cancelled.');
        redirect('/pages/submissions.php');
    }

    $action = $_POST['action'] ?? '';
    $recId  = isset($_POST['id']) ? (int) $_POST['id'] : 0;

    if ($action === 'delete' && $recId > 0) {
        $del = $pdo->prepare('DELETE FROM submissions WHERE id = :id');
        $del->execute([':id' => $recId]);
        logActivity($pdo, $userId, 'DELETE_INQUIRY', 'Deleted submission #' . $recId);
        setFlash('success', 'Submission #' . $recId . ' was deleted.');
        redirect('/pages/submissions.php');
    }

    if ($action === 'update' && $recId > 0) {
        $parsed = parseAndValidateForm($_POST, [
            'category_id' => ['label' => 'Subject', 'required' => true, 'in' => $allowedCatIds],
            'status'      => ['label' => 'Status', 'required' => true, 'in' => $allowedStatuses],
            'message'     => ['label' => 'Message', 'required' => true, 'min' => 10, 'max' => 2000],
        ]);

        if (!empty($parsed['errors'])) {
            setFlash('error', implode(' ', array_values($parsed['errors'])));
            redirect('/pages/submissions.php?edit_id=' . $recId);
        }

        $catId       = (int) $parsed['data']['category_id'];
        $subjectName = 'General Inquiry';
        foreach ($catalog as $c) {
            if ($c['id'] === $catId) {
                $subjectName = $c['name'];
                break;
            }
        }

        $upd = $pdo->prepare(
            'UPDATE submissions
             SET category_id = :category_id,
                 subject = :subject,
                 status = :status,
                 message = :message
             WHERE id = :id'
        );
        $upd->execute([
            ':category_id' => $catId,
            ':subject'     => $subjectName,
            ':status'      => $parsed['data']['status'],
            ':message'     => $parsed['data']['message'],
            ':id'          => $recId,
        ]);

        logActivity($pdo, $userId, 'UPDATE_INQUIRY', 'Updated submission #' . $recId . ' (' . $parsed['data']['status'] . ')');
        setFlash('success', 'Submission #' . $recId . ' updated successfully.');
        redirect('/pages/submissions.php');
    }
}

// 2. Read GET Parameters for Search, Filtering, Sorting & Pagination
$search     = sanitizeInput($_GET['q'] ?? '');
$catFilter  = isset($_GET['category']) && ctype_digit($_GET['category']) ? (int) $_GET['category'] : 0;
$statFilter = isset($_GET['status']) && in_array($_GET['status'], $allowedStatuses, true) ? $_GET['status'] : '';
$sortKey    = $_GET['sort'] ?? 'newest';
$page       = isset($_GET['page']) && ctype_digit($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

$orderMap = [
    'newest'  => 's.created_at DESC',
    'oldest'  => 's.created_at ASC',
    'subject' => 's.subject ASC, s.created_at DESC',
    'status'  => 's.status ASC, s.created_at DESC',
];
$orderBy = $orderMap[$sortKey] ?? $orderMap['newest'];

$conditions = [];
$params     = [];

if ($search !== '') {
    $likeValue    = '%' . $search . '%';
    $conditions[] = '(s.sender_name LIKE :q1 OR s.sender_email LIKE :q2 OR s.subject LIKE :q3 OR s.message LIKE :q4)';
    $params[':q1'] = $likeValue;
    $params[':q2'] = $likeValue;
    $params[':q3'] = $likeValue;
    $params[':q4'] = $likeValue;
}

if ($catFilter > 0) {
    $conditions[]      = 's.category_id = :cat_id';
    $params[':cat_id'] = $catFilter;
}

if ($statFilter !== '') {
    $conditions[]      = 's.status = :status';
    $params[':status'] = $statFilter;
}

$whereSql = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// 3. Export to CSV (File Handling) - Must execute before header.php
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $csvSql = "SELECT s.id, s.sender_name, s.sender_email, c.name AS category_name,
                      s.subject, s.message, s.status, s.created_at
               FROM submissions s
               INNER JOIN categories c ON s.category_id = c.id
               {$whereSql}
               ORDER BY {$orderBy}";
    $csvStmt = $pdo->prepare($csvSql);
    $csvStmt->execute($params);
    $exportRows = $csvStmt->fetchAll();

    logActivity($pdo, $userId, 'EXPORT_CSV', 'Exported ' . count($exportRows) . ' submissions to CSV');
    exportSubmissionsToCSV($exportRows, 'hudumacampus_submissions_' . date('Ymd_His') . '.csv');
}

// 4. Pagination Count + Paginated Query (LIMIT & OFFSET)
$countSql  = "SELECT COUNT(s.id) FROM submissions s {$whereSql}";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRows  = (int) $countStmt->fetchColumn();
$perPage    = ITEMS_PER_PAGE;
$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$dataSql = "SELECT s.id, s.category_id, s.sender_name, s.sender_email,
                   c.name AS category_name, s.subject, s.message, s.status, s.created_at
            FROM submissions s
            INNER JOIN categories c ON s.category_id = c.id
            {$whereSql}
            ORDER BY {$orderBy}
            LIMIT :limit OFFSET :offset";

$dataStmt = $pdo->prepare($dataSql);
foreach ($params as $key => $val) {
    $dataStmt->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$dataStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$dataStmt->execute();

// Optional Edit Record Fetch
$editRecord = null;
if (isset($_GET['edit_id']) && ctype_digit($_GET['edit_id'])) {
    $editStmt = $pdo->prepare(
        'SELECT id, category_id, sender_name, subject, message, status
         FROM submissions
         WHERE id = :id
         LIMIT 1'
    );
    $editStmt->execute([':id' => (int) $_GET['edit_id']]);
    $editRecord = $editStmt->fetch() ?: null;
}

$baseQuery = array_filter([
    'q'        => $search,
    'category' => $catFilter > 0 ? (string) $catFilter : '',
    'status'   => $statFilter,
    'sort'     => $sortKey !== 'newest' ? $sortKey : '',
], static function (string $v): bool {
    return $v !== '';
});

$exportUrl = BASE_URL . '/pages/submissions.php?' . http_build_query(array_merge($baseQuery, ['export' => 'csv']));

$pageTitle = 'Submissions Directory';
$pageDesc  = 'Search, filter, edit, delete, and export campus helpdesk submissions on HudumaCampus.';
require_once dirname(__DIR__) . '/includes/header.php';
?>
<section class="page-header card">
    <div>
        <h1>Submissions Directory</h1>
        <p class="muted">Showing <strong><?= $totalRows ?></strong> matching record(s) &bull; Page <?= $page ?> of <?= $totalPages ?></p>
    </div>
    <div class="btn-group">
        <a href="<?= e(BASE_URL) ?>/pages/contact.php" class="btn btn-primary">+ New Inquiry</a>
        <a href="<?= e($exportUrl) ?>" class="btn btn-secondary">Export CSV</a>
    </div>
</section>

<?php if ($editRecord !== null): ?>
<section class="card edit-panel">
    <h2>Edit Submission #<?= (int) $editRecord['id'] ?> (<?= e($editRecord['sender_name']) ?>)</h2>
    <form method="POST" action="<?= e(BASE_URL) ?>/pages/submissions.php">
        <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" value="<?= (int) $editRecord['id'] ?>">

        <div class="grid-2">
            <div class="form-group">
                <label for="edit_category">Subject / Department</label>
                <select id="edit_category" name="category_id" required>
                    <?php foreach ($catalog as $item): ?>
                        <option value="<?= (int) $item['id'] ?>" <?= (int) $editRecord['category_id'] === $item['id'] ? 'selected' : '' ?>>
                            <?= e($item['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="edit_status">Ticket Status</label>
                <select id="edit_status" name="status" required>
                    <?php foreach ($allowedStatuses as $st): ?>
                        <option value="<?= e($st) ?>" <?= $editRecord['status'] === $st ? 'selected' : '' ?>>
                            <?= e($st) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="edit_message">Message</label>
            <textarea id="edit_message" name="message" rows="3" required><?= e($editRecord['message']) ?></textarea>
        </div>

        <div class="btn-group">
            <button type="submit" class="btn btn-primary">Update Record</button>
            <a href="<?= e(BASE_URL) ?>/pages/submissions.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</section>
<?php endif; ?>

<section class="card">
    <form method="GET" action="<?= e(BASE_URL) ?>/pages/submissions.php" class="filter-bar">
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search name, email, subject, or message...">

        <select name="category" aria-label="Filter by Department">
            <option value="">All Departments</option>
            <?php foreach ($catalog as $item): ?>
                <option value="<?= (int) $item['id'] ?>" <?= $catFilter === $item['id'] ? 'selected' : '' ?>>
                    <?= e($item['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="status" aria-label="Filter by Status">
            <option value="">All Statuses</option>
            <?php foreach ($allowedStatuses as $st): ?>
                <option value="<?= e($st) ?>" <?= $statFilter === $st ? 'selected' : '' ?>>
                    <?= e($st) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="sort" aria-label="Sort Results">
            <option value="newest" <?= $sortKey === 'newest' ? 'selected' : '' ?>>Newest First</option>
            <option value="oldest" <?= $sortKey === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
            <option value="subject" <?= $sortKey === 'subject' ? 'selected' : '' ?>>By Subject (A-Z)</option>
            <option value="status" <?= $sortKey === 'status' ? 'selected' : '' ?>>By Status</option>
        </select>

        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="<?= e(BASE_URL) ?>/pages/submissions.php" class="btn btn-secondary">Reset</a>
    </form>

    <?php if ($totalRows === 0): ?>
        <p class="muted" style="margin-top: 1rem;">No submissions matched your search criteria.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Sender</th>
                        <th>Subject</th>
                        <th>Message</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $dataStmt->fetch()): ?>
                        <?php
                        switch ($row['status']) {
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
                        $preview = strlen($row['message']) > 80
                            ? substr($row['message'], 0, 80) . '...'
                            : $row['message'];
                        ?>
                        <tr>
                            <td>#<?= (int) $row['id'] ?></td>
                            <td>
                                <strong><?= e($row['sender_name']) ?></strong><br>
                                <small class="muted"><?= e($row['sender_email']) ?></small>
                            </td>
                            <td><?= e($row['subject']) ?></td>
                            <td title="<?= e($row['message']) ?>"><?= e($preview) ?></td>
                            <td><span class="badge <?= e($badgeClass) ?>"><?= e($row['status']) ?></span></td>
                            <td><?= e(date('d M Y', strtotime($row['created_at']))) ?></td>
                            <td class="actions-cell">
                                <a class="btn-sm btn-secondary" href="<?= e(BASE_URL) ?>/pages/submissions.php?edit_id=<?= (int) $row['id'] ?>">Edit</a>
                                <form method="POST" action="<?= e(BASE_URL) ?>/pages/submissions.php" class="inline-form" onsubmit="return confirm('Delete submission #<?= (int) $row['id'] ?>?');">
                                    <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                    <button type="submit" class="btn-sm btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="pagination" aria-label="Submissions Pagination">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php $pageUrl = BASE_URL . '/pages/submissions.php?' . http_build_query(array_merge($baseQuery, ['page' => $i])); ?>
                    <a href="<?= e($pageUrl) ?>" class="page-link <?= $i === $page ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>