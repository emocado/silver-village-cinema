<?php
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/auth.php';

$sentDir = __DIR__ . '/sent_emails';
$emails = [];

if (is_dir($sentDir)) {
    $files = scandir($sentDir);
    foreach ($files as $file) {
        if (substr($file, -5) === '.html') {
            $filePath = $sentDir . '/' . $file;
            $content = file_get_contents($filePath);
            $modTime = filemtime($filePath);

            // Extract booking reference from filename or content
            preg_match('/booking_(.+)\.html/', $file, $matches);
            $ref = $matches[1] ?? 'Unknown';

            $emails[] = [
                'filename' => $file,
                'ref' => $ref,
                'time' => $modTime,
                'content' => $content
            ];
        }
    }
}

// Sort newest first
usort($emails, function($a, $b) {
    return $b['time'] - $a['time'];
});

// Selected email to view
$selectedRef = $_GET['ref'] ?? ($emails[0]['ref'] ?? '');
$currentEmail = null;
foreach ($emails as $e) {
    if ($e['ref'] === $selectedRef || strpos($e['filename'], $selectedRef) !== false) {
        $currentEmail = $e;
        break;
    }
}
if (!$currentEmail && !empty($emails)) {
    $currentEmail = $emails[0];
}

$pageTitle = "Local Server Mailbox - Silver Village Cinema";
require_once __DIR__ . '/includes/header.php';
?>

<div class="section-header" style="margin-bottom: 24px;">
    <div>
        <h1 class="section-title">Local Server Mailbox Viewer</h1>
        <p style="color: var(--color-text-muted); font-size: 14px; margin-top: 4px;">
            Inspect all official booking confirmation emails, receipts, and e-tickets generated on the local web server.
        </p>
    </div>
</div>

<?php if (!empty($emails)): ?>
    <div style="display: grid; grid-template-columns: 320px 1fr; gap: 24px; align-items: start;">
        <!-- Left: Email List Sidebar -->
        <div style="background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: var(--radius-md); overflow: hidden;">
            <div style="padding: 14px 18px; background: #121620; border-bottom: 1px solid var(--color-border); font-weight: bold; font-size: 13px; color: var(--color-primary-light);">
                📥 Dispatched Mailbox (<?php echo count($emails); ?> Messages)
            </div>
            <div style="max-height: 600px; overflow-y: auto;">
                <?php foreach ($emails as $em): 
                    $isActive = ($currentEmail && $em['ref'] === $currentEmail['ref']);
                ?>
                    <a href="mailbox.php?ref=<?php echo urlencode($em['ref']); ?>" 
                       style="display: block; padding: 14px 18px; border-bottom: 1px solid rgba(255,255,255,0.05); text-decoration: none; background: <?php echo $isActive ? 'rgba(212,175,55,0.1)' : 'transparent'; ?>; border-left: <?php echo $isActive ? '4px solid var(--color-primary)' : '4px solid transparent'; ?>;">
                        <strong style="color: <?php echo $isActive ? 'var(--color-primary-light)' : '#ffffff'; ?>; font-size: 14px; display: block; font-family: monospace;">
                            ✉️ <?php echo htmlspecialchars($em['ref']); ?>
                        </strong>
                        <small style="color: var(--color-text-dim); font-size: 11px;">
                            Dispatched: <?php echo date('d M Y, h:i:s A', $em['time']); ?>
                        </small>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Right: Rendered Email Preview -->
        <div style="background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 24px;">
            <?php if ($currentEmail): ?>
                <div style="background: #121620; padding: 12px 18px; border-radius: var(--radius-sm); margin-bottom: 20px; font-size: 13px; border: 1px solid var(--color-border);">
                    <p style="margin: 2px 0;"><strong>Subject:</strong> Booking Confirmation & E-Tickets: <?php echo htmlspecialchars($currentEmail['ref']); ?></p>
                    <p style="margin: 2px 0; color: var(--color-text-muted);"><strong>From:</strong> Silver Village Cinema &lt;noreply@silvervillage.local&gt;</p>
                    <p style="margin: 2px 0; color: var(--color-text-muted);"><strong>Date:</strong> <?php echo date('r', $currentEmail['time']); ?></p>
                </div>

                <!-- Live Email Body Output -->
                <div style="background: #0a0e17; border-radius: var(--radius-md); overflow: hidden; border: 1px solid rgba(212,175,55,0.2);">
                    <?php echo $currentEmail['content']; ?>
                </div>
            <?php else: ?>
                <p style="color: var(--color-text-muted);">Select an email from the left sidebar to view.</p>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="form-card" style="text-align: center; padding: 48px 24px;">
        <span style="font-size: 48px; display: block; margin-bottom: 16px;">📭</span>
        <h2 style="color: #ffffff; margin-bottom: 8px;">No Dispatched Emails Found</h2>
        <p style="color: var(--color-text-muted); margin-bottom: 24px;">
            Complete a movie ticket booking or confirm items from your wishlist to trigger and view your local email acknowledgement receipt!
        </p>
        <a href="movies.php" class="btn btn--primary">Browse Movies & Book</a>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
