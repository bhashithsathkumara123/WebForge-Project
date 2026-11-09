<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('donor');
$donorId = $_SESSION['user_id'];

$hospitals = $pdo->query("SELECT id, name FROM users WHERE role = 'hospital' ORDER BY name")->fetchAll();
$hospitalIds = array_column($hospitals, 'id');

// Earliest date this donor may pledge (today, or the end of the 4-month gap)
$eligible = nextEligibleDate($pdo, $donorId);
$today = date('Y-m-d');
$canDonate = ($eligible === null || $eligible <= $today);
$minDate = $canDonate ? $today : $eligible;

$errors = [];
$hospitalId = post('hospital_id');   // also used to pre-select the hospital from an alert
$pledgeDate = '';

// Handle the pledge form
if (isset($_POST['send_pledge'])) {
    $pledgeDate = post('pledge_date');

    // Date must be a real date in the format Y-m-d
    $date = DateTime::createFromFormat('Y-m-d', $pledgeDate);
    $validDate = $date && $date->format('Y-m-d') === $pledgeDate;

    if (!in_array((int)$hospitalId, $hospitalIds)) {
        $errors[] = 'Please choose a hospital.';
    }
    if (!$validDate) {
        $errors[] = 'Please choose a valid date.';
    } elseif ($pledgeDate < $minDate) {
        $errors[] = 'You can only pledge from ' . $minDate . ' onwards (' . DONATION_GAP_MONTHS . '-month gap between donations).';
    }

    // Only one pending pledge at a time
    if (!$errors) {
        $stmt = $pdo->prepare("SELECT id FROM donation_pledges WHERE donor_id = ? AND status = 'Pending'");
        $stmt->execute([$donorId]);
        if ($stmt->fetch()) {
            $errors[] = 'You already have a pending pledge. Wait until the hospital responds.';
        }
    }

    // Save the pledge as Pending
    if (!$errors) {
        $stmt = $pdo->prepare('INSERT INTO donation_pledges (donor_id, hospital_id, pledge_date) VALUES (?, ?, ?)');
        $stmt->execute([$donorId, (int)$hospitalId, $pledgeDate]);
        setFlash('success', 'Thank you! Your pledge was sent to the hospital.');
        redirect('donor/dashboard.php');
    }
}

// Alerts for low blood types this donor can give to
$alerts = getAlertsForDonor($pdo, $_SESSION['blood_type']);

// All pledges of this donor (confirmed ones are the donation history)
$stmt = $pdo->prepare('SELECT p.*, u.name AS hospital_name FROM donation_pledges p
                       JOIN users u ON u.id = p.hospital_id
                       WHERE p.donor_id = ? ORDER BY p.id DESC');
$stmt->execute([$donorId]);
$pledges = $stmt->fetchAll();

$pageTitle = 'Donor Dashboard';
require __DIR__ . '/../includes/header.php';
?>
<h1>Welcome, <?= e($_SESSION['name']) ?></h1>
<div class="card">
    <p><strong>Your blood type:</strong> <?= e($_SESSION['blood_type']) ?></p>
    <p><strong>Next eligible donation date:</strong> <?= $canDonate ? 'You can donate now' : e($eligible) ?></p>
</div>

<h2>Low-stock alerts for your blood type</h2>
<?php if (!$alerts): ?>
    <p>No alerts right now. Thank you for being ready to help!</p>
<?php else: ?>
    <?php foreach ($alerts as $a): ?>
        <?php $level = stockLevel($a['units']); ?>
        <div class="banner banner-<?= strtolower($level) ?> banner-row">
            <span>
                <strong><?= $level ?>:</strong>
                <?= e($a['blood_type']) ?> needed at <?= e($a['hospital_name']) ?> (<?= (int)$a['units'] ?> units left)
            </span>
            <form method="post" action="dashboard.php">
                <input type="hidden" name="hospital_id" value="<?= (int)$a['hospital_id'] ?>">
                <button type="submit" class="btn btn-small">Pledge here</button>
            </form>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<h2>Pledge a donation</h2>
<form method="post" action="dashboard.php" class="card form-narrow">
    <input type="hidden" name="send_pledge" value="1">
    <?php if ($errors): ?>
        <ul class="error-list"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
    <?php endif; ?>
    <div class="field">
        <label for="hospital_id">Hospital</label>
        <select id="hospital_id" name="hospital_id" required>
            <option value="">-- Select --</option>
            <?php foreach ($hospitals as $h): ?>
                <option value="<?= (int)$h['id'] ?>" <?= (string)$h['id'] === (string)$hospitalId ? 'selected' : '' ?>><?= e($h['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="pledge_date">Donation date (from <?= e($minDate) ?>)</label>
        <input type="date" id="pledge_date" name="pledge_date" min="<?= e($minDate) ?>" value="<?= e($pledgeDate) ?>" required>
    </div>
    <button type="submit" class="btn">Send pledge</button>
</form>

<h2>My pledges</h2>
<?php if (!$pledges): ?>
    <p>You have no pledges yet.</p>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <tr><th>Hospital</th><th>Pledge date</th><th>Status</th></tr>
            <?php foreach ($pledges as $p): ?>
                <tr>
                    <td><?= e($p['hospital_name']) ?></td>
                    <td><?= e($p['pledge_date']) ?></td>
                    <td><?= statusBadge($p['status']) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
<?php endif; ?>

<h2>Donation history</h2>
<?php
// Confirmed pledges are real donations
$history = array_filter($pledges, function ($p) { return $p['status'] === 'Confirmed'; });
?>
<?php if (!$history): ?>
    <p>No donations recorded yet.</p>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <tr><th>Date donated</th><th>Hospital</th><th>Units</th></tr>
            <?php foreach ($history as $p): ?>
                <tr>
                    <td><?= e($p['donated_on']) ?></td>
                    <td><?= e($p['hospital_name']) ?></td>
                    <td><?= UNITS_PER_DONATION ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <p>Total donations: <?= count($history) ?></p>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
