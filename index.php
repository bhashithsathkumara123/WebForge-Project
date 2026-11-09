<?php
require_once __DIR__ . '/includes/auth.php';

// Who sees the low-stock banner? Visitors see all alerts, donors see only compatible ones.
$alerts = [];
if (!isLoggedIn()) {
    $alerts = getAlertsForDonor($pdo, null);
} elseif ($_SESSION['role'] === 'donor') {
    $alerts = getAlertsForDonor($pdo, $_SESSION['blood_type']);
}
$alerts = array_slice($alerts, 0, 5);

// Total units of each blood type across all hospitals
$totals = $pdo->query('SELECT blood_type, SUM(units) AS total FROM blood_stock GROUP BY blood_type')->fetchAll(PDO::FETCH_KEY_PAIR);

$pageTitle = 'Home';
require __DIR__ . '/includes/header.php';
?>
<section class="grid grid-3">
    <div class="card"><h3>Patients</h3><p>Search hospitals for compatible blood and send a formal request.</p></div>
    <div class="card"><h3>Donors</h3><p>See which hospitals need your blood type and pledge a donation date.</p></div>
    <div class="card"><h3>Hospitals</h3><p>Manage blood stock, approve requests and confirm donations.</p></div>
</section>

<section>
    <h2>Blood available now (all hospitals)</h2>
    <div class="chips">
        <?php foreach (bloodTypes() as $type): ?>
            <div class="chip"><strong><?= e($type) ?></strong><span><?= (int)($totals[$type] ?? 0) ?> units</span></div>
        <?php endforeach; ?>
    </div>
</section>
<?php if ($alerts): ?>
<section style="margin-top: 2rem;">
<?php foreach ($alerts as $a): ?>
    <?php $level = stockLevel($a['units']); ?>
    <div class="banner banner-<?= strtolower($level) ?>">
        <strong><?= $level ?>:</strong>
        <?= e($a['blood_type']) ?> blood is running low at <?= e($a['hospital_name']) ?>
        (<?= (int)$a['units'] ?> units left).
        <?php if (!isLoggedIn()): ?><a href="auth/register.php">Register as a donor</a><?php endif; ?>
    </div>
<?php endforeach; ?>
</section>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
