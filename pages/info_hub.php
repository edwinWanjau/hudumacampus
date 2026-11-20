<?php
require_once dirname(__DIR__) . '/includes/functions.php';
requireLogin();

$pdo     = getDBConnection();
$userId  = (int) $_SESSION['user_id'];
$catalog = getSubjectCatalog();

// 1. Handle Theme Cookie Switcher & CSV Import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'set_theme') {
    if (verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $chosen = ($_POST['theme'] ?? 'light') === 'dark' ? 'dark' : 'light';
        setcookie('hc_theme', $chosen, [
            'expires'  => time() + (86400 * 30),
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        logActivity($pdo, $userId, 'THEME_CHANGE', 'Switched interface theme to ' . $chosen);
        setFlash('success', 'Theme preference saved in cookie: ' . ucfirst($chosen) . ' Mode.');
    }
    redirect('/pages/info_hub.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import_csv') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Invalid CSRF token.');
        redirect('/pages/info_hub.php');
    }

    if (empty($_FILES['csv_file']['tmp_name']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        setFlash('error', 'Please select a valid CSV file to import.');
        redirect('/pages/info_hub.php');
    }

    $ext = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));
    if ($ext !== 'csv') {
        setFlash('error', 'Only .csv files are permitted.');
        redirect('/pages/info_hub.php');
    }

    $imported = 0;
    $handle   = fopen($_FILES['csv_file']['tmp_name'], 'r');

    if ($handle !== false) {
        fgetcsv($handle); // Skip header row: Name,Email,CategoryID,Message
        $insert = $pdo->prepare(
            'INSERT INTO submissions (user_id, category_id, sender_name, sender_email, subject, message)
             VALUES (:uid, :cid, :name, :email, :subject, :message)'
        );

        while (($cols = fgetcsv($handle)) !== false) {
            if (count($cols) < 4) {
                continue;
            }
            $sName  = sanitizeInput($cols[0]);
            $sEmail = strtolower(sanitizeInput($cols[1]));
            $catId  = (int) $cols[2];
            $msg    = sanitizeInput($cols[3]);

            if ($sName === '' || !validateEmail($sEmail) || strlen($msg) < 10) {
                continue;
            }

            $subject = null;
            foreach ($catalog as $c) {
                if ($c['id'] === $catId) {
                    $subject = $c['name'];
                    break;
                }
            }
            if ($subject === null) {
                continue;
            }

            $insert->execute([
                ':uid'     => $userId,
                ':cid'     => $catId,
                ':name'    => $sName,
                ':email'   => $sEmail,
                ':subject' => $subject,
                ':message' => $msg,
            ]);
            $imported++;
        }
        fclose($handle);
    }

    logActivity($pdo, $userId, 'IMPORT_CSV', 'Imported ' . $imported . ' inquiry row(s) via fgetcsv()');
    setFlash($imported > 0 ? 'success' : 'error', "CSV import completed: {$imported} valid inquiry row(s) added.");
    redirect('/pages/info_hub.php');
}

// 2. Complex Report Query: LEFT JOIN + GROUP BY + Aggregate Functions
$reportRows = $pdo->query(
    "SELECT
        c.name AS department_name,
        c.desk_email,
        COUNT(s.id) AS total_tickets,
        COALESCE(SUM(CASE WHEN s.status = 'Open' THEN 1 ELSE 0 END), 0) AS open_tickets,
        COALESCE(SUM(CASE WHEN s.status = 'In Progress' THEN 1 ELSE 0 END), 0) AS progress_tickets,
        COALESCE(SUM(CASE WHEN s.status = 'Resolved' THEN 1 ELSE 0 END), 0) AS resolved_tickets,
        MAX(s.created_at) AS last_activity
     FROM categories c
     LEFT JOIN submissions s ON c.id = s.category_id
     GROUP BY c.id, c.name, c.desk_email
     ORDER BY total_tickets DESC, c.name ASC"
)->fetchAll();

// 3. String & Array Processing Tool
$sampleInput = '  huduma campus helpdesk: wifi, fee statement, exam card, wifi, portal login  ';
$findWord    = 'wifi';
$replaceWord = 'campus-wifi';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'string_tool') {
    $sampleInput = $_POST['raw_text'] ?? $sampleInput;
    $findWord    = sanitizeInput($_POST['find_word'] ?? '');
    $replaceWord = sanitizeInput($_POST['replace_word'] ?? '');
}

$trimmed   = trim($sampleInput);
$replaced  = $findWord !== '' ? str_replace($findWord, $replaceWord, $trimmed) : $trimmed;
$rawWords  = explode(' ', preg_replace('/\s+/', ' ', $replaced));
$cleanTags = array_values(array_filter($rawWords, static function (string $w): bool {
    return strlen($w) >= 4;
}));
$sorted = $cleanTags;
sort($sorted, SORT_STRING | SORT_FLAG_CASE);

$stringResults = [
    'trimmed'     => $trimmed,
    'length'      => strlen($trimmed),
    'title_case'  => ucwords(strtolower($trimmed)),
    'preview'     => substr($trimmed, 0, 35) . (strlen($trimmed) > 35 ? '...' : ''),
    'replaced'    => $replaced,
    'word_count'  => count($rawWords),
    'sorted_tags' => implode(' | ', $sorted),
    'concat_note' => 'Processed by ' . $_SESSION['username'] . ' on ' . date('Y-m-d'),
];

// 4. File Handling: Read recent entries from logs/activity.log
$logLines = [];
if (is_readable(LOG_FILE)) {
    $fp = fopen(LOG_FILE, 'r');
    if ($fp !== false) {
        while (($line = fgets($fp)) !== false) {
            $trimmedLine = trim($line);
            if ($trimmedLine !== '') {
                $logLines[] = $trimmedLine;
            }
        }
        fclose($fp);
    }
}
$recentLogs   = array_slice(array_reverse($logLines), 0, 8);
$currentTheme = getCurrentTheme();

$pageTitle = 'Information Hub';
$pageDesc  = 'HudumaCampus analytics report, cookie theme settings, string processing utilities, and activity logs.';
require_once dirname(__DIR__) . '/includes/header.php';
?>
<section class="page-header card">
    <div>
        <h1>Information Hub &amp; Reports</h1>
        <p class="muted">Department analytics (JOIN + GROUP BY), cookie theme settings, PHP string utilities, and file handling tools.</p>
    </div>

    <form method="POST" action="<?= e(BASE_URL) ?>/pages/info_hub.php" class="inline-form">
        <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
        <input type="hidden" name="action" value="set_theme">
        <input type="hidden" name="theme" value="<?= $currentTheme === 'dark' ? 'light' : 'dark' ?>">
        <button type="submit" class="btn btn-secondary">
            Switch to <?= $currentTheme === 'dark' ? 'Light' : 'Dark' ?> Theme (Cookie)
        </button>
    </form>
</section>

<section class="card">
    <h2>Department Workload Report (SQL JOIN &amp; GROUP BY)</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Department</th>
                    <th>Support Email</th>
                    <th>Total</th>
                    <th>Open</th>
                    <th>In Progress</th>
                    <th>Resolved</th>
                    <th>Last Activity</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reportRows as $rep): ?>
                    <tr>
                        <td><strong><?= e($rep['department_name']) ?></strong></td>
                        <td><?= e($rep['desk_email']) ?></td>
                        <td><strong><?= (int) $rep['total_tickets'] ?></strong></td>
                        <td><?= (int) $rep['open_tickets'] ?></td>
                        <td><?= (int) $rep['progress_tickets'] ?></td>
                        <td><?= (int) $rep['resolved_tickets'] ?></td>
                        <td><?= $rep['last_activity'] ? e(date('d M Y H:i', strtotime($rep['last_activity']))) : 'No tickets' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="grid-2">
    <article class="card">
        <h2>Notice &amp; String Formatter Tool</h2>
        <p class="muted">Demonstrates <code>trim</code>, <code>strlen</code>, <code>str_replace</code>, <code>explode</code>, <code>implode</code>, <code>substr</code>, and <code>sort</code>.</p>

        <form method="POST" action="<?= e(BASE_URL) ?>/pages/info_hub.php">
            <input type="hidden" name="action" value="string_tool">
            <div class="form-group">
                <label for="raw_text">Sample Text</label>
                <textarea id="raw_text" name="raw_text" rows="2"><?= e($sampleInput) ?></textarea>
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label for="find_word">Find Word</label>
                    <input type="text" id="find_word" name="find_word" value="<?= e($findWord) ?>">
                </div>
                <div class="form-group">
                    <label for="replace_word">Replace With</label>
                    <input type="text" id="replace_word" name="replace_word" value="<?= e($replaceWord) ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Process Text</button>
        </form>

        <dl class="profile-list" style="margin-top: 1rem;">
            <div><dt>Length (<code>strlen</code>)</dt><dd><?= $stringResults['length'] ?> characters (<?= $stringResults['word_count'] ?> words)</dd></div>
            <div><dt>Title Case</dt><dd><?= e($stringResults['title_case']) ?></dd></div>
            <div><dt>Excerpt (<code>substr</code>)</dt><dd><?= e($stringResults['preview']) ?></dd></div>
            <div><dt>Replaced</dt><dd><?= e($stringResults['replaced']) ?></dd></div>
            <div><dt>Sorted Keywords (&ge;4 chars)</dt><dd><?= e($stringResults['sorted_tags']) ?></dd></div>
            <div><dt>Concatenation</dt><dd><?= e($stringResults['concat_note']) ?></dd></div>
        </dl>
    </article>

    <article class="card">
        <h2>File Handling: CSV Import &amp; Activity Log</h2>
        <p class="muted">Bulk-import inquiries using <code>fgetcsv()</code>. Columns: <code>Name,Email,CategoryID(1-5),Message</code>.</p>

        <form method="POST" action="<?= e(BASE_URL) ?>/pages/info_hub.php" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
            <input type="hidden" name="action" value="import_csv">
            <div class="form-group">
                <label for="csv_file">Choose CSV File</label>
                <input type="file" id="csv_file" name="csv_file" accept=".csv" required>
            </div>
            <button type="submit" class="btn btn-secondary">Import CSV Rows</button>
        </form>

        <hr class="divider">

        <h3>Recent System Log (<code>logs/activity.log</code>)</h3>
        <?php if (empty($recentLogs)): ?>
            <p class="muted">No file log entries recorded yet.</p>
        <?php else: ?>
            <ul class="log-list">
                <?php foreach ($recentLogs as $entry): ?>
                    <li><code><?= e($entry) ?></code></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </article>
</section>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>